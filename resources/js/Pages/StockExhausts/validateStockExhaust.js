/*
Author: Cipher
Created: 2026-11-05 12:39:47 Asia/Calcutta (UTC+05:30)
*/

// Database existence checks remain authoritative on the server.
export function validateStockExhaust(form) {
    const errors = {};
    const blank = value => value === null || value === undefined || (typeof value === 'string' && !value.trim());
    const text = (key, value, label, max) => {
        if (!blank(value) && (typeof value !== 'string' || [...value].length > max)) {
            errors[key] = `${label} must be text with at most ${max} characters.`;
        }
    };
    const numeric = (key, value, label, integer = false) => {
        if (blank(value)) return;
        if (!['string', 'number'].includes(typeof value) || !Number.isFinite(Number(value)) ||
            (integer && !Number.isInteger(Number(value)))) {
            errors[key] = `${label} must be ${integer ? 'an integer' : 'a valid number'}.`;
        }
    };
    const date = (key, value, label) => {
        if (blank(value)) return;
        if (!(value instanceof Date) && typeof value !== 'string') {
            errors[key] = `${label} must be a valid date.`;
            return;
        }
        const parsed = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(parsed.getTime())) errors[key] = `${label} must be a valid date.`;
        if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) &&
            !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) !== value) {
            errors[key] = `${label} must be a valid date.`;
        }
    };

    if (blank(form.partner_id)) errors.partner_id = 'Partner is required.';
    text('name', form.name, 'Voucher label', 250);
    text('bill_number', form.bill_number, 'Bill number', 150);
    text('prefix', form.prefix, 'Prefix', 20);
    text('reference_number', form.reference_number, 'Reference number', 100);
    date('billed_date', form.billed_date, 'Billed date');
    date('issued_date', form.issued_date, 'Issued date');
    if ([1, 2].includes(Number(form.invoice_status))) {
        if (blank(form.bill_number)) errors.bill_number = 'Bill number is required for billed stock out.';
        if (blank(form.billed_date)) errors.billed_date = 'Billed date is required for billed stock out.';
    }
    numeric('status', form.status, 'Status', true);
    numeric('invoice_status', form.invoice_status, 'Invoice status', true);

    if (!Array.isArray(form.lines) || !form.lines.some(line => !blank(line.product_id))) {
        errors.lines = 'Add at least one issued item with a product.';
        return errors;
    }
    form.lines.forEach((line, index) => {
        if (blank(line.product_id)) return;
        const key = field => `lines.${index}.${field}`;
        const label = field => `Row ${index + 1}: ${field}`;
        date(key('issue_date'), line.issue_date, label('Issue date'));
        for (const field of ['unit_price', 'discount']) {
            numeric(key(field), line[field], label(field));
            if (Number(line[field]) < 0) errors[key(field)] = label(field) + ' cannot be negative.';
        }
        if (line.discount_type === '%' && Number(line.discount) > 100) errors[key('discount')] = 'Percentage discount cannot exceed 100.';
        if (Number(line.quantity_issued) <= 0) errors[key('quantity_issued')] = 'Quantity must be greater than zero.';
        numeric(key('quantity_issued'), line.quantity_issued, label('Quantity issued'));
        numeric(key('no_items_issued'), line.no_items_issued, label('Items issued'));
        numeric(key('changed_km'), line.changed_km, label('Changed km'));
        text(key('issued_to'), line.issued_to, label('Issued to'), 255);
        text(key('notes'), line.notes, label('Notes'), 200);
    });
    return errors;
}
