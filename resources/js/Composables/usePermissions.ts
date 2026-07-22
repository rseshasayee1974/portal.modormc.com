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
        ['Super Administrator', 'Platform Admin', 'Saas Owner', 'Super Admin'].includes(userRole.value)
    );

    const isAdmin = computed(() =>
        ['Administrator', 'Admin'].includes(userRole.value)
    );

    /**
     * Check if the current user has a specific permission.
     * Super Administrators always return true (all permissions granted).
     */
    const can = (permission: string): boolean => {
        if (isAdmin.value) return true;
        const normalized = permission.toLowerCase();
        // console.log('normalized', normalized);
        // console.log('permissions', permissions.value);
        // console.log('isSuperAdmin', isSuperAdmin.value);

        return permissions.value.some(p => p.toLowerCase() === normalized);
    };

    return { can, isSuperAdmin, isAdmin, permissions, userRole };
}
