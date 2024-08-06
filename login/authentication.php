<?php

/**
 *  Login Redirect Features
 */

 // Redirect to custom login page on authentication failure
function custom_login_failed_redirect() {
    $login_page = home_url('/login/');
    wp_redirect($login_page . '?login=failed');
    exit;
}
add_action('wp_login_failed', 'custom_login_failed_redirect');

// Redirect to custom login page if not logged in
function custom_authenticate_redirect($user, $username, $password) {
    if ($username == '' || $password == '') {
        $login_page = home_url('/login/');
        wp_redirect($login_page . '?login=empty');
        exit;
    }
    return $user;
}
add_filter('authenticate', 'custom_authenticate_redirect', 30, 3);

// Redirect to custom login page if the user tries to access wp-login.php
function custom_login_page_redirect() {
    global $pagenow;
    if ($pagenow == 'wp-login.php' && $_SERVER['REQUEST_METHOD'] == 'GET') {
        $login_page = home_url('/login/');
        wp_redirect($login_page);
        exit;
    }
}
add_action('init', 'custom_login_page_redirect');


function display_login_error_messages() {
    if (isset($_GET['login'])) {
        $login = $_GET['login'];
        if ($login == 'failed') {
            return '<div class="login-error">Invalid username or password.</div>';
        } elseif ($login == 'empty') {
            return '<div class="login-error">Please fill in all fields.</div>';
        } elseif ($login == 'exists') {
            return '<div class="login-error">User already exists.</div>';
        }
    }
    return '';
}
add_shortcode('login_error_messages', 'display_login_error_messages');


function redirect_account_pages_to_login() {
    // Specify your login page URL
    $login_page = home_url('/login/');
    
    // Check if the URL contains "account"
    if (strpos($_SERVER['REQUEST_URI'], 'account') !== false && !is_user_logged_in()) {
        wp_redirect($login_page);
        exit;
    }
}
add_action('template_redirect', 'redirect_account_pages_to_login');

function custom_login_error_styles() {
    // Check if we are on the login page and if there is an error message
    if (is_page('login') && (isset($_GET['login']) || isset($_GET['redirected']))) {
        // Enqueue the custom CSS for login errors
        wp_enqueue_style('custom-login-error-styles', plugins_url('../assets/css/custom.css', __FILE__));
    }
}
add_action('wp_enqueue_scripts', 'custom_login_error_styles');