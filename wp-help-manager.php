<?php
/**
*
* @package   Help Manager
* @author    Charles Jaimet
* @link      https://github.com/cmjaimet
*
* @wordpress-plugin
* Plugin Name:       Help Manager
* Description:       Add help tabs to each page in your admin
* Version:           0.0.1
* Author:            Charles Jaimet
* Author URI:        https://github.com/cmjaimet
*
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class HelpManager {

	function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
	}

	function admin_init() {
		add_filter( 'contextual_help', array( $this, 'help_menus' ), 10, 3 );
	}

	function admin_enqueues() {
	}

	function help_menus( $contextual_help, $screen_id, $screen ) {
		$_screen = get_current_screen();
		$_slug = $screen->id;
		$_feed = false;
		// look up posts from local custom posts
		if ( false === $_feed ) {
			return $contextual_help; // can't get feed so abort
		}
		$_help = json_decode( $_feed );
		// set HTML elements that wp_kses will allow
		$_allowed = array(
			'a' => array(
				'href' => array(),
				'title' => array(),
				'target' => array(),
			),
			'img' => array(
				'src' => array(),
				'alt' => array(),
				'title' => array(),
				'width' => array(),
				'height' => array(),
				'style' => array(),
			),
			'p' => array(),
			'div' => array(),
			'span' => array(),
			'br' => array(),
			'em' => array(),
			'b' => array(),
			'i' => array(),
			'strong' => array(),
			'ol' => array(),
			'ul' => array(),
			'li' => array(),
			'blockquote' => array(),
		);
		foreach ( $_help as $_tab ) {
			// can't escape content because it will contain HTML but this is fed directly from post_content from another WP instance which is properly escaped
			$_content = $_tab->content;
			$_screen->add_help_tab( array(
				'id' => 'pn_' . esc_attr( $_tab->slug ),
				'title' => esc_html( $_tab->title ),
				'content' => wp_kses( $_content, $_allowed ),
			) );
			if ( isset( $_SERVER['SERVER_NAME'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
				$_url = 'http://' . sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			}
		}
		return $contextual_help;
	}

}

new HelpManager();
