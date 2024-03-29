<?php
/**
 *
 * eiseIntra core class
 *
 * Authentication, form elements display, data handling routines, archive/restore functions
 *
 *
 * @package eiseIntra
 * @version 1.0.15
 *
 */


include "inc_config.php";
include "inc_mysqli.php";

/*
$arrJS[] = eiseIntraRelativePath."intra.js";
$arrJS[] = eiseIntraRelativePath."intra_execute.js";

$arrCSS[] = imagesRelativePath."sprites/sprite.css";
$arrCSS[] = eiseIntraRelativePath."intra.css";
$arrCSS[] = commonStuffRelativePath."screen.css";
*/

$arrJS[] = jQueryPath."jquery-1.6.1.min.js";
$arrJS[] = eiseIntraJSPath."intra.js";
$arrJS[] = eiseIntraJSPath."intra_execute.js";

$arrCSS[] = eiseIntraCSSPath.'themes/'.eiseIntraCSSTheme.'/screen.css';
$arrCSS[] = imagesRelativePath."sprites/sprite.css";


class eiseIntra {

public $arrDataTypes = array("integer", "real", "boolean", "text", "binary", "date", "time", "datetime","FK","PK");

public $arrAttributeTypes = array(
    "integer" => 'integer'
    , "real" => 'real'
    , "boolean" => 'checkbox'
    , "text" => 'text'
    , "textarea" => 'text'
//    , "binary" => 'file' #not supported yet for docflow apps
    , "date" => 'date'
    , "datetime" => 'datetime'
//    , "time" => 'time'
    , "combobox" => 'FK'
    , "ajax_dropdown" => 'FK'
    );

private $arrHTML5AllowedInputTypes = 
    Array("color"
        #, "date", "datetime", "datetime-local", "time"
        , "email", "month", "number", "range", "search", "tel", "url", "week", "password", "text");

private $arrClassInputTypes = 
    Array("ajax_dropdown", "date", "datetime", "datetime-local", "time");

const cachePreventorVar = 'nc';
const dataActionKey = 'DataAction';
const dataReadKey = 'DataAction';

static $arrKeyboard = array(
        'EN' =>   'qwertyuiop[]asdfghjkl;\'\\zxcvbnm,./QWERTYUIOP{}ASDFGHJKL:"|ZXCVBNM<>?'
        , 'RU' => 'йцукенгшщзхъфывапролджэёячсмитьбю/ЙЦУКЕНГШЩЗХЪФЫВАПРОЛДЖЭЁЯЧСМИТЬБЮ?'
    );

function __construct($oSQL = null, $conf = Array()){ //$oSQL is not mandatory anymore

    $this->conf = Array(                    //defaults for intra
        'dateFormat' => "d.m.Y" // 
        , 'timeFormat' => "H:i" // 
        , 'decimalPlaces' => "2"
        , 'decimalSeparator' => "."
        , 'thousandsSeparator' => ","
        , 'logofftimeout' => 360 //6 hours
        , 'addEiseIntraValueClass' => true
        , 'keyboards' => 'EN,RU'
 //       , 'flagSetGlobalCookieOnRedirect' = false
    );
    
    $this->conf = array_merge($this->conf, $conf);
    
    
    $arrFind = Array();
    $arrReplace = Array();
    $arrFind[] = '.'; $arrReplace[]='\\.';          
    $arrFind[] = '/'; $arrReplace[]='\\/';          
    $arrFind[] = 'd'; $arrReplace[]='([0-9]{1,2})'; 
    $arrFind[] = 'm'; $arrReplace[]='([0-9]{1,2})';
    $arrFind[] = 'Y'; $arrReplace[]='([0-9]{4})';
    $arrFind[] = 'y'; $arrReplace[]='([0-9]{1,2})';
    $this->conf['prgDate'] = str_replace($arrFind, $arrReplace, $this->conf['dateFormat']);
    $dfm  = preg_replace('/[^a-z]/i','', $this->conf['dateFormat']);
    $this->conf['prgDateReplaceTo'] = '\\'.(strpos($dfm, 'y')===false ? strpos($dfm, 'Y')+1 : strpos($dfm, 'y')+1).'-\\'.(strpos($dfm, 'm')+1).'-\\'.(strpos($dfm, 'd')+1);
    
    $arrFind = Array();
    $arrReplace = Array();            
    $arrFind[] = "."; $arrReplace[]="\\.";
    $arrFind[] = ":"; $arrReplace[]="\\:";
    $arrFind[] = "/"; $arrReplace[]="\\/";
    $arrFind[] = "H"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "h"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "i"; $arrReplace[]="([0-9]{1,2})";
    $arrFind[] = "s"; $arrReplace[]="([0-9]{1,2})";
    $this->conf["prgTime"] = str_replace($arrFind, $arrReplace, $this->conf["timeFormat"]);
    
    $this->conf['UserMessageCookieName'] = eiseIntraUserMessageCookieName ? eiseIntraUserMessageCookieName: 'UserMessage';

    $this->oSQL = $oSQL;

}

/**********************************
   Authentication Routines
/**********************************/
/**
 * Function decodes authstring login:password
 * using current encoding algorithm 
 * (now base64).
 * 
 * @param string $authstring
 *
 * @return array {string $login, string $password}
 */
function decodeAuthString($authstring){

    $auth_str = base64_decode($authstring);
        
    preg_match("/^([^\:]+)\:([\S ]+)$/i", $auth_str, $arrMatches);

    return Array($arrMatches[1], $arrMatches[2]);
}

/**
 * Function that checks authentication with credentials database using selected $method.
 * Now it supports the following methods:
 * 1) LDAP - it checks credentials with specified GLOBAL $ldap_server with GLOBAL $ldap_domain
 * 2) database (or DB) - it checks credentials with database table stbl_user
 * 3) mysql - it checks credentials of MySQL database user supplied with $login and $password parameters
 * Function returns true when authentication successfull, otherwise it returns false and $strError parameter 
 * variable becomes updated with authentication error message.
 *
 * LDAP method was successfully tested with Active Directory on Windows 2000, 2003, 2008, 2008R2 servers.
 * 
 * @param string $login - login name
 * @param string $password - password
 * @param string $strError - error message will be set to this parameter passed by ref
 * @param string $method - authentication method. Can be 'LDAP', 'database'(equal to 'DB'), 'mysql'
 * 
 * @return boolean authentication result: true on success, otherwise false.
 */
function Authenticate($login, $password, &$strError, $method="LDAP"){
    
    $oSQL = $this->oSQL;
    
    switch($method) {
    case "LDAP":    
        GLOBAL $ldap_server;
        GLOBAL $ldap_domain;
        GLOBAL $ldap_dn;
        GLOBAL $ldap_conn, $ldap_anonymous_login, $ldap_anonymous_pass;
        if (preg_match("/^([a-z0-9]+)[\/\\\]([a-z0-9]+)$/i", $login, $arrMatch)){
            $login = $arrMatch[2];
            $ldap_domain = strtolower($arrMatch[1].".e-ise.com");
        } else
            if (preg_match("/^([a-z0-9\_]+)[\@]([a-z0-9\.\-]+)$/i", $login, $arrMatch)){
                $login = $arrMatch[1];
                $ldap_domain = $arrMatch[2];
            }
            
        $ldap_conn = ldap_connect($ldap_server);
        $binding = @ldap_bind($ldap_conn, $ldap_anonymous_login, $ldap_anonymous_pass);
        
        if (!$binding){
            $strError = $this->translate("Connnection attempt to server failed")." ({$ldap_server})";
            $method = "database";
        } else {
            $ldap_login = $login."@".$ldap_domain;
            $ldap_pass = $password;
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
            $binding = ldap_bind($ldap_conn, $ldap_login, $ldap_pass);

           if (!$binding){
                $strError = $this->translate("Bad password or user name");
                return false;
           } else
                return true;
        }
    case "database":
    case "DB":
        if(!$oSQL->connect()){
            $strError = $this->translate("Unable to connect to database");
            return false;
        }
        $sqlAuth = "SELECT usrID FROM stbl_user WHERE usrID='{$login}' AND usrPass='".md5($password)."'";
        $rsAuth = $oSQL->do_query($sqlAuth);
        if ($oSQL->num_rows($rsAuth)==1)
            return true;
        else {
            $strError = $this->translate("Bad password or user name");
            return false;
        }
        break;
    case "mysql":
        try {
            $this->oSQL = new eiseSQL ($_POST["host"], $login, $password, (!$_POST["database"] ? 'mysql' : $_POST["database"]));
        } catch(Exception $e){
            $strError = $e->getMessage();
            return false;
        }
        return true;
    }

    
}

/**
 * This function intialize session with session cookes placed at path set by eiseIntraCookiePath global constant.
 */
function session_initialize(){
   session_set_cookie_params(0, eiseIntraCookiePath);
   session_start();
   $this->usrID = $_SESSION["usrID"];
} 

/**
 * This function checks current user's permissions on currently open script.
 * Also it checks session expiration time, and condition when user is blocked or not in the database.
 * Script name is obtained from $_SERVER['SCRIPT_NAME'] global variable.
 * Permission information is collected from stbl_page_role table and calculated according to user role membership defined at stbl_role_user table.
 * Permissions are calulated in the following way:
 * - if at least one user's role is permitted to do something, it means that this user is permitted to to it.
 * 
 * If user has no permissions to 'Read' the script, function throws header Location: login.php and stops the script.
 * When 'Read' permissions are confirmed for the user, function updates $intra->arrUsrData property with the following data:
 * - all user data from stbl_user table
 * - string pagID - database page ID
 * - string pagTitle - page title in English
 * - string pagTitleLocal - page title in local language
 * - string* FlagRead - always '1'
 * - string* FlagCreate - '0' or '1', as set in database
 * - string* FlagUpdate - '0' or '1', as set in database
 * - string* FlagDelete - '0' or '1', as set in database
 * - string* FlagWrite - '0' or '1', as set in database
 * - array roles - array of role titles in currently selected language
 * - array roleIDs - array of role IDs
 * ---------------
 * (*) - type is 'string' because of PHP function mysql_fetch_assoc()'s nature. It fetches anything like strings despite actual data type in the database.
 *
 * NOTE: Role membership information is collected from stbl_role_user table basing on rluInsertDate timestamp, 
 * it should not be in the future. It is useful when some actions should be temporarily delegated to the other user in case of vacations, illness etc.
 *
 * Page permissions can be set with eiseAdmin's GUI at <database>/Pages menu.
 * Role membership can be set by system's GUI at system's Setting/Access Control menu or <database>/Roles menu of eiseAdmin.
 *
 * @return array $intra->arrUsrData
 */
function checkPermissions(){
   
   $oSQL = $this->oSQL;
   
   GLOBAL $strSubTitle;
   
   // checking user timeout
   if ($_SESSION["last_login_time"]!="" && $strSubTitle != "DEVELOPMENT" ){
      if (mktime() - strtotime($_SESSION["last_login_time"])>60*$this->conf['logofftimeout']) {
          $tt = Date("Y-m-d H:i:s", mktime())." - ".$_SESSION["last_login_time"];
          header("HTTP/1.0 403 Access denied");
          header ("Location: login.php?error=".urlencode($this->translate("Session timeout ($tt). Please re-login.")));
          die();
      }
   }
   
   //checking is user blocked or not?
   $rsUser = $oSQL->do_query("SELECT * FROM stbl_user WHERE usrID='".$_SESSION["usrID"]."'");
   $rwUser = $oSQL->fetch_array($rsUser);
   
   if (!$rwUser["usrID"]){
        header("HTTP/1.0 403 Access denied"); 
        header ("Location: login.php?error=".urlencode($this->translate("Your User ID doesnt exist in master database. Contact system administrator.")));
        die();
   }
   
   if ($rwUser["usrFlagDeleted"]){
        header("HTTP/1.0 403 Access denied");
        header ("Location: login.php?error=".urlencode($this->translate("Your User ID is blocked.")));
        die();
   }
   
   // checking script permissions
   $script_name = preg_replace("/^(\/[^\/]+)/", "", $_SERVER["SCRIPT_NAME"]);
   $sqlCheckUser = "SELECT
             pagID
           , PAG.pagTitle
           , PAG.pagTitleLocal
           , MAX(pgrFlagRead) as FlagRead
           , MAX(pgrFlagCreate) as FlagCreate
           , MAX(pgrFlagUpdate) as FlagUpdate
           , MAX(pgrFlagDelete) as FlagDelete
           , MAX(pgrFlagWrite) as FlagWrite
           FROM stbl_page PAG
           INNER JOIN stbl_page_role PGR ON PAG.pagID=PGR.pgrPageID
           INNER JOIN stbl_role ROL ON PGR.pgrRoleID=ROL.rolID
           LEFT OUTER JOIN stbl_role_user RLU ON ROL.rolID=RLU.rluRoleID
           WHERE PAG.pagFile='$script_name'
               AND (
               (RLU.rluUserID='".strtoupper($_SESSION["usrID"])."'  AND DATEDIFF(NOW(), rluInsertDate)>=0)
               OR
               ROL.rolFlagDefault=1
               )
           GROUP BY PAG.pagID, PAG.pagTitle;";
       //echo $sqlCheckUser;
    $rsChkPerms = $oSQL->do_query($sqlCheckUser);
    $rwPerms = $oSQL->fetch_array($rsChkPerms);
        
    if (!$rwPerms["FlagRead"]){
        header("HTTP/1.0 403 Access denied");
        $errortext = "".$_SERVER["PHP_SELF"].": ".$this->translate("access denied");
        $this->redirect("ERROR: ".$errortext
            , (($_SERVER["HTTP_REFERER"]!="" && !strstr($_SERVER["HTTP_REFERER"], "login.php")) ? $_SERVER["HTTP_REFERER"] : "login.php?error=".urlencode($errortext)));
        die();
    } 
    
    
    $sqlRoles = "SELECT rolID, rolTitle$this->local
       FROM stbl_role ROL
       LEFT OUTER JOIN stbl_role_user RLU ON RLU.rluRoleID=ROL.rolID
       WHERE (RLU.rluUserID = '{$_SESSION["usrID"]}' AND DATEDIFF(NOW(), rluInsertDate)>=0)
          OR rolID='Everyone'";
    $rsRoles = $oSQL->do_query($sqlRoles);
    $arrRoles = Array();
    $arrRoleIDs = Array();
    while ($rwRol = $oSQL->fetch_array($rsRoles)){
        $arrRoles[] = $rwRol["rolTitle$this->local"];
        $arrRoleIDs[] = $rwRol["rolID"];
    }
    $oSQL->free_result($rsRoles); 

    $this->arrUsrData = array_merge($rwUser, $rwPerms);

    $clear_uri = preg_replace('/^'.preg_quote(dirname($_SERVER['PHP_SELF']), '/').'/', '', $_SERVER['REQUEST_URI']);
    if($rwPage = $oSQL->f($oSQL->q("SELECT * FROM stbl_page WHERE pagFile=".$oSQL->e($clear_uri))) ) {
        $this->arrUsrData = array_merge( $this->arrUsrData, $rwPage);  
    }

    $this->arrUsrData["roles"] = $arrRoles;
    $this->arrUsrData["roleIDs"] = $arrRoleIDs;
    $_SESSION["last_login_time"] = Date("Y-m-d H:i:s");
    
    $this->usrID = $this->arrUsrData["usrID"];
    return $this->arrUsrData;
     
}


function redirect($strMessage, $strLocation, $arrConfig = array()){

    $conf = array_merge($this->conf, $arrConfig);
    
    $cookiePath = (!$this->conf['flagSetGlobalCookieOnRedirect']
        ? parse_url($strLocation, PHP_URL_PATH)
        : eiseIntraCookiePath);

    setcookie ( $this->conf['UserMessageCookieName'], $strMessage, 0, $cookiePath );
    header("Location: {$strLocation}");
    die();

}

function backref($urlIfNoReferer){
    
    if (strpos($_SERVER["HTTP_REFERER"], $_SERVER["REQUEST_URI"])===false //if referer is not from itself
        &&
        strpos($_SERVER["HTTP_REFERER"], 'index.php?pane=')===false ) //and not from fullEdit
    {
        SetCookie("referer", $_SERVER["HTTP_REFERER"], 0, $_SERVER["PHP_SELF"]);
        $backref = $_SERVER["HTTP_REFERER"];
    } else {
        $backref = ($_COOKIE["referer"] ? $_COOKIE["referer"] : $urlIfNoReferer);
    }
    return $backref;

}

/**
 * Function outputs JSON-encoded response basing on intra specification and terminates the script.
 * 
 * @param string $status - response status. 'ok' should be set in case of successfull execution
 * @param string $message - status message to be displayed to the user
 * @param varian $data - data to be transmitted
 *
 */
function json($status, $message, $data=null){
    
    header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
    header("Content-type: application/json"); // JSON

    echo json_encode(array('status'=>$status, 'message'=>$message, 'data'=>$data));

    die();

}

function hasUserMessage(){
    if (isset($_COOKIE[$this->conf['UserMessageCookieName']])){
        return true;
    }
    return false;
}

function getUserMessage(){
    $strRet = $_COOKIE[$this->conf['UserMessageCookieName']];
    if($strRet){
        //setcookie($this->conf['UserMessageCookieName'], '', 0, $_SERVER['REQUEST_URI']);
        setcookie($this->conf['UserMessageCookieName'], '', 0, $_SERVER['PHP_SELF']);
        setcookie($this->conf['UserMessageCookieName'], ''); // backward-compatibility
    }
    return $strRet;
}

function getRoleUsers($strRoleName) {
   $sqlRoleUsers = "SELECT rluUserID
       FROM stbl_role ROL
       INNER JOIN stbl_role_user RLU ON RLU.rluRoleID=ROL.rolID
       WHERE rolID='$strRoleName'   AND DATEDIFF(NOW(), rluInsertDate)>=0";
   $rsRole = $this->oSQL->do_query($sqlRoleUsers);
   while ($rwRole = $this->oSQL->fetch_array($rsRole))
      $arrRoleUsers[] = $rwRole["rluUserID"];

   return $arrRoleUsers;
}

function checkLanguage(){
    
    if(isset($_GET["local"])){
        switch ($_GET["local"]){
            case "on":
                SetCookie("l", "Local");
                break;
            case "off":
                SetCookie("l", "en");
                break;
            default:
                break;
        }
        header("Location: ".$_SERVER["PHP_SELF"]);
        die();
    } else 
    if (!isset($_COOKIE["l"]) && preg_match("/(ru|uk|be)/i", $_SERVER["HTTP_ACCEPT_LANGUAGE"])){
        SetCookie("l", "Local");
        $this->local = "Local";
    }

    $this->local = (isset($_COOKIE["l"]) 
        ? ( $_COOKIE["l"]=="en" 
            ? ""
            : "Local")
        : (isset($this->local) 
            ? $this->local 
            : ""));

    $this->conf['local'] = $this->local;

}

function translate($key){
    
    $key = addslashes($key);
    
    if (!isset($this->lang[$key]) && $this->conf['collect_keys'] && $this->local){
        $this->addTranslationKey($key);
    }
    
    return stripslashes(isset($this->lang[$key]) ? $this->lang[$key] : $key);
}

function addTranslationKey($key){
    $oSQL = $this->oSQL;
    $sqlSTR = "INSERT IGNORE INTO stbl_translation (
        strKey
        , strValue
        ) VALUES (
        ".$oSQL->escape_string($key)."
        , '');";
    $oSQL->q($sqlSTR);
}


