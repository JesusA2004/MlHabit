<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->hasSession()) {
            foreach (['success', 'error'] as $type) {
                $message = $request->session()->get($type);
                if (is_string($message) && $message !== '') {
                    Inertia::flash('toast', ['type' => $type, 'message' => $message]);
                    break;
                }
            }
        }

        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                    'permissions' => $user->permissions(),
                    'emailVerified' => $user->email_verified_at !== null,
                    'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
