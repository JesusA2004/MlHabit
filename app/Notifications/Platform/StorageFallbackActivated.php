<?php

namespace App\Notifications\Platform;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso (una sola vez por corte) de que el NAS no respondió y los archivos
 * nuevos se están guardando en el servidor. Se suben solos al NAS en cuanto
 * vuelva (media:sync-pending). Nunca incluye credenciales ni rutas.
 */
class StorageFallbackActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $providerLabel = 'Synology') {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('El NAS no responde: los archivos se guardan en el servidor')
            ->greeting('Hola')
            ->line("No se pudo conectar con «{$this->providerLabel}». Para no perder nada, las fotos y videos nuevos se están guardando en el servidor.")
            ->line('En cuanto el NAS vuelva a responder, la plataforma los pasará automáticamente (revisa cada 10 minutos).')
            ->action('Ver almacenamiento', route('admin.almacenamiento.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'El NAS no responde',
            'body' => "Los archivos nuevos se guardan en el servidor y se pasarán a «{$this->providerLabel}» en cuanto conecte.",
            'url' => '/admin/almacenamiento',
            'tone' => 'warning',
            'icon' => 'server',
            'kind' => 'almacenamiento',
        ];
    }
}
