// @ts-check
const { test, expect } = require( '@playwright/test' );

/**
 * Authenticate as admin and save session state.
 *
 * Uses the dev-auto-login mu-plugin: ?autologin=1 logs in as admin (user ID 1).
 * The mu-plugin redirects after setting the auth cookie, so we wait for the
 * redirect to complete then verify we're logged in.
 *
 * Install the mu-plugin first:
 *   wp-content/mu-plugins/dev-auto-login.php
 *
 * Supports: ?autologin=1 (admin), ?autologin=username (any user)
 */
test( 'authenticate as admin', async ( { page } ) => {
	// Use autologin mu-plugin — logs in as admin and redirects
	await page.goto( '/wp-admin/?autologin=1' );

	// Wait for redirect to complete (mu-plugin strips the autologin param)
	await page.waitForLoadState( 'networkidle' );

	// Verify we're logged in — admin bar or dashboard should be visible
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	// Save authentication state for all other tests
	await page.context().storageState( {
		path: './tests/e2e/.auth/admin.json',
	} );
} );
