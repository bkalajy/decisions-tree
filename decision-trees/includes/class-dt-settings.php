<?php
if (!defined('ABSPATH')) exit;

class DT_Settings {

  const OPTION_KEY = 'dt_options';

  public static function menu() {
    // Put settings under the existing CPT menu.
    add_submenu_page(
      'edit.php?post_type=decision_tool',
      __('Decision Tools Settings','decision-trees'),
      __('Settings','decision-trees'),
      'manage_options',
      'dt-settings',
      [__CLASS__, 'render_page']
    );
  }

  public static function register() {
    register_setting(self::OPTION_KEY, self::OPTION_KEY, ['sanitize_callback' => [__CLASS__, 'sanitize']]);

    add_settings_section('dt_general', __('General','decision-trees'), '__return_false', self::OPTION_KEY);

    add_settings_field(
      'hide_title',
      __('Hide form title','decision-trees'),
      [__CLASS__, 'field_hide_title'],
      self::OPTION_KEY,
      'dt_general'
    );

    add_settings_field(
      'statuses',
      __('Result statuses','decision-trees'),
      [__CLASS__, 'field_statuses'],
      self::OPTION_KEY,
      'dt_general'
    );
  }

  public static function get_options() {
    $defaults = [
      'hide_title' => 0,
      'statuses'   => [
        // keep your three defaults so nothing breaks
        'ok' => ['label' => __('Recommended','decision-trees'),            'text' => '#065f46', 'bg' => '#ecfdf5', 'border' => '#a7f3d0'],
        'no' => ['label' => __('Not recommended','decision-trees'),        'text' => '#991b1b', 'bg' => '#fef2f2', 'border' => '#fecaca'],
        'restrict' => ['label' => __('Recommended with restriction','decision-trees'), 'text' => '#9a3412', 'bg' => '#fff7ed', 'border' => '#fed7aa'],
      ],
    ];
    $opt = get_option(self::OPTION_KEY, []);
    if (!is_array($opt)) $opt = [];
    $opt = array_merge($defaults, $opt);

    // ensure structure
    if (!isset($opt['statuses']) || !is_array($opt['statuses'])) $opt['statuses'] = $defaults['statuses'];
    return $opt;
  }

  public static function sanitize($input) {
    $out = self::get_options(); // start from defaults, then overwrite

    // hide title
    $out['hide_title'] = isset($input['hide_title']) ? 1 : 0;

    // statuses
    $out['statuses'] = [];
    if (!empty($input['statuses']) && is_array($input['statuses'])) {
      foreach ($input['statuses'] as $row) {
        $key = isset($row['key']) ? sanitize_key($row['key']) : '';
        if (!$key) continue;
        $out['statuses'][$key] = [
          'label'  => sanitize_text_field($row['label'] ?? ''),
          'text'   => self::sanitize_color($row['text'] ?? ''),
          'bg'     => self::sanitize_color($row['bg'] ?? ''),
          'border' => self::sanitize_color($row['border'] ?? ''),
        ];
      }
    }
    // keep at least defaults if none provided
    if (!$out['statuses']) $out['statuses'] = self::get_options()['statuses'];

    return $out;
  }

  private static function sanitize_color($c) {
    $c = trim($c);
    // allow valid 3/6/8 hex like #fff / #ffffff / #ffffffff
    if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c)) return $c;
    return '';
  }

  /* -------- Field renderers -------- */

  public static function field_hide_title() {
    $opt = self::get_options();
    ?>
      <label>
        <input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[hide_title]" value="1" <?php checked($opt['hide_title'], 1); ?>>
        <?php esc_html_e('Hide the “Decision Tool” title above all forms','decision-trees'); ?>
      </label>
    <?php
  }

  public static function field_statuses() {
    $opt = self::get_options();
    $rows = $opt['statuses'];
    // enqueue color picker
    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');

    // normalize for iteration
    $pairs = [];
    foreach ($rows as $k => $cfg) {
      $pairs[] = [
        'key'    => $k,
        'label'  => $cfg['label'] ?? '',
        'text'   => $cfg['text'] ?? '',
        'bg'     => $cfg['bg'] ?? '',
        'border' => $cfg['border'] ?? '',
      ];
    }
    ?>
    <p><?php esc_html_e('Add as many statuses as you need. Use the “key” in your rules (e.g., ok, no, restrict, custom1...).','decision-trees'); ?></p>

    <table class="widefat striped" id="dt-statuses-table" style="max-width:860px">
      <thead>
        <tr>
          <th><?php esc_html_e('Key','decision-trees'); ?></th>
          <th><?php esc_html_e('Label','decision-trees'); ?></th>
          <th><?php esc_html_e('Text color','decision-trees'); ?></th>
          <th><?php esc_html_e('Background','decision-trees'); ?></th>
          <th><?php esc_html_e('Border','decision-trees'); ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pairs as $i => $row): ?>
          <tr>
            <td><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[statuses][<?php echo $i; ?>][key]" value="<?php echo esc_attr($row['key']); ?>" placeholder="ok"></td>
            <td><input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[statuses][<?php echo $i; ?>][label]" value="<?php echo esc_attr($row['label']); ?>" placeholder="Recommended"></td>
            <td><input class="dt-color" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[statuses][<?php echo $i; ?>][text]" value="<?php echo esc_attr($row['text']); ?>" placeholder="#065f46"></td>
            <td><input class="dt-color" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[statuses][<?php echo $i; ?>][bg]" value="<?php echo esc_attr($row['bg']); ?>" placeholder="#ecfdf5"></td>
            <td><input class="dt-color" type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[statuses][<?php echo $i; ?>][border]" value="<?php echo esc_attr($row['border']); ?>" placeholder="#a7f3d0"></td>
            <td><button type="button" class="button link-delete">&times;</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p><button type="button" class="button" id="dt-add-status"><?php esc_html_e('Add status','decision-trees'); ?></button></p>

    <script>
    (function($){
      function initColors($ctx){ $ctx.find('.dt-color').wpColorPicker(); }
      $(function(){
        initColors($(document));
        $('#dt-add-status').on('click', function(){
          var $tbody = $('#dt-statuses-table tbody');
          var idx = $tbody.find('tr').length;
          var html = '<tr>'
            + '<td><input type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[statuses]['+idx+'][key]" placeholder="custom"></td>'
            + '<td><input type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[statuses]['+idx+'][label]" placeholder="Custom label"></td>'
            + '<td><input class="dt-color" type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[statuses]['+idx+'][text]" placeholder="#000000"></td>'
            + '<td><input class="dt-color" type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[statuses]['+idx+'][bg]" placeholder="#ffffff"></td>'
            + '<td><input class="dt-color" type="text" name="<?php echo esc_js(self::OPTION_KEY); ?>[statuses]['+idx+'][border]" placeholder="#cccccc"></td>'
            + '<td><button type="button" class="button link-delete">&times;</button></td>'
            + '</tr>';
          var $row = $(html).appendTo($tbody);
          initColors($row);
        });
        $('#dt-statuses-table').on('click', '.link-delete', function(){
          $(this).closest('tr').remove();
        });
      });
    })(jQuery);
    </script>
    <?php
  }

  public static function render_page() {
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Decision Tools Settings','decision-trees'); ?></h1>
      <form method="post" action="options.php">
        <?php
          settings_fields(self::OPTION_KEY);
          do_settings_sections(self::OPTION_KEY);
          submit_button();
        ?>
      </form>
    </div>
    <?php
  }
}