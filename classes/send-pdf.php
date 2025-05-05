<?php

class cf7_sendpdf {

    protected static $instance;
    private $hidden_fields = array();

	public static function init() {
        is_null( self::$instance ) AND self::$instance = new self;
        return self::$instance;
	}

    private static $notices = array(
		'welcome' => 'views/notices/welcome'
	);
    
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
        add_action( 'admin_head', array( $this, 'wpcf7pdf_admin_head' ) );
        add_action( 'admin_init', array( $this, 'wpcf7pdf_process_settings_import' ) );
        add_action( 'admin_init', array( $this, 'wpcf7pdf_process_settings_export' ) );
        add_action( 'wpcf7_before_send_mail', array( $this, 'wpcf7pdf_send_pdf' ) );
        // Ajoute des champs cachés
        add_filter('wpcf7_form_hidden_fields', array( $this, 'wpcf7pdf_form_hidden_fields'), 10, 1);
        // Use ajax
        add_action( 'wp_ajax_wpcf7pdf_js_action', array( $this, 'wpcf7pdf_js_action' ) );
        add_action( 'wp_ajax_nopriv_wpcf7pdf_js_action', array( $this, 'wpcf7pdf_js_action' ) );

        //if( !get_option( 'wpcf7pdf_admin_notices' ) ) { add_option( 'wpcf7pdf_admin_notices', array() ); } 
        add_action( 'admin_notices', array( $this, 'wpcf7pdf_notices' ) );      
        add_action( 'wp_loaded', array( __CLASS__, 'wpcf7pdf_hide' ) );

        // on affiche les scripts footer
        add_action( 'wp_footer', array( $this, 'wpcf7_add_footer' ), 90 );
        
