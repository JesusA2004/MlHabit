<script setup lang="ts">
import { Check, Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', Icon: Sun, label: 'Claro', hint: 'Marfil y tinta' },
    { value: 'dark', Icon: Moon, label: 'Oscuro', hint: 'Ideal de noche' },
    { value: 'system', Icon: Monitor, label: 'Sistema', hint: 'Sigue tu dispositivo' },
] as const;
</script>

<template>
    <div role="radiogroup" aria-label="Tema del panel" class="grid gap-3 sm:grid-cols-3">
        <button
            v-for="{ value, Icon, label, hint } in options"
            :key="value"
            type="button"
            role="radio"
            :aria-checked="appearance === value"
            :class="
                cn(
                    'group relative flex flex-col overflow-hidden rounded-xl border bg-card text-left transition-[border-color,box-shadow] hover:border-foreground/30',
                    appearance === value && 'border-brand ring-2 ring-brand/40 hover:border-brand',
                )
            "
            @click="updateAppearance(value)"
        >
            <!-- Miniatura del panel -->
            <span class="relative flex aspect-[16/10] w-full overflow-hidden border-b" aria-hidden="true">
                <template v-if="value === 'system'">
                    <span class="flex w-1/2 gap-1.5 bg-paper p-2.5">
                        <span class="w-1/4 rounded-sm bg-ink/10" />
                        <span class="flex flex-1 flex-col gap-1.5">
                            <span class="h-1.5 w-3/4 rounded-full bg-ink/70" />
                            <span class="flex-1 rounded-sm bg-white ring-1 ring-ink/10" />
                        </span>
                    </span>
                    <span class="flex w-1/2 gap-1.5 bg-ink p-2.5">
                        <span class="w-1/4 rounded-sm bg-paper/10" />
                        <span class="flex flex-1 flex-col gap-1.5">
                            <span class="h-1.5 w-3/4 rounded-full bg-paper/70" />
                            <span class="flex-1 rounded-sm bg-ink-soft ring-1 ring-paper/10" />
                        </span>
                    </span>
                </template>
                <span v-else :class="cn('flex w-full gap-2 p-3', value === 'light' ? 'bg-paper' : 'bg-ink')">
                    <span :class="cn('w-1/5 rounded-sm', value === 'light' ? 'bg-ink/10' : 'bg-paper/10')" />
                    <span class="flex flex-1 flex-col gap-2">
                        <span :class="cn('h-2 w-1/2 rounded-full', value === 'light' ? 'bg-ink/70' : 'bg-paper/70')" />
                        <span class="flex flex-1 gap-2">
                            <span :class="cn('flex-1 rounded-sm ring-1', value === 'light' ? 'bg-white ring-ink/10' : 'bg-ink-soft ring-paper/10')" />
                            <span :class="cn('flex-1 rounded-sm ring-1', value === 'light' ? 'bg-white ring-ink/10' : 'bg-ink-soft ring-paper/10')" />
                        </span>
                        <span class="h-1.5 w-1/4 rounded-full bg-gold" />
                    </span>
                </span>
            </span>

            <span class="flex min-h-14 items-center gap-3 px-3.5 py-3">
                <component :is="Icon" class="size-4 shrink-0 text-muted-foreground" />
                <span class="flex min-w-0 flex-1 flex-col leading-tight">
                    <span class="text-sm font-medium">{{ label }}</span>
                    <span class="truncate text-xs text-muted-foreground">{{ hint }}</span>
                </span>
                <span
                    :class="
                        cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-full border transition-colors',
                            appearance === value ? 'border-brand bg-brand text-brand-foreground' : 'border-border',
                        )
                    "
                >
                    <Check v-if="appearance === value" class="size-3" />
                </span>
            </span>
        </button>
    </div>
</template>
