<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    dummyData: any;
    settings?: any;
}>();

const pdfSettings = computed(() => props.settings?.pdf || props.dummyData?.settings?.pdf || {});
const labels = computed(() => pdfSettings.value?.labels || {});

const company = computed(() => props.dummyData?.company || {});
const billTo = computed(() => props.dummyData?.bill_to || {});
const shipTo = computed(() => props.dummyData?.ship_to || {});
const items = computed(() => props.dummyData?.items || []);
const totals = computed(() => props.dummyData?.totals || {});
const meta = computed(() => props.dummyData?.meta || {});
</script>

<template>
    <div class="invoice-root">

        <!-- ═══ HEADER ═══ -->
        <div class="inv-header">
            <div class="inv-header-left">
                <div v-if="pdfSettings.company_name !== false" class="company-name">{{ company.name || 'Company Name' }}</div>
                <div v-if="pdfSettings.address !== false" class="company-address">
                    {{ company.address }} {{ company.city ? `, ${company.city}` : '' }} {{ company.state ? `, ${company.state}` : '' }} {{ company.pin ? `- ${company.pin}` : '' }}
                </div>
                <div v-if="pdfSettings.phone !== false && company.phone" class="company-address">Phone: {{ company.phone }}</div>
                <div v-if="pdfSettings.email !== false && company.email" class="company-address">Email: {{ company.email }}</div>
                <div v-if="pdfSettings.gstin !== false && company.gstin" class="company-gstin">GSTIN: {{ company.gstin }}</div>
            </div>
            <div class="inv-header-right">
                <div class="inv-title">{{ labels.invoice_title || dummyData.doc_title || 'DOCUMENT' }}</div>
                <div v-if="pdfSettings.invoice_number !== false" class="inv-number">
                    {{ (labels.invoice_title || dummyData.doc_title || '').includes('INVOICE') ? 'Invoice : ' : 'Ref : ' }} {{ dummyData.doc_no }}
                </div>
            </div>
        </div>

        <!-- ═══ INFO GRID ═══ -->
        <table class="info-table">
            <tbody>
                <tr>
                    <!-- Invoice Details column -->
                    <td class="info-cell info-details">
                        <table class="meta-table">
                            <tbody>
                                <tr v-if="pdfSettings.date !== false">
                                    <td class="meta-label">Date</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value">{{ dummyData.doc_date || dummyData.date }}</td>
                                </tr>
                                <tr v-if="pdfSettings.due_date !== false && dummyData.due_date">
                                    <td class="meta-label">Due Date</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value">{{ dummyData.due_date }}</td>
                                </tr>
                                <tr v-if="meta.po_number">
                                    <td class="meta-label">P.O.#</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value bold">{{ meta.po_number }}</td>
                                </tr>
                                <tr v-if="meta.project_name">
                                    <td class="meta-label">Project</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value bold">{{ meta.project_name }}</td>
                                </tr>
                                <tr v-if="pdfSettings.status && dummyData.state">
                                    <td class="meta-label">Status</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value bold">{{ dummyData.state }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <!-- Bill To column -->
                    <td class="info-cell info-bill">
                        <template v-if="pdfSettings.bill_to !== false">
                            <div class="address-header">{{ labels.bill_to || 'Bill To' }} :</div>
                            <div class="address-name">{{ billTo.name }}</div>
                            <div class="address-line">{{ billTo.address }}</div>
                            <div class="address-line">{{ billTo.city }} {{ billTo.state }} {{ billTo.pin }}</div>
                            <div v-if="pdfSettings.gstin !== false && billTo.gstin" class="address-line small">GSTIN: {{ billTo.gstin }}</div>
                        </template>
                    </td>

                    <!-- Ship To column -->
                    <td class="info-cell info-ship no-right-border">
                        <template v-if="pdfSettings.ship_to !== false">
                            <div class="address-header">{{ labels.ship_to || 'Ship To' }} :</div>
                            <div class="address-name">{{ shipTo.name }}</div>
                            <div class="address-line">{{ shipTo.address }}</div>
                            <div class="address-line">{{ shipTo.city }} {{ shipTo.state }} {{ shipTo.pin }}</div>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ═══ ITEMS TABLE ═══ -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-item text-left">Item</th>
                    <th v-if="pdfSettings.hsn_code !== false" class="col-hsn text-center">HSN/SAC</th>
                    <th v-if="pdfSettings.description !== false" class="col-desc text-left">Description</th>
                    <th class="col-qty text-right">Qty</th>
                    <th v-if="pdfSettings.unit !== false" class="col-unit text-right">Unit</th>
                    <th class="col-rate text-right">{{ labels.rate || 'Rate' }}</th>
                    <th class="col-amt text-right">{{ labels.amount || 'Amount' }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, idx) in items" :key="idx">
                    <td class="col-num text-center">{{ idx + 1 }}</td>
                    <td class="col-item">
                        <div class="item-name">{{ item.name || item.description }}</div>
                    </td>
                    <td v-if="pdfSettings.hsn_code !== false" class="col-hsn text-center">{{ item.hsn || '-' }}</td>
                    <td v-if="pdfSettings.description !== false" class="col-desc item-desc">{{ item.description || '-' }}</td>
                    <td class="col-qty text-right">{{ Number(item.qty || 0).toFixed(2) }}</td>
                    <td v-if="pdfSettings.unit !== false" class="col-unit text-right">{{ item.unit || 'm³' }}</td>
                    <td class="col-rate text-right">₹{{ Number(item.unit_price || item.rate || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                    <td class="col-amt text-right">₹{{ Number(item.total || item.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- ═══ NOTES + TOTALS ═══ -->
        <div class="notes-totals-row">
            <div class="notes-section">
                <div v-if="pdfSettings.notes !== false && (meta.notes || dummyData.notes)">
                    <span class="notes-label">Notes :</span>
                    <span class="notes-text">{{ meta.notes || dummyData.notes }}</span>
                </div>
                <div v-if="pdfSettings.terms !== false" class="terms-block">
                    <div class="notes-label">Terms & Conditions :</div>
                    <div class="terms-text whitespace-pre-line">{{ pdfSettings.terms_text || meta.terms_text || 'Payment due within 15 days of invoice date.' }}</div>
                </div>
            </div>
            <div class="totals-section">
                <table class="totals-table">
                    <tbody>
                        <tr>
                            <td class="total-label">Sub Total</td>
                            <td class="total-value">₹{{ Number(totals.sub_total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                        </tr>
                        <tr v-if="pdfSettings.discount !== false && totals.discount">
                            <td class="total-label">Discount</td>
                            <td class="total-value">(-) ₹{{ Number(totals.discount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                        </tr>
                        <template v-if="totals.tax_lines && totals.tax_lines.length">
                            <tr v-for="tLine in totals.tax_lines" :key="tLine.label">
                                <td class="total-label">{{ tLine.label }}</td>
                                <td class="total-value">₹{{ Number(tLine.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                            </tr>
                        </template>
                        <tr v-if="pdfSettings.shipping !== false && totals.shipping">
                            <td class="total-label">Shipping Charges</td>
                            <td class="total-value">₹{{ Number(totals.shipping || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                        </tr>
                        <tr class="total-row-bold">
                            <td class="total-label">Grand Total</td>
                            <td class="total-value bold">₹{{ Number(totals.grand_total || dummyData.total || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 }) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="pdfSettings.total_words !== false && (meta.total_words || totals.grand_total)" class="amount-words">
            <span class="words-label">Amount in Words:</span> {{ meta.total_words || 'Rupees Only' }}
        </div>

        <div v-if="pdfSettings.signature !== false" class="signature-row">
            <div class="sig-box">
                <div class="sig-title">For {{ company.name || 'Company' }}</div>
                <div class="sig-line">Authorized Signatory</div>
            </div>
        </div>

    </div>
</template>

<style scoped>
/* ── Base ── */
.invoice-root {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #1a1a1a;
    background: white;
    border: 1px solid #b0b0b0;
    width: 100%;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    line-height: 1.45;
}

/* ── HEADER ── */
.inv-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 14px 8px 14px;
    border-bottom: 1px solid #b0b0b0;
}

.company-name {
    font-size: 14px;
    font-weight: 700;
    color: #111;
    margin-bottom: 2px;
}

.company-address {
    font-size: 10px;
    color: #666;
    line-height: 1.4;
}

.inv-title {
    font-size: 20px;
    font-weight: 900;
    color: #111;
    text-align: right;
    letter-spacing: 0.01em;
    font-style: italic;
}

.inv-number {
    font-size: 11px;
    color: #555;
    text-align: right;
}

/* ── INFO TABLE ── */
.info-table {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1px solid #b0b0b0;
}

.info-cell {
    padding: 8px 12px;
    vertical-align: top;
    border-right: 1px solid #b0b0b0;
    font-size: 11px;
}

.no-right-border { border-right: none; }

.info-details { width: 33%; }
.info-bill     { width: 34%; }
.info-ship     { width: 33%; }

/* Meta key-value inside info-details */
.meta-table { border-collapse: collapse; width: 100%; }
.meta-label  { color: #444; padding: 1px 0; white-space: nowrap; }
.meta-colon  { padding: 1px 4px; color: #444; }
.meta-value  { color: #111; padding: 1px 0; }
.meta-value.bold { font-weight: 700; }

/* Address blocks */
.address-header { font-weight: 700; margin-bottom: 3px; color: #111; }
.address-name   { font-weight: 700; margin-bottom: 1px; }
.address-line   { color: #444; }

/* ── SUBJECT ROW ── */
.subject-row {
    padding: 5px 12px;
    border-bottom: 1px solid #b0b0b0;
    font-size: 11px;
    color: #333;
}
.subject-label { font-weight: 600; }

/* ── ITEMS TABLE ── */
.items-table {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1px solid #b0b0b0;
    font-size: 11px;
}

.items-table th {
    background: white;
    border: 1px solid #b0b0b0;
    border-left: none;
    border-right: none;
    padding: 5px 8px;
    font-weight: 700;
    font-size: 11px;
    color: #111;
    border-top: 1px solid #b0b0b0;
    border-bottom: 1px solid #b0b0b0;
}

.items-table th:first-child { border-left: none; }
.items-table th:last-child  { border-right: none; }

.items-table td {
    padding: 5px 8px;
    vertical-align: top;
    border-bottom: 1px solid #e0e0e0;
    color: #333;
}

.items-table tr:last-child td { border-bottom: none; }

.item-name { font-weight: 600; color: #111; }
.item-desc  { color: #555; font-size: 10.5px; }

/* Column widths */
.col-num  { width: 30px; text-align: center; }
.col-item { width: 18%; }
.col-desc { width: 30%; }
.col-qty  { width: 8%; }
.col-unit { width: 8%; }
.col-rate { width: 12%; }
.col-amt  { width: 12%; font-weight: 600; }

.text-left   { text-align: left !important; }
.text-right  { text-align: right !important; }
.text-center { text-align: center !important; }

/* ── NOTES + TOTALS ── */
.notes-totals-row {
    display: flex;
    border-bottom: 1px solid #b0b0b0;
    min-height: 120px;
}

.notes-section {
    flex: 1;
    padding: 8px 12px;
    border-right: 1px solid #b0b0b0;
    font-size: 11px;
}

.notes-label { font-weight: 700; color: #111; margin-right: 4px; }
.notes-text  { color: #444; }

.totals-section {
    width: 260px;
    padding: 0;
}

.totals-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.totals-table tr > td { padding: 3px 12px; vertical-align: middle; }

.total-label {
    color: #333;
    text-align: right;
    padding-right: 16px !important;
    width: 60%;
}

.total-value {
    color: #333;
    text-align: right;
    white-space: nowrap;
}

.underline-label { text-decoration: underline; }

.total-row-bold { border-top: 1px solid #b0b0b0; border-bottom: 1px solid #b0b0b0; }
.total-row-bold td { padding-top: 4px !important; padding-bottom: 4px !important; }

.balance-row { border-top: 1px solid #b0b0b0; }
.balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

.bold { font-weight: 700; color: #111; }
.red  { color: #cc0000; }

/* ── TOTAL IN WORDS ── */
.words-row {
    padding: 6px 12px;
    border-bottom: 1px solid #b0b0b0;
    font-size: 11px;
}

.words-label { font-weight: 700; color: #111; margin-right: 4px; }
.words-value  { color: #333; }

/* ── PAYMENT OPTIONS ── */
.payment-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 12px;
    border-bottom: 1px solid #b0b0b0;
    font-size: 11px;
}

.payment-label { font-weight: 600; color: #333; white-space: nowrap; }

.payment-icons { display: flex; align-items: center; gap: 6px; }

.paypal-badge {
    background: #003087;
    color: white;
    font-size: 9px;
    font-weight: 900;
    padding: 2px 7px;
    border-radius: 3px;
    font-style: italic;
    letter-spacing: 0.03em;
}

.card-badge {
    font-size: 14px;
    border: 1px solid #ccc;
    padding: 1px 5px;
    border-radius: 3px;
    background: #f5f5f5;
}

/* ── TERMS + SIGNATURE ── */
.terms-signature-row {
    display: flex;
    flex: 1;
    border-bottom: 1px solid #b0b0b0;
    min-height: 100px;
}

.terms-section {
    flex: 1;
    padding: 8px 12px;
    border-right: 1px solid #b0b0b0;
    font-size: 11px;
}

.terms-label { font-weight: 700; color: #111; margin-bottom: 3px; }
.terms-text  { color: #555; line-height: 1.5; }

.signature-section {
    width: 220px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    padding: 8px 16px 12px;
}

.signature-line  {
    width: 140px;
    border-top: 1px solid #999;
    margin-bottom: 4px;
}

.signature-label {
    font-size: 10.5px;
    color: #444;
    text-align: center;
}

/* ── FOOTER ── */
.inv-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 12px;
    background: white;
}

.powered-by {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 9px;
    color: #888;
}

.powered-text { text-transform: uppercase; letter-spacing: 0.05em; }

.powered-logo-img {
    width: 12px;
    height: 12px;
    object-fit: contain;
}

.powered-brand {
    font-size: 10px;
    font-weight: 700;
    color: #444;
}

.page-num {
    font-size: 10px;
    color: #888;
}
</style>
