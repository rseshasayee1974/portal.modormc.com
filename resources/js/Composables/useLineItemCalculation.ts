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

    // 1. Pump charge (fixed lump sum / unit charge) and material amount (rate * qty)
    const pumpCharge = pumpRate;
    const materialAmount = qty * rate;

    // 2. Untaxed Amount = (rate * qty) + pump_rate
    const untaxedAmount = materialAmount + pumpCharge;

    let materialUntaxed = 0;
    let materialTax = 0;
    let materialTotal = 0;
    let taxAmount = 0;
    let amountTotal = 0;

    if (isTaxInclusive) {
        // TAX INCLUSIVE
        // Total = (rate * qty) + pump_rate
        amountTotal = untaxedAmount;

        taxAmount = amountTotal - amountTotal / (1 + taxRate / 100);

        materialUntaxed = materialAmount / (1 + taxRate / 100);
        materialTax = materialAmount - materialUntaxed;
        materialTotal = materialAmount;
    } else {
        // TAX EXCLUSIVE
        // Untaxed Amount = (rate * qty) + pump_rate
        // Tax Amount = Untaxed Amount * (taxRate / 100)
        // Total = Untaxed Amount + Tax Amount

        materialUntaxed = materialAmount;
        materialTax = (materialUntaxed * taxRate) / 100;
        materialTotal = materialUntaxed + materialTax;

        taxAmount = (untaxedAmount * taxRate) / 100;
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
    };
}

export function useLineItemCalculation() {
    return {
        calculateLineItemTotals,
    };
}
