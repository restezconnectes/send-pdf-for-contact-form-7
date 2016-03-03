<?php
/*
Plugin Name: Send PDF for Contact Form 7
Plugin URI: http://restezconectes.fr
Description: Send a PDF with Contact Form 7. It is originally created for Contact Form 7 plugin.
Author: Florent Maillefaud
Version: 0.1
Author URI: http://restezconnectes.fr/
*/

/*  Copyright 2007-2015 Florent Maillefaud (email: contact at restezconectes.fr)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if( !defined( 'WPCF7PDF_VERSION' )) { define( 'WPCF7PDF_VERSION', '0.1' ); }

cf7_sendpdf::instance();

class cf7_sendpdf {
    
    public static $instance;
	private $rendered = array();


	public static function instance() {

		if ( ! self::$instance )
			self::$instance = new self();

		return self::$instance;

	}

	private function __construct() {
     
        /* Version du plugin */
        $option['wpcf7pdf_version'] = WPCF7PDF_VERSION;
        if( !get_option('wpcf7pdf_version') ) {
            add_option('wpcf7pdf_version', $option);
        } else if ( get_option('wpcf7pdf_version') != WPCF7PDF_VERSION ) {
            update_option('wpcf7pdf_version', WPCF7PDF_VERSION);
        }
        // Maybe disable AJAX requests
        add_filter( 'wpcf7_mail_components', array( $this, 'wpcf7pdf_mail_components' ), 10, 3 );
        add_action( 'admin_menu', array( $this, 'wpcf7pdf_add_admin') );
        add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'wpcf7pdf_plugin_actions' ) );
        add_action('init', array( $this, 'wpcf7pdf_session_start') );
        add_action( 'wpcf7_before_send_mail', array( $this, 'wpcf7pdf_send_pdf' ) );
        // Enable localization
		add_action( 'plugins_loaded', array( $this, 'init_l10n' ) );
        register_deactivation_hook(__FILE__, 'wpcf7pdf_uninstall');
        
    }
            
    function init_l10n() {
		load_plugin_textdomain( 'wp-cf7pdf', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
    
    // Add "Réglages" link on plugins page
    function wpcf7pdf_plugin_actions( $links ) {
        $settings_link = '<a href="admin.php?page=wpcf7-send-pdf">'.__('Settings', 'wp-cf7pdf').'</a>';
        array_unshift ( $links, $settings_link );
        return $links;
    }
    function wpcf7pdf_dashboard_html_page() {
        include("wpcf7-send-pdf-admin.php");
    }
    function wpcf7pdf_add_admin() {
    
        $addPDF = add_submenu_page( 'wpcf7',
		__('Options for CF7 Send PDF', 'wp-cf7pdf'),
		__('Send PDF with CF7', 'wp-cf7pdf'),
		'administrator', 'wpcf7-send-pdf',
		array( $this, 'wpcf7pdf_dashboard_html_page') );
        
        wp_enqueue_script('media-upload');
        wp_enqueue_script('thickbox');
        
        wp_register_script('wpcf7-my-upload', plugins_url( 'js/wpcf7pdf-script.js', __FILE__ ), array('jquery','media-upload','thickbox'));
        wp_enqueue_script('wpcf7-my-upload');
        
        // If you're not including an image upload then you can leave this function call out
        wp_enqueue_media();
        
        // Now we can localize the script with our data.
        wp_localize_script( 'wpcf7-my-upload', 'Data', array(
          'textebutton'  =>  __( 'Choose This Image', 'wp-cf7pdf' ),
          'title'  => __( 'Choose Image', 'wp-cf7pdf' ),
        ) );
        
        global $wpdb;        
        $wpdb->ma_table_wpcf7pdf = $wpdb->prefix.'wpcf7pdf_files';
        $wpdb->tables[] = 'ma_table_wpcf7pdf';
        /* Création des tables nécessaires */
        if($wpdb->get_var("SHOW TABLES LIKE '".$wpdb->ma_table_wpcf7pdf."'") != $wpdb->ma_table_wpcf7pdf) {

            $sql .= "CREATE TABLE `".$wpdb->ma_table_wpcf7pdf."` (
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
        return $namePDF;
        
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
            $meta_values = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf', true );
            $meta_fields = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields', true );
            
            // On récupère le dossier upload de WP
            $upload_dir = wp_upload_dir();
            $createDirectory = $upload_dir['basedir'].$upload_dir['subdir'];

            // On va cherche les champ du formulaire
            $meta_tags = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf_fields', true );
        
            // SAVE FORM FIELD DATA AS VARIABLES
            if( isset($meta_values['generate_pdf']) && !empty($meta_values['generate_pdf']) ) {

                $nameOfPdf = $this->wpcf7pdf_name_pdf($post['_wpcf7']);
                
                $text = trim($meta_values['generate_pdf']);
                $text = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $text);
                $text = str_replace('[url-pdf]', $createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $text);
                
                $csvTab = array($_SESSION['pdf_uniqueid']);
                foreach($meta_tags as $ntags => $vtags) {
                    $returnValue = wpcf7_mail_replace_tags($vtags);
                    array_push($csvTab, $returnValue);
                }
                $text = wpcf7_mail_replace_tags( wpautop($text) );
                //error_log(print_r($text)); //not blank, all sorts of stuff
            
                // On génère le PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false') {

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
                    $insertPost = $this->save($post['_wpcf7'], serialize($csvTab), $upload_dir['url'].'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.pdf');
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

                    // Je copy le PDF genere
                    copy($createDirectory.'/'.$nameOfPdf.'-'.$_SESSION['pdf_uniqueid'].'.csv', $createDirectory.'/'.$nameOfPdf.'.csv');

                    
                }
                // END GENERATE CSV
                
                
                //Définition possible de la page de redirection à partir de ce plugin (url relative réécrite).
                if( isset($meta_values['page_next']) && is_numeric($meta_values['page_next']) ) {
                    $redirect = basename(get_permalink($meta_values['page_next']));

                    //Une fois que tout est bon, on lui définie le nouveau mail par la méthode associée à l'object "set_properties".
                    $contact_form->set_properties(array('additional_settings' => "on_sent_ok: \"location.replace('".$redirect."');\"")); 
                }
            }                       
        }
    }

    function wpcf7pdf_mail_components($components, $contact_form, $mail) {
        
        // see : http://plugin144.rssing.com/chan-8774780/all_p511.html
        $submission = WPCF7_Submission::get_instance();
        if( $submission ) {
            $posted_data = $submission->get_posted_data();
            // On récupère le dossier upload de WP
            $upload_dir = wp_upload_dir();
            $createDirectory = $upload_dir['basedir'].$upload_dir['subdir'];
            // on va chercher les options du formulaire
            global $post;

            // On recupere les donnees et le nom du pdf personnalisé
            $meta_values = get_post_meta( $post['_wpcf7'], '_wp_cf7pdf', true );
            $nameOfPdf = $this->wpcf7pdf_name_pdf($post['_wpcf7']);
                
            if ( 'mail' == $mail->name() ) {
                  // do something for 'Mail'

                // Send PDF
                if( isset($meta_values["disable-pdf"]) && $meta_values['disable-pdf'] == 'false' ) {
                    if( isset($meta_values["send-attachment"]) && ($meta_values["send-attachment"] == 'sender' OR $meta_values["send-attachment"] == 'both') ) {
                        $components['attachments'][] = $createDirectory.'/'.$nameOfPdf.'.pdf';
                        //$components['attachments'][] = $createDirectory.'/facture-toilettes.pdf';
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
                        //echo 'Total de '.$nb_lignes.' lignes : <br />';
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
            //error_log(serialize($components['attachments'])); //not blank, all sorts of stuff
            return $components;

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
    
}


?>