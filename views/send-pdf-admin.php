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
        delete_post_meta( $_POST['idform'], '_wp_cf7pdf_customtagsname' );
        $_POST['idform'] = '';
        
        wp_redirect( 'admin.php?page=wpcf7-send-pdf&deleted=1' );

    } else {

        if( isset($_POST['wp_cf7pdf_settings']['pdf-uploads-delete']) && $_POST['wp_cf7pdf_settings']['pdf-uploads-delete']=="true" ) {
            
            if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true') {
                echo '<div id="message" class="notice notice-error"><p><strong>'.esc_html__("I can't deleted all files in this folder. Only in my folder. Thanks to check 'Change uploads folder?' option for this.", 'send-pdf-for-contact-form-7').'</strong></p></div>';
            } else {
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
                    
                    echo '<div id="message" class="updated fade"><p><strong>'.esc_html__('The upload folder has been deleted.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
                }
            }

        }

        $updateSetting = WPCF7PDF_settings::update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_settings"], '_wp_cf7pdf');

        if ( isset($_POST["wp_cf7pdf_tags"]) ) {
            $updateSettingTags = WPCF7PDF_settings::update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_tags"], '_wp_cf7pdf_fields');
        }
        if ( isset($_POST["wp_cf7pdf_tags_scan"]) ) {
            $updateSettingTagsScan = WPCF7PDF_settings::update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_tags_scan"], '_wp_cf7pdf_fields_scan');
        }
        if ( isset($_POST['wp_cf7pdf_custom_tags_name']) ) {
            $updateSettingTagsName = WPCF7PDF_settings::update_settings(esc_html($_POST['idform']), $_POST["wp_cf7pdf_custom_tags_name"], '_wp_cf7pdf_customtagsname');
        }
        if( isset($updateSetting) && $updateSetting == true) {
            $options_saved = true;
            echo '<div id="message" class="updated fade"><p><strong>'.esc_html__('Options saved.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
        }
    }

}

if( isset($_POST['idform']) && isset($_POST['truncate_table']) && $_POST['truncate_table'] == 'true' && wp_verify_nonce($_POST['security-sendform'], 'go-sendform') ) {

    $DeleteList = WPCF7PDF_settings::truncate();
    if( $DeleteList == true ) {
        echo '<div id="message" class="updated fade"><p><strong>'.esc_html__('All the data has been deleted.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
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

    echo '<div id="message" class="updated fade"><p><strong>' . esc_html__('Limit updating successfully!', 'send-pdf-for-contact-form-7') . '</strong></p></div>';
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
    echo '<div id="message" class="updated fade"><p><strong>'.esc_html__('All settings hare been deleted.', 'send-pdf-for-contact-form-7').'</strong></p></div>';
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
        <?php esc_html_e('Send PDF for Contact Form 7 - Settings', 'send-pdf-for-contact-form-7'); ?><sup>V.<?php echo esc_html(WPCF7PDF_VERSION); ?></sup>
    </h2>

    <?php if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) { ?>
    <div id="wpcf7-bandeau">
        <table width="100%" cellspacing="20">
            <tr>
                <td style="text-align:center;" valign="middle" width="33%">
                    <?php
                        $forms = WPCF7_ContactForm::find();
                        if ( count($forms) == 0 ) {
                            /* translators: %s: lien pour créer un formulaire */
                            printf( esc_html__('No forms have not been found. %s', 'send-pdf-for-contact-form-7'), '<a href="'.esc_url(admin_url('admin.php?page=wpcf7')).'">'.esc_html__('Create your first form here.', 'send-pdf-for-contact-form-7').'</a>');
                        } else {
                    ?>
                    <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" name="displayform" id="displayform">
                        <input type="hidden" name="page" value="wpcf7-send-pdf"/>
                        <?php wp_nonce_field('go-sendform', 'security-sendform'); ?>
                        <select name="idform" id="idform" class="wpcf7-form-field" onchange="this.form.submit();">
                            <option value=""><?php esc_html_e('... Select a form ...', 'send-pdf-for-contact-form-7'); ?></option>
                            <?php
                                $selected = '';
                               
                                foreach($forms as $form) {
                                    if(isset($_POST['idform']) ) {
                                        $selected = ($form->id() == sanitize_text_field($_POST['idform'])) ? "selected" : "";
                                    }
                                    $formPriority = '';
                                    $formNameEscaped = htmlentities($form->title(), ENT_QUOTES | ENT_IGNORE, 'UTF-8');
                                    echo '<option value="'.esc_html($form->id()).'" '.esc_html($selected).'>'.esc_html($formNameEscaped).'</option>';
                                }
                            ?>
                        </select>
                    </form>
                    <?php 
                        if( $tmpDirectory != get_option('wpcf7pdf_path_temp') ) {
                            esc_html_e('Your TMP folder is bad.', 'send-pdf-for-contact-form-7');
                            ?>
                            <form method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>" name="resettmp" id="resettmp">
                                <?php wp_nonce_field('go-resettmp', 'security-resettmp'); ?>
                                <input type="hidden" name="action" value="reset"/>
                                <input type="submit" value="<?php esc_html_e('Fix it!', 'send-pdf-for-contact-form-7'); ?>" style="background-color:#656830;color:#fff;border:1px solid #656830;" />
                            </form>
                            <?php
                        }
                    ?>
                    <?php } ?>
                </td>
                <td style="text-align:center;" width="33%">
                        <div id="wpmimgcreated">
                            <a href="https://MadeBy.RestezConnectes.fr" title="Created by MadeByRestezConnectes.fr" class="wpcf7-link" alt="Created by MadeByRestezConnectes.fr" target="_blank" onfocus="this.blur();"><img class="wpmresponsive" src="<?php echo esc_url(plugins_url('send-pdf-for-contact-form-7/images/logo-madeby-restezconnectes.png')); ?>" width="250" valign="bottom"  /></a>
                        </div><p><?php /* translators: %s: lien pour lire le tutoriel */ printf( esc_html__('Read %s here !', 'send-pdf-for-contact-form-7'), '<a href="https://restezconnectes.fr/tutoriel-wordpress-lextension-send-pdf-for-contact-form-7/" class="wpcf7-link" target="_blank" onfocus="this.blur();">'.esc_html__('Tutorial', 'send-pdf-for-contact-form-7').'</a>' ); ?> -  <a href="https://github.com/Florent73/send-pdf-for-contact-form-7/" class="wpcf7-link" target="_blank" onfocus="this.blur();"><?php esc_html_e('GitHub version', 'send-pdf-for-contact-form-7'); ?></a></p>
                </td>
                <td style="text-align:right;" width="33%">
                    <!-- FAIRE UN DON SUR PAYPAL -->
                    <div style="font-size:0.8em;">
                        <div style="width:350px;margin-left:auto;margin-right:auto;padding:5px;">
                            <a href="https://paypal.me/RestezConnectes/10" onfocus="this.blur();" target="_blank" class="wpcf7pdfclassname">
                                <img src="<?php echo esc_url(plugins_url('send-pdf-for-contact-form-7/images/donate.png')); ?>" valign="bottom" width="64" /> <?php esc_html_e('Donate now!', 'send-pdf-for-contact-form-7'); ?>
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

        // On va chercher l'instance du form
        $contact_form = WPCF7_ContactForm::get_instance(esc_html($idForm));
        $contact_tag = $contact_form->scan_form_tags();

        // Genere le nom du PDF
        $nameOfPdf = cf7_sendpdf::wpcf7pdf_name_pdf($idForm);
        
        /**********************************************/
        /******** ON GENERE UN PDF DE PREVIEW *********/
        /**********************************************/
        // On récupère le dossier upload de WP
        $upload_dir = wp_upload_dir();
        $createDirectory = cf7_sendpdf::wpcf7pdf_folder_uploads($idForm);
        
        // On récupère le format de date dans les paramètres
        $date_format = get_option( 'date_format' );
        $hour_format = get_option( 'time_format' );

        // Definition des marges par defaut
        $marginHeader = 10;
        $marginTop = 40;
        $marginBottomHeader = 10;
        $marginLeft = 15;
        $marginRight = 15;

        // Definition de la taille, le format de page et la font par defaut
        $fontsizePdf = 9;
        $fontPdf = 'dejavusanscondensed';
        $formatPdf = 'A4-P';

        // Definition des dates par defaut
        $dateField = WPCF7PDF_prepare::returndate($idForm);
        $timeField = WPCF7PDF_prepare::returntime($idForm);

        // Definition des dimensions du logo par defaut
        $width = 150;
        $height = 80;

        // On efface l'ancien pdf renommé si il y a (on garde l'original)
        if( file_exists($createDirectory.'/preview.pdf') ) {
            wp_delete_file($createDirectory.'/preview.pdf');
        }

        if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

            // définit le contenu du PDf
            $messageText = wp_kses(trim($meta_values['generate_pdf']), WPCF7PDF_prepare::wpcf7pdf_autorizeHtml());       
            
            // Preparation du contenu du PDF
            $messageText = WPCF7PDF_prepare::tags_parser($idForm, $nameOfPdf, '', $messageText, 0, 1);
            
            // Shortcodes?
            if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {
                $messageText = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $messageText);
            }

            // Création du PDF
            $generatePdfFile = WPCF7PDF_generate::wpcf7pdf_create_pdf($idForm, $messageText, $nameOfPdf, '', $createDirectory, 1);

        }

        // Si plusieurs PDF
        if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) {

            for ($ipdf = 2; $ipdf <= $meta_values["number-pdf"]; $ipdf++) {

                if( isset($meta_values['nameaddpdf'.$ipdf]) && $meta_values['nameaddpdf'.$ipdf]!='') {

                    $addNamePdf = sanitize_title($meta_values['nameaddpdf'.$ipdf]);

                    // définit le contenu du PDf
                    $messageAddPdf = wp_kses(trim($meta_values['content_addpdf_'.$ipdf.'']), WPCF7PDF_prepare::wpcf7pdf_autorizeHtml());       
                    
                    // Preparation du contenu du PDF
                    $messageAddPdf = WPCF7PDF_prepare::tags_parser($idForm, $addNamePdf, '', $messageAddPdf, 0, 1);
                    
                    // Shortcodes?
                    if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {
                        $messageAddPdf = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $messageAddPdf);
                    }

                    // Création du PDF
                    $generateAddPdfFile = WPCF7PDF_generate::wpcf7pdf_create_pdf($idForm, $messageAddPdf, $addNamePdf, '', $createDirectory, 2);
                }

            }

        }

        if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {
            $generateCsvFile = WPCF7PDF_generate::wpcf7pdf_create_csv($idForm, $nameOfPdf, '', $createDirectory, 1);
        }
            // Contenu PDF par default
            $messagePdf = '
