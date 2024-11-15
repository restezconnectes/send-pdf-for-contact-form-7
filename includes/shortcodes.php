<?php

defined( 'ABSPATH' )
	or die( 'No direct load ! ' );

function wpcf7pdf_btn_shortcode( $atts ) {

	// Attributes
	extract( shortcode_atts(
		array(
			'class' => 'button-primary',
            'target' => '_blank',
            'type' => 'button',
            'dashicons' => 'dashicons-download',
            'textbutton' => 'Download your PDF',
		), $atts )
	);
    
    if( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'go_reference') ) {

        if( isset($_GET['pdf-reference']) && !empty($_GET['pdf-reference']) ) {
            
            $infos = cf7_sendpdf::get_byReference(esc_html($_GET['pdf-reference']));

            if( empty($infos->wpcf7pdf_id_form) ) { 
                return '';
            } else {

                $meta_values = get_post_meta( $infos->wpcf7pdf_id_form, '_wp_cf7pdf', true );
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == 'false' ) {
                    
                    if( isset($infos) ) {

                        if( $dashicons == 'none') {
                            $iconDashicons = '';
                        } else {
                            $iconDashicons = '<span class="dashicons '.esc_html($dashicons).'"></span> ';
                        }
                        if( $type == 'text' ) {
                            return '<a class="'.esc_html($class).'" href="'.esc_url($infos->wpcf7pdf_files).'" target="'.esc_html($target).'">'.$iconDashicons.esc_html($textbutton).'</a>';
                        } else {
                            return '<a href="'.esc_url($infos->wpcf7pdf_files).'" target="'.esc_html($target).'"><button class="'.esc_html($class).'" type="button">'.$iconDashicons.esc_html($textbutton).'</button></a>';
                        }

                    } else {
                        return '<div style="text-align:center;width:80%;margin-left:auto;margin-right:right;background-color:#333;color:#ffffff;"><strong>ERROR Send PDF for Contact Form 7</strong><br />'.__('No data for this reference number', 'send-pdf-for-contact-form-7').' value:'.esc_html($meta_values["disable-insert"]).'</div>';
                    }
                    
                } else if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]== 'true' ) {

                    return '<div style="text-align:center;width:80%;margin-left:auto;margin-right:right;background-color:#333;color:#ffffff;"><strong>ERROR Send PDF for Contact Form 7</strong><br />'.__('"Insert subscribtion in database" option is disabled!<br />Please enable "Insert subscribtion in database" option', 'send-pdf-for-contact-form-7').' value:'.esc_html($meta_values["disable-insert"]).'</div>';

                } else {
                    return '';
                }
            }
            
        } else {
            return esc_html__('Reference is not available', 'send-pdf-for-contact-form-7');
        }
    } else {
        return '';
    }
}
add_shortcode( 'wpcf7pdf_download', 'wpcf7pdf_btn_shortcode' );

function wpcf7pdf_return_data( $atts ) {

	// Attributes
	extract( shortcode_atts(
		array(
            'tag' => '',
		), $atts )
	);
    
    if( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'go_reference') ) {

        if( isset($_GET['pdf-reference']) && !empty($_GET['pdf-reference']) ) {

            $infos = cf7_sendpdf::get_byReference(esc_html($_GET['pdf-reference']));
            if( empty($infos->wpcf7pdf_id_form) ) { 
                return '';
            } else {

                $meta_values = get_post_meta( $infos->wpcf7pdf_id_form, '_wp_cf7pdf', true );
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == 'false' ) {
                    
                    if( isset($infos) ) {

                        $entete = array("reference", "date");
                        $lignes = array();
                        $list = array();
                        $meta_fields = get_post_meta( intval($infos->wpcf7pdf_id_form), '_wp_cf7pdf_fields', true );
                        $valueData = unserialize($infos->wpcf7pdf_data);

                        foreach($meta_fields as $nb => $field) {
                            preg_match_all( '#\[(.*?)\]#', $field, $nameField );
                            array_push($entete, $nameField[1][0]);        
                        }

                        foreach( $valueData as $pdfList) {
                            array_push($list, $pdfList);
                        }
                        array_push($lignes, $list);

                        $tabData = array_combine($entete, $lignes[0]);
                        if( isset($tag) && $tag!='') {
                            return $tabData[$tag];
                        }

                    } else {
                        return '';
                    }
                } else {
                    return '';
                }
            }

        } else {
            return esc_html__('Reference is not available', 'send-pdf-for-contact-form-7');
        }
    } else {
        return '';
    }
}
add_shortcode( 'wpcf7pdf_data', 'wpcf7pdf_return_data' );

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