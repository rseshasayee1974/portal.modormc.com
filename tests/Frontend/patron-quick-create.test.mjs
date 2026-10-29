/*
Author: ragul-onemodo
Created: 2026-10-29 14:03:43 Asia/Calcutta (UTC+05:30)
*/
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import vm from 'node:vm';
import * as vue from 'vue';
import { parse, compileScript, compileTemplate } from 'vue/compiler-sfc';
import { transformSync } from 'esbuild';

async function loadSelect(overrides = {}, allowed = true, base = false) {
    const file = new URL(base ? '../../resources/js/Components/Base/BaseCreatableSelect.vue'
        : '../../resources/js/Components/PatronQuickCreateSelect.vue', import.meta.url);
    const { descriptor } = parse(await fs.readFile(file, 'utf8'), { filename: file.pathname });
    const compiled = compileScript(descriptor, { id: 'patron-select-test' });
    const template = compileTemplate({ source: descriptor.template.content, filename: file.pathname,
        id: 'patron-select-test', compilerOptions: { bindingMetadata: compiled.bindings } });
    assert.deepEqual(template.errors, []);
    const fixture = { error: null, patron: { id: 42, legal_name: 'New Patron' } };
    const calls = [], events = [], reloads = [];
    const context = vm.createContext({ console, route: name => name });
    const module = new vm.SourceTextModule(transformSync(compiled.content, { loader: 'ts', format: 'esm' }).code, { context });
    await module.link(specifier => {
        const exports = specifier === 'vue' ? vue
            : specifier === '@inertiajs/vue3' ? { router: { reload: options => reloads.push(options) } }
            : specifier === 'axios' ? { default: { post: async (...args) => {
                calls.push(args);
                if (fixture.error) throw fixture.error;
                return { data: { patron: fixture.patron } };
            } } }
            : specifier.includes('usePermissions') ? { usePermissions: () => ({ can: () => allowed }) }
            : specifier.includes('usetoast') ? { useToast: () => ({ add() {} }) }
            : { default: { render: () => null } };
        return new vm.SyntheticModule(Object.keys(exports), function () {
            for (const [key, value] of Object.entries(exports)) this.setExport(key, value);
        }, { context });
    });
    await module.evaluate();
    const props = vue.reactive({ modelValue: 7, options: [], optionLabel: 'label', optionValue: 'value',
        patronTypes: ['Customer'], reloadProp: 'patrons', disabled: false,
        creating: false, canCreate: true, allowEmptyCreate: false, ...overrides });
    const state = module.namespace.default.setup(props, { expose() {}, emit: (...args) => events.push(args) });
    return { state, props, fixture, calls, events, reloads };
}

test('Create Patron opens a blank popup and prefills a typed name without posting', async () => {
    const { state, calls } = await loadSelect();
    state.openCreate();
    assert.equal(state.visible.value, true);
    assert.equal(state.form.legal_name, '');
    state.openCreate('  New Patron  ');
    assert.equal(state.form.legal_name, 'New Patron');
    assert.equal(calls.length, 0);
});

test('save retains existing options and selects the created vendor after refreshing the owning lookup', async () => {
    const { state, calls, events, reloads } = await loadSelect({ options: [{ label: 'Existing', value: 7 }],
        patronTypes: ['Vendor', 'Supplier'], reloadProp: 'vendors' });
    state.openCreate('New Patron');
    state.form.gstin = '33ABCDE1234F1Z5';
    await state.save();
    assert.equal(calls[0][0], 'patrons.store');
    assert.deepEqual([...calls[0][1].patron_type], ['Vendor', 'Supplier']);
    assert.equal(calls[0][1].gstin, '33ABCDE1234F1Z5');
    assert.equal(calls[0][2].headers.Accept, 'application/json');
    assert.equal(state.visible.value, false);
    assert.equal(state.selectOptions.value.length, 2);
    assert.equal(state.selectOptions.value[1].value, 42);
    assert.equal(state.saving.value, true);
    assert.equal(events.some(event => event[0] === 'update:modelValue'), false);
    assert.deepEqual([...reloads[0].only], ['vendors']);
    assert.equal(reloads[0].preserveState, true);
    reloads[0].onFinish();
    assert.equal(events.find(event => event[0] === 'update:modelValue')[1], 42);
    assert.equal(state.saving.value, false);
});

test('duplicate and identifier errors stay in the popup with entered data intact', async () => {
    const { state, fixture, events, reloads } = await loadSelect();
    state.openCreate('Duplicate Patron');
    fixture.error = { response: { status: 422, data: { errors: {
        legal_name: ['Name already exists'], gstin: ['Please provide either GSTIN or Aadhar Number.'],
    } } } };
    await state.save();
    assert.equal(state.visible.value, true);
    assert.equal(state.form.legal_name, 'Duplicate Patron');
    assert.equal(state.errors.value.legal_name[0], 'Name already exists');
    assert.equal(state.saving.value, false);
    assert.equal(events.length, 0);
    assert.equal(reloads.length, 0);
});

test('cancel and reopening reset stale errors and preserve the selected patron', async () => {
    const { state, props, events } = await loadSelect();
    state.openCreate('First');
    state.errors.value = { legal_name: ['Duplicate'] };
    state.visible.value = false;
    state.openCreate('Second');
    assert.equal(Object.keys(state.errors.value).length, 0);
    assert.equal(state.form.legal_name, 'Second');
    assert.equal(props.modelValue, 7);
    assert.equal(events.length, 0);
});

test('permissions and disabled fields prevent opening or saving a patron', async () => {
    for (const [props, permission] of [[{}, false], [{ disabled: true }, true]]) {
        const { state, calls } = await loadSelect(props, permission);
        state.openCreate('Blocked');
        await state.save();
        assert.equal(state.visible.value, false);
        assert.equal(calls.length, 0);
    }
});

test('repeated submit cannot create a second patron while lookup refresh is pending', async () => {
    const { state, calls, reloads } = await loadSelect();
    state.openCreate('New Patron');
    await state.save();
    await state.save();
    assert.equal(calls.length, 1);
    reloads[0].onFinish();
});

test('creatable dropdown supports Create Patron with an empty search and closes its overlay', async () => {
    const { state, events } = await loadSelect({ allowEmptyCreate: true, createLabel: 'Create Patron' }, true, true);
    let hidden = false;
    state.selectRef.value = { hide: () => { hidden = true; } };
    state.handleCreate();
    assert.equal(hidden, true);
    assert.equal(events[0][0], 'create');
    assert.equal(events[0][1], '');
    assert.equal(state.creatableLabel.value, 'Create Patron');
});

test('ordinary creatable dropdowns still require a name and respect creation permissions', async () => {
    const { state, props, events } = await loadSelect({}, true, true);
    state.handleCreate();
    assert.equal(events.length, 0);
    state.filterValue.value = '  Typed Patron  ';
    state.handleCreate();
    assert.equal(events[0][1], 'Typed Patron');
    props.canCreate = false;
    state.filterValue.value = 'Blocked';
    state.handleCreate();
    assert.equal(events.length, 1);
});