function readSettings(){
    
    $oSQL = $this->oSQL;
    
    /* ?????????????? ?????????? ?? tbl_setup ? ?????? ============================ BEGIN */
    
    $arrSetup = Array();
    
    $sqlSetup = "SELECT * FROM stbl_setup";

    $rsSetup = $oSQL->do_query($sqlSetup);

    while ($rwSetup = $oSQL->fetch_array($rsSetup)){

        switch ($rwSetup["stpCharType"]){
            case "varchar":
            case "text":
                $arrSetup[$rwSetup["stpVarName"]] = $rwSetup["stpCharValue"];
            break;
            case "numeric":
                if (preg_match("/^[0-9,\.]+$/",$rwSetup["stpCharValue"])) {
                    eval("\$arrSetup['".$rwSetup["stpVarName"]."'] = ".str_replace(",", ".", $rwSetup["stpCharValue"]).";\n");
                }
                break;
        case "boolean":
            switch ($rwSetup["stpCharValue"]){
                case "on":
                case "true":
                case "yes":
                case 1:
                    eval("\$arrSetup['".$rwSetup["stpVarName"]."'] = true; \n");
                    break;
                default:
                    eval("\$arrSetup['".$rwSetup["stpVarName"]."'] = false; \n");
                    break;
            }
            default: 
                $arrSetup[$rwSetup["stpVarName"]] = $rwSetup["stpCharValue"];
                break;
        }
    }
    /* ?????????????? ?????????? ?? tbl_setup ============================ END */
    $this->conf = array_merge($this->conf, $arrSetup);
    
    return $arrSetup;
}

