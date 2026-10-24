// Author: Cipher
// Created: 2026-10-23 16:57:20 Asia/Calcutta (UTC+05:30)
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import { build } from 'esbuild';

// Exercise the real composable with reactive Inertia props, without a browser.
const result = await build({
    stdin: {
        contents: `export { usePermissions } from './resources/js/Composables/usePermissions.ts';
            export { page } from '@inertiajs/vue3';`,
        resolveDir: process.cwd(),
    },
    bundle: true, write: false, format: 'esm', platform: 'node',
    plugins: [{
        name: 'inertia-test-page',
        setup(builder) {
            builder.onResolve({ filter: /^@inertiajs\/vue3$/ }, () => ({ path: 'page', namespace: 'fixture' }));
            builder.onLoad({ filter: /.*/, namespace: 'fixture' }, () => ({
                contents: `import { reactive } from 'vue';
                    export const page = reactive({ props: {} });
                    export const usePage = () => page;`,
                resolveDir: process.cwd(),
            }));
        },
    }],
});
const { usePermissions, page } = await import(`data:text/javascript;base64,${Buffer.from(result.outputFiles[0].text).toString('base64')}`);

test('permissions remain case insensitive and deny missing permissions', () => {
    page.props = { user_permissions: ['USERS.VIEW', 'orders.edit'], user_role: 'Operator' };
    const { can } = usePermissions();
    assert.equal(can('users.view'), true);
    assert.equal(can('ORDERS.EDIT'), true);
    assert.equal(can('users.delete'), false);
});

test('permission index refreshes on replacement and in-place updates', () => {
    page.props = { user_permissions: ['users.view'], user_role: 'Operator' };
    const { can } = usePermissions();
    assert.equal(can('users.view'), true);
    page.props.user_permissions = ['orders.view'];
    assert.equal(can('users.view'), false);
    assert.equal(can('orders.view'), true);
    page.props.user_permissions.push('users.edit');
    assert.equal(can('USERS.EDIT'), true);
    page.props.user_permissions.splice(0, 1);
    assert.equal(can('orders.view'), false);
});

test('privileged roles retain access and role changes revoke the bypass', () => {
    page.props = { user_permissions: [], user_role: '' };
    const { can } = usePermissions();
    for (const role of ['Saas Owner', 'Super Administrator', 'Platform Admin', 'Super Admin']) {
        page.props.user_role = role;
        assert.equal(can('anything'), true);
    }
    page.props.user_role = 'Administrator';
    assert.equal(can('anything'), false);
    page.props.user_permissions = undefined;
    assert.equal(can('anything'), false);
});

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
function staticDependencies(key, seen = new Set()) {
    if (seen.has(key)) return seen;
    assert.ok(manifest[key], `Missing manifest entry: ${key}`);
    seen.add(key);
    for (const dependency of manifest[key].imports || []) staticDependencies(dependency, seen);
    return seen;
}
test('production startup has a bounded JavaScript budget', () => {
    const dependencies = staticDependencies('resources/js/app.js');
    const bytes = [...dependencies].reduce((sum, key) => sum + statSync(`public/build/${manifest[key].file}`).size, 0);
    assert.ok(bytes < 450_000, `Startup JavaScript is ${bytes} bytes; budget is 450 KB`);
    console.log(`Startup: ${bytes} bytes in ${dependencies.size} JavaScript chunks`);
});

test('login uses its small stylesheet and does not depend on the ERP UI', () => {
    const dependencies = staticDependencies('resources/js/Pages/Auth/Login.vue');
    assert.equal(dependencies.has('resources/js/applicationUI.js'), false);
    const styles = new Set([...dependencies].flatMap(key => manifest[key].css || []));
    const bytes = [...styles].reduce((sum, file) => sum + statSync(`public/build/${file}`).size, 0);
    assert.ok(bytes < 50_000, `Login CSS is ${bytes} bytes; budget is 50 KB`);
    assert.ok(manifest['resources/js/app.js'].dynamicImports.includes('resources/js/applicationUI.js'));
    console.log(`Login CSS: ${bytes} bytes`);
});

test('charts, maps and rich text stay outside startup and login dependencies', () => {
    const startup = staticDependencies('resources/js/app.js');
    const login = staticDependencies('resources/js/Pages/Auth/Login.vue');
    const heavyEntries = Object.keys(manifest).filter(key => /apexcharts|leaflet|quill|editor/i.test(key));
    assert.ok(heavyEntries.length > 0, 'Expected lazy feature chunks in production build');
    for (const key of heavyEntries) {
        assert.equal(startup.has(key), false, `${key} was eagerly loaded at startup`);
        assert.equal(login.has(key), false, `${key} was eagerly loaded by login`);
    }
});
