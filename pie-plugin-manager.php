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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly.

if ( !class_exists( 'PiePluginManager' ) ) {
    class PiePluginManager {

        private $options;
        private $plugin_paths;
        /**
         * Class constructor
         */
        public function __construct( )
        {
            register_activation_hook( __FILE__, [ $this, 'on_activate' ] );

            $active_theme = wp_get_theme( );
            $theme_path = $active_theme->get_stylesheet_directory(  );
            if ( is_dir( $theme_path . '/plugins' ) ) {
                $this->plugin_paths = $this->get_plugin_paths( $theme_path . '/plugins' );
                add_action( 'admin_menu', [ $this, 'add_plugin_page' ] );
                add_action( 'admin_init', [ $this, 'page_init' ] );
            }
        }

        /**
         * A method to be called on plugin activation.
         */
        public function on_activate( ) {
            add_option( 'pie_plugin_options' );
        }

        /**
         * Get plugin paths
         *
         * @param String $path Provides the path to get the plugins from
         * @return Array An array of the plugins installed in the theme
         */
        private function get_plugin_paths( $path ) {
            $paths = [  ];
            $plugins = list_files( $path, 1 );
            foreach ( $plugins as $plugin )
                $paths[ basename( $plugin ) ] = $plugin;
            return $paths;
        }

        /**
         * Add option page on Appearance admin menu
         */
        public function add_plugin_page( )
        {
            // This page will be under "Appearance"
            add_theme_page(
                'Manage PIE Plugins',
                'PIE Plugins',
                'manage_options',
                'pie-plugin-management',
                [ $this, 'create_plugin_management_page' ]
            );
        }

        /**
         * Options page callback
         */
        public function create_plugin_management_page( )
        {
            // Set class property
            $this->options = get_option( 'pie_plugin_options' );
            ?>
            <div class="wrap">
                <h1>Pie Plugin Management</h1>
                <form method="post" action="options.php">
                <?php
                    // This prints out all hidden setting fields
                    settings_fields( 'pie_plugins_option_group' );
                    do_settings_sections( 'pie-plugin-management' );
                ?>
                </form>
            </div>
            <?php
        }

        /**
         * Register and add settings
         */
        public function page_init( )
        {
            register_setting(
                'pie_plugins_option_group', // Option group
                'pie_plugin_options', // Option name
                [ $this, 'sanitize' ] // Sanitize
            );

            add_settings_section(
                'theme_plugin_settings', // ID
                'Theme Plugins', // Title
                null, // Callback
                'pie-plugin-management' // Page
            );

            add_settings_field(
                'third_party_dependencies', // ID
                'Third Party Dependencies', // Title
                [ $this, 'third_party_dependencies_callback' ], // Callback
                'pie-plugin-management', // Page
                'theme_plugin_settings' // Section
            );

            add_settings_field(
                'plugins', // ID
                'Plugins', // Title
                [ $this, 'plugins_callback' ], // Callback
                'pie-plugin-management', // Page
                'theme_plugin_settings' // Section
            );
        }

        /**
         * Sanitize each setting field as needed
         *
         * @param array $input Contains all settings fields as array keys
         */
        public function sanitize( $input )
        {
            $new_input = [  ];
            $ids = [ 'third_party_dependencies' ] ;
            if( isset( $input[ 'third_party_dependencies' ] ) ) {
                $dependencies = preg_split( "/\r\n|\n|\r/", esc_attr( $input[ 'third_party_dependencies' ] ) );
                $new_input[ 'third_party_dependencies' ] = json_encode( $dependencies );
            }

            return $new_input;
        }

        /**
         * Get the settings option array and print one of its values
         */
        public function third_party_dependencies_callback( )
        {
            /**
             * Perhaps have some styling that is based on if the dependency is active or not.
             * TODO: include the inline styling in a stylesheet
             */
            foreach ( json_decode( $this->options[ 'third_party_dependencies' ] ) as $dependency ): ?>
                <p style="display:block; background: white; width: 50%; padding: 5px 10px;"><?php echo $dependency; ?></p>
            <?php endforeach;
        }

        /**
         * Get the theme plugins path array and print out the values
         */
        public function plugins_callback(  ) {
            foreach ( $this->plugin_paths as $plugin => $path ): ?>
            <p style="display:block; background: white; width: 50%; padding: 5px 10px;"><?php echo $plugin; ?></p>
        <?php endforeach;
        }
    }
    $plugin = new PiePluginManager( );
}

// https://codex.wordpress.org/Creating_Options_Pages used for reference
// activate_plugin( 'path_to_plugin' ); to activate a plugin
// deactivate_plugin( 'path_to_plugin' ); to deactivate a plugin
