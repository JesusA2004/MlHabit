<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Check, ImagePlus, Monitor, PenTool, RotateCcw, Save, Send, Smartphone, Tablet, Undo2, X } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import FormSection from '@/components/admin/FormSection.vue';
import MediaPickerDialog, { type PickedMedia } from '@/components/admin/MediaPickerDialog.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import SegmentedControl from '@/components/studio/controls/SegmentedControl.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { loadFonts } from '@/lib/fonts';
import apariencia from '@/routes/admin/apariencia';
import { contrastRatio } from '@/studio/color';
import type { BreadcrumbItem } from '@/types';

interface ThemeState {
    colors: { background: string; foreground: string; accent: string; muted: string };
    fonts: { display: string; body: string };
    buttons: { style: 'solid' | 'outline'; radius: 'none' | 'sm' | 'md' | 'lg' | 'full' };
    cards: { radius: 'none' | 'sm' | 'md' | 'lg' | 'xl' };
    logo: { logo_media_id: number | null; mark_media_id: number | null; favicon_media_id: number | null };
    site: { name: string; subtitle: string };
}

interface MediaRef {
    id: number;
    url: string | null;
}

const props = defineProps<{
    published: ThemeState;
    draft: ThemeState;
    hasDraft: boolean;
    media: { logo: MediaRef | null; mark: MediaRef | null; favicon: MediaRef | null };
    presets: { key: string; label: string; colors: ThemeState['colors']; fonts: ThemeState['fonts'] }[];
    defaults: ThemeState;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Apariencia', href: apariencia.edit() },
        ] as BreadcrumbItem[],
    },
});

const STUDIO_HREF = '/admin/studio';

// Listas blancas (coinciden con AppearanceRequest).
const DISPLAY_FONTS = ['Fraunces', 'Playfair Display', 'Cormorant Garamond'];
const BODY_FONTS = ['Instrument Sans', 'Inter', 'Work Sans'];

onMounted(() => loadFonts([...DISPLAY_FONTS, ...BODY_FONTS]));

const COLOR_FIELDS: { key: keyof ThemeState['colors']; label: string; help: string }[] = [
    { key: 'background', label: 'Fondo', help: 'Color base de todas las secciones.' },
    { key: 'foreground', label: 'Texto', help: 'Titulares y párrafos.' },
    { key: 'accent', label: 'Acento', help: 'Botones, eyebrows y enlaces.' },
    { key: 'muted', label: 'Secundario', help: 'Fechas, pies de foto, metadatos.' },
];

const buttonStyleOptions = [
    { value: 'solid', label: 'Sólido' },
    { value: 'outline', label: 'Contorno' },
];
const buttonRadiusOptions = [
    { value: 'none', label: 'Recto' },
    { value: 'sm', label: 'S' },
    { value: 'md', label: 'M' },
    { value: 'lg', label: 'L' },
    { value: 'full', label: 'Píldora' },
];
const cardRadiusOptions = [
    { value: 'none', label: 'Recto' },
    { value: 'sm', label: 'S' },
    { value: 'md', label: 'M' },
    { value: 'lg', label: 'L' },
    { value: 'xl', label: 'XL' },
];

const form = useForm<ThemeState>({
    colors: { ...props.draft.colors },
    fonts: { ...props.draft.fonts },
    buttons: { ...props.draft.buttons },
    cards: { ...props.draft.cards },
    logo: { ...props.draft.logo },
    site: { ...props.draft.site },
});

const logoUrl = ref(props.media.logo?.url ?? null);
const markUrl = ref(props.media.mark?.url ?? null);
const faviconUrl = ref(props.media.favicon?.url ?? null);

const logoPickerOpen = ref(false);
const markPickerOpen = ref(false);
const faviconPickerOpen = ref(false);

const discardOpen = ref(false);
const resetOpen = ref(false);

function onLogo(media: PickedMedia) {
    form.logo.logo_media_id = media.id;
    logoUrl.value = media.url;
}
function onMark(media: PickedMedia) {
    form.logo.mark_media_id = media.id;
    markUrl.value = media.url;
}
function onFavicon(media: PickedMedia) {
    form.logo.favicon_media_id = media.id;
    faviconUrl.value = media.url;
}

