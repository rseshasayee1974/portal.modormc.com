import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

/**
 * Composable – useBatchActions
 *
 * Handles batch-level CRUD actions and utility helpers:
 *  - destroy        : Confirm + delete a batch record
 *  - downloadPdf    : Download the batch PDF report
 *  - retrySync      : Retry syncing a failed batch to the scheduler
 *  - statusSeverity : Map a status code to a PrimeVue Tag severity string
 *  - statusLabel    : Resolve a status code to its human-readable label
 */
export function useBatchActions(props: { statuses: { label: string; value: number }[] }) {

    // ── Delete Batch ─────────────────────────────────────────────────────────
    const destroy = (row: any) => {
        if (Number(row.status) === 5 || row?.dispatches?.[0]?.dispatch_status === 'Cancelled') {
            Swal.fire({
                icon: 'error',
                title: 'Cannot Delete',
                text: 'Cancelled batches cannot be deleted.',
            });
            return;
        }

        Swal.fire({
            title: 'Delete batch?',
            text: `Batch #${row.batch_no} will be deleted.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, delete',
        }).then((result) => {
            if (!result.isConfirmed) return;
            router.delete(route('batches.destroy', row.id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Batch deleted successfully.',
                        showConfirmButton: false,
                        timer: 1500,
                    });
                },
            });
        });
    };

    // ── Download PDF ─────────────────────────────────────────────────────────
    /**
     * Uses the anchor-click technique to avoid popup-blocker issues.
     */
    const downloadPdf = (id: number | string) => {
        const url  = route('batches.download', id);
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', '');
        link.setAttribute('target', '_blank');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    // ── Retry Scheduler Sync ─────────────────────────────────────────────────
    const retrySync = (id: number) => {
        router.post(route('batches.sync', id), {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const hasError = page.props?.flash?.error;
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: hasError ? 'error' : 'success',
                    title: hasError ? 'Sync failed again.' : 'Sync successful.',
                    showConfirmButton: false,
                    timer: 2000,
                });
            },
        });
    };

    // ── Status Helpers ───────────────────────────────────────────────────────
    const isEInvoiced = (row?: any): boolean => {
        if (!row) return false;
        if (row.has_einvoice || row.einvoice_irn || row.einvoice_status === 'generated') return true;
        const inv = row.dispatches?.[0]?.status?.invoice;
        if (inv && (inv.einvoice_irn || inv.einvoice_status === 'generated')) return true;
        return false;
    };

    const isInvoiced = (row?: any): boolean => {
        if (!row) return false;
        return Boolean(row.invoice_id || row.has_invoice || row.dispatches?.[0]?.status?.invoice_id || row.dispatches?.[0]?.status?.invoice_status === 1);
    };

    const statusSeverity = (status: number, row?: any): string => {
        if (status === 5 || row?.dispatches?.[0]?.dispatch_status === 'Cancelled') return 'danger';
        if (isEInvoiced(row)) return 'help';
        if (isInvoiced(row)) return 'success';
        if (status === 4) return 'success';
        if (status === 3) return 'info';
        if (status === 2) return 'info';
        return 'warn';
    };

    const statusLabel = (status: number, row?: any): string => {
        if (status === 5 || row?.dispatches?.[0]?.dispatch_status === 'Cancelled') return 'Cancelled';
        if (isEInvoiced(row)) return 'E-Invoiced';
        if (isInvoiced(row)) return 'Invoiced';
        return props.statuses.find((entry) => entry.value === status)?.label ?? 'Unknown';
    };

    // ── Public API ───────────────────────────────────────────────────────────
    return {
        destroy,
        downloadPdf,
        retrySync,
        statusSeverity,
        statusLabel,
    };
}
