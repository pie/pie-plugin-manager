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

if (!class_exists('PiePluginManager')) {
    class PiePluginManager {

        private $options;
        public function __construct()
        {
            add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $this, 'page_init' ) );
        }

        /**
         * Add options page
         */
        public function add_plugin_page()
        {
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
         * Options page callback
         */
        public function create_plugin_management_page()
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
                    submit_button();
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
                'pie_plugin_options', // Option name
                array( $this, 'sanitize' ) // Sanitize
            );

            add_settings_section(
                'general_management_settings', // ID
                'General', // Title
                null, // Callback
                'pie-plugin-management' // Page
            );

            add_settings_field(
                'third_party_dependencies', // ID
                'Third Party Dependencies', // Title
                array( $this, 'third_party_dependencies_callback' ), // Callback
                'pie-plugin-management', // Page
                'general_management_settings' // Section
            );
        }

        /**
         * Sanitize each setting field as needed
         *
         * @param array $input Contains all settings fields as array keys
         */
        public function sanitize( $input )
        {
            $new_input = array();
            $ids = ['third_party_dependencies'];
            if( isset( $input['third_party_dependencies'] ) ) {
                $dependencies = preg_split( "/\r\n|\n|\r/", esc_attr( $input['third_party_dependencies'] ) );
                $new_input['third_party_dependencies'] = json_encode( $dependencies );
            }

            return $new_input;
        }

        /**
         * Get the settings option array and print one of its values
         */
        public function third_party_dependencies_callback()
        {
            printf(
                '<textarea id="third_party_dependencies" name="pie_plugin_options[third_party_dependencies]" rows="5" cols="30">%s</textarea>',
                isset( $this->options['third_party_dependencies'] ) ? implode( "\n", json_decode( $this->options['third_party_dependencies'] ) ) : ''
            );
        }
    }
    $plugin = new PiePluginManager();
}

// https://codex.wordpress.org/Creating_Options_Pages used for reference
