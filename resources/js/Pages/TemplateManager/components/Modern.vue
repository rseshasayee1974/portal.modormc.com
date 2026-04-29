<script setup lang="ts">
defineProps<{
    dummyData: any;
}>();
</script>

<template>
    <div class="inv-root">

        <!-- ═══════════════════════════════════════
             HEADER — no border, lots of breathing room
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
             BILL TO / SHIP TO — borderless free-floating
        ════════════════════════════════════════ -->
        <div class="addr-section">
            <div class="addr-col">
                <div class="addr-label">Bill To</div>
                <div class="addr-name">{{ dummyData.bill_to.name }}</div>
                <div class="addr-line">{{ dummyData.bill_to.address }}</div>
                <div class="addr-line">{{ dummyData.bill_to.city }}</div>
                <div class="addr-line">{{ dummyData.bill_to.pin }} {{ dummyData.bill_to.state }}</div>
                <div class="addr-line">India</div>
            </div>
            <div class="addr-col">
                <div class="addr-label">Ship To</div>
                <div class="addr-line">{{ dummyData.ship_to.address }}</div>
                <div class="addr-line">{{ dummyData.ship_to.city }}</div>
                <div class="addr-line">{{ dummyData.ship_to.pin }} {{ dummyData.ship_to.state }}</div>
                <div class="addr-line">India</div>
            </div>
        </div>

        <!-- SUBJECT — label on one line, value on next -->
        <div class="subject-block">
            <div class="subject-label">Subject :</div>
            <div class="subject-value">Description</div>
        </div>

        <!-- ═══════════════════════════════════════
             INVOICE DETAILS BAR — dark header table
        ════════════════════════════════════════ -->
        <table class="details-bar">
            <thead>
                <tr class="dark-row">
                    <th class="db-th">Invoice Date</th>
                    <th class="db-th">Terms</th>
                    <th class="db-th">Due Date</th>
                    <th class="db-th">P.O.#</th>
                    <th class="db-th">Project Name</th>
                </tr>
            </thead>
            <tbody>
                <tr class="db-body-row">
                    <td class="db-td">{{ dummyData.date }}</td>
                    <td class="db-td">Due on Receipt</td>
                    <td class="db-td">{{ dummyData.due_date }}</td>
                    <td class="db-td">SO-17</td>
                    <td class="db-td">Design project</td>
                </tr>
            </tbody>
        </table>

        <!-- ═══════════════════════════════════════
             ITEMS TABLE — dark header, items below
        ════════════════════════════════════════ -->
        <table class="items-table">
            <thead>
                <tr class="dark-row">
                    <th class="it-th it-num">#</th>
                    <th class="it-th it-desc text-left">Item &amp; Description</th>
                    <th class="it-th it-qty text-right">Qty</th>
                    <th class="it-th it-rate text-right">Rate</th>
                    <th class="it-th it-amt text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, idx) in dummyData.items" :key="idx" class="item-row">
                    <td class="it-td it-num text-center">{{ idx + 1 }}</td>
                    <td class="it-td it-desc">
                        <div class="item-name">{{ item.description }}</div>
                        <div class="item-sub">{{ item.description }} — Single Sided Color</div>
                    </td>
                    <td class="it-td it-qty text-right">
                        <div>{{ item.qty }}.00</div>
                        <div class="unit-text">Nos</div>
                    </td>
                    <td class="it-td it-rate text-right">{{ item.rate }}.00</td>
                    <td class="it-td it-amt text-right">{{ item.amount }}.00</td>
                </tr>
            </tbody>
        </table>

        <!-- ═══════════════════════════════════════
             TOTALS — RIGHT SIDE ONLY
        ════════════════════════════════════════ -->
        <div class="totals-block">
            <table class="totals-table">
                <tbody>
                    <tr>
                        <td class="tt-label">Sub Total</td>
                        <td class="tt-val">{{ dummyData.sub_total }}.00</td>
                    </tr>
                    <tr>
                        <td class="tt-label">Discount</td>
                        <td class="tt-val">0.00</td>
                    </tr>
                    <tr class="tt-total-row">
                        <td class="tt-label bold">Total</td>
                        <td class="tt-val bold">₹{{ dummyData.total }}.75</td>
                    </tr>
                    <tr>
                        <td class="tt-label underline">Payment Retention</td>
                        <td class="tt-val red">(-) 10.00</td>
                    </tr>
                    <tr>
                        <td class="tt-label">Payment Made</td>
                        <td class="tt-val red">(-) 100.00</td>
                    </tr>
                    <tr class="tt-balance-row">
                        <td class="tt-label bold">Balance Due</td>
                        <td class="tt-val bold">₹562.75</td>
                    </tr>
                </tbody>
            </table>

            <!-- Total in Words — right aligned, below balance -->
            <div class="tow-row">
                <div class="tow-label">Total In Words:</div>
                <div class="tow-value">{{ dummyData.total_words }}</div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════
             NOTES — borderless, grey label
        ════════════════════════════════════════ -->
        <div class="bottom-section">

            <div class="info-block">
                <div class="info-label">Notes</div>
                <div class="info-text">Thanks for your business.</div>
            </div>

            <!-- Payment Options -->
            <div class="payment-block">
                <span class="pay-label">Payment Options</span>
                <span class="paypal-badge">PayPal</span>
                <span class="card-badge">💳</span>
            </div>

            <!-- Terms & Conditions -->
            <div class="info-block">
                <div class="info-label">Terms &amp; Conditions</div>
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
/* ───────────────────────────────────────────
   BASE — no border, clean white
─────────────────────────────────────────── */
.inv-root {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #111;
    background: #fff;
    /* NO border — this template is borderless */
    width: 100%;
    min-height: 100%;
    display: flex;
    flex-direction: column;
    line-height: 1.5;
    padding: 0;
}