/**
 * Input display functions
 */

/**
 * This function returns HTML for single field
 * If parameter $title is specified, it returns full HTML with container, label and input/text
 * If parameter $name is specified it returns HTML for input/text according to $value parameter
 * else it returns HTML specified in $value parameter.
 */
public function field( $title, $name, $value, $conf=array() ){

    $oSQL = $this->oSQL;

    
    if(in_array($conf['type'], array('row_id', 'hidden')) )
        return '<input type="hidden" name="'.$name.'" id="'.$name.'" value="'.htmlspecialchars($value).'">'."\r\n";

    $html = '';

    if($title!==null) {

        $html .= "<div class=\"eiseIntraField\"".($name!='' ? " id=\"field_{$name}\"" : '').">";

        $title = ($this->conf['auto_translate'] ? $this->translate($title) : $title);

        if(!in_array($conf['type'], array('boolean', 'checkbox')) ) {
            if ($title!==''){
                $html .= "<label".($name!='' ? " id=\"title_{$name}\"" : '').">".htmlspecialchars($title).(
                    trim($title)!='' ? ':' : ''
                  )."</label>";
            }
        } else {
            $html .= "<label></label>";
        }

    }

    if($name){

        switch($conf['type']){

            case "datetime":
            case "date":
                $html .= $this->showTextBox($name
                    , ($conf['type']=='datetime' ? $this->datetimeSQL2PHP($value) : $this->dateSQL2PHP($value)) 
                    , $conf); 
                break;

            case "select":
            case "combobox"://backward-compatibility

                $defaultConf = array();
                $conf  = array_merge($defaultConf, $conf);

                $conf['source'] = self::confVariations($conf, array('source', 'arrValues', 'strTable'));
                $conf['source_prefix'] = self::confVariations($conf, array('source_prefix', 'prefix', 'prfx'));
                if( ( $confVar = self::confVariations($conf, array('defaultText', 'textIfNull', 'strZeroOptnText')) )!==null )
                    $conf['strZeroOptnText'] = $confVar;

                if(!$conf['source'])
                  $conf['source'] = array();

                if (is_array($conf['source'])){
                    $opts = $conf['source'];
                } else {
                    $rsCMB = $this->getDataFromCommonViews(null, null, $conf["source"]
                        , $conf["source_prefix"]
                        , (! ($this->arrUsrData['FlagWrite'] && (isset($conf['FlagWrite']) ? $conf['FlagWrite'] : 1) ) )
                        , (string)$conf['extra']
                        , true
                        );
                    $opts = Array();
                    while($rwCMB = $oSQL->f($rsCMB))
                        $opts[$rwCMB["optValue"]]=$rwCMB["optText"];
                }
                $html .= $this->showCombo($name, $value, $opts, $conf);
                break;

            case "ajax_dropdown":
            case "typeahead":
                $html .= $this->showAjaxDropdown($name, $value, $conf);
                break;

            case "boolean":
            case "checkbox":
                $html .= '<div class="eiseIntraValue eiseIntraCheckbox">';
                $html .= $this->showCheckBox($name, $value, $conf);
                $html .= '<label for="'.$name.'">'.htmlspecialchars($title).'</label>';
                $html .= '</div>';
                break;

            case 'submit':
            case 'delete':
            case 'button':
                $html .= $this->showButton($name, $value, $conf);
                break;

            case "textarea":
                $html .= $this->showTextArea($name, $value, $conf);
                break;

            case 'text':
            default:
                $html .= $this->showTextBox($name, $value, $conf);
                break;
                    
        }

    } else {

        $html .= '<div class="eiseIntraValue">'.$value.'</div>';

    }


    if($title!==null){

        $html .= '</div>'."\r\n\r\n";

    }

    return $html;

}

private function handleClass(&$arrConfig){

    $arrClass = Array();
    if ($this->conf['addEiseIntraValueClass'])
        $arrClass['eiseIntraValue'] = 'eiseIntraValue';
    
    // get contents of 'class' attribute in strAttrib
    $prgClass = "/\s+class=[\"\']([^\"\']+)[\"\']/i";
    $attribs = $arrConfig["strAttrib"];
    if (preg_match($prgClass, $attribs, $arrMatch)){
        $strClass = $arrMatch[1];
        $arrConfig["strAttrib"] = preg_replace($prgClass, "", $arrConfig["strAttrib"]);
    }
    
    // if we specify something in arrConfig, we add it to the string
    if (!is_array($arrConfig["class"])){
        $strClass = ($strClass!="" ? $strClass." " : "").$arrConfig["class"];
    } else {
        $strClass = ($strClass!="" ? $strClass." " : "").implode(" ",$arrConfig["class"]);
    }
    
    // split class sting into array
    $arrClassList = preg_split("/\s+/", $strClass); 
    // remove duplicates using unique key
    foreach($arrClassList as $class) 
        if($class!="")
            $arrClass[$class] =  $class;
    
    $arrConfig["class"] = $arrClass;
    return implode(" ", $arrClass);
}
    
