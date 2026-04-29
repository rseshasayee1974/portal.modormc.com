<script setup lang="ts">
defineProps<{
    dummyData: any;
}>();
</script>

<template>
    <div class="invoice-root">

        <!-- ═══ HEADER ═══ -->
        <div class="inv-header">
            <div class="inv-header-left">
                <div class="company-name">msrk</div>
                <div class="company-address"> , , Tamil Nadu - , India</div>
            </div>
            <div class="inv-header-right">
                <div class="inv-title">TAX INVOICE</div>
                <div class="inv-number">Invoice# {{ dummyData.doc_no }}</div>
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
                                <tr>
                                    <td class="meta-label">Invoice Date</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value">{{ dummyData.date }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Terms</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value bold">Due on Receipt</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Due Date</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value">{{ dummyData.due_date }}</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">P.O.#</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value bold">SO-17</td>
                                </tr>
                                <tr>
                                    <td class="meta-label">Project Name</td>
                                    <td class="meta-colon">:</td>
                                    <td class="meta-value bold">Design project</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>

                    <!-- Bill To column -->
                    <td class="info-cell info-bill">
                        <div class="address-header">Bill To :</div>
                        <div class="address-name">{{ dummyData.bill_to.name }}</div>
                        <div class="address-line">{{ dummyData.bill_to.address }}</div>
                        <div class="address-line">{{ dummyData.bill_to.city }}</div>
                        <div class="address-line">{{ dummyData.bill_to.pin }} {{ dummyData.bill_to.state }}</div>
                        <div class="address-line">India</div>
                    </td>

                    <!-- Ship To column -->
                    <td class="info-cell info-ship no-right-border">
                        <div class="address-header">Ship To :</div>
                        <div class="address-line">{{ dummyData.ship_to.address }}</div>
                        <div class="address-line">{{ dummyData.ship_to.city }}</div>
                        <div class="address-line">{{ dummyData.ship_to.pin }} {{ dummyData.ship_to.state }}</div>
                        <div class="address-line">India</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ═══ SUBJECT ROW ═══ -->
        <div class="subject-row">
            <span class="subject-label">Subject :</span> Description
        </div>

        <!-- ═══ ITEMS TABLE ═══ -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-num">#</th>
                    <th class="col-item text-left">Item</th>
                    <th class="col-desc text-left">Description</th>
                    <th class="col-qty text-right">Qty</th>
                    <th class="col-unit text-right">Units</th>
                    <th class="col-rate text-right">Rate</th>
                    <th class="col-amt text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, idx) in dummyData.items" :key="idx">
                    <td class="col-num text-center">{{ idx + 1 }}</td>
                    <td class="col-item">
                        <div class="item-name">{{ item.description }}</div>
                    </td>
                    <td class="col-desc item-desc">{{ item.description }} Single Sided Color</td>
                    <td class="col-qty text-right">{{ item.qty }}.00</td>
                    <td class="col-unit text-right">Nos</td>
                    <td class="col-rate text-right">{{ item.rate }}.00</td>
                    <td class="col-amt text-right">{{ item.amount }}.00</td>
                </tr>
            </tbody>
        </table>

        <!-- ═══ NOTES + TOTALS ═══ -->
        <div class="notes-totals-row">
            <div class="notes-section">
                <span class="notes-label">Notes :</span>
                <span class="notes-text">Thanks for your business.</span>
            </div>
            <div class="totals-section">
                <table class="totals-table">
                    <tbody>
                        <tr>
                            <td class="total-label">Sub Total</td>
                            <td class="total-value">{{ dummyData.sub_total }}.00</td>
                        </tr>
                        <tr>
                            <td class="total-label">Discount</td>
                            <td class="total-value">0.00</td>
                        </tr>
                        <tr class="total-row-bold">
                            <td class="total-label">Total</td>
                            <td class="total-value bold">₹{{ dummyData.total }}.75</td>
                        </tr>
                        <tr>
                            <td class="total-label underline-label">Payment Retention</td>
                            <td class="total-value red">(-) 10.00</td>
                        </tr>
                        <tr>
                            <td class="total-label">Payment Made</td>
                            <td class="total-value red">(-) 100.00</td>
                        </tr>
                        <tr class="balance-row">
                            <td class="total-label bold">Balance Due</td>
                            <td class="total-value bold">₹562.75</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ TOTAL IN WORDS ═══ -->
        <div class="words-row">
            <span class="words-label">Total In Words :</span>
            <span class="words-value">Indian Rupee Six Hundred Sixty-Two and Seventy-Five Paise Only</span>
        </div>

        <!-- ═══ PAYMENT OPTIONS ═══ -->
        <div class="payment-row">
            <span class="payment-label">Payment Options</span>
            <div class="payment-icons">
                <span class="paypal-badge">PayPal</span>
                <span class="card-badge">💳</span>
            </div>
        </div>

        <!-- ═══ TERMS + SIGNATURE ═══ -->
        <div class="terms-signature-row">
            <div class="terms-section">
                <div class="terms-label">Terms &amp; Conditions :</div>
                <div class="terms-text">Your company's Terms and Conditions will be displayed here.<br>You can add it in the Invoice Preferences page under Settings.</div>
            </div>
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-label">Authorized Signature</div>
            </div>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div class="inv-footer">
            <div class="powered-by">
                <span class="powered-text">POWERED BY</span>
                <img src="https://onemodo.com/favicon.ico" alt="onemodo" class="powered-logo-img" onerror="this.style.display='none'" />
                <span class="powered-brand">onemodo.com</span>
            </div>
            <div class="page-num">1</div>
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
