import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import { usePermissions } from '@/Composables/usePermissions';

declare const route: any;

export function canGenerateEInvoice(invoice: any): boolean {
    if (!invoice?.id || invoice.deleted_at || invoice.einvoice_irn || invoice.einv_irn) return false;
    const status = String(invoice.einvoice_status || invoice.einv_status || '').toLowerCase();
    return !['act', 'generated', 'can', 'cancelled'].includes(status)
        && String(invoice.status || '').toLowerCase() !== 'cancelled';
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
