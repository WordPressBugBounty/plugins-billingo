<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

global $wpdb;

$options = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'wc_billingo_%'");

foreach ($options as $option) {
    delete_option($option);
}

delete_metadata('user', 0, 'billingo_notice_review_dismissed', '', true);

