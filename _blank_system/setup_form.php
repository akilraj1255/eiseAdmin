<?php
include("common/auth.php");

switch ($_POST["DataAction"]) {

  case "update":
       $rsSTP = $oSQL->do_query("SELECT * FROM stbl_setup");
       while ($rwSTP = $oSQL->fetch_array($rsSTP)) {
          /* ���������� ���� ��������� � �������� ������ ���� �� ����� */
          /* � ���������� ���������� ������ - ��� �������������������� */
          if (($_POST[$rwSTP["stpVarName"]]!=$rwSTP["stpCharValue"])&&($rwSTP["stpFlagReadOnly"]!="y")){
              $sqlUpdSetupRow = "UPDATE tbl_setup SET stpCharValue=".$oSQL->escape_string($_POST[$rwSTP["stpVarName"]]).
                                " WHERE stpVarName = '".$rwSTP["stpVarName"]."'";
              $oSQL->do_query($sqlUpdSetupRow);
          }
       }
       
       SetCookie("UserMessage", "Settings are saved");
       header("Location: ".$_SERVER["PHP_SELF"]);
       
       die;

}

include eiseIntraAbsolutePath."inc-frame_top.php";
?>


<form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="POST" class="eiseIntraForm">
<input type="hidden" name="DataAction" value="update">

<fieldset><legend><?php  echo $intra->translate("System settings"); ; ?></legend>

<?php
   $rsSTP = $oSQL->do_query("SELECT * FROM stbl_setup ORDER BY stpFlagReadOnly ASC, stpNGroup ASC, stpID ASC");
   
   while ($rwSTP = $oSQL->fetch_array($rsSTP)) {
   
   //print_r($rwSTP);
?>
<div><label><?php echo $rwSTP["stpCharName"]; ?>:&nbsp;</label>
	<?php 
    $arrConf = Array("FlagWrite"=>(!$rwSTP["stpFlagReadOnly"] && (bool)$intra->arrUsrData["FlagWrite"])
                , "class"=>"eiseIntraValue");
    if ($rwSTP["stpCharType"]=="text")
       echo $intra->showTextArea(htmlspecialchars($rwSTP["stpVarName"]), $rwSTP["stpCharValue"], array_merge($arrConf, Array("strAttrib"=>"rows=\"6\""))); 
    else 
       echo $intra->showTextBox(htmlspecialchars($rwSTP["stpVarName"]), $rwSTP["stpCharValue"], $arrConf); 
    ?>
</div>      
<?php
   }
?>
<script language="JavaScript">
function SubmitForm(){
   return true;
}
</script>

<?php if ($intra->arrUsrData["FlagWrite"]) { ?>  
<div><label>&nbsp;</label><input type="submit" value="Save" onClick="return SubmitForm();"></div>
<?php } ?>

</fieldset>
</form>

<?php 
include eiseIntraAbsolutePath."inc-frame_bottom.php";
 ?>  