import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme, updateTheme } from '@/composables/useAppearance';
import AccountLayout from '@/layouts/AccountLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import TechnicianLayout from '@/layouts/TechnicianLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import type { Appearance } from '@/types';

const appName = import.meta.env.VITE_APP_NAME || 'ML Habitat';

/** El sitio público y el portal de clientes siempre se ven en claro. */
const isLightOnly = (component: string) =>
    component.startsWith('public/') || component.startsWith('portal/');

function applyThemeFor(component: string): void {
    if (isLightOnly(component)) {
        document.documentElement.classList.remove('dark');

        return;
    }

    let stored: Appearance | null = null;

    try {
        stored = localStorage.getItem('appearance') as Appearance | null;
    } catch {
        stored = null;
    }

    updateTheme(stored ?? 'system');
}

void createInertiaApp({
    title: (title) =>
        title && title !== appName ? `${title} · ${appName}` : appName,
    layout: (name) => {
        switch (true) {
            case name.startsWith('errors/'):
                return undefined;
            case name.startsWith('public/'):
                return PublicLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('portal/'):
                return PortalLayout;
            case name.startsWith('tecnico/'):
                return TechnicianLayout;
            case name.startsWith('notificaciones/'):
                return AccountLayout;
            case name.startsWith('settings/'):
                return [AccountLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#2F5A4C',
        showSpinner: false,
    },
});

/** Componente de la primera página (Inertia lo serializa en el HTML inicial). */
function initialComponent(): string {
    try {
        const script = document.querySelector('script[data-page]');
        const raw =
            script?.textContent ||
            document.getElementById('app')?.dataset.page ||
            '{}';

        return (JSON.parse(raw) as { component?: string }).component ?? '';
    } catch {
        return '';
    }
}

// Modo claro/oscuro desde la preferencia guardada (cookie + localStorage).
initializeTheme();

// Fuerza el modo claro en sitio público y portal, también al navegar.
if (typeof window !== 'undefined') {
    applyThemeFor(initialComponent());

    router.on('navigate', (event) => {
        applyThemeFor(event.detail.page.component);
    });
}

// Toasts enviados por el backend (`flash.toast`).
initializeFlashToast();
