<script setup lang="ts">
import { Lock } from '@lucide/vue';
import type { CSSProperties } from 'vue';
import { computed, inject, ref } from 'vue';
import CompositeBlock from './CompositeBlock.vue';
import { compositeOf } from './compose';
import { editorBridgeKey } from './context';
import type { Placement } from './grid';
import { BLOCKS } from './registry';
import { blockVisualStyle } from './tokens';
import type { Block } from './types';

/**
 * Un bloque colocado en la retícula. En el sitio público solo dibuja el
 * contenido y emite `data-motion`/`data-motion-delay` (el motor de
 * movimiento global lo anima al hacer scroll). En el editor además es
 * enfocable, se arrastra, se redimensiona con 8 asas y muestra su anillo de
 * selección.
 */
const props = defineProps<{
    block: Block;
    placement: Placement;
    sectionKey: string;
}>();

const bridge = inject(editorBridgeKey, null);
const root = ref<HTMLElement | null>(null);

const definition = computed(() => BLOCKS[props.block.type]);
/** Unidad compuesta del sitio público (fila de botones o columna fija). */
const composite = computed(() => (bridge ? null : compositeOf(props.block)));
/** Formas decorativas flotan un poco con el scroll (solo sitio público). */
const floats = computed(
    () =>
        !bridge &&
        props.block.type === 'shape' &&
        props.placement.role !== 'backdrop' &&
        props.block.props.kind !== 'line',
);
/** Solo los elementos «en línea» se encogen a su contenido; el resto llena su área. */
const INLINE = ['button', 'badge', 'icon'];
const align = computed(() =>
    !composite.value && INLINE.includes(props.block.type)
        ? { start: 'flex-start', center: 'center', end: 'flex-end' }[
              props.block.style.align
          ]
        : 'stretch',
);
const textAlign = computed(
    () =>
        ({ start: 'left', center: 'center', end: 'right' })[
            props.block.style.align
        ],
);

const selected = computed(
    () => bridge?.isSelected(props.sectionKey, props.block.id) ?? false,
);
const primary = computed(
    () => bridge?.isPrimary(props.sectionKey, props.block.id) ?? false,
);
const transient = computed(() => bridge?.transient(props.block.id) ?? null);
const interactive = computed(() => bridge?.canInteract.value ?? false);

const style = computed<CSSProperties>(() => {
    const p = props.placement;
    const s: Record<string, string | number> = {
        '--sb-area': p.area,
        '--sb-area-t': p.areaTablet,
        '--sb-m-row': String(p.mobileRow),
        '--sb-m-align': p.mobileAlign,
        '--sb-aspect': String(p.aspect),
        '--sb-z': String(props.block.z + (selected.value ? 500 : 0)),
        '--sb-h': String(props.block.h),
        '--sb-items': align.value,
        '--sb-text-align': textAlign.value,
    };

    const t = transient.value;
    if (t) {
        s.transform = `translate(${t.dx}px, ${t.dy}px)`;
        if (t.width !== null) {
            s.width = `${t.width}px`;
            s.justifySelf = 'start';
        }
        if (t.height !== null) {
            s.height = `${t.height}px`;
            s.alignSelf = 'start';
        }
        s.zIndex = 900;
        s.willChange = 'transform';
    }

    return s as CSSProperties;
});

const HANDLES = ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'] as const;
const CURSORS: Record<(typeof HANDLES)[number], string> = {
    nw: 'nwse-resize',
    se: 'nwse-resize',
    ne: 'nesw-resize',
    sw: 'nesw-resize',
    n: 'ns-resize',
    s: 'ns-resize',
    e: 'ew-resize',
    w: 'ew-resize',
};

const ariaLabel = computed(() => {
    const p = props.block.props;
    const text =
        (typeof p.text === 'string' && p.text) ||
        (typeof p.label === 'string' && p.label) ||
        '';

    return `${definition.value?.label ?? 'Elemento'}${text ? `: ${text}` : ''}${props.block.locked ? ' (bloqueado)' : ''}`;
});

function onPointerDown(event: PointerEvent) {
    if (!bridge || !root.value) return;
    bridge.pointerDown(event, props.sectionKey, props.block, root.value);
}

