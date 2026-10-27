<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Crea la primera cuenta de superadministrador de ML Habitat de forma
 * interactiva (o con opciones para despliegues automatizados). El correo
 * queda verificado; la contraseña nunca se guarda en texto plano.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'mlhabitat:create-admin
        {--name= : Nombre completo}
        {--email= : Correo electrónico}
        {--generate-password : Genera una contraseña segura sin preguntar}';

    protected $description = 'Crea de forma segura una cuenta de superadministrador de ML Habitat';

    public function handle(): int
    {
        $this->info('Creación segura de un superadministrador de ML Habitat.');

        $name = (string) ($this->option('name') ?: $this->ask('Nombre completo'));
        $email = Str::lower(trim((string) ($this->option('email') ?: $this->ask('Correo electrónico'))));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error("Ya existe una cuenta con el correo {$email}.");

            return self::FAILURE;
        }

        $generated = false;
        $password = $this->option('generate-password')
            ? null
            : $this->secret('Contraseña (déjala vacía para generar una segura automáticamente)');

        if (blank($password)) {
            $password = Str::password(20);
            $generated = true;
        } else {
            $confirmPassword = $this->secret('Confirma la contraseña');
            if ($password !== $confirmPassword) {
                $this->error('Las contraseñas no coinciden.');

                return self::FAILURE;
            }

            $passwordValidator = Validator::make(
                ['password' => $password],
                ['password' => ['required', 'string', 'min:12', Password::defaults()]],
            );

            if ($passwordValidator->fails()) {
                foreach ($passwordValidator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }
        }

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->newLine();
        $this->info("Superadministrador creado: {$user->email}");

        if ($generated) {
            $this->warn('Contraseña generada automáticamente (guárdala ahora, no se mostrará de nuevo):');
            $this->line((string) $password);
        }

        $this->line('Recomendación: activa la verificación en dos pasos en Cuenta → Seguridad al iniciar sesión.');

        return self::SUCCESS;
    }
}
