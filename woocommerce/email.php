<?php


/**
 * Separate email for license key
 */

 // Hook into the order completed action
add_action('woocommerce_order_status_completed', 'schedule_license_key_email', 83, 1);

function schedule_license_key_email($order_id) {
    // Schedule the event to run after 10 seconds
    wp_schedule_single_event(time() + 10, 'send_license_key_email_event', array($order_id));
}


// Hook to the custom event
add_action('send_license_key_email_event', 'send_license_key_email', 10, 1);

// add_action('init', 'send_license_key_email');

function send_license_key_email($order_id) {
        
    // $order_id = 17543;
    // Get the order object
    $order = wc_get_order($order_id);
   
    // Get the user's email
    $email = $order->get_billing_email();

    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();
    $customer_name = $first_name . ' ' . $last_name;
    
    // Get the license keys associated with the order
    $license__keys = "";
    foreach ($order->get_items() as $item_id => $item) {
        $license_key = wc_get_order_item_meta($item_id, '_slm_lic_key', true);
        if (!empty($license_key)) {
            $license__keys = $license_key;
        }
    }
    // If there are license keys, send an email
    if (!empty($license__keys)) {
        global $wpdb;
        $license_details = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}lic_key_tbl WHERE license_key = %s", $license__keys));
           
        if ($license_details) {
            $expiry_date = $license_details->date_expiry;
        }
        
        
        $subject = 'Your License Key(s)';
       
        // Get the template content using EmailKit
        $template_id = 17537; // Replace with your actual template ID
        
       $template_content = get_post_meta($template_id, 'emailkit_template_content_html', true);

        if ($template_content) {
            // Replace placeholders in the template
            $message = str_replace('[CUSTOMER_NAME]', $customer_name, $template_content);
            $message = str_replace('[LICENSE_KEY]', $license__keys, $message);
            $message = str_replace('[EXPIRY_DATE]', $expiry_date, $message);
            
            // Set the email headers
            $headers = array('Content-Type: text/html; charset=UTF-8');
            // dd($template_content);
            // Send the email
            wp_mail($email, $subject, $message, $headers);
        }
    }
}