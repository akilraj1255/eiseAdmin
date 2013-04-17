<?php 
include "common/auth.php";

$dbName = (isset($_POST["dbName"]) ? $_POST["dbName"] : $_GET["dbName"]);

include commonStuffAbsolutePath.'eiseGrid/inc_eiseGrid.php';
$arrJS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.js';
$arrCSS[] = commonStuffRelativePath.'eiseGrid/eiseGrid.css';

$grid = new easyGrid($oSQL
                    ,"tbl"
                    , Array(
                            'arrPermissions' => Array('FlagWrite' => false)
                            , 'flagStandAlone' => false
                            )
                    );

$grid->Columns[]=Array(
	'field'=>"Name"
	,'type'=>'row_id'
);
$grid->Columns[]=Array(
	'field'=>"Name"
    , 'title' => "Name"
	, 'type' => "text"
    , 'href' => "table_form.php?dbName=$dbName&tblName=[Name]"
);
$grid->Columns[] = Array(
   'title' => "Rows"
   , 'field' => "Rows"
   , 'type' => "numeric"
);
$grid->Columns[] = Array(
   'title' => "Data Size"
   , 'field' => "Data_length"
   , 'type' => "numeric"
);

$grid->Columns[] = Array(
   'title' => "Index Size"
   , 'field' => "Index_length"
   , 'type' => "numeric"
);

$grid->Columns[] = Array(
   'title' => "Created"
   , 'field' => "Create_time"
   , 'type' => "datetime"   
);

$grid->Columns[] = Array(
   'title' => "Updated"
   , 'field' => "Update_time"
   , 'type' => "datetime"   
);

$grid->Columns[] = Array(
   'title' => "Collation"
   , 'field' => "Collation"
   , 'type' => "text"
);

$grid->Columns[] = Array(
   'title' => "Engine"
   , 'field' => "Engine"
   , 'type' => "text"
);

$grid->Columns[] = Array(
   'title' => "Comment"
   , 'field' => "Comment"
   , 'type' => "text"
   , 'width' => "100%"
);

if ($dbName!="") {
    $sqlDB = "SHOW TABLE STATUS FROM `$dbName`";
    $rsDB = $oSQL->do_query($sqlDB);

    while($rwDB = $oSQL->fetch_array($rsDB)){
      $grid->Rows[] = $rwDB;
      //print_r($rwDB);
        if ($rwDB["Name"]=="stbl_page") $arrFlags["hasPages"] = true;
        if ($rwDB["Name"]=="stbl_entity") {
            $arrFlags["hasEntity"] = true;
        }
    }
    $eiseIntraVersion = (int)$oSQL->d("SELECT MAX(fvrNumber) FROM `{$dbName}`.stbl_framework_version");
    
    
$arrActions[]= Array ("title" => "Create table"
	   , "action" => "javascript:CreateNewTable();"
	   , "class" => "ss_add"
	);

if (isset($eiseIntraVersion) && $eiseIntraVersion < 100){
    $arrActions[]= Array ("title" => "Upgrade eiseIntra"
	   , "action" => "database_act.php?DataAction=upgrade&dbName=".urlencode($dbName)
	   , "class" => "ss_wrench_orange "
	);
}   
    
    
}


include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<h1><?php echo ($dbName!="" ? "Database $dbName" : "New Database"); ?></h1>

<div class="panel">
<table width="100%">
<form action="database_act.php" method="POST">
<input type="hidden" name="dbName_key" value="<?php  echo $dbName ; ?>">
<input type="hidden" name="DataAction" value="create">
<tr>
<td>
<span class="field_title_top">Name:</span>
<input type="text" name="dbName" value="<?php  echo $dbName ; ?>"><br>

<input type="checkbox" name="hasPages" id="hasPages"<?php  echo ($arrFlags["hasPages"] ? " checked" : ""); ?> style="width:auto;"><label for="hasPages">Has Pages</label><br>
<input type="checkbox" name="hasEntity" id="hasEntity"<?php  echo ($arrFlags["hasEntity"] ? " checked" : ""); ?> style="width:auto;"><label for="hasEntity">Has Entities</label><br>
</td>
</tr>
<?php
if ($dbName!="") {
?>
<tr>
<td>
<?php
$grid->Execute();
?>
</td>
<tr>
<?php
}
?>
<tr>
<td style="text-align:center;"><input value="Save" type="submit" onclick="return confirm('Are you sure you\'d like to <?php  
echo ($dbName=="" ? "create" : "update") ; ?> the database?')"></td>
</tr>
</form>
</table>
</div>
<script>
function CreateNewTable(){
 var tbl = prompt('Please enter table name:', 'tbl_');
 if (tbl!=null && tbl!="tbl_" && tbl!=""){
    location.href="codegen_form.php?toGen=newtable&dbName=<?php  echo $dbName ; ?>&tblName="+tbl;
 }
}
</script>



<?php

include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>