<p>Votre nom : [your-name]</p>

<p>Votre email : [your-email]</p>

<p>Sujet : [your-subject] </p>

<p>Votre message : [your-message]</p>

';

// Liste Fonts
$listFont = WPCF7PDF_settings::getFontsTab();

// Si custom fonts dans dossiers /pdffonts
$pathFolder = serialize($createDirectory);
if ( is_dir(get_stylesheet_directory()."/pdffonts/") == true ) {
    
    $dossier = new DirectoryIterator(get_stylesheet_directory()."/pdffonts/");

    foreach($dossier as $fichier){
    
        // continue;
        if(preg_match("#\.(ttf)$#i", $fichier)){

            //on fusionne les fonts avec ceux définis dans getFontsTab()
            $addFontData = array(
            ''.substr($fichier->getFilename(), 0, -4).'' => ''.sanitize_title(substr($fichier->getFilename(), 0, -4)).'',
            );
            $listFont = array_merge($listFont, $addFontData);
        }
    }
}
?>

<form method="post" action="" name="valide_settings">

    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="idform" value="<?php echo esc_html($idForm); ?>"/>
    <input type="hidden" name="path_uploads" value="<?php echo esc_url($pathFolder); ?>" />
    <?php wp_nonce_field('go-sendform', 'security-sendform'); ?>

    <div style="text-align:right;">
        <p>
            <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php esc_html_e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
            <?php if( file_exists($createDirectory.'/preview-'.esc_html($idForm).'.pdf') ) { ?>
                <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.esc_html($idForm).'.pdf?ver='.esc_html(wp_rand()); ?>" ><?php esc_html_e('Preview your PDF', 'send-pdf-for-contact-form-7'); ?></a>
            <?php } ?>
        </p>
    </div>

    <!-- PARAMETRES GENERAUX -->
    <div class="postbox">
        <div class="handlediv" style="height:1px!important;" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
        <span class="dashicons customDashicons dashicons-admin-settings"></span> <h3 class="hndle" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><?php esc_html_e('General Settings', 'send-pdf-for-contact-form-7'); ?></h3>
        <div class="inside">

            <!-- Disable GENERATE PDF -->
            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td style="vertical-align: middle;margin-top:15px;"><?php esc_html_e('Disable generate PDF?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td style="text-align:left;">
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_left" name="wp_cf7pdf_settings[disable-pdf]" value="true" <?php if( isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_left"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_right" name="wp_cf7pdf_settings[disable-pdf]" value="false" <?php if( empty($meta_values["disable-pdf"]) || (isset($meta_values["disable-pdf"]) && $meta_values["disable-pdf"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_right"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Who send the PDF file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment]" class="wpcf7-form-field">
                                <option value="sender"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "sender" ) { echo ' selected'; } ?>><?php esc_html_e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "recipient" ) { echo ' selected'; } ?>><?php esc_html_e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment"]) && $meta_values["send-attachment"] == "both") || empty($meta_values["send-attachment"]) ) { echo ' selected'; } ?>><?php esc_html_e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Number of PDF file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td><input type="number" id="quantity" name="wp_cf7pdf_settings[number-pdf]" min="1" max="5" class="wpcf7-form-field" value="<?php if( empty($meta_values["number-pdf"]) ) { echo '1'; } elseif( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>=1 ) { echo esc_html($meta_values["number-pdf"]); } ?>">
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Conditional field for sending PDF file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td style="text-align:left;">
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_condition" name="wp_cf7pdf_settings[condition-sending]" value="true" <?php if( isset($meta_values["condition-sending"]) && $meta_values["condition-sending"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_condition"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_condition_no" name="wp_cf7pdf_settings[condition-sending]" value="false" <?php if( empty($meta_values["condition-sending"]) || (isset($meta_values["condition-tag"]) && $meta_values["condition-tag"]=='') || (isset($meta_values["condition-sending"]) && $meta_values["condition-sending"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_condition_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <?php if( isset($meta_values["condition-sending"]) && $meta_values["condition-sending"]=='true') { ?>
                    <tr>
                        <td><?php esc_html_e('Enter your tag for Conditional?', 'send-pdf-for-contact-form-7'); ?><p><i><?php esc_html_e("Conditional tag must be a Checkbox, Radio or Select and must be return TRUE or FALSE.", 'send-pdf-for-contact-form-7'); ?><br />
                        <strong><?php esc_html_e('Example:', 'send-pdf-for-contact-form-7'); ?></strong><br />[checkbox* contidional exclusive use_label_element default:2 "Yes|true" "No|false"]<br />
                    [select send-facture "Yes|true" "No|false"]</i></p></td>
                        <td style="text-align:left;">                            
                            <select name="wp_cf7pdf_settings[condition-tag]" class="wpcf7-form-field">
                                <option value="#">-- <?php esc_html_e('Choose a tag for conditional', 'send-pdf-for-contact-form-7'); ?> --</option>                            
                                <?php      
                                foreach ( (array) $contact_form->collect_mail_tags() as $mail_tag ) {
                                    $found_key = cf7_sendpdf::wpcf7pdf_foundkey($contact_tag, $mail_tag);
                                    $pattern = sprintf( '/\[(_[a-z]+_)?%s([ \t]+[^]]+)?\]/', preg_quote( esc_html($mail_tag), '/' ) );
                                    $baseTypeRaw = $contact_tag[$found_key]['basetype'];
                                    $selectTag = '';
                                    if( isset($baseTypeRaw) && ($baseTypeRaw==='checkbox' || $baseTypeRaw==='radio' || $baseTypeRaw==='select') ) {
                                        if( isset($meta_values["condition-tag"]) && $meta_values["condition-tag"] == esc_html( $mail_tag ) ) { $selectTag = ' selected'; }
                                        echo '<option value="'.esc_html( $mail_tag ).'"'.$selectTag.'>['.esc_html( $mail_tag ).']</option>';
                                    }                                
                                } ?>
                            </select>
                        </td>
                    </tr>
                    <?php } ?>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr style="vertical-align: middle;margin-top:15px;">
                        <td><?php esc_html_e("Disable data submit in a database?", 'send-pdf-for-contact-form-7'); ?></td>
                        <td style="text-align:left;">
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_insert" name="wp_cf7pdf_settings[disable-insert]" value="true" <?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_insert"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_insert_no" name="wp_cf7pdf_settings[disable-insert]" value="false" <?php if( empty($meta_values["disable-insert"]) || (isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_insert_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Truncate database?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_truncate" name="truncate_table" value="true" />
                                <label for="switch_truncate"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_truncate_no" name="truncate_table" value="false" checked />
                                <label for="switch_truncate_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td><?php esc_html_e('Disable generate CSV file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <div>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_csv" name="wp_cf7pdf_settings[disable-csv]" value="true" <?php if( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_csv"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_csv_no" name="wp_cf7pdf_settings[disable-csv]" value="false" <?php if( empty($meta_values["disable-csv"]) || (isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_csv_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                            </div>
                            </div>
                        </td>
                    </tr>
                    <?php if( empty($meta_values["disable-csv"]) || (isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false') ) { ?>
                    <tr>
                        <td><?php esc_html_e('Who send the CSV file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment2]" class="wpcf7-form-field">
                                <option value="sender"<?php if( isset($meta_values["send-attachment2"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php esc_html_e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "recipient" ) { echo ' selected'; } ?>><?php esc_html_e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment2"] == "both") || empty($meta_values["send-attachment2"]) ) { echo ' selected'; } ?>><?php esc_html_e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Change CSV separator', 'send-pdf-for-contact-form-7'); ?><br />
                            <p><i><?php esc_html_e("By defaut it's separated by commas", 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td><input size="3" type= "text" name="wp_cf7pdf_settings[csv-separate]" class="wpcf7-form-field" value="<?php if( isset($meta_values["csv-separate"]) && !empty($meta_values["csv-separate"]) ) { echo esc_html($meta_values["csv-separate"]); } else { echo ','; } ?>" /></td>
                    </tr>

                    <?php } ?>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Enter a name for your PDF', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php esc_html_e("By default, the file's name will be 'document-pdf'", 'send-pdf-for-contact-form-7'); ?></i>)</p>
                            <br />
                            <p><?php esc_html_e("You can use this tags (separated by commas):", 'send-pdf-for-contact-form-7'); ?></p>
                            <p>
                            <span class="dashicons dashicons-arrow-right"></span> <?php /* translators: %s: tag [reference] */ echo sprintf( esc_html__('Use %s in the name of your PDF', 'send-pdf-for-contact-form-7'), ' <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span>' ); ?><br />
                            <span class="dashicons dashicons-arrow-right"></span> <?php /* translators: %s: tag [date] */ echo sprintf( esc_html__('Use %s in the name of your PDF', 'send-pdf-for-contact-form-7'), ' <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[date]</strong></span>' ); ?><br />
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
                                &nbsp;&nbsp;<small><?php esc_html_e('Enter date format without space, -, /, _, etc..', 'send-pdf-for-contact-form-7'); ?></small><input size="5" type= "text" name="wp_cf7pdf_settings[date-for-name]" value="<?php echo esc_html($dateForName); ?>" /> <?php echo esc_html(date_i18n($dateForName), current_time('timestamp')); ?>
                            </p>

                        </td>
                        <td>
                            <input type= "text" class="wpcf7-form-field" name="wp_cf7pdf_settings[pdf-name]" value="<?php if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) { echo esc_html(sanitize_title($meta_values["pdf-name"])); } else { echo esc_html('document-pdf'); } ?>">.pdf<br /><br /><br />
                            <input type="text" class="wpcf7-form-field" size="30" name="wp_cf7pdf_settings[pdf-add-name]" value="<?php if( isset($meta_values["pdf-add-name"]) && !empty($meta_values["pdf-add-name"]) ) { echo esc_html($meta_values["pdf-add-name"]); } ?>" />
                        </td>

                    </tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Change uploads folder?', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true') { esc_html_e("Great ! Now the upload folder's path is /wp-content/uploads/sendpdfcf7_uploads/*ID_FORM*/", 'send-pdf-for-contact-form-7'); } else { esc_html_e("By default, the upload folder's path is /wp-content/uploads/*YEAR*/*MONTH*/", 'send-pdf-for-contact-form-7'); } ?></i>)</p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_uploads" name="wp_cf7pdf_settings[pdf-uploads]" value="true" <?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_uploads"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_uploads_no" name="wp_cf7pdf_settings[pdf-uploads]" value="false" <?php if( empty($meta_values["pdf-uploads"]) || (isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_uploads_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Delete all files into this uploads folder?', 'send-pdf-for-contact-form-7'); ?><?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='false') { ?><p><i style="color:#CC0000;">I can't deleted all files in this folder. Only in my folder. Thanks to check 'Change uploads folder?' option for this.</i></p><?php } ?>
                        </td>
                        <td><?php if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true') { ?>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_delete" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="true" />
                                <label for="switch_delete"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_delete_no" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="false" checked />
                                <label for="switch_delete_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                            <?php } else { ?>
                                <img src="<?php echo esc_url(WPCF7PDF_URL.'images/btn_off.png'); ?>" /><br />                                
                                <input type="hidden" name="wp_cf7pdf_settings[pdf-uploads-delete]" value="false" />
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Delete each PDF file after send the email?', 'send-pdf-for-contact-form-7'); ?><?php if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='true') { ?><p><i style="color:#CC0000;">I can't deleted each PDF file because the 'Redirects directly to the PDF after sending the form?' option is activated.</i></p><?php } ?>
                        </td>
                        <td>
                            <?php if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='false') { ?>
                            <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_filedelete" name="wp_cf7pdf_settings[pdf-file-delete]" value="true" <?php if( isset($meta_values["pdf-uploads-delete"]) && $meta_values["pdf-file-delete"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_filedelete"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_filedelete_no" name="wp_cf7pdf_settings[pdf-file-delete]" value="false" <?php if( (empty($meta_values["pdf-file-delete"]) || (isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=='false')) || isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='true' ) { echo ' checked'; } ?> />
                                <label for="switch_filedelete_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                            </div>
                            </div>
                            <?php } else { ?>
                                <img src="<?php echo esc_url(WPCF7PDF_URL.'images/btn_off.png'); ?>" /><br />                                
                                <input type="hidden" name="wp_cf7pdf_settings[pdf-file-delete]" value="false" />
                            <?php } ?>
                            <div>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td><?php esc_html_e('Other files attachments?', 'send-pdf-for-contact-form-7'); ?><p>(<i><?php esc_html_e("Enter one URL file by line", 'send-pdf-for-contact-form-7'); ?></i>)</p><textarea class="wpcf7-form-field" cols="100%" rows="5" name="wp_cf7pdf_settings[pdf-files-attachments]"><?php if( isset($meta_values["pdf-files-attachments"]) ) { echo esc_textarea($meta_values["pdf-files-attachments"]); } ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Who send the attachments file?', 'send-pdf-for-contact-form-7'); ?></td>
                        <td>
                            <select name="wp_cf7pdf_settings[send-attachment3]" class="wpcf7-form-field" >
                                <option value="sender"<?php if( isset($meta_values["send-attachment3"]) && isset($meta_values["send-pdf"]) && $meta_values["send-pdf"] == "sender" ) { echo ' selected'; } ?>><?php esc_html_e('Sender', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="recipient"<?php if( isset($meta_values["send-attachment3"]) && $meta_values["send-attachment3"] == "recipient" ) { echo ' selected'; } ?>><?php esc_html_e('Recipient', 'send-pdf-for-contact-form-7'); ?></option>
                                <option value="both"<?php if( (isset($meta_values["send-attachment2"]) && $meta_values["send-attachment3"] == "both") || empty($meta_values["send-attachment3"]) ) { echo ' selected'; } ?>><?php esc_html_e('Both', 'send-pdf-for-contact-form-7'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Select a page to display after sending the form (optional)', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                        <td><?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='false') { ?>
                            <?php
                                if( isset($meta_values['page_next']) ) {
                                    $idSelectPage = $meta_values['page_next'];
                                } else {
                                    $idSelectPage = 0;
                                }
                                wp_dropdown_pages( array( 
                                    'name' => 'wp_cf7pdf_settings[page_next]', 
                                    'class' => 'wpcf7-form-field',
                                    'show_option_none' => esc_html(__('Please select a page', 'send-pdf-for-contact-form-7')), 
                                    'option_none_value' => '0', 
                                    'selected' => esc_html($idSelectPage),
                                    ));
                            ?>
                             <?php } else { ?>
                                <img src="<?php echo esc_url(WPCF7PDF_URL.'images/select_off.png'); ?>" /><br />                                
                                <input type="hidden" name="wp_cf7pdf_settings[page_next]" value="0" />
                            <?php } ?>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Rediriger sur cette page sans envoyer un e-mail? -->
                            <?php esc_html_e('Send email without attachments?', 'send-pdf-for-contact-form-7'); ?><p></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_attachments" name="wp_cf7pdf_settings[disable-attachments]" value="true" <?php if( isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_attachments"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_attachments_no" name="wp_cf7pdf_settings[disable-attachments]" value="false" <?php if( empty($meta_values["disable-attachments"]) || (isset($meta_values["disable-attachments"]) && $meta_values["disable-attachments"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_attachments_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose la redirection vers le pdf direct -->
                            <?php esc_html_e('Redirects directly to the PDF after sending the form?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php esc_html_e( 'This option disable the Page Redirection selected', 'send-pdf-for-contact-form-7'); ?> (<?php esc_html_e( 'Except the popup window option', 'send-pdf-for-contact-form-7'); ?>)</i></p><?php if( (isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='true') || (isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=='true') ) { ?><p><i style="color:#CC0000;">I can't redirect PDF file because the 'Disable data submit in a database?' option is activated.</i></p><?php } ?>
                            <?php if( isset($idSelectPage) && $idSelectPage > 0) { ?><p><i style="color:#CC0000;">I can't redirect PDF file because you have choose a redirection page.</i></p><?php $meta_values["redirect-to-pdf"]='false'; ?><input type="hidden"  name="wp_cf7pdf_settings[redirect-to-pdf]" value="false" /><?php } ?>
                        </td>
                        <td>
                            <?php if( (isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='false') || (isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=='false') ) { ?>
                                <div>
                                    <div class="switch-field">
                                    <input class="switch_left" type="radio" id="switch_redirect" name="wp_cf7pdf_settings[redirect-to-pdf]" value="true" <?php if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='true') { echo ' checked'; } ?>/>
                                    <label for="switch_redirect"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                    <input class="switch_right" type="radio" id="switch_redirect_no" name="wp_cf7pdf_settings[redirect-to-pdf]" value="false" <?php if( empty($meta_values["redirect-to-pdf"]) || (isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=='false') ) { echo ' checked'; } ?> />
                                    <label for="switch_redirect_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                    </div>
                                </div><br />
                                <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="on" <?php if( (isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='on') or empty($meta_values["redirect-window"]) ) { echo ' checked'; } ?>  /><?php esc_html_e('Same Window', 'send-pdf-for-contact-form-7'); ?> <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="off" <?php if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='off') { echo 'checked'; } ?> /><?php esc_html_e('New Window', 'send-pdf-for-contact-form-7'); ?> <input type="radio" class="wpcf7-form-field" name="wp_cf7pdf_settings[redirect-window]" value="popup" <?php if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"]=='popup') { echo 'checked'; } ?> /><?php esc_html_e('Popup Window', 'send-pdf-for-contact-form-7'); ?>
                            <?php } else { ?>
                                <img src="<?php echo esc_url(WPCF7PDF_URL.'images/btn_off.png'); ?>" /><br />                                
                                <input type="hidden" name="wp_cf7pdf_settings[redirect-to-pdf]" value="false" />
                            <?php } ?>
                            <div>
                            
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Propose l'envoi d'un zip dans l'email plutôt que le PDF -->
                            <?php esc_html_e('Send a ZIP instead PDF?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php esc_html_e( 'This option send all your files in a ZIP', 'send-pdf-for-contact-form-7'); ?></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_pdf_zip" name="wp_cf7pdf_settings[pdf-to-zip]" value="true" <?php if( isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_pdf_zip"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_pdf_zip_no" name="wp_cf7pdf_settings[pdf-to-zip]" value="false" <?php if( empty($meta_values["pdf-to-zip"]) || (isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_pdf_zip_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Select a date and time format', 'send-pdf-for-contact-form-7'); ?><br /><p><i><?php esc_html_e('By default, the date format is defined in the admin settings', 'send-pdf-for-contact-form-7'); ?> (<a href="https://codex.wordpress.org/Formatting_Date_and_Time" target="_blank"><?php esc_html_e('Formatting Date and Time', 'send-pdf-for-contact-form-7'); ?></a>)</i></p>
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
                            <input id="date_format" class="wpcf7-form-field" size="16" name="wp_cf7pdf_settings[date_format]" value="<?php echo esc_html($formatDate); ?>" type="text" /> <?php esc_html_e('Date:', 'send-pdf-for-contact-form-7'); ?> <?php echo esc_html(date_i18n($formatDate)); ?><br />
                            <input id="time_format" size="16" class="wpcf7-form-field" name="wp_cf7pdf_settings[time_format]" value="<?php echo esc_html($formatTime); ?>" type="text" /> <?php esc_html_e('Time:', 'send-pdf-for-contact-form-7'); ?> <?php echo esc_html(date_i18n($formatTime)); ?>
                        </td>
                    </tr>
                    <!-- ENTETE PDF -->
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Input encoding', 'send-pdf-for-contact-form-7'); ?><br /><p><i><?php esc_html_e('mPDF accepts UTF-8 encoded text by default for all functions', 'send-pdf-for-contact-form-7'); ?></i></p>
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
                            <?php esc_html_e('PDF directionality', 'send-pdf-for-contact-form-7'); ?><br /><p><i><?php esc_html_e('Defines the directionality of the document', 'send-pdf-for-contact-form-7'); ?></i></p>
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
                        <td>
                            <?php esc_html_e('Desactivate line break auto for PDF content?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php esc_html_e('This disables automatic line break replacement (\n and \r) in PDF content', 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_linebreak" name="wp_cf7pdf_settings[linebreak]" value="true" <?php if( isset($meta_values["linebreak"]) && $meta_values["linebreak"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_linebreak"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_linebreak_no" name="wp_cf7pdf_settings[linebreak]" value="false" <?php if( empty($meta_values["linebreak"]) || (isset($meta_values["linebreak"]) && $meta_values["linebreak"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_linebreak_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <?php esc_html_e('Desactivate line break auto for MAIL?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php esc_html_e('This disables automatic line break replacement (\n and \r) in mail content', 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_disable-html" name="wp_cf7pdf_settings[disable-html]" value="true" <?php if( isset($meta_values["disable-html"]) && $meta_values["disable-html"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_disable-html"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_disable-html_no" name="wp_cf7pdf_settings[disable-html]" value="false" <?php if( empty($meta_values["disable-html"]) || (isset($meta_values["disable-html"]) && $meta_values["disable-html"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_disable-html_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose de désactiver le remplissage auto du formulaire -->
                            <?php esc_html_e('Desactivate autocomplete form?', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_autocomplete" name="wp_cf7pdf_settings[disabled-autocomplete-form]" value="true" <?php if( isset($meta_values["disabled-autocomplete-form"]) && $meta_values["disabled-autocomplete-form"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_autocomplete"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_autocomplete_no" name="wp_cf7pdf_settings[disabled-autocomplete-form]" value="false" <?php if( empty($meta_values["disabled-autocomplete-form"]) || (isset($meta_values["disabled-autocomplete-form"]) && $meta_values["disabled-autocomplete-form"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_autocomplete_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    <tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Propose de mettre la cases réelles des Checkbox et Radio -->
                            <?php esc_html_e('Enable display data in the checkbox or radio buttons of your PDF file?', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_data_input" name="wp_cf7pdf_settings[data_input]" value="true" <?php if( isset($meta_values["data_input"]) && $meta_values["data_input"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_data_input"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_data_input_no" name="wp_cf7pdf_settings[data_input]" value="false" <?php if( empty($meta_values["data_input"]) || (isset($meta_values["data_input"]) && $meta_values["data_input"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_data_input_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose de mettre la cases réelles des Textarea -->
                            <?php esc_html_e('Enable display data in the textarea of your PDF file?', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_data_textarea" name="wp_cf7pdf_settings[data_textarea]" value="true" <?php if( isset($meta_values["data_textarea"]) && $meta_values["data_textarea"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_data_textarea"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_data_textarea_no" name="wp_cf7pdf_settings[data_textarea]" value="false" <?php if( empty($meta_values["data_textarea"]) || (isset($meta_values["data_textarea"]) && $meta_values["data_textarea"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_data_textarea_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Ne pas afficher les entrée vides des Checkbox et Radio -->
                            <?php esc_html_e('Disable display empty data in the checkbox or radio buttons?', 'send-pdf-for-contact-form-7'); ?>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_empty_input" name="wp_cf7pdf_settings[empty_input]" value="true" <?php if( isset($meta_values["empty_input"]) && $meta_values["empty_input"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_empty_input"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_empty_input_no" name="wp_cf7pdf_settings[empty_input]" value="false" <?php if( empty($meta_values["empty_input"]) || (isset($meta_values["empty_input"]) && $meta_values["empty_input"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_empty_input_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Propose de mettre le PDF en mode éditable -->
                            <?php esc_html_e('Enable fillable PDF Forms?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php esc_html_e("Don't works if your PDF is protected.", 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_fillable_data" name="wp_cf7pdf_settings[fillable_data]" value="true" <?php if( isset($meta_values["fillable_data"]) && $meta_values["fillable_data"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_fillable_data"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_fillable_data_no" name="wp_cf7pdf_settings[fillable_data]" value="false" <?php if( empty($meta_values["fillable_data"]) || (isset($meta_values["fillable_data"]) && $meta_values["fillable_data"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_fillable_data_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>
                    <tr>
                        <td><!-- Proteger le pdf? -->
                            <?php esc_html_e('Protect your PDF file?', 'send-pdf-for-contact-form-7'); ?>
                            <p><i><?php esc_html_e('Use [pdf-password] tag in your emails.', 'send-pdf-for-contact-form-7'); ?></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_protect" name="wp_cf7pdf_settings[protect]" value="true" <?php if( isset($meta_values["protect"]) && $meta_values["protect"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_protect"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_protect_no" name="wp_cf7pdf_settings[protect]" value="false" <?php if( empty($meta_values["protect"]) || (isset($meta_values["protect"]) && $meta_values["protect"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_protect_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td><!-- Proteger le pdf? -->
                            <?php esc_html_e('Generate and use a random password?', 'send-pdf-for-contact-form-7'); ?>
                            <?php
                                $nbPassword = 12;
                                if( isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"]) ) { 
                                    $nbPassword = esc_html($meta_values["protect_password_nb"]); 
                                }
                            ?>
                            <p><i><?php esc_html_e('Example (not working in preview mode):', 'send-pdf-for-contact-form-7'); ?> <strong><?php echo esc_html(cf7_sendpdf::wpcf7pdf_generateRandomPassword($nbPassword)); ?></strong></i></p>
                        </td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_protect_uniquepassword" name="wp_cf7pdf_settings[protect_uniquepassword]" value="true" <?php if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true') { echo ' checked'; } ?>/>
                                <label for="switch_protect_uniquepassword"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_protect_uniquepassword_no" name="wp_cf7pdf_settings[protect_uniquepassword]" value="false" <?php if( empty($meta_values["protect_uniquepassword"]) || (isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='false') ) { echo ' checked'; } ?> />
                                <label for="switch_protect_uniquepassword_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td><?php esc_html_e('Maximum number of characters for password?', 'send-pdf-for-contact-form-7'); ?><p><i><?php esc_html_e('By default : 12', 'send-pdf-for-contact-form-7'); ?></i></p></td>
                        <td><input type="text" size="3" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password_nb]" value="<?php if( isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"]) ) { echo esc_html($meta_values["protect_password_nb"]); } else { echo esc_html($nbPassword); } ?>" /></td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Or enter your unique password for all PDF files.', 'send-pdf-for-contact-form-7'); ?></td>
                        <td><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password]" value="<?php if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) { echo esc_html(stripslashes($meta_values["protect_password"])); } ?>" /></td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Or choose a tag for password for each PDF files.', 'send-pdf-for-contact-form-7'); ?><p><i><?php esc_html_e('Like: [tag]', 'send-pdf-for-contact-form-7'); ?></i></p></td>
                        <td><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[protect_password_tag]" value="<?php if( isset($meta_values["protect_password_tag"]) && $meta_values["protect_password_tag"]!='' ) { echo esc_html( $meta_values["protect_password_tag"] ); } ?>" /></td>
                    </tr>

                    <tr>
                        <td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td>
                    </tr>

                    <tr>
                        <td><?php esc_html_e('Delete all config for this form?', 'send-pdf-for-contact-form-7'); ?><p><i><?php esc_html_e('Click Yes and save the form.', 'send-pdf-for-contact-form-7'); ?></i></p></td>
                        <td>
                            <div>
                                <div class="switch-field">
                                <input class="switch_left" type="radio" id="switch_deleteconfig" name="deleteconfig" value="true"/>
                                <label for="switch_deleteconfig"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                <input class="switch_right" type="radio" id="switch_deleteconfig_no" name="deleteconfig" value="false" checked />
                                <label for="switch_deleteconfig_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
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
        <div class="handlediv" style="height:1px!important;" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
        <span class="dashicons customDashicons dashicons-pdf"></span> <h3 class="hndle" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><?php esc_html_e('Layout of your PDF', 'send-pdf-for-contact-form-7'); ?></h3>
        <div class="inside openinside">

            <table class="wp-list-table widefat fixed" cellspacing="0">
                <tbody id="the-list">
                    <tr>
                        <td style="width: 60%;">
                            <h3 class="hndle"><span class="dashicons dashicons-format-image"></span>&nbsp;&nbsp;<?php esc_html_e('Image header', 'send-pdf-for-contact-form-7'); ?></h3>
                            <?php esc_html_e('Enter a URL or upload an image:', 'send-pdf-for-contact-form-7'); ?><br />
                            <input id="upload_image" size="80%" class="wpcf7-form-field" name="wp_cf7pdf_settings[image]" value="<?php if( isset($meta_values['image']) ) { echo esc_url($meta_values['image']); } ?>" type="text" /> <a href="#" id="upload_image_button" class="button" OnClick="this.blur();"><span> <?php esc_html_e('Select or Upload your picture', 'send-pdf-for-contact-form-7'); ?> </span></a> <br />
                            <div style="margin-top:0.8em;">
                                <select name="wp_cf7pdf_settings[image-alignment]" class="wpcf7-form-field">
                                    <option value="left" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='left') ) { echo 'selected'; } ?>><?php esc_html_e('Left', 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="center" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='center') ) { echo 'selected'; } ?>><?php esc_html_e('Center', 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="right" <?php if( isset($meta_values['image-alignment']) && ($meta_values['image-alignment']=='right') ) { echo 'selected'; } ?>><?php esc_html_e('Right', 'send-pdf-for-contact-form-7'); ?></option>
                                </select>
                                <?php esc_html_e('Size', 'send-pdf-for-contact-form-7'); ?> <input type="text" class="wpcf7-form-field" size="3" name="wp_cf7pdf_settings[image-width]" value="<?php if( isset($meta_values['image-width']) ) { echo esc_html($meta_values['image-width']); } else { echo '150'; } ?>" />&nbsp;X&nbsp;<input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[image-height]" size="3" value="<?php if( isset($meta_values['image-height']) ) { echo esc_html($meta_values['image-height']); } ?>" />px<br /><br />
                                
                                <div><?php esc_html_e('Display header on each page?', 'send-pdf-for-contact-form-7'); ?>
                                    <div class="switch-field-mini">
                                        <input class="switch_left" type="radio" id="switch_page_header" name="wp_cf7pdf_settings[page_header]" value="1" <?php if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) { echo ' checked'; } ?>/>
                                        <label for="switch_page_header"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                        <input class="switch_right" type="radio" id="switch_page_header_no" name="wp_cf7pdf_settings[page_header]" value="0" <?php if( empty($meta_values["page_header"]) || (isset($meta_values["page_header"]) && $meta_values["page_header"]==0) ) { echo ' checked'; } ?> />
                                        <label for="switch_page_header_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
                                    </div><br />
                                    <?php esc_html_e('Margin Bottom Header', 'send-pdf-for-contact-form-7'); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[margin_bottom_header]" value="<?php if( isset($meta_values["margin_bottom_header"]) && $meta_values["margin_bottom_header"]!='' ) { echo esc_html($meta_values["margin_bottom_header"]); } else { echo esc_html($marginBottomHeader); } ?>" />                            
                                </div>
                            </div>

                            <h3 class="hndle"><span class="dashicons dashicons-images-alt2"></span>&nbsp;&nbsp;<?php esc_html_e('Image Background', 'send-pdf-for-contact-form-7'); ?></h3>
                            <?php esc_html_e('Enter a URL or upload an image:', 'send-pdf-for-contact-form-7'); ?><br />
                            <input id="upload_background" size="80%" class="wpcf7-form-field" name="wp_cf7pdf_settings[image_background]" value="<?php if( isset($meta_values['image_background']) ) { echo esc_url($meta_values['image_background']); } ?>" type="text" /> <a href="#" id="upload_image_background" class="button" OnClick="this.blur();"><span> <?php esc_html_e('Select or Upload your picture', 'send-pdf-for-contact-form-7'); ?> </span></a><br /><small><?php esc_html_e('Example for demo:', 'send-pdf-for-contact-form-7'); ?> <a href="<?php echo esc_url(plugins_url( '../images/background.jpg', __FILE__ )); ?>" target="_blank"><?php esc_html_e('here', 'send-pdf-for-contact-form-7'); ?></a></small><br />
                            <div style="margin-top:0.8em;">                           
                                <div><?php esc_html_e('Display background on each page?', 'send-pdf-for-contact-form-7'); ?>
                                    <div class="switch-field-mini">
                                        <input class="switch_left" type="radio" id="switch_page_background" name="wp_cf7pdf_settings[page_background]" value="1" <?php if( isset($meta_values["page_background"]) && $meta_values["page_background"]==1) { echo ' checked'; } ?>>
                                        <label for="switch_page_background"><?php esc_html_e('Yes', 'send-pdf-for-contact-form-7'); ?></label>
                                        <input class="switch_right" type="radio" id="switch_page_background_no" name="wp_cf7pdf_settings[page_background]" value="0" <?php if( empty($meta_values["page_background"]) || (isset($meta_values["page_background"]) && $meta_values["page_background"]==0) ) { echo ' checked'; } ?>>
                                        <label for="switch_page_background_no"><?php esc_html_e('No', 'send-pdf-for-contact-form-7'); ?></label>
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
                                <div style="color:#cccccc;text-align:justify;margin-top:<?php echo esc_html($previewMargin); ?>px;">
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
                            <h3 class="hndle"><span class="dashicons dashicons-media-code"></span> <?php esc_html_e('Custom CSS', 'send-pdf-for-contact-form-7'); ?></h3>
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
                            <h3 class="hndle"><span class="dashicons dashicons-arrow-down-alt"></span> <?php esc_html_e('Footer', 'send-pdf-for-contact-form-7'); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <?php esc_html_e('You can use this tags:', 'send-pdf-for-contact-form-7'); ?><br />
                            <ul>
                                <li><strong>{PAGENO}/{nbpg}</strong> <?php esc_html_e('will be replaced by the current page number / total pages.', 'send-pdf-for-contact-form-7'); ?></li>
                                <li><strong>{DATE j-m-Y}</strong> <?php esc_html_e('will be replaced by the current date. j-m-Y can be replaced by any of the valid formats used in the php', 'send-pdf-for-contact-form-7'); ?> <a href="http://www.php.net/manual/en/function.date.php" target="_blank">date()</a> <?php esc_html_e('function.', 'send-pdf-for-contact-form-7' ); ?></li>
                                <li><strong>[reference]</strong>, <strong>[date]</strong>, <strong>[time]</strong> <?php esc_html_e('tags works also.', 'send-pdf-for-contact-form-7'); ?></li>
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
                            <h3 class="hndle"><span class="dashicons dashicons-tagcloud"></span> <?php esc_html_e('Personalize your PDF', 'send-pdf-for-contact-form-7'); ?></h3>
                        </td>
                    </tr>
                    <tr>
                        <tr>
                            <td><?php esc_html_e('Page size & Orientation', 'send-pdf-for-contact-form-7'); ?></td>
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
                                    <option value="-P" <?php if( (isset($meta_values['pdf-orientation']) && ($meta_values['pdf-orientation']=='-P')) OR empty($meta_values['pdf-orientation']) ) { echo 'selected'; } ?>><?php esc_html_e('Portrait', 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="-L" <?php if( isset($meta_values['pdf-orientation']) && ($meta_values['pdf-orientation']=='-L') ) { echo 'selected'; } ?>><?php esc_html_e('Landscape', 'send-pdf-for-contact-form-7'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Font Family & Size', 'send-pdf-for-contact-form-7'); ?></td>
                            <td>
                                <select name="wp_cf7pdf_settings[pdf-font]" class="wpcf7-form-field">
                                    <?php             
                                        foreach($listFont as $font => $nameFont) {
                                            $selected ='';
                                            if( isset($meta_values['pdf-font']) && $meta_values['pdf-font']==$nameFont ) { $selected = 'selected'; }
                                            echo '<option value="'.esc_html($nameFont).'" '.esc_html($selected).'>'.esc_html($font).'</option>';
                                        }
                                    ?>
                                </select>
                                <input type="text" name="wp_cf7pdf_settings[pdf-fontsize]" class="wpcf7-form-field" size="2" value="<?php if( isset($meta_values['pdf-fontsize']) && is_numeric($meta_values['pdf-fontsize']) ) { echo esc_html($meta_values['pdf-fontsize']); } else { echo esc_html($fontsizePdf); } ?>">
                            </td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Add a CSS file', 'send-pdf-for-contact-form-7'); ?><br /><p><a href="<?php echo esc_url(plugins_url( '../css/mpdf-style-A4.css', __FILE__ )); ?>" target="_blank"><small><i><?php esc_html_e('Download a example A4 page here', 'send-pdf-for-contact-form-7'); ?></i></small></a></p></td>
                            <td>
                                <input size="60%" class="wpcf7-form-field" name="wp_cf7pdf_settings[stylesheet]" value="<?php if( isset($meta_values['stylesheet']) ) { echo esc_url($meta_values['stylesheet']); } ?>" type="text" /><br />
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <?php esc_html_e('Global Margin PDF', 'send-pdf-for-contact-form-7'); ?><br /><p></p>
                            </td>
                            <td>
                                <?php esc_html_e('Margin Header', 'send-pdf-for-contact-form-7'); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[margin_header]" value="<?php if( isset($meta_values["margin_header"]) && $meta_values["margin_header"]!='' ) { echo esc_html($meta_values["margin_header"]); } else { echo esc_html($marginHeader); } ?>" /> <?php esc_html_e('Margin Top Header', 'send-pdf-for-contact-form-7'); ?> <input type="text" class="wpcf7-form-field" size="4" name="wp_cf7pdf_settings[margin_top]" value="<?php if( isset($meta_values["margin_top"]) && $meta_values["margin_top"]!='' ) { echo esc_html($meta_values["margin_top"]); } else { echo esc_html($marginTop); } ?>" /><br /><br />
                                <?php esc_html_e('Margin Left', 'send-pdf-for-contact-form-7'); ?> <input type="text" size="4" class="wpcf7-form-field" name="wp_cf7pdf_settings[margin_left]" value="<?php if( isset($meta_values["margin_left"]) && $meta_values["margin_left"]!='' ) { echo esc_html($meta_values["margin_left"]); } else { echo esc_html($marginLeft); } ?>" /> <?php esc_html_e('Margin Right', 'send-pdf-for-contact-form-7'); ?> <input type="text" class="wpcf7-form-field" size="4" name="wp_cf7pdf_settings[margin_right]" value="<?php if( isset($meta_values["margin_right"]) && $meta_values["margin_right"]!='' ) { echo esc_html($meta_values["margin_right"]); } else { echo esc_html($marginRight); } ?>" /><br /><br />
                                <?php esc_html_e('Margin Header Auto', 'send-pdf-for-contact-form-7'); ?> <select name="wp_cf7pdf_settings[margin_auto_header]" class="wpcf7-form-field">
                                    <option value="pad" <?php if( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"] == 'pad' ) { echo 'selected'; } ?>>pad</option>
                                    <option value="stretch" <?php if( empty($meta_values["margin_auto_header"]) || (isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"] == 'stretch') ) { echo 'selected'; } ?>>stretch</option>
                                    <option value="false" <?php if( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"] == 'false' ) { echo 'selected'; } ?>>false</option>
                                </select> <?php esc_html_e('Margin Bottom Auto', 'send-pdf-for-contact-form-7'); ?>
                                <select name="wp_cf7pdf_settings[margin_auto_bottom]" class="wpcf7-form-field">
                                    <option value="pad" <?php if( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"] == 'pad' ) { echo 'selected'; } ?>>pad</option>
                                    <option value="stretch" <?php if( empty($meta_values["margin_auto_bottom"]) || (isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"] == 'stretch') ) { echo 'selected'; } ?>>stretch</option>
                                    <option value="false" <?php if( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"] == 'false' ) { echo 'selected'; } ?>>false</option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <?php esc_html_e('Choice of separator for checkboxes or radio buttons', 'send-pdf-for-contact-form-7'); ?><br /><p></p>
                            </td>
                            <td><?php esc_html_e("Before:", 'send-pdf-for-contact-form-7'); ?><br />
                               <select name="wp_cf7pdf_settings[separate]" class="wpcf7-form-field">
                                    <option value="none" <?php if( empty($meta_values["separate"]) || (isset($meta_values["separate"]) && $meta_values["separate"] == 'none') ) { echo 'selected'; } ?>><?php esc_html_e("None", 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="dash" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'dash') { echo 'selected'; } ?>><?php esc_html_e("Dash", 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="star" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'star') { echo 'selected'; } ?>><?php esc_html_e("Star", 'send-pdf-for-contact-form-7'); ?></option>                                    
                                    <option value="rightarrow" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'rightarrow') { echo 'selected'; } ?>><?php esc_html_e("Right Arrow", 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="double-right-arrow" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'double-right-arrow') { echo 'selected'; } ?>><?php esc_html_e("Double Right Arrow", 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="cornerarrow" <?php if( isset($meta_values["separate"]) && $meta_values["separate"] == 'cornerarrow') { echo 'selected'; } ?>><?php esc_html_e("Corner Arrow", 'send-pdf-for-contact-form-7'); ?></option>                   
                                </select><br />
                                <?php esc_html_e("After:", 'send-pdf-for-contact-form-7'); ?><br />
                                <select name="wp_cf7pdf_settings[separate_after]" class="wpcf7-form-field">
                                    <option value="comma" <?php if( isset($meta_values["separate_after"]) && $meta_values["separate_after"] == 'comma' ) { echo 'selected'; } ?>><?php esc_html_e("Comma", 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="space" <?php if( empty($meta_values["separate_after"]) || (isset($meta_values["separate_after"]) && $meta_values["separate_after"] == 'space') ) { echo 'selected'; } ?>><?php esc_html_e("Space", 'send-pdf-for-contact-form-7'); ?></option>
                                    <option value="linebreak" <?php if( isset($meta_values["separate_after"]) && $meta_values["separate_after"] == 'linebreak') { echo 'selected'; } ?>><?php esc_html_e("Line break", 'send-pdf-for-contact-form-7'); ?></option>                                    
                                </select><br />
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2">
                            <legend>
                                <?php esc_html_e('For personalize your PDF you can in the following text field, use these mail-tags:', 'send-pdf-for-contact-form-7'); ?><br />
                                <table>
                                    <tr>
                                        <td width="50%">
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[addpage]</strong></span><br /><i><?php esc_html_e("[addpage] is a simple tag to force a page break anywhere in your PDF.", 'send-pdf-for-contact-form-7'); ?></i>
                                        </td>
                                        <td width="50%">
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[date]</strong></span> <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[time]</strong></span><br /><i><?php esc_html_e("Use [date] and [time] to print the date and time anywhere in your PDF.", 'send-pdf-for-contact-form-7'); ?></i>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width="50%">
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[reference]</strong></span><br /><i><?php esc_html_e("[reference] is a simple mail-tag who is used for create unique PDF. It's also recorded in the database. Every PDF is named like this : name-pdf-uniqid() and it's uploaded in the upload folder of WordPress.", 'send-pdf-for-contact-form-7'); ?></i><br /><br />
                                        </td>
                                        <td width="50%">                                            
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[ID]</strong></span><br /><i><?php esc_html_e("[ID] is a simple tag that comes from the database ID if you have allowed registration in the options.", 'send-pdf-for-contact-form-7'); ?></i><br /><br />
                                            <span class="mailtag code used" onclick="jQuery(this).selectText()" style="cursor: pointer;"><strong>[avatar]</strong></span><br /><i><?php esc_html_e("[avatar] is a simple mail-tag for the user Avatar URL.", 'send-pdf-for-contact-form-7'); ?></i>
                                            <br /><br />
                                            <i><?php echo esc_html__('Enter here your Shortcodes', 'send-pdf-for-contact-form-7'); ?></i><br /><small><?php esc_html_e('It will then be necessary to put them in the PDF layout. Test with this shortcode: [wpcf7pdf_test]', 'send-pdf-for-contact-form-7'); ?></small><br /><input type="text" class="wpcf7-form-field" name="wp_cf7pdf_settings[shotcodes_tags]" size="80%" value="<?php if( isset($meta_values['shotcodes_tags'])) { echo esc_html($meta_values['shotcodes_tags']); } ?>" />
                                        </td>
                                    
                                    </tr>
                                    <tr>
                                    <td width="50%">
                                        <?php
                                            /*
                                            * ECRIT DANS UN POST-META LES TAGS DU FORMULAIRE 
                                            *
                                            */
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
                                            $valOpt = '';
                                            $valTag = '';
                                            foreach ( $contact_tag as $sh_tag ) {
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
                                                    <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php esc_html_e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
                                                    <?php if( file_exists($createDirectory.'/preview-'.esc_html($idForm).'.pdf') ) { ?>
                                                        <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.esc_html($idForm).'.pdf?ver='.esc_html(wp_rand()); ?>" ><?php esc_html_e('Preview your PDF', 'send-pdf-for-contact-form-7'); ?></a>
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

                    <!-- Si plusieurs PDF demandé -->
                    <?php 
                    // Nombre de PDF demandé
                    if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) {                                

                        $compareName = array();

                        for ($i = 2; $i <= $meta_values["number-pdf"]; $i++) {

                            // On évite le même nom du fichier, sinon message d'erreur
                            $compareName[$i] = sanitize_title($meta_values['nameaddpdf'.$i.'']);
                            $sameName = array_count_values($compareName);
                           
                    ?>
                    <tr><td colspan="2"><hr style="background-color: <?php echo esc_html($colors[2]); ?>; height: 1px; border: 0;"></td></tr>
                    <tr>
                        <td width="50%"><h3>PDF <?php echo esc_html($i); ?></h3></td>
                        <td width="50%" style="text-align:center;">
                            <div style="text-align:right;">
                                <p>
                                    <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php esc_html_e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
                                    <?php if( file_exists($createDirectory.'/preview-'.sanitize_title($meta_values['nameaddpdf'.$i.'']).'-'.esc_html($idForm).'.pdf') ) { ?>
                                        <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.sanitize_title($meta_values['nameaddpdf'.$i.'']).'-'.esc_html($idForm).'.pdf?ver='.esc_html(wp_rand()); ?>" ><?php esc_html_e('Preview your PDF', 'send-pdf-for-contact-form-7'); ?></a>
                                    <?php } ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2">                           
                            <i><?php echo esc_html__('Enter here the PDF name.', 'send-pdf-for-contact-form-7'); ?></i><br />
                            <input type="text" class="wpcf7-form-field" value="<?php if( isset( $meta_values['nameaddpdf'.$i] ) ) { echo esc_html($meta_values['nameaddpdf'.$i]); } ?>" name="wp_cf7pdf_settings[nameaddpdf<?php echo esc_html($i); ?>]" ><span style="font-weight: bold;color: #CC0000;padding: 1.2em;"><?php if( $sameName[sanitize_title($meta_values['nameaddpdf'.$i.''])] >1 ) { esc_html_e('Error: this file name already exists!', 'send-pdf-for-contact-form-7'); } ?></span><br /><br />                                                 
                            <textarea name="wp_cf7pdf_settings[content_addpdf_<?php echo esc_html($i); ?>]" id="wp_cf7pdf_pdf_<?php echo esc_html($i); ?>" cols=70 rows=24 class="widefat textarea"style="height:250px;"><?php if( isset($meta_values['content_addpdf_'.$i.'']) && !empty($meta_values['content_addpdf_'.$i.'']) ) { echo esc_textarea($meta_values['content_addpdf_'.$i.'']); } ?></textarea><br />
                            
                        </td>
                    </tr>
                    <?php } ?>
                    <?php } ?>

                </tbody>
            </table>
        </div>
    </div>
    <div class="clear">&nbsp;</div>
    <div style="text-align:left;">
        <p>
            <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php esc_html_e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
            <?php if( file_exists($createDirectory.'/preview-'.esc_html($idForm).'.pdf') ) { ?>
                <a class="button button-secondary" target="_blank" href="<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)).'/preview-'.esc_html($idForm).'.pdf?ver='.esc_html(wp_rand()); ?>" ><?php esc_html_e('Preview your PDF', 'send-pdf-for-contact-form-7'); ?></a>
            <?php } ?>
        </p>
    </div>
    <div class="clear" style="margin-bottom:15px;">&nbsp;</div>
<?php if( empty($meta_values["disable-csv"]) || (isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false') ) { ?>
<a name="fieldmatching"></a>
<div class="postbox">

    <div class="handlediv" style="height:1px!important;" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
    <span class="dashicons customDashicons dashicons-randomize"></span> <h3 class="hndle"><?php esc_html_e( 'Personalize Field for CSV file', 'send-pdf-for-contact-form-7' ); ?></h3>
    <div class="inside">
        <div style="padding:5px;margin-bottom:10px;">
            <div>
                <table>
                    <thead>
                        <tr id="cb" class="manage-column column-cb check-column">
                            <th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e( 'Hidden', 'send-pdf-for-contact-form-7' ); ?></th>
                            <th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e( 'Identifier', 'send-pdf-for-contact-form-7' ); ?></th>
                            <th scope="col" id="slug" class="manage-column column-name column-primary"><?php esc_html_e( 'Field Name', 'send-pdf-for-contact-form-7' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="the-list" class="ui-sortable">
                        <?php 
                            $meta_tagsname = get_post_meta( $idForm, '_wp_cf7pdf_customtagsname', true );
                        ?>
                        <tr scope="row" class="check-column">
                            <td class="name column-name" style="text-align:center;"><input type="checkbox" class="wpcf7-form-field" name="wp_cf7pdf_custom_tags_name['hidden-reference']" value="1" <?php if( ( isset($meta_tagsname) ) && isset($meta_tagsname['hidden-reference']) && $meta_tagsname['hidden-reference']==1 ) { echo ' checked'; } ?> /></td>
                            <td class="name column-name"><input type="text" value="reference" size="50" disabled /></td>
                            <td class="slug column-slug"><input type="text" class="wpcf7-form-field" value="<?php if( isset($meta_tagsname) && isset($meta_tagsname['reference']) ) { echo esc_html($meta_tagsname['reference']); } ?>" size="50" name="wp_cf7pdf_custom_tags_name[reference]" /></td>
                        </tr>
                        <tr scope="row" class="check-column">
                            <td class="name column-name" style="text-align:center;"><input type="checkbox" class="wpcf7-form-field" name="wp_cf7pdf_custom_tags_name['hidden-date']" value="1" <?php if( ( isset($meta_tagsname) ) && isset($meta_tagsname['hidden-reference']) && $meta_tagsname['hidden-reference']==1 ) { echo ' checked'; } ?> /></td>
                            <td class="name column-name"><input type="text" value="date" size="50" disabled /></td>
                            <td class="slug column-slug"><input type="text" class="wpcf7-form-field" value="<?php if( isset($meta_tagsname) && isset($meta_tagsname['date']) ) { echo esc_html($meta_tagsname['date']); } ?>" size="50" name="wp_cf7pdf_custom_tags_name[date]" /></td>
                        </tr>
                            <?php 
                                foreach ( $contact_tag as $sh_tag ) {

                                    if( isset($sh_tag["name"]) && $sh_tag["name"]!='' ) {  
                                        $hiddenTag = 'hidden-'.$sh_tag["name"];                                        
                            ?>
                        <tr scope="row" class="check-column">
                            <td class="name column-name" style="text-align:center;"><input type="checkbox" class="wpcf7-form-field" name="wp_cf7pdf_custom_tags_name[<?php echo esc_html('hidden-'.stripslashes($sh_tag["name"])); ?>]" value="1" <?php if( ( isset($meta_tagsname) ) && isset($meta_tagsname[$hiddenTag]) && $meta_tagsname[$hiddenTag]==1 ) { echo ' checked'; } ?> /></td>
                            <td class="name column-name"><input type="text" value="<?php echo esc_html($sh_tag["name"]); ?>" size="50" disabled /></td>
                            <td class="slug column-slug"><input type="text" class="wpcf7-form-field" value="<?php if( isset($meta_tagsname) && isset($meta_tagsname[$sh_tag["name"]]) ) { echo esc_html($meta_tagsname[$sh_tag["name"]]); } ?>" size="50" name="wp_cf7pdf_custom_tags_name[<?php echo esc_html(stripslashes($sh_tag["name"])); ?>]" /></td>
                        </tr>
                            <?php
                                    }
                                } 
                            ?>
                        <tr>
                            <td></td>
                            <td></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="col" id="name" class="manage-column column-name column-primary"><?php esc_html_e( 'Hidden', 'send-pdf-for-contact-form-7' ); ?></th>
                            <th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Identifier', 'send-pdf-for-contact-form-7' ); ?></th>
                            <th scope="col" class="manage-column column-slug"><?php esc_html_e( 'Field Name', 'send-pdf-for-contact-form-7' ); ?></th>
                        </tr>
                    </tfoot>
                </table>
                <div style="text-align:left;">
                    <p>
                        <input type="submit" name="wp_cf7pdf_update_settings" class="button-primary" value="<?php esc_html_e('Save settings', 'send-pdf-for-contact-form-7'); ?>"/>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php } ?>
</form>
<?php if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=="false") { ?>

    <div class="postbox">

        <div class="handlediv" style="height:1px!important;" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
        <span class="dashicons customDashicons dashicons-list-view"></span> <h3 class="hndle"><?php esc_html_e( 'Last records', 'send-pdf-for-contact-form-7' ); ?></h3>
        <div class="inside">
            <a name="listing"></a>
            <?php
            $limitList = 15;
            $settingsLimit = get_post_meta( esc_html($idForm), '_wp_cf7pdf_limit', true );
            if( isset($settingsLimit) && $settingsLimit > 0 ) { $limitList = $settingsLimit; }
                                                                                            
            $list = WPCF7PDF_settings::wpcf7pdf_listing(esc_html($idForm), esc_html($limitList));
            if( $list ) { ?>
                <div style="padding:5px;margin-bottom:10px;">
                    <div>
                        <form method="post" action="#listing">

                            <?php wp_nonce_field( 'wpcf7_listing_nonce', 'wpcf7_listing_nonce' ); ?>
                            <input type="hidden" name="idform" value="<?php echo esc_html($idForm); ?>"/>
                            <input type="hidden" name="wpcf7_action" value="listing_settings" />
                            <input type="text" value="<?php echo esc_html($limitList); ?>" size="4" name="listing_limit" > <?php submit_button( esc_html__( 'Change', 'send-pdf-for-contact-form-7' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </div>
                
                    <?php    
                        echo '<br /><table id="customers">';
                        echo '<th>'.esc_html__('Reference', 'send-pdf-for-contact-form-7').'</th><th>'.esc_html__('Date', 'send-pdf-for-contact-form-7').'</th><th colspan="2">'.esc_html__('Download', 'send-pdf-for-contact-form-7').'</th><th>'.esc_html__('Delete', 'send-pdf-for-contact-form-7').'</th>';

                        foreach($list as $recorder) {
                            echo '<tr width="100%">';
                            $datas = maybe_unserialize($recorder->wpcf7pdf_data);
                            if( isset($datas) && $datas!=false) {
                                echo '<td width="10%">';
                                echo '<a href="'.esc_url($recorder->wpcf7pdf_files).'" target="_blank">'.esc_html($datas[0]) .'</a>';                                
                                echo '</td>'; 
                                echo '<td width="20%">';
                                echo ''.esc_html($datas[1]);                                
                                echo '</td>';                                 
                                echo '<td width="5%"><a href="'.esc_url($recorder->wpcf7pdf_files).'" target="_blank"><img src="'.esc_url(WPCF7PDF_URL.'images/icon_download.png').'" width="40" title="'.esc_html__('Download', 'send-pdf-for-contact-form-7').'" alt="'.esc_html__('Download', 'send-pdf-for-contact-form-7').'" /></a></td>';
                                if( isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false' &&  $recorder->wpcf7pdf_files2!='' ) {
                                echo '<td width="5%"><a href="'.esc_url($recorder->wpcf7pdf_files2).'" target="_blank"><img src="'.esc_url(WPCF7PDF_URL.'images/icon_download_csv.png').'" width="40" title="'.esc_html__('Download CSV file', 'send-pdf-for-contact-form-7').'" alt="'.esc_html__('Download CSV file', 'send-pdf-for-contact-form-7').'" /></a></td>'; 
                                } else {
                                    echo '<td width="5%"><img src="'.esc_url(WPCF7PDF_URL.'images/icon_download_empty.png').'" width="40" /></td>';
                                }           
                        ?><td width="5%"><a href="#" data-idform="<?php echo esc_html($idForm); ?>" data-id="<?php echo esc_html($recorder->wpcf7pdf_id); ?>" data-message="<?php esc_html_e('Are you sure you want to delete this Record?', 'send-pdf-for-contact-form-7'); ?>" data-nonce="<?php echo esc_html(wp_create_nonce('delete_record-'.$recorder->wpcf7pdf_id)); ?>" class="delete-record"><img src="<?php echo esc_url(WPCF7PDF_URL.'images/icon_delete.png'); ?>" width="40" title="<?php esc_html_e('Delete', 'send-pdf-for-contact-form-7'); ?>" alt="<?php esc_html_e('Delete', 'send-pdf-for-contact-form-7'); ?>" /></a>
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
                                <div><?php
                                        $my_url = admin_url('admin.php?page=wpcf7-send-pdf&amp;idform='.esc_html($_POST['idform']).'&amp;csv=1');
                                        $nonce_url = wp_nonce_url( $my_url, 'csv_security' );
                                    ?>
                                    <span class="dashicons dashicons-download"></span> <a href="<?php echo esc_url($nonce_url); ?>" alt="<?php esc_html_e('Export list', 'send-pdf-for-contact-form-7'); ?>" title="<?php esc_html_e('Export list', 'send-pdf-for-contact-form-7'); ?>"><?php esc_html_e('Export list in CSV file', 'send-pdf-for-contact-form-7'); ?></a>
                                </div>
                        </tr>
                    </tbody>
                </table>
            <?php } else { esc_html_e('Data not found!', 'send-pdf-for-contact-form-7'); } ?>
        </div>
    </div>
<?php } ?>


    
<div class="postbox">
   <div class="handlediv" style="height:1px!important;" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
   <span class="dashicons customDashicons dashicons-download"></span> <h3 class="hndle" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><?php esc_html_e( 'Export Settings', 'send-pdf-for-contact-form-7' ); ?></h3>
    <div class="inside">
        <form method="post">
            <p>
              <input type="hidden" name="wpcf7_action" value="export_settings" />
              <input type="hidden" name="wpcf7pdf_export_id" value="<?php echo esc_html($idForm); ?>" />
            </p>
            <p>
                <?php wp_nonce_field( 'go_export_nonce', 'wpcf7_export_nonce' ); ?>
                <?php submit_button( esc_html__( 'Export', 'send-pdf-for-contact-form-7' ), 'secondary', 'submit', false ); ?>
            </p>
        </form>
    </div>
</div>

<div class="postbox">
   <div class="handlediv" style="height:1px!important;" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><br></div>
   <span class="dashicons customDashicons dashicons-upload"></span> <h3 class="hndle" title="<?php esc_html_e('Click to toggle', 'send-pdf-for-contact-form-7'); ?>"><?php esc_html_e( 'Import Settings', 'send-pdf-for-contact-form-7' ); ?></h3>
    <div class="inside">
      <p><?php esc_html_e( 'Import the plugin settings from a .json file. This file can be obtained by exporting the settings on another site using the form above.', 'send-pdf-for-contact-form-7' ); ?></p>
      <form method="post" enctype="multipart/form-data">
          <p>
              <input type="file" name="wpcf7_import_file"/>
          </p>
          <p>
              <input type="hidden" name="wpcf7_action" value="import_settings" />
              <input type="hidden" name="idform" value="<?php echo esc_html($idForm); ?>"/>
              <input type="hidden" name="wpcf7pdf_import_id" value="<?php echo esc_html($idForm); ?>" />
              <?php wp_nonce_field( 'go_import_nonce', 'wpcf7_import_nonce' ); ?>
              <?php submit_button( esc_html__( 'Import', 'send-pdf-for-contact-form-7' ), 'secondary', 'submit_import', false ); ?>
          </p>
      </form>
    </div>
</div>

<?php }?>
<?php } else { ?>
    <div style="margin-left: 0px;margin-top: 5px;background-color: #ffffff;border: 1px solid #cccccc;padding: 10px;">
        <?php /* translators: %s: lien vers Contact Form 7 */ printf( esc_html__('To work I need %s plugin, but it is apparently not installed or not enabled!', 'send-pdf-for-contact-form-7'), '<a href="https://wordpress.org/plugins/contact-form-7/" target="_blank">Contact Form 7</a>' ); ?>
    </div>
<?php } ?>
    <div style="margin-top:40px;">
        <?php esc_html_e('Send PDF for Contact Form 7 is brought to you by', 'send-pdf-for-contact-form-7'); ?> <a href="https://madeby.restezconnectes.fr/" target="_blank">MadeByRestezConnectes</a> - <?php esc_html_e('If you found this plugin useful', 'send-pdf-for-contact-form-7'); ?> <a href="https://wordpress.org/support/view/plugin-reviews/send-pdf-for-contact-form-7/" target="_blank"><?php esc_html_e('give it 5 &#9733; on WordPress.org', 'send-pdf-for-contact-form-7'); ?></a>
    </div>
</div>
<?php 
// Si plusieurs PDF 
if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) { ?>
<script>
 'use strict';
 (function($){
    $(function(){

        <?php   for ($i = 2; $i <= $meta_values["number-pdf"]; $i++) { ?>
        if( $('#wp_cf7pdf_pdf_<?php echo esc_html($i); ?>').length ) {
            var editorSettings = wp.codeEditor.defaultSettings ? _.clone( wp.codeEditor.defaultSettings ) : {};
            editorSettings.codemirror = _.extend(
                {},
                editorSettings.codemirror,
                {
                    indentUnit: 2,
                    tabSize: 2,
                    mode: 'text/html',
                }
            );
            var editor = wp.codeEditor.initialize( $('#wp_cf7pdf_pdf_<?php echo esc_html($i); ?>'), editorSettings );
        }
        <?php } ?>

    });
})(jQuery);
</script>
<?php } ?>