<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
use App\Services\Storage\SynologyDisk;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureStorage();
    }

    /**
     * Disco `media_synology`: SFTP con las credenciales guardadas desde
     * /admin/almacenamiento (ver SynologyDisk).
     */
    protected function configureStorage(): void
    {
        Storage::extend('synology', function (Application $app, array $config) {
            /** @var FilesystemManager $manager */
            $manager = $app->make('filesystem');

            return SynologyDisk::create($manager, $config);
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(app()->isProduction());

        if (app()->isProduction()) {
            URL::forceHttps();
            // URLs absolutas (canónicas, sitemap, Open Graph) siempre con el host de APP_URL.
            URL::forceRootUrl((string) config('app.url'));
        }

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Cada permiso es un Gate con el mismo nombre (p. ej. `leads.view`).
     * Las Policies combinan estos permisos con reglas de propiedad (IDOR).
     */
    protected function configureAuthorization(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user) => $user->hasPermission($permission));
        }
    }
}
