<?php

class cf7_sendpdf {

    protected static $instance;

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

    static function wpcf7pdf_name_pdf($id) {

        if( empty($id) ) { wp_redirect( 'admin.php?page=wpcf7-send-pdf&deleted=1' ); die('No ID Form'); }

        $meta_values = get_post_meta(sanitize_textarea_field($id), '_wp_cf7pdf', true);

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
        set_transient('pdf_name', $namePDF, MINUTE_IN_SECONDS);
        return $namePDF;

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

        return $createDirectory;

    }

    function wpcf7pdf_attachments( $tag = null ) {

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

        return $attachments[0];
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

    public static function adjustImageOrientation($filename, $quality = 90) {
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

    function wpcf7pdf_autoRotateImage($full_filename) {  

        $exif = @exif_read_data( $full_filename, 'EXIF', true );

        //$exif = exif_read_data($full_filename);
        if(false !== $exif && isset($exif['Orientation'])) {
            $orientation = $exif['Orientation'];
            if($orientation != 1){
                $img = imagecreatefromjpeg($full_filename);

                $mirror = false;
                $deg    = 0;

                switch ($orientation) {
                case 2:
                    $mirror = true;
                    break;
                case 3:
                    $deg = 180;
                    break;
                case 4:
                    $deg = 180;
                    $mirror = true;  
                    break;
                case 5:
                    $deg = 270;
                    $mirror = true; 
                    break;
                case 6:
                    $deg = 270;
                    break;
                case 7:
                    $deg = 90;
                    $mirror = true; 
                    break;
                case 8:
                    $deg = 90;
                    break;
                }
                if ($deg) $img = imagerotate($img, $deg, 0); 
                if ($mirror) $img = _mirrorImage($img);
                imagejpeg($img, $full_filename, 95);
            }
        }
        return $full_filename;
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
            $custom_tmp_path = get_option('wpcf7pdf_path_temp');

            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true);
            $meta_fields = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf_fields', true);
            
            // On récupère le dossier upload de WP
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));
            
            // On récupère le format de date dans les paramètres
            $date_format = get_option('date_format');
            $hour_format = get_option('time_format');

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

            // On supprime le password en session
            if(null!==get_transient('pdf_password')) {
                delete_transient('pdf_password');
            }

            $nbPassword = 12;
            if(isset($meta_values["protect_password_nb"]) && $meta_values["protect_password_nb"]!='' && is_numeric($meta_values["protect_password_nb"])) { 
                $nbPassword = esc_html($meta_values["protect_password_nb"]); 
            }
            set_transient('pdf_password', $this->wpcf7pdf_generateRandomPassword($nbPassword), HOUR_IN_SECONDS);
    
