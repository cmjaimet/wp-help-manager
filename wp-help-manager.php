<?php
/**
* Inline Help Manager
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelpManager {

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	function init() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
	}

	function admin_init() {
		add_action( 'wp_ajax_json_feedback_email', array( $this, 'json_feedback_email' ) );
		add_filter( 'contextual_help', array( $this, 'help_menus' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueues' ) );
	}

	function admin_enqueues() {
		wp_enqueue_style( 'help_manager_css', PM_NEWSROOM_URI . 'css/documentation.css' );
		wp_enqueue_script( 'help_manager_js', PM_NEWSROOM_URI . 'js/documentation.js' );
	}

	function help_menus( $contextual_help, $screen_id, $screen ) {
		global $pn_newsroom_newsroom;
		$_settings = $pn_newsroom_newsroom->get_newsroom_settings();
		if ( ! is_array( $_settings ) ) {
			return $contextual_help; // settings not configured so abort
		}
		$_feed_domain = trim( $_settings['feed_domain'] );
		if ( empty( $_feed_domain ) ) {
			return $contextual_help; // settings not configured so abort
		}
		$_feed_domain = 'http://' . $_feed_domain;
		$_screen = get_current_screen();
		$_slug = $screen->id;
		$_url = esc_url_raw( $_feed_domain . '?feed=helpfeed&cat=' . urlencode( $_slug ) );
		if ( function_exists( 'wpcom_vip_file_get_contents' ) ) {
			$_feed = @wpcom_vip_file_get_contents( $_url ); // @codingStandardsIgnoreLine - if feed fails we abort and return unaltered help text
		} else {
			$_feed = @file_get_contents( $_url ); // @codingStandardsIgnoreLine - if feed fails we abort and return unaltered help text
		}
		if ( false === $_feed ) {
			return $contextual_help; // can't get feed so abort
		}
		if ( false !== $_feed ) {
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
			}
			if ( isset( $_SERVER['SERVER_NAME'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
				$_url = 'http://' . sanitize_text_field( wp_unslash( $_SERVER['SERVER_NAME'] ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			}
			$_feedback = '';
			$_feedback .= '<p>Is something missing, unclear or just plain wrong? Let us know so we can fix it.</p><p id="pnx_feedback_msg"></p>';
			$_feedback .= '<textarea id="pnx_feedback_content"></textarea>';
			$_feedback .= '<input type="button" id="pnx_feedback_btn" value="Send" class="button-primary" />';
			$_feedback .= wp_nonce_field( PM_NEWSROOM_URI, 'pnx_feedback_nonce', true, false );
			$_feedback .= '<input type="hidden" id="pnx_feedback_url" value="' . esc_url( $_url ) . '" />';
			$_feedback .= '<input type="hidden" id="pnx_feedback_slug" value="' . urlencode( $_slug ) . '" />';
			$_screen->add_help_tab( array(
				'id' => 'pnx_feedback',
				'title' => 'Feedback',
				'content' => $_feedback,
			) );
		}
		return $contextual_help;
	}

	function json_feedback_email() {
		$_recipients = array( 'cmjaimet@gmail.com' ); // *very* temporarily hard code this
		if ( ! isset( $_POST['nonce'] ) ) {
			return false;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'], PM_NEWSROOM_URI ) ) ) ) {
			return false;
		}
		$current_user = wp_get_current_user();
		$_url = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( $_POST['url'] ) ) : '';
		$_slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
		$_content = isset( $_POST['content'] ) ? sanitize_text_field( wp_unslash( $_POST['content'] ) ) : '';
		// the form escapes quotes '" so parse the escape characters out
		$_content = preg_replace( '/\\\([\"\'])/', '$1', $_content );
		if ( '' != $_content ) {
			$_content .= "\n\nFrom:\n" . esc_html( $current_user->display_name ) . ' (' . esc_html( $current_user->user_email ) . ')';
			$_content .= "\n\nSee the problem here:\n" . esc_url_raw( $_url );
			$_content .= "\n\nSolve the problem here:\n" . esc_url_raw( $this->help_uri . 'wp-admin/edit.php?category_name=' . $_slug );
			wp_mail( $_recipients, 'Inline Help Request', $_content ); // add email thru settings next
			echo '1';
		} else {
			echo '0';
		}
		die();
	}
}

new HelpManager();
