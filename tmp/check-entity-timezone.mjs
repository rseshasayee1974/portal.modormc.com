import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const {parse, compileScript, compileTemplate, babelParse} = require('vue/compiler-sfc');
const files = new Set([...JSON.parse(fs.readFileSync('tmp/entity-timezone-files.json','utf8')), 'resources/js/Components/Base/BaseDatePicker.vue', 'resources/js/Pages/Entities/Index.vue', 'resources/js/Pages/Entities/components/EntityFormFields.vue', 'resources/js/Pages/Discounts/components/DiscountForm.vue', 'resources/js/Utils/entityDateTime.js', 'resources/js/Utils/formatters.js', 'resources/js/app.js']);
let failures = 0;
for (const filename of files) {
    try {
        const source = fs.readFileSync(filename, 'utf8');
        if (filename.endsWith('.vue')) {
            const {descriptor, errors} = parse(source, {filename});
            if (errors.length) throw errors;
            const script = compileScript(descriptor, {id:'check', fs: {fileExists: fs.existsSync, readFile: f=>fs.readFileSync(f,'utf8')}});
            const result = compileTemplate({source: descriptor.template.content, filename, id:'check', compilerOptions:{bindingMetadata: script.bindings}});
            if (result.errors.length) throw result.errors;
        } else babelParse(source, {sourceType:'module', plugins:['typescript']});
    } catch (e) { failures++; console.error(filename, e); }
}
if (failures) process.exit(1);
console.log(`Compiled/parsed ${files.size} updated frontend files.`);
