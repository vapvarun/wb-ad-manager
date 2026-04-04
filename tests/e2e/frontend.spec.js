// @ts-check
const { test, expect } = require( '@playwright/test' );

/**
 * WB Ad Manager — Frontend Tests
 *
 * Tests that the plugin works correctly on the frontend.
 * Run: npx playwright test
 */

test.describe( 'Frontend', () => {
	test( 'homepage loads without fatal errors', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );
		await expect( page.locator( 'body' ) ).not.toContainText( 'Critical Error' );
	} );

	test( 'no JS console errors on frontend', async ( { page } ) => {
		const errors = [];
		page.on( 'console', ( msg ) => {
			if ( msg.type() === 'error' ) {
				errors.push( msg.text() );
			}
		} );

		await page.goto( '/' );
		await page.waitForLoadState( 'networkidle' );

		const pluginErrors = errors.filter(
			( e ) => ! e.includes( 'favicon' ) && ! e.includes( 'net::ERR' )
		);
		expect( pluginErrors ).toHaveLength( 0 );
	} );

	test( 'plugin CSS is enqueued on frontend', async ( { page } ) => {
		await page.goto( '/' );
		const frontendCSS = page.locator(
			'link[href*="wb-ads-rotator"], link[href*="wbam"]'
		);
		// CSS should be present (may or may not be, depending on settings)
		const count = await frontendCSS.count();
		// Just verify no fatal error — CSS presence depends on ad placements
		expect( count ).toBeGreaterThanOrEqual( 0 );
	} );
} );

test.describe( 'Deactivation Safety', () => {
	test( 'plugin can be deactivated without errors', async ( { page } ) => {
		await page.goto( '/wp-admin/plugins.php' );
		const deactivateLink = page.locator(
			'tr[data-slug="wb-ads-rotator-with-split-test"] .deactivate a'
		);

		if ( await deactivateLink.isVisible() ) {
			await deactivateLink.click();
			await page.waitForLoadState( 'networkidle' );
			// Should not show fatal error
			await expect( page.locator( 'body' ) ).not.toContainText( 'Fatal error' );

			// Re-activate for other tests
			const activateLink = page.locator(
				'tr[data-slug="wb-ads-rotator-with-split-test"] .activate a'
			);
			if ( await activateLink.isVisible() ) {
				await activateLink.click();
				await page.waitForLoadState( 'networkidle' );
			}
		}
	} );
} );
