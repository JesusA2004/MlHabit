<script setup lang="ts">
import {
    CircleCheck,
    FileText,
    Film,
    ImageIcon,
    RotateCw,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { formatBytes } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { UploadItem } from './useUploadQueue';

/** Fila de un archivo en FileDropzone: nombre, peso, progreso y acciones. */
const props = defineProps<{ item: UploadItem }>();

const emit = defineEmits<{ cancel: []; retry: []; remove: [] }>();

const icon = computed(() => {
    const type = props.item.file.type;

    if (type.startsWith('image/')) {
        return ImageIcon;
    }

    return type.startsWith('video/') ? Film : FileText;
});

const statusText = computed(
    () =>
        ({
            queued: 'En espera',
            uploading: `Subiendo… ${props.item.percent}%`,
            completed: 'Subido',
            error: props.item.error ?? 'Error al subir',
            aborted: 'Cancelado',
        })[props.item.status],
);
</script>

<template>
    <li class="flex items-center gap-3 rounded-lg border bg-card px-3 py-2.5">
        <span
            :class="
                cn(
                    'grid size-9 shrink-0 place-items-center rounded-md',
                    props.item.status === 'error'
                        ? 'bg-danger-soft text-danger-ink'
                        : 'bg-muted text-muted-foreground',
                )
            "
            aria-hidden="true"
        >
            <TriangleAlert
                v-if="props.item.status === 'error'"
                class="size-4"
            />
            <component :is="icon" v-else class="size-4" />
        </span>

        <div class="min-w-0 flex-1">
            <div class="flex items-baseline justify-between gap-3">
                <p class="truncate text-sm font-medium text-foreground">
                    {{ props.item.file.name }}
                </p>
                <span
                    class="shrink-0 text-xs text-muted-foreground tabular-nums"
                >
                    {{ formatBytes(props.item.file.size) }}
                </span>
            </div>
            <Progress
                v-if="
                    props.item.status === 'uploading' ||
                    props.item.status === 'queued'
                "
                :model-value="props.item.percent"
                class="mt-2 h-1.5"
                :aria-label="`Progreso de ${props.item.file.name}`"
            />
            <p
                :class="
                    cn(
                        'mt-1 flex items-center gap-1 text-xs',
                        props.item.status === 'error'
                            ? 'text-danger-ink'
                            : 'text-muted-foreground',
                        props.item.status === 'completed' && 'text-success-ink',
                    )
                "
                aria-live="polite"
            >
                <CircleCheck
                    v-if="props.item.status === 'completed'"
                    class="size-3.5"
                    aria-hidden="true"
                />
                {{ statusText }}
            </p>
        </div>

        <div class="flex shrink-0 items-center gap-0.5">
            <Button
                v-if="
                    props.item.status === 'error' ||
                    props.item.status === 'aborted'
                "
                variant="ghost"
                size="icon"
                class="size-11 md:size-8"
                :aria-label="`Reintentar ${props.item.file.name}`"
                @click="emit('retry')"
            >
                <RotateCw />
            </Button>
            <Button
                v-if="
                    props.item.status === 'uploading' ||
                    props.item.status === 'queued'
                "
                variant="ghost"
                size="icon"
                class="size-11 md:size-8"
                :aria-label="`Cancelar ${props.item.file.name}`"
                @click="emit('cancel')"
            >
                <X />
            </Button>
            <Button
                v-else
                variant="ghost"
                size="icon"
                class="size-11 text-muted-foreground md:size-8"
                :aria-label="`Quitar ${props.item.file.name} de la lista`"
                @click="emit('remove')"
            >
                <X />
            </Button>
        </div>
    </li>
</template>
