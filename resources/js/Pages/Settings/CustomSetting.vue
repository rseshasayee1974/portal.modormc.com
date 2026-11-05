<script setup lang="ts">
import AppLayout from "@/Layouts/AppLayout.vue";
import { useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { entityToday } from "@/Utils/entityDateTime";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import InputSwitch from "primevue/inputswitch";
import InputNumber from "primevue/inputnumber";
import Dropdown from "primevue/dropdown";
import Dialog from "primevue/dialog";
import Swal from "sweetalert2";
import ModuleSubTopNav from "@/Navigation/ModuleSubTopNav.vue";
import { usePermissions } from "@/Composables/usePermissions";
import { Cog6ToothIcon, BuildingOffice2Icon } from "@heroicons/vue/24/outline";

const { isSassOwner } = usePermissions();

const props = defineProps<{
    batchingSettings: any;
    customSettings: any[];
    plantId: number;
    plantName: string;
    batchSheetLogoUrl?: string | null;
    batchSheetDrivers?: { label: string; value: string }[];
}>();

// ─── Form State ───────────────────────────────────────────────────────────────
const form = useForm({
    module: "batching",
    batch_sheet_logo: null as File | null,
    remove_batch_sheet_logo: false,
    settings: {
        batch_creation_source:
            props.batchingSettings?.batch_creation_source || "sales_order",
        dispatch_discount_tax_mode:
            props.batchingSettings?.dispatch_discount_tax_mode || "after_tax",
        invoice_quantity_basis:
            props.batchingSettings?.invoice_quantity_basis || "batch_size",
        show_delivered_qty: Number(props.batchingSettings?.show_delivered_qty ?? 1) === 1,
        batch_series_enabled:
            Number(props.batchingSettings?.batch_series_enabled ?? 1) === 1,
        default_batch_series:
            props.batchingSettings?.default_batch_series || "NONE",
        batch_sheet_driver: props.batchingSettings?.batch_sheet_driver || "",
        new_weight:
            props.batchingSettings?.new_weight !== undefined
                ? Number(props.batchingSettings?.new_weight)
                : props.batchingSettings?.newweight !== undefined
                  ? Number(props.batchingSettings?.newweight)
                  : 1,
        newweight:
            props.batchingSettings?.new_weight !== undefined
                ? Number(props.batchingSettings?.new_weight)
                : props.batchingSettings?.newweight !== undefined
                  ? Number(props.batchingSettings?.newweight)
                  : 1,
        manual_weight: props.batchingSettings?.manual_weight == 1,
        weight_mandatory:
            props.batchingSettings?.weight_mandatory !== undefined
                ? props.batchingSettings?.weight_mandatory == 1 ||
                  props.batchingSettings?.weight_mandatory === true ||
                  props.batchingSettings?.weight_mandatory === "true"
                : true,
        with_inventory:
            props.batchingSettings?.with_inventory !== undefined
                ? props.batchingSettings?.with_inventory == 1 ||
                  props.batchingSettings?.with_inventory === true ||
                  props.batchingSettings?.with_inventory === "true"
                : true,
        camera: props.batchingSettings?.camera == 1,
        camera_url: props.batchingSettings?.camera_url || "",
        camera_url_1: props.batchingSettings?.camera_url_1 || "",
        camera_url_2: props.batchingSettings?.camera_url_2 || "",
        loader_gif: props.batchingSettings?.loader_gif || "",
        sheet_upload: props.batchingSettings?.sheet_upload == 1,
        hide_batch_form: props.batchingSettings?.hide_batch_form == 1,
        po_prefix: props.batchingSettings?.po_prefix || "PO",
        cpo_prefix: props.batchingSettings?.cpo_prefix || "CPO",
        so_prefix: props.batchingSettings?.so_prefix || "SO",
        quote_prefix: props.batchingSettings?.quote_prefix || "QT",
        invoice_number_format: [1, "1", "prefix_number_fy"].includes(
            props.batchingSettings?.invoice_number_format,
        )
            ? 1
            : 0,
        invoice_show_batch_no:
            Number(props.batchingSettings?.invoice_show_batch_no ?? 1) === 1,
        invoice_show_site_ref:
            Number(props.batchingSettings?.invoice_show_site_ref ?? 1) === 1,
        invoice_show_customer_po_no:
            Number(props.batchingSettings?.invoice_show_customer_po_no ?? 1) === 1,
        invoice_number_padding: Number(
            props.batchingSettings?.invoice_number_padding ?? 0,
        ),
        invoice_number_start: Number(
            props.batchingSettings?.invoice_number_start ?? 1,
        ),
        billing_number_format: [1, "1", "prefix_number_fy"].includes(
            props.batchingSettings?.billing_number_format,
        )
            ? 1
            : 0,
        billing_number_padding: Number(
            props.batchingSettings?.billing_number_padding ?? 0,
        ),
        billing_number_start: Number(
            props.batchingSettings?.billing_number_start ?? 1,
        ),
        target_to_actual: props.batchingSettings?.target_to_actual == 1,
        actual_consumption_variation: Number(
            props.batchingSettings?.actual_consumption_variation ??
                props.batchingSettings?.actual_variation_limit ??
                5,
        ),
        auto_carry_pump: props.batchingSettings?.auto_carry_pump == 1,
        default_transport: props.batchingSettings?.default_transport || "",
        quote_validity:
            props.batchingSettings?.quote_validity !== undefined
                ? Number(props.batchingSettings.quote_validity)
                : 15,
        is_grade_or_is_mix_design:
            props.batchingSettings?.is_grade_or_is_mix_design !== undefined
                ? Number(props.batchingSettings.is_grade_or_is_mix_design)
                : 2,
        print_delivery_ingredients:
            props.batchingSettings?.print_delivery_ingredients !== undefined
                ? props.batchingSettings?.print_delivery_ingredients == 1 ||
                  props.batchingSettings?.print_delivery_ingredients === true ||
                  props.batchingSettings?.print_delivery_ingredients === "true"
                : false,
        quotation_price_list:
            props.batchingSettings?.quotation_price_list !== undefined
                ? props.batchingSettings?.quotation_price_list == 1 ||
                  props.batchingSettings?.quotation_price_list === true ||
                  props.batchingSettings?.quotation_price_list === "true"
                : false,
        custom_params: props.batchingSettings?.custom_params || [],
    },
});

const batchSheetDriverOptions = computed(() => [
    { label: "Automatic Detection", value: "" },
    ...(props.batchSheetDrivers || []),
]);

const numberingPreview = (module: "invoice" | "billing") => {
    const [year, month] = entityToday().split("-").map(Number);
    const startYear = month >= 4 ? year : year - 1;
    const fy = `${String(startYear).slice(-2)}-${String(startYear + 1).slice(-2)}`;
    const prefix = module === "invoice" ? "INV" : "BILL";
    const sequence = String(
        form.settings[`${module}_number_start`] || 1,
    ).padStart(Number(form.settings[`${module}_number_padding`] || 0), "0");
    return form.settings[`${module}_number_format`] === 1
        ? `${prefix}/${sequence}/${fy}`
        : `${prefix}/${fy}/${sequence}`;
};

// ─── Logo Input ───────────────────────────────────────────────────────────────
const batchSheetLogoInput = ref<HTMLInputElement | null>(null);
const selectBatchSheetLogo = (event: Event) => {
    form.batch_sheet_logo =
        (event.target as HTMLInputElement).files?.[0] ?? null;
    form.remove_batch_sheet_logo = false;
};

// ─── Custom Parameters ────────────────────────────────────────────────────────
const showParamDialog = ref(false);
const paramForm = ref({
    module: "batching",
    label: "",
    key: "",
    type: "text",
});

const addParameter = () => {
    if (!paramForm.value.key || !paramForm.value.label) return;
    form.settings.custom_params.push({
        ...paramForm.value,
        value: paramForm.value.type === "bool" ? false : "",
    });
    paramForm.value = { module: "batching", label: "", key: "", type: "text" };
    showParamDialog.value = false;
};

const removeParameter = (index: number) => {
    form.settings.custom_params.splice(index, 1);
};

// ─── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
    const payload = {
        ...form.settings,
        invoice_show_batch_no: form.settings.invoice_show_batch_no ? 1 : 0,
        show_delivered_qty: form.settings.show_delivered_qty ? 1 : 0,
        invoice_show_site_ref: form.settings.invoice_show_site_ref ? 1 : 0,
        invoice_show_customer_po_no: form.settings.invoice_show_customer_po_no ? 1 : 0,
        batch_series_enabled: form.settings.batch_series_enabled ? 1 : 0,
        default_batch_series: form.settings.default_batch_series || "NONE",
        new_weight: Number(form.settings.new_weight ?? 1),
        newweight: Number(form.settings.new_weight ?? 1),
        manual_weight: form.settings.manual_weight ? 1 : 0,
        weight_mandatory: form.settings.weight_mandatory ? 1 : 0,
        with_inventory: form.settings.with_inventory ? 1 : 0,
        camera: form.settings.camera ? 1 : 0,
        sheet_upload: form.settings.sheet_upload ? 1 : 0,
        hide_batch_form: form.settings.hide_batch_form ? 1 : 0,
        target_to_actual: form.settings.target_to_actual ? 1 : 0,
        actual_consumption_variation: Math.max(
            0,
            Number(form.settings.actual_consumption_variation ?? 5),
        ),
        auto_carry_pump: form.settings.auto_carry_pump ? 1 : 0,
        quote_validity: form.settings.quote_validity
            ? parseInt(form.settings.quote_validity as any, 10)
            : 15,
        print_delivery_ingredients: form.settings.print_delivery_ingredients
            ? 1
            : 0,
        quotation_price_list: form.settings.quotation_price_list ? 1 : 0,
        is_grade_or_is_mix_design: Number(
            form.settings.is_grade_or_is_mix_design ?? 2,
        ),
    };

    form.transform((data) => ({ ...data, settings: payload })).post(
        route("settings.customsetting.update"),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.batch_sheet_logo = null;
                form.remove_batch_sheet_logo = false;
                if (batchSheetLogoInput.value)
                    batchSheetLogoInput.value.value = "";
                Swal.fire({
                    toast: true,
                    position: "top-end",
                    icon: "success",
                    title: "Settings saved successfully",
                    showConfirmButton: false,
                    timer: 1500,
                });
            },
        },
    );
};
</script>

