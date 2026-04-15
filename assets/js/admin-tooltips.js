/**
 * Field-level tooltip popovers (Phase G.4).
 *
 * Vanilla JS, no jQuery dependency. Delegated click handler
 * toggles the sibling popover, syncs aria-expanded, and closes
 * any open popover on outside click or Escape.
 *
 * Loads safely on pages with no tooltip icons — all selectors
 * are delegated so missing nodes are a no-op.
 */
( function () {
	'use strict';

	var ICON_SELECTOR = '.wbam-tip__icon';
	var POPOVER_SELECTOR = '.wbam-tip__popover';

	/**
	 * Close every open popover on the page and reset its trigger.
	 *
	 * @param {Element} [except] Optional icon whose popover should stay open.
	 */
	function closeAll( except ) {
		var icons = document.querySelectorAll( ICON_SELECTOR + '[aria-expanded="true"]' );
		for ( var i = 0; i < icons.length; i++ ) {
			if ( except && icons[ i ] === except ) {
				continue;
			}
			icons[ i ].setAttribute( 'aria-expanded', 'false' );
			var pop = icons[ i ].parentNode
				? icons[ i ].parentNode.querySelector( POPOVER_SELECTOR )
				: null;
			if ( pop ) {
				pop.setAttribute( 'hidden', '' );
			}
		}
	}

	/**
	 * Toggle a single icon's popover.
	 *
	 * @param {Element} icon The .wbam-tip__icon button.
	 */
	function toggle( icon ) {
		var parent = icon.parentNode;
		if ( ! parent ) {
			return;
		}
		var popover = parent.querySelector( POPOVER_SELECTOR );
		if ( ! popover ) {
			return;
		}

		var isOpen = 'true' === icon.getAttribute( 'aria-expanded' );

		// Close any other open popover first.
		closeAll( icon );

		if ( isOpen ) {
			icon.setAttribute( 'aria-expanded', 'false' );
			popover.setAttribute( 'hidden', '' );
		} else {
			icon.setAttribute( 'aria-expanded', 'true' );
			popover.removeAttribute( 'hidden' );
		}
	}

	// Delegated click handler. Using closest() lets the handler
	// catch clicks on the dashicon glyph inside the button too.
	document.addEventListener( 'click', function ( event ) {
		var icon = event.target && event.target.closest
			? event.target.closest( ICON_SELECTOR )
			: null;

		if ( icon ) {
			event.preventDefault();
			event.stopPropagation();
			toggle( icon );
			return;
		}

		// Outside click: ignore clicks inside an open popover so
		// users can select/copy the text, but close otherwise.
		var insidePopover = event.target && event.target.closest
			? event.target.closest( POPOVER_SELECTOR )
			: null;

		if ( ! insidePopover ) {
			closeAll();
		}
	} );

	// Escape key closes any open popover.
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key || 'Esc' === event.key || 27 === event.keyCode ) {
			closeAll();
		}
	} );
} )();
