<script setup lang="ts">
import { ChevronDown, UploadCloud, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { HTMLAttributes } from 'vue';
import FileUploadItem from '@/components/ds/FileUploadItem.vue';
import type { UploadItem } from '@/components/ds/useUploadQueue';
import { Button } from '@/components/ui/button';
import { formatEta } from '@/lib/transferRate';
import { cn } from '@/lib/utils';
import ProgressBar from './ProgressBar.vue';

/**
 * Bandeja flotante de subidas (esquina inferior), para pantallas donde los
 * archivos se suben sin una zona de carga visible (p. ej. al soltar fotos en
 * el editor visual). Resume el avance total y lista cada archivo con su
 * progreso, velocidad y tiempo restante; se puede contraer.
 */
const props = defineProps<{
    items: UploadItem[];
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
    cancel: [item: UploadItem];
    retry: [item: UploadItem];
    remove: [item: UploadItem];
    dismiss: [];
}>();

const collapsed = ref(false);

const active = computed(() =>
    props.items.filter((i) =>
        ['queued', 'uploading', 'processing'].includes(i.status),
    ),
);

const totals = computed(() => {
    const uploading = props.items.filter((i) =>
        ['queued', 'uploading'].includes(i.status),
    );
    const total = uploading.reduce((sum, i) => sum + i.file.size, 0);
    const sent = uploading.reduce((sum, i) => sum + (i.sentBytes ?? 0), 0);
    const rate = uploading.reduce((sum, i) => sum + (i.bytesPerSecond ?? 0), 0);

    return {
        percent: total > 0 ? (sent / total) * 100 : null,
        eta: rate > 0 ? Math.round((total - sent) / rate) : null,
        uploading: uploading.length,
    };
});

const heading = computed(() => {
    if (totals.value.uploading > 0) {
        return totals.value.uploading === 1
            ? 'Subiendo 1 archivo'
            : `Subiendo ${totals.value.uploading} archivos`;
    }

    if (active.value.length > 0) {
        return 'Procesando…';
    }

    return 'Subidas terminadas';
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
        leave-to-class="translate-y-2 opacity-0"
    >
        <section
            v-if="props.items.length"
            data-slot="upload-tray"
            aria-label="Subidas"
            :class="
                cn(
                    'fixed right-4 bottom-4 z-[70] w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-xl border bg-card text-card-foreground shadow-lg',
                    props.class,
                )
            "
        >
            <header class="flex items-center gap-3 border-b px-4 py-3">
                <span
                    class="grid size-8 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary"
                    aria-hidden="true"
                >
                    <UploadCloud class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold" aria-live="polite">
                        {{ heading }}
                    </p>
                    <p
                        v-if="totals.uploading > 0"
                        class="text-xs text-muted-foreground tabular-nums"
                    >
                        {{ Math.round(totals.percent ?? 0) }}%
                        <template v-if="totals.eta !== null">
                            · {{ formatEta(totals.eta) }}</template
                        >
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    :aria-expanded="!collapsed"
                    :aria-label="collapsed ? 'Mostrar archivos' : 'Contraer'"
                    @click="collapsed = !collapsed"
                >
                    <ChevronDown
                        :class="
                            cn(
                                'size-4 transition-transform motion-reduce:transition-none',
                                collapsed && 'rotate-180',
                            )
                        "
                    />
                </Button>
                <Button
                    v-if="active.length === 0"
                    variant="ghost"
                    size="icon"
                    class="size-8"
                    aria-label="Cerrar"
                    @click="emit('dismiss')"
                >
                    <X class="size-4" />
                </Button>
            </header>
            <ProgressBar
                v-if="totals.uploading > 0"
                :value="totals.percent"
                size="xs"
                class="rounded-none"
                label="Avance total"
            />
            <ul
                v-show="!collapsed"
                class="flex max-h-72 flex-col gap-2 overflow-y-auto p-3"
            >
                <FileUploadItem
                    v-for="item in props.items"
                    :key="item.id"
                    :item="item"
                    @cancel="emit('cancel', item)"
                    @retry="emit('retry', item)"
                    @remove="emit('remove', item)"
                />
            </ul>
        </section>
    </Transition>
</template>
