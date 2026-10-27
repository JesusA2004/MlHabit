<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import {
    Drawer,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
} from '@/components/ui/drawer';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

/**
 * Contenedor para formularios de alta/edición: hoja lateral en escritorio
 * y drawer inferior en móvil. El formulario va en el slot por defecto y los
 * botones en `#footer` (quedan fijos abajo).
 */
const open = defineModel<boolean>('open', { default: false });

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        size?: 'md' | 'lg' | 'xl';
        class?: HTMLAttributes['class'];
    }>(),
    { size: 'md' },
);

const isDesktop = useMediaQuery('(min-width: 768px)');

const widths = {
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-2xl',
} as const;
</script>

<template>
    <Sheet v-if="isDesktop" v-model:open="open">
        <SheetContent
            side="right"
            :class="
                cn(
                    'flex w-full flex-col gap-0 p-0',
                    widths[props.size],
                    props.class,
                )
            "
        >
            <SheetHeader class="border-b px-6 py-5 text-left">
                <SheetTitle
                    class="font-display text-lg font-semibold tracking-tight"
                    >{{ props.title }}</SheetTitle
                >
                <SheetDescription v-if="props.description">{{
                    props.description
                }}</SheetDescription>
            </SheetHeader>
            <div class="flex-1 overflow-y-auto px-6 py-5">
                <slot />
            </div>
            <SheetFooter
                v-if="$slots.footer"
                class="flex-row justify-end gap-2 border-t bg-muted/40 px-6 py-4"
            >
                <slot name="footer" />
            </SheetFooter>
        </SheetContent>
    </Sheet>

    <Drawer v-else v-model:open="open">
        <DrawerContent :class="cn('max-h-[92svh]', props.class)">
            <DrawerHeader class="px-4 pt-2 pb-3 text-left">
                <DrawerTitle
                    class="font-display text-lg font-semibold tracking-tight"
                    >{{ props.title }}</DrawerTitle
                >
                <DrawerDescription v-if="props.description">{{
                    props.description
                }}</DrawerDescription>
            </DrawerHeader>
            <div class="flex-1 overflow-y-auto px-4 pb-4">
                <slot />
            </div>
            <DrawerFooter
                v-if="$slots.footer"
                class="flex-col-reverse gap-2 border-t bg-muted/40 px-4 py-3 pb-safe [&>*]:h-11 [&>*]:w-full"
            >
                <slot name="footer" />
            </DrawerFooter>
        </DrawerContent>
    </Drawer>
</template>
