<script setup lang="ts">
import type { CSSProperties } from 'vue';
import { computed } from 'vue';
import { compositeOf } from './compose';
import { BLOCKS } from './registry';
import { blockVisualStyle } from './tokens';
import type { Block } from './types';

/**
 * Unidad compuesta del sitio público (ver compose.ts): una fila de botones
 * que se acomoda sola o una columna de texto fija junto a un bloque alto.
 * Cada hijo conserva su propio movimiento (`data-motion`) y estilo.
 */
defineOptions({ name: 'CompositeBlock' });

const props = defineProps<{ block: Block }>();

const composite = computed(() => compositeOf(props.block));
const INLINE = ['button', 'badge', 'icon'];
const SELF = { start: 'flex-start', center: 'center', end: 'flex-end' };

function childStyle(child: Block): CSSProperties {
    return {
        ...(blockVisualStyle(child) as CSSProperties),
        alignSelf: INLINE.includes(child.type)
            ? SELF[child.style.align]
            : undefined,
        textAlign: ({ start: 'left', center: 'center', end: 'right' } as const)[
            child.style.align
        ],
    };
}

/** Separación vertical en la columna: la que había en la retícula (mín. 16 px). */
function gapBefore(index: number): string | undefined {
    const children = composite.value?.children ?? [];

    if (index === 0) return undefined;

    const prev = children[index - 1];
    const rows = Math.max(0, children[index].y - (prev.y + prev.h));

    return `max(16px, calc(${rows} * 2cqi))`;
}

const justify = computed(
    () => SELF[composite.value?.children[0]?.style.align ?? 'start'],
);
</script>

<template>
    <div
        v-if="composite?.kind === 'buttons'"
        class="sb-button-row"
        :style="{ justifyContent: justify }"
    >
        <div
            v-for="child in composite.children"
            :key="child.id"
            class="flex min-w-0"
            :data-motion="
                child.motion.preset !== 'none' ? child.motion.preset : undefined
            "
            :data-motion-delay="
                child.motion.preset !== 'none' && child.motion.delay
                    ? String(child.motion.delay)
                    : undefined
            "
            :style="blockVisualStyle(child)"
        >
            <component :is="BLOCKS[child.type].component" :block="child" />
        </div>
    </div>

    <div v-else-if="composite" class="sb-stack">
        <template v-for="(child, i) in composite.children" :key="child.id">
            <CompositeBlock
                v-if="compositeOf(child)"
                :block="child"
                :style="{ marginTop: gapBefore(i) }"
            />
            <div
                v-else
                class="flex min-w-0 flex-col"
                :data-sb-kind="child.type"
                :data-motion="
                    child.motion.preset !== 'none'
                        ? child.motion.preset
                        : undefined
                "
                :data-motion-delay="
                    child.motion.preset !== 'none' && child.motion.delay
                        ? String(child.motion.delay)
                        : undefined
                "
                :style="{ ...childStyle(child), marginTop: gapBefore(i) }"
            >
                <component :is="BLOCKS[child.type].component" :block="child" />
            </div>
        </template>
    </div>
</template>