const brandAssets = computed(() => [
    {
        key: 'logo' as const,
        label: 'Logotipo',
        help: 'Encabezado del sitio. PNG o SVG con fondo transparente.',
        url: logoUrl.value,
        open: (): void => {
            logoPickerOpen.value = true;
        },
        clear: (): void => {
            form.logo.logo_media_id = null;
            logoUrl.value = null;
        },
        error: fieldError('logo.logo_media_id'),
    },
    {
        key: 'mark' as const,
        label: 'Isotipo',
        help: 'Versión compacta, cuadrada.',
        url: markUrl.value,
        open: (): void => {
            markPickerOpen.value = true;
        },
        clear: (): void => {
            form.logo.mark_media_id = null;
            markUrl.value = null;
        },
        error: fieldError('logo.mark_media_id'),
    },
    {
        key: 'favicon' as const,
        label: 'Favicon',
        help: 'Ícono de la pestaña. Cuadrado, 512 px.',
        url: faviconUrl.value,
        open: (): void => {
            faviconPickerOpen.value = true;
        },
        clear: (): void => {
            form.logo.favicon_media_id = null;
            faviconUrl.value = null;
        },
        error: fieldError('logo.favicon_media_id'),
    },
]);

function fieldError(path: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[path];
}

function applyPreset(preset: { colors: ThemeState['colors']; fonts: ThemeState['fonts'] }) {
    form.colors = { ...preset.colors };
    form.fonts = { ...preset.fonts };
}

function isActivePreset(preset: { colors: ThemeState['colors']; fonts: ThemeState['fonts'] }): boolean {
    const c = form.colors;

    return (
        (['background', 'foreground', 'accent', 'muted'] as const).every((k) => c[k].toLowerCase() === preset.colors[k].toLowerCase()) &&
        form.fonts.display === preset.fonts.display &&
        form.fonts.body === preset.fonts.body
    );
}

function fill(state: ThemeState) {
    form.colors = { ...state.colors };
    form.fonts = { ...state.fonts };
    form.buttons = { ...state.buttons };
    form.cards = { ...state.cards };
    form.logo = { ...state.logo };
    form.site = { ...state.site };
}

function syncMediaFromProps() {
    logoUrl.value = props.media.logo?.url ?? null;
    markUrl.value = props.media.mark?.url ?? null;
    faviconUrl.value = props.media.favicon?.url ?? null;
}

function saveDraft() {
    form.put(apariencia.update().url, { preserveScroll: true });
}

// Publicar envía los valores actuales del formulario para publicar
// exactamente lo que se ve en pantalla.
function doPublish() {
    form.post(apariencia.publicar().url, { preserveScroll: true });
}

function doDiscard() {
    router.post(
        apariencia.descartar().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                fill(props.published);
                form.defaults();
                syncMediaFromProps();
                discardOpen.value = false;
            },
        },
    );
}

function doReset() {
    router.post(
        apariencia.restablecer().url,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                fill(props.defaults);
                form.defaults();
                logoUrl.value = null;
                markUrl.value = null;
                faviconUrl.value = null;
                resetOpen.value = false;
            },
        },
    );
}

// --- Estado del borrador ---
const status = computed<'saving' | 'unsaved' | 'draft' | 'published'>(() => {
    if (form.processing) return 'saving';
    if (form.isDirty) return 'unsaved';

    return props.hasDraft ? 'draft' : 'published';
});

const statusText = computed(
    () =>
        ({
            saving: 'Guardando…',
            unsaved: 'Tienes cambios sin guardar en esta pantalla.',
            draft: 'Borrador guardado. El sitio público aún muestra la versión anterior.',
            published: 'El sitio público muestra exactamente esta apariencia.',
        })[status.value],
);

// --- Vista previa ---
const viewport = ref<'desktop' | 'tablet' | 'mobile'>('desktop');
const viewportWidth = computed(() => ({ desktop: '100%', tablet: '768px', mobile: '390px' })[viewport.value]);
const viewports = [
    { value: 'desktop' as const, label: 'Escritorio', icon: Monitor },
    { value: 'tablet' as const, label: 'Tableta', icon: Tablet },
    { value: 'mobile' as const, label: 'Móvil', icon: Smartphone },
];

