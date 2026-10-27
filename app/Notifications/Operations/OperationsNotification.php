<?php

namespace App\Notifications\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Base de las notificaciones de operaciones: correo + base de datos (centro
 * de notificaciones), en cola. Las subclases definen título, líneas y
 * enlace; el contenido nunca incluye datos sensibles.
 */
abstract class OperationsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    /** @return list<string> */
    abstract protected function lines(): array;

    abstract protected function actionLabel(): string;

    abstract protected function actionUrl(object $notifiable): string;

    /** Tipo corto para el centro de notificaciones. */
    abstract protected function kind(): string;

    /** Tono del aviso (success|warning|danger|info|neutral|brand). */
    protected function tone(): string
    {
        return 'info';
    }

    /** Icono (nombre lucide) para el centro de notificaciones. */
    protected function icon(): string
    {
        return 'bell';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting('Hola');

        foreach ($this->lines() as $line) {
            $mail->line($line);
        }

        return $mail->action($this->actionLabel(), $this->actionUrl($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $url = $this->actionUrl($notifiable);
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return [
            'title' => $this->title(),
            'body' => implode(' ', $this->lines()),
            'url' => $query ? "{$path}?{$query}" : $path,
            'tone' => $this->tone(),
            'icon' => $this->icon(),
            'kind' => $this->kind(),
        ];
    }

    /** Fecha legible en español sin depender del locale del servidor. */
    protected static function spanishDate(\DateTimeInterface $date, bool $withTime = false): string
    {
        $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $text = $date->format('j').' de '.$months[(int) $date->format('n') - 1].' de '.$date->format('Y');

        return $withTime ? $text.' a las '.$date->format('H:i') : $text;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function link(string $route, array $parameters, string $fallback): string
    {
        return Route::has($route) ? route($route, $parameters) : url($fallback);
    }
}
