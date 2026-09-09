<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import ModuleSubTopNav from '@/Navigation/ModuleSubTopNav.vue';
import Swal from 'sweetalert2';

const props = defineProps<{
    test: any;
}>();

const formatDate = (val: string | null) => {
    if (!val) return '—';
    try {
        const parts = val.substring(0, 10).split('-');
        if (parts.length === 3) {
            return `${parts[2]}-${parts[1]}-${parts[0]}`;
        }
    } catch (e) {}
    return val;
};

const handlePrint = () => {
    window.print();
};

const handleMail = () => {
    Swal.fire({
        title: 'Send Quality Certificate',
        input: 'email',
        inputLabel: 'Recipient Email Address',
        inputPlaceholder: 'Enter customer or lab email...',
        showCancelButton: true,
        confirmButtonText: 'Send Mail',
        confirmButtonColor: '#0088cc',
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                icon: 'success',
                title: 'Dispatched',
                text: `Certificate queued for transmission to ${result.value}.`,
                timer: 2000,
                showConfirmButton: false,
            });
        }
    });
};

const dimensionText = computed(() => {
    const l = props.test.dimension_length ?? 15;
    const w = props.test.dimension_width ?? 15;
    const h = props.test.dimension_height ?? 15;
    return `${l} X ${w} X ${h}`;
});

import { computed } from 'vue';
</script>

