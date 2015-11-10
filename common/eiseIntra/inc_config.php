<?php

error_reporting(7);
$stpExtendedLog = false;

define ("eiseIntraRelativePath", "/".ltrim(str_replace(
    str_replace(DIRECTORY_SEPARATOR, "/", $_SERVER["DOCUMENT_ROOT"])
    , ""
    , str_replace(DIRECTORY_SEPARATOR, "/", dirname(__FILE__)))
    ."/"
    , "/")
);

define ("eiseIntraAbsolutePath", dirname(__FILE__).DIRECTORY_SEPARATOR);
define ("commonStuffRelativePath", dirname(eiseIntraRelativePath)."/");

define ("eiseIntraJSPath",  eiseIntraRelativePath.'js/');
define ("eiseIntraCSSPath",  eiseIntraRelativePath.'css/');
define ("eiseIntraCSSTheme", 'eise');

define ("commonStuffAbsolutePath", dirname(eiseIntraAbsolutePath).DIRECTORY_SEPARATOR);

define ("jQueryPath", commonStuffRelativePath."jquery/");
define ("jQueryUIPath", jQueryPath."jquery-ui-1.11.4.custom/");

define ("jQueryRelativePath", commonStuffRelativePath."jquery/");
define ("jQueryUIRelativePath", jQueryRelativePath."ui/");

define ("jQueryUITheme","redmond");
define ("imagesRelativePath", commonStuffRelativePath."images/");

define ("eiseIntraCookiePath", "/");
define ("eiseIntraCookieExpire", time()+60*60*24*30); // 30 days

define ('eiseIntraUserMessageCookieName', 'UserMessage');

define("prgDT", "/([0-9]{1,2})[\.\-\/]([0-9]{1,2})[\.\-\/]([0-9]{4})/i");
$prgDT = prgDT;
define("prgReplaceTo","\\3-\\2-\\1");

$strSubTitle = "DEVELOPMENT";
$strSubTitleLocal = "Версия для разработки";

//$arrJS[] = "../common/jquery/jquery-1.2.6.js";

$localLanguage = "ru";
$localCountry = "RUS";
$localCurrency = "RUB";
?>