/*
Author: ragul-onemodo
Created: 2026-11-25 14:25:55 Asia/Calcutta (UTC+05:30)
*/

import standard from './default';

type DosingMaterial = {
    productId: number;
    name: string;
    batchTarget: number;
    group: string;
    designRate: number;
    moisture: number;
    absorption: number;
};

// Calculate each run from the SSD recipe, then add the rounded run targets as on save.
export function moistureTargets(materials: DosingMaterial[], volume: number, capacity: number) {
    const totals = new Map<number, number>();
    const fail = (error: string) => ({ targets: new Map<number, number>(), error });
    if (!(volume > 0 && Number.isFinite(volume))) return fail('Enter a positive production quantity.');
    if (!(capacity > 0 && Number.isFinite(capacity))) return fail('Set a positive mixer capacity for this plant.');
    const dosing = materials.filter(item => (item.group === 'Aggregate' || item.group === 'Water / Ice')
        && !(item.designRate === 0 && item.batchTarget === 0));
    const invalid = dosing.find(item => !Number.isFinite(item.designRate) || item.designRate <= 0);
    if (invalid) return fail('Enter a positive quantity for ' + invalid.name + ' in the linked mix design.');
    const water = dosing.filter(item => item.group === 'Water / Ice');
    if (!water.length) return fail('Add a water material with a positive quantity in the linked mix design.');
    if (water.length > 1) return fail('Select one active water dosing material. Found: ' + water.map(item => item.name).join(', ') + '.');

    const aggregates = dosing.filter(item => item.group === 'Aggregate');
    const runs = Math.ceil(Number((volume / capacity).toFixed(9)));
    
    const addRuns = (runVolume: number, count: number) => {
        const addQuantity = (id: number, quantity: number) => {
            const roundedRun = Number(quantity.toFixed(3));
            totals.set(id, Number(((totals.get(id) ?? 0) + count * roundedRun).toFixed(3)));
        };
      
        let waterCorrection = 0;
        for (const item of aggregates) {
            const designWeight = item.designRate * runVolume;
             
            const correction = designWeight * (item.moisture - item.absorption) / 100;
            waterCorrection += correction;
            addQuantity(item.productId, designWeight + correction);
        }
        const waterDose = water[0].designRate * runVolume - waterCorrection;
        if (!Number.isFinite(waterDose) || waterDose < 0) return false;
        addQuantity(water[0].productId, waterDose);
        return true;
    };

    if (runs > 1 && !addRuns(capacity, runs - 1)) return fail('Aggregate moisture exceeds the available mix design water. Check the moisture readings and water quantity.');
    const lastVolume = Math.min(capacity, volume - capacity * (runs - 1));
    if (!addRuns(lastVolume, 1)) return fail('Aggregate moisture exceeds the available mix design water. Check the moisture readings and water quantity.');
    return { targets: totals, error: '' };
}

export default {
    ...standard,
    precision(materialPrecision: number | undefined, correctedMoisture = true) {
        return standard.precision(materialPrecision, correctedMoisture);
    },
};
