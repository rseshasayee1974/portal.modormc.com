/*
Author: ragul-onemodo
Created: 2026-10-18 21:32:52 Asia/Calcutta (UTC+05:30)
*/

import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';
import * as vue from 'vue';
import { parse, compileScript, compileTemplate } from 'vue/compiler-sfc';
import { renderToString } from 'vue/server-renderer';

const filename = new URL('../../resources/js/Pages/Accounting/Index.vue', import.meta.url);
const source = await fs.readFile(filename, 'utf8');
const { descriptor } = parse(source, { filename: filename.pathname });
const compiled = compileScript(descriptor, { id: 'accounting-workflow-test' });

test('pending setup screen renders plant context and migration guidance', async () => {
    const setupFile = new URL('../../resources/js/Pages/Accounting/Setup.vue', import.meta.url);
    const { descriptor: setupDescriptor } = parse(await fs.readFile(setupFile, 'utf8'), { filename: setupFile.pathname });
    const setupScript = compileScript(setupDescriptor, { id: 'accounting-setup-test', inlineTemplate: true });
    const context = vm.createContext({ route: (name, params) => name + '?' + new URLSearchParams(params).toString() });
    const module = new vm.SourceTextModule(setupScript.content, { context });
    await module.link(specifier => {
        const exports = specifier === 'vue' ? vue : specifier === '@inertiajs/vue3' ? { Head: { render: () => null } } : { default: specifier.includes('AppLayout') ? { setup: (_, { slots }) => () => vue.h('div', slots.default?.()) } : { render: () => null } };
        return new vm.SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context });
    });
    await module.evaluate();
    const app = vue.createSSRApp(module.namespace.default, { plant_id: 7 });
    app.config.globalProperties.route = context.route;
    const html = await renderToString(app);
    assert.match(html, /Accounting setup required/);
    assert.match(html, /Plant 7/);
    assert.match(html, /accounting:preflight/);
    assert.match(html, /plant_id=7/);
    assert.doesNotMatch(html, /Trial Balance|New journal/);
});

async function workspace(view = 'dashboard') {
    const calls = [];
    const payload = { plant_id: 7, permissions: { VIEW: true, CREATE: true, UPDATE: true, TALLY: true }, summary: { from: '2026-10-01', to: '2026-10-18', warnings: [], profit_loss: { income: '0.0000', expense: '0.0000', profit: '0.0000' }, balance_sheet: { asset: '0.0000', liability: '0.0000', equity: '0.0000', retained_result: '0.0000', difference: '0.0000' }, totals: { opening: '0', debit: '0', credit: '0', closing: '0' }, controls: { receivable: null, payable: null }, gst: {}, accounts: [], reconciliation: null }, accounts: [], groups: [], journals: { data: [], total: 0, next_page_url: null }, events: { data: [], next_page_url: null }, periods: [], tally: { settings: null, configured_listener: false, jobs: { data: [], next_page_url: null } } };
    const fixture = { payload, error: null, response: null };
    const axios = Object.fromEntries(['get','post','put','delete'].map(method => [method, async (url, body) => {
        calls.push({ method, url, body });
        if (fixture.error) throw fixture.error;
        return { data: fixture.response || payload };
    }]));
    const context = vm.createContext({ console, URLSearchParams, Intl, BigInt, crypto: webcrypto, route: (name, params) => name + '?' + new URLSearchParams(params).toString() });
    const module = new vm.SourceTextModule(compiled.content, { context });
    await module.link(specifier => {
        const exports = specifier === 'vue' ? vue : specifier === 'axios' ? { default: axios } : specifier === '@inertiajs/vue3' ? { Head: { render: () => null }, usePage: () => ({ url: '/finance/accounting?view=' + view }) } : { default: specifier.includes('AppLayout') ? { setup: (_, { slots }) => () => vue.h('div', slots.default?.()) } : { render: () => null } };
        return new vm.SyntheticModule(Object.keys(exports), function () { for (const [key, value] of Object.entries(exports)) this.setExport(key, value); }, { context });
    });
    await module.evaluate();
    const state = module.namespace.default.setup({ workspace: payload }, { expose() {} });
    const component = module.namespace.default;
    const template = compileTemplate({ source: descriptor.template.content, filename: filename.pathname, id: 'accounting-workflow-test', compilerOptions: { bindingMetadata: compiled.bindings } });
    const render = new vm.SourceTextModule(template.code, { context });
    await render.link(() => new vm.SyntheticModule(Object.keys(vue), function () { for (const [key,value] of Object.entries(vue)) this.setExport(key,value); }, { context }));
    await render.evaluate();
    component.render = render.namespace.render;
    return { state, calls, fixture, component, route: context.route };
}

