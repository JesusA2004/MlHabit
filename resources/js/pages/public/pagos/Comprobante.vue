<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import DateText from '@/components/ds/DateText.vue';
import StatusBadge from '@/components/ds/StatusBadge.vue';
import type { Tone } from '@/components/ds/tones';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';

type Option = { value: string; label: string; tone: string };

defineProps<{
    receipt: {
        folio: string;
        concept: string;
        description: string;
        amount: string;
        refundedAmount: string;
        currency: string;
        status: Option;
        method: string;
        cardBrand: string | null;
        cardLast4: string | null;
        authorization: string | null;
        reference: string | null;
        paidAt: string | null;
        customerName: string;
        visit: { folio: string; when: string; address: string } | null;
    };
}>();

function print() {
    window.print();
}
</script>

<template>
    <Head :title="`Comprobante ${receipt.folio}`" />

    <section class="bg-background pb-20 print:bg-white print:pb-0">
        <div class="container-site max-w-2xl pt-10 md:pt-16 print:pt-0">
            <div
                class="mb-6 flex items-center justify-between gap-4 print:hidden"
            >
                <h1 class="text-display-md font-display tracking-tight">
                    Comprobante de pago
                </h1>
                <Button variant="outline" @click="print"
                    ><Printer aria-hidden="true" /> Imprimir</Button
                >
            </div>

            <article
                class="rounded-xl border bg-card p-6 shadow-xs sm:p-8 print:border-0 print:shadow-none"
                aria-label="Comprobante de pago"
            >
                <header
                    class="flex flex-wrap items-start justify-between gap-4 border-b pb-5"
                >
                    <div>
                        <p
                            class="font-display text-xl font-semibold tracking-tight"
                        >
                            ML Habitat
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Comprobante de pago · no es factura fiscal
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-mono text-sm">{{ receipt.folio }}</p>
                        <StatusBadge
                            :label="receipt.status.label"
                            :tone="receipt.status.tone as Tone"
                            size="sm"
                            class="mt-1"
                        />
                    </div>
                </header>

                <dl class="grid gap-x-6 gap-y-4 py-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Cliente</dt>
                        <dd class="mt-0.5 font-medium">
                            {{ receipt.customerName }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Fecha de pago</dt>
                        <dd class="mt-0.5 font-medium">
                            <DateText
                                :value="receipt.paidAt"
                                format="datetime"
                            />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Concepto</dt>
                        <dd class="mt-0.5 font-medium">
                            {{ receipt.concept }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Forma de pago</dt>
                        <dd class="mt-0.5 font-medium">
                            {{ receipt.method
                            }}<template v-if="receipt.cardLast4">
                                · {{ receipt.cardBrand ?? 'tarjeta' }} ••••
                                {{ receipt.cardLast4 }}</template
                            >
                        </dd>
                    </div>
                    <div v-if="receipt.authorization">
                        <dt class="text-muted-foreground">Autorización</dt>
                        <dd class="mt-0.5 font-mono">
                            {{ receipt.authorization }}
                        </dd>
                    </div>
                    <div v-if="receipt.reference">
                        <dt class="text-muted-foreground">Referencia</dt>
                        <dd class="mt-0.5 font-mono">
                            {{ receipt.reference }}
                        </dd>
                    </div>
                    <div v-if="receipt.visit" class="sm:col-span-2">
                        <dt class="text-muted-foreground">
                            Visita {{ receipt.visit.folio }}
                        </dt>
                        <dd class="mt-0.5 first-letter:uppercase">
                            {{ receipt.visit.when }}
                        </dd>
                        <dd class="text-muted-foreground">
                            {{ receipt.visit.address }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-muted-foreground">Descripción</dt>
                        <dd class="mt-0.5">{{ receipt.description }}</dd>
                    </div>
                </dl>

                <div class="space-y-1 border-t pt-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-sm text-muted-foreground"
                            >Total pagado ({{ receipt.currency }})</span
                        >
                        <span
                            class="font-display text-2xl font-semibold tabular-nums"
                            >{{
                                formatMoney(receipt.amount, {
                                    currency: receipt.currency,
                                })
                            }}</span
                        >
                    </div>
                    <div
                        v-if="Number(receipt.refundedAmount) > 0"
                        class="flex items-baseline justify-between text-sm"
                    >
                        <span class="text-muted-foreground">Reembolsado</span>
                        <span class="tabular-nums"
                            >−{{
                                formatMoney(receipt.refundedAmount, {
                                    currency: receipt.currency,
                                })
                            }}</span
                        >
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
