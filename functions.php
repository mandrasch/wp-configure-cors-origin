<?php
/**
 * Plugin Name:       WP Configure CORS-origin
 * Plugin URI:        https://github.com/programmieraffe/wp-configure-cors-origin
 * Description:       Configure the Access-Control-Allow-Origin for access from other domains via REST (used in headless/jamstack)
 * Version:           0.9
 * Author:            Matthias Andrasch
 * Author URI:        https://matthias-andrasch.eu
 * License:           CC0
 * License URI:       https://creativecommons.org/publicdomain/zero/1.0/legalcode
 * Text Domain:       wp-configure-cors-origin
 * Domain Path:       /languages
 */
 
 // TODO: add setting page for frontenddomain
 // TODO: add option to allow all for debug/dev calls from localhost (* or checkbox?)


// Namespace
// https://wptavern.com/beyond-prefixing-a-wordpress-developers-guide-to-php-namespaces
// for action hooks: https://stevegrunwell.com/blog/php-namespaces-wordpress/

namespace WpConfigureCorsOrigin;

// ========== REST hooks

// Thanks to https://dev.to/robmarshall/wordpress-rest-api-cors-issues-13p7
// Thanks to https://thoughtsandstuff.com/wordpress-rest-api-cors-issues/

function handle_preflight()
{
    $options = get_option('wpconfigurecorsorigin_plugin_settings');
  
    // settings not configured yet, default mode => deactivated
    if ($options == false) {
        return;
    }
  
    // it was in tutorial, but we don't need it
    // $origin = get_http_origin();
    
    switch ($options['mode']) {
      case 'inactive':
        return; // exit this action
        break;
      case 'custom':
        $allow_value = $options['custom_allowed_origin']; // something like https://yourfrontenddomain.org
        break;
      case 'all':
        $allow_value = '*';
        break;
    }
    
    // set header for rest API (and other resources as well?)
    header("Access-Control-Allow-Origin: " . $allow_value);
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
    header("Access-Control-Allow-Credentials: true");
    header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
    if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
}

// TODO: use https://developer.wordpress.org/reference/hooks/rest_api_init/ ?
add_action('init', __NAMESPACE__ . '\handle_preflight');

// TODO: This should be handled by the headers? why do we implement it?
function rest_filter_incoming_connections($errors)
{
    $request_server = $_SERVER['REMOTE_ADDR'];
    $origin = get_http_origin();
    
    $options = get_option('wpconfigurecorsorigin_plugin_settings');

    // settings not configured yet, default mode => deactivated
    if ($options == false || $options['mode'] == 'all' || $options['mode'] == 'inactive') {
        return;
    }
  
    if ($options['mode'] == 'custom' && $origin !== $options['custom_allowed_origin']) {
        return new WP_Error('forbidden_access', $origin, array(
        'status' => 403
    ));
    }
    return $errors;
}*/

add_filter('rest_authentication_errors', __NAMESPACE__ .'\rest_filter_incoming_connections');


// ======== SETTINGS ===========

// Settings page  by tutorial
// https://neliosoftware.com/blog/how-to-create-settings-page-in-wordpress/

function add_settings_page()
{
    add_options_page(
        'Configure CORS origin',
        'CORS origin',
        'manage_options',
        'wp-configure-cors-origin', // page slug (options-general.php?page=wp-configure-cors-origin)
    __NAMESPACE__ . '\render_settings_page'
  );
}
add_action('admin_menu', __NAMESPACE__ . '\add_settings_page');

function render_settings_page()
{
    ?>
  <h2>Configure CORS origin - Settings</h2>
  <form action="options.php" method="post">
    <?php
    settings_fields('wpconfigurecorsorigin_plugin_settings');
    do_settings_sections('wp-configure-cors-origin'); ?>
    <input
      type="submit"
      name="submit"
      class="button button-primary"
      value="<?php esc_attr_e('Save'); ?>"
    />
  </form>
<?php
}

