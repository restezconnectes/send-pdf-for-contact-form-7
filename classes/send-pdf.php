<?php

class cf7_sendpdf {

    protected static $instance;
    private $hidden_fields = array();

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

        /* Définition du répertoire TMP pour CF7 */
        $upload_dir = wp_upload_dir();
        if ( defined( 'WPCF7_UPLOADS_TMP_DIR' ) ) {
            update_option('wpcf7pdf_path_temp', WPCF7_UPLOADS_TMP_DIR);
        } else {
            update_option('wpcf7pdf_path_temp', $upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp');
        }

        // If you want to keep certain style properties you have to use this filter
        add_filter( 'safe_style_css', function( $styles ) {
            $styles[] = 'text-rotate';
            return $styles;
        } );

        // Maybe disable AJAX requests
        add_filter( 'wpcf7_mail_components', array( $this, 'wpcf7pdf_mail_components' ), 10, 3 );
        add_action( 'wpcf7_mail_sent', array( $this, 'wpcf7pdf_after_mail_actions' ), 10, 1 );
        add_action( 'admin_menu', array( $this, 'wpcf7pdf_add_admin' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'wpcf7pdf_codemirror_enqueue_scripts' ) );
        add_filter( 'plugin_action_links', array( $this, 'wpcf7pdf_plugin_actions' ), 10, 2 );
        add_action( 'init', array( $this, 'wpcf7pdf_session_start' ), 1 );
        add_action( 'admin_head', array( $this, 'wpcf7pdf_admin_head' ) );
        add_action( 'admin_init', array( $this, 'wpcf7pdf_process_settings_import' ) );
        add_action( 'admin_init', array( $this, 'wpcf7pdf_process_settings_export' ) );
        add_action( 'wpcf7_before_send_mail', array( $this, 'wpcf7pdf_send_pdf' ) );
        
        // Use ajax
        add_action( 'wp_ajax_wpcf7pdf_js_action', array( $this, 'wpcf7pdf_js_action' ) );
        add_action( 'wp_ajax_nopriv_wpcf7pdf_js_action', array( $this, 'wpcf7pdf_js_action' ) );       

        // on affiche les scripts footer
        add_action( 'wp_footer', array( $this, 'wpcf7_add_footer' ), 90 );
        
        if( isset($_GET['csv']) && intval($_GET['csv']) && $_GET['csv']==1 && (isset($_GET['csv_security']) || wp_verify_nonce($_GET['csv_security'], 'go_generate')) ) {
			$csv = $this->wpcf7_export_csv( esc_html($_GET['idform']) );

			header("Pragma: public");
			header("Expires: 0");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Cache-Control: private", false);
			header("Content-Type: application/octet-stream");
			header("Content-Disposition: attachment; filename=\"sendpdfcf7_export_".esc_html($_GET['idform']).".csv\";" );
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
                . esc_html( __( 'Settings', WPCF7PDF_TEXT_DOMAIN ) ) . '</a>';

            array_unshift( $links, $settings_link );

            return $links;
        }
    }

    
    function wpcf7pdf_js_action() {

        global $wpdb;

        $id = sanitize_text_field($_POST['element_id']);
        $idform = sanitize_text_field($_POST['form_id']);
        $nonce = sanitize_text_field($_POST['nonce']);

        if( wp_verify_nonce($nonce, 'delete_record-'.$id) ) {

            // Supprime dans la table des promesses 'PREFIX_wpcf7pdf_files'
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
                        wp_delete_file($chemin_path);
                        //unlink($chemin_path);
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
    static function wpcf7pdf_listing( $id, $limit = 15 ) {
        
        global $wpdb;
        $result = $wpdb->get_results( $wpdb->prepare("SELECT wpcf7pdf_id, wpcf7pdf_id_form, wpcf7pdf_reference, wpcf7pdf_data, wpcf7pdf_files, wpcf7pdf_files2 FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id_form = %d ORDER BY wpcf7pdf_id DESC LIMIT %d", sanitize_text_field($id),  sanitize_text_field($limit)), 'OBJECT' );
        if($result) {
            return $result;
        } 
        
    }

    static function wpcf7pdf_mailparser($data, $raw=0) {

        if( isset($raw) && $raw==1) {
            preg_match_all( '/\[(_raw_.*?)\]/', $data, $matches );
            return $matches[1];
        } else {
            preg_match_all('/\[(.*?)\]/', $data, $contentPdfTags, PREG_SET_ORDER, 0);
            return $contentPdfTags;
        }

    }

    static function wpcf7pdf_foundkey($data, $name) {

        $found_name = array_column($data, 'name');
        $found_key = array_search($name, $found_name);

        return $found_key;

    }

    static function wpf7pdf_tagsparser($id) {

        // nothing's here... do nothing...
        if (empty($id))
            return;


        // On va cherche les champs du formulaire
        $meta_tags = get_post_meta(esc_html($id), '_wp_cf7pdf_fields', true);

        // Definition des dates par defaut
        $dateField = WPCF7PDF_prepare::returndate($id);
        $timeField = WPCF7PDF_prepare::returntime($id);

        // Prepare les valeurs dans tableau CSV
        $tagsParser = array(sanitize_text_field(get_transient('pdf_uniqueid')), $dateField.' '.$timeField);
        foreach($meta_tags as $ntags => $vtags) {
            $returnValue = wpcf7_mail_replace_tags($vtags);
            array_push($tagsParser, $returnValue);
        }

        return $tagsParser;

    }
    
     /**
     * Process a settings export that generates a .json file of the erident settings
     */
    function wpcf7pdf_process_settings_export() {

        if(empty( $_POST['wpcf7_action']) || 'export_settings' != $_POST['wpcf7_action'])
            return;

        if(!wp_verify_nonce($_POST['wpcf7_export_nonce'], 'go_export_nonce' ))
            return;

        if(!current_user_can('manage_options') )
            return;

        if(empty($_POST['wpcf7pdf_export_id']))
        return;

        $settings = get_post_meta(sanitize_text_field($_POST['wpcf7pdf_export_id']), '_wp_cf7pdf', true);

        ignore_user_abort(true);

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=wpcf7pdf-settings-export-'.esc_html($_POST['wpcf7pdf_export_id']).'-'.date('m-d-Y').'.json');
        header("Expires: 0");

        echo json_encode($settings);
        exit;
    }


    /**
     * Process a settings import from a json file
     */
    function wpcf7pdf_process_settings_import() {

        if(empty($_POST['wpcf7_action']) || 'import_settings' != $_POST['wpcf7_action'])
            return;

        if(!wp_verify_nonce( $_POST['wpcf7_import_nonce'], 'go_import_nonce' ))
            return;

        if(!current_user_can( 'manage_options'))
            return;

        if(empty($_POST['wpcf7pdf_import_id']))
        return;

        $extensionExploded = explode('.', $_FILES['wpcf7_import_file']['name']);
        $extension = strtolower(end($extensionExploded));

        if($extension != 'json') {
            wp_die(__( 'Please upload a valid .json file' ));
        }

        $import_file = $_FILES['wpcf7_import_file']['tmp_name'];

        if(empty($import_file) ) {
            wp_die( __( 'Please upload a file to import', WPCF7PDF_TEXT_DOMAIN ) );
        }

        // Retrieve the settings from the file and convert the json object to an array.
        $settings = (array) json_decode(file_get_contents($import_file));

        update_post_meta(sanitize_text_field($_POST['wpcf7pdf_import_id']), '_wp_cf7pdf', $settings);

        echo '<div id="message" class="updated fade"><p><strong>' . __('New settings imported successfully!', WPCF7PDF_TEXT_DOMAIN) . '</strong></p></div>';

    }
    
    function wpcf7pdf_dashboard_html_page() {
        include(WPCF7PDF_DIR."/views/send-pdf-admin.php");
    }

    /* Ajout feuille CSS pour l'admin barre */
    function wpcf7pdf_admin_head() {
        
        global $current_user;
        global $_wp_admin_css_colors;
      
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {

            $admin_color = get_user_option( 'admin_color', get_current_user_id() );
            $colors      = $_wp_admin_css_colors[$admin_color]->colors;

            echo '
<style type="text/css">
.switch-field input:checked + label { background-color: '.esc_html($colors[2]).'; }
.wpcf7-form-field {
    border: 1px solid '.esc_html($colors[2]).'!important;
    background: #fff;
    -webkit-border-radius: 4px;
    -moz-border-radius: 4px;
    border-radius: 4px;
    color: '.esc_html($colors[2]).'!important;
    -webkit-box-shadow: rgba(255,255,255,0.4) 0 1px 0, inset rgba(000,000,000,0.7) 0 0px 0px;
    -moz-box-shadow: rgba(255,255,255,0.4) 0 1px 0, inset rgba(000,000,000,0.7) 0 0px 0px;
    box-shadow: rgba(255,255,255,0.4) 0 1px 0, inset rgba(000,000,000,0.7) 0 0px 0px;
    padding:8px;
    /*margin-bottom:20px;*/
}
.wpcf7-form-field:focus {
    background: #fff!important;
    color: '.esc_html($colors[0]).'!important;
}
.switch-field input:checked + label:last-of-type {
    background-color: '.esc_html($colors[0]).'!important;
    color:#e4e4e4!important;
}
.switch-field-mini input:checked + label { background-color: '.esc_html($colors[2]).'; }
.switch-field-mini input:checked + label:last-of-type {background-color: '.esc_html($colors[0]).'!important;color:#e4e4e4!important;}
.preview-btn {
    background: '.esc_html($colors[2]).';
}
.preview-btn:hover, .preview-btn a:hover {
    background: '.esc_html($colors[1]).';
}
.postbox, .bottom-notices {
	max-width:none;
    background-color: #fafafa;
}
#wpcf7-bandeau {
    background-image:url('.plugins_url('../images/bandeau-extension.gif',  __FILE__).');
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

    function wpcf7pdf_codemirror_enqueue_scripts($hook) {

        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {    
            wp_enqueue_code_editor(array( 'type' => 'text/html'));
            wp_enqueue_script('js-code-editor', WPCF7PDF_URL.'js/wpcf7pdf-code-editor.js', array( 'jquery' ), '', true);
        }

    }
    
    function wpcf7pdf_add_admin() {

        $capability = apply_filters( 'wpcf7pdf_modify_capability', WPCF7_ADMIN_READ_CAPABILITY );
        
        if ( !empty( $capability ) ) { 
            add_submenu_page( 'wpcf7',
            __('Options for CF7 Send PDF', WPCF7PDF_TEXT_DOMAIN),
            __('Create PDF', WPCF7PDF_TEXT_DOMAIN),
            $capability, 'wpcf7-send-pdf',
            array( $this, 'wpcf7pdf_dashboard_html_page') );
        }

        // If you're not including an image upload then you can leave this function call out
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') {

            wp_enqueue_media();

            wp_enqueue_script('media-upload');
            wp_enqueue_script('thickbox');
            
            wp_enqueue_script('script', WPCF7PDF_URL.'js/wpcf7pdf-action.js', array('jquery'), '1.0', true);
            /*// pass Ajax Url to script.js
            wp_localize_script('script', 'ajaxurl', admin_url( 'admin-ajax.php' ) );*/
            wp_enqueue_script('main');
            $localize = array(
                'ajaxurl' => admin_url('admin-ajax.php')
            );
            wp_localize_script('main', 'ajax_params', $localize);

            wp_register_script('wpcf7-my-upload', WPCF7PDF_URL.'/js/wpcf7pdf-script.js', array('jquery','media-upload','thickbox'));
            wp_enqueue_script('wpcf7-my-upload');

            $pcf7pdf_settings['codeEditor'] = wp_enqueue_code_editor(array('type' => 'text/html'));
            wp_localize_script('jquery', 'pcf7pdf_settings', $pcf7pdf_settings);
            
            wp_enqueue_script('wp-theme-plugin-editor');
            wp_enqueue_style('wp-codemirror');

            wp_enqueue_style('jquery-defaut-style', WPCF7PDF_URL.'css/wpcf7-admin.css');

            // Now we can localize the script with our data.
            wp_localize_script( 'wpcf7-my-upload', 'Data', array(
              'textebutton'  =>  __( 'Choose This Image', WPCF7PDF_TEXT_DOMAIN ),
              'title'  => __( 'Choose Image', WPCF7PDF_TEXT_DOMAIN ),
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

        //delete_transient('pdf_uniqueid');
        if ( false === get_transient( 'pdf_uniqueid' ) ) {
            set_transient('pdf_uniqueid', uniqid(), MINUTE_IN_SECONDS);
            //set_transient('pdf_uniqueid', uniqid(), 60);
        }

    }

    function save($id, $data, $file = '', $file2 = '') {

        global $wpdb;
        $meta_values = get_post_meta(sanitize_text_field($id), '_wp_cf7pdf', true);

        if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) {
            $namePDF = esc_html(trim($meta_values["pdf-name"]));
            $namePDF = str_replace(' ', '-', $namePDF);
        
            $data = array(
                'wpcf7pdf_id_form' => sanitize_text_field($id),
                'wpcf7pdf_data' => sanitize_textarea_field($data),
                'wpcf7pdf_reference' => get_transient('pdf_uniqueid'),
                'wpcf7pdf_files' => sanitize_url($file),
                'wpcf7pdf_files2' => sanitize_url($file2)
            );
            $result = $wpdb->insert($wpdb->prefix.'wpcf7pdf_files', $data);
            if($result) {
                return $wpdb->insert_id;
            }
        }

    }

    static function wpcf7pdf_name_pdf($id='') {

        // On recupere l'ID du Formulaire current
        $wpcf7 = WPCF7_ContactForm::get_current();
        if( $wpcf7 && $id=='') {            
            $idForm = $wpcf7->id();
        } else {
            $idForm = $id;
        }

        if( empty($idForm) ) { wp_redirect( 'admin.php?page=wpcf7-send-pdf&deleted=1' ); die('No ID Form'); }

        // Le transient est-il inexistant ou expiré ?
        //if ( false === ( $transient = get_transient('pdf_name') ) ) {

            // Si oui, je fais appelle aux fonctions pour donner une valeur au transient.
            $meta_values = get_post_meta(sanitize_textarea_field($idForm), '_wp_cf7pdf', true);
            if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) {
                $namePDF = esc_html(trim($meta_values["pdf-name"]));
                $namePDF = str_replace(' ', '-', $namePDF);
            } else {
                $namePDF = 'document-pdf';
            }
    
            if(isset($meta_values["pdf-add-name"]) && $meta_values["pdf-add-name"]!= '') {
    
                $addName = '';
                $getNamePerso = explode(',', esc_html($meta_values["pdf-add-name"]));
                if(isset($meta_values["date-for-name"]) && !empty($meta_values["date-for-name"])) {
                    $dateForName = date_i18n($meta_values["date-for-name"]);
                } else {
                    $dateForName = date_i18n('mdY', current_time('timestamp'));
                }
                $getNamePerso = str_replace('[date]', $dateForName, $getNamePerso);
                $getNamePerso = str_replace('[reference]', get_transient('pdf_uniqueid'), $getNamePerso);
                foreach ( $getNamePerso as $key => $value ) {
                    $addNewName[$key] = wpcf7_mail_replace_tags($value);
                    $addNewName[$key] = str_replace(' ', '-', $addNewName[$key]);
                    $addNewName[$key] = str_replace('.', '', $addNewName[$key]);
                    $addNewName[$key] = strtolower($addNewName[$key]);
                    $addName .= '-'.sanitize_title($addNewName[$key]);
                }
                $namePDF = $namePDF.$addName;
    
                $contact_form = WPCF7_ContactForm::get_instance($id);
                if( $contact_form ) {
                    $contact_tag = $contact_form->scan_form_tags();
                    if( !empty($contact_tag) ) {
                        foreach ( $contact_tag as $sh_tag ) {    
                            $valueTag = wpcf7_mail_replace_tags('['.esc_html($sh_tag["name"]).']');                            
                            $namePDF = str_replace('['.esc_html($sh_tag["name"]).']', sanitize_title($valueTag), $namePDF);
                        }
                    }
                }
    
            } else {
    
                $namePDF = $namePDF.'-'.sanitize_text_field(get_transient('pdf_uniqueid'));
    
            }
            
            // Je met à jour la valeur de ma variable $transient
            set_transient('pdf_name', $namePDF, MINUTE_IN_SECONDS);
		    $transient = get_transient( 'pdf_name' );

        /*} else {
            $transient = get_transient( 'pdf_name' );
        }*/
        
        return $transient;

    }

    static function wpcf7pdf_folder_uploads($id) {

        global $post;

        if( empty($id) ) { die('No ID Form'); }
        $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );

        $upload_dir = wp_upload_dir();

        // Si on a déjà défini
        if ( defined( 'WPCF7_UPLOADS_TMP_DIR' ) ) {

            add_option('wpcf7pdf_path_temp', sanitize_url(WPCF7_UPLOADS_TMP_DIR));

        } else {
            
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

                add_option('wpcf7pdf_path_temp', sanitize_url($upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp'));
            } else if( empty(get_option('wpcf7pdf_path_temp')) ) {
                add_option('wpcf7pdf_path_temp', sanitize_url($upload_dir['basedir'] . '/sendpdfcf7_uploads/tmp'));
            }

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

        // Je crée une image dans le dossier des uploads PDF. onepixel.png remplace l'image si pas d'upload du client
        if( file_exists($createDirectory.'/onepixel.png')===FALSE) {
            copy(WPCF7PDF_URL.'images/onepixel.png', $createDirectory . '/onepixel.png');
        }
        return $createDirectory;

    }

    static function wpcf7pdf_attachments( $tag = null ) {

        if ( ! $tag ) {
            $tag = $this->get( 'attachments' );
        }

        $attachments = array();

        if ( $submission = WPCF7_Submission::get_instance() ) {
            $uploaded_files = $submission->uploaded_files();

            foreach ( (array) $uploaded_files as $name => $paths ) {
                if ( false !== strpos( $tag, "[{$name}]" ) ) {
                    $attachments = array_merge( $attachments, (array) $paths );
                }
            }
        }

        foreach ( explode( "\n", $tag ) as $line ) {
            $line = trim( $line );

            if ( '[' == substr( $line, 0, 1 ) ) {
                continue;
            }

            $path = path_join( WP_CONTENT_DIR, $line );

            if ( ! wpcf7_is_file_path_in_content_dir( $path ) ) {
                // $path is out of WP_CONTENT_DIR
                continue;
            }

            if ( is_readable( $path ) && is_file( $path ) ) {
                $attachments[] = $path;
            }
        }

        if( isset($attachments[0]) ) {
            return $attachments[0];
        }
    }

    function _mirrorImage ( $imgsrc) {

        $width = imagesx ( $imgsrc );
        $height = imagesy ( $imgsrc );

        $src_x = $width -1;
        $src_y = 0;
        $src_width = -$width;
        $src_height = $height;

        $imgdest = imagecreatetruecolor ( $width, $height );

        if ( imagecopyresampled ( $imgdest, $imgsrc, 0, 0, $src_x, $src_y, $width, $height, $src_width, $src_height ) )
        {
            return $imgdest;
        }

        return $imgsrc;
    }

    function wpcf7pdf_send_pdf($contact_form) {

        $submission = WPCF7_Submission::get_instance();

        if($submission) {

            // get submission data
            $posted_data = $submission->get_posted_data();
            
            // nothing's here... do nothing...
            if (empty($posted_data))
                return;
                
            global $post;
            // récupère le POST
            $post = $_POST;

            $upload_dir = wp_upload_dir();
            $uploaded_files = $submission->uploaded_files(); // this allows you access to the upload file in the temp location

            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true);
            
            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));

            // On supprime le password en session
            if(null!==get_transient('pdf_password')) {
                delete_transient('pdf_password');
            }
            
            // Genere le nom du PDF
            $nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']));
            //$nameOfPdf = get_transient('pdf_name');

            $nbPassword = 12;
            if(isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"])) { 
                $nbPassword = esc_html($meta_values["protect_password_nb"]); 
            }
            set_transient('pdf_password', $this->wpcf7pdf_generateRandomPassword($nbPassword), HOUR_IN_SECONDS);          

            // Si on a personnalisé le PDF, on recupère le contenu et on remplace les tags
            if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

                // définit le contenu du PDf
                $contentPdf = wp_kses(trim($meta_values['generate_pdf']), WPCF7PDF_prepare::wpcf7pdf_autorizeHtml());
                $contentPdf = apply_filters( 'pl_filter_content', $contentPdf, $posted_data );
                
                // Compatibilité avec CF7 Conditional Fields / Conditional Fields PRO
                if( class_exists('Wpcf7cfMailParser') ){

                    $hidden_groups = json_decode(stripslashes($_POST['_wpcf7cf_hidden_groups']));
                    $visible_groups = json_decode(stripslashes($_POST['_wpcf7cf_visible_groups']));
                    $repeaters = json_decode(stripslashes($_POST['_wpcf7cf_repeaters']));
                    //$steps = json_decode(stripslashes($_POST['_wpcf7cf_steps']));                   

                    $parser = new Wpcf7cfMailParser($contentPdf, $visible_groups, $hidden_groups, $repeaters, $_POST);
                    $contentPdf = $parser->getParsedMail();
                }

                // Je vais chercher le tableau des tags
                $csvTab = cf7_sendpdf::wpf7pdf_tagsparser($post['_wpcf7']);
                // On insère dans la BDD
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == "false" ) {
                    $insertPost = $this->save($post['_wpcf7'], serialize($csvTab), esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'.pdf'));
                    $contentPdf = str_replace('[ID]', $insertPost, $contentPdf);
                }                

                // On génère le PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {

                    $contentPdf = WPCF7PDF_prepare::tags_parser($post['_wpcf7'], $nameOfPdf, $contentPdf);
                    // Si il existe des Shortcodes?
                    $contentPdf = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $contentPdf);
                    // On genere le PDF
                    $generatePdfFile = WPCF7PDF_generate::wpcf7pdf_create_pdf($post['_wpcf7'], $contentPdf, $nameOfPdf, $createDirectory);

                }
                // END GENERATE PDF
            }

