<script setup lang="ts">
import { Plus } from '@lucide/vue';
import type { StudioNode } from '@/studio/types';
import { useRenderCtx } from './context';
import ElementView from './ElementView.vue';

const { section, name } = defineProps<{
    section: StudioNode;
    name: string;
}>();

const ctx = useRenderCtx();
</script>

<template>
    <div class="sn-slot" :data-slot="name" :data-section="section.id">
        <ElementView
            v-for="child in section.slots?.[name] ?? []"
            :key="child.id"
            :node="child"
        />
        <button
            v-if="ctx.editing && !(section.slots?.[name] ?? []).length"
            type="button"
            class="sn-slot-empty"
            data-slot-add
            :data-section="section.id"
            :data-slot="name"
        >
            <Plus class="size-4" aria-hidden="true" />
            Agregar bloque
        </button>
    </div>
</template>
