<?php
/**
 * Plugin Installer Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPB_Plugin_Installer {
    
    /**
     * Install multiple plugins
     */
    public static function install_plugins($plugins, $activate = false) {
        // Load required WordPress files
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/misc.php');
        require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
        require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
        
        $results = array();
        
        // Disable maintenance mode for bulk operations
        add_filter('enable_maintenance_mode', '__return_false');
        
        foreach ($plugins as $plugin_slug) {
            $result = array(
                'slug' => $plugin_slug,
                'status' => 'error',
                'message' => '',
                'details' => array()
            );
            
            try {
                // Sanitize slug
                $plugin_slug = sanitize_key($plugin_slug);
                
                // Check if plugin already exists
                $installed_plugins = get_plugins();
                $plugin_file = self::get_plugin_file($plugin_slug, $installed_plugins);
                
                if ($plugin_file) {
                    $result['status'] = 'already_installed';
                    $result['message'] = __('Plugin already installed', 'wpbulkify');
                    $result['details']['plugin_file'] = $plugin_file;
                    
                    // Check if active
                    if (is_plugin_active($plugin_file)) {
                        $result['status'] = 'already_active';
                        $result['message'] = __('Plugin already installed and active', 'wpbulkify');
                    } elseif ($activate) {
                        // Activate the plugin
                        $activation = activate_plugin($plugin_file);
                        if (is_wp_error($activation)) {
                            $result['status'] = 'activation_failed';
                            $result['message'] = $activation->get_error_message();
                        } else {
                            $result['status'] = 'activated';
                            $result['message'] = __('Plugin activated successfully', 'wpbulkify');
                        }
                    }
                } else {
                    // Get plugin info from WordPress.org
                    $api = plugins_api('plugin_information', array(
                        'slug' => $plugin_slug,
                        'fields' => array(
                            'short_description' => true,
                            'sections' => false,
                            'requires' => true,
                            'rating' => true,
                            'ratings' => false,
                            'downloaded' => true,
                            'last_updated' => true,
                            'added' => false,
                            'tags' => false,
                            'compatibility' => false,
                            'homepage' => false,
                            'donate_link' => false,
                        )
                    ));
                    
                    if (is_wp_error($api)) {
                        $result['message'] = $api->get_error_message();
                        $result['details']['api_error'] = $api->get_error_code();
                    } else {
                        // Store plugin info
                        $result['details']['plugin_info'] = array(
                            'name' => $api->name,
                            'version' => $api->version,
                            'author' => $api->author,
                            'requires' => $api->requires,
                            'tested' => $api->tested,
                            'downloaded' => $api->downloaded,
                            'rating' => $api->rating,
                            'num_ratings' => $api->num_ratings
                        );
                        
                        // Install plugin
                        $skin = new WP_Ajax_Upgrader_Skin();
                        $upgrader = new Plugin_Upgrader($skin);
                        $install_result = $upgrader->install($api->download_link);
                        
                        if (is_wp_error($install_result)) {
                            $result['message'] = $install_result->get_error_message();
                            $result['details']['install_error'] = $install_result->get_error_code();
                        } elseif (!$install_result) {
                            $result['message'] = __('Installation failed', 'wpbulkify');
                            if (!empty($skin->errors)) {
                                $result['details']['skin_errors'] = $skin->errors;
                            }
                        } else {
                            $result['status'] = 'installed';
                            $result['message'] = __('Plugin installed successfully', 'wpbulkify');
                            
                            // Clear plugin cache
                            wp_clean_plugins_cache();
                            
                            // Get the installed plugin file
                            $installed_plugins = get_plugins();
                            $plugin_file = self::get_plugin_file($plugin_slug, $installed_plugins);
                            
                            if ($plugin_file) {
                                $result['details']['plugin_file'] = $plugin_file;
                                
                                // Activate if requested
                                if ($activate) {
                                    $activation = activate_plugin($plugin_file);
                                    if (is_wp_error($activation)) {
                                        $result['status'] = 'installed_activation_failed';
                                        
                                        $result['message'] = sprintf(
											/* translators: %s: Error message from plugin activation */
                                            __('Plugin installed but activation failed: %s', 'wpbulkify'),
                                            $activation->get_error_message()
                                        );
                                    } else {
                                        $result['status'] = 'installed_activated';
                                        $result['message'] = __('Plugin installed and activated successfully', 'wpbulkify');
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $result['message'] = $e->getMessage();
                $result['details']['exception'] = get_class($e);
            }
            
            $results[] = $result;
        }
        
        // Re-enable maintenance mode
        remove_filter('enable_maintenance_mode', '__return_false');
        
        return $results;
    }
    
    /**
     * Get plugin file from slug
     */
    private static function get_plugin_file($slug, $installed_plugins) {
        foreach ($installed_plugins as $file => $data) {
            // Check main plugin file
            if ($file === $slug . '.php') {
                return $file;
            }
            
            // Check plugin directory
            if (strpos($file, $slug . '/') === 0) {
                return $file;
            }
            
            // Check text domain
            if (!empty($data['TextDomain']) && $data['TextDomain'] === $slug) {
                return $file;
            }
        }
        
        return false;
    }
    
    /**
     * Get installation status for multiple plugins
     */
    public static function get_plugins_status($slugs) {
        $installed_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $results = array();
        
        foreach ($slugs as $slug) {
            $plugin_file = self::get_plugin_file($slug, $installed_plugins);
            
            if ($plugin_file) {
                $is_active = in_array($plugin_file, $active_plugins);
                $results[$slug] = array(
                    'installed' => true,
                    'active' => $is_active,
                    'plugin_file' => $plugin_file,
                    'plugin_data' => $installed_plugins[$plugin_file]
                );
            } else {
                $results[$slug] = array(
                    'installed' => false,
                    'active' => false
                );
            }
        }
        
        return $results;
    }
}