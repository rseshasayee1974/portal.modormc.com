export interface LineItemCalculationParams {
    quantity: number;
    rate: number;
    pump_rate: number;
    taxRate: number;
    isTaxInclusive: boolean;
}

export interface LineItemCalculationResult {
    materialUntaxed: number;
    materialTax: number;
    materialTotal: number;
    pumpCharge: number;
    grossAmount: number;
    untaxedAmount: number;
    taxAmount: number;
    amountTotal: number;
}

export function calculateLineItemTotals(
    params: LineItemCalculationParams
): LineItemCalculationResult {
    const qty = Number(params.quantity || 0);
    const rate = Number(params.rate || 0);
    const pumpRate = Number(params.pump_rate || 0);
    const taxRate = Number(params.taxRate || 0);
    const isTaxInclusive = Boolean(params.isTaxInclusive);

    const pumpCharge = pumpRate;

    let materialUntaxed = 0;
    let materialTax = 0;
    let materialTotal = 0;
    let untaxedAmount = 0;
    let taxAmount = 0;
    let amountTotal = 0;
    let grossAmount = 0;

    if (isTaxInclusive) {
        // TAX INCLUSIVE:
        // gross_amount = (qty * rate) + pump_charge
        // taxable_amount (untaxed) = gross_amount * 100 / (100 + tax_rate)
        // tax_amount = gross_amount - taxable_amount
        // amount_total = gross_amount
        grossAmount = qty * rate + pumpCharge;
        untaxedAmount = taxRate > 0 ? (grossAmount * 100) / (100 + taxRate) : grossAmount;
        taxAmount = grossAmount - untaxedAmount;
        amountTotal = grossAmount;

        materialTotal = qty * rate;
        materialUntaxed = taxRate > 0 ? (materialTotal * 100) / (100 + taxRate) : materialTotal;
        materialTax = materialTotal - materialUntaxed;
    } else {
        // TAX EXCLUSIVE:
        // material_amount = qty * rate
        // untaxed_amount = material_amount + pump_charge
        // tax_amount = untaxed_amount * (tax_rate / 100)
        // amount_total = untaxed_amount + tax_amount
        materialUntaxed = qty * rate;
        materialTax = (materialUntaxed * taxRate) / 100;
        materialTotal = materialUntaxed + materialTax;

        untaxedAmount = materialUntaxed + pumpCharge;
        taxAmount = (untaxedAmount * taxRate) / 100;
        grossAmount = untaxedAmount;
        amountTotal = untaxedAmount + taxAmount;
    }

    return {
        materialUntaxed: Number(materialUntaxed.toFixed(2)),
        materialTax: Number(materialTax.toFixed(2)),
        materialTotal: Number(materialTotal.toFixed(2)),
        pumpCharge: Number(pumpCharge.toFixed(2)),
        untaxedAmount: Number(untaxedAmount.toFixed(2)),
        taxAmount: Number(taxAmount.toFixed(2)),
        amountTotal: Number(amountTotal.toFixed(2)),
        grossAmount: Number(grossAmount.toFixed(2)),
    };
}

export function useLineItemCalculation() {
    return {
        calculateLineItemTotals,
    };
}
