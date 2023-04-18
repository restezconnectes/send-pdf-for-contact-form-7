<?php

defined( 'ABSPATH' )
	or die( 'No direct load ! ' );

function wpcf7pdf_btn_shortcode( $atts ) {

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
        
        $infos = cf7_sendpdf::get_byReference(esc_html($_GET['pdf-reference']));

        if( empty($infos->wpcf7pdf_id_form) ) { 
            return '';
        
        } else {
            $meta_values = get_post_meta( $infos->wpcf7pdf_id_form, '_wp_cf7pdf', true );

            if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == 'false' ) {
                
                if( isset($infos) ) {

                    if( empty($meta_values["text-link"]) or $meta_values["text-link"]=="" ) {
                        $downloadText = __('Download your PDF', WPCF7PDF_TEXT_DOMAIN);
                    } else if( isset($text) && $text!='') {
                        $downloadText = esc_html($text);
                    } else {
                        $downloadText = esc_html($meta_values["text-link"]);
                    }
                    if( $dashicons == 'none') {
                        $iconDashicons = '';
                    } else {
                        $iconDashicons = '<span class="dashicons '.esc_html($dashicons).'"></span> ';
                    }
                    if( $type == 'text' ) {
                        return '<a class="'.esc_html($class).'" href="'.esc_url($infos->wpcf7pdf_files).'" target="'.esc_html($target).'">'.$iconDashicons.$downloadText.'</a>';
                    } else {
                        return '<a href="'.esc_url($infos->wpcf7pdf_files).'" target="'.esc_html($target).'"><button class="'.esc_html($class).'" type="button">'.$iconDashicons.$downloadText.'</button></a>';
                    }

                } else {
                    return '<div style="text-align:center;width:80%;margin-left:auto;margin-right:right;background-color:#333;color:#ffffff;"><strong>ERROR Send PDF for Contact Form 7</strong><br />'.__('No data for this reference number', WPCF7PDF_TEXT_DOMAIN).' value:'.esc_html($meta_values["disable-insert"]).'</div>';
                }
                
            } else if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]== 'true' ) {

                return '<div style="text-align:center;width:80%;margin-left:auto;margin-right:right;background-color:#333;color:#ffffff;"><strong>ERROR Send PDF for Contact Form 7</strong><br />'.__('"Insert subscribtion in database" option is disabled!<br />Please enable "Insert subscribtion in database" option', WPCF7PDF_TEXT_DOMAIN).' value:'.esc_html($meta_values["disable-insert"]).'</div>';

            } else {
                return '';
            }
        }
        
    } else {
        return '';
    }
}
add_shortcode( 'wpcf7pdf_download', 'wpcf7pdf_btn_shortcode' );

function wpcf7pdf_test_shortcode( $atts ) {

	// Attributes
	extract( shortcode_atts(
		array(
			'class' => 'btn btn-large btn-primary',
            'size' => '18',
            'text' => 'This is a test',
		), $atts )
	);

    return '<div class="'.esc_html($class).'" style="text-align:center;width:80%;margin-left:auto;margin-right:auto;background-color:#333333;color:#ffffff;padding:5px;margin-top:15px;margin-bottom:15px;font-size:'.esc_html($size).'px;"><strong>'.esc_html($text).'</strong></div>';
}
add_shortcode( 'wpcf7pdf_test', 'wpcf7pdf_test_shortcode' );