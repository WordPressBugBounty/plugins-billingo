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

// 3. Naplók törlése (a feltöltések alatti, webhelyenként egyedi nevű mappa)
$billingo_uploads = function_exists( 'wp_upload_dir' ) ? wp_upload_dir( null, false ) : array();

if ( ! empty( $billingo_uploads['basedir'] ) ) {
    foreach ( (array) glob( rtrim( $billingo_uploads['basedir'], '/' ) . '/billingo-logs-*', GLOB_ONLYDIR ) as $billingo_log_dir ) {
        foreach ( (array) scandir( $billingo_log_dir ) as $billingo_log_file ) {
            if ( is_file( $billingo_log_dir . '/' . $billingo_log_file ) ) {
                @unlink( $billingo_log_dir . '/' . $billingo_log_file );
            }
        }

        @rmdir( $billingo_log_dir );
    }
}
