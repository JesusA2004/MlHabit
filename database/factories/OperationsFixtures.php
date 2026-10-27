<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Datos mínimos de otros módulos (cliente, proyecto, versión de diseño)
 * que necesitan las factories y pruebas de operaciones, sin depender de
 * factories ajenas que pueden no existir todavía.
 */
final class OperationsFixtures
{
    public static function client(): User
    {
        // fresh(): carga todas las columnas (modo estricto de Eloquent).
        return User::factory()->role(UserRole::Customer)->create()->fresh();
    }

    public static function project(ProjectStatus $status = ProjectStatus::Authorized, ?User $client = null): Project
    {
        $client ??= self::client();

        $customer = Customer::query()->create([
            'user_id' => $client->id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => $client->email,
        ]);

        return Project::query()->create([
            'folio' => 'MLH-P-TEST-'.Str::upper(Str::random(8)),
            'customer_id' => $customer->id,
            'name' => 'Cocina '.fake()->lastName(),
            'status' => $status,
        ]);
    }

    /** Inserta una versión de diseño (tabla de projects) y devuelve su id. */
    public static function designVersion(Project $project, bool $technicallyApproved = true): int
    {
        $number = (int) DB::table('design_versions')->where('project_id', $project->id)->max('number') + 1;

        return (int) DB::table('design_versions')->insertGetId([
            'project_id' => $project->id,
            'number' => $number,
            'title' => "Versión {$number}",
            'status' => $technicallyApproved ? 'approved' : 'draft',
            'technical_approved_at' => $technicallyApproved ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
