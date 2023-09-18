<?php

defined( 'ABSPATH' ) or die( 'Not allowed' );

global $current_user;
global $_wp_admin_css_colors;
global $post;

$admin_color = get_user_option( 'admin_color', get_current_user_id() );
$colors      = $_wp_admin_css_colors[$admin_color]->colors;

$upload_dir = wp_upload_dir();
if ( defined( 'WPCF7_UPLOADS_TMP_DIR' ) ) {
    $tmpDirectory = WPCF7_UPLOADS_TMP_DIR;
} else {
    $tmpDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads/tmp';
}

/* Update des paramètres */
if( (isset($_POST['action']) && isset($_POST['idform']) && $_POST['action'] == 'update') && isset($_POST['security-sendform']) && wp_verify_nonce($_POST['security-sendform'], 'go-sendform') ) {

    if( isset($_POST['deleteconfig']) && $_POST['deleteconfig']=="true") {

        delete_post_meta( $_POST['idform'], '_wp_cf7pdf' );
        delete_post_meta( $_POST['idform'], '_wp_cf7pdf_fields' );
        delete_post_meta( $_POST['idform'], '_wp_cf7pdf_fields_scan' );
        $_POST['idform'] = '';
        
        wp_redirect( 'admin.php?page=wpcf7-send-pdf&deleted=1' );

    } else {

        if( isset($_POST['wp_cf7pdf_settings']['pdf-uploads-delete']) && $_POST['wp_cf7pdf_settings']['pdf-uploads-delete']=="true" ) {
            
            $dossier_traite = cf7_sendpdf::wpcf7pdf_folder_uploads(esc_html($_POST['idform']));
            
            if( isset($dossier_traite) && is_dir($dossier_traite) ) {
                
                $repertoire = opendir($dossier_traite); // On définit le répertoire dans lequel on souhaite travailler.
    
                while (false !== ($fichier = readdir($repertoire))) // On lit chaque fichier du répertoire dans la boucle.
                {
                    $chemin = $dossier_traite."/".$fichier; // On définit le chemin du fichier à effacer.

                    // Si le fichier n'est pas un répertoire…
                    if ($fichier != ".." AND $fichier != "." AND !is_dir($fichier)) {
                        wp_delete_file($chemin);
                    }
                }
                closedir($repertoire);
                
                echo '<div id="message" class="updated fade"><p><strong>'.__('The upload folder has been deleted.', WPCF7PDF_TEXT_DOMAIN).'</strong></p></div>';
            }

        }

        $updateSetting = cf7_sendpdf::wpcf7pdf_update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_settings"], '_wp_cf7pdf');

        if ( isset($_POST["wp_cf7pdf_tags"]) ) {
            $updateSettingTags = cf7_sendpdf::wpcf7pdf_update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_tags"], '_wp_cf7pdf_fields');
        }
        if ( isset($_POST["wp_cf7pdf_tags_scan"]) ) {
            $updateSettingTagsScan = cf7_sendpdf::wpcf7pdf_update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_tags_scan"], '_wp_cf7pdf_fields_scan');
        }
        
        if( isset($updateSetting) && $updateSetting == true) {
            $options_saved = true;
            echo '<div id="message" class="updated fade"><p><strong>'.__('Options saved.', WPCF7PDF_TEXT_DOMAIN).'</strong></p></div>';
        }
    }

}

if( isset($_POST['idform']) && isset($_POST['truncate_table']) && $_POST['truncate_table'] == 'true' && wp_verify_nonce($_POST['security-sendform'], 'go-sendform') ) {

    $DeleteList = cf7_sendpdf::truncate();
    if( $DeleteList == true ) {
        echo '<div id="message" class="updated fade"><p><strong>'.__('All the data has been deleted.', WPCF7PDF_TEXT_DOMAIN).'</strong></p></div>';
    }
}
if( (isset($_POST['wpcf7_action']) && isset($_POST['idform']) && $_POST['wpcf7_action'] == 'listing_settings') ) {

    if(!wp_verify_nonce($_POST['wpcf7_listing_nonce'], 'wpcf7_listing_nonce'))
        return;

    if(!current_user_can('manage_options'))
        return;

    if(empty($_POST['listing_limit']))
    return;

    update_post_meta(sanitize_text_field($_POST['idform']), '_wp_cf7pdf_limit', sanitize_text_field($_POST['listing_limit']));

    echo '<div id="message" class="updated fade"><p><strong>' . __('Limit updating successfully!', WPCF7PDF_TEXT_DOMAIN) . '</strong></p></div>';
}

