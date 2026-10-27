<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, Printer } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import DateText from '@/components/ds/DateText.vue';
import Money from '@/components/ds/Money.vue';
import { Button } from '@/components/ui/button';
import type { QuoteData } from '@/types/projects';

/**
 * Cotización imprimible: diseño pensado para papel carta / PDF desde el
 * navegador (Imprimir → Guardar como PDF). Sin layout de la app.
 */
defineOptions({ layout: [] });

defineProps<{
    quote: QuoteData;
    project: {
        folio: string;
        name: string;
        customer: string;
        customer_email: string | null;
        customer_phone: string | null;
        address: string | null;
        advisor: {
            name: string;
            email: string | null;
            phone: string | null;
        } | null;
    };
    company: {
        legal_name: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
    };
    backUrl: string;
}>();

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Cotización ${quote.folio}`" />

    <div class="min-h-screen bg-muted/40 py-6 print:bg-white print:py-0">
        <div
            class="mx-auto mb-4 flex max-w-[52rem] items-center justify-between gap-3 px-4 print:hidden"
        >
            <Link
                :href="backUrl"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft class="size-4" aria-hidden="true" /> Volver
            </Link>
            <Button @click="print"
                ><Printer class="size-4" aria-hidden="true" /> Imprimir o
                guardar PDF</Button
            >
        </div>

        <article
            class="quote-sheet mx-auto max-w-[52rem] bg-white px-8 py-10 text-[13px] leading-relaxed text-neutral-900 shadow-sm sm:px-12 print:max-w-none print:px-0 print:py-0 print:shadow-none"
        >
            <header
                class="flex flex-col gap-6 border-b border-neutral-200 pb-6 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <AppLogo />
                    <p
                        v-if="company.legal_name"
                        class="mt-3 text-xs text-neutral-600"
                    >
                        {{ company.legal_name }}
                    </p>
                    <p class="text-xs text-neutral-600">
                        <span v-if="company.phone">{{ company.phone }}</span
                        ><span v-if="company.phone && company.email"> · </span
                        ><span v-if="company.email">{{ company.email }}</span>
                    </p>
                    <p v-if="company.address" class="text-xs text-neutral-600">
                        {{ company.address }}
                    </p>
                </div>
                <div class="sm:text-right">
                    <p
                        class="text-xs tracking-wider text-neutral-500 uppercase"
                    >
                        Cotización
                    </p>
                    <p class="font-mono text-base font-semibold">
                        {{ quote.folio }}
                    </p>
                    <p class="text-xs text-neutral-600">
                        Versión {{ quote.version }}
                    </p>
                    <p class="mt-2 text-xs text-neutral-600">
                        Fecha:
                        <DateText
                            :value="quote.sent_at ?? quote.created_at"
                            format="long"
                        />
                    </p>
                    <p
                        v-if="quote.valid_until"
                        class="text-xs text-neutral-600"
                    >
                        Vigente hasta:
                        <DateText :value="quote.valid_until" format="long" />
                    </p>
                </div>
            </header>

            <section
                class="grid gap-6 border-b border-neutral-200 py-6 sm:grid-cols-2"
            >
                <div>
                    <h2
                        class="text-xs tracking-wider text-neutral-500 uppercase"
                    >
                        Cliente
                    </h2>
                    <p class="mt-1 font-medium">{{ project.customer }}</p>
                    <p v-if="project.customer_email" class="text-neutral-600">
                        {{ project.customer_email }}
                    </p>
                    <p v-if="project.customer_phone" class="text-neutral-600">
                        {{ project.customer_phone }}
                    </p>
                    <p v-if="project.address" class="mt-1 text-neutral-600">
                        {{ project.address }}
                    </p>
                </div>
                <div>
                    <h2
                        class="text-xs tracking-wider text-neutral-500 uppercase"
                    >
                        Proyecto
                    </h2>
                    <p class="mt-1 font-medium">{{ quote.title }}</p>
                    <p class="text-neutral-600">
                        {{ project.name }} · {{ project.folio }}
                    </p>
                    <p v-if="project.advisor" class="mt-1 text-neutral-600">
                        Asesor: {{ project.advisor.name
                        }}<template v-if="project.advisor.phone">
                            · {{ project.advisor.phone }}</template
                        >
                    </p>
                </div>
            </section>

            <div class="mt-6 overflow-x-auto print:overflow-visible">
                <table
                    class="w-full min-w-[34rem] border-collapse print:min-w-0"
                >
                    <thead>
                        <tr
                            class="border-b-2 border-neutral-800 text-left text-xs tracking-wider text-neutral-600 uppercase"
                        >
                            <th class="py-2 pr-2 font-medium">Clave</th>
                            <th class="py-2 pr-2 font-medium">Descripción</th>
                            <th class="py-2 pr-2 text-right font-medium">
                                Cant.
                            </th>
                            <th class="py-2 pr-2 font-medium">Unidad</th>
                            <th class="py-2 pr-2 text-right font-medium">
                                P. unitario
                            </th>
                            <th
                                v-if="Number(quote.discount_total) > 0"
                                class="py-2 pr-2 text-right font-medium"
                            >
                                Desc.
                            </th>
                            <th class="py-2 text-right font-medium">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in quote.items"
                            :key="item.id"
                            class="break-inside-avoid border-b border-neutral-200 align-top"
                        >
                            <td class="py-2 pr-2 font-mono text-xs">
                                {{ item.code || '—' }}
                            </td>
                            <td class="py-2 pr-2">{{ item.description }}</td>
                            <td class="py-2 pr-2 text-right tabular-nums">
                                {{
                                    Number(item.quantity).toLocaleString(
                                        'es-MX',
                                        {
                                            maximumFractionDigits: 3,
                                        },
                                    )
                                }}
                            </td>
                            <td class="py-2 pr-2">{{ item.unit }}</td>
                            <td class="py-2 pr-2 text-right tabular-nums">
                                <Money :value="item.unit_price" />
                            </td>
                            <td
                                v-if="Number(quote.discount_total) > 0"
                                class="py-2 pr-2 text-right tabular-nums"
                            >
                                <Money
                                    v-if="Number(item.discount_amount) > 0"
                                    :value="item.discount_amount"
                                /><span v-else>—</span>
                            </td>
                            <td class="py-2 text-right tabular-nums">
                                <Money :value="item.line_total" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex break-inside-avoid justify-end">
                <dl class="w-full max-w-xs">
                    <div class="flex justify-between py-1">
                        <dt class="text-neutral-600">Subtotal</dt>
                        <dd class="tabular-nums">
                            <Money :value="quote.subtotal" />
                        </dd>
                    </div>
                    <div
                        v-if="Number(quote.discount_total) > 0"
                        class="flex justify-between py-1"
                    >
                        <dt class="text-neutral-600">Descuento</dt>
                        <dd class="tabular-nums">
                            −<Money :value="quote.discount_total" />
                        </dd>
                    </div>
                    <div class="flex justify-between py-1">
                        <dt class="text-neutral-600">
                            IVA {{ quote.tax_percent }}%
                            {{ quote.prices_include_tax ? '(incluido)' : '' }}
                        </dt>
                        <dd class="tabular-nums">
                            <Money :value="quote.tax_total" />
                        </dd>
                    </div>
                    <div
                        class="mt-1 flex justify-between border-t-2 border-neutral-800 pt-2 text-base font-semibold"
                    >
                        <dt>Total</dt>
                        <dd class="tabular-nums">
                            <Money :value="quote.total" show-currency />
                        </dd>
                    </div>
                </dl>
            </div>

            <section class="mt-8 grid gap-6 sm:grid-cols-2">
                <div v-if="quote.inclusions" class="break-inside-avoid">
                    <h2
                        class="text-xs tracking-wider text-neutral-500 uppercase"
                    >
                        Incluye
                    </h2>
                    <p class="mt-1 whitespace-pre-line">
                        {{ quote.inclusions }}
                    </p>
                </div>
                <div v-if="quote.exclusions" class="break-inside-avoid">
                    <h2
                        class="text-xs tracking-wider text-neutral-500 uppercase"
                    >
                        No incluye
                    </h2>
                    <p class="mt-1 whitespace-pre-line">
                        {{ quote.exclusions }}
                    </p>
                </div>
            </section>
            <section v-if="quote.notes" class="mt-6 break-inside-avoid">
                <h2 class="text-xs tracking-wider text-neutral-500 uppercase">
                    Notas
                </h2>
                <p class="mt-1 whitespace-pre-line">{{ quote.notes }}</p>
            </section>
            <section v-if="quote.terms" class="mt-6 break-inside-avoid">
                <h2 class="text-xs tracking-wider text-neutral-500 uppercase">
                    Términos y condiciones
                </h2>
                <p class="mt-1 text-xs whitespace-pre-line text-neutral-700">
                    {{ quote.terms }}
                </p>
            </section>

            <footer
                class="mt-10 border-t border-neutral-200 pt-4 text-[11px] text-neutral-500"
            >
                Precios en {{ quote.currency }}. Documento generado desde el
                sistema de ML Habitat; el folio {{ quote.folio }} identifica
                esta versión.
            </footer>
        </article>
    </div>
</template>

<style>
@media print {
    @page {
        size: letter;
        margin: 14mm;
    }
}
</style>
