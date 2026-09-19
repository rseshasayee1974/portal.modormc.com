const fs = require('node:fs');
const assert = require('node:assert/strict');
const { transformSync } = require('esbuild');
const { ref, watch, computed, nextTick, reactive } = require('vue');

async function main() {
    const source = fs.readFileSync('resources/js/Pages/Patrons/components/PatronFormFields.vue', 'utf8');
    const script = source.split('// --- Dynamic Address Lookup Logic ---')[1].split('</script>')[0];
    const code = transformSync(script, { loader: 'ts', format: 'cjs' }).code;
    const form = reactive({
        address_state_id: 36,
        address_city: 'Chennai',
        address_zipcode: '600001',
        address_line_2: 'Saved locality',
    });
    let mounted;
    const requests = [];
    const axios = { get: async (url) => {
        requests.push(url);
        return { data: url.endsWith('/districts') ? ['Chennai'] : [{ zipcode: '600001', area: 'Saved locality' }] };
    } };
    const run = new Function('ref', 'watch', 'computed', 'nextTick', 'onMounted', 'axios', 'props',
        code + '\nreturn { selectedDistrict, selectedZipcode, hasDistrictOptions };');
    const fields = run(ref, watch, computed, nextTick, callback => { mounted = callback; }, axios, { form });
    await mounted();
    await nextTick();
    assert.equal(fields.hasDistrictOptions.value, true);
    assert.equal(fields.selectedDistrict.value, 'Chennai');
    assert.equal(fields.selectedZipcode.value, '600001');
    assert.equal(form.address_zipcode, '600001');
    assert.equal(form.address_line_2, 'Saved locality');
    assert.equal(requests.filter(url => url.endsWith('/zipcodes')).length, 1);
    console.log('Patron edit address dropdown initialization passed.');
}

main().catch(error => { console.error(error); process.exitCode = 1; });
