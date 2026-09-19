// Keep instants (ISO timestamps) separate from business calendar values.
let timezoneResolver = () => 'Asia/Kolkata';

export function configureEntityTimezone(resolver) {
    timezoneResolver = resolver;
}

export function entityTimezone() {
    const zone = timezoneResolver() || 'Asia/Kolkata';
    try {
        new Intl.DateTimeFormat('en', { timeZone: zone });
        return zone;
    } catch {
        return 'Asia/Kolkata';
    }
}

export function entityDateTime(value = new Date()) {
    if (value === null || value === '') return '';
    // A date or SQL datetime without an offset is already an entity calendar value.
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/.test(value)) {
        return value.replace('T', ' ');
    }
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
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
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function calendarDateTimeString(date) {
    if (!(date instanceof Date)) return entityDateTime(date);
    return `${calendarDateString(date)} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}:${String(date.getSeconds()).padStart(2, '0')}`;
}

export function entityDaysAgo(days) {
    const date = entityCalendarDate();
    date.setDate(date.getDate() - days);
    return calendarDateString(date);
}

export function entityLocaleDate(value, locale = 'en-GB', options = {}) {
    const date = displayDate(value);
    return date ? date.toLocaleDateString(locale, { ...options, timeZone: 'UTC' }) : '—';
}

export function entityLocaleTime(value, locale = 'en-GB', options = {}) {
    const date = displayDate(value);
    return date ? date.toLocaleTimeString(locale, { ...options, timeZone: 'UTC' }) : '—';
}

export function entityLocaleDateTime(value, locale = 'en-GB', options = {}) {
    const date = displayDate(value);
    return date ? date.toLocaleString(locale, { ...options, timeZone: 'UTC' }) : '—';
}

function displayDate(value) {
    const text = entityDateTime(value);
    if (!text) return null;
    return new Date(text.length === 10 ? text + 'T00:00:00Z' : text.replace(' ', 'T') + 'Z');
}
