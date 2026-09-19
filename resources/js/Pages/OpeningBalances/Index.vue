<script setup>
import { ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import OpeningBalanceCreateForm from './OpeningBalanceCreateForm.vue';
import OpeningBalanceList from './OpeningBalanceList.vue';

const props = defineProps({
    batch: Object,
    ledgers: { type: Array, default: () => [] },
    patrons: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] }
});

const currentBatch = ref(props.batch);
const creating = ref(false);

watch(() => props.batch, batch => {
    currentBatch.value = batch;
});

function created(batch) {
    currentBatch.value = batch;
}
</script>

<template>
    <AppLayout title="Opening Balances">
        <template #header>
            <ModuleSubTopNav />
        </template>

        <Head title="Opening Balances | Initial Setup" />

        <main class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6 text-slate-800 dark:text-slate-100">
            <!-- ── Create Form (Dedicated separate component when no batch exists) ── -->
            <section v-if="!currentBatch" id="opening-create" class="animate-in fade-in duration-300">
                <OpeningBalanceCreateForm
                    :ledgers="ledgers"
                    :patrons="patrons"
                    @created="created"
                    @busy="creating = $event"
                />
            </section>

            <!-- ── Table List with Row Expand to Show Edit Form (when batch exists) ── -->
            <section v-else>
                <OpeningBalanceList
                    :batch="currentBatch"
                    :ledgers="ledgers"
                    :patrons="patrons"
                    :history="history"
                    @saved="currentBatch = $event"
                    @create="currentBatch = null"
                />
            </section>
        </main>
    </AppLayout>
</template>
