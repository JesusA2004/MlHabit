<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ShieldCheck, UserRound } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import { accountLinks, isActivePath } from '@/lib/navigation';
import { cn } from '@/lib/utils';

type SettingsNavItem = {
    title: string;
    hint: string;
    href: string;
    icon: LucideIcon;
};

const items: SettingsNavItem[] = [
    {
        title: 'Perfil',
        hint: 'Nombre y correo',
        href: accountLinks.profile,
        icon: UserRound,
    },
    {
        title: 'Seguridad',
        hint: 'Contraseña y verificación',
        href: accountLinks.security,
        icon: ShieldCheck,
    },
];

const page = usePage();
const active = (href: string) => isActivePath(page.url, { href });
</script>

<template>
    <PageContainer width="wide">
        <PageHeader
            title="Tu cuenta"
            description="Datos personales y seguridad de acceso."
        />

        <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)] lg:gap-10">
            <nav
                aria-label="Secciones de tu cuenta"
                class="lg:sticky lg:top-20 lg:self-start"
            >
                <ul
                    class="flex gap-1 rounded-lg border bg-muted/60 p-1 lg:flex-col lg:gap-0.5 lg:border-0 lg:bg-transparent lg:p-0"
                >
                    <li
                        v-for="item in items"
                        :key="item.href"
                        class="min-w-0 flex-1 lg:flex-none"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="
                                active(item.href) ? 'page' : undefined
                            "
                            :class="
                                cn(
                                    'group flex min-h-11 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground lg:min-h-12 lg:justify-start lg:gap-3 lg:hover:bg-accent',
                                    active(item.href) &&
                                        'bg-card text-foreground shadow-xs ring-1 ring-border',
                                )
                            "
                        >
                            <component
                                :is="item.icon"
                                :class="
                                    cn(
                                        'size-4 shrink-0',
                                        active(item.href)
                                            ? 'text-primary'
                                            : 'text-muted-foreground group-hover:text-foreground',
                                    )
                                "
                            />
                            <span class="flex min-w-0 flex-col leading-tight">
                                <span class="truncate">{{ item.title }}</span>
                                <span
                                    class="hidden truncate text-xs font-normal text-muted-foreground lg:block"
                                    >{{ item.hint }}</span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="flex max-w-4xl min-w-0 flex-col gap-6">
                <slot />
            </div>
        </div>
    </PageContainer>
</template>
