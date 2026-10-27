<?php

namespace App\Enums;

/**
 * Roles de ML Habitat. Los permisos de cada rol se configuran en
 * role_permissions (por defecto: App\Support\Permissions\RoleDefaults).
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Direction = 'direccion';
    case Sales = 'comercial';
    case Design = 'diseno';
    case Operations = 'operaciones';
    case Quality = 'calidad';
    case Finance = 'finanzas';
    case Credit = 'credito';
    case Technician = 'tecnico';
    case Customer = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Superadministrador',
            self::Direction => 'Dirección',
            self::Sales => 'Comercial',
            self::Design => 'Diseño',
            self::Operations => 'Operaciones',
            self::Quality => 'Calidad',
            self::Finance => 'Administración y finanzas',
            self::Credit => 'Crédito y cobranza',
            self::Technician => 'Técnico / instalador',
            self::Customer => 'Cliente',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Acceso total. Administra usuarios, permisos y configuración.',
            self::Direction => 'Visión completa del negocio y autorizaciones de alto nivel.',
            self::Sales => 'Prospectos, asesorías, visitas y cotizaciones.',
            self::Design => 'Mediciones, versiones de diseño y aprobación técnica.',
            self::Operations => 'Agenda, cobertura, proveedores, fabricación e instalación.',
            self::Quality => 'Revisión de evidencias, calidad, incidencias y garantías.',
            self::Finance => 'Pagos, reembolsos, conciliación y pagos a proveedores.',
            self::Credit => 'Precalificación y expedientes de pago a plazos.',
            self::Technician => 'Visitas de medición e instalaciones asignadas.',
            self::Customer => 'Portal del cliente: su proyecto, citas, pagos y documentos.',
        };
    }

    /** Roles con acceso al panel administrativo (/admin). */
    public function isStaff(): bool
    {
        return ! in_array($this, [self::Customer, self::Technician], true);
    }

    /** Página de inicio tras iniciar sesión. */
    public function homePath(): string
    {
        return match ($this) {
            self::Customer => '/portal',
            self::Technician => '/tecnico',
            default => '/admin',
        };
    }
}
