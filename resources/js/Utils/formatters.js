/**
 * Utility functions for formatting dates, currency, and quantities across reports.
 */

/**
 * Format any date object or string into clean YYYY-MM-DD format.
 * Prevents raw JS Date toString outputs (e.g. Sat Aug 01 2026 GMT+0530).
 */
export const formatDate = (val, fallback = '---') => {
    if (!val) return fallback;
    try {
        const d = new Date(val);
        if (isNaN(d.getTime())) return String(val);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    } catch (e) {
        return String(val);
    }
};

/**
 * Format numeric value as Indian Rupee (INR) currency.
 */
export const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
    }).format(val);
};

/**
 * Format numeric value with fixed decimal places cleanly.
 */
export const formatQuantity = (val, decimals = 2) => {
    const num = Number(val);
    if (isNaN(num)) return '0.00';
    return num.toFixed(decimals);
};
