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
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Invoice Number</label>
                        <input id="swal-invoice-number" type="text" placeholder="e.g. 101 (Prefix added automatically)" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Notes</label>
                        <textarea id="swal-notes" rows="2" placeholder="Enter invoice notes..." class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white"></textarea>
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
                const invoiceNumber = (document.getElementById('swal-invoice-number') as HTMLInputElement).value;
                const notes = (document.getElementById('swal-notes') as HTMLTextAreaElement).value;
                if (!ledgerId) {
                    Swal.showValidationMessage('Please select a Sales Ledger');
                    return false;
                }
                if (!invoiceDate) {
                    Swal.showValidationMessage('Please select an Invoice Date');
                    return false;
                }
                return { ledgerId, invoiceDate, invoiceNumber, notes };
            },
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                router.post(
                    route('dispatches.generate-invoice', dispatch.id),
                    {
                        ledger_id: result.value.ledgerId,
                        invoice_date: result.value.invoiceDate,
                        invoice_number: result.value.invoiceNumber,
                        notes: result.value.notes,
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
                        onError: (errors: any) => {
                            const errorMsg = errors.invoice_number || errors.error || Object.values(errors)[0] || 'Failed to generate invoice.';
                            Swal.fire({
                                icon: 'error',
                                title: 'Invoice Generation Failed',
                                text: String(errorMsg),
                                confirmButtonColor: '#d33',
                            });
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

    // ── Generate Standalone E-Way Bill (Without IRN) ──────────────────────────
    const generateEwayBillDirect = (batchOrInvoice: any, callback?: () => void) => {
        if (!batchOrInvoice) return;

        const isBatch = !!batchOrInvoice.batch_no || !batchOrInvoice.invoice_date;
        const batchId = isBatch ? (batchOrInvoice.id || batchOrInvoice.batch_id) : (batchOrInvoice.dispatch?.batch_id || null);
        const invoiceId = !isBatch ? batchOrInvoice.id : (batchOrInvoice.dispatches?.[0]?.status?.invoice_id || batchOrInvoice.invoice_id);

        const defaultVehNo = batchOrInvoice.truck_registration
            || batchOrInvoice.dispatches?.[0]?.truck?.registration
            || batchOrInvoice.vehicle_number
            || '';

        const defaultDistance = batchOrInvoice.dispatches?.[0]?.transport_km
            || batchOrInvoice.transport_km
            || 20;

        Swal.fire({
            title: 'Generate E-Way Bill',
            html: `
                <div class="text-left space-y-3">
                    <p class="text-xs text-slate-500 mb-2">
                        Generate a standard E-Way Bill directly without requiring an E-Invoice (IRN).
                    </p>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Vehicle Number *</label>
                        <input id="swal-ewb-veh-no" type="text" value="${defaultVehNo}" placeholder="e.g. TN09AB1234" class="w-full px-3 py-2 border rounded-md text-sm uppercase dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Distance (in KM)</label>
                        <input id="swal-ewb-distance" type="number" min="1" value="${defaultDistance}" placeholder="e.g. 25" class="w-full px-3 py-2 border rounded-md text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-white" />
                    </div>
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Generate E-Way Bill',
            confirmButtonColor: '#0d9488',
            cancelButtonColor: '#64748b',
            preConfirm: () => {
                const vehNo = (document.getElementById('swal-ewb-veh-no') as HTMLInputElement)?.value?.trim();
                const distance = (document.getElementById('swal-ewb-distance') as HTMLInputElement)?.value?.trim();
                if (!vehNo) {
                    Swal.showValidationMessage('Please enter a vehicle number');
                    return false;
                }
                return { vehNo, distance: Number(distance) || 20 };
            },
        }).then(async (result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    title: 'Generating E-Way Bill...',
                    text: 'Please wait while the E-Way Bill is being generated.',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); },
                });

                try {
                    const postUrl = batchId
                        ? route('batches.generate-ewaybill', batchId)
                        : route('invoices.generate-standalone-ewaybill', invoiceId);

                    const res = await axios.post(postUrl, {
                        veh_no: result.value.vehNo,
                        distance: result.value.distance,
                    });

                    Swal.close();

                    if (res.data?.success) {
                        const ewbNo = res.data.data?.eway_bill_no || '';
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: `E-Way Bill #${ewbNo} generated successfully.`,
                            showConfirmButton: false,
                            timer: 3000,
                        });
                        if (callback) callback();
                        if (onInvoiceChange && batchId) onInvoiceChange(batchId);
                    } else {
                        Swal.fire('Error', res.data?.message || 'Failed to generate E-Way Bill', 'error');
                    }
                } catch (error: any) {
                    Swal.close();
                    const msg = error.response?.data?.message || error.response?.data?.error || 'Failed to generate E-Way Bill.';
                    Swal.fire({
                        icon: 'error',
                        title: 'E-Way Bill Failed',
                        text: msg,
                        confirmButtonColor: '#d33',
                    });
                }
            }
        });
    };

    // ── Public API ───────────────────────────────────────────────────────────
    return {
        generateInvoiceDirect,
        generateEInvoiceDirect,
        generateEwayBillDirect,
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