test('accounting template compiles with the real Vue compiler', () => {
    const result = compileTemplate({ source: descriptor.template.content, filename: filename.pathname, id: 'accounting-workflow-test', compilerOptions: { bindingMetadata: compiled.bindings } });
    assert.deepEqual(result.errors, []);
});

test('journal form retains exact decimal strings and plant context when saving', async () => {
    const { state, calls } = await workspace();
    state.newJournal();
    state.journal.value.reference = 'OPENING-verified';
    state.journal.value.narration = 'Verified opening balances';
    state.journal.value.lines[0] = { account_id: 1, debit: '999999999999.9999', credit: '0', narration: '' };
    state.journal.value.lines[1] = { account_id: 2, debit: '0', credit: '999999999999.9999', narration: '' };
    await state.saveJournal();
    assert.equal(calls[0].body.lines[0].debit, '999999999999.9999');
    assert.match(calls[0].url, /plant_id=7/);
    assert.ok(calls[0].body.request_key);
    assert.equal(state.journal.value, null);
    assert.equal(state.failed.value, false);
});

test('stale-plant errors retain the journal and idempotency key for review', async () => {
    const { state, fixture } = await workspace();
    state.newJournal();
    const key = state.journal.value.request_key;
    fixture.error = { response: { status: 409, data: { message: 'Active plant changed. Reload this page.' } } };
    await state.saveJournal();
    assert.equal(state.journal.value.request_key, key);
    assert.equal(state.failed.value, true);
    assert.match(state.message.value, /Reload this page/);
    assert.equal(state.busy.value, false);
});

test('ledger filters carry plant, dates, account and reference to the API', async () => {
    const { state, calls, fixture } = await workspace();
    state.ledgerId.value = '42'; state.search.value = 'supplier/ref';
    fixture.response = { rows: [], total: 0, opening: '50.0000', closing: '50.0000' };
    await state.loadLedger();
    const params = new URLSearchParams(calls[0].url.split('?')[1]);
    assert.equal(params.get('plant_id'), '7'); assert.equal(params.get('account_id'), '42');
    assert.equal(params.get('from'), '2026-10-01'); assert.equal(params.get('to'), '2026-10-18');
    assert.equal(params.get('search'), 'supplier/ref');
    assert.equal(state.ledger.value.closing, '50.0000');
});

test('large monetary display preserves four-place precision and Indian grouping', async () => {
    const { state } = await workspace();
    assert.equal(state.money('999999999999.9999'), '9,99,99,99,99,999.9999');
    assert.equal(state.money('-0.0001'), '-0.0001');
    assert.equal(state.money('100.1000'), '100.10');
});

test('reversal confirmation sends its reason, date and stable request key', async () => {
    const { state, calls } = await workspace();
    state.confirm('reverse', { id: 12 });
    state.reason.value = 'Verified correction evidence'; state.reversalDate.value = '2026-10-18';
    const key = state.confirmation.value.request_key;
    await state.execute();
    assert.equal(calls[0].body.request_key, key); assert.equal(calls[0].body.reason, 'Verified correction evidence');
    assert.equal(calls[0].body.date, '2026-10-18'); assert.match(calls[0].url, /accounting.journals.reverse/);
    assert.equal(state.confirmation.value, null);
});

test('all accounting sections render their empty states without runtime errors', async () => {
    const headings = { dashboard: 'Recent journals', accounts: 'Chart of Accounts', journals: 'Journal register', ledger: 'General ledger', trial: 'Trial balance', reports: 'Financial reports', audit: 'Accounting audit history', tally: 'TallyPrime exchange' };
    for (const [view, heading] of Object.entries(headings)) {
        const { component, fixture, route } = await workspace(view);
        const app = vue.createSSRApp(component, { workspace: fixture.payload });
        app.config.globalProperties.route = route;
        const html = await renderToString(app);
        assert.ok(html.includes(heading), `${view} section must render its content`);
        assert.ok(html.includes('Plant 7'));
    }
});