<template>
    <AppLayout title="Custom Settings">
        <div class="settings-page min-h-screen py-5">
            <div class="w-full max-w-[1680px] mx-auto px-4 lg:px-8 space-y-6">
                <ModuleSubTopNav />

                <!-- Header Actions Bar -->
                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="p-2.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100"
                        >
                            <Cog6ToothIcon class="size-6 text-indigo-600" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-3">
                                <h1
                                    class="text-2xl font-semibold text-slate-900 tracking-tight"
                                >
                                    Custom Settings
                                </h1>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-slate-700 border border-slate-200"
                                >
                                    <BuildingOffice2Icon
                                        class="size-3.5 text-slate-500 shrink-0"
                                    />
                                    <span>{{ plantName }}</span>
                                </span>
                            </div>
                            <span class="text-sm text-slate-500 block mt-1.5"
                                >Manage how your plant operates, numbers
                                documents, and prints.</span
                            >
                        </div>
                    </div>

                    <div class="flex items-center gap-2 self-end sm:self-auto">
                        <Button
                            v-if="isSassOwner"
                            icon="pi pi-plus"
                            label="Add Parameter"
                            size="small"
                            severity="secondary"
                            outlined
                            class="rounded-lg !bg-white text-xs font-bold"
                            @click="showParamDialog = true"
                        />
                        <Button
                            icon="pi pi-check"
                            label="Save Changes"
                            size="small"
                            class="rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 border-indigo-600 text-white shadow-xs px-4"
                            :loading="form.processing"
                            @click="submit"
                        />
                    </div>
                </div>

                <!-- Grouped settings with six fields per desktop row across both panels. -->
                <div
                    class="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)] gap-6 items-start"
                >
                    <aside
                        class="lg:sticky lg:top-6 rounded-2xl border border-slate-200 bg-white p-3"
                    >
                        <p
                            class="px-3 pt-2 pb-3 text-[10px] font-bold tracking-[0.16em] uppercase text-slate-400"
                        >
                            Plant preferences
                        </p>
                        <nav
                            aria-label="Settings sections"
                            class="flex gap-1 overflow-x-auto lg:flex-col"
                        >
                            <a
                                href="#settings-batching"
                                class="settings-nav-link"
                                ><i class="pi pi-box" aria-hidden="true"></i
                                ><span>Batching</span
                                ><i
                                    class="pi pi-angle-right ml-auto text-[10px]"
                                    aria-hidden="true"
                                ></i
                            ></a>
                            <a
                                href="#settings-weighbridge"
                                class="settings-nav-link"
                                ><i class="pi pi-camera" aria-hidden="true"></i
                                ><span>Weighbridge &amp; Camera</span
                                ><i
                                    class="pi pi-angle-right ml-auto text-[10px]"
                                    aria-hidden="true"
                                ></i
                            ></a>
                            <a
                                href="#settings-numbering"
                                class="settings-nav-link"
                                ><i class="pi pi-hashtag" aria-hidden="true"></i
                                ><span>Document Numbering</span
                                ><i
                                    class="pi pi-angle-right ml-auto text-[10px]"
                                    aria-hidden="true"
                                ></i
                            ></a>
                            <a
                                href="#settings-quotation"
                                class="settings-nav-link"
                                ><i
                                    class="pi pi-file-edit"
                                    aria-hidden="true"
                                ></i
                                ><span>Quotations &amp; Sales</span
                                ><i
                                    class="pi pi-angle-right ml-auto text-[10px]"
                                    aria-hidden="true"
                                ></i
                            ></a>
                            <a
                                href="#settings-printing"
                                class="settings-nav-link"
                                ><i class="pi pi-print" aria-hidden="true"></i
                                ><span>Printing &amp; Delivery</span
                                ><i
                                    class="pi pi-angle-right ml-auto text-[10px]"
                                    aria-hidden="true"
                                ></i
                            ></a>
                            <a href="#settings-custom" class="settings-nav-link"
                                ><i
                                    class="pi pi-sliders-h"
                                    aria-hidden="true"
                                ></i
                                ><span>Appearance &amp; Custom</span
                                ><i
                                    class="pi pi-angle-right ml-auto text-[10px]"
                                    aria-hidden="true"
                                ></i
                            ></a>
                        </nav>
                        <p
                            class="hidden lg:block mt-4 border-t border-slate-100 px-3 pt-4 pb-2 text-xs leading-relaxed text-slate-500"
                        >
                            Changes apply to
                            <strong class="font-medium text-slate-700">{{
                                plantName
                            }}</strong
                            >. Save when you are finished.
                        </p>
                    </aside>
                    <div class="settings-panels min-w-0 space-y-6">
                        <!-- TAB 1: BATCHING -->
                        <section
                            id="settings-batching"
                            aria-labelledby="heading-batching"
                            class="settings-group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                                    ><i class="pi pi-box" aria-hidden="true"></i
                                ></span>
                                <div>
                                    <h2
                                        id="heading-batching"
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Batching
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        Control batch creation, series, and
                                        material consumption.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 p-5"
                            >
                                <!-- batch_creation_source -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Create Batches From</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >batch_creation_source</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model="
                                                form.settings
                                                    .batch_creation_source
                                            "
                                            :options="[
                                                {
                                                    label: 'Sales Order',
                                                    value: 'sales_order',
                                                },
                                                {
                                                    label: 'Batch Schedule',
                                                    value: 'batch_schedule',
                                                },
                                                {
                                                    label: 'Sales Order or Batch Schedule',
                                                    value: 'both',
                                                },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- batch_series_enabled -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Show Batch Series Selection</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >batch_series_enabled</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings
                                                    .batch_series_enabled
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings
                                                    .batch_series_enabled
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings
                                                    .batch_series_enabled
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- default_batch_series -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Default Batch Series</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >default_batch_series</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model="
                                                form.settings
                                                    .default_batch_series
                                            "
                                            :options="[
                                                {
                                                    label: 'Numeric (Default, no prefix)',
                                                    value: 'NONE',
                                                },
                                                {
                                                    label: 'B Series',
                                                    value: 'B',
                                                },
                                                {
                                                    label: 'P Series (Commercial Counter)',
                                                    value: 'P',
                                                },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- batch_sheet_driver -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Batch Sheet PDF Driver</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >batch_sheet_driver</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model="
                                                form.settings.batch_sheet_driver
                                            "
                                            :options="batchSheetDriverOptions"
                                            optionLabel="label"
                                            optionValue="value"
                                            filter
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- sheet_upload -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Upload Batch Sheet</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >sheet_upload</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="form.settings.sheet_upload"
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.sheet_upload
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.sheet_upload
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- hide_batch_form -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Hide Add &amp; Edit Batch
                                            Forms</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >hide_batch_form</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings.hide_batch_form
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.hide_batch_form
                                                    ? 'text-rose-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.hide_batch_form
                                                    ? "Forms Hidden"
                                                    : "Forms Visible"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- target_to_actual -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >One-Click Target to Actual</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >target_to_actual</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings.target_to_actual
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.target_to_actual
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.target_to_actual
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- actual_consumption_variation -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Actual Consumption Variation
                                            (+/-)</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >actual_consumption_variation</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputNumber
                                            v-model="
                                                form.settings
                                                    .actual_consumption_variation
                                            "
                                            :min="0"
                                            :maxFractionDigits="3"
                                            class="w-full text-xs font-semibold"
                                        />
                                    </div>
                                </div>

                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <label
                                        for="dispatch-discount-tax-mode"
                                        class="text-xs font-bold text-slate-800"
                                        >Dispatch Discount Calculation</label
                                    >
                                    <Dropdown
                                        inputId="dispatch-discount-tax-mode"
                                        v-model="
                                            form.settings
                                                .dispatch_discount_tax_mode
                                        "
                                        :options="[
                                            {
                                                label: 'After tax (existing process)',
                                                value: 'after_tax',
                                            },
                                            {
                                                label: 'Before tax',
                                                value: 'before_tax',
                                            },
                                        ]"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                    />
                                    
                                    <small class="text-red-500">{{
                                        form.errors[
                                            "settings.dispatch_discount_tax_mode"
                                        ]
                                    }}</small>
                                </div>

                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <label
                                        for="invoice-quantity-basis"
                                        class="text-xs font-bold text-slate-800"
                                        >Invoice Quantity Basis</label
                                    >
                                    <Dropdown
                                        inputId="invoice-quantity-basis"
                                        v-model="
                                            form.settings.invoice_quantity_basis
                                        "
                                        :options="[
                                            {
                                                label: 'Batch size (existing process)',
                                                value: 'batch_size',
                                            },
                                            {
                                                label: 'Delivered quantity',
                                                value: 'delivered_qty',
                                            },
                                        ]"
                                        optionLabel="label"
                                        optionValue="value"
                                        class="w-full"
                                    />
                                    
                                    <small class="text-red-500">{{
                                        form.errors[
                                            "settings.invoice_quantity_basis"
                                        ]
                                    }}</small>
                                </div>

                                <!-- auto_carry_pump -->
                                <div class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3">
                                    <div class="setting-label min-w-0 flex flex-col items-start gap-1">
                                        <span class="text-xs font-bold text-slate-800">Show Delivered Quantity</span>
                                        <code class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono">show_delivered_qty</code>
                                    </div>
                                    <div class="min-w-0 w-full flex items-center gap-3">
                                        <InputSwitch inputId="show-delivered-qty" v-model="form.settings.show_delivered_qty" />
                                        <span class="text-xs font-bold" :class="form.settings.show_delivered_qty ? 'text-emerald-600' : 'text-slate-400'">
                                            {{ form.settings.show_delivered_qty ? 'Enabled' : 'Disabled' }}
                                        </span>
                                    </div>
                                    <small class="text-red-500">{{ form.errors['settings.show_delivered_qty'] }}</small>
                                </div>
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Auto-Select Previous Batch
                                            Pump</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >auto_carry_pump</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings.auto_carry_pump
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.auto_carry_pump
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.auto_carry_pump
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- default_transport -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Default Transporter Name</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >default_transport</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="
                                                form.settings.default_transport
                                            "
                                            placeholder="e.g. In-house fleet"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- is_grade_or_is_mix_design -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Document Item Name Format</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >is_grade_or_is_mix_design</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model="
                                                form.settings
                                                    .is_grade_or_is_mix_design
                                            "
                                            :options="[
                                                {
                                                    label: 'Grade Only',
                                                    value: 0,
                                                },
                                                {
                                                    label: 'Mix Design Only',
                                                    value: 1,
                                                },
                                                {
                                                    label: 'Grade + Mix Design',
                                                    value: 2,
                                                },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- TAB 2: WEIGHBRIDGE & CAMERA -->
                        <section
                            id="settings-weighbridge"
                            aria-labelledby="heading-weighbridge"
                            class="settings-group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                                    ><i
                                        class="pi pi-camera"
                                        aria-hidden="true"
                                    ></i
                                ></span>
                                <div>
                                    <h2
                                        id="heading-weighbridge"
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Weighbridge &amp; Camera
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        Configure weighing operations and
                                        connected cameras.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 p-5"
                            >
                                <!-- new_weight -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Weighbridge API Mode</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >new_weight</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model.number="
                                                form.settings.new_weight
                                            "
                                            @change="
                                                form.settings.newweight =
                                                    form.settings.new_weight
                                            "
                                            :options="[
                                                {
                                                    label: '0 — Disabled (Web Serial Port)',
                                                    value: 0,
                                                },
                                                {
                                                    label: '1 — Port 8089 (HTTP localhost:8089)',
                                                    value: 1,
                                                },
                                                {
                                                    label: '2 — Port 8074 (HTTPS localhost:8074)',
                                                    value: 2,
                                                },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- manual_weight -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Manual Weight Entry</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >manual_weight</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings.manual_weight
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.manual_weight
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.manual_weight
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- weight_mandatory -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Weighbridge Mandatory</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >weight_mandatory</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings.weight_mandatory
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.weight_mandatory
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.weight_mandatory
                                                    ? "Mandatory"
                                                    : "Optional"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- with_inventory -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Stock Deduction with
                                            Inventory</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >with_inventory</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings.with_inventory
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.with_inventory
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.with_inventory
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- camera -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Enable Camera Snapshots</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >camera</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="form.settings.camera"
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings.camera
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings.camera
                                                    ? "Enabled"
                                                    : "Disabled"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- camera_url -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Default Camera URL</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >camera_url</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.camera_url"
                                            placeholder="http://127.0.0.1:8089/api/cameras/{id}/snapshot"
                                            class="w-full text-xs font-mono"
                                            :disabled="!form.settings.camera"
                                        />
                                    </div>
                                </div>

                                <!-- camera_url_1 -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Camera 1 URL (Entry / Empty)</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >camera_url_1</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.camera_url_1"
                                            placeholder="Optional specific snapshot URL"
                                            class="w-full text-xs font-mono"
                                            :disabled="!form.settings.camera"
                                        />
                                    </div>
                                </div>

                                <!-- camera_url_2 -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Camera 2 URL (Exit / Loaded)</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >camera_url_2</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.camera_url_2"
                                            placeholder="Optional specific snapshot URL"
                                            class="w-full text-xs font-mono"
                                            :disabled="!form.settings.camera"
                                        />
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- TAB 3: DOCUMENT NUMBERING & PREFIXES -->
                        <section
                            id="settings-numbering"
                            aria-labelledby="heading-numbering"
                            class="settings-group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                                    ><i
                                        class="pi pi-hashtag"
                                        aria-hidden="true"
                                    ></i
                                ></span>
                                <div>
                                    <h2
                                        id="heading-numbering"
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Document Numbering
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        Set document prefixes and customise
                                        invoice sequences.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 p-5"
                            >
                                <!-- po_prefix -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Purchase Order Prefix</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >po_prefix</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.po_prefix"
                                            placeholder="PO"
                                            class="w-full text-xs font-mono uppercase"
                                        />
                                    </div>
                                </div>

                                <!-- so_prefix -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Sales Order Prefix</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >so_prefix</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.so_prefix"
                                            placeholder="SO"
                                            class="w-full text-xs font-mono uppercase"
                                        />
                                    </div>
                                </div>

                                <!-- cpo_prefix -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Customer PO Prefix</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >cpo_prefix</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.cpo_prefix"
                                            placeholder="CPO"
                                            class="w-full text-xs font-mono uppercase"
                                        />
                                    </div>
                                </div>

                                <!-- quote_prefix -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Quotation Prefix</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >quote_prefix</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.quote_prefix"
                                            placeholder="QT"
                                            class="w-full text-xs font-mono uppercase"
                                        />
                                    </div>
                                </div>

                                <!-- invoice_number_format -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <span
                                        class="text-xs font-bold text-slate-800"
                                        >Invoice PDF — Batch No</span
                                    >
                                    <div class="flex items-center gap-3">
                                        <InputSwitch
                                            v-model="
                                                form.settings
                                                    .invoice_show_batch_no
                                            "
                                            aria-label="Show Batch No on invoice PDF"
                                        />
                                        <span class="text-xs text-slate-500"
                                            >Show batch number</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <span
                                        class="text-xs font-bold text-slate-800"
                                        >Invoice PDF — Site Ref</span
                                    >
                                    <div class="flex items-center gap-3">
                                        <InputSwitch
                                            v-model="
                                                form.settings
                                                    .invoice_show_site_ref
                                            "
                                            aria-label="Show Site Ref on invoice PDF"
                                        />
                                        <span class="text-xs text-slate-500"
                                            >Show site reference</span
                                        >
                                    </div>
                                </div>
                                <div class="setting-field min-w-0 flex flex-col gap-3">
                                    <span class="text-xs font-bold text-slate-800">3-box Invoice PDF — Customer PO Number</span>
                                    <div class="flex items-center gap-3">
                                        <InputSwitch v-model="form.settings.invoice_show_customer_po_no"
                                            aria-label="Show Customer PO Number on 3-box invoice PDF" />
                                        <span class="text-xs text-slate-500">Show customer PO number</span>
                                    </div>
                                </div>
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Invoice Number Format</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >invoice_number_format</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model="
                                                form.settings
                                                    .invoice_number_format
                                            "
                                            :options="[
                                                {
                                                    label: '0 — Default: Prefix / FY / Number',
                                                    value: 0,
                                                },
                                                {
                                                    label: '1 — Custom: Prefix / Number / FY',
                                                    value: 1,
                                                },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- invoice_number_start & padding -->
                                <div
                                    class="setting-field min-w-0 sm:col-span-2 lg:col-span-6 flex flex-col gap-2 rounded-md bg-white/40 p-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-indigo-950"
                                            >Invoice Sequence Start &amp;
                                            Digits</span
                                        >
                                        <span
                                            class="text-[11px] font-mono font-bold text-indigo-700 bg-indigo-100/70 px-2 py-0.5 rounded border border-indigo-200"
                                        >
                                            {{ numberingPreview("invoice") }}
                                        </span>
                                        <p class="text-[11px] text-slate-500">
                                            By default, the sequence starts at 1
                                            with no zero padding (0 digits).
                                        </p>
                                    </div>
                                    <div
                                        class="min-w-0 w-full grid grid-cols-2 gap-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[11px] font-bold text-slate-500 whitespace-nowrap"
                                                >Start:</span
                                            >
                                            <InputNumber
                                                v-model="
                                                    form.settings
                                                        .invoice_number_start
                                                "
                                                :min="1"
                                                :max="9999999999"
                                                class="w-full text-xs"
                                            />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[11px] font-bold text-slate-500 whitespace-nowrap"
                                                >Digits:</span
                                            >
                                            <InputNumber
                                                v-model="
                                                    form.settings
                                                        .invoice_number_padding
                                                "
                                                :min="0"
                                                :max="10"
                                                class="w-full text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- billing_number_format -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Billing Number Format</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >billing_number_format</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <Dropdown
                                            v-model="
                                                form.settings
                                                    .billing_number_format
                                            "
                                            :options="[
                                                {
                                                    label: '0 — Default: Prefix / FY / Number',
                                                    value: 0,
                                                },
                                                {
                                                    label: '1 — Custom: Prefix / Number / FY',
                                                    value: 1,
                                                },
                                            ]"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full text-xs font-medium"
                                        />
                                    </div>
                                </div>

                                <!-- billing_number_start & padding -->
                                <div
                                    class="setting-field min-w-0 sm:col-span-2 lg:col-span-6 flex flex-col gap-2 rounded-md bg-white/40 p-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-indigo-950"
                                            >Billing Sequence Start &amp;
                                            Digits</span
                                        >
                                        <span
                                            class="text-[11px] font-mono font-bold text-indigo-700 bg-indigo-100/70 px-2 py-0.5 rounded border border-indigo-200"
                                        >
                                            {{ numberingPreview("billing") }}
                                        </span>
                                        <p class="text-[11px] text-slate-500">
                                            By default, the sequence starts at 1
                                            with no zero padding (0 digits).
                                        </p>
                                    </div>
                                    <div
                                        class="min-w-0 w-full grid grid-cols-2 gap-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[11px] font-bold text-slate-500 whitespace-nowrap"
                                                >Start:</span
                                            >
                                            <InputNumber
                                                v-model="
                                                    form.settings
                                                        .billing_number_start
                                                "
                                                :min="1"
                                                :max="9999999999"
                                                class="w-full text-xs"
                                            />
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-[11px] font-bold text-slate-500 whitespace-nowrap"
                                                >Digits:</span
                                            >
                                            <InputNumber
                                                v-model="
                                                    form.settings
                                                        .billing_number_padding
                                                "
                                                :min="0"
                                                :max="10"
                                                class="w-full text-xs"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- TAB 4: QUOTATION & SALES -->
                        <section
                            id="settings-quotation"
                            aria-labelledby="heading-quotation"
                            class="settings-group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                                    ><i
                                        class="pi pi-file-edit"
                                        aria-hidden="true"
                                    ></i
                                ></span>
                                <div>
                                    <h2
                                        id="heading-quotation"
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Quotations &amp; Sales
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        Choose defaults for quotations and sales
                                        workflows.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 p-5"
                            >
                                <!-- quote_validity -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Quotation Validity Offset
                                            (Days)</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >quote_validity</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputNumber
                                            v-model="
                                                form.settings.quote_validity
                                            "
                                            :min="1"
                                            :max="365"
                                            class="w-full text-xs font-semibold"
                                        />
                                    </div>
                                </div>

                                <!-- quotation_price_list -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Quotation as Price List (Hide
                                            Totals)</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >quotation_price_list</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings
                                                    .quotation_price_list
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings
                                                    .quotation_price_list
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings
                                                    .quotation_price_list
                                                    ? "Price List Mode Enabled"
                                                    : "Normal Quotation"
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- TAB 5: PRINTING & DELIVERY -->
                        <section
                            id="settings-printing"
                            aria-labelledby="heading-printing"
                            class="settings-group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                                    ><i
                                        class="pi pi-print"
                                        aria-hidden="true"
                                    ></i
                                ></span>
                                <div>
                                    <h2
                                        id="heading-printing"
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Printing &amp; Delivery
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        Personalise batch sheets and delivery
                                        documents.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 p-5"
                            >
                                <!-- print_delivery_ingredients -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Batching Ingredients on Delivery
                                            Token</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >print_delivery_ingredients</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <InputSwitch
                                            v-model="
                                                form.settings
                                                    .print_delivery_ingredients
                                            "
                                        />
                                        <span
                                            class="text-xs font-bold"
                                            :class="
                                                form.settings
                                                    .print_delivery_ingredients
                                                    ? 'text-emerald-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{
                                                form.settings
                                                    .print_delivery_ingredients
                                                    ? "Print Ingredients"
                                                    : "Hide Ingredients"
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <!-- batch_sheet_logo -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div class="min-w-0 w-full space-y-1.5">
                                        <div class="flex items-center gap-2.5">
                                            <span
                                                class="text-xs font-bold text-slate-800"
                                                >Batch Sheet Custom Logo</span
                                            >
                                            <code
                                                class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                                >batch_sheet_logo</code
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                batchSheetLogoUrl &&
                                                !form.remove_batch_sheet_logo
                                            "
                                            class="pt-1"
                                        >
                                            <img
                                                :src="batchSheetLogoUrl"
                                                alt="Current Logo"
                                                class="max-h-10 max-w-36 object-contain bg-slate-50 rounded border border-slate-200 p-1"
                                            />
                                        </div>
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex flex-col gap-2"
                                    >
                                        <input
                                            ref="batchSheetLogoInput"
                                            type="file"
                                            accept="image/png,image/jpeg"
                                            @change="selectBatchSheetLogo"
                                            class="text-xs text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                                        />
                                        <label
                                            v-if="batchSheetLogoUrl"
                                            class="inline-flex items-center gap-1.5 text-xs text-rose-600 cursor-pointer font-medium"
                                        >
                                            <input
                                                v-model="
                                                    form.remove_batch_sheet_logo
                                                "
                                                type="checkbox"
                                                @change="
                                                    form.batch_sheet_logo =
                                                        null;
                                                    if (batchSheetLogoInput)
                                                        batchSheetLogoInput.value =
                                                            '';
                                                "
                                                class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                                            />
                                            <span>Remove custom logo</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- TAB 6: APPEARANCE & CUSTOM -->
                        <section
                            id="settings-custom"
                            aria-labelledby="heading-custom"
                            class="settings-group scroll-mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 px-5 py-4"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                                    ><i
                                        class="pi pi-sliders-h"
                                        aria-hidden="true"
                                    ></i
                                ></span>
                                <div>
                                    <h2
                                        id="heading-custom"
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Appearance &amp; Custom
                                    </h2>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-slate-500"
                                    >
                                        Manage your loading image and additional
                                        plant parameters.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 p-5"
                            >
                                <!-- loader_gif -->
                                <div
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >Global Loader GIF URL</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >loader_gif</code
                                        >
                                    </div>
                                    <div class="min-w-0 w-full">
                                        <InputText
                                            v-model="form.settings.loader_gif"
                                            placeholder="/storage/loaders/truck.gif"
                                            class="w-full text-xs font-mono"
                                        />
                                    </div>
                                </div>

                                <!-- Dynamic custom parameters -->
                                <div
                                    v-for="(p, index) in form.settings
                                        .custom_params"
                                    :key="index"
                                    class="setting-field min-w-0 col-span-1 lg:col-span-1 flex flex-col gap-3"
                                >
                                    <div
                                        class="setting-label min-w-0 flex flex-col items-start gap-1"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-800"
                                            >{{ p.label }}</span
                                        >
                                        <code
                                            class="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200/60 font-mono"
                                            >{{ p.key }}</code
                                        >
                                    </div>
                                    <div
                                        class="min-w-0 w-full flex items-center gap-3"
                                    >
                                        <div class="flex-1">
                                            <InputSwitch
                                                v-if="p.type === 'bool'"
                                                v-model="p.value"
                                            />
                                            <InputText
                                                v-else
                                                v-model="p.value"
                                                class="w-full text-xs font-mono"
                                            />
                                        </div>
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            size="small"
                                            @click="removeParameter(index)"
                                        />
                                    </div>
                                </div>

                                <div
                                    v-if="!form.settings.custom_params.length"
                                    class="sm:col-span-2 lg:col-span-6 py-3 text-xs text-slate-500"
                                >
                                    No additional custom parameters defined for
                                    this plant.
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
                <!-- Footer Save Action Button -->
                <div
                    class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur"
                >
                    <p class="text-sm text-slate-500" role="status">
                        {{
                            form.isDirty
                                ? "You have unsaved changes."
                                : "Settings are up to date."
                        }}
                    </p>
                    <Button
                        icon="pi pi-check"
                        label="Save Changes"
                        size="small"
                        class="rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 border-indigo-600 text-white shadow-xs px-6 py-2.5"
                        :loading="form.processing"
                        @click="submit"
                    />
                </div>
            </div>
        </div>

        <!-- Add Custom Parameter Modal -->
        <Dialog
            v-model:visible="showParamDialog"
            header="Add Parameter"
            :style="{ width: '380px' }"
            modal
        >
            <div class="space-y-3 py-2">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600"
                        >Label</label
                    >
                    <InputText
                        v-model="paramForm.label"
                        placeholder="e.g. Max Batch Size"
                        class="w-full text-xs"
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600"
                        >Key (unique)</label
                    >
                    <InputText
                        v-model="paramForm.key"
                        placeholder="e.g. max_batch_size"
                        class="w-full text-xs font-mono"
                    />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-600">Type</label>
                    <Dropdown
                        v-model="paramForm.type"
                        :options="[
                            { label: 'Text / Value', value: 'text' },
                            { label: 'Toggle (On / Off)', value: 'bool' },
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full text-xs"
                    />
                </div>
            </div>
            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    text
                    size="small"
                    @click="showParamDialog = false"
                />
                <Button
                    label="Add"
                    icon="pi pi-plus"
                    size="small"
                    @click="addParameter"
                />
            </template>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.settings-page {
    background: #f6f8fc;
}
.settings-nav-link {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    padding: 0.75rem;
    border-radius: 0.625rem;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    transition:
        background-color 150ms,
        color 150ms;
}
.settings-nav-link:hover,
.settings-nav-link:focus-visible {
    color: #4338ca;
    background: #eef2ff;
}
.settings-group:target {
    border-color: #a5b4fc;
}
.setting-label {
    min-height: 2rem;
    justify-content: center;
}
.setting-label > span {
    font-weight: 500;
    font-size: 0.8125rem;
    line-height: 1.4;
}
.setting-field code {
    display: none;
}
.setting-field :deep(.p-inputtext),
.setting-field :deep(.p-inputnumber),
.setting-field :deep(.p-dropdown),
.setting-field :deep(.p-select) {
    min-width: 0;
    width: 100%;
}
.setting-field :deep(.p-inputtext),
.setting-field :deep(.p-dropdown),
.setting-field :deep(.p-select),
.setting-field input[type="file"] {
    background-color: #fff;
    color: #0f172a;
    border-color: #cbd5e1;
    border-radius: 0.5rem;
}
.setting-field :deep(.p-inputtext) {
    padding: 0.625rem 0.75rem;
    font-size: 0.8125rem;
}
.setting-field :deep(.p-dropdown-label),
.setting-field :deep(.p-select-label) {
    white-space: normal;
    font-size: 0.75rem;
}
.setting-field :deep(.p-inputswitch) {
    flex-shrink: 0;
}
.setting-field input[type="file"] {
    width: 100%;
    min-width: 0;
}
.setting-field :deep(.p-inputtext:focus),
.setting-field :deep(.p-dropdown:focus-within),
.setting-field :deep(.p-select:focus-within) {
    border-color: #818cf8;
    box-shadow: 0 0 0 3px #eef2ff;
}
.setting-field > div:last-child:has(.p-inputswitch) {
    min-height: 42px;
    padding: 0.5rem 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
}
</style>
