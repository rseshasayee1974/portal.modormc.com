// Author: ragul-onemodo
// Created: 2026-10-08 12:15:25 Asia/Calcutta (UTC+05:30)
import { computed, watch } from 'vue';

export function useNoteItems(form, getDocument, getTaxes, getUnits, getProducts = () => [], getMixdesign = () => []) {
    const round = value => Math.round((Number(value) + Number.EPSILON) * 100) / 100;
    watch(getDocument, document => {
        form.is_tax_inclusive = Boolean(document?.is_tax_inclusive ?? document?.tax_inclusive);
        form.items = (document?.items || []).map(item => ({
            id: item.id, item_id: item.item_id ?? null, item_name: item.item_name, hsn_code: item.hsn_code,
            uom_id: item.uom_id, tax_id: item.tax_id, quantity: Number(item.quantity),
            price_unit: Number(item.price_unit), discount_type: item.discount_type === '₹' ? '₹' : '%',
            discount: Number(item.discount || 0),
        }));
    }, { immediate: true });
    const isPurchaseDocument = () => {
        const document = getDocument();
        const type = String(document?.source_document?.invoice_type || document?.invoice_type || form.note_type || '').toLowerCase();
        return ['bill', 'purchase', 'debit_note'].includes(type);
    };
    const taxOptions = computed(() => getTaxes()
        .filter(tax => String(tax.tax_type).toLowerCase() === (isPurchaseDocument() ? 'purchase' : 'sales'))
        .map(tax => ({ ...tax, label: tax.tax_name, value: tax.id })));
    const unitOptions = computed(() => getUnits().map(unit => ({ label: unit.unit_code, value: unit.id })));
    const catalogOptions = computed(() => {
        return isPurchaseDocument()
            ? getProducts().map(product => ({ label: product.title, value: product.id, uom_id: product.unit_id }))
            : getMixdesign().map(design => ({ label: design.label, value: design.value, uom_id: design.uom_id }));
    });
    const itemOptions = line => {
        const options = [...catalogOptions.value];
        // Keep the saved description visible even when its catalog entry was renamed or removed.
        const index = options.findIndex(option => option.value === line.item_id);
        const saved = { label: line.item_name, value: line.item_id, uom_id: line.uom_id };
        if (line.item_name) {
            if (index < 0) options.unshift(saved);
            else if (options[index].label !== line.item_name) options[index] = saved;
        }
        return options;
    };
    const selectItem = (line, value) => {
        const option = catalogOptions.value.find(option => option.value === value);
        if (!option) return;
        line.item_id = option.value;
        line.item_name = option.label;
        if (option.uom_id != null) line.uom_id = option.uom_id;
    };
    const lineTotals = item => {
        const tax = getTaxes().find(tax => tax.id === item.tax_id)
            || getDocument()?.items?.find(original => original.id === item.id && original.tax_id === item.tax_id)?.tax;
        const rate = Number(tax?.tax_rate || 0);
        const gross = Number(item.quantity || 0) * Number(item.price_unit || 0);
        const discount = item.discount_type === '%' ? gross * Number(item.discount || 0) / 100 : Number(item.discount || 0);
        const net = gross - discount;
        const subtotal = round(form.is_tax_inclusive && rate > 0 ? net / (1 + rate / 100) : net);
        const initialTax = round(form.is_tax_inclusive && rate > 0 ? net - subtotal : subtotal * rate / 100);
        const taxAmount = tax?.tax_group === 'GST' && rate > 0 ? round(2 * round(subtotal * rate / 200)) : initialTax;
        return { discount_amount: round(discount), subtotal, line_tax_amount: taxAmount,
            line_total: round(form.is_tax_inclusive ? net : subtotal + initialTax), initialTax };
    };
    const amounts = computed(() => {
        const document = getDocument() || {};
        const totals = form.items.map(lineTotals);
        const subtotal = round(totals.reduce((sum, item) => sum + item.subtotal, 0));
        const taxAmount = round(totals.reduce((sum, item) => sum + item.line_tax_amount, 0));
        const globalDiscount = document.global_discount_type === '%' ? subtotal * Number(document.global_discount || 0) / 100 : Number(document.global_discount || 0);
        const extras = Number(document.adjustment || 0) + Number(document.shipping_charges || 0) - globalDiscount;
        const total = Math.round(round(subtotal + totals.reduce((sum, item) => sum + item.initialTax, 0) + extras));
        return { ...document, subtotal, tax_amount: taxAmount, total_amount: total,
            discount_total: round(totals.reduce((sum, item) => sum + item.discount_amount, 0) + globalDiscount),
            round_off: round(total - (subtotal + taxAmount + extras)) };
    });
    const removeItem = index => { if (form.items.length > 1) form.items.splice(index, 1); };
    return { amounts, lineTotals, taxOptions, unitOptions, itemOptions, selectItem, removeItem };
}
