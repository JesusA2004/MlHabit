import { createInertiaApp } from '@inertiajs/vue3';
import AccountLayout from '@/layouts/AccountLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import TechnicianLayout from '@/layouts/TechnicianLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'ML Habitat';

void createInertiaApp({
    // "Página | ML Habitat". Las páginas públicas ya traen el título completo
    // (`seo.fullTitle`, ≤ 60 caracteres) y no se le vuelve a añadir la marca.
    title: (title) =>
        !title || title === appName
            ? appName
            : title.endsWith(`| ${appName}`)
              ? title
              : `${title} | ${appName}`,
    // Etiquetas SEO/Open Graph que el servidor ya imprimió en el HTML
    // (`seo.head`): Inertia las conserva y actualiza al navegar.
    serverHead: (page) => {
        const seo = page.props.seo as { head?: unknown } | undefined;

        return Array.isArray(seo?.head) ? (seo.head as string[]) : [];
    },
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

// Toasts enviados por el backend (`flash.toast`).
initializeFlashToast();
