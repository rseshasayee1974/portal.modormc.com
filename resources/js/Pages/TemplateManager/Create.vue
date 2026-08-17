<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';
import { PaintBrushIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const form = useForm({
    name: '',
    key: '',
    category: 'gst',
    description: '',
    primary_color: '#1e293b',
    font: 'Inter',
});

const categoryOptions = [
    { label: 'GST / Tax Invoice', value: 'gst' },
    { label: 'General / Invoice', value: 'invoice' },
    { label: 'Inventory / PO', value: 'inventory' },
    { label: 'Statement / Financial', value: 'statement' },
];

const fontOptions = [
    { label: 'Inter', value: 'Inter' },
    { label: 'Roboto', value: 'Roboto' },
    { label: 'Outfit', value: 'Outfit' },
    { label: 'Courier / Monospace', value: 'Courier' },
];

const submit = () => {
    form.post(route('templates.store'));
};
</script>

<template>
    <AppLayout title="Create Print Template">
        <template #header>
            <div class="flex items-center justify-between max-w-4xl mx-auto">
                <div class="flex items-center gap-4">
                    <Link :href="route('templates.index')" class="p-2 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition-colors">
                        <ArrowLeftIcon class="w-5 h-5" />
                    </Link>
                    <div class="p-3 bg-indigo-50 rounded-2xl">
                        <PaintBrushIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800">Create Custom Print Template</h1>
                        <p class="text-xs text-slate-400">Register a new Blade template layout for printing and customizer</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto py-6 px-4">
            <form @submit.prevent="submit" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700/60 p-6 space-y-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <BaseInput
                        v-model="form.name"
                        label="Template Name"
                        required
                        placeholder="e.g. Modern Tax Invoice"
                        :error="form.errors.name"
                    />

                    <BaseInput
                        v-model="form.key"
                        label="Template Key (Blade View Slug)"
                        required
                        placeholder="e.g. box_layout"
                        hint="Must correspond to resources/views/pdfs/templates/{key}.blade.php"
                        :error="form.errors.key"
                    />

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Category</label>
                        <BaseSelect
                            v-model="form.category"
                            :options="categoryOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Category"
                        />
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Primary Font</label>
                        <BaseSelect
                            v-model="form.font"
                            :options="fontOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Font"
                        />
                    </div>

                    <BaseInput
                        v-model="form.primary_color"
                        label="Primary Color Code (Hex)"
                        placeholder="#1e293b"
                    />

                    <div class="md:col-span-2">
                        <BaseInput
                            v-model="form.description"
                            label="Template Description"
                            placeholder="Describe the layout structure, borders, or specific use-case..."
                        />
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                    <BaseFormActions
                        submit-label="Save Template"
                        submit-icon="pi pi-check"
                        :loading="form.processing"
                        @submit="submit"
                        @cancel="$inertia.visit(route('templates.index'))"
                    />
                </div>
            </form>
        </div>
    </AppLayout>
</template>
