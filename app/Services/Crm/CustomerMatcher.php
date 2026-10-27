<?php

namespace App\Services\Crm;

use App\Models\Customer;
use Illuminate\Support\Str;

/**
 * Deduplicación de clientes por correo o teléfono. Nunca sobrescribe datos
 * existentes: completa solo campos vacíos y deja una nota con lo que llegó
 * distinto para que el equipo lo revise.
 */
class CustomerMatcher
{
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '52')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 13 && str_starts_with($digits, '521')) {
            $digits = substr($digits, 3);
        }

        return $digits === '' ? null : $digits;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    public function find(?string $email, ?string $phone): ?Customer
    {
        $email = self::normalizeEmail($email);
        $phone = self::normalizePhone($phone);

        if ($email === null && $phone === null) {
            return null;
        }

        return Customer::query()
            ->where(function ($q) use ($email, $phone) {
                if ($email !== null) {
                    $q->orWhere('email', $email);
                }
                if ($phone !== null) {
                    $q->orWhere('phone', $phone);
                }
            })
            // Preferir coincidencia por correo, luego la más reciente.
            ->orderByRaw('CASE WHEN email = ? THEN 0 ELSE 1 END', [$email ?? ''])
            ->latest('id')
            ->first();
    }

    /**
     * Busca o crea el cliente. Devuelve [cliente, ¿existía?].
     *
     * @param  array{first_name: string, last_name?: ?string, email?: ?string, phone?: ?string, preferred_contact?: ?string}  $data
     * @return array{0: Customer, 1: bool}
     */
    public function findOrCreate(array $data, ?string $context = null): array
    {
        $email = self::normalizeEmail($data['email'] ?? null);
        $phone = self::normalizePhone($data['phone'] ?? null);
        $existing = $this->find($email, $phone);

        if ($existing === null) {
            $customer = Customer::query()->create([
                'first_name' => trim($data['first_name']),
                'last_name' => isset($data['last_name']) ? trim((string) $data['last_name']) ?: null : null,
                'email' => $email,
                'phone' => $phone,
                'preferred_contact' => $data['preferred_contact'] ?? null,
                'privacy_accepted_at' => now(),
            ]);

            return [$customer, false];
        }

        $differences = [];
        $fill = [];
        $incoming = [
            'first_name' => trim($data['first_name']),
            'last_name' => isset($data['last_name']) ? trim((string) $data['last_name']) : null,
            'email' => $email,
            'phone' => $phone,
            'preferred_contact' => $data['preferred_contact'] ?? null,
        ];
        $labels = ['first_name' => 'nombre', 'last_name' => 'apellidos', 'email' => 'correo', 'phone' => 'teléfono', 'preferred_contact' => 'contacto preferido'];

        foreach ($incoming as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $current = $existing->getAttribute($field);
            if ($current === null || $current === '') {
                $fill[$field] = $value;
            } elseif (Str::lower((string) $current) !== Str::lower((string) $value)) {
                $differences[] = "{$labels[$field]}: {$value}";
            }
        }

        if ($existing->privacy_accepted_at === null) {
            $fill['privacy_accepted_at'] = now();
        }

        if ($differences !== []) {
            $line = sprintf('[%s]%s Datos recibidos distintos a los registrados — %s.', now()->format('Y-m-d H:i'), $context ? " {$context}:" : '', implode('; ', $differences));
            $fill['notes'] = trim(($existing->notes ? $existing->notes."\n" : '').$line);
        }

        if ($fill !== []) {
            $existing->update($fill);
        }

        return [$existing, true];
    }
}
