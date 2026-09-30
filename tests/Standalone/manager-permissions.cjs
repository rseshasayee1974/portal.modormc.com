const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { transformSync } = require('esbuild');

const source = fs.readFileSync(path.join(__dirname, '../../resources/js/Composables/usePermissions.ts'), 'utf8');
const page = { props: { user_role: 'Sales Manager', user_permissions: ['ADDRESS_TYPE.VIEW', 'SALES_ORDER.VIEW'] } };
const sandbox = {
    module: { exports: {} },
    require: (name) => {
        if (name === '@inertiajs/vue3') return { usePage: () => page };
        if (name === 'vue') return { computed: (read) => ({ get value() { return read(); } }) };
        throw new Error(`Unexpected dependency: ${name}`);
    },
};
vm.runInNewContext(transformSync(source, { loader: 'ts', format: 'cjs', platform: 'node' }).code, sandbox);
const { can } = sandbox.module.exports.usePermissions();
assert.equal(can('ADDRESS_TYPE.VIEW'), true, 'Manager lost a granted master permission');
assert.equal(can('sales_order.view'), true, 'Permission matching must ignore case');
assert.equal(can('ROLE.VIEW'), false, 'Manager gained unassigned role-management access');
page.props.user_permissions = [];
assert.equal(can('ADDRESS_TYPE.VIEW'), false, 'Permission removal was not reflected');
page.props.user_role = 'Saas Owner';
assert.equal(can('ROLE.VIEW'), true, 'SaaS owner lost system access');
page.props.user_role = 'Super Administrator';
assert.equal(can('ADDRESS_TYPE.VIEW'), true, 'System administrator lost master access');
page.props.user_role = 'Sales Manager';
assert.equal(can('ROLE.VIEW'), false, 'Switching back to manager retained system privileges');
page.props.user_role = 'Administrator';
page.props.user_permissions = ['ROLE.VIEW', 'ADDRESS_TYPE.VIEW'];
assert.equal(can('ROLE.VIEW'), true, 'Administrator lost an explicitly granted permission');
assert.equal(can('ADDRESS_TYPE.VIEW'), true, 'Administrator lost an explicitly granted master permission');
assert.equal(can('DISPATCH.VIEW'), false, 'Administrator gained an unassigned permission');
console.log('Manager frontend permission checks passed.');
