<?php
/**
 * AJAX Handler for when REST API is disabled
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wpbulkify_Ajax_Handler {
    
    public function __construct() {
        add_action('wp_ajax_wpb_install_plugins', array($this, 'ajax_install_plugins'));
        add_action('wp_ajax_wpb_get_status', array($this, 'ajax_get_status'));
    }

    /**
     * AJAX install handler
     */
    public function ajax_install_plugins() {
        // Check if required POST data exists
        if ( ! isset( $_POST['nonce'] ) || ! isset( $_POST['plugins'] ) ) {
            wp_send_json_error('Missing required data');
        }

        // Check nonce
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpb_ajax_nonce' ) ) {
            wp_send_json_error('Invalid security token');
        }

        // Check permissions
        if ( ! current_user_can('install_plugins') ) {
            wp_send_json_error('Insufficient permissions');
        }

        $plugins = json_decode( sanitize_text_field( wp_unslash( $_POST['plugins'] ) ), true );
        $activate = isset($_POST['activate']) && $_POST['activate'] === '1';

        if (!is_array($plugins)) {
            wp_send_json_error('Invalid plugin data');
        }

        $results = Wpbulkify_Plugin_Installer::install_plugins($plugins, $activate);

        wp_send_json_success(array(
            'results' => $results,
            'timestamp' => current_time('mysql')
        ));
    }

    /**
     * AJAX status handler
     */
    public function ajax_get_status() {
        if ( ! current_user_can('install_plugins') ) {
            wp_send_json_error('Insufficient permissions');
        }

        wp_send_json_success( array (
            'version' => WPBULKIFY_VERSION,
            'ajax_enabled' => true,
            'nonce' => wp_create_nonce('wpbulkify_ajax_nonce')
        ));
    }
}