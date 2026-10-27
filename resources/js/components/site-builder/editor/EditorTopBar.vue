<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    CloudOff,
    Eye,
    History,
    Loader2,
    Monitor,
    Redo2,
    Smartphone,
    Tablet,
    Undo2,
    UploadCloud,
} from '@lucide/vue';
import { useNow } from '@vueuse/core';
import { computed, inject } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Device } from '../types';
import { editorKey } from './keys';

const props = defineProps<{
    pageName: string;
    previewUrl: string;
    canPublish: boolean;
}>();

const emit = defineEmits<{ publish: []; history: [] }>();

const editor = inject(editorKey)!;
const now = useNow({ interval: 15000 });

const status = computed(() => {
    switch (editor.saveState.value) {
        case 'saving':
            return {
                icon: Loader2,
                text: 'Guardando…',
                tone: 'text-muted-foreground',
                spin: true,
            };
        case 'dirty':
            return {
                icon: UploadCloud,
                text: 'Cambios sin guardar',
                tone: 'text-muted-foreground',
                spin: false,
            };
        case 'error':
            return {
                icon: AlertTriangle,
                text: 'No se pudo guardar',
                tone: 'text-danger-ink',
                spin: false,
            };
        case 'conflict':
            return {
                icon: CloudOff,
                text: 'Hay una versión más reciente',
                tone: 'text-warning-ink',
                spin: false,
            };
        default: {
            const at = editor.savedAt.value;
            const seconds = at
                ? Math.max(0, (now.value.getTime() - at.getTime()) / 1000)
                : null;
            const text =
                seconds === null
                    ? 'Borrador guardado'
                    : seconds < 45
                      ? 'Guardado hace un momento'
                      : seconds < 3600
                        ? `Guardado hace ${Math.round(seconds / 60)} min`
                        : `Guardado a las ${at!.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}`;

            return {
                icon: Check,
                text,
                tone: 'text-muted-foreground',
                spin: false,
            };
        }
    }
});

const device = computed({
    get: () => editor.device.value,
    set: (value: Device | undefined) => {
        if (value) editor.device.value = value;
    },
});

const zoomValue = computed({
    get: () => String(editor.zoomMode.value),
    set: (value: string) => {
        editor.zoomMode.value = value === 'fit' ? 'fit' : Number(value);
    },
});

const zoomLabel = computed(() => `${Math.round(editor.zoom.value * 100)} %`);
const isMac =
    typeof navigator !== 'undefined' && /Mac/.test(navigator.platform);
const mod = isMac ? '⌘' : 'Ctrl';
</script>

<template>
    <header
        class="flex h-14 shrink-0 items-center gap-3 border-b border-border bg-card px-3 text-foreground"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button as-child variant="ghost" size="icon" class="size-9">
                        <Link
                            href="/admin/sitio"
                            aria-label="Volver a Sitio web"
                        >
                            <ArrowLeft class="size-4" />
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Volver a Sitio web</TooltipContent>
            </Tooltip>
            <div class="min-w-0">
                <p
                    class="truncate font-display text-[15px] leading-tight font-semibold"
                >
                    {{ props.pageName }}
                </p>
                <p
                    class="flex items-center gap-1.5 text-xs leading-tight"
                    :class="status.tone"
                    role="status"
                    aria-live="polite"
                >
                    <component
                        :is="status.icon"
                        class="size-3"
                        :class="status.spin && 'animate-spin'"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ status.text }}</span>
                    <span
                        v-if="
                            editor.draft.hasChanges &&
                            editor.saveState.value === 'saved'
                        "
                        class="hidden items-center gap-1.5 text-warning-ink sm:inline-flex"
                    >
                        <span aria-hidden="true">·</span> Sin publicar
                    </span>
                </p>
            </div>
        </div>

        <ToggleGroup
            v-model="device"
            type="single"
            variant="outline"
            size="sm"
            aria-label="Vista del dispositivo"
            class="hidden md:flex"
        >
            <ToggleGroupItem value="desktop" aria-label="Escritorio"
                ><Monitor class="size-4"
            /></ToggleGroupItem>
            <ToggleGroupItem value="tablet" aria-label="Tableta"
                ><Tablet class="size-4"
            /></ToggleGroupItem>
            <ToggleGroupItem value="mobile" aria-label="Móvil"
                ><Smartphone class="size-4"
            /></ToggleGroupItem>
        </ToggleGroup>

        <div class="flex flex-1 items-center justify-end gap-1.5">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        :disabled="!editor.past.value.length"
                        aria-label="Deshacer"
                        @click="editor.undo()"
                    >
                        <Undo2 class="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent
                    >Deshacer
                    <kbd class="ml-1 opacity-70"
                        >{{ mod }}+Z</kbd
                    ></TooltipContent
                >
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-9"
                        :disabled="!editor.future.value.length"
                        aria-label="Rehacer"
                        @click="editor.redo()"
                    >
                        <Redo2 class="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent
                    >Rehacer
                    <kbd class="ml-1 opacity-70"
                        >{{ mod }}+Shift+Z</kbd
                    ></TooltipContent
                >
            </Tooltip>

            <Select v-model="zoomValue">
                <SelectTrigger
                    size="sm"
                    class="hidden w-[5.5rem] lg:flex"
                    aria-label="Zoom"
                >
                    <SelectValue>{{ zoomLabel }}</SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="fit">Ajustar</SelectItem>
                    <SelectItem value="0.5">50 %</SelectItem>
                    <SelectItem value="0.75">75 %</SelectItem>
                    <SelectItem value="1">100 %</SelectItem>
                </SelectContent>
            </Select>

            <span
                class="mx-1 hidden h-6 w-px bg-border sm:block"
                aria-hidden="true"
            />

            <Button
                variant="ghost"
                size="sm"
                class="hidden sm:inline-flex"
                @click="emit('history')"
            >
                <History class="size-4" /> Historial
            </Button>
            <Button
                as="a"
                :href="props.previewUrl"
                target="_blank"
                rel="noopener"
                variant="outline"
                size="sm"
            >
                <Eye class="size-4" /> Vista previa
            </Button>
            <Tooltip :disabled="props.canPublish">
                <TooltipTrigger as-child>
                    <span>
                        <Button
                            size="sm"
                            :disabled="!props.canPublish"
                            @click="emit('publish')"
                        >
                            Publicar
                        </Button>
                    </span>
                </TooltipTrigger>
                <TooltipContent v-if="!props.canPublish"
                    >Necesitas el permiso para publicar
                    contenido</TooltipContent
                >
            </Tooltip>
        </div>
    </header>
</template>
