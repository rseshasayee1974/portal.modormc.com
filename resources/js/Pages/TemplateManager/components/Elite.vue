<script setup lang="ts">
defineProps<{
    dummyData: any;
}>();
</script>

<template>
    <div class="inv-root">

        <!-- ═══════════════════════════════════════
             SECTION 1: HEADER
        ════════════════════════════════════════ -->
        <div class="inv-header">
            <div class="header-left">
                <div class="co-name">msrk</div>
                <div class="co-detail">Tamil Nadu</div>
                <div class="co-detail">India</div>
                <div class="co-detail">ragul@onemodo.com</div>
            </div>
            <div class="header-right">
                <div class="inv-title">TAX INVOICE</div>
                <div class="inv-ref">Invoice# <strong>{{ dummyData.doc_no }}</strong></div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             SECTION 2: INVOICE DETAILS (2-col)
        ════════════════════════════════════════ -->
        <table class="details-table">
            <tbody>
                <tr>
                    <td class="details-cell details-left">
                        <table class="kv-table">
                            <tr>
                                <td class="kv-key">Invoice Date</td>
                                <td class="kv-sep">:</td>
                                <td class="kv-val bold">{{ dummyData.date }}</td>
                            </tr>
                            <tr>
                                <td class="kv-key">Terms</td>
                                <td class="kv-sep">:</td>
                                <td class="kv-val bold">Due on Receipt</td>
                            </tr>
                            <tr>
                                <td class="kv-key">Due Date</td>
                                <td class="kv-sep">:</td>
                                <td class="kv-val bold">{{ dummyData.due_date }}</td>
                            </tr>
                            <tr>
                                <td class="kv-key">P.O.#</td>
                                <td class="kv-sep">:</td>
                                <td class="kv-val bold">SO-17</td>
                            </tr>
                        </table>
                    </td>
                    <td class="details-cell details-right no-right-border">
                        <table class="kv-table">
                            <tr>
                                <td class="kv-key">Project Name</td>
                                <td class="kv-sep">:</td>
                                <td class="kv-val bold">Design project</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ═══════════════════════════════════════
             SECTION 3: BILL TO / SHIP TO
        ════════════════════════════════════════ -->
        <table class="addr-table">
            <!-- Header row -->
            <thead>
                <tr>
                    <th class="addr-th addr-th-left">Bill To</th>
                    <th class="addr-th addr-th-right">Ship To</th>
                </tr>
            </thead>
            <!-- Address row -->
            <tbody>
                <tr>
                    <td class="addr-cell addr-left">
                        <div class="addr-name">{{ dummyData.bill_to.name }}</div>
                        <div class="addr-line">{{ dummyData.bill_to.address }}</div>
                        <div class="addr-line">{{ dummyData.bill_to.city }}</div>
                        <div class="addr-line">{{ dummyData.bill_to.pin }} {{ dummyData.bill_to.state }}</div>
                        <div class="addr-line">India</div>
                    </td>
                    <td class="addr-cell addr-right">
                        <div class="addr-line">{{ dummyData.ship_to.address }}</div>
                        <div class="addr-line">{{ dummyData.ship_to.city }}</div>
                        <div class="addr-line">{{ dummyData.ship_to.pin }} {{ dummyData.ship_to.state }}</div>
                        <div class="addr-line">India</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- ═══════════════════════════════════════
             SECTION 4: SUBJECT ROW
        ════════════════════════════════════════ -->
        <div class="subject-row">
            &nbsp;&nbsp;Subject : Description
        </div>

        <!-- ═══════════════════════════════════════
             SECTION 5: ITEMS TABLE
        ════════════════════════════════════════ -->
        <table class="items-table">
            <thead>
                <tr class="items-header">
                    <th class="th-num">#</th>
                    <th class="th-desc text-left">Item &amp; Description</th>
                    <th class="th-qty text-right">Qty</th>
                    <th class="th-rate text-right">Rate</th>
                    <th class="th-amt text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, idx) in dummyData.items" :key="idx" class="item-row">
                    <td class="td-num">{{ idx + 1 }}</td>
                    <td class="td-desc">
                        <div class="item-name">{{ item.description }}</div>
                        <div class="item-sub">{{ item.description }} — Custom specification for client</div>
                    </td>
                    <td class="td-qty">
                        <div class="qty-val">{{ item.qty }}.00</div>
                        <div class="qty-unit">Nos</div>
                    </td>
                    <td class="td-rate">{{ item.rate }}.00</td>
                    <td class="td-amt">{{ item.amount }}.00</td>
                </tr>
                <!-- Blank spacer rows -->
                <tr v-for="n in 4" :key="'blank-' + n" class="blank-row">
                    <td class="td-num">&nbsp;</td>
                    <td class="td-desc"></td>
                    <td class="td-qty"></td>
                    <td class="td-rate"></td>
                    <td class="td-amt"></td>
                </tr>
            </tbody>
        </table>

        <!-- ═══════════════════════════════════════
             SECTION 6: TOTALS SPLIT ROW
        ════════════════════════════════════════ -->
        <div class="totals-split">
            <!-- LEFT: Total in Words + Payment -->
            <div class="totals-left">
                <div class="tow-label">Total In Words</div>
                <div class="tow-value">{{ dummyData.total_words }}</div>
                <div class="payment-row">
                    <span class="pay-label">Payment Options</span>
                    <span class="paypal-badge">PayPal</span>
                    <span class="card-badge">💳</span>
                </div>
            </div>

            <!-- RIGHT: Breakdown -->
            <div class="totals-right">
                <table class="breakdown-table">
                    <tbody>
                        <tr>
                            <td class="bt-label">Sub Total</td>
                            <td class="bt-val">{{ dummyData.sub_total }}.00</td>
                        </tr>
                        <tr>
                            <td class="bt-label">Discount</td>
                            <td class="bt-val">0.00</td>
                        </tr>
                        <tr class="bt-total-row">
                            <td class="bt-label bold">Total</td>
                            <td class="bt-val bold">₹{{ dummyData.total }}.75</td>
                        </tr>
                        <tr>
                            <td class="bt-label underline">Payment Retention</td>
                            <td class="bt-val red">(-) 10.00</td>
                        </tr>
                        <tr>
                            <td class="bt-label">Payment Made</td>
                            <td class="bt-val red">(-) 100.00</td>
                        </tr>
                        <tr class="bt-balance-row">
                            <td class="bt-label bold">Balance Due</td>
                            <td class="bt-val bold">₹562.75</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             SECTION 7: NOTES
        ════════════════════════════════════════ -->
        <div class="notes-section">
            <div class="section-label">Notes</div>
            <div class="section-text">Thanks for your business.</div>
        </div>

        <!-- ═══════════════════════════════════════
             SECTION 8: TERMS & CONDITIONS
        ════════════════════════════════════════ -->
        <div class="terms-section">
            <div class="section-label">Terms &amp; Conditions</div>
            <div class="section-text">Your company's Terms and Conditions will be displayed here. You can add it in the Invoice Preferences page under Settings.</div>
        </div>

        <!-- ═══════════════════════════════════════
             SECTION 9: SIGNATURE AREA
        ════════════════════════════════════════ -->
        <div class="signature-area">
            <div class="sig-box">
                <div class="sig-label">Authorized Signature</div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             FOOTER
        ════════════════════════════════════════ -->
        <div class="inv-footer">
            <div class="powered-by">
                <span class="powered-text">POWERED BY</span>
                <img src="https://onemodo.com/favicon.ico" alt="" class="powered-ico" onerror="this.style.display='none'" />
                <span class="powered-brand">onemodo.com</span>
            </div>
            <div class="page-num">1</div>
        </div>

    </div>