if( isset($_POST['action']) && $_POST['action'] == 'reset' ) {

    if( ! wp_verify_nonce($_POST['security-resettmp'], 'go-resettmp') )
        return;

    if ( defined( 'WPCF7_UPLOADS_TMP_DIR' ) ) {
        update_option('wpcf7pdf_path_temp', WPCF7_UPLOADS_TMP_DIR);
    } else {
        update_option('wpcf7pdf_path_temp', $upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp');
    }
    
}

if( isset($_GET['deleted']) && $_GET['deleted']==1 ) {
    echo '<div id="message" class="updated fade"><p><strong>'.__('All settings hare been deleted.', WPCF7PDF_TEXT_DOMAIN).'</strong></p></div>';
}
?>
<script type="text/javascript">
jQuery.fn.selectText = function () {
    return jQuery(this).each(function (index, el) {
        if (document.selection) {
            var range = document.body.createTextRange();
            range.moveToElementText(el);
            range.select();
        } else if (window.getSelection) {
            selection = window.getSelection();        
            range = document.createRange();
            range.selectNodeContents(el);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    });
}
jQuery(document).ready(function() {

  jQuery( ".postbox .hndle" ).on( "mouseover", function() {
    jQuery( this ).css( "cursor", "pointer" );
  });
  /* Sliding the panels */
  jQuery(".postbox").on('click', '.handlediv', function(){
    jQuery(this).siblings(".inside").slideToggle();
  });
  jQuery(".postbox").on('click', '.hndle', function(){
    jQuery(this).siblings(".inside").slideToggle();
  });
    
});
</script>
<style type="text/css">
.CodeMirror{border: 1px solid #eee;height: auto;}
.customDashicons{color: #444444!important;box-sizing: content-box;padding: 15px;width: 40px;height: 40px;white-space: nowrap;font-size: 40px;line-height: 1;cursor: pointer;float: left;}
</style>
<div id="wpcf7-general" class="wrap">

    <h2 style="font-size: 23px;font-weight: 400;padding: 9px 15px 4px 0px;line-height: 29px;">
        <?php _e('Send PDF for Contact Form 7 - Settings', WPCF7PDF_TEXT_DOMAIN); ?><sup><?php echo 'V.'.WPCF7PDF_VERSION; ?></sup>
    </h2>

    <?php if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) { ?>
    <div id="wpcf7-bandeau">
        <table width="100%" cellspacing="20">
            <tr>
                <td style="text-align:center;" valign="middle" width="33%">
                    <?php
                        $forms = WPCF7_ContactForm::find();
                        if ( count($forms) == 0 ) {
                            printf( __('No forms have not been found. %s', WPCF7PDF_TEXT_DOMAIN), '<a href="'.admin_url('admin.php?page=wpcf7').'">'.__('Create your first form here.', WPCF7PDF_TEXT_DOMAIN).'</a>');
                        } else {
                    ?>
                    <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" name="displayform" id="displayform">
                        <input type="hidden" name="page" value="wpcf7-send-pdf"/>
                        <?php wp_nonce_field('go-sendform', 'security-sendform'); ?>
                        <select name="idform" id="idform" class="wpcf7-form-field" onchange="this.form.submit();">
                            <option value=""><?php echo htmlspecialchars(__('* Select a form *', WPCF7PDF_TEXT_DOMAIN)); ?></option>
                            <?php
                                $selected = '';
                               
                                foreach($forms as $form) {
                                    if(isset($_POST['idform']) ) {
                                        $selected = ($form->id() == sanitize_text_field($_POST['idform'])) ? "selected" : "";
                                    }
                                    $formPriority = '';
                                    $formNameEscaped = htmlentities($form->title(), ENT_QUOTES | ENT_IGNORE, 'UTF-8');
                                    echo '<option value="'.sanitize_text_field($form->id()).'" '.$selected.'>'.$formNameEscaped.'</option>';
                                }
                            ?>
                        </select>
                    </form>
                    <?php 
                        if( $tmpDirectory != get_option('wpcf7pdf_path_temp') ) {
                            _e('Your TMP folder is bad.', WPCF7PDF_TEXT_DOMAIN);
                            ?><!-- <?php echo $tmpDirectory; ?> ==> <?php echo get_option('wpcf7pdf_path_temp'); ?>-->
                            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" name="resettmp" id="resettmp">
                                <?php wp_nonce_field('go-resettmp', 'security-resettmp'); ?>
                                <input type="hidden" name="action" value="reset"/>
                                <input type="submit" value="<?php _e('Fix it!', WPCF7PDF_TEXT_DOMAIN); ?>" style="background-color:#656830;color:#fff;border:1px solid #656830;" />
                            </form>
                            <?php
                        }
                    ?>
                    <?php } ?>
                </td>
                <td style="text-align:center;" width="33%">
                        <div id="wpmimgcreated">
                            <a href="https://MadeBy.RestezConnectes.fr" title="Created by MadeByRestezConnectes.fr" class="wpcf7-link" alt="Created by MadeByRestezConnectes.fr" target="_blank" onfocus="this.blur();"><img class="wpmresponsive" src="<?php echo plugins_url('send-pdf-for-contact-form-7/images/logo-madeby-restezconnectes.png'); ?>" width="250" valign="bottom"  /></a>
                        </div>
                    <p><?php printf( __('Read %s here !', WPCF7PDF_TEXT_DOMAIN), '<a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/" class="wpcf7-link" target="_blank" onfocus="this.blur();">'.__('Tutorial', WPCF7PDF_TEXT_DOMAIN).'</a>' ); ?></p>
                </td>
                <td style="text-align:right;" width="33%">
                    <!-- FAIRE UN DON SUR PAYPAL -->
                    <div style="font-size:0.8em;">
                        <div style="width:350px;margin-left:auto;margin-right:auto;padding:5px;">
                            <a href="https://paypal.me/RestezConnectes/10" onfocus="this.blur();" target="_blank" class="wpcf7pdfclassname">
                                <img src="<?php echo plugins_url('send-pdf-for-contact-form-7/images/donate.png'); ?>" valign="bottom" width="64" /> <?php _e('Donate now!', WPCF7PDF_TEXT_DOMAIN); ?>
                            </a>
                        </div>
                    </div>
                    <!-- FIN FAIRE UN DON -->
                </td>
            </tr>
        </table>
    </div>

    <?php
    if( isset($_POST['idform']) && ( (isset( $_REQUEST['security-sendform'] ) && wp_verify_nonce($_POST['security-sendform'], 'go-sendform')) || (isset( $_REQUEST['wpcf7_import_nonce'] ) && wp_verify_nonce($_POST['wpcf7_import_nonce'], 'go_import_nonce'))) ) {

        $idForm = esc_html($_POST['idform']);
        $meta_values = get_post_meta( $idForm, '_wp_cf7pdf', true );
        $meta_form = get_post_meta( $idForm, '_form', true);

        // Genere le nom du PDF
        $nameOfPdf = cf7_sendpdf::wpcf7pdf_name_pdf($idForm);
        
        /**********************************************/
        /******** ON GENERE UN PDF DE PREVIEW *********/
        /**********************************************/
        // On récupère le dossier upload de WP
        $upload_dir = wp_upload_dir();
        $createDirectory = cf7_sendpdf::wpcf7pdf_folder_uploads($idForm);
        
        $custom_tmp_path = get_option('wpcf7pdf_path_temp');
        
        // On récupère le format de date dans les paramètres
        $date_format = get_option( 'date_format' );
        $hour_format = get_option( 'time_format' );

        // Definition des marges par defaut
        $marginHeader = 10;
        $marginTop = 40;
        $marginBottomHeader = 10;
        $marginLeft = 15;
        $marginRight = 15;
        $setAutoTopMargin = 'stretch';
        $setAutoBottomMargin = 'stretch';

        // Definition de la taille, le format de page et la font par defaut
        $fontsizePdf = 9;
        $fontPdf = 'dejavusanscondensed';
        $formatPdf = 'A4-P';

        // Definition des dates par defaut
        $dateField = date_i18n(esc_html($date_format), current_time('timestamp'));
        $timeField = date_i18n(esc_html($hour_format), current_time('timestamp'));

        // Definition des dimensions du logo par defaut
        $width = 150;
        $height = 80;

        // On efface l'ancien pdf renommé si il y a (on garde l'original)
        if( file_exists($createDirectory.'/preview.pdf') ) {
            wp_delete_file($createDirectory.'/preview.pdf');
        }

        if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

            if( isset($meta_values['pdf-type']) && isset($meta_values['pdf-orientation']) ) {
                $formatPdf = esc_html($meta_values['pdf-type'].$meta_values['pdf-orientation']);
            }
            if( isset($meta_values['pdf-font'])  ) {
                $fontPdf = esc_html($meta_values['pdf-font']);
            }
            if( isset($meta_values['pdf-fontsize']) && is_numeric($meta_values['pdf-fontsize']) ) {
                $fontsizePdf = esc_html($meta_values['pdf-fontsize']);
            }
            
            require WPCF7PDF_DIR . 'mpdf/vendor/autoload.php';
            
            if( isset($meta_values["margin_header"]) && $meta_values["margin_header"]!='' ) { $marginHeader = esc_html($meta_values["margin_header"]); }
            if( isset($meta_values["margin_top"]) && $meta_values["margin_top"]!='' ) { $marginTop = esc_html($meta_values["margin_top"]); }            
            if( isset($meta_values["margin_left"]) && $meta_values["margin_left"]!='' ) { $marginLeft = esc_html($meta_values["margin_left"]); }
            if( isset($meta_values["margin_right"]) && $meta_values["margin_right"]!='' ) { $marginRight = esc_html($meta_values["margin_right"]); }

            $setDirectionality = 'ltr';
            if( isset($meta_values["set_directionality"]) && $meta_values["set_directionality"]!='' ) {  $setDirectionality = esc_html($meta_values["set_directionality"]);  }

            if( isset($meta_values['pdf-type']) && isset($meta_values['pdf-orientation']) ) {

                $formatPdf = esc_html($meta_values['pdf-type'].$meta_values['pdf-orientation']);
                $mpdfConfig = array(
                    'mode' =>
                    'utf-8',
                    'format' => $formatPdf,
                    'margin_header' => $marginHeader,
                    'margin_top' => $marginTop,
                    'margin_left' => $marginLeft,    	// 15 margin_left
                    'margin_right' => $marginRight,    	// 15 margin right
                    'default_font' => $fontPdf,
                    'default_font_size' => $fontsizePdf,
                );

            } else if( isset($meta_values['fillable_data']) && $meta_values['fillable_data']== 'true') {

                $mpdfConfig = array(
                    'mode' => 'c',
                    'format' => $formatPdf,
                    'margin_header' => $marginHeader,
                    'margin_top' => $marginTop,
                    'default_font' => $fontPdf,
                    'default_font_size' => $fontsizePdf,
                    'tempDir' => $custom_tmp_path,
                    'margin_left' => $marginLeft,    	// 15 margin_left
                    'margin_right' => $marginRight,    	// 15 margin right
                );

            } else {

                $mpdfConfig = array(
                    'mode' => 'utf-8',
                    'format' => 'A4-L',
                    'margin_header' => $marginHeader,
                    'margin_top' => $marginTop,
                    'default_font' => $fontPdf,
                    'default_font_size' => $fontsizePdf,
                    'tempDir' => $custom_tmp_path,
                    'margin_left' => $marginLeft,    	// 15 margin_left
                    'margin_right' => $marginRight,    	// 15 margin right
                );
                
            }
            
            $mpdf = new \Mpdf\Mpdf($mpdfConfig);
            $mpdf->autoScriptToLang = true;
            $mpdf->baseScript = 1;
            $mpdf->autoVietnamese = true;
            $mpdf->autoArabic = true;
            $mpdf->autoLangToFont = true;
            $mpdf->SetTitle(get_the_title($idForm));
            $mpdf->SetCreator(get_bloginfo('name'));
            $mpdf->SetDirectionality($setDirectionality);
            $mpdf->ignore_invalid_utf8 = true;
            $mpdf->simpleTables = false;

            $mpdfCharset = 'utf-8';
            if( isset($meta_values["charset"]) && $meta_values["charset"]!='utf-8' ) {
                $mpdfCharset = esc_html($meta_values["charset"]);
            }
            $mpdf->allow_charset_conversion=true;  // Set by default to TRUE
            $mpdf->charset_in=''.$mpdfCharset.'';

            if( empty($meta_values["margin_auto_header"]) || ( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"]=='' ) ) { $meta_values["margin_auto_header"] = 'stretch'; }
            if( empty($meta_values["margin_auto_header"]) || ( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"]=='' ) ) { $meta_values["margin_auto_bottom"] = 'stretch'; }

            if( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"]!='' ) { $setAutoBottomMargin = esc_html($meta_values["margin_auto_header"]); }
            if( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"]!='' ) { $setAutoBottomMargin = esc_html($meta_values["margin_auto_bottom"]); }

            $mpdf->setAutoTopMargin = $setAutoBottomMargin;
            $mpdf->setAutoBottomMargin = $setAutoBottomMargin;

            if( isset($meta_values['image_background']) && $meta_values['image_background']!='' ) {
                 
                $mpdf->SetDefaultBodyCSS('background', "url('".esc_url($meta_values['image_background'])."')");
                $mpdf->SetDefaultBodyCSS('background-image-resize', 6);
            }
            
            // LOAD a stylesheet
            if( isset($meta_values['stylesheet']) && $meta_values['stylesheet']!='' ) {
                $stylesheet = file_get_contents(esc_url($meta_values['stylesheet']));
                $mpdf->WriteHTML($stylesheet,1);	// The parameter 1 tells that this is css/style only and no body/html/text
            }

            // Adding FontAwesome CSS 
            $mpdf->WriteHTML('<style>
            .fa { font-family: fontawesome; }
            .fas { font-family: fontawesome-solid; }
            .fab { font-family: fontawesome-brands;}
            .far { font-family: fontawesome-regular;}
            .dashicons { font-family: dashicons;}
            </style>');

            // Adding Custom CSS            
            if( isset($meta_values['custom_css']) && $meta_values['custom_css']!='' ) {
                $mpdf->WriteHTML('<style>'.esc_html($meta_values['custom_css']).'</style>');
            }
            
            $entetePage = '';
            if( isset($meta_values["image"]) && !empty($meta_values["image"]) ) {
                if( ini_get('allow_url_fopen')==1) {
                    $image_path = str_replace(get_bloginfo('url'), ABSPATH, $meta_values['image']);
                    list($width, $height, $type, $attr) = getimagesize($image_path);
                }
                $imgAlign = 'left';
                if( isset($meta_values['image-alignment']) ) {
                    $imgAlign = esc_html($meta_values['image-alignment']);
                }
                if( empty($meta_values['image-width']) ) { $imgWidth = esc_html($width); } else { $imgWidth = esc_html($meta_values['image-width']);  }
                if( empty($meta_values['image-height']) ) { $imgHeight = esc_html($height); } else { $imgHeight = esc_html($meta_values['image-height']);  }

                $attribut = 'width='.esc_html($imgWidth).' height="'.esc_html($imgHeight).'"';
                $entetePage = '<div style="text-align:'.esc_html($imgAlign).';"><img src="'.esc_url($meta_values["image"]).'" '.esc_html($attribut).' /></div>';

                if( isset($meta_values["margin_bottom_header"]) && $meta_values["margin_bottom_header"]!='' ) { $marginBottomHeader = esc_html($meta_values["margin_bottom_header"]); }
                $mpdf->WriteHTML('<p style="margin-bottom:'.esc_html($marginBottomHeader).'px;">&nbsp;</p>');
            }
            $mpdf->SetHTMLHeader($entetePage, 'O', true);

            // définit le contenu du PDf
            $messageText = wp_kses(trim($meta_values['generate_pdf']), $this->wpcf7pdf_autorizeHtml());

            $tagSeparate = '';
            if( isset($meta_values["separate"]) ) {
                if( $meta_values["separate"] == 'none' ) { $tagSeparate = ''; }
                if( $meta_values["separate"] == 'comma' ) { $tagSeparate = ', '; }
                if( $meta_values["separate"] == 'space') { $tagSeparate = ' '; }
                if( $meta_values["separate"] == 'dash') { $tagSeparate = '- '; }
                if( $meta_values["separate"] == 'star') { $tagSeparate = '<i class="fas">&#xf621</i> '; }
                if( $meta_values["separate"] == 'rightarrow') { $tagSeparate = '<i class="fas">&#xf061</i> '; }
                if( $meta_values["separate"] == 'double-right-arrow') { $tagSeparate = '<i class="fas">&#xf101</i> '; }
                if( $meta_values["separate"] == 'cornerarrow') { $tagSeparate = '<i class="fas">&#xf064</i> '; }
                
            }
            $tagSeparateAfter = ' ';
            if( isset($meta_values["separate_after"]) ) {
                if( $meta_values["separate_after"] == 'none' ) { $tagSeparateAfter = ''; }
                if( $meta_values["separate_after"] == 'comma' ) { $tagSeparateAfter = ', '; }
                if( $meta_values["separate_after"] == 'space') { $tagSeparateAfter = ' '; }
                if( $meta_values["separate_after"] == 'linebreak') { $tagSeparateAfter = '<br />'; }
            }
        
            /**
             * GESTION DES IMAGES UPLOADEES / AVATAR
             */
            // read all image tags into an array
            preg_match_all('/<img[^>]+>/i', $messageText, $imgTags);
            for ($i = 0; $i < count($imgTags[0]); $i++) {
                // get the source string
                preg_match('/src="([^"]+)/i', $imgTags[0][$i], $imageTag);
                   // remove opening 'src=' tag, can`t get the regex right
                $origImageSrc = str_ireplace( 'src="', '',  $imageTag[0]);
                if( strpos( $origImageSrc, 'http' ) === false ) {                
                    $messageText = str_replace( $origImageSrc, WPCF7PDF_URL.'images/temporary-image.jpg', $messageText);
                }
            }
        
            // replace tag by avatar picture
            $user = wp_get_current_user();
            if ( $user ) :
                $messageText = str_replace('[avatar]', esc_url( get_avatar_url( $user->ID ) ), $messageText);
            endif;
            /**
             * FIN
             */

            $contact_form = WPCF7_ContactForm::get_instance($idForm);
            $contact_tag = $contact_form->scan_form_tags();
            $form_tag = new WPCF7_FormTag( $contact_tag[0] );
            $contentPdfTags = cf7_sendpdf::wpcf7pdf_mailparser($messageText);

            foreach ( (array) $contentPdfTags as $name_tags ) {

                $found_key = cf7_sendpdf::wpcf7pdf_foundkey($contact_tag, $name_tags[1]);

                $thisTagRaw = false;
                if( isset($contact_tag[$found_key]['basetype']) ) {
                    $basetype = $contact_tag[$found_key]['basetype'];
                }

                $tagOptions = '';
                if( isset( $contact_tag[$found_key]['options'] ) ) {
                    $tagOptions = $contact_tag[$found_key]['options'];
                }

                if ( preg_match( '/^_raw_(.+)$/', $name_tags[1], $matches ) ) {
                    $thisTagRaw = true;
                }

                if( isset($basetype) && ($basetype==='text' || $basetype==='email') ) {                  

                    if (isset($meta_values['data_input']) && $meta_values['data_input']=='true') {

                        $inputSelect = '<input type="text" class="wpcf7-input" name="'.esc_html($name_tags[1]).'" value="" />';

                    } else {

                        $inputSelect = '';
                        
                    }
                    $messageText = str_replace(esc_html($name_tags[0]), $inputSelect, $messageText);

                } else if( isset($basetype) && $basetype==='checkbox' && $thisTagRaw===false ) {

                    $inputCheckbox = '';
                    $i = 1;
                    
                    foreach( $contact_tag[$found_key]['values'] as $id=>$val ) {

                        $valueTag = wpcf7_mail_replace_tags($name_tags[0]);

                        if (isset($meta_values['data_input']) && $meta_values['data_input']=='true') {

                            if( in_array('label_first', $tagOptions) ) {
                                $inputCheckbox .= ''.$tagSeparate.''.esc_html($val).' <input type="checkbox" class="wpcf7-checkbox" name="'.esc_html($name_tags[1].$id).'" value="'.$i.'" />'.$tagSeparateAfter.'';
                            } else {
                                $inputCheckbox .= ''.$tagSeparate.'<input type="checkbox" class="wpcf7-checkbox" name="'.esc_html($name_tags[1].$id).'" value="'.$i.'"/> '.esc_html($val).''.$tagSeparateAfter.'';
                            }

                        } else {

                            $inputCheckbox .= ''.$tagSeparate.''.esc_html($val).''.$tagSeparateAfter.'';
                            
                        }
                        $i++;

                    }
                    $messageText = str_replace(esc_html($name_tags[0]), $inputCheckbox, $messageText);
                    
                } else if( isset($basetype) && $basetype==='radio' && $thisTagRaw===false ) {

                    $inputRadio = '';
                    $i = 1;

                    foreach( $contact_tag[$found_key]['values'] as $id=>$val ) {

                        $valueTag = wpcf7_mail_replace_tags($name_tags[0]);

                        if (isset($meta_values['data_input']) && $meta_values['data_input']=='true') {
                         
                            if( in_array('label_first', $tagOptions) ) {
                                $inputRadio .= ''.$tagSeparate.''.esc_html($val).' <input type="radio" class="wpcf7-radio" name="'.esc_html($name_tags[1].$id).'" value="'.$i.'" />'.$tagSeparateAfter.'';
                            } else {
                                $inputRadio .= ''.$tagSeparate.'<input type="radio" class="wpcf7-radio" name="'.esc_html($name_tags[1].$id).'" value="'.$i.'" /> '.esc_html($val).''.$tagSeparateAfter.'';
                            }

                        } else {                          

                            $inputRadio .= ''.$tagSeparate.''.esc_html($val).''.$tagSeparateAfter.'';
                        }
                        $i++;
                    }

                    $messageText = str_replace(esc_html($name_tags[0]), $inputRadio, $messageText);

                } else {
                    
                    $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));                            
                    $messageText = str_replace(esc_html($name_tags[0]), esc_html($valueTag), $messageText);
                }

            }

            if( empty( $meta_values["linebreak"] ) or ( isset($meta_values["linebreak"]) && $meta_values["linebreak"] == 'false') ) {
                $messageText = preg_replace("/(\r\n|\n|\r)/", "<div></div>", $messageText);
                $messageText = str_replace("<div></div><div></div>", '<div style="height:10px;"></div>', $messageText);
            }
            
            $messageText = str_replace('[reference]', wp_kses_post(get_transient('pdf_uniqueid')), $messageText);
            $messageText = str_replace('[url-pdf]', esc_url($upload_dir['url'].'/'.$nameOfPdf.'-'.wp_kses_post(get_transient('pdf_uniqueid')).'.pdf'), $messageText);
            $messageText = str_replace('[ID]', '000'.date('md'), $messageText);
            if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                $dateField = date_i18n($meta_values['date_format']);
            } else {
                $dateField = date_i18n($date_format, current_time('timestamp'));
            }
            if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                $timeField = date_i18n($meta_values['time_format']);
            } else {
                $timeField = date_i18n($hour_format, current_time('timestamp'));
            }
            $messageText = str_replace('[date]', $dateField, $messageText);
            $messageText = str_replace('[time]', $timeField, $messageText);
            
            // Enable Fillable Form
            if( isset($meta_values['fillable_data']) && $meta_values['fillable_data']=='true') {
                $mpdf->useActiveForms = true;
                $mpdf->SetProtection(array('copy', 'print', 'fill-forms', 'modify', 'annot-forms' ), '', '', 128);
            }
            
            if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                $footerText = wp_kses(trim($meta_values['footer_generate_pdf']), $this->wpcf7pdf_autorizeHtml());
                $footerText = str_replace('[reference]', sanitize_text_field(get_transient('pdf_uniqueid')), $footerText);
                $footerText = str_replace('[url-pdf]', esc_url($upload_dir['url'].'/'.$nameOfPdf.'-'.wp_kses_post(get_transient('pdf_uniqueid')).'.pdf'), $footerText);
                if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                    $dateField = date_i18n($meta_values['date_format']);
                }
                if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                    $timeField = date_i18n($meta_values['time_format']);
                }
                $footerText = str_replace('[date]', $dateField, $footerText);
                $footerText = str_replace('[time]', $timeField, $footerText);
                $mpdf->SetHTMLFooter($footerText);
            }

            // Shortcodes?
            if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {

                $tagShortcodes = explode(',', esc_html($meta_values['shotcodes_tags']));
                $countShortcodes = count($tagShortcodes);
                for($i = 0; $i < ($countShortcodes);  $i++) {

                    $pattern = '`\[([^\]]*)\]`';
                    preg_match_all($pattern, $tagShortcodes[$i], $shortcodeTags);

                    if( is_array($shortcodeTags) && isset($shortcodeTags[1][0]) ) {

                        $shortcodeName = explode(' ', $shortcodeTags[1][0]);
                        if( is_plugin_active('shortcoder/shortcoder.php') && class_exists('Shortcoder') ) {

                            $shortcodes =  Shortcoder::get_shortcodes();
                            $returnShortcode = Shortcoder::find_shortcode(array('name'=>$shortcodeName[0]), $shortcodes);

                            if( isset($returnShortcode['id']) ) {
                                $messageText = str_replace('['.$shortcodeName[0].']', esc_html( Shortcoder::get_sc_tag( $returnShortcode['id'] ) ), $messageText);
                            }
                        
                        }
                        
                        if( stripos($messageText, '['.$shortcodeName[0].']') !== false ) {
                            $messageText = str_replace('['.$shortcodeName[0].']', do_shortcode($tagShortcodes[$i]), $messageText);
                        }
                    }
                }
            }

            // En cas de saut de page avec le tag [addpage]
            if( stripos($messageText, '[addpage]') !== false ) {

                $newPage = explode('[addpage]', $messageText);
                $countPage = count($newPage);

                for($i = 0; $i < ($countPage);  $i++) {
                    
                    if( $i == 0 ) {
                        // On print la première page
                        $mpdf->WriteHTML($newPage[$i]);
                    } else {
                        // On print ensuite les autres pages trouvées
                        if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                            $mpdf->SetHTMLHeader($entetePage, '', true);
                            $mpdf->AddPage();
                        } else {
                            $mpdf->SetHTMLHeader(); 
                            $mpdf->AddPage('','','','','',15,15,15,15,5,5);
                        } 
                        if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                            $mpdf->SetHTMLFooter($footerText);
                        }
                        $mpdf->WriteHTML($newPage[$i]);
                        if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                            $mpdf->SetHTMLHeader($entetePage, '', true);
                        } else {
                            $mpdf->SetHTMLHeader();                                 
                        }
                    }
                    
                }

            } else {

                $mpdf->WriteHTML( wpautop( $messageText ) );

            }

            // En cas de nouveau document PDF
            /*preg_match_all('/\[add_document([^\]]*)\]/m', $messageText, $matches, PREG_SET_ORDER, 0);
            if( $matches ) {

                foreach($matches as $document) {
                    // Print the entire match result
                    preg_match_all('/\"([^\]]*)\"/m', $document[1], $suffix, PREG_SET_ORDER, 0);
                    var_dump($suffix[0][1]);
                }
            }*/

            $pdfPassword = '';
            if ( isset($meta_values["protect"]) && $meta_values["protect"]=='true') {
                
                if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) {
                    $pdfPassword = esc_html($meta_values["protect_password"]);
                }
                $mpdf->SetProtection(array('print', 'fill-forms', 'modify', 'copy'), $pdfPassword, $pdfPassword, 128);
            }            
            $mpdf->Output($createDirectory.'/preview-'.esc_html($idForm).'.pdf', 'F');

        }

            $messagePdf = '