const radiusRem: Record<string, number> = { none: 0, sm: 0.25, md: 0.5, lg: 0.75, xl: 1, full: 9999 };
const HEX = /^#[0-9A-Fa-f]{6}$/;

/** Colores de la vista previa: vienen del formulario (elección de la persona). */
const previewStyle = computed(() => {
    const cardRem = radiusRem[form.cards.radius] ?? 0.75;
    const buttonRem = radiusRem[form.buttons.radius] ?? 0.5;
    const safe = (hex: string, fallback: string) => (HEX.test(hex) ? hex : fallback);

    return {
        '--p-bg': safe(form.colors.background, props.published.colors.background),
        '--p-fg': safe(form.colors.foreground, props.published.colors.foreground),
        '--p-accent': safe(form.colors.accent, props.published.colors.accent),
        '--p-muted': safe(form.colors.muted, props.published.colors.muted),
        '--p-surface': 'color-mix(in srgb, var(--p-fg) 7%, var(--p-bg))',
        '--p-line': 'color-mix(in srgb, var(--p-fg) 12%, transparent)',
        '--p-card-radius': cardRem >= 9999 ? '9999px' : `${cardRem}rem`,
        '--p-button-radius': buttonRem >= 9999 ? '9999px' : `${buttonRem}rem`,
        '--p-display': `"${form.fonts.display}", serif`,
        background: 'var(--p-bg)',
        color: 'var(--p-fg)',
        fontFamily: `"${form.fonts.body}", sans-serif`,
        maxWidth: viewportWidth.value,
    } as Record<string, string>;
});

const primaryButtonStyle = computed(() => ({
    borderRadius: 'var(--p-button-radius)',
    background: form.buttons.style === 'solid' ? 'var(--p-accent)' : 'transparent',
    color: form.buttons.style === 'solid' ? 'var(--p-bg)' : 'var(--p-accent)',
    border: '1px solid var(--p-accent)',
}));

// --- Contraste (WCAG) ---
function safeContrast(a: string, b: string): number | null {
    return HEX.test(a) && HEX.test(b) ? contrastRatio(a, b) : null;
}
const contrastChecks = computed(() => [
    { label: 'Texto sobre fondo', ratio: safeContrast(form.colors.background, form.colors.foreground), min: 4.5 },
    { label: 'Acento sobre fondo', ratio: safeContrast(form.colors.background, form.colors.accent), min: 3 },
    { label: 'Secundario sobre fondo', ratio: safeContrast(form.colors.background, form.colors.muted), min: 4.5 },
]);
</script>

