/*
Author: ragul-onemodo
Created: 2026-11-25 14:25:55 Asia/Calcutta (UTC+05:30)
*/

type Material = { target_qty: number; runs: (number | null)[] };
type RecipeItem = { cross_quantity?: number; actual_quantity?: number; quantity?: number };

export default {
    quantityPerCbm(batchQuantity: number | null | undefined, volume: number) {
        return batchQuantity != null && volume > 0 ? batchQuantity / volume : null;
    },
    batchQuantityFromRate(rate: number, volume: number) {
        return Number((rate * volume).toFixed(3));
    },
    targetPerCbm(batchTarget: number, volume: number) {
        return this.quantityPerCbm(batchTarget, volume);
    },
    batchTargetFromRate(rate: number | null, volume: number) {
        return this.batchQuantityFromRate(rate ?? 0, volume);
    },
    targetQuantity(item: RecipeItem, volume: number) {
        return Number(item.cross_quantity || item.actual_quantity || item.quantity || 0) * volume;
    },
    precision(materialPrecision: number | undefined, correctedMoisture = false) {
        return correctedMoisture ? 3 : (materialPrecision ?? 3);
    },
    generateRuns(actualPerRun: number, firstVolume: number, volumes: number[], limit: number) {
        // Apply symmetric offsets to equal-size runs so their quantities still add up to the baseline.
        const runs = volumes.map(volume => firstVolume > 0
            ? Number(Math.max(0, actualPerRun * volume / firstVolume).toFixed(3)) : 0);
        const groups = new Map<number, number[]>();
        for (const [index, baseline] of runs.entries()) {
            if (!groups.has(baseline)) groups.set(baseline, []);
            groups.get(baseline)!.push(index);
        }
        for (const [baseline, indices] of groups) {
            if (indices.length < 2) continue;
            const bound = Math.floor(Math.max(0, Math.min(limit, baseline)) * 1000);
            for (const [position, index] of indices.entries()) {
                const distance = 2 * position - (indices.length - 1);
                const offset = Math.sign(distance) * Math.round(Math.abs(distance) * bound / (indices.length - 1));
                runs[index] = Number((baseline + offset / 1000).toFixed(3));
            }
        }
        return runs;
    },
    productionTotalActual(actualPerRun: number | null, productionQty: number, mixerCapacity: number) {
        return mixerCapacity > 0 ? Number(((productionQty / mixerCapacity) * (actualPerRun ?? 0)).toFixed(3)) : 0;
    },
    totalActual(item: Material) {
        return item.runs.reduce<number>((sum, value) => sum + Number(value || 0), 0);
    },
    deviation(item: Material) {
        return this.totalActual(item) - Number(item.target_qty || 0);
    },
    totalTargets(items: Material[]) {
        return Math.round(items.reduce((sum, item) => sum + Number(item.target_qty || 0), 0));
    },
    totalWeight(items: Material[]) {
        return items.reduce((sum, item) => sum + this.totalActual(item), 0).toFixed(0);
    },
};
