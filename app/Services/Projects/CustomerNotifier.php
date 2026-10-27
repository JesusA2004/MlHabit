<?php

namespace App\Services\Projects;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Entrega notificaciones al cliente: a su cuenta de portal si la tiene
 * (mail + database) o solo por correo si aún no tiene cuenta. Un fallo de
 * envío nunca revierte la operación de negocio (se registra en el log).
 */
class CustomerNotifier
{
    public function notify(?Customer $customer, Notification $notification): void
    {
        if ($customer === null) {
            return;
        }

        try {
            $user = $customer->user_id ? User::query()->find($customer->user_id) : null;

            if ($user && $user->is_active) {
                $user->notify($notification);
            } elseif ($customer->email) {
                NotificationFacade::route('mail', $customer->email)->notify($notification);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Aviso interno (asesor/diseñador responsable). */
    public function notifyStaff(?User $user, Notification $notification): void
    {
        if ($user === null || ! $user->is_active) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
