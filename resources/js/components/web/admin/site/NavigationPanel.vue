<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    ChevronDown,
    CornerDownRight,
    EyeOff,
    GripVertical,
    Link2,
    ListPlus,
    Menu as MenuIcon,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
} from '@lucide/vue';
import { VueDraggable } from 'vue-draggable-plus';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import SectionCard from '@/components/ds/SectionCard.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import menuRoutes from '@/routes/admin/sitio/menu';

type TargetType = 'builtin' | 'page' | 'url' | 'group';

/** Enlace del menú (los del submenú no tienen hijos). */
export type MenuLink = {
    id: string;
    label: string;
    type: TargetType;
    target: string;
    newTab: boolean;
    visible: boolean;
};

export type MenuItem = MenuLink & { children: MenuLink[] };

export type MenuCta = {
    visible: boolean;
    label: string;
    type: Exclude<TargetType, 'group'>;
    target: string;
    newTab: boolean;
};

export type NavigationData = {
    items: MenuItem[];
    cta: MenuCta;
    customized: boolean;
    builtins: { value: string; label: string; href: string }[];
    pages: {
        value: string;
        label: string;
        path: string;
        isPublished: boolean;
    }[];
    limits: { items: number; children: number };
};

const props = defineProps<{
    navigation: NavigationData;
    canPublish: boolean;
}>();

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const newId = () => `n${Math.random().toString(36).slice(2, 10)}`;

const form = useForm({
    items: clone(props.navigation.items).map((item) => ({
        ...item,
        children: item.children ?? [],
    })),
    cta: clone(props.navigation.cta),
});

const dirty = computed(() => form.isDirty);
const readOnly = computed(() => !props.canPublish);

function save() {
    form.put(menuRoutes.update().url, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

const resetOpen = ref(false);
function resetMenu(): Promise<void> {
    return new Promise((resolve, reject) => {
        router.post(
            menuRoutes.restablecer().url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.items = clone(props.navigation.items);
                    form.cta = clone(props.navigation.cta);
                    form.defaults();
                    resolve();
                },
                onError: () => reject(new Error('No se pudo restablecer.')),
            },
        );
    });
}

// ----- Descripción de cada destino ---------------------------------------
function describe(item: Pick<MenuLink, 'type' | 'target'>): string {
    switch (item.type) {
        case 'builtin':
            return (
                props.navigation.builtins.find((b) => b.value === item.target)
                    ?.href ?? 'Página del sitio'
            );
        case 'page': {
            const page = props.navigation.pages.find(
                (p) => p.value === item.target,
            );

            return page ? page.path : 'Página eliminada';
        }
        case 'url':
            return item.target || 'Sin enlace';
        default:
            return 'Solo abre el submenú';
    }
}

function pageUnpublished(item: Pick<MenuLink, 'type' | 'target'>): boolean {
    if (item.type !== 'page') return false;
    const page = props.navigation.pages.find((p) => p.value === item.target);

    return !page || !page.isPublished;
}

function errorFor(path: string): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;

    return (
        errors[`${path}.label`] ??
        errors[`${path}.target`] ??
        errors[`${path}.type`] ??
        errors[`${path}.children`]
    );
}

// ----- Edición (hoja lateral) ---------------------------------------------
type Path = { index: number; child: number | null } | { cta: true };

const sheetOpen = ref(false);
const path = ref<Path | null>(null);
const draft = ref<MenuItem>({
    id: '',
    label: '',
    type: 'builtin',
    target: 'inicio',
    newTab: false,
    visible: true,
    children: [],
});
const isCta = computed(() => !!path.value && 'cta' in path.value);
const isChild = computed(
    () => !!path.value && !('cta' in path.value) && path.value.child !== null,
);
const creating = ref(false);

function openEditor(next: Path, item: MenuLink | null) {
    path.value = next;
    creating.value = item === null;
    draft.value = item
        ? { ...clone(item), children: [] }
        : {
              id: newId(),
              label: '',
              type: 'builtin',
              target: 'inicio',
              newTab: false,
              visible: true,
              children: [],
          };
    sheetOpen.value = true;
}

function editCta() {
    openEditor({ cta: true }, { ...form.cta, id: 'cta' });
}

