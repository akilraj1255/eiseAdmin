<?php
include "common/auth.php";

$oSQL->dbname=$_GET["dbName"];
$dbName = $oSQL->dbname;
$tblName = $_GET["tblName"];

function getInsertCode($toGen, $arrTable, $indent=""){
        
        GLOBAL $intra;
        
        $tblName = $arrTable["table"];
       
        $strFields = "";
        $strValues = "";
        foreach($arrTable['columns'] as $i=>$col){
          
            $rn = $prevCol["DataType"]=="activity_stamp" && $col["DataType"]=="activity_stamp" ? "" : "\r\n".$indent."    ";
          
            $strFields .= ($strFields!="" ? $rn.", " : "");
            $strValues .= ($strValues!="" ? $rn.", " : "");
          
            if ($col["DataType"]=="PK"){
                switch($arrTable['PKtype']){
                    case "auto_increment":
                        break;
                    case "GUID":
                    default:
                        $strFields .= $col["Field"];
                        $strValues .= ($arrTable['PKtype']=="GUID" ? "@".$col["Field"] : "'\$".$col["Field"]."'");
                        break;
                }
                continue;
            }
            $strFields .= "`".$col["Field"]."`";
            $strValues .= ($toGen=="INSERT PHP" ? $intra->getSQLValue($col) : " #".$col["Field"]);
          
            $prevCol = $col;
          
        }
        
        $strCode = "";
        
        if ($arrTable['PKtype']=="GUID")
            $strCode .= "SET @".$arrTable['PK'][0]."=UUID();\r\n\r\n";
            
        $strCode .= "INSERT INTO $tblName (\r\n{$indent}    ";
        $strCode .= $strFields;
        $strCode .= "\r\n{$indent}) VALUES (\r\n{$indent}    ";
        $strCode .= $strValues;
        $strCode .= ");";
        
        if ($arrTable['PKtype']=="GUID")
            $strCode .= "\r\n\r\nSELECT @".$arrTable['PK'][0]." as ".$arrTable['PK'][0].";\r\n\r\n";
        
        return $strCode;
}

function getUpdateCode($toGen, $arrTable, $indent=""){
        
        GLOBAL $intra;
        
        $tblName = $arrTable["table"];
       

        $strCode = "UPDATE $tblName SET\r\n".$indent."    ";
        
        $strFields = "";
        $strPKs = $arrTable["PKCond"];
        foreach($arrTable['columns'] as $i=>$col){
          
            if ($col["DataType"]=="activity_stamp" && preg_match("/insert/i",$col["Field"]))
                continue;
          
            $rn = $prevCol["DataType"]=="activity_stamp" && $col["DataType"]=="activity_stamp" ? "" : "\r\n".$indent."    ";
          
            $strFields .= ($strFields!="" ? $rn.", " : "");
            $strFields .= $col["Field"]." = ".($toGen=="UPDATE PHP" ? $intra->getSQLValue($col) : " #".$col["Field"]);
          
            $prevDT = $col["DataType"];
            
            $prevCol = $col;
        }
        
        $strCode .= $strFields;
        $strCode .= "\r\n{$indent}WHERE ".$strPKs;
        
        return $strCode;
}


$arrActions[]= Array ("title" => "Table"
	   , "action" => "table_form.php?dbName=$dbName&tblName=$tblName"
	   , "class"=> "ss_arrow_left"
	);

try{

    if ($tblName!=""  && !in_array($_GET["toGen"], Array("EntTables", "newtable", "MissingFields")))    
       $arrTable = $intra->getTableInfo($dbName, $tblName);

}catch(Exception $e){
    SetCookie("UserMessage", "ERROR:".$e->getMessage());
    header("Location: ".(isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : "about.php"));
    die();
}
    
