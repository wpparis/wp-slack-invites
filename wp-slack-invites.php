<?php
/*
Plugin Name: WP Slack Invites
Plugin URI: https://wpparis.fr/
Description: Generates a slack invite to your team for each created users.
Version: 1.0.0
Requires at least: 4.7
Tested up to: 4.7
License: GNU/GPL 2
Author: l'association WP Paris
Author URI: https://wpparis.fr/
*/

/**
 * Credits: Julio Potier
 *
 * This plugin is an adaptation of Julio Potier's Lazy Invitation plugin.
 * @see  https://fr.wordpress.org/plugins/lazy-invitation/
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Get the Team's Slack invites data.
 *
 * @since  1.0.0
 *
 * @return object The Team's Slack invites data.
 */
function wpparis_get_slack_invites_data() {
	$json = trailingslashit( plugin_dir_path( __FILE__ ) ) . 'team.json';

	// Default data.
	$slack_invites_data = new stdClass;
	$slack_invites_data->team  = '';
	$slack_invites_data->token = '';

	// Load the Team invites data.
	if ( file_exists( $json ) ) {
		/**
		 * Customize the team.json file with your own team's data use the part of your slack's team url
		 *
		 * team: the name of your team which is used as a {prefix} in your slack url (eg: https://{prefix}.slack.com)
		 * token: the value of your invites token, to get it, go to https://{prefix}.slack.com/admin/invites then
		 *        use the JavaScript console of your browser to log into it boot_data.api_token
		 */
		$slack_invites_data = json_decode( file_get_contents( $json ) );
	}

	return $slack_invites_data;
}

/**
 * Send an invite to the Slack channel to the new user.
 *
 * @since  1.0.0
 *
 * @param  integer $user_id The ID of the created user.
 */
function wpparis_send_slack_invite( $user_id = 0 ) {
	if ( ! $user_id ) {
		return;
	}

	$slack_data = wpparis_get_slack_invites_data();

	if ( ! $slack_data->team || ! $slack_data->token ) {
		return;
	}

	$user = get_user_by( 'id', $user_id );

	if ( empty( $user->user_email ) ) {
		return;
	}

	$team_url = sprintf( 'https://%s.slack.com', esc_html( $slack_data->team ) );
	$response = wp_remote_post(
		$team_url . '/api/users.admin.invite?t=1',
		array( 'body' => array(
			'email'      => $user->user_email,
			'channels'   => '',
			'first_name' => $user->first_name,
			'token'      => $slack_data->token,
			'set_active' => 'true',
			'_attempts' => '1',
		) )
	);

	$error = '';
	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$result = json_decode( wp_remote_retrieve_body( $response ) );

		if ( isset( $result->error ) ) {
			$error = esc_html( $result->error );
		}

	} else {
		$error = $response->get_error_message();
	}

	if ( $error ) {
		error_log( $error );
	}
}
add_action( 'user_register', 'wpparis_send_slack_invite', 10, 1 );
