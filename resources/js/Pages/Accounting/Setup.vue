<!--
Author: ragul-onemodo
Created: 2026-10-18 22:19:04 Asia/Calcutta (UTC+05:30)
-->
<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';

defineProps({ plant_id: { type: Number, required: true } });
</script>

<template>
    <Head title="Accounting setup required" />
    <AppLayout title="Accounting">
        <ModuleSubTopNav />
        <section class="mx-auto my-8 max-w-3xl rounded-lg border border-amber-300 bg-white p-6 shadow-sm" role="status">
            <h1 class="text-xl font-semibold text-slate-900">Accounting setup required</h1>
            <p class="mt-3 text-slate-700">Plant {{ plant_id }} · The accounting database migration has not been completed. Reports and accounting actions will become available after setup.</p>
            <p class="mt-3 text-slate-700">Ask your administrator to run <code>php artisan accounting:preflight</code> on the portal server and follow the staging migration instructions in <code>docs/accounting-integration.md</code>.</p>
            <p class="mt-3 text-slate-700">Existing duplicate ledger codes require an approved mapping before migration. Preserve financial history when resolving them.</p>
            <a :href="route('accounting.index', { plant_id })" class="mt-5 inline-block rounded border border-slate-300 px-4 py-2 font-medium text-slate-700 hover:bg-slate-100">Check setup again</a>
        </section>
    </AppLayout>
</template>
