/**
 * Utility functions for formatting dates, currency, and quantities across reports.
 */
import { APP_LOCALE } from './locale.js';
import { entityToday } from './entityDateTime';

/**
 * Format any date object or string into clean YYYY-MM-DD format.
 * Prevents raw JS Date toString outputs (e.g. Sat Aug 01 2026 GMT+0530).
 */
export const formatDate = (val, fallback = '---') => {
    if (!val) return fallback;
    try {
        if (typeof val === 'string') return entityToday(val) || String(val);
        const text = entityToday(val);
        if (!text) return String(val);
        const [year, month, day] = text.split('-');
        return `${day}-${month}-${year}`;
    } catch (e) {
        return String(val);
    }
};

/**
 * Format numeric value as Indian Rupee (INR) currency.
 */
export const formatCurrency = (val) => {
    if (val === null || val === undefined || isNaN(val)) return '₹ 0.00';
    return new Intl.NumberFormat(APP_LOCALE, {
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
