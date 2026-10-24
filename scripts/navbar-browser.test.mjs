// Author: Cipher
// Created: 2026-10-23 17:32:42 Asia/Calcutta (UTC+05:30)
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
const { chromium } = await import('playwright').catch(() => import('../storage/logs/performance-tools/node_modules/playwright/index.mjs'));
const origin = process.env.PERFORMANCE_URL || 'http://127.0.0.1:8011';
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const loginCss = manifest['resources/js/Pages/Auth/Login.vue'].css.map(file => readFileSync(`public/build/${file}`, 'utf8')).join('\n');
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
    await test('desktop navbar survives sign-in and late login stylesheet loading', async () => {
        const context = await browser.newContext({ viewport: { width: 1908, height: 942 }, serviceWorkers: 'block' });
        await context.addInitScript(() => localStorage.setItem('login_layout', 'split'));
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(`${origin}/login`);
        await page.getByLabel('Work Email', { exact: true }).waitFor();
        const initial = JSON.parse(await page.locator('#app').getAttribute('data-page'));
        const loginUrl = await page.evaluate(() => route('login'));
        const fixture = {
            ...initial, component: 'Permissions/Index', url: '/settings/permissions',
            props: {
                ...initial.props, errors: {}, presence: null,
                auth: { user: { id: 1, username: 'Navbar test', email: 'navbar@example.test', profile_photo_url: '/assets/modormc_logo_v1.png', roles: [] } },
                user_role: 'Operator', user_permissions: [], user_entities: [],
                permissions: { data: [], total: 0, current_page: 1, per_page: 15, last_page: 1 }, filters: {},
                menus: { top_nav: [
                    { id: 1, title: 'Dashboard', link: 'dashboard', alias: 'dashboard', icon: 'HomeIcon' },
                    { id: 3, title: 'Orders', link: 'orders/quotations', alias: 'orders', icon: 'ClipboardDocumentListIcon' },
                ], sidebar_nav: {} },
            },
        };
        await page.route(loginUrl, request => request.request().method() === 'POST'
            ? request.fulfill({ status: 200, headers: { 'X-Inertia': 'true', 'Content-Type': 'application/json' }, body: JSON.stringify(fixture) })
            : request.continue());
        await page.getByLabel('Work Email', { exact: true }).fill('navbar@example.test');
        await page.getByLabel('Password', { exact: true }).fill('mocked-password');
        await page.getByRole('button', { name: 'Sign in', exact: true }).click();
        const nav = page.locator('nav').first();
        await nav.waitFor();
        // Exercise the stylesheet order that exposed the reported regression.
        await page.addStyleTag({ content: loginCss });
        const desktop = nav.locator('div.hidden[class~="md:flex"]').filter({ hasText: 'Orders' }).first();
        const orders = desktop.getByRole('link', { name: 'Orders', exact: true });
        const mobile = nav.locator('div[class~="md:hidden"]').first();
        for (const width of [1908, 1024, 768]) {
            await page.setViewportSize({ width, height: 942 });
            assert.equal(await orders.isVisible(), true, `Desktop navigation hidden at ${width}px`);
            assert.equal(await mobile.isVisible(), false, `Mobile hamburger visible at ${width}px`);
        }
        await page.setViewportSize({ width: 1908, height: 942 });
        await page.screenshot({ path: 'storage/logs/navbar-desktop-fixed.png', fullPage: true });
        await page.setViewportSize({ width: 390, height: 844 });
        assert.equal(await orders.isVisible(), false, 'Desktop links must collapse on mobile');
        assert.equal(await mobile.isVisible(), true, 'Mobile hamburger must be available');
        await mobile.locator('button').first().click();
        await page.getByRole('link', { name: 'Orders', exact: true }).last().waitFor({ state: 'visible' });
        await page.waitForFunction(() => [...document.querySelectorAll('nav a')].some(link =>
            link.textContent.trim() === 'Orders' && link.offsetParent !== null && link.getBoundingClientRect().x >= 0
        ));
        await page.screenshot({ path: 'storage/logs/navbar-mobile-fixed.png', fullPage: true });
        assert.deepEqual(errors, []);
        await context.close();
    });
} finally {
    await browser.close();
}