function showTextBox($strName, $strValue, $arrConfig=Array()) {
    
    if(!is_array($arrConfig)){
        $arrConfig = Array("strAttrib"=>$arrConfig);
    }
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $strClass = $this->handleClass($arrConfig);
   
    $strAttrib = $arrConfig["strAttrib"];
    if ($flagWrite){
        
        $strType = (in_array($arrConfig['type'], $this->arrHTML5AllowedInputTypes) ? $arrConfig["type"] : 'text');

        $strClass .= (!in_array($arrConfig['type'], $this->arrHTML5AllowedInputTypes) ? ($strClass!='' ? ' ' : '').'eiseIntra_'.$arrConfig["type"] : '');

        $strRet = "<input type=\"{$strType}\" name=\"{$strName}\" id=\"{$strName}\"".
            ($strAttrib ? " ".$strAttrib : "").
            ($strClass ? ' class="'.$strClass.'"' : "").
            ($arrConfig["required"] ? " required=\"required\"" : "").
            ($arrConfig["placeholder"] 
                ? ' placeholder="'.htmlspecialchars($arrConfig["placeholder"]).'"'
                    .' title="'.htmlspecialchars($arrConfig["placeholder"]).'"'
                : "").
            ($arrConfig["maxlength"] ? " maxlength=\"{$arrConfig["maxlength"]}\"" : "").
       " value=\"".htmlspecialchars($strValue)."\" />";
    } else {
        $strRet = "<div id=\"span_{$strName}\"".
        ($strAttrib ? " ".$strAttrib : "").
        ($strClass ? ' class="'.$strClass.'"' : "").">".
        htmlspecialchars($strValue)."</div>\r\n".
        "<input type=\"hidden\" name=\"{$strName}\" id=\"{$strName}\"".
        " value=\"".htmlspecialchars($strValue)."\" />";
    }
    
   return $strRet;
}

function showTextArea($strName, $strValue, $arrConfig=Array()){
    
    if(!is_array($arrConfig)){
        $arrConfig = Array("strAttrib"=>$arrConfig);
    }

    $strAttrib = $arrConfig["strAttrib"];
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $strClass = $this->handleClass($arrConfig);
    
    if ($flagWrite){
        $strRet .= "<textarea"
            ." id=\"".($arrConfig['id'] ? $arrConfig['id'] : $strName)."\""
            ." name=\"".$strName."\"";
        if($strAttrib) $strRet .= " ".$strAttrib;
        $strRet .= ($strClass ? ' class="'.$strClass.'"' : "").
            ($arrConfig["required"] ? " required=\"required\"" : "").">";
        $strRet .= htmlspecialchars($strValue);
        $strRet .= "</textarea>";
    } else {
        $strRet = "<div id=\"span_{$strName}\"".
            ($strAttrib ? " ".$strAttrib : "").
            ($strClass ? ' class="'.$strClass.'"' : "").">"
                .($arrConfig['href'] ? "<a href=\"{$arrConfig['href']}\"".($arrConfig["target"] ? " target=\"{$arrConfig["target"]}\"" : '').">" : '')
                .htmlspecialchars($strValue)."</div>\r\n"
                .($arrConfig['href'] ? '</a>' : '')
            ."<input type=\"hidden\" name=\"{$strName}\" id=\"{$strName}\""
            ." value=\"".htmlspecialchars($strValue)."\" />\r\n";
    }
    return $strRet;        
    
}

/**
 * showButton() method returns <input type="submit"> or <button> HTML. Input type should be specified in $arrConfig['type'] member. 
 *
 * @return HTML string
 *
 * @param $strName - button name and id, can be empty or null
 * @param $strValue - button label
 * @param $arrConfing - configuration array. The same as for any other form elements. Supported input types ($arrConfig['type'] values) are:
 *      - submit - <input type="submit" class="eiseIntraActionSubmit"> will be returned
 *      - delete - method will return <button class="eiseIntraDelete">
 *      - button (default) - <button> element will be returned
 */
function showButton($strName, $strValue, $arrConfig=array()){

    if(!is_array($arrConfig)){
        $arrConfig = Array("strAttrib"=>$arrConfig);
    }
    


    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $o = $this->conf['addEiseIntraValueClass'];
    $this->conf['addEiseIntraValueClass'] = false;
    $strClass = $this->handleClass($arrConfig);
    $this->conf['addEiseIntraValueClass'] = $o;

    $value = ($this->conf['auto_translate'] ? $this->translate($strValue) : $strValue);

    if($arrConfig['type']=='submit'){
        $strRet = '<input type="submit"'
            .($strName!='' ? ' name="'.htmlspecialchars($name).'" id="'.htmlspecialchars($name).'"' : '')
            .' class="eiseIntraActionSubmit'.($strClass!='' ? ' ' : '').$strClass.'"'
            .(!$flagWrite ? ' disabled' : '')
            .' value="'.htmlspecialchars($value).'">';
    } else {
        if($arrConfig['type']=='delete')
            $strClass = 'eiseIntraDelete'.($strClass!='' ? ' ' : '').$strClass;
        $strRet = '<button'
            .($strName!='' ? ' name="'.htmlspecialchars($name).'" id="'.htmlspecialchars($name).'"' : '')
            .(!$flagWrite ? ' disabled' : '')
            .'>'.$value.'</button>';
    }

    return $strRet;

}

function showCombo($strName, $strValue, $arrOptions, $arrConfig=Array()){
    
    if(!is_array($arrConfig)){
        $arrConfig = Array("strAttrib"=>$arrConfig);
    }
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $retVal = "";
    
    $strClass = $this->handleClass($arrConfig);
    
    $strAttrib = $arrConfig["strAttrib"];
    if ($flagWrite){

        $retVal .= "<select id=\"".$strName."\" name=\"".$strName."\"".$strAttrib.
            ($strClass ? ' class="'.$strClass.'"' : "").
            ($arrConfig["required"] ? " required=\"required\"" : "").">\r\n";
        if ( isset($arrConfig["strZeroOptnText"]) ){
            $retVal .= "<option value=\"\">".htmlspecialchars($arrConfig["strZeroOptnText"])."</option>\r\n" ;
        }
        if (!isset($arrConfig['deletedOptions']))
            $arrConfig['deletedOptions'] = array();
        foreach ($arrOptions as $key => $value){
            if (is_array($value)){ // if there's an optgoup
                $retVal .= '<optgroup label="'.(isset($arrConfig['optgroups']) ? $arrConfig['optgroups'][$key] : $key).'">';
                foreach($value as $optVal=>$optText){
                    $retVal .= "<option value='$optVal'".((string)$optVal==(string)$strValue ? " SELECTED " : "").
                        (in_array($optVal, $arrConfig['deletedOptions']) ? ' class="deleted"' : '').
                        ">".str_repeat('&nbsp;',5*$arrConfig["indent"][$key]).htmlspecialchars($optText)."</option>\r\n";
                }
                $retVal .= '</optgroup>';
            } else
                $retVal .= "<option value='$key'".((string)$key==(string)$strValue ? " SELECTED " : "").
                        (in_array($key, $arrConfig['deletedOptions']) ? ' class="deleted"' : '').
                        ">".str_repeat('&nbsp;',5*$arrConfig["indent"][$key]).htmlspecialchars($value)."</option>\r\n";
        }
        $retVal .= "</select>";

    } else {
        
        foreach ($arrOptions as $key => $value){
            if ((string)$key==(string)$strValue) {
               $valToShow = $value;
               $textToShow = $key;
               break;
            }
        }
        $valToShow=($valToShow!="" ? $valToShow : $arrConfig["strZeroOptnText"]);
        
        $retVal = '<div id="span_{$strName}"'
            .($strClass ? ' class="'.$strClass.'"' : "")
            .'>'
            .($arrConfig['href'] ? "<a href=\"{$arrConfig['href']}\"".($arrConfig["target"] ? " target=\"{$arrConfig["target"]}\"" : '').">" : '')
            .htmlspecialchars($valToShow)
            .($arrConfig['href'] ? '</a>' : '')
            ."</div>\r\n".
        "<input type=\"hidden\" name=\"{$strName}\" id=\"{$strName}\"".
        " value=\"".htmlspecialchars($textToShow)."\" />\r\n";
        
    
    }
    return $retVal;
}

function showCheckBox($strName, $strValue, $arrConfig=Array()){

    if(!is_array($arrConfig)){
        $arrConfig = Array("strAttrib"=>$arrConfig);
    }

    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $strClass = $this->handleClass($arrConfig);
    
    $strAttrib = $arrConfig["strAttrib"];
    $retVal = "<input name=\"{$strName}\" id=\"{$strName}\" type=\"checkbox\"".
    ($strValue ? " checked=\"checked\" " : "").
    (!$flagWrite ? " readonly=\"readonly\"" : "").
    ($strAttrib!="" ? $strAttrib : " style='width:auto;'" ).">";

    return $retVal;
}