        if( isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'csv_security') ) {

            $capability = apply_filters( 'wpcf7pdf_modify_capability', WPCF7_ADMIN_READ_CAPABILITY );
            if ( isset($capability) && !empty( $capability ) ) { 

                if( isset($_GET['csv']) && intval($_GET['csv']) && $_GET['csv']==1 ) {
                    $csv_output = $this->wpcf7_export_csv( esc_html($_GET['idform']) );
                    
                    header("Pragma: public");
                    header("Expires: 0");
                    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                    header("Cache-Control: private", false);
                    //header("Content-Type: application/octet-stream");
                    header("Content-Disposition: attachment; filename=\"sendpdfcf7_export_".esc_html($_GET['idform']).".csv\";" );
                    header("Content-Transfer-Encoding: binary");
                    header('Content-Type: text/csv; charset=utf-8');
                    //header('Content-Length: '. strlen($encoded_csv));
                    echo esc_html($csv_output);
                    exit;
                }
            }
        }

    }

    function wpcf7pdf_notices() {
        
        $hidden_notices = get_option( 'wpcf7pdf_admin_notices', array() );
        foreach ( self::$notices as $id => $template ) {
            if ( ! in_array( $id, $hidden_notices ) ) {
                include(WPCF7PDF_DIR.$template.'.php');
            }
        }

    }

    public static function wpcf7pdf_hide() {

		if ( ! empty( $_GET['wpcf7pdf-hide-notice'] ) ) {
			if ( ! wp_verify_nonce( $_GET['_wpcf7pdf_notice_nonce'], 'wpcf7pdf_hide_notices_nonce' ) ) {
				wp_die( esc_html_e( 'Please refresh the page and retry action.', 'send-pdf-for-contact-form-7' ) );
			}

			$notices = get_option( 'wpcf7pdf_admin_notices', array() );
			$notices[] = $_GET['wpcf7pdf-hide-notice'];
			update_option( 'wpcf7pdf_admin_notices', $notices );
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

        $id = sanitize_text_field($_POST['element_id']);
        $idform = sanitize_text_field($_POST['form_id']);
        $nonce = sanitize_text_field($_POST['nonce']);

        if( wp_verify_nonce($nonce, 'delete_record-'.$id) ) {

            // Supprime dans la table des PDF 'PREFIX_wpcf7pdf_files'
            $resultOptions = WPCF7PDF_settings::delete($id);

            if( isset($resultOptions) && $resultOptions == "true" ) {

                // On récupère le dossier upload de WP
                $upload_dir = wp_upload_dir();

                // va chercher le nom du PDF
                $resultFile = WPCF7PDF_settings::get($id);

                if( isset($resultFile) && !empty($resultFile) ) {
                    // remplace par le PATH            
                    $chemin_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $resultFile->wpcf7pdf_files);
                    if( isset($chemin_path) && file_exists($chemin_path) ) {
                        wp_delete_file($chemin_path);
                    }
                }
                echo 'success';
            }
            
        } else {
            echo 'error js action';
        }

        die();
    }

    static function wpcf7pdf_mailparser($data, $raw='') {

        if( isset($raw) && $raw=='raw' ) {
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

    static function wpf7pdf_tagsparser($idForm, $reference) {

        // nothing's here... do nothing...
        if (empty($idForm))
            return;

        if( empty($reference) || $reference == '' ) { $reference = '3F7A8B43EA2F'; }

        // On va cherche les champs du formulaire
        $meta_tags = get_post_meta(esc_html($idForm), '_wp_cf7pdf_fields', true);

        // On va chercher les noms personnalisé et les cachés
        $meta_tagsname = get_post_meta(esc_html($idForm), '_wp_cf7pdf_customtagsname', true);

        // Definition des dates par defaut
        $dateField = WPCF7PDF_prepare::returndate($idForm);
        $timeField = WPCF7PDF_prepare::returntime($idForm);

        // Prepare les valeurs dans tableau CSV
        $tagsParser = array(esc_html($reference), $dateField.' '.$timeField);
        foreach($meta_tags as $ntags => $vtags) {

            preg_match_all( '#\[(.*?)\]#', $vtags, $nameField );
            $hiddenTag = 'hidden-'.$nameField[1][0];
            if( isset($meta_tagsname) && (isset($meta_tagsname[$hiddenTag]) && $meta_tagsname[$hiddenTag]==1) ) {
                
            } else {
                $returnValue = wpcf7_mail_replace_tags($vtags);
                array_push($tagsParser, $returnValue);
            }
            
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
        header('Content-Disposition: attachment; filename=wpcf7pdf-settings-export-'.esc_html($_POST['wpcf7pdf_export_id']).'-'.gmdate('m-d-Y').'.json');
        header("Expires: 0");

        echo wp_json_encode($settings);
        exit;
    }

    /**
     * Process a settings import from a json file
     */
    function wpcf7pdf_process_settings_import() {

        if (empty($_POST)) return false;
        //check_admin_referer('wpcf7_import_nonce');

        if(empty($_POST['wpcf7_action']) || 'import_settings' != $_POST['wpcf7_action'])
            return;

        if(!wp_verify_nonce( $_POST['wpcf7_import_nonce'], 'go_import_nonce' ))
            return;

        if(!current_user_can( 'manage_options'))
            return;

        if(empty($_POST['wpcf7pdf_import_id']))
        return;

        $extension = strtolower(pathinfo($_FILES['wpcf7_import_file']['name'], PATHINFO_EXTENSION));
        if($extension != 'json') {
            wp_die( esc_html__( 'Please upload a valid .json file', 'send-pdf-for-contact-form-7' ) );
        }

        $import_file = $_FILES['wpcf7_import_file']['tmp_name'];

        if(empty($import_file) ) {
            wp_die( esc_html__( 'Please upload a file to import', 'send-pdf-for-contact-form-7' ) );
        }

        $import = ! empty( $_FILES['wpcf7_import_file'] ) && is_array( $_FILES['wpcf7_import_file'] ) && isset( $_FILES['wpcf7_import_file']['type'], $_FILES['wpcf7_import_file']['name'] ) ? $_FILES['wpcf7_import_file'] : array();

        $_post_action    = $_POST['action'];
        $_POST['action'] = 'wp_handle_sideload';
        $file            = wp_handle_sideload( $import, array( 'mimes' => array( 'json' => 'application/json' ) ) );
        $_POST['action'] = $_post_action;
        if ( ! isset( $file['file'] ) ) {
            return;
        }
        $filesystem      = WPCF7PDF_settings::wpcf7pdf_get_filesystem();
        $settings        = $filesystem->get_contents( $file['file'] );
	    $settings        = maybe_unserialize( $settings );

        // Retrieve the settings from the file and convert the json object to an array.
        $settings = (array) json_decode($settings);
        update_post_meta(sanitize_text_field($_POST['wpcf7pdf_import_id']), '_wp_cf7pdf', $settings);

        echo '<div id="message" class="updated fade"><p><strong>' . esc_html__('New settings imported successfully!', 'send-pdf-for-contact-form-7') . '</strong></p></div>';

    }
    
    function wpcf7pdf_dashboard_html_page() {
        include(WPCF7PDF_DIR."/views/send-pdf-admin.php");
    }

    /* Ajout feuille CSS pour l'admin barre */
    function wpcf7pdf_admin_head() {
        
        global $current_user;
        global $_wp_admin_css_colors;
      
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') { // phpcs:ignore

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
    background-image:url('.esc_url(plugins_url('../images/bandeau-extension.gif',  __FILE__)).');
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

        wp_enqueue_style('wpcf7-notices-style', WPCF7PDF_URL.'css/wpcf7-notices.css', array(), WPCF7PDF_VERSION);
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') { // phpcs:ignore
            wp_enqueue_code_editor(array( 'type' => 'text/html'));
            wp_enqueue_script('js-code-editor', WPCF7PDF_URL.'js/wpcf7pdf-code-editor.js', array( 'jquery' ), WPCF7PDF_VERSION, true);
        }

    }
    
    function wpcf7pdf_add_admin() {

        $capability = apply_filters( 'wpcf7pdf_modify_capability', WPCF7_ADMIN_READ_CAPABILITY );
        
        if ( isset($capability) && !empty( $capability ) ) { 
            add_submenu_page( 'wpcf7',
            __('Options for CF7 Send PDF', 'send-pdf-for-contact-form-7'),
            __('Create PDF', 'send-pdf-for-contact-form-7'),
            $capability, 'wpcf7-send-pdf',
            array( $this, 'wpcf7pdf_dashboard_html_page') );
        }
        

        // If you're not including an image upload then you can leave this function call out
        if (isset($_GET['page']) && $_GET['page'] == 'wpcf7-send-pdf') { // phpcs:ignore

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

            wp_register_script('wpcf7-my-upload', WPCF7PDF_URL.'/js/wpcf7pdf-script.js', array('jquery','media-upload','thickbox'), WPCF7PDF_VERSION, true);
            wp_enqueue_script('wpcf7-my-upload');

            $pcf7pdf_settings['codeEditor'] = wp_enqueue_code_editor(array('type' => 'text/html'));
            wp_localize_script('jquery', 'pcf7pdf_settings', $pcf7pdf_settings);
            
            wp_enqueue_script('wp-theme-plugin-editor');
            wp_enqueue_style('wp-codemirror');

            wp_enqueue_style('jquery-defaut-style', WPCF7PDF_URL.'css/wpcf7-admin.css', array(), WPCF7PDF_VERSION);

            // Now we can localize the script with our data.
            wp_localize_script( 'wpcf7-my-upload', 'Data', array(
              'textebutton'  =>  __( 'Choose This Image', 'send-pdf-for-contact-form-7' ),
              'title'  => __( 'Choose Image', 'send-pdf-for-contact-form-7' ),
            ) );
        }

        global $wpdb;
        $wpdb->ma_table_wpcf7pdf = $wpdb->prefix.'wpcf7pdf_files';
        $wpdb->tables[] = 'ma_table_wpcf7pdf';
        $showTable = $wpdb->get_var("SHOW TABLES LIKE '".$wpdb->ma_table_wpcf7pdf."'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        /* Création des tables nécessaires */
        if( $showTable != $wpdb->ma_table_wpcf7pdf) {

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

    function save($id, $data, $reference='-', $file = '', $file2 = '') {

        global $wpdb;
        $meta_values = get_post_meta(sanitize_text_field($id), '_wp_cf7pdf', true);

        if( isset($meta_values["pdf-name"]) && !empty($meta_values["pdf-name"]) ) {
            $namePDF = esc_html(trim($meta_values["pdf-name"]));
            $namePDF = str_replace(' ', '-', $namePDF);
            $dataTab = array(
                'wpcf7pdf_id_form' => sanitize_text_field($id),
                'wpcf7pdf_data' => sanitize_textarea_field($data),
                'wpcf7pdf_reference' => $reference,
                'wpcf7pdf_files' => sanitize_url($file),
                'wpcf7pdf_files2' => sanitize_url($file2)
            );
            $result = $wpdb->insert($wpdb->prefix.'wpcf7pdf_files', $dataTab); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if($result) {
                return $wpdb->insert_id;
            }
        }

    }

    static function wpcf7pdf_name_pdf($id='', $reference = '') {

        // On recupere l'ID du Formulaire current
        $wpcf7 = WPCF7_ContactForm::get_current();
        if( $wpcf7 && $id=='') {            
            $idForm = $wpcf7->id();
        } else {
            $idForm = $id;
        }

        if( empty($idForm) ) { wp_redirect( 'admin.php?page=wpcf7-send-pdf&deleted=1' ); die('No ID Form'); }

        // Je fais appelle aux fonctions pour donner une valeur au nom.
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
            $getNamePerso = str_replace('[reference]', $reference, $getNamePerso);
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

            $namePDF = $namePDF;

        }
        
        return $namePDF;

    }

    function uniqidReal($lenght = 13) {
        // uniqid gives 13 chars, but you could adjust it to your needs.
        if (function_exists("random_bytes")) {
            $bytes = random_bytes(ceil($lenght / 2));
        } elseif (function_exists("openssl_random_pseudo_bytes")) {
            $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
        } else {
            $bytes = uniqid();
        }
        return substr(bin2hex($bytes), 0, $lenght);
    }

    function wpcf7pdf_form_hidden_fields($fields) {

        $current_form = wpcf7_get_current_contact_form();
        $current_form_id = $current_form->id();

        $nonce = wp_create_nonce('cf7-redirect-id');
        $fields['redirect_nonce'] = $nonce;
        $fields['wpcf7cfpdf_hidden_name'] = self::wpcf7pdf_name_pdf($current_form_id);
        $fields['wpcf7cfpdf_hidden_reference'] = self::uniqidReal(8);

        $meta_values = get_post_meta( $current_form_id, '_wp_cf7pdf', true );

        if(isset($meta_values["date-for-name"]) && !empty($meta_values["date-for-name"])) {
            $dateForName = date_i18n($meta_values["date-for-name"]);
        } else {
            $dateForName = date_i18n('mdY', current_time('timestamp'));
        }
        $fields['wpcf7cfpdf_hidden_date'] = $dateForName;

        return $fields;

    }

    static function wpcf7pdf_folder_uploads($id) {

        global $post;

        if( empty($id) ) { die('No ID Form'); }
        $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );

        $upload_dir     = wp_upload_dir();
        $filesystem     = WPCF7PDF_settings::wpcf7pdf_get_filesystem();

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
                        $filesystem->put_contents( trailingslashit( $file['base'] ) . $file['file'], $file['content'], FS_CHMOD_FILE);
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
                        $filesystem->put_contents( trailingslashit( $file['base'] ) . $file['file'], $file['content'], FS_CHMOD_FILE);
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
                        $filesystem->put_contents( trailingslashit( $file['base'] ) . $file['file'], $file['content'], FS_CHMOD_FILE);
                    }
                }
                
            }
            $createDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads/'.$id;
            

        } else {
            $createDirectory = $upload_dir['basedir'].$upload_dir['subdir'];
        }

        // Je crée une image dans le dossier des uploads PDF. onepixel.png remplace l'image si pas d'upload du client
        if( filter_var( ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN ) && file_exists($createDirectory.'/onepixel.png')===FALSE) {
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

            //make sure the nonce is available too,
            if( !isset($_POST['redirect_nonce']) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            // récupère le POST
            $post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            $upload_dir = wp_upload_dir();
            $uploaded_files = $submission->uploaded_files(); // this allows you access to the upload file in the temp location

            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true);

            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));

            // On supprime le password en session
            if(null!==get_transient('pdf_password')) {
                delete_transient('pdf_password');
            }
            
            // Récupère la référence
            $referencePDF = esc_html($post['wpcf7cfpdf_hidden_reference']);
            
            // Genere le nom du PDF
            $nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']), esc_html($referencePDF));

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

                    $hidden_groups = json_decode(stripslashes($_POST['_wpcf7cf_hidden_groups'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $visible_groups = json_decode(stripslashes($_POST['_wpcf7cf_visible_groups'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $repeaters = json_decode(stripslashes($_POST['_wpcf7cf_repeaters'])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    //$steps = json_decode(stripslashes($_POST['_wpcf7cf_steps']));                   

                    $parser = new Wpcf7cfMailParser($contentPdf, $visible_groups, $hidden_groups, $repeaters, $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $contentPdf = $parser->getParsedMail();
                }

                // Je vais chercher le tableau des tags
                $csvTab = self::wpf7pdf_tagsparser($post['_wpcf7'], $referencePDF);
                // On insère dans la BDD
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == "false" ) {
                    if( empty($meta_values["disable-csv"]) || (isset($meta_values["disable-csv"]) && $meta_values["disable-csv"]=='false') ) {
                        $saveCsv = esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'-'.$referencePDF.'.csv');
                    } else {
                        $saveCsv = '';
                    }
                    $insertPost = $this->save($post['_wpcf7'], serialize($csvTab), $referencePDF, esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'-'.$referencePDF.'.pdf'), $saveCsv);
                    $contentPdf = str_replace('[ID]', $insertPost, $contentPdf);
                }                

                // On génère le PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {

                    $contentPdf = WPCF7PDF_prepare::tags_parser($post['_wpcf7'], $nameOfPdf, $referencePDF, $contentPdf);
                    // Si il existe des Shortcodes?
                    if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {
                        $contentPdf = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $contentPdf);
                    }
                    // On genere le PDF
                    $generatePdfFile = WPCF7PDF_generate::wpcf7pdf_create_pdf($post['_wpcf7'], $contentPdf, $nameOfPdf, $referencePDF, $createDirectory);

                    // Si plusieurs PDF
                    if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) {

                        for ($i = 2; $i <= $meta_values["number-pdf"]; $i++) {

                            if( isset($meta_values['content_addpdf_'.$i]) && $meta_values['content_addpdf_'.$i] != '') {

                                $addNamePdf = sanitize_title($meta_values['nameaddpdf'.$i]);

                                // définit le contenu du PDf
                                $messageAddPdf = wp_kses(trim($meta_values['content_addpdf_'.$i]), WPCF7PDF_prepare::wpcf7pdf_autorizeHtml());   
                                $messageAddPdf = apply_filters( 'pl_filter_content', $messageAddPdf, $posted_data );    
                                
                                if( class_exists('Wpcf7cfMailParser') ){
                                    $parserPdf = new Wpcf7cfMailParser($messageAddPdf, $visible_groups, $hidden_groups, $repeaters, $_POST); // phpcs:ignore WordPress.Security.NonceVerification.Missing
                                    $messageAddPdf = $parserPdf->getParsedMail();
                                }

                                // Preparation du contenu du PDF
                                $messageAddPdf = WPCF7PDF_prepare::tags_parser($post['_wpcf7'], $addNamePdf, $referencePDF, $messageAddPdf);
                                
                                // Shortcodes?
                                if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {
                                    $messageAddPdf = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $messageAddPdf);
                                }    
                
                                // Création du PDF
                                $generateAddPdfFile = WPCF7PDF_generate::wpcf7pdf_create_pdf($post['_wpcf7'], $messageAddPdf, $addNamePdf, $referencePDF, $createDirectory);
                            }
            
                        }
            
                    }

                }

                
                // END GENERATE PDF
            }

            // If CSV is enable
            if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {
                $generateCsvFile = WPCF7PDF_generate::wpcf7pdf_create_csv($post['_wpcf7'], $nameOfPdf, $referencePDF, $createDirectory);
            }

        }
    }

    function wpcf7pdf_mail_components($components, $contact_form, $mail) {

        // see : http://plugin144.rssing.com/chan-8774780/all_p511.html
        $submission = WPCF7_Submission::get_instance();
        if( $submission ) {

            //$posted_data = $submission->get_posted_data();

            global $post;

            //make sure the nonce is available too,
            if( !isset($_POST['redirect_nonce']) ) return; // phpcs:ignore WordPress.Security.NonceVerification.Missing

            // On récupère le dossier upload de WP (utile pour les autres pièces jointes)
            $upload_dir = wp_upload_dir();
            // On récupère le dossier upload de l'extension (/sendpdfcf7_uploads/)
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));
            //$uploaded_files = $submission->uploaded_files();

            // On recupere les donnees et le nom du pdf personnalisé
            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true);
            // Récupère la référence
            $referencePDF = esc_html($post['wpcf7cfpdf_hidden_reference']);
            // Genere le nom du PDF
            $nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']), esc_html($referencePDF));
            // PDF generé et envoyé
            $disablePDF = 0;
            
            // On récupère les tags du formulaire
            $contact_form = WPCF7_ContactForm::get_instance(esc_html($post['_wpcf7']));           
            $contact_tag = $contact_form->scan_form_tags();

            // Je déclare le contenu de l'email
            $messageText = $components['body'];

            // Si on a définit un champ conditionnel (envoie ou non du PDF)
            if( isset($meta_values["condition-sending"]) && $meta_values["condition-sending"]=='true') { 

                if( isset($meta_values["condition-tag"]) && isset($meta_values["condition-tag"]) ) {

                    $raw1Value = wpcf7_mail_replace_tags(esc_html('['.$meta_values["condition-tag"].']'));
                    // On desactive l'envoi du PDF
                    if( isset($raw1Value) && $raw1Value == 'false' ) {
                        $disablePDF = 1;
                    }

                }
            }

            // Si la fonction envoi mail est activée
            if( empty($meta_values['disable-attachments']) OR (isset($meta_values['disable-attachments']) && $meta_values['disable-attachments'] == 'false') && $disablePDF==0 ) {

                // On envoi les mails
                if ( 'mail' == $mail->name() ) {
                    // do something for 'Mail'

                    // Send just zip
                    if( isset($meta_values["pdf-to-zip"]) && $meta_values["pdf-to-zip"] == 'true' ) {
                        
                        // Création du zip
                        $zip = new ZipArchive(); 
                        if($zip->open($createDirectory.'/'.$nameOfPdf.'-'.sanitize_text_field($referencePDF).'.zip', ZipArchive::CREATE) === true) {
                            // Ajout des fichiers.
                            if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                                if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'sender' OR $meta_values["send-attachment"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'-'.$referencePDF.'.pdf', $nameOfPdf.'-'.$referencePDF.'.pdf');
                                }
                            }
                            if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false' ) {
                                if( isset($meta_values["send-attachment2"]) && ($meta_values["send-attachment2"] == 'sender' OR $meta_values["send-attachment2"] == 'both') ) {
                                    $zip->addFile($createDirectory.'/'.$nameOfPdf.'-'.$referencePDF.'.csv', $nameOfPdf.'-'.$referencePDF.'.csv');
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
                        
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'-'.sanitize_text_field($referencePDF).'.zip';

                    } else {

                        // Send PDF
                        if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                            if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'sender' OR $meta_values["send-attachment"] == 'both') ) {
                                
                                $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.pdf';
                                
                                // Si plusieurs PDF
                                if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) {

                                    for ($i = 2; $i <= $meta_values["number-pdf"]; $i++) {
                                        $addNamePdf = sanitize_title($meta_values['nameaddpdf'.$i.'']);
                                        if( isset($addNamePdf) && $addNamePdf != '') {
                                            $components['attachments'][] = $createDirectory.'/'.$addNamePdf.'.pdf';
                                        }
                                    }
                                }
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
                        if($zip->open($createDirectory.'/'.$nameOfPdf.'-2'.sanitize_text_field($referencePDF).'.zip', ZipArchive::CREATE) === true) {

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
                        
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'-'.sanitize_text_field($referencePDF).'.zip';

                    } else {

                        // Send PDF
                        if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                            if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'recipient' OR $meta_values["send-attachment"] == 'both') ) {
                                $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.pdf';
                                // Si plusieurs PDF
                                if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) {

                                    for ($i = 2; $i <= $meta_values["number-pdf"]; $i++) {
                                        $addNamePdf = sanitize_title($meta_values['nameaddpdf'.$i.'']);
                                        if( isset($addNamePdf) && $addNamePdf != '') {
                                            $components['attachments'][] = $createDirectory.'/'.$addNamePdf.'.pdf';
                                        }
                                    }
                                }
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

            // Si le contenu du PDF doit rester en brut et pas en HTML
            /*if( isset($meta_values["disable-html"]) && $meta_values['disable-html'] == 'false' ) {
                $messageText = str_replace("\r\n", "<br />", $messageText);
            }*/
            
            // Definition des dates par defaut
            $dateField = WPCF7PDF_prepare::returndate($post['_wpcf7']);
            $timeField = WPCF7PDF_prepare::returntime($post['_wpcf7']);

            // Je remplace les codes courts dans le text
            if( isset($messageText) && !empty($messageText) ) {

                if( isset($pdfPassword) && $pdfPassword!='' ) {
                    $messageText = str_replace('[pdf-password]', $pdfPassword, $messageText);
                } else {
                    $messageText = str_replace('[pdf-password]', __('*** NO PASSWORD ***', 'send-pdf-for-contact-form-7'), $messageText);
                }
                $messageText = WPCF7PDF_prepare::tags_parser($post['_wpcf7'], $nameOfPdf, $referencePDF, $messageText, 1);
                
                // Shortcodes?
                if( isset($meta_values['shotcodes_tags']) && !empty($meta_values['shotcodes_tags'])) {
                    $messageText = WPCF7PDF_prepare::shortcodes($meta_values['shotcodes_tags'], $messageText);
                }
              
                $components['body'] = $messageText;
            }

            // Je remplace les codes courts dans le sujet
            $subjectText = $components['subject'];
            if( isset($messageText) && !empty($messageText) ) {
                
                $subjectText = str_replace('[reference]', sanitize_text_field($referencePDF), $subjectText);
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
    function wpcf7pdf_after_mail_actions($wpcf7) {

       $submission = WPCF7_Submission::get_instance();

	   if ($submission) {

            global $post;

            // récupère le POST
            $post = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
            // Récupère la référence
            $referencePDF = esc_html($post['wpcf7cfpdf_hidden_reference']);
            // Genere le nom du PDF
            $nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']), $referencePDF);
            // On récupère le dossier upload de l'extension (/sendpdfcf7_uploads/)
            $upload_dir = wp_upload_dir();
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));
            $createPathDirectory = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $createDirectory);
            // On recupere les donnees et le nom du pdf personnalisé
            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true );

            // Si l'option de supprimer les fichiers est activée
            if( isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=="true") {

                // Si fichier PDF activé, on les suprime disable-pdf
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {
                    if( file_exists($createPathDirectory.'/'.$nameOfPdf.'.pdf') ) {
                        wp_delete_file($createPathDirectory.'/'.$nameOfPdf.'.pdf');
                    } 
                    if( file_exists($createPathDirectory.'/'.$nameOfPdf.'-'.$referencePDF.'.pdf') ) {
                        wp_delete_file($createPathDirectory.'/'.$nameOfPdf.'-'.$referencePDF.'.pdf');
                    }
                    // Si plusieurs PDF
                    if( isset($meta_values["number-pdf"]) && $meta_values["number-pdf"]>1 ) {

                        for ($i = 2; $i <= $meta_values["number-pdf"]; $i++) {                            
                            $addNamePdf = sanitize_title($meta_values['nameaddpdf'.$i.'']);
                            if( file_exists($createPathDirectory.'/'.$addNamePdf.'-'.$referencePDF.'.pdf') ) {
                                wp_delete_file($createPathDirectory.'/'.$addNamePdf.'-'.$referencePDF.'.pdf');
                            }
                            if( file_exists($createPathDirectory.'/'.$addNamePdf.'.pdf') ) {
                                wp_delete_file($createPathDirectory.'/'.$addNamePdf.'.pdf');
                            }
                        }
                    }
                }

                // Si fichier CSV activé, on les suprime
                if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {
                    if( file_exists($createPathDirectory.'/'.$nameOfPdf.'.csv') ) {
                        wp_delete_file($createPathDirectory.'/'.$nameOfPdf.'.csv');
                    }
                    if( file_exists($createPathDirectory.'/'.$nameOfPdf.'-'.$referencePDF.'.csv') ) {
                        wp_delete_file($createPathDirectory.'/'.$nameOfPdf.'-'.$referencePDF.'.csv');
                    }
                }
                
                // Si ZIP activé, on les suprime
                if( isset($meta_values["pdf-to-zip"]) && $meta_values['pdf-to-zip'] == 'true') {
                    if( file_exists($createPathDirectory.'/'.$nameOfPdf.'.zip') ) {
                        wp_delete_file($createPathDirectory.'/'.$nameOfPdf.'.zip');
                    }
                }

            }

            delete_transient('pdf_password');
           
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


    public static function get_byReference($ref) {

        global $wpdb;
        if(!$ref or !$ref) { die('No reference!'); }
        $result = $wpdb->get_row( $wpdb->prepare("SELECT wpcf7pdf_id, wpcf7pdf_id_form, wpcf7pdf_data, wpcf7pdf_reference, wpcf7pdf_files FROM ".$wpdb->prefix."wpcf7pdf_files WHERE wpcf7pdf_reference = %s LIMIT 1", $ref ), 'OBJECT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if($result) {
            return $result;
        }
    }

    static function wpcf7pdf_deactivation() {

        if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }
        if(get_option('wpcf7pdf_path_temp')) { delete_option('wpcf7pdf_path_temp'); }
        if(get_option('wpcf7pdf_admin_notices')) { delete_option('wpcf7pdf_admin_notices'); }
    }

    static function wpcf7pdf_uninstall() {

        global $wpdb;

        if(get_option('wpcf7pdf_version')) { delete_option('wpcf7pdf_version'); }
        if(get_option('wpcf7pdf_path_temp')) { delete_option('wpcf7pdf_path_temp'); }
        if(get_option('wpcf7pdf_admin_notices')) { delete_option('wpcf7pdf_admin_notices'); }
        
        $allposts = get_posts( 'numberposts=-1&post_type=wpcf7_contact_form&post_status=any' );
        foreach( $allposts as $postinfo ) {
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf' );
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields' );
            delete_post_meta( $postinfo->ID, '_wp_cf7pdf_fields_scan' );
        }
        $dropTable = WPCF7PDF_settings::drop();

    }

    static function wpcf7pdf_generateRandomPassword($nb_car = 8, $chaine = 'azertyuiopqsdfghjklmwxcvbnAZERTYUIOPMLKJGFDNBD123456789') {
        // the finished password
        //return md5(time());
        $nb_lettres = strlen($chaine) - 1;
        $generation = '';
        for($i=0; $i < $nb_car; $i++)
        {
            $pos = wp_rand(0, $nb_lettres);
            $car = $chaine[$pos];
            $generation .= $car;
        }
        return $generation;
    }   
    
    function wpcf7_export_csv($idform) {

        $meta_fields = get_post_meta( intval($idform), '_wp_cf7pdf_fields', true );
        //$nameOfPdf = $this->wpcf7pdf_name_pdf($idform);
        $nameOfPdf = get_transient('pdf_name');
        $upload_dir = wp_upload_dir();
        $createDirectory = $this->wpcf7pdf_folder_uploads($idform);
        $createDirectory = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory);
            
        if( isset($meta_values['csv-separate']) && $meta_values['csv-separate']!='') { $separateur = esc_html($meta_values['csv-separate']); } else { $separateur = ";"; }
        
        if( isset($meta_fields) ) {

            $csv_output = '';
            $entete = array("download", "reference", "date");
            $lignes = array();
            $pdfFormList = WPCF7PDF_settings::get_list( intval($idform) );

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
                        $data = html_entity_decode($data);
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
        ?>
<!-- Send PDF for CF7 -->
<script type='text/javascript'>

        //generates random id for reference;
        let wpcf7_unique_id = (type = 2) => {
            let dateuid = () => {
                return Date.now().toString(26)
                        .toString(16)
                        .substring(1);
            }
            let mathuid = () => {
                return Math.floor((1 + Math.random()) * 0x10000)
                    .toString(16)
                    .substring(1);
            }
            //return id if type = 2
            if( type == 2 ) {
                return dateuid() + mathuid();
            } else {
                return dateuid();
            }
            
        }

        <?php
            $id = $wpcf7->id();

            $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );
            //$nameOfPdf = $this->wpcf7pdf_name_pdf($id);
            if (isset($meta_values['pdf-name']) && is_string($meta_values['pdf-name'])) {
                $singleNamePDF = esc_html(sanitize_title($meta_values['pdf-name']));
            } else {
                // Handle the error or set a default value
                $singleNamePDF = 'document-pdf';
            }

            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads($id);    
            $upload_dir = wp_upload_dir();

            $targetPDF = '_self';

            //Définition possible de la page de redirection à partir de ce plugin (url relative réécrite).
            if( isset($meta_values['page_next']) && is_numeric($meta_values['page_next']) ) {
                $displayAddEventList = 1;
            }

            // Redirection direct ver le pdf après envoi du formulaire
            if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=="true" ) {
                $displayAddEventList = 1;
            }

            if ( isset($cf7msm_redirect_urls) && !empty( $cf7msm_redirect_urls ) ) {
                $displayAddEventList = 0;
            }
            if( (isset($meta_values["disable-insert"]) && $meta_values["disable-insert"]=='true') || (isset($meta_values["pdf-file-delete"]) && $meta_values["pdf-file-delete"]=='true') ) { 
                $displayAddEventList == 0;
            }

            if( $displayAddEventList == 1 ) {
            ?>

    document.addEventListener( 'wpcf7mailsent', function( event ) {
       
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
        // On va chercher la référence dans le retour du formulaire
        var inputs = event.detail.inputs;
        for ( var o = 0; o < inputs.length; o++ ) {
            if ( 'wpcf7cfpdf_hidden_reference' == inputs[o].name ) {
                var reference = string_to_slug(inputs[o].value);
            }
        }
        <?php 
        $addName = '';
        // On recupère les tags du nom du PDF
        if (isset($meta_values["pdf-add-name"]) && $meta_values["pdf-add-name"] != '') {
            
            $getNamePerso = explode(',', esc_html($meta_values["pdf-add-name"]));
            $getNamePerso = str_replace('[date]', 'wpcf7cfpdf_hidden_date', $getNamePerso);
            $getNamePerso = str_replace('[reference]', 'wpcf7cfpdf_hidden_reference', $getNamePerso);
            foreach ( $getNamePerso as $key => $value ) {
                $addNewName[$key] = str_replace(' ', '-', $value);
                $addNewName[$key] = strtolower($addNewName[$key]);
                $addName .= "'".esc_html(sanitize_title($addNewName[$key]))."',";
            }

        ?>         
            var fieldname = [<?php echo wp_kses_data($addName, 0, -1); ?>];           
            let valuefield = '';

            for ( var ii = 0; ii < fieldname.length; ii++ ) { // je liste les champs du nom du PDF
                for ( var i = 0; i < inputs.length; i++ ) { // je liste les champs envoyé du form
                    //console.log( fieldname[ii] + ' == ' + inputs[i].name + ' : '+ inputs[i].value );
                    if ( fieldname[ii] == inputs[i].name ) { // je compare si le nom se trouve dans le form
                        valuefield += '-' + string_to_slug(inputs[i].value);
                    }                   
                }
                
            }   let text = valuefield;
                <?php 
                // Si on ne redirige pas sur une page, on construit la redirection 
                if( isset($meta_values['page_next']) && $meta_values['page_next']=='' ) { 
                    // Si on redirige sur une popup
                    if( (isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"] == 'true') && (isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'popup') ) { ?>

                window.open('<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)); ?>/<?php echo esc_html($singleNamePDF); ?>' + text + '-' + reference + '.pdf?ver=<?php echo esc_html(wp_rand()); ?>','text','menubar=no, status=no, scrollbars=yes, menubar=no, width=600, height=900');

                <?php } else if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"] == 'true' ) { ?>
                // Si option réglée sur nouvelle fenêtre mais avec des tags dans le nom du PDF
                var location = '<?php echo esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)); ?>/<?php echo esc_html($singleNamePDF); ?>' + text + '-' + reference + '.pdf?ver=<?php echo esc_html(wp_rand()); ?>';
                window.open(location, text, '<?php echo esc_html($targetPDF); ?>');

                <?php } } ?>
           

    <?php } else { 
        
        $urlRredirectPDF = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$singleNamePDF;

        if( (isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"] == 'true' ) && (isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'popup') ) { ?>
        // Si popup
        window.open('<?php echo esc_url($urlRredirectPDF); ?>-' + reference + '.pdf?ver=<?php echo esc_html(wp_rand()); ?>', '<?php echo esc_html($singleNamePDF); ?>','menubar=no, status=no, scrollbars=yes, menubar=no, width=600, height=900');
        
        <?php } else if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"] == 'true' ) { ?>
        // Si option réglée sur nouvelle fenêtre
        var location = '<?php echo esc_url($urlRredirectPDF); ?>-' + reference + '.pdf?ver=<?php echo esc_html(wp_rand()); ?>'; 
        window.open(location, '<?php echo esc_html($singleNamePDF); ?>', '<?php echo esc_html($targetPDF); ?>');
        
        <?php } 

        } 
        if( (isset($meta_values['page_next']) && is_numeric($meta_values['page_next'])) && $meta_values['page_next']>=1 ) {
            $nonce_url = wp_nonce_url( esc_url(htmlspecialchars_decode(get_permalink($meta_values['page_next']))), 'go_reference' );
        ?>
        // Si option réglée sur redirection vers page externe
        location.replace("<?php echo esc_url($nonce_url); ?>&pdf-reference=" + reference);
        /*location.replace("<?php //echo htmlspecialchars_decode( esc_url( get_permalink($meta_values['page_next']).'?pdf-reference=')); ?>" + reference);*/
        <?php }
    
    ?>
    }, false );

<?php
            }
        
?>
<?php
    // Désactivation remplissage du formulaire
    if( isset($meta_values["disabled-autocomplete-form"]) && $meta_values["disabled-autocomplete-form"]=="true" ) { 
?>

    jQuery(document).ready(function( $ ){
        $('form').attr('autocomplete', 'off');
    });

<?php } ?>

    document.addEventListener( 'wpcf7submit', function( event ) {
        jQuery('input[name="wpcf7cfpdf_hidden_reference"]').val(wpcf7_unique_id(3));
        
    }, false );
    
</script>
<!-- END :: Send PDF for CF7 -->
<?php } ?>
<?php

    }
}
