import fs from 'node:fs';
let changed = JSON.parse(fs.readFileSync('tmp/entity-timezone-files.json', 'utf8'));
function edit(file, names, transform) {
    let source = transform(fs.readFileSync(file, 'utf8'));
    const existing = /import \{ ([^}]+) \} from '@\/Utils\/entityDateTime';\r?\n/;
    const line = n => `import { ${[...new Set(n)].join(', ')} } from '@/Utils/entityDateTime';\n`;
    if (existing.test(source)) source = source.replace(existing, (_, old) => line([...old.split(',').map(s=>s.trim()), ...names]));
    else source = source.replace(/<script\b[^>]*>\r?\n/, m => m + line(names));
    fs.writeFileSync(file, source); changed.push(file);
}
for (const file of ['resources/js/Pages/Personnel/Index.vue','resources/js/Pages/Personnel/components/PersonnelEditForm.vue', 'resources/js/Pages/SalesOrders/components/SalesOrderCreateForm.vue', 'resources/js/Pages/SalesOrders/components/SalesOrderEditForm.vue']) {
    edit(file, ['entityCalendarDate'], s => s.replace(/new Date\(\)/g, 'entityCalendarDate()').replace(/new Date\(([^()]*scheduled_(?:start|end))\)/g, 'entityCalendarDate($1)'));
}
for (const file of ['resources/js/Pages/StockExhausts/Partials/StockExhaustCreateForm.vue','resources/js/Pages/StockExhausts/Partials/StockExhaustEditForm.vue']) {
    edit(file, ['entityToday'], s => s.replace(/new Date\(\)/g, 'entityToday()'));
}
edit('resources/js/Pages/MaintenanceRequests/Index.vue', ['entityDateTime'], s => s.replace('date_planned: new Date()', 'date_planned: entityDateTime()'));
edit('resources/js/Pages/FuelLogs/Index.vue', ['entityCalendarDate','calendarDateTimeString'], s => s.replace(/new Date\(\)/g, 'entityCalendarDate()').replace('new Date(log.log_date)', 'entityCalendarDate(log.log_date)').replace(/((?:createForm|editForm)\.log_date)\.toISOString\(\)/g, 'calendarDateTimeString($1)'));
for (const file of ['resources/js/Pages/Dashboard/Dashboard.vue','resources/js/Pages/Dashboard/AnalyticsDashboard.vue']) {
    edit(file, ['entityLocaleDateTime'], s => s.replace("return date.toLocaleString('en-IN', {", "return entityLocaleDateTime(value, 'en-IN', {"));
}
fs.writeFileSync('tmp/entity-timezone-files.json', JSON.stringify([...new Set(changed)]));
