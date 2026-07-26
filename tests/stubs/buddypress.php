<?php
/**
 * BuddyPress function stubs for static analysis only.
 *
 * BuddyPress is an OPTIONAL runtime dependency: the BuddyPress placement and
 * directory code only runs when BuddyPress is active, and every call site is
 * guarded with function_exists() / bp_is_active(). PHPStan has no way to see
 * those functions otherwise, so it reported them as "not found" — noise that
 * hid real findings.
 *
 * Never loaded at runtime; referenced from phpstan.neon (scanFiles) only.
 *
 * @package WB_Ads_Rotator_With_Split_Test
 */

// phpcs:disable

if ( ! function_exists( 'bp_is_my_profile' ) ) {
	function bp_is_my_profile(): bool {}
	function bp_is_user(): bool {}
	function bp_is_group(): bool {}
	function bp_is_activity_component(): bool {}
	function bp_displayed_user_id(): int {}
	function bp_displayed_user_domain(): string {}
	function bp_loggedin_user_domain(): string {}
	function bp_get_members_directory_permalink(): string {}
	function bp_core_current_time( bool $gmt = true, string $type = 'mysql' ): string {}
	function bp_core_get_userlink( int $user_id, bool $no_anchor = false, bool $just_link = false ): string {}
	function bp_get_member_type( int $user_id, bool $single = true ) {}
	function bp_core_load_template( $templates ): void {}
	function bp_core_new_nav_item( array $args ) {}
	function bp_core_new_subnav_item( array $args ) {}
	function bp_activity_add( array $args ) {}
	function bp_notifications_add_notification( array $args ) {}
	function bp_xprofile_get_group( int $group_id ) {}
	function xprofile_get_field( $field, $user_id = null, bool $get_data = true ) {}
	function xprofile_get_field_data( $field, int $user_id = 0, string $multi_format = 'array' ) {}
}