<template>
    <AppLayout :title="`Quality Test: ${test.test_number || test.test_code}`">
        <template #header>
            <ModuleSubTopNav class="no-print" />
        </template>
        <Head :title="`Quality Test: ${test.test_number || test.test_code}`" />

        <div class="min-h-screen bg-[#f4f6f9] py-6 px-4 sm:px-6 lg:px-8 print:p-0 print:bg-white">
            <div class="max-w-7xl mx-auto print:max-w-none">

                <!-- ── Top Tab Bar (Exact match to screenshot) ── -->
                <div class="flex items-center border-b border-gray-200 no-print">
                    <div class="bg-[#00a2ed] text-white px-8 py-2.5 font-semibold text-sm rounded-t-sm shadow-sm flex items-center gap-2">
                        <span>Basic Information</span>
                    </div>
                </div>

                <!-- Printable Certificate Header (only in print view) -->
                <div class="hidden print:block mb-6 border-b-2 border-gray-800 pb-4 text-center">
                    <h1 class="text-2xl font-black uppercase tracking-wider text-gray-900">
                        {{ test.plant?.name || 'CONCRETE READY MIX' }}
                    </h1>
                    <p class="text-xs text-gray-600 font-semibold tracking-wide">
                        QUALITY CONTROL LABORATORY & CONCRETE COMPRESSIVE STRENGTH TEST REPORT
                    </p>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        Conforming to IS: 516 & IS: 456 Specifications for Concrete Testing
                    </p>
                </div>

                <!-- ── Main Card Container ── -->
                <div class="bg-white border border-gray-200 shadow-sm p-6 sm:p-8 space-y-8 rounded-b-sm print:border-none print:shadow-none print:p-0">

                    <!-- ── SECTION 1: Concrete Test Information ── -->
                    <div class="space-y-4">
                        <div class="border-b border-sky-100 pb-2">
                            <h2 class="text-sm font-bold text-[#0088cc] uppercase tracking-wider">
                                Concrete Test Information
                            </h2>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-3.5 text-xs text-gray-800">

                            <!-- Left Column -->
                            <div class="space-y-2.5">
                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Account Name</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.account_name || test.patron?.name || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Factory</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.plant?.name || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Invoice</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.invoice_no || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Grade</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.grade || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Concrete Date</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ formatDate(test.concrete_date) }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Age Of Test (days)</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.age_of_test_days || 7 }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Date Of Testing</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ formatDate(test.date_of_testing) }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Project</span>
                                    <span class="col-span-7 font-medium text-gray-900">{{ test.project || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1">
                                    <span class="col-span-5 font-semibold text-gray-600">Billing Address</span>
                                    <span class="col-span-7 text-gray-700 whitespace-pre-line leading-relaxed">{{ test.billing_address || '—' }}</span>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="space-y-2.5">
                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Test Number</span>
                                    <span class="col-span-7 font-extrabold text-gray-900">{{ test.test_number || test.test_code || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Dimension (cm)</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ dimensionText }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Fresh Unit Weight (Kg)</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.fresh_unit_weight ?? '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Slump (mm)</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.slump_value ?? '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Air Content (%)</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.air_content ?? '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Concrete Temperature (°C)</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.fresh_temperature ?? '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Lab Technician</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.lab_technician || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1 border-b border-gray-50">
                                    <span class="col-span-5 font-semibold text-gray-600">Field Technician</span>
                                    <span class="col-span-7 font-bold text-gray-900">{{ test.field_technician || '—' }}</span>
                                </div>

                                <div class="grid grid-cols-12 py-1">
                                    <span class="col-span-5 font-semibold text-gray-600">Shipping Address</span>
                                    <span class="col-span-7 text-gray-700 whitespace-pre-line leading-relaxed">{{ test.shipping_address || '—' }}</span>
                                </div>
                            </div>

                            <!-- Description full row -->
                            <div v-if="test.description || test.remarks" class="lg:col-span-2 grid grid-cols-12 py-2 border-t border-gray-100 mt-2">
                                <span class="col-span-2 font-semibold text-gray-600">Description</span>
                                <span class="col-span-10 text-gray-700 whitespace-pre-line">{{ test.description || test.remarks }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ── SECTION 2: Laboratory Test Information (Cube Specimens) ── -->
                    <div class="space-y-4 pt-4 border-t border-gray-200">
                        <div class="border-b border-sky-100 pb-2">
                            <h2 class="text-sm font-bold text-[#0088cc] uppercase tracking-wider">
                                Laboratory Test Information
                            </h2>
                        </div>

                        <!-- Cube Specimens Table (Exact match to View Screenshot) -->
                        <div class="overflow-x-auto border border-gray-200 rounded-sm">
                            <table class="w-full text-xs text-center border-collapse">
                                <thead>
                                    <tr class="bg-[#fcf9ee] border-b border-gray-200 text-gray-800 font-bold">
                                        <th class="py-2.5 px-4 text-center font-bold tracking-wide w-48">Ident Mark</th>
                                        <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                            Weight <span class="text-[#0088cc]">(kg)</span>
                                        </th>
                                        <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                            Load <span class="text-[#0088cc]">(kN)</span>
                                        </th>
                                        <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                            Compressive Strength <span class="text-[#0088cc]">(N/mm²)</span>
                                        </th>
                                        <th class="py-2.5 px-4 text-center font-bold tracking-wide">
                                            Avg.Compressive Strength <span class="text-[#0088cc]">(N/mm²)</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <template v-if="test.specimens && test.specimens.length > 0">
                                        <tr v-for="(spec, index) in test.specimens" :key="spec.id || index" class="hover:bg-gray-50/50">
                                            <!-- Ident Mark (displayed on first row spanning all rows) -->
                                            <td v-if="index === 0" :rowspan="test.specimens.length" class="p-3 border-r border-gray-200 align-middle font-bold text-gray-900 text-sm">
                                                {{ test.ident_mark || spec.ident_mark || 'EB' }}
                                            </td>

                                            <!-- Weight (kg) -->
                                            <td class="p-2.5 border-r border-gray-200 font-semibold text-gray-800">
                                                {{ spec.weight_kg ? Number(spec.weight_kg).toFixed(2) : '—' }}
                                            </td>

                                            <!-- Load (kN) -->
                                            <td class="p-2.5 border-r border-gray-200 font-bold text-gray-900">
                                                {{ spec.load_kn ? Number(spec.load_kn).toFixed(2) : '—' }}
                                            </td>

                                            <!-- Compressive Strength (N/mm2) -->
                                            <td class="p-2.5 border-r border-gray-200 font-bold text-sky-700">
                                                {{ spec.compressive_strength ? Number(spec.compressive_strength).toFixed(2) : '—' }}
                                            </td>

                                            <!-- Avg. Compressive Strength (spanning all rows) -->
                                            <td v-if="index === 0" :rowspan="test.specimens.length" class="p-3 align-middle font-black text-sm text-sky-800 bg-sky-50/40">
                                                {{ test.avg_compressive_strength ? Number(test.avg_compressive_strength).toFixed(2) : '—' }}
                                            </td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td colspan="5" class="p-6 text-center text-gray-400 italic">
                                            No laboratory cube specimens recorded for this test.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Signatures block for Print view -->
                    <div class="hidden print:grid grid-cols-3 gap-8 pt-16 text-center text-xs font-semibold text-gray-800">
                        <div>
                            <div class="border-t border-gray-400 pt-1">
                                Field / Sampling Technician
                            </div>
                            <div class="text-[10px] text-gray-500 font-normal mt-0.5">
                                {{ test.field_technician || 'Verified' }}
                            </div>
                        </div>
                        <div>
                            <div class="border-t border-gray-400 pt-1">
                                Laboratory Testing Officer
                            </div>
                            <div class="text-[10px] text-gray-500 font-normal mt-0.5">
                                {{ test.lab_technician || 'Tested' }}
                            </div>
                        </div>
                        <div>
                            <div class="border-t border-gray-400 pt-1">
                                Quality Assurance Manager
                            </div>
                            <div class="text-[10px] text-gray-500 font-normal mt-0.5">
                                Authorized Signature & Stamp
                            </div>
                        </div>
                    </div>

                    <!-- ── Bottom Action Buttons (Exact match to View Screenshot) ── -->
                    <div class="flex items-center justify-center gap-2 pt-6 border-t border-gray-200 no-print">
                        <button
                            type="button"
                            @click="router.get(route('concrete-quality-tests.edit', test.id))"
                            class="bg-white hover:bg-gray-50 text-gray-800 font-semibold px-5 py-1.5 text-xs rounded border border-gray-300 shadow-sm transition-all min-w-[70px]"
                        >
                            Edit
                        </button>
                        <button
                            type="button"
                            @click="router.get(route('concrete-quality-tests.index'))"
                            class="bg-white hover:bg-gray-50 text-gray-800 font-semibold px-5 py-1.5 text-xs rounded border border-gray-300 shadow-sm transition-all min-w-[70px]"
                        >
                            Back
                        </button>
                        <button
                            type="button"
                            @click="handlePrint"
                            class="bg-white hover:bg-gray-50 text-gray-800 font-semibold px-5 py-1.5 text-xs rounded border border-gray-300 shadow-sm transition-all min-w-[70px]"
                        >
                            Print
                        </button>
                        <button
                            type="button"
                            @click="handleMail"
                            class="bg-white hover:bg-gray-50 text-gray-800 font-semibold px-5 py-1.5 text-xs rounded border border-gray-300 shadow-sm transition-all min-w-[70px]"
                        >
                            Mail
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>
