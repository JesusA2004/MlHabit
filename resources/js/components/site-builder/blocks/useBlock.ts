import { computed, inject } from 'vue';
import { editorBridgeKey, sectionContextKey } from '../context';
import type { Block } from '../types';

/** Acceso común de un bloque a su sección y al editor (si existe). */
export function useBlock(block: () => Block) {
    const section = inject(sectionContextKey, null);
    const bridge = inject(editorBridgeKey, null);

    const props = computed(() => block().props);
    const editing = computed(() => bridge !== null);
    const textEditing = computed(
        () => bridge?.textEditing.value === block().id,
    );

    function str(key: string, fallback = ''): string {
        const value = props.value[key];

        return typeof value === 'string' ? value : fallback;
    }

    function num(key: string, fallback = 0): number {
        const value = props.value[key];

        return typeof value === 'number' ? value : fallback;
    }

    function bool(key: string, fallback = false): boolean {
        const value = props.value[key];

        return typeof value === 'boolean' ? value : fallback;
    }

    function commit(prop: string, value: string) {
        if (!bridge || !section) return;
        bridge.commitText(section.value.key, block().id, prop, value);
    }

    return {
        section,
        bridge,
        props,
        editing,
        textEditing,
        str,
        num,
        bool,
        commit,
        dark: computed(() => section?.value.dark ?? false),
    };
}
