<?php
/*
Plugin Name: Steam Recent Inventory Widget
Description: Sidebar widget & shortcode to show the 5 or 10 most recent TF2 or CS2 items from a public Steam inventory.
Version: 1.2.0
Author: Frop Core Labs
Author URI: https://fropcore.github.io/
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit;

define('SRIW_VERSION', '1.0.0');
define('SRIW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SRIW_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SRIW_PLUGIN_DIR . 'includes/class-steam-recent-inventory-widget.php';

/**
 * Register widget on widgets_init
 */
function sriw_register_widget() {
    register_widget('SRIW_Recent_Inventory_Widget');
}
add_action('widgets_init', 'sriw_register_widget');

/**
 * Shortcode: [steam_recent_items steamid="76561198000000000" app="cs2|tf2" count="5" lang="english"]
 */
function sriw_shortcode($atts) {
    $atts = shortcode_atts(array(
        'steamid' => '',
        'app'     => 'cs2',
        'count'   => 5,
        'lang'    => 'english',
        'title'   => ''
    ), $atts, 'steam_recent_items');

    $appid = strtolower($atts['app']) === 'tf2' ? 440 : 730; // default cs2
    $count = intval($atts['count']);
    if (!in_array($count, array(5,10))) $count = 5;

    $args = array(
        'steamid' => sanitize_text_field($atts['steamid']),
        'appid'   => $appid,
        'count'   => $count,
        'lang'    => sanitize_text_field($atts['lang']),
        'title'   => sanitize_text_field($atts['title'])
    );

    ob_start();
    echo SRIW_Recent_Inventory_Widget::render_list($args);
    return ob_get_clean();
}
add_shortcode('steam_recent_items', 'sriw_shortcode');

/**
 * Basic CSS for widget list
 */
function sriw_enqueue_styles() {
    wp_register_style('sriw-styles', SRIW_PLUGIN_URL . 'assets/sriw.css', array(), SRIW_VERSION);
    wp_enqueue_style('sriw-styles');
}
add_action('wp_enqueue_scripts', 'sriw_enqueue_styles');

// Create CSS file on the fly if not present (for simple distribution)
register_activation_hook(__FILE__, function() {
    $css_dir = SRIW_PLUGIN_DIR . 'assets/';
    if (!file_exists($css_dir)) {
        @mkdir($css_dir);
    }
    $css_file = $css_dir . 'sriw.css';
    if (!file_exists($css_file)) {
        $css = ".sriw-list{list-style:none;margin:0;padding:0}.sriw-item{display:flex;align-items:center;margin:6px 0}.sriw-item img{width:32px;height:32px;margin-right:8px;object-fit:contain;border-radius:4px}.sriw-title{margin-bottom:6px;font-weight:bold}";
        @file_put_contents($css_file, $css);
    }
});
