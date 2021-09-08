<?php
/*
Plugin Name: PIE Plugin Manager
Plugin URI:  https://bitbucket.org/pieweb/pie-plugin-manager
Description: Allows the user to view and toggle plugins that are in a PIE theme
Version:     1.0
Author:      The team at PIE
Author URI:  pie.co.de
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pie-plugin-manager
*/
namespace pie_plugin_manager;

if (! defined('ABSPATH')) exit; // Exit if accessed directly.



if (!class_exists('PiePluginManager')) {
    class PiePluginManager {

        private $third_party_dependencies;
        private $plugin_paths;
		private $plugin_states;
        /**
         * Class constructor
         */
        public function __construct()
        {
            register_activation_hook(__FILE__, [$this, 'on_activate']);

            $theme_path = wp_get_theme()->get_stylesheet_directory();
            if (is_dir($theme_path . '/plugins')) {
                $this->plugin_states = $this->get_plugin_states($theme_path);
                add_action('admin_menu', [$this, 'add_plugin_page']);
                add_action('admin_init', [$this, 'page_init']);
                add_action('admin_enqueue_scripts', [$this, 'load_css']);
            }
        }

        function get_plugin_state($slug) {
			if (isset($this->plugin_active_states[$slug])) {
				return $this->plugin_active_states[$slug];
			}
			else return false;
        }

        function get_plugin_states($theme_path) {

			$this->plugin_paths = $this->get_plugin_paths($theme_path . '/plugins');
			$states = [];
			foreach ($this->plugin_paths as $slug => $path) {
				if (isset(get_option('pie_plugin_states')[$slug])) {
					$states[$slug] = get_option('pie_plugin_states')[$slug];
				} else {
					$states[$slug] = true;
				}
			}
			update_option('pie_plugin_states', $states);
			return $states;
        }

        /**
         * Get plugin paths
         *
         * @param String $path Provides the path to get the plugins from
         * @return Array An array of the plugins installed in the theme
         */
        private function get_plugin_paths($path) {
            $paths = [];
            $plugins = list_files($path, 1);
            foreach ($plugins as $plugin)
                $paths[basename($plugin)] = $plugin;
            return $paths;
        }

        /**
         * Add option page on Appearance admin menu
         */
        public function add_plugin_page()
        {
            // This page will be under "Appearance"
            add_theme_page(
                'Manage PIE Plugins',
                'PIE Plugins',
                'manage_options',
                'pie-plugin-management',
                [$this, 'create_plugin_management_page']
          );
        }

        /**
         * Options page callback
         */
        public function create_plugin_management_page()
        {
			$this->plugin_active_states = get_option('pie_plugin_states');
			if (isset($_POST['plugin-activate']) || isset($_POST['plugin-deactivate'])) {
				if (isset($_POST['theme_plugin_state_change_nonce'])) {
					if (!wp_verify_nonce($_POST['theme_plugin_state_change_nonce'], 'theme_plugin_state_change_nonce')) {
						show_message("There was an issue toggling the plugin state. Try again");
					}
				 	else {
						if (isset($_POST['plugin-activate'])) {
							$this->plugin_active_states[$_POST['plugin-activate']] = !$this->plugin_active_states[$_POST['plugin-active']];
							update_option('pie_plugin_states', $this->plugin_active_states);
						}
						if (isset($_POST['plugin-deactivate'])) {
							$this->plugin_active_states[$_POST['plugin-deactivate']] = !$this->plugin_active_states[$_POST['plugin-deactivate']];
							update_option('pie_plugin_states', $this->plugin_active_states);
						}
						wp_redirect($_SERVER['HTTP_REFERER']);
					}
				}
			}
            ?>

            <div class="wrap">
                <h1>Pie Plugin Management</h1>
				<form method="POST">
					<input type="hidden" name="theme_plugin_state_change_nonce" value='<?=wp_create_nonce('theme_plugin_state_change_nonce')?>' />
	                <?php
	                    // This prints out all hidden setting fields
	                    settings_fields('pie_plugins_option_group');
	                    do_settings_sections('pie-plugin-management');
	                ?>
                </form>
            </div>
            <?php
        }

        /**
         * Register and add settings
         */
        public function page_init()
        {
			register_setting(
                'pie_plugins_option_group', // Option group
                'pie_plugin_dependencies', // Option name
                [$this, 'sanitize_dependencies'] // Sanitize
			);

			register_setting(
				'pie_plugins_option_group', // Option group
                'pie_plugin_states', // Option name
                [$this, 'sanitize_active_states'] // Sanitize
            );

            add_settings_section(
                'theme_plugin_settings', // ID
                'Theme Plugins', // Title
                [$this, 'plugins_callback'], // Callback
                'pie-plugin-management' // Page
          );
        }

        /**
         * Sanitize each setting field as needed
         *
         * @param array $input Contains all settings fields as array keys
         */
        public function sanitize_states($input)
        {
            if(isset($his->plugin_states)) {
                return preg_split("/\r\n|\n|\r/", esc_attr($this->plugin_states));
            }
        }

        /**
         * Get the theme plugins path array and print out the values
         */
        public function plugins_callback() {
	  		include_once(plugin_dir_path(__FILE__).'/class-pie-plugin-list-table.php');
	  		$table = new PiePluginListTable($this->plugin_paths, $this->plugin_states);
	  		$table->prepare_items();
	  		$table->display();
		}

        /**
         * A method to be called on plugin activation.
         */
        public function on_activate() {
            add_option("pie_plugin_states", []);
        }

        function load_css() {
            wp_enqueue_style('pie_plugins_style', plugins_url('/css/style.css' , __FILE__));
        }
    }
    $plugin = new PiePluginManager();
}

// https://codex.wordpress.org/Creating_Options_Pages used for reference
// activate_plugin('path_to_plugin'); to activate a plugin
// deactivate_plugin('path_to_plugin'); to deactivate a plugin
//https://developer.wordpress.org/reference/classes/wp_list_table/
