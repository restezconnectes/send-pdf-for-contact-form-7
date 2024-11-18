<?php


defined( 'ABSPATH' )
	or die( 'No direct load ! ' );


/**
 * Generate PDF Class for Contact Form 7.
 *
 * @link https://madeby.restezconnectes.fr/project/send-pdf-for-contact-form-7/
 * @author Florent Maillefaud <contact at restezconnectes.fr> 
 * @since 1.0.0.3
 * @license GPL3 or later
 */

class WPCF7PDF_generate extends cf7_sendpdf {

    static function wpcf7pdf_create_pdf($idForm, $data, $nameOfPdf, $referenceOfPdf, $createDirectory, $preview = 0) {

        // nothing's here... do nothing...
        if (empty($idForm) || empty($data))
            return;

        global $wp_session;

        $upload_dir = wp_upload_dir();
        $custom_tmp_path = get_option('wpcf7pdf_path_temp');

        $contact_form = WPCF7_ContactForm::get_instance(esc_html($idForm));   

        // Definition des dates par defaut
        $dateField = WPCF7PDF_prepare::returndate($idForm);
        $timeField = WPCF7PDF_prepare::returntime($idForm);

        // Definition des marges par defaut
        $marginHeader = 10;
        $marginTop = 40;
        $marginBottomHeader = 10;
        $marginLeft = 15;
        $marginRight = 15;

        // On va chercher les paramètres
        $meta_values = get_post_meta(esc_html($idForm), '_wp_cf7pdf', true);

        if( $referenceOfPdf=='' && ($preview==1 or $preview==2) ) { $referenceOfPdf = '3F7A8B43EA2F'; }

        require WPCF7PDF_DIR . 'mpdf/vendor/autoload.php';

        if( isset($meta_values['pdf-font'])  ) {
            $fontPdf = esc_html($meta_values['pdf-font']);
        }
        if( isset($meta_values['pdf-fontsize']) && is_numeric($meta_values['pdf-fontsize']) ) {
            $fontsizePdf = esc_html($meta_values['pdf-fontsize']);
        }
        
        if( isset($meta_values["margin_header"]) && $meta_values["margin_header"]!='' ) { $marginHeader = esc_html($meta_values["margin_header"]); }
        if( isset($meta_values["margin_top"]) && $meta_values["margin_top"]!='' ) { $marginTop = esc_html($meta_values["margin_top"]); }
        if( isset($meta_values["margin_left"]) && $meta_values["margin_left"]!='' ) { $marginLeft = esc_html($meta_values["margin_left"]); }
        if( isset($meta_values["margin_right"]) && $meta_values["margin_right"]!='' ) { $marginRight = esc_html($meta_values["margin_right"]); }

        $setDirectionality = 'ltr';
        if( isset($meta_values["set_directionality"]) && $meta_values["set_directionality"]!='' ) {  $setDirectionality = esc_html($meta_values["set_directionality"]);  }

        // Adding custom font
        $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];
        
