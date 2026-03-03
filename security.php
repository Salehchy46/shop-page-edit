<?php
/**
 * Complete WordPress Security Enhancements
 * - Disable XML-RPC
 * - Remove WP version
 * - Limit login attempts (transient-based)
 * - Block all countries except Bangladesh (with caching)
 * - IP whitelist for admin/staff
 * - Optional strict mode when API fails
 *
 * IMPORTANT: Add define('DISALLOW_FILE_EDIT', true); to wp-config.php manually.
 */

// ============================
// 1. DISABLE XML-RPC
// ============================
add_filter( 'xmlrpc_enabled', '__return_false' );

// ============================
// 2. REMOVE WORDPRESS VERSION
// ============================
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// ============================
// 3. LIMIT LOGIN ATTEMPTS
// ============================
function wpb_limit_login_attempts_check() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5( $ip );
    $attempts = (int) get_transient( $transient_key );

    if ( $attempts >= 3 ) {
        wp_die(
            '<h1>Too many failed login attempts</h1>' .
            '<p>You have exceeded the maximum number of login attempts. Please try again after 15 minutes.</p>',
            'Login Blocked',
            [ 'response' => 403 ]
        );
    }
}
add_action( 'login_head', 'wpb_limit_login_attempts_check' );

function wpb_increment_login_attempts() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5( $ip );
    $attempts = (int) get_transient( $transient_key );
    set_transient( $transient_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
}
add_action( 'wp_login_failed', 'wpb_increment_login_attempts' );

function wpb_reset_login_attempts() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $transient_key = 'login_attempts_' . md5( $ip );
    delete_transient( $transient_key );
}
add_action( 'wp_login', 'wpb_reset_login_attempts' );

// ============================
// 4. BLOCK ALL COUNTRIES EXCEPT BANGLADESH (with whitelist)
// ============================
function block_by_country() {
    // --- CONFIGURATION ---
    // Allowed country code (ISO 3166-1 alpha-2) – currently Bangladesh
    $allowed_country = 'BD';

    // Whitelist IPs that bypass country blocking (add your own)
    $whitelist_ips = array(
        '123.456.789.0',   // Replace with your home/office IP
        '987.654.321.0'    // You can add more
    );

    // Cache duration in seconds (12 hours)
    $cache_duration = 12 * HOUR_IN_SECONDS;

    // Strict mode: if true, visitors are blocked when the API fails or returns no country.
    // If false, they are allowed (less secure but avoids false positives).
    $strict_mode = false;   // Set to true if you want to block when location can't be determined

    // --- DO NOT EDIT BELOW THIS LINE (unless you know what you're doing) ---
    $ip = $_SERVER['REMOTE_ADDR'];

    // 1. Whitelist check – allow specified IPs immediately
    if ( in_array( $ip, $whitelist_ips ) ) {
        return;
    }

    // 2. Skip private/internal IPs (localhost, LAN) – allow them
    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
        return;
    }

    // 3. Check cached country code
    $cache_key = 'country_code_' . md5( $ip );
    $country_code = get_transient( $cache_key );

    if ( false === $country_code ) {
        // Fetch country code from ip-api.com
        $response = wp_remote_get( "http://ip-api.com/json/{$ip}?fields=countryCode", [
            'timeout' => 3,
        ] );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            // API call failed
            if ( $strict_mode ) {
                wp_die(
                    '<h1>Access Denied</h1>' .
                    '<p>Unable to verify your location. Access denied.</p>',
                    'Forbidden',
                    [ 'response' => 403 ]
                );
            } else {
                return; // Allow access
            }
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['countryCode'] ) ) {
            $country_code = $data['countryCode'];
            set_transient( $cache_key, $country_code, $cache_duration );
        } else {
            // No country code in response
            if ( $strict_mode ) {
                wp_die(
                    '<h1>Access Denied</h1>' .
                    '<p>Your location could not be determined. Access denied.</p>',
                    'Forbidden',
                    [ 'response' => 403 ]
                );
            } else {
                return; // Allow access
            }
        }
    }

    // 4. Block if country is not allowed
    if ( $country_code !== $allowed_country ) {
        wp_die(
            '<h1>Access Denied</h1>' .
            '<p>This website is only available from within Bangladesh.</p>',
            'Forbidden',
            [ 'response' => 403 ]
        );
    }
}
add_action( 'init', 'block_by_country' );