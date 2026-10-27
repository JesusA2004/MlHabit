<?php

namespace App\Enums;

/**
 * Catálogo de permisos. Cada caso se registra como Gate (AppServiceProvider)
 * y se comparte con el frontend para filtrar navegación y acciones. La
 * autorización real siempre ocurre en backend (Policies / Gates).
 */
enum Permission: string
{
    case DashboardView = 'dashboard.view';

    case LeadsView = 'leads.view';
    case LeadsManage = 'leads.manage';
    case LeadsAssign = 'leads.assign';
    case CustomersView = 'customers.view';
    case CustomersManage = 'customers.manage';
    case ConsultationsView = 'consultations.view';
    case ConsultationsManage = 'consultations.manage';
    case VisitsView = 'visits.view';
    case VisitsManage = 'visits.manage';
    case VisitsAssign = 'visits.assign';
    case PrequalificationView = 'prequalification.view';
    case PrequalificationReview = 'prequalification.review';
    case QuotesView = 'quotes.view';
    case QuotesManage = 'quotes.manage';
    case QuotesAuthorizeDiscount = 'quotes.authorize_discount';

    case ProjectsView = 'projects.view';
    case ProjectsManage = 'projects.manage';
    case DesignsView = 'designs.view';
    case DesignsManage = 'designs.manage';
    case DesignsApproveTechnical = 'designs.approve_technical';
    case FabricationView = 'fabrication.view';
    case FabricationManage = 'fabrication.manage';
    case InstallationsView = 'installations.view';
    case InstallationsManage = 'installations.manage';
    case InstallationsExecute = 'installations.execute';
    case MeasurementsExecute = 'measurements.execute';
    case QualityView = 'quality.view';
    case QualityReview = 'quality.review';
    case IncidentsView = 'incidents.view';
    case IncidentsManage = 'incidents.manage';
    case WarrantiesView = 'warranties.view';
    case WarrantiesManage = 'warranties.manage';

    case ScheduleView = 'schedule.view';
    case ScheduleManage = 'schedule.manage';
    case CoverageView = 'coverage.view';
    case CoverageManage = 'coverage.manage';
    case SuppliersView = 'suppliers.view';
    case SuppliersManage = 'suppliers.manage';
    case RateCardsView = 'rate_cards.view';
    case RateCardsManage = 'rate_cards.manage';

    case PaymentsView = 'payments.view';
    case PaymentsRecord = 'payments.record';
    case PaymentsRefund = 'payments.refund';
    case PayoutsView = 'payouts.view';
    case PayoutsAuthorize = 'payouts.authorize';
    case PayoutsPay = 'payouts.pay';
    case OpenpayDiagnostics = 'openpay.diagnostics';

    case ContentManage = 'content.manage';
    case ContentPublish = 'content.publish';
    case CatalogManage = 'catalog.manage';
    case MediaManage = 'media.manage';

    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case ActivityView = 'activity.view';
    case SettingsManage = 'settings.manage';
    case SensitiveDataView = 'sensitive.view';

    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Ver tablero',
            self::LeadsView => 'Ver prospectos',
            self::LeadsManage => 'Gestionar prospectos',
            self::LeadsAssign => 'Asignar prospectos',
            self::CustomersView => 'Ver clientes',
            self::CustomersManage => 'Gestionar clientes',
            self::ConsultationsView => 'Ver asesorías',
            self::ConsultationsManage => 'Gestionar asesorías',
            self::VisitsView => 'Ver visitas',
            self::VisitsManage => 'Gestionar visitas',
            self::VisitsAssign => 'Asignar técnicos a visitas',
            self::PrequalificationView => 'Ver precalificaciones',
            self::PrequalificationReview => 'Dictaminar precalificaciones',
            self::QuotesView => 'Ver cotizaciones',
            self::QuotesManage => 'Crear y editar cotizaciones',
            self::QuotesAuthorizeDiscount => 'Autorizar descuentos',
            self::ProjectsView => 'Ver proyectos',
            self::ProjectsManage => 'Gestionar proyectos',
            self::DesignsView => 'Ver diseños',
            self::DesignsManage => 'Crear versiones de diseño',
            self::DesignsApproveTechnical => 'Aprobar diseño técnico',
            self::FabricationView => 'Ver fabricación',
            self::FabricationManage => 'Gestionar órdenes de fabricación',
            self::InstallationsView => 'Ver instalaciones',
            self::InstallationsManage => 'Gestionar instalaciones',
            self::InstallationsExecute => 'Ejecutar instalaciones asignadas',
            self::MeasurementsExecute => 'Registrar mediciones',
            self::QualityView => 'Ver calidad',
            self::QualityReview => 'Revisar y dictaminar calidad',
            self::IncidentsView => 'Ver incidencias',
            self::IncidentsManage => 'Gestionar incidencias',
            self::WarrantiesView => 'Ver garantías',
            self::WarrantiesManage => 'Gestionar garantías',
            self::ScheduleView => 'Ver agenda',
            self::ScheduleManage => 'Gestionar disponibilidad',
            self::CoverageView => 'Ver cobertura',
            self::CoverageManage => 'Gestionar cobertura',
            self::SuppliersView => 'Ver proveedores y técnicos',
            self::SuppliersManage => 'Gestionar proveedores y técnicos',
            self::RateCardsView => 'Ver tarifarios',
            self::RateCardsManage => 'Gestionar tarifarios',
            self::PaymentsView => 'Ver pagos',
            self::PaymentsRecord => 'Registrar pagos manuales',
            self::PaymentsRefund => 'Reembolsar pagos',
            self::PayoutsView => 'Ver pagos a proveedores',
            self::PayoutsAuthorize => 'Autorizar pagos a proveedores',
            self::PayoutsPay => 'Registrar pagos a proveedores',
            self::OpenpayDiagnostics => 'Diagnóstico de Openpay',
            self::ContentManage => 'Editar contenido del sitio',
            self::ContentPublish => 'Publicar contenido',
            self::CatalogManage => 'Gestionar catálogo',
            self::MediaManage => 'Gestionar multimedia',
            self::UsersManage => 'Gestionar usuarios',
            self::RolesManage => 'Gestionar roles y permisos',
            self::ActivityView => 'Ver bitácora de actividad',
            self::SettingsManage => 'Gestionar configuración',
            self::SensitiveDataView => 'Ver datos sensibles',
        };
    }

    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'dashboard' => 'General',
            'leads', 'customers', 'consultations', 'visits', 'prequalification', 'quotes' => 'Comercial',
            'projects', 'designs', 'fabrication', 'installations', 'measurements', 'quality', 'incidents', 'warranties' => 'Proyectos',
            'schedule', 'coverage', 'suppliers', 'rate_cards' => 'Operación',
            'payments', 'payouts', 'openpay' => 'Finanzas',
            'content', 'catalog', 'media' => 'Contenido',
            default => 'Administración',
        };
    }
}
