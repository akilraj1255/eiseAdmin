<?php
include "common/auth.php" ;

include commonStuffAbsolutePath.'eiseGrid/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.css';

$oSQL->dbname=(isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->select_db($oSQL->dbname);
$dbName = $oSQL->dbname;

//$_DEBUG = true;

$DataAction = isset($_POST["DataAction"]) ? $_POST["DataAction"] : $_GET["DataAction"];

$gridROL = new easyGrid($oSQL
        ,'rol'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_role'
                , 'strPrefix' => 'rol'
                , 'flagStandAlone' => true
                )
        );

$gridROL->Columns[]  = Array(
            'type' => 'row_id'
            , 'field' => 'rolID_id'
        );
$gridROL->Columns[] = Array(
        'title' => "ID"
        , 'field' => "rolID"
        , 'mandatory' => true
        , 'type' => "text"
);
$gridROL->Columns[] = Array(
        'title' => "Title (Loc)"
        , 'field' => "rolTitleLocal"
        , 'type' => "text"
        , 'width' => "300px;"
        );
$gridROL->Columns[] = Array(
        'title' => "Title"
        , 'field' => "rolTitle"
        , 'type' => "text"
        , 'width' => "300px;"
);$gridROL->Columns[] = Array(
        'title' => "all"
        , 'field' => "rolFlagDefault"
        , 'type' => "checkbox"
);
$gridROL->Columns[] = Array(
        'title' => "Members"
        , 'field' => "rolMembers"
        , 'type' => "text"
        , 'disabled' => "[rolFlagDefault]"
        , 'width' => "100%"
);


switch($DataAction){
    case "update":
        
        $gridROL->Update();
        
        //determining newly created roles
        for ($i=0;$i<count($_POST["rolID_id"]);$i++){
            if ($_POST["rolID_id"]==""){
                $sql[] = "INSERT INTO stbl_page_role (
                    pgrPageID
                    , pgrRoleID
                    , pgrFlagRead
                    , pgrFlagWrite
                    , pgrInsertBy, pgrInsertDate, pgrEditBy, pgrEditDate
                    , pgrFlagCreate
                    , pgrFlagUpdate
                    , pgrFlagDelete
                    ) SELECT 
                    pagID
                    , ".$oSQL->escape_string($_POST["rolID"])." as pgrRoleID
                    , 0 as pgrFlagRead
                    , 0 as pgrFlagWrite
                    , '$usrID' AS pgrInsertBy, NOW() AS pgrInsertDate, '$usrID' AS pgrEditBy, NOW() AS pgrEditDate
                    , 0 AS pgrFlagCreate
                    , 0 AS pgrFlagUpdate
                    , 0 AS pgrFlagDelete
                    FROM stbl_page";
                    
                $sql[] = "INSERT INTO stbl_role_action (
                        rlaRoleID
                        , rlaActionID
                        ) SELECT 
                        ".$oSQL->escape_string($_POST["rolID"])." AS rlaRoleID
                        , actID AS rlaActionID
                        FROM stbl_action";       
            }
        }
                
        //detrminig deleted roles
        $arrRolToDel = explode("|", $_POST["inp_rol_deleted"]);
        for($i=0;$i<count($arrRolToDel);$i++)
            if ($arrRolToDel[$i]!=""){
               $sql[] = "DELETE FROM stbl_role_action WHERE rlaRoleID='".$arrRolToDel[$i]."'";
               $sql[] = "DELETE FROM stbl_page_role WHERE pgrRoleID='".$arrRolToDel[$i]."'";
            }
        
        //updating members
        for ($i=0;$i<count($_POST["rolID_id"]);$i++)
        if (!in_array($_POST["rolID_id"][$i], $arrRolToDel) && $_POST["inp_rol_updated"][$i]=="1")
        {
            $arrUsr = preg_split("/\s*[,\;\|]\s*/", $_POST["rolMembers"][$i]);
            $sql[] = "DELETE FROM stbl_role_user WHERE rluRoleID='".$_POST["rolID_id"][$i]."'";
            foreach ($arrUsr as $val)
               if ($val!="")
                   $sql[] = "INSERT INTO stbl_role_user(
                        rluUserID
                        , rluRoleID
                        , rluInsertBy, rluInsertDate, rluEditBy, rluEditDate
                        ) VALUES (
                        ".$oSQL->escape_string($val)."
                        , '".$_POST["rolID_id"][$i]."'
                        , '$usrID', NOW(), '$usrID', NOW());";
        }
        
        for($i=0;$i<count($sql);$i++)
            $oSQL->do_query($sql[$i]);
        /*
        echo "<pre>";
        print_r($_POST);
        print_r($sql);
        echo "</pre>";
        die();
        //*/
        SetCookie("UserMessage", "Data is updated");
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName");
        break;
    default:
        break;
}

$arrActions[]= Array ('title' => 'Add Row'
	   , 'action' => "javascript:easyGridAddRow('rol')"
	   , 'class'=> 'ss_add'
	);
include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<h1>Roles</h1>

<script>
$(document).ready(function(){  
	easyGridInitialize();
});
</script>


<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update" />
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>" />
<?php 
$sqlROL = "SELECT ROL.*
, GROUP_CONCAT(rluUserID SEPARATOR ', ') as rolMembers
FROM stbl_role ROL
LEFT OUTER JOIN stbl_role_user ON rolID=rluRoleID
GROUP BY rolID, rolTitle, rolTitleLocal";
$rsROL = $oSQL->do_query($sqlROL);
while ($rwROL = $oSQL->fetch_array($rsROL)){
    $rwROL['rolID_id'] = $rwROL['rolID'];
    $gridROL->Rows[] = $rwROL;
}

$gridROL->Execute();
?>
<div align="center">
<input type="submit" value="Save" onclick="easyGridVerify('rol');">
</div>
</form>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>