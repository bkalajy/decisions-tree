<?php
if (!defined('ABSPATH')) exit;

class DT_Shortcode {
  public static function register() {
    add_shortcode('decision_tool', [__CLASS__, 'render']);
  }

  public static function render($atts = []) {
    $atts = shortcode_atts(['slug' => ''], $atts, 'decision_tool');
    $slug = sanitize_title($atts['slug']);
    if (!$slug) {
      return '<em>'.esc_html__('Missing slug for decision_tool shortcode.', 'decision-trees').'</em>';
    }
  
    // Post by slug (CPT: decision_tool)
    $post = get_page_by_path($slug, OBJECT, 'decision_tool');
    if (!$post) {
      return '<em>'.esc_html__('Decision Tool not found.', 'decision-trees').'</em>';
    }
  
    // Load tool JSON config
    $json = get_post_meta($post->ID, '_dt_config', true);
    if (!$json) {
      return '<em>'.esc_html__('Decision Tool has no configuration.', 'decision-trees').'</em>';
    }
    $config = json_decode($json, true);
    if (!$config || !is_array($config)) {
      return '<em>'.esc_html__('Invalid configuration.', 'decision-trees').'</em>';
    }
  
    // ---------- Build STATUS maps (per-tree overrides + global) ----------
    // Helper: normalize a status key (kept inline to avoid extra includes)
    $normalize = static function($key) {
      $k = remove_accents(wp_strip_all_tags((string)$key));
      $k = strtolower(preg_replace('/[^a-z0-9_]/', '_', $k));
      return trim($k, '_');
    };
  
    // (1) Per-tree overrides from post meta
    $per_tree_rows = get_post_meta($post->ID, '_dtree_statuses', true);
    $per_tree_rows = is_array($per_tree_rows) ? $per_tree_rows : [];
    $per_labels = [];
    $per_styles = [];
    foreach ($per_tree_rows as $row) {
      if (empty($row['key'])) continue;
      $k = $row['key']; // we saved normalized keys in the metabox
      $per_labels[$k] = $row['label'] ?? $k;
      $per_styles[$k] = [
        'text'   => $row['text']   ?? '#111111',
        'bg'     => $row['bg']     ?? '#FFFFFF',
        'border' => $row['border'] ?? '#CCCCCC',
      ];
    }
  
    // (2) Global defaults from settings
    $options = DT_Settings::get_options();
$global_rows = isset($options['statuses']) && is_array($options['statuses']) ? $options['statuses'] : [];
$global_labels = [];
$global_styles = [];
foreach ($global_rows as $key => $row) {
  $k = $normalize($key);
  if (!$k || !is_array($row)) continue;
  $global_labels[$k] = $row['label'] ?? $k;
  $global_styles[$k] = [
    'text'   => $row['text']   ?? '#111111',
    'bg'     => $row['bg']     ?? '#FFFFFF',
    'border' => $row['border'] ?? '#CCCCCC',
  ];
}
  
    // (3) Merge: per-tree overrides WIN over global
    $statusLabels = $global_labels;
    foreach ($per_labels as $k => $v) { $statusLabels[$k] = $v; }
  
    $statusStyles = $global_styles;
    foreach ($per_styles as $k => $v) { $statusStyles[$k] = $v; }
  
    // Also build a single keyed map (key => {label,text,bg,border})
    $statuses_map = [];
    foreach ($statusStyles as $k => $style) {
      $statuses_map[$k] = [
        'label'  => $statusLabels[$k] ?? $k,
        'text'   => $style['text'],
        'bg'     => $style['bg'],
        'border' => $style['border'],
      ];
    }
    // ---------------------------------------------------------------------
  
    // Unique container id for multi-instance pages
    $container_id = 'dtree-'.wp_generate_uuid4();
  
    // Enqueue assets (cache-busted)
    $css_path = DT_PLUGIN_PATH.'assets/style.css';
    $js_path  = DT_PLUGIN_PATH.'assets/decision-tool.js';
    wp_enqueue_style('dtree-fonts','https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',[],null);
    wp_enqueue_style('dtree-style', DT_PLUGIN_URL.'assets/style.css', [], @filemtime($css_path) ?: DT_PLUGIN_VERSION);
    wp_enqueue_script('dtree-js',   DT_PLUGIN_URL.'assets/decision-tool.js', [], @filemtime($js_path)  ?: DT_PLUGIN_VERSION, true);
  
    // Inject merged statuses both in config* and options* so either frontend path works
    $config['statusLabels'] = $statusLabels;
    $config['statusStyles'] = $statusStyles;
  
    // Keep your existing options but add a keyed map the JS can read
    if (!is_array($options)) $options = [];
    $options['statuses_map'] = $statuses_map; // NEW: keyed map { key: {label,text,bg,border} }
  
    // Prepare payload for JS
    $payload = [
      'containerId' => $container_id,
      'config'      => $config,
      'options'     => $options,
    ];
  
  // Inject merged statuses into config/options for the JS
  $config['statusLabels'] = $statusLabels;
  $config['statusStyles'] = $statusStyles;

  // Add a keyed map for easier JS lookups
  if (!is_array($options)) $options = [];
  $options['statuses_map'] = $statuses_map;

  // Update payload with these
  $payload['config']  = $config;
  $payload['options'] = $options;

    // Pass config safely to JS
    $inline = 'window.DTREE_INSTANCES = window.DTREE_INSTANCES || [];'
            . 'window.DTREE_INSTANCES.push('.wp_json_encode($payload).');';
    wp_add_inline_script('dtree-js', $inline, 'before');
  
    ob_start(); ?>
      <div id="<?php echo esc_attr($container_id); ?>" class="dtree-container" aria-live="polite"></div>
    <?php
    return ob_get_clean();
  }
}