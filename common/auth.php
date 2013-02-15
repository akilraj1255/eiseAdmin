<?php
header("Content-Type: text/html; charset=UTF-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
ob_start();

include ("eiseIntra/inc_intra.php");
include ("config.php");

$js_path = "";
$easyAdmin = true;

function dbAuth(){
   
   GLOBAL $intra;
   $oSQL = $intra->oSQL;
   
    if (!$_SESSION["DBHOST"] && !$_SESSION["usrID"] && !$_SESSION["DBPASS"]){
        dbNoAuth("Bad credentials");
        return false;
    }
   
    $oSQL->dbhost = $_SESSION["DBHOST"];
    $oSQL->dbuser = $_SESSION["usrID"];
    $oSQL->dbpass = $_SESSION["DBPASS"];
    $oSQL->dbname = (!$_SESSION["DBNAME"] ? 'mysql' : $_SESSION["DBNAME"]) ;
   
    try{
        
        $oSQL->connect(); 
        
    } catch(Exception $e){
        
        dbNoAuth($e->getMessage());
        
    }

}

function dbNoAuth($message="Authentication error"){
    
    header("Location: login.php?error=".urlencode($message));
    die();
    
}

$oSQL = new sql($DBHOST, $DBUSER, $DBPASS, $DBNAME, false, CP_UTF8);
$intra = new eiseIntra($oSQL);

$intra->session_initialize();

if (!$flagNoAuth) {
    $arrAuth = dbAuth();
}

$intra->usrID = $intra->oSQL->dbuser;
$intra->arrUsrData["FlagWrite"] = true;

$intra->checkLanguage();
if ($intra->local)
    @include "lang.php";
    
$strLocal = $intra->local; //backward-compatibility stuff
?>