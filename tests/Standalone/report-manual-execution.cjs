/*
Author: ragul-onemodo
Created: 2026-10-31 12:28:35 Asia/Calcutta (UTC+05:30)
*/

// Exercise the actual report setup scripts with mocked HTTP, without application data writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const vue = require('vue');
const { parse, compileScript } = require('vue/compiler-sfc');
const esbuild = require('esbuild');
const root = path.resolve(__dirname, '../..');
const scripts = new Map();

function harness(relative, props, expose) {
    if (!scripts.has(relative)) {
        const descriptor = parse(fs.readFileSync(path.join(root, relative), 'utf8')).descriptor;
        const ast = compileScript(descriptor, { id: relative }).scriptSetupAst;
        const imports = ast.filter(node => node.type === 'ImportDeclaration');
        const names = imports.flatMap(node => node.specifiers.map(specifier => specifier.local.name));
        let source = descriptor.scriptSetup.content;
        for (const node of imports.reverse()) source = source.slice(0, node.start) + source.slice(node.end);
        scripts.set(relative, { names, source: esbuild.transformSync(source, { loader: 'ts' }).code });
    }
    const { names, source } = scripts.get(relative);
    const mounted = [], unmounted = [], calls = [];
    let resolvePending;
    const state = { delay: false };
    const context = {
        ...vue, console, Date, URLSearchParams, setTimeout, clearTimeout, setInterval, clearInterval,
        route: name => name, defineProps: () => props, entityToday: () => '2026-10-31',
        onMounted: callback => mounted.push(callback), onUnmounted: callback => unmounted.push(callback),
        Swal: { fire: () => { throw new Error('Unexpected report error'); } },
        axios: { get: async (url, config) => {
            if (url === 'reports.schedules.index') return { data: [] };
            calls.push({ url, params: config?.params });
            if (state.delay) return new Promise(resolve => { resolvePending = resolve; });
            if (url === 'reports.bulk-documents.options') return { data: { options: [] } };
            return { data: { transactions: [], count: 1, documents: [{ id: 1 }] } };
        } },
    };
    for (const name of names) if (!(name in context)) context[name] = () => null;
    vm.createContext(context);
    const scope = vue.effectScope();
    scope.run(() => vm.runInContext(`${source}\nglobalThis.subject = {${expose}};`, context));
    return {
        subject: context.subject, calls, state,
        mount: async () => { for (const callback of mounted) await callback(); await vue.nextTick(); },
        resolve: data => resolvePending({ data }),
        stop: () => { for (const callback of unmounted) callback(); scope.stop(); },
    };
}

