<?php
include("common/auth.php");

switch ($_POST["DataAction"]) {

  case "update":
       $rsSTP = $oSQL->do_query("SELECT * FROM stbl_setup");
       while ($rwSTP = $oSQL->fetch_array($rsSTP)) {
            /* ���������� ���� ��������� � �������� ������ ���� �� ����� */
            /* � ���������� ���������� ������ - ��� �������������������� */
            if (!$rwSTP["stpFlagReadOnly"]){
                if($_POST[$rwSTP["stpVarName"]]!=$rwSTP["stpCharValue"]){
                    $sqlUpdSetupRow = "UPDATE stbl_setup SET stpCharValue=".$oSQL->escape_string($_POST[$rwSTP["stpVarName"]]).
                                    " WHERE stpVarName = '".$rwSTP["stpVarName"]."'";
                    $oSQL->do_query($sqlUpdSetupRow);
                }
                if(!is_null($rwSTP["stpCharValueLocal"]) && $_POST[$rwSTP["stpVarName"]."_local"]!=$rwSTP["stpCharValueLocal"]){
                    $sqlUpdSetupRow = "UPDATE stbl_setup SET stpCharValueLocal=".$oSQL->escape_string($_POST[$rwSTP["stpVarName"]."_local"]).
                                    " WHERE stpVarName = '".$rwSTP["stpVarName"]."'";
                    $oSQL->do_query($sqlUpdSetupRow);
                }
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

<fieldset style="width: 66%;"><legend><?php  echo $intra->translate("System settings"); ?></legend>

<?php
    

   $rsSTP = $oSQL->do_query("SELECT * FROM stbl_setup ORDER BY stpFlagReadOnly ASC, stpNGroup ASC, stpID ASC");
   
   while ($rwSTP = $oSQL->fetch_array($rsSTP)) {
   
    if (isset($oldGroup) && $oldGroup != $rwSTP["stpNGroup"])
        echo "<hr>";
   
   //print_r($rwSTP);
?>
<div class="eiseIntraField"><label><?php echo $rwSTP["stpTitle{$intra->local}"]; ?>:&nbsp;</label>
	<?php 
    $arrConf = Array("FlagWrite"=>(!$rwSTP["stpFlagReadOnly"] && (bool)$intra->arrUsrData["FlagWrite"]));
    if ($rwSTP["stpCharType"]=="text"){ 
        echo $intra->showTextArea(htmlspecialchars($rwSTP["stpVarName"]), $rwSTP["stpCharValue"], array_merge($arrConf, Array("strAttrib"=>"rows=\"4\""))); 
        if (!is_null($rwSTP["stpCharValueLocal"])){
            echo $intra->showTextArea(htmlspecialchars($rwSTP["stpVarName"])."_local", $rwSTP["stpCharValueLocal"]
            , array_merge($arrConf, Array("strAttrib"=>"rows=\"4\"", "class"=>"eiseIntraValue_next"))); 
        }
    } else {
        echo $intra->showTextBox(htmlspecialchars($rwSTP["stpVarName"]), $rwSTP["stpCharValue"], $arrConf); 
        if (!is_null($rwSTP["stpCharValueLocal"])){
            echo $intra->showTextBox(htmlspecialchars($rwSTP["stpVarName"])."_local", $rwSTP["stpCharValueLocal"]
                , array_merge($arrConf, Array("class"=>"eiseIntraValue_next"))); 
        }
    }
    ?>
</div>      
<?php
        $oldGroup = $rwSTP["stpNGroup"];
   }
?>
<script language="JavaScript">
function SubmitForm(){
   return true;
}
</script>

<?php if ($intra->arrUsrData["FlagWrite"]) { ?>  
<div><label>&nbsp;</label><input type="submit" value="Save" class="eiseIntraSubmit" onClick="return SubmitForm();"></div>
<?php } ?>

</fieldset>
</form>

<?php 
include eiseIntraAbsolutePath."inc-frame_bottom.php";
 ?>  