            // On va chercher les tags FILE destinés aux images
            if( isset( $meta_values['file_tags'] ) && $meta_values['file_tags']!='' ) {
                $cf7_file_field_name = esc_html($meta_values['file_tags']); // [file uploadyourfile]
                if( !empty($cf7_file_field_name) ) {

                    preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTags, PREG_SET_ORDER, 0);
                    foreach($contentTags as $tags) {
                        $image_name = '';
                        if( isset($tags[1]) && $tags[1] != '' && !empty($posted_data[$tags[1]]) ) {
                            $image_name = $posted_data[$tags[1]];
                            
                            if( isset($image_name) && $image_name!='' && !empty($posted_data[$tags[1]]) ) {
                                
                                if( !empty($uploaded_files[$tags[1]]) ) {
                                    
                                    $image_location = $this->wpcf7pdf_attachments($tags[0]);
                                    $chemin_final[$tags[1]] = $createDirectory.'/'.sanitize_text_field(get_transient('pdf_uniqueid')).'-'.wpcf7_mail_replace_tags($tags[0]);
                                    // On copie l'image dans le dossier
                                    copy($image_location, $chemin_final[$tags[1]]);
                                }

                            }

                        }
                    }
                }
            }

            // On va cherche les champs du formulaire
            $meta_tags = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf_fields', true);
            
            // On va cherche les champs détaillés du formulaire
            //$meta_tags_scan = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf_fields_scan', true);
            
            // SAVE FORM FIELD DATA AS VARIABLES
            if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

                // Genere le nom du PDF
                //$nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']));
                $nameOfPdf = get_transient('pdf_name');

                // définit le contenu du PDf
                $contentPdf = wp_kses(trim($meta_values['generate_pdf']), $this->wpcf7pdf_autorizeHtml());
                $contentPdf = apply_filters( 'pl_filter_content', $contentPdf, $posted_data );
                
                /**
                 * GESTION DES IMAGES UPLOADEES / AVATAR
                 */
                // replace tag by avatar picture
                $user = wp_get_current_user();
                if ( $user ) :
                    $contentPdf = str_replace('[avatar]', esc_url( get_avatar_url( $user->ID ) ), $contentPdf);
                endif;
                // read all image tags into an array
                preg_match_all('/<img[^>]+>/i', $contentPdf, $imgTags); 

                for ($i = 0; $i < count($imgTags[0]); $i++) {
                    // get the source string
                    preg_match('/src="([^"]+)/i', $imgTags[0][$i], $imgage);

                    // remove opening 'src=' tag, can`t get the regex right
                    $origImageSrc = str_ireplace( 'src="', '',  $imgage[0]);
                    if( strpos( $origImageSrc, 'http' ) === false ) {                
                        $contentPdf = str_replace( $origImageSrc, WPCF7PDF_URL.'images/temporary-image.jpg', $contentPdf);
                    }
                }
                /**
                 * FIN
                 */

                // Compatibilité avec CF7 Conditional Fields / Conditional Fields PRO
                if( class_exists('Wpcf7cfMailParser') ){

                    $hidden_groups = json_decode(stripslashes($_POST['_wpcf7cf_hidden_groups']));
                    $visible_groups = json_decode(stripslashes($_POST['_wpcf7cf_visible_groups']));
                    $repeaters = json_decode(stripslashes($_POST['_wpcf7cf_repeaters']));
                    //$steps = json_decode(stripslashes($_POST['_wpcf7cf_steps']));                   

                    $parser = new Wpcf7cfMailParser($contentPdf, $visible_groups, $hidden_groups, $repeaters, $_POST);
                    $contentPdf = $parser->getParsedMail();
                }

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
                $contact_form = WPCF7_ContactForm::get_instance(esc_html($post['_wpcf7']));           
                $contact_tag = $contact_form->scan_form_tags();

                $contentPdfTagsRaw = self::wpcf7pdf_mailparser($contentPdf, 1);
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

                $contentPdfTags = self::wpcf7pdf_mailparser($contentPdf);
                foreach ( (array) $contentPdfTags as $name_tags ) {

                    $found_key = cf7_sendpdf::wpcf7pdf_foundkey($contact_tag, $name_tags[1]);
                    $basetype = $contact_tag[$found_key]['basetype'];
                    
                    $tagOptions = '';
                    if( isset( $contact_tag[$found_key]['options'] ) ) {
                        $tagOptions = $contact_tag[$found_key]['options'];
                    }

                    /*if( isset($basetype) && ($basetype==='text' || $basetype==='email') ) {  
                        
                        $valueTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));
                        if (isset($meta_values['data_input']) && $meta_values['data_input']=='true') {

                            $inputSelect = '<input type="text" class="wpcf7-input" name="'.esc_html($valueTag).'" value="" />';
    
                        } else {
    
                            $inputSelect = esc_html($valueTag);
                            
                        }
                        $contentPdf = str_replace(esc_html($name_tags[0]), $inputSelect, $contentPdf);

                    } else */if( isset($basetype) && $basetype==='checkbox' ) {

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
                                    } else if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') ) {
                                        $emptyCheckInput = 1;
                                    }
                                } else {
                                    if( strpos($valueTag, trim($valCheckbox) )!== false ){
                                        $caseChecked = 'checked="checked"';
                                    } else if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') ) {
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
                                    if( sanitize_text_field($valueTag)===sanitize_text_field($valCheckbox) ) {  
                                        if( $emptyCheckInput == 0 ) {                                 
                                            $inputCheckbox .= ''.$tagSeparate.''.$valCheckbox.''.$tagSeparateAfter.'';
                                        }
                                    }
                                } else {
                                    if( strpos($valueTag, trim($valCheckbox) )!== false ) {
                                        if( $emptyCheckInput == 0 ) {
                                            $inputCheckbox .= ''.$tagSeparate.''.$valCheckbox.''.$tagSeparateAfter.'';
                                        }
                                    }
                                }

                            } 
                            $i++;

                        }
                        $contentPdf = str_replace(esc_html($name_tags[0]), $inputCheckbox, $contentPdf);
                        
                    } else if( isset($basetype) && $basetype==='radio' ) {

                        $inputRadio = '';

                        foreach( $contact_tag[$found_key]['values'] as $idRadio=>$valRadio ) {
                           
                            $radioChecked = '';
                            $valueRadioTag = wpcf7_mail_replace_tags(esc_html($name_tags[0]));
                            $emptyRadioInput = 0;
                            
                            if(isset($meta_values['data_input']) && $meta_values['data_input']=='true') {

                                if( sanitize_text_field($valueRadioTag)===sanitize_text_field($valRadio) ) {
                                    $radioChecked = ' checked="yes"';
                                } else if( (isset($meta_values['empty_input']) && $meta_values['empty_input']=='true') ) {
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

                                if( sanitize_text_field($valueRadioTag)===sanitize_text_field($valRadio) ) {
                                    if( $emptyRadioInput == 0 ) {                                 
                                        $inputRadio .= ''.$tagSeparate.''.$valRadio.''.$tagSeparateAfter.'';
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
     
                $contentPdf = str_replace('[reference]', sanitize_text_field(get_transient('pdf_uniqueid')), $contentPdf);
                $contentPdf = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$nameOfPdf.'.pdf', $contentPdf);

                $cf7_file_field_name = $meta_values['file_tags']; // [file uploadyourfile]
                if( !empty($cf7_file_field_name) ) {

                    preg_match_all('`\[([^\]]*)\]`', $cf7_file_field_name, $contentTagsOnPdf, PREG_SET_ORDER, 0);
                    foreach($contentTagsOnPdf as $tagsOnPdf) {
                        $image_name2 = '';
                        if( isset($tagsOnPdf[1]) && $tagsOnPdf[1] != '' && !empty($posted_data[$tagsOnPdf[1]]) ) {
                            $image_name2 = $posted_data[$tagsOnPdf[1]];
                            if( isset($image_name2) && $image_name2!='' ) {
                                
                                // remplace le tag
                                $contentPdf = str_replace('['.$tagsOnPdf[1].']', $image_name2, $contentPdf);
                                // URL IMAGE 
                                $uploadingImg[$tagsOnPdf[1]] = $createDirectory.'/'.sanitize_text_field(get_transient('pdf_uniqueid')).'-'.wpcf7_mail_replace_tags($tagsOnPdf[0]);
                                // rotation de l'image si besoin
                                $rotate_image[$tagsOnPdf[1]] = $this->adjustImageOrientation($uploadingImg[$tagsOnPdf[1]]);
                                // retourne l'URL complete du tag 
                                $chemin_final2[$tagsOnPdf[1]] = esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $uploadingImg[$tagsOnPdf[1]]));
                                // retourne l'URL complete du tag 
                                $contentPdf = str_replace('[url-'.$tagsOnPdf[1].']', $chemin_final2[$tagsOnPdf[1]], $contentPdf);

                            } else {
                                $contentPdf = str_replace('[url-'.$tagsOnPdf[1].']', WPCF7PDF_URL.'images/onepixel.png', $contentPdf);
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
                $contentPdf = str_replace('[date]', $dateField, $contentPdf);
                $contentPdf = str_replace('[time]', $timeField, $contentPdf);

                $csvTab = array(sanitize_text_field(get_transient('pdf_uniqueid')), $dateField.' '.$timeField);
                /* Prepare les valeurs dans tableau CSV */
                foreach($meta_tags as $ntags => $vtags) {
                    //error_log($ntags.' => '.$vtags);
                    $returnValue = wpcf7_mail_replace_tags($vtags);
                    array_push($csvTab, $returnValue);
                }

                // On insère dans la BDD
                if( isset($meta_values["disable-insert"]) && $meta_values["disable-insert"] == "false" ) {
                    $insertPost = $this->save($post['_wpcf7'], serialize($csvTab), esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'.pdf'));
                    $contentPdf = str_replace('[ID]', $insertPost, $contentPdf);
                }

                

                $contentPdf = wpcf7_mail_replace_tags( wpautop($contentPdf) );
                if( empty( $meta_values["linebreak"] ) or ( isset($meta_values["linebreak"]) && $meta_values["linebreak"] == 'false') ) {
                    $contentPdf = preg_replace("/(\r\n|\n|\r)/", "<div></div>", $contentPdf);
                    $contentPdf = str_replace("<div></div><div></div>", '<div style="height:10px;"></div>', $contentPdf);
                }

                // On génère le PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {

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
                        );

                    }

                    $mpdf = new \Mpdf\Mpdf($mpdfConfig);
                    $mpdf->autoScriptToLang = true;
                    $mpdf->baseScript = 1;
                    $mpdf->autoVietnamese = true;
                    $mpdf->autoArabic = true;
                    $mpdf->autoLangToFont = true;                    
                    $mpdf->SetTitle(get_the_title(esc_html($post['_wpcf7'])));
                    $mpdf->SetCreator(get_bloginfo('name'));
                    $mpdf->SetDirectionality($setDirectionality);
                    $mpdf->ignore_invalid_utf8 = true;

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
                    
                    // LOAD a stylesheet
                    if( isset($meta_values['stylesheet']) && $meta_values['stylesheet']!='' ) {
                        $stylesheet = file_get_contents(esc_url($meta_values['stylesheet']));
                        $mpdf->WriteHTML($stylesheet,1);	// The parameter 1 tells that this is css/style only and no body/html/text
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
                    $mpdf->SetHTMLHeader($entetePage, '', true);

                    if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {

                        $footerText = wp_kses(trim($meta_values['footer_generate_pdf']), $this->wpcf7pdf_autorizeHtml());
                        $footerText = str_replace('[reference]', sanitize_text_field(get_transient('pdf_uniqueid')), $footerText);
                        $footerText = str_replace('[url-pdf]', esc_url($upload_dir['url'].'/'.$nameOfPdf.'.pdf'), $footerText);
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


                    // Shortcodes?
                    if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {
                        $tagShortcodes = explode(',', esc_html($meta_values['shotcodes_tags']));
                        $countShortcodes = count($tagShortcodes);
                        for($i = 0; $i < ($countShortcodes);  $i++) {

                            $pattern = '`\[([^\]]*)\]`';
                            $result = preg_match_all($pattern, $tagShortcodes[$i], $shortcodeTags);
                            $shortcodeName = explode(' ', $shortcodeTags[1][0]);
                            
                            if( stripos($contentPdf, '['.$shortcodeName[0].']') !== false ) {
                                $contentPdf = str_replace('['.$shortcodeName[0].']', do_shortcode($tagShortcodes[$i]), $contentPdf);
                            }
                        }
                    }

                    // En cas de saut de page avec le tag [addpage]
                    if( stripos($contentPdf, '[addpage]') !== false ) {

                        $newPage = explode('[addpage]', $contentPdf);
                        $countPage = count($newPage);

                        for($i = 0; $i < ($countPage);  $i++) {
                            
                            if( $i == 0 ) {
                                // On print la première page
                                $mpdf->WriteHTML($newPage[$i]);
                            } else {
                                // On print ensuite les autres pages trouvées
                                if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                                    $mpdf->SetHTMLHeader($entetePage, '', true);
                                    $mpdf->AddPage();
                                } else {
                                    $mpdf->SetHTMLHeader(); 
                                    $mpdf->AddPage('','','','','',15,15,15,15,5,5);
                                }
                                if( isset($meta_values['footer_generate_pdf']) && $meta_values['footer_generate_pdf']!='' ) {
                                    $mpdf->SetHTMLFooter($footerText);
                                }
                                $mpdf->WriteHTML($newPage[$i]);
                                if( isset($meta_values["page_header"]) && $meta_values["page_header"]==1) {
                                    $mpdf->SetHTMLHeader($entetePage, '', true);
                                } else {
                                    $mpdf->SetHTMLHeader();                                 
                                }
                            }
                            
                        }

                    } else {

                        $contentPdf = apply_filters('wpcf7pdf_text', $contentPdf, $contact_form);
                        $mpdf->WriteHTML($contentPdf);

                    }
                    
                    // Option for Protect PDF by Password
                    if ( isset($meta_values["protect"]) && $meta_values["protect"]=='true') {
                        $pdfPassword = '';
                        if( isset($meta_values["protect_password"]) && $meta_values["protect_password"]!='' ) {
                            $pdfPassword = esc_html($meta_values["protect_password"]);
                        }
                        if( isset($meta_values["protect_uniquepassword"]) && $meta_values["protect_uniquepassword"]=='true' && (null!==get_transient('pdf_password') && get_transient('pdf_password')!='')) {
                            $pdfPassword = get_transient('pdf_password');
                        }
                        if( isset($meta_values["protect_password_tag"]) && $meta_values["protect_password_tag"]!='' ) {
                            $pdfPassword = wpcf7_mail_replace_tags($meta_values["protect_password_tag"]);
                        }
                        $mpdf->SetProtection(array('print','fill-forms'), $pdfPassword, $pdfPassword, 128);             
                    } 

                    $mpdf->Output($createDirectory.'/'.$nameOfPdf.'.pdf', 'F');

                    // Je copy le PDF genere
                    if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                        copy($createDirectory.'/'.$nameOfPdf.'.pdf', $createDirectory.'/'.$nameOfPdf.'-'.get_transient('pdf_uniqueid').'.pdf');
                    }

                }
                // END GENERATE PDF

                // If CSV is enable
                if( isset($meta_values["disable-csv"]) && $meta_values['disable-csv'] == 'false') {

                    // On efface l'ancien csv renommé si il y a (on garde l'original)
                    /*if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                        unlink($createDirectory.'/'.$nameOfPdf.'.csv');
                    }*/

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

                    $fpCsv = fopen($createDirectory.'/'.$nameOfPdf.'.csv', 'w+');
                    if( isset($meta_values["csv-separate"]) && !empty($meta_values["csv-separate"]) ) { $csvSeparate = esc_html($meta_values["csv-separate"]); } else { $csvSeparate = ','; }
                    foreach ($csvlist as $csvfields) {
                        fputcsv($fpCsv, $csvfields, $csvSeparate);
                    }
                    fclose($fpCsv);

                    // Je copy le CSV genere
                    if( file_exists($createDirectory.'/'.$nameOfPdf.'.csv') ) {
                        copy($createDirectory.'/'.$nameOfPdf.'.csv', $createDirectory.'/'.$nameOfPdf.'-'.get_transient('pdf_uniqueid').'.csv');
                    }


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
            $createDirectory = $this->wpcf7pdf_folder_uploads(esc_html($post['_wpcf7']));
            $uploaded_files = $submission->uploaded_files();

            // On recupere les donnees et le nom du pdf personnalisé
            $meta_values = get_post_meta(esc_html($post['_wpcf7']), '_wp_cf7pdf', true);
            //$nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($post['_wpcf7']));
            $nameOfPdf = get_transient('pdf_name');
            // PDF generé et envoyé
            $disablePDF = 0;

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

                    preg_match_all('`\[([^\]]*)\]`', $meta_values['file_tags'], $contentTagsOnMail, PREG_SET_ORDER, 0);
                    foreach($contentTagsOnMail as $tagsOnMail) {
                        $image_name_mail = '';
                        if( isset($tagsOnMail[1]) && $tagsOnMail[1] != '' && !empty($posted_data[$tagsOnMail[1]]) ) {
                            $image_name_mail = $posted_data[$tagsOnMail[1]];
                            if( isset($image_name_mail) && $image_name_mail!='' ) {
                                
                                $chemin_final2[$tagsOnMail[1]] = esc_url(str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.sanitize_text_field(get_transient('pdf_uniqueid')).'-'.wpcf7_mail_replace_tags($tagsOnMail[0]));
                                $messageText = str_replace('['.$tagsOnMail[1].']', $image_name_mail, $messageText);
                                $messageText = str_replace('[url-'.$tagsOnMail[1].']', $chemin_final2[$tagsOnMail[1]], $messageText);
                            } else {
                                $messageText = str_replace('[url-'.$tagsOnMail[1].']', WPCF7PDF_URL.'images/onepixel.png', $messageText);
                            }
                        }
                    }
                }
                
                // Shortcodes?
                if( isset($meta_values['shotcodes_tags']) && $meta_values['shotcodes_tags']!='') {
                    $tagShortcodes = explode(',', esc_html($meta_values['shotcodes_tags']));
                    $countShortcodes = count($tagShortcodes);
                    for($i = 0; $i < ($countShortcodes);  $i++) {
                        if( stripos($messageText, $tagShortcodes[$i]) !== false ) {
                            $messageText = str_replace($tagShortcodes[$i], do_shortcode($tagShortcodes[$i]), $messageText);
                        }
                    }
                }

                $messageText = str_replace('[reference]', sanitize_text_field(get_transient('pdf_uniqueid')), $messageText);
                $messageText = str_replace('[url-pdf]', str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory ).'/'.$nameOfPdf.'.pdf', $messageText);
                
                $messageText = str_replace('[date]', $dateField, $messageText);
                $messageText = str_replace('[time]', $timeField, $messageText);
               
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
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.pdf') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.pdf');
                }
                if( file_exists($createDirectory.'/'.$nameOfPdf.'.zip') ) {
                    unlink($createDirectory.'/'.$nameOfPdf.'.zip');
                }
                if( !empty($cf7_file_field_name) ) {

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
                }

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
                    $arr = self::wpcf7pdf_autorizeHtml();
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

    static function wpcf7pdf_autorizeHtml() {

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
            'h1' => array(),
            'h2' => array(), 
            'h3' => array(), 
            'h4' => array(),
            'h5' => array(), 
            'h6' => array(),             
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
                'style' => array()
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
            /*'html' => array(
                'lang' => array(),
            ),
            
            'meta' => array( 'charset' => array()),
            'title' => array(),
            'body' => array( 'dir' => array()),*/
        );

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
            $nameOfPdf = $this->wpcf7pdf_name_pdf(esc_html($id));

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
                    $redirect = get_permalink($meta_values['page_next']).'?&id='.$nameOfPdf.'&pdf-reference='.sanitize_text_field(get_transient('pdf_uniqueid'));
                } else {
                    $redirect = get_permalink($meta_values['page_next']).'?&id='.$nameOfPdf.'&pdf-reference='.sanitize_text_field(get_transient('pdf_uniqueid'));
                }
                $displayAddEventList = 1;
            }
            
            // Redirection direct ver le pdf après envoi du formulaire
            if( isset($meta_values["redirect-to-pdf"]) && $meta_values["redirect-to-pdf"]=="true" ) {

                if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'off' ) {
                    $targetPDF = '_tab';
                }
                //$urlRredirectPDF = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory).'/'.$nameOfPdf.'.pdf?ver='.rand();
                $redirectPDF = "/* REDICTION DIRECT */ 
                var string_to_slug = function (str) {
                    str = str.replace(/^\s+|\s+$/g, ''); // trim
                    str = str.toLowerCase();

                    // remove accents, swap ñ for n, etc
                    var from = 'àáäâèéëêìíïîòóöôùúüûñçěščřžýúůďťň·/_,:;';
                    var to   = 'aaaaeeeeiiiioooouuuuncescrzyuudtn------';

                    for (var i=0, l=from.length ; i<l ; i++)
                    {
                        str = str.replace(new RegExp(from.charAt(i), 'g'), to.charAt(i));
                    }

                    str = str.replace('.', '-') // replace a dot by a dash 
                        .replace(/[^a-z0-9 -]/g, '') // remove invalid chars
                        .replace(/\s+/g, '-') // collapse whitespace and replace by a dash
                        .replace(/-+/g, '-') // collapse dashes
                        .replace( /\//g, '' ); // collapse all forward-slashes

                    return str;
                }

                var inputs = event.detail.inputs;
                let text = '".$nameOfPdf."';

                for ( var i = 0; i < inputs.length; i++ ) {
              
                    let result = text.indexOf(inputs[i].name);
                    if ( result > 0 ) {
                        text = string_to_slug(text.replace(inputs[i].name, inputs[i].value));
                        break;
                    }
                }
                ";
                    if( isset($meta_values["redirect-window"]) && $meta_values["redirect-window"] == 'popup' ) {
                        $redirectPDF .= "
        window.open('".str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)."/' + text + '.pdf?ver=".rand()."','text','menubar=no, status=no, scrollbars=yes, menubar=no, width=600, height=900');";
                        } else { 
                        $redirectPDF .= "
        var location = '".str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $createDirectory)."/' + text + '.pdf?ver=".rand()."'; window.open(location, text, '".$targetPDF."');";
                    }
                $redirectPDF .= "
";
                $displayAddEventList = 1;

            }
            
            if ( isset($cf7msm_redirect_urls) && !empty( $cf7msm_redirect_urls ) ) {
                $displayAddEventList = 0;
            }
                    
$js .= '/* REDIRECTION  */
';
$js .= sprintf('location.replace("%1$s");', htmlspecialchars_decode( esc_url( $redirect ) ) );
$js .= '
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