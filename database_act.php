<?php
include "common/auth.php";

function parse_mysql_dump($url){
    $file_content = file($url);
    $query = "";
	$delimiter = ";";
    foreach($file_content as $sql_line){
        if (!preg_match("/^\#/", $sql_line) )
		    $query .= $sql_line;
		if (preg_match("/^CREATE FUNCTION/i", $sql_line) )
		    $delimiter = ";;";
        if(preg_match("/$delimiter\s*$/", $sql_line)){
		  //echo "--\r\n".$query."\r\n---";
          $result = mysql_query($query)or die('Error: '.mysql_error());
          $query = "";
		  $delimiter = ";";
        }
    }
}

function rollDBSVFramework($dbName){
    
    GLOBAL $oSQL;
    
    $table = $oSQL->d("SHOW TABLES FROM `{$dbName}` LIKE 'stbl_framework_version'");
    if (!$table) {
        die("Framework DBSV roll-out not possible");
    }
    
    $verNumber = $oSQL->d("SELECT MAX(fvrNumber) FROM `{$dbName}`.stbl_framework_version");
    
    $verNumber = (!$verNumber ? $verNumber=59 : $verNumber);
    
    $oSQL->q("USE `$dbName`");
    
    echo "Current DB Framework Schema Version number is #".sprintf("%03d",$verNumber)."\r\n";

    $dh  = opendir(eiseIntraAbsolutePath.".SQL");
        
    $arrFiles = Array();
        
    while (false !== ($filename = readdir($dh))) {
       if (preg_match("/^([0-9]{3}).+(\.sql)/",$filename, $arrMatch)){
          $arrFiles[(integer)$arrMatch[1]] = $filename;
       }
    }

    ksort($arrFiles);
    end($arrFiles);
    $newVerNo = key($arrFiles);
    echo "New version number is going to be #".sprintf("%03d",$newVerNo)."\r\n";ob_flush();
    if ($newVerNo<=$verNumber) 
       die("Nowhere to update. Currenct DB framework version is bigger than this update.\r\n\r\n");ob_flush();


    for ($i=($verNumber+1);$i<=$newVerNo;$i++){
       if (!isset($arrFiles[$i]))
           die("Cannot get SQL script for version #$i.");
        $fileName = eiseIntraAbsolutePath.".SQL".DIRECTORY_SEPARATOR.$arrFiles[$i];
       $fh = fopen($fileName, "r");
       parse_mysql_dump($fileName);
       mysql_query("INSERT INTO stbl_framework_version (fvrNumber, fvrDate, fvrDesc) VALUES ($i, NOW(),'".
          mysql_escape_string(fread($fh, filesize($fileName)))."')");
       echo "Version is now #".sprintf("%03d",$i)."\r\n";
       fclose($fh);
    }
    
    
}




set_time_limit(1200);
ob_start();
ob_implicit_flush(true);
$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];
$dbName = $_GET["dbName"];

