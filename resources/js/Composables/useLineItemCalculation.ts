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

export function calculateLineItemTotals(params: LineItemCalculationParams): LineItemCalculationResult {
    const qty = Number(params.quantity || 0);
    const rate = Number(params.rate || 0);
    const pumpRate = Number(params.pump_rate || 0);
    const taxRate = Number(params.taxRate || 0);
    const isTaxInclusive = Boolean(params.isTaxInclusive);

    const pumpCharge = pumpRate; // Flat pump_rate added after tax

    let materialUntaxed = 0;
    let materialTax = 0;
    let materialTotal = 0;

    if (isTaxInclusive) {
        materialTotal = rate * qty;
        materialTax = materialTotal - (materialTotal / (1 + taxRate / 100));
        materialUntaxed = materialTotal - materialTax;
    } else {
        materialUntaxed = rate * qty;
        materialTax = (materialUntaxed * taxRate) / 100;
        materialTotal = materialUntaxed + materialTax;
    }

    const untaxedAmount = Number((materialUntaxed + pumpCharge).toFixed(2));
    const taxAmount = Number(materialTax.toFixed(2));
    const amountTotal = Number((materialTotal + pumpCharge).toFixed(2));

    return {
        materialUntaxed: Number(materialUntaxed.toFixed(2)),
        materialTax: Number(materialTax.toFixed(2)),
        materialTotal: Number(materialTotal.toFixed(2)),
        pumpCharge: Number(pumpCharge.toFixed(2)),
        untaxedAmount,
        taxAmount,
        amountTotal,
    };
}

export function useLineItemCalculation() {
    return {
        calculateLineItemTotals,
    };
}
