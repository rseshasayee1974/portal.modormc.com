import fs from 'node:fs';
const changed = JSON.parse(fs.readFileSync('tmp/entity-timezone-files.json', 'utf8'));
const replacements = [
    ['Components/AI/OSAIChatDrawer.vue','d','ts'],
    ['Pages/Agents/Index.vue','d','ts'],
    ['Pages/Attendances/Index.vue','d','date'],
    ['Pages/Dashboard/BatchingDashboard.vue','date','isoString'],
    ['Pages/Dashboard/InventoryDashboard.vue','date','date'],
    ['Pages/InventoryAuditLogs/Index.vue','d','value'],
    ['Pages/GpsDevices/Index.vue','date','date'],
    ['Pages/Invoices/components/InvoiceIndexList.vue','date','dateString'],
    ['Pages/Payroll/Index.vue','d','val'],
    ['Pages/Production/PumpBoomDeployment/Index.vue','d','normalized'],
    ['Pages/Production/BatchingScheduleDashboard.vue','d','normalized'],
    ['Pages/Quotations/Index.vue','parsed','date'],
    ['Pages/CustomerPOs/Index.vue','parsed','date'],
];
for (const [relative, receiver, value] of replacements) {
    const file = 'resources/js/' + relative;
    const names = new Set();
    let source = fs.readFileSync(file, 'utf8').replace(new RegExp(`\\b${receiver}\\.toLocale(DateString|TimeString)\\(`,'g'), (_, type) => {
        const helper = type === 'DateString' ? 'entityLocaleDate' : 'entityLocaleTime';
        names.add(helper); return `${helper}(${value}, `;
    });
    const existing = /import \{ ([^}]+) \} from '@\/Utils\/entityDateTime';\r?\n/;
    const line = n => `import { ${[...new Set(n)].join(', ')} } from '@/Utils/entityDateTime';\n`;
    if (existing.test(source)) source = source.replace(existing, (_, old) => line([...old.split(',').map(s=>s.trim()), ...names]));
    else source = source.replace(/<script\b[^>]*>\r?\n/, m => m + line(names));
    fs.writeFileSync(file, source); changed.push(file);
}
fs.writeFileSync('tmp/entity-timezone-files.json', JSON.stringify([...new Set(changed)]));