function onTypeChange(value: unknown) {
    const type = String(value) as TargetType;
    draft.value.type = type;
    draft.value.target =
        type === 'builtin'
            ? (props.navigation.builtins[0]?.value ?? '')
            : type === 'page'
              ? (props.navigation.pages[0]?.value ?? '')
              : '';
}

function onBuiltinChange(value: unknown) {
    const previous = props.navigation.builtins.find(
        (b) => b.value === draft.value.target,
    );
    draft.value.target = String(value);
    // Si la etiqueta era la sugerida, se actualiza con la nueva.
    if (!draft.value.label || draft.value.label === previous?.label) {
        draft.value.label =
            props.navigation.builtins.find((b) => b.value === value)?.label ??
            draft.value.label;
    }
}

function onPageChange(value: unknown) {
    const previous = props.navigation.pages.find(
        (p) => p.value === draft.value.target,
    );
    draft.value.target = String(value);
    if (!draft.value.label || draft.value.label === previous?.label) {
        draft.value.label =
            props.navigation.pages.find((p) => p.value === value)?.label ??
            draft.value.label;
    }
}

const draftError = computed(() => {
    if (!draft.value.label.trim()) return 'Escribe el texto del enlace.';
    if (draft.value.label.trim().length > 40)
        return 'Máximo 40 caracteres para que quepa en la cabecera.';
    if (draft.value.type === 'url') {
        const t = draft.value.target.trim();
        if (!/^(\/(?!\/)\S*|https:\/\/\S+|mailto:\S+|tel:\S+)$/i.test(t)) {
            return 'Usa una ruta interna (p. ej. /cocinas) o un enlace https://, mailto: o tel:.';
        }
    }
    if (draft.value.type === 'page' && !draft.value.target)
        return 'Elige una de tus páginas.';

    return null;
});

function applyDraft() {
    if (draftError.value || !path.value) return;
    const item: MenuLink = {
        id: draft.value.id,
        label: draft.value.label.trim(),
        type: draft.value.type,
        target: draft.value.target.trim(),
        newTab: draft.value.newTab,
        visible: draft.value.visible,
    };
    const p = path.value;

    if ('cta' in p) {
        form.cta = {
            visible: form.cta.visible,
            label: item.label,
            type: item.type === 'group' ? 'builtin' : item.type,
            target: item.target,
            newTab: item.newTab,
        };
    } else if (p.child === null) {
        if (creating.value) form.items.push({ ...item, children: [] });
        else
            form.items[p.index] = {
                ...item,
                children: form.items[p.index].children,
            };
    } else {
        const parent = form.items[p.index];
        if (creating.value) parent.children.push(item);
        else parent.children[p.child] = item;
    }
    sheetOpen.value = false;
}

function addItem() {
    openEditor({ index: form.items.length, child: null }, null);
}

function addChild(index: number) {
    openEditor({ index, child: form.items[index].children.length }, null);
}

function removeItem(index: number, child: number | null = null) {
    if (child === null) form.items.splice(index, 1);
    else form.items[index].children.splice(child, 1);
}

