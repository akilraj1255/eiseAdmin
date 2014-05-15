<?php
header("Content-Type: text/html; charset=UTF-8");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
ob_start();

$title = "A System";

include ("../common/eiseIntra/inc_intra.php");
include ("config.php");

$oSQL = new eiseSQL($DBHOST, $DBUSER, $DBPASS, $DBNAME, false, CP_UTF8);

try {
    $oSQL->connect();
} catch (Exception $e){
    die($e->getMessage());
}

$intra = new eiseIntra($oSQL, Array("collect_keys"=>$collect_keys));

if (!$flagNoAuth) {
        // checking is session available
    $intra->session_initialize();
    if (!$intra->usrID){
       SetCookie("PageNoAuth", $_SERVER["PHP_SELF"].($_SERVER["QUERY_STRING"]!="" ? ("?".$_SERVER["QUERY_STRING"]) : ""));
       header("HTTP/1.0 401 Unauthorized");
       header ("Location: login.php");
       die();
    }
    
    $intra->checkPermissions($oSQL);
}

$intra->readSettings();


$intra->checkLanguage();
if ($intra->local)
    @include "lang.php";

    
$strLocal = $intra->local; //backward-compatibility stuff

include ("../common/eiseIntra/inc_backcomp.php");
?>