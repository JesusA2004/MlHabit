<script setup lang="ts">
import { Loader2 } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { apiFetch } from '@/lib/http';

export interface StudioPageMeta {
    id: number;
    key: string;
    kind: 'system' | 'custom';
    title: string;
    slug: string | null;
    seoTitle: string | null;
    seoDescription: string | null;
    path: string;
    revision: number;
    draftUpdatedAt: string | null;
    draftUpdatedBy: string | null;
    publishedAt: string | null;
    publishedNumber: number | null;
    hasChanges: boolean;
}

const open = defineModel<boolean>('open', { default: false });
const props = defineProps<{ page: StudioPageMeta; url: string }>();
const emit = defineEmits<{ saved: [page: StudioPageMeta] }>();

const form = reactive({ title: '', slug: '', seo_title: '', seo_description: '' });
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);

watch(open, (v) => {
    if (!v) return;
    form.title = props.page.title;
    form.slug = props.page.slug ?? '';
    form.seo_title = props.page.seoTitle ?? '';
    form.seo_description = props.page.seoDescription ?? '';
    errors.value = {};
});

async function save() {
    saving.value = true;
    const response = await apiFetch(props.url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(form),
    });
    saving.value = false;
    const payload = await response.json();
    if (response.status === 422) {
        errors.value = payload.errors ?? {};

        return;
    }
    if (response.ok) {
        emit('saved', payload.page);
        open.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Ajustes de la página</DialogTitle>
                <DialogDescription>Nombre interno, dirección y cómo aparece en buscadores.</DialogDescription>
            </DialogHeader>
            <form class="flex flex-col gap-4" @submit.prevent="save">
                <div class="flex flex-col gap-1.5">
                    <label for="ps-title" class="text-sm font-medium">Nombre</label>
                    <Input id="ps-title" v-model="form.title" maxlength="120" />
                    <InputError :message="errors.title?.[0]" />
                </div>
                <div v-if="page.kind === 'custom'" class="flex flex-col gap-1.5">
                    <label for="ps-slug" class="text-sm font-medium">Dirección</label>
                    <div class="flex items-center rounded-md border border-input bg-background pl-3 text-sm">
                        <span class="text-muted-foreground">/</span>
                        <input id="ps-slug" v-model="form.slug" class="h-9 flex-1 bg-transparent px-1 outline-none" maxlength="80" />
                    </div>
                    <InputError :message="errors.slug?.[0]" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="ps-seo-title" class="text-sm font-medium">Título SEO</label>
                    <Input id="ps-seo-title" v-model="form.seo_title" maxlength="255" placeholder="Si lo dejas vacío se usa el nombre" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="ps-seo-desc" class="text-sm font-medium">Descripción SEO</label>
                    <Textarea id="ps-seo-desc" v-model="form.seo_description" maxlength="255" rows="3" placeholder="Una o dos frases que inviten a entrar (máx. 160 caracteres recomendados)." />
                    <span class="text-xs text-muted-foreground">{{ form.seo_description.length }}/160 recomendados</span>
                </div>
                <DialogFooter>
                    <Button type="button" variant="ghost" @click="open = false">Cancelar</Button>
                    <Button type="submit" :disabled="saving"><Loader2 v-if="saving" class="size-4 animate-spin" /> Guardar ajustes</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
