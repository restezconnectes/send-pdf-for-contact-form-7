<?php


defined( 'ABSPATH' )
	or die( 'No direct load ! ' );


/**
 * Prepare PDF Class for Contact Form 7.
 *
 * @link https://madeby.restezconnectes.fr/project/send-pdf-for-contact-form-7/
 * @author Florent Maillefaud <contact at restezconnectes.fr> 
 * @since 1.0.0.3
 * @license GPL3 or later
 */

class WPCF7PDF_prepare extends cf7_sendpdf {


    public static function shortcodes($shotcodes_tags='', $contentPdf = '') {
        
        // Shortcodes?
        if( isset($shotcodes_tags) && $shotcodes_tags!='' && isset($contentPdf) && $contentPdf!='') {

            $tagShortcodes = explode(',', esc_html($shotcodes_tags));
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
                            $contentPdf = str_replace('['.$shortcodeName[0].']', do_shortcode('[sc name="'.$shortcodeName[0].'"][/sc]'), $contentPdf);
                        }
                    
                    }
                    
                    if( stripos($contentPdf, '['.$shortcodeName[0].']') !== false ) {
                        $contentPdf = str_replace('['.$shortcodeName[0].']', do_shortcode($tagShortcodes[$i]), $contentPdf);
                    }
                    
                }
            }

        }

        return $contentPdf;

    }

    public static function adjust_image_orientation($filename, $quality = 90) {

        try {
            $exif = @exif_read_data($filename);
        } catch (\Exception $e) {
            $exif = false;
        }

        // If no exif info, or no orientation info, or if orientation needs no adjustment
        if( isset($exif['Orientation']) ) { $orientation = $exif['Orientation']; } else { $orientation = 1; }
        if (!$orientation || $orientation === 1) {
            return false;
        }

        switch ($fileType = @exif_imagetype($filename)) {
            case 1: // gif
                $img = @imageCreateFromGif($filename);
                break;
            case 2: // jpg
                $img = @imageCreateFromJpeg($filename);
                break;
            case 3: // png
                $img = @imageCreateFromPng($filename);
                break;
            default:
                $img = @imagecreatefromjpeg($filename);
        }

        if (!$img) {
            return false;
        }

        $mirror = in_array($orientation, [2, 5, 4, 7]);
        $deg = 0;
        switch ($orientation) {
            case 3:
            case 4:
                $deg = 180;
                break;
            case 6:
            case 5:
                $deg = 270;
                break;
            case 8:
            case 7:
                $deg = 90;
                break;
        }

        if ($deg) {
            $img = imagerotate($img, $deg, 0);
        }

        if ($mirror) {
            $img = imageflip($img, IMG_FLIP_HORIZONTAL);
        }

        switch ($fileType = @exif_imagetype($filename)) {
            case 1: // gif
                imagegif($img, $filename);
                break;
            case 2: // jpg
                imagejpeg($img, $filename, $quality);
                break;
            case 3: // png
                imagepng($img, $filename, $quality);
                break;
            default:
                imagejpeg($img, $filename, $quality);
        }

        return true;
    }

    public static function wpcf7pdf_autorizeHtml() {

        return array(
            'a' => array(
                'href' => array(),
                'title' => array()
                ),
            'br' => array(),
            'p' => array(
                'id' => array(),
                'style' => array(),
                'class' => array()
                ),
            'h1' => array(
                'class' => array(),
                'style' => array(),
            ),
            'h2' => array(
                'class' => array(),
                'style' => array(),
            ), 
            'h3' => array(
                'class' => array(),
                'style' => array(),
            ), 
            'h4' => array(
                'class' => array(),
                'style' => array(),
            ),
            'h5' => array(
                'class' => array(),
                'style' => array(),
            ), 
            'h6' => array(
                'class' => array(),
                'style' => array(),
            ),             
            'em' => array(),
            'i' => array(
                'style' => array(),
                'class' => array()
                ),
            'font-awesome-icon' => array(
                'icon' => array(),
                'class' => array()
                ),
            'strong' => array(),
            'small' => array(),
            'img' => array(
                'id' => array(),
                'src' => array(),
                'title' => array(),
                'width' => array(),
                'height' => array(),
                'style' => array(),
                'rotate' => array(),
                'class' => array()
                ),
            'div' => array(
                'id' => array(),
                'class' => array(),
                'title' => array(),
                'style' => array(),
                'dir' => array()
                ),
            'style' => array(
                'id' => array(),
                'media' => array(),
                'title' => array(),
                'lang' => array(),
                'dir' => array()
            ),
            'bdo' => array(
                'id' => array(),
                'class' => array(),
                'title' => array(),
                'style' => array(),
                'dir' => array()
                ),                
            'span' => array(
                'id' => array(),
                'class' => array(),
                'title' => array(),
                'style' => array(),
                'dir' => array()
                ),
            'table' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'width' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'border' => array()
                ),
            'td' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'width' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'border' => array(),
                'text-rotate' => array(),
                'valign' => array()
                ),
            'tr' => array(
                'id' => array(),
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'width' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'border' => array(),
                'text-rotate' => array(),
                'valign' => array()
                ),
            'th' => array(
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'width' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'text-rotate' => array(),
                'border' => array()
                ),
            'tbody' => array(
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'width' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'text-rotate' => array(),
                'border' => array()
                ),
            'thead' => array(
                'style' => array(),
                'class' => array(),
                'colspan' => array(),
                'rowspan' => array(),
                'width' => array(),
                'cellpadding' => array(),
                'cellspacing' => array(),
                'text-rotate' => array(),
                'border' => array()
                ),
            'barcode' => array(
                'code' => array(),
                'class' => array(),
                'type' => array()
                ),
            'ul' => array(
                'class' => array(),
                ),
            'li' => array(
                'class' => array(),
                ),
            'ol' => array(
                'class' => array(),
                ),
            'b' => array(),
            'blockquote' => array(
                'cite'  => array(),
                ),
            'cite' => array(
                'title' => array(),
                ),
            'code' => array(),
            'del' => array(
                'datetime' => array(),
                'title' => array(),
                ),
                'dd' => array(),
                'dl' => array(),
            'dt' => array(),
            'em' => array(),
            'dl' => array(),
            'dt' => array(),
            'em' => array(),
            'bdi' => array(),
            'textarea' => array(
                'cols' => array(),
                'rows' => array(),
                'name' => array(),
                'style' => array(),
                'class' => array(),
                ),
            'input' => array(
                'type' => array(),
                'value' => array(),
                'name' => array(),
                'style' => array(),
                'class' => array(),
                ),
            /*'html' => array(
                'lang' => array(),
            ),
            
            'meta' => array( 'charset' => array()),
            'title' => array(),
            'body' => array( 'dir' => array()),*/
        );

    }
    
    public static function returndate($id) {

        if (empty($id))
            return;

        $meta_values = get_post_meta(esc_html($id), '_wp_cf7pdf', true);
        // Definition des dates par defaut
        $dateField = date_i18n(esc_html(get_option( 'date_format' )), current_time('timestamp'));
        // On récupere la date et le format
        if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
            $dateField = date_i18n( esc_html($meta_values['date_format']) );
        } else {
            $dateField = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time('timestamp') );
        }        
        return $dateField;
    }

    public static function returntime($id) {

        if (empty($id))
            return;

        $meta_values = get_post_meta(esc_html($id), '_wp_cf7pdf', true);
        // Definition des dates par defaut
        $timeField = date_i18n(esc_html(get_option( 'time_format' )), current_time('timestamp'));
        // On récupere l'heure' et le format
        if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
            $timeField = date_i18n( esc_html($meta_values['time_format']) );
        } else {
            $timeField = date_i18n( get_option( 'time_format' ), current_time('timestamp') );
        }
        return $timeField;
    }

    public static function protect_pdf($id) {

        if (empty($id))
            return;

        $meta_values = get_post_meta(esc_html($id), '_wp_cf7pdf', true);

        $pdfPassword = '--';
        if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) {
            $pdfPassword = $meta_values["protect_password"];
        }
        if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true' && (null!==get_transient('pdf_password') && get_transient('pdf_password')!='') ) {
            $pdfPassword = get_transient('pdf_password');
        }
        if( isset($meta_values["protect_password_tag"]) && $meta_values["protect_password_tag"]!='' ) {
            $pdfPassword = wpcf7_mail_replace_tags($meta_values["protect_password_tag"]);
        }
        return $pdfPassword;
    }

    public static function upload_file($id, $valueTag, $name_tags, $name_tags1, $referenceOfPdf, $content) {

        $upload_dir = wp_upload_dir();
        // On récupère le dossier upload de WP
        $createDirectory = cf7_sendpdf::wpcf7pdf_folder_uploads(esc_html($id));
        $file_location = cf7_sendpdf::wpcf7pdf_attachments($name_tags);

        if( isset($file_location) && exif_imagetype($file_location) !=false ) {
            // remplace le tag d'une image
            $content = str_replace($name_tags, $valueTag, $content);
            // URL de l'image envoyée
            $chemin_initial[$name_tags1] = $createDirectory.'/'.sanitize_text_field($referenceOfPdf).'-'.wpcf7_mail_replace_tags($name_tags);
            // On copie l'image dans le dossier
            copy($file_location, $chemin_initial[$name_tags1]);
            // rotation de l'image si besoin
            $rotate_image[$name_tags1] = self::adjust_image_orientation($chemin_initial[$name_tags1]);
            // retourne l'URL complete du tag 
            $chemin_final[$name_tags1] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $chemin_initial[$name_tags1]);
        } else if( isset($valueTag) && $valueTag!='') {
            // remplace le tag d'une image
            $content = str_replace($name_tags, $valueTag, $content);
            // URL du fichier envoyé
            $uploadingImg[$name_tags1] = $createDirectory.'/'.sanitize_text_field($referenceOfPdf).'-'.wpcf7_mail_replace_tags($name_tags);
            $chemin_final[$name_tags1] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $uploadingImg[$name_tags1]);

        } else {
            // On copie l'image ONE PIXEL dans le dossier si il n'existe pas déjà
            if( file_exists($upload_dir['basedir'] . '/sendpdfcf7_uploads/'.$id.'/onepixel.png')===FALSE) {
                copy(esc_url(WPCF7PDF_URL.'images/onepixel.png'), esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory . '/onepixel.png')));
            }
            $chemin_final[$name_tags1] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory . '/onepixel.png');
        }
        $content = str_replace('[url-'.$name_tags1.']', $chemin_final[$name_tags1], $content);
        return $content;

    }

    public static function tags_parser($id, $nameOfPdf, $referenceOfPdf, $contentPdf, $mailcontent = 0, $preview = 0) {

        if (empty($id))
        return;

        // On récupère le dossier upload de WP
        $upload_dir = wp_upload_dir();
        $createDirectory = cf7_sendpdf::wpcf7pdf_folder_uploads($id);

        $meta_values = get_post_meta(esc_html($id), '_wp_cf7pdf', true);

        // Genere le nom du PDF
        $nameOfPdf = cf7_sendpdf::wpcf7pdf_name_pdf($id);

        // Definition des dates par defaut
        $dateField = self::returndate($id);
        $timeField = self::returntime($id);

        // replace tag by avatar picture
        $user = wp_get_current_user();
        if ( $user ) :
            $contentPdf = str_replace('[avatar]', esc_url( get_avatar_url( $user->ID ) ), $contentPdf);
        endif;
        /**
         * FIN
         */

        // Recupère les dates Tags _format_ potentiels, et change le format définit par l'user
        if ( preg_match_all( '/\[(_format_.*?)\]/',  $contentPdf, $outdate, PREG_PATTERN_ORDER ) ) {

            for ($i = 0; $i < count($outdate[1]); $i++) {

                $dateFormat = str_replace('_format_', '', $outdate[1][$i]);
                $dateFormat = explode('"', $dateFormat);

                if ( isset($preview) && ($preview == 1 || $preview == 2) ) {
                    $date = gmdate("d-m-Y");
                    $formatDate = new DateTime($date);
                    $contentPdf = str_replace('['.$outdate[1][$i].']', $formatDate->format($dateFormat[1]), $contentPdf);
                } else {                
                    $dateValue = wpcf7_mail_replace_tags(esc_html('['.trim($dateFormat[0]).']'));
                    $formatDate = new DateTime($dateValue);
                    $contentPdf = str_replace('['.$outdate[1][$i].']', $formatDate->format($dateFormat[1]), $contentPdf);
                }
            }
            
        }

        // Si on trouve des shortcodes de prix
        if ( preg_match_all( '/\[(_price.*?)\]/',  $contentPdf, $outprice, PREG_PATTERN_ORDER ) ) {

            for ($i = 0; $i < count($outprice[1]); $i++) {
                
                // On separe les données
                $price = explode('|', $outprice[1][$i]);

                if ( isset($preview) && ($preview == 1 || $preview == 2) ) {
                    $valueprice = 25000;                  
                } else {
                    $valueprice = wpcf7_mail_replace_tags(esc_html('['.$price[1].']'));
                }
                // Nom du tag $price[1] / Decimals : $price[2] / Decimal_separator : $price[3].' / Thousands_separator : $price[4]
                if( isset($valueprice) && $valueprice>0 ) {
                    $formatPrice = number_format($valueprice, $price[2], $price[3], $price[4]);
                    $contentPdf = str_replace('['.$outprice[1][$i].']', $formatPrice, $contentPdf);
                } else {
                    $contentPdf = str_replace('['.$outprice[1][$i].']', '', $contentPdf);
                }
            }

        }
        
        if ( isset($preview) && ($preview == 1 || $preview == 2) ) {
            $referenceOfPdf = uniqid();
        }
        // Remplace le tag reference
        $contentPdf = str_replace('[reference]', wp_kses_post($referenceOfPdf), $contentPdf);
        // Remplace le tag URL-PDF
        $contentPdf = str_replace('[url-pdf]', esc_url($upload_dir['url'].'/'.$nameOfPdf.'-'.wp_kses_post($referenceOfPdf).'.pdf'), $contentPdf);
        if ( isset($preview) && ($preview == 1 || $preview == 2) ) {
            // Remplace le tag ID
            $contentPdf = str_replace('[ID]', '000'.gmdate('md'), $contentPdf);
            if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {
                $contentPdf = str_replace('[your-name]', '<input type="text" class="wpcf7-text" size="80" name="your-name" value="Doe" />', $contentPdf);
            } else {
                $contentPdf = str_replace('[your-name]', 'Doe', $contentPdf);
            }
            if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {
                $contentPdf = str_replace('[your-firstname]', '<input type="text" class="wpcf7-text" size="80" name="your-firstname" value="John" />', $contentPdf);
            } else {
                $contentPdf = str_replace('[your-firstname]', 'John', $contentPdf);
            }
            if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {
                $contentPdf = str_replace('[your-email]', '<input type="text" class="wpcf7-text" size="80" name="your-email" value="johndoe@nowhere.com" />', $contentPdf);
            } else {
                $contentPdf = str_replace('[your-email]', 'John', $contentPdf);
            }
            if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {
                $contentPdf = str_replace('[your-subject]', '<input type="text" class="wpcf7-text" name="your-subject" size="80" value="'.__("This is a subject test!", 'send-pdf-for-contact-form-7').'" />', $contentPdf);
            } else {
                $contentPdf = str_replace('[your-subject]', __("This is a subject test!", 'send-pdf-for-contact-form-7'), $contentPdf);
            }
            if (isset($meta_values['data_textarea']) && $meta_values['data_textarea']== 'true') {
                $contentPdf = str_replace('[your-message]', '<textarea cols="100%" rows="20" class="wpcf7-textarea" name="your-message">'.__("I did not understand at first for what it was intended, but it appeared. Great!", 'send-pdf-for-contact-form-7').'</textarea>', $contentPdf);
            } else {
                $contentPdf = str_replace('[your-message]', __("I did not understand at first for what it was intended, but it appeared. Great!", 'send-pdf-for-contact-form-7'), $contentPdf);
            }
        }
        // Remplace les tags date et time
        $contentPdf = str_replace('[date]', $dateField, $contentPdf);
        $contentPdf = str_replace('[time]', $timeField, $contentPdf);        

        // On gère les séparateurs avant et après les balise checkbox et radio
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

        // Si option fillable, on genere les champs et remplace les données                   
        $contact_form = WPCF7_ContactForm::get_instance(esc_html($id));           
        $contact_tag = $contact_form->scan_form_tags();

        // Si le champ est checkbox ou radio en RAW
        $contentPdfTagsRaw = cf7_sendpdf::wpcf7pdf_mailparser($contentPdf, 'raw');
        foreach ( (array) $contentPdfTagsRaw as $name_raw ) {

            $name1raw = str_replace('_raw_', '', $name_raw);
            $found_key = cf7_sendpdf::wpcf7pdf_foundkey($contact_tag, $name1raw);
            $baseTypeRaw = $contact_tag[$found_key]['basetype'];

            if( isset($baseTypeRaw) && ($baseTypeRaw==='checkbox' || $baseTypeRaw==='radio') ) {

                // Exemple : CEO | sales@example.com
                // on remplace _raw_TAG par l'avant PIPE soit CEO
                $rawValue = wpcf7_mail_replace_tags(esc_html('['.$name_raw.']'));                        
                $contentPdf = str_replace(esc_html('['.$name_raw.']'), $rawValue, $contentPdf);

                // on remplace TAG du raw par la valeur d'après PIPE soit sales@example.com                        
                $raw1Value = wpcf7_mail_replace_tags(esc_html('['.$name1raw.']'));
                $contentPdf = str_replace(esc_html('['.$name1raw.']'), $raw1Value, $contentPdf);
            }
        }

        // On parse le content pour extraire les types de champ
        $contentPdfTags = cf7_sendpdf::wpcf7pdf_mailparser($contentPdf);
        foreach ( (array) $contentPdfTags as $name_tags ) {

            $name_tags[1] = str_replace('url-', '', $name_tags[1]);
            $name_tags[0] = str_replace('url-', '', $name_tags[0]);
            $found_key = cf7_sendpdf::wpcf7pdf_foundkey($contact_tag, $name_tags[1]);
            $basetype = $contact_tag[$found_key]['basetype'];
            $tagOptions = array();
            if( isset( $contact_tag[$found_key]['options'] ) && !empty($contact_tag[$found_key]['options']) ) {
                $tagOptions = $contact_tag[$found_key]['options'];
            }

            /**
             *  Si le champ est un type file et image
             */
            if( isset($basetype) && $basetype==='file' ) {

                if ( isset($preview) && ($preview == 1 || $preview == 2) ) {
                    preg_match_all('/<img[^>]+>/i', $contentPdf, $imgTags);
                    for ($i = 0; $i < count($imgTags[0]); $i++) {
                        // get the source string
                        preg_match('/src="([^"]+)/i', $imgTags[0][$i], $imageTag);
                        // remove opening 'src=' tag, can`t get the regex right
                        $origImageSrc = str_ireplace( 'src="', '',  $imageTag[0]);              
                        $contentPdf = str_replace( $origImageSrc, WPCF7PDF_URL.'images/temporary-image.jpg', $contentPdf);
                    }
                    $contentPdf = str_replace(esc_html($name_tags[0]), '<img src="'.WPCF7PDF_URL.'images/temporary-image.jpg" width="300" height="300" />', $contentPdf);
                } else {                    
                    $valueTag = wpcf7_mail_replace_tags($name_tags[0]);
                    $contentPdf = self::upload_file($id, $valueTag, $name_tags[0], $name_tags[1], $referenceOfPdf, $contentPdf);                    
                }

            /**
             *  Si le champ est un type textarea
             */
            } else if(isset($basetype) && $basetype==='textarea') {

                $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));
                $emptyTextareaInput = 0;                
                if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') && $valueTag=='' ) {
                    $emptyTextareaInput = 1;
                }
                // Si le contenu du PDF doit rester en brut et pas en HTML
                if( isset($meta_values["linebreak"]) && $meta_values['linebreak'] == 'false' ) {
                    $valueTag = str_replace("\r\n", "<br />", $valueTag);
                } else if( $mailcontent==1 && (isset($meta_values["disable-html"]) && $meta_values['disable-html'] == 'false') ) {      
                    $valueTag = str_replace("\r\n", "<br />", $valueTag);
                } 
                if( $emptyTextareaInput == 0 ) {
                    if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {                    
                        $contentPdf = str_replace(esc_html($name_tags[0]), '<textarea cols="40" rows="10" name="'.$name_tags[1].'">'.$valueTag.'</textarea>', $contentPdf);        
                    } else {                    
                        $contentPdf = str_replace(esc_html($name_tags[0]), $valueTag, $contentPdf);
                    }
                } else if( $emptyTextareaInput == 1 ) {
                    $contentPdf = str_replace(esc_html($name_tags[0]), '', $contentPdf);
                }
            
            /**
             *  Si le champ est un type select
             */
            } else if( isset($basetype) && $basetype==='select' ) {

                $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));
                if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') && ($valueTag=='' || $valueTag=='#') ) {
                    $contentPdf = str_replace(esc_html($name_tags[0]), '', $contentPdf);
                } else {
                    $contentPdf = str_replace(esc_html($name_tags[0]), $valueTag, $contentPdf);
                }

            /**
             *  Si le champ est un type text
             *  On retire si c'est le shortocode de test et si c'est un codebarre
             */
            } else if( ( isset($basetype) && $basetype==='text' ) && ( isset($name_tags[1]) && $name_tags[1]!='0-9') && ( isset($name_tags[1]) && $name_tags[1]!='addpage' )  && (isset($name_tags[1]) && $name_tags[1]!='wpcf7pdf_test') ) {

                $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));               
                if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') && $valueTag=='' ) {
                    $contentPdf = str_replace(esc_html($name_tags[0]), '', $contentPdf);
                } else {
                    if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {                        
                        $contentPdf = str_replace(esc_html($name_tags[0]), '<input type="text" name="'.$name_tags[1].'" value="'.esc_html($valueTag).'">', $contentPdf);                        
                    } else {
                        $contentPdf = str_replace(esc_html($name_tags[0]), esc_html($valueTag), $contentPdf);
                    } 
                }

            /**
             *  Si le champ est un type checkbox
             */
            } else if( isset($basetype) && $basetype==='checkbox' ) {

                $inputCheckbox = '';
                $i = 1;
                               
                foreach( $contact_tag[$found_key]['values'] as $idCheckbox=>$valCheckbox ) {
                    
                    $caseChecked = '';
                    $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));
                    $emptyCheckInput = 0;

                    if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {

                        // Si le tag est exclusive
                        if( in_array('exclusive', $tagOptions) ) {  
                            if( sanitize_text_field($valueTag)===sanitize_text_field($valCheckbox) ) {
                                $caseChecked = 'checked="checked"';
                            } else if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') && $valueTag=='' ) {
                                $emptyCheckInput = 1;
                            }

                        } else {
                            if( strpos($valueTag, trim($valCheckbox) )!== false ){
                                $caseChecked = 'checked="checked"';
                            } else if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') && $valueTag=='' ) {
                                $emptyCheckInput = 1;
                            }
                        }

                        if( in_array('label_first', $tagOptions) ) {
                            if( $emptyCheckInput == 0 ) {
                                $inputCheckbox .= ''.$tagSeparate.''.esc_html($valCheckbox).' <input type="checkbox" class="wpcf7-checkbox" name="'.esc_html($name_tags[1].$idCheckbox).'" value="'.$i.'" '.$caseChecked.' />'.$tagSeparateAfter.'';
                            }                            
                        } else {
                            if( $emptyCheckInput == 0 ) {
                                $inputCheckbox .= ''.$tagSeparate.'<input type="checkbox" class="wpcf7-checkbox" name="'.esc_html($name_tags[1].$idCheckbox).'" value="'.$i.'" '.$caseChecked.'/> '.esc_html($valCheckbox).''.$tagSeparateAfter.'';
                            }
                        }

                    } else {

                        if( in_array('exclusive', $tagOptions) ) { 

                            if( in_array('free_text', $tagOptions) && ( isset($_POST['_wpcf7_free_text_'.$name_tags[1]]) && $_POST['_wpcf7_free_text_'.$name_tags[1]]!='') ) {

                                if( sanitize_title($valueTag)===sanitize_title(wpcf7_mail_replace_tags($name_tags[0])) ) {

                                    if( $emptyCheckInput == 1 ) {
                                        $inputCheckbox .= '';
                                    } else {
                                        $contentPdf = str_replace('[free_text_'.esc_html($name_tags[1].']'), esc_html($_POST['_wpcf7_free_text_'.$name_tags[1]]), $contentPdf);
                                        $inputCheckbox = ''.$tagSeparate.''.esc_html($valueTag).''.$tagSeparateAfter.'';
                                    }
                                }
    
                            } else if( sanitize_title($valueTag)===sanitize_title($valCheckbox) ) {  

                                if( $emptyCheckInput == 1 ) {                                 
                                    $inputCheckbox .= '';
                                } else {
                                    $inputCheckbox .= ''.$tagSeparate.''.$valCheckbox.''.$tagSeparateAfter.'';
                                }
                            }

                        } else {

                            if( strpos($valueTag, trim($valCheckbox) )!== false ) {
                                if( $emptyCheckInput == 1 ) {
                                    $inputCheckbox .= '';
                                } else {
                                    if( in_array('free_text', $tagOptions) && ( isset($_POST['_wpcf7_free_text_'.$name_tags[1]]) && $_POST['_wpcf7_free_text_'.$name_tags[1]]!='') ) {
                                        $contentPdf = str_replace('free_text_'.esc_html($name_tags[1]), esc_html($_POST['_wpcf7_free_text_'.$name_tags[1]]), $contentPdf);
                                        $inputCheckbox = ''.$tagSeparate.''.esc_html($valueTag).''.$tagSeparateAfter.'';
                                    } else {
                                        $inputCheckbox .= ''.$tagSeparate.''.$valCheckbox.''.$tagSeparateAfter.'';
                                    }
                                }
                            }
                        }

                    } 
                    $i++;

                }
                $contentPdf = str_replace(esc_html($name_tags[0]), $inputCheckbox, $contentPdf);
             
            /**
             *  Si le champ est un type radio
             */
            } else if( isset($basetype) && $basetype==='radio' ) {

                $inputRadio = '';

                foreach( $contact_tag[$found_key]['values'] as $idRadio=>$valRadio ) {
                    
                    $radioChecked = '';
                    $valueRadioTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));
                    $emptyRadioInput = 0;
                    
                    if(isset($meta_values['data_input']) && $meta_values['data_input']=='true') {

                        if( sanitize_text_field($valueRadioTag)===sanitize_text_field($valRadio) ) {
                            $radioChecked = ' checked="yes"';
                        } else if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') && $valueRadioTag=='' ) {
                            $emptyRadioInput = 1;
                        }
                    
                        if(in_array('label_first', $tagOptions) ) {
                            if( $emptyRadioInput == 0 ) {
                                $inputRadio .= ''.$tagSeparate.''.$valRadio.' <input type="radio" class="wpcf7-radio" name="'.esc_html($name_tags[1]).'" value="'.$idRadio.'" '.$radioChecked.' >'.$tagSeparateAfter.'';
                            }
                        } else {
                            if( $emptyRadioInput == 0 ) {
                                $inputRadio .= ''.$tagSeparate.'<input type="radio" class="wpcf7-radio" name="'.esc_html($name_tags[1]).'" value="'.$idRadio.'" '.$radioChecked.' > '.$valRadio.''.$tagSeparateAfter.'';
                            }
                        }

                    } else {

                        if( in_array('exclusive', $tagOptions) ) { 

                            if( in_array('free_text', $tagOptions) && ( isset($_POST['_wpcf7_free_text_'.$name_tags[1]]) && $_POST['_wpcf7_free_text_'.$name_tags[1]]!='') ) {

                                if( sanitize_title($valueRadioTag)===sanitize_title(wpcf7_mail_replace_tags($name_tags[0])) ) {

                                    if( $emptyRadioInput == 1 ) {
                                        $inputRadio .= '';
                                    } else {
                                        $contentPdf = str_replace('[free_text_'.esc_html($name_tags[1].']'), esc_html($_POST['_wpcf7_free_text_'.$name_tags[1]]), $contentPdf);
                                        $inputRadio = ''.$tagSeparate.''.esc_html($valueRadioTag).''.$tagSeparateAfter.'';
                                    }
                                }
    
                            } else if( sanitize_title($valueRadioTag)===sanitize_title($valRadio) ) {  

                                if( $emptyRadioInput == 1 ) {                                 
                                    $inputRadio .= '';
                                } else {
                                    $inputRadio .= ''.$tagSeparate.''.$valRadio.''.$tagSeparateAfter.'';
                                }
                            }

                        } else {

                            if( $emptyRadioInput == 1 ) {                                 
                                $inputRadio .= '';
                            } else {
                                if( in_array('free_text', $tagOptions) && ( isset($_POST['_wpcf7_free_text_'.$name_tags[1]]) && $_POST['_wpcf7_free_text_'.$name_tags[1]]!='') ) {
                                    $contentPdf = str_replace('free_text_'.esc_html($name_tags[1]), esc_html($_POST['_wpcf7_free_text_'.$name_tags[1]]), $contentPdf);
                                    $inputRadio = ''.$tagSeparate.''.esc_html($valueRadioTag).''.$tagSeparateAfter.'';
                                } else if( sanitize_text_field($valueRadioTag)===sanitize_text_field($valRadio) ) {
                                    if( $emptyRadioInput == 0 ) {                                 
                                        $inputRadio .= ''.$tagSeparate.''.$valRadio.''.$tagSeparateAfter.'';
                                    }
                                }
                            }
                            
                        }
                    }
                }
                $contentPdf = str_replace(esc_html($name_tags[0]), $inputRadio, $contentPdf);

            } else {
                
                $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));                            
                $contentPdf = str_replace(esc_html($name_tags[0]), esc_html($valueTag), $contentPdf);
            }
        }

        // Si le contenu du PDF doit rester en brut et pas en HTML
        /*if( isset($meta_values["linebreak"]) && $meta_values['linebreak'] == 'false' ) {
            $contentPdf = str_replace("\r\n", "<br />", $contentPdf);
        } else if( $mailcontent==1 && (isset($meta_values["disable-html"]) && $meta_values['disable-html'] == 'false') ) {      
            $contentPdf = str_replace("\r\n", "<br />", $contentPdf);
        }*/

        return $contentPdf;
    }


}