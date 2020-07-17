<?php

class cf7_sendpdf {

    protected static $instance;
    public $session;
    
	public static function init() {
			is_null( self::$instance ) AND self::$instance = new self;
			return self::$instance;
	}
    
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
        add_filter( 'plugin_action_links', array( $this, 'wpcf7pdf_plugin_actions'), 10, 2 );
        add_action( 'init', array( $this, 'wpcf7pdf_session_start'), 1 );
        add_action('admin_head', array( $this, 'wpcf7pdf_admin_head') );
        add_action( 'admin_init', array( $this, 'wpcf7pdf_process_settings_import') );
        add_action( 'admin_init', array( $this, 'wpcf7pdf_process_settings_export') );
        add_action( 'wpcf7_before_send_mail', array( $this, 'wpcf7pdf_send_pdf' ) );
        
        // Use ajax
        add_action( 'wp_ajax_wpcf7pdf_js_action', array( $this, 'wpcf7pdf_js_action' ) );
        add_action( 'wp_ajax_nopriv_wpcf7pdf_js_action', array( $this, 'wpcf7pdf_js_action' ) );
        
        add_action('wp_footer', array( $this, 'wpcf7_add_footer'), 90 );
        
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

        if ( $file != WPCF7PDF_PLUGIN_BASENAME ) {
		  return $links;
        } else {
            $settings_link = '<a href="admin.php?page=wpcf7-send-pdf">'
                . esc_html( __( 'Settings', 'send-pdf-for-contact-form-7' ) ) . '</a>';

            array_unshift( $links, $settings_link );

            return $links;
        }
    }
    
    function wpcf7pdf_js_action() {

        global $wpdb;

        $id = $_POST['element_id'];
        $idform = $_POST['form_id'];
        $nonce = $_POST['nonce'];

        if( wp_verify_nonce($nonce, 'delete_record-'.$id) ) {

            // Supprime dans la table des promesses 'PREFIX_wpspo_promesse'
            $resultOptions =  $wpdb->query( $wpdb->prepare("DELETE FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id = %d LIMIT 1", $id), 'OBJECT' );

            if($resultOptions) {

                // On récupère le dossier upload de WP
                $createDirectory = $this->wpcf7pdf_folder_uploads($idform);
                $upload_dir = wp_upload_dir();

                // va chercher le nom du PDF
                $resultFile = $wpdb->get_row( $wpdb->prepare("SELECT wpcf7pdf_files FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id = %d LIMIT %d", $id,  1), 'OBJECT' );

                if( isset($resultFile) && !empty($resultFile) ) {
                    // remplace par le PATH            
                    $chemin_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $resultFile->wpcf7pdf_files);

                    if( isset($chemin_path) && file_exists($chemin_path) ) {
                        unlink($chemin_path);
                    }
                }

                echo 'success';
            }
            
        } else {
            echo 'error js action';
        }

        die();
    }

    /**
     * Listing last PDF
     */
    function wpcf7pdf_listing($id, $limit = 10 ) {
        
        global $wpdb;
        $result = $wpdb->get_results( $wpdb->prepare("SELECT wpcf7pdf_id, wpcf7pdf_id_form, wpcf7pdf_reference, wpcf7pdf_data, wpcf7pdf_files, wpcf7pdf_files2 FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id_form = %d ORDER BY wpcf7pdf_id DESC LIMIT %d", $id,  $limit), 'OBJECT' );
        if($result) {
            return $result;
        } 
        
    }
    
     /**
     * Process a settings export that generates a .json file of the erident settings
     */
    function wpcf7pdf_process_settings_export() {

        if( empty( $_POST['wpcf7_action'] ) || 'export_settings' != $_POST['wpcf7_action'] )
            return;

        if( ! wp_verify_nonce( $_POST['wpcf7_export_nonce'], 'wpcf7_export_nonce' ) )
            return;

        if( ! current_user_can( 'manage_options' ) )
            return;

        if( empty($_POST['wpcf7pdf_export_id']) )
        return;

        $settings = get_post_meta( intval($_POST['wpcf7pdf_export_id']), '_wp_cf7pdf', true );

        ignore_user_abort( true );

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=wpcf7pdf-settings-export-'.$_POST['wpcf7pdf_export_id'].'-' . date( 'm-d-Y' ) . '.json' );
        header( "Expires: 0" );

        echo json_encode( $settings );
        exit;
    }


    /**
     * Process a settings import from a json file
     */
    function wpcf7pdf_process_settings_import() {

        if( empty( $_POST['wpcf7_action'] ) || 'import_settings' != $_POST['wpcf7_action'] )
            return;

        if( ! wp_verify_nonce( $_POST['wpcf7_import_nonce'], 'wpcf7_import_nonce' ) )
            return;

        if( ! current_user_can( 'manage_options' ) )
            return;

        if( empty($_POST['wpcf7pdf_import_id']) )
        return;

        $extensionExploded = explode('.', $_FILES['wpcf7_import_file']['name']);
        $extension = strtolower(end($extensionExploded));

        if( $extension != 'json' ) {
            wp_die( __( 'Please upload a valid .json file' ) );
        }

        $import_file = $_FILES['wpcf7_import_file']['tmp_name'];

        if( empty( $import_file ) ) {
            wp_die( __( 'Please upload a file to import', 'send-pdf-for-contact-form-7' ) );
        }

        // Retrieve the settings from the file and convert the json object to an array.
        $settings = (array) json_decode( file_get_contents( $import_file ) );

        update_post_meta( intval($_POST['wpcf7pdf_import_id']), '_wp_cf7pdf', $settings );

      echo '<div id="message" class="updated fade"><p><strong>' . __('New settings imported successfully!', 'send-pdf-for-contact-form-7') . '</strong></p></div>';

    }
    
    function wpcf7pdf_dashboard_html_page() {
        include(WPCF7PDF_DIR."/views/send-pdf-admin.php");
    }

    /* Ajout feuille CSS pour l'admin barre */
    function wpcf7pdf_admin_head() {
        
        global $current_user;
        global $_wp_admin_css_colors;
      
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {
            echo '<link rel="stylesheet" type="text/css" media="all" href="' .WP_PLUGIN_URL.'/send-pdf-for-contact-form-7/css/wpcf7-admin.css">';

            $admin_color = get_user_option( 'admin_color', get_current_user_id() );
            $colors      = $_wp_admin_css_colors[$admin_color]->colors;

            echo '
<style type="text/css">
.switch-field input:checked + label { background-color: '.$colors[2].'; }
.wpcf7-form-field {
    border: 1px solid '.$colors[2].'!important;
    background: #fff;
    -webkit-border-radius: 4px;
    -moz-border-radius: 4px;
    border-radius: 4px;
    color: '.$colors[2].'!important;
    -webkit-box-shadow: rgba(255,255,255,0.4) 0 1px 0, inset rgba(000,000,000,0.7) 0 0px 0px;
    -moz-box-shadow: rgba(255,255,255,0.4) 0 1px 0, inset rgba(000,000,000,0.7) 0 0px 0px;
    box-shadow: rgba(255,255,255,0.4) 0 1px 0, inset rgba(000,000,000,0.7) 0 0px 0px;
    padding:8px;
    /*margin-bottom:20px;*/
}
.wpcf7-form-field:focus {
    background: #fff!important;
    color: '.$colors[0].'!important;
}
.switch-field input:checked + label:last-of-type {
    background-color: '.$colors[0].'!important;
    color:#e4e4e4!important;
}
.switch-field-mini input:checked + label { background-color: '.$colors[2].'; }
.switch-field-mini input:checked + label:last-of-type {background-color: '.$colors[0].'!important;color:#e4e4e4!important;}
.preview-btn {
    background: '.$colors[2].';
}
.preview-btn:hover, .preview-btn a:hover {
    background: '.$colors[1].';
}
.postbox, .bottom-notices {
	max-width:none;
    background-color: #fafafa;
}
#wpcf7-bandeau {
    background-image:url('.WP_PLUGIN_URL.'/send-pdf-for-contact-form-7/images/bandeau-extension.gif);
    background-repeat:no-repeat;
}
#wpcf7-general h2 sup {
	font-size: 14px;
	position: relative;
	font-weight: 400;
	background: #0085ba;
	color: #fff !important;
	padding: 2px 4px !important;
	border-radius: 3px;
	top: 5px;
	left: 3px;
	border: none !important;
}
</style>
';

        }
    }

    function wpcf7pdf_add_admin() {

        $capability = apply_filters( 'wpcf7pdf_modify_capability', 'administrator' );
		
        $addPDF = add_submenu_page( 'wpcf7',
		__('Options for CF7 Send PDF', 'send-pdf-for-contact-form-7'),
		__('Send PDF with CF7', 'send-pdf-for-contact-form-7'),
		$capability, 'wpcf7-send-pdf',
		array( $this, 'wpcf7pdf_dashboard_html_page') );

        // If you're not including an image upload then you can leave this function call out

        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {

            wp_enqueue_media();

            wp_enqueue_script('media-upload');
            wp_enqueue_script('thickbox');
            
            wp_enqueue_script( 'script', WPCF7PD_URL.'js/wpcf7pdf-action.js', array('jquery'), '1.0', true );
            // pass Ajax Url to script.js
            wp_localize_script('script', 'ajaxurl', admin_url( 'admin-ajax.php' ) );

            wp_register_script('wpcf7-my-upload', WPCF7PD_URL.'js/wpcf7pdf-script.js', array('jquery','media-upload','thickbox'));
            wp_enqueue_script('wpcf7-my-upload');

            $pcf7pdf_settings['codeEditor'] = wp_enqueue_code_editor(array('type' => 'text/html'));
            wp_localize_script('jquery', 'pcf7pdf_settings', $pcf7pdf_settings);
            
            wp_enqueue_script('wp-theme-plugin-editor');
            wp_enqueue_style('wp-codemirror');

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
        
        if( !isset($_COOKIE['pdf_uniqueid']) ) {
            $uniqId = setcookie( 'pdf_uniqueid', uniqid(), time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
        } else {
            $uniqId = $_COOKIE['pdf_uniqueid'];
        }
        return $uniqId;

    }

    function save($id, $data, $file = '', $file2 = '') {

        global $wpdb;

        $data = array(
            'wpcf7pdf_id_form' => $id,
            'wpcf7pdf_data' => $data,
            'wpcf7pdf_reference' => $_COOKIE['pdf_uniqueid'],
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
            if( isset($meta_values["date-for-name"]) && !empty($meta_values["date-for-name"]) ) {
                $dateForName = date_i18n($meta_values["date-for-name"]);
            } else {
                $dateForName = date_i18n( 'mdY', current_time('timestamp'));
            }
            $getNamePerso = str_replace('[date]', $dateForName, $getNamePerso );
            $getNamePerso = str_replace('[reference]', $_COOKIE['pdf_uniqueid'], $getNamePerso );
            foreach ( $getNamePerso as $key => $value ) {
                $addNewName[$key] = wpcf7_mail_replace_tags($value);
                $addNewName[$key] = str_replace(' ', '-', $addNewName[$key]);
                $addNewName[$key] = utf8_decode($addNewName[$key]);
                $addNewName[$key] = strtolower($addNewName[$key]);
                $addName .= '-'.sanitize_title($addNewName[$key]);
            }
            $namePDF = $namePDF.$addName;
            
            $contact_form = WPCF7_ContactForm::get_instance($id);
            if( $contact_form ) {
                $contact_tag = $contact_form->scan_form_tags();
                foreach ( $contact_tag as $sh_tag ) {

                        $valueTag = wpcf7_mail_replace_tags('['.$sh_tag["name"].']');                            
                        $namePDF = str_replace('['.$sh_tag["name"].']', sanitize_title($valueTag), $namePDF);                            

                }
            }

        }        
        
        return $namePDF;

    }

    function wpcf7pdf_folder_uploads($id) {

        global $post;

        if( empty($id) ) { die('No ID Form'); }
        $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );

        $upload_dir = wp_upload_dir();

        $tmpDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads/tmp';
        if( is_dir($tmpDirectory) == false ) {
            $files = array(
                array(
                    'base' 		=> $upload_dir['basedir'] . '/sendpdfcf7_uploads/',
                    'file' 		=> 'index.php',
                    'content' 	=> '<?php // Silence is Golden'
                ),
                array(
                    'base' 		=> $upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp',
                    'file' 		=> 'index.php',
                    'content' 	=> '<?php // Silence is Golden'
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

            add_option('wpcf7pdf_path_temp', $upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp');
        } else if( empty(get_option('wpcf7pdf_path_temp')) ) {
            add_option('wpcf7pdf_path_temp', $upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp');
        }

        if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true' ) {

            $newDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads';
            if( is_dir($newDirectory) == false ) {
                //mkdir($newDirectory, 0755);
                $files = array(
                    array(
                        'base' 		=> $upload_dir['basedir'] . '/sendpdfcf7_uploads/'.$id,
                        'file' 		=> 'index.php',
                        'content' 	=> '<?php // Silence is Golden'
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
                        'file' 		=> 'index.php',
                        'content' 	=> '<?php // Silence is Golden'
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
            $custom_tmp_path = get_option('wpcf7pdf_path_temp');

            $meta_values = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf', true );
            $meta_fields = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields', true );
            
            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads($post['_wpcf7']);
            
            // On récupère le format de date dans les paramètres
            $date_format = get_option( 'date_format' );
            $hour_format = get_option( 'time_format' );

            // Definition des marges par defaut
            $marginHeader = 10;
            $marginTop = 40;

            // Definition de la taille, le format de page et la font par defaut
            $fontsizePdf = 9;
            $fontPdf = 'dejavusanscondensed';
            $formatPdf = 'A4-P';

            // On enregistre un password en session
            if ( isset( $_COOKIE['pdf_password'] ) ) {
                //unset( $_COOKIE['pdf_password'] );
                setcookie( 'pdf_password', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
            }
            $nbPassword = 12;
            if( isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"]) ) { 
                $nbPassword = $meta_values["protect_password_nb"]; 
            }
            setcookie( 'pdf_password', $this->wpcf7pdf_generateRandomPassword($nbPassword), time() + 3600, COOKIEPATH, COOKIE_DOMAIN );
            //error_log($_COOKIE['pdf_password']);
            // On va chercher les tags FILE destinés aux images
            if( isset( $meta_values['file_tags'] ) && $meta_values['file_tags']!='' ) {
                $cf7_file_field_name = $meta_values['file_tags']; // [file uploadyourfile]
                if( !empty($cf7_file_field_name) ) {

                    preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTags, PREG_SET_ORDER, 0);
                    foreach($contentTags as $tags) {
                        $image_name = '';
                        if( isset($tags[1]) && $tags[1] != '' && !empty($posted_data[$tags[1]]) ) {
                            $image_name = $posted_data[$tags[1]];
                            //error_log($tags[1].' -- '.$posted_data[$tags[1]]);
                            if( isset($image_name) && $image_name!='' && !empty($posted_data[$tags[1]]) ) {
                                //error_log($tags[1].' --> '.$uploaded_files[$tags[1]]);
                                if( !empty($uploaded_files[$tags[1]]) ) {
                                    $image_location = $uploaded_files[$tags[1]];
                                    $chemin_final[$tags[1]] = $createDirectory.'/'.$_COOKIE['pdf_uniqueid'].'-'.$image_name;
                                    // On copie l'image dans le dossier
                                    copy($image_location, $chemin_final[$tags[1]]);
                                }
                            }
                        }
                    }
                }
            }

            // On va cherche les champs du formulaire
            $meta_tags = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields', true );

            // On va cherche les champs détaillés du formulaire
            $meta_tags_scan = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields_scan', true );
            
            // SAVE FORM FIELD DATA AS VARIABLES
            if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

                // Genere le nom du PDF
                $nameOfPdf = $this->wpcf7pdf_name_pdf($post['_wpcf7']);
                // définit le contenu du PDf
                $text = trim($meta_values['generate_pdf']);

                // Si option fillable, on genere les champs et remplace les données
                if (isset($meta_values['data_input']) && $meta_values['data_input']== 'true') {
                    
                    $contact_form = WPCF7_ContactForm::get_instance($post['_wpcf7']);
                    $contact_tag = $contact_form->scan_form_tags();
                    foreach ( $contact_tag as $sh_tag ) {

                        $tagOptions = $sh_tag["options"];
                        
                        if( $sh_tag["basetype"] == 'checkbox') {
                            $inputCheckbox = '';
                            $i = 1;
                            foreach($sh_tag["values"] as $id=>$val) {
                                $caseChecked = '';
                                $valueTag = wpcf7_mail_replace_tags('['.$sh_tag["name"].']');
                                if( $val == $valueTag ) {
                                    $caseChecked = 'checked="checked"';
                                }
                                if( in_array('label_first', $tagOptions) ) {
                                    $inputCheckbox .= ''.$val.' <input type="checkbox" class="wpcf7-checkbox" name="'.$sh_tag["name"].$i.'" value="'.$i.'" '.$caseChecked.' /> ';
                                } else {
                                    $inputCheckbox .= '<input type="checkbox" class="wpcf7-checkbox" name="'.$sh_tag["name"].$i.'" value="'.$i.'" '.$caseChecked.' /> '.$val.'';
                                }
                                $i++;
                            }
                            $text = str_replace('['.$sh_tag["name"].']', $inputCheckbox, $text);

                        } else if ( $sh_tag["basetype"] == 'radio') {
                            $inputRadio = '';
                            $i = 1;
                            foreach($sh_tag["values"] as $id=>$val) {
                                $radioChecked = '';
                                $valueTag = wpcf7_mail_replace_tags('['.$sh_tag["name"].']');
                                if( $val == $valueTag ) {
                                    $radioChecked = 'checked';
                                }                            
                                if( in_array('label_first', $tagOptions) ) {
                                    $inputRadio .= ''.$val.' <input type="radio" class="wpcf7-radio" name="'.$sh_tag["name"].'" value="'.$i.'" '.$radioChecked.' > ';
                                } else {
                                    $inputRadio .= '<input type="radio" class="wpcf7-radio" name="'.$sh_tag["name"].'" value="'.$i.'" '.$radioChecked.' > '.$val.' ';
                                }
                                $i++;
                            }
                            $text = str_replace('['.$sh_tag["name"].']', $inputRadio, $text);

                        } else {
                            
                            $valueTag = wpcf7_mail_replace_tags('['.$sh_tag["name"].']');                            
                            $text = str_replace('['.$sh_tag["name"].']', $valueTag, $text);                            
                        }
                    }
                }
                            
                $text = str_replace('[reference]', $_COOKIE['pdf_uniqueid'], $text);
                $text = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf', $text);

                $cf7_file_field_name = $meta_values['file_tags']; // [file uploadyourfile]
                if( !empty($cf7_file_field_name) ) {

                    preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTagsOnPdf, PREG_SET_ORDER, 0);
                    foreach($contentTagsOnPdf as $tagsOnPdf) {
                        $image_name2 = '';
                        if( isset($tagsOnPdf[1]) && $tagsOnPdf[1] != '' && !empty($posted_data[$tagsOnPdf[1]]) ) {
                            $image_name2 = $posted_data[$tagsOnPdf[1]];
                            if( isset($image_name2) && $image_name2!='' ) {
                                $chemin_final2[$tagsOnPdf[1]] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$_COOKIE['pdf_uniqueid'].'-'.$image_name2;
                                $text = str_replace('['.$tagsOnPdf[1].']', $image_name2, $text);
                                $text = str_replace('[url-'.$tagsOnPdf[1].']', $chemin_final2[$tagsOnPdf[1]], $text);
                            } else {
                                $text = str_replace('[url-'.$tagsOnPdf[1].']', WPCF7PD_URL.'images/onepixel.png', $text);
                            }
                        }
                    }
                }
                if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                    $dateField = date_i18n( $meta_values['date_format'] );
                } else {
                    $dateField = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time('timestamp') );
                }
                if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                    $timeField = date_i18n( $meta_values['time_format'] );
                } else {
                    $timeField = date_i18n( get_option( 'time_format' ), current_time('timestamp') );
                }
                $text = str_replace('[date]', $dateField, $text);
                $text = str_replace('[time]', $timeField, $text);

                $csvTab = array($_COOKIE['pdf_uniqueid'], $dateField.' '.$timeField);
                /* Prepare les valeurs dans tableau CSV */
                foreach($meta_tags as $ntags => $vtags) {
                    $returnValue = wpcf7_mail_replace_tags($vtags);
                    array_push($csvTab, $returnValue);
                }

                $text = wpcf7_mail_replace_tags( wpautop($text) );
                if( empty( $meta_values["linebreak"] ) or ( isset($meta_values["linebreak"]) && $meta_values["linebreak"] == 'false') ) {
                    $text = preg_replace("/(\r\n|\n|\r)/", "<div></div>", $text);
                    $text = str_replace("<div></div><div></div>", '<div style="height:10px;"></div>', $text);
                }

                // On génère le PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {

                    require WPCF7PDF_DIR . 'mpdf/vendor/autoload.php';

                    if( isset($meta_values['pdf-font'])  ) {
                        $fontPdf = $meta_values['pdf-font'];
                    }
                    if( isset($meta_values['pdf-fontsize']) && is_numeric($meta_values['pdf-fontsize']) ) {
                        $fontsizePdf = $meta_values['pdf-fontsize'];
                    }
                    
                    if( isset($meta_values["margin_header"]) && $meta_values["margin_header"]!='' ) { $marginHeader = $meta_values["margin_header"]; }
                    if( isset($meta_values["margin_top"]) && $meta_values["margin_top"]!='' ) { $marginTop = $meta_values["margin_top"]; }

                    if( isset($meta_values['pdf-type']) && isset($meta_values['pdf-orientation']) ) {
                        $formatPdf = $meta_values['pdf-type'].$meta_values['pdf-orientation'];
                        //$mpdf=new mPDF('utf-8', $formatPdf);
                        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => $formatPdf, 'margin_header' => $marginHeader, 'margin_top' => 40,]);
                    } else if( isset($meta_values['fillable_data']) && $meta_values['fillable_data']== 'true') {
                        $mpdf = new \Mpdf\Mpdf(['mode' => 'c', 'format' => $formatPdf, 'margin_header' => $marginHeader, 'margin_top' => $marginTop, 'default_font' => $fontPdf, 'default_font_size' => $fontsizePdf, 'tempDir' => $custom_tmp_path]);
                    } else {
                        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_header' => $marginHeader, 'margin_top' => $marginTop, 'default_font' => $fontPdf, 'default_font_size' => $fontsizePdf, 'tempDir' => $custom_tmp_path]);
                    }
                    $mpdf->autoScriptToLang = true;
                    $mpdf->baseScript = 1;
                    $mpdf->autoVietnamese = true;
                    $mpdf->autoArabic = true;
                    $mpdf->autoLangToFont = true;                    
                    $mpdf->SetTitle(get_the_title($post['_wpcf7']));
                    $mpdf->SetCreator(get_bloginfo('name'));
                    $mpdf->ignore_invalid_utf8 = true;
                    
                    if( isset($meta_values['fillable_data']) && $meta_values['fillable_data']== 'true') {
                        $mpdf->useActiveForms = true;
                        /*$mpdf->formUseZapD = false;
                        $mpdf->formSubmitNoValueFields = true;
                        $mpdf->formExportType = 'xfdf'; // 'html' or 'xfdf'
                        $mpdf->formSelectDefaultOption = true;
                        $mpdf->form_border_color = '0.6 0.6 0.72';
                        $mpdf->form_button_border_width = '2';
                        $mpdf->form_button_border_style = 'S';
                        $mpdf->form_radio_color = '0.0 0.0 0.4'; // radio and checkbox
                        $mpdf->form_radio_background_color = '0.9 0.9 0.9';*/
                    }
                    
                    $entetePage = '';
                    if( isset($meta_values["image"]) && !empty($meta_values["image"]) ) {
                        if( ini_get('allow_url_fopen')==1) {
                            list($width, $height, $type, $attr) = getimagesize($meta_values["image"]);
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
                        $entetePage = '<div style="text-align:'.$imgAlign.';height:'.$imgHeight.'"><img src="'.esc_url($meta_values["image"]).'" '.$attribut.' /></div>';
                    }
                    $mpdf->SetHTMLHeader($entetePage);
                    

                    if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                        $footerText = str_replace('[reference]', $_COOKIE['pdf_uniqueid'], $meta_values['footer_generate_pdf']);
                        $footerText = str_replace('[url-pdf]', $upload_dir['url'].'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf', $footerText);
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

                    // LOAD a stylesheet
                    if( isset($meta_values['stylesheet']) && $meta_values['stylesheet']!='' ) {
                        $stylesheet = file_get_contents($meta_values['stylesheet']);
                        $mpdf->WriteHTML($stylesheet,1);	// The parameter 1 tells that this is css/style only and no body/html/text
                    }

                    // En cas de saut de page avec le tag [addpage]
                    if( stripos($text, '[addpage]') !== false ) {

                        $newPage = explode('[addpage]', $text);
                        for($i = 0, $size = count($newPage); $i < $size; ++$i) {

                            $mpdf->WriteHTML($newPage[$i]);
                            if( isset($meta_values["page_header"]) && $meta_values["page_header"]==0) { $mpdf->SetHTMLHeader(); }
                            if( $i < (count($newPage)-1) ) {
                                if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                                    $mpdf->AddPage();
                                } else {
                                    $mpdf->AddPage('','','','','',15,15,15,15,5,5);
                                }                                  
                            }
                        }

                    } else {

                        $mpdf->WriteHTML($text);

                    }
                    
                    // Option for Protect PDF by Password
                    if ( isset($meta_values["protect"]) && $meta_values["protect"]=='true') {
                        $pdfPassword = '';
                        if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) {
                            $pdfPassword = $meta_values["protect_password"];
                        }
                        if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true' && (isset($_COOKIE['pdf_password']) && $_COOKIE['pdf_password']!='') ) {
                            $pdfPassword = $_COOKIE['pdf_password'];
                        }
                        $mpdf->SetProtection(array(), $pdfPassword, $pdfPassword, 128);                        
                    } 
                    
                    $mpdf->Output($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf', 'F');

                    // On efface l'ancien pdf renommé si il y a (on garde l'original)
                    if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                        unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                    }
                    // Je copy le PDF genere
                    copy($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf', $createDirectory.'/'.$nameOfPdf.'.pdf');

                }
                // END GENERATE PDF

                // On insère dans la BDD
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == "false" ) {
                    $insertPost = $this->save($post['_wpcf7'], serialize($csvTab), str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf');
                }

                // If CSV is enable
                if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {

                    // On efface l'ancien csv renommé si il y a (on garde l'original)
                    if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                        unlink($createDirectory.'/'.$nameOfPdf.'.csv');
                    }

                    if( isset($meta_fields) ) {

                        $entete = array("reference", "date");

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

                    $fpCsv = fopen($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.csv', 'w+');

                    foreach ($csvlist as $csvfields) {
                        fputcsv($fpCsv, $csvfields);
                    }
                    fclose($fpCsv);

                    // Je copy le CSV genere
                    copy($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.csv', $createDirectory.'/'.$nameOfPdf.'.csv');


                }
                // END GENERATE CSV
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
            // on va chercher les options du formulaire

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

                    // Send just zip
                    if( isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"] == 'true' ) {

                        
                        // Création du zip
                        $zip = new ZipArchive(); 
                        if($zip->open($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.zip', ZipArchive::CREATE) === true) {
                            // Ajout des fichiers.
                            if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                                if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'sender' OR $meta_values["send-attachment"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'.pdf', $nameOfPdf.'.pdf');
                                }
                            }
                            if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false' ) {
                                if( isset($meta_values["send-attachment2"]) && ($meta_values["send-attachment2"] == 'sender' OR $meta_values["send-attachment2"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'.csv', $nameOfPdf.'.csv');
                                }
                            }
                            if( isset($meta_values["send-attachment3"]) && ($meta_values["send-attachment3"] == 'sender' OR $meta_values["send-attachment3"] == 'both') ) {

                                $tabDocs = explode("\n", $meta_values["pdf-files-attachments"]);
                                $tabDocs = array_map('trim',$tabDocs);// Enlève les espaces vides
                                $tabDocs = array_filter($tabDocs);// Supprime les éléments vides (= lignes vides) non
        
                                $nbDocs = count($tabDocs);
        
                                if( $nbDocs >= 1) {
                                    foreach($tabDocs as $urlDocs) {
                                        $urlDocs = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $urlDocs);
                                        $nameFile = basename($urlDocs);
                                        $zip->addFile($urlDocs, $nameFile);
                                    }
                                }
                            }
                            $zip->close();
                        }
                        
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.zip';
                        //error_log('ZIP : '.$createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.zip');

                    } else {

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
                    }

                }
                if ( 'mail_2' == $mail->name() ) {

                    // do something for 'Mail (2)'
                    if( isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"] == 'true' ) {

                        // Création du zip
                        $zip = new ZipArchive(); 
                        if($zip->open($createDirectory.'/'.$nameOfPdf.'-2'.$_COOKIE['pdf_uniqueid'].'.zip', ZipArchive::CREATE) === true) {

                            // Ajout des fichiers.
                            if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                                if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'recipient' OR $meta_values["send-attachment"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'.pdf', $nameOfPdf.'.pdf');
                                }
                            }
                            if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false' ) {
                                if( isset($meta_values["send-attachment2"]) && ($meta_values["send-attachment2"] == 'recipient' OR $meta_values["send-attachment2"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'.csv', $nameOfPdf.'.csv');
                                }
                            }
                            if( isset($meta_values["send-attachment3"]) && ($meta_values["send-attachment3"] == 'recipient' OR $meta_values["send-attachment3"] == 'both') ) {

                                $tabDocs = explode("\n", $meta_values["pdf-files-attachments"]);
                                $tabDocs = array_map('trim',$tabDocs);// Enlève les espaces vides
                                $tabDocs = array_filter($tabDocs);// Supprime les éléments vides (= lignes vides) non
        
                                $nbDocs = count($tabDocs);
        
                                if( $nbDocs >= 1) {
                                    foreach($tabDocs as $urlDocs) {
                                        $urlDocs = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $urlDocs);
                                        $nameFile = basename($urlDocs);
                                        $zip->addFile($urlDocs, $nameFile);
                                    }
                                }
                            }
                            $zip->close();
                        }
                        
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.zip';

                    } else {

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
                                if( $nbDocs2 >= 1) {
                                    foreach($tabDocs2 as $urlDocs2) {
                                        $urlDocs2 = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $urlDocs2);
                                        $components['attachments'][] = $urlDocs2;
                                    }
                                }

                            }
                        }
                    }

                }
            } // Fin si la fonction envoi mail est activée

            // Option for Protect PDF by Password
            $pdfPassword = '';
            if ( isset($meta_values["protect"]) && $meta_values["protect"]=='true') {
                $pdfPassword = '--';
                if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) {
                    $pdfPassword = $meta_values["protect_password"];
                }
                if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true' && (isset($_COOKIE['pdf_password']) && $_COOKIE['pdf_password']!='') ) {
                    $pdfPassword = $_COOKIE['pdf_password'];
                }
            }

            // les format de dates
            $dateField = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), current_time('timestamp') );
            if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
                $dateField = date_i18n( $meta_values['date_format'] );
            }
            $timeField = date_i18n( get_option( 'time_format' ), current_time('timestamp') );
            if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
                $timeField = date_i18n( $meta_values['time_format'] );
            }

            // Je remplace les codes courts dans le text
            if( isset($messageText) && !empty($messageText) ) {

                if( isset($pdfPassword) && $pdfPassword!='' ) {
                    $messageText = str_replace('[pdf-password]', $pdfPassword, $messageText);
                } else {
                    $messageText = str_replace('[pdf-password]', '', $messageText);
                }

                // On va chercher les tags FILE destinés aux images
                if( isset( $meta_values['file_tags'] ) && $meta_values['file_tags']!='' ) {
                    $cf7_file_field_name = $meta_values['file_tags']; // [file uploadyourfile]
                    if( !empty($cf7_file_field_name) ) {

                        preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTagsOnMail, PREG_SET_ORDER, 0);
                        foreach($contentTagsOnMail as $tagsOnMail) {
                            $image_name_mail = '';
                            if( isset($tagsOnMail[1]) && $tagsOnMail[1] != '' && !empty($posted_data[$tagsOnMail[1]]) ) {
                                $image_name_mail = $posted_data[$tagsOnMail[1]];
                                if( isset($image_name_mail) && $image_name_mail!='' ) {
                                    $chemin_final_mail[$tagsOnMail[1]] = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$_COOKIE['pdf_uniqueid'].'-'.$image_name_mail;
                                    $messageText = str_replace('['.$tagsOnMail[1].']', $image_name_mail, $messageText);
                                    $messageText = str_replace('[url-'.$tagsOnMail[1].']', $chemin_final_mail[$tagsOnMail[1]], $messageText);
                                } else {
                                    $messageText = str_replace('[url-'.$tagsOnMail[1].']', WPCF7PD_URL.'images/onepixel.png', $messageText);
                                }
                            }
                        }
                    }
                }
                
                $messageText = str_replace('[reference]', $_COOKIE['pdf_uniqueid'], $messageText);
                $messageText = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf', $messageText);
                
                $messageText = str_replace('[date]', $dateField, $messageText);
                $messageText = str_replace('[time]', $timeField, $messageText);

                $components['body'] = $messageText;
            }
            // Je remplace les codes courts dans le sujet
            $subjectText = $components['subject'];
            if( isset($messageText) && !empty($messageText) ) {
                
                $subjectText = str_replace('[reference]', $_COOKIE['pdf_uniqueid'], $subjectText);
                if( isset($pdfPassword) && $pdfPassword!='' ) {
                    $subjectText = str_replace('[pdf-password]', $pdfPassword, $subjectText);
                }
                $subjectText = str_replace('[date]', $dateField, $subjectText);
                $subjectText = str_replace('[time]', $timeField, $subjectText);

                $components['subject'] = $subjectText;
            }

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
            $cf7_file_field_name = '';
            if( isset( $meta_values['file_tags'] ) && $meta_values['file_tags']!='' ) {
                $cf7_file_field_name = $meta_values['file_tags'];
            }

            // Si l'option de supprimer les fichiers est activée
            if( isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=="true") {

                if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.csv');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.zip') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.zip');
                }
                if( !empty($cf7_file_field_name) ) {

                    preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTagsDelete, PREG_SET_ORDER, 0);
                    foreach($contentTagsDelete as $tagsDelete) {
                        if( isset($tagsDelete[1]) && $tagsDelete[1] != '' ) {
                            $image_name_delete = $posted_data[$tagsDelete[1]];
                            if( isset($image_name_delete) && $image_name_delete!='' ) {
                                $chemin_final_delete[$tagsDelete[1]] = $createDirectory.'/'.$_COOKIE['pdf_uniqueid'].'-'.$image_name_delete;
                                if( file_exists($chemin_final_delete[$tagsDelete[1]]) ) {
                                    unlink($chemin_final_delete[$tagsDelete[1]]);
                                }
                            }
                        }
                    }
                }

            }
            setcookie( 'pdf_uniqueid', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
            //unset( $_COOKIE['pdf_uniqueid'] );
       }

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

    static function wpcf7pdf_uninstall() {

        global $wpdb;

        if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }

        $allposts = get_posts( 'numberposts=-1&post_type=wpcf7_contact_form&post_status=any' );
        foreach( $allposts as $postinfo ) {
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf' );
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields' );
        }

        
        $wpcf7pdf_files_table = $wpdb->prefix.'wpcf7pdf_files';
        $sql = "DROP TABLE IF EXISTS `$wpcf7pdf_files_table`";
        $wpdb->query($sql);
    }

    function wpcf7pdf_generateRandomPassword($nb_car = 8, $chaine = 'azertyuiopqsdfghjklmwxcvbnAZERTYUIOPMLKJGFDNBD123456789') {
        // the finished password
        //return md5(time());
        $nb_lettres = strlen($chaine) - 1;
        $generation = '';
        for($i=0; $i < $nb_car; $i++)
        {
            $pos = mt_rand(0, $nb_lettres);
            $car = $chaine[$pos];
            $generation .= $car;
        }
        return $generation;
    }
    
    function wpcf7pdf_getFontsTab() {
    
        return array(
            'DejaVuSans' => 'dejavusans',
            'DejaVuSansCondensed' => 'dejavusanscondensed',
            'DejaVuSerif' => 'dejavuserif',
            'DejaVuSerifCondensed' => 'dejavuserifcondensed',
            'DejaVuSansMono' => 'dejavusansmono',
            'FreeSans' => 'freesans',
            'FreeSerif' => 'freeserif',
            'FreeMono' => 'freemono',
            'Quivira' => 'quivira',
            'Abyssinica SIL (Ethiopic)' => 'abyssinicasil',
            'XBRiyaz' => 'xbriyaz',
            'Taamey David CLM' => 'taameydavidclm',
            'Estrangelo Edessa (Syriac)' => 'estrangeloedessa',
            'Aegean' => 'aegean',
            'Jomolhari (Tibetan)' => 'jomolhari',
            'Kaputaunicode (Sinhala)' => 'kaputaunicode',
            'Pothana2000' => 'pothana2000',
            'Lateef' => 'lateef',
            'Khmeros' => 'khmeros',
            'Dhyana' => 'dhyana',
            'Tharlon' => 'tharlon',
            'Padauk Book' => 'padaukbook',
            'Ayar fonts' => 'ayar',
            'ZawgyiOne' => 'zawgyi-one',
            'Garuda (Thai)' => 'garuda',
            'Sundanese Unicode (Sundanese)' => 'sundaneseunicode',
            'Tai Heritage Pro (Tai Viet)' => 'taiheritagepro',
            'Sun-ExtA' => 'sun-exta',
            'Sun-ExtB' => 'sun-extb',
            'Unbatang' => 'unbatang',
            'Aboriginal Sans (Cherokee and Canadian)' => 'aboriginalsans',
            'MPH 2B Damase' => '',
            'Aegyptus' => 'aegyptus',
            'Eeyek Unicode (Meetei Mayek)' => 'eeyekunicode',
            'Lannaalif (Tai Tham)' => 'lannaalif',
            'Daibanna SIL Book (New Tai Lue)' => 'daibannasilbook'
        );
    }
    
    function wpcf7_export_csv($idform) {

        $meta_fields = get_post_meta( intval($idform), '_wp_cf7pdf_fields', true );
        $nameOfPdf = $this->wpcf7pdf_name_pdf($idform);
        $upload_dir = wp_upload_dir();
        $createDirectory = $this->wpcf7pdf_folder_uploads($idform);
        $createDirectory = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory);
            
        $separateur = ";";
        if( isset($meta_fields) ) {

            $csv_output = '';
            $entete = array("download", "reference", "date");
            $lignes = array();
            $pdfFormList = cf7_sendpdf::get_list( intval($idform) );

            if( isset($pdfFormList) ) {

                foreach($meta_fields as $field) {

                    preg_match_all( '#\[(.*?)\]#', $field, $nameField );
                    $nb=count($nameField[1]);

                    for($i=0;$i<$nb;$i++) {
                        array_push($entete, $nameField[1][$i]);
                    }

                }

                foreach( $pdfFormList as $pdfList) {
                    $list = array();
                    $pdfData = unserialize($pdfList->wpcf7pdf_data);
                    //error_log( var_dump($pdfData) );
                    
                    array_push($list, $createDirectory.'/'.$nameOfPdf.'-'.$pdfData[0].'.pdf');
                    foreach($pdfData as $data) {
                        array_push($list, $data);
                    }
                    
                    array_push($lignes, $list);

                }
            }

            // Affichage de la ligne de titre, terminée par un retour chariot
            $csv_output .= implode($separateur, $entete)."\r\n";

            foreach( $lignes as $ligne ) {
                 $csv_output .= implode($separateur, $ligne)."\r\n";
            }
            return $csv_output;
        }
    }
    
    function wpcf7_add_footer(){ 
    
        // Multi STEP plugin?
        global $cf7msm_redirect_urls;
        $displayAddEventList = 0;
        
        // On recupere l'ID du Formulaire
        $wpcf7 = WPCF7_ContactForm::get_current();
        if( $wpcf7 ) {
            
            $id = $wpcf7->id();

            $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );
            $nameOfPdf = $this->wpcf7pdf_name_pdf($id);
            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads($id);    
            $upload_dir = wp_upload_dir();

            $redirect = '';

            $js = '';
            $redirectPDF = '';
            $targetPDF = '_self';

            //Définition possible de la page de redirection à partir de ce plugin (url relative réécrite).
            if( isset($meta_values['page_next']) && is_numeric($meta_values['page_next']) ) {

                if( isset($meta_values['download-pdf']) && $meta_values['download-pdf']=="true" ) {
                    $redirect = get_permalink($meta_values['page_next']).'?pdf-reference='.$_COOKIE['pdf_uniqueid'];
                } else {
                    $redirect = get_permalink($meta_values['page_next']);
                }
                $displayAddEventList = 1;
            }
            
            // Redirection direct ver le pdf après envoi du formulaire
            if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=="true" ) {

                if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'off' ) {
                    $targetPDF = '_tab';
                }
                $urlRredirectPDF = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$nameOfPdf.'-'.$_COOKIE['pdf_uniqueid'].'.pdf';
                $redirectPDF = "/* REDICTION DIRECT */
        if ( '" . $id . "' === event.detail.contactFormId ) {";
                    if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'popup' ) {
                        $redirectPDF .= "
            window.open('".$urlRredirectPDF."','".$nameOfPdf."','menubar=no, status=no, scrollbars=yes, menubar=no, width=600, height=900');";
                     } else { 
                        $redirectPDF .= "
            var location = '".$urlRredirectPDF."'; window.open(location, '".$targetPDF."');";
                    }
                $redirectPDF .= "}
";
                $displayAddEventList = 1;

            }
            
            if ( isset($cf7msm_redirect_urls) && !empty( $cf7msm_redirect_urls ) ) {
                $displayAddEventList = 0;
            }
            
$js .= '/* REDIRECTION  */
        if ( "' . $id . '" === event.detail.contactFormId) { ';
$js .= sprintf('location.replace("%1$s");', htmlspecialchars_decode( esc_url( $redirect ) ) );
$js .= '}
';  
            if( $displayAddEventList == 1 ) {
                
    ?>
<!-- Send PDF for CF7 -->
<script type='text/javascript'>
    document.addEventListener( 'wpcf7mailsent', function( event ) {
        <?php if( isset($redirectPDF) ) { echo $redirectPDF; } ?>
    <?php if( (isset($meta_values['page_next']) && is_numeric($meta_values['page_next'])) ) { echo $js; } ?>
}, false );
</script>
<!-- END :: Send PDF for CF7 -->
    <?php
            }
        }
    }
}