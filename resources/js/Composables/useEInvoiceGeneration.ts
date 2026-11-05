import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';

declare const route: any;

export function plantHasEInvoice(): boolean {
    return (usePage().props.active_plant as any)?.einvoice_enabled === true;
}

export function plantHasEwayBill(): boolean {
    return (usePage().props.active_plant as any)?.ewaybill_enabled === true;
}

export function canGenerateEInvoice(invoice: any): boolean {
    if (!plantHasEInvoice()) return false;
    if (!invoice?.id || invoice.deleted_at || invoice.einvoice_irn || invoice.einv_irn) return false;
    const status = String(invoice.einvoice_status || invoice.einv_status || '').toLowerCase();
    return !['act', 'generated'].includes(status) && !isEInvoiceCancelled(invoice)
        && String(invoice.status || '').toLowerCase() !== 'cancelled';
}

export function isEInvoiceCancelled(invoice: any): boolean {
    const status = String(invoice?.einvoice_status || invoice?.einv_status || '').trim().toLowerCase();
    return ['cnl', 'can', 'cancelled', 'canceled'].includes(status);
}

export function hasActiveEInvoice(invoice: any): boolean {
    if (!invoice || invoice.deleted_at || isEInvoiceCancelled(invoice)) return false;
    const status = String(invoice.einvoice_status || invoice.einv_status || '').trim().toLowerCase();
    return Boolean(invoice.einvoice_irn || invoice.einv_irn || ['act', 'generated'].includes(status));
}

export function canCancelEInvoice(invoice: any): boolean {
    return Boolean(invoice?.id && !invoice.deleted_at && (invoice.einvoice_irn || invoice.einv_irn))
        && !isEInvoiceCancelled(invoice);
}

export function useEInvoiceCancellation() {
    const { can } = usePermissions();
    const cancellingEInvoice = ref(false);
    const canCancel = (invoice: any) => (can('INVOICE.UPDATE') || can('INVOICES.UPDATE')) && canCancelEInvoice(invoice);

    async function cancelEInvoice(invoice: any, onCancelled?: () => void) {
        if (cancellingEInvoice.value || !canCancel(invoice)) return;
        cancellingEInvoice.value = true;
        try {
            const result = await Swal.fire({
                titleText: `Cancel E-Invoice for ${invoice.full_number || invoice.invoice_number || '#' + invoice.id}?`,
                icon: 'warning',
                html: `
                    <p>This cancels the registered IRN. An active E-Way Bill must be cancelled first.</p>
                    <label class="block mt-3 text-left" for="irn-cancel-reason">Cancellation reason</label>
                    <select id="irn-cancel-reason" class="w-full rounded-md border border-slate-300 p-2">
                        <option value="">Select a reason</option>
                        <option value="1">Duplicate</option>
                        <option value="2">Data entry mistake</option>
                        <option value="3">Order cancelled</option>
                        <option value="4">Other</option>
                    </select>
                    <label class="block mt-3 text-left" for="irn-cancel-remarks">Remarks</label>
                    <textarea id="irn-cancel-remarks" maxlength="100" class="w-full rounded-md border border-slate-300 p-2" rows="2"></textarea>
                `,
                showCancelButton: true,
                confirmButtonText: 'Cancel IRN',
                cancelButtonText: 'Keep E-Invoice',
                confirmButtonColor: '#be123c',
                focusConfirm: false,
                preConfirm: () => {
                    const reason = Swal.getPopup()?.querySelector<HTMLSelectElement>('#irn-cancel-reason')?.value;
                    const remarks = Swal.getPopup()?.querySelector<HTMLTextAreaElement>('#irn-cancel-remarks')?.value.trim();
                    if (!reason || !remarks) {
                        Swal.showValidationMessage('Select a reason and enter cancellation remarks.');
                        return false;
                    }
                    return { cancel_reason: reason, cancel_remarks: remarks };
                },
            });
            if (!result.isConfirmed || !result.value) return;

            await new Promise<void>(resolve => {
                router.post(route('invoices.cancel-einvoice', invoice.id), result.value, {
                    preserveScroll: true,
                    onSuccess: () => {
                        onCancelled?.();
                        Swal.fire({ icon: 'success', title: 'E-Invoice cancelled', text: 'The gateway confirmed IRN cancellation.' });
                    },
                    onError: (errors) => {
                        Swal.fire('Cancellation failed', Object.values(errors).flat().join('\n') || 'The IRN could not be cancelled.', 'error');
                    },
                    onFinish: () => resolve(),
                });
            });
        } finally {
            cancellingEInvoice.value = false;
        }
    }

    return { canCancel, cancelEInvoice, cancellingEInvoice };
}

export function useEInvoiceGeneration() {
    const { can } = usePermissions();
    const generatingEInvoice = ref(false);

    async function generateEInvoice(invoice: any, onGenerated?: () => void) {
        if (generatingEInvoice.value || !can('DISPATCH.GENERATE_EINVOICE') || !canGenerateEInvoice(invoice)) return;
        generatingEInvoice.value = true;
        try {
            const confirmation = await Swal.fire({
                title: 'Generate E-Invoice',
                text: `Generate an E-Invoice IRN for invoice ${invoice.full_number || invoice.invoice_number || '#' + invoice.id}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Generate E-Invoice',
                confirmButtonColor: '#7c3aed',
            });
            if (!confirmation.isConfirmed) return;

            await new Promise<void>(resolve => {
                router.post(route('invoices.generate-einvoice', invoice.id), { generate_eway: false }, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        onGenerated?.();
                        Swal.fire({
                            toast: true, position: 'top-end', icon: 'success',
                            title: 'E-Invoice IRN generated successfully.',
                            showConfirmButton: false, timer: 2000,
                        });
                    },
                    onError: (errors: Record<string, any>) => {
                        Swal.fire({
                            icon: 'error', title: 'E-Invoice Failed',
                            text: Object.values(errors || {}).flat().join('\n') || 'Failed to generate E-Invoice.',
                        });
                    },
                    onFinish: () => resolve(),
                });
            });
        } finally {
            generatingEInvoice.value = false;
        }
    }

    return { generateEInvoice, generatingEInvoice };
}
