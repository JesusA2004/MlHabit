<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'mrinsight:create-admin';

    protected $description = 'Crea de forma segura la cuenta del primer administrador de MR. INSIGHT';

    public function handle(): int
    {
        $this->info('Creación segura del primer administrador de MR. INSIGHT.');

        $name = $this->ask('Nombre completo');
        $email = $this->ask('Correo electrónico');

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $this->error("Ya existe una cuenta con el correo {$email}.");

            return self::FAILURE;
        }

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

        $generated = false;
        $password = $this->secret('Contraseña (déjala vacía para generar una segura automáticamente)');

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
                ['password' => ['required', 'string', 'min:12']],
            );

            if ($passwordValidator->fails()) {
                $this->error('La contraseña debe tener al menos 12 caracteres.');

                return self::FAILURE;
            }
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->newLine();
        $this->info("Administrador creado: {$user->email}");

        if ($generated) {
            $this->warn('Contraseña generada automáticamente (guárdala ahora, no se mostrará de nuevo):');
            $this->line($password);
        }

        return self::SUCCESS;
    }
}
