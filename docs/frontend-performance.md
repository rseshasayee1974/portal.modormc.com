<!--
Author: Cipher
Created: 2026-10-23 17:05:05 Asia/Calcutta (UTC+05:30)
-->

# Frontend performance verification

Measured on 2026-10-23 using Vite 6.4.1, Lighthouse 13.5.0 and local Chrome.

## Desktop navbar regression correction

The split login stylesheet contained global Tailwind utilities. If it loaded after ERP styles during navigation, its `.flex` and `.hidden` rules could override desktop breakpoint rules and display the mobile hamburger on desktop. A browser regression test reproduced this at 1908px.

Login utilities are now scoped under `#login-page`. Shared Tailwind resets load once through `resources/css/base.css`, and login-specific font rules are scoped as well. This prevents login CSS from changing the authenticated navbar regardless of stylesheet order. The production assets were rebuilt.

`scripts/navbar-browser.test.mjs` passes with mocked sign-in: desktop links are visible and the hamburger hidden at 768px, 1024px and 1908px; at 390px the desktop links collapse and the mobile drawer opens. It deliberately appends login CSS after ERP styles to exercise the original failure. Both existing login browser scenarios, all 12 JavaScript checks and three PHP scenarios also pass. Screenshots are saved to `storage/logs/navbar-desktop-fixed.png` and `storage/logs/navbar-mobile-fixed.png`.

The total login CSS is now 42,784 bytes, still within the existing 50 KB budget. The Lighthouse results below were recorded before this stylesheet-isolation correction; Lighthouse was not rerun for this layout fix.

```powershell
node --test scripts/navbar-browser.test.mjs
```

## Login follow-up: current results

Browser network tests exposed blanket `Vite::prefetch(concurrency: 3)` calls in both application and Jetstream providers. They caused lazy feature assets to download after load, even on login. Both calls were removed. Required route dependencies still use normal module loading.

The local site also had an active `public/hot` pointer to `http://[::1]:5173`, so it served Vite development modules instead of production assets. The development client alone was 182,535 bytes. **The pointer is now backed up at `storage/logs/frontend-hot-backup.log` and left out of `public/`, so Laravel serves the optimized production build.** The development server itself was not stopped. To intentionally restore hot reloading against that server:

```powershell
Copy-Item -LiteralPath storage/logs/frontend-hot-backup.log -Destination public/hot
```

Login now uses native inputs, checkboxes and buttons, plus a small accessible password-reveal component. Its own Tailwind stylesheet replaces the full application's CSS at startup. PrimeVue, its preset, icons, toast/confirmation services and ERP CSS load through `applicationUI.js` before the first page needing them mounts, including navigation from login. Service-worker registration is scheduled after authentication and when the browser is idle, avoiding guest offline-shell pre-caching. The form's entrance animation was removed so rendering does not deliberately delay visibility.

Current build measurements:

- App entry JavaScript: **390,479 bytes**, down from 578,878 after the first pass. Login's own script adds 35,534 bytes.
- Login's total initial CSS: **38,875 bytes**, down from roughly 300 KB of shared application CSS.
- The production HTML no longer contains blanket asset-prefetch scheduling. Browser tests confirm no ERP UI, icon-font or service-worker downloads during guest login interactions.

| Metric | Current mobile | Current desktop |
| --- | ---: | ---: |
| Performance | **70** | **91** |
| Accessibility | 95 | 95 |
| Best practices | 100 | 100 |
| SEO | 91 | 92 |
| First contentful paint | 4.3 s | 1.0 s |
| Largest contentful paint | 5.3 s | 1.7 s |
| Total blocking time | 10 ms | 0 ms |
| Cumulative layout shift | 0 | 0 |

These single runs used the local Laravel server at port 8011 and the default split login layout. Compared with the first pass, performance increased from 56 to 70 on mobile and 82 to 91 on desktop. Mobile remains below 90; production compression and delivery still need separate measurement. The centered layout was behavior-tested and visually checked, but did not receive a separate Lighthouse audit.

Current reports and screenshots are in `storage/logs/`:

- `lighthouse-login-no-animation-mobile.report.html` and `.json`
- `lighthouse-login-final-desktop.report.html` and `.json`
- `login-split-password.png` and `login-centered-password.png`
- `frontend-login-final-build.log`

Current validation passed: production build; 12 JavaScript tests; three PHP data-loading/asset-loading scenarios; and two browser scenarios covering both login layouts. Browser checks cover email validation, password reveal, remember-me, mocked authentication errors, OTP entry, no guest feature prefetching and navigation into a PrimeVue page. Authentication and OTP endpoints were mocked; no real credentials or OTP messages were used. Browser screenshots were visually inspected.

The existing concrete-quality feature suite passed on rerun (3 tests, 51 assertions, 18 PHPUnit deprecations). Its first follow-up run hit an unrelated randomized-name capitalization failure (`O'Kon` versus `O'kon`) in the title-case middleware path; that result is retained in `storage/logs/frontend-login-feature-tests.log` and the passing rerun in `frontend-login-feature-tests-rerun.log`.

Additional regression commands, after building and starting the local server with production assets:

```powershell
npm install --prefix storage/logs/performance-tools --no-save --package-lock=false playwright
$env:PERFORMANCE_URL = 'http://127.0.0.1:8011'
node --test scripts/login-browser.test.mjs
```

## Implemented changes