</template>

<style scoped>
/* ─────────────────────────────────────────────
   BASE
───────────────────────────────────────────── */
.inv-root {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #111;
    background: #fff;
    border: 1px solid #aaa;
    width: 100%;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    line-height: 1.5;
}

/* ─────────────────────────────────────────────
   HEADER
───────────────────────────────────────────── */
.inv-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 14px;
    border-bottom: 1px solid #aaa;
}

.co-name {
    font-size: 15px;
    font-weight: 700;
    color: #111;
    margin-bottom: 1px;
}

.co-detail {
    font-size: 10.5px;
    color: #555;
    line-height: 1.45;
}

.inv-title {
    font-size: 26px;
    font-weight: 900;
    color: #111;
    text-align: right;
    letter-spacing: 0.01em;
    line-height: 1.1;
}

.inv-ref {
    font-size: 10.5px;
    color: #555;
    text-align: right;
    margin-top: 2px;
}

/* ─────────────────────────────────────────────
   DETAILS TABLE (Invoice Date / Project Name)
───────────────────────────────────────────── */
.details-table {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1px solid #aaa;
}

.details-cell {
    padding: 7px 12px;
    vertical-align: top;
    border-right: 1px solid #aaa;
}

.no-right-border { border-right: none; }
.details-left  { width: 50%; }
.details-right { width: 50%; }

