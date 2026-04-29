import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface Plan {
    id: number;
    plan_type: string;
    price_monthly: number;
    monthly_plan_description: string | null;
    price_yearly: number;
    yearly_plan_description: string | null;
    max_users: number;
    features_json: string[] | null;
    is_active: number | boolean;
}

export const usePlanStore = defineStore('plan', () => {
    const plans = ref<Plan[]>([]);

    function setPlans(data: Plan[]) {
        plans.value = data;
    }

    function addPlan(item: Plan) {
        plans.value.push(item);
    }

    function updatePlan(updatedItem: Plan) {
        const index = plans.value.findIndex(a => a.id === updatedItem.id);
        if (index !== -1) {
            plans.value[index] = updatedItem;
        }
    }

    function removePlan(id: number) {
        plans.value = plans.value.filter(a => a.id !== id);
    }

    return {
        plans,
        setPlans,
        addPlan,
        updatePlan,
        removePlan
    };
});
