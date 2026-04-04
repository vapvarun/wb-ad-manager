// @ts-check
const { defineConfig } = require( '@playwright/test' );

/**
 * Playwright config for WB Ad Manager E2E tests.
 *
 * Update baseURL to match your Local WP site URL.
 *
 * Commands:
 *   npx playwright test                    Run all tests
 *   npx playwright test --reporter=list    Run with detailed output
 *   npx playwright show-report             Open HTML report in browser
 *
 * Files:
 *   test-results/       Screenshots on failure
 *   playwright-report/  HTML report (open with: npx playwright show-report)
 */
module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 30000,
	retries: 0,

	// Generate both list output (terminal) and HTML report (browser)
	reporter: [
		[ 'list' ],
		[ 'html', { outputFolder: 'playwright-report', open: 'never' } ],
	],

	use: {
		baseURL: 'http://local-test.local',

		// Take screenshot on every test (for reporting)
		screenshot: 'on',

		// Save trace on first retry (for debugging failures)
		trace: 'on-first-retry',
	},

	// Where to save test artifacts (screenshots, traces)
	outputDir: 'test-results',

	projects: [
		{
			name: 'setup',
			testMatch: /auth\.setup\.js/,
		},
		{
			name: 'e2e',
			dependencies: [ 'setup' ],
			use: {
				storageState: './tests/e2e/.auth/admin.json',
			},
		},
	],
} );
