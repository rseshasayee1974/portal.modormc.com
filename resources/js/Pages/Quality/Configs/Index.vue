<!--
Author: Cipher
Created: 2026-10-31 10:51:32 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import BaseDeleteButton from '@/Components/Base/BaseDeleteButton.vue';

defineProps({ configs: Object, materials: Array, grades: Array, testTypes: Array, standards: Array, filters: Object });
const editingId = ref(null);
const form = useForm({
    material_id: '', concrete_grade_id: '', test_type_id: '', standard_id: '',
    specimen_shape: 'Cube', specimen_dimensions: '150 MM X 150 MM X 150 MM',
    specimens_per_set: 3, total_sets: 3, formula_bindings: null, milestones: [],
});
function reset() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
}
function edit(config) {
    reset();
    editingId.value = config.id;
    for (const key of Object.keys(form.data())) form[key] = config[key] ?? form[key];
}
function save() {
    const options = { preserveScroll: true, onSuccess: reset };
    if (editingId.value) form.put(route('quality.config.product-grade-configs.update', editingId.value), options);
    else form.post(route('quality.config.product-grade-configs.store'), options);
}
function addMilestone() {
    form.milestones.push({ set_number: form.milestones.length + 1, age_days: 7, age_label: '7 days', target_percentage: null, target_value: null, min_value: null, max_value: null, rule_type: 'GREATER_THAN_OR_EQUAL', tolerance: null });
}
</script>

<template>
    <AppLayout title="Product Grade QC Configuration">
        <template #header><ModuleSubTopNav /></template>
        <Head title="Product Grade QC Configuration" />
        <div class="p-6 space-y-6 text-gray-900 dark:text-gray-100">
            <h1 class="text-2xl font-bold">Product Grade QC Configuration</h1>
            <form class="rounded-xl bg-white dark:bg-gray-900 p-6 space-y-4" @submit.prevent="save">
                <h2 class="font-semibold">{{ editingId ? 'Edit configuration' : 'Add configuration' }}</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label>Material<select v-model="form.material_id" required class="block w-full dark:bg-gray-800"><option value="" disabled>Select material</option><option v-for="item in materials" :key="item.id" :value="item.id">{{ item.title }}</option></select></label>
                    <label>Concrete grade<select v-model="form.concrete_grade_id" class="block w-full dark:bg-gray-800"><option value="">All grades</option><option v-for="item in grades" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                    <label>Test type<select v-model="form.test_type_id" required class="block w-full dark:bg-gray-800"><option value="" disabled>Select test type</option><option v-for="item in testTypes" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                    <label>Standard<select v-model="form.standard_id" class="block w-full dark:bg-gray-800"><option value="">No standard</option><option v-for="item in standards" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
                    <label>Specimen shape<input v-model="form.specimen_shape" class="block w-full dark:bg-gray-800" /></label>
                    <label>Specimen dimensions<input v-model="form.specimen_dimensions" class="block w-full dark:bg-gray-800" /></label>
                    <label>Specimens per set<input v-model.number="form.specimens_per_set" type="number" min="1" max="10" class="block w-full dark:bg-gray-800" /></label>
                    <label>Total sets<input v-model.number="form.total_sets" type="number" min="1" max="10" class="block w-full dark:bg-gray-800" /></label>
                </div>
                <div v-for="(milestone, index) in form.milestones" :key="index" class="flex flex-wrap items-end gap-3 border rounded p-3">
                    <label>Set<input v-model.number="milestone.set_number" type="number" required min="1" class="block w-20 dark:bg-gray-800" /></label>
                    <label>Age (days)<input v-model.number="milestone.age_days" type="number" required min="1" class="block w-24 dark:bg-gray-800" /></label>
                    <label>Label<input v-model="milestone.age_label" required maxlength="50" class="block w-36 dark:bg-gray-800" /></label>
                    <label>Target %<input v-model.number="milestone.target_percentage" type="number" step="any" class="block w-24 dark:bg-gray-800" /></label>
                    <label>Target value<input v-model.number="milestone.target_value" type="number" step="any" class="block w-28 dark:bg-gray-800" /></label>
                    <button type="button" class="text-red-600" @click="form.milestones.splice(index, 1)">Remove</button>
                </div>
                <button type="button" class="text-indigo-600" @click="addMilestone">Add age milestone</button>
                <div v-if="form.hasErrors" role="alert" class="text-red-600"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
                <div class="flex gap-4"><button type="submit" :disabled="form.processing" class="rounded bg-indigo-600 px-4 py-2 text-white disabled:opacity-50">Save configuration</button><button type="button" @click="reset">Cancel</button></div>
            </form>
            <div class="overflow-x-auto rounded-xl bg-white dark:bg-gray-900 p-4">
                <table class="w-full text-left"><thead><tr><th class="p-3">Material</th><th>Grade</th><th>Test type</th><th>Sets</th><th>Actions</th></tr></thead>
                    <tbody><tr v-for="config in configs.data" :key="config.id" class="border-t"><td class="p-3">{{ config.material?.title }}</td><td>{{ config.concrete_grade?.name || 'All grades' }}</td><td>{{ config.test_type?.name }}</td><td>{{ config.total_sets }}</td><td><div class="flex items-center gap-3"><button type="button" class="text-indigo-600" @click="edit(config)">Edit</button><BaseDeleteButton :url="route('quality.config.product-grade-configs.destroy', config.id)" /></div></td></tr>
                    <tr v-if="!configs.data.length"><td colspan="5" class="p-4 text-gray-500">No configurations found.</td></tr></tbody>
                </table>
                <nav aria-label="Configuration pages" class="flex gap-3 mt-4"><template v-for="(link, index) in configs.links" :key="index"><Link v-if="link.url" :href="link.url" :class="{ 'font-bold': link.active }" v-html="link.label" /><span v-else class="text-gray-400" v-html="link.label" /></template></nav>
            </div>
        </div>
    </AppLayout>
</template>
