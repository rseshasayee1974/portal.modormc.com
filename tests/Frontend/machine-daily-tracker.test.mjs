/*
Author: ragul-onemodo
Created: 2026-10-29 14:36:08 Asia/Calcutta (UTC+05:30)
*/
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import vm from 'node:vm';
import * as vue from 'vue';
import { transformSync } from 'esbuild';
import { parse, compileScript, compileTemplate } from 'vue/compiler-sfc';

const dateTime = { entityDateTime: () => '2026-10-29 09:00:00', entityToday: value => value ? String(value).slice(0, 10) : '2026-10-29' };
const context = vm.createContext({ console, route: name => name });
const typesSource = await fs.readFile(new URL('../../resources/js/Pages/MachineTrackers/types.ts', import.meta.url), 'utf8');
const types = new vm.SourceTextModule(transformSync(typesSource, { loader: 'ts', format: 'esm' }).code, { context });
const synthetic = exports => new vm.SyntheticModule(Object.keys(exports), function () {
    for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
}, { context });
await types.link(() => synthetic(dateTime));
await types.evaluate();

async function component(file, props) {
    const url = new URL('../../resources/js/Pages/MachineTrackers/' + file, import.meta.url);
    const { descriptor } = parse(await fs.readFile(url, 'utf8'), { filename: url.pathname });
    const compiled = compileScript(descriptor, { id: 'daily-tracker-test' });
    const template = compileTemplate({ source: descriptor.template.content, filename: url.pathname,
        id: 'daily-tracker-test', compilerOptions: { bindingMetadata: compiled.bindings } });
    assert.deepEqual(template.errors, []);
    const module = new vm.SourceTextModule(transformSync(compiled.content, { loader: 'ts', format: 'esm' }).code, { context });
    await module.link(specifier => {
        if (specifier.endsWith('/types') || specifier === './types') return types;
        if (specifier === 'vue') return synthetic(vue);
        if (specifier.includes('entityDateTime')) return synthetic(dateTime);
        if (specifier.includes('/locale')) return synthetic({ APP_LOCALE: 'en-IN' });
        if (specifier === '@inertiajs/vue3') return synthetic({
            Head: { render: () => null }, router: {}, usePage: () => ({ props: { flash: null } }),
            useForm: initial => vue.reactive({ ...initial, errors: {}, clearErrors() {}, defaults() {}, reset() {} }),
        });
        return synthetic({ default: { render: () => null } });
    });
    await module.evaluate();
    return module.namespace.default.setup(props, { expose() {} });
}

test('running KM handles decimals, stationary machines, missing values and invalid historic readings', () => {
    const calculate = types.namespace.calculateRunningKm;
    assert.equal(calculate(100, 140.25), 40.25);
    assert.equal(calculate('100.10', '100.30'), 0.2);
    assert.equal(calculate(0, 10), 10);
    assert.equal(calculate(50, 50), 0);
    assert.equal(calculate(100, 90), 0);
    assert.equal(calculate(-10, 10), 0);
    assert.equal(calculate(null, 20), 0);
    assert.equal(calculate('', 20), 0);
});

test('opening date defaults to the current entity date and fuel readings do not need a refill', () => {
    const form = types.namespace.getInitialTrackerForm();
    assert.equal(form.opening, '2026-10-29 09:00:00');
    assert.equal(form.last_fuel_filled_km, 0);
    assert.equal(form.fuel, 0);
});

test('read-only running KM preview updates as the opening and closing readings change', async () => {
    const form = vue.reactive({ odometer_start: 100, odometer_end: 140.25, errors: {} });
    const state = await component('components/MachineTrackerFormFields.vue', { form,
        machineOptions: [], shiftOptions: [], operatorOptions: [] });
    assert.equal(state.runningKm.value, 40.25);
    form.odometer_end = 160.5;
    assert.equal(state.runningKm.value, 60.5);
    form.odometer_start = 200;
    assert.equal(state.runningKm.value, 0);
});

test('daily totals use the same selected date and machine records without including odometer gaps', async () => {
    const trackers = [
        { id: 1, log_date: '2026-10-29', machine_id: 1, running_km: 40.25 },
        { id: 2, log_date: '2026-10-29', machine_id: 1, running_km: 30 },
        { id: 3, log_date: '2026-10-29', machine_id: 2, running_km: 100 },
        { id: 4, log_date: '2026-10-28', machine_id: 1, running_km: 999 },
    ];
    const state = await component('Index.vue', { trackers, machines: [], operators: [] });
    assert.equal(state.totalRunningKm.value, 170.25);
    state.selectedMachine.value = 1;
    assert.equal(state.totalRunningKm.value, 70.25);
    assert.equal(state.filteredTrackers.value.length, 2);
    state.selectedDate.value = '2026-10-28';
    assert.equal(state.totalRunningKm.value, 999);
    state.selectedDate.value = null;
    assert.equal(state.totalRunningKm.value, 1069.25);
});
