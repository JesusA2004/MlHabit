<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    BookOpenText,
    GripVertical,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import InputError from '@/components/InputError.vue';
import ConfirmDialog from '@/components/ds/ConfirmDialog.vue';
import EmptyState from '@/components/ds/EmptyState.vue';
import FormSheet from '@/components/ds/FormSheet.vue';
import PageContainer from '@/components/ds/PageContainer.vue';
import PageHeader from '@/components/ds/PageHeader.vue';
import SearchSelect from '@/components/ds/SearchSelect.vue';
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
import { Textarea } from '@/components/ui/textarea';
import preguntas from '@/routes/admin/preguntas';
import type { Option } from '@/types/web';

type FaqRow = {
    id: number;
    topic: string;
    topicLabel: string;
    productCategoryId: number | null;
    categoryName: string | null;
    question: string;
    answer: string;
    isPublished: boolean;
    sort: number;
};

const props = defineProps<{
    faqs: FaqRow[];
    topics: { value: string; label: string }[];
    categories: Option<number>[];
    can: { publish: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Preguntas frecuentes', href: '/admin/preguntas' },
        ],
    },
});

const groups = ref<Record<string, FaqRow[]>>({});
function regroup() {
    const out: Record<string, FaqRow[]> = {};
    for (const topic of props.topics)
        out[topic.value] = props.faqs.filter((f) => f.topic === topic.value);
    groups.value = out;
}
regroup();
watch(() => props.faqs, regroup);

const total = computed(() => props.faqs.length);

function persist(topic: string) {
    const ids = (groups.value[topic] ?? []).map((f) => f.id);
    if (ids.length)
        router.put(preguntas.orden().url, { ids }, { preserveScroll: true });
}

function move(topic: string, index: number, delta: number) {
    const list = [...(groups.value[topic] ?? [])];
    const target = index + delta;
    if (target < 0 || target >= list.length) return;
    [list[index], list[target]] = [list[target], list[index]];
    groups.value[topic] = list;
    persist(topic);
}

const sheetOpen = ref(false);
const editing = ref<FaqRow | null>(null);
const form = useForm({
    topic: 'general',
    product_category_id: null as number | null,
    question: '',
    answer: '',
    is_published: false,
});

function create(topic = 'general') {
    editing.value = null;
    form.reset();
    form.topic = topic;
    form.is_published = props.can.publish;
    form.clearErrors();
    sheetOpen.value = true;
}

function edit(row: FaqRow) {
    editing.value = row;
    Object.assign(form, {
        topic: row.topic,
        product_category_id: row.productCategoryId,
        question: row.question,
        answer: row.answer,
        is_published: row.isPublished,
    });
    form.clearErrors();
    sheetOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (sheetOpen.value = false),
    };
    if (editing.value)
        form.put(preguntas.update(editing.value.id).url, options);
    else form.post(preguntas.store().url, options);
}

const removing = ref<FaqRow | null>(null);
const removeOpen = ref(false);
function remove(): Promise<void> {
    return new Promise((resolve, reject) => {
        if (!removing.value) return resolve();
        router.delete(preguntas.destroy(removing.value.id).url, {
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: () => reject(new Error('No se pudo eliminar.')),
        });
    });
}
</script>