const collapsed = ref<Set<string>>(new Set());
function toggleCollapsed(id: string) {
    const next = new Set(collapsed.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    collapsed.value = next;
}

// ----- Vista previa de la cabecera ---------------------------------------
const preview = computed(() => form.items.filter((i) => i.visible));
</script>

<template>
    <SectionCard
        title="Menú del sitio"
        description="Los enlaces de la cabecera del sitio, en el orden en que aparecen. Arrastra para reordenar, agrupa enlaces en un submenú y elige el botón destacado."
    >
        <template #actions>
            <Button
                variant="ghost"
                size="sm"
                :disabled="readOnly || !props.navigation.customized"
                @click="resetOpen = true"
            >
                <RotateCcw class="size-4" /> Restablecer
            </Button>
            <Button
                size="sm"
                :disabled="readOnly || !dirty || form.processing"
                @click="save"
            >
                <Spinner v-if="form.processing" />
                {{ form.processing ? 'Guardando…' : 'Guardar menú' }}
            </Button>
        </template>

        <p
            v-if="readOnly"
            class="mb-4 rounded-lg bg-info-soft px-3 py-2 text-sm text-info-ink"
        >
            Puedes ver el menú, pero cambiarlo requiere el permiso de publicar
            contenido.
        </p>

        <!-- Vista previa -->
        <div
            class="mb-6 overflow-hidden rounded-xl border border-border bg-background"
            aria-label="Vista previa de la cabecera"
            role="img"
        >
            <div
                class="flex items-center gap-4 overflow-x-auto px-4 py-3 text-sm"
            >
                <span class="shrink-0 font-display font-semibold tracking-tight"
                    >ML Habitat</span
                >
                <span class="flex flex-1 items-center justify-center gap-1">
                    <span
                        v-for="item in preview"
                        :key="item.id"
                        class="inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-muted-foreground"
                    >
                        {{ item.label }}
                        <ChevronDown
                            v-if="item.children.length"
                            class="size-3.5"
                            aria-hidden="true"
                        />
                    </span>
                </span>
                <span
                    v-if="form.cta.visible"
                    class="shrink-0 rounded-full bg-primary px-3 py-1.5 font-medium text-primary-foreground"
                    >{{ form.cta.label }}</span
                >
            </div>
        </div>

        <div v-if="form.errors.items" class="mb-3">
            <InputError :message="form.errors.items" />
        </div>

        <VueDraggable
            v-if="form.items.length"
            v-model="form.items"
            :animation="180"
            handle="[data-handle]"
            :disabled="readOnly"
            tag="ol"
            class="flex flex-col gap-2"
            ghost-class="nav-ghost"
        >
            <li
                v-for="(item, index) in form.items"
                :key="item.id"
                class="rounded-xl border border-border bg-card"
            >
                <div
                    :class="
                        cn(
                            'flex items-center gap-2 p-2 pr-3',
                            !item.visible && 'opacity-60',
                        )
                    "
                >
                    <button
                        type="button"
                        data-handle
                        class="grid size-9 shrink-0 cursor-grab place-items-center rounded-lg text-muted-foreground hover:bg-muted active:cursor-grabbing"
                        :aria-label="`Arrastrar «${item.label}» para reordenar`"
                        :disabled="readOnly"
                    >
                        <GripVertical class="size-4" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 font-medium">
                            <span class="truncate">{{ item.label }}</span>
                            <StatusBadge
                                v-if="!item.visible"
                                tone="neutral"
                                label="Oculto"
                                size="sm"
                            />
                            <StatusBadge
                                v-if="pageUnpublished(item)"
                                tone="warning"
                                label="Página sin publicar"
                                size="sm"
                            />
                        </p>
                        <p
                            class="flex items-center gap-1 truncate text-xs text-muted-foreground"
                        >
                            <Link2 class="size-3" aria-hidden="true" />
                            {{ describe(item) }}
                            <ArrowUpRight
                                v-if="item.newTab"
                                class="size-3"
                                aria-label="Abre en otra pestaña"
                            />
                        </p>
                        <InputError
                            :message="errorFor(`items.${index}`)"
                            class="mt-1"
                        />
                    </div>
                    <Switch
                        :model-value="item.visible"
                        :disabled="readOnly"
                        :aria-label="`Mostrar «${item.label}» en el menú`"
                        @update:model-value="(v: boolean) => (item.visible = v)"
                    />
                    <Button
                        v-if="item.children.length"
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :aria-expanded="!collapsed.has(item.id)"
                        :aria-label="`${collapsed.has(item.id) ? 'Mostrar' : 'Ocultar'} el submenú de ${item.label}`"
                        @click="toggleCollapsed(item.id)"
                    >
                        <ChevronDown
                            :class="
                                cn(
                                    'size-4 transition-transform',
                                    collapsed.has(item.id) && '-rotate-90',
                                )
                            "
                        />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="
                            readOnly ||
                            item.children.length >=
                                props.navigation.limits.children
                        "
                        :aria-label="`Agregar enlace al submenú de ${item.label}`"
                        :title="'Agregar al submenú'"
                        @click="addChild(index)"
                    >
                        <ListPlus class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        :disabled="readOnly"
                        :aria-label="`Editar ${item.label}`"
                        @click="openEditor({ index, child: null }, item)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 text-danger-ink"
                        :disabled="readOnly"
                        :aria-label="`Quitar ${item.label}`"
                        @click="removeItem(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <VueDraggable
                    v-if="item.children.length && !collapsed.has(item.id)"
                    v-model="item.children"
                    :animation="180"
                    handle="[data-handle]"
                    :disabled="readOnly"
                    tag="ol"
                    class="flex flex-col gap-1.5 border-t border-border bg-muted/40 p-2 pl-6"
                    ghost-class="nav-ghost"
                >
                    <li
                        v-for="(child, j) in item.children"
                        :key="child.id"
                        :class="
                            cn(
                                'flex items-center gap-2 rounded-lg border border-border bg-card p-1.5 pr-2',
                                !child.visible && 'opacity-60',
                            )
                        "
                    >
                        <CornerDownRight
                            class="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <button
                            type="button"
                            data-handle
                            class="grid size-8 shrink-0 cursor-grab place-items-center rounded-md text-muted-foreground hover:bg-muted"
                            :aria-label="`Arrastrar «${child.label}» para reordenar`"
                            :disabled="readOnly"
                        >
                            <GripVertical class="size-4" />
                        </button>
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span class="truncate">{{ child.label }}</span>
                                <EyeOff
                                    v-if="!child.visible"
                                    class="size-3.5 text-muted-foreground"
                                    aria-label="Oculto"
                                />
                                <StatusBadge
                                    v-if="pageUnpublished(child)"
                                    tone="warning"
                                    label="Sin publicar"
                                    size="sm"
                                />
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ describe(child) }}
                            </p>
                            <InputError
                                :message="
                                    errorFor(`items.${index}.children.${j}`)
                                "
                            />
                        </div>
                        <Switch
                            :model-value="child.visible"
                            :disabled="readOnly"
                            :aria-label="`Mostrar «${child.label}»`"
                            @update:model-value="
                                (v: boolean) => (child.visible = v)
                            "
                        />
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8"
                            :disabled="readOnly"
                            :aria-label="`Editar ${child.label}`"
                            @click="openEditor({ index, child: j }, child)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-8 text-danger-ink"
                            :disabled="readOnly"
                            :aria-label="`Quitar ${child.label}`"
                            @click="removeItem(index, j)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </li>
                </VueDraggable>
            </li>
        </VueDraggable>
        <EmptyState
            v-else
            compact
            :icon="MenuIcon"
            title="El menú está vacío"
            description="Agrega al menos un enlace para que los visitantes encuentren tus páginas."
        />

        <Button
            variant="outline"
            size="sm"
            class="mt-3"
            :disabled="
                readOnly || form.items.length >= props.navigation.limits.items
            "
            @click="addItem"
        >
            <Plus class="size-4" /> Agregar enlace
        </Button>

        <!-- Botón destacado -->
        <div
            class="mt-6 flex flex-col gap-3 rounded-xl border border-border p-4 sm:flex-row sm:items-center"
        >
            <div class="min-w-0 flex-1">
                <p class="font-medium">Botón destacado</p>
                <p class="text-sm text-muted-foreground">
                    «{{ form.cta.label }}» → {{ describe(form.cta) }}
                </p>
                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>)[
                            'cta.label'
                        ] ??
                        (form.errors as Record<string, string | undefined>)[
                            'cta.target'
                        ]
                    "
                />
            </div>
            <label class="flex items-center gap-2 text-sm">
                Mostrar
                <Switch v-model="form.cta.visible" :disabled="readOnly" />
            </label>
            <Button
                variant="outline"
                size="sm"
                :disabled="readOnly"
                @click="editCta"
            >
                <Pencil class="size-4" /> Editar botón
            </Button>
        </div>

        <p v-if="dirty" class="mt-4 text-sm text-warning-ink" role="status">
            Tienes cambios sin guardar en el menú.
        </p>

        <!-- Hoja de edición -->
        <FormSheet
            v-model:open="sheetOpen"
            :title="
                isCta
                    ? 'Botón destacado'
                    : creating
                      ? isChild
                          ? 'Agregar al submenú'
                          : 'Agregar enlace'
                      : 'Editar enlace'
            "
            description="Elige el texto y a dónde lleva. Los cambios se aplican al guardar el menú."
        >
            <form
                id="nav-item-form"
                class="grid gap-4"
                @submit.prevent="applyDraft"
            >
                <div class="grid gap-1.5">
                    <Label for="nav-label">Texto</Label>
                    <Input
                        id="nav-label"
                        v-model="draft.label"
                        maxlength="40"
                        placeholder="Ej. Cocinas"
                    />
                    <p class="text-xs text-muted-foreground tabular-nums">
                        {{ draft.label.length }}/40
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="nav-type">Lleva a</Label>
                    <Select
                        :model-value="draft.type"
                        @update:model-value="onTypeChange"
                    >
                        <SelectTrigger id="nav-type" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="builtin"
                                >Una sección del sitio</SelectItem
                            >
                            <SelectItem value="page"
                                >Una de tus páginas</SelectItem
                            >
                            <SelectItem value="url"
                                >Un enlace (interno o externo)</SelectItem
                            >
                            <SelectItem v-if="!isChild && !isCta" value="group"
                                >Nada: solo abre un submenú</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <div v-if="draft.type === 'builtin'" class="grid gap-1.5">
                    <Label for="nav-builtin">Sección</Label>
                    <Select
                        :model-value="draft.target"
                        @update:model-value="onBuiltinChange"
                    >
                        <SelectTrigger id="nav-builtin" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="b in props.navigation.builtins"
                                :key="b.value"
                                :value="b.value"
                                >{{ b.label }}
                                <span class="text-muted-foreground"
                                    >· {{ b.href }}</span
                                ></SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p
                        v-if="
                            ['catalogo', 'inspiracion'].includes(draft.target)
                        "
                        class="text-xs text-muted-foreground"
                    >
                        Siempre visible: mientras no publiques contenido, la
                        página muestra un aviso amable para solicitar asesoría.
                    </p>
                </div>
                <div v-else-if="draft.type === 'page'" class="grid gap-1.5">
                    <Label for="nav-page">Página</Label>
                    <Select
                        v-if="props.navigation.pages.length"
                        :model-value="draft.target"
                        @update:model-value="onPageChange"
                    >
                        <SelectTrigger id="nav-page" class="w-full"
                            ><SelectValue placeholder="Elige una página"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="p in props.navigation.pages"
                                :key="p.value"
                                :value="p.value"
                                >{{ p.label }}
                                <span class="text-muted-foreground"
                                    >·
                                    {{
                                        p.isPublished ? p.path : 'sin publicar'
                                    }}</span
                                ></SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <p v-else class="text-sm text-muted-foreground">
                        Aún no tienes páginas propias. Créalas en la pestaña
                        «Páginas».
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Las páginas sin publicar no aparecen en el menú hasta
                        que las publiques.
                    </p>
                </div>
                <div v-else-if="draft.type === 'url'" class="grid gap-1.5">
                    <Label for="nav-url">Enlace</Label>
                    <Input
                        id="nav-url"
                        v-model="draft.target"
                        maxlength="300"
                        placeholder="/cocinas o https://instagram.com/…"
                    />
                    <p class="text-xs text-muted-foreground">
                        Ruta interna que empiece con «/», o un enlace https://,
                        mailto: o tel:.
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    Agrega los enlaces del submenú con el botón
                    <ListPlus class="inline size-4" aria-hidden="true" /> de la
                    lista.
                </p>

                <label
                    v-if="draft.type !== 'group'"
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    Abrir en otra pestaña
                    <Switch v-model="draft.newTab" />
                </label>
                <label
                    v-if="!isCta"
                    class="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    Mostrar en el menú
                    <Switch v-model="draft.visible" />
                </label>

                <InputError :message="draftError ?? undefined" />
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="nav-item-form"
                    :disabled="!!draftError"
                    >{{ creating ? 'Agregar' : 'Aplicar' }}</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="¿Restablecer el menú?"
            description="La cabecera vuelve a los enlaces predeterminados: Catálogo, Inspiración, Cómo funciona y Preguntas frecuentes, con el botón «Solicitar asesoría»."
            confirm-label="Restablecer"
            loading-label="Restableciendo…"
            :confirm="resetMenu"
        />
    </SectionCard>
</template>

<style scoped>
:deep(.nav-ghost) {
    opacity: 0.45;
    outline: 2px dashed var(--primary);
    outline-offset: 2px;
}
</style>
