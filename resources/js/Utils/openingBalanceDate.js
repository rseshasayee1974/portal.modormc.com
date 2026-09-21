import { entityToday } from './entityDateTime';

export function currentOpeningBalanceDate(today = entityToday()) {
    const year = Number(today.slice(0, 4));
    return `${Number(today.slice(5, 7)) < 4 ? year - 1 : year}-04-01`;
}
