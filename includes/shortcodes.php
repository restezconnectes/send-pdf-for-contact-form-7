<?php

defined( 'ABSPATH' )
	or die( 'No direct load ! ' );

function wpcf7pdf_analytics_shortcode( $atts ) {


	// Attributes
	extract( shortcode_atts(
		array(
			'class' => 'btn btn-large btn-primary',
            'target' => '_blank',
            'type' => 'button',
            'dashicons' => 'dashicons-download',
            'text' => '',
		), $atts )
	);
    global $wpdb;
    
    if( !empty($_GET['pdf-reference']) ) {
        
        $infos = cf7_sendpdf::get_byReference($_GET['pdf-reference']);
        $meta_values = get_post_meta( $infos->wpcf7pdf_id_form, '_wp_cf7pdf', true );

        if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == 'false' ) {
            
            if( isset($infos) ) {

                if( empty($meta_values["text-link"]) or $meta_values["text-link"]=="" ) {
                    $downloadText = __('Download your PDF', 'send-pdf-for-contact-form-7');
                } else if( isset($text) && $text!='') {
                    $downloadText = $text;
                } else {
                    $downloadText = $meta_values["text-link"];
                }
                if( $dashicons == 'none') {
                    $iconDashicons = '';
                } else {
                    $iconDashicons = '<span class="dashicons '.$dashicons.'"></span> ';
                }
                if( $type == 'text' ) {
                    return '<a class="'.$class.'" href="'.$infos->wpcf7pdf_files.'" target="'.$target.'">'.$iconDashicons.$downloadText.'</a>';
                } else {
                    return '<a href="'.$infos->wpcf7pdf_files.'" target="'.$target.'"><button class="'.$class.'" type="button">'.$iconDashicons.$downloadText.'</button></a>';
                }

            } else {
                return '<div style="text-align:center;width:80%;margin-left:auto;margin-right:right;background-color:#333;color:#ffffff;"><strong>ERROR Send PDF for Contact Form 7</strong><br />'.__('No data for this reference number', 'send-pdf-for-contact-form-7').' value:'.$meta_values["disable-insert"].'</div>';
            }
            
        } else if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]== 'true' ) {

            return '<div style="text-align:center;width:80%;margin-left:auto;margin-right:right;background-color:#333;color:#ffffff;"><strong>ERROR Send PDF for Contact Form 7</strong><br />'.__('"Insert subscribtion in database" option is disabled!<br />Please enable "Insert subscribtion in database" option', 'send-pdf-for-contact-form-7').' value:'.$meta_values["disable-insert"].'</div>';

        } else {
            return '';
        }
        
    } else {
        return '';
    }
}
add_shortcode( 'wpcf7pdf_download', 'wpcf7pdf_analytics_shortcode' );