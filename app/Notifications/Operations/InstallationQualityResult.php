<?php

namespace App\Notifications\Operations;

use App\Enums\UserRole;
use App\Models\InstallationOrder;
use App\Models\User;

/** Al instalador líder y al responsable de operación: dictamen de calidad. */
class InstallationQualityResult extends OperationsNotification
{
    public function __construct(public InstallationOrder $order, public bool $approved, public ?string $observations = null) {}

    protected function kind(): string
    {
        return $this->approved ? 'quality_approved' : 'quality_requires_fixes';
    }

    protected function title(): string
    {
        return $this->approved
            ? "Calidad aprobó la instalación {$this->order->folio}"
            : "La instalación {$this->order->folio} requiere correcciones";
    }

    protected function lines(): array
    {
        $lines = [$this->approved
            ? 'La revisión de calidad quedó aprobada.'
            : 'Calidad encontró detalles por corregir. Revisa las partidas reabiertas y vuelve a enviar evidencia.'];

        if ($this->observations) {
            $lines[] = 'Observaciones: '.$this->observations;
        }

        return $lines;
    }

    protected function actionLabel(): string
    {
        return 'Ver orden';
    }

    protected function actionUrl(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->role === UserRole::Technician) {
            return $this->link('tecnico.instalaciones.show', ['installationOrder' => $this->order->id], '/tecnico');
        }

        return $this->link('admin.instalaciones.show', ['installationOrder' => $this->order->id], '/admin');
    }

    protected function tone(): string
    {
        return $this->approved ? 'success' : 'danger';
    }

    protected function icon(): string
    {
        return 'clipboard-check';
    }
}
