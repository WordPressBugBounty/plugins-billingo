<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Az összes opció betöltése (WordPress cache-ből jön, nem DB-ből)
$all_options = wp_load_alloptions();

if ( $all_options ) {
    foreach ( $all_options as $option_name => $value ) {
        if ( str_starts_with( $option_name, 'wc_billingo_' ) ) {
            delete_option( $option_name );
        }
    }
}

// 2. User meta törlés
delete_metadata( 'user', 0, 'billingo_notice_review_dismissed', '', true );