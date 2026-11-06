<?php

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\Web\Navigation;
use App\Services\Web\PublicSite;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Menú del sitio (Sitio web → Menú): enlaces de la cabecera, submenús y
 * botón destacado. Cambia lo que ve todo visitante, por eso requiere
 * `content.manage` y `content.publish`.
 */
class NavigationController extends Controller
{
    public function update(Request $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('content.manage');
        Gate::authorize('content.publish');

        $request->validate([
            'items' => ['present', 'array'],
            'cta' => ['required', 'array'],
        ]);

        $before = Navigation::stored();
        $menu = Navigation::validate($request->only(['items', 'cta']));

        Settings::put('navigation', $menu);
        PublicSite::flush();

        $activity->log('sitio.menu_actualizado', null, ['before' => $before, 'after' => $menu]);

        return back()->with('success', 'Menú guardado. Ya se ve en el sitio.');
    }

    public function reset(ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('content.manage');
        Gate::authorize('content.publish');

        Settings::put('navigation', ['items' => null, 'cta' => null]);
        PublicSite::flush();

        $activity->log('sitio.menu_restablecido', null, []);

        return back()->with('success', 'Restableciste el menú predeterminado.');
    }
}