<template>
    <Head title="Preguntas frecuentes" />

    <PageContainer>
        <PageHeader
            title="Preguntas frecuentes"
            description="Agrupadas por tema. Arrastra o usa las flechas para ordenarlas dentro de cada tema."
        >
            <template #actions>
                <Button @click="create()"
                    ><Plus class="size-4" /> Nueva pregunta</Button
                >
            </template>
        </PageHeader>

        <EmptyState
            v-if="!total"
            :icon="BookOpenText"
            title="Aún no hay preguntas"
            description="Responde las dudas más comunes sobre el proceso, la visita técnica, los pagos y la garantía."
        >
            <Button @click="create()"
                ><Plus class="size-4" /> Nueva pregunta</Button
            >
        </EmptyState>

        <template v-else>
            <SectionCard
                v-for="topic in props.topics"
                :key="topic.value"
                :title="topic.label"
            >
                <template #actions>
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="create(topic.value)"
                        ><Plus class="size-4" /> Agregar</Button
                    >
                </template>
                <VueDraggable
                    v-if="groups[topic.value]?.length"
                    v-model="groups[topic.value]"
                    :animation="150"
                    handle="[data-handle]"
                    tag="ol"
                    class="divide-y divide-border"
                    @end="persist(topic.value)"
                >
                    <li
                        v-for="(row, index) in groups[topic.value]"
                        :key="row.id"
                        class="flex items-start gap-3 py-3"
                    >
                        <span
                            data-handle
                            class="mt-1 hidden cursor-grab touch-none text-muted-foreground sm:block"
                            aria-hidden="true"
                            ><GripVertical class="size-4"
                        /></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ row.question }}</p>
                            <p
                                class="mt-0.5 line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ row.answer }}
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <StatusBadge
                                    :tone="
                                        row.isPublished ? 'success' : 'warning'
                                    "
                                    :label="
                                        row.isPublished
                                            ? 'Publicada'
                                            : 'Borrador'
                                    "
                                    size="sm"
                                />
                                <StatusBadge
                                    v-if="row.categoryName"
                                    tone="neutral"
                                    :label="row.categoryName"
                                    :dot="false"
                                    size="sm"
                                />
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :disabled="index === 0"
                                aria-label="Subir"
                                @click="move(topic.value, index, -1)"
                                ><ArrowUp class="size-4"
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :disabled="
                                    index ===
                                    (groups[topic.value]?.length ?? 0) - 1
                                "
                                aria-label="Bajar"
                                @click="move(topic.value, index, 1)"
                                ><ArrowDown class="size-4"
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                aria-label="Editar"
                                @click="edit(row)"
                                ><Pencil class="size-4"
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8 text-danger-ink"
                                aria-label="Eliminar"
                                @click="((removing = row), (removeOpen = true))"
                                ><Trash2 class="size-4"
                            /></Button>
                        </div>
                    </li>
                </VueDraggable>
                <p v-else class="text-sm text-muted-foreground">
                    Sin preguntas en este tema.
                </p>
            </SectionCard>
        </template>

        <FormSheet
            v-model:open="sheetOpen"
            :title="editing ? 'Editar pregunta' : 'Nueva pregunta'"
            size="lg"
        >
            <form id="faq-form" class="grid gap-4" @submit.prevent="submit">
                <div class="grid gap-1.5">
                    <Label for="faq-topic">Tema</Label>
                    <Select v-model="form.topic">
                        <SelectTrigger id="faq-topic" class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="t in props.topics"
                                :key="t.value"
                                :value="t.value"
                                >{{ t.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.topic" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="faq-category"
                        >Categoría del catálogo (opcional)</Label
                    >
                    <SearchSelect
                        id="faq-category"
                        v-model="form.product_category_id"
                        :options="props.categories"
                        clearable
                        placeholder="Aplica a todo el sitio"
                    />
                    <p class="text-xs text-muted-foreground">
                        Si eliges una categoría, la pregunta también aparece en
                        su página.
                    </p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="faq-question">Pregunta</Label>
                    <Input
                        id="faq-question"
                        v-model="form.question"
                        maxlength="300"
                    />
                    <InputError :message="form.errors.question" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="faq-answer">Respuesta</Label>
                    <Textarea
                        id="faq-answer"
                        v-model="form.answer"
                        rows="6"
                        maxlength="4000"
                    />
                    <InputError :message="form.errors.answer" />
                </div>
                <label
                    class="flex items-start justify-between gap-3 rounded-lg border border-border p-3 text-sm"
                >
                    <span
                        >Publicada<span
                            v-if="!props.can.publish"
                            class="block text-xs text-muted-foreground"
                            >Sin permiso de publicar, se guarda como
                            borrador.</span
                        ></span
                    >
                    <Switch
                        v-model="form.is_published"
                        :disabled="!props.can.publish"
                    />
                </label>
            </form>
            <template #footer>
                <Button variant="outline" @click="sheetOpen = false"
                    >Cancelar</Button
                >
                <Button
                    type="submit"
                    form="faq-form"
                    :disabled="form.processing"
                    ><Spinner v-if="form.processing" /> Guardar</Button
                >
            </template>
        </FormSheet>

        <ConfirmDialog
            v-model:open="removeOpen"
            title="¿Eliminar esta pregunta?"
            description="Se borra de forma permanente."
            confirm-label="Eliminar"
            tone="destructive"
            :confirm="remove"
        />
    </PageContainer>
</template>
