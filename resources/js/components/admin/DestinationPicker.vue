<script setup lang="ts">
import { FolderOpen } from '@lucide/vue';
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/**
 * Elige dónde se guardará un archivo subido: colección de primer nivel
 * bajo la biblioteca «mr-insight» y, opcionalmente, una carpeta dentro.
 * El valor (v-model:path) es la ruta lógica `mr-insight/coleccion[/carpeta]`
 * que espera el backend; la UI muestra siempre nombres legibles.
 * Se deriva por completo de `path`, así que si la pantalla cambia el
 * destino desde fuera (p. ej. al elegir una carpeta en la barra lateral)
 * los selectores se sincronizan solos.
 */
export interface CollectionNode {
    id: number;
    parent_id: number | null;
    name: string;
    path: string;
}

const ROOT_FOLDER = '__raiz__';

const { collections, providerLabel, stacked = false, idPrefix = 'destino' } = defineProps<{
    collections: CollectionNode[];
    providerLabel?: string;
    /** Apila los selectores en una sola columna (barras laterales). */
    stacked?: boolean;
    idPrefix?: string;
}>();

const path = defineModel<string>('path', { default: 'mr-insight/general' });

const slugOf = (node: CollectionNode) => node.path.split('/').pop() ?? '';

const root = computed(() =>
    collections.find((c) => c.parent_id === null && c.path === 'mr-insight'),
);

const colecciones = computed(() =>
    collections
        .filter((c) => c.parent_id === root.value?.id)
        .sort((a, b) => a.name.localeCompare(b.name)),
);

function carpetasDe(coleccionId: number | undefined) {
    if (coleccionId === undefined) return [];

    return collections
        .filter((c) => c.parent_id === coleccionId)
        .sort((a, b) => a.name.localeCompare(b.name));
}

const segments = computed(() => path.value.split('/'));

const coleccionSlug = computed<string>({
    get: () => segments.value[1] ?? '',
    set: (slug) => {
        // Al cambiar de colección se propone su primera carpeta (comportamiento
        // histórico); si no tiene carpetas, se guarda en la raíz de la colección.
        const coleccion = colecciones.value.find((c) => slugOf(c) === slug);
        const primera = carpetasDe(coleccion?.id)[0];
        path.value = primera ? `mr-insight/${slug}/${slugOf(primera)}` : `mr-insight/${slug}`;
    },
});

const coleccionActual = computed(() =>
    colecciones.value.find((c) => slugOf(c) === coleccionSlug.value),
);

const carpetas = computed(() => carpetasDe(coleccionActual.value?.id));

const carpetaSlug = computed<string>({
    get: () => segments.value[2] ?? ROOT_FOLDER,
    set: (slug) => {
        path.value = slug === ROOT_FOLDER
            ? `mr-insight/${coleccionSlug.value}`
            : `mr-insight/${coleccionSlug.value}/${slug}`;
    },
});

/** Nombre legible del destino: «Colección › Carpeta». */
const destinoLegible = computed(() => {
    const coleccion = coleccionActual.value?.name ?? segments.value[1] ?? '';
    const carpeta = carpetas.value.find((c) => slugOf(c) === segments.value[2])?.name ?? segments.value[2];

    return carpeta ? `${coleccion} › ${carpeta}` : coleccion;
});
</script>

<template>
    <div :class="cn('grid gap-3', !stacked && 'sm:grid-cols-2')">
        <div class="grid gap-1.5">
            <Label :for="`${idPrefix}-coleccion`" class="text-xs text-muted-foreground">Colección</Label>
            <Select v-model="coleccionSlug">
                <SelectTrigger :id="`${idPrefix}-coleccion`" class="w-full pointer-coarse:data-[size=default]:h-11">
                    <SelectValue placeholder="Elige una colección" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="coleccion in colecciones" :key="coleccion.id" :value="slugOf(coleccion)">
                        {{ coleccion.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <div class="grid gap-1.5">
            <Label :for="`${idPrefix}-carpeta`" class="text-xs text-muted-foreground">Carpeta</Label>
            <Select v-model="carpetaSlug">
                <SelectTrigger :id="`${idPrefix}-carpeta`" class="w-full pointer-coarse:data-[size=default]:h-11">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="ROOT_FOLDER">Sin subcarpeta</SelectItem>
                    <SelectItem v-for="carpeta in carpetas" :key="carpeta.id" :value="slugOf(carpeta)">
                        {{ carpeta.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
        <p class="col-span-full flex items-start gap-1.5 text-xs text-muted-foreground">
            <FolderOpen class="mt-px size-3.5 shrink-0" aria-hidden="true" />
            <span>
                Se guardará en <span class="font-medium text-foreground">{{ destinoLegible }}</span>
                · {{ providerLabel ?? 'Almacenamiento local' }}
            </span>
        </p>
    </div>
</template>
