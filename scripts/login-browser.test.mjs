// Author: Cipher
// Created: 2026-10-23 17:16:17 Asia/Calcutta (UTC+05:30)
import test from 'node:test';
import assert from 'node:assert/strict';
const { chromium } = await import('playwright').catch(() =>
    import('../storage/logs/performance-tools/node_modules/playwright/index.mjs')
);

const origin = process.env.PERFORMANCE_URL || 'http://127.0.0.1:8011';
const browser = await chromium.launch({ executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
try {
    for (const layout of ['split', 'centered']) {
        await test(`login ${layout}: controls, errors, OTP and lazy UI navigation`, async () => {
            const context = await browser.newContext({ viewport: { width: layout === 'split' ? 390 : 1440, height: 900 } });
            await context.addInitScript(value => localStorage.setItem('login_layout', value), layout);
            const page = await context.newPage();
            const requests = [];
            const errors = [];
            page.on('request', request => requests.push(request.url()));
            page.on('pageerror', error => errors.push(error.message));
            await page.goto(`${origin}/login`);
            await page.getByLabel('Work Email', { exact: true }).waitFor();
            await page.screenshot({ path: `storage/logs/login-${layout}-password.png`, fullPage: true });
            const initial = await page.locator('#app').getAttribute('data-page').then(JSON.parse);
            const endpoints = await page.evaluate(() => ({ login: route('login'), otp: route('login.send-otp') }));
            assert.equal(requests.some(url => /applicationUI-|primeicons|\/sw\.js/.test(url)), false, 'ERP UI and offline worker must not load for login');
            const submit = page.getByRole('button', { name: layout === 'split' ? 'Sign in' : 'Secure Login', exact: true });
            assert.equal(await submit.isDisabled(), true);
            await page.getByLabel('Work Email', { exact: true }).fill('invalid');
            await page.getByLabel('Work Email', { exact: true }).blur();
            await page.getByText('Enter a valid email address.', { exact: true }).waitFor();
            await page.getByLabel('Work Email', { exact: true }).fill('performance@example.test');
            const password = page.getByLabel('Password', { exact: true });
            await password.fill('test-password');
            await page.getByRole('button', { name: 'Show password', exact: true }).click();
            assert.equal(await password.getAttribute('type'), 'text');
            await page.getByRole('button', { name: 'Hide password', exact: true }).click();
            assert.equal(await password.getAttribute('type'), 'password');
            const remember = page.getByRole('checkbox', { name: 'Remember me', exact: true });
            assert.equal(await remember.isChecked(), true);
            await remember.uncheck();
            assert.equal(await remember.isChecked(), false);
            assert.equal(await submit.isEnabled(), true);
            await page.route(endpoints.login, async request => {
                if (request.request().method() !== 'POST') return request.continue();
                await request.fulfill({ status: 200, headers: { 'X-Inertia': 'true', 'Content-Type': 'application/json' }, body: JSON.stringify({ ...initial, props: { ...initial.props, errors: { email: 'Test authentication error.' } } }) });
            });
            await submit.click();
            await page.getByRole('alert').filter({ hasText: 'Test authentication error.' }).waitFor();
            assert.equal(await password.inputValue(), '');
            await page.getByRole('button', { name: 'OTP Login', exact: true }).click();
            await page.getByLabel('Mobile Number or Email', { exact: true }).fill('9876543210');
            await page.route(endpoints.otp, request => request.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'Test OTP sent.' }) }));
            await page.getByRole('button', { name: 'Send OTP', exact: true }).click();
            await page.getByText('Test OTP sent.', { exact: true }).waitFor();
            const digits = page.locator('input[maxlength="1"]');
            assert.equal(await digits.count(), 6);
            for (let index = 0; index < 6; index++) await digits.nth(index).fill(String(index + 1));
            assert.equal(await page.getByRole('button', { name: 'Verify & Login', exact: true }).isEnabled(), true);
            await page.screenshot({ path: `storage/logs/login-${layout}.png`, fullPage: true });
            assert.equal(requests.some(url => /applicationUI-|primeicons|\/sw\.js/.test(url)), false, 'Guest interactions must not start background ERP downloads');
            await page.getByRole('button', { name: 'Password', exact: true }).click();
            const forgot = page.getByRole('link', { name: /Forgot/ });
            if (await forgot.count()) {
                await forgot.click();
                await page.waitForURL('**/forgot-password');
                await page.locator('.p-inputtext').waitFor();
                assert.equal(requests.some(url => /applicationUI-/.test(url)), true, 'PrimeVue must initialize before a page using its components mounts');
            }
            assert.deepEqual(errors, [], 'No runtime errors on login or UI navigation');
            await context.close();
        });
    }
} finally {
    await browser.close();
}
