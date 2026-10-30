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
    const permissionIndex = computed(() =>
        new Set(permissions.value.map(permission => permission.toLowerCase()))
    );

    const userRole = computed<string>(() =>
        (page.props.user_role as string) ?? ''
    );

    const hasFullAccess = computed(() => page.props.has_full_access === true);
    const isSassOwner = computed(() =>
        hasFullAccess.value && (page.props.user_code === 'SAAS_OWNER' || userRole.value === 'Saas Owner')
    );
    const isSuperAdmin = computed(() => hasFullAccess.value && !isSassOwner.value);

    // Existing forms use this flag as a permission bypass.
    const isAdmin = hasFullAccess;

    /**
     * Check if the current user has a specific permission.
     * The backend grants full access only to SaaS Owner and Super Admin.
     */
    const can = (permission: string): boolean => {
        const normalized = permission.toLowerCase();
        if (hasFullAccess.value) return true;
        // console.log('normalized', normalized);
        // console.log('permissions', permissions.value);
        // console.log('isSuperAdmin', isSuperAdmin.value);

        return permissionIndex.value.has(normalized);
    };

    return { can, hasFullAccess, isSuperAdmin, isAdmin, permissions, userRole, isSassOwner };
}
