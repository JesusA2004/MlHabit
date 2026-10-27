<?php

namespace Tests\Feature\Web;

use App\Enums\SiteSectionType;
use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Models\SiteSection;
use App\Models\User;

/** Apoyo para las pruebas del módulo web (solo factories y configuración). */
final class WebFixtures
{
    /**
     * Usuario del panel con exactamente estos permisos (rol de prueba).
     *
     * @param  list<string>  $permissions
     */
    public static function staff(array $permissions, UserRole $role = UserRole::Design): User
    {
        RolePermission::sync($role, $permissions);

        return User::factory()->role($role)->create();
    }

    public static function admin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    /** Las 11 secciones de la portada con su contenido sugerido. */
    public static function sections(): void
    {
        foreach (SiteSectionType::cases() as $i => $type) {
            SiteSection::factory()->type($type)->create(['sort' => ($i + 1) * 10]);
        }
    }

    /** Ruta absoluta a un JPEG real de prueba del ancho indicado. */
    public static function jpeg(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, 176, 125, 79);
        imagefill($image, 0, 0, (int) $color);
        imagejpeg($image, $path, 80);
        imagedestroy($image);

        return $path;
    }
}
