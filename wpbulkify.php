<?php
/**
 * Plugin Name: WPBulkify
 * Plugin URI: https://wpbulkify.com/
 * Author URI: https://profiles.wordpress.org/wpbulkify/
 * Description: Enables bulk plugin installation via the WPBulkify browser extension.
 * Version: 1.0.0
 * Author: WPBulkify
 * License: GPL v2 or later
 * Text Domain: wpbulkify
 *  
 * WPBulkify is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * WPBulkify is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WPBulkify. If not, see <http://www.gnu.org/licenses/>.
 */

 // Prevent direct access
if ( ! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPBULKIFY_VERSION', '1.0.0');
define('WPBULKIFY_PLUGIN_DIR', plugin_dir_path(__FILE__) );
define('WPBULKIFY_PLUGIN_URL', plugin_dir_url(__FILE__) );

// Include required files
require_once WPBULKIFY_PLUGIN_DIR . 'includes/class-plugin-installer.php';
require_once WPBULKIFY_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once WPBULKIFY_PLUGIN_DIR . 'includes/class-ajax-handler.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    if ( class_exists('Wpbulkify_Rest_Api') ) {
        new Wpbulkify_Rest_Api();
    }

    if ( class_exists('Wpbulkify_Ajax_Handler') ) {
        new Wpbulkify_Ajax_Handler();
    }
});

// Add admin notice for successful activation
add_action('admin_notices', function() {
    if ( get_transient('wpbulkify_activated') ) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('WPBulkify is now active! Your browser extension can now perform bulk plugin installations.', 'wpbulkify'); ?></p>
        </div>
        <?php
        delete_transient('wpbulkify_activated');
    }
});

// Set transient on activation
register_activation_hook(__FILE__, function() {
    set_transient('wpbulkify_activated', true, 5);
});

// Expose helper object to frontend
add_action('admin_enqueue_scripts', function($hook) {
    // Only enqueue on admin pages
    wp_enqueue_script(
        'wpbulkify-admin',
        WPBULKIFY_PLUGIN_URL . 'assets/js/admin.js',
        [],
        WPBULKIFY_VERSION,
        true
    );

    wp_localize_script('wpbulkify-admin', 'wpbulkifyAdminData', [
        'version'   => esc_attr(WPBULKIFY_VERSION),
        'ajaxUrl'   => esc_url(admin_url('admin-ajax.php')),
        'restUrl'   => esc_url(rest_url('wpbulkify/v1')),
        'restNonce' => esc_attr(wp_create_nonce('wp_rest')),
        'nonce'     => esc_attr(wp_create_nonce('wpbulkify_ajax_nonce')),
    ]);
});