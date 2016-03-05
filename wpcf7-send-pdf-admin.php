<?php

/* Update des paramètres */
if( (isset($_POST['action']) && isset($_GET['idform']) && $_POST['action'] == 'update') ) {
    
    update_post_meta( intval($_GET['idform']), '_wp_cf7pdf', $_POST["wp_cf7pdf_settings"] );
    update_post_meta( intval($_GET['idform']), '_wp_cf7pdf_fields', $_POST["wp_cf7pdf_tags"] );
    //update_option('wp_cf7pdf_settings', $_POST["wp_cf7pdf_settings"]);
    $options_saved = true;
    echo '<div id="message" class="updated fade"><p><strong>'.__('Options saved.', 'wp-cf7pdf').'</strong></p></div>';
    
}
if( isset($_GET['idform']) && isset($_GET['truncate']) && intval($_GET['truncate']) == 1 ) {
     
    $DeleteList = cf7_sendpdf::truncate();
    if( $DeleteList == true ) {
        echo '<div id="message" class="updated fade"><p><strong>'.__('All the data has been deleted.', 'wp-cf7pdf').'</strong></p></div>';
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
<style>
.round-button {
	width:25%;
}
.round-button-circle {
	width: 100%;
	height:0;
	padding-bottom: 100%;
    border-radius: 50%;
	border:10px solid #8a0f00;
    overflow:hidden;
    
    background: #cf1c00; 
    box-shadow: 0 0 3px gray;
}
.round-button-circle:hover {
	background:#ffffff;
}
.round-button-circle a:hover { color:#8a0f00; }
.round-button a {
    display:block;
	float:left;
	width:100%;
	padding-top:50%;
    padding-bottom:50%;
	line-height:1em;
	margin-top:-0.5em;
    
	text-align:center;
	color:#ffffff;
    font-family:Verdana;
    font-size:1.2em;
    font-weight:bold;
    text-decoration:none;
}
</style>
<div class="wrap">
    
    <h2 style="font-size: 23px;font-weight: 400;padding: 9px 15px 4px 0px;line-height: 29px;">
        <?php echo __('Send PDF for Contact Form 7 - Settings', 'wp-cf7pdf'); ?>
    </h2>
    <?php if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) { ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
        <table width="100%" cellspacing="20">
            <tr>
                <td align="left" valign="middle">
                    <?php
                        $formsList = cf7_sendpdf::getForms();
                        if (count($formsList) == 0) {
                            echo htmlspecialchars(__('No form submissions in the database', 'contact-form-7-to-database-extension'));
                            return;
                        } else {
                      //print_r($formsList);  
                    ?>
                    <form method="get" action="<?php echo $_SERVER['REQUEST_URI']?>" name="displayform" id="displayform">
                        <input type="hidden" name="page" value="wpcf7-send-pdf"/>
                        <select name="idform" id="idform" method="GET" onchange="this.form.submit();">
                            <option value=""><?php echo htmlspecialchars(__('* Select a form *', 'wp-cf7pdf')); ?></option>
                            <?php 
                                $selected = '';
                                foreach ($formsList as $formName) {
                                    if( isset($_GET['idform']) ) {
                                        $selected = ($formName->ID == $_GET['idform']) ? "selected" : ""; 
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
                    <h3><?php printf( __('Read %s here !', 'wp-cf7pdf'), '<a href="http://www.restezconnectes.fr/tutoriel-wordpress-le-plugin-send-pdf-for-contact-form-7/" target="_blank">'.__('Tutorial', 'wp-cf7pdf').'</a>' ); ?></h3>
                </td>
                <td align="right" width="25%">
                    <!-- FAIRE UN DON SUR PAYPAL -->
                    <div style="border:1px dotted #ccc;text-align:center;"><?php _e('If you want Donate (French Paypal) for my current and future developments:', 'wp-cf7pdf'); ?><br />
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                        <input type="hidden" name="cmd" value="_s-xclick">
                        <input type="hidden" name="hosted_button_id" value="ABGJLUXM5VP58">
                        <input type="image" src="https://www.paypalobjects.com/fr_FR/FR/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - la solution de paiement en ligne la plus simple et la plus sécurisée !">
                        <img alt="" border="0" src="https://www.paypalobjects.com/fr_FR/i/scr/pixel.gif" width="1" height="1">
                        </form>
                    </div>
                    <!-- FIN FAIRE UN DON -->
                </td>
            </tr>
        </table>
    </div>
    
    <?php 
    if( isset($_GET['idform']) ) {

        //name,forename,bithday,sex,phone,adress,cp,city,sport,
        $idForm = intval($_GET['idform']);
        $meta_values = get_post_meta( $idForm, '_wp_cf7pdf', true );
        $meta_form = get_post_meta( $idForm, '_form', true);
        
        /**********************************************/
        /******** ON GENERE UN PDF DE PREVIEW *********/
        /**********************************************/
        // On récupère le dossier upload de WP
        $upload_dir = wp_upload_dir();
        $createDirectory = $upload_dir['basedir'].$upload_dir['subdir'];
        // On efface l'ancien pdf renommé si il y a (on garde l'original)
        if( file_exists($createDirectory.'/preview.pdf') ) {
            unlink($createDirectory.'/preview.pdf');
        }
        if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {
            include('/mpdf/mpdf.php');
            $mpdf=new mPDF('c');
            $mpdf->ignore_invalid_utf8 = true;
            if( isset($meta_values["image"]) && !empty($meta_values["image"]) ) {
                list($width, $height, $type, $attr) = getimagesize($meta_values["image"]);
                $imgAlign = 'left';
                if( isset($meta_values['image-alignment']) ) {
                    $imgAlign = $meta_values['image-alignment'];
                }
                if( empty($meta_values['image-width']) ) { $imgWidth = $width; } else { $imgWidth = $meta_values['image-width'];  }
                if( empty($meta_values['image-height']) ) { $imgHeight = $height; } else { $imgHeight = $meta_values['image-height'];  } 

                $attribut = 'width='.$imgWidth.' height="'.$imgHeight.'"';

                $mpdf->WriteHTML('<div style="text-align:'.$imgAlign.'"><img src="'.esc_url($meta_values["image"]).'" '.$attribut.' /></div>');
            }
            $mpdf->WriteHTML( wpautop( $meta_values['generate_pdf']) );
            $mpdf->Output($createDirectory.'/preview.pdf', 'F');
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
                <div style="text-align:right;">
                        <p>
                            <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', 'wp-cf7pdf'); ?>"/>
                        </p>
                </div>
                <!-- Disable GENERATE PDF -->                
                <table class="wp-list-table widefat fixed" cellspacing="0">
                    <tbody id="the-list">
                        <tr>
                            <td>
                                <h3 class="hndle"><span class="dashicons dashicons-dashboard"></span> <?php _e('General Settings', 'wp-cf7pdf'); ?></h3>
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e('Disable generate PDF?', 'wp-cf7pdf'); ?></td>
                            <td><input type="radio" name="wp_cf7pdf_settings[disable-pdf]" value="true" <?php if( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'wp-cf7pdf'); ?>&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-pdf]" value="false" <?php if( ( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=="false") or empty($meta_values["disable-pdf"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'wp-cf7pdf'); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Who send the PDF file?', 'wp-cf7pdf'); ?></td>
                            <td>
                                <select name="wp_cf7pdf_settings[send-attachment]">
                                    <option value="sender"<?php if( isset($meta_values["send-attachment"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'wp-cf7pdf'); ?></option>
                                    <option value="recipient"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'wp-cf7pdf'); ?></option>
                                    <option value="both"<?php if( (isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "both") || empty($meta_values["send-attachment"]) ) { echo ' selected'; } ?>><?php _e('Both', 'wp-cf7pdf'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr><td colspan="2"><hr /></td></tr>
                        <tr>
                            <td><?php _e('Disable Insert subscribtion in database?', 'wp-cf7pdf'); ?></td>
                            <td><input type= "radio" name="wp_cf7pdf_settings[disable-insert]" value="true" <?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'wp-cf7pdf'); ?>&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-insert]" value="false" <?php if( ( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="false") or empty($meta_values["disable-insert"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'wp-cf7pdf'); ?></td>
                        </tr>
                        <tr><td colspan="2"><hr /></td></tr>
                        <tr>
                            <td><?php _e('Disable generate CSV file?', 'wp-cf7pdf'); ?></td>
                            <td><input type= "radio" name="wp_cf7pdf_settings[disable-csv]" value="true" <?php if( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=="true" ) { echo ' checked'; } ?>>&nbsp;<?php _e('Yes', 'wp-cf7pdf'); ?>&nbsp;<input type="radio" name="wp_cf7pdf_settings[disable-csv]" value="false" <?php if( ( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=="false") or empty($meta_values["disable-csv"]) ) { echo ' checked'; } ?> />&nbsp;<?php _e('No', 'wp-cf7pdf'); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Who send the CSV file?', 'wp-cf7pdf'); ?></td>
                            <td>
                                <select name="wp_cf7pdf_settings[send-attachment2]">
                                    <option value="sender"<?php if( isset($meta_values["send-attachment2"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'wp-cf7pdf'); ?></option>
                                    <option value="recipient"<?php if( isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'wp-cf7pdf'); ?></option>
                                    <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "both") || empty($meta_values["send-attachment2"]) ) { echo ' selected'; } ?>><?php _e('Both', 'wp-cf7pdf'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr><td colspan="2"><hr /></td></tr>
                        <tr>
                            <td><?php _e('Enter a name for your PDF', 'wp-cf7pdf'); ?><p>(<i><?php _e("By default, the file's name will be 'document-pdf'", 'wp-cf7pdf'); ?></i>)</p></td>
                            <td><input type= "text" name="wp_cf7pdf_settings[pdf-name]" value="<?php if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) { echo $meta_values["pdf-name"]; } ?>">.pdf</td>
                        </tr>
                        <tr><td colspan="2"><hr /></td></tr>
                        <tr>
                            <td><?php _e('Other files attachments?', 'wp-cf7pdf'); ?><p>(<i><?php _e("Enter one URL file by line", 'wp-cf7pdf'); ?></i>)</p><textarea cols="100%" rows="5" name="wp_cf7pdf_settings[pdf-files-attachments]"><?php if( isset($meta_values["pdf-files-attachments"]) ) { echo esc_textarea($meta_values["pdf-files-attachments"]); } ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <td><?php _e('Who send the attachments file?', 'wp-cf7pdf'); ?></td>
                            <td>
                                <select name="wp_cf7pdf_settings[send-attachment3]">
                                    <option value="sender"<?php if( isset($meta_values["send-attachment3"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php _e('Sender', 'wp-cf7pdf'); ?></option>
                                    <option value="recipient"<?php if( isset($meta_values["send-attachment3"]) && $meta_values["send-attachment3"] == "recipient" ) { echo ' selected'; } ?>><?php _e('Recipient', 'wp-cf7pdf'); ?></option>
                                    <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment3"] == "both") || empty($meta_values["send-attachment3"]) ) { echo ' selected'; } ?>><?php _e('Both', 'wp-cf7pdf'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr><td colspan="2"><hr /></td></tr>
                        <tr>
                            <td>
                                <?php _e('Select a page to display after sending the form (optional)', 'wp-cf7pdf'); ?>
                            </td>
                            <td>
                                <?php 
                                    if( isset($meta_values['page_next']) ) { 
                                        $idSelectPage = $meta_values['page_next'];
                                    } else {
                                        $idSelectPage = 0;
                                    }
                                    $args = array('name' => 'wp_cf7pdf_settings[page_next]', 'selected' => $idSelectPage, 'show_option_none' => __('Please select a page', 'wp-cf7pdf') ); 
                                    wp_dropdown_pages($args);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"><hr></td>
                        </tr>
                        <tr>
                            <td>
                                <span class="dashicons dashicons-format-image"></span> <?php _e('Enter a URL or upload an image.', 'wp-cf7pdf'); ?></small><br />
                                <input id="upload_image" size="36" name="wp_cf7pdf_settings[image]" value="<?php if( isset($meta_values['image']) ) { echo esc_url($meta_values['image']); } ?>" type="text" /> <a href="#" id="upload_image_button" class="button" OnClick="this.blur();"><span> <?php _e('Select or Upload your picture', 'wp-cf7pdf'); ?> </span></a> <br />
                                <div style="margin-top:0.8em;">
                                    <select name="wp_cf7pdf_settings[image-alignment]">
                                        <option value="left" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='left') ) { echo 'selected'; } ?>><?php _e('Left', 'wp-cf7pdf'); ?></option>
                                        <option value="center" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='center') ) { echo 'selected'; } ?>><?php _e('Center', 'wp-cf7pdf'); ?></option>
                                        <option value="right" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='right') ) { echo 'selected'; } ?>><?php _e('Right', 'wp-cf7pdf'); ?></option>
                                    </select>
                                    <?php _e('Size', 'wp-cf7pdf'); ?> <input type="text" size="3" name="wp_cf7pdf_settings[image-width]" value="<?php if( isset($meta_values['image-width']) ) { echo $meta_values['image-width']; } else { echo '150'; } ?>" />&nbsp;X&nbsp;<input type="text" name="wp_cf7pdf_settings[image-height]" size="3" value="<?php if( isset($meta_values['image-height']) ) { echo $meta_values['image-height']; } ?>" />px

                                </div>
                            </td>
                            <td align="center">
                                <?php if( isset($meta_values['image']) ) { echo '<img src="'.esc_url($meta_values['image']).'" height="100">'; } ?><br />
                                <?php 
                                    if( !empty($meta_values["image"]) ) {
                                        list($width, $height, $type, $attr) = getimagesize($meta_values["image"]);
                                        echo '<i>('.__('Original size is', 'wp-cf7pdf').' '.$width.'px X '.$height.'px)</i>';
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
                                <h3 class="hndle"><span class="dashicons dashicons-media-document"></span> <?php _e('Layout of your PDF', 'wp-cf7pdf'); ?></h3>
                            </td>
                        </tr>                
                        <tr>
                            <td width="80%">
                                <legend><?php _e('For personalize your PDF you can in the following text field, use these mail-tags:', 'wp-cf7pdf'); ?><br /><br />
                                    <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span><br /><i>(<?php _e("[reference] is a simple mail-tag who is used for create unique PDF. It's also recorded in the database. Every PDF is named like this : name-pdf-uniqid() and it's uploaded in the upload folder of WordPress. For example : document-pdf-56BC4A3EF0752.pdf", 'wp-cf7pdf'); ?>)</i><br /><br />
                                    <?php
                                    //preg_match_all( '#(\[[^\]]*\])#', $meta_form, $matches );
                                    preg_match_all( '#\[(.*?)\]#', $meta_form, $matches );
                                    $tagsCf7 = array('response', 'text', 'text*', 'email', 'email*', 'tel', 'tel*', 'url', 'url*', 'textarea', 'textarea*', 'number', 'number*', 'range', 'range*', 'date', 'date*', 'checkbox', 'checkbox*', 'radio', 'select', 'select*', 'file', 'file*', 'acceptance', 'placeholder', 'submit');

                                    $nb=count($matches[0]); 
                                    $wp_cf7pdf_tags = '';
                                    if($nb>0) { 
                                        $nb = 0;
                                        foreach($matches[0] as $complet) {
                                            
                                            foreach($tagsCf7 as $str) {                                                
                                                $complet = str_replace($str.' ', '', $complet);
                                                $complet = str_replace('response', '', $complet);                        
                                            }
                                            $pices = explode(' ', $complet);
                                            //echo 'ici ->'.$pices[0].']<br /><br />';
                                            $complet = str_replace(' ', '', $pices[0]);
                                            $complet = preg_replace('/\"[^)]*\"/', '', $complet);
                                            $patterns = '/placeholder/';
                                            if( $complet != '[]' ) {
                                                $pos = strpos($complet, ']');
                                                if ($pos === false) {
                                                    $complet = $complet.']';
                                                }
                                                echo '<span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>'.preg_replace($patterns, '', $complet).'</strong></span>&nbsp;&nbsp;';
                                                echo '<input type="hidden" name="wp_cf7pdf_tags[]" value="'.preg_replace($patterns, '', $complet).'" />';
                                            }
                                            $nb++;
                                        }
                                    }
                                    ?>
                                </legend>
                                <br /><br />
                                <textarea name="wp_cf7pdf_settings[generate_pdf]" rows="25" cols="80%"><?php if( empty($meta_values['generate_pdf']) ) { echo $messagePdf; } else { echo esc_textarea($meta_values['generate_pdf']); } ?></textarea>
                            </td>
                            <td align="center" width="20%">
                                <?php if( file_exists($createDirectory.'/preview.pdf') ) { ?>
                                    <iframe src=""></iframe>
                                    <div class="round-button">
                                        <div class="round-button-circle">
                                            <a href="<?php echo $upload_dir['url'].'/preview.pdf'; ?>" class="round-button" target="_blank"><?php _e('Preview your PDF', 'wp-cf7pdf'); ?></a>
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
                                <span class="dashicons dashicons-download"></span> <a href="admin.php?page=wpcf7-send-pdf&amp;idform=<?php echo intval($_GET['idform']); ?>&amp;csv=1" alt="<?php _e('Export list of participants', 'sponsorpress'); ?>" title="<?php _e('Export list', 'wp-cf7pdf'); ?>"><?php _e('Export list in CSV file', 'wp-cf7pdf'); ?></a>
                            </div>
                            </td>
                        <td width="50%" align="right">
                            <span class="dashicons dashicons-dismiss"></span> <a href="?page=wpcf7-send-pdf&idform=17&truncate=1"  onClick="if(!confirm('<?php _e('Are you sure to delete all data? ', 'wp-cf7pdf'); ?>')) return false;" alt="<?php _e('Delete all data?', 'wp-cf7pdf'); ?>" title="<?php _e('Delete all data?', 'wp-cf7pdf'); ?>"><?php _e('Delete all data?', 'wp-cf7pdf'); ?></a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php } ?>
            
            <ul>
                <li>
                    <p>
                        <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php _e('Save settings', 'wp-cf7pdf'); ?>"/>
                    </p>
                </li>
            </ul>
        </form>
        
     </div>
<?php } ?>
<?php } else { ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
        <?php printf( __('To work I need %s plugin, but it is apparently not installed or not enabled!', 'wp-cf7pdf'), '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a>' ); ?>
    </div>
<?php } ?>
    <div style="margin-top:40px;">
        <?php _e('WP Contact Form 7 Send PDF is brought to you by', 'wp-cf7pdf'); ?> <a href="http://www.restezconnectes.fr/" target="_blank">Restez Connectés</a> - <?php _e('If you found this plugin useful', 'wp-cf7pdf'); ?> <a href="https://wordpress.org/support/view/plugin-reviews/wp-maintenance" target="_blank"><?php _e('give it 5 &#9733; on WordPress.org', 'wp-cf7pdf'); ?></a>
    </div>
</div>