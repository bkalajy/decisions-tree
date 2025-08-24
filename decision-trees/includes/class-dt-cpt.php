<?php
if (!defined('ABSPATH')) exit;

class DT_CPT {

  public static function register() {
    register_post_type('decision_tool', [
      'label'       => __('Decision Tools', 'decision-trees'),
      'public'      => false,
      'show_ui'     => true,
      'show_in_menu'=> true,
      'show_in_rest'=> false, // keep REST off for this CPT
      'menu_icon'   => DT_PLUGIN_URL . 'assets/icon.svg',
      'supports'    => ['title'],
      'map_meta_cap'=> true,
      'capabilities'=> [
        'edit_post'          => 'manage_options',
        'read_post'          => 'manage_options',
        'delete_post'        => 'manage_options',
        'edit_posts'         => 'manage_options',
        'edit_others_posts'  => 'manage_options',
        'publish_posts'      => 'manage_options',
        'read_private_posts' => 'manage_options',
      ],
    ]);
  }

  public static function add_metabox() {
    add_meta_box(
      'dt_config_box',
      __('Decision Tool JSON', 'decision-trees'),
      [__CLASS__, 'render_config_metabox'],
      'decision_tool',
      'normal',
      'high'
    );

    // v1.1: Shortcode + Slug box
    add_meta_box(
      'dt_meta_box',
      __('Shortcode & Slug', 'decision-trees'),
      [__CLASS__, 'render_meta_metabox'],
      'decision_tool',
      'side',
      'high'
    );
  }

  public static function render_config_metabox($post) {
    wp_nonce_field('dt_config_save', 'dt_config_nonce');
    $json = get_post_meta($post->ID, '_dt_config', true);
    echo '<p>'.esc_html__('Paste validated JSON that defines fields and rules. No HTML or scripts.', 'decision-trees').'</p>';
    echo '<textarea name="dt_config" style="width:100%;min-height:320px;font-family:monospace;">'
         . esc_textarea($json) . '</textarea>';
    echo '<p style="margin-top:8px;color:#666">'.esc_html__('Tip: test JSON at jsonlint.com.', 'decision-trees').'</p>';
  }

  // v1.1: shortcode display + slug editor
  public static function render_meta_metabox($post) {
    wp_nonce_field('dt_meta_save', 'dt_meta_nonce');
    $slug = $post->post_name ?: '';

    echo '<div style="display:grid;gap:8px;">';

    // Copyable shortcode
    $shortcode = '[decision_tool slug="'.esc_attr($slug).'"]';
    echo '<label style="font-weight:600;">'.esc_html__('Shortcode', 'decision-trees').'</label>';
    echo '<div style="display:flex;gap:6px;">';
    echo '<input type="text" id="dt-shortcode" readonly value="'.esc_attr($shortcode).'" style="flex:1;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;font-family:monospace;">';
    echo '<button type="button" class="button" onclick="(function(){const i=document.getElementById(\'dt-shortcode\');i.select();i.setSelectionRange(0,99999);document.execCommand(\'copy\');})();">'.esc_html__('Copy', 'decision-trees').'</button>';
    echo '</div>';

    // Editable slug
    echo '<label style="font-weight:600;margin-top:8px;">'.esc_html__('Slug (URL part)', 'decision-trees').'</label>';
    echo '<input type="text" name="dt_slug" value="'.esc_attr($slug).'" style="width:100%;padding:6px 8px;border:1px solid #cbd5e1;border-radius:6px;">';
    echo '<p style="color:#666">'.esc_html__('Letters, numbers, and hyphens only. Changing the slug updates the shortcode above on save.', 'decision-trees').'</p>';

    echo '</div>';
  }

  public static function save_metabox($post_id) {
    // Save JSON config
    if (isset($_POST['dt_config_nonce']) && wp_verify_nonce($_POST['dt_config_nonce'], 'dt_config_save')) {
      if (!(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) && current_user_can('edit_post', $post_id)) {
        $raw = isset($_POST['dt_config']) ? wp_unslash($_POST['dt_config']) : '';
        $decoded = json_decode($raw, true);
        if ($raw === '') {
          delete_post_meta($post_id, '_dt_config');
        } else if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
          // Light validation
          $ok = isset($decoded['fields'], $decoded['rules']) && is_array($decoded['fields']) && is_array($decoded['rules']);
          if ($ok) {
            update_post_meta($post_id, '_dt_config', wp_json_encode($decoded, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
          }
        }
      }
    }

    // v1.1: Save slug (post_name)
    if (isset($_POST['dt_meta_nonce']) && wp_verify_nonce($_POST['dt_meta_nonce'], 'dt_meta_save')) {
      if (!(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) && current_user_can('edit_post', $post_id)) {
        if (isset($_POST['dt_slug'])) {
          $new = sanitize_title(wp_unslash($_POST['dt_slug']));
          if ($new) {
            // Update post_name; keep post_status etc.
            wp_update_post([
              'ID' => $post_id,
              'post_name' => $new,
            ]);
          }
        }
      }
    }
  }
}