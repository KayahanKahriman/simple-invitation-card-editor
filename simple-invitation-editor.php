<?php
/**
 * Plugin Name: Simple Invitation Editor
 * Description: A lightweight, DOM-based invitation editor for WooCommerce.
 * Version: 1.0.0
 * Author: Kayahan
 * Text Domain: simple-invitation-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Simple_Invitation_Editor {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->init_hooks();
	}

	private function define_constants() {
		define( 'SIE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		define( 'SIE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		define( 'SIE_VERSION', '1.0.0' );
	}

	private function includes() {
		require_once SIE_PLUGIN_DIR . 'includes/class-product-meta.php';
		require_once SIE_PLUGIN_DIR . 'includes/class-cart-handler.php';
	}

	private function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		
		// Initialize classes
		SIE_Product_Meta::get_instance();
		SIE_Cart_Handler::get_instance();
	}

	public function enqueue_scripts() {
		if ( ! is_product() ) {
			return;
		}

		global $post;
		$config = get_post_meta( $post->ID, '_invitation_json_config', true );

		if ( empty( $config ) ) {
			return;
		}

		wp_enqueue_style( 'sie-editor-css', SIE_PLUGIN_URL . 'assets/css/editor.css', array(), SIE_VERSION );
		wp_enqueue_script( 'sie-editor-js', SIE_PLUGIN_URL . 'assets/js/editor.js', array( 'jquery' ), SIE_VERSION, true );

		wp_localize_script( 'sie-editor-js', 'sie_config', array(
			'raw_config' => $config,
			'is_admin_mode' => current_user_can( 'manage_options' ) && isset( $_GET['mode'] ) && $_GET['mode'] === 'admin',
		) );
	}
}

Simple_Invitation_Editor::get_instance();
