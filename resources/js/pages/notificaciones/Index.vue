<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BellOff, CheckCheck, ChevronRight } from '@lucide/vue';
import { ref } from 'vue';
import DataPagination from '@/components/ds/DataPagination.vue';
import DateText from '@/components/ds/DateText.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import { toneClasses } from '@/components/ds/tones';
import type { Tone } from '@/components/ds/tones';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import {
    index as notificationsIndex,
    leida,
    leidas,
} from '@/routes/notificaciones';
import type { LengthAwarePaginator } from '@/types/ui';

type NotificationItem = {
    id: string;
    title: string;
    body: string | null;
    url: string | null;
    tone: Tone;
    icon: string | null;
    readAt: string | null;
    createdAt: string | null;
};

const props = defineProps<{
    notifications: LengthAwarePaginator<NotificationItem>;
    unread: number;
    filter: 'todas' | 'no-leidas';
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Notificaciones', href: '/notificaciones' }],
    },
});

const tabs = [
    { value: 'todas', label: 'Todas', href: notificationsIndex().url },
    {
        value: 'no-leidas',
        label: 'No leídas',
        href: notificationsIndex({ query: { filtro: 'no-leidas' } }).url,
    },
] as const;

const markingAll = ref(false);

function markAll() {
    router.post(
        leidas().url,
        {},
        {
            preserveScroll: true,
            onStart: () => (markingAll.value = true),
            onFinish: () => (markingAll.value = false),
        },
    );
}

function open(item: NotificationItem) {
    if (item.readAt) {
        if (item.url) {
            router.visit(item.url);
        }

        return;
    }

    router.patch(
        leida(item.id).url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (item.url) {
                    router.visit(item.url);
                }
            },
        },
    );
}
</script>

<template>
    <Head title="Notificaciones" />

    <PageContainer width="narrow">
        <PageHeader
            title="Notificaciones"
            :description="
                props.unread > 0
                    ? `Tienes ${props.unread} sin leer.`
                    : 'Estás al día.'
            "
        >
            <template #actions>
                <Button
                    v-if="props.unread > 0"
                    variant="outline"
                    :disabled="markingAll"
                    :aria-busy="markingAll"
                    @click="markAll"
                >
                    <Spinner v-if="markingAll" />
                    <CheckCheck v-else class="size-4" />
                    Marcar todas como leídas
                </Button>
            </template>
        </PageHeader>

        <nav
            aria-label="Filtrar notificaciones"
            class="flex w-fit gap-1 rounded-lg border bg-muted/60 p-1"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.value"
                :href="tab.href"
                preserve-scroll
                :aria-current="props.filter === tab.value ? 'page' : undefined"
                :class="
                    cn(
                        'inline-flex min-h-9 items-center rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                        props.filter === tab.value &&
                            'bg-background text-foreground shadow-xs ring-1 ring-border',
                    )
                "
            >
                {{ tab.label }}
            </Link>
        </nav>

        <EmptyState
            v-if="props.notifications.data.length === 0"
            :icon="BellOff"
            :title="
                props.filter === 'no-leidas'
                    ? 'No tienes notificaciones sin leer'
                    : 'No tienes notificaciones nuevas'
            "
            description="Aquí verás avisos de citas, pagos y avances de proyectos."
        />

        <ul v-else class="divide-y overflow-hidden rounded-xl border bg-card">
            <li v-for="item in props.notifications.data" :key="item.id">
                <button
                    type="button"
                    :class="
                        cn(
                            'flex w-full items-start gap-3 px-4 py-4 text-left transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none sm:px-5',
                            !item.readAt && 'bg-primary-soft/40',
                        )
                    "
                    @click="open(item)"
                >
                    <span
                        aria-hidden="true"
                        :class="
                            cn(
                                'mt-2 size-2 shrink-0 rounded-full',
                                item.readAt
                                    ? 'bg-border'
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
                                        ? 'text-foreground'
                                        : 'font-semibold text-foreground',
                                )
                            "
                        >
                            {{ item.title }}
                            <span v-if="!item.readAt" class="sr-only"
                                >(sin leer)</span
                            >
                        </span>
                        <span
                            v-if="item.body"
                            class="mt-1 block text-sm text-muted-foreground"
                            >{{ item.body }}</span
                        >
                        <DateText
                            :value="item.createdAt"
                            relative
                            class="mt-1 block text-xs text-muted-foreground"
                        />
                    </span>
                    <ChevronRight
                        v-if="item.url"
                        class="mt-1 size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </button>
            </li>
        </ul>

        <DataPagination
            v-if="props.notifications.last_page > 1"
            :paginator="props.notifications"
            item-label="notificaciones"
        />
    </PageContainer>
</template>
