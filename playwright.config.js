// @ts-check
const { defineConfig } = require( '@playwright/test' );

/**
 * Playwright config for WB Ad Manager E2E tests.
 *
 * Update baseURL to match your Local WP site URL.
 * Default credentials: admin / admin (or whatever you set in Local WP).
 */
module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 30000,
	retries: 0,
	use: {
		baseURL: 'http://local-test.local',
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
	},
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
