// Keep instants (ISO timestamps) separate from business calendar values.
import { APP_LOCALE } from './locale.js';

export const IST_TIMEZONE = 'Asia/Kolkata';

export function entityTimezone() {
    return IST_TIMEZONE;
}

// Offset-free business values are IST; timestamps with offsets remain instants.
export function entityInstant(value) {
    if (value instanceof Date) return value;
    if (value === null || value === undefined || value === '') return new Date(NaN);
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?$/.test(value)) {
        const text = value.replace(' ', 'T');
        return new Date(`${text.length === 10 ? text + 'T00:00:00' : text}+05:30`);
    }
    return new Date(value);
}

export function entityDateTime(value = new Date()) {
    if (value === null || value === '' || value === undefined) return '';
    // A date or SQL datetime without an offset is already an entity calendar value.
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?)?$/.test(value)) {
        const text = value.replace('T', ' ').replace(/\.\d+$/, '');
        const instant = new Date(text.length === 10 ? `${text}T00:00:00+05:30` : `${text.replace(' ', 'T')}+05:30`);
        if (Number.isNaN(instant.getTime())) return '';
        const formatted = entityDateTime(instant);
        return formatted.startsWith(text) ? text : '';
    }
    const date = entityInstant(value);
    if (Number.isNaN(date.getTime())) return '';
    const parts = Object.fromEntries(new Intl.DateTimeFormat(APP_LOCALE, {
        timeZone: entityTimezone(), year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    }).formatToParts(date).map(({ type, value }) => [type, value]));
    return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`;
}

export function entityToday(value = new Date()) {
    return entityDateTime(value).slice(0, 10);
}

// UI calendar adapter only. Do not serialize this synthetic Date as a UTC instant.
export function entityCalendarDate(value = new Date()) {
    const text = entityDateTime(value);
    if (!text) return null;
    const [y, m, d, h = 0, min = 0, s = 0] = text.split(/[- :]/).map(Number);
    return new Date(y, m - 1, d, h, min, s);
}

export function calendarDateString(date) {
    if (!(date instanceof Date)) return entityToday(date);
    if (Number.isNaN(date.getTime())) return '';
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function calendarDateTimeString(date) {
    if (!(date instanceof Date)) return entityDateTime(date);
    if (Number.isNaN(date.getTime())) return '';
    return `${calendarDateString(date)} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}:${String(date.getSeconds()).padStart(2, '0')}`;
}

export function entityDaysAgo(days) {
    return entityAddDays(entityToday(), -days);
}

export function entityAddDays(value, days) {
    const text = entityToday(value);
    if (!text) return '';
    const date = new Date(`${text}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + Number(days));
    if (Number.isNaN(date.getTime())) return '';
    return date.toISOString().slice(0, 10);
}

export function entityLocaleDate(value, locale = APP_LOCALE, options = {}) {
    const date = displayDate(value);
    return date ? date.toLocaleDateString(locale, { ...options, timeZone: IST_TIMEZONE }) : '—';
}

export function entityLocaleTime(value, locale = APP_LOCALE, options = {}) {
    const date = displayDate(value);
    return date ? date.toLocaleTimeString(locale, { ...options, timeZone: IST_TIMEZONE }) : '—';
}

export function entityLocaleDateTime(value, locale = APP_LOCALE, options = {}) {
    const date = displayDate(value);
    return date ? date.toLocaleString(locale, { ...options, timeZone: IST_TIMEZONE }) : '—';
}

function displayDate(value) {
    const text = entityDateTime(value);
    if (!text) return null;
    return new Date(text.length === 10 ? text + 'T00:00:00+05:30' : text.replace(' ', 'T') + '+05:30');
}
