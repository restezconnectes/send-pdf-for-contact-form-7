<?php

defined( 'ABSPATH' ) or die( 'Not allowed' );

/* Update des paramètres */
if( (isset($_POST['action']) && isset($_POST['idform']) && $_POST['action'] == 'update') && isset( $_POST['security-sendform'] ) && wp_verify_nonce($_POST['security-sendform'], 'go-sendform') ) {
    
    if( isset($_POST['wp_cf7pdf_settings']['pdf-uploads-delete']) && $_POST['wp_cf7pdf_settings']['pdf-uploads-delete']==true ) {
        
        if( isset($_POST['path_uploads']) && is_dir($_POST['path_uploads']) ) {
            rmdir( $_POST['path_uploads'], true );
            echo '<div id="message" class="updated fade"><p><strong>'.__('The upload folder has been deleted.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
        }
        
    }
    
    update_post_meta( intval($_POST['idform']), '_wp_cf7pdf', $_POST["wp_cf7pdf_settings"] );
    if (isset($_POST["wp_cf7pdf_tags"]) ) {
        update_post_meta( intval($_POST['idform']), '_wp_cf7pdf_fields', $_POST["wp_cf7pdf_tags"] );
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
            var range = document.createRange();
            range.selectNode(el);
            window.getSelection().addRange(range);
        }
    });
}
</script>
<div class="wrap">
    
    <h2 style="font-size: 23px;font-weight: 400;padding: 9px 15px 4px 0px;line-height: 29px;">
        <?php echo __('Send PDF for Contact Form 7 - Settings', 'send-pdf-for-contact-form-7'); ?>
    </h2>
    
    <?php if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) { ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
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
                        <select name="idform" id="idform" onchange="this.form.submit();">
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
                            <a href="https://restezconnectes.fr" title="Created by RestezConnectes.fr" alt="Created by RestezConnectes.fr" target="_blank"><img class="wpmresponsive" src="<?php echo plugins_url('send-pdf-for-contact-form-7/images/logo-Extensions.png'); ?>" style="" valign="bottom"  /></a>
                        </div>
                    <h3><?php printf( __('Read %s here !', 'send-pdf-for-contact-form-7'), '<a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/" target="_blank">'.__('Tutorial', 'send-pdf-for-contact-form-7').'</a>' ); ?></h3>
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

            include(WPCF7PDF_DIR.'/mpdf/mpdf.php');
            $mpdf=new mPDF();
            $mpdf->autoScriptToLang = true;
            $mpdf->baseScript = 1;
            $mpdf->autoVietnamese = true;
            $mpdf->autoArabic = true;
            $mpdf->autoLangToFont = true;
            $mpdf->ignore_invalid_utf8 = true;
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
                $meta_values["image"] = str_replace('https://', 'http://', $meta_values["image"]);
                $mpdf->WriteHTML('<div style="text-align:'.$imgAlign.'"><img src="'.esc_url($meta_values["image"]).'" '.$attribut.' /></div>');
            }
            $messageText = $meta_values['generate_pdf'];
            $messageText = str_replace('https://', 'http://', $messageText);
            $messageText = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $messageText);
            $messageText = str_replace('[url-pdf]', $upload_dir['url'].'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $messageText);
            if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                $dateField = date_i18n($meta_values['date_format']);
            } else {
                $dateField = date_i18n( $date_format . ' ' . $hour_format, current_time('timestamp'));
            }
            $messageText = str_replace('[date]', $dateField, $messageText);
            
            $mpdf->WriteHTML( wpautop( $messageText ) );
            $mpdf->Output($createDirectory.'/preview-'.$idForm.'.pdf', 'F');
            //$changeFile = chmod($createDirectory.'/preview-'.$idForm.'.pdf', 0705);
        }
        
            $messagePdf = '
<p>Votre nom : [your-name]</p>

<p>Votre email : [your-email]</p>

<p>Sujet : [your-subject] </p>

<p>Votre message : [your-message]</p>