function onResize(event: PointerEvent, handle: string) {
    if (!bridge || !root.value) return;
    event.stopPropagation();
    bridge.resizeStart(
        event,
        props.sectionKey,
        props.block,
        handle,
        root.value,
    );
}

function onFocus(event: FocusEvent) {
    if (!bridge || event.target !== root.value) return;
    if (!selected.value) {
        bridge.focusBlock(props.sectionKey, props.block, false);
    }
}
</script>

<template>
    <div
        v-if="!bridge"
        class="sb-block"
        :style="style"
        :data-sb-kind="composite ? composite.kind : props.block.type"
        :data-sb-composite="composite?.kind"
        :data-sb-frame="definition?.frame && !composite ? '' : undefined"
        :data-sb-role="props.placement.role"
        :data-hide-tablet="
            props.block.hideOn.includes('tablet') ? '' : undefined
        "
        :data-hide-mobile="
            props.block.hideOn.includes('mobile') ? '' : undefined
        "
        :data-motion="
            props.block.motion.preset !== 'none'
                ? props.block.motion.preset
                : undefined
        "
        :data-motion-delay="
            props.block.motion.preset !== 'none' && props.block.motion.delay
                ? String(props.block.motion.delay)
                : undefined
        "
    >
        <div
            class="items-[inherit] flex min-h-0 flex-1 flex-col"
            :data-parallax="floats ? '-0.07' : undefined"
            :style="blockVisualStyle(props.block)"
        >
            <CompositeBlock v-if="composite" :block="props.block" />
            <component :is="definition.component" v-else :block="props.block" />
        </div>
    </div>

    <div
        v-else
        ref="root"
        class="sb-block group/block outline-none"
        :class="[
            interactive &&
                !props.block.locked &&
                'cursor-grab active:cursor-grabbing',
            transient && 'sb-dragging',
        ]"
        :style="style"
        :tabindex="0"
        role="group"
        :aria-roledescription="'elemento del lienzo'"
        :aria-label="ariaLabel"
        :aria-selected="selected"
        :data-block-id="props.block.id"
        :data-sb-kind="props.block.type"
        :data-sb-frame="definition?.frame ? '' : undefined"
        :data-sb-role="props.placement.role"
        :data-hide-tablet="
            props.block.hideOn.includes('tablet') ? '' : undefined
        "
        :data-hide-mobile="
            props.block.hideOn.includes('mobile') ? '' : undefined
        "
        @pointerdown="onPointerDown"
        @dblclick.stop="bridge.doubleClick(props.sectionKey, props.block)"
        @focus="onFocus"
    >
        <div
            class="items-[inherit] flex min-h-0 flex-1 flex-col"
            :class="
                interactive &&
                bridge.textEditing.value !== props.block.id &&
                bridge.cropping.value !== props.block.id &&
                '[&_*]:pointer-events-none'
            "
            :style="blockVisualStyle(props.block)"
        >
            <component :is="definition.component" :block="props.block" />
        </div>

        <!-- Anillo de hover / selección -->
        <span
            class="sb-ring pointer-events-none absolute -inset-px rounded-[3px]"
            :class="[
                selected
                    ? 'sb-ring--selected'
                    : 'opacity-0 group-hover/block:opacity-100 group-focus-visible/block:opacity-100',
            ]"
            aria-hidden="true"
        />

        <template v-if="selected && primary && interactive">
            <span
                class="sb-tag pointer-events-none absolute -top-px left-0 inline-flex -translate-y-full items-center gap-1 rounded-t-md px-1.5 py-0.5 text-[11px] leading-none font-medium whitespace-nowrap"
                aria-hidden="true"
            >
                <Lock v-if="props.block.locked" class="size-3" />
                {{ definition.label }}
                <span class="tabular-nums opacity-75"
                    >{{ props.block.w }}×{{ props.block.h }}</span
                >
            </span>
            <template v-if="!props.block.locked">
                <span
                    v-for="handle in HANDLES"
                    :key="handle"
                    class="sb-handle"
                    :class="`sb-handle--${handle}`"
                    :style="{ cursor: CURSORS[handle] }"
                    :data-handle="handle"
                    aria-hidden="true"
                    @pointerdown="onResize($event, handle)"
                />
            </template>
        </template>
    </div>
</template>
