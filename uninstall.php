<?php
/**
 * Désinstallation du plugin WP Contact Form 7 Send PDF
 */
function wpcf7pdf_uninstall() {
    
    global $wpdb;
    
    if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }
        
    $allposts = get_posts( 'numberposts=-1&post_type=wpcf7_contact_form&post_status=any' );
    foreach( $allposts as $postinfo ) {
        delete_post_meta( $postinfo->ID, '_wp_cf7pdf' );
        delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields' );
    }
    
    $wpcf7pdf_files_table = $wpdb->prefix.'wpcf7pdf_files';
    $sql = "DROP TABLE `$wpcf7pdf_files_table`";
    $wpdb->query($sql);
}
register_deactivation_hook(__FILE__, 'wpcf7pdf_uninstall');