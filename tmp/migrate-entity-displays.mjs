import fs from 'node:fs';
import path from 'node:path';
const changed = JSON.parse(fs.readFileSync('tmp/entity-timezone-files.json', 'utf8'));
function walk(dir) {
    for (const entry of fs.readdirSync(dir, {withFileTypes: true})) {
        const file = path.join(dir, entry.name);
        if (entry.isDirectory()) { walk(file); continue; }
        if (!/\.(vue|js|ts)$/.test(file) || file.endsWith('entityDateTime.js')) continue;
        const original = fs.readFileSync(file, 'utf8');
        const imports = new Set();
        let source = original.replace(/new Date\(([^()\r\n]*)\)\.toLocale(DateString|TimeString|String)\(/g, (_, value, method) => {
            const name = {DateString: 'entityLocaleDate', TimeString: 'entityLocaleTime', String: 'entityLocaleDateTime'}[method];
            imports.add(name);
            return `${name}(${value || 'undefined'}, `;
        });
        if (/JournalEntry[\\/]Index.vue$/.test(file)) {
            source = source.replace(/new Date\(\)/g, 'entityCalendarDate()');
            source = source.replace(/journalForm\.(voucher_date|posting_date)\.toISOString\(\)\.slice\(0, 10\)/g, 'calendarDateString(journalForm.$1)');
            imports.add('entityCalendarDate'); imports.add('calendarDateString');
        }
        if (/PettyCash[\\/]Index.vue$/.test(file)) {
            source = source.replace(/new Date\(([^()]*)\)/g, 'entityCalendarDate($1)');
            source = source.replace(/((?:createForm|editForm|i)\.date)\.toISOString\(\)\.slice\(0, 19\)\.replace\('T', ' '\)/g, 'calendarDateTimeString($1)');
            imports.add('entityCalendarDate'); imports.add('calendarDateTimeString');
        }
        if (source === original) continue;
        const existing = /import \{ ([^}]+) \} from '@\/Utils\/entityDateTime';\r?\n/;
        const line = `import { ${[...imports].join(', ')} } from '@/Utils/entityDateTime';\n`;
        if (existing.test(source)) {
            source = source.replace(existing, (_, names) => `import { ${[...new Set([...names.split(',').map(n=>n.trim()), ...imports])].join(', ')} } from '@/Utils/entityDateTime';\n`);
        } else if (file.endsWith('.vue')) {
            if (!/<script\b/.test(source)) throw new Error(file);
            source = source.replace(/<script\b[^>]*>\r?\n/, m => m + line);
        } else source = line + source;
        fs.writeFileSync(file, source);
        changed.push(file);
    }
}
walk('resources/js');
fs.writeFileSync('tmp/entity-timezone-files.json', JSON.stringify([...new Set(changed)]));
console.log(`Entity date helpers now used across ${new Set(changed).size} updated files.`);
