<?php

namespace App\Services\Operations;

use App\Enums\RateCardStatus;
use App\Models\RateCard;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tarifarios versionados. Solo el borrador se edita; activar retira la
 * versión vigente anterior del mismo nombre; "nueva versión" clona en un
 * borrador con version+1. Vigentes y retirados son inmutables.
 */
class RateCardService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data  Validado por RateCardRequest
     */
    public function createDraft(array $data, User $actor): RateCard
    {
        return DB::transaction(function () use ($data, $actor) {
            $name = trim($data['name']);

            if (RateCard::query()->where('name', $name)->where('status', RateCardStatus::Draft->value)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['name' => 'Ya existe un borrador con este nombre. Edítalo o actívalo primero.']);
            }

            $version = (int) RateCard::query()->where('name', $name)->max('version') + 1;

            $card = RateCard::query()->create([
                'name' => $name,
                'version' => $version,
                'supplier_id' => $data['supplier_id'] ?? null,
                'status' => RateCardStatus::Draft,
                'effective_from' => $data['effective_from'] ?? null,
                'mobilization_fee' => Money::of($data['mobilization_fee'] ?? '0')->amount,
                'minimum_charge' => Money::of($data['minimum_charge'] ?? '0')->amount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->replaceItems($card, $data['items'] ?? []);
            $this->logger->log('tarifario.creado', $card, ['after' => ['name' => $name, 'version' => $version]]);

            return $card;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validado por RateCardRequest
     */
    public function updateDraft(RateCard $card, array $data): RateCard
    {
        return DB::transaction(function () use ($card, $data) {
            /** @var RateCard $locked */
            $locked = RateCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $this->ensureDraft($locked);

            $locked->fill([
                'supplier_id' => $data['supplier_id'] ?? null,
                'effective_from' => $data['effective_from'] ?? null,
                'mobilization_fee' => Money::of($data['mobilization_fee'] ?? '0')->amount,
                'minimum_charge' => Money::of($data['minimum_charge'] ?? '0')->amount,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->logger->logModelChanges('tarifario.actualizado', $locked);
            $locked->save();

            if (array_key_exists('items', $data)) {
                $this->replaceItems($locked, $data['items'] ?? []);
            }

            return $locked;
        });
    }

    public function activate(RateCard $card, User $actor): RateCard
    {
        return DB::transaction(function () use ($card, $actor) {
            /** @var RateCard $locked */
            $locked = RateCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === RateCardStatus::Active) {
                return $locked;
            }

            $this->ensureDraft($locked);

            if (! $locked->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos un concepto antes de activar el tarifario.']);
            }

            $previous = RateCard::query()
                ->where('name', $locked->name)
                ->where('status', RateCardStatus::Active->value)
                ->whereKeyNot($locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($previous as $old) {
                $old->update(['status' => RateCardStatus::Retired]);
                $this->logger->log('tarifario.retirado', $old, ['reemplazado_por' => $locked->id]);
            }

            $locked->update([
                'status' => RateCardStatus::Active,
                'activated_at' => now(),
                'effective_from' => $locked->effective_from ?? now()->toDateString(),
            ]);

            $this->logger->log('tarifario.activado', $locked, ['version' => $locked->version, 'por' => $actor->id]);

            return $locked;
        });
    }

    /** Clona cualquier versión en un borrador nuevo (version máxima + 1). */
    public function newVersion(RateCard $card, User $actor): RateCard
    {
        return DB::transaction(function () use ($card, $actor) {
            $draft = RateCard::query()
                ->where('name', $card->name)
                ->where('status', RateCardStatus::Draft->value)
                ->lockForUpdate()
                ->first();

            if ($draft !== null) {
                throw ValidationException::withMessages(['version' => "Ya hay un borrador de este tarifario (versión {$draft->version})."]);
            }

            $version = (int) RateCard::query()->where('name', $card->name)->lockForUpdate()->max('version') + 1;

            $clone = RateCard::query()->create([
                'name' => $card->name,
                'version' => $version,
                'supplier_id' => $card->supplier_id,
                'status' => RateCardStatus::Draft,
                'mobilization_fee' => $card->mobilization_fee,
                'minimum_charge' => $card->minimum_charge,
                'notes' => $card->notes,
                'created_by' => $actor->id,
            ]);

            foreach ($card->items as $item) {
                $clone->items()->create($item->only(['code', 'description', 'unit', 'unit_price', 'sort']));
            }

            $this->logger->log('tarifario.nueva_version', $clone, ['desde' => $card->id, 'version' => $version]);

            return $clone;
        });
    }

    public function deleteDraft(RateCard $card): void
    {
        $this->ensureDraft($card);
        $this->logger->log('tarifario.borrador_eliminado', $card, ['name' => $card->name, 'version' => $card->version]);
        $card->delete();
    }

    private function ensureDraft(RateCard $card): void
    {
        if ($card->status !== RateCardStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Esta versión ya no se puede modificar. Crea una nueva versión para cambiar precios.',
            ]);
        }
    }

    /**
     * @param  array<int, array{code: string, description: string, unit: string, unit_price: string}>  $items
     */
    private function replaceItems(RateCard $card, array $items): void
    {
        $codes = array_map(fn ($i) => mb_strtoupper(trim($i['code'])), $items);
        if (count($codes) !== count(array_unique($codes))) {
            throw ValidationException::withMessages(['items' => 'Cada concepto debe tener una clave única.']);
        }

        $card->items()->delete();

        foreach (array_values($items) as $index => $item) {
            $card->items()->create([
                'code' => mb_strtoupper(trim($item['code'])),
                'description' => trim($item['description']),
                'unit' => trim($item['unit']),
                'unit_price' => Money::of($item['unit_price'])->amount,
                'sort' => $index,
            ]);
        }
    }
}