function showRadio($strRadioName, $strValue, $arrConfig){
    
    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $oSQL = $this->oSQL;
    
    $retVal = "";
    
    if ($arrConfig["strSQL"]){
        $rs = $oSQL->do_query($arrConfig["strSQL"]);
        while ($rw = $oSQL->fetch_array($rs)){
            $arrData[$rw["optValue"]] = $rw["optText"];
        }
    }
    
    $arrData = $arrConfig["arrOptions"];
    foreach($arrData as $value =>  $text){
        $inpID = $strRadioName."_".$value;
        $retVal .= "<input type=\"radio\" name=\"{$strRadioName}\" value=\"".htmlspecialchars($value)."\"";
        if ($strValue!="" && $value===$strValue)
            $retVal .= " checked";
        else if ($strValue=="" && $value==$arrConfig["strDefaultChecked"])
            $retVal .= " checked";
       $retVal .= " style=\"width:auto;\" id=\"{$inpID}\"".
        ($arrConfig["strAttrib"]!="" ? " ".$arrConfig["strAttrib"] : "").">".
        "<label for=\"{$inpID}\"".($arrConfig["strLabelAttrib"]!="" ? " ".$arrConfig["strLabelAttrib"] : "").">".
        htmlspecialchars($text)."</label><br>\r\n";
   }
   
   return $retVal;
   
}


function showAjaxDropdown($strFieldName, $strValue, $arrConfig) {
    
    if(!is_array($arrConfig)){
        $arrConfig = Array("strAttrib"=>$arrConfig);
    }

    $src = eiseIntra::confVariations($arrConfig, array('source', 'strTable'));
    $prf = eiseIntra::confVariations($arrConfig, array('source_prefix', 'prefix', 'strPrefix'));
    $txt = eiseIntra::confVariations($arrConfig, array('text', 'strText'));

    if(!$src)
        throw new Exception("AJAX drop-down box has no source specified", 1);


    $flagWrite = isset($arrConfig["FlagWrite"]) ? $arrConfig["FlagWrite"] : $this->arrUsrData["FlagWrite"];
    
    $oSQL = $this->oSQL;
    
    if ($strValue!="" && $txt==""){
        $rs = $this->getDataFromCommonViews($strValue, "", $src, $prf);
        $rw = $oSQL->fetch_array($rs);
        $txt = $rw["optText"];
    }
    
    $strOut = "";
    $strOut .= "<input type=\"hidden\" name=\"$strFieldName\" id=\"$strFieldName\" value=\"".htmlspecialchars($strValue)."\">\r\n";
    
    if ($flagWrite){
        $strOut .= $this->showTextBox($strFieldName."_text", $txt
            , array_merge(
                $arrConfig 
                , Array("FlagWrite"=>true
                    , "strAttrib" => $arrConfig["strAttrib"]." src=\"{table:'{$src}', prefix:'{$prf}'}\" autocomplete=\"off\""
                    , 'type'=>"ajax_dropdown")
                )
            );
    } else {
        $strOut .= "<div id=\"span_{$strFieldName}\""
            .($strClass ? ' class="'.$strClass.'"' : "")
            .">"
            .($arrConfig['href'] ? "<a href=\"{$arrConfig['href']}\"".($arrConfig["target"] ? " target=\"{$arrConfig["target"]}\"" : '').">" : '')
            .htmlspecialchars($txt)
            .($arrConfig['href'] ? "</a>" : '')
            ."</div>\r\n";
    }
    
    return $strOut;
    
}

/**
 * Static functions that returns first occurence of configuration array $conf key variations passed as $variations parameter (array).
 *
 * @param $conf associative (configuration) array
 * @param $variations enumerated array of variations
 * 
 * @return $conf array value of first occurence of supplied key variations. NULL if key not found
 *
 * @example echo eiseIntra::confVariations(array('foo'=>'bar', 'foo1'=>'bar1'), array('fee', 'foo', 'fuu', 'fyy'));
 * output: bar
 */
private static function confVariations($conf, $variations){
    $retVal = null;
    foreach($variations as $variant){
        if(isset($conf[$variant])){
            $retVal = $conf[$variant];
            break;
        }
    }
    return $retVal;
}

/**
 * 
 * Page-formatting functions 
 *
 */

/**
 * Function that loads JavaScript files basing on GLOBAL $arrJS
 *
 */
function loadJS(){
    GLOBAL $js_path, $arrJS;
        
        $cachePreventor = self::cachePreventorVar.'='.preg_replace('/\D/', '', $this->conf['version']);
        
        //-----------?????????? ?????????? ???????  $arrJS
        for ($i=0;$i<count($arrJS);$i++){
           echo "<script type=\"text/javascript\" src=\"{$arrJS[$i]}?{$cachePreventor}\"></script>\r\n";
        }
        unset ($i);
        
//------------?????????? ??????? ??? ?????????? ???????? ?? js/*.js
        $arrScript = array_pop(explode("/",$_SERVER["PHP_SELF"]));
        $arrScript = explode(".",$arrScript);
        $strJS = (isset($js_path) ? $js_path : "js/").$arrScript[0].".js";
        if (file_exists( $strJS)) 
            echo "<script type=\"text/javascript\" src=\"{$strJS}\"></script>\r\n";
        
}

/**
 * Function that loads CSS files basing on GLOBAL $arrCSS
 *
 */
function loadCSS(){
    GLOBAL $arrCSS;
    
    $cachePreventor = self::cachePreventorVar.'='.preg_replace('/\D/', '', $this->conf['version']);
    
    for($i=0; $i<count($arrCSS); $i++ ){
        echo "<link rel=\"STYLESHEET\" type=\"text/css\" href=\"{$arrCSS[$i]}?{$cachePreventor}\" media=\"screen\">\r\n";
    }

}

/**
 * Data handling hook function. If $_GET or $_POST ['DataAction'] array member fits contents of $dataAction parameter that can be array or string, 
 * user function $function_name will be called and contents of $_POST or $_GET will be passed as parameters.
 *
 * @param variant $dataAction - string or array of possible <input name=DataAction> values that $function should handle.
 * @param string $function - callback function name.
 * 
 * @return variant value that return user function.
 */
function dataAction($dataAction, $function){
    
    $newData = ($_SERVER['REQUEST_METHOD']=='POST' ? $_POST : $_GET);

    $dataAction = (is_array($dataAction) ? $dataAction : array($dataAction));

    if(in_array($newData[self::dataActionKey], $dataAction)
        && $this->arrUsrData['FlagWrite']
        && is_callable($function))
        return call_user_func($function, $newData);

}

/**
 * Data read hook function. If $query['DataAction'] array member fits contents of $dataReadValues parameter that can be array or string, 
 * user function $function_name will be called and contents of $query parameter will be passed. If $query parameter is omitted, function 
 * will take $_GET global array.
 *
 * @param variant $dataReadValues - string or array of possible <input name=DataAction> values that $function should handle.
 * @param string $function - callback function name.
 * @param array $query - associative array data query  
 * 
 * @return variant value that return user function.
 */
function dataRead($dataReadValues, $function, $query=null){
    
    $query = (is_array($query) ? $query : $_GET);

    $dataReadValues = (is_array($dataReadValues) ? $dataReadValues : array($dataReadValues));

    if(in_array($query[self::dataReadKey], $dataReadValues)
        && is_callable($function))
            return call_user_func($function, $query);

}



/**********************************
   Database Routines
/**********************************/
/**
 * Funiction retrieves MySQL table information with eiseIntra's semantics
 *
 */
