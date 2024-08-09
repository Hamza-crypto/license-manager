<?php


// add_action('wp_head', 'payment_complete');
function payment_complete($order_id)
{
    $order_id = 17804;
    if (is_page(782)) {
        wc_custom_slm_create_license_keys($order_id);
    }
}


function convert_timestamp_to_datetime($timestamp)
{
    $datetime = new DateTime("@$timestamp");
    $datetime->setTimezone(new DateTimeZone('UTC'));
    return $datetime->format('Y-m-d\TH:i:s\+00:00');
}

function custom_log($message, $level = 'info')
{
    // Define log file path
    $log_file = WP_CONTENT_DIR . '/logs/custom_log.log';

    // Create log message format
    $log_entry = date('Y-m-d H:i:s') . ' [' . $level . '] ' . $message . PHP_EOL;

    // Append to log file
    error_log($log_entry, 3, $log_file);
}


function license_exists($item_id)
{
    return wc_get_order_item_meta($item_id, '_slm_lic_key', true);
}

function extend_license_expiration_date($license_key, $new_expiry_date)
{
    global $wpdb;

    // $license_key = 'SLM-D351C-9E1BB-39191-31483-C59B6-62A18-10';
    $table_name = $wpdb->prefix . 'lic_key_tbl'; // Replace 'lic_key_tbl' with your actual table name

    $results = $wpdb->get_row("SELECT * FROM $table_name WHERE license_key = '$license_key'");

    $old_expiry = $results->date_expiry;

    $today = current_time('Y-m-d');

    if (strtotime($old_expiry) > strtotime($today)) {
        // Calculate remaining days from the current expiration date
        $remaining_days = (strtotime($old_expiry) - strtotime($today)) / (60 * 60 * 24);
    } else {
        $remaining_days = 0; // If the license is already expired, no remaining days
    }

    // Calculate the new expiration date by adding the remaining days to the new subscription period
    $new_expiry_timestamp = strtotime($new_expiry_date) + ($remaining_days * 24 * 60 * 60);
    $new_final_expiry_date = date('Y-m-d', $new_expiry_timestamp);

    // Update the expiration date in the database
    $wpdb->update(
        $table_name,
        array('date_expiry' => $new_final_expiry_date),
        array('license_key' => $license_key)
    );

    return $new_final_expiry_date;
}



