# WB Ad Manager — Quality Report (Baseline)

Generated: 2026-04-04 | Plugin Version: 2.7.0 | PHP: 8.2 | WordPress: 6.9.4

## Summary

| Tool | Command | Result |
|------|---------|--------|
| **PHPCS** | `phpcs` | 158 errors, 65 warnings in 26 files |
| **PHPStan** (level 5) | `./vendor/bin/phpstan analyse --memory-limit=1G` | 84 errors in 17 files |
| **ESLint** | `npx eslint@8 assets/js/` | 1 warning (after globals config) |
| **Stylelint** | `npx stylelint "assets/css/*.css" --ignore-pattern "**/*.min.css"` | 122 issues in 8 files |
| **PHP Compat** (7.4-8.4) | `phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-8.4` | 0 errors — clean |
| **Playwright** (13 tests) | `npx playwright test` | 13 passed, 0 failed |
| **Grunt build** | `npx grunt build` | 8 CSS + 4 JS minified, POT generated |

## Playwright E2E Test Results

All screenshots saved in `docs/test-reports/screenshots/`.

### Passed (13/13)

| # | Test | Screenshot |
|---|------|-----------|
| 1 | Auth: authenticate as admin | `auth.setup.js-authenticate-as-admin.png` |
| 2 | Plugin is active in plugins list | `plugin-activation-Plugin-A-64452-n-is-active-in-plugins-list.png` |
| 3 | Admin menu item exists | `plugin-activation-Plugin-Activation-admin-menu-item-exists.png` |
| 4 | Settings page loads without errors | `plugin-activation-Settings-f3ea7-s-page-loads-without-errors.png` |
| 5 | General settings fields are present | `plugin-activation-Settings-cd748-settings-fields-are-present.png` |
| 6 | Settings can be saved | `plugin-activation-Settings-Page-settings-can-be-saved.png` |
| 7 | Add new ad page loads | `plugin-activation-Ad-Creation-add-new-ad-page-loads.png` |
| 8 | All ads list page loads | `plugin-activation-Ad-Creation-all-ads-list-page-loads.png` |
| 9 | No JS console errors (admin) | `plugin-activation-No-Conso-d3dd2-ge-has-no-JS-console-errors.png` |
| 10 | Homepage loads without fatal errors | `frontend-Frontend-homepage-loads-without-fatal-errors.png` |
| 11 | No JS console errors (frontend) | `frontend-Frontend-no-JS-console-errors-on-frontend.png` |
| 12 | Plugin CSS is enqueued | `frontend-Frontend-plugin-CSS-is-enqueued-on-frontend.png` |
| 13 | Deactivation is safe | `frontend-Deactivation-Safe-703ed--deactivated-without-errors.png` |

## PHPStan Error Categories

| Identifier | Count | Type |
|-----------|-------|------|
| `argument.type` | 39 | Real bug — wrong type passed to function |
| `new.static` | 20 | Pattern issue — unsafe static constructor |
| `constant.notFound` | 13 | Config issue — bootstrap file needed |
| `property.onlyWritten` | 3 | Dead code — property set but never read |
| `function.notFound` | 2 | Missing plugin — install bbPress |
| `class.notFound` | 2 | Missing import or wrong namespace |
| `method.resultUnused` | 2 | Return value ignored |
| `method.nonObject` | 2 | Possible null — needs null check |
| `function.alreadyNarrowedType` | 1 | Redundant check |

## How to Reproduce

```bash
# Clone and install
git clone git@github.com:vapvarun/wb-ads-rotator-with-split-test.git
cd wb-ads-rotator-with-split-test
npm install --include=dev
composer install
npx playwright install chromium

# Run all quality checks
phpcs                                                              # PHPCS
./vendor/bin/phpstan analyse --memory-limit=1G                     # PHPStan
npx eslint@8 assets/js/                                            # ESLint
npx stylelint "assets/css/*.css" --ignore-pattern "**/*.min.css"   # Stylelint
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4-8.4 .  # PHP Compat
npx playwright test                                                # E2E tests
npx grunt build                                                    # Build assets
```
