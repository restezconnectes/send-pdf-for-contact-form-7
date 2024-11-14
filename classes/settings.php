<?php


defined( 'ABSPATH' )
	or die( 'No direct load ! ' );


/**
 * Settings Class for Contact Form 7.
 *
 * @link https://madeby.restezconnectes.fr/project/send-pdf-for-contact-form-7/
 * @author Florent Maillefaud <contact at restezconnectes.fr> 
 * @since 1.0.0.3
 * @license GPL3 or later
 */

class WPCF7PDF_settings extends cf7_sendpdf {

    static function update_settings($idForm, $tabSettings, $nameOption = '', $type=0) {

        if( empty($nameOption) || $nameOption =='' ) { return false; }

        if( isset($tabSettings) && is_array($tabSettings) ) {

            $newTabSettings = array();
            foreach($tabSettings as $nameSettings => $valueSettings) {

                if( $type == 3 ) {
                    $newTabSettings[$nameSettings] = wp_strip_all_tags( stripslashes( esc_url_raw($valueSettings) ) );
                } elseif(filter_var($valueSettings, FILTER_VALIDATE_URL)) {
                    $newTabSettings[$nameSettings] = sanitize_url($valueSettings);
                } elseif(filter_var($valueSettings, FILTER_VALIDATE_EMAIL)) {
                    $newTabSettings[$nameSettings] = sanitize_email($valueSettings);
                } elseif($nameSettings == 'generate_pdf' || $nameSettings == 'footer_generate_pdf' || strpos($nameSettings, 'content_addpdf_')!== false ) {
                    $arr = WPCF7PDF_prepare::wpcf7pdf_autorizeHtml();
                    $newTabSettings[$nameSettings] = wp_kses($valueSettings, $arr);
                } else {
                    $newTabSettings[$nameSettings] = sanitize_textarea_field($valueSettings);
                }
                /* 
                 * Vérification des incompatibilités
                 */
                // Si on a désactivé l'insertion dans la BDD:
                if( $nameSettings=='disable-insert' && $valueSettings == 'true') { 
                    // On ne peut pas faire rediriger vers le PDF
                    $newTabSettings['redirect-to-pdf'] = sanitize_textarea_field('false');
                }
                // Si la redirection vers le PDF est active:
                if( $nameSettings=='redirect-to-pdf' && $valueSettings == 'true') { 
                    // On ne peut pas effacer chaque PDF après envoi
                    $newTabSettings['pdf-file-delete'] = sanitize_textarea_field('false');
                }
                // Si on n'est pas dans le dossier /uploads/sendpdfcf7_upload/:
                if( $nameSettings=='pdf-uploads' && $valueSettings == 'false') { 
                    // On ne peut pas effacer tout le contenu du dossier
                    $newTabSettings['pdf-uploads-delete'] = sanitize_textarea_field('false');
                }

            }
            update_post_meta(sanitize_text_field($idForm), $nameOption, $newTabSettings);

            return true;

        } else {
            return false;
        }
        
    }

    static function wpcf7pdf_get_filesystem() {
        static $filesystem;
    
        if ( $filesystem ) {
            return $filesystem;
        }
    
        require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
        require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
    
        $filesystem = new WP_Filesystem_Direct( new StdClass() ); // WPCS: override ok.
    
        // Set the permission constants if not already set.
        if ( ! defined( 'FS_CHMOD_DIR' ) ) {
            define( 'FS_CHMOD_DIR', ( @fileperms( ABSPATH ) & 0777 | 0755 ) );
        }
        if ( ! defined( 'FS_CHMOD_FILE' ) ) {
            define( 'FS_CHMOD_FILE', ( @fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
        }
    
        return $filesystem;
    }

    static function getFontsTab() {
    
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

    static function truncate() {
        global $wpdb;
        $result =  $wpdb->query( "TRUNCATE TABLE ".$wpdb->prefix."wpcf7pdf_files" );
		if($result) {
            return true;
        }
    }

    static function get_list($idForm) {

        global $wpdb;
        if(!$idForm or !$idForm) { die('Aucun formulaire sélectionné !'); }
        $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."wpcf7pdf_files WHERE wpcf7pdf_id_form = %d ", intval($idForm) ), 'OBJECT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if($result) {
            return $result;
        }
    }

    static function drop() {

        global $wpdb;
        $result = $wpdb->query( "DROP TABLE IF EXISTS ".$wpdb->prefix."wpcf7pdf_files" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if($result) {
            return true;
        }
    }

    /**
     * Listing last PDF
     */
    static function wpcf7pdf_listing( $id, $limit = 15 ) {
        
        global $wpdb;
        $result = $wpdb->get_results( $wpdb->prepare("SELECT wpcf7pdf_id, wpcf7pdf_id_form, wpcf7pdf_reference, wpcf7pdf_data, wpcf7pdf_files, wpcf7pdf_files2 FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id_form = %d ORDER BY wpcf7pdf_id DESC LIMIT %d", sanitize_text_field($id),  sanitize_text_field($limit)), 'OBJECT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if($result) {
            return $result;
        } 
        
    }

    static function get( $id ) {

        global $wpdb;

        if(empty($id) || $id=='') { return false; }

        $result =  $wpdb->get_row( $wpdb->prepare("SELECT wpcf7pdf_files FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id = %d LIMIT %d", $id,  1), 'OBJECT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if($result) {
            return $result;
        }
    }

    static function delete($id) {

        global $wpdb;

        if(empty($id) || $id=='') { return false; }

        // Supprime dans la table des PDF 'PREFIX_wpcf7pdf_files'
        $result =  $wpdb->query( $wpdb->prepare("DELETE FROM ". $wpdb->prefix. "wpcf7pdf_files WHERE wpcf7pdf_id = %d LIMIT 1", $id), 'OBJECT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        if($result) {
            return 'true';
        } else {
            return 'false';
        }

    }

}