function wc_custom_slm_create_license_keys($order_id)
{
    $order = wc_get_order($order_id);
    $purchase_id_ = $order->get_id();

    // Check for linked subscriptions
    $subscriptions = wcs_get_subscriptions_for_order($order);
    if (! empty($subscriptions)) {
        $subscription = reset($subscriptions);
        $subscription = $subscription->get_data();
    } else {
        $subscription = [];
    }

    global $user_id;

    $user_id = $order->get_user_id();
    $user_info = get_userdata($user_id);
    $get_user_meta = get_user_meta($user_id);
    $payment_meta['user_info']['first_name'] = $get_user_meta['billing_first_name'][0];
    $payment_meta['user_info']['last_name'] = $get_user_meta['billing_last_name'][0];
    $payment_meta['user_info']['email'] = $get_user_meta['billing_email'][0];
    $payment_meta['user_info']['company'] = $get_user_meta['billing_company'][0];

    // Collect license keys
    $licenses = array();
    $items = $order->get_items();


    foreach ($items as $item => $values) {

        $download_id = $product_id = $values['product_id'];

        if (! has_term('Licensed', 'product_cat', $product_id)) {
            continue;
        }

        $product = $values->get_product();

        // Get product meta data
        $product_metas = get_post_meta($product_id);

        $download_quantity = absint($values['qty']);
        //Get all existing licence keys of the product
        $order_item_lic_key = $values->get_meta('_slm_lic_key', false);
        $lic_to_add = $download_quantity - count($order_item_lic_key);

        $lic_to_add = $lic_to_add == 0 ? 1 : $lic_to_add;
        //Create keys only if there are not keys created already

        $license = license_exists($item);

        /**
         * Custom Method Created By Hamza
         */
        $expiration = set_expiration_date($product, $product_metas, $subscription);

        if($license != "") { //if license is present in database
            extend_license_expiration_date($license, $expiration);
            return;
        }

        for ($i = 1; $i <= $lic_to_add; $i++) {
            /**
             * Calculate Expire date
             * @since 1.0.3
             */
            $expiration = '';

            $renewal_period = (int)wc_slm_get_licensing_renewal_period($product_id);
            $renewal_term = wc_slm_get_licensing_renewal_period_term($product_id);

            $slm_billing_length = $renewal_period;
            $slm_billing_interval = $renewal_term;

            // if ($renewal_period == 'onetime') {
            //     $expiration = '0000-00-00';
            // }

            // elseif ($renewal_period == 30) {
            // 	$renewal_period = date('Y-m-d', strtotime('+' . 31 . ' days'));
            // }
            // else {
            //     $expiration = date('Y-m-d', strtotime('+' . $renewal_period . ' ' . $renewal_term));
            // }
            // SLM_Helper_Class::write_log('renewal_period -- '.$renewal_period  );
            // SLM_Helper_Class::write_log('exp -- ' . $expiration);
            // SLM_Helper_Class::write_log('term -- ' . $renewal_term);


            // Sites allowed get license meta from variation
            $sites_allowed = wc_slm_get_sites_allowed($product_id);

            // Get the custumer ID
            // $user_id = $order->get_user_id();
            $order_data = $order->get_data(); // The Order data

            ## Access Order Items data properties (in an array of values) ##
            $item_data = $values->get_data();
            $product_name = $item_data['name'];
            $product_id = $item_data['product_id'];
            $_license_current_version = get_post_meta($product_id, '_license_current_version', true);
            $_license_until_version = get_post_meta($product_id, '_license_until_version', true);
            $amount_of_licenses_devices = wc_slm_get_devices_allowed($product_id);
            $current_version = (int)get_post_meta($product_id, '_license_current_version', true);
            $license_type = get_post_meta($product_id, '_license_type', true);
            $lic_item_ref = get_post_meta($product_id, '_license_item_reference', true);

            // Transaction id
            $transaction_id = wc_get_payment_transaction_id($product_id);

            // Build item name
            $item_name = $product->get_title();

            // Build parameters
            $api_params = array();
            $api_params['slm_action'] = 'slm_create_new';
            $api_params['secret_key'] = KEY_API;
            $api_params['first_name'] = (isset($payment_meta['user_info']['first_name'])) ? $payment_meta['user_info']['first_name'] : '';
            $api_params['last_name'] = (isset($payment_meta['user_info']['last_name'])) ? $payment_meta['user_info']['last_name'] : '';
            $api_params['email'] = (isset($payment_meta['user_info']['email'])) ? $payment_meta['user_info']['email'] : '';
            $api_params['company_name'] = $payment_meta['user_info']['company'];
            $api_params['purchase_id_'] = $purchase_id_;
            $api_params['product_ref'] = $product_id; // TODO: get product id
            $api_params['txn_id'] = $purchase_id_;
            $api_params['max_allowed_domains'] = $sites_allowed;
            $api_params['max_allowed_devices'] = $amount_of_licenses_devices;
            $api_params['date_created'] = date('Y-m-d');
            $api_params['date_expiry'] = $expiration;
            $api_params['slm_billing_length'] = $slm_billing_length;
            $api_params['slm_billing_interval'] = $slm_billing_interval;
            $api_params['until'] = $_license_until_version;
            $api_params['current_ver'] = $_license_current_version;
            $api_params['subscr_id'] = $order->get_customer_id();
            $api_params['lic_type'] = $license_type;
            $api_params['item_reference'] = $lic_item_ref;

            //access_expires
            //SLM_Helper_Class::write_log('license_type -- ' . $license_type );
            // Send query to the license manager server
            $url = SLM_SITE_HOME_URL . '?' . http_build_query($api_params);
            $url = str_replace(array('http://', 'https://'), '', $url);
            $url = 'http://' . $url;
            $response = wp_remote_get($url, array('timeout' => 20, 'sslverify' => false));

            $license_key = wc_slm_get_license_key($response);

            // Collect license keys
            if ($license_key) {
                $licenses[] = array(
                    'item' => $item_name,
                    'key' => $license_key,
                    'expires' => $expiration,
                    'type' => $license_type,
                    'item_ref' => $lic_item_ref,
                    'slm_billing_length' => $slm_billing_length,
                    'slm_billing_interval' => $slm_billing_interval,
                    'status' => 'pending',
                    'version' => $_license_current_version,
                    'until' => $_license_until_version
                );
                $item_id = $values->get_id();
                wc_add_order_item_meta($item_id, '_slm_lic_key', $license_key);
                wc_add_order_item_meta($item_id, '_slm_lic_type', $license_type);
            }
        }
    }

}


function set_expiration_date($product, $product_metas, $subscription = [])
{
    $expiration = "0000-00-00";

    custom_log("subscription Type " . $product->get_type());
    // Get related subscriotion
    // Check if the trial has ended
    // if trial is remainig for subscriotn then set the expiration to trial end date
    // else set the expiration date to subscription price duraiton
    if($product->get_type() == 'subscription') {
        dump($subscription);

        $current_time = current_time('timestamp');

        // Get Subscription period info
        if (isset($product_metas['_subscription_period'][0]) && isset($product_metas['_subscription_period_interval'][0])) {
            $period = $product_metas['_subscription_period'][0];
            $interval = $product_metas['_subscription_period_interval'][0];
            custom_log($period);
            custom_log($interval);
        }


        // Get Trial period info
        if (isset($subscription['trial_period']) && isset($subscription['schedule_trial_end'])) {
            $trial_end_timestamp = $subscription['schedule_trial_end']->getTimestamp();

            if ($current_time < $trial_end_timestamp) {
                // Trial period is still active, return the trial end date
                $trial_end_date = date('Y-m-d', $trial_end_timestamp);
                custom_log("Trial is still active, ends on: " . $trial_end_date);
                return $trial_end_date;
            }
        }


        // If trial has ended or no trial info, set expiration based on subscription interval
        $expiration = date('Y-m-d', strtotime('+' . $interval . ' ' . $period));
        custom_log("Trial ended or no trial, subscription expiration set to: " . $expiration);
        return $expiration;

    } elseif($product->get_type() == 'simple') {
        return $expiration;
    }

    return $expiration;
}