<template>
    <Head title="Apariencia" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader
            eyebrow="Sitio web"
            title="Apariencia"
            description="La identidad visual del sitio público: colores, tipografías, botones, tarjetas y logotipos. Prueba sin miedo: nada cambia en público hasta que publiques."
        />

        <!-- Barra de borrador / publicación -->
        <div class="sticky top-14 z-20 -mx-1 flex flex-col gap-3 rounded-xl border bg-card/95 p-3 shadow-sm backdrop-blur md:flex-row md:items-center md:justify-between">
            <div class="flex min-w-0 items-center gap-3 px-1" role="status" aria-live="polite">
                <span
                    v-if="status === 'unsaved' || status === 'saving'"
                    class="inline-flex h-6 items-center gap-1.5 rounded-full bg-warning/15 px-2.5 text-[11px] font-semibold whitespace-nowrap text-warning"
                >
                    <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
                    {{ status === 'saving' ? 'Guardando' : 'Sin guardar' }}
                </span>
                <StatusBadge v-else :status="status === 'draft' ? 'changes' : 'published'" />
                <p class="truncate text-xs text-muted-foreground">{{ statusText }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="ghost" size="sm" class="pointer-coarse:min-h-11" @click="resetOpen = true"><RotateCcw class="size-4" /> Restablecer original</Button>
                <Button variant="outline" size="sm" class="pointer-coarse:min-h-11" :disabled="!hasDraft || form.processing" @click="discardOpen = true">
                    <Undo2 class="size-4" /> Descartar borrador
                </Button>
                <Button variant="outline" size="sm" class="pointer-coarse:min-h-11" :disabled="form.processing || !form.isDirty" :aria-busy="form.processing" @click="saveDraft">
                    <Save class="size-4" /> Guardar borrador
                </Button>
                <Button size="sm" class="pointer-coarse:min-h-11" :disabled="form.processing || (!hasDraft && !form.isDirty)" :aria-busy="form.processing" @click="doPublish">
                    <Send class="size-4" /> Publicar
                </Button>
            </div>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)] xl:gap-8">
            <!-- Controles -->
            <div class="flex min-w-0 flex-col gap-6">
                <aside class="flex items-start gap-3 rounded-xl border border-brand/30 bg-brand/5 p-4">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand/15 text-brand"><PenTool class="size-4" /></span>
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold">Tokens globales del sitio</p>
                        <p class="mt-0.5 text-muted-foreground">
                            Colores y tipografías se aplican a cada bloque de Studio que use «Fondo», «Texto», «Acento» o «Titulares». Cámbialos aquí una vez y todo el sitio se actualiza.
                        </p>
                        <Link :href="STUDIO_HREF" class="mt-1 inline-flex min-h-9 items-center gap-1 font-medium text-brand hover:underline pointer-coarse:min-h-11">
                            Ver en Studio <span aria-hidden="true">→</span>
                        </Link>
                    </div>
                </aside>

                <div class="rounded-xl border bg-card px-5 [&>section]:lg:grid-cols-1 [&>section]:lg:gap-4">
                    <FormSection title="Plantillas" description="Combinaciones de color y tipografía probadas. Aplica una y ajusta lo que quieras.">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <button
                                v-for="preset in presets"
                                :key="preset.key"
                                type="button"
                                class="flex min-h-14 items-center gap-3 rounded-lg border p-2.5 text-left transition-colors hover:border-brand focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                :class="isActivePreset(preset) && 'border-brand ring-1 ring-brand/40'"
                                :aria-pressed="isActivePreset(preset)"
                                @click="applyPreset(preset)"
                            >
                                <span class="grid size-10 shrink-0 grid-cols-2 overflow-hidden rounded-md border" aria-hidden="true">
                                    <span :style="{ background: preset.colors.background }" />
                                    <span :style="{ background: preset.colors.accent }" />
                                    <span :style="{ background: preset.colors.foreground }" />
                                    <span :style="{ background: preset.colors.muted }" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium">{{ preset.label }}</span>
                                    <span class="block truncate text-xs text-muted-foreground">{{ preset.fonts.display }} · {{ preset.fonts.body }}</span>
                                </span>
                                <Check v-if="isActivePreset(preset)" class="size-4 shrink-0 text-brand" />
                            </button>
                        </div>
                    </FormSection>

                    <FormSection title="Colores" description="Cuatro colores para todo el sitio. Ej.: fondo carbón #0D0E10 con acento dorado #C7AA7D.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div v-for="field in COLOR_FIELDS" :key="field.key" class="grid content-start gap-1.5">
                                <Label :for="`color-${field.key}`">{{ field.label }}</Label>
                                <div class="flex items-center gap-2">
                                    <input
                                        v-model="form.colors[field.key]"
                                        type="color"
                                        :aria-label="`Selector de color: ${field.label}`"
                                        class="h-10 w-12 shrink-0 cursor-pointer rounded-md border bg-background p-1"
                                    />
                                    <Input
                                        :id="`color-${field.key}`"
                                        v-model="form.colors[field.key]"
                                        class="font-mono text-xs uppercase"
                                        maxlength="7"
                                        :aria-invalid="!!fieldError(`colors.${field.key}`)"
                                    />
                                </div>
                                <p class="text-xs text-muted-foreground">{{ field.help }}</p>
                                <InputError :message="fieldError(`colors.${field.key}`)" />
                            </div>
                        </div>
                        <ul class="grid gap-1.5 rounded-lg bg-muted/40 p-3" aria-label="Revisión de contraste">
                            <li v-for="check in contrastChecks" :key="check.label" class="flex items-center justify-between gap-3 text-xs">
                                <span class="text-muted-foreground">{{ check.label }}</span>
                                <span
                                    v-if="check.ratio !== null"
                                    class="inline-flex items-center gap-1.5 font-medium tabular-nums"
                                    :class="check.ratio >= check.min ? 'text-success' : 'text-destructive'"
                                >
                                    {{ check.ratio.toFixed(2) }}:1 · {{ check.ratio >= check.min ? 'Legible (AA)' : `Bajo, mínimo ${check.min}:1` }}
                                </span>
                                <span v-else class="text-muted-foreground">Color inválido</span>
                            </li>
                        </ul>
                    </FormSection>

                    <FormSection title="Tipografías" description="Una para titulares y otra para texto corrido. Se ven en vivo en la vista previa.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid content-start gap-1.5">
                                <Label for="font-display">Titulares</Label>
                                <Select v-model="form.fonts.display">
                                    <SelectTrigger id="font-display" class="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="f in DISPLAY_FONTS" :key="f" :value="f">
                                            <span :style="{ fontFamily: `&quot;${f}&quot;, serif` }">{{ f }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="truncate text-2xl leading-tight" :style="{ fontFamily: `&quot;${form.fonts.display}&quot;, serif` }" aria-hidden="true">Sin tapujos</p>
                                <InputError :message="fieldError('fonts.display')" />
                            </div>
                            <div class="grid content-start gap-1.5">
                                <Label for="font-body">Cuerpo</Label>
                                <Select v-model="form.fonts.body">
                                    <SelectTrigger id="font-body" class="w-full"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="f in BODY_FONTS" :key="f" :value="f">
                                            <span :style="{ fontFamily: `&quot;${f}&quot;, sans-serif` }">{{ f }}</span>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="line-clamp-2 text-sm text-muted-foreground" :style="{ fontFamily: `&quot;${form.fonts.body}&quot;, sans-serif` }" aria-hidden="true">
                                    Negocios, hábitos y vida contados con claridad.
                                </p>
                                <InputError :message="fieldError('fonts.body')" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Botones y tarjetas" description="Forma de los botones y esquinas de tarjetas e imágenes en todo el sitio.">
                        <div class="grid gap-1.5">
                            <span id="btn-style-label" class="text-sm font-medium">Estilo de botón</span>
                            <SegmentedControl v-model="form.buttons.style" label="Estilo de botón" :options="buttonStyleOptions" class="[&>button]:min-h-9 pointer-coarse:[&>button]:min-h-11" />
                        </div>
                        <div class="grid gap-1.5">
                            <span class="text-sm font-medium">Esquinas de botón</span>
                            <SegmentedControl v-model="form.buttons.radius" label="Esquinas de botón" :options="buttonRadiusOptions" class="[&>button]:min-h-9 pointer-coarse:[&>button]:min-h-11" />
                        </div>
                        <div class="grid gap-1.5">
                            <span class="text-sm font-medium">Esquinas de tarjeta</span>
                            <SegmentedControl v-model="form.cards.radius" label="Esquinas de tarjeta" :options="cardRadiusOptions" class="[&>button]:min-h-9 pointer-coarse:[&>button]:min-h-11" />
                        </div>
                    </FormSection>

                    <FormSection title="Identidad" description="Nombre y firma que acompañan al logotipo y aparecen en el título de la pestaña.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid content-start gap-1.5">
                                <Label for="site-name">Nombre del sitio</Label>
                                <Input id="site-name" v-model="form.site.name" required maxlength="60" placeholder="MR. INSIGHT" :aria-invalid="!!fieldError('site.name')" />
                                <InputError :message="fieldError('site.name')" />
                            </div>
                            <div class="grid content-start gap-1.5">
                                <Label for="site-subtitle">Subtítulo</Label>
                                <Input id="site-subtitle" v-model="form.site.subtitle" maxlength="80" placeholder="Ej.: Por Zuriel Ávila" />
                                <InputError :message="fieldError('site.subtitle')" />
                            </div>
                        </div>
                    </FormSection>

                    <FormSection title="Logotipos" description="Elige imágenes de la biblioteca. Si no hay logotipo se muestra el nombre del sitio.">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div v-for="asset in brandAssets" :key="asset.key" class="grid content-start gap-1.5">
                                <span class="text-sm font-medium">{{ asset.label }}</span>
                                <button
                                    type="button"
                                    class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/40 transition-colors hover:border-brand focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none max-sm:aspect-[3/1]"
                                    :aria-label="asset.url ? `Cambiar ${asset.label.toLowerCase()}` : `Elegir ${asset.label.toLowerCase()}`"
                                    @click="asset.open"
                                >
                                    <img v-if="asset.url" :src="asset.url" alt="" class="size-full object-contain p-3" />
                                    <span v-else class="flex flex-col items-center gap-1 text-xs text-muted-foreground"><ImagePlus class="size-5" /> Elegir</span>
                                </button>
                                <p class="text-xs text-muted-foreground">{{ asset.help }}</p>
                                <Button v-if="asset.url" type="button" variant="ghost" size="sm" class="justify-start pointer-coarse:min-h-11" @click="asset.clear">
                                    <X class="size-4" /> Quitar
                                </Button>
                                <InputError :message="asset.error" />
                            </div>
                        </div>
                    </FormSection>
                </div>
            </div>

            <!-- Vista previa -->
            <div class="flex min-w-0 flex-col gap-3 lg:sticky lg:top-36">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Vista previa en vivo</h2>
                        <p class="text-xs text-muted-foreground">Refleja la pantalla tal como la editas, antes de guardar.</p>
                    </div>
                    <div class="flex gap-1 rounded-lg border bg-muted/50 p-0.5" role="radiogroup" aria-label="Tamaño de la vista previa">
                        <button
                            v-for="v in viewports"
                            :key="v.value"
                            type="button"
                            role="radio"
                            :aria-checked="viewport === v.value"
                            :aria-label="v.label"
                            :title="v.label"
                            class="grid size-8 place-items-center rounded-md text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none pointer-coarse:size-11"
                            :class="viewport === v.value && 'bg-background text-foreground shadow-xs ring-1 ring-brand/40'"
                            @click="viewport = v.value"
                        >
                            <component :is="v.icon" class="size-4" />
                        </button>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border bg-muted/40 p-3 md:p-5">
                    <!-- Pestaña del navegador -->
                    <div class="mx-auto flex items-center gap-2 rounded-t-lg border border-b-0 bg-background px-3 py-2 transition-all" :style="{ maxWidth: viewportWidth }">
                        <span class="flex gap-1" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-muted-foreground/30" />
                            <span class="size-2.5 rounded-full bg-muted-foreground/30" />
                            <span class="size-2.5 rounded-full bg-muted-foreground/30" />
                        </span>
                        <span class="ml-2 flex min-w-0 items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-[11px] text-muted-foreground">
                            <img v-if="faviconUrl" :src="faviconUrl" alt="" class="size-3.5 shrink-0 object-contain" />
                            <span v-else class="size-3.5 shrink-0 rounded-sm" :style="{ background: previewStyle['--p-accent'] }" />
                            <span class="truncate">{{ form.site.name || 'Sitio' }}</span>
                        </span>
                    </div>

                    <div
                        class="@container mx-auto overflow-hidden rounded-b-lg shadow-xl transition-all"
                        :style="previewStyle"
                        aria-label="Vista previa del sitio público"
                    >
                        <!-- Encabezado -->
                        <div class="flex items-center justify-between gap-4 border-b px-5 py-4 @lg:px-8" :style="{ borderColor: 'var(--p-line)' }">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <img v-if="logoUrl" :src="logoUrl" alt="" class="h-7 w-auto max-w-32 object-contain" />
                                <img v-else-if="markUrl" :src="markUrl" alt="" class="size-7 object-contain" />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm leading-tight font-semibold tracking-wide" :style="{ fontFamily: 'var(--p-display)' }">{{ form.site.name }}</span>
                                    <span v-if="form.site.subtitle" class="block truncate text-[9px] tracking-[0.2em] uppercase" :style="{ color: 'var(--p-muted)' }">{{ form.site.subtitle }}</span>
                                </span>
                            </div>
                            <nav class="hidden items-center gap-5 text-xs @md:flex" aria-hidden="true">
                                <span>Tips</span>
                                <span>Mi historia</span>
                                <span :style="{ color: 'var(--p-accent)' }">Mi proceso</span>
                            </nav>
                            <span class="flex flex-col gap-1 @md:hidden" aria-hidden="true">
                                <span class="block h-px w-5" :style="{ background: 'var(--p-fg)' }" />
                                <span class="block h-px w-5" :style="{ background: 'var(--p-fg)' }" />
                            </span>
                        </div>

                        <!-- Hero -->
                        <div class="grid gap-6 px-5 py-10 @lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] @lg:items-end @lg:px-8 @lg:py-14">
                            <div>
                                <p class="text-[10px] font-semibold tracking-[0.24em] uppercase" :style="{ color: 'var(--p-accent)' }">Negocios · Hábitos · Vida</p>
                                <p class="mt-3 text-4xl leading-[1.02] font-semibold tracking-tight @lg:text-5xl" :style="{ fontFamily: 'var(--p-display)' }">Ideas claras, sin tapujos.</p>
                                <p class="mt-4 max-w-md text-sm leading-relaxed" :style="{ color: 'var(--p-muted)' }">
                                    Tips prácticos para emprender, ordenar tu dinero y construir hábitos que sí se quedan.
                                </p>
                                <div class="mt-6 flex flex-wrap gap-3" aria-hidden="true">
                                    <span class="inline-flex h-10 items-center px-5 text-xs font-semibold" :style="primaryButtonStyle">Leer los tips</span>
                                    <span class="inline-flex h-10 items-center px-2 text-xs font-medium underline underline-offset-4" :style="{ textDecorationColor: 'var(--p-accent)' }">Mi historia</span>
                                </div>
                            </div>
                            <div class="hidden aspect-[4/5] @lg:block" :style="{ background: 'var(--p-surface)', borderRadius: 'var(--p-card-radius)' }">
                                <div class="flex size-full items-end p-4">
                                    <span class="h-px w-12" :style="{ background: 'var(--p-accent)' }" />
                                </div>
                            </div>
                        </div>

                        <!-- Tarjetas -->
                        <div class="grid gap-4 px-5 pb-10 @md:grid-cols-2 @2xl:grid-cols-3 @lg:px-8">
                            <div
                                v-for="(card, i) in ['Cómo fijar tu primer precio', 'El hábito de las 5 de la mañana', 'Ahorro sin sufrir']"
                                :key="card"
                                class="overflow-hidden border"
                                :class="i === 2 && 'hidden @2xl:block'"
                                :style="{ background: 'var(--p-surface)', borderColor: 'var(--p-line)', borderRadius: 'var(--p-card-radius)' }"
                            >
                                <div class="aspect-[3/2]" :style="{ background: 'color-mix(in srgb, var(--p-fg) 12%, var(--p-bg))' }" />
                                <div class="p-4">
                                    <p class="text-[9px] font-semibold tracking-[0.2em] uppercase" :style="{ color: 'var(--p-accent)' }">{{ ['Negocios', 'Hábitos', 'Finanzas'][i] }}</p>
                                    <p class="mt-1.5 text-base leading-snug font-medium" :style="{ fontFamily: 'var(--p-display)' }">{{ card }}</p>
                                    <p class="mt-2 text-[11px]" :style="{ color: 'var(--p-muted)' }">5 min de lectura</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">Maqueta ilustrativa con textos de ejemplo. «Publicar» aplica estos valores al sitio real y a todos los bloques de Studio.</p>
            </div>
        </div>
    </div>

    <MediaPickerDialog v-model:open="logoPickerOpen" type="image" @select="onLogo" />
    <MediaPickerDialog v-model:open="markPickerOpen" type="image" @select="onMark" />
    <MediaPickerDialog v-model:open="faviconPickerOpen" type="image" @select="onFavicon" />

    <AlertDialog v-model:open="discardOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Descartar el borrador?</AlertDialogTitle>
                <AlertDialogDescription>
                    Se perderán los cambios sin publicar y el formulario volverá a la última versión publicada.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction @click="doDiscard">Descartar</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <AlertDialog v-model:open="resetOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>¿Restablecer a los valores originales?</AlertDialogTitle>
                <AlertDialogDescription>
                    Esto publica de inmediato la paleta carbón y dorado original, reemplazando lo que esté publicado ahora.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-white hover:bg-destructive/90" @click="doReset">Restablecer</AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