<p>Votre nom : [your-name]</p>

<p>Votre email : [your-email]</p>

<p>Sujet : [your-subject] </p>

<p>Votre message : [your-message]</p>

';

$pathFolder = serialize($createDirectory);
        
?>

<form method="post" action="" name="valide_settings">

    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="idform" value="<?php echo esc_html($idForm); ?>"/>
    <input type="hidden" name="path_uploads" value="<?php echo esc_url($pathFolder); ?>" />
    <?php wp_nonce_field('go-sendform', 'security-sendform'); ?>

    <div style="text-align:right;">
        <p>
            <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', WPCF7PDF_TEXT_DOMAIN); ?>"/>
            <?php if( file_exists($createDirectory.'/preview-'.esc_html($idForm).'.pdf') ) { ?>
                <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.esc_html($idForm).'.pdf?ver='.rand(); ?>" ><?php _e('Preview your PDF', WPCF7PDF_TEXT_DOMAIN); ?></a>
            <?php } ?>
        </p>
    </div>

    <!-- PARAMETRES GENERAUX -->
    <div class="postbox">
        <div class="handlediv" style="height:1px!important;" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><br></div>
        <span class="dashicons customDashicons dashicons-admin-settings"></span> <h3 class="hndle" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><?php _e('General Settings', WPCF7PDF_TEXT_DOMAIN); ?></h3>
        <div class="inside">

            <!-- Disable GENERATE PDF -->
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">

                    <tr>
                        <td style="vertical-align: middle;margin-top:15px;"><?php _e('Disable generate PDF?', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td style="text-align:left;">
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_left" name="wp_cf7pdf_settings[disable-pdf]" value="true" <?php if( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_left"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_right" name="wp_cf7pdf_settings[disable-pdf]" value="false" <?php if( empty($meta_values["disable-pdf"]) || (isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_right"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Who send the PDF file?', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment]" class="wpcf7-form-field">
                                <option value="sender"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "both") || empty($meta_values["send-attachment"]) ) { echo ' selected'; } ?>><?php _e('Both', WPCF7PDF_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr style="vertical-align: middle;margin-top:15px;">
                        <td><?php _e("Disable data submit in a database?", WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td style="text-align:left;">
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_insert" name="wp_cf7pdf_settings[disable-insert]" value="true" <?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_insert"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_insert_no" name="wp_cf7pdf_settings[disable-insert]" value="false" <?php if( empty($meta_values["disable-insert"]) || (isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_insert_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Truncate database?', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td>
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_truncate" name="truncate_table" value="true" />
                                <label for="switch_truncate"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_truncate_no" name="truncate_table" value="false" checked />
                                <label for="switch_truncate_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td><?php _e('Disable generate CSV file?', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td>
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_csv" name="wp_cf7pdf_settings[disable-csv]" value="true" <?php if( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_csv"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_csv_no" name="wp_cf7pdf_settings[disable-csv]" value="false" <?php if( empty($meta_values["disable-csv"]) || (isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_csv_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Who send the CSV file?', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment2]" class="wpcf7-form-field">
                                <option value="sender"<?php if( isset($meta_values["send-attachment2"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "both") || empty($meta_values["send-attachment2"]) ) { echo ' selected'; } ?>><?php _e('Both', WPCF7PDF_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Change CSV separator', WPCF7PDF_TEXT_DOMAIN); ?><br />
                            <p><i><?php _e("By defaut it's separated by commas", WPCF7PDF_TEXT_DOMAIN); ?></i></p></td>
                        <td><input size="3" type= "text" name="wp_cf7pdf_settings[csv-separate]" class="wpcf7-form-field" value="<?php if( isset($meta_values["csv-separate"]) && !empty($meta_values["csv-separate"]) ) { echo esc_html($meta_values["csv-separate"]); } else { echo ','; } ?>" /></td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td>
                            <?php _e('Enter a name for your PDF', WPCF7PDF_TEXT_DOMAIN); ?><p>(<i><?php _e("By default, the file's name will be 'document-pdf'", WPCF7PDF_TEXT_DOMAIN); ?></i>)</p>
                            <br />
                            <p><?php _e("You can use this tags (separated by commas):", WPCF7PDF_TEXT_DOMAIN); ?></p>
                            <p>
                            <span class="dashicons dashicons-arrow-right"></span> <?php echo sprintf( __('Use %s in the name of your PDF', WPCF7PDF_TEXT_DOMAIN), ' <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span>' ); ?><br />
                            <span class="dashicons dashicons-arrow-right"></span> <?php echo sprintf( __('Use %s in the name of your PDF', WPCF7PDF_TEXT_DOMAIN), ' <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[date]</strong></span>' ); ?><br />
                                <?php
                                    if( isset($meta_values["date-for-name"]) && !empty($meta_values["date-for-name"]) ) {
                                        $dateForName = $meta_values["date-for-name"];
                                    } else {
                                        $dateForName = get_option( 'date_format' );
                                    }
                                    $dateForName = str_replace('-', '', $dateForName);
                                    $dateForName = str_replace(' ', '', $dateForName);
                                    $dateForName = str_replace('/', '', $dateForName);
                                ?>
                                &nbsp;&nbsp;<small><?php _e('Enter date format without space, -, /, _, etc..', WPCF7PDF_TEXT_DOMAIN); ?></small><input size="5" type= "text" name="wp_cf7pdf_settings[date-for-name]" value="<?php echo esc_html($dateForName); ?>" /> <?php echo date_i18n(esc_html($dateForName), current_time('timestamp')); ?>
                            </p>

                        </td>
                        <td>
                            <input type= "text" class="wpcf7-form-field" name="wp_cf7pdf_settings[pdf-name]" value="<?php if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) { echo esc_html($meta_values["pdf-name"]); } ?>">.pdf<br /><br /><br />
                            <input type="text" class="wpcf7-form-field" size="30" name="wp_cf7pdf_settings[pdf-add-name]" value="<?php if( isset($meta_values["pdf-add-name"]) && !empty($meta_values["pdf-add-name"]) ) { echo esc_html($meta_values["pdf-add-name"]); } ?>" />
                        </td>

                    </tr>
                    <tr>
                        <td>
                            <?php _e('Change uploads folder?', WPCF7PDF_TEXT_DOMAIN); ?><p>(<i><?php _e("By default, the uploads folder's name is in /wp-content/uploads/*YEAR*/*MONTH*/", WPCF7PDF_TEXT_DOMAIN); ?></i>)</p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_uploads" name="wp_cf7pdf_settings[pdf-uploads]" value="true" <?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_uploads"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_uploads_no" name="wp_cf7pdf_settings[pdf-uploads]" value="false" <?php if( empty($meta_values["pdf-uploads"]) || (isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_uploads_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Delete all files into this uploads folder?', WPCF7PDF_TEXT_DOMAIN); ?><p></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_delete" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="true" />
                                <label for="switch_delete"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_delete_no" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="false" checked />
                                <label for="switch_delete_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Delete each PDF file after send the email?', WPCF7PDF_TEXT_DOMAIN); ?><p></p>
                        </td>
                        <td>
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_filedelete" name="wp_cf7pdf_settings[pdf-file-delete]" value="true" <?php if( isset($meta_values["pdf-uploads-delete"]) && $meta_values["pdf-file-delete"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_filedelete"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_filedelete_no" name="wp_cf7pdf_settings[pdf-file-delete]" value="false" <?php if( empty($meta_values["pdf-file-delete"]) || (isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_filedelete_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td><?php _e('Other files attachments?', WPCF7PDF_TEXT_DOMAIN); ?><p>(<i><?php _e("Enter one URL file by line", WPCF7PDF_TEXT_DOMAIN); ?></i>)</p><textarea class="wpcf7-form-field" cols="100%" rows="5" name="wp_cf7pdf_settings[pdf-files-attachments]"><?php if( isset($meta_values["pdf-files-attachments"]) ) { echo esc_textarea($meta_values["pdf-files-attachments"]); } ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Who send the attachments file?', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment3]" class="wpcf7-form-field" >
                                <option value="sender"<?php if( isset($meta_values["send-attachment3"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment3"]) && $meta_values["send-attachment3"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment3"] == "both") || empty($meta_values["send-attachment3"]) ) { echo ' selected'; } ?>><?php _e('Both', WPCF7PDF_TEXT_DOMAIN); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td>
                            <?php _e('Select a page to display after sending the form (optional)', WPCF7PDF_TEXT_DOMAIN); ?>
                        </td>
                        <td>
                            <?php
                                if( isset($meta_values['page_next']) ) {
                                    $idSelectPage = $meta_values['page_next'];
                                } else {
                                    $idSelectPage = 0;
                                }
                                $args = array('name' => 'wp_cf7pdf_settings[page_next]', 'class' => 'wpcf7-form-field', 'selected' => esc_html($idSelectPage), 'show_option_none' => __('Please select a page', WPCF7PDF_TEXT_DOMAIN) );
                                wp_dropdown_pages($args);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Rediriger sur cette page sans envoyer un e-mail? -->
                            <?php _e('Send email without attachments?', WPCF7PDF_TEXT_DOMAIN); ?><p></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_attachments" name="wp_cf7pdf_settings[disable-attachments]" value="true" <?php if( isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_attachments"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_attachments_no" name="wp_cf7pdf_settings[disable-attachments]" value="false" <?php if( empty($meta_values["disable-attachments"]) || (isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_attachments_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose la redirection vers le pdf direct -->
                            <?php _e('Redirects directly to the PDF after sending the form?', WPCF7PDF_TEXT_DOMAIN); ?>
                            <p><i><?php _e( 'This option disable the Page Redirection selected', WPCF7PDF_TEXT_DOMAIN); ?> (<?php _e( 'Except the popup window option', WPCF7PDF_TEXT_DOMAIN); ?>)</i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_redirect" name="wp_cf7pdf_settings[redirect-to-pdf]" value="true" <?php if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_redirect"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_redirect_no" name="wp_cf7pdf_settings[redirect-to-pdf]" value="false" <?php if( empty($meta_values["redirect-to-pdf"]) || (isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_redirect_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div><br />
                            <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="on" <?php if( (isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='on') or empty($meta_values["redirect-window"]) ) { echo ' checked'; } ?>  /><?php _e('Same Window', WPCF7PDF_TEXT_DOMAIN); ?> <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="off" <?php if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='off') { echo 'checked'; } ?> /><?php _e('New Window', WPCF7PDF_TEXT_DOMAIN); ?> <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="popup" <?php if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='popup') { echo 'checked'; } ?> /><?php _e('Popup Window', WPCF7PDF_TEXT_DOMAIN); ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Propose l'envoi d'un zip dans l'email plutôt que le PDF -->
                            <?php _e('Send a ZIP instead PDF?', WPCF7PDF_TEXT_DOMAIN); ?>
                            <p><i><?php _e( 'This option send all your files in a ZIP', WPCF7PDF_TEXT_DOMAIN); ?></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_pdf_zip" name="wp_cf7pdf_settings[pdf-to-zip]" value="true" <?php if( isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_pdf_zip"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_pdf_zip_no" name="wp_cf7pdf_settings[pdf-to-zip]" value="false" <?php if( empty($meta_values["pdf-to-zip"]) || (isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_pdf_zip_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Select a date and time format', WPCF7PDF_TEXT_DOMAIN); ?><br /><p><i><?php _e('By default, the date format is defined in the admin settings', WPCF7PDF_TEXT_DOMAIN); ?> (<a href="https://codex.wordpress.org/Formatting_Date_and_Time" target="_blank"><?php _e('Formatting Date and Time', WPCF7PDF_TEXT_DOMAIN); ?></a>)</i></p>
                        </td>
                        <td>
                            <?php

                            $formatDate = stripslashes($date_format);
                            $formatTime = $hour_format;
                            if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                                $formatDate = stripslashes($meta_values['date_format']);
                            }
                            if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                                $formatTime = $meta_values['time_format'];
                            }
                            ?>
                            <input id="date_format" class="wpcf7-form-field" size="16" name="wp_cf7pdf_settings[date_format]" value="<?php echo esc_html($formatDate); ?>" type="text" /> <?php _e('Date:', WPCF7PDF_TEXT_DOMAIN); ?> <?php echo date_i18n($formatDate); ?><br />
                            <input id="time_format" size="16" class="wpcf7-form-field" name="wp_cf7pdf_settings[time_format]" value="<?php echo esc_html($formatTime); ?>" type="text" /> <?php _e('Time:', WPCF7PDF_TEXT_DOMAIN); ?> <?php echo date_i18n($formatTime); ?>
                        </td>
                    </tr>
                    <!-- ENTETE PDF -->
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Input encoding', WPCF7PDF_TEXT_DOMAIN); ?><br /><p><i><?php _e('mPDF accepts UTF-8 encoded text by default for all functions', WPCF7PDF_TEXT_DOMAIN); ?></i></p>
                        </td>
                        <td>
                            <?php

                            ?>
                            <select name="wp_cf7pdf_settings[charset]">
                                <option value="utf-8" <?php if( empty($meta_values["charset"]) || (isset($meta_values["charset"]) && $meta_values["charset"]=='utf-8') ) { echo ' selected'; } ?>>utf-8</option>
                                <option value="windows-1252" <?php if( isset($meta_values["charset"]) && $meta_values["charset"]=='windows-1252"' ) { echo ' selected'; } ?>>windows-1252</option>
                            </select>
                        </td>
                    </tr>

                    <!-- FONT DIRECTIONALITY PDF -->
                    <tr>
                        <td>
                            <?php _e('PDF directionality', WPCF7PDF_TEXT_DOMAIN); ?><br /><p><i><?php _e('Defines the directionality of the document', WPCF7PDF_TEXT_DOMAIN); ?></i></p>
                        </td>
                        <td>
                            <?php

                            ?>
                            <select name="wp_cf7pdf_settings[set_directionality]">
                                <option value="ltr" <?php if( empty($meta_values["set_directionality"]) || (isset($meta_values["set_directionality"]) && $meta_values["set_directionality"]=='ltr') ) { echo ' selected'; } ?>>LTR</option>
                                <option value="rtl" <?php if( isset($meta_values["set_directionality"]) && $meta_values["set_directionality"]=='rtl' ) { echo ' selected'; } ?>>RTL</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Propose de télécharger le pdf? -->
                            <?php _e('Desactivate line break auto?', WPCF7PDF_TEXT_DOMAIN); ?>
                            <p><i><?php _e('This disables automatic line break replacement (\n and \r)', WPCF7PDF_TEXT_DOMAIN); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_linebreak" name="wp_cf7pdf_settings[linebreak]" value="true" <?php if( isset($meta_values["linebreak"]) && $meta_values["linebreak"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_linebreak"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_linebreak_no" name="wp_cf7pdf_settings[linebreak]" value="false" <?php if( empty($meta_values["linebreak"]) || (isset($meta_values["linebreak"]) && $meta_values["linebreak"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_linebreak_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose de désactiver le remplissage auto du formulaire -->
                            <?php _e('Desactivate autocomplete form?', WPCF7PDF_TEXT_DOMAIN); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_autocomplete" name="wp_cf7pdf_settings[disabled-autocomplete-form]" value="true" <?php if( isset($meta_values["disabled-autocomplete-form"]) && $meta_values["disabled-autocomplete-form"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_autocomplete"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_autocomplete_no" name="wp_cf7pdf_settings[disabled-autocomplete-form]" value="false" <?php if( empty($meta_values["disabled-autocomplete-form"]) || (isset($meta_values["disabled-autocomplete-form"]) && $meta_values["disabled-autocomplete-form"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_autocomplete_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    <tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Propose de mettre la cases réelles des Checkbox et Radio -->
                            <?php _e('Enable display data in the checkbox or radio buttons of your PDF file?', WPCF7PDF_TEXT_DOMAIN); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_data_input" name="wp_cf7pdf_settings[data_input]" value="true" <?php if( isset($meta_values["data_input"]) && $meta_values["data_input"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_data_input"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_data_input_no" name="wp_cf7pdf_settings[data_input]" value="false" <?php if( empty($meta_values["data_input"]) || (isset($meta_values["data_input"]) && $meta_values["data_input"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_data_input_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Ne pas afficher les entrée vides des Checkbox et Radio -->
                            <?php _e('Disable display empty data in the checkbox or radio buttons?', WPCF7PDF_TEXT_DOMAIN); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_empty_input" name="wp_cf7pdf_settings[empty_input]" value="true" <?php if( isset($meta_values["empty_input"]) && $meta_values["empty_input"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_empty_input"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_empty_input_no" name="wp_cf7pdf_settings[empty_input]" value="false" <?php if( empty($meta_values["empty_input"]) || (isset($meta_values["empty_input"]) && $meta_values["empty_input"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_empty_input_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose de mettre le PDF en mode éditable -->
                            <?php _e('Enable fillable PDF Forms?', WPCF7PDF_TEXT_DOMAIN); ?>
                            <p><i><?php _e("Don't works if your PDF is protected.", WPCF7PDF_TEXT_DOMAIN); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_fillable_data" name="wp_cf7pdf_settings[fillable_data]" value="true" <?php if( isset($meta_values["fillable_data"]) && $meta_values["fillable_data"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_fillable_data"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_fillable_data_no" name="wp_cf7pdf_settings[fillable_data]" value="false" <?php if( empty($meta_values["fillable_data"]) || (isset($meta_values["fillable_data"]) && $meta_values["fillable_data"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_fillable_data_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Proteger le pdf? -->
                            <?php _e('Protect your PDF file?', WPCF7PDF_TEXT_DOMAIN); ?>
                            <p><i><?php _e('Use [pdf-password] tag in your emails.', WPCF7PDF_TEXT_DOMAIN); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_protect" name="wp_cf7pdf_settings[protect]" value="true" <?php if( isset($meta_values["protect"]) && $meta_values["protect"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_protect"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_protect_no" name="wp_cf7pdf_settings[protect]" value="false" <?php if( empty($meta_values["protect"]) || (isset($meta_values["protect"]) && $meta_values["protect"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_protect_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Proteger le pdf? -->
                            <?php _e('Generate and use a random password?', WPCF7PDF_TEXT_DOMAIN); ?>
                            <?php
                                $nbPassword = 12;
                                if( isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"]) ) { 
                                    $nbPassword = esc_html($meta_values["protect_password_nb"]); 
                                }
                            ?>
                            <p><i><?php _e('Example (not working in preview mode):', WPCF7PDF_TEXT_DOMAIN); echo ' <strong>'.cf7_sendpdf::wpcf7pdf_generateRandomPassword($nbPassword).'</strong>'; ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_protect_uniquepassword" name="wp_cf7pdf_settings[protect_uniquepassword]" value="true" <?php if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_protect_uniquepassword"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_protect_uniquepassword_no" name="wp_cf7pdf_settings[protect_uniquepassword]" value="false" <?php if( empty($meta_values["protect_uniquepassword"]) || (isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_protect_uniquepassword_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><?php _e('Maximum number of characters for password?', WPCF7PDF_TEXT_DOMAIN); ?><p><i><?php _e('By default : 12', WPCF7PDF_TEXT_DOMAIN); ?></i></p></td>
                        <td><input type="text" size="3" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password_nb]" value="<?php if( isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"]) ) { echo esc_html($meta_values["protect_password_nb"]); } else { echo esc_html($nbPassword); } ?>" /></td>
                    </tr>

                    <tr>
                        <td><?php _e('Or enter your unique password for all PDF files.', WPCF7PDF_TEXT_DOMAIN); ?></td>
                        <td><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password]" value="<?php if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) { echo esc_html(stripslashes($meta_values["protect_password"])); } ?>" /></td>
                    </tr>

                    <tr>
                        <td><?php _e('Or choose a tag for password for each PDF files.', WPCF7PDF_TEXT_DOMAIN); ?><p><i><?php _e('Like: [tag]', WPCF7PDF_TEXT_DOMAIN); ?></i></p></td>
                        <td><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password_tag]" value="<?php if( isset($meta_values["protect_password_tag"]) && $meta_values["protect_password_tag"]!='' ) { echo esc_html( $meta_values["protect_password_tag"] ); } ?>" /></td>
                    </tr>

                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>

                    <tr>
                        <td><?php _e('Delete all config for this form?', WPCF7PDF_TEXT_DOMAIN); ?><p><i><?php _e('Click Yes and save the form.', WPCF7PDF_TEXT_DOMAIN); ?></i></p></td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_deleteconfig" name="deleteconfig" value="true"/>
                                <label for="switch_deleteconfig"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                <input class="switch_right" type="radio" id="switch_deleteconfig_no" name="deleteconfig" value="false" checked />
                                <label for="switch_deleteconfig_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>


                </tbody>
            </table>
        </div>
    </div>
    <div class="clear">&nbsp;</div>

    <!-- MISE EN PAGE -->
    <div class="postbox">
        <div class="handlediv" style="height:1px!important;" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><br></div>
        <span class="dashicons customDashicons dashicons-pdf"></span> <h3 class="hndle" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><?php _e('Layout of your PDF', WPCF7PDF_TEXT_DOMAIN); ?></h3>
        <div class="inside openinside">

            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td style="width: 60%;">
                            <h3 class="hndle"><span class="dashicons dashicons-format-image"></span>&nbsp;&nbsp;<?php _e('Image header', WPCF7PDF_TEXT_DOMAIN); ?></h3>
                            <?php _e('Enter a URL or upload an image:', WPCF7PDF_TEXT_DOMAIN); ?><br />
                            <input id="upload_image" size="80%" class="wpcf7-form-field" name="wp_cf7pdf_settings[image]" value="<?php if( isset($meta_values['image']) ) { echo esc_url($meta_values['image']); } ?>" type="text" /> <a href="#" id="upload_image_button" class="button" OnClick="this.blur();"><span> <?php _e('Select or Upload your picture', WPCF7PDF_TEXT_DOMAIN); ?> </span></a> <br />
                            <div style="margin-top:0.8em;">
                                <select name="wp_cf7pdf_settings[image-alignment]" class="wpcf7-form-field">
                                    <option value="left" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='left') ) { echo 'selected'; } ?>><?php _e('Left', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="center" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='center') ) { echo 'selected'; } ?>><?php _e('Center', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="right" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='right') ) { echo 'selected'; } ?>><?php _e('Right', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                </select>
                                <?php _e('Size', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" class="wpcf7-form-field" size="3" name="wp_cf7pdf_settings[image-width]" value="<?php if( isset($meta_values['image-width']) ) { echo $meta_values['image-width']; } else { echo '150'; } ?>" />&nbsp;X&nbsp;<input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[image-height]" size="3" value="<?php if( isset($meta_values['image-height']) ) { echo esc_html($meta_values['image-height']); } ?>" />px<br /><br />
                                
                                <div><?php _e('Display header on each page?', WPCF7PDF_TEXT_DOMAIN); ?>
                                    <div class="switch-field-mini">
                                        <input class="switch_left" type="radio" id="switch_page_header" name="wp_cf7pdf_settings[page_header]" value="1" <?php if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) { echo ' checked'; } ?>/>
                                        <label for="switch_page_header"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                        <input class="switch_right" type="radio" id="switch_page_header_no" name="wp_cf7pdf_settings[page_header]" value="0" <?php if( empty($meta_values["page_header"]) || (isset($meta_values["page_header"]) && $meta_values["page_header"]==0) ) { echo ' checked'; } ?> />
                                        <label for="switch_page_header_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                    </div><br />
                                    <?php _e('Margin Bottom Header', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[margin_bottom_header]" value="<?php if( isset($meta_values["margin_bottom_header"]) && $meta_values["margin_bottom_header"]!='' ) { echo esc_html($meta_values["margin_bottom_header"]); } else { echo esc_html($marginBottomHeader); } ?>" />                            
                                </div>
                            </div>

                            <h3 class="hndle"><span class="dashicons dashicons-images-alt2"></span>&nbsp;&nbsp;<?php _e('Image Background', WPCF7PDF_TEXT_DOMAIN); ?></h3>
                            <?php _e('Enter a URL or upload an image:', WPCF7PDF_TEXT_DOMAIN); ?><br />
                            <input id="upload_background" size="80%" class="wpcf7-form-field" name="wp_cf7pdf_settings[image_background]" value="<?php if( isset($meta_values['image_background']) ) { echo esc_url($meta_values['image_background']); } ?>" type="text" /> <a href="#" id="upload_image_background" class="button" OnClick="this.blur();"><span> <?php _e('Select or Upload your picture', WPCF7PDF_TEXT_DOMAIN); ?> </span></a><br /><small><?php _e('Example for demo:', WPCF7PDF_TEXT_DOMAIN); ?> <a href="<?php echo esc_url(plugins_url( '../images/background.jpg', __FILE__ )); ?>" target="_blank"><?php _e('here', WPCF7PDF_TEXT_DOMAIN); ?></a></small><br />
                            <div style="margin-top:0.8em;">                           
                                <div><?php _e('Display background on each page?', WPCF7PDF_TEXT_DOMAIN); ?>
                                    <div class="switch-field-mini">
                                        <input class="switch_left" type="radio" id="switch_page_background" name="wp_cf7pdf_settings[page_background]" value="1" <?php if( isset($meta_values["page_background"]) && $meta_values["page_background"]==1) { echo ' checked'; } ?>>
                                        <label for="switch_page_background"><?php _e('Yes', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                        <input class="switch_right" type="radio" id="switch_page_background_no" name="wp_cf7pdf_settings[page_background]" value="0" <?php if( empty($meta_values["page_background"]) || (isset($meta_values["page_background"]) && $meta_values["page_background"]==0) ) { echo ' checked'; } ?>>
                                        <label for="switch_page_background_no"><?php _e('No', WPCF7PDF_TEXT_DOMAIN); ?></label>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="text-align:center;">
                            <div style="border:1px solid #CCCCCC;height:500px;padding:5px;<?php if( isset($meta_values['image_background']) ) { echo 'background: no-repeat url('.esc_url($meta_values['image_background']); } ?>);background-size: cover;">
                                <div style="text-align:<?php if( isset($meta_values['image-alignment']) ) { echo esc_html($meta_values['image-alignment']); } ?>;margin-top:<?php if( isset($meta_values["margin_header"]) && $meta_values["margin_header"]!='' ) { echo esc_html($meta_values["margin_header"]); } else { echo esc_html($marginHeader); } ?>px;"><?php if( isset($meta_values['image']) ) { echo '<img src="'.esc_url($meta_values['image']).'" width="150">'; } ?>
                                </div>
                                <?php
                                    
                                    if( isset($meta_values["margin_top"]) && $meta_values["margin_top"]!='' ) { 
                                        $previewMargin = esc_html($meta_values["margin_top"]);
                                        if($meta_values["margin_top"]<40) { $previewMargin = ($previewMargin-40); }
                                        if($meta_values["margin_top"]==0) { $previewMargin = -60; }
                                    } else { 
                                        $previewMargin = esc_html($marginTop); 
                                    }
                                ?>
                                <div style="color:#cccccc;text-align:justify;margin-top:<?php echo $previewMargin; ?>px;">
                                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Aenean commodo, neque nec vehicula molestie, lacus nunc ornare mi, nec aliquam libero dolor sed dolor. Quisque in lacinia lacus, ut tincidunt mauris. Integer vitae scelerisque dui. Curabitur pharetra et velit vitae interdum. Donec sollicitudin massa ante, nec malesuada quam scelerisque sit amet. Donec tristique semper diam vehicula auctor. Suspendisse venenatis porta odio eget consequat. Nullam nec lacus dapibus nulla auctor feugiat sit amet vel tortor.<br /><br />

                                    Nulla facilisi. Nam ullamcorper interdum efficitur. Etiam sollicitudin orci sit amet congue aliquam. Integer eu leo tempus, pellentesque eros at, cursus odio. Aliquam venenatis lectus tempus lacus pretium, sed tincidunt eros tempus. Pellentesque aliquet mollis risus, a auctor justo. Aliquam suscipit quis nisi et consectetur. Fusce tempus ex ac arcu dapibus scelerisque. In hendrerit convallis est non placerat. Fusce turpis sem, facilisis a mauris at, interdum imperdiet metus. Nullam lacus eros, tempor vel lectus id, tristique accumsan risus. Nunc ullamcorper ipsum ut accumsan fermentum. Nunc tempor est mauris. Vivamus eu tellus dictum felis pretium volutpat. Maecenas vitae tincidunt purus.<br /><br />

                                    Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Proin nec dolor eget diam ullamcorper pharetra. Aenean pulvinar interdum lacus, eu suscipit enim vehicula vitae. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos. Aenean hendrerit urna id malesuada porttitor. Vestibulum laoreet hendrerit iaculis. Vestibulum id quam non lectus euismod vulputate porta eu purus. Etiam ultricies dolor ut turpis pellentesque faucibus.
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            
                        </td>
                    </tr>
                    <tr>
                        <td >
                            
                        </td>
                    </tr>
                    </tbody>
            </table>
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td>
                            <h3 class="hndle"><span class="dashicons dashicons-media-code"></span> <?php _e('Custom CSS', WPCF7PDF_TEXT_DOMAIN); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <textarea name="wp_cf7pdf_settings[custom_css]" id="wp_cf7pdf_pdf_css" cols=70 rows=24 class="widefat textarea"style="height:250px;"><?php if( isset( $meta_values['custom_css']) ) { echo esc_textarea($meta_values['custom_css']); } ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td >
                            
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td>
                    <tr>
                        <td>
                            <h3 class="hndle"><span class="dashicons dashicons-arrow-down-alt"></span> <?php _e('Footer', WPCF7PDF_TEXT_DOMAIN); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <?php _e('You can use this tags:', WPCF7PDF_TEXT_DOMAIN); ?><br />
                            <ul>
                                <li><?php _e('<strong>{PAGENO}/{nbpg}</strong> will be replaced by the current page number / total pages.', WPCF7PDF_TEXT_DOMAIN); ?></li>
                                <li><?php _e('<strong>{DATE j-m-Y}</strong> will be replaced by the current date. j-m-Y can be replaced by any of the valid formats used in the php <a href="http://www.php.net/manual/en/function.date.php" target="_blank">date()</a> function.', WPCF7PDF_TEXT_DOMAIN); ?></li>
                                <li><?php _e('<strong>[reference] [date]</strong> and <strong>[time]</strong> tags works also.', WPCF7PDF_TEXT_DOMAIN); ?></li>
                            </ul>
                            <textarea id="cf7pdf_html_footer" name="wp_cf7pdf_settings[footer_generate_pdf]" rows="15" cols="80%"><?php if( isset( $meta_values['footer_generate_pdf']) ) { echo esc_textarea($meta_values['footer_generate_pdf']); } ?></textarea>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td>
                            <h3 class="hndle"><span class="dashicons dashicons-tagcloud"></span> <?php _e('Personalize your PDF', WPCF7PDF_TEXT_DOMAIN); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <tr>
                            <td><?php _e('Page size & Orientation', WPCF7PDF_TEXT_DOMAIN); ?></td>
                            <td>
                                <select name="wp_cf7pdf_settings[pdf-type]" class="wpcf7-form-field">
                                    <option value="Letter" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='Letter') ) { echo 'selected'; } ?>>Letter</option>
                                    <option value="Legal" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='Legal') ) { echo 'selected'; } ?>>Legal</option>
                                    <option value="Executive" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='Executive') ) { echo 'selected'; } ?>>Executive</option>
                                    <option value="Folio" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='Folio') ) { echo 'selected'; } ?>>Folio</option>
                                    <option value="Demy" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='Demy') ) { echo 'selected'; } ?>>Demy</option>
                                    <option value="Royal" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='Royal') ) { echo 'selected'; } ?>>Royal</option>
                                    <option value="4A0" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='4A0') ) { echo 'selected'; } ?>>4A0</option>
                                    <option value="2A0" <?php if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='2A0') ) { echo 'selected'; } ?>>2A0</option>
                                    <?php
                                    for ($typeA = 0; $typeA <= 10; $typeA++) {
                                        echo '<option value="A'.esc_html($typeA).'"';
                                        if( (isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='A'.$typeA)) OR (empty($meta_values['pdf-type']) && $typeA==4) ) { echo 'selected'; }
                                        echo '>A'.esc_html($typeA).'</option>';
                                    }
                                    for ($typeB = 0; $typeB <= 10; $typeB++) {
                                        echo '<option value="B'.esc_html($typeB).'"';
                                            if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='B'.$typeB) ) { echo 'selected'; }
                                        echo '>B'.esc_html($typeB).'</option>';
                                    }
                                    for ($typeC = 0; $typeC <= 10; $typeC++) {
                                        echo '<option value="C'.esc_html($typeC).'"';
                                            if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='C'.$typeC) ) { echo 'selected'; }
                                        echo '>C'.esc_html($typeC).'</option>';
                                    }
                                    for ($typeRA = 0; $typeRA <= 4; $typeRA++) {
                                        echo '<option value="RA'.esc_html($typeRA).'"';
                                            if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='RA'.$typeRA) ) { echo 'selected'; }
                                        echo '>RA'.esc_html($typeRA).'</option>';
                                    }
                                    for ($typeSRA = 0; $typeSRA <= 4; $typeSRA++) {
                                        echo '<option value="SRA'.esc_html($typeSRA).'"';
                                            if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='SRA'.$typeSRA) ) { echo 'selected'; }
                                        echo '>SRA'.esc_html($typeSRA).'</option>';
                                    }
                                    ?>
                                </select>
                                <select name="wp_cf7pdf_settings[pdf-orientation]" class="wpcf7-form-field">
                                    <option value="-P" <?php if( (isset($meta_values['pdf-orientation']) && ($meta_values['pdf-orientation']=='-P')) OR empty($meta_values['pdf-orientation']) ) { echo 'selected'; } ?>><?php _e('Portrait', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="-L" <?php if( isset($meta_values['pdf-orientation']) && ($meta_values['pdf-orientation']=='-L') ) { echo 'selected'; } ?>><?php _e('Landscape', WPCF7PDF_TEXT_DOMAIN); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e('Font Family & Size', WPCF7PDF_TEXT_DOMAIN); ?></td>
                            <td>
                                <select name="wp_cf7pdf_settings[pdf-font]" class="wpcf7-form-field">
                                    <?php 
                                        
                                        $listFont = cf7_sendpdf::wpcf7pdf_getFontsTab();
            
                                        foreach($listFont as $font => $nameFont) {
                                            $selected ='';
                                            if( isset($meta_values['pdf-font']) && $meta_values['pdf-font']==$nameFont ) { $selected = 'selected'; }
                                            echo '<option value="'.esc_html($nameFont).'" '.esc_html($selected).'>'.esc_html($font).'</option>';
                                        }
                                    ?>
                                </select>
                                <input name="wp_cf7pdf_settings[pdf-fontsize]" class="wpcf7-form-field" size="2" value="<?php if( isset($meta_values['pdf-fontsize']) && is_numeric($meta_values['pdf-fontsize']) ) { echo esc_html($meta_values['pdf-fontsize']); } else { echo esc_html($fontsizePdf); } ?>">
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e('Add a CSS file', WPCF7PDF_TEXT_DOMAIN); ?><br /><p><a href="<?php echo esc_url(plugins_url( '../css/mpdf-style-A4.css', __FILE__ )); ?>" target="_blank"><small><i><?php _e('Download a example A4 page here', WPCF7PDF_TEXT_DOMAIN); ?></i></small></a></p></td>
                            <td>
                                <input size="60%" class="wpcf7-form-field" name="wp_cf7pdf_settings[stylesheet]" value="<?php if( isset($meta_values['stylesheet']) ) { echo esc_url($meta_values['stylesheet']); } ?>" type="text" /><br />
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <?php _e('Global Margin PDF', WPCF7PDF_TEXT_DOMAIN); ?><br /><p></p>
                            </td>
                            <td>
                                <?php _e('Margin Header', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[margin_header]" value="<?php if( isset($meta_values["margin_header"]) && $meta_values["margin_header"]!='' ) { echo esc_html($meta_values["margin_header"]); } else { echo esc_html($marginHeader); } ?>" /> <?php _e('Margin Top Header', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" class="wpcf7-form-field" size="4" name="wp_cf7pdf_settings[margin_top]" value="<?php if( isset($meta_values["margin_top"]) && $meta_values["margin_top"]!='' ) { echo esc_html($meta_values["margin_top"]); } else { echo esc_html($marginTop); } ?>" /><br /><br />
                                <?php _e('Margin Left', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[margin_left]" value="<?php if( isset($meta_values["margin_left"]) && $meta_values["margin_left"]!='' ) { echo esc_html($meta_values["margin_left"]); } else { echo esc_html($marginLeft); } ?>" /> <?php _e('Margin Right', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" class="wpcf7-form-field" size="4" name="wp_cf7pdf_settings[margin_right]" value="<?php if( isset($meta_values["margin_right"]) && $meta_values["margin_right"]!='' ) { echo esc_html($meta_values["margin_right"]); } else { echo esc_html($marginRight); } ?>" /><br /><br />
                                <?php _e('Margin Header Auto', WPCF7PDF_TEXT_DOMAIN); ?> <select name="wp_cf7pdf_settings[margin_auto_header]" class="wpcf7-form-field">
                                    <option value="pad" <?php if( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"] == 'pad' ) { echo 'selected'; } ?>>pad</option>
                                    <option value="stretch" <?php if( empty($meta_values["margin_auto_header"]) || (isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"] == 'stretch') ) { echo 'selected'; } ?>>stretch</option>
                                    <option value="false" <?php if( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"] == 'false' ) { echo 'selected'; } ?>>false</option>
                                </select> <?php _e('Margin Bottom Auto', WPCF7PDF_TEXT_DOMAIN); ?>
                                <select name="wp_cf7pdf_settings[margin_auto_bottom]" class="wpcf7-form-field">
                                    <option value="pad" <?php if( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"] == 'pad' ) { echo 'selected'; } ?>>pad</option>
                                    <option value="stretch" <?php if( empty($meta_values["margin_auto_bottom"]) || (isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"] == 'stretch') ) { echo 'selected'; } ?>>stretch</option>
                                    <option value="false" <?php if( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"] == 'false' ) { echo 'selected'; } ?>>false</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <?php _e('Choice of separator for checkboxes or radio buttons', WPCF7PDF_TEXT_DOMAIN); ?><br /><p></p>
                            </td>
                            <td><?php _e("Before:", WPCF7PDF_TEXT_DOMAIN); ?><br />
                               <select name="wp_cf7pdf_settings[separate]" class="wpcf7-form-field">
                                    <option value="none" <?php if( empty($meta_values["separate"]) || (isset($meta_values["separate"]) && $meta_values["separate"] == 'none') ) { echo 'selected'; } ?>><?php _e("None", WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="dash" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'dash') { echo 'selected'; } ?>><?php _e("Dash", WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="star" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'star') { echo 'selected'; } ?>><?php _e("Star", WPCF7PDF_TEXT_DOMAIN); ?></option>                                    
                                    <option value="rightarrow" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'rightarrow') { echo 'selected'; } ?>><?php _e("Right Arrow", WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="double-right-arrow" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'double-right-arrow') { echo 'selected'; } ?>><?php _e("Double Right Arrow", WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="cornerarrow" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'cornerarrow') { echo 'selected'; } ?>><?php _e("Corner Arrow", WPCF7PDF_TEXT_DOMAIN); ?></option>                   
                                </select><br />
                                <?php _e("After:", WPCF7PDF_TEXT_DOMAIN); ?><br />
                                <select name="wp_cf7pdf_settings[separate_after]" class="wpcf7-form-field">
                                    <option value="comma" <?php if( isset($meta_values["separate_after"]) && $meta_values["separate_after"] == 'comma' ) { echo 'selected'; } ?>><?php _e("Comma", WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="space" <?php if( empty($meta_values["separate_after"]) || (isset($meta_values["separate_after"]) && $meta_values["separate_after"] == 'space') ) { echo 'selected'; } ?>><?php _e("Space", WPCF7PDF_TEXT_DOMAIN); ?></option>
                                    <option value="linebreak" <?php if( isset($meta_values["separate_after"]) && $meta_values["separate_after"] == 'linebreak') { echo 'selected'; } ?>><?php _e("Line break", WPCF7PDF_TEXT_DOMAIN); ?></option>                                    
                                </select><br />
                                <!--<?php _e('Other:', WPCF7PDF_TEXT_DOMAIN); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[separate_other]" value="<?php if( isset($meta_values["separate_other"]) && $meta_values["separate_other"]!='' ) { echo esc_html($meta_values["separate_other"]); } else { echo ''; } ?>" />-->
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                            <legend>
                                <?php _e('For personalize your PDF you can in the following text field, use these mail-tags:', WPCF7PDF_TEXT_DOMAIN); ?><br />
                                <table>
                                    <tr>
                                        <td width="50%">
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[addpage]</strong></span><br /><i><?php _e("[addpage] is a simple tag to force a page break anywhere in your PDF.", WPCF7PDF_TEXT_DOMAIN); ?></i>
                                        </td>
                                        <td width="50%">
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[date]</strong></span> <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[time]</strong></span><br /><i><?php _e("Use [date] and [time] to print the date and time anywhere in your PDF.", WPCF7PDF_TEXT_DOMAIN); ?></i>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%">
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span><br /><i><?php _e("[reference] is a simple mail-tag who is used for create unique PDF. It's also recorded in the database. Every PDF is named like this : name-pdf-uniqid() and it's uploaded in the upload folder of WordPress.", WPCF7PDF_TEXT_DOMAIN); ?></i><br /><br />
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[ID]</strong></span><br /><i><?php _e("[ID] is a simple tag that comes from the database ID if you have allowed registration in the options.", WPCF7PDF_TEXT_DOMAIN); ?></i><br /><br />
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[avatar]</strong></span><br /><i><?php _e("[avatar] is a simple mail-tag for the user Avatar URL.", WPCF7PDF_TEXT_DOMAIN); ?></i>
                                        </td>
                                        <td width="50%">
                                            <?php if( empty($fileTags) || ( isset($fileTags) && $fileTags == '') ) { $fileTags = '[file-1][file-2]'; } ?>
                                            <i><?php echo sprintf( __('The <strong>[file]</strong> tags are for images? Enter them here to display them in images on your PDF and like this: %s', WPCF7PDF_TEXT_DOMAIN), esc_html($fileTags) ); ?></i><br /><small><?php _e('It will then be necessary to put them in the image HTML tag for the PDF layout.', WPCF7PDF_TEXT_DOMAIN); ?><br /><?php _e('Use url- prefix for display URL like this:', WPCF7PDF_TEXT_DOMAIN); ?> <?php echo str_replace('[', '[url-', esc_html($fileTags)); ?></small><br /><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[file_tags]" size="80%" value="<?php if( isset($meta_values['file_tags'])) { echo esc_html($meta_values['file_tags']); } ?>" /><br /><br />
                                            <i><?php echo __('Enter here your Shortcodes (separated by commas)', WPCF7PDF_TEXT_DOMAIN); ?></i><br /><small><?php _e('It will then be necessary to put them in the PDF layout. Test with this shortcode: [wpcf7pdf_test]', WPCF7PDF_TEXT_DOMAIN); ?></small><br /><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[shotcodes_tags]" size="80%" value="<?php if( isset($meta_values['shotcodes_tags'])) { echo esc_html($meta_values['shotcodes_tags']); } ?>" />
                                        </td>
                                    
                                    </tr>
                                    <tr>
                                    <td width="50%">
                                        <?php
                                            /*
                                            * ECRIT DANS UN POST-META LES TAGS DU FORMULAIRE 
                                            *
                                            */
                                            $contact_form = WPCF7_ContactForm::get_instance(esc_html($idForm));
                                            $fileTags = '';
        
                                            foreach ( (array) $contact_form->collect_mail_tags() as $mail_tag ) {
                                                $pattern = sprintf( '/\[(_[a-z]+_)?%s([ \t]+[^]]+)?\]/',
                                                    preg_quote( esc_html($mail_tag), '/' ) );
                                                if( substr(esc_html($mail_tag), 0, 4) == 'file' ) {
                                                    $fileTags .= '<span class="%1$s" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>['.esc_html($mail_tag).']</strong></span> ';
                                                }
                                                echo sprintf(
                                                    '<span class="%1$s" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[%2$s]</strong></span> ',
                                                    'mailtag code used',
                                                    esc_html( $mail_tag ) );
                                                echo '<input type="hidden" name="wp_cf7pdf_tags[]" value="['.esc_html( $mail_tag ).']" />';
                                            }
                                            /*
                                            * ECRIT DANS UN POST-META LES PROPRIETES DES TAGS DU FORMULAIRE 
                                            *
                                            */
                                            $contact_tag = $contact_form->scan_form_tags();
                                            $valOpt = '';
                                            $valTag = '';
                                            foreach ( $contact_tag as $sh_tag ) {
                                                //echo '<br /><br />';
                                                //print_r($sh_tag);
                                                if( !empty($sh_tag["options"]) ) {
                                                    $valOpt = 'array(';
                                                    foreach($sh_tag["options"] as $idOpt=>$opt) {
                                                        $valOpt .= '['.$idOpt.']=>'.$opt;
                                                    }
                                                    $valOpt .= ')';
                                                }
                                                if( !empty($sh_tag["values"]) ) {
                                                    $valTag = 'array(';
                                                    foreach($sh_tag["values"] as $id=>$val) {
                                                        $valTag .= '['.$id.']=>'.$val;
                                                    }
                                                    $valTag .= ')'; 
                                                }
                                                $mail_tag_scan = '['.$sh_tag["name"].'],'.$sh_tag["basetype"].','.$valTag.','.$valOpt;
                                                echo '<input type="hidden" name="wp_cf7pdf_tags_scan[]" value="'.esc_html($mail_tag_scan).'" />';
                                            }
                                        ?>
                                        </td>
                                        <td width="50%"></td>
                                    </tr>
                                    <tr>
                                        <td width="50%"><br /></td>
                                        <td width="50%" style="text-align:center;">
                                            <div style="text-align:right;">
                                                <p>
                                                    <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', WPCF7PDF_TEXT_DOMAIN); ?>"/>
                                                    <?php if( file_exists($createDirectory.'/preview-'.esc_html($idForm).'.pdf') ) { ?>
                                                        <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.esc_html($idForm).'.pdf?ver='.rand(); ?>" ><?php _e('Preview your PDF', WPCF7PDF_TEXT_DOMAIN); ?></a>
                                                    <?php } ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>    
                            </legend>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">                            
                            <textarea name="wp_cf7pdf_settings[generate_pdf]" id="wp_cf7pdf_pdf" cols=70 rows=24 class="widefat textarea"style="height:250px;"><?php if( empty($meta_values['generate_pdf']) ) { echo esc_textarea($messagePdf); } else { echo esc_textarea($meta_values['generate_pdf']); } ?></textarea>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>
    </div>
    <div class="clear">&nbsp;</div>
    <div style="text-align:left;">
        <p>
            <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', WPCF7PDF_TEXT_DOMAIN); ?>"/>
            <?php if( file_exists($createDirectory.'/preview-'.esc_html($idForm).'.pdf') ) { ?>
                <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.esc_html($idForm).'.pdf?ver='.rand(); ?>" ><?php _e('Preview your PDF', WPCF7PDF_TEXT_DOMAIN); ?></a>
            <?php } ?>
        </p>
    </div>

</form>
<?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="false") { ?>
    <div class="clear" style="margin-bottom:15px;">&nbsp;</div>
    <div class="postbox">

        <div class="handlediv" style="height:1px!important;" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><br></div>
        <span class="dashicons customDashicons dashicons-list-view"></span> <h3 class="hndle"><?php _e( 'Last records', WPCF7PDF_TEXT_DOMAIN ); ?></h3>
        <div class="inside">
            <a name="listing"></a>
            <?php
            $limitList = 15;
            $settingsLimit = get_post_meta( esc_html($idForm), '_wp_cf7pdf_limit', true );
            if( isset($settingsLimit) && $settingsLimit > 0 ) { $limitList = $settingsLimit; }
                                                                                            
            $list = cf7_sendpdf::wpcf7pdf_listing(esc_html($idForm), esc_html($limitList));
            if( $list ) { ?>
                <div style="padding:5px;margin-bottom:10px;">
                    <div>
                        <form method="post" action="#listing">

                            <?php wp_nonce_field( 'wpcf7_listing_nonce', 'wpcf7_listing_nonce' ); ?>
                            <input type="hidden" name="idform" value="<?php echo esc_html($idForm); ?>"/>
                            <input type="hidden" name="wpcf7_action" value="listing_settings" />
                            <input type="text" value="<?php echo esc_html($limitList); ?>" size="4" name="listing_limit" > <?php submit_button( __( 'Change', WPCF7PDF_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
                        </form>
                    </div>
                
                    <?php    
                        echo '<table>';
                        echo '<th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th>';

                        foreach($list as $recorder) {
                            echo '<tr width="100%">';
                            $datas = maybe_unserialize($recorder->wpcf7pdf_data);
                            if( isset($datas) && $datas!=false) {
                                echo '<td width="80%">';
                                echo '<a href="'.esc_url($recorder->wpcf7pdf_files).'" target="_blank">'.esc_html($datas[0]) .'</a> - '.esc_html($datas[1]);                                
                                echo '</td>';
                                echo '<td width="5%"><a href="'.esc_url($recorder->wpcf7pdf_files).'" target="_blank"><img src="'.esc_url(WPCF7PDF_URL.'images/icon_download.png').'" width="30" title="'.__('Download', WPCF7PDF_TEXT_DOMAIN).'" alt="'.__('Download', WPCF7PDF_TEXT_DOMAIN).'" /></a></td>';                        
                        ?><td width="5%"><a href="#" data-idform="<?php echo esc_html($idForm); ?>" data-id="<?php echo esc_html($recorder->wpcf7pdf_id); ?>" data-message="<?php _e('Are you sure you want to delete this Record?', WPCF7PDF_TEXT_DOMAIN); ?>" data-nonce="<?php echo wp_create_nonce('delete_record-'.esc_html($recorder->wpcf7pdf_id)); ?>" class="delete-record"><img src="<?php echo esc_url(WPCF7PDF_URL.'images/icon_delete.png'); ?>" width="30" title="<?php _e('Delete', WPCF7PDF_TEXT_DOMAIN); ?>" alt="<?php _e('Delete', WPCF7PDF_TEXT_DOMAIN); ?>" /></a>
                        <?php
                                echo '</td><tr>';
                            }
                            }

                        echo '</table>';
                        
                    ?>
                </div>
                <table width="100%">
                    <tbody>
                        <tr>
                            <td width="5%">
                                <div>
                                    <span class="dashicons dashicons-download"></span> <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=wpcf7-send-pdf&amp;idform='.esc_html($_POST['idform']).'&amp;csv=1'), 'go_generate', 'csv_security'); ?>" alt="<?php _e('Export list', WPCF7PDF_TEXT_DOMAIN); ?>" title="<?php _e('Export list', WPCF7PDF_TEXT_DOMAIN); ?>"><?php _e('Export list in CSV file', WPCF7PDF_TEXT_DOMAIN); ?></a>
                                </div>
                        </tr>
                    </tbody>
                </table>
            <?php } else { _e('Data not found!', WPCF7PDF_TEXT_DOMAIN); } ?>
        </div>
    </div>
<?php } ?>
    
<div class="postbox">
   <div class="handlediv" style="height:1px!important;" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><br></div>
   <span class="dashicons customDashicons dashicons-download"></span> <h3 class="hndle" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><?php _e( 'Export Settings', WPCF7PDF_TEXT_DOMAIN ); ?></h3>
    <div class="inside">
        <form method="post">
            <p>
              <input type="hidden" name="wpcf7_action" value="export_settings" />
              <input type="hidden" name="wpcf7pdf_export_id" value="<?php echo esc_html($idForm); ?>" />
            </p>
            <p>
                <?php wp_nonce_field( 'go_export_nonce', 'wpcf7_export_nonce' ); ?>
                <?php submit_button( __( 'Export', WPCF7PDF_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
            </p>
        </form>
    </div>
</div>

<div class="postbox">
   <div class="handlediv" style="height:1px!important;" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><br></div>
   <span class="dashicons customDashicons dashicons-upload"></span> <h3 class="hndle" title="<?php _e('Click to toggle', WPCF7PDF_TEXT_DOMAIN); ?>"><?php _e( 'Import Settings', WPCF7PDF_TEXT_DOMAIN ); ?></h3>
    <div class="inside">
      <p><?php _e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', WPCF7PDF_TEXT_DOMAIN ); ?></p>
      <form method="post" enctype="multipart/form-data">
          <p>
              <input type="file" name="wpcf7_import_file"/>
          </p>
          <p>
              <input type="hidden" name="wpcf7_action" value="import_settings" />
              <input type="hidden" name="idform" value="<?php echo esc_html($idForm); ?>"/>
              <input type="hidden" name="wpcf7pdf_import_id" value="<?php echo esc_html($idForm); ?>" />
              <?php wp_nonce_field( 'go_import_nonce', 'wpcf7_import_nonce' ); ?>
              <?php submit_button( __( 'Import', WPCF7PDF_TEXT_DOMAIN ), 'secondary', 'submit', false ); ?>
          </p>
      </form>
    </div>
</div>
<?php } ?>
<?php } else { ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
        <?php printf( __('To work I need %s plugin, but it is apparently not installed or not enabled!', WPCF7PDF_TEXT_DOMAIN), '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a>' ); ?>
    </div>
<?php } ?>
    <div style="margin-top:40px;">
        <?php _e('Send PDF for Contact Form 7 is brought to you by', WPCF7PDF_TEXT_DOMAIN); ?> <a href="https://madeby.restezconnectes.fr/" target="_blank">MadeByRestezConnectes</a> - <?php _e('If you found this plugin useful', WPCF7PDF_TEXT_DOMAIN); ?> <a href="https://wordpress.org/support/view/plugin-reviews/send-pdf-for-contact-form-7/" target="_blank"><?php _e('give it 5 &#9733; on WordPress.org', WPCF7PDF_TEXT_DOMAIN); ?></a>
    </div>
</div>
