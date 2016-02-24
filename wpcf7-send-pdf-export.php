<?php
// This includes gives us all the WordPress functionality
include_once($_SERVER['DOCUMENT_ROOT'].'/wp-load.php' );

if( !$_GET["idform"] ) { exit('erreur'); }

$meta_fields = get_post_meta( $_GET["idform"], '_wp_cf7pdf_fields', true );
//$meta_tags = get_post_meta( $_GET["idform"], '_wp_cf7pdf_fields', true );
$separateur = ";";
if( isset($meta_fields) ) {
        
    $entete = array("reference");
    $lignes = array();
    $pdfFormList = cf7_sendpdf::get_list($_GET["idform"]);
    
    if( isset($pdfFormList) ) {

        foreach($meta_fields as $field) {

            preg_match_all( '#\[(.*?)\]#', $field, $nameField );
            //print_r($nameField);
            $nb=count($nameField[1]); 

            for($i=0;$i<$nb;$i++) { 
                array_push($entete, $nameField[1][$i]);
                //echo $nameField[1][$i];
            }

        }
        /*$csvTab = array($_SESSION['pdf_uniqueid']);
        foreach($meta_fields as $ntags => $vtags) {
            $returnValue = wpcf7_mail_replace_tags($vtags);
            array_push($csvTab, $returnValue);
        }*/
        
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
    
}
//print_r($list);
//print_r($entete);
header("Content-Type: text/csv");
header("Content-disposition: filename=csv_export_".$_GET["idform"].".csv");


// Affichage de la ligne de titre, terminée par un retour chariot
echo implode($separateur, $entete)."\r\n";

foreach( $lignes as $ligne ) {
    echo implode($separateur, $ligne)."\r\n";
    //print_r($ligne);

}
//echo implode($separateur, $list)."\r\n";

exit('');
