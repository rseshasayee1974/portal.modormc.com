import { computed, unref, type Ref } from 'vue';
import { useForm as useInertiaForm } from '@inertiajs/vue3';

type Errors = Record<string, string | string[]>;

function firstError(v: string | string[] | undefined): string | undefined {
    if (!v) return undefined;
    return Array.isArray(v) ? v[0] : v;
}

export function useForm<TForm extends Record<string, any>>(initial: TForm) {
    const form = useInertiaForm<TForm>(initial);

    const errors = computed<Errors>(() => (form.errors ?? {}) as Errors);

    function errorFor(path: string): string | undefined {
        // Supports nested keys like "items.0.amount" and array syntax "items[0].amount"
        const key = path.replace(/\[(\d+)\]/g, '.$1');
        return firstError((errors.value as any)[key] ?? (errors.value as any)[path]);
    }

    function setApiErrors(payload: unknown) {
        // Accepts Laravel-ish: { errors: { field: ["msg"] } } or { errors: { field: "msg" } }
        const maybe = payload as any;
        if (maybe?.errors && typeof maybe.errors === 'object') {
            form.clearErrors();
            form.setError(maybe.errors);
        }
    }

    const isSubmitting = computed(() => Boolean(form.processing));

    return {
        ...form,
        errors,
        errorFor,
        setApiErrors,
        isSubmitting,
    };
}

