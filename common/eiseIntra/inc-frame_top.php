<!DOCTYPE html>
<html>
<head>

<title><?php echo $intra->arrUsrData["pagTitle{$intra->local}"]; ?></title>

<?php
$intra->loadJS();
$intra->loadCSS();
?>


<?php
echo "\t".$strHead."\r\n";
?>

</head>
<body><input type="hidden" id="eiseIntraConf" value="<?php  echo htmlspecialchars(json_encode($intra->conf)) ; ?>"><?php 
if ($intra->hasUserMessage()) {
   $strUserMessage = $intra->getUserMessage();
}
 ?><div style="display:none;" id="sysmsg"<?php  
     echo (preg_match("/^ERROR/", $strUserMessage) ? " class='error'" : "") ; 
   ?>><?php  
     echo $strUserMessage ; 
     ?></div>

<?php 
if (!$flagNoMenu) {
 ?>
<div class="menubar" id="menubar">
<?php
    for ($i=0;$i<count($arrActions);$i++) {
            echo "<div class=\"menubutton\">";
			$strClass = ($arrActions[$i]['class'] != "" ? " class='ss_sprite ".$arrActions[$i]['class']."'" : "");
            $strTarget = (isset($arrActions[$i]["target"]) ? " target=\"{$arrActions[$i]["target"]}\"" : "");
            $isJS = preg_match("/javascript\:(.+)$/", $arrActions[$i]['action'], $arrJSAction);
            if (!$isJS){
                 echo "<a href=\"".$arrActions[$i]['action']."\"{$strClass}{$strTarget}>{$arrActions[$i]["title"]}</a>\r\n";
            } else {
                 echo "<a href=\"".$arrActions[$i]['action']."\" onclick=\"".$arrJSAction[1]."; return false;\"{$strClass}>{$arrActions[$i]["title"]}</a>\r\n";
            }
            echo "</div>";
    }
?>
<div class="menubutton float_right"><a target=_top href='index.php?pane=<?php  echo urlencode($_SERVER["REQUEST_URI"]) ; ?>'
class='ss_sprite ss_link'><?php  echo $intra->translate("Link") ; ?></a></div>
</div>
<?php 
}
?>
<div id="frameContent">