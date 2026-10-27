<script setup lang="ts">
import { Check, ImageOff, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import FileDropzone from '@/components/ds/FileDropzone.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { apiFetch } from '@/lib/http';
import { cn } from '@/lib/utils';
import multimedia from '@/routes/admin/multimedia';
import type { MediaItem, SimplePaginator } from '@/types/web';

/**
 * Selector de la biblioteca multimedia (solo archivos públicos y listos):
 * buscar, elegir uno o varios y subir nuevos sin salir del formulario.
 */
const open = defineModel<boolean>('open', { default: false });

const props = withDefaults(
    defineProps<{
        multiple?: boolean;
        type?: 'image' | 'video' | 'document' | null;
        collection?: string;
        title?: string;
    }>(),
    {
        multiple: false,
        type: 'image',
        collection: 'sitio/general',
        title: 'Elegir de la biblioteca',
    },
);

const emit = defineEmits<{ select: [items: MediaItem[]] }>();

const tab = ref<'biblioteca' | 'subir'>('biblioteca');
const query = ref('');
const items = ref<MediaItem[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const error = ref<string | null>(null);
const selected = ref<Map<number, MediaItem>>(new Map());

const acceptList = computed(() =>
    props.type === 'image'
        ? ['image/*']
        : props.type === 'video'
          ? ['video/*']
          : props.type === 'document'
            ? ['.pdf']
            : [],
);

async function load(reset = true) {
    loading.value = true;
    error.value = null;
    try {
        const next = reset ? 1 : page.value + 1;
        const query_: Record<string, string> = { page: String(next) };
        if (query.value.trim()) query_.q = query.value.trim();
        if (props.type) query_.tipo = props.type;
        const response = await apiFetch(
            multimedia.buscar({ query: query_ }).url,
        );
        if (!response.ok) throw new Error();
        const payload = (await response.json()) as SimplePaginator<MediaItem>;
        items.value = reset ? payload.data : [...items.value, ...payload.data];
        page.value = payload.current_page;
        lastPage.value = payload.last_page;
    } catch {
        error.value = 'No se pudo cargar la biblioteca. Intenta de nuevo.';
    } finally {
        loading.value = false;
    }
}

const search = useDebounceFn(() => load(true), 300);
watch(query, () => search());

watch(open, (value) => {
    if (value) {
        selected.value = new Map();
        tab.value = 'biblioteca';
        void load(true);
    }
});

function toggle(item: MediaItem) {
    const next = new Map(props.multiple ? selected.value : []);
    if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.set(item.id, item);
    }
    selected.value = next;
}

function confirm() {
    emit('select', [...selected.value.values()]);
    open.value = false;
}

async function onUploaded() {
    tab.value = 'biblioteca';
    await load(true);
    const newest = items.value[0];
    if (newest) {
        const next = new Map(props.multiple ? selected.value : []);
        next.set(newest.id, newest);
        selected.value = next;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="flex max-h-[92svh] flex-col gap-4 sm:max-w-4xl">
            <DialogHeader>
                <DialogTitle class="font-display">{{
                    props.title
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        props.multiple
                            ? 'Puedes elegir varias imágenes.'
                            : 'Elige una imagen.'
                    }}
                    Solo se muestran archivos públicos.
                </DialogDescription>
            </DialogHeader>

            <Tabs v-model="tab" class="flex min-h-0 flex-1 flex-col">
                <TabsList class="self-start">
                    <TabsTrigger value="biblioteca">Biblioteca</TabsTrigger>
                    <TabsTrigger value="subir">Subir archivos</TabsTrigger>
                </TabsList>

                <TabsContent
                    value="biblioteca"
                    class="flex min-h-0 flex-1 flex-col gap-3"
                >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            v-model="query"
                            type="search"
                            placeholder="Buscar por nombre, título o texto alternativo"
                            class="pl-9"
                            aria-label="Buscar en la biblioteca"
                        />
                    </div>

                    <p
                        v-if="error"
                        role="alert"
                        class="text-sm text-danger-ink"
                    >
                        {{ error }}
                    </p>

                    <div class="min-h-[16rem] flex-1 overflow-y-auto pr-1">
                        <div
                            v-if="items.length"
                            class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5"
                        >
                            <button
                                v-for="item in items"
                                :key="item.id"
                                type="button"
                                :aria-pressed="selected.has(item.id)"
                                :aria-label="`${selected.has(item.id) ? 'Quitar' : 'Elegir'} ${item.filename}`"
                                :class="
                                    cn(
                                        'group relative aspect-square overflow-hidden rounded-lg bg-muted ring-offset-2 outline-none focus-visible:ring-3 focus-visible:ring-ring/50',
                                        selected.has(item.id) &&
                                            'ring-3 ring-primary',
                                    )
                                "
                                @click="toggle(item)"
                                @dblclick="
                                    !props.multiple && (toggle(item), confirm())
                                "
                            >
                                <img
                                    v-if="item.thumbnailUrl"
                                    :src="item.thumbnailUrl"
                                    :alt="item.altText ?? ''"
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="grid h-full place-items-center text-muted-foreground"
                                    ><ImageOff class="size-5"
                                /></span>
                                <span
                                    v-if="selected.has(item.id)"
                                    class="absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-primary text-primary-foreground"
                                >
                                    <Check class="size-3.5" />
                                </span>
                                <span
                                    class="absolute inset-x-0 bottom-0 truncate bg-black/55 px-2 py-1 text-left text-[11px] text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
                                >
                                    {{ item.filename }}
                                </span>
                            </button>
                        </div>
                        <div
                            v-else-if="!loading"
                            class="grid h-full place-items-center py-12 text-center text-sm text-muted-foreground"
                        >
                            <p>
                                No hay archivos que coincidan.<br />Sube uno
                                nuevo en la pestaña “Subir archivos”.
                            </p>
                        </div>
                        <div v-if="loading" class="flex justify-center py-6">
                            <Spinner />
                        </div>
                        <div
                            v-else-if="page < lastPage"
                            class="flex justify-center py-4"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                @click="load(false)"
                                >Cargar más</Button
                            >
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="subir">
                    <FileDropzone
                        :accept="acceptList"
                        :multiple="props.multiple"
                        :collection="props.collection"
                        visibility="public"
                        hint="JPG, PNG, WebP o AVIF. Usa imágenes horizontales de al menos 2400 px para portadas."
                        @all-completed="onUploaded"
                    />
                </TabsContent>
            </Tabs>

            <DialogFooter class="gap-2">
                <p class="mr-auto self-center text-sm text-muted-foreground">
                    {{
                        selected.size
                            ? `${selected.size} seleccionada${selected.size === 1 ? '' : 's'}`
                            : 'Nada seleccionado'
                    }}
                </p>
                <Button variant="outline" @click="open = false"
                    >Cancelar</Button
                >
                <Button :disabled="!selected.size" @click="confirm">{{
                    props.multiple ? 'Agregar' : 'Usar imagen'
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
