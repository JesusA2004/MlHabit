<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleHelp, Compass, LifeBuoy, Route } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { TourArea } from './types';
import { useTourActions } from './useTourActions';

/**
 * Botón "?" de las cabeceras (panel, portal y app de técnicos): recorrido
 * general, recorrido de la pantalla actual y centro de ayuda.
 */
const props = defineProps<{ area: TourArea }>();

const { general, section, startGeneral, startSection } = useTourActions(
    props.area,
);

const helpHref = computed<string | null>(() =>
    props.area === 'admin'
        ? '/admin/ayuda'
        : props.area === 'portal'
          ? '/portal/ayuda'
          : null,
);

// Se inicia tras cerrar el menú, para que no le devuelva el foco al botón
// encima de la tarjeta del recorrido.
function later(fn: () => void): void {
    window.setTimeout(fn, 60);
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                data-tour="help-button"
                aria-label="Ayuda y recorridos guiados"
                class="text-muted-foreground hover:text-foreground"
            >
                <CircleHelp class="size-5" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" :side-offset="6" class="w-72">
            <DropdownMenuLabel class="text-xs font-medium text-muted-foreground"
                >Ayuda</DropdownMenuLabel
            >
            <DropdownMenuItem class="py-2" @select="later(startGeneral)">
                <Compass />
                <span class="flex min-w-0 flex-col">
                    <span>{{ general.title }}</span>
                    <span class="truncate text-xs text-muted-foreground">{{
                        general.description
                    }}</span>
                </span>
            </DropdownMenuItem>
            <DropdownMenuItem
                class="py-2"
                :disabled="!section"
                @select="later(startSection)"
            >
                <Route />
                <span class="flex min-w-0 flex-col">
                    <span>Recorrido de esta sección</span>
                    <span class="truncate text-xs text-muted-foreground">{{
                        section
                            ? section.title.replace(/^Recorrido: /, '')
                            : 'Esta pantalla aún no tiene recorrido'
                    }}</span>
                </span>
            </DropdownMenuItem>
            <template v-if="helpHref">
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child class="py-2">
                    <Link :href="helpHref">
                        <LifeBuoy />
                        Centro de ayuda
                    </Link>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
