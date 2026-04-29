<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import TripForm from './TripForm.vue';
import TripList from './TripList.vue';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import Swal from 'sweetalert2';
import { TruckIcon, ChartBarIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
    trips: any[];
    options: any;
}>();

const showEditDialog = ref(false);
const selectedTrip = ref<any>(null);

const handleEdit = (trip: any) => {
    selectedTrip.value = trip;
    showEditDialog.value = true;
};

const handleDelete = (trip: any) => {
    Swal.fire({
        title: 'Delete Voyage?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('trips.destroy', trip.id));
        }
    });
};

const handleView = (trip: any) => {
    console.log('Viewing trip:', trip);
};

const onSuccess = () => {
    // Handle success
};
</script>

<template>
    <AppLayout title="Trip Management">
        <template #header><ModuleSubTopNav /></template>

        <div class="min-h-screen py-8 bg-slate-50/50 dark:bg-slate-950">
            <div class="mx-auto max-w-[1650px] space-y-8 px-4 sm:px-6 lg:px-8">

                <div class="flex flex-col justify-between gap-4 rounded-[28px] border border-[#d8dfeb] dark:border-slate-800 bg-white/90 dark:bg-slate-900 shadow-sm md:flex-row md:items-center p-6">
                    <div class="flex items-center gap-4">
                        <div class="rounded-[18px] bg-[#e5edf7] dark:bg-blue-900/20 p-3 text-[#4479b7] dark:text-blue-400">
                            <TruckIcon class="w-8 h-8" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-black uppercase tracking-tight text-slate-800 dark:text-slate-100">Trip Management</h1>
                            <p class="mt-1 text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Dispatch Register And Movement Entry</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a :href="route('trips.dashboard')" class="flex items-center gap-2 rounded-full border border-[#d8dfeb] dark:border-slate-800 bg-[#eef4fb] dark:bg-slate-800 px-6 py-3 text-[10px] font-black uppercase tracking-widest text-[#4479b7] dark:text-blue-400 transition hover:bg-[#4479b7] hover:text-white">
                            <ChartBarIcon class="w-4 h-4" />
                            Performance Dashboard
                        </a>
                        <Tag severity="info" rounded class="px-4 font-black !bg-[#e5edf7] dark:!bg-blue-900/20 !text-[#4479b7] dark:!text-blue-400 border-none">
                            {{ trips.length }} Voyages
                        </Tag>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-8">
                    <div class="pt-5">
                        <TripForm :options="options" @success="onSuccess" />
                    </div>

                    <div class="rounded-lg">
                        <TripList :trips="trips" :options="options" @view="handleView" @edit="handleEdit" @delete="handleDelete" />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
</style>