function register_settings()
{
    register_setting(
        'wpconfigurecorsorigin_plugin_settings',
        'wpconfigurecorsorigin_plugin_settings',
        array('sanitize_callback' => __NAMESPACE__ .'\validate_settings')
  );

    add_settings_section(
        'wpconfigurecorsorigin_basic_section',
        '',
        __NAMESPACE__ . '\render_section_basic',
        'wp-configure-cors-origin'
  );
  
    add_settings_field(
        'mode',
        'Configure CORS origin?',
        __NAMESPACE__ . '\render_mode_field',
        'wp-configure-cors-origin',
        'wpconfigurecorsorigin_basic_section'
  );

    add_settings_field(
        'allowed_origin',
        'Custom Allow Origin:',
        __NAMESPACE__ . '\render_allow_origin_field',
        'wp-configure-cors-origin',
        'wpconfigurecorsorigin_basic_section'
  );
}
add_action('admin_init', __NAMESPACE__ . '\register_settings');

function validate_settings($input)
{
    $sanitized['mode'] = sanitize_text_field($input['mode']);
    $sanitized['custom_allowed_origin'] = sanitize_text_field($input['custom_allowed_origin']);
  
    if (!in_array($sanitized['mode'], array('inactive','custom','all'))) {
        $type = 'error';
        echo "error";
        $message = __('Not accepted value for radio button.', 'wp-configure-cors-origin');
        add_settings_error(
        'wpconfigurecorsorigin_plugin_settings',
        esc_attr('settings_updated'),
        $message,
        $type
      );
    }
    
    // check url if mode == custom
    if ($sanitized['mode'] == 'custom' && !filter_var($sanitized['custom_allowed_origin'], FILTER_VALIDATE_URL)) {
        $type = 'error';
        $message = __('Allow origin must match URL standards and cannot be empty. Please try again.', 'wp-configure-cors-origin');
        add_settings_error(
        'wpconfigurecorsorigin_plugin_settings',
        esc_attr('settings_updated'),
        $message,
        $type
      );
    }
  
    if (count(get_settings_errors('wpconfigurecorsorigin_plugin_settings')) > 0) {
        // we had errors, so we just use the old values as return value (there is no "break on error")
        // get old options, if there are errors, we don't want to overwrite
        $old_options = get_option("wpconfigurecorsorigin_plugin_settings");
        return $old_options;
    } else {
        // successful validation and sanitation
        return $sanitized;
    }
}

function render_section_basic()
{
    echo "<p>CORS request can be cached by browser, so give it some time ;-)</p>";
    //echo '<p>This is the first (and only) section in my settings.</p>';
    //echo '<p>If you want to allow all origins, enter a \'*\'</p>';
}

function render_mode_field()
{
    $options = get_option('wpconfigurecorsorigin_plugin_settings');
    // on plugin install bool(false) by default?
    if ($options==false) {
        $options = array('mode'=>'inactive');
    } ?>
        <input type="radio" name="wpconfigurecorsorigin_plugin_settings[mode]" value="inactive" <?php checked('inactive', $options['mode'], true); ?>>Deactivated (default)<br>
        <input type="radio" name="wpconfigurecorsorigin_plugin_settings[mode]" value="custom" <?php checked('custom', $options['mode'], true); ?>>Allow custom HTTP origin (enter below)<br>
        <input type="radio" name="wpconfigurecorsorigin_plugin_settings[mode]" value="all" <?php checked('all', $options['mode'], true); ?>>Allow <span style="color:red;">all</span> HTTP origins (e.g. localhost-calls)
        
        <p><i>Allowing all HTTP origins could be a potential security risk. Please inform yourself about possible security problems.</i></p>
   <?php
}
function render_allow_origin_field()
{
    $options = get_option('wpconfigurecorsorigin_plugin_settings');

    if ($options == false || !array_key_exists('custom_allowed_origin', $options)) {
        $custom_allowed_origin = '';
    } else {
        $custom_allowed_origin = $options['custom_allowed_origin'];
    }
    
    printf(
        '<input type="url" name="%s" value="%s" placeholder="https://yourfrontenddomain.org/" size="40" />',
        esc_attr('wpconfigurecorsorigin_plugin_settings[custom_allowed_origin]'),
        esc_attr($custom_allowed_origin)
  );
  
  echo "<p><i>Example: https://yourfrontenddomain.org (no trailing slash)</i></p>";
  
}