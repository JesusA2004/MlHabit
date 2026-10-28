<?php

namespace App\Http\Controllers\Portal\Concerns;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Throwable;

/**
 * Todo el portal parte del cliente del usuario autenticado: ninguna
 * consulta usa IDs que vengan del navegador sin filtrarse por él (IDOR).
 */
trait ResolvesPortalCustomer
{
    protected const SESSION_PROJECT = 'portal.project';

    protected function customer(Request $request): ?Customer
    {
        /** @var User $user */
        $user = $request->user();

        return Customer::query()->where('user_id', $user->id)->first();
    }

    /**
     * IDs de todas las fichas de cliente de la persona: la ligada a su
     * cuenta y, si su correo está verificado, las fichas SIN cuenta con ese
     * mismo correo (solicitudes que hizo en /solicitar antes de tener
     * acceso). Una ficha ligada a otra cuenta nunca se incluye.
     *
     * @return list<int>
     */
    protected function customerIds(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $email = $user->hasVerifiedEmail() ? mb_strtolower(trim((string) $user->email)) : '';

        return array_values(Customer::query()
            ->where(function ($q) use ($user, $email) {
                $q->where('user_id', $user->id);
                if ($email !== '') {
                    $q->orWhere(fn ($q) => $q->whereNull('user_id')->whereRaw('LOWER(email) = ?', [$email]));
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /** @return Collection<int, Project> */
    protected function projects(?Customer $customer): Collection
    {
        if ($customer === null) {
            return new Collection;
        }

        return Project::query()
            ->where('customer_id', $customer->id)
            ->with(['category:id,name', 'advisor:id,name,email,phone'])
            ->latest('created_at')
            ->get();
    }

    /**
     * Proyecto elegido en el selector (o el más reciente activo).
     *
     * @param  Collection<int, Project>  $projects
     */
    protected function currentProject(Request $request, Collection $projects): ?Project
    {
        $selected = $request->session()->get(self::SESSION_PROJECT);
        foreach ($projects as $project) {
            if ($selected !== null && $project->uuid === $selected) {
                return $project;
            }
        }
        foreach ($projects as $project) {
            if (! in_array($project->status->value, ['completed', 'cancelled'], true)) {
                return $project;
            }
        }

        return $projects->first();
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{uuid: string, folio: string, name: string, status: string}>
     */
    protected function projectOptions(Collection $projects): array
    {
        return $projects->map(fn (Project $p) => [
            'uuid' => $p->uuid,
            'folio' => $p->folio,
            'name' => $p->name,
            'status' => $p->status->label(),
        ])->values()->all();
    }

    /**
     * Lectura tolerante de modelos de otros módulos (si aún no existen,
     * la sección se muestra vacía).
     *
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    protected function attempt(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }
    }
}
