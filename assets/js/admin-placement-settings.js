/**
 * Placements settings matrix.
 *
 * Two behaviours: an advertiser gate can never outlive its site gate, and
 * closing a slot that carries live creatives asks first. Both are
 * conveniences — sanitize_settings() enforces the intersection server-side.
 */
( function () {
	'use strict';

	var table = document.querySelector( '.wbam-placement-matrix' );
	if ( ! table ) {
		return;
	}

	table.addEventListener( 'change', function ( event ) {
		var box = event.target;
		if ( ! box.classList.contains( 'wbam-gate-site' ) ) {
			return;
		}

		var id = box.getAttribute( 'data-placement' );
		var count = parseInt( box.getAttribute( 'data-count' ), 10 ) || 0;
		var advertiser = table.querySelector(
			'.wbam-gate-advertiser[data-placement="' + id + '"]'
		);

		if ( ! box.checked && count > 0 ) {
			var message = window.wbamPlacementSettings.confirmDisable.replace( '%d', count );
			if ( ! window.confirm( message ) ) {
				box.checked = true;
				return;
			}
		}

		if ( advertiser ) {
			advertiser.disabled = ! box.checked;
			if ( ! box.checked ) {
				advertiser.checked = false;
			}
		}
	} );
}() );
