import { computed, ref } from 'vue';
import { FilterMatchMode, FilterOperator } from 'primevue/api';

export function useDataTableFilters(options?: {
    globalFields?: string[];
}) {
    const global = ref('');

    const filters = ref<any>({
        global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    });

    const globalFilterFields = computed(() => options?.globalFields ?? []);

    function setGlobal(value: string) {
        global.value = value;
        filters.value.global.value = value || null;
    }

    return {
        global,
        filters,
        globalFilterFields,
        setGlobal,
        filterMode: 'lenient' as const,
    };
}

