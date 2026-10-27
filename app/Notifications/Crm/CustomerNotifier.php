<?php

namespace App\Notifications\Crm;

use App\Models\Customer;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Entrega notificaciones a un cliente: a su cuenta del portal si la tiene
 * (correo + centro de notificaciones) o solo por correo si es prospecto.
 * Nunca interrumpe el flujo de negocio si el envío falla.
 */
final class CustomerNotifier
{
    public static function send(Customer $customer, Notification $notification): void
    {
        try {
            if ($customer->user_id && $customer->user) {
                $customer->user->notify($notification);

                return;
            }

            if ($customer->email) {
                NotificationFacade::route('mail', $customer->email)->notify($notification);
            }
        } catch (Throwable $e) {
            Log::warning('crm.customer_notification_failed', ['customer' => $customer->id, 'error' => $e->getMessage()]);
        }
    }
}
