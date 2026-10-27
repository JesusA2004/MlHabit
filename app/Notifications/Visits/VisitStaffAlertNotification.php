<?php

namespace App\Notifications\Visits;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso interno (visita pagada, requiere atención, crédito aplicado reembolsado…). */
class VisitStaffAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly bool $urgent = false,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->urgent ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->body)
            ->action('Abrir en el panel', url($this->url));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => VisitText::path($this->url), 'tone' => $this->urgent ? 'warning' : 'info', 'icon' => 'map-pinned'];
    }
}
