import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';

/**
 * Composable – useInvoiceActions
 *
 * Handles all invoice-related actions inside the Batches module:
 *  - generateInvoiceDirect  : Show Swal form → POST to create invoice
 *  - printInvoiceDirect     : Open invoice in a new tab (view)
 *  - printOriginalInvoiceDirect     : Open invoice in a new tab (view original)
 *  - printDuplicateInvoiceDirect    : Open invoice in a new tab (view duplicate)
 *  - downloadInvoiceDirect  : Open invoice download link in a new tab
 *  - printEInvoiceDirect    : Open e-invoice view in a new tab
 *  - deleteInvoiceDirect    : Confirm + DELETE invoice and reset dispatch billing
 *  - sendWhatsAppDirect     : Fetch WhatsApp URL and open it
 */
export function useInvoiceActions(
    props: { sales_ledgers: { label: string; value: any }[] },
    onInvoiceChange?: (batchId: number) => void
) {

    // ── Generate Invoice ─────────────────────────────────────────────────────
    const generateInvoiceDirect = (dispatch: any) => {
        if (!dispatch || !dispatch.id) return;

        const ledgersOptionsHtml = props.sales_ledgers
            .map(l => `<option value="${l.value}">${l.label}</option>`)
            .join('');
        const defaultDate = new Date().toISOString().substring(0, 10);

        Swal.fire({
            title: 'Generate Invoice',
            html: `
                <div class="text-left space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Sales Ledger</label>
                        <select id="swal-ledger-id" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            <option value="">Select ...</option>
                            ${ledgersOptionsHtml}
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Invoice Date</label>
                        <input id="swal-invoice-date" type="date" value="${defaultDate}" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Generate',
            confirmButtonColor: '#4f46e5',
            preConfirm: () => {
                const ledgerId = (document.getElementById('swal-ledger-id') as HTMLSelectElement).value;
                const invoiceDate = (document.getElementById('swal-invoice-date') as HTMLInputElement).value;
                if (!ledgerId) {
                    Swal.showValidationMessage('Please select a Sales Ledger');
                    return false;
                }
                if (!invoiceDate) {
                    Swal.showValidationMessage('Please select an Invoice Date');
                    return false;
                }
                return { ledgerId, invoiceDate };
            },
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                router.post(
                    route('dispatches.generate-invoice', dispatch.id),
                    {
                        ledger_id: result.value.ledgerId,
                        invoice_date: result.value.invoiceDate,
                    },
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Invoice generated successfully.',
                                showConfirmButton: false,
                                timer: 2000,
                            });
                            if (onInvoiceChange) onInvoiceChange(dispatch.batch_id);
                        },
                    }
                );
            }
        });
    };

    // ── Print / Download Invoice ─────────────────────────────────────────────
    const printInvoiceDirect = (invoice: any) => {
        if (!invoice || !invoice.encrypted_id) return;
        window.open(
            route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'view' }),
            '_blank'
        );
    };

    const printOriginalInvoiceDirect = (invoice: any) => {
        if (!invoice || !invoice.encrypted_id) return;
        window.open(
            route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'view', force: 'original' }),
            '_blank'
        );
    };

    const printDuplicateInvoiceDirect = (invoice: any) => {
        if (!invoice || !invoice.encrypted_id) return;
        window.open(
            route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'view', force: 'duplicate' }),
            '_blank'
        );
    };


    const downloadInvoiceDirect = (invoice: any) => {
        if (!invoice || !invoice.encrypted_id) return;
        window.open(
            route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'download' }),
            '_blank'
        );
    };

    const generateEInvoiceDirect = (invoice: any, callback?: () => void) => {
        if (!invoice || !invoice.id) return;
        Swal.fire({
            title: 'Generate E-Invoice',
            text: `Are you sure you want to generate E-Invoice IRN for invoice #${invoice.full_number || invoice.invoice_number || ''}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Generate E-Invoice',
            confirmButtonColor: '#7c3aed',
            cancelButtonColor: '#64748b',
        }).then((result) => {
            if (result.isConfirmed) {
                router.post(
                    route('invoices.generate-einvoice', invoice.id),
                    {
                        generate_eway: false,
                    },
                    {
                        preserveScroll: true,
                        preserveState: true,
                        onSuccess: () => {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'E-Invoice IRN generated successfully.',
                                showConfirmButton: false,
                                timer: 2000,
                            });
                            if (callback) callback();
                            if (onInvoiceChange && invoice.dispatch?.batch_id) onInvoiceChange(invoice.dispatch.batch_id);
                        },
                        onError: (errors: any) => {
                            const msg = errors?.error || Object.values(errors || {}).flat().join('\n') || 'Failed to generate E-Invoice';
                            Swal.fire({
                                icon: 'error',
                                title: 'E-Invoice Failed',
                                text: msg,
                                confirmButtonColor: '#d33',
                            });
                        }
                    }
                );
            }
        });
    };

    const printEInvoiceDirect = (invoice: any) => {
        if (!invoice || !invoice.encrypted_id) return;
        window.open(
            route('print.document', { module: 'invoices', id: invoice.encrypted_id, action: 'view' }),
            '_blank'
        );
    };

    // ── Delete Invoice ───────────────────────────────────────────────────────
    const deleteInvoiceDirect = (dispatch: any) => {
        if (!dispatch || !dispatch.id) return;
        Swal.fire({
            title: 'Delete Invoice?',
            text: 'This will delete the generated invoice and reset the dispatch billing status. This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('dispatches.delete-invoice', dispatch.id), {
                    preserveScroll: true,
                    onSuccess: () => {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Invoice deleted successfully.',
                            showConfirmButton: false,
                            timer: 2000,
                        });
                        if (onInvoiceChange) onInvoiceChange(dispatch.batch_id);
                    },
                });
            }
        });
    };

    // ── Send WhatsApp ────────────────────────────────────────────────────────
    const sendWhatsAppDirect = async (dispatch: any) => {
        if (!dispatch || !dispatch.id) return;
        try {
            Swal.fire({
                title: 'Preparing WhatsApp message...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); },
            });

            const response = await axios.get(route('dispatches.whatsapp-url', dispatch.id));
            Swal.close();

            if (response.data.url) {
                window.open(response.data.url, '_blank');
            } else {
                Swal.fire('Error', 'Could not generate WhatsApp URL.', 'error');
            }
        } catch (error: any) {
            Swal.close();
            const msg =
                error.response?.data?.error ||
                'Failed to generate WhatsApp URL. Please check if customer mobile number exists.';
            Swal.fire('Error', msg, 'error');
        }
    };

    // ── Send Email ───────────────────────────────────────────────────────────
    const sendBatchEmailDirect = async (batch: any) => {
        if (!batch || !batch.id) return;
        try {
            Swal.fire({
                title: 'Sending email...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); },
            });

            const response = await axios.post(route('batches.send-email', batch.id));
            Swal.close();

            Swal.fire('Success', response.data.message || 'Batch report email sent successfully.', 'success');
        } catch (error: any) {
            Swal.close();
            const msg =
                error.response?.data?.error ||
                'Failed to send email. Please check if customer email address is configured.';
            Swal.fire('Error', msg, 'error');
        }
    };

    // ── Public API ───────────────────────────────────────────────────────────
    return {
        generateInvoiceDirect,
        generateEInvoiceDirect,
        printInvoiceDirect,
        printOriginalInvoiceDirect,
        printDuplicateInvoiceDirect,
        downloadInvoiceDirect,
        printEInvoiceDirect,
        deleteInvoiceDirect,
        sendWhatsAppDirect,
        sendBatchEmailDirect,
    };
}
