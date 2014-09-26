<?php
header("Content-Type: text/html; charset=UTF-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
ob_start();

include "version.php";

include ("eiseIntra/inc_intra.php");
include ("config.php");

$js_path = "";
$easyAdmin = true;

$intra = new eiseIntra(null, array('version'=>$version));
$intra->session_initialize();

if (!$flagNoAuth){
    /*
    echo '<pre>';
    print_r($_SESSION);
    print_r($intra);
    die();
    //*/
    if (!$intra->usrID){

        header("Location: login.php");
        die();
        
    }

    if (!$_SESSION["DBHOST"] && !$_SESSION["DBPASS"]){
        header("Location: login.php?error=".urlencode("Database not specified"));
        die();
    }

    try {

        $intra->oSQL = new eiseSQL($_SESSION["DBHOST"], $intra->usrID, $_SESSION["DBPASS"], (!$_SESSION["DBNAME"] ? 'mysql' : $_SESSION["DBNAME"]));
        $intra->oSQL->connect();
        
    } catch(Exception $e) {
        
        header("Location: login.php?error=".urlencode($e->getMessage()));
        die();
        
    }
    
}
$oSQL = $intra->oSQL;

$intra->arrUsrData["FlagWrite"] = true;

$intra->checkLanguage();
if ($intra->local)
    @include "lang.php";
    
$arrCSS[] = 'eiseAdmin.css';

$strLocal = $intra->local; //backward-compatibility stuff
?>