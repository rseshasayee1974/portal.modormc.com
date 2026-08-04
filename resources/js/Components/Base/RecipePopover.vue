<script setup lang="ts">
import { ref, computed } from 'vue';
import { InformationCircleIcon } from '@heroicons/vue/24/outline';
import Popover from 'primevue/popover';

const props = defineProps<{
    mixDesignId: number | null | undefined;
    mixDesigns: any[];
}>();

const op = ref<InstanceType<typeof Popover> | null>(null);

const toggle = (event: Event) => {
    op.value?.toggle(event);
};

const design = computed(() => {
    if (!props.mixDesignId) return null;
    return props.mixDesigns.find(md => Number(md.id) === Number(props.mixDesignId));
});

const designName = computed(() => {
    return design.value?.design_name || design.value?.title || '-';
});

const materials = computed(() => {
    if (!design.value) return [];
    // Support both Quotation/PO style (items) and Sales Order style (ingredients)
    const rawItems = design.value.items || design.value.ingredients || [];
    return rawItems.map((it: any) => ({
        id: it.id || it.product_id || Math.random(),
        name: it.product?.title || it.name || 'Unknown Material',
        qty: Number(it.actual_quantity ?? it.qty ?? 0),
        uom: it.uom?.unit_code || it.uom || '',
    }));
});
</script>

<template>
    <div v-if="mixDesignId" class="flex-shrink-0">
        <button
            type="button"
            @click.stop="toggle"
            class="p-[0.5] rounded-full text-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
            title="View recipe details"
        >
            <InformationCircleIcon class="w-4 h-4" />
        </button>

        <Popover ref="op" class="z-[99999]">
            <div class="p-3 text-left w-72">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-indigo-500">Recipe Details</span>
                    <span class="rounded border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                        {{ designName }}
                    </span>
                </div>

                <div v-if="materials.length" class="flex flex-wrap gap-2">
                    <div
                        v-for="mat in materials"
                        :key="mat.id"
                        class="flex items-center gap-1 rounded-md border border-slate-100 bg-slate-50 px-2 py-1"
                    >
                        <span class="text-[11px] text-slate-600">{{ mat.name }}</span>
                        <span class="text-[11px] font-semibold text-indigo-600">
                            {{ mat.qty }}<span class="text-slate-400 ml-0.5">{{ mat.uom }}</span>
                        </span>
                    </div>
                </div>
                <p v-else class="text-xs italic text-slate-400">No materials configured for this recipe.</p>
            </div>
        </Popover>
    </div>
</template>
