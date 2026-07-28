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

		function applyGate() {
			if ( advertiser ) {
				advertiser.disabled = ! box.checked;
				if ( ! box.checked ) {
					advertiser.checked = false;
				}
			}
		}

		// window.confirm() is synchronous — its return value decided
		// in-line whether to revert the checkbox. wbamToast.confirm() is
		// callback-based, so the checkbox is left in its already-toggled
		// (unchecked) state while the dialog is open, and the two possible
		// outcomes are handled explicitly: confirmed applies the gate to
		// the advertiser checkbox exactly as before; cancelled restores
		// the site checkbox to checked and leaves the advertiser checkbox
		// untouched, matching the original revert-on-cancel behaviour.
		if ( ! box.checked && count > 0 ) {
			var message = window.wbamPlacementSettings.confirmDisable.replace( '%d', count );
			window.wbamToast.confirm( message, function () {
				applyGate();
			}, function () {
				box.checked = true;
			} );
			return;
		}

		applyGate();
	} );
}() );
