<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Palette, ShieldCheck, UserRound } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import AdminPageHeader from '@/components/admin/AdminPageHeader.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

type SettingsNavItem = NavItem & { icon: LucideIcon; hint: string };

const sidebarNavItems: SettingsNavItem[] = [
    { title: 'Perfil', hint: 'Nombre, correo y cuenta', href: editProfile(), icon: UserRound },
    { title: 'Seguridad', hint: 'Contraseña de acceso', href: editSecurity(), icon: ShieldCheck },
    { title: 'Apariencia', hint: 'Tema claro u oscuro', href: editAppearance(), icon: Palette },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8">
        <AdminPageHeader eyebrow="Sistema" title="Configuración" description="Tu cuenta, la seguridad de acceso y cómo se ve el panel en este dispositivo." />

        <div class="grid gap-6 lg:grid-cols-[232px_minmax(0,1fr)] lg:gap-10">
            <!-- Segmentado en móvil / tablet, lista vertical en escritorio -->
            <nav aria-label="Secciones de configuración" class="lg:sticky lg:top-6 lg:self-start">
                <ul class="flex gap-1 rounded-lg border bg-muted/60 p-1 lg:flex-col lg:gap-0.5 lg:border-0 lg:bg-transparent lg:p-0">
                    <li v-for="item in sidebarNavItems" :key="toUrl(item.href)" class="min-w-0 flex-1 lg:flex-none">
                        <Link
                            :href="item.href"
                            :aria-current="isCurrentOrParentUrl(item.href) ? 'page' : undefined"
                            :class="
                                cn(
                                    'group flex min-h-11 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground lg:min-h-12 lg:justify-start lg:gap-3 lg:hover:bg-accent/70',
                                    isCurrentOrParentUrl(item.href) && 'bg-background text-foreground shadow-xs ring-1 ring-border lg:bg-card',
                                )
                            "
                        >
                            <component
                                :is="item.icon"
                                :class="cn('size-4 shrink-0', isCurrentOrParentUrl(item.href) ? 'text-brand' : 'text-muted-foreground group-hover:text-foreground')"
                            />
                            <span class="flex min-w-0 flex-col leading-tight">
                                <span class="truncate">{{ item.title }}</span>
                                <span class="hidden truncate text-xs font-normal text-muted-foreground lg:block">{{ item.hint }}</span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="flex min-w-0 max-w-4xl flex-col gap-6">
                <slot />
            </div>
        </div>
    </div>
</template>