- Removed package-name-based manual chunking. The old `vue` match combined PrimeVue and chart wrappers with the core framework and made charts a static startup dependency. Rollup now preserves feature import boundaries.
- Startup's transitive static JavaScript dependencies fell from 2,461,860 bytes across 28 chunks to 578,878 bytes in one chunk (76.5% fewer bytes). Summed gzip sizes fell from 596,322 to approximately 154,640 bytes. These figures exclude route-specific JavaScript, CSS, fonts, images and HTML; they are not total page transfer sizes.
- Frontend permissions build a reactive Set in O(P) time and O(P) space per permission change. Each subsequent permission check uses expected O(1) lookup, rather than scanning O(P) entries. Role changes and permission replacements/mutations invalidate the computed index.
- Shared menu construction indexes permissions and parent IDs, replacing repeated permission scans and one child-count SQL query per top menu with a single parent-ID query. Existing permission and unpublished-child behavior is preserved.
- Users, roles, permissions and terms list search/pagination request only their table data and filters. User dropdown collections and the role permission matrix are lazy server props, so excluded collections are neither queried nor serialized during these partial reloads. User dropdowns are memoized per request.
- Debounced searches are cancelled on unmount and when paginating the users, roles and permissions lists.
- User row edit code loads when expanded. Terms rich-text code and its stylesheet load when the dialog is opened.
- Fonts no longer use a blocking CSS import or request the unused Figtree family. Inter and Outfit load through a nonblocking stylesheet with preconnections and a no-JavaScript fallback. Login background images use native lazy loading and asynchronous decoding, avoiding the hidden desktop background download on mobile.

## First-pass Lighthouse results

Production assets were built with `npm run build` and served at `http://127.0.0.1:8009/login` using Laravel's development server. Vite's hot-file was temporarily moved aside for the audits and restored afterward. Audits used clean Chrome profiles and default mobile simulated throttling or the desktop preset.

| Metric | Mobile | Desktop |
| --- | ---: | ---: |
| Performance | 56 | 82 |
| Accessibility | 88 | 89 |
| Best practices | 100 | 100 |
| SEO | 91 | 92 |
| First contentful paint | 6.9 s | 1.4 s |
| Largest contentful paint | 8.7 s | 2.2 s |
| Total blocking time | 130 ms | 0 ms |
| Cumulative layout shift | 0 | 0.004 |

These are single local runs, not production guarantees. Mobile performance remains below target. The local server serves uncompressed static assets. Production gzip/Brotli, cache headers, server response time, CSS delivery and route-specific payloads still need measurement and tuning. Accessibility and SEO findings remain outside these performance changes.

The first audit **after chunk splitting**, before the font and image adjustments, scored 57 mobile, with 7.8 s FCP, 9.5 s LCP and 2,244,077 transferred bytes. The final mobile run transferred 1,573,873 bytes, about 670 KB less. The score did not improve despite reduced transfer and faster paint; no original-application Lighthouse baseline was captured. The original build graph provides the startup-size baseline above.

Local reports (ignored by Git):

- `storage/logs/lighthouse-after.report.html` and `.json`
- `storage/logs/lighthouse-final-mobile.report.html` and `.json`
- `storage/logs/lighthouse-final-desktop.report.html` and `.json`
- `storage/logs/frontend-build-before.log`
- `storage/logs/frontend-build-final.log`

## Verification and reruns

```powershell
npm run build
node --test scripts/frontend-performance.test.mjs scripts/camera-snapshot.test.mjs
php -d xdebug.mode=off scripts/frontend-data-loading.test.php
```

The frontend tests check case-insensitive access, missing permissions, reactive permission changes, privileged-role changes, a 450 KB startup JavaScript budget, a 50 KB login CSS budget, and heavy-feature isolation from startup/login. The PHP test uses an isolated in-memory SQLite database to verify both initial visits and partial reloads for role permissions and user dropdowns, including query counts. It also checks that generated production asset HTML does not schedule blanket prefetches. The camera tests cover the existing snapshot behavior.

First-pass validation: production build passed; all 11 JavaScript tests passed; both data-loading scenarios passed. The existing `ConcreteQualityDropdownTest` feature suite also passed (3 tests, 51 assertions), with 18 PHPUnit deprecations. PHPUnit required running outside the filesystem sandbox because its bootstrap readability check failed inside it. An exploratory run of `tests/Standalone/report-permissions.php` reached the private-export download scenario and failed its file-readability check; that full standalone script is not claimed as passing. Its role-matrix check was updated to resolve the new lazy prop and assert partial-reload behavior.

To reproduce Lighthouse, start Laravel with production assets, ensure `public/hot` is temporarily absent, and restore it after auditing if a development server is in use:

```powershell
npm exec --yes --package=lighthouse -- lighthouse http://127.0.0.1:8009/login --chrome-path="C:/Program Files/Google/Chrome/Application/chrome.exe" --chrome-flags="--headless=new --no-sandbox" --only-categories=performance,accessibility,best-practices,seo --output=json --output=html --output-path=storage/logs/lighthouse-mobile
# Add --preset=desktop and change --output-path for the desktop audit.
```

Build outputs are generated; rebuild them before deployment and before running the manifest-based tests.

## Remaining coverage

Only the public login page received Lighthouse audits. Authenticated dashboards, lists, forms, reports and maps still require browser measurements with representative authorized sessions and realistic datasets. Existing dashboards already use explicit refresh and lazy chart imports; those data-loading semantics were preserved. This work does not establish optimal complexity or complete on-demand loading for every page in the application.
