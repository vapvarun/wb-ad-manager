<?php
/**
 * Single-space count dropdown walker.
 *
 * @package WBAM\Admin
 */

namespace WBAM\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dropdown walker that puts a single space before the term count.
 *
 * Core's Walker_CategoryDropdown hardcodes `&nbsp;&nbsp;(count)`, which
 * reads as a double space on every option of the Ad Tags filter.
 * Identical to core's start_el otherwise.
 *
 * @since 3.1.0
 */
class Single_Space_Count_Dropdown_Walker extends \Walker_CategoryDropdown {

	/**
	 * Start element output.
	 *
	 * @param string   $output            Used to append additional content (passed by reference).
	 * @param \WP_Term $data_object       Term object.
	 * @param int      $depth             Depth of term in reference to parents.
	 * @param array<string, mixed> $args         Arguments.
	 * @param int      $current_object_id Optional. ID of the current term.
	 * @return void
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ): void {
		$pad      = str_repeat( '&nbsp;', $depth * 3 );
		$cat_name = apply_filters( 'list_cats', $data_object->name, $data_object );

		$value_field = isset( $args['value_field'] ) && isset( $data_object->{$args['value_field']} ) ? $args['value_field'] : 'term_id';

		$output .= "\t<option class=\"level-$depth\" value=\"" . esc_attr( $data_object->{$value_field} ) . '"';
		if ( (string) ( $args['selected'] ?? '' ) === (string) $data_object->{$value_field} ) {
			$output .= ' selected="selected"';
		}
		$output .= '>';
		$output .= $pad . esc_html( $cat_name );
		if ( ! empty( $args['show_count'] ) ) {
			$output .= ' (' . esc_html( number_format_i18n( $data_object->count ) ) . ')';
		}
		$output .= "</option>\n";
	}
}
