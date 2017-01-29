<?php

class cf7_sendpdf {
    
	public function hooks() {
     
        /* Version du plugin */
        $option['wpcf7pdf_version'] = WPCF7PDF_VERSION;
        if( !get_option('wpcf7pdf_version') ) {
            add_option('wpcf7pdf_version', $option);
        } else if ( get_option('wpcf7pdf_version') != WPCF7PDF_VERSION ) {
            update_option('wpcf7pdf_version', WPCF7PDF_VERSION);
        }
        // Maybe disable AJAX requests
        add_filter( 'wpcf7_mail_components', array( $this, 'wpcf7pdf_mail_components' ), 10, 3 );
        add_action( 'wpcf7_mail_sent', array( $this, 'wpcf7pdf_after_mail_actions' ), 10, 1 );
        add_action( 'admin_menu', array( $this, 'wpcf7pdf_add_admin') );
        //add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), array( $this, 'wpcf7pdf_plugin_actions' ), 10, 2 );
        add_filter( 'plugin_action_links', array( $this, 'wpcf7pdf_plugin_actions'), 10, 2 );
        add_action( 'init', array( $this, 'wpcf7pdf_session_start') );
        add_action('admin_head', array( $this, 'wpcf7pdf_admin_head') );
        //add_action( 'admin_init', array( $this, 'wpcf7_export_csv') );
        add_action( 'wpcf7_before_send_mail', array( $this, 'wpcf7pdf_send_pdf' ) );

        register_deactivation_hook(__FILE__, 'wpcf7pdf_uninstall');
        
        if( isset($_GET['csv']) && intval($_GET['csv']) && $_GET['csv']==1 && (isset($_GET['csv_security']) || wp_verify_nonce($_GET['csv_security'], 'go_generate')) ) {
			$csv = $this->wpcf7_export_csv( intval($_GET['idform']) );

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"sendpdfcf7_export_".$_GET['idform'].".csv\";" );
			header("Content-Transfer-Encoding: binary");

			echo $csv;
			exit;
		}
        
    }
    
    // Add "Réglages" link on plugins page
    function wpcf7pdf_plugin_actions( $links, $file ) {
        //$settings_link = '<a href="admin.php?page=wpcf7-send-pdf">'.__('Settings', 'send-pdf-for-contact-form-7').'</a>';
        //return array_merge( $links, $settings_link );
        if ( $file != WPCF7PDF_PLUGIN_BASENAME ) {
		  return $links;
        } else {
            $settings_link = '<a href="admin.php?page=wpcf7-send-pdf">'
                . esc_html( __( 'Settings', 'send-pdf-for-contact-form-7' ) ) . '</a>';

            array_unshift( $links, $settings_link );

            return $links;
        }
    }
    
    function wpcf7pdf_dashboard_html_page() {
        include(WPCF7PDF_DIR."/views/send-pdf-admin.php");
    }
    
    /* Ajout feuille CSS pour l'admin barre */
    function wpcf7pdf_admin_head() {
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {
            echo '<link rel="stylesheet" type="text/css" media="all" href="' .WP_PLUGIN_URL.'/send-pdf-for-contact-form-7/css/wpcf7-admin.css">';
        }
    }
        
    function wpcf7pdf_add_admin() {
    
        $addPDF = add_submenu_page( 'wpcf7',
		__('Options for CF7 Send PDF', 'send-pdf-for-contact-form-7'),
		__('Send PDF with CF7', 'send-pdf-for-contact-form-7'),
		'administrator', 'wpcf7-send-pdf',
		array( $this, 'wpcf7pdf_dashboard_html_page') );
        
        // If you're not including an image upload then you can leave this function call out
        
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {
                
            wp_enqueue_media();

            wp_enqueue_script('media-upload');
            wp_enqueue_script('thickbox');

            wp_register_script('wpcf7-my-upload', WPCF7PD_URL.'js/wpcf7pdf-script.js', array('jquery','media-upload','thickbox'));
            wp_enqueue_script('wpcf7-my-upload');

            // Now we can localize the script with our data.
            wp_localize_script( 'wpcf7-my-upload', 'Data', array(
              'textebutton'  =>  __( 'Choose This Image', 'send-pdf-for-contact-form-7' ),
              'title'  => __( 'Choose Image', 'send-pdf-for-contact-form-7' ),
            ) );
        }
        
        global $wpdb;        
        $wpdb->ma_table_wpcf7pdf = $wpdb->prefix.'wpcf7pdf_files';
        $wpdb->tables[] = 'ma_table_wpcf7pdf';
        /* Création des tables nécessaires */
        if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->ma_table_wpcf7pdf."'") != $wpdb->ma_table_wpcf7pdf) {

            $sql = "CREATE TABLE `".$wpdb->ma_table_wpcf7pdf."` (
                `wpcf7pdf_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `wpcf7pdf_id_form` bigint(20) unsigned NOT NULL,
                `wpcf7pdf_reference` varchar(40) NOT NULL,
                `wpcf7pdf_status` tinyint(2) NOT NULL DEFAULT '1',
                `wpcf7pdf_data` text NOT NULL,
                `wpcf7pdf_files` longtext NOT NULL,
                `wpcf7pdf_files2` longtext NOT NULL,
                PRIMARY KEY (`wpcf7pdf_id`)
               ) ;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
        
    }

    function wpcf7pdf_session_start() {
       if ( ! session_id() ) {
          @session_start();
       }
        // On enregistre un ID en session
        if ( isset( $_SESSION['pdf_uniqueid'] ) ) {
            unset( $_SESSION['pdf_uniqueid'] );
        }
        $_SESSION['pdf_uniqueid'] = uniqid();
    }
    
    function save($id, $data, $file = '', $file2 = '') {
        
        global $wpdb;
         
        $data = array(
            'wpcf7pdf_id_form' => $id,
            'wpcf7pdf_data' => $data,
            'wpcf7pdf_reference' => $_SESSION['pdf_uniqueid'],
            'wpcf7pdf_files' => $file,
            'wpcf7pdf_files2' => $file2
        );
        $result = $wpdb->insert($wpdb->prefix.'wpcf7pdf_files', $data);
        if($result) {
            return true;
        }
        
    }
    function wpcf7pdf_name_pdf($id) {
        
        global $post;
        
        if( empty($id) ) { die('No ID Form'); }
        $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );
        
        if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) {
            $namePDF = trim($meta_values["pdf-name"]);
            $namePDF = str_replace(' ', '-', $namePDF);
        } else {
            $namePDF = 'document-pdf';
        }
        
        if( isset($meta_values["pdf-add-name"]) && $meta_values["pdf-add-name"] != '' ) {

            $addName = '';
            $getNamePerso = explode(',', $meta_values["pdf-add-name"] );
            foreach ( $getNamePerso as $key => $value ) {
                $addNewName[$key] = wpcf7_mail_replace_tags($value);
                $addNewName[$key] = str_replace(' ', '-', $addNewName[$key]);
                $addNewName[$key] = utf8_decode($addNewName[$key]);
                $addNewName[$key] = strtolower($addNewName[$key]);
                $addName .= '-'.sanitize_title($addNewName[$key]);
            }
            $namePDF = $namePDF.$addName;
            
        }
        return $namePDF;
        
    }
    
    function wpcf7pdf_folder_uploads($id) {
        
        global $post;
        
        if( empty($id) ) { die('No ID Form'); }
        $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );
        
        $upload_dir = wp_upload_dir();
        
        if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true' ) {
            
            $newDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads';            
            if( is_dir($newDirectory) == false ) {
                //mkdir($newDirectory, 0755);
                $files = array(
                    array(
                        'base' 		=> $upload_dir['basedir'] . '/sendpdfcf7_uploads/'.$id,
                        'file' 		=> '',
                        'content' 	=> ''
                    )
                );

                foreach ( $files as $file ) {
                    if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
                        if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
                            fwrite( $file_handle, $file['content'] );
                            fclose( $file_handle );
                        }
                    }
                }
            }
            
            $subDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads/'.$id;
            if ( is_dir($subDirectory) == false ) {
                $files = array(
                    array(
                        'base' 		=> $subDirectory,
                        'file' 		=> '',
                        'content' 	=> ''
                    )
                );

                foreach ( $files as $file ) {
                    if ( wp_mkdir_p( $file['base'] ) && ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) ) {
                        if ( $file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'w' ) ) {
                            fwrite( $file_handle, $file['content'] );
                            fclose( $file_handle );
                        }
                    }
                }              
            }
            $createDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads/'.$id;
            
        } else {
            $createDirectory = $upload_dir['basedir'].$upload_dir['subdir'];
        }
        
        return $createDirectory;
        
    }

    function wpcf7pdf_send_pdf($contact_form) {

        $submission = WPCF7_Submission::get_instance();

        if ( $submission ) {

            $posted_data = $submission->get_posted_data();

            global $wpdb;
            global $current_user;
            global $post;
            // récupère le POST            
            $post = $_POST;
            
            $upload_dir = wp_upload_dir();
            $uploaded_files = $submission->uploaded_files(); // this allows you access to the upload file in the temp location
            
            //error_log( $posted_data['your-message'] );
            $meta_values = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf', true );
            $meta_fields = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields', true );
            
            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads($post['_wpcf7']);

            // On va chercher les tags FILE destinés aux images
            $cf7_file_field_name = $meta_values['file_tags']; // [file uploadyourfile]
            if( !empty($cf7_file_field_name) ) {
                $contentTags  = explode('[', $cf7_file_field_name);
                foreach($contentTags as $tags) {
                    // On enlève les []
                    if( isset($tags) && $tags != '' ) {
                        $tags = substr($tags, 0, -1);
                        $image_name = $posted_data[$tags];
                        $image_location = $uploaded_files[$tags];
                        $chemin_final[$tags] = $createDirectory.'/'.$image_name; 
                        if ( $image_name > '' ) {
                            // On copie l'image dans le dossier
                            copy($image_location, $chemin_final[$tags]);
                        }
                    }
                }
            }
            
            // On va cherche les champs du formulaire
            $meta_tags = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields', true );
        
            // SAVE FORM FIELD DATA AS VARIABLES
            if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

                $nameOfPdf = $this->wpcf7pdf_name_pdf($post['_wpcf7']);
                //$text = preg_replace("/(\r\n|\n|\r)/", "---", $meta_values['generate_pdf']);
                $text = trim($meta_values['generate_pdf']);
                //$text = str_replace('https://', 'http://', $text);
                $text = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $text);
                $text = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $text);
                if( !empty($cf7_file_field_name) ) {
                    $contentTagsOnPdf  = explode('[', $cf7_file_field_name);
                    foreach($contentTagsOnPdf as $tagsOnPdf) {
                        if( isset($tagsOnPdf) && $tagsOnPdf != '' ) {
                            $tagsOnPdf = substr($tagsOnPdf, 0, -1);
                            $image_name2 = $posted_data[$tagsOnPdf];
                            $chemin_final2[$tagsOnPdf] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$image_name2; 
                            $text = str_replace('['.$tagsOnPdf.']', $chemin_final2[$tagsOnPdf], $text);
                            //error_log( 'chemin:'.str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $chemin_final[$tagsOnPdf] ) ); //not blank, all sorts of stuff
                        }
                    }
                }
                if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                    $dateField = date_i18n( $meta_values['date_format'] );
                } else {
                    $dateField = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time('timestamp') );
                }
                $text = str_replace('[date]', $dateField, $text);
                
                $csvTab = array($_SESSION['pdf_uniqueid']);
                foreach($meta_tags as $ntags => $vtags) {
                    $returnValue = wpcf7_mail_replace_tags($vtags);
                    array_push($csvTab, $returnValue);
                }
                
                $text = wpcf7_mail_replace_tags( wpautop($text) );
                $text = preg_replace("/(\r\n|\n|\r)/", "<div></div>", $text);
                $text = str_replace("<div></div><div></div>", '<div style="height:10px;"></div>', $text);
                //error_log( $text ); //not blank, all sorts of stuff
            
                // On génère le PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {

                    //include(__DIR__.'/mpdf/mpdf.php');
                    include(WPCF7PDF_DIR.'/mpdf/mpdf.php');
                    $mpdf=new mPDF();
                    $mpdf->autoScriptToLang = true;
                    $mpdf->baseScript = 1;
                    $mpdf->autoVietnamese = true;
                    $mpdf->autoArabic = true;
                    $mpdf->autoLangToFont = true;
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
                        //$meta_values["image"] = str_replace('https://', 'http://', $meta_values["image"]);
                        $mpdf->WriteHTML('<div style="text-align:'.$imgAlign.'"><img src="'.esc_url($meta_values["image"]).'" '.$attribut.' /></div>');
                    }
                    $mpdf->WriteHTML($text);
                    
                    $mpdf->Output($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', 'F');

                    // On efface l'ancien pdf renommé si il y a (on garde l'original)
                    if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                        unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                    }
                    // Je copy le PDF genere
                    copy($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $createDirectory.'/'.$nameOfPdf.'.pdf');
                    
                }
                // END GENERATE PDF
                
                // On insère dans la BDD
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == "false" ) {
                    $insertPost = $this->save($post['_wpcf7'], serialize($csvTab), str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf');
                }
                
                // If CSV is enable
                if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {

                    // On efface l'ancien csv renommé si il y a (on garde l'original)
                    if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                        unlink($createDirectory.'/'.$nameOfPdf.'.csv');
                    }

                    if( isset($meta_fields) ) {

                        $entete = array("reference");

                        foreach($meta_fields as $field) {

                            preg_match_all( '#\[(.*?)\]#', $field, $nameField );
                            $nb=count($nameField[1]);
                            for($i=0;$i<$nb;$i++) { 
                                array_push($entete, $nameField[1][$i]);
                            }

                        }

                    }

                    $csvlist = array (
                       $entete,
                       $csvTab
                    );

                    $fpCsv = fopen($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.csv', 'w+');

                    foreach ($csvlist as $csvfields) {
                        fputcsv($fpCsv, $csvfields);
                    }
                    fclose($fpCsv);

                    // Je copy le CSV genere
                    copy($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.csv', $createDirectory.'/'.$nameOfPdf.'.csv');

                    
                }
                // END GENERATE CSV
                
                
                //Définition possible de la page de redirection à partir de ce plugin (url relative réécrite).
                if( isset($meta_values['page_next']) && is_numeric($meta_values['page_next']) ) {
                    
                    if( isset($meta_values['download-pdf']) && $meta_values['download-pdf']=="true" ) {
                        $redirect = get_permalink($meta_values['page_next']).'?&pdf-reference='.$_SESSION['pdf_uniqueid'];
                    } else {
                        $redirect = get_permalink($meta_values['page_next']);
                    }
                    //Une fois que tout est bon, on lui définie le nouveau mail par la méthode associée à l'object "set_properties".
                    $contact_form->set_properties(array('additional_settings' => "on_sent_ok: \"location.replace('".$redirect."');\"")); 
                }
                
                //error_log(serialize($redirect)); //not blank, all sorts of stuff
            }                       
        }
    }

    function wpcf7pdf_mail_components($components, $contact_form, $mail) {
        
        // see : http://plugin144.rssing.com/chan-8774780/all_p511.html
        $submission = WPCF7_Submission::get_instance();
        if( $submission ) {
            
            $posted_data = $submission->get_posted_data();
            
            global $post;
            // On récupère le dossier upload de WP (utile pour les autres pièces jointes)
            $upload_dir = wp_upload_dir();
            // On récupère le dossier upload de l'extension (/sendpdfcf7_uploads/)
            $createDirectory = $this->wpcf7pdf_folder_uploads($post['_wpcf7']);
            $uploaded_files = $submission->uploaded_files();
            //$createDirectory = $upload_dir['basedir'].$upload_dir['subdir'];
            // on va chercher les options du formulaire
            
            //$components['send'] = false;
            // On recupere les donnees et le nom du pdf personnalisé
            $meta_values = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf', true );
            $nameOfPdf = $this->wpcf7pdf_name_pdf($post['_wpcf7']);
            
            // Je déclare le contenu de l'email
            $messageText = $components['body'];
            
            // Si la fonction envoi mail est activée
            if( empty($meta_values['disable-attachments']) OR (isset($meta_values['disable-attachments']) && $meta_values['disable-attachments'] == 'false') ) {
                
            // On envoi les mails
            if ( 'mail' == $mail->name() ) {
                  // do something for 'Mail'

                // Send PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                    if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'sender' OR $meta_values["send-attachment"] == 'both') ) {
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.pdf';
                    }
                }

                // SEND CSV
                if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false' ) {
                    if( isset($meta_values["send-attachment2"]) && ($meta_values["send-attachment2"] == 'sender' OR $meta_values["send-attachment2"] == 'both') ) {
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.csv';
                    }
                }

                //SEND OTHER
                if( isset($meta_values["pdf-files-attachments"]) ) {
                    if( isset($meta_values["send-attachment3"]) && ($meta_values["send-attachment3"] == 'sender' OR $meta_values["send-attachment3"] == 'both') ) { 

                        $tabDocs = explode("\n", $meta_values["pdf-files-attachments"]);
                        $tabDocs = array_map('trim',$tabDocs);// Enlève les espaces vides
                        $tabDocs = array_filter($tabDocs);// Supprime les éléments vides (= lignes vides) non 

                        $nbDocs = count($tabDocs);
                        
                        if( $nbDocs >= 1) { 
                            foreach($tabDocs as $urlDocs) {
                                $urlDocs = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $urlDocs);
                                $components['attachments'][] = $urlDocs;
                            }
                        }
                    }
                }

            } elseif ( 'mail_2' == $mail->name() ) {

                // do something for 'Mail (2)'
                // Send PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                    if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'recipient' OR $meta_values["send-attachment"] == 'both') ) {
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.pdf';
                    }
                }

                // SEND CSV
                if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false' ) {
                    if( isset($meta_values["send-attachment2"]) && ($meta_values["send-attachment2"] == 'recipient' OR $meta_values["send-attachment2"] == 'both') ) {
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.csv';
                    }
                }

                 //SEND OTHER
                if( isset($meta_values["pdf-files-attachments"]) ) {
                    if( isset($meta_values["send-attachment3"]) && ($meta_values["send-attachment3"] == 'recipient' OR $meta_values["send-attachment3"] == 'both') ) { 

                        $tabDocs2 = explode("\n", $meta_values["pdf-files-attachments"]);
                        $tabDocs2 = array_map('trim',$tabDocs2);// Enlève les espaces vides
                        $tabDocs2 = array_filter($tabDocs2);// Supprime les éléments vides (= lignes vides) non 

                        $nbDocs2 = count($tabDocs2);
                        //echo 'Total de '.$nb_lignes.' lignes : <br />';
                        if( $nbDocs2 >= 1) { 
                            foreach($tabDocs2 as $urlDocs2) {
                                $urlDocs2 = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $urlDocs2);
                                $components['attachments'][] = $urlDocs2;
                            }
                        }

                    }
                }

            }
            } // Fin si la fonction envoi mail est activée
                    
            // Je remplace les codes courts
            if( isset($messageText) && !empty($messageText) ) {
                
                // On va chercher les tags FILE destinés aux images
                $cf7_file_field_name = $meta_values['file_tags']; // [file uploadyourfile]
                if( !empty($cf7_file_field_name) ) {
                    $contentTagsOnMail  = explode('[', $cf7_file_field_name);
                    foreach($contentTagsOnMail as $tagsOnMail) {
                        if( isset($tagsOnMail) && $tagsOnMail != '' ) {
                            $tagsOnMail = substr($tagsOnMail, 0, -1);
                            $image_name2 = $posted_data[$tagsOnMail];
                            $chemin_final2[$tagsOnMail] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$image_name2;
                            if( file_exists($chemin_final2[$tagsOnMail]) ) {
                                $messageText = str_replace('[url-'.$tagsOnMail.']', $chemin_final2[$tagsOnMail], $messageText);
                            }
                        }
                    }
                }
                $messageText = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $messageText);
                $messageText = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $messageText);
                if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                    $dateField = date_i18n( $meta_values['date_format'] );
                } else {
                    $dateField = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time('timestamp') );
                }
                $messageText = str_replace('[date]', $dateField, $messageText);
                $components['body'] = $messageText;
            }
            
            //error_log(serialize($components['attachments'])); //not blank, all sorts of stuff
            return $components;

        }
    }
    
    /* Run code after the email has been sent */
    function wpcf7pdf_after_mail_actions() {
        
       $submission = WPCF7_Submission::get_instance();

	   if ($submission) {
           
            $posted_data = $submission->get_posted_data();
           
            global $post;
            // récupère le POST            
            $post = $_POST;
            $nameOfPdf = $this->wpcf7pdf_name_pdf($post['_wpcf7']);
            $createDirectory = $this->wpcf7pdf_folder_uploads($post['_wpcf7']);
            //error_log( $posted_data['your-message'] );
            $meta_values = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf', true );
            $cf7_file_field_name = $meta_values['file_tags'];
           
            // Si l'option de supprimer les fichiers est activée
            if( isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=="true") {

                if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.csv');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf');
                }
                if( !empty($cf7_file_field_name) ) {
                    $contentTagsOnPdf  = explode('[', $cf7_file_field_name);
                    foreach($contentTagsOnPdf as $tagsOnPdf) {
                        $tagsOnPdf = substr($tagsOnPdf, 0, -1);
                        $image_name = $posted_data[$tagsOnPdf];
                        $chemin_final[$tagsOnPdf] = $createDirectory.'/'.$image_name; // http:// -> /wp-content/uploads/
                        if( file_exists($chemin_final[$tagsOnPdf]) ) {
                            unlink($chemin_final[$tagsOnPdf]);
                        }
                    }
                }

            }
       }
        //exit('Meta -> '.$meta_values["pdf-file-delete"].' -- Name:'.$createDirectory.'/'.$nameOfPdf.'.pdf');
        
    }
    
    /* Récupère la liste des formulaires enregistrés */
    function getForms() {
        global $wpdb;

        $forms = get_posts( array(
            'post_type'   => 'wpcf7_contact_form',
            'orderby'     => 'ID',
            'post_parent' => 0,
            'order'       => 'ASC',            
            'posts_per_page' => -1
            ) );

        return $forms;

    }
    
    static function get_list($idForm) {

        global $wpdb;
        if(!$idForm or !$idForm) { die('Aucun formulaire sélectionné !'); }
        $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."wpcf7pdf_files WHERE wpcf7pdf_id_form = %d ", intval($idForm) ), 'OBJECT' );
        if($result) {
            return $result;
        } 
    }
    
    public static function get_byReference($ref) {

        global $wpdb;
        if(!$ref or !$ref) { die('No reference!'); }
        $result = $wpdb->get_row( $wpdb->prepare("SELECT wpcf7pdf_id, wpcf7pdf_id_form, wpcf7pdf_reference, wpcf7pdf_files FROM ".$wpdb->prefix."wpcf7pdf_files WHERE wpcf7pdf_reference = %s LIMIT 1", $ref ), 'OBJECT' );
        if($result) {
            return $result;
        } 
    }
    
    function truncate() {
        global $wpdb;
        $result =  $wpdb->query( "TRUNCATE TABLE ".$wpdb->prefix."wpcf7pdf_files" );        
		if($result) {
            return true;
        }
    }
    
    function wpcf7pdf_uninstall() {
    
        global $wpdb;

        if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }

        $allposts = get_posts( 'numberposts=-1&post_type=wpcf7_contact_form&post_status=any' );
        foreach( $allposts as $postinfo ) {
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf' );
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields' );
        }

        $wpcf7pdf_files_table = $wpdb->prefix.'wpcf7pdf_files';
        $sql = "DROP TABLE `$wpcf7pdf_files_table`";
        $wpdb->query($sql);
    }
    
    function wpcf7_export_csv($idform) {
        
        $meta_fields = get_post_meta( intval($idform), '_wp_cf7pdf_fields', true );
        $separateur = ";";
        if( isset($meta_fields) ) {

            $csv_output = '';
            $entete = array("reference");
            $lignes = array();
            $pdfFormList = cf7_sendpdf::get_list( intval($idform) );

            if( isset($pdfFormList) ) {

                foreach($meta_fields as $field) {

                    preg_match_all( '#\[(.*?)\]#', $field, $nameField );
                    //print_r($nameField);
                    $nb=count($nameField[1]); 

                    for($i=0;$i<$nb;$i++) { 
                        array_push($entete, $nameField[1][$i]);
                    }

                }

                foreach( $pdfFormList as $pdfList) {
                    $list = array();
                    $pdfData = unserialize($pdfList->wpcf7pdf_data);
                    //print_r($pdfData);
                    foreach($pdfData as $data) {
                        //$lignes[] = $data;
                        array_push($list, $data);
                    }
                    //print_r($list);
                    array_push($lignes, $list);

                }
            }
            //return $lignes;

            // Affichage de la ligne de titre, terminée par un retour chariot
            $csv_output .= implode($separateur, $entete)."\r\n";

            foreach( $lignes as $ligne ) {
                 $csv_output .= implode($separateur, $ligne)."\r\n";
            }
            return $csv_output;

        }
    }
    
}


?>