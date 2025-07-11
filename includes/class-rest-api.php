<?php
/**
 * REST API Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wpbulkify_Rest_Api {
    
    public function __construct() {
        add_action('rest_api_init', array( $this, 'register_routes') );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'wpbulkify/v1';
        
        // Install endpoint
        register_rest_route($namespace, '/install', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array( $this, 'install_plugins'),
            'permission_callback' => array( $this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'string'
                    )
                ),
                'activate' => array(
                    'type' => 'boolean',
                    'default' => false
                )
            )
        ));
        
        // Status endpoint
        register_rest_route($namespace, '/status', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'status_endpoint'),
            'permission_callback' => '__return_true'
        ));

        // Plugin status endpoint
        register_rest_route($namespace, '/plugins-status', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'get_plugins_status'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array'
                )
            )
        ));
    
        // Installed plugins endpoint
        register_rest_route($namespace, '/installed-plugins', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_installed_plugins'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        // Deactivate plugins only endpoint
        register_rest_route($namespace, '/deactivate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'deactivate_plugins'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'string'
                    )
                )
            )
        ));

        // Delete plugins endpoint
        register_rest_route($namespace, '/delete', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'delete_plugins'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'plugins' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'string'
                    )
                )
            )
        ));
   
    }

    /**
     * Check permissions
     */
    public function check_permissions($request) {
        // Check if user has the required capability
        if (!current_user_can('install_plugins')) {
            return false;
        }
        
        // For POST requests, verify nonce using WordPress REST API method
        if ($request->get_method() === 'POST') {
            $nonce = $request->get_header('X-WP-Nonce');
            
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Install plugins endpoint
     */
    public function install_plugins($request) {
        $plugins = $request->get_param('plugins');
        $activate = $request->get_param('activate');
        
        $results = Wpbulkify_Plugin_Installer::install_plugins($plugins, $activate);

        // Process results for better response format
        $installed = array();
        $errors = array();
        $summary = array(
            'total' => count($plugins),
            'installed' => 0,
            'activated' => 0,
            'already_installed' => 0,
            'already_active' => 0,
            'errors' => 0
        );
        
        foreach ($results as $result) {
            switch ($result['status']) {
                case 'installed':
                case 'installed_activated':
                    $installed[] = $result['slug'];
                    $summary['installed']++;
                    if ($result['status'] === 'installed_activated') {
                        $summary['activated']++;
                    }
                    break;
                case 'activated':
                    $summary['activated']++;
                    break;
                case 'already_installed':
                    $summary['already_installed']++;
                    break;
                case 'already_active':
                    $summary['already_active']++;
                    break;
                default:
                    $errors[] = array(
                        'slug' => $result['slug'],
                        'message' => $result['message']
                    );
                    $summary['errors']++;
                    break;
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'installed' => $installed,
            'errors' => $errors,
            'summary' => $summary,
            'results' => $results,
            'timestamp' => current_time('mysql')
        ), 200);
    }
    
    /**
     * Public test endpoint (no authentication required)
     */
    public function status_endpoint() {
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'WPBulkify helper plugin is active',
            'version' => WPBULKIFY_VERSION,
            'timestamp' => current_time('mysql')
        ), 200);
    }
    
    /**
     * Get plugins status
     */
    public function get_plugins_status($request) {
        $slugs = $request->get_param('plugins');
        $results = Wpbulkify_Plugin_Installer::get_plugins_status($slugs);
        
        return new WP_REST_Response(array(
            'success' => true,
            'plugins' => $results
        ), 200);
    }
    
    /**
     * Get installed plugins
     */
    public function get_installed_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $plugin_list = array();
        
        foreach ($installed_plugins as $plugin_file => $plugin_data) {
            // Extract slug from plugin file path
            $slug = dirname($plugin_file);
            if ($slug === '.') {
                $slug = basename($plugin_file, '.php');
            }
            
            $plugin_list[] = array(
                'slug' => $slug,
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'description' => $plugin_data['Description'],
                'url' => $plugin_data['PluginURI'],
                'isActive' => in_array($plugin_file, $active_plugins),
                'isDeactivated' => !in_array($plugin_file, $active_plugins)
            );
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'plugins' => $plugin_list
        ), 200);
    }
    
    /**
     * Deactivate plugins only (no delete)
     */
    public function deactivate_plugins($request) {
        $plugins = $request->get_param('plugins');

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $results = array();
        $summary = array(
            'total' => count($plugins),
            'deactivated' => 0,
            'errors' => 0
        );

        foreach ($plugins as $plugin_slug) {
            $result = array(
                'slug' => $plugin_slug,
                'status' => 'unknown',
                'message' => ''
            );

            try {
                // Find the plugin file
                $plugin_file = null;
                $installed_plugins = get_plugins();

                foreach ($installed_plugins as $file => $plugin_data) {
                    $slug = dirname($file);
                    if ($slug === '.') {
                        $slug = basename($file, '.php');
                    }

                    if ($slug === $plugin_slug) {
                        $plugin_file = $file;
                        break;
                    }
                }

                if (!$plugin_file) {
                    $result['status'] = 'not_found';
                    $result['message'] = 'Plugin not found';
                    $summary['errors']++;
                    $results[] = $result;
                    continue;
                }

                // Check if plugin is active
                $is_active = is_plugin_active($plugin_file);

                // Deactivate if active
                if ($is_active) {
                    deactivate_plugins($plugin_file);
                    $summary['deactivated']++;
                    $result['status'] = 'deactivated';
                    $result['message'] = 'Deactivated.';
                } else {
                    $result['status'] = 'already_deactivated';
                    $result['message'] = 'Plugin already deactivated.';
                }
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['message'] = 'Error: ' . $e->getMessage();
                $summary['errors']++;
            }

            $results[] = $result;
        }

        return new WP_REST_Response(array(
            'success' => true,
            'results' => $results,
            'summary' => $summary
        ), 200);
    }

    /**
     * Delete plugins endpoint
     */
    public function delete_plugins($request) {
        $plugins = $request->get_param('plugins');

        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('delete_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $results = array();
        $summary = array(
            'total' => count($plugins),
            'deleted' => 0,
            'errors' => 0
        );

        foreach ($plugins as $plugin_slug) {
            $result = array(
                'slug' => $plugin_slug,
                'status' => 'unknown',
                'message' => ''
            );

            try {
                // Find the plugin file
                $plugin_file = null;
                $installed_plugins = get_plugins();

                foreach ($installed_plugins as $file => $plugin_data) {
                    $slug = dirname($file);
                    if ($slug === '.') {
                        $slug = basename($file, '.php');
                    }
                    if ($slug === $plugin_slug) {
                        $plugin_file = $file;
                        break;
                    }
                }

                if (!$plugin_file) {
                    $result['status'] = 'not_found';
                    $result['message'] = 'Plugin not found';
                    $summary['errors']++;
                    $results[] = $result;
                    continue;
                }

                // Deactivate if active
                if (is_plugin_active($plugin_file)) {
                    deactivate_plugins($plugin_file);
                }

                if ( ! function_exists( 'request_filesystem_credentials' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                    require_once ABSPATH . 'wp-admin/includes/misc.php';
                    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
                }

                // Delete the plugin
                $delete_result = delete_plugins(array($plugin_file));
                if (is_wp_error($delete_result)) {
                    $result['status'] = 'error';
                    $result['message'] = $delete_result->get_error_message();
                    $summary['errors']++;
                } else {
                    $result['status'] = 'deleted';
                    $result['message'] = 'Deleted.';
                    $summary['deleted']++;
                }
            } catch (Exception $e) {
                $result['status'] = 'error';
                $result['message'] = 'Error: ' . $e->getMessage();
                $summary['errors']++;
            }

            $results[] = $result;
        }

        return new WP_REST_Response(array(
            'success' => true,
            'results' => $results,
            'summary' => $summary
        ), 200);
    }
}