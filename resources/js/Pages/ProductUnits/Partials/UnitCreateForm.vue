<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import BaseFormActions from '@/Components/Base/BaseFormActions.vue';

const props = defineProps<{ unitTypes: string[] }>();

const toast = useToast();
const isOpen = ref(false);

const form = useForm({
    unit_name: '',
    unit_code:  '',
    unit_type:  null as string | null,
});

const unitTypeOptions = computed(() =>
    props.unitTypes.map(t => ({ label: t, value: t }))
);

const toggle = () => {
    isOpen.value = !isOpen.value;
    if (!isOpen.value) {
        form.reset();
        form.clearErrors();
    }
};

const submit = () => {
    form.post(route('productunits.store'), {
        onSuccess: () => {
            form.reset();
            isOpen.value = false;
            toast.add({ severity: 'success', summary: 'Unit Created', detail: 'New measurement unit added', life: 3000 });
        },
    });
};
</script>

<template>
    <div class="create-panel" :class="{ 'create-panel--open': isOpen }">
        <!-- ── Header ── -->
        <button class="create-panel__header" @click="toggle" type="button">
            <div class="flex items-center gap-3">
                <div class="create-panel__icon">
                    <i class="pi pi-ruler text-indigo-500 text-sm"></i>
                </div>
                <div class="text-left">
                    <p class="text-xs font-bold text-gray-700 uppercase tracking-widest">Register New Unit</p>
                    <p class="text-[11px] text-gray-400 font-medium mt-0.5">Define a measurement unit for inventory</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span v-if="!isOpen" class="create-panel__hint">Click to expand</span>
                <div class="create-panel__toggle" :class="{ 'create-panel__toggle--open': isOpen }">
                    <i class="pi pi-plus text-[10px]"></i>
                </div>
            </div>
        </button>

        <!-- ── Collapsible Body ── -->
        <Transition name="panel-slide">
            <div v-if="isOpen" class="create-panel__body">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                    <div class="field-group">
                        <label class="field-label">Unit Name <span class="text-rose-400">*</span></label>
                        <BaseInput
                            v-model="form.unit_name"
                            placeholder="e.g. Kilogram"
                            fluid 
                            :class="{ 'p-invalid': form.errors.unit_name }"
                            autofocus
                        />
                        <small v-if="form.errors.unit_name" class="field-error">{{ form.errors.unit_name }}</small>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Unit Code</label>
                        <BaseInput
                            v-model="form.unit_code"
                            placeholder="e.g. kg"
                            fluid 
                            :class="{ 'p-invalid': form.errors.unit_code }"
                        />
                        <small v-if="form.errors.unit_code" class="field-error">{{ form.errors.unit_code }}</small>
                    </div>

                    <div class="field-group">
                        <label class="field-label">Unit Type <span class="text-rose-400">*</span></label>
                        <BaseSelect
                            v-model="form.unit_type"
                            :options="unitTypeOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select type"
                            fluid 
                            :class="{ 'p-invalid': form.errors.unit_type }"
                        />
                        <small v-if="form.errors.unit_type" class="field-error">{{ form.errors.unit_type }}</small>
                    </div>

                </div>

                <div class="flex items-center justify-between mt-8 pt-4 border-t border-slate-100 dark:border-slate-800">
                    <p class="text-[11px] text-slate-400 italic px-1">Fields marked <span class="text-rose-500 font-bold">*</span> are required</p>
                    <BaseFormActions
                        mode="save"
                        save-label="Save Unit"
                        cancel-label="Reset"
                        :loading="form.processing"
                        @submit="submit"
                        @cancel="form.reset(); form.clearErrors()"
                        class="!mt-0 !pt-0 !border-none"
                    />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.create-panel {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
.create-panel--open {
    border-color: #c7d2fe;
    box-shadow: 0 0 0 3px rgba(99,102,241,0.07), 0 1px 3px rgba(0,0,0,0.05);
}

.create-panel__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 14px 18px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    transition: background 0.15s ease;
}
.create-panel__header:hover { background: #f8fafc; }

.create-panel__icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.create-panel__hint {
    font-size: 11px;
    color: #818cf8;
    font-weight: 600;
    letter-spacing: 0.03em;
}

.create-panel__toggle {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    background: #eef2ff;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    transition: background 0.2s, transform 0.25s ease;
    flex-shrink: 0;
}
.create-panel__toggle--open {
    transform: rotate(45deg);
    background: #6366f1;
    color: white;
}

.create-panel__body {
    padding: 20px 18px;
    border-top: 1px solid #eef2ff;
    background: linear-gradient(180deg, #fafbff 0%, #ffffff 100%);
}

.field-group { display: flex; flex-direction: column; gap: 5px; }
.field-label  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
.field-error  { font-size: 11px; color: #e11d48; }

/* Transition */
.panel-slide-enter-active { transition: all 0.22s cubic-bezier(0.4,0,0.2,1); }
.panel-slide-leave-active { transition: all 0.16s ease; }
.panel-slide-enter-from,
.panel-slide-leave-to     { opacity: 0; transform: translateY(-6px); }
</style>
