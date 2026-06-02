<?php
/**
 * Plugin Name: Events Membership Popup
 * Description: Unclosable popup on Events Calendar pages. Bypassed only by admins
 *              or users with one of the allowed MemberPress membership IDs.
 *              Triggers on page click OR scroll — whichever comes first.
 * Version: 5.2.0
 * Author: Angelo Gabriel M. De Guzman
 * Text Domain: events-membership-popup
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =============================================================================
// CONFIG — Update membership IDs to match your MemberPress products
// =============================================================================
$GLOBALS['emp_allowed_ids'] = array( 15881, 27675, 35069, 27670, 27323, 27685, 27680 );

// =============================================================================
// HOOKS
// =============================================================================
add_action( 'wp_head',   'emp_inline_styles'  );
add_action( 'wp_footer', 'emp_render_popup'   );
add_action( 'wp_footer', 'emp_inline_scripts', 20 );

// =============================================================================
// HELPER: Is this an Events Calendar page?
// =============================================================================
function emp_is_events_page() {
    if ( function_exists( 'tribe_is_event' ) && tribe_is_event() ) return true;
    if ( function_exists( 'tribe_is_events_view' ) && tribe_is_events_view() ) return true;
    if ( function_exists( 'tribe_is_month' ) && tribe_is_month() ) return true;
    if ( function_exists( 'tribe_is_list_view' ) && tribe_is_list_view() ) return true;
    if ( is_singular( 'tribe_events' ) ) return true;
    if ( is_post_type_archive( 'tribe_events' ) ) return true;
    global $post;
    if ( isset( $post->post_type ) && $post->post_type === 'tribe_events' ) return true;
    if ( get_query_var( 'post_type' ) === 'tribe_events' ) return true;
    return false;
}

// =============================================================================
// HELPER: Is the current user an administrator?
// =============================================================================
function emp_user_is_admin() {
    return is_user_logged_in() && current_user_can( 'administrator' );
}

// =============================================================================
// HELPER: Does the user hold ANY of the allowed membership IDs?
// =============================================================================
function emp_user_has_allowed_membership() {
    if ( ! is_user_logged_in() ) return false;
    if ( ! class_exists( 'MeprUser' ) ) return false;

    $allowed_ids = $GLOBALS['emp_allowed_ids'];
    $user_id     = get_current_user_id();
    $mepr_user   = new MeprUser( $user_id );

    if ( method_exists( $mepr_user, 'active_product_subscriptions' ) ) {
        $active_ids = $mepr_user->active_product_subscriptions( 'ids' );
        if ( is_array( $active_ids ) ) {
            foreach ( $active_ids as $active_id ) {
                if ( in_array( (int) $active_id, $allowed_ids, true ) ) return true;
            }
        }
    }

    if ( class_exists( 'MeprProduct' ) && method_exists( $mepr_user, 'is_active_subscriber_of' ) ) {
        foreach ( $allowed_ids as $mid ) {
            $product = new MeprProduct( $mid );
            if ( $product instanceof MeprProduct && $mepr_user->is_active_subscriber_of( $product ) ) return true;
        }
    }

    if ( class_exists( 'MeprUtils' ) && method_exists( 'MeprUtils', 'is_user_subscribed_to_product' ) ) {
        foreach ( $allowed_ids as $mid ) {
            if ( MeprUtils::is_user_subscribed_to_product( $user_id, $mid ) ) return true;
        }
    }

    $meta = get_user_meta( $user_id, '_mepr_product_memberships', true );
    if ( ! empty( $meta ) ) {
        $meta_ids = maybe_unserialize( $meta );
        if ( is_array( $meta_ids ) ) {
            foreach ( $meta_ids as $meta_id ) {
                if ( in_array( (int) $meta_id, $allowed_ids, true ) ) return true;
            }
        }
    }

    if ( class_exists( 'MeprTransaction' ) && method_exists( $mepr_user, 'transactions' ) ) {
        $txns = $mepr_user->transactions();
        if ( is_array( $txns ) ) {
            foreach ( $txns as $txn ) {
                if ( isset( $txn->product_id, $txn->status ) &&
                     in_array( (int) $txn->product_id, $allowed_ids, true ) &&
                     in_array( $txn->status, array( 'complete', 'confirmed' ), true ) ) {
                    return true;
                }
            }
        }
    }

    return false;
}

// =============================================================================
// MASTER GATE
// =============================================================================
function emp_should_show_popup() {
    if ( ! emp_is_events_page() ) return false;
    if ( emp_user_is_admin() ) return false;
    if ( emp_user_has_allowed_membership() ) return false;
    return true;
}

// Note: Styles, HTML, and JavaScript functions follow below.
// Add emp_inline_styles(), emp_render_popup(), and emp_inline_scripts()
// from the full plugin source as needed.
