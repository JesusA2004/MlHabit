<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Bell, BellOff, CheckCheck } from '@lucide/vue';
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { computed, onMounted, ref, watch } from 'vue';
import { toneClasses } from '@/components/ds/tones';
import type { Tone } from '@/components/ds/tones';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate, formatRelative } from '@/lib/format';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    leida,
    leidas,
} from '@/routes/notificaciones';

/**
 * Menú de notificaciones de la cabecera. Consulta GET /notificaciones
 * (JSON) al montarse y cada 60 s mientras la pestaña está visible.
 */
export type NotificationItem = {
    id: string;
    title: string;
    body: string | null;
    url: string | null;
    tone: Tone;
    icon: string | null;
    readAt: string | null;
    createdAt: string | null;
};

const POLL_MS = 60_000;

const open = ref(false);
const unread = ref(0);
const items = ref<NotificationItem[]>([]);
const loading = ref(false);
const loaded = ref(false);
const failed = ref(false);

const visibility = useDocumentVisibility();

async function refresh() {
    if (loading.value) {
        return;
    }

    loading.value = true;

    try {
        const response = await apiFetch(notificationsIndex().url);

        if (!response.ok) {
            failed.value = true;

            return;
        }

        const data = (await response.json()) as {
            unread: number;
            items: NotificationItem[];
        };
        unread.value = data.unread;
        items.value = data.items;
        failed.value = false;
        loaded.value = true;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

const { pause, resume } = useIntervalFn(refresh, POLL_MS, { immediate: false });

onMounted(() => {
    void refresh();

    if (visibility.value === 'visible') {
        resume();
    }
});

watch(visibility, (state) => {
    if (state === 'visible') {
        void refresh();
        resume();
    } else {
        pause();
    }
});

watch(open, (value) => {
    if (value) {
        void refresh();
    }
});

const badge = computed(() =>
    unread.value > 99 ? '99+' : String(unread.value),
);
const triggerLabel = computed(() =>
    unread.value === 0
        ? 'Notificaciones, sin pendientes'
        : `Notificaciones, ${unread.value} sin leer`,
);

async function select(item: NotificationItem) {
    if (!item.readAt) {
        item.readAt = new Date().toISOString();
        unread.value = Math.max(0, unread.value - 1);

        try {
            const response = await apiFetch(leida(item.id).url, {
                method: 'PATCH',
            });

            if (response.ok) {
                const data = (await response.json()) as { unread: number };
                unread.value = data.unread;
            }
        } catch {
            // Si falla, el siguiente sondeo corrige el conteo.
        }
    }

    if (item.url) {
        open.value = false;
        router.visit(item.url);
    }
}

async function markAll() {
    const previous = items.value.map((i) => i.readAt);
    const now = new Date().toISOString();
    items.value.forEach((i) => (i.readAt ??= now));
    unread.value = 0;

    try {
        const response = await apiFetch(leidas().url, { method: 'POST' });

        if (!response.ok) {
            throw new Error();
        }
    } catch {
        items.value.forEach((i, index) => (i.readAt = previous[index]));
        void refresh();
    }
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="triggerLabel"
            >
                <Bell class="size-5" />
                <span
                    v-if="unread > 0"
                    aria-hidden="true"
                    class="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-none font-semibold text-danger-foreground tabular-nums"
                >
                    {{ badge }}
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent align="end" class="w-[min(24rem,calc(100vw-2rem))] p-0">
            <header
                class="flex items-center justify-between gap-2 border-b px-4 py-3"
            >
                <p class="text-sm font-semibold">Notificaciones</p>
                <Button
                    v-if="unread > 0"
                    variant="ghost"
                    size="sm"
                    class="h-8 px-2 text-xs"
                    @click="markAll"
                >
                    <CheckCheck class="size-4" />
                    Marcar todas como leídas
                </Button>
            </header>

            <div class="max-h-[60vh] overflow-y-auto" aria-live="polite">
                <p
                    v-if="!loaded && loading"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    Cargando…
                </p>

                <p
                    v-else-if="!loaded && failed"
                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                >
                    No pudimos cargar tus notificaciones.
                </p>

                <div
                    v-else-if="items.length === 0"
                    class="flex flex-col items-center gap-2 px-4 py-10 text-center"
                >
                    <BellOff class="size-6 text-muted-foreground" />
                    <p class="text-sm font-medium">
                        No tienes notificaciones nuevas
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Aquí verás avisos de citas, pagos y avances de
                        proyectos.
                    </p>
                </div>

                <ul v-else class="divide-y">
                    <li v-for="item in items" :key="item.id">
                        <button
                            type="button"
                            :class="
                                cn(
                                    'flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-muted/60 focus-visible:bg-muted/60 focus-visible:outline-none',
                                    !item.readAt && 'bg-primary-soft/40',
                                )
                            "
                            @click="select(item)"
                        >
                            <span
                                aria-hidden="true"
                                :class="
                                    cn(
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        item.readAt
                                            ? 'bg-transparent'
                                            : toneClasses[item.tone].dot,
                                    )
                                "
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    :class="
                                        cn(
                                            'block text-sm',
                                            item.readAt
                                                ? 'text-muted-foreground'
                                                : 'font-medium text-foreground',
                                        )
                                    "
                                >
                                    {{ item.title }}
                                </span>
                                <span
                                    v-if="item.body"
                                    class="mt-0.5 line-clamp-2 block text-xs text-muted-foreground"
                                    >{{ item.body }}</span
                                >
                                <time
                                    v-if="item.createdAt"
                                    :datetime="item.createdAt"
                                    :title="
                                        formatDate(
                                            item.createdAt,
                                            'datetime',
                                        ) ?? undefined
                                    "
                                    class="mt-1 block text-xs text-muted-foreground"
                                >
                                    {{ formatRelative(item.createdAt) }}
                                </time>
                                <span v-if="!item.readAt" class="sr-only"
                                    >(sin leer)</span
                                >
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <footer class="border-t px-4 py-2.5 text-center">
                <Link
                    :href="notificationsIndex()"
                    class="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    @click="open = false"
                >
                    Ver todas
                </Link>
            </footer>
        </PopoverContent>
    </Popover>
</template>
