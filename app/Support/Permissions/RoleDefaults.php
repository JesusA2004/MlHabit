<?php

namespace App\Support\Permissions;

use App\Enums\Permission as P;
use App\Enums\UserRole;

/**
 * Permisos iniciales por rol (mínimo privilegio). Se siembran en
 * role_permissions y desde ahí son editables. El superadministrador tiene
 * todos los permisos de forma implícita (Gate::before).
 */
final class RoleDefaults
{
    /**
     * @return list<P>
     */
    public static function for(UserRole $role): array
    {
        return match ($role) {
            UserRole::SuperAdmin => P::cases(),
            UserRole::Direction => array_values(array_filter(
                P::cases(),
                fn (P $p) => ! in_array($p, [P::UsersManage, P::RolesManage, P::SettingsManage, P::InstallationsExecute, P::MeasurementsExecute], true),
            )),
            UserRole::Sales => [
                P::DashboardView, P::LeadsView, P::LeadsManage, P::CustomersView, P::CustomersManage,
                P::ConsultationsView, P::ConsultationsManage, P::VisitsView, P::VisitsManage,
                P::PrequalificationView, P::QuotesView, P::QuotesManage, P::ProjectsView, P::ProjectsManage,
                P::DesignsView, P::ScheduleView, P::CoverageView, P::PaymentsView,
            ],
            UserRole::Design => [
                P::DashboardView, P::CustomersView, P::VisitsView, P::ProjectsView, P::ProjectsManage,
                P::DesignsView, P::DesignsManage, P::DesignsApproveTechnical, P::QuotesView, P::QuotesManage,
                P::FabricationView, P::MeasurementsExecute,
            ],
            UserRole::Operations => [
                P::DashboardView, P::CustomersView, P::VisitsView, P::VisitsManage, P::VisitsAssign,
                P::ProjectsView, P::ProjectsManage, P::DesignsView, P::FabricationView, P::FabricationManage,
                P::InstallationsView, P::InstallationsManage, P::QualityView, P::IncidentsView, P::IncidentsManage,
                P::WarrantiesView, P::ScheduleView, P::ScheduleManage, P::CoverageView, P::CoverageManage,
                P::SuppliersView, P::SuppliersManage, P::RateCardsView, P::RateCardsManage, P::PayoutsView,
            ],
            UserRole::Quality => [
                P::DashboardView, P::CustomersView, P::ProjectsView, P::DesignsView, P::InstallationsView,
                P::QualityView, P::QualityReview, P::IncidentsView, P::IncidentsManage,
                P::WarrantiesView, P::WarrantiesManage, P::SuppliersView,
            ],
            UserRole::Finance => [
                P::DashboardView, P::CustomersView, P::ProjectsView, P::QuotesView, P::VisitsView,
                P::PaymentsView, P::PaymentsRecord, P::PaymentsRefund, P::PayoutsView, P::PayoutsAuthorize,
                P::PayoutsPay, P::OpenpayDiagnostics, P::RateCardsView, P::SuppliersView,
            ],
            UserRole::Credit => [
                P::DashboardView, P::LeadsView, P::CustomersView, P::PrequalificationView,
                P::PrequalificationReview, P::SensitiveDataView, P::PaymentsView, P::ProjectsView,
            ],
            UserRole::Technician => [P::MeasurementsExecute, P::InstallationsExecute],
            UserRole::Customer => [],
        };
    }
}
