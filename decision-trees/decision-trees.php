<?php
/**
 * Plugin Name: Decision Trees (Scalable Tools)
 * Description: Create multiple decision tools from JSON and render via shortcode [decision_tool slug="..."].
 * Version: 1.0.0
 * Author: Picology
 * Text Domain: decision-trees
 */

if (!defined('ABSPATH')) exit;

define('DT_PLUGIN_VERSION', '1.0.0');
define('DT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DT_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once DT_PLUGIN_PATH . 'includes/class-dt-cpt.php';
require_once DT_PLUGIN_PATH . 'includes/class-dt-shortcode.php';

add_action('init', ['DT_CPT', 'register']);
add_action('add_meta_boxes', ['DT_CPT', 'add_metabox']);
add_action('save_post', ['DT_CPT', 'save_metabox']);

add_action('init', ['DT_Shortcode', 'register']);