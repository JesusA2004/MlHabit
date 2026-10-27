<?php

namespace App\Notifications\Operations;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;

/** Incidencia creada, asignada o actualizada (responsable y, si es visible, cliente). */
class IncidentNotification extends OperationsNotification
{
    /**
     * @param  'created'|'assigned'|'updated'  $event
     */
    public function __construct(public Incident $incident, public string $event) {}

    protected function kind(): string
    {
        return 'incident_'.$this->event;
    }

    protected function title(): string
    {
        return match ($this->event) {
            'created' => "Nueva incidencia {$this->incident->folio}",
            'assigned' => "Te asignaron la incidencia {$this->incident->folio}",
            default => "Incidencia {$this->incident->folio}: {$this->incident->status->label()}",
        };
    }

    protected function lines(): array
    {
        $lines = [$this->incident->title];

        if ($this->event === 'updated' && $this->incident->resolution) {
            $lines[] = 'Resolución: '.$this->incident->resolution;
        }

        return $lines;
    }

    protected function actionLabel(): string
    {
        return 'Ver incidencia';
    }

    protected function actionUrl(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->role === UserRole::Customer) {
            return url('/portal');
        }

        return $this->link('admin.incidencias.show', ['incident' => $this->incident->id], '/admin');
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function icon(): string
    {
        return 'shield-alert';
    }
}
