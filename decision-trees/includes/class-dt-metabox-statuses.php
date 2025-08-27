<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Classic Editor meta box for per-tree status overrides.
 * CPT slug assumed: 'dtree'. Change if yours differs.
 */

// --- Helpers (reuse if you already have them elsewhere) ---
if ( ! function_exists( 'dt_normalize_key' ) ) {
  function dt_normalize_key( $key ) {
    $k = remove_accents( wp_strip_all_tags( (string) $key ) );
    $k = strtolower( preg_replace( '/[^a-z0-9_]/', '_', $k ) );
    return trim( $k, '_' );
  }
}
if ( ! function_exists( 'dt_sanitize_color' ) ) {
  function dt_sanitize_color( $c ) {
    $c = (string) $c;
    return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $c ) ? $c : '#333333';
  }
}

// --- Register the meta box ---
add_action( 'add_meta_boxes', function () {
  add_meta_box(
    'dtree_statuses_box',
    __( 'Decision Tool – Result Statuses (Overrides)', 'dtree' ),
    'dtree_statuses_box_render',
    'decision_tool',
    'normal',
    'default'
  );
} );

// --- Render the box ---
function dtree_statuses_box_render( $post ) {
  wp_nonce_field( 'dtree_statuses_save', 'dtree_statuses_nonce' );

  $rows = get_post_meta( $post->ID, '_dtree_statuses', true );
  if ( ! is_array( $rows ) ) $rows = [];

  echo '<style>
    .dtree-statuses-table{width:100%;border-collapse:collapse;margin-top:8px}
    .dtree-statuses-table th,.dtree-statuses-table td{border:1px solid #ddd;padding:6px;vertical-align:top}
    .dtree-statuses-table input[type=text],
    .dtree-statuses-table input[type=color]{width:100%}
    .dtree-controls{margin:8px 0}
  </style>';

  echo '<p>'.esc_html__( 'Define per-tree status overrides. Keys must match your JSON rule outputs (then.status). These override global settings for this tree.', 'dtree' ).'</p>';

  echo '<div class="dtree-controls"><button type="button" class="button" id="dtree-add-status">'.esc_html__( 'Add status', 'dtree' ).'</button></div>';

  echo '<table class="dtree-statuses-table" id="dtree-statuses-table">
    <thead><tr>
      <th>'.esc_html__( 'Key', 'dtree' ).'</th>
      <th>'.esc_html__( 'Label', 'dtree' ).'</th>
      <th>'.esc_html__( 'Text color', 'dtree' ).'</th>
      <th>'.esc_html__( 'Background', 'dtree' ).'</th>
      <th>'.esc_html__( 'Border', 'dtree' ).'</th>
      <th></th>
    </tr></thead><tbody>';

  foreach ( $rows as $r ) {
    $key    = esc_attr( $r['key'] ?? '' );
    $label  = esc_attr( $r['label'] ?? '' );
    $text   = esc_attr( $r['text'] ?? '#111111' );
    $bg     = esc_attr( $r['bg'] ?? '#FFFFFF' );
    $border = esc_attr( $r['border'] ?? '#CCCCCC' );

    echo '<tr>
      <td><input type="text" name="dtree_statuses[key][]" value="'.$key.'" placeholder="ok" /></td>
      <td><input type="text" name="dtree_statuses[label][]" value="'.$label.'" placeholder="Recommended" /></td>
      <td><input type="color" name="dtree_statuses[text][]" value="'.$text.'" /></td>
      <td><input type="color" name="dtree_statuses[bg][]" value="'.$bg.'" /></td>
      <td><input type="color" name="dtree_statuses[border][]" value="'.$border.'" /></td>
      <td><button type="button" class="button-link-delete dtree-remove-row">&times;</button></td>
    </tr>';
  }

  echo '</tbody></table>';

  echo '<script>
    (function(){
      const tbody = document.getElementById("dtree-statuses-table").getElementsByTagName("tbody")[0];
      const addBtn = document.getElementById("dtree-add-status");
      if (addBtn) addBtn.addEventListener("click", function(){
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td><input type="text" name="dtree_statuses[key][]"    placeholder="ok"/></td>
          <td><input type="text" name="dtree_statuses[label][]"  placeholder="Recommended"/></td>
          <td><input type="color" name="dtree_statuses[text][]" /></td>
          <td><input type="color" name="dtree_statuses[bg][]" /></td>
          <td><input type="color" name="dtree_statuses[border][]" /></td>
          <td><button type="button" class="button-link-delete dtree-remove-row">&times;</button></td>`;
        tbody.appendChild(tr);
      });
      tbody.addEventListener("click", function(e){
        if(e.target && e.target.classList.contains("dtree-remove-row")){
          e.preventDefault();
          const tr = e.target.closest("tr");
          if (tr) tr.remove();
        }
      });
    })();
  </script>';
}

// --- Save handler ---
add_action( 'save_post_decision_tool', function( $post_id ) {
  if ( ! isset( $_POST['dtree_statuses_nonce'] ) || ! wp_verify_nonce( $_POST['dtree_statuses_nonce'], 'dtree_statuses_save' ) ) return;
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
  if ( ! current_user_can( 'edit_post', $post_id ) ) return;

  $in  = $_POST['dtree_statuses'] ?? null;
  $out = [];

  if ( is_array( $in ) && isset( $in['key'] ) && is_array( $in['key'] ) ) {
    $count = count( $in['key'] );
    $seen = [];
    for ( $i = 0; $i < $count; $i++ ) {
      $key = dt_normalize_key( $in['key'][ $i ] ?? '' );
      if ( ! $key || isset( $seen[ $key ] ) ) continue; // skip empty/duplicate
      $seen[ $key ] = true;

      $label  = sanitize_text_field( $in['label'][ $i ]  ?? $key );
      $text   = dt_sanitize_color( $in['text'][ $i ]     ?? '#111111' );
      $bg     = dt_sanitize_color( $in['bg'][ $i ]       ?? '#FFFFFF' );
      $border = dt_sanitize_color( $in['border'][ $i ]   ?? '#CCCCCC' );

      $out[] = compact( 'key', 'label', 'text', 'bg', 'border' );
    }
  }

  if ( ! empty( $out ) ) {
    update_post_meta( $post_id, '_dtree_statuses', $out );
  } else {
    delete_post_meta( $post_id, '_dtree_statuses' );
  }
} );