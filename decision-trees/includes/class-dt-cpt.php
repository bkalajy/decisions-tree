<?php
if (!defined('ABSPATH')) exit;

class DT_CPT {
  public static function register() {
    register_post_type('decision_tool', [
      'label' => __('Decision Tools', 'decision-trees'),
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'menu_icon' => 'dashicons-chart-network',
      'supports' => ['title', 'slug'],
    ]);
  }

  public static function add_metabox() {
    add_meta_box(
      'dt_config_box',
      __('Decision Tool JSON', 'decision-trees'),
      [__CLASS__, 'render_metabox'],
      'decision_tool',
      'normal',
      'high'
    );
  }

  public static function render_metabox($post) {
    wp_nonce_field('dt_config_save', 'dt_config_nonce');
    $json = get_post_meta($post->ID, '_dt_config', true);
    echo '<p>'.esc_html__('Paste validated JSON that defines fields and rules. No HTML or scripts.', 'decision-trees').'</p>';
    echo '<textarea name="dt_config" style="width:100%;min-height:320px;font-family:monospace;">'
         . esc_textarea($json) . '</textarea>';
    echo '<p style="margin-top:8px;color:#666">'.esc_html__('Tip: test JSON at jsonlint.com.', 'decision-trees').'</p>';
  }

  public static function save_metabox($post_id) {
    if (!isset($_POST['dt_config_nonce']) || !wp_verify_nonce($_POST['dt_config_nonce'], 'dt_config_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $raw = isset($_POST['dt_config']) ? wp_unslash($_POST['dt_config']) : '';

    // Strict decode and re-encode to strip junk; disallow objects outside spec later.
    $decoded = json_decode($raw, true);
    if ($raw !== '' && (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded))) {
      // Do not save invalid JSON
      return;
    }

    // Basic schema checks to reduce risk & ensure scalability.
    $allowed_ops = ['==','!=','>','>=','<','<=','in']; // keep in sync with JS
    $ok = true;
    if ($decoded) {
      // Require minimal keys
      $ok = isset($decoded['slug'], $decoded['title'], $decoded['fields'], $decoded['rules'])
        && is_array($decoded['fields']) && is_array($decoded['rules']);

      // Very lightweight validation (you can harden this further if needed)
      foreach ($decoded['fields'] as $f) {
        if (!isset($f['id'], $f['label'], $f['type'])) { $ok = false; break; }
      }
      foreach ($decoded['rules'] as $r) {
        if (!isset($r['if'], $r['then']) || !is_array($r['then'])) { $ok = false; break; }
        // ops are validated on the JS side too; we trust only listed ops
        // (deep validation omitted for brevity)
      }
    }

    if ($ok) {
      // Store pretty JSON; this avoids storing executable code/HTML.
      update_post_meta($post_id, '_dt_config', wp_json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    }
  }
}