switch($DataAction) {

case "convert":
    
    echo "<pre>";
    $sqlDB = "SHOW TABLE STATUS FROM $dbName";
    $rsDB = $oSQL->do_query($sqlDB);
    $oSQL->dbname = $dbName;
    
    while($rwDB = $oSQL->fetch_array($rsDB))
       if ($rwDB['Comment']!="VIEW") {
          $sql = Array();
          $arrKeys = Array();
          $arrColToModify = Array();
          
          echo "Converting table ".$rwDB['Name']."\r\n";
          
          $arrTable = getTableInfo($dbName, $rwDB['Name']);
          $tblName = $rwDB['Name'];
          for ($i=0;$i<count($arrTable['columns']);$i++)
             if ($arrTable['columns'][$i]['DataType']=="text"){
                $arrCol['colName'] = $arrTable['columns'][$i]['Field'];
                $arrCol['sql_modback'] = "ALTER TABLE `".$tblName."` MODIFY `".$arrCol['colName']."` ".$arrTable['columns'][$i]['Type']." ".
                   ($arrTable['columns'][$i]['Null']=="NO" 
                     ? " NOT NULL ".(!preg_match("/TEXT/i", $arrTable['columns'][$i]['Type'])
                        ? "DEFAULT '".$arrTable['columns'][$i]['Default']."'"
                        : "")
                     : "NULL DEFAULT NULL");
                     
                $arrColToModify[] = $arrCol;
             }
          
          for ($i=0;$i<count($arrTable['keys']);$i++)
             if ($arrTable['keys'][$i]['Key_name']!="PRIMARY"){
                $arrKeys[] = $arrTable['keys'][$i]['Key_name'];
            }
           
          $arrKeys = array_unique($arrKeys);
          
          
          
          $sql[] = "ALTER TABLE $tblName CONVERT TO CHARACTER SET latin1";
          foreach($arrKeys as $key=>$value){
             $sql[] = "ALTER TABLE $tblName DROP INDEX ".$value;
          }
          
          //if ($tblName=="stbl_page_role")
          //print_r($arrKeys);
          
          
          for ($i=0;$i<count($arrColToModify);$i++){
            $sql[] = "ALTER TABLE `".$tblName."` MODIFY `".$arrColToModify[$i]['colName']."` LONGBLOB";
          }
          
          $sql[] = "ALTER TABLE $tblName CONVERT TO CHARACTER SET utf8";
          
          for ($i=0;$i<count($arrColToModify);$i++){
            $sql[] = $arrColToModify[$i]['sql_modback'];
          }
          
          //re-creating keys
          $arrCT = $oSQL->fetch_array($oSQL->do_query("SHOW CREATE TABLE $tblName"));
          $arrCTStr = preg_split('/[\r\n]/', $arrCT['Create Table']);
          for($i=0;$i<count($arrCTStr);$i++)
             if (preg_match("/KEY/", $arrCTStr[$i]) && !preg_match("/PRIMARY KEY/", $arrCTStr[$i])){
               $sql[] = "ALTER TABLE $tblName ADD ".trim(preg_replace("/,$/", "", $arrCTStr[$i]));
             }
          
          for($i=0;$i<count($sql);$i++){
             echo "     running ".$sql[$i]."\r\n";
             $oSQL->do_query($sql[$i]);
          }
          echo "\r\n";
       }
    
    echo "</pre>";
    
break;

case "create":

if ($_POST["dbName_key"]==""){

   echo "<pre>";   
   
   //print_r($_POST);
   
   
   //create new database
   $sqlDB = "CREATE DATABASE `".$_POST["dbName"]."` /*!40100 CHARACTER SET utf8 COLLATE utf8_general_ci */";
   $oSQL->do_query($sqlDB);
   
   
   
   $oSQL->dbname = $_POST["dbName"];
   
   $sqlTable[] = "
CREATE TABLE `stbl_version` (
  `verNumber` int(11) NOT NULL AUTO_INCREMENT,
  `verDesc` text,
  `verDate` datetime DEFAULT NULL,
  PRIMARY KEY (`verNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version history for the whole database';
   ";

   $sqlTable[] = "
CREATE TABLE `stbl_framework_version` (
  `fvrNumber` int(11) NOT NULL AUTO_INCREMENT,
  `fvrDesc` text,
  `fvrDate` datetime DEFAULT NULL,
  PRIMARY KEY (`fvrNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Version history for the framework';
   ";
   
   $sqlTable[] = "
CREATE TABLE `tbl_setup` (
	`stpID` INT(11) NOT NULL AUTO_INCREMENT,
	`stpVarName` VARCHAR(255) NULL DEFAULT NULL,
	`stpCharType` VARCHAR(20) NULL DEFAULT NULL,
	`stpCharValue` VARCHAR(1024) NULL DEFAULT NULL,
	`stpFlagReadOnly` TINYINT(4) NULL DEFAULT NULL,
	`stpNGroup` INT(11) NULL DEFAULT NULL,
	`stpCharName` VARCHAR(30) NULL DEFAULT NULL,
	`stpCharNameLocal` VARCHAR(30) NULL DEFAULT NULL,
	`stpInsertBy` VARCHAR(50) NULL DEFAULT NULL,
	`stpInsertDate` DATETIME NULL DEFAULT NULL,
	`stpEditBy` VARCHAR(50) NULL DEFAULT NULL,
	`stpEditDate` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`stpID`)
)
COLLATE='utf8_general_ci' ENGINE=InnoDB COMMENT 'Stores common setting fot the system';
   ";
if ($_POST["hasPages"]=="on") {

   $sqlTable[] = "
CREATE TABLE `stbl_page` (
  `pagID` int(11) NOT NULL AUTO_INCREMENT,
  `pagParentID` int(11) unsigned DEFAULT NULL,
  `pagTitle` varchar(255) DEFAULT NULL,
  `pagTitleLocal` varchar(255) DEFAULT NULL,
  `pagIdxLeft` int(11) unsigned DEFAULT NULL,
  `pagIdxRight` int(11) unsigned DEFAULT NULL,
  `pagFlagShowInMenu` tinyint(4) unsigned DEFAULT NULL,
  `pagFile` varchar(255) DEFAULT NULL,
  `pagTable` varchar(20) DEFAULT NULL,
  `pagPrefix` char(3) DEFAULT NULL,
  `pagFlagSystem` tinyint(4) unsigned DEFAULT NULL,
  `pagFlagHierarchy` tinyint(4) unsigned DEFAULT NULL,
  `pagInsertBy` varchar(30) DEFAULT NULL,
  `pagInsertDate` datetime DEFAULT NULL,
  `pagEditBy` varchar(30) DEFAULT NULL,
  `pagEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`pagID`),
  KEY `IX_pagIdxLeft` (`pagIdxLeft`),
  KEY `IX_pagIdxRight` (`pagIdxRight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='The table defines the list and the structure of all scripts';
   ";

   $sqlTable[] = "
CREATE TABLE `stbl_role` (
  `rolID` varchar(10) NOT NULL,
  `rolTitle` varchar(50) DEFAULT NULL,
  `rolTitleLocal` varchar(50) DEFAULT NULL,
  `rolFlagDefault` tinyint(4) DEFAULT '0',
  `rolFlagDeleted` tinyint(4) DEFAULT '0',
  `rolInsertBy` varchar(30) DEFAULT NULL,
  `rolInsertDate` datetime DEFAULT NULL,
  `rolEditBy` varchar(30) DEFAULT NULL,
  `rolEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`rolID`),
  UNIQUE KEY `IX_rolTitle` (`rolTitle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='This table defines roles in the application';
";
   
   
   
   $sqlTable[] = "
CREATE TABLE `stbl_page_role` (
  `pgrID` int(11) NOT NULL AUTO_INCREMENT,
  `pgrPageID` int(11) DEFAULT NULL,
  `pgrRoleID` varchar(10) DEFAULT NULL,
  `pgrFlagRead` tinyint(4) DEFAULT NULL,
  `pgrFlagWrite` tinyint(4) DEFAULT NULL,
  `pgrInsertBy` varchar(30) DEFAULT NULL,
  `pgrInsertDate` datetime DEFAULT NULL,
  `pgrEditBy` varchar(30) DEFAULT NULL,
  `pgrEditDate` datetime DEFAULT NULL,
  `pgrFlagCreate` tinyint(4) DEFAULT NULL,
  `pgrFlagUpdate` tinyint(4) DEFAULT NULL,
  `pgrFlagDelete` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`pgrID`),
  UNIQUE KEY `IX_pgrPageRole` (`pgrPageID`,`pgrRoleID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Authorization table, assigns script rights to users';
   ";
   
   $sqlTable[] = "
CREATE VIEW stbl_user AS SELECT * FROM common_db.stbl_user
   ";
   
   $sqlTable[] = "
CREATE TABLE `stbl_role_user` (
  `rluID` int(11) NOT NULL AUTO_INCREMENT,
  `rluUserID` varchar(50) DEFAULT NULL,
  `rluRoleID` varchar(10) DEFAULT NULL,
  `rluInsertBy` varchar(30) DEFAULT NULL,
  `rluInsertDate` datetime DEFAULT NULL,
  `rluEditBy` varchar(30) DEFAULT NULL,
  `rluEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`rluID`),
  UNIQUE KEY `IX_rluRoleUser` (`rluRoleID`,`rluUserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Assigns users to respective roles in the application';
   ";

   $sqlTable[] = "
CREATE TABLE `stbl_user_log` (
  `logID` int(11) NOT NULL AUTO_INCREMENT,
  `logUsrID` varchar(50) DEFAULT NULL,
  logTicket varchar(255) DEFAULT NULL,
  `logAuthCode` varchar(20) DEFAULT NULL,
  `logAuthMessage` varchar(254) DEFAULT NULL,
  `logPageName` varchar(255) DEFAULT NULL,
  `logProtocol` varchar(255) DEFAULT NULL,
  `logMethod` varchar(20) DEFAULT NULL,
  `logGET` varchar(255) DEFAULT NULL,
  `logPOST` varchar(1024) DEFAULT NULL,
  `logCookies` varchar(1024) DEFAULT NULL,
  `logAuthType` varchar(20) DEFAULT NULL,
  `logRemoteIP` varchar(15) DEFAULT NULL,
  `logUserAgent` varchar(255) DEFAULT NULL,
  `logTime` datetime DEFAULT NULL,
  PRIMARY KEY (`logID`),
  KEY `IX_logTicket` (logTicket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT 'Stores user activity log';
   ";
 
//data for stbl_page_role
    $sqlTable[] = "
INSERT INTO `stbl_page` (`pagID`, `pagParentID`, `pagTitle`, `pagTitleLocal`, `pagIdxLeft`, `pagIdxRight`, `pagFlagShowInMenu`, `pagFile`, `pagTable`, `pagPrefix`, `pagFlagSystem`, `pagFlagHierarchy`, `pagInsertBy`, `pagInsertDate`, `pagEditBy`, `pagEditDate`) VALUES
	(1,NULL,'ROOT','','1','20',0,'',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(2,'1','HOME','','10','19',0,'/index.php',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(3,'2','Header','','17','18',0,'/frm_header.php',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(4,'2','Table of contents','','11','12',0,'/frm_toc.php',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(5,'2','Login form','','15','16',0,'/login.php',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(6,'2','Logout script','','13','14',0,'/logout.php',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(7,'1','About','','8','9',1,'/about.php',NULL,NULL,NULL,NULL,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(8,'1','Settings','','2','7',1,'',NULL,NULL,0,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(9,'8','Common Settings','','5','6',1,'/setup_form.php',NULL,NULL,0,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21'),
	(10,'8','Access Control','','3','4',1,'/role_form.php','','',0,0,'','2009-04-14 12:28:52','','2009-04-14 00:00:00')
    ";
    
    $sqlTable[] = "
INSERT INTO `stbl_page_role` (`pgrID`, `pgrPageID`, `pgrRoleID`, `pgrFlagRead`, `pgrFlagWrite`, `pgrInsertBy`, `pgrInsertDate`, `pgrEditBy`, `pgrEditDate`, `pgrFlagCreate`, `pgrFlagUpdate`, `pgrFlagDelete`) VALUES
	(1,1,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(2,2,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(3,3,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(4,4,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(5,5,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(6,6,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(7,7,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(8,8,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(9,9,'admin',1,0,'admin','2009-04-14 12:21:21','admin','2009-04-14 12:21:21',0,0,0),
	(10,10,'admin',1,1,'','2009-04-14 12:28:52','','2009-04-14 12:28:58',1,1,1);
    ";
    
    $sqlTable[] = "
INSERT INTO `stbl_role` (`rolID`, `rolTitle`, `rolTitleLocal`, `rolFlagDefault`, `rolFlagDeleted`, `rolInsertBy`, `rolInsertDate`, `rolEditBy`, `rolEditDate`) VALUES
	('admin','Administrator','',0,0,'admin',NOW(),'admin',NOW())
    ";
    
    $sqlTable[] = "
INSERT INTO stbl_role_user (
rluUserID
, rluRoleID
, rluInsertBy, rluInsertDate, rluEditBy, rluEditDate
) VALUES (
'ELISEEV'
, 'Admin'
, 'admin',NOW(),'admin',NOW());
    ";
 
}



   
if ($_POST["hasEntity"]=="on") {
   
   $sqlTable['CREATE TABLE stbl_entity'] = "
CREATE TABLE `stbl_entity` (
  `entID` varchar(20) NOT NULL,
  `entTitle` varchar(255) NOT NULL DEFAULT '',
  `entTitleLocal` varchar(255) DEFAULT NULL,
  `entTable` varchar(20) DEFAULT NULL,
  `entPrefix` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`entID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT 'Defines entities';
   ";
   
   $sqlTable['CREATE TABLE stbl_action'] = "
CREATE TABLE `stbl_action` (
  `actID` int(11) NOT NULL AUTO_INCREMENT,
  `actEntityID` varchar(20) DEFAULT NULL,
  `actOldStatusID` int(11) DEFAULT NULL,
  `actNewStatusID` int(11) DEFAULT NULL,
  `actTitle` varchar(255) NOT NULL DEFAULT '',
  `actTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `actTitlePast` varchar(255) DEFAULT NULL,
  `actTitlePastLocal` varchar(255) DEFAULT NULL,
  `actDescription` text NOT NULL,
  `actDescriptionLocal` text NOT NULL,
  `actFlagDeleted` int(11) NOT NULL DEFAULT '0',
  `actPriority` int(11) NOT NULL DEFAULT '0',
  `actFlagComment` int(11) NOT NULL DEFAULT '0',
  `actShowConditions` varchar(255) NOT NULL DEFAULT '',
  `actInsertBy` varchar(255) DEFAULT NULL,
  `actInsertDate` datetime DEFAULT NULL,
  `actEditBy` varchar(20) DEFAULT NULL,
  `actEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`actID`),
  KEY `IX_actOldStatusID` (`actOldStatusID`),
  KEY `IX_actNewStatusID` (`actNewStatusID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines actions for entities';
   ";
   
   $sqlTable['CREATE TABLE stbl_role_action'] = "
CREATE TABLE `stbl_role_action` (
  `rlaID` int(11) NOT NULL AUTO_INCREMENT,
  `rlaRoleID` varchar(10) NOT NULL,
  `rlaActionID` int(11) NOT NULL,
  PRIMARY KEY (`rlaID`),
  KEY `IX_rlaRoleID` (`rlaRoleID`),
  KEY `IX_rlaActionID` (`rlaActionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Allow or disallow action for entity';
    ";
    
    $sqlTable['CREATE TABLE stbl_action_log'] = "
CREATE TABLE `stbl_action_log` (
  `aclGUID` char(36) NOT NULL DEFAULT '',
  `aclActionID` int(11) NOT NULL,
  `aclEntityItemID` varchar(36) NOT NULL,
  `aclOldStatusID` int(11) DEFAULT NULL,
  `aclNewStatusID` int(11) DEFAULT NULL,
  `aclComments` text,
  `aclInsertBy` varchar(255) NOT NULL DEFAULT '',
  `aclInsertDate` datetime NOT NULL,
  PRIMARY KEY (`aclGUID`),
  KEY `aclEntityItemID` (`aclEntityItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores action history on entity items';
    ";
    
    $sqlTable['CREATE stbl_status'] = "
CREATE TABLE `stbl_status` (
  `staID` int(11) NOT NULL,
  `staEntityID` varchar(20) NOT NULL DEFAULT '',
  `staTitle` varchar(255) NOT NULL DEFAULT '',
  `staTitleLocal` varchar(255) NOT NULL DEFAULT '',
  `staFlagCanUpdate` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Defines can the entity be updated in current status',
  `staFlagCanDelete` tinyint(4) NOT NULL DEFAULT '0',
  `staInsertBy` varchar(255) NOT NULL DEFAULT '',
  `staInsertDate` datetime DEFAULT NULL,
  `staEditBy` varchar(255) NOT NULL DEFAULT '',
  `staEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`staEntityID`,`staID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entity statuses';
    ";
    
    $sqlTable['CREATE TABLE stbl_attribute'] = "
CREATE TABLE `stbl_attribute` (
  `atrID` varchar(255) NOT NULL,
  `atrEntityID` varchar(20) NOT NULL DEFAULT '',
  `atrTitle` varchar(255) DEFAULT NULL,
  `atrTitleLocal` varchar(255) DEFAULT NULL,
  `atrFieldName` varchar(255) DEFAULT NULL COMMENT 'Defines field name in entity table to be updated toghther with attribute',
  `atrType` varchar(20) DEFAULT NULL,
  `atrOrder` int(11) DEFAULT '10' COMMENT 'Defines order how this attribute appears on screen',
  `atrProperties` varchar(255) NOT NULL DEFAULT '' COMMENT 'Defines attribute properties to be set and used by programmer',
  `atrDefault` varchar(255) NOT NULL DEFAULT '',
  `atrFlagDeleted` int(11) NOT NULL DEFAULT '0',
  `atrInsertBy` varbinary(255) DEFAULT NULL,
  `atrInsertDate` datetime DEFAULT NULL,
  `atrEditBy` varbinary(255) DEFAULT NULL,
  `atrEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`atrID`,`atrEntityID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines entity attributes';
    ";
    
    $sqlTable['CREATE stbl_attribute_value'] = "
CREATE TABLE `stbl_attribute_value` (
  `atvGUID` varchar(36) NOT NULL,
  `atvEntityID` varchar(10) NOT NULL,
  `atvAttributeID` varchar(255) NOT NULL,
  `atvEntityItemID` varchar(255) NOT NULL DEFAULT '',
  `atvActionLogID` varchar(255) DEFAULT NULL,
  `atvValue` varchar(255) DEFAULT NULL,
  `atvInsertBy` varchar(255) NOT NULL DEFAULT '',
  `atvInsertDate` datetime DEFAULT NULL,
  `atvEditBy` varchar(255) NOT NULL DEFAULT '',
  `atvEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`atvGUID`),
  KEY `IX_atv` (`atvEntityID`,`atvAttributeID`,`atvEntityItemID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores entity attribute values';
    ";
    
    $sqlTable['CREATE stbl_action_attribute'] = "
CREATE TABLE `stbl_action_attribute` (
  `aatID` int(11) NOT NULL AUTO_INCREMENT,
  `aatActionID` int(11) DEFAULT NULL,
  `aatAttributeID` varchar(20) DEFAULT NULL,
  `aatFlagToAdd` tinyint(4) NOT NULL DEFAULT '0' COMMENT 'Defines should this attribute be added or updated if it already exists on given action',
  `aatInsertBy` varchar(255) NOT NULL DEFAULT '',
  `aatInsertDate` datetime DEFAULT NULL,
  `aatEditBy` varchar(255) NOT NULL DEFAULT '',
  `aatEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`aatID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Defines the attributes to be set when the action is executed';
    ";
    
   $sqlTable['CREATE stbl_status_attribute'] = "
CREATE TABLE `stbl_status_attribute` (
  `satID` int(11) NOT NULL AUTO_INCREMENT,
  `satStatusID` varchar(255) NOT NULL,
  `satEntityID` varchar(255) NOT NULL,
  `satAttributeID` varchar(255) NOT NULL,
  `satInsertBy` varchar(50) DEFAULT NULL,
  `satInsertDate` datetime DEFAULT NULL,
  `satEditBy` varchar(50) DEFAULT NULL,
  `satEditDate` datetime DEFAULT NULL,
  PRIMARY KEY (`satID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
   ";
    
    
    
    $sqlTable['INSERT stbl_action'] = "
INSERT INTO `stbl_action` (`actID`, `actEntityID`, `actOldStatusID`, `actNewStatusID`, `actTitle`, `actTitleLocal`, `actTitlePast`, `actTitlePastLocal`, `actDescription`, `actDescriptionLocal`, `actFlagDeleted`, `actPriority`, `actFlagComment`, `actInsertBy`, `actInsertDate`, `actEditBy`, `actEditDate`) VALUES
	(1,NULL,NULL,0,'Create','Create','Created','Created','create new','create new',0,0,0,'ELISEEEV',NULL,'ELISEEV',NULL),
	(2,NULL,NULL,NULL,'Update','Update','Updated','Updated','update existing','update existing',0,0,0,'ELISEEV',NULL,'ELISEEV',NULL),
	(3,NULL,0,NULL,'Delete','Delete','Deleted','Deleted','delete existing','delete existing',0,-1,0,'ELISEEV',NULL,'ELISEEV',NULL)
    ";
    
    //echo "Dick:".(count($sqlTable));
    
    foreach($sqlTable as $action=>$sql)
        echo $action."\r\n";ob_flush();
        $oSQL->do_query($sqlTable[$i]);
    }
   
    echo "</pre>";

}

break;



case "upgrade":

    include eiseIntraAbsolutePath."inc_entity_item.php";

    set_time_limit(0);

    //$oSQL->startProfiling();
    for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
    ob_implicit_flush(1);
    echo str_repeat(" ", 256)."<pre>"; ob_flush();

    rollDBSVFramework($dbName);    
    
    die();
    
    echo "Upgrading entities...\r\n";ob_flush();
/*
    $sqlEnt = "SELECT * FROM stbl_entity";
    $rsEnt = $oSQL->q($sqlEnt);
    while($rwEnt = $oSQL->f($rsEnt)){
        $ent = new eiseEntity($oSQL, $intra, $rwEnt["entID"]);
        $ent->upgrade_eiseIntra();
    }
*/
    break;

}

?>