        $folderFont = '';
        if ( is_dir(get_stylesheet_directory()."/pdffonts/") == true ) {

            $folderFont = get_stylesheet_directory()."/pdffonts/";
            $dossier = new DirectoryIterator(get_stylesheet_directory()."/pdffonts/");
            foreach($dossier as $fichier){
            
                // si c'est pas un "." ni ".."
                if($fichier->isDot())
                    continue; // "continue" permet de passer à l'itération suivante

                //si c'est pas un fichier
                if($fichier->getType() != 'file')
                    continue;
            
                // continue;
                if(preg_match("#\.(ttf)$#i", $fichier)){
                    //on fusionne les fonts avec ceux définis dans FontVariables.php
                    $addFontData = array(
                        ''.sanitize_title(substr($fichier->getFilename(), 0, -4)).'' => array(
                            'R' => ''.$fichier->getFilename().'',
                            ),
                        );
                    $fontData = array_merge($fontData, $addFontData);
                }
            }
        }

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
                'tempDir' => $custom_tmp_path,
                'fontDir' => array_merge($fontDirs, [$folderFont]),
                'fontdata' => $fontData,
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
                'fontDir' => array_merge($fontDirs, [$folderFont]),
                'fontdata' => $fontData + [
                    'croissantOne-regular' => [
                        'R' => 'CroissantOne-Regular.ttf',
                    ],
                    'sedgwickavedisplay-regular' => [
                        'R' => 'SedgwickAveDisplay-Regular.ttf',
                    ],
                ],
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
                'fontDir' => array_merge($fontDirs, [$folderFont]),
                'fontdata' => $fontData + [
                    'croissantOne-regular' => [
                        'R' => 'CroissantOne-Regular.ttf',
                    ],
                    'sedgwickavedisplay-regular' => [
                        'R' => 'SedgwickAveDisplay-Regular.ttf',
                    ],
                ],
            );

        }

        $mpdf = new \Mpdf\Mpdf($mpdfConfig);
        $mpdf->autoScriptToLang = true;
        $mpdf->baseScript = 1;
        $mpdf->autoVietnamese = true;
        $mpdf->autoArabic = true;
        $mpdf->autoLangToFont = true;                    
        $mpdf->SetTitle(get_the_title(esc_html($idForm)));
        $mpdf->SetCreator(get_bloginfo('name'));
        $mpdf->SetDirectionality($setDirectionality);
        $mpdf->ignore_invalid_utf8 = true;
        if( empty($meta_values["page_header"]) || (isset($meta_values["page_header"]) && $meta_values["page_header"]==0)) {
        $mpdf->mirrorMargins = 1;
        }

        $mpdfCharset = 'utf-8';
        if( isset($meta_values["charset"]) && $meta_values["charset"]!='utf-8' ) {
            $mpdfCharset = esc_html($meta_values["charset"]);
        }
        $mpdf->allow_charset_conversion=true;  // Set by default to TRUE
        $mpdf->charset_in=''.$mpdfCharset.'';
        
        if( empty($meta_values["margin_auto_header"]) || ( isset($meta_values["margin_auto_header"]) && $meta_values["margin_auto_header"]=='' ) ) { $meta_values["margin_auto_header"] = 'stretch'; }
        if( empty($meta_values["margin_auto_header"]) || ( isset($meta_values["margin_auto_bottom"]) && $meta_values["margin_auto_bottom"]=='' ) ) { $meta_values["margin_auto_bottom"] = 'stretch'; }

        $mpdf->setAutoTopMargin = esc_html($meta_values["margin_auto_header"]);
        $mpdf->setAutoBottomMargin = esc_html($meta_values["margin_auto_bottom"]);

        if( isset($meta_values['fillable_data']) && $meta_values['fillable_data']== 'true') {
            $mpdf->useActiveForms = true;
        }
        
        if( isset($meta_values['image_background']) && $meta_values['image_background']!='' ) {
            $mpdf->SetDefaultBodyCSS('background', "url('".esc_url($meta_values['image_background'])."')");
            $mpdf->SetDefaultBodyCSS('background-image-resize', 6);
        }
        
        // Set HTML headers/footers before writing to the document
        if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
            $footerText = wp_kses(trim($meta_values['footer_generate_pdf']), WPCF7PDF_prepare::wpcf7pdf_autorizeHtml());
            $footerText = str_replace('[reference]', sanitize_text_field($referenceOfPdf), $footerText);
            $footerText = str_replace('[url-pdf]', esc_url($upload_dir['url'].'/'.$nameOfPdf.'.pdf'), $footerText);
            $footerText = str_replace('[date]', $dateField, $footerText);
            $footerText = str_replace('[time]', $timeField, $footerText);
            $mpdf->SetHTMLFooter($footerText, 'E');
        }

        // LOAD a stylesheet
        if( isset($meta_values['stylesheet']) && $meta_values['stylesheet']!='' ) {
            // Verifie extension .css
            $extension = strtolower(pathinfo($meta_values['stylesheet'], PATHINFO_EXTENSION));
            if( isset($extension) && $extension == 'css' ) {
                $stylesheet = wp_remote_get(esc_url($meta_values['stylesheet']));
                $mpdf->WriteHTML($stylesheet['body'],1);	// The parameter 1 tells that this is css/style only and no body/html/text
            }
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
                list($width, $height, $type, $attr) = getimagesize(esc_url($meta_values["image"]));
            } else {
                $width = 150;
                $height = 80;
            }
            $imgAlign = 'left';
            if( isset($meta_values['image-alignment']) ) {
                $imgAlign = esc_html($meta_values['image-alignment']);
            }
            if( empty($meta_values['image-width']) ) { $imgWidth = $width; } else { $imgWidth = esc_html($meta_values['image-width']);  }
            if( empty($meta_values['image-height']) ) { $imgHeight = $height; } else { $imgHeight = esc_html($meta_values['image-height']);  }

            $attribut = 'width='.$imgWidth.' height="'.$imgHeight.'"';
            $entetePage = '<div style="text-align:'.$imgAlign.';height:'.$imgHeight.'"><img src="'.esc_url($meta_values["image"]).'" '.$attribut.' /></div>';

            if( isset($meta_values["margin_bottom_header"]) && $meta_values["margin_bottom_header"]!='' ) { $marginBottomHeader = esc_html($meta_values["margin_bottom_header"]); }
            $mpdf->WriteHTML('<p style="margin-bottom:'.$marginBottomHeader.'px;">&nbsp;</p>');
        }

        
        // En cas de saut de page avec le tag [addpage]
        if( isset($data) && stripos($data, '[addpage]') !== false ) {

            $newPage = explode('[addpage]', $data);
            $countPage = count($newPage);

            for($i = 0; $i < ($countPage);  $i++) {
                
                // On print les pages trouvées
                if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                    $mpdf->SetHTMLHeader($entetePage, '', true);
                } else if ($i == 0) {
                    $mpdf->SetHTMLHeader($entetePage, '', true);
                } else {
                    $mpdf->SetHTMLHeader('&nbsp;', '', true);                        
                } 
                if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                    $mpdf->SetHTMLFooter($footerText);
                }
                $mpdf->WriteHTML($newPage[$i]);
                
                if( $i < ($countPage-1) ) {
                    $mpdf->AddPage('','','','','',15,15,15,15,5,5);
                }

            }

        } else {

            $mpdf->SetHTMLHeader($entetePage, '', true);
            if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                $mpdf->SetHTMLHeader($entetePage, 'O', true);
            } else {
                $mpdf->SetHTMLHeader();
            }
            if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                $mpdf->SetHTMLFooter($footerText);
            }
            $data = apply_filters('wpcf7pdf_text', $data, $contact_form);
            $mpdf->WriteHTML($data);

        }
        
        // Option for Protect PDF by Password
        if ( isset($meta_values["protect"]) && $meta_values["protect"]=='true') {
            $pdfPassword = WPCF7PDF_prepare::protect_pdf($idForm);
            $mpdf->SetProtection(array('print','fill-forms'), $pdfPassword, $pdfPassword, 128);             
        } 

        // Si je suis dans l'admin je génère un preview 
        if ( isset($preview) && $preview == 1 ) {

            $mpdf->Output($createDirectory.'/preview-'.esc_html($idForm).'.pdf', 'F');

        // et des preview pour les autres PDF si existant
        } elseif (  isset($preview) && $preview == 2  ) {

            $mpdf->Output($createDirectory.'/preview-'.esc_html($nameOfPdf).'-'.esc_html($idForm).'.pdf', 'F');

        // Sinon on génère les PDF
        } else {

            $data = wpcf7_mail_replace_tags( wpautop($data) );
            $mpdf->Output($createDirectory.'/'.esc_html($nameOfPdf).'.pdf', 'F');
            
            // Je copy le PDF genere
            if( file_exists($createDirectory.'/'.esc_html($nameOfPdf).'.pdf') ) {
                copy($createDirectory.'/'.esc_html($nameOfPdf).'.pdf', $createDirectory.'/'.esc_html($nameOfPdf).'-'.$referenceOfPdf.'.pdf');
            }
        }

    }

    static function wpcf7pdf_create_csv($idForm, $nameOfPdf, $referenceOfPdf, $createDirectory, $preview = 0) {

        // nothing's here... do nothing...
        if (empty($idForm))
            return;

        if( empty($referenceOfPdf) || $referenceOfPdf=='' && $preview==1 ) { $referenceOfPdf = '3F7A8B43EA2F'; }

        // Je vais chercher le tableau des tags
        $csvTab = cf7_sendpdf::wpf7pdf_tagsparser($idForm, $referenceOfPdf);

        // On va chercher les paramètres
        $meta_values = get_post_meta(esc_html($idForm), '_wp_cf7pdf', true);
        
        // Je vais chercher la liste des tags pour l'entete du CSV
        $meta_fields = get_post_meta(esc_html($idForm), '_wp_cf7pdf_fields', true);
        
        // On va chercher les noms personnalisé
        $meta_tagsname = get_post_meta(esc_html($idForm), '_wp_cf7pdf_customtagsname', true);

        // Construction de l'entete
        if( isset($meta_fields) ) {
            
            if( isset($meta_tagsname) && (isset($meta_tagsname['reference']) && $meta_tagsname['reference']!='') ) { 
                $tag_reference = esc_html($meta_tagsname['reference']); 
            } else { 
                $tag_reference = "Reference"; 
            }
            if( isset($meta_tagsname) && (isset($meta_tagsname['date']) && $meta_tagsname['date']!='') ) { 
                $tag_date = esc_html($meta_tagsname['date']); 
            } else { 
                $tag_date = "Date";
            }
            $entete = array( esc_html($tag_reference), esc_html($tag_date) );
            
            foreach($meta_fields as $field) {
                preg_match_all( '#\[(.*?)\]#', $field, $nameField );
                $nb=count($nameField[1]);
                for($i=0;$i<$nb;$i++) {    

                    $hiddenTag = 'hidden-'.esc_html($nameField[1][$i]);

                    // si on cache des champs, on les retire de l'entete
                    if( isset($meta_tagsname) && (isset($meta_tagsname[$nameField[1][$i]]) ) ) {

                        if( isset($meta_tagsname[$hiddenTag]) && $meta_tagsname[$hiddenTag]==1 ) {
                            $tagsName = ''; // si champ caché = tableau vide                          
                        } else if ($meta_tagsname[$nameField[1][$i]]!='') {
                            $tagsName = esc_html($meta_tagsname[$nameField[1][$i]]);
                        }

                    } else {                        
                        $tagsName = esc_html($nameField[1][$i]);
                    }
                    if( isset($tagsName) && $tagsName!='') {
                        array_push($entete, $tagsName);
                    }
                }
            }
        }

        $csvlist = array (
            $entete,
            $csvTab
        );

        if( isset($preview) && $preview == 1 ) {
            $fpCsv = fopen($createDirectory.'/preview-'.esc_html($idForm).'.csv', 'w+'); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen */
        } else {
            $fpCsv = fopen($createDirectory.'/'.$nameOfPdf.'.csv', 'w+'); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen */
        }
        //add BOM to fix UTF-8 in Excel
        fputs($fpCsv, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));

        // On va chercher le séparateur défini en paramètres
        if( isset($meta_values["csv-separate"]) && !empty($meta_values["csv-separate"]) ) { $csvSeparate = esc_html($meta_values["csv-separate"]); } else { $csvSeparate = ','; }
        
        foreach ($csvlist as $csvfields) {
            $csvfields = str_replace("<br />", " ", $csvfields);
            $csvfields = str_replace("\r\n", " ", $csvfields);
            fputcsv($fpCsv, $csvfields, $csvSeparate); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fputs */
        }
        fclose($fpCsv); /* phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose */

        if( isset($preview) && $preview == 0 ) {
            // Je copy le CSV genere
            if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                copy($createDirectory.'/'.$nameOfPdf.'.csv', $createDirectory.'/'.$nameOfPdf.'-'.$referenceOfPdf.'.csv');
            }
        }
        // END GENERATE CSV

    }
}