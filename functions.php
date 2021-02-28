<?php 
/**
 * Plugin Name:       WP Configure CORS-origin
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Configure the Access-Control-Allow-Origin for access from other domains via REST (used in headless/jamstack)
 * Version:           0.9
 * Author:            Matthias Andrasch
 * Author URI:        https://matthias-andrasch.eu
 * License:           CC0
 * License URI:       https://creativecommons.org/publicdomain/zero/1.0/legalcode
 * Text Domain:       wp-headless-cors-origin
 * Domain Path:       /languages
 */
 
 // TODO: add setting page for frontenddomain
 // TODO: add option to allow all for debug/dev calls from localhost (* or checkbox?)

// Thanks to https://dev.to/robmarshall/wordpress-rest-api-cors-issues-13p7
// Thanks to https://thoughtsandstuff.com/wordpress-rest-api-cors-issues/

 add_action('init', 'handle_preflight');
function handle_preflight() {
    $origin = get_http_origin();
    
    // for debug
    if (true && $origin === 'https://yourfrontenddomain') {
        header("Access-Control-Allow-Origin: " . HEADLESS_FRONTEND_URL);
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Credentials: true");
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
        if ('OPTIONS' == $_SERVER['REQUEST_METHOD']) {
            status_header(200);
            exit();
        }
    }
}
add_filter('rest_authentication_errors', 'rest_filter_incoming_connections');
function rest_filter_incoming_connections($errors) {
    $request_server = $_SERVER['REMOTE_ADDR'];
    $origin = get_http_origin();
    if ($origin !== 'https://yourfrontenddomain') return new WP_Error('forbidden_access', $origin, array(
        'status' => 403
    ));
    return $errors;
}