.kv-table { border-collapse: collapse; }
.kv-key   { color: #555; white-space: nowrap; padding: 1px 0; font-size: 11px; }
.kv-sep   { padding: 1px 6px; color: #555; }
.kv-val   { color: #111; font-size: 11px; }
.kv-val.bold { font-weight: 700; }

/* ─────────────────────────────────────────────
   BILL TO / SHIP TO
───────────────────────────────────────────── */
.addr-table {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1px solid #aaa;
}

.addr-th {
    background: #f0f0f0;
    border-bottom: 1px solid #aaa;
    padding: 5px 12px;
    font-weight: 700;
    font-size: 11px;
    color: #111;
    text-align: left;
}

.addr-th-left  { width: 50%; border-right: 1px solid #aaa; }
.addr-th-right { width: 50%; }

.addr-cell {
    padding: 7px 12px;
    vertical-align: top;
    font-size: 11px;
}

.addr-left  { border-right: 1px solid #aaa; }

.addr-name { font-weight: 700; color: #111; margin-bottom: 1px; }
.addr-line { color: #444; line-height: 1.5; }

/* ─────────────────────────────────────────────
   SUBJECT ROW
───────────────────────────────────────────── */
.subject-row {
    padding: 4px 12px;
    border-bottom: 1px solid #aaa;
    font-size: 11px;
    background: #fafafa;
    color: #444;
}

/* ─────────────────────────────────────────────
   ITEMS TABLE
───────────────────────────────────────────── */
.items-table {
    width: 100%;
    border-collapse: collapse;
    border-bottom: 1px solid #aaa;
    font-size: 11px;
}

/* Header */
.items-header th {
    padding: 6px 10px;
    font-weight: 700;
    color: #111;
    border-bottom: 1px solid #aaa;
    background: white;
}

/* Column widths */
.th-num  { width: 28px; text-align: center; }
.th-desc { width: auto; }
.th-qty  { width: 70px; }
.th-rate { width: 80px; }
.th-amt  { width: 80px; }

/* Body cells */
.item-row td { padding: 5px 10px; vertical-align: top; border-bottom: 1px solid #e8e8e8; }
.blank-row td { padding: 10px 10px; border-bottom: 1px solid #e8e8e8; }

.td-num  { text-align: center; color: #555; }
.td-qty  { text-align: right; }
.td-rate { text-align: right; color: #333; }
.td-amt  { text-align: right; font-weight: 600; color: #111; }

.item-name { font-weight: 700; color: #111; }
.item-sub  { font-size: 10px; color: #777; margin-top: 1px; }

.qty-val  { text-align: right; color: #111; }
.qty-unit { text-align: right; color: #777; font-size: 10px; }

.text-left  { text-align: left !important; }
.text-right { text-align: right !important; }

/* ─────────────────────────────────────────────
   TOTALS SPLIT ROW
───────────────────────────────────────────── */
.totals-split {
    display: flex;
    border-bottom: 1px solid #aaa;
    min-height: 90px;
}

.totals-left {
    flex: 1;
    padding: 8px 12px;
    border-right: 1px solid #aaa;
    font-size: 11px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.tow-label {
    color: #555;
    margin-bottom: 2px;
    font-size: 10.5px;
}

.tow-value {
    font-style: italic;
    font-weight: 700;
    color: #111;
    font-size: 11px;
    line-height: 1.5;
    margin-bottom: 10px;
}

.payment-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
}

.pay-label {
    color: #555;
    font-size: 11px;
    margin-right: 4px;
}

.paypal-badge {
    background: #003087;
    color: #fff;
    font-size: 9px;
    font-weight: 900;
    font-style: italic;
    padding: 2px 7px;
    border-radius: 3px;
    letter-spacing: 0.02em;
}

.card-badge {
    font-size: 13px;
    border: 1px solid #ccc;
    padding: 1px 4px;
    border-radius: 3px;
    background: #f5f5f5;
}

/* RIGHT: breakdown table */
.totals-right {
    width: 260px;
    padding: 0;
}

.breakdown-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

.breakdown-table tr > td {
    padding: 3px 12px;
    vertical-align: middle;
}

.bt-label {
    text-align: right;
    color: #444;
    padding-right: 16px !important;
    width: 55%;
}

.bt-val {
    text-align: right;
    color: #333;
    white-space: nowrap;
}

.bt-total-row {
    border-top: 1px solid #aaa;
    border-bottom: 1px solid #aaa;
}

.bt-total-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

.bt-balance-row { border-top: 1px solid #aaa; }
.bt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

.bold      { font-weight: 700; color: #111; }
.red       { color: #cc0000; }
.underline { text-decoration: underline; }

/* ─────────────────────────────────────────────
   NOTES
───────────────────────────────────────────── */
.notes-section {
    padding: 7px 12px;
    border-bottom: 1px solid #aaa;
    font-size: 11px;
}

/* ─────────────────────────────────────────────
   TERMS
───────────────────────────────────────────── */
.terms-section {
    padding: 7px 12px;
    border-bottom: 1px solid #aaa;
    font-size: 11px;
}

.section-label {
    color: #777;
    margin-bottom: 2px;
    font-size: 10.5px;
}

.section-text {
    color: #444;
    line-height: 1.5;
}

/* ─────────────────────────────────────────────
   SIGNATURE AREA
───────────────────────────────────────────── */
.signature-area {
    flex: 1;
    min-height: 120px;
    border-bottom: 1px solid #aaa;
    position: relative;
}

.sig-box {
    position: absolute;
    bottom: 10px;
    right: 14px;
}

.sig-label {
    font-size: 10.5px;
    color: #555;
    text-align: right;
}

/* ─────────────────────────────────────────────
   FOOTER
───────────────────────────────────────────── */
.inv-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 12px;
}

.powered-by {
    display: flex;
    align-items: center;
    gap: 4px;
}

.powered-text {
    font-size: 9px;
    color: #999;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.powered-ico {
    width: 12px;
    height: 12px;
    object-fit: contain;
}

.powered-brand {
    font-size: 10px;
    font-weight: 700;
    color: #555;
}

.page-num {
    font-size: 10px;
    color: #888;
}
</style>
