<script setup lang="ts">
import { ArrowRight, ClipboardList } from '@lucide/vue';
import { computed } from 'vue';
import SmartLink from '@/components/web/public/SmartLink.vue';
import type { Block } from '../types';
import { useBlock } from './useBlock';

/** Tarjeta compacta que invita a llenar la solicitud de asesoría. */
const props = defineProps<{ block: Block }>();

const { str, editing, dark } = useBlock(() => props.block);

const linkTag = computed(() => (editing.value ? 'span' : SmartLink));
</script>

<template>
    <div
        class="flex h-full flex-col justify-between gap-6 rounded-[1.5rem] p-7 ring-1 @2xl/sbpage:flex-row @2xl/sbpage:items-center @2xl/sbpage:p-9"
        :class="
            dark ? 'bg-white/5 ring-white/10' : 'bg-card shadow-sm ring-border'
        "
    >
        <div class="flex items-start gap-4">
            <span
                class="grid size-12 shrink-0 place-items-center rounded-full"
                :class="
                    dark
                        ? 'bg-white/10 text-oak'
                        : 'bg-primary-soft text-primary'
                "
                aria-hidden="true"
            >
                <ClipboardList class="size-5" />
            </span>
            <div>
                <p class="font-display text-xl font-semibold tracking-tight">
                    {{ str('title') }}
                </p>
                <p
                    v-if="str('body')"
                    class="mt-1 max-w-prose text-[color:var(--sb-ink-muted)]"
                >
                    {{ str('body') }}
                </p>
            </div>
        </div>
        <component
            :is="linkTag"
            :href="editing ? undefined : str('cta_href', '/solicitar')"
            class="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-full bg-primary px-7 text-base font-medium text-primary-foreground transition-colors hover:bg-primary-hover"
        >
            {{ str('cta_label') || 'Solicita una asesoría' }}
            <ArrowRight class="size-4" aria-hidden="true" />
        </component>
    </div>
</template>