async function main() {
    const index = 'resources/js/Pages/Reports/Index.vue';
    const permissions = new Proxy({}, { get: () => ({ view: true }) });
    const props = type => ({
        filters: { type, module: 'accounting', start_date: '2026-10-01', end_date: '2026-10-31' },
        reportPermissions: permissions, ledgers: [], patrons: [],
    });
    const exposed = 'reportCatalog, reportType, selectedId, startDate, reportData, executeReport, generateReport, handlePageChange';
    const catalog = harness(index, props('ledger'), exposed);
    const types = catalog.subject.reportCatalog.flatMap(module => module.reports.map(report => report.id));
    catalog.stop();
    for (const type of types.filter(type => type !== 'bulk_documents')) {
        const h = harness(index, props(type), exposed), s = h.subject;
        await h.mount();
        assert.equal(h.calls.length, 0, `${type}: must not query on mount`);
        s.handlePageChange(2);
        assert.equal(h.calls.length, 0, `${type}: cannot paginate before execution`);
        if (type === 'ledger') s.selectedId.value = 1;
        await vue.nextTick();
        assert.equal(h.calls.length, 0, `${type}: selection must not query`);
        await s.executeReport();
        assert.equal(h.calls.length, 1, `${type}: Execute Query must request data`);
        assert.ok(s.reportData.value, `${type}: executed query must show results`);
        s.handlePageChange(2);
        await vue.nextTick();
        assert.equal(h.calls.length, 2, `${type}: executed results must remain pageable`);
        s.startDate.value = '2026-09-01';
        await vue.nextTick();
        assert.equal(h.calls.length, 2, `${type}: filters must not query`);
        assert.equal(s.reportData.value, null, `${type}: filter edits must clear rows`);
        await s.generateReport();
        assert.equal(h.calls.length, 2, `${type}: implicit requests must be ignored`);
        h.state.delay = true;
        const pending = s.executeReport();
        await Promise.resolve();
        s.startDate.value = '2026-08-01';
        await vue.nextTick();
        h.resolve({ stale: true });
        await pending;
        assert.equal(s.reportData.value, null, `${type}: outdated responses must not refill rows`);
        h.stop();
    }
    console.log(`PASS: manual execution, filter clearing, pagination and stale responses across ${types.length - 1} report types`);

    const bulk = harness('resources/js/Pages/Reports/components/BulkDocumentReport.vue', {
        defaultStartDate: '2026-10-01', defaultEndDate: '2026-10-31',
    }, 'filters, result, runPreview, resetFilters, loadInvoiceOptions');
    await bulk.mount();
    assert.equal(bulk.calls.length, 0, 'Bulk reports must not query on mount');
    bulk.subject.filters.patron_id = 4;
    await vue.nextTick();
    assert.equal(bulk.calls.length, 0, 'Bulk filter changes must not query');
    await bulk.subject.runPreview();
    assert.ok(bulk.subject.result.value);
    assert.equal(bulk.calls.length, 1);
    bulk.subject.filters.reference = 'INV-10';
    await vue.nextTick();
    assert.equal(bulk.subject.result.value, null);
    assert.equal(bulk.calls.length, 1);
    bulk.state.delay = true;
    const pending = bulk.subject.runPreview();
    bulk.subject.filters.reference = 'INV-11';
    await vue.nextTick();
    bulk.resolve({ count: 1, documents: [{ stale: true }] });
    await pending;
    assert.equal(bulk.subject.result.value, null);
    bulk.subject.resetFilters();
    await vue.nextTick();
    assert.equal(bulk.calls.length, 2, 'Bulk Reset must not execute a query');
    bulk.stop();
    console.log('PASS: bulk preview waits for Execute Query, resets/filters clear rows, stale responses ignored');

    let tableCount = 0;
    const directory = path.join(root, 'resources/js/Pages/Reports/components');
    for (const filename of fs.readdirSync(directory).filter(file => file.endsWith('.vue'))) {
        const descriptor = parse(fs.readFileSync(path.join(directory, filename), 'utf8')).descriptor;
        const tables = [];
        function walk(node, table = null, section = null) {
            if (node.type === 1) {
                if (node.tag === 'table') { table = { headers: [], rows: [] }; tables.push(table); section = null; }
                if (['thead', 'tbody', 'tfoot'].includes(node.tag)) section = node.tag;
                if (table && node.tag === 'th' && section === 'thead') table.headers.push(node);
                if (table && node.tag === 'tr') table.rows.push(node);
            }
            for (const child of node.children || []) walk(child, table, section);
        }
        walk(descriptor.template.ast);
        for (const table of tables) {
            if (!table.headers.length || !table.rows.some(row => row.props.some(prop => prop.type === 7 && prop.name === 'for'))) continue;
            const first = table.headers[0].children.map(node => typeof node.content === 'string' ? node.content : '').join('').trim();
            assert.ok(['#', 'S/No', 'Trip #'].includes(first), `${filename}: missing serial-number column`);
            tableCount++;
        }
    }
    console.log(`PASS: all ${tableCount} transaction tables have serial-number columns`);
}

main().catch(error => { console.error(error); process.exitCode = 1; });