';
        
        
    ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
        <form method="post" action="" name="valide_settings">
            <input type="hidden" name="action" value="update" />
            <input type="hidden" name="idform" value="<?php echo $idForm; ?>"/>
            <input type="hidden" name="path_uploads" value="<?php echo $createDirectory; ?>" />
            <?php wp_nonce_field('go-sendform', 'security-sendform'); ?>
            <div style="text-align:right;">
                    <p>
                        <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
                    </p>
            </div>

            <!-- Disable GENERATE PDF -->                
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td>
                            <h3 class="hndle"><span class="dashicons dashicons-dashboard"></span> <?php _e('General Settings', 'send-pdf-for-contact-form-7'); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Disable generate PDF?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td><input type="radio" name="wp_cf7pdf_settings[disable-pdf]" value="true" <?php if( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'send-pdf-for-contact-form-7'); ?>&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-pdf]" value="false" <?php if( ( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=="false") or empty($meta_values["disable-pdf"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'send-pdf-for-contact-form-7'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Who send the PDF file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment]">
                                <option value="sender"<?php if( isset($meta_values["send-attachment"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "both") || empty($meta_values["send-attachment"]) ) { echo ' selected'; } ?>><?php _e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr /></td></tr>
                    <tr>
                        <td><?php _e('Disable Insert subscribtion in database?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td><input type= "radio" name="wp_cf7pdf_settings[disable-insert]" value="true" <?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'send-pdf-for-contact-form-7'); ?>&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-insert]" value="false" <?php if( ( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="false") or empty($meta_values["disable-insert"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'send-pdf-for-contact-form-7'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Truncate database?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td><input type="checkbox" name="truncate_table" value="true"></td>
                    </tr>
                    <tr><td colspan="2"><hr /></td></tr>
                    <tr>
                        <td><?php _e('Disable generate CSV file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td><input type= "radio" name="wp_cf7pdf_settings[disable-csv]" value="true" <?php if( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'send-pdf-for-contact-form-7'); ?>&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-csv]" value="false" <?php if( ( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=="false") or empty($meta_values["disable-csv"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'send-pdf-for-contact-form-7'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Who send the CSV file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment2]">
                                <option value="sender"<?php if( isset($meta_values["send-attachment2"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "both") || empty($meta_values["send-attachment2"]) ) { echo ' selected'; } ?>><?php _e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr /></td></tr>
                    <tr>
                        <td>
                            <?php _e('Enter a name for your PDF', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("By default, the file's name will be 'document-pdf'", 'send-pdf-for-contact-form-7'); ?></i>)</p>
                            <br />
                            <?php _e('Fill in the name of your PDF with your form tags', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("Enter your tags separated by commas", 'send-pdf-for-contact-form-7'); ?></i>)</p>
                        </td>
                        <td>
                            <input type= "text" name="wp_cf7pdf_settings[pdf-name]" value="<?php if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) { echo $meta_values["pdf-name"]; } ?>">.pdf<br /><br /><br />
                            <input type="text" size="30" name="wp_cf7pdf_settings[pdf-add-name]" value="<?php if( isset($meta_values["pdf-add-name"]) && !empty($meta_values["pdf-add-name"]) ) { echo $meta_values["pdf-add-name"]; } ?>" />
                        </td>
                        
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Change uploads folder?', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("By default, the uploads folder's name is in /wp-content/uploads/*YEAR*/*MONTH*/", 'send-pdf-for-contact-form-7'); ?></i>)</p>
                        </td>
                        <td>
                            <input type= "checkbox" name="wp_cf7pdf_settings[pdf-uploads]" value="true" <?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]==true ) { echo 'checked'; } ?>>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Delete all files into this uploads folder?', 'send-pdf-for-contact-form-7'); ?><p></p>
                        </td>
                        <td>
                            <input type= "checkbox" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="true">
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr /></td></tr>
                    <tr>
                        <td><?php _e('Other files attachments?', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php _e("Enter one URL file by line", 'send-pdf-for-contact-form-7'); ?></i>)</p><textarea cols="100%" rows="5" name="wp_cf7pdf_settings[pdf-files-attachments]"><?php if( isset($meta_values["pdf-files-attachments"]) ) { echo esc_textarea($meta_values["pdf-files-attachments"]); } ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td><?php _e('Who send the attachments file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment3]">
                                <option value="sender"<?php if( isset($meta_values["send-attachment3"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment3"]) && $meta_values["send-attachment3"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment3"] == "both") || empty($meta_values["send-attachment3"]) ) { echo ' selected'; } ?>><?php _e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr /></td></tr>
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
                                $args = array('name' => 'wp_cf7pdf_settings[page_next]', 'selected' => $idSelectPage, 'show_option_none' => __('Please select a page', 'send-pdf-for-contact-form-7') ); 
                                wp_dropdown_pages($args);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Rediriger sur cette page sans envoyer un e-mail? -->
                            <?php _e('Send email without attachments?', 'send-pdf-for-contact-form-7'); ?><p></p>
                        </td>
                        <td>
                            <input type="radio" name="wp_cf7pdf_settings[disable-attachments]" value="true" <?php if( isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'send-pdf-for-contact-form-7'); ?>&nbsp;&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-attachments]" value="false" <?php if( ( isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=="false") or empty($meta_values["disable-attachments"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose de télécharger le pdf? -->
                            <?php _e('Use a link in the redirect page for download PDF?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php _e('* Requires enable option "insert into a database"', 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td>
                            <input type="radio" name="wp_cf7pdf_settings[download-pdf]" value="true" <?php if( isset($meta_values["download-pdf"]) && $meta_values["download-pdf"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'send-pdf-for-contact-form-7'); ?>&nbsp;&nbsp;<input type="radio" name="wp_cf7pdf_settings[download-pdf]" value="false" <?php if( ( isset($meta_values["download-pdf"]) && $meta_values["download-pdf"]=="false") or empty($meta_values["download-pdf"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Rediriger sur cette page sans envoyer un e-mail? -->
                            <?php _e('Enter the name for the link', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php _e( 'Use this shortcode : [wpcf7pdf_download]', 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td>
                            <input type="text" name="wp_cf7pdf_settings[text-link]" value="<?php if( empty($meta_values["text-link"]) or $meta_values["text-link"]=="" ) { _e('Download your PDF', 'send-pdf-for-contact-form-7'); } else { echo $meta_values["text-link"]; } ?>">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr></td>
                    </tr>
                    <tr>
                        <td>
                            <?php _e('Select a date and time format', 'send-pdf-for-contact-form-7'); ?><br /><p><i><?php _e('By default, the date format is defined in the admin settings', 'send-pdf-for-contact-form-7'); ?> (<a href="https://codex.wordpress.org/Formatting_Date_and_Time" target="_blank"><?php _e('Formatting Date and Time', 'send-pdf-for-contact-form-7'); ?></a>)</i></p>
                        </td>
                        <td>
                            <input id="date_format" size="16" name="wp_cf7pdf_settings[date_format]" value="<?php if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) { echo stripslashes($meta_values['date_format']); } else { echo stripslashes($date_format . ' ' . $hour_format); } ?>" type="text" /> <?php if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) { echo date_i18n($meta_values['date_format']); } else { echo date_i18n($date_format . ' ' . $hour_format); } ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr></td>
                    </tr>
                    <tr>
                        <td>
                            <span class="dashicons dashicons-format-image"></span> <?php _e('Enter a URL or upload an image.', 'send-pdf-for-contact-form-7'); ?></small><br />
                            <input id="upload_image" size="36" name="wp_cf7pdf_settings[image]" value="<?php if( isset($meta_values['image']) ) { echo esc_url($meta_values['image']); } ?>" type="text" /> <a href="#" id="upload_image_button" class="button" OnClick="this.blur();"><span> <?php _e('Select or Upload your picture', 'send-pdf-for-contact-form-7'); ?> </span></a> <br />
                            <div style="margin-top:0.8em;">
                                <select name="wp_cf7pdf_settings[image-alignment]">
                                    <option value="left" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='left') ) { echo 'selected'; } ?>><?php _e('Left', 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="center" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='center') ) { echo 'selected'; } ?>><?php _e('Center', 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="right" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='right') ) { echo 'selected'; } ?>><?php _e('Right', 'send-pdf-for-contact-form-7'); ?></option>
                                </select>
                                <?php _e('Size', 'send-pdf-for-contact-form-7'); ?> <input type="text" size="3" name="wp_cf7pdf_settings[image-width]" value="<?php if( isset($meta_values['image-width']) ) { echo $meta_values['image-width']; } else { echo '150'; } ?>" />&nbsp;X&nbsp;<input type="text" name="wp_cf7pdf_settings[image-height]" size="3" value="<?php if( isset($meta_values['image-height']) ) { echo $meta_values['image-height']; } ?>" />px

                            </div>
                        </td>
                        <td align="center">
                            <?php if( isset($meta_values['image']) ) { echo '<img src="'.esc_url($meta_values['image']).'" height="100">'; } ?><br />
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
                        <td colspan="2"><hr></td>
                    </tr>
                </tbody>                
            </table>
           
            <div class="clear">&nbsp;</div>
            <div id="meta">
                <table class="wp-list-table widefat fixed" cellspacing="0">
                    <tbody id="the-list">
                        <tr>
                            <td>
                                <h3 class="hndle"><span class="dashicons dashicons-media-document"></span> <?php _e('Layout of your PDF', 'send-pdf-for-contact-form-7'); ?></h3>
                            </td>
                        </tr>                
                        <tr>
                            <td width="80%">
                                <legend>
                                    <?php _e('For personalize your PDF you can in the following text field, use these mail-tags:', 'send-pdf-for-contact-form-7'); ?><br /><br />
                                    <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span><br /><i>(<?php _e("[reference] is a simple mail-tag who is used for create unique PDF. It's also recorded in the database. Every PDF is named like this : name-pdf-uniqid() and it's uploaded in the upload folder of WordPress. For example : document-pdf-56BC4A3EF0752.pdf", 'send-pdf-for-contact-form-7'); ?>)</i><br /><br />
                                    <?php 
                                        $contact_form = WPCF7_ContactForm::get_instance($idForm);
                                        foreach ( (array) $contact_form->collect_mail_tags() as $mail_tag ) {
                                            $pattern = sprintf( '/\[(_[a-z]+_)?%s([ \t]+[^]]+)?\]/',
                                                preg_quote( $mail_tag, '/' ) );
                                            echo sprintf(
                                                '<span class="%1$s" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[%2$s]</strong></span>',
                                                'mailtag code used',
                                                esc_html( $mail_tag ) );
                                            echo '<input type="hidden" name="wp_cf7pdf_tags[]" value="['.esc_html( $mail_tag ).']" />';
                                        }
                                    ?>
                                </legend>
                                <br /><br />
                                <textarea name="wp_cf7pdf_settings[generate_pdf]" rows="25" cols="80%"><?php if( empty($meta_values['generate_pdf']) ) { echo $messagePdf; } else { echo esc_textarea($meta_values['generate_pdf']); } ?></textarea>
                            </td>
                            <td align="center" width="20%">
                                <?php if( file_exists($createDirectory.'/preview-'.$idForm.'.pdf') ) { ?>
                                    <iframe src=""></iframe>
                                    <div class="round-button">
                                        <div class="round-button-circle">
                                            <a href="<?php echo str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/preview-'.$idForm.'.pdf?ver='.$_SESSION['pdf_uniqueid']; ?>" class="round-button" target="_blank"><?php _e('Preview your PDF', 'send-pdf-for-contact-form-7'); ?></a>
                                        </div>
                                    </div>
                                <?php } ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
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
        
     </div>
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