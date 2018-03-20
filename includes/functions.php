<?php

defined( 'ABSPATH' )
	or die( 'No direct load ! ' );


function wpcf7pdf_replace_datetime($id, $type = 'date') {
    
    if( empty($id) ) { die(); }
    
    $date_format = get_option( 'date_format' );
    $hour_format = get_option('time_format');
    
    $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );

    if ( $type == 'time' ) {
        
        if( isset($meta_values['time_format']) && !empty($meta_values['time_format']) ) {
            $field = date_i18n($meta_values['time_format']);
        } else {
            $field = date_i18n($hour_format, current_time('timestamp'));
        }
        
    } else { 
        
        if( isset($meta_values['date_format']) && !empty($meta_values['date_format']) ) {
            $field = date_i18n( $meta_values['date_format'] );
        } else {
            $field = date_i18n( $date_format . ' ' . $hour_format, current_time('timestamp'));
        }
        
    }
    
    return $field;
}

function wpcf7pdf_format_text($id, $text) {
    
    global $post;
    if( empty($id) ) { die(); }
    
    $upload_dir = wp_upload_dir();
    $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );
    
    if( empty( $meta_values["linebreak"] ) or ( isset($meta_values["linebreak"]) && $meta_values["linebreak"] == 'false') ) {
        $text = preg_replace("/(\r\n|\n|\r)/", "<div></div>", $text);
    }
    if( empty($meta_values['image-width']) ) { $imgWidth = 250; } else { $imgWidth = $meta_values['image-width'];  }
    if( empty($meta_values['image-height']) ) { $imgHeight = 250; } else { $imgHeight = $meta_values['image-height'];  }
    
    if ( isset($meta_values["image"]) && $meta_values["image"]!='' ) {
        $imgEntete = '<img src="'.esc_url($meta_values["image"]).'" width="'.$meta_values['image-width'].'" height="'.$meta_values['image-height'].'" />';
    }
    $text = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $text);
    $text = str_replace('[url-pdf]', $upload_dir['url'].'/preview-'.$id.'-'.$_SESSION['pdf_uniqueid'].'.pdf', $text);                
    $text = str_replace('[date]', wpcf7pdf_replace_datetime($id), $text);
    $text = str_replace('[time]', wpcf7pdf_replace_datetime($id, 'time'), $text);
    $text = str_replace('[image]', $imgEntete, $text);
                             
    return $text;
    
}

function wpcf7pdf_name_pdf($id) {

    global $post;

    if( empty($id) ) { die(); }
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
        $getNamePerso = str_replace('[reference]', $_SESSION['pdf_uniqueid'], $getNamePerso );
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

    if( empty($id) ) { die(); }
    $meta_values = get_post_meta( $id, '_wp_cf7pdf', true );

    $upload_dir = wp_upload_dir();

    if( isset($meta_values["pdf-uploads"]) && $meta_values["pdf-uploads"]=='true' ) {

        $newDirectory = $upload_dir['basedir'].'/sendpdfcf7_uploads';
        if( is_dir($newDirectory) == false ) {
            //mkdir($newDirectory, 0755);
            $files = array(
                array(
                    'base' 		=> $upload_dir['basedir'] . '/sendpdfcf7_uploads/',
                    'file' 		=> '.htaccess',
                    'content' 	=> 'Options -Indexes'
                ),
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

function get_list($idForm) {

    global $wpdb;
    if(!$idForm or !$idForm) { die('Aucun formulaire sélectionné !'); }
    $result = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ".$wpdb->prefix."wpcf7pdf_files WHERE wpcf7pdf_id_form = %d ", intval($idForm) ), 'OBJECT' );
    if($result) {
        return $result;
    }
}

function get_byReference($ref) {

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

function wpcf7pdf_generateRandomPassword() {
    $alpha = "abcdefghijklmnopqrstuvwxyz";
    $alpha_upper = strtoupper($alpha);
    $numeric = "0123456789";
    $special = "-+=_,!@$#*%<>[]{}";
    $chars = "";

    $chars = $alpha . $special . $alpha_upper . $numeric . $special;
    $length = 16;

    $len = strlen($chars);
    $pw = '';

    for ($i=0;$i<$length;$i++)
    $pw .= substr($chars, rand(0, $len-1), 1);

    // the finished password
    return 'P[D]F'.str_shuffle($pw);
}