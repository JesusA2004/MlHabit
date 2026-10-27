<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';

const { appearance, updateAppearance } = useAppearance();

/** Colores fijos de las miniaturas: muestran cada tema sin importar el actual. */
const LIGHT = {
    bg: '#F7F5F2',
    side: '#EFEBE5',
    card: '#FFFFFF',
    ink: '#231E1A',
    accent: '#2F5A4C',
};
const DARK = {
    bg: '#141311',
    side: '#1C1A17',
    card: '#26231F',
    ink: '#EFEAE3',
    accent: '#8CC0AA',
};

const options = [
    { value: 'light', Icon: Sun, label: 'Claro', hint: 'Lino y espresso' },
    { value: 'dark', Icon: Moon, label: 'Oscuro', hint: 'Cómodo de noche' },
    {
        value: 'system',
        Icon: Monitor,
        label: 'Sistema',
        hint: 'Igual que tu dispositivo',
    },
] as const;
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Tema de la aplicación"
        class="grid gap-3 sm:grid-cols-3"
    >
        <button
            v-for="{ value, Icon, label, hint } in options"
            :key="value"
            type="button"
            role="radio"
            :aria-checked="appearance === value"
            :class="
                cn(
                    'group relative flex flex-col overflow-hidden rounded-xl border bg-card text-left transition-[border-color,box-shadow] duration-150 hover:border-foreground/25 focus-visible:ring-3 focus-visible:ring-ring/40 focus-visible:outline-none',
                    appearance === value &&
                        'border-primary ring-2 ring-primary/30 hover:border-primary',
                )
            "
            @click="updateAppearance(value)"
        >
            <span
                class="relative flex aspect-[16/10] w-full overflow-hidden border-b"
                aria-hidden="true"
            >
                <template
                    v-for="(theme, i) in value === 'system'
                        ? [LIGHT, DARK]
                        : [value === 'light' ? LIGHT : DARK]"
                    :key="i"
                >
                    <span
                        class="flex flex-1 gap-2 p-2.5"
                        :style="{ background: theme.bg }"
                    >
                        <span
                            class="w-1/5 rounded-sm"
                            :style="{ background: theme.side }"
                        />
                        <span class="flex flex-1 flex-col gap-1.5">
                            <span
                                class="h-1.5 w-1/2 rounded-full"
                                :style="{
                                    background: theme.ink,
                                    opacity: 0.75,
                                }"
                            />
                            <span
                                class="flex-1 rounded-sm"
                                :style="{ background: theme.card }"
                            />
                            <span
                                class="h-1.5 w-1/4 rounded-full"
                                :style="{ background: theme.accent }"
                            />
                        </span>
                    </span>
                </template>
            </span>

            <span class="flex min-h-14 items-center gap-3 px-3.5 py-3">
                <component
                    :is="Icon"
                    class="size-4 shrink-0 text-muted-foreground"
                />
                <span class="flex min-w-0 flex-1 flex-col leading-tight">
                    <span class="text-sm font-medium">{{ label }}</span>
                    <span class="truncate text-xs text-muted-foreground">{{
                        hint
                    }}</span>
                </span>
                <span
                    :class="
                        cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                            appearance === value
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border',
                        )
                    "
                >
                    <Check v-if="appearance === value" class="size-3" />
                </span>
            </span>
        </button>
    </div>
</template>
