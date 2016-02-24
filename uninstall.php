<?php
/**
 * Désinstallation du plugin WP WP Contact Form 7 Send PDF
 */
function wpcf7pdf_uninstall() {  
    if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }
        
    $allposts = get_posts( 'numberposts=-1&post_type=wpcf7_contact_form&post_status=any' );
    foreach( $allposts as $postinfo ) {
        delete_post_meta( $postinfo->ID, '_wp_cf7pdf' );
        delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields' );
    }
}
register_deactivation_hook(__FILE__, 'wpcf7pdf_uninstall');