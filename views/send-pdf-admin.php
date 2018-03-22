<?php

defined( 'ABSPATH' ) or die( 'Not allowed' );

global $current_user;
global $_wp_admin_css_colors;
global $post;

$admin_color = get_user_option( 'admin_color', get_current_user_id() );
$colors      = $_wp_admin_css_colors[$admin_color]->colors;

/* Update des paramètres */
if( (isset($_POST['action']) && isset($_POST['idform']) && $_POST['action'] == 'update') && isset( $_POST['security-sendform'] ) && wp_verify_nonce($_POST['security-sendform'], 'go-sendform') ) {

    if( isset($_POST['wp_cf7pdf_settings']['pdf-uploads-delete']) && $_POST['wp_cf7pdf_settings']['pdf-uploads-delete']=="true" ) {
        
        $dossier_traite = cf7_sendpdf::wpcf7pdf_folder_uploads($_POST['idform']);
        //var_dump($dossier_traite);
        
        if( isset($dossier_traite) && is_dir($dossier_traite) ) {
            
            //$dossier_traite = unserialize($_POST['path_uploads']);
            $repertoire = opendir($dossier_traite); // On définit le répertoire dans lequel on souhaite travailler.
 
            while (false !== ($fichier = readdir($repertoire))) // On lit chaque fichier du répertoire dans la boucle.
            {
            $chemin = $dossier_traite."/".$fichier; // On définit le chemin du fichier à effacer.

            // Si le fichier n'est pas un répertoire…
            if ($fichier != ".." AND $fichier != "." AND !is_dir($fichier))
                   {
                   unlink($chemin); // On efface.
                   }
            }
            closedir($repertoire);
            
            echo '<div id="message" class="updated fade"><p><strong>'.__('The upload folder has been deleted.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
        }

    }

    update_post_meta( intval($_POST['idform']), '_wp_cf7pdf', $_POST["wp_cf7pdf_settings"] );
    if (isset($_POST["wp_cf7pdf_tags"]) ) {
        update_post_meta( intval($_POST['idform']), '_wp_cf7pdf_fields', $_POST["wp_cf7pdf_tags"] );
    }
    if ( isset($_POST["wp_cf7pdf_tags_scan"]) ) {
        update_post_meta( intval($_POST['idform']), '_wp_cf7pdf_fields_scan', $_POST["wp_cf7pdf_tags_scan"] );
    }
    //update_option('wp_cf7pdf_settings', $_POST["wp_cf7pdf_settings"]);
    $options_saved = true;
    echo '<div id="message" class="updated fade"><p><strong>'.__('Options saved.', 'send-pdf-for-contact-form-7').'</strong></p></div>';

}

if( isset($_POST['idform']) && isset($_POST['truncate_table']) && $_POST['truncate_table'] == 'true' ) {

    $DeleteList = cf7_sendpdf::truncate();
    if( $DeleteList == true ) {
        echo '<div id="message" class="updated fade"><p><strong>'.__('All the data has been deleted.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
    }
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
    .parent1 .CodeMirror {
        border: 1px solid #eee;
        height: 250px;
    }
    .parent2 .CodeMirror {
        border: 1px solid #eee;
        height: 550px;
    }
</style>
<div class="wrap">

    <h2 style="font-size: 23px;font-weight: 400;padding: 9px 15px 4px 0px;line-height: 29px;">
        <?php echo __('Send PDF for Contact Form 7 - Settings', 'send-pdf-for-contact-form-7'); ?>
    </h2>

    <?php if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) { ?>
    <div id="wpcf7-bandeau" style="">
        <table width="100%" cellspacing="20">
            <tr>
                <td align="center" valign="middle">
                    <?php
                        $formsList = cf7_sendpdf::getForms();
                        if ( count($formsList) == 0 ) {
                            printf( __('No forms have not been found. %s', 'send-pdf-for-contact-form-7'), '<a href="'.admin_url('admin.php?page=wpcf7').'">'.__('Create your first form here.', 'send-pdf-for-contact-form-7').'</a>');
                        } else {
                    ?>
                    <form method="post" action="<?php echo $_SERVER['REQUEST_URI']?>" name="displayform" id="displayform">
                        <input type="hidden" name="page" value="wpcf7-send-pdf"/>
                        <?php //wp_nonce_field('go-chooseform', 'security-form'); ?>
                        <select name="idform" id="idform" class="wpcf7-form-field" onchange="this.form.submit();">
                            <option value=""><?php echo htmlspecialchars(__('* Select a form *', 'send-pdf-for-contact-form-7')); ?></option>
                            <?php
                                $selected = '';
                                foreach ($formsList as $formName) {
                                    if( isset($_POST['idform']) ) {
                                        $selected = ($formName->ID == $_POST['idform']) ? "selected" : "";
                                    }
                                    $formNameEscaped = htmlentities($formName->post_title, null, 'UTF-8');
                                    echo '<option value="'.$formName->ID.'" '.$selected.'>'.$formNameEscaped.'</option>';
                                }
                            ?>
                        </select>
                    </form>
                    <?php } ?>
                </td>
                <td align="center" width="20%">
                        <div id="wpmimgcreated">
                            <a href="https://restezconnectes.fr" title="Created by RestezConnectes.fr" class="wpcf7-link" alt="Created by RestezConnectes.fr" target="_blank"><img class="wpmresponsive" src="<?php echo plugins_url('send-pdf-for-contact-form-7/images/logo-Extensions.png'); ?>" style="" valign="bottom"  /></a>
                        </div>
                    <h3><?php printf( __('Read %s here !', 'send-pdf-for-contact-form-7'), '<a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/" class="wpcf7-link" target="_blank">'.__('Tutorial', 'send-pdf-for-contact-form-7').'</a>' ); ?></h3>
                </td>
                <td align="right" width="25%">
                    <!-- FAIRE UN DON SUR PAYPAL -->
                    <div style="font-size:0.8em;"><?php _e('If you want Donate (French Paypal) for my current and future developments:', 'send-pdf-for-contact-form-7'); ?><br />
                        <div style="width:350px;margin-left:auto;margin-right:auto;padding:5px;">
                            <a href="https://paypal.me/RestezConnectes/10" target="_blank" class="wpcf7pdfclassname">
                                <img src="<?php echo plugins_url('send-pdf-for-contact-form-7/images/donate.png'); ?>" valign="bottom" width="64" /> <?php _e('Donate now!', 'send-pdf-for-contact-form-7'); ?>
                            </a>
                        </div>
                    </div>
                    <!-- FIN FAIRE UN DON -->
                </td>
            </tr>
        </table>
    </div>

    <?php
    if( isset($_POST['idform']) ) {

        //name,forename,bithday,sex,phone,adress,cp,city,sport,
        $idForm = intval($_POST['idform']);
        $meta_values = get_post_meta( $idForm, '_wp_cf7pdf', true );
        $meta_form = get_post_meta( $idForm, '_form', true);

        /**********************************************/
        /******** ON GENERE UN PDF DE PREVIEW *********/
        /**********************************************/
        // On récupère le dossier upload de WP
        $upload_dir = wp_upload_dir();
        //$upload_dir = wp_upload_dir( null, false, true );
        $createDirectory = cf7_sendpdf::wpcf7pdf_folder_uploads($idForm);
        //echo str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory );
        //echo '<br />'.$createDirectory;
        // On récupère le format de date dans les paramètres
        $date_format = get_option( 'date_format' );
        $hour_format = get_option('time_format');

        // On efface l'ancien pdf renommé si il y a (on garde l'original)
        if( file_exists($createDirectory.'/preview.pdf') ) {
            unlink($createDirectory.'/preview.pdf');
        }
        if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

            if( isset($meta_values['pdf-type']) && isset($meta_values['pdf-orientation']) ) {
                $formatPdf = $meta_values['pdf-type'].$meta_values['pdf-orientation'];
            } else {
                $formatPdf = 'A4-P';
            }
            //include(WPCF7PDF_DIR.'/mpdf/mpdf.php');
            require WPCF7PDF_DIR . '/mpdf/vendor/autoload.php';
            $mpdf=new mPDF('utf-8', $formatPdf);
            $mpdf->autoScriptToLang = true;
            $mpdf->baseScript = 1;
            $mpdf->autoVietnamese = true;
            $mpdf->autoArabic = true;
            $mpdf->autoLangToFont = true;
            $mpdf->SetTitle(get_the_title($idForm));
            $mpdf->SetCreator(get_bloginfo('name'));
            $mpdf->ignore_invalid_utf8 = true;
            
            if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                $footerText = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $meta_values['footer_generate_pdf']);
                $footerText = str_replace('[url-pdf]', $upload_dir['url'].'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $footerText);
                if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                    $dateField = date_i18n($meta_values['date_format']);
                } else {
                    $dateField = date_i18n( $date_format . ' ' . $hour_format, current_time('timestamp'));
                }
                if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                    $timeField = date_i18n($meta_values['time_format']);
                } else {
                    $timeField = date_i18n($hour_format, current_time('timestamp'));
                }
                $footerText = str_replace('[date]', $dateField, $footerText);
                $footerText = str_replace('[time]', $timeField, $footerText);
                $mpdf->SetHTMLFooter($footerText);
            }
            
            if( isset($meta_values["image"]) && !empty($meta_values["image"]) ) {
                if( ini_get('allow_url_fopen')==1) {
                    $image_path = str_replace(get_bloginfo('url'), ABSPATH, $meta_values['image']);
                    list($width, $height, $type, $attr) = getimagesize($image_path);
                } else {
                    $width = 150;
                    $height = 80;
                }
                $imgAlign = 'left';
                if( isset($meta_values['image-alignment']) ) {
                    $imgAlign = $meta_values['image-alignment'];
                }
                if( empty($meta_values['image-width']) ) { $imgWidth = $width; } else { $imgWidth = $meta_values['image-width'];  }
                if( empty($meta_values['image-height']) ) { $imgHeight = $height; } else { $imgHeight = $meta_values['image-height'];  }

                $attribut = 'width='.$imgWidth.' height="'.$imgHeight.'"';
                //$meta_values["image"] = str_replace('https://', 'http://', $meta_values["image"]);
                $mpdf->WriteHTML('<div style="text-align:'.$imgAlign.'"><img src="'.esc_url($meta_values["image"]).'" '.$attribut.' /></div>');
            }
            $messageText = $meta_values['generate_pdf'];
            
            $contact_form = WPCF7_ContactForm::get_instance($idForm);
            $contact_tag = $contact_form->form_scan_shortcode();
            foreach ( $contact_tag as $sh_tag ) {
                
                $tagOptions = $sh_tag["options"];
                
                if( $sh_tag["basetype"] == 'checkbox') {
                    foreach($sh_tag["values"] as $id=>$val) {
                        if( in_array('label_first', $tagOptions) ) {
                            $inputCheckbox .= ''.$val.' <input type="checkbox" name="'.$sh_tag["name"].'" value="'.$val.'" /> ';
                        } else {
                            $inputCheckbox .= '<input type="checkbox" name="'.$sh_tag["name"].'" value="'.$val.'" /> '.$val.' ';
                        }
                        
                    }
                    $messageText = str_replace('['.$sh_tag["name"].']', $inputCheckbox, $messageText);
                } else if ( $sh_tag["basetype"] == 'radio') {
                    foreach($sh_tag["values"] as $id=>$val) {
                        if( in_array('label_first', $tagOptions) ) {
                            $inputRadio .= ''.$val.' <input type="radio" name="'.$sh_tag["name"].'" value="'.$val.'" > ';
                        } else {
                            $inputRadio .= '<input type="radio" name="'.$sh_tag["name"].'" value="'.$val.'" > '.$val.' ';
                        }
                    }
                    $messageText = str_replace('['.$sh_tag["name"].']', $inputRadio, $messageText);
                }
            }
            
            // read all image tags into an array
            preg_match_all('/<img[^>]+>/i', $messageText, $imgTags); 

            for ($i = 0; $i < count($imgTags[0]); $i++) {
                // get the source string
                preg_match('/src="([^"]+)/i', $imgTags[0][$i], $imgage);

                // remove opening 'src=' tag, can`t get the regex right
                $origImageSrc = str_ireplace( 'src="', '',  $imgage[0]);
                if( strpos( $origImageSrc, 'http' ) === false ) {
                
                    $messageText = str_replace( $origImageSrc, WPCF7PDF_DIR.'images/temporary-image.jpg', $messageText);
                    //error_log( 'OK image pas trouvée :'.$origImageSrc ); //not blank, all sorts of stuff
                }
            }
            
            if( empty( $meta_values["linebreak"] ) or ( isset($meta_values["linebreak"]) && $meta_values["linebreak"] == 'false') ) {
                $messageText = preg_replace("/(\r\n|\n|\r)/", "<div></div>", $messageText);
                $messageText = str_replace("<div></div><div></div>", '<div style="height:10px;"></div>', $messageText);
            }
            $messageText = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $messageText);
            $messageText = str_replace('[url-pdf]', $upload_dir['url'].'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $messageText);
            if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                $dateField = date_i18n($meta_values['date_format']);
            } else {
                $dateField = date_i18n( $date_format . ' ' . $hour_format, current_time('timestamp'));
            }
            if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                $timeField = date_i18n($meta_values['time_format']);
            } else {
                $timeField = date_i18n($hour_format, current_time('timestamp'));
            }
            $messageText = str_replace('[date]', $dateField, $messageText);
            $messageText = str_replace('[time]', $timeField, $messageText);

            // En cas de saut de page avec le tag [addpage]
            if( stripos($messageText, '[addpage]') !== false ) {

                $newPage = explode('[addpage]', $messageText);
                for($i = 0, $size = count($newPage); $i < $size; ++$i) {
                    $mpdf->WriteHTML($newPage[$i]);
                    if( $i < (count($newPage)-1) ) {
                        $mpdf->AddPage();
                    }
                }

            } else {

                $mpdf->WriteHTML( wpautop( $messageText ) );

            }
            if ( isset($meta_values["protect"]) && $meta_values["protect"]=='true') {
                $pdfPassword = '';
                if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) {
                    $pdfPassword = $meta_values["protect_password"];
                }
                $mpdf->SetProtection(array(), $pdfPassword, '');
            }
            $mpdf->Output($createDirectory.'/preview-'.$idForm.'.pdf', 'F');

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
<input type="hidden" name="idform" value="<?php echo $idForm; ?>"/>
<input type="hidden" name="path_uploads" value="<?php echo $pathFolder; ?>" />
<?php wp_nonce_field('go-sendform', 'security-sendform'); ?>

<div style="text-align:right;">
    <p>
        <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
    </p>
</div>

<!-- PARAMETRES GENERAUX -->
  <div class="postbox">
     <div class="handlediv" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
      <h3 class="hndle" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><span class="dashicons dashicons-media-document"></span> <?php _e('General Settings', 'send-pdf-for-contact-form-7'); ?></h3>
      <div class="inside">

    <!-- Disable GENERATE PDF -->
    <table class="wp-list-table widefat fixed" cellspacing="0">
        <tbody id="the-list">

            <tr>
                <td style="vertical-align: middle;margin-top:15px;"><?php _e('Disable generate PDF?', 'send-pdf-for-contact-form-7'); ?></td>
                <td align="left">
                    <div style="">
                      <div class="switch-field">
                          <input class="switch_left" type="radio" id="switch_left" name="wp_cf7pdf_settings[disable-pdf]" value="true" <?php if( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=='true') { echo ' checked'; } ?>/>
                          <label for="switch_left"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                          <input class="switch_right" type="radio" id="switch_right" name="wp_cf7pdf_settings[disable-pdf]" value="false" <?php if( empty($meta_values["disable-pdf"]) || (isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=='false') ) { echo ' checked'; } ?> />
                          <label for="switch_right"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                      </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><?php _e('Who send the PDF file?', 'send-pdf-for-contact-form-7'); ?></td>
                <td>
                    <select name="wp_cf7pdf_settings[send-attachment]" class="wpcf7-form-field">
                        <option value="sender"<?php if( isset($meta_values["send-attachment"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                        <option value="recipient"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                        <option value="both"<?php if( (isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "both") || empty($meta_values["send-attachment"]) ) { echo ' selected'; } ?>><?php _e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                    </select>
                </td>
            </tr>
            <tr><td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td></tr>
            <tr style="vertical-align: middle;margin-top:15px;">
                <td><?php _e('Disable Insert subscribtion in database?', 'send-pdf-for-contact-form-7'); ?></td>
                <td align="left">
					<div style="">
                      <div class="switch-field">
                          <input class="switch_left" type="radio" id="switch_insert" name="wp_cf7pdf_settings[disable-insert]" value="true" <?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='true') { echo ' checked'; } ?>/>
                          <label for="switch_insert"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                          <input class="switch_right" type="radio" id="switch_insert_no" name="wp_cf7pdf_settings[disable-insert]" value="false" <?php if( empty($meta_values["disable-insert"]) || (isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='false') ) { echo ' checked'; } ?> />
                          <label for="switch_insert_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                      </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><?php _e('Truncate database?', 'send-pdf-for-contact-form-7'); ?></td>
                <td>
                    <div style="">
                      <div class="switch-field">
                          <input class="switch_left" type="radio" id="switch_truncate" name="truncate_table" value="true" />
                          <label for="switch_truncate"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                          <input class="switch_right" type="radio" id="switch_truncate_no" name="truncate_table" value="false" checked />
                          <label for="switch_truncate_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                      </div>
                    </div>
                </td>
            </tr>
            <tr><td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td></tr>
            <tr>
                <td><?php _e('Disable generate CSV file?', 'send-pdf-for-contact-form-7'); ?></td>
                <td>
                    <div style="">
                      <div class="switch-field">
                          <input class="switch_left" type="radio" id="switch_csv" name="wp_cf7pdf_settings[disable-csv]" value="true" <?php if( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='true') { echo ' checked'; } ?>/>
                          <label for="switch_csv"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                          <input class="switch_right" type="radio" id="switch_csv_no" name="wp_cf7pdf_settings[disable-csv]" value="false" <?php if( empty($meta_values["disable-csv"]) || (isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false') ) { echo ' checked'; } ?> />
                          <label for="switch_csv_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                      </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><?php _e('Who send the CSV file?', 'send-pdf-for-contact-form-7'); ?></td>
                <td>
                    <select name="wp_cf7pdf_settings[send-attachment2]" class="wpcf7-form-field">
                        <option value="sender"<?php if( isset($meta_values["send-attachment2"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                        <option value="recipient"<?php if( isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                        <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "both") || empty($meta_values["send-attachment2"]) ) { echo ' selected'; } ?>><?php _e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                    </select>
                </td>
            </tr>
            <tr><td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td></tr>
            <tr>
                <td>
                    <?php _e('Enter a name for your PDF', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("By default, the file's name will be 'document-pdf'", 'send-pdf-for-contact-form-7'); ?></i>)</p>
                    <br />
                    <p><?php _e("You can use this tags (separated by commas):", 'send-pdf-for-contact-form-7'); ?></p>
                    <p>
                    <span class="dashicons dashicons-arrow-right"></span> <?php echo sprintf( __('Use %s in the name of your PDF', 'send-pdf-for-contact-form-7'), ' <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span>' ); ?><br />
                    <span class="dashicons dashicons-arrow-right"></span> <?php echo sprintf( __('Use %s in the name of your PDF', 'send-pdf-for-contact-form-7'), ' <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[date]</strong></span>' ); ?><br />
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
                        &nbsp;&nbsp;<small><?php _e('Enter date format without space, -, /, _, etc..', 'send-pdf-for-contact-form-7'); ?></small><input size="5" type= "text" name="wp_cf7pdf_settings[date-for-name]" value="<?php echo $dateForName; ?>" /> <?php echo date_i18n($dateForName, current_time('timestamp')); ?>
                    </p>

                </td>
                <td>
                    <input type= "text" class="wpcf7-form-field" name="wp_cf7pdf_settings[pdf-name]" value="<?php if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) { echo $meta_values["pdf-name"]; } ?>">.pdf<br /><br /><br />
                    <input type="text" class="wpcf7-form-field" size="30" name="wp_cf7pdf_settings[pdf-add-name]" value="<?php if( isset($meta_values["pdf-add-name"]) && !empty($meta_values["pdf-add-name"]) ) { echo $meta_values["pdf-add-name"]; } ?>" />
                </td>

            </tr>
            <tr>
                <td>
                    <?php _e('Change uploads folder?', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("By default, the uploads folder's name is in /wp-content/uploads/*YEAR*/*MONTH*/", 'send-pdf-for-contact-form-7'); ?></i>)</p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_uploads" name="wp_cf7pdf_settings[pdf-uploads]" value="true" <?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_uploads"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_uploads_no" name="wp_cf7pdf_settings[pdf-uploads]" value="false" <?php if( empty($meta_values["pdf-uploads"]) || (isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_uploads_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <?php _e('Delete all files into this uploads folder?', 'send-pdf-for-contact-form-7'); ?><p></p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_delete" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="true" />
                        <label for="switch_delete"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_delete_no" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="false" checked />
                        <label for="switch_delete_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <?php _e('Delete each PDF file after send the email?', 'send-pdf-for-contact-form-7'); ?><p></p>
                </td>
                <td>
                    <div style="">
                      <div class="switch-field">
                          <input class="switch_left" type="radio" id="switch_filedelete" name="wp_cf7pdf_settings[pdf-file-delete]" value="true" <?php if( isset($meta_values["pdf-uploads-delete"]) && $meta_values["pdf-file-delete"]=='true') { echo ' checked'; } ?>/>
                          <label for="switch_filedelete"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                          <input class="switch_right" type="radio" id="switch_filedelete_no" name="wp_cf7pdf_settings[pdf-file-delete]" value="false" <?php if( empty($meta_values["pdf-file-delete"]) || (isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=='false') ) { echo ' checked'; } ?> />
                          <label for="switch_filedelete_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                      </div>
                    </div>
                </td>
            </tr>
            <tr><td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td></tr>
            <tr>
                <td><?php _e('Other files attachments?', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("Enter one URL file by line", 'send-pdf-for-contact-form-7'); ?></i>)</p><textarea class="wpcf7-form-field" cols="100%" rows="5" name="wp_cf7pdf_settings[pdf-files-attachments]"><?php if( isset($meta_values["pdf-files-attachments"]) ) { echo esc_textarea($meta_values["pdf-files-attachments"]); } ?></textarea>
                </td>
            </tr>
            <tr>
                <td><?php _e('Who send the attachments file?', 'send-pdf-for-contact-form-7'); ?></td>
                <td>
                    <select name="wp_cf7pdf_settings[send-attachment3]" class="wpcf7-form-field" >
                        <option value="sender"<?php if( isset($meta_values["send-attachment3"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                        <option value="recipient"<?php if( isset($meta_values["send-attachment3"]) && $meta_values["send-attachment3"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                        <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment3"] == "both") || empty($meta_values["send-attachment3"]) ) { echo ' selected'; } ?>><?php _e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                    </select>
                </td>
            </tr>
            <tr><td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td></tr>
            <tr>
                <td>
                    <?php _e('Select a page to display after sending the form (optional)', 'send-pdf-for-contact-form-7'); ?>
                </td>
                <td>
                    <?php
                        if( isset($meta_values['page_next']) ) {
                            $idSelectPage = $meta_values['page_next'];
                        } else {
                            $idSelectPage = 0;
                        }
                        $args = array('name' => 'wp_cf7pdf_settings[page_next]', 'class' => 'wpcf7-form-field', 'selected' => $idSelectPage, 'show_option_none' => __('Please select a page', 'send-pdf-for-contact-form-7') );
                        wp_dropdown_pages($args);
                    ?>
                </td>
            </tr>
            <tr>
                <td><!-- Rediriger sur cette page sans envoyer un e-mail? -->
                    <?php _e('Send email without attachments?', 'send-pdf-for-contact-form-7'); ?><p></p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_attachments" name="wp_cf7pdf_settings[disable-attachments]" value="true" <?php if( isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_attachments"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_attachments_no" name="wp_cf7pdf_settings[disable-attachments]" value="false" <?php if( empty($meta_values["disable-attachments"]) || (isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_attachments_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><!-- Propose de télécharger le pdf? -->
                    <?php _e('Use a link in the redirect page for download PDF?', 'send-pdf-for-contact-form-7'); ?>
                    <p><i><?php _e('* Requires enable option "insert into a database"', 'send-pdf-for-contact-form-7'); ?></i></p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_download" name="wp_cf7pdf_settings[download-pdf]" value="true" <?php if( isset($meta_values["download-pdf"]) && $meta_values["download-pdf"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_download"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_download_no" name="wp_cf7pdf_settings[download-pdf]" value="false" <?php if( empty($meta_values["download-pdf"]) || (isset($meta_values["download-pdf"]) && $meta_values["download-pdf"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_download_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><!-- Rediriger sur cette page sans envoyer un e-mail? -->
                    <?php _e('Enter the name for the link', 'send-pdf-for-contact-form-7'); ?>
                    <p><i><?php _e( 'Use this shortcode : [wpcf7pdf_download]', 'send-pdf-for-contact-form-7'); ?></i></p>
                </td>
                <td>
                    <input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[text-link]" value="<?php if( empty($meta_values["text-link"]) or $meta_values["text-link"]=="" ) { _e('Download your PDF', 'send-pdf-for-contact-form-7'); } else { echo $meta_values["text-link"]; } ?>">
                </td>
            </tr>
            <tr>
                <td><!-- Propose la redirection vers le pdf direct -->
                    <?php _e('Redirects directly to the PDF after sending the form?', 'send-pdf-for-contact-form-7'); ?>
                    <p><i><?php _e( 'This option disable the Page Redirection selected', 'send-pdf-for-contact-form-7'); ?></i></p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_redirect" name="wp_cf7pdf_settings[redirect-to-pdf]" value="true" <?php if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_redirect"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_redirect_no" name="wp_cf7pdf_settings[redirect-to-pdf]" value="false" <?php if( empty($meta_values["redirect-to-pdf"]) || (isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_redirect_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div><br />
                    <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="on" <?php if( (isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='on') or empty($meta_values["redirect-window"]) ) { echo ' checked'; } ?>  /><?php _e('Same Window', 'send-pdf-for-contact-form-7'); ?> <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="off" <?php if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='off') { echo 'checked'; } ?> /><?php _e('New Window', 'send-pdf-for-contact-form-7'); ?>
                </td>
            </tr>
            <tr>
                <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
            </tr>
            <tr>
                <td>
                    <?php _e('Select a date and time format', 'send-pdf-for-contact-form-7'); ?><br /><p><i><?php _e('By default, the date format is defined in the admin settings', 'send-pdf-for-contact-form-7'); ?> (<a href="https://codex.wordpress.org/Formatting_Date_and_Time" target="_blank"><?php _e('Formatting Date and Time', 'send-pdf-for-contact-form-7'); ?></a>)</i></p>
                </td>
                <td>
                    <?php

                      $formatDate = stripslashes($date_format . ' ' . $hour_format);
                      $formatTime = $hour_format;
                      if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                        $formatDate = stripslashes($meta_values['date_format']);
                      }
                      if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                        $formatTime = stripslashes($meta_values['time_format']);
                      }
                      ?>
                      <input id="date_format" class="wpcf7-form-field" size="16" name="wp_cf7pdf_settings[date_format]" value="<?php echo $formatDate; ?>" type="text" /> <?php _e('Date:', 'send-pdf-for-contact-form-7'); ?> <?php echo date_i18n($formatDate); ?><br />
                      <input id="time_format" size="16" class="wpcf7-form-field" name="wp_cf7pdf_settings[time_format]" value="<?php echo $formatTime; ?>" type="text" /> <?php _e('Time:', 'send-pdf-for-contact-form-7'); ?> <?php echo date_i18n($formatTime); ?>
                </td>
            </tr>
            <tr>
                <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
            </tr>
            <tr>
                <td><!-- Propose de télécharger le pdf? -->
                    <?php _e('Desactivate line break auto?', 'send-pdf-for-contact-form-7'); ?>
                    <p><i><?php _e('This disables automatic line break replacement (\n and \r)', 'send-pdf-for-contact-form-7'); ?></i></p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_linebreak" name="wp_cf7pdf_settings[linebreak]" value="true" <?php if( isset($meta_values["linebreak"]) && $meta_values["linebreak"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_linebreak"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_linebreak_no" name="wp_cf7pdf_settings[linebreak]" value="false" <?php if( empty($meta_values["linebreak"]) || (isset($meta_values["linebreak"]) && $meta_values["linebreak"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_linebreak_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
            </tr>
            <tr>
                <td><!-- Proteger le pdf? -->
                    <?php _e('Protect your PDF file?', 'send-pdf-for-contact-form-7'); ?>
                    <p><i><?php _e('Use [pdf-password] tag in your emails.', 'send-pdf-for-contact-form-7'); ?></i></p>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_protect" name="wp_cf7pdf_settings[protect]" value="true" <?php if( isset($meta_values["protect"]) && $meta_values["protect"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_protect"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_protect_no" name="wp_cf7pdf_settings[protect]" value="false" <?php if( empty($meta_values["protect"]) || (isset($meta_values["protect"]) && $meta_values["protect"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_protect_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td><!-- Proteger le pdf? -->
                    <?php _e('Generate and use a random password?', 'send-pdf-for-contact-form-7'); ?>
                    <p><i><?php _e('Example (not working in preview mode):', 'send-pdf-for-contact-form-7'); echo ' <strong>'.cf7_sendpdf::wpcf7pdf_generateRandomPassword().'</strong>'; ?></i></p>
                    <?php _e('Or enter your unique password for all PDF files.', 'send-pdf-for-contact-form-7'); ?>
                </td>
                <td>
                    <div style="">
                        <div class="switch-field">
                        <input class="switch_left" type="radio" id="switch_protect_uniquepassword" name="wp_cf7pdf_settings[protect_uniquepassword]" value="true" <?php if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true') { echo ' checked'; } ?>/>
                        <label for="switch_protect_uniquepassword"><?php _e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                        <input class="switch_right" type="radio" id="switch_protect_uniquepassword_no" name="wp_cf7pdf_settings[protect_uniquepassword]" value="false" <?php if( empty($meta_values["protect_uniquepassword"]) || (isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='false') ) { echo ' checked'; } ?> />
                        <label for="switch_protect_uniquepassword_no"><?php _e('No', 'send-pdf-for-contact-form-7'); ?></label>
                        </div>
                    </div>
                    <input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password]" value="<?php if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) { echo stripslashes($meta_values["protect_password"]); } ?>" />
                </td>
            </tr>
            <tr>
                <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
            </tr>

        </tbody>
    </table>
    </div>
    </div>
    <div class="clear">&nbsp;</div>

    <!-- MISE EN PAGE -->
   <div class="postbox">
     <div class="handlediv" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
      <h3 class="hndle" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><span class="dashicons dashicons-media-document"></span> <?php _e('Layout of your PDF', 'send-pdf-for-contact-form-7'); ?></h3>
      <div class="inside openinside">

        <table class="wp-list-table widefat fixed" cellspacing="0">
            <tbody id="the-list">
                <tr>
                    <td>
                        <h3 class="hndle"><span class="dashicons dashicons-format-image"></span> <?php _e('Image header', 'send-pdf-for-contact-form-7'); ?></h3>
                    </td>
                </tr>
                <tr>
                    <td>
                        <?php _e('Enter a URL or upload an image.', 'send-pdf-for-contact-form-7'); ?><br /><br />
                        <input id="upload_image" size="80%" class="wpcf7-form-field" name="wp_cf7pdf_settings[image]" value="<?php if( isset($meta_values['image']) ) { echo esc_url($meta_values['image']); } ?>" type="text" /> <a href="#" id="upload_image_button" class="button" OnClick="this.blur();"><span> <?php _e('Select or Upload your picture', 'send-pdf-for-contact-form-7'); ?> </span></a> <br />
                        <div style="margin-top:0.8em;">
                            <select name="wp_cf7pdf_settings[image-alignment]" class="wpcf7-form-field">
                                <option value="left" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='left') ) { echo 'selected'; } ?>><?php _e('Left', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="center" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='center') ) { echo 'selected'; } ?>><?php _e('Center', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="right" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='right') ) { echo 'selected'; } ?>><?php _e('Right', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                            <?php _e('Size', 'send-pdf-for-contact-form-7'); ?> <input type="text" class="wpcf7-form-field" size="3" name="wp_cf7pdf_settings[image-width]" value="<?php if( isset($meta_values['image-width']) ) { echo $meta_values['image-width']; } else { echo '150'; } ?>" />&nbsp;X&nbsp;<input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[image-height]" size="3" value="<?php if( isset($meta_values['image-height']) ) { echo $meta_values['image-height']; } ?>" />px

                        </div>
                    </td>
                    <td align="center">
                        <?php if( isset($meta_values['image']) ) { echo '<img src="'.esc_url($meta_values['image']).'" width="150">'; } ?><br />
                        <?php
                            if( !empty($meta_values["image"]) && ini_get('allow_url_fopen')==1 ) {
                                $image_path = str_replace(get_bloginfo('url'), ABSPATH, $meta_values['image']);
                                list($width, $height, $type, $attr) = getimagesize($image_path);
                                echo '<i>('.__('Original size is', 'send-pdf-for-contact-form-7').' '.$width.'px X '.$height.'px)</i>';
                            }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
                </tr>
                
                <tr>
                    <td>
                        <h3 class="hndle"><span class="dashicons dashicons-arrow-down-alt"></span> <?php _e('Footer', 'send-pdf-for-contact-form-7'); ?></h3>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <?php _e('You can use this tags:', 'send-pdf-for-contact-form-7'); ?><br />
                        <ul>
                            <li><?php _e('<strong>{PAGENO}/{nbpg}</strong> will be replaced by the current page number / total pages.', 'send-pdf-for-contact-form-7'); ?></li>
                            <li><?php _e('<strong>{DATE j-m-Y}</strong> will be replaced by the current date. j-m-Y can be replaced by any of the valid formats used in the php <a href="http://www.php.net/manual/en/function.date.php" target="_blank">date()</a> function.', 'send-pdf-for-contact-form-7'); ?></li>
                            <li><?php _e('<strong>[reference] [date]</strong> and <strong>[time]</strong> tags works also.', 'send-pdf-for-contact-form-7'); ?></li>
                        </ul>
                        <textarea id="cf7pdf_html_footer" class="footer_html" name="wp_cf7pdf_settings[footer_generate_pdf]" rows="15" cols="80%"><?php if( isset( $meta_values['footer_generate_pdf']) ) { echo esc_textarea($meta_values['footer_generate_pdf']); } ?></textarea>
                    </td>
                </tr>
                <tr>
                    <td colspan="2"><hr style="background-color: <?php echo $colors[2]; ?>; height: 1px; border: 0;"></td>
                </tr>

                <tr>
                    <td>
                        <h3 class="hndle"><span class="dashicons dashicons-media-document"></span> <?php _e('Personalize your PDF', 'send-pdf-for-contact-form-7'); ?></h3>
                    </td>
                </tr>
                <tr>
                    <tr>
                        <td><?php _e('Page size & Orientation', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <?php if( empty($meta_values['pdf-type']) ) { $meta_values['pdf-type']="A4"; } ?>
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
                                    echo '<option value="A'.$typeA.'"';
                                        if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='A'.$typeA) ) { echo 'selected'; }
                                    echo '>A'.$typeA.'</option>';
                                }
                                for ($typeB = 0; $typeB <= 10; $typeB++) {
                                    echo '<option value="B'.$typeB.'"';
                                        if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='B'.$typeB) ) { echo 'selected'; }
                                    echo '>B'.$typeB.'</option>';
                                }
                                for ($typeC = 0; $typeC <= 10; $typeC++) {
                                    echo '<option value="C'.$typeC.'"';
                                        if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='C'.$typeC) ) { echo 'selected'; }
                                    echo '>C'.$typeC.'</option>';
                                }
                                for ($typeRA = 0; $typeRA <= 4; $typeRA++) {
                                    echo '<option value="RA'.$typeRA.'"';
                                        if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='RA'.$typeRA) ) { echo 'selected'; }
                                    echo '>RA'.$typeRA.'</option>';
                                }
                                for ($typeSRA = 0; $typeSRA <= 4; $typeSRA++) {
                                    echo '<option value="SRA'.$typeSRA.'"';
                                        if( isset($meta_values['pdf-type']) && ($meta_values['pdf-type']=='SRA'.$typeSRA) ) { echo 'selected'; }
                                    echo '>SRA'.$typeSRA.'</option>';
                                }
                                ?>
                            </select>
                            <?php if( empty($meta_values['pdf-orientation']) ) { $meta_values['pdf-orientation']="-P"; } ?>
                            <select name="wp_cf7pdf_settings[pdf-orientation]" class="wpcf7-form-field">
                                <option value="-P" <?php if( isset($meta_values['pdf-orientation']) && ($meta_values['pdf-orientation']=='-P') ) { echo 'selected'; } ?>><?php _e('Portrait', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="-L" <?php if( isset($meta_values['pdf-orientation']) && ($meta_values['pdf-orientation']=='-L') ) { echo 'selected'; } ?>><?php _e('Landscape', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                        <legend>
                            <?php _e('For personalize your PDF you can in the following text field, use these mail-tags:', 'send-pdf-for-contact-form-7'); ?><br />
                            <table>
                                <tr>
                                    <td width="50%">
                                        <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[addpage]</strong></span><br /><i><?php _e("[addpage] is a simple tag to force a page break anywhere in your PDF.", 'send-pdf-for-contact-form-7'); ?></i>
                                    </td>
                                    <td width="50%">
                                        <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[date]</strong></span> <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[time]</strong></span><br /><i><?php _e("Use [date] and [time] to print the date and time anywhere in your PDF.", 'send-pdf-for-contact-form-7'); ?></i>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%">
                                        <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span><br /><i><?php _e("[reference] is a simple mail-tag who is used for create unique PDF. It's also recorded in the database. Every PDF is named like this : name-pdf-uniqid() and it's uploaded in the upload folder of WordPress. For example : document-pdf-56BC4A3EF0752.pdf", 'send-pdf-for-contact-form-7'); ?></i>
                                    </td>
                                    <td width="50%">
                                        <?php if( empty($fileTags) || ( isset($fileTags) && $fileTags == '') ) { $fileTags = '[file-1][file-2]'; } ?>
                                        <i><?php echo sprintf( __('The <strong>[file]</strong> tags are for images? Enter them here to display them in images on your PDF and like this: %s', 'send-pdf-for-contact-form-7'), $fileTags ); ?></i><br /><small><?php _e('It will then be necessary to put them in the image HTML tag for the PDF layout.', 'send-pdf-for-contact-form-7'); ?></small><br /><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[file_tags]" size="80%" value="<?php if( isset($meta_values['file_tags'])) { echo $meta_values['file_tags']; } ?>" />
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2">
                                    <?php
                                            /*
                                             * ECRIT DANS UN POST-META LES TAGS DU FORMULAIRE 
                                             *
                                             */
                                            $contact_form = WPCF7_ContactForm::get_instance($idForm);
                                            $fileTags = '';
        
                                            foreach ( (array) $contact_form->collect_mail_tags() as $mail_tag ) {
                                                $pattern = sprintf( '/\[(_[a-z]+_)?%s([ \t]+[^]]+)?\]/',
                                                    preg_quote( $mail_tag, '/' ) );
                                                if( substr($mail_tag, 0, 4) == 'file' ) {
                                                    $fileTags .= '<span class="%1$s" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>['.$mail_tag.']</strong></span> ';
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
                                                echo '<input type="hidden" name="wp_cf7pdf_tags_scan[]" value="'.$mail_tag_scan.'" />';
                                            }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="50%"><br /></td>
                                    <td width="50%" align="center">
                                        <?php if( file_exists($createDirectory.'/preview-'.$idForm.'.pdf') ) { ?><br />
                                        <a href="<?php echo str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/preview-'.$idForm.'.pdf?ver='.$_SESSION['pdf_uniqueid']; ?>" target="_blank"><span class="preview-btn" style="padding:10px;"><?php _e('Preview your PDF', 'send-pdf-for-contact-form-7'); ?></span></a>
                                        <?php } ?>
                                    </td>
                                </tr>
                            </table>
                        </legend>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <textarea id="cf7pdf_html" class="text_html" name="wp_cf7pdf_settings[generate_pdf]" rows="45" cols="80%"><?php if( empty($meta_values['generate_pdf']) ) { echo $messagePdf; } else { echo esc_textarea($meta_values['generate_pdf']); } ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    </div>
    <div class="clear">&nbsp;</div>

    <?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="false") { ?>
    <table width="100%">
        <tbody>
            <tr>
                <td width="50%">
                     <div>
                        <span class="dashicons dashicons-download"></span> <a href="<?php echo wp_nonce_url( admin_url('admin.php?page=wpcf7-send-pdf&amp;idform='.intval($_POST['idform']).'&amp;csv=1'), 'go_generate', 'csv_security'); ?>" alt="<?php _e('Export list', 'send-pdf-for-contact-form-7'); ?>" title="<?php _e('Export list', 'send-pdf-for-contact-form-7'); ?>"><?php _e('Export list in CSV file', 'send-pdf-for-contact-form-7'); ?></a>
                    </div>
            </tr>
        </tbody>
    </table>
    <?php } ?>

    <ul>
        <li>
            <p>
                <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
            </p>
        </li>
    </ul>

</form>
<div class="clear" style="margin-bottom:15px;">&nbsp;</div>
<div class="postbox">
   <div class="handlediv" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
    <h3 class="hndle" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><span class="dashicons dashicons-download"></span> <?php _e( 'Export Settings', 'send-pdf-for-contact-form-7' ); ?></h3>
    <div class="inside">
        <form method="post">
            <p>
              <input type="hidden" name="wpcf7_action" value="export_settings" />
              <input type="hidden" name="wpcf7pdf_export_id" value="<?php echo $idForm; ?>" />
            </p>
            <p>
                <?php wp_nonce_field( 'wpcf7_export_nonce', 'wpcf7_export_nonce' ); ?>
                <?php submit_button( __( 'Export', 'send-pdf-for-contact-form-7' ), 'secondary', 'submit', false ); ?>
            </p>
        </form>
    </div>
</div>
<div class="postbox">
   <div class="handlediv" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
    <h3 class="hndle" title="<?php _e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><span class="dashicons dashicons-upload"></span> <?php _e( 'Import Settings', 'send-pdf-for-contact-form-7' ); ?></h3>
    <div class="inside">
      <p><?php _e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', 'send-pdf-for-contact-form-7' ); ?></p>
      <form method="post" enctype="multipart/form-data">
          <p>
              <input type="file" name="wpcf7_import_file"/>
          </p>
          <p>
              <input type="hidden" name="wpcf7_action" value="import_settings" />
              <input type="hidden" name="idform" value="<?php echo $idForm; ?>"/>
              <input type="hidden" name="wpcf7pdf_import_id" value="<?php echo $idForm; ?>" />
              <?php wp_nonce_field( 'wpcf7_import_nonce', 'wpcf7_import_nonce' ); ?>
              <?php submit_button( __( 'Import', 'send-pdf-for-contact-form-7' ), 'secondary', 'submit', false ); ?>
          </p>
      </form>
    </div>
</div>
<script>
    var mixedMode = {
        name: "htmlmixed",
        scriptTypes: [{matches: /\/x-handlebars-template|\/x-mustache/i,
                       mode: null},
                      {matches: /(text|application)\/(x-)?vb(a|script)/i,
                       mode: "vbscript"}]
      };
      var textEditor = CodeMirror.fromTextArea(document.getElementById("cf7pdf_html"), {
        mode: mixedMode,
        selectionPointer: true,
        lineNumbers: true,
        textWrapping: true,
        lineWrapping: true,
        theme: "pastel-on-dark",
        addModeClass: true
      });
        textEditor.setSize('100%', 550);
      var footerEditor = CodeMirror.fromTextArea(document.getElementById("cf7pdf_html_footer"), {
        mode: mixedMode,
        selectionPointer: true,
        lineNumbers: true,
        textWrapping: true,
        lineWrapping: true,
        theme: "pastel-on-dark",
        addModeClass: true
      });
    footerEditor.setSize('100%', 'auto');
</script>
<?php } ?>
<?php } else { ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
        <?php printf( __('To work I need %s plugin, but it is apparently not installed or not enabled!', 'send-pdf-for-contact-form-7'), '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a>' ); ?>
    </div>
<?php } ?>
    <div style="margin-top:40px;">
        <?php _e('Send PDF for Contact Form 7 is brought to you by', 'send-pdf-for-contact-form-7'); ?> <a href="https://restezconnectes.fr/" target="_blank">Restez Connectés</a> - <?php _e('If you found this plugin useful', 'send-pdf-for-contact-form-7'); ?> <a href="https://wordpress.org/support/view/plugin-reviews/send-pdf-for-contact-form-7/" target="_blank"><?php _e('give it 5 &#9733; on WordPress.org', 'send-pdf-for-contact-form-7'); ?></a>
    </div>
</div>
