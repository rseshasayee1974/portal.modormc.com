<!--
Author: ragul-onemodo
Created: 2026-11-25 12:54:46 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({ feed: { type: Object, required: true } });
const category = ref('all');
const filters = ref({ global: { value: '', matchMode: 'contains' } });
const first = ref(0);
const pageSize = ref(30);
const busy = ref(false);
const error = ref('');
let timer;

const categoryLabels = {
    all: 'All',
    sales: 'Sales & CRM',
    operations: 'Operations',
    fleet: 'Fleet',
    finance: 'Finance',
    hr: 'HR',
    general: 'System',
};

const categories = computed(() => ['all', ...props.feed.categories]);
const items = computed(() => props.feed.notifications
    .filter(item => category.value === 'all' || (item.category || 'general') === category.value)
    .map(item => ({
        ...item,
        categoryLabel: categoryLabels[item.category || 'general'] || item.category,
    })));

watch([category, () => filters.value.global.value], () => { first.value = 0; });
watch(() => items.value.length, count => {
    if (first.value >= count) first.value = Math.floor(Math.max(0, count - 1) / pageSize.value) * pageSize.value;
});
watch(categories, values => {
    if (!values.includes(category.value)) category.value = 'all';
});

function refresh() {
    if (busy.value) return;
    busy.value = true;
    router.reload({ only: ['feed'], onFinish: () => { busy.value = false; } });
}

async function markRead(item) {
    if (busy.value) return;
    busy.value = true;
    error.value = '';
    try {
        if (!item) await axios.post(route('notifications.read-all'));
        else if (!item.is_read) await axios.post(route('notifications.read', { id: item.id }));
    } catch {
        error.value = 'Could not update read status. Please try again.';
        return;
    } finally {
        busy.value = false;
    }
    if (item?.url) router.visit(item.url);
    else refresh();
}

onMounted(() => { timer = setInterval(refresh, 60000); });
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <AppLayout title="Notifications">
        <main class="p-4 sm:p-6">
            <p v-if="error" role="alert" class="mb-3 text-sm text-red-700">{{ error }}</p>
            <div class="notification-table card overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <BaseDataTable :value="items" v-model:filters="filters" v-model:first="first" v-model:rows="pageSize"
                    :rowsPerPageOptions="[10, 30, 50]" :globalFilterFields="['title', 'message', 'type', 'categoryLabel']"
                    heading="Notifications" headingIcon="pi pi-bell" showSerial showSearch :showDateFilters="false">
                    <template #toolbar>
                        <span class="text-xs font-semibold text-slate-600">{{ feed.unread_count }} unread</span>
                        <Button label="Mark all as read" icon="pi pi-check" :disabled="busy || !feed.unread_count"
                            class="!h-10 !rounded-lg !px-4 !text-xs !font-black !uppercase !tracking-widest" @click="markRead()" />
                        <Button icon="pi pi-refresh" severity="secondary" text rounded aria-label="Refresh notifications"
                            title="Refresh notifications" :disabled="busy" @click="refresh" />
                    </template>
                    <template #filters>
                        <label for="notification-category" class="text-xs font-semibold text-slate-700">Category</label>
                        <select id="notification-category" v-model="category" class="w-full rounded-md border-slate-300 text-sm">
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ categoryLabels[cat] || cat }}</option>
                        </select>
                    </template>
                    <Column field="title" header="Notification" sortable>
                        <template #body="{ data }">
                            <button type="button" class="text-left font-semibold text-indigo-700 hover:underline"
                                :disabled="busy" @click="markRead(data)">{{ data.title }}</button>
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600">{{ data.message }}</p>
                        </template>
                    </Column>
                    <Column field="categoryLabel" header="Category" sortable />
                    <Column field="type" header="Type" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.type" :severity="data.severity === 'warning' ? 'warn' : data.severity === 'danger' ? 'danger' : 'secondary'" rounded />
                        </template>
                    </Column>
                    <Column field="time_label" header="Time" />
                    <Column field="is_read" header="Status" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.is_read ? 'Read' : 'Unread'" :severity="data.is_read ? 'secondary' : 'info'" rounded />
                        </template>
                    </Column>
                    <Column header="Actions">
                        <template #body="{ data }">
                            <Button :icon="data.url ? 'pi pi-eye' : 'pi pi-check'" severity="secondary" text rounded
                                :aria-label="data.url ? 'Open ' + data.title : 'Mark ' + data.title + ' as read'"
                                :title="data.url ? 'Open notification' : 'Mark as read'"
                                :disabled="busy || (!data.url && data.is_read)" @click="markRead(data)" />
                        </template>
                    </Column>
                    <template #empty>{{ filters.global.value || category !== 'all' ? 'No matching notifications' : 'No notifications' }}</template>
                </BaseDataTable>
            </div>
        </main>
    </AppLayout>
</template>

<style scoped>
@media (max-width: 640px) {
    .notification-table :deep(.no-print > .ml-auto),
    .notification-table :deep(.no-print .flex.items-center.gap-3:has(.p-inputgroup)) {
        flex-wrap: wrap;
        max-width: 100%;
    }
    .notification-table :deep(.p-inputgroup) {
        max-width: 100%;
    }
}
</style>
