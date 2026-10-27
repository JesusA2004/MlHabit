<?php

namespace App\Notifications\Operations;

use App\Models\InstallationOrder;

/** Al cliente: la evidencia de instalación está lista para su revisión. */
class InstallationEvidenceReady extends OperationsNotification
{
    public function __construct(public InstallationOrder $order) {}

    protected function kind(): string
    {
        return 'installation_evidence_ready';
    }

    protected function title(): string
    {
        return 'Revisa cómo quedó tu instalación';
    }

    protected function lines(): array
    {
        return [
            'El equipo terminó la instalación y subió fotos de cada módulo.',
            'Revísalas y dinos si todo quedó conforme o si hay algo pendiente. Tu respuesta no limita tus garantías ni tus derechos como consumidor.',
        ];
    }

    protected function actionLabel(): string
    {
        return 'Revisar instalación';
    }

    protected function actionUrl(object $notifiable): string
    {
        return $this->link('portal.instalaciones.show', ['installationOrder' => $this->order->id], '/portal');
    }

    protected function tone(): string
    {
        return 'warning';
    }

    protected function icon(): string
    {
        return 'camera';
    }
}