function getTableInfo($dbName, $tblName){
    
    $oSQL = $this->oSQL;
    
    $arrPK = Array();

    $rwTableStatus=$oSQL->f($oSQL->q("SHOW TABLE STATUS FROM $dbName LIKE '".$tblName."'"));
    if($rwTableStatus['Comment']=='VIEW' && $rwTableStatus['Engine']==null){
        $tableType = 'view';
    } else {
        $tableType = 'table';
    }

    
    $sqlCols = "SHOW FULL COLUMNS FROM `".$tblName."`";
    $rsCols  = $oSQL->do_query($sqlCols);
    $ii = 0;
    while ($rwCol = $oSQL->fetch_array($rsCols)){
        
        if ($ii==0)
            $firstCol = $rwCol["Field"];
        
        $strPrefix = (isset($strPrefix) && $strPrefix==substr($rwCol["Field"], 0, 3) 
            ? substr($rwCol["Field"], 0, 3)
            : (!isset($strPrefix) ? substr($rwCol["Field"], 0, 3) : "")
            );
        
        if (preg_match("/int/i", $rwCol["Type"]))
            $rwCol["DataType"] = "integer";
        
        if (preg_match("/float/i", $rwCol["Type"])
           || preg_match("/double/i", $rwCol["Type"])
           || preg_match("/decimal/i", $rwCol["Type"]))
            $rwCol["DataType"] = "real";
        
        if (preg_match("/tinyint/i", $rwCol["Type"])
            || preg_match("/bit/i", $rwCol["Type"]))
            $rwCol["DataType"] = "boolean";
        
        if (preg_match("/char/i", $rwCol["Type"])
           || preg_match("/text/i", $rwCol["Type"]))
            $rwCol["DataType"] = "text";
        
        if (preg_match("/binary/i", $rwCol["Type"])
           || preg_match("/blob/i", $rwCol["Type"]))
            $rwCol["DataType"] = "binary";
            
        if (preg_match("/date/i", $rwCol["Type"])
           || preg_match("/time/i", $rwCol["Type"]))
            $rwCol["DataType"] = $rwCol["Type"];
            
        if (preg_match("/ID$/", $rwCol["Field"]) && $rwCol["Key"] != "PRI"){
            $rwCol["FKDataType"] = $rwCol["DataType"];
            $rwCol["DataType"] = "FK";
        }
        
        if ($rwCol["Key"] == "PRI" 
                || preg_match("/^$strPrefix(GU){0,1}ID$/i",$rwCol["Field"])
            ){
            $rwCol["PKDataType"] = $rwCol["DataType"];
            $rwCol["DataType"] = "PK";
        }
        
        if ($rwCol["Field"]==$strPrefix."InsertBy" 
          || $rwCol["Field"]==$strPrefix."InsertDate" 
          || $rwCol["Field"]==$strPrefix."EditBy" 
          || $rwCol["Field"]==$strPrefix."EditDate" ) {
            $rwCol["DataType"] = "activity_stamp"; 
            $arrTable['hasActivityStamp'] = true;
        }
        $arrCols[$rwCol["Field"]] = $rwCol;
        if ($rwCol["Key"] == "PRI"){
            $arrPK[] = $rwCol["Field"];
            if ($rwCol["Extra"]=="auto_increment")
                $pkType = "auto_increment";
            else 
                if (preg_match("/GUID$/", $rwCol["Field"]) && preg_match("/^(varchar)|(char)/", $rwCol["Type"]))
                    $pkType = "GUID";
                else 
                    $pkType = "user_defined";
        }
        $ii++;
    }
    
    if (count($arrPK)==0)
        $arrPK[] = $arrCols[$firstCol]['Field'];
    
    $sqlKeys = "SHOW KEYS FROM `".$tblName."`";
    $rsKeys  = $oSQL->do_query($sqlKeys);
    while ($rwKey = $oSQL->fetch_array($rsKeys)){
      $arrKeys[] = $rwKey;
    }
    
    //foreign key constraints
    $rwCreate = $oSQL->fetch_array($oSQL->do_query("SHOW CREATE TABLE `{$tblName}`"));
    $strCreate = $rwCreate["Create Table"];
    $arrCreate = explode("\n", $strCreate);$arrCreateLen = count($arrCreate);
    for($i=0;$i<$arrCreateLen;$i++){
        // CONSTRAINT `FK_vhcTypeID` FOREIGN KEY (`vhcTypeID`) REFERENCES `tbl_vehicle_type` (`vhtID`)
        if (preg_match("/^CONSTRAINT `([^`]+)` FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/", trim($arrCreate[$i]), $arrConstraint)){
            foreach($arrCols as $idx=>$col){
                if ($col["Field"]==$arrConstraint[2]) { //if column equals to foreign key constraint
                    $arrCols[$idx]["DataType"]="FK";
                    $arrCols[$idx]["ref_table"] = $arrConstraint[3];
                    $arrCols[$idx]["ref_column"] = $arrConstraint[4];
                    break;
                }
            }
            /*
            echo "<pre>";
            print_r($arrConstraint);
            echo "</pre>";
            //*/
        }
    }
    
    $arrColsIX = Array();
    foreach($arrCols as $ix => $col){ $arrColsIX[$col["Field"]] = $col["Field"]; }
    
    $strPKVars = $strPKCond = $strPKURI = '';
    foreach($arrPK as $pk){
        $strPKVars .= "\${$pk}  = (isset(\$_POST['{$pk}']) ? \$_POST['{$pk}'] : \$_GET['{$pk}'] );\r\n";
        $strPKCond .= ($strPKCond!="" ? " AND " : "")."`{$pk}` = \".".(
                in_array($arrCols["DataType"], Array("integer", "boolean"))
                ? "(int)(\${$pk})"
                : "\$oSQL->e(\${$pk})"
            ).".\"";
        $strPKURI .= ($strPKURI!="" ? "&" : "")."{$pk}=\".urlencode(\${$pk}).\"";
    }
    
    $arrTable['columns'] = $arrCols;
    $arrTable['keys'] = $arrKeys;
    $arrTable['PK'] = $arrPK;
    $arrTable['PKtype'] = $pkType;
    $arrTable['prefix'] = $strPrefix;
    $arrTable['table'] = $tblName;
    $arrTable['columns_index'] = $arrColsIX;
    
    $arrTable["PKVars"] = $strPKVars;
    $arrTable["PKCond"] = $strPKCond;
    $arrTable["PKURI"] = $strPKURI;

    $arrTable['type'] = $tableType;

    $arrTable['Comment'] = $rwTableStatus['Comment'];
    
    return $arrTable;
}


function getSQLValue($col, $flagForArray=false){
    $strValue = "";
    
    $strPost = "\$_POST['".$col["Field"]."']".($flagForArray ? "[\$i]" : "");
    
    if (preg_match("/norder$/i", $col["Field"]))
        $col["DataType"] = "nOrder";

    if (preg_match("/ID$/", $col["Field"]))
        $col["DataType"] = "FK";
    
    switch($col["DataType"]){
      case "integer":
        $strValue = "'\".(integer)\$intra->decPHP2SQL($strPost).\"'";
        break;
      case "nOrder":
        $strValue = "'\".($strPost=='' ? \$i : $strPost).\"'";
        break;
      case "real":
      case "numeric":
      case "number":
        $strValue = "'\".(double)\$intra->decPHP2SQL($strPost).\"'";
        break;
      case "boolean":
        if (!$flagForArray)
           $strValue = "'\".($strPost=='on' ? 1 : 0).\"'";
        else
           $strValue = "'\".(integer)\$_POST['".$col["Field"]."'][\$i].\"'";
        break;
      case "binary":
        $strValue = "\".\$oSQL->e(\$".$col["Field"].").\"";
        break;
      case "datetime":
        $strValue = "\".\$intra->datetimePHP2SQL($strPost).\"";
        break;
      case "date":
        $strValue = "\".\$intra->datePHP2SQL($strPost).\"";
        break;
      case "activity_stamp":
        if (preg_match("/By$/i", $col["Field"]))
           $strValue .= "'\$intra->usrID'";
        if (preg_match("/Date$/i", $col["Field"]))
           $strValue .= "NOW()";
        break;
      case "FK":
      case "combobox":
      case "ajax_dropdown":
        $strValue = "\".($strPost!=\"\" ? \$oSQL->e($strPost) : \"NULL\").\"";
        break;
      case "PK":
      case "text":
      case "varchar":
      default:
        $strValue = "\".\$oSQL->e($strPost).\"";
        break;
    }
    return $strValue;
}

function getMultiPKCondition($arrPK, $strValue){
    $arrValue = explode("##", $strValue);
    $sql_ = "";
    for($jj = 0; $jj < count($arrPK);$jj++)
        $sql_ .= ($sql_!="" ? " AND " : "").$arrPK[$jj]."=".$this->oSQL->e($arrValue[$jj])."";
    return $sql_;
}


