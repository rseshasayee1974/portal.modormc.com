export interface LineItemCalculationParams {
    quantity: number;
    rate: number;
    pump_rate: number;
    taxRate: number;
    isTaxInclusive: boolean;
    discount?: number;
    discountType?: string | null;
    roundAmounts?: boolean;
}

export interface LineItemCalculationResult {
    discountAmount: number;
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
    const grossLine = qty * rate + pumpCharge;
    const discountValue = Math.max(0, Number(params.discount || 0));
    const amount = (value: number) => params.roundAmounts === false ? value : Number(value.toFixed(2));
    const discountAmount = Math.min(grossLine, amount(params.discountType === '%' ? grossLine * discountValue / 100 : discountValue));

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
        grossAmount = qty * rate + pumpCharge - discountAmount;
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

        untaxedAmount = materialUntaxed + pumpCharge - discountAmount;
        taxAmount = (untaxedAmount * taxRate) / 100;
        grossAmount = untaxedAmount;
        amountTotal = untaxedAmount + taxAmount;
    }

    return {
        discountAmount,
        materialUntaxed: amount(materialUntaxed),
        materialTax: amount(materialTax),
        materialTotal: amount(materialTotal),
        pumpCharge: amount(pumpCharge),
        untaxedAmount: amount(untaxedAmount),
        taxAmount: amount(taxAmount),
        amountTotal: amount(amountTotal),
        grossAmount: amount(grossAmount),
    };
}

export function useLineItemCalculation() {
    return {
        calculateLineItemTotals,
    };
}
