import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

/** Muestra un toast con el formato de la marca. */
export function showToast(data: FlashToast): void {
    const notify = toast[data.type] ?? toast;

    notify(
        data.message,
        data.description ? { description: data.description } : undefined,
    );
}

/** Escucha `flash.toast` enviado por el backend y lo muestra con vue-sonner. */
export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data?.message) {
            return;
        }

        showToast(data);
    });
}
