<?php

namespace App\Notifications\Platform;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Invitación a una cuenta del equipo. El enlace es una URL firmada que
 * vence en EXPIRES_HOURS; al abrirla se genera un token de restablecimiento
 * fresco y la persona crea su contraseña (ver InvitationController).
 */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public const EXPIRES_HOURS = 72;

    public function __construct(public readonly ?string $invitedBy = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'invitacion.aceptar',
            Carbon::now()->addHours(self::EXPIRES_HOURS),
            ['user' => $notifiable->getKey()],
        );

        $mail = (new MailMessage)
            ->subject('Te damos la bienvenida a ML Habitat')
            ->greeting('¡Hola, '.$notifiable->name.'!')
            ->line($this->invitedBy
                ? "{$this->invitedBy} te invitó a la plataforma de ML Habitat con el rol de {$notifiable->role->label()}."
                : "Te invitamos a la plataforma de ML Habitat con el rol de {$notifiable->role->label()}.")
            ->line('Para entrar, crea tu contraseña con el siguiente botón.')
            ->action('Crear mi contraseña', $url)
            ->line('El enlace vence en '.self::EXPIRES_HOURS.' horas. Si vence, pide a tu administrador que te reenvíe la invitación.')
            ->salutation("Saludos,\nEl equipo de ML Habitat");

        return $mail;
    }
}
