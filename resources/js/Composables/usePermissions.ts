import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Composable to check Spatie permissions on the frontend.
 * Permissions are shared from the backend via HandleInertiaRequests.
 *
 * Usage:
 *   const { can, isSuperAdmin } = usePermissions();
 *   if (can('users.create')) { ... }
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(() =>
        (page.props.user_permissions as string[]) ?? []
    );

    const userRole = computed<string>(() =>
        (page.props.user_role as string) ?? ''
    );

    const isSuperAdmin = computed(() =>
        userRole.value === 'Super Administrator' || userRole.value === 'Saas Owner'
    );

    /**
     * Check if the current user has a specific permission.
     * Super Administrators always return true (all permissions granted).
     */
    const can = (permission: string): boolean => {
        if (isSuperAdmin.value) return true;
        return permissions.value.includes(permission);
    };

    return { can, isSuperAdmin, permissions, userRole };
}