            // If CSV is enable
            if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {
                $generateCsvFile = WPCF7PDF_generate::wpcf7pdf_create_csv($post['_wpcf7'], $nameOfPdf, $createDirectory);
            }

        }
    }

    function wpcf7pdf_mail_components($components, $contact_form, $mail) {

        // see : http://plugin144.rssing.com/chan-8774780/all_p511.html
        $submission = WPCF7_Submission::get_instance();
        if( $submission ) {

            //$posted_data = $submission->get_posted_data();

            global $post;
            // On récupère le dossier upload de WP (utile pour les autres pièces jointes)
            $upload_dir = wp_upload_dir();
            // On récupère le dossier upload de l'extension (/sendpdfcf7_uploads/)
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));
            //$uploaded_files = $submission->uploaded_files();

            // On recupere les donnees et le nom du pdf personnalisé
            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true);
            //$nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']));
            $nameOfPdf = get_transient('pdf_name');
            // PDF generé et envoyé
            $disablePDF = 0;

            // On récupère les tags du formulaire
            $contact_form = WPCF7_ContactForm::get_instance(esc_html($post['_wpcf7']));           
            $contact_tag = $contact_form->scan_form_tags();

            // Je déclare le contenu de l'email
            $messageText = $components['body'];

            // Si la fonction envoi mail est activée
            if( empty($meta_values['disable-attachments']) OR (isset($meta_values['disable-attachments']) && $meta_values['disable-attachments'] == 'false') && $disablePDF==0 ) {

                // On envoi les mails
                if ( 'mail' == $mail->name() ) {
                    // do something for 'Mail'

                    // Send just zip
                    if( isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"] == 'true' ) {
                        
                        // Création du zip
                        $zip = new ZipArchive(); 
                        if($zip->open($createDirectory.'/'.$nameOfPdf.'-'.sanitize_text_field(get_transient('pdf_uniqueid')).'.zip', ZipArchive::CREATE) === true) {
                            // Ajout des fichiers.
                            if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                                if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'sender' OR $meta_values["send-attachment"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'-'.get_transient('pdf_uniqueid').'.pdf', $nameOfPdf.'-'.get_transient('pdf_uniqueid').'.pdf');
                                }
                            }
                            if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false' ) {
                                if( isset($meta_values["send-attachment2"]) && ($meta_values["send-attachment2"] == 'sender' OR $meta_values["send-attachment2"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'-'.get_transient('pdf_uniqueid').'.csv', $nameOfPdf.'-'.get_transient('pdf_uniqueid').'.csv');
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
                        
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'-'.sanitize_text_field(get_transient('pdf_uniqueid')).'.zip';

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
                        if($zip->open($createDirectory.'/'.$nameOfPdf.'-2'.sanitize_text_field(get_transient('pdf_uniqueid')).'.zip', ZipArchive::CREATE) === true) {

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
                        
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'-'.sanitize_text_field(get_transient('pdf_uniqueid')).'.zip';

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
                $pdfPassword = WPCF7PDF_prepare::protect_pdf($post['_wpcf7']);
            }

            // Definition des dates par defaut
            $dateField = WPCF7PDF_prepare::returndate($post['_wpcf7']);
            $timeField = WPCF7PDF_prepare::returntime($post['_wpcf7']);

            // Je remplace les codes courts dans le text
            if( isset($messageText) && !empty($messageText) ) {

                if( isset($pdfPassword) && $pdfPassword!='' ) {
                    $messageText = str_replace('[pdf-password]', $pdfPassword, $messageText);
                } else {
                    $messageText = str_replace('[pdf-password]', __('*** NO PASSWORD ***', WPCF7PDF_TEXT_DOMAIN), $messageText);
                }
                
                $messageText = WPCF7PDF_prepare::tags_parser($post['_wpcf7'], $nameOfPdf, $messageText);
/*
                $contentPdfTags = self::wpcf7pdf_mailparser($messageText);
                
                error_log( print_r($contentPdfTags, true) );
                foreach ( (array) $contentPdfTags as $name_tags ) {
                    $tagReplace = str_replace('url-', '', $name_tags[1]);
                    $found_key = cf7_sendpdf::wpcf7pdf_foundkey($contact_tag, $tagReplace);
                    $basetype = $contact_tag[$found_key]['basetype'];

                    if( isset($basetype) && $basetype==='file' ) {
                        $valueTag = wpcf7_mail_replace_tags('['.$tagReplace.']');
                        error_log('EMAIL: '.$valueTag.'  -  '.str_replace('url-', '', $name_tags[0]).'  -  '.str_replace('url-', '', $name_tags[1]));
                        $messageText = WPCF7PDF_prepare::upload_file($post['_wpcf7'], $valueTag, str_replace('url-', '', $name_tags[0]), str_replace('url-', '', $name_tags[1]), $messageText);
                    }

                }*/
                
                // Shortcodes?
                $messageText = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $messageText);
                /*$messageText = str_replace('[reference]', sanitize_text_field(get_transient('pdf_uniqueid')), $messageText);
                $messageText = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'.pdf', $messageText);
                
                $messageText = str_replace('[date]', $dateField, $messageText);
                $messageText = str_replace('[time]', $timeField, $messageText);*/
               
                $components['body'] = $messageText;
            }

            // Je remplace les codes courts dans le sujet
            $subjectText = $components['subject'];
            if( isset($messageText) && !empty($messageText) ) {
                
                $subjectText = str_replace('[reference]', sanitize_text_field(get_transient('pdf_uniqueid')), $subjectText);
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
            //$nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']));
            $nameOfPdf = get_transient('pdf_name');
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));

            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true );
            /*$cf7_file_field_name = '';
            if( isset( $meta_values['file_tags'] ) && $meta_values['file_tags']!='' ) {
                $cf7_file_field_name = $meta_values['file_tags'];
            }*/

            // Si l'option de supprimer les fichiers est activée
            if( isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=="true") {

                if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.csv');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.zip') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.zip');
                }
                /*if( !empty($cf7_file_field_name) ) {

                    preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTagsDelete, PREG_SET_ORDER, 0);
                    foreach($contentTagsDelete as $tagsDelete) {
                        if( isset($tagsDelete[1]) && $tagsDelete[1] != '' ) {
                            $image_name_delete = $posted_data[$tagsDelete[1]];
                            if( isset($image_name_delete) && $image_name_delete!='' ) {
                                $chemin_final_delete[$tagsDelete[1]] = $createDirectory.'/'.sanitize_text_field(get_transient('pdf_uniqueid')).'-'.$image_name_delete;
                                if( file_exists($chemin_final_delete[$tagsDelete[1]]) ) {
                                    unlink($chemin_final_delete[$tagsDelete[1]]);
                                }
                            }
                        }
                    }
                }*/

            }
            
       }

       delete_transient('pdf_uniqueid');
       delete_transient('pdf_password');
       delete_transient('pdf_name');
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

    static function truncate() {
        global $wpdb;
        $result =  $wpdb->query( "TRUNCATE TABLE ".$wpdb->prefix."wpcf7pdf_files" );
		if($result) {
            return true;
        }
    }

    static function wpcf7pdf_deactivation() {

        global $wpdb;

        if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }
        if(get_option('wpcf7pdf_path_temp')) { delete_option('wpcf7pdf_path_temp'); }
    }

    static function wpcf7pdf_uninstall() {

        global $wpdb;

        if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }
        if(get_option('wpcf7pdf_path_temp')) { delete_option('wpcf7pdf_path_temp'); }
        
        $allposts = get_posts( 'numberposts=-1&post_type=wpcf7_contact_form&post_status=any' );
        foreach( $allposts as $postinfo ) {
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf' );
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields' );
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields_scan' );
        }
       
        $wpcf7pdf_files_table = $wpdb->prefix.'wpcf7pdf_files';
        $sql = "DROP TABLE IF EXISTS `$wpcf7pdf_files_table`";
        $wpdb->query($sql);
    }

    static function wpcf7pdf_generateRandomPassword($nb_car = 8, $chaine = 'azertyuiopqsdfghjklmwxcvbnAZERTYUIOPMLKJGFDNBD123456789') {
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

    
    static function wpcf7pdf_update_settings($idForm, $tabSettings, $nameOption = '', $type=0) {

        if( empty($nameOption) || $nameOption =='' ) { return false; }

        if( isset($tabSettings) && is_array($tabSettings) ) {

            $newTabSettings = array();
            foreach($tabSettings as $nameSettings => $valueSettings) {
                if( $type == 3 ) {
                    $newTabSettings[$nameSettings] = strip_tags( stripslashes( esc_url_raw($valueSettings) ) );
                } elseif(filter_var($valueSettings, FILTER_VALIDATE_URL)) {
                    $newTabSettings[$nameSettings] = sanitize_url($valueSettings);
                } elseif(filter_var($valueSettings, FILTER_VALIDATE_EMAIL)) {
                    $newTabSettings[$nameSettings] = sanitize_email($valueSettings);
                } elseif($nameSettings == 'generate_pdf' || $nameSettings == 'footer_generate_pdf') {
                    $arr = WPCF7PDF_prepare::wpcf7pdf_autorizeHtml();
                    $newTabSettings[$nameSettings] = wp_kses($valueSettings, $arr);
                } else {
                    $newTabSettings[$nameSettings] = sanitize_textarea_field($valueSettings);
                }
            }
            update_post_meta(sanitize_text_field($idForm), $nameOption, $newTabSettings);

            return true;

        } else {
            return false;
        }
        
    }
    
    static function wpcf7pdf_getFontsTab() {
    
        return array(
            'DejaVuSans' => 'dejavusans',
            'DejaVuSansCondensed' => 'dejavusanscondensed',
            'DejaVuSerif' => 'dejavuserif',
            'DejaVuSerifCondensed' => 'dejavuserifcondensed',
            'DejaVuSansMono' => 'dejavusansmono',
            'Ubuntu' => 'ubuntu',
            'Ubuntu-Medium' => 'ubuntu-medium',
            'Ubuntu-Light' => 'ubuntu-light',
            'Roboto' => 'roboto',
            'Poppins' => 'poppins',
            'Montserrat' => 'montserrat',
            'IslandMoments-Regular' => 'islandmoments',
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
            'KolkerBrush-Regular' => 'kolkerbrush-regular',
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
            'IPA-PGothic' => 'ipagp',
            'IPA-PMincho' => 'ipamp',
            'IPA-Mincho' => 'ipam',
            'IPA-Gothic' => 'ipag',
            'Aboriginal Sans (Cherokee and Canadian)' => 'aboriginalsans',
            'MPH 2B Damase' => '',
            'Aegyptus' => 'aegyptus',
            'Eeyek Unicode (Meetei Mayek)' => 'eeyekunicode',
            'Lannaalif (Tai Tham)' => 'lannaalif',
            'Daibanna SIL Book (New Tai Lue)' => 'daibannasilbook',
            'Cyrillic Time New roman Bulgarian' => 'times-new-roman-cyr',
        );
    }
    
    function wpcf7_export_csv($idform) {

        $meta_fields = get_post_meta( intval($idform), '_wp_cf7pdf_fields', true );
        //$nameOfPdf = $this->wpcf7pdf_name_pdf($idform);
        $nameOfPdf = get_transient('pdf_name');
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
            //$nameOfPdf = get_transient('pdf_name');
            $nameOfPdf = $this->wpcf7pdf_name_pdf($id);     

            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads($id);    
            $upload_dir = wp_upload_dir();

            $redirect = '';
            $targetPDF = '_self';

            //Définition possible de la page de redirection à partir de ce plugin (url relative réécrite).
            if( isset($meta_values['page_next']) && is_numeric($meta_values['page_next']) ) {
                $redirect = get_permalink($meta_values['page_next']).'?pdf-reference='.sanitize_text_field(get_transient('pdf_uniqueid'));
                $displayAddEventList = 1;
            }

            // Redirection direct ver le pdf après envoi du formulaire
            if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=="true" ) {
                $displayAddEventList = 1;
            }

            if ( isset($cf7msm_redirect_urls) && !empty( $cf7msm_redirect_urls ) ) {
                $displayAddEventList = 0;
            }

            if( $displayAddEventList == 1 ) {
            ?>
<!-- Send PDF for CF7 -->
<script type='text/javascript'>

    document.addEventListener( 'wpcf7mailsent', function( event ) {

        <?php 

        // Redirection direct ver le pdf après envoi du formulaire
        if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=="true" ) {
        ?>

            // Fonction sanitize champs du formulaire
            var string_to_slug = function (str) {
                str = str.replace(/^\s+|\s+$/g, ''); // trim
                str = str.toLowerCase();

                // remove accents, swap ñ for n, etc
                var from = 'àáäâèéëêìíïîòóöôùúüûñçěščřžýúůďťň·/_,:;';
                var to   = 'aaaaeeeeiiiioooouuuuncescrzyuudtn------';

                for (var i=0, l=from.length ; i<l ; i++) {
                    str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
                }

                str = str.replace('.', '') // replace a dot by a dash 
                    .replace(/[^a-z0-9 -]/g, '') // remove invalid chars
                    .replace(/\s+/g, '-') // collapse whitespace and replace by a dash
                    .replace(/-+/g, '-') // collapse dashes
                    .replace( /\//g, '' ); // collapse all forward-slashes

                return str;
            }
        
            var inputs = event.detail.inputs;
            <?php 
                // On recupère les tags du nom du PDF
                if (isset($meta_values["pdf-add-name"]) && $meta_values["pdf-add-name"] != '') {

                    $addName = '';
                    $getNamePerso = explode(',', esc_html($meta_values["pdf-add-name"]));
                    if(isset($meta_values["date-for-name"]) && !empty($meta_values["date-for-name"])) {
                        $dateForName = date_i18n($meta_values["date-for-name"]);
                    } else {
                        $dateForName = date_i18n('mdY', current_time('timestamp'));
                    }
                    $getNamePerso = str_replace('[date]', $dateForName, $getNamePerso);
                    $getNamePerso = str_replace('[reference]', get_transient('pdf_uniqueid'), $getNamePerso);
                    foreach ( $getNamePerso as $key => $value ) {
                        $addNewName[$key] = str_replace(' ', '-', $value);
                        $addNewName[$key] = strtolower($addNewName[$key]);
                        $addName .= '"'.sanitize_title($addNewName[$key]).'",';

                    }
                    ?>                
                    var fieldname = new Array(<?php echo substr($addName, 0, -1); ?>);
                    <?php 
                }
                
            ?>
            let valuefield = '';
            for ( var i = 0; i < fieldname.length; i++ ) {
            
                for ( var i = 0; i < inputs.length; i++ ) {
                    if ( fieldname[i] == inputs[i].name ) {
                       valuefield += string_to_slug(inputs[i].value) + '-';
                       //console.log('value:' + string_to_slug(inputs[i].value) );
                    }
                }

                const text = valuefield.slice(0, -1);
                <?php if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'popup' ) { ?>

                window.open('<?php echo str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)?>/<?php echo esc_html($meta_values['pdf-name']).'-'; ?>' + text + '.pdf?ver=<?php echo rand(); ?>','text','menubar=no, status=no, scrollbars=yes, menubar=no, width=600, height=900');
                
                <?php } else { ?>

                var location = '<?php echo str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)?>/<?php echo esc_html($meta_values['pdf-name']).'-'; ?>' + text + '.pdf?ver=<?php echo rand(); ?>'; window.open(location, text, '<?php echo $targetPDF; ?>');
                
                <?php } ?>

            }
    <?php } ?>
    <?php 
    if( (isset($meta_values['page_next']) && is_numeric($meta_values['page_next'])) ) {
        /* REDIRECTION  */
        echo  sprintf('location.replace("%1$s");', htmlspecialchars_decode( esc_url( $redirect ) ) );
    }
    ?>
}, false );


</script>
<!-- END :: Send PDF for CF7 -->
<?php
            }
        }
        
?>
<?php  
    // Désactivation remplissage du formulaire
    if( isset($meta_values["disabled-autocomplete-form"]) && $meta_values["disabled-autocomplete-form"]=="true" ) { 
?>
<script type='text/javascript'>
    jQuery(document).ready(function( $ ){
        $('form').attr('autocomplete', 'off');
    });
</script>
<?php } ?>
<?php

    }
}


//add_filter('wpcf7_form_hidden_fields', 'wpcf7pdf_form_hidden_fields',10,1);
    
function wpcf7pdf_form_hidden_fields($hidden_fields) {

    $current_form = wpcf7_get_current_contact_form();
    $current_form_id = $current_form->id();
 
    return array_merge($hidden_fields, array(
        '_wpcf7cfpdf_hidden_namepdf' => $current_form_id,
    ));

}