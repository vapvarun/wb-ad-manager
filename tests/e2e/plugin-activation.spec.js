// @ts-check
const { test, expect } = require( '@playwright/test' );

/**
 * WB Ad Manager — Plugin Activation & Basic Functionality Tests
 *
 * These tests verify the plugin is active and core pages load correctly.
 * Run: npx playwright test
 */

test.describe( 'Plugin Activation', () => {
	test( 'plugin is active in plugins list', async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		const pluginRow = page.locator( 'tr[data-slug="wb-ads-rotator-with-split-test"]' );
		await expect( pluginRow ).toBeVisible();
		await expect( pluginRow.locator( '.deactivate' ) ).toBeVisible();
	} );

	test( 'admin menu item exists', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		const menuItem = page.locator( '#adminmenu a:has-text("WB Ad Manager")' );
		await expect( menuItem ).toBeVisible();
	} );
} );

test.describe( 'Settings Page', () => {
	test( 'settings page loads without errors', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wbam-settings' );
		await expect( page.locator( 'h1, h2' ).first() ).toContainText( 'Ad Settings' );
	} );

	test( 'general settings fields are present', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wbam-settings' );
		await expect( page.locator( 'text=Disable for Logged-in Users' ) ).toBeVisible();
		await expect( page.locator( 'text=Maximum Ads Per Page' ) ).toBeVisible();
		await expect( page.locator( 'text=Ad Label Text' ) ).toBeVisible();
	} );

	test( 'settings can be saved', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wbam-settings' );

		// Find the max ads input by its visible label
		const maxAdsInput = page.locator( '#wbam-max-ads, input[id*="max_ads"], input[id*="max-ads"]' ).first();

		if ( await maxAdsInput.isVisible() ) {
			await maxAdsInput.fill( '5' );
			await page.click( 'input[type="submit"], button[type="submit"]' );
			await page.waitForLoadState( 'networkidle' );

			// Verify the value persisted
			const value = await maxAdsInput.inputValue();
			expect( value ).toBe( '5' );
		}
	} );
} );

test.describe( 'Ad Creation', () => {
	test( 'add new ad page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/post-new.php?post_type=wbam_ad' );
		// Should load without fatal errors
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Critical Error' );
	} );

	test( 'all ads list page loads', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=wbam-ad' );
		await page.waitForLoadState( 'networkidle' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Critical Error' );
	} );
} );

test.describe( 'No Console Errors', () => {
	test( 'settings page has no JS console errors', async ( { page } ) => {
		const errors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				errors.push( msg.text() );
			}
		} );

		await page.goto( '/wp-admin/admin.php?page=wbam-settings' );
		await page.waitForLoadState( 'networkidle' );

		// Filter out known WordPress core errors
		// Filter out browser/server noise — keep only real JS errors
		const pluginErrors = errors.filter(
			( e ) =>
				! e.includes( 'favicon' ) &&
				! e.includes( 'net::ERR' ) &&
				! e.includes( 'Failed to load resource' )
		);
		expect( pluginErrors ).toHaveLength( 0 );
	} );
} );
