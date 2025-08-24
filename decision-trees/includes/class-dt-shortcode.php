<?php
if (!defined('ABSPATH')) exit;

class DT_Shortcode {
  public static function register() {
    add_shortcode('decision_tool', [__CLASS__, 'render']);
  }

  public static function render($atts = []) {
    $atts = shortcode_atts(['slug' => ''], $atts, 'decision_tool');
    $slug = sanitize_title($atts['slug']);
    if (!$slug) return '<em>'.esc_html__('Missing slug for decision_tool shortcode.', 'decision-trees').'</em>';

    $post = get_page_by_path($slug, OBJECT, 'decision_tool');
    if (!$post) return '<em>'.esc_html__('Decision Tool not found.', 'decision-trees').'</em>';

    $json = get_post_meta($post->ID, '_dt_config', true);
    if (!$json) return '<em>'.esc_html__('Decision Tool has no configuration.', 'decision-trees').'</em>';

    $config = json_decode($json, true);
    if (!$config) return '<em>'.esc_html__('Invalid configuration.', 'decision-trees').'</em>';

    // Unique container id for multi-instance pages
    $container_id = 'dtree-'.wp_generate_uuid4();

    // Enqueue assets
    wp_enqueue_style('dtree-style', DT_PLUGIN_URL.'assets/style.css', [], DT_PLUGIN_VERSION);
    wp_enqueue_script('dtree-js', DT_PLUGIN_URL.'assets/decision-tool.js', [], DT_PLUGIN_VERSION, true);

    // Pass config safely to JS (no HTML; JS never uses innerHTML from config)
    $payload = [
      'containerId' => $container_id,
      'config' => $config,
    ];
    $inline = 'window.DTREE_INSTANCES = window.DTREE_INSTANCES || [];'
            . 'window.DTREE_INSTANCES.push('.wp_json_encode($payload).');';
    wp_add_inline_script('dtree-js', $inline, 'before');

    ob_start(); ?>
      <div id="<?php echo esc_attr($container_id); ?>" class="dtree-container" aria-live="polite"></div>
    <?php
    return ob_get_clean();
  }
}