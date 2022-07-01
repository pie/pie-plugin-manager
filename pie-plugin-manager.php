<?php
/**
 * @package pie-plugin-manager
 * Plugin Name: PIE Plugin Manager
 * Plugin URI:  https://github.com/pie/pie-plugin-manager
 * Description: Allows the user to view and toggle plugins that are in a PIE theme
 * Version:     1.0.0
 * Author:      The team at PIE
 * Author URI:  https://pie.co.de
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pie-plugin-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// Update Checker.
require 'plugin-update-checker/plugin-update-checker.php';
$update_checker = Puc_v4_Factory::buildUpdateChecker(
	'http://pie.co.de/wp-content/uploads/plugins-themes/plugins/pie-plugin-manager/release-data.json',
	__FILE__,
	'pie-plugin-manager'
);

if ( ! class_exists( 'PiePluginManager' ) ) {
	/**
	 * A container class for the plugin
	 *
	 * @var $plugin_dependencies Holds a list of the plugin dependencies.
	 * @var $plugin_paths Holds a list of the plugin paths.
	 * @var $plugin_states Holds a list of the plug states.
	 */
	class PiePluginManager {
		private $plugin_dependencies;
		private $plugin_paths;
		private $plugin_states;

		/**
		 * Class constructor
		 */
		public function __construct() {
			register_activation_hook( __FILE__, array( $this, 'on_activate' ) );

			$theme_path = wp_get_theme()->get_stylesheet_directory();
			if ( is_dir( $theme_path . '/plugins' ) ) {
				$this->plugin_states = $this->get_plugin_states( $theme_path );
				add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
				add_action( 'admin_init', array( $this, 'page_init' ) );
				add_action( 'admin_enqueue_scripts', array( $this, 'load_css' ) );
			}
		}

		/**
		 * Add option page on Appearance admin menu
		 */
		public function add_plugin_page() {
			// This page will be under "Appearance"
			add_theme_page(
				'Manage PIE Plugins',
				'PIE Plugins',
				'manage_options',
				'pie-plugin-management',
				array( $this, 'create_plugin_management_page' )
			);
		}

		/**
		 * Get dependency data
		 *
		 * @return Array An array mapping dependency path => name
		 */
		public function get_plugin_dependencies() {
			$path = wp_get_theme()->get_stylesheet_directory() . '/plugins/dependencies.json';
			if ( ! file_exists( $path ) ) {
				return array();
			}
			return json_decode( file_get_contents( $path ), true );
		}

		/**
		 * Get plugin paths
		 *
		 * @param String $path Provides the path to get the plugins from.
		 * @return Array An array of the paths of plugins installed in the theme.
		 */
		private function get_plugin_paths( $path ) {
			$paths   = array();
			$plugins = list_files( $path, 1 );
			foreach ( $plugins as $plugin ) {
				if ( is_dir( $plugin ) ) {
					$paths[ basename( $plugin ) ] = $plugin;
				}
			}
			return $paths;
		}

		/**
		 * Get the state of a single plugin
		 *
		 * @param String $slug contains the related slug of the theme based plugin.
		 * @return Boolean A state active flag (true=active, false=inactive)
		 */
		private function get_plugin_state( $slug ) {
			if ( isset( $this->plugin_active_states[ $slug ] ) ) {
				return $this->plugin_active_states[ $slug ];
			} else {
				return false;
			}
		}

		/**
		 * Get the state of all plugins from a path
		 *
		 * @param String $slug contains the related slug of the theme based plugin.
		 * @return Array An array mapping a plugin slug => state (recieved from the options table)
		 */
		private function get_plugin_states( $path ) {

			$this->plugin_paths = $this->get_plugin_paths( $path );
			$states             = array();
			foreach ( $this->plugin_paths as $slug => $path ) {
				if ( isset( get_option( 'pie_plugin_states' )[ $slug ] ) ) {
					$states[ $slug ] = get_option( 'pie_plugin_states' )[ $slug ];
				} else {
					$states[ $slug ] = true;
				}
			}
			update_option( 'pie_plugin_states', $states );
			return $states;
		}

		/**
		 * Options page callback
		 */
		public function create_plugin_management_page() {
			$this->plugin_active_states = get_option( 'pie_plugin_states' );
			// If the either of the plugin state change values are POSTed, then perform the function.
			if ( isset( $_POST['plugin-activate'] ) || isset( $_POST['plugin-deactivate'] ) ) {
				$this->do_plugin_state_toggle();
			} ?>
			<div class="wrap">
				<h1>Pie Plugin Management</h1>
				<form method="POST">
					<input type="hidden" name="theme_plugin_state_change_nonce" value='<?php echo wp_create_nonce( 'theme_plugin_state_change_nonce' ); ?>' />
					<?php
						// This prints out all hidden setting fields
						settings_fields( 'theme_plugins_option_group' );
						do_settings_sections( 'pie-plugin-management' );
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Try and do a plugin state toggle.
		 */
		private function do_plugin_state_toggle() {
			// Check if the correct nonce is set.
			if ( isset( $_POST['theme_plugin_state_change_nonce'] ) ) {
				// If the nonce value is incorrect, let the user know
				if ( ! wp_verify_nonce( $_POST['theme_plugin_state_change_nonce'], 'theme_plugin_state_change_nonce' ) ) {
					show_message( 'There was an issue toggling the plugin state. Try again' );
					return;
				}
				if ( isset( $_POST['plugin-activate'] ) ) {
					// If the a 'plugin-activate' value is POSTed, then activate the plugin then call the plugin activation hook
					$this->plugin_active_states[ $_POST['plugin-activate'] ] = ! $this->plugin_active_states[ $_POST['plugin-active'] ];
					do_action( 'activate_' . $_POST['plugin-active'] );
				} elseif ( isset( $_POST['plugin-deactivate'] ) ) {
					// If the a 'plugin-deactivate' value is POSTed, then deactivate the plugin, then call the plugin deactivation hook
					$this->plugin_active_states[ $_POST['plugin-deactivate'] ] = ! $this->plugin_active_states[ $_POST['plugin-deactivate'] ];
					do_action( 'deactivate_' . $_POST['plugin-active'] );
				}
				// Update the correct option field and reload the page
				update_option( 'pie_plugin_states', $this->plugin_active_states );
				wp_redirect( $_SERVER['HTTP_REFERER'] );
			}
		}

		/**
		 * Register and add settings
		 */
		public function page_init() {
			register_setting(
				'theme_plugins_option_group', // Option group
				'theme_plugin_dependencies', // Option name
				array( $this, 'sanitize_dependencies' ) // Sanitize
			);

			register_setting(
				'theme_plugins_option_group', // Option group
				'theme_plugin_states', // Option name
				array( $this, 'sanitize_active_states' ) // Sanitize
			);

			add_settings_section(
				'theme_plugins_option_group', // ID
				'Theme Plugin Dependencies', // Title
				array( $this, 'plugin_dependencies_callback' ), // Callback
				'pie-plugin-management' // Page
			);

			add_settings_section(
				'theme_plugin_settings', // ID
				'Theme Plugins', // Title
				array( $this, 'plugins_callback' ), // Callback
				'pie-plugin-management' // Page
			);
		}

		/**
		 * Sanitize each setting field as needed
		 *
		 * @param array $input Contains all settings fields as array keys
		 */
		public function sanitize_states( $input ) {
			if ( isset( $this->plugin_states ) ) {
				return preg_split( "/\r\n|\n|\r/", esc_attr( $this->plugin_states ) );
			}
		}

		/**
		 * Outputs a structured & formatted list of the dependencies
		 */
		public function plugin_dependencies_callback() {
			if ( count( $this->plugin_dependencies ) > 0 ) {
				foreach ( $this->plugin_dependencies as $plugin => $dependency_types ) {
					$plugin_path = $this->plugin_paths[ $plugin ] . '/' . $plugin . '.php';
					$plugin_data = get_plugin_data( $plugin_path );
					echo '<p class="plugin_name">' . $plugin_data['Name'] . ' (' . ( $this->plugin_states[ $plugin ] ? 'active' : 'inactive' ) . ')</p><div>';
					foreach ( $dependency_types as $type => $dependencies ) {
						echo '<p class="dependency_type">' . ucwords( $type ) . '</p><div>';
						if ( count( $dependencies ) > 0 ) {
							foreach ( $dependencies as $dependency_rel_path => $dependency_name ) {
								$dependency_active      = is_plugin_active( $dependency_rel_path );
								$activate_state_str     = array( 'inactive', 'active' )[ $dependency_active ];
								$dependency_search_path = '/wp-admin/plugins.php?s=' . str_replace( '.php', '', end( explode( '/', $dependency_rel_path ) ) ) . '&plugin_status=all';
								echo '<a class="dependency_name ' . $activate_state_str . '" href="' . $dependency_search_path . '">' . $dependency_name . '</a>';
							}
						} else {
							echo '<p class="dependency_name">No ' . ucfirst( $type ) . ' Plugin Dependencies Found</p>';
						}
						echo '</div>';
					}
					echo '</div>';
				}
			} else {
				echo '<p class="plugin_name">No Theme Plugin Dependencies Found</p>';
			}
		}

		/**
		 * Output a WP_List_Table containing data related to the plugins installed in the theme.
		 */
		public function plugins_callback() {
			include_once plugin_dir_path( __FILE__ ) . 'class-pie-plugin-list-table.php';
		}

		/**
		 * A method to be called on plugin activation.
		 */
		public function on_activate() {
			add_option( 'pie_plugin_states', array() );
		}

		function load_css() {
			wp_enqueue_style( 'pie_plugins_style', plugins_url( '/style.css', __FILE__ ) );
		}
	}
	$plugin = new PiePluginManager();
}

// https://codex.wordpress.org/Creating_Options_Pages used for reference
// activate_plugin('path_to_plugin'); to activate a plugin
// deactivate_plugin('path_to_plugin'); to deactivate a plugin
// https://developer.wordpress.org/reference/classes/wp_list_table/