function getDataFromCommonViews($strValue, $strText, $strTable, $strPrefix, $flagShowDeleted=false, $extra='', $flagNoLimits=false){
    
    $oSQL = $this->oSQL;

    if ($strPrefix!=""){
        $arrFields = Array(
            "idField" => "{$strPrefix}ID"
            , "textField" => "{$strPrefix}Title"
            , "textFieldLocal" => "{$strPrefix}TitleLocal"
            , "delField" => "{$strPrefix}FlagDeleted"
            );
    } else {
        $arrFields = Array(
            "idField" => "optValue"
            , "textField" => "optText"
            , "textFieldLocal" => "optTextLocal"
            , "delField" => "optFlagDeleted"
        );
    }    
    
    $sql = "SELECT `".$arrFields["textField{$this->local}"]."` as optText, `{$arrFields["idField"]}` as optValue
        FROM `{$strTable}`";
    
    if ($strValue!=""){ // key-based search
        $sql .= "\r\nWHERE `{$arrFields["idField"]}`=".$oSQL->escape_string($strValue);
    } else { //value-based search
        $strExtra = '';
        if ($extra!=''){
            $arrExtra = explode("|", $extra);
            foreach($arrExtra as $ix=>$ex){ 
                $ex = trim($ex);
                $strExtra .= ($ex!='' 
                    ? ' AND extra'.($ix==0 ? '' : $ix).' = '.$oSQL->e($ex) 
                    : ''); 
            }
        }

        $arrVariations = self::getKeyboardVariations($strText);
        $sqlVariations = '';
        
        foreach($arrVariations as $layout=>$variation){
            $sqlVariations.= ($sqlVariations=='' ? '' : "\r\nOR")
                ." `{$arrFields["textField"]}` LIKE ".$oSQL->escape_string($variation, "for_search")." COLLATE 'utf8_general_ci' "
                ." OR `{$arrFields["textFieldLocal"]}` LIKE ".$oSQL->escape_string($variation, "for_search")." COLLATE 'utf8_general_ci'";
        }

        $sql .= "\r\nWHERE (\r\n{$sqlVariations}\r\n)"
            .($flagShowDeleted==false ? " AND IFNULL(`{$arrFields["delField"]}`, 0)=0" : "")
            .$strExtra;
    }
    if(!$flagNoLimits)
        $sql .= "\r\nLIMIT 0, 30";
    $rs = $oSQL->do_query($sql);
    
    return $rs;
}

function result2JSON($rs, $arrConf = array()){
    $arrConf_default = array(
        'flagAllowDeny' => 'allow'
        , 'arrPermittedFields' => array() // if 'allow', it contains only closed fields and vice-versa
        , 'arrHref' => array()
        , 'fields' => array()
        , 'flagEncode' => false
        );
    $arrConf = array_merge($arrConf_default, $arrConf);
    $arrRet = array();
    $oSQL = $this->oSQL;
    $arrFields = $oSQL->ff($rs);

    while ($rw = $oSQL->f($rs)){
        $arrRW = array();
        if(isset($arrConf['fieldPermittedFields']) && isset($rw[$arrConf['fieldPermittedFields']])){
            $arrPermittedFields = explode(',', $rw[$arrConf['fieldPermittedFields']]);
        } else {
            $arrPermittedFields = is_array($arrConf['arrPermittedFields']) ? $arrConf['arrPermittedFields'] : array();
        }

        foreach($rw as $key=>$value){

            switch($arrFields[$key]['type']){
                case 'real':
                    $decPlaces = (isset($arrConf['fields'][$key]['decimalPlaces'])
                        ? $arrConf['fields'][$key]['decimalPlaces']
                        : ($arrFields[$key]['decimalPlaces']<6
                            ? $arrFields[$key]['decimalPlaces']
                            : $this->conf['decimalPlaces'])
                        );
                    $arrRW[$key]['v'] = $this->decSQL2PHP($value, $decPlaces);
                    break;
                case 'integer':
                case 'boolean':
                    $arrRW[$key]['v'] = (int)$value;
                    break;
                case 'date':
                    $arrRW[$key]['v'] = $this->dateSQL2PHP($value);
                    break;
                case 'datetime':
                    $arrRW[$key]['v'] = $this->datetimeSQL2PHP($value);
                    break;
                case 'timestamp':
                    $arrRW[$key]['v'] = $this->datetimeSQL2PHP(date('Y-m-d H:i:s', $value));
                    break;
                case 'time':
                default:
                    $arrRW[$key]['v'] = (string)$value;
                    break;
            }


            if (isset($rw[$key.'_text'])){
                $arrRW[$key]['t'] = $rw[$key.'_text'];
                unset($rw[$key.'_text']);
            }

            if (($arrConf['flagAllowDeny']=='allow' && in_array($key, $arrPermittedFields))
                || ($arrConf['flagAllowDeny']=='deny' && !in_array($key, $arrPermittedFields))
                || $arrConf['fields'][$key]['disabled'] || $arrConf['fields'][$key]['static']
                || !$this->arrUsrData['FlagWrite']
                ){

                $arrRW[$key]['rw'] = 'r';

            }

            if (isset($arrConf['arrHref'][$key]) || $arrConf['fields'][$key]['href']){
                $href = ($arrConf['arrHref'][$key] ? $arrConf['arrHref'][$key] : $arrConf['fields'][$key]['href']);
                $target = $arrConf['fields'][$key]['target'];
                foreach ($rw as $kkey => $vvalue){
                    $href = str_replace("[".$kkey."]", (strpos($cell['href'], "[{$rowKey}]")==0 
                                ? $vvalue // avoid urlencode() for first argument
                                : urlencode($vvalue)), $href);
                    $target = str_replace("[".$kkey."]", $vvalue, $target);
                }
                $arrRW[$key]['h'] = $href;
                $arrRW[$key]['rw'] = 'r';
                if ($target) {
                    $arrRW[$key]['tr'] = $target;
                }
            }
        }
        $arrRW_ = $arrRW;
        foreach($arrRW_ as $key=>$v){
            if(isset($arrRW_[$key.'_text'])){
                unset($arrRW[$key.'_text']);
            }
        }

        $arrRet[] = $arrRW;
    }
    return ($arrConf['flagEncode'] ? json_encode($arrRet) : $arrRet);

}

function unq($sqlReadyValue){
    return (strtoupper($sqlReadyValue)=='NULL' ? null : (string)preg_replace("/^(')(.*)(')$/", '\2', $sqlReadyValue));
}

function decPHP2SQL($val, $valueIfNull=null){
    return ($val!=='' 
        ? (double)str_replace($this->conf['decimalSeparator'], '.', str_replace($this->conf['thousandsSeparator'], '', $val))
        : ($valueIfNull===null ? 'NULL' : $valueIfNull)
        );
}

function decSQL2PHP($val, $decimalPlaces=null){
    $decPlaces = ((is_int($var) && $decimalPlaces===null) 
        ? 0 
        : ($decimalPlaces!==null 
            ? $decimalPlaces
            : $intra->conf['decimalPlaces'])
        );
    return (!is_null($val) 
            ? number_format((double)$val, $decimalPlaces, $intra->conf['decimalSeparator'], $intra->conf['thousandsSeparator'])
            : '');
}

function dateSQL2PHP($dtVar, $precision='date'){
$result =  $dtVar ? date($this->conf["dateFormat"].($precision!='date' ? " ".$this->conf["timeFormat"] : ''), strtotime($dtVar)) : "";
return $result ;
}

function datetimeSQL2PHP($dtVar){
$result =  $dtVar ? date($this->conf["dateFormat"]." ".$this->conf["timeFormat"], strtotime($dtVar)) : "";
return $result ;
}

function datePHP2SQL($dtVar, $valueIfEmpty="NULL"){
    $result =  (
        preg_match("/^".$this->conf["prgDate"]."$/", $dtVar) 
        ? "'".preg_replace("/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"], $dtVar)."'" 
        : (
            preg_match('/^[12][0-9]{3}\-[0-9]{2}-[0-9]{2}( [0-9]{1,2}\:[0-9]{2}(\:[0-9]{2}){0,1}){0,1}$/', $dtVar)
            ? "'".$dtVar."'"
            : $valueIfEmpty 
        )
        );
    return $result;
}
function datetimePHP2SQL($dtVar, $valueIfEmpty="NULL"){
    $prg = "/^".$this->conf["prgDate"]."( ".$this->conf["prgTime"]."){0,1}$/";
    $result =  (
        preg_match($prg, $dtVar) 
        ? preg_replace("/".$this->conf["prgDate"]."/", $this->conf["prgDateReplaceTo"], $dtVar) 
        : (
            preg_match('/^[12][0-9]{3}\-[0-9]{2}-[0-9]{2}( [0-9]{1,2}\:[0-9]{2}(\:[0-9]{2}){0,1}){0,1}$/', $dtVar)
            ? $dtVar
            : null 
        )
        );

    return ($result!==null ? "'".date('Y-m-d H:i:s', strtotime($result))."'" : $valueIfEmpty);
}

function getDateTimeByOperationTime($operationDate, $time){

    $stpOperationDayStart = isset($this->conf['stpOperationDayStart']) ? $this->conf['stpOperationDayStart'] : '00:00'; 
    $stpOperationDayEnd = isset($this->conf['stpOperationDayEnd']) ? $this->conf['stpOperationDayEnd'] : '23:59:59'; 
    $tempDate = Date('Y-m-d');
    if (strtotime($tempDate.' '.$stpOperationDayEnd) <= strtotime($tempDate.' '.$stpOperationDayStart)
    // e.g. 1:30 < 7:30, this means that operation date prolongs to next day till $stpOperationDayEnd
        && strtotime($tempDate.' '.$time) <= strtotime($tempDate.' '.$stpOperationDayEnd)
         // and current time less than $stpOperationDayEnd
        ){
            return (Date('Y-m-d',strtotime($operationDate)+60*60*24).' '.$time);
    } else 
        return $operationDate.' '.$time;
}

