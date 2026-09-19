import fs from 'node:fs';
import path from 'node:path';
const changed = [];
function walk(dir) {
    for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
        const file = path.join(dir, entry.name);
        if (entry.isDirectory()) { walk(file); continue; }
        if (!/\.(vue|js|ts)$/.test(file) || file.endsWith('entityDateTime.js')) continue;
        const original = fs.readFileSync(file, 'utf8');
        let source = original;
        const imports = new Set();
        function replace(pattern, value, name) {
            source = source.replace(pattern, () => { imports.add(name); return value; });
        }
        replace(/new Date\(new Date\(\)\.setDate\(new Date\(\)\.getDate\(\) - 30\)\)\.toISOString\(\)\.split\('T'\)\[0\]/g, 'entityDaysAgo(30)', 'entityDaysAgo');
        replace(/new Date\(\)\.toISOString\(\)\.(?:substring|slice)\(0, 10\)|new Date\(\)\.toISOString\(\)\.split\('T'\)\[0\]/g, 'entityToday()', 'entityToday');
        replace(/new Date\(\)\.toISOString\(\)\.substring\(0, 7\)/g, 'entityToday().substring(0, 7)', 'entityToday');
        replace(/new Date\(\)\.toISOString\(\)\.substring\(0, 16\)/g, "entityDateTime().replace(' ', 'T').substring(0, 16)", 'entityDateTime');
        replace(/new Date\(\)\.toLocaleDateString\('en-CA',\s*\{\s*timeZone:\s*'Asia\/Kolkata'\s*\}\)/g, 'entityToday()', 'entityToday');
        if (/Batches[\\/]components[\\/](BatchCreateForm|BatchEditForm|DispatchSection)\.vue$/.test(file)) {
            replace(/new Date\(\)(?!\.toISOString)/g, 'entityCalendarDate()', 'entityCalendarDate');
            source = source.replace(/new Date\(([^()\n]*(?:start_time|end_time|empty_time|load_time|dispatch_time|invoice_date)[^()\n]*)\)/g, (_, value) => {
                imports.add('entityCalendarDate'); return `entityCalendarDate(${value})`;
            });
        }
        if (source === original) continue;
        const line = `import { ${[...imports].join(', ')} } from '@/Utils/entityDateTime';\n`;
        const existing = /import \{ ([^}]+) \} from '@\/Utils\/entityDateTime';\r?\n/;
        if (existing.test(source)) {
            source = source.replace(existing, (_, names) => `import { ${[...new Set([...names.split(',').map(n=>n.trim()), ...imports])].join(', ')} } from '@/Utils/entityDateTime';\n`);
        } else if (file.endsWith('.vue')) {
            source = source.replace(/<script\b[^>]*>\r?\n/, m => m + line);
        } else source = line + source;
        fs.writeFileSync(file, source);
        changed.push(file);
    }
}
walk('resources/js');
fs.writeFileSync('tmp/entity-timezone-files.json', JSON.stringify(changed));
console.log(`Updated ${changed.length} files to use entity business dates.`);
