<?php
/**
 * Plugin Name: ACF AI Generator
 * Plugin URI:  https://yourwebsite.com/acf-ai-generator
 * Description: Generates ACF field group code snippets using AI based on natural language input.
 * Version:     1.0.0
 * Author:      Kunjan Gohel
 * Author URI:  https://yourwebsite.com
 * License:     GPL2
 * Text Domain: acf-ai-generator
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'ACF_AI_GENERATOR_VERSION', '1.0.0' );
define( 'ACF_AI_GENERATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACF_AI_GENERATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the main plugin class.
require_once ACF_AI_GENERATOR_PLUGIN_DIR . 'includes/class-acf-ai-generator.php';
require_once ACF_AI_GENERATOR_PLUGIN_DIR . 'includes/acf-ai-generator-snippets.php';

// Initialize the plugin.
function acf_ai_generator_init() {
    $plugin = new ACF_AIGenerator();
    $plugin->run();

    // Initialize the snippets class
    $acf_ai_generator_snippets = new ACF_AIGenerator_Snippets();
    $acf_ai_generator_snippets->run();
}
add_action( 'plugins_loaded', 'acf_ai_generator_init' );

// Enqueue admin styles and scripts
function acf_ai_generator_admin_assets($hook) {
    // Load assets only on our plugin's admin page
    if ($hook !== 'toplevel_page_acf-ai-generator') {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style(
        'acf-ai-generator-admin-style',
        plugin_dir_url(__FILE__) . 'assets/css/admin-style.css',
        array(),
        ACF_AI_GENERATOR_VERSION
    );

    // Enqueue JS
    wp_enqueue_script(
        'acf-ai-generator-admin-script',
        plugin_dir_url(__FILE__) . 'assets/js/admin-script.js',
        array('jquery'),
        ACF_AI_GENERATOR_VERSION,
        true
    );
}
add_action('admin_enqueue_scripts', 'acf_ai_generator_admin_assets');
