<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cuenta → Perfil: nombre, correo y teléfono. Si cambia el correo se pide
 * verificarlo de nuevo.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => true,
            'emailVerified' => $user->email_verified_at !== null,
            'phone' => $user->phone,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');
        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return to_route('profile.edit')->with('success', 'Perfil actualizado. Te enviamos un enlace para verificar tu nuevo correo.');
        }

        return to_route('profile.edit')->with('success', 'Perfil actualizado.');
    }
}
