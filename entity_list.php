<?php
include 'common/auth.php';

$dbName = (isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);
$oSQL->dbname = $dbName;

$DataAction  = (isset($_POST['DataAction']) ? $_POST['DataAction'] : $_GET['DataAction'] );

include commonStuffAbsolutePath.'eiseGrid/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.css';

$gridENT = new easyGrid($oSQL
        ,'ent'
        , Array(
                'rowNum' =>40
                , 'arrPermissions' => Array('FlagWrite'=>true)
                , 'strTable' => 'stbl_entity'
                , 'strPrefix' => 'ent'
                , 'flagStandAlone' => true
                )
        );

$gridENT->Columns[]  = Array(
        'type' => 'row_id'
        , 'field' => 'entID_id'
        );
        
$gridENT->Columns[] = Array(
        'title' => "entID"
        , 'field' => "entID"
        , 'type' => "text"
        , 'mandatory' => true
//        , 'disabled' => true
//        , 'href' => "entity_form.php?dbName=$dbName&entID=[entID]"
);        
/*
$gridENT->Columns[] = Array(
        'title' => "entID"
        , 'field' => "entID"
        , 'type' => "text"
        , 'href' => "entity_form.php?dbName=$dbName&entID=[entID]"
);
*/
$gridENT->Columns[] = Array(
        'title' => "entTitle"
        , 'field' => "entTitle"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "entTitleLocal"
        , 'field' => "entTitleLocal"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "entTable"
        , 'field' => "entTable"
        , 'type' => "text"
);
$gridENT->Columns[] = Array(
        'title' => "entPrefix"
        , 'field' => "entPrefix"
        , 'type' => "text"
);

switch($DataAction){
    case "update":
        $gridENT->Update();
        //die();
        header("Location: ".$_SERVER["PHP_SELF"]."?dbName=$dbName");
        break;
    default:
        break;
}


$arrActions[]= Array ('title' => 'Add Row'
	   , 'action' => "javascript:easyGridAddRow('ent')"
	   , 'class'=> 'ss_add'
	);
    
include eiseIntraAbsolutePath."inc-frame_top.php";
?>
<script>
$(document).ready(function(){  
	easyGridInitialize();
});
</script>

<h1>Entities</h1>

<div class="panel">
<table width="100%">
<form action="<?php  echo $_SERVER["PHP_SELF"] ; ?>" method="POST">
<input type="hidden" name="DataAction" value="update">
<input type="hidden" name="dbName" value="<?php  echo $dbName ; ?>">
<tr>
<td>

<?php
$sqlENT = "SELECT * FROM stbl_entity";
$rsENT = $oSQL->do_query($sqlENT);
while ($rwENT = $oSQL->fetch_array($rsENT)){
    $rwENT['entID_id'] = $rwENT['entID'];
    $gridENT->Rows[] = $rwENT;
}

$gridENT->Execute();
?>
</td>
</tr>
<tr>
<td align="center"><input type="submit" value="Save"></td>
</tr>
</table>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>
