<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centro de notificaciones (canal `database` de las Notification de cada
 * módulo). JSON para el menú de la cabecera (sondeo cada 60 s) y página
 * completa en /notificaciones.
 *
 * Contrato de `data` recomendado para los módulos:
 *   ['title' => string, 'body' => ?string, 'url' => ?string (ruta interna),
 *    'tone' => ?'success|warning|danger|info|neutral|brand', 'icon' => ?string]
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            $items = $user->notifications()->latest()->limit(15)->get();

            return response()->json([
                'unread' => $user->unreadNotifications()->count(),
                'items' => $items->map(fn (DatabaseNotification $n) => self::present($n))->values(),
            ]);
        }

        $filter = $request->query('filtro') === 'no-leidas' ? 'no-leidas' : 'todas';

        $notifications = ($filter === 'no-leidas' ? $user->unreadNotifications() : $user->notifications())
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('notificaciones/Index', [
            'notifications' => $notifications->through(fn (DatabaseNotification $n) => self::present($n)),
            'unread' => $user->unreadNotifications()->count(),
            'filter' => $filter,
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Solo notificaciones propias (IDOR): se busca dentro de la relación.
        $item = $user->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread' => $user->unreadNotifications()->count()]);
        }

        return back();
    }

    public function markAllRead(Request $request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['unread' => 0]);
        }

        return back()->with('success', 'Todas las notificaciones quedaron como leídas.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $url = isset($data['url']) && is_string($data['url']) ? $data['url'] : null;

        // Solo rutas internas: evita redirecciones abiertas desde datos guardados.
        if ($url !== null && ! str_starts_with($url, '/')) {
            $appUrl = rtrim((string) config('app.url'), '/');
            $url = str_starts_with($url, $appUrl.'/') ? Str::after($url, $appUrl) : null;
        }
        if ($url !== null && str_starts_with($url, '//')) {
            $url = null;
        }

        $tone = $data['tone'] ?? 'neutral';

        return [
            'id' => $notification->id,
            'title' => Str::limit((string) ($data['title'] ?? $data['message'] ?? 'Notificación'), 160),
            'body' => isset($data['body']) && is_string($data['body']) ? Str::limit($data['body'], 400) : null,
            'url' => $url,
            'tone' => in_array($tone, ['success', 'warning', 'danger', 'info', 'neutral', 'brand'], true) ? $tone : 'neutral',
            'icon' => isset($data['icon']) && is_string($data['icon']) ? $data['icon'] : null,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
        ];
    }
}