switch ($_GET["toGen"]){
    case "newtable":
       $strPrefix = preg_replace("/^tbl_/", "", $tblName);
       $arrCstName = explode("_", $strPrefix);
       $nCsts = count($arrCstName);
       for ($i=0;$i<$nCsts;$i++) 
          $arrPrefix[$i] = preg_replace("/([euoai])/", "", $arrCstName[$i]);
       //print_r($arrCstName);
       switch($nCsts){
         case 1:
             $strPrefix = substr($arrPrefix[0], 0, 3);
             break;
         case 2:
             $strPrefix = substr($arrPrefix[0], 0, 1).substr($arrPrefix[1], 0, 2);
             break;
         case 3:
             $strPrefix = substr($arrPrefix[0], 0, 1).substr($arrPrefix[1], 0, 1).substr($arrPrefix[2], 0, 1);
             break;
       }
       
       $strCode = "CREATE TABLE `$tblName` (
  `".$strPrefix."ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `".$strPrefix."TitleLocal` varchar(255) NOT NULL DEFAULT '',
  `".$strPrefix."Title` varchar(255) NOT NULL DEFAULT '',
  `".$strPrefix."FlagDeleted` tinyint(4) NOT NULL DEFAULT '0',
  `".$strPrefix."InsertBy` varchar(50) DEFAULT NULL,
  `".$strPrefix."InsertDate` datetime DEFAULT NULL,
  `".$strPrefix."EditBy` varchar(50) DEFAULT NULL,
  `".$strPrefix."EditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`".$strPrefix."ID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8";
       
       //$oSQL->do_query($strCode);
    
       break;
    
    case "INSERT":
    case "INSERT PHP":
        $strCode  = getInsertCode($_GET["toGen"], $arrTable);
        
        break;
    case "UPDATE":
    case "UPDATE PHP":
        
        $strCode = getUpdateCode($_GET["toGen"], $arrTable);
        
        break;
    case "easyGrid":
        $strCode .= "<?php\r\n";
        $strCode .= "include 'common/auth.php';\r\n\r\n";
        
        $strCode .= "\$DataAction  = (isset(\$_POST['DataAction']) ? \$_POST['DataAction'] : \$_GET['DataAction'] );\r\n\r\n";
        
        $strCode .= 'include "../common/eiseGrid/inc_eiseGrid.php";'."\r\n";
        $strCode .= '$arrJS[] = "../common/eiseGrid/eiseGrid.js";'."\r\n";
        $strCode .= '$arrCSS[] = "../common/eiseGrid/eiseGrid.css";'."\r\n\r\n";
        
        $strCode .= "\$grid".strtoupper($arrTable['prefix'])." = new eiseGrid(\$oSQL
        ,'".$arrTable['prefix']."'
        , Array('arrPermissions' => Array('FlagWrite'=>\$intra->arrUsrData['FlagWrite'])
                , 'strTable' => '".$tblName."'
                , 'strPrefix' => '".$arrTable['prefix']."'
                , 'controlBarButtons' => 'add|moveup|movedown|save'
                )
        );\r\n\r\n";
        
        $strCode .= "\$grid".strtoupper($arrTable['prefix'])."->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => '".($arrTable['PKtype']=="user_defined" ? $arrTable['PK'][0]."_id" : $arrTable['PK'][0])."'
        );\r\n";
        
        for($i=0;$i<count($arrTable['columns']);$i++){
            $col = $arrTable['columns'][$i];
            if ($col["DataType"]=="binary")
               continue;
            if ($col["Extra"]=="auto_increment")
               continue;
            if ($col["DataType"]=="activity_stamp" && $col["Field"]!=$arrTable['prefix']."EditDate")
               continue;
               
           $strCode .= "\$grid".strtoupper($arrTable['prefix'])."->Columns[] = Array(\r\n";    
           
           
           $strCode .= "        'title' => \$intra->translate(\"".($col["Comment"]!="" ? $col["Comment"] : $col["Field"])."\")\r\n";
           $strCode .= "        , 'field' => \"".$col["Field"]."\"\r\n";
           $strCode .= "        , 'type' => \"";
           switch($col["DataType"]){
               case "text":
               case "FK":
               case "PK":
               default:
                  $strCode .= "text";
                  break;
               case "integer":
               case "real":
                  $strCode .= "numeric";
                  break;
               case "boolean":
                    $strCode .= "checkbox";
                    break;
               case "date":
               case "datetime":
                  $strCode .= $col["DataType"];
                  break;
               case "activity_type":
                    $strCode .= "datetime";
                    break;
           }
           $strCode .= "\"\r\n";
           $strCode .= ($col["Field"]==$arrTable['prefix']."EditDate" || $col["DataType"]=="PK"
                                                                        ? "        , 'disabled' => true\r\n" : "");
           $strCode .= ($col["Field"]==$arrTable['prefix']."TitleLocal" ? "        , 'width'=>'50%', 'mandatory' => true\r\n" : "");
           $strCode .= ($col["Field"]==$arrTable['prefix']."Title"      ? "        , 'width'=>'50%', 'mandatory' => true\r\n" : "");
           $strCode .= ");\r\n";
           
        }
        
        $strCode .= "\r\nswitch(\$DataAction){
    case \"update\":
        \$grid".strtoupper($arrTable['prefix'])."->Update();
        SetCookie(\"UserMessage\", \"Data is updated\");
        header(\"Location: \".\$_SERVER[\"PHP_SELF\"]);
        break;
    default:
        break;
}
include('../common/inc-frame_top.php');
?>
<script>
\$(document).ready(function(){  
	eiseGridInitialize();
});
</script>
<h1><?php echo \$intra->arrUsrData[\"pagTitle{\$intra->local}\"]; ?></h1>

<div class=\"panel\">
<?php
\$sql".strtoupper($arrTable['prefix'])." = \"SELECT * FROM ".$tblName."\";
\$rs".strtoupper($arrTable['prefix'])." = \$oSQL->do_query(\$sql".strtoupper($arrTable['prefix']).");
while (\$rw".strtoupper($arrTable['prefix'])." = \$oSQL->fetch_array(\$rs".strtoupper($arrTable['prefix']).")){
    //".($arrTable['PKtype']=="user_defined" 
       ? "\$rw".strtoupper($arrTable['prefix'])."['".$arrTable['PK'][0]."_id'] = \$rw".strtoupper($arrTable['prefix'])."['".$arrTable['PK'][0]."'];" 
       : $arrTable['PK'][0])."
    \$grid".strtoupper($arrTable['prefix'])."->Rows[] = \$rw".strtoupper($arrTable['prefix']).";
}

\$grid".strtoupper($arrTable['prefix'])."->Execute();

include('../common/inc-frame_bottom.php');
?>
";
        /*
        echo "<pre>";
        print_r($arrTable);
        echo "</pre>";
//        */
        
        
        break;      
    case "phpList":
        
        /*
        echo"<pre>";
        print_r($arrTable);
        echo"</pre>";
        //*/
        
        $strCode = "";
        
        $strCode .= "<?php\r\n".
                "include(\"common/auth.php\");\r\n".
                "//\$_DEBUG=true;\r\n".
                "include(\"../common/phpList/inc_phpList.php\");\r\n\r\n".
                "\$listName = \$listName ? \$listName : \"".$arrTable['prefix']."\";\r\n".
                "\$lst = new phpLister(\$listName);\r\n\r\n";
        
        $strCode .= "\$lst->Columns[] = array('title' => \"\"\r\n".
                "        , 'field' => '".implode("_", $arrTable['PK'])."'\r\n".
                (count($arrTable['PK']) > 1 ? 
                "        , 'sql' => \"CONCAT(".implode(", '_', ", $arrTable["PK"]).")\"\r\n" : "").        
                "        , 'PK' => true\r\n".
                "        );\r\n\r\n";
        
        $strCode .= "\$lst->Columns[] = array('title' => \"##\"\r\n".
                "        , 'field' => \"phpLNums\"\r\n".
                "        , 'type' => \"num\"\r\n".
                "        );\r\n\r\n";
                
        for($i=0;$i<count($arrTable['columns']);$i++){
           $col = $arrTable['columns'][$i];
           if ($col["DataType"]=="binary")
               continue;
           if ($col["DataType"]=="activity_stamp")
               continue;
           
           $field = $col["Field"];
           $sql = $col["Field"];
           
           if ($col["DataType"]=="PK"){
              $field = $field."_";
           }
           
           $strCode .= "\$lst->Columns[] = array('title' => \$intra->translate(\"".($col["Comment"]!="" ? $col["Comment"] : $col["Field"])."\")\r\n";
           
           if ($col["DataType"]=="FK"){
               if ( $col["ref_table"]!=""){
                    $arrRefTable = $intra->getTableInfo("", $col["ref_table"]);
                    $strCode .= "        , 'type' => \"combobox\"\r\n";
                    $strCode .= "        , 'source_prefix' => \"{$arrRefTable["prefix"]}\"\r\n";
                    $strCode .= "        , 'source' => \"{$col["ref_table"]}\"\r\n";
                    $strCode .= "        , 'defaultText' => getTranslation(\"Any\")\r\n";
                    $strCode .= "        , 'field' => \"{$col["Field"]}\"\r\n";
                    $strCode .= "        , 'filter' => \"{$col["Field"]}\"\r\n";
                    $strCode .= "        , 'order_field' => \"{$col["Field"]}_Text\"\r\n";
                    $strCode .= "        );\r\n";
                    continue;
               } else {
                    $strType="text";
               }
           }
           
           switch ($col["DataType"]){
               case "datetime":
                  $strType = "date";
                  break;
               case "real":
                  $strType = "money";
                  break;
               case "integer":
                  $strType = "numeric";
                  break;
               case "boolean":
                    $strType = "boolean";
                    break;
               case "FK":
                  $strType = ($col["ref_table"]!="" ? "combobox" : "text");
                  break;
               default:
                  $strType = "text";
                  break;
           }
           
           $strCode .= "        , 'type'=>\"$strType\"\r\n";
           
           $strCode .= "        , 'field' => \"".$field."\"\r\n";
           $strCode .= ($field != $sql
                     ?  "        , 'sql' => \"".$sql."\"\r\n"
                     : ""
                    );
           
           $strCode .= "        , 'filter' => \"".$sql."\"\r\n";
           $strCode .= "        , 'order_field' => \"".$field."\"\r\n";
           
           
           
           if(preg_match("/Title$/i", $field))
              $strCode .= "        , 'width' => \"100%\"\r\n";
           $strCode .= "        );\r\n";
           
        }
        
        if ($arrTable["hasActivityStamp"]){
            //put changed date here
           $strEditByField = $arrTable["prefix"]."EditDate";
           
           $strCode .= "\$lst->Columns[] = array('title' => \"Updated\"\r\n";
           $strCode .= "        , 'type'=>\"date\"\r\n";
           
           $strCode .= "        , 'field' => \"".$strEditByField."\"\r\n";
           $strCode .= "        , 'filter' => \"".$strEditByField."\"\r\n";
           $strCode .= "        , 'order_field' => \"".$strEditByField."\"\r\n";
           $strCode .= "        );\r\n";
        }
        
        $strCode .= "\r\n";
        
        $strCode .= "\$sqlFrom = \"$tblName\";\r\n\r\n";
        
        $strCode .= "if (\$_GET['phpLExcel']){\r\n";
        $strCode .= "   \$lst->Execute (\$oSQL, \$sqlFrom, \$sqlWhere, \"".
        ($strEditByField ? $strEditByField : $arrTable["PK"][0])
        ."\", \"".($strEditByField ? "DESC" : "ASC")."\", 0, true);\r\n";
        $strCode .= "   die();\r\n";
        $strCode .= "}\r\n\r\n";
        
        $strCode .= "if (\$intra->arrUsrData['FlagWrite']){\r\n";
        $strCode .= "    \$arrActions[]= Array ('title' => getTranslation(\"New\")\r\n";
        $strCode .= "       , 'action' => \"".(str_replace("tbl_", "", $tblName))."_form.php\"\r\n";
        $strCode .= "       , 'class' => \"ss_add\"\r\n";
        $strCode .= "    );\r\n";
        $strCode .= "}\r\n\r\n";
        
        $strCode .= "\$arrJS[] = \"../common/phpList/phpList.js\";\r\n";
        $strCode .= "include(\"../common/inc-frame_top.php\");\r\n";
        $strCode .= "?>\r\n\r\n";
        
        $strCode .= "<h1><?php echo \$intra->arrUsrData[\"pagTitle{\$intra->local}\"]; ?></h1>\r\n\r\n";
        
        $strCode .= "<?php\r\n\r\n";
        
        
        $strCode .= "\$lst->Execute (\$oSQL, \$sqlFrom, \$sqlWhere, \"".
        ($strEditByField ? $strEditByField : $arrTable["PK"][0])
        ."\", \"".($strEditByField ? "DESC" : "ASC")."\");\r\n\r\n";
        $strCode .= "include(\"../common/inc-frame_bottom.php\");\r\n";
        $strCode .= "?>";
        
        
        break;
    case "Form":
        //echo "<pre>";
        //print_r($arrTable); die();
        $strCode .= "<?php\r\n";
        $strCode .= "include 'common/auth.php';\r\n\r\n";
        
        foreach($arrTable["PK"] as $pk){
            $strCode .= "\${$pk}  = (isset(\$_POST['{$pk}']) ? \$_POST['{$pk}'] : \$_GET['{$pk}'] );\r\n";
            $pkCond .= ($pkCond!="" ? " AND " : "")."`{$pk}` = \".\$oSQL->e(\${$pk}).\"";
            $pkURI .= ($pkURI!="" ? "&" : "")."{$pk}`=\".urlencode(\${$pk}).\"";
        }
        $strCode .= "\$DataAction  = (isset(\$_POST['DataAction']) ? \$_POST['DataAction'] : \$_GET['DataAction'] );\r\n\r\n";
        
        $strCode .= "\r\nif(\$intra->arrUsrData['FlagWrite']){\r\n";
        $strCode .= "\r\nswitch(\$DataAction){
    case 'update':
        
        \$oSQL->q('START TRANSACTION');
        
        if (\$".$arrTable["PK"][0]."==\"\") {
            \$sqlIns = \"".getInsertCode("INSERT PHP", $arrTable, "                ")."\";
            \$oSQL->q(\$sqlIns);".(
            $arrTable["PKtype"]=="auto_increment" 
                ? "\r\n\$".$arrTable["PK"][0]." = \$oSQL->i();"
                : "")."
        } else {
            \$sqlUpd = \"".getUpdateCode("UPDATE PHP", $arrTable, "                ")."\";
            \$oSQL->q(\$sqlUpd);
        }
        
        \$oSQL->q('COMMIT');
        
        
        SetCookie(\"UserMessage\", \"Data is updated\");
        header(\"Location: \".\$_SERVER[\"PHP_SELF\"].\"?{$pkURI}\");
        die();
        
    case 'delete':
    
        \$oSQL->q('START TRANSACTION');
        \$sqlDel = \"DELETE FROM `{$tblName}` WHERE ".$arrTable["PKCond"]."\";
        \$oSQL->q(\$sqlDel);
        \$oSQL->q('COMMIT');
        SetCookie(\"UserMessage\", \"Data is deleted\");
        header(\"Location: \".preg_replace('/form\.php$/', 'list.php', \$_SERVER[\"PHP_SELF\"]));
        die();
        
    default:
        break;
}
}

\$sql".strtoupper($arrTable['prefix'])." = \"SELECT * FROM `{$tblName}` WHERE ".$arrTable["PKCond"]."\";
\$rs".strtoupper($arrTable['prefix'])." = \$oSQL->do_query(\$sql".strtoupper($arrTable['prefix']).");
\$rw".strtoupper($arrTable['prefix'])." = \$oSQL->fetch_array(\$rs".strtoupper($arrTable['prefix']).");

\$arrActions[]= Array ('title' => 'Back to list'
	   , 'action' => \"".(str_replace("tbl_", "", $tblName))."_list.php\"
	   , 'class'=> 'ss_arrow_left'
	);
\$arrJS[] = jQueryUIRelativePath.'js/jquery-ui-1.8.16.custom.min.js';
\$arrCSS[] = jQueryUIRelativePath.'css/'.jQueryUITheme.'/jquery-ui-1.8.16.custom.css';
include eiseIntraAbsolutePath.'inc-frame_top.php';
?>

<form action=\"<?php  echo \$_SERVER[\"PHP_SELF\"] ; ?>\" method=\"POST\" class=\"eiseIntraForm\">\r\n";
foreach($arrTable["PK"] as $i=>$pk){
    $strCode .= "<input type=\"hidden\" name=\"".$pk."\" value=\"<?php  echo htmlspecialchars(\$".$pk.") ; ?>\">\r\n";
}
$strCode .= "<input type=\"hidden\" name=\"DataAction\" value=\"update\">

<fieldset class=\"eiseIntraMainForm\"><legend><?php echo \$intra->arrUsrData[\"pagTitle{\$intra->local}\"]; ?></legend>\r\n\r\n";
        $i=0;
        foreach($arrTable['columns'] as $ix=>$col){
           if ($col["DataType"]=="PK")
               continue;
           if ($col["DataType"]=="binary")
               continue;
           if ($col["DataType"]=="activity_stamp")
               continue;
           
           
           $strCode .= "<div class=\"eiseIntraField\">\r\n".
            "<label><?php echo \$intra->translate(\"".($col["Comment"]!="" ? $col["Comment"] : $col["Field"])."\"); ?>:</label>";
           $strCode .= "<?php\r\n";
           $strParams ="";
           switch ($col["DataType"]){
               case "FK":
                  
                  if ( $col["ref_table"]!=""){
                        $arrRefTable = $intra->getTableInfo("", $col["ref_table"]);
                        $strCode .= "\$rs = \$intra->getDataFromCommonViews('', '', '{$col["ref_table"]}', '{$arrRefTable["prefix"]}');\r\n";
                    } else {
                        $strCode .= "\$sql = \"SELECT NULL as optValue, NULL as optText\";\r\n";
                        $strCode .= "\$rs = \$oSQL->q(\$sql);\r\n";
                    }
                   $strCode .= "while(\$rw = \$oSQL->f(\$rs)){ \$arrOptions[\$rw['optValue']] = \$rw['optText']; }\r\n";
                   $strCode .=    
                   "echo \$intra->showCombo(\"".$col["Field"]."\", \$rw".strtoupper($arrTable['prefix'])."[\"".$col["Field"]."\"], \$arrOptions
                   , Array('strZeroOptnText'=>\$intra->translate('-- please select')));\r\n";
                  break;
               case "date":
               case "datetime":
                  $strCode .= "echo \$intra->showTextBox(\"".$col["Field"]."\", ".
                  "\$intra->DateSQL2PHP(\$rw".strtoupper($arrTable['prefix'])."[\"".$col["Field"]."\"])".
                  ", Array('type'=>'{$col["DataType"]}'));\r\n";
                  break;
               case "real":
               case "integer":
                  $strCode .= " echo \$intra->showTextBox(\"".$col["Field"]."\", ".
                  "\$rw".strtoupper($arrTable['prefix'])."[\"".$col["Field"]."\"]".
                  ", Array('type'=>'number'));";
                  break;
               case "boolean":
                  $strCode .= " echo \$intra->showCheckBox(\"".$col["Field"]."\", ".
                  "\$rw".strtoupper($arrTable['prefix'])."[\"".$col["Field"]."\"]);";
                  break;
               default:
                  $strCode .= " echo \$intra->showTextBox(\"".$col["Field"]."\", ".
                  "\$rw".strtoupper($arrTable['prefix'])."[\"".$col["Field"]."\"]".
                  ", Array('type'=>'text'));";
                  break;
           }
           $strCode .= "?></div>\r\n\r\n";
        }

$strCode .= "<div class=\"eiseIntraField\">\r\n
<?php 
if (\$intra->arrUsrData[\"FlagWrite\"]) {
 ?>
<label>&nbsp;</label><div class=\"eiseIntraValue\"><input class=\"eiseIntraSubmit\" type=\"Submit\" value=\"Update\">
<?php 
if (\$".$arrTable['PK'][0]."!=\"\" && \$rw".strtoupper($arrTable['prefix'])."[\"".$arrTable['prefix']."DeleteDate\"]==\"\"){
?>
<input type=\"Submit\" value=\"Delete\" class=\"eiseIntraDelete\">
<?php  
  }
}
?></div>

</div>\r\n";

$strCode .= "
</fieldset>
</form>
<script>
$(document).ready(function(){
    eiseIntraInitializeForm();
});
</script>
<?php
include eiseIntraAbsolutePath.'inc-frame_bottom.php';
?>";
        
        break;
    case "MissingFields":
        $entID = $_GET["entID"];
        $rwEnt = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_entity WHERE entID='$entID'"));
        
        $arrCols = Array();
        try{
            $arrTable = $intra->getTableInfo($dbName, $rwEnt["entTable"]);
        }catch(Exception $e){
            $strCode = "DROP TABLE IF EXISTS `{$rwEnt["entTable"]}`;
CREATE TABLE `{$rwEnt["entTable"]}` (
	`{$entID}ID` VARCHAR(50) NOT NULL,
	`{$entID}StatusID` INT UNSIGNED NULL DEFAULT NULL,
	`{$entID}ActionID` VARCHAR(50) NULL DEFAULT NULL,
	`{$entID}ActionLogID` VARCHAR(50) NULL DEFAULT NULL,
	`{$entID}StatusActionLogID` VARCHAR(36) NULL DEFAULT NULL,
	`{$entID}InsertBy` VARCHAR(255) NULL DEFAULT NULL,
	`{$entID}InsertDate` DATETIME NULL DEFAULT NULL,
	`{$entID}EditBy` VARCHAR(255) NULL DEFAULT NULL,
	`{$entID}EditDate` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`{$entID}ID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;";
            $strCode .= "\r\n\r\nDROP TABLE IF EXISTS `{$rwEnt["entTable"]}_log`;
CREATE TABLE `{$rwEnt["entTable"]}_log` (
	`l{$entID}GUID` VARCHAR(36) NOT NULL,
	`l{$entID}InsertBy` VARCHAR(50) NULL DEFAULT NULL,
	`l{$entID}InsertDate` DATETIME NULL DEFAULT NULL,
	`l{$entID}EditBy` VARCHAR(50) NULL DEFAULT NULL,
	`l{$entID}EditDate` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`l{$entID}GUID`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

DROP TABLE IF EXISTS `{$rwEnt["entTable"]}_number`;
CREATE TABLE `tbl_request_number` (
  `n{$entID}ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `n{$entID}InsertDate` datetime DEFAULT NULL,
  PRIMARY KEY (`n{$entID}ID`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;

";


        }
        
        for($i=0; $i<count($arrTable["columns"]); $i++)
            $arrCols[] = $arrTable["columns"][$i]["Field"];
            //echo "<pre>";
            //print_r($arrTable);
            //print_r($arrCols);
            //echo "</pre>";
        $sqlMsf = "SELECT * FROM stbl_attribute WHERE atrEntityID='$entID' 
        ORDER BY atrOrder";
        //echo $sqlMsf;
        $strCodeMaster = "";
        $strCodeLog = "";
        $rsMsf = $oSQL->do_query($sqlMsf);
        while($rwMsf = $oSQL->fetch_array($rsMsf)){
            if (!in_array($rwMsf["atrID"], $arrCols)){
                $colName = $rwMsf["atrID"];
                $strCodeMaster .= ($strCodeMaster != "" ? "\r\n, " : "")."\tADD COLUMN $colName ";
                $strCodeLog .= ($strCodeLog != "" ? "\r\n, " : "")."\tADD COLUMN l{$colName} ";
                switch($rwMsf["atrType"]){
                    case "date":
                        $strType = "DATE";
                        break;
                    case "datetime":
                        $strType = "DATETIME";
                        break;
                    case "numeric":
                        $strType = "DOUBLE";
                        break;
                    case "integer":
                        $strType = "INT";
                        break;
                    case "money":
                        $strType = "DECIMAL(10,2)";
                        break;
                    case "combobox":
                    case "ajax_dropdown":
                        $strType = "VARCHAR(50)";
                        break;
                    default:
                       $strType = "VARCHAR(1024)";
                       break;
                }
                $strCodeMaster .= "{$strType} NULL DEFAULT NULL";
                $strCodeLog .= "{$strType} NULL DEFAULT NULL";
            }
            
            $lastColName = $colName;
            
            $strUOMCol = $rwMsf["atrID"]."_uomID";
            if (!empty($rwMsf["atrUOMTypeID"]) && !in_array($strUOMCol, $arrCols)){
                 $strCodeMaster .= "\r\n, ADD COLUMN {$strUOMCol} VARCHAR(10) NULL DEFAULT NULL AFTER {$rwMsf["atrID"]}";
                 $strCodeLog .= "\r\n, ADD COLUMN l{$strUOMCol} VARCHAR(10) NULL DEFAULT NULL AFTER l{$rwMsf["atrID"]}";
                 $lastColName = $strUOMCol;
            }
        }
        
        if ($strCodeMaster!=""){
            
            $strCodeMaster .= "\r\n, CHANGE ".$entID."InsertBy ".$entID."InsertBy VARCHAR(255) NULL DEFAULT NULL  AFTER {$lastColName}";
            $strCodeMaster .= "\r\n, CHANGE ".$entID."InsertDate ".$entID."InsertDate DATETIME NULL DEFAULT NULL AFTER ".$entID."InsertBy";
            $strCodeMaster .= "\r\n, CHANGE ".$entID."EditBy ".$entID."EditBy VARCHAR(255) NULL DEFAULT NULL AFTER ".$entID."InsertDate";
            $strCodeMaster .= "\r\n, CHANGE ".$entID."EditDate ".$entID."EditDate DATETIME NULL DEFAULT NULL AFTER ".$entID."EditBy";
            
            $strCodeLog .= "\r\n, CHANGE l".$entID."InsertBy l".$entID."InsertBy VARCHAR(255) NULL DEFAULT NULL  AFTER l{$lastColName}";
            $strCodeLog .= "\r\n, CHANGE l".$entID."InsertDate l".$entID."InsertDate DATETIME NULL DEFAULT NULL AFTER l".$entID."InsertBy";
            $strCodeLog .= "\r\n, CHANGE l".$entID."EditBy l".$entID."EditBy VARCHAR(255) NULL DEFAULT NULL AFTER l".$entID."InsertDate ";
            $strCodeLog .= "\r\n, CHANGE l".$entID."EditDate l".$entID."EditDate DATETIME NULL DEFAULT NULL AFTER l".$entID."EditBy ";
            
            $strCode .= "\r\n\r\nALTER TABLE ".$rwEnt["entTable"]."\r\n".$strCodeMaster.";";
            $strCode .= "\r\n\r\nALTER TABLE ".$rwEnt["entTable"]."_log\r\n".$strCodeLog.";";
        } else {
            $strCode .= "--no fields added";
        }
        
        break;
    case "EntTables":
        $entID = $_GET["entID"];
        $rwEnt = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_entity WHERE entID='$entID'"));
        $strTBL = $rwEnt["entTable"];
        $strLTBL = $rwEnt["entTable"]."_log";
        
        $strCode = "DROP TABLE IF EXISTS `{$strTBL}`;\r\n";;
        
        //collect attributes
        $strFields = "";
        $strFieldsMaster = "";
        $arrATR = Array();
        $arrFields = Array();
        $sqlATR = "SELECT * FROM stbl_attribute WHERE atrEntityID='{$entID}' ORDER BY atrOrder";
        $rsATR = $oSQL->do_query($sqlATR);
        while ($rwATR = $oSQL->fetch_array($rsATR)) {
            switch ($rwATR["atrType"]){
                case "boolean":
                    $strType = "INT";
                    break;
                case "numeric":
                    $strType = "DECIMAL";    
                    break;
                case "date":
                case "datetime":
                    $strType = $rwATR["atrType"];
                    break;
                case "text":
                case "textarea":
                    $strType = "LONGTEXT";
                    break;
                case "combobox":
                case "ajax_dropdown":
                    $strKeysMaster .= "\r\n, KEY `IX_".$rwATR["atrID"]."` (`".$rwATR["atrID"]."`)";
                    $strKeys .= "\r\n, KEY `IX_l".$rwATR["atrID"]."` (`l".$rwATR["atrID"]."`)";
                    $strType = "VARCHAR(36)";
                    break;
                case "varchar":
                default:                    
                    $strType = "VARCHAR(1024)";
                    break;
            }
            $strFieldsMaster .= "\r\n, `{$rwATR["atrID"]}` {$strType} DEFAULT NULL COMMENT ".$oSQL->escape_string($rwATR["atrTitle"]);
            $strFields .= "\r\n, `l{$rwATR["atrID"]}` {$strType} DEFAULT NULL COMMENT ".$oSQL->escape_string($rwATR["atrTitle"]);
         }
        //create master table
        $strCode .= "\r\nCREATE TABLE `{$strTBL}` (
              `{$entID}ID` VARCHAR(36) NOT NULL
               ,`{$entID}StatusID` INT(11) NOT NULL DEFAULT '0'
               ,`{$entID}ActionLogID` VARCHAR(36) NULL DEFAULT NULL
               ,`{$entID}StatusActionLogID` VARCHAR(36) NULL DEFAULT NULL
               ,`{$entID}StatusLogID` VARCHAR(36) NULL DEFAULT NULL
              {$strFieldsMaster}
              , `{$entID}InsertBy` varchar(50) DEFAULT NULL
              , `{$entID}InsertDate` datetime DEFAULT NULL
              , `{$entID}EditBy` varchar(50) DEFAULT NULL
              , `{$entID}EditDate` datetime DEFAULT NULL
              , PRIMARY KEY (`{$rwEnt["entID"]}ID`)
              , INDEX `IX_{$entID}StatusID` (`{$entID}StatusID`)
              , INDEX `IX_{$entID}ActionLogID` (`{$entID}ActionLogID`)
              , INDEX `IX_{$entID}StatusLogID` (`{$entID}StatusLogID`)
              ".($strKeysMaster!="" ? $strKeysMaster : "")."
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;\r\n";
        
        //create log table
        $strCode .= "\r\nDROP TABLE IF EXISTS `{$strLTBL}`;";
        $strCode .= "\r\nCREATE TABLE `{$strLTBL}` (
              `l{$entID}GUID` VARCHAR(36) NOT NULL
              , `l{$entID}ID` VARCHAR(36) NOT NULL
              {$strFields}
              , `l{$entID}InsertBy` varchar(50) DEFAULT NULL
              , `l{$entID}InsertDate` datetime DEFAULT NULL
              , `l{$entID}EditBy` varchar(50) DEFAULT NULL
              , `l{$entID}EditDate` datetime DEFAULT NULL
              , PRIMARY KEY (`l{$rwEnt["entID"]}GUID`)
              ".($strKeys!="" ? $strKeys : "")."
            ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8;\r\n";
            
        break;
    case "ATV2MT":
        
        $entID = $_GET["entID"];
        $rwEnt = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_entity WHERE entID='$entID'"));
        
        
        
        //collect attributes
        $strFields = "";
        $arrATR = Array();
        $arrFields = Array();
        $arrCheckMask = Array();
        $sqlATR = "SELECT * FROM stbl_attribute INNER JOIN
            (
            SELECT atvEntityID, atvAttributeID 
            FROM stbl_attribute_value 
            GROUP BY atvAttributeID, atvEntityID
            ) as t1 ON atrID=atvAttributeID AND atrEntityID=atvEntityID
            WHERE atrEntityID='{$entID}'";
            
        $strCode = "UPDATE IGNORE `{$rwEnt["entTable"]}` SET";
        $strFieldValue = "";
        $rsATR = $oSQL->do_query($sqlATR);
            while ($rwATR = $oSQL->fetch_array($rsATR)) {
                
                $strFieldValue .= ($strFieldValue!="" ? "\r\n, " : "");
                $strFieldValue .= " `{$rwATR["atrID"]}`=IFNULL((SELECT ";
                $strFieldValue .= ($rwATR["atrType"]=="easyGrid"
                    ? "GROUP_CONCAT((CASE WHEN atvValue='' THEN NULL ELSE atvValue END) SEPARATOR '\\r\\n')"
                    : "MAX(CASE WHEN atvValue='' THEN NULL ELSE atvValue END)");
                $strFieldValue .= " as atvValue FROM stbl_attribute_value 
                    WHERE atvEntityID='{$entID}' AND atvEntityItemID={$entID}ID AND atvAttributeID='{$rwATR["atrID"]}'
                    GROUP BY atvEntityItemID, atvEntityID, atvAttributeID), {$rwATR["atrID"]})";
            }
            
            
            $strCode .= $strFieldValue;
            
        break;
	case "EntityReport":
        $entID = $_GET["entID"];
        $rwEnt = $oSQL->fetch_array($oSQL->do_query("SELECT * FROM stbl_entity WHERE entID='$entID'"));
        
        $strLocal = "Local";
        
        $strHTML = "";
        
        $strHTML .= "<H1>Сущность &quot;{$rwEnt["entTitle$strLocal"]}&quot;</H1>\r\n\r\n";
        
        /* statuses */ 
        $strHTML .= "<h2>Состояния</h2>\r\n<p>Доступны следующие состояния:";
        $sqlSta = "SELECT * FROM stbl_status WHERE staEntityID='{$entID}' ORDER BY staID";
        $rsSta = $oSQL->do_query($sqlSta);
        $strHTML .= "<ul>\r\n";
        while ($rwSta = $oSQL->fetch_array($rsSta)){
            $strHTML .= "<li><b>{$rwSta["staID"]}: {$rwSta["staTitle$strLocal"]}</b><br><br></li>\r\n";
            $strHTML .= "<blockquote><b>Доступные для редактирования атрибуты:</b><br>\r\n";
            $sqlSat = "SELECT * FROM stbl_status_attribute 
               INNER JOIN stbl_attribute ON satAttributeID=atrID AND satEntityID=atrEntityID
               WHERE satStatusID='{$rwSta["staID"]}' AND satEntityID='{$entID}' AND satFlagEditable=1
               ORDER BY atrOrder";
            $rsSat = $oSQL->do_query($sqlSat);
            while ($rwSat = $oSQL->fetch_array($rsSat)){
               $strHTML .= " - {$rwSat["atrTitle$strLocal"]}<br />\r\n";
            }
            $strHTML .= "</blockquote>\r\n";    
            
            $strHTML .= "<blockquote><b>Доступные действия:</b><ol>\r\n";
            $sqlAct = "SELECT * FROM stbl_action_status
                INNER JOIN stbl_action ON atsActionID=actID
               WHERE atsOldStatusID='{$rwSta["staID"]}' AND actEntityID='{$entID}'
               ORDER BY actPriority";
            $rsAct = $oSQL->do_query($sqlAct);
            while ($rwAct = $oSQL->fetch_array($rsAct)){
               $strHTML .= "<li><b>{$rwAct["actTitle$strLocal"]}</b></li><br><br>Обязательные атрибуты:<br><i>\r\n";
               $sqlAAT = "SELECT *
                FROM stbl_action_attribute INNER JOIN stbl_attribute ON atrID=aatAttributeID 
                WHERE atrEntityID='{$entID}' AND aatActionID='{$rwAct["actID"]}'
                ORDER BY atrOrder";
                $rsAAT = $oSQL->do_query($sqlAAT);
                while ($rwAAT = $oSQL->fetch_array($rsAAT)){
                    $strHTML .= "- {$rwAAT["atrTitle$strLocal"]}".($rwAAT["aatFlagToPush"] ? ", запись" : "").
                        ($rwAAT["aatFlagTimestamp"] ? ", <tt>{$rwAAT["aatFlagTimestamp"]}</tt>" : "")."<br>\r\n";
                }
                $strHTML .= "</i><br><br>";
            }
            $strHTML .= "</ol></blockquote>\r\n";   
        }
        $strHTML .= "</ul>\r\n";
        $strHTML .= "</p>\r\n";
        
        
        break;
        
    case "table_Description":
        
        $strHTML ="<h3>Table &quot;{$arrTable["table"]}&quot;</h3>
        <style>td, th {border: 1px solid black; font-size: 10pt;vertical-align: top;} table {border-collapse: collapse;}</style>
        <table>
        <thead>
        <tr>
        <th>##</th>
        <th>Field</th>
        <th>Type/length</th>
        <th>Designation</th>
        <th>Description</th>
        </tr>
        </thead>
        <tbody>";
        
        $iCounter = 0;
        /*
        echo "<pre>";
        print_r($arrTable);
        echo "</pre>";
        */
        foreach($arrTable["columns"] as $col){
            $strHTML .= "<tr>
            <td>".($iCounter+1).".</td>
            <td>".$col["Field"]."</td>
            <td>".$col["Type"]."</td>
            <td>".$col["DataType"]."</td>
            <td>".$col["Comment"]."&nbsp;</td>
            </tr>";
            $iCounter++;
        }
        
        
        $strHTML .= "</tbody>
        </table>";
        
        break;
        
        
	case "StatusLogCheck":
        
        for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
        ob_implicit_flush(1);
        echo str_repeat(" ", 256)."<pre>"; ob_flush();
        
        $sqlENT = "SELECT * FROM {$_GET["tblName"]}";
        $rsENT = $oSQL->do_query($sqlENT);
        
        $ii = 0;
        $entID = $_GET["entID"];
        $oSQL->do_query("DELETE FROM stbl_status_log WHERE stlEntityID='{$entID}'");
        
        while ($rwENT=$oSQL->fetch_array($rsENT)){
            
            $ii++;
            
            //if ($ii<5000 || $ii>6000)
            //    continue;
            
            ob_flush();
            
            $entItemID = $rwENT[$_GET["entID"]."ID"];
            
            echo "Shipment '{$entItemID}': ";
            updateStatusLog($entItemID, "0", $rwENT[$_GET["entID"]."InsertDate"]);
            echo "\r\n";
            
        }
        
        
        echo "</pre>";
        ob_flush();
        die();
        break;    
}

if ($strHTML){
   echo $strHTML;
   die();
}

include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<h1><?php  echo $_GET["toGen"] ; ?> code for <?php  echo $tblName ; ?></h1>

<div class="panel">
<?php 
if ($strCode!=""){
 ?>
<textarea name='code' style="width:100%;height:600px;font-family:Courier New;font-size:12px;"><?php  echo htmlspecialchars($strCode) ; ?></textarea>
<?php 
} 
 ?>
</div>

<?php echo $strHTML ?>

<?php

include eiseIntraAbsolutePath."inc-frame_bottom.php";

?>