/*
Author: ragul-onemodo
Created: 2026-11-06 15:20:30 Asia/Calcutta (UTC+05:30)
*/
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useDocumentNumberPermission(module: string) {
    const page = usePage();
    return computed(() => (page.props.document_number_permissions as Record<string, boolean> | undefined)?.[module] === true);
}