/* ───────────────────────────────────────────
   HEADER
─────────────────────────────────────────── */
.inv-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 18px 18px 14px 18px;
}

.co-name {
    font-size: 13px;
    font-weight: 700;
    color: #111;
    margin-bottom: 0px;
}

.co-detail {
    font-size: 10.5px;
    color: #666;
    line-height: 1.5;
}

.inv-title {
    font-size: 30px;
    font-weight: 900;
    color: #111;
    text-align: right;
    letter-spacing: 0.01em;
    line-height: 1.0;
}

.inv-ref {
    font-size: 10.5px;
    color: #666;
    text-align: right;
    margin-top: 3px;
}

/* ───────────────────────────────────────────
   BILL TO / SHIP TO — free-floating, no borders
─────────────────────────────────────────── */
.addr-section {
    display: flex;
    padding: 12px 18px 8px 18px;
    gap: 0;
}

.addr-col {
    flex: 1;
    padding-right: 20px;
}

.addr-label {
    color: #888;
    font-size: 10.5px;
    margin-bottom: 3px;
}

.addr-name {
    font-weight: 700;
    font-size: 11px;
    color: #111;
    margin-bottom: 1px;
}

.addr-line {
    font-size: 11px;
    color: #444;
    line-height: 1.5;
}

/* ───────────────────────────────────────────
   SUBJECT
─────────────────────────────────────────── */
.subject-block {
    padding: 8px 18px 14px;
    font-size: 11px;
}

.subject-label {
    color: #888;
    margin-bottom: 2px;
}

.subject-value {
    color: #111;
    font-size: 11px;
}

/* ───────────────────────────────────────────
   INVOICE DETAILS BAR (dark header table)
─────────────────────────────────────────── */
.details-bar {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 6px;
}

.dark-row {
    background: #3a3a3a;
}

.db-th {
    padding: 7px 12px;
    color: #fff;
    font-size: 10.5px;
    font-weight: 700;
    text-align: left;
    border: none;
}

.db-body-row { background: white; }

.db-td {
    padding: 6px 12px;
    font-size: 11px;
    color: #333;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

/* ───────────────────────────────────────────
   ITEMS TABLE (dark header)
─────────────────────────────────────────── */
.items-table {
    width: 100%;
    border-collapse: collapse;
}

.it-th {
    padding: 7px 10px;
    color: #fff;
    font-size: 10.5px;
    font-weight: 700;
    border: none;
}

.it-num  { width: 30px; text-align: center !important; }
.it-desc { width: auto; }
.it-qty  { width: 70px; }
.it-rate { width: 80px; }
.it-amt  { width: 80px; }

.it-td {
    padding: 6px 10px;
    vertical-align: top;
    border-bottom: 1px solid #e0e0e0;
    font-size: 11px;
}

.item-row:last-child td { border-bottom: 1px solid #ccc; }

.item-name { font-weight: 700; color: #111; }
.item-sub  { font-size: 10px; color: #888; margin-top: 1px; }
.unit-text { font-size: 10px; color: #888; }

.text-left   { text-align: left !important; }
.text-right  { text-align: right !important; }
.text-center { text-align: center !important; }

/* ───────────────────────────────────────────
   TOTALS BLOCK — right aligned only
─────────────────────────────────────────── */
.totals-block {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    padding: 2px 0 0;
}

.totals-table {
    width: 300px;
    border-collapse: collapse;
    font-size: 11px;
    margin-right: 0;
}

.totals-table tr > td {
    padding: 4px 10px;
    vertical-align: middle;
}

.tt-label {
    text-align: right;
    color: #555;
    padding-right: 18px !important;
    width: 55%;
}

.tt-val {
    text-align: right;
    color: #333;
    white-space: nowrap;
    width: 45%;
}

.tt-total-row { border-top: 1px solid #ccc; border-bottom: 1px solid #ccc; }
.tt-total-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

.tt-balance-row {
    background: #f2f2f2;
}
.tt-balance-row td { padding-top: 5px !important; padding-bottom: 5px !important; }

.bold      { font-weight: 700; color: #111; }
.red       { color: #cc0000; }
.underline { text-decoration: underline; }

/* Total in Words — right side */
.tow-row {
    width: 300px;
    font-size: 11px;
    padding: 6px 10px 4px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.tow-label {
    font-size: 10.5px;
    color: #555;
    text-align: right;
    margin-bottom: 2px;
}

.tow-value {
    font-style: italic;
    font-weight: 700;
    color: #111;
    text-align: right;
    line-height: 1.5;
    max-width: 180px;
}

/* ───────────────────────────────────────────
   BOTTOM SECTION — notes / payment / terms
─────────────────────────────────────────── */
.bottom-section {
    flex: 1;
    padding: 12px 18px 10px;
    border-top: 1px solid #ccc;
    margin-top: 6px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-block { font-size: 11px; }

.info-label {
    color: #888;
    font-size: 10.5px;
    margin-bottom: 2px;
}

.info-text { color: #444; }

.payment-block {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
}

.pay-label { color: #555; margin-right: 4px; }

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

/* ───────────────────────────────────────────
   FOOTER
─────────────────────────────────────────── */
.inv-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 18px;
    border-top: 1px solid #ccc;
    margin-top: auto;
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
