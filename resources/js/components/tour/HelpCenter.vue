<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CircleCheck,
    Compass,
    Play,
    RotateCcw,
    Search,
    SearchX,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/ds/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { normalizePath } from '@/lib/navigation';
import { useTourProgress } from './progress';
import type { HelpModule, TourArea } from './types';
import { useTourActions } from './useTourActions';

/**
 * Centro de ayuda: una ficha por módulo al que la persona tiene acceso,
 * con "¿Para qué sirve?", los pasos clave y el botón para ver su recorrido.
 * Lo usan /admin/ayuda (panel) y /portal/ayuda (portal del cliente).
 */
const props = withDefaults(
    defineProps<{ area: TourArea; compact?: boolean }>(),
    { compact: false },
);

const page = usePage();
const { modules, general, start, startGeneral } = useTourActions(props.area);
const progress = useTourProgress();

const query = ref('');
const currentPath = computed(() => normalizePath(page.url));

function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

const filtered = computed<HelpModule[]>(() => {
    const q = normalize(query.value.trim());

    if (!q) {
        return modules.value;
    }

    return modules.value.filter((m) =>
        normalize(
            [m.title, m.group, m.summary, m.purpose, ...m.keySteps].join(' '),
        ).includes(q),
    );
});

const groups = computed(() => {
    const map = new Map<string, HelpModule[]>();

    for (const m of filtered.value) {
        map.set(m.group, [...(map.get(m.group) ?? []), m]);
    }

    return Array.from(map, ([label, items]) => ({ label, items }));
});

const userId = computed(() => page.props.auth?.user?.id ?? null);

onMounted(() => {
    if (userId.value) {
        void progress.load(userId.value);
    }
});

function seen(m: HelpModule): boolean {
    return !!m.tour && progress.statusOf(m.tour.id) === 'completed';
}

const resetting = ref(false);

async function resetTours(): Promise<void> {
    if (!userId.value || resetting.value) {
        return;
    }

    resetting.value = true;
    const ok = await progress.reset(userId.value);
    resetting.value = false;

    if (ok) {
        toast.success(
            'Listo. Te volveremos a ofrecer el recorrido de bienvenida.',
        );
    } else {
        toast.error('No pudimos reiniciar los recorridos. Intenta de nuevo.');
    }
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Recorrido general -->
        <section
            class="relative overflow-hidden rounded-xl border bg-linear-to-br from-primary-soft via-card to-card p-5 sm:p-6"
            aria-labelledby="help-general-title"
            data-tour="help-general"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <span
                        class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground shadow-xs"
                        aria-hidden="true"
                    >
                        <Compass class="size-5" />
                    </span>
                    <div>
                        <h2
                            id="help-general-title"
                            class="font-display text-lg font-semibold tracking-tight text-foreground"
                        >
                            {{ general.title }}
                        </h2>
                        <p class="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {{ general.description }} Te lleva por cada pantalla
                            y te explica qué hace cada parte. Usa ← y → para
                            avanzar y Esc para salir.
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <Button @click="startGeneral()">
                        <Play class="size-4" aria-hidden="true" />
                        Iniciar recorrido general
                    </Button>
                    <Button
                        variant="ghost"
                        :disabled="resetting"
                        @click="resetTours"
                    >
                        <RotateCcw class="size-4" aria-hidden="true" />
                        Reiniciar recorridos
                    </Button>
                </div>
            </div>
        </section>

        <!-- Búsqueda -->
        <div class="relative max-w-md" data-tour="help-search">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <Input
                v-model="query"
                type="search"
                class="pl-9"
                :placeholder="
                    area === 'admin'
                        ? 'Busca un módulo: pagos, visitas, menú…'
                        : 'Busca una sección: pagos, citas, diseños…'
                "
                aria-label="Buscar en la ayuda"
            />
        </div>

        <div data-tour="help-modules" class="flex flex-col gap-8">
            <EmptyState
                v-if="!groups.length"
                :icon="SearchX"
                title="No encontramos esa sección"
                description="Prueba con otra palabra, por ejemplo «pagos» o «agenda»."
            />

            <section
                v-for="group in groups"
                :key="group.label"
                :aria-label="group.label"
            >
                <h2
                    v-if="!compact || groups.length > 1"
                    class="mb-3 text-xs font-medium text-muted-foreground"
                >
                    {{ group.label }}
                </h2>
                <ul
                    :class="[
                        'grid gap-4',
                        compact
                            ? 'sm:grid-cols-2'
                            : 'md:grid-cols-2 2xl:grid-cols-3',
                    ]"
                >
                    <li
                        v-for="m in group.items"
                        :key="m.id"
                        class="flex flex-col rounded-xl border bg-card p-5 shadow-xs"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                v-if="m.icon"
                                class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-soft text-primary ring-1 ring-primary/10"
                                aria-hidden="true"
                            >
                                <component :is="m.icon" class="size-4.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="flex items-center gap-2 font-semibold text-foreground"
                                >
                                    {{ m.title }}
                                    <span
                                        v-if="seen(m)"
                                        class="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-medium text-success"
                                    >
                                        <CircleCheck
                                            class="size-3"
                                            aria-hidden="true"
                                        />
                                        Visto
                                    </span>
                                </h3>
                                <p class="mt-0.5 text-sm text-muted-foreground">
                                    {{ m.summary }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 text-sm">
                            <p class="font-medium text-foreground">
                                ¿Para qué sirve?
                            </p>
                            <p class="mt-1 text-muted-foreground">
                                {{ m.purpose }}
                            </p>
                        </div>

                        <div class="mt-4 text-sm">
                            <p class="font-medium text-foreground">
                                Pasos clave
                            </p>
                            <ol class="mt-1.5 flex flex-col gap-1.5">
                                <li
                                    v-for="(s, i) in m.keySteps"
                                    :key="i"
                                    class="flex gap-2.5 text-muted-foreground"
                                >
                                    <span
                                        class="mt-px grid size-5 shrink-0 place-items-center rounded-full bg-muted text-[11px] font-semibold text-foreground tabular-nums"
                                        aria-hidden="true"
                                        >{{ i + 1 }}</span
                                    >
                                    <span>{{ s }}</span>
                                </li>
                            </ol>
                        </div>

                        <div class="mt-auto flex flex-wrap gap-2 pt-5">
                            <Button
                                v-if="m.tour"
                                size="sm"
                                :aria-label="`Iniciar el recorrido de ${m.title}`"
                                @click="start(m.tour)"
                            >
                                <Play class="size-4" aria-hidden="true" />
                                Iniciar recorrido
                            </Button>
                            <Button
                                v-if="normalizePath(m.href) !== currentPath"
                                as-child
                                size="sm"
                                variant="outline"
                            >
                                <Link :href="m.href">
                                    Ir a {{ m.title.replace(/ \(.*\)$/, '') }}
                                    <ArrowUpRight
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
