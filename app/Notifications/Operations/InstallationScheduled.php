<?php

namespace App\Notifications\Operations;

use App\Models\InstallationOrder;

/** Al cliente: su instalación tiene fecha. */
class InstallationScheduled extends OperationsNotification
{
    public function __construct(public InstallationOrder $order) {}

    protected function kind(): string
    {
        return 'installation_scheduled';
    }

    protected function title(): string
    {
        return 'Tu instalación ya tiene fecha';
    }

    protected function lines(): array
    {
        $start = $this->order->scheduled_start
            ? self::spanishDate($this->order->scheduled_start->setTimezone('America/Mexico_City'), withTime: true)
            : 'la fecha acordada';

        return [
            "Programamos la instalación de tu proyecto para el {$start}.",
            'El día de la instalación te pedimos que el espacio esté despejado y que haya alguien mayor de edad para recibir al equipo.',
        ];
    }

    protected function actionLabel(): string
    {
        return 'Ver mi proyecto';
    }

    protected function actionUrl(object $notifiable): string
    {
        return url('/portal');
    }

    protected function tone(): string
    {
        return 'brand';
    }

    protected function icon(): string
    {
        return 'calendar-check';
    }
}
