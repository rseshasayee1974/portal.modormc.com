import { computed, ref, unref, type Ref } from 'vue';

export type ValidationErrors = Record<string, string>;
export type RuleFn<TValues> = (values: TValues) => string | null;
export type Rules<TValues> = Partial<Record<keyof TValues & string, RuleFn<TValues>[]>>;

export function useValidation<TValues extends Record<string, any>>(
    values: Ref<TValues> | TValues,
    rules: Rules<TValues>
) {
    const errors = ref<ValidationErrors>({});

    function clear() {
        errors.value = {};
    }

    function validate(): boolean {
        const v = unref(values) as TValues;
        const next: ValidationErrors = {};

        for (const key of Object.keys(rules) as (keyof TValues & string)[]) {
            const fns = rules[key] ?? [];
            for (const fn of fns) {
                const msg = fn(v);
                if (msg) {
                    next[key] = msg;
                    break;
                }
            }
        }

        errors.value = next;
        return Object.keys(next).length === 0;
    }

    const hasErrors = computed(() => Object.keys(errors.value).length > 0);

    return { errors, hasErrors, validate, clear };
}

// Common rules
export const rules = {
    required: <T extends Record<string, any>>(field: keyof T & string, label?: string) =>
        (values: T) => {
            const v = values[field];
            if (v === null || v === undefined || v === '') return `${label ?? field} is required.`;
            return null;
        },
    email: <T extends Record<string, any>>(field: keyof T & string, label?: string) =>
        (values: T) => {
            const v = values[field];
            if (!v) return null;
            const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v));
            return ok ? null : `${label ?? field} must be a valid email.`;
        },
};

