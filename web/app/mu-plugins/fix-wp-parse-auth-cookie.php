<?php

/**
 * Fix WPML user_lang_by_authcookie() error when logged out.
 * Error is caused by passing $username = NULL into the WP_User constructor
 * in SitePress::user_lang_by_authcookie().
 *
 * Functions copied out of wp/wp-includes/pluggable.php.
 */
function wp_parse_auth_cookie($cookie = '', $scheme = '')
{
    if (empty($cookie)) {
        switch ($scheme) {
            case 'auth':
                $cookie_name = AUTH_COOKIE;
                break;
            case 'secure_auth':
                $cookie_name = SECURE_AUTH_COOKIE;
                break;
            case 'logged_in':
                $cookie_name = LOGGED_IN_COOKIE;
                break;
            default:
                if (is_ssl()) {
                    $cookie_name = SECURE_AUTH_COOKIE;
                    $scheme      = 'secure_auth';
                } else {
                    $cookie_name = AUTH_COOKIE;
                    $scheme      = 'auth';
                }
        }

        if (empty($_COOKIE[ $cookie_name ])) {
            return ["username" => ""];
        }
        $cookie = $_COOKIE[ $cookie_name ];
    }

    $cookie_elements = explode('|', $cookie);
    if (count($cookie_elements) !== 4) {
        return ["username" => ""];
        ;
    }

    list( $username, $expiration, $token, $hmac ) = $cookie_elements;
    $username = $username ?? "";

    return compact('username', 'expiration', 'token', 'hmac', 'scheme');
}

function wp_validate_auth_cookie($cookie = '', $scheme = '')
{
    $cookie_elements = wp_parse_auth_cookie($cookie, $scheme);
    if (! $cookie_elements || count($cookie_elements) < 5) {
        /**
         * Fires if an authentication cookie is malformed.
         *
         * @since 2.7.0
         *
         * @param string $cookie Malformed auth cookie.
         * @param string $scheme Authentication scheme. Values include 'auth', 'secure_auth',
         *                       or 'logged_in'.
         */
        do_action('auth_cookie_malformed', $cookie, $scheme);
        return false;
    }

    $scheme     = $cookie_elements['scheme'];
    $username   = $cookie_elements['username'];
    $hmac       = $cookie_elements['hmac'];
    $token      = $cookie_elements['token'];
    $expired    = $cookie_elements['expiration'];
    $expiration = $cookie_elements['expiration'];

    // Allow a grace period for POST and Ajax requests.
    if (wp_doing_ajax() || 'POST' === $_SERVER['REQUEST_METHOD']) {
        $expired += HOUR_IN_SECONDS;
    }

    // Quick check to see if an honest cookie has expired.
    if ($expired < time()) {
        /**
         * Fires once an authentication cookie has expired.
         *
         * @since 2.7.0
         *
         * @param string[] $cookie_elements {
         *     Authentication cookie components. None of the components should be assumed
         *     to be valid as they come directly from a client-provided cookie value.
         *
         *     @type string $username   User's username.
         *     @type string $expiration The time the cookie expires as a UNIX timestamp.
         *     @type string $token      User's session token used.
         *     @type string $hmac       The security hash for the cookie.
         *     @type string $scheme     The cookie scheme to use.
         * }
         */
        do_action('auth_cookie_expired', $cookie_elements);
        return false;
    }

    $user = get_user_by('login', $username);
    if (! $user) {
        /**
         * Fires if a bad username is entered in the user authentication process.
         *
         * @since 2.7.0
         *
         * @param string[] $cookie_elements {
         *     Authentication cookie components. None of the components should be assumed
         *     to be valid as they come directly from a client-provided cookie value.
         *
         *     @type string $username   User's username.
         *     @type string $expiration The time the cookie expires as a UNIX timestamp.
         *     @type string $token      User's session token used.
         *     @type string $hmac       The security hash for the cookie.
         *     @type string $scheme     The cookie scheme to use.
         * }
         */
        do_action('auth_cookie_bad_username', $cookie_elements);
        return false;
    }

    $pass_frag = substr($user->user_pass, 8, 4);

    $key = wp_hash($username . '|' . $pass_frag . '|' . $expiration . '|' . $token, $scheme);

    // If ext/hash is not present, compat.php's hash_hmac() does not support sha256.
    $algo = function_exists('hash') ? 'sha256' : 'sha1';
    $hash = hash_hmac($algo, $username . '|' . $expiration . '|' . $token, $key);

    if (! hash_equals($hash, $hmac)) {
        /**
         * Fires if a bad authentication cookie hash is encountered.
         *
         * @since 2.7.0
         *
         * @param string[] $cookie_elements {
         *     Authentication cookie components. None of the components should be assumed
         *     to be valid as they come directly from a client-provided cookie value.
         *
         *     @type string $username   User's username.
         *     @type string $expiration The time the cookie expires as a UNIX timestamp.
         *     @type string $token      User's session token used.
         *     @type string $hmac       The security hash for the cookie.
         *     @type string $scheme     The cookie scheme to use.
         * }
         */
        do_action('auth_cookie_bad_hash', $cookie_elements);
        return false;
    }

    $manager = WP_Session_Tokens::get_instance($user->ID);
    if (! $manager->verify($token)) {
        /**
         * Fires if a bad session token is encountered.
         *
         * @since 4.0.0
         *
         * @param string[] $cookie_elements {
         *     Authentication cookie components. None of the components should be assumed
         *     to be valid as they come directly from a client-provided cookie value.
         *
         *     @type string $username   User's username.
         *     @type string $expiration The time the cookie expires as a UNIX timestamp.
         *     @type string $token      User's session token used.
         *     @type string $hmac       The security hash for the cookie.
         *     @type string $scheme     The cookie scheme to use.
         * }
         */
        do_action('auth_cookie_bad_session_token', $cookie_elements);
        return false;
    }

    // Ajax/POST grace period set above.
    if ($expiration < time()) {
        $GLOBALS['login_grace_period'] = 1;
    }

    /**
     * Fires once an authentication cookie has been validated.
     *
     * @since 2.7.0
     *
     * @param string[] $cookie_elements {
     *     Authentication cookie components.
     *
     *     @type string $username   User's username.
     *     @type string $expiration The time the cookie expires as a UNIX timestamp.
     *     @type string $token      User's session token used.
     *     @type string $hmac       The security hash for the cookie.
     *     @type string $scheme     The cookie scheme to use.
     * }
     * @param WP_User  $user            User object.
     */
    do_action('auth_cookie_valid', $cookie_elements, $user);

    return $user->ID;
}