function showDatesPeriod($trnStartDate, $trnEndDate){
    $strRet = (!empty($trnStartDate)
            ? $this->DateSQL2PHP($trnStartDate)
            : ""
            );
    $strRet .= ($strRet!="" && !empty($trnEndDate) && !($trnStartDate==$trnEndDate)
            ? " - "
            : "");
    $strRet .= (!empty($trnEndDate) && !($trnStartDate==$trnEndDate)
            ? $this->DateSQL2PHP($trnEndDate)
            : ""
            );
    
    return $strRet;
}

function getUserData($usrID){
    $oSQL = $this->oSQL;
    $rs = $this->getDataFromCommonViews($usrID, "", "svw_user", "");
    $rw = $oSQL->fetch_array($rs);
    
    return ($rw["optValue"]!="" 
        ? ($rw["optText{$this->local}"]==""
            ? $rw["optText"]
            : $rw["optText{$this->local}"])
         : $usrID);
}
function getUserData_All($usrID, $strWhatData='all'){
    
    $rsUser = $this->oSQL->q("SELECT * FROM stbl_user WHERE usrID='$usrID'");
    $rwUser = $this->oSQL->f($rsUser);
    
    $key = strtolower($strWhatData);
    
    switch ($key) {
        case "all":
            return $rwUser;
        case "name":
        case "fn_sn":
        case "sn_fn":
            return $rwUser["usrName"];
        case "namelocal":
            return $rwUser["usrNameLocal"];
        case "email":
        case "e-mail":
            return $rwUser["usrEMail"];
        default:
            return $rwUser[$strWhatData];
   }
}


/******************************************************************************/
/* static functions                                                           */
/******************************************************************************/
static function getFullHREF($iframeHREF){
    $prjDir = dirname($_SERVER['REQUEST_URI']);
    $flagHTTPS = preg_match('/^HTTPS/', $_SERVER['SERVER_PROTOCOL']);
    $strURL = 'http'
        .($flagHTTPS ? 's' : '')
        .'://'
        .$_SERVER['SERVER_NAME']
        .($_SERVER['SERVER_PORT']!=($flagHTTPS ? 443 : 80) ? ':'.$_SERVER['SERVER_PORT'] : '')
        .$prjDir.'/'
        .'index.php?pane='.urlencode($iframeHREF);
    return $strURL;
}

/**
* function to obtain keyboard layout variations when user searches something but miss keyboard layout switch
* 
* It takes multibyte UTF-8-encoded string as the parameter, then it searches variations in static property self::$arrKeyboard and returns it as associative array.
*
* @package eiseIntra
* @since 1.0.15
*
* @param   string   $src    Original user input
* @return  array            Associative array of possible string variations, like array('EN'=>'qwe', 'RU'=>'йцу') 
*/

static function getKeyboardVariations($src){
    
    mb_internal_encoding('UTF-8');
    $toRet = array();

    // 1. look for origin layout. if user has mixed layouts in one criteria - it's definitely bullshit
    foreach(self::$arrKeyboard as $layoutSrc=>$keyboardSrc){
        $destToCompare = '';
        $flagAtLeastOneSymbolFound = false;
        for($i=0;$i<mb_strlen($src);$i++){
            $key = mb_substr($src, $i, 1);
            $keyIx = mb_strpos($keyboardSrc, $key);
            if($keyIx!==false){
                $val = mb_substr($keyboardSrc, $keyIx, 1);
                $flagAtLeastOneSymbolFound = true;
            } else 
                $val = $key;
            $destToCompare .= $val;
        }

        // if we've found original layout
        if( $destToCompare == $src && $flagAtLeastOneSymbolFound ){

            $toRet[$layoutSrc] = $src;

            // ... we look for variations
            foreach(self::$arrKeyboard as $layout=>$keyboard){
                if($layout==$layoutSrc) // skip original layout
                    continue;
                $dest = '';
                for($i=0;$i<mb_strlen($src);$i++){
                    $key = mb_substr($src, $i, 1);
                    $keyIx = mb_strpos($keyboardSrc, $key);
                    $val = ($keyIx===false ? $key : mb_substr($keyboard, $keyIx, 1));
                    $dest .= $val;
                }
                if($dest!=$src)
                    $toRet[$layout] = $dest;
            }

            break;

        } 

    }

    if(count($toRet)==0){
        reset(self::$arrKeyboard);
        $toRet = array(key(self::$arrKeyboard)=>$src);
    }

    return $toRet;

}


/******************************************************************************/
/* ARCHIVE/RESTORE ROUTINES                                                   */
/******************************************************************************/

function getArchiveSQLObject(){
    
    if (!$this->conf["stpArchiveDB"])
        throw new Exception("Archive database name is not set. Contact system administrator.");
    
    //same server, different DBs
    $this->oSQL_arch = new sql($this->oSQL->dbhost, $this->oSQL->dbuser, $this->oSQL->dbpass, $this->conf["stpArchiveDB"], false, CP_UTF8);
    $this->oSQL_arch->connect();
    
    return $this->oSQL_arch;
    
}


function archiveTable($table, $criteria, $nodelete = false, $limit = ""){
    
    $oSQL = $this->oSQL;
    
    if (!isset($this->oSQL_arch))
        $this->getArvhiceSQLObject();
    
    $oSQL_arch = $this->oSQL_arch;
    $intra_arch = new eiseIntra($oSQL_arch);
    
    // 1. check table exists in archive DB
    if(!$oSQL_arch->d("SHOW TABLES LIKE ".$oSQL->e($table))){
        // if doesnt exists, we create it w/o indexes, on MyISAM engine
        $sqlGetCreate = "SHOW CREATE TABLE `{$table}`";
        $rsC = $oSQL->q($sqlGetCreate);
        $rwC = $oSQL->f($rsC);
        $sqlCR = $rwC["Create Table"];
        //skip INDEXes and FKs
        $arrS = preg_split("/(\r|\n|\r\n)/", $sqlCR);
        $sqlCR = "";
        foreach($arrS as $ix => $string){
            if (preg_match("/^(INDEX|KEY|CONSTRAINT)/", trim($string))){
                continue;
            }
            $string = preg_replace("/(ENGINE=InnoDB)/", "ENGINE=MyISAM", $string);
            $string = preg_match("/^PRIMARY/", trim($string)) ? preg_replace("/\,$/", "", trim($string)) : $string;
            $sqlCR .= ($sqlCR!="" ? "\r\n" : "").$string;
        }
        $oSQL_arch->q($sqlCR);
        
    }
    
    // if table exists, we check it for missing columns
    $arrTable = $this->getTableInfo($oSQL->dbname, $table);
    $arrTable_arch = $intra_arch->getTableInfo($oSQL_arch->dbname, $table);
    $arrCol_arch = Array();
    foreach($arrTable_arch["columns"] as $col) $arrCol_arch[] = $col["Field"];
    $strFields = "";
    foreach($arrTable["columns"] as $col){
        //if column is missing, we add column
        if (!in_array($col["Field"], $arrCol_arch)){
            $sqlAlter = "ALTER TABLE `{$table}` ADD COLUMN `{$col["Field"]}` {$col["Type"]} ".
                ($col["Null"]=="YES" ? "NULL" : "NOT NULL").
                " DEFAULT ".($col["Null"]=="YES" ? "NULL" : $oSQL->e($col["Default"]) );
            $oSQL_arch->q($sqlAlter);
        }
        
        $strFields .= ($strFields!="" ? "\r\n, " : "")."`{$col["Field"]}`";
        
    }
    
    // 2. insert-select to archive from origin
    // presume that origin and archive are on the same host, archive user can do SELECT from origin
    $sqlIns = "INSERT IGNORE INTO `{$table}` ({$strFields})
        SELECT {$strFields}
        FROM `{$oSQL->dbname}`.`{$table}`
        WHERE {$criteria}".
        ($limit!="" ? " LIMIT {$limit}" : "");
    $oSQL_arch->q($sqlIns);
    $nAffected = $oSQL->a();
    
    // 3. delete from the origin
    if (!$nodelete)
        $oSQL->q("DELETE FROM `{$table}` WHERE {$criteria}".($limit!="" ? " LIMIT {$limit}" : ""));
    
    return $nAffected;
}



}


class eiseException extends Exception  {
function __construct($msg, $level = 0){
    parent::__construct($msg);
}
}







?>