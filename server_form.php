<?php 
include "common/auth.php";

$arrActions[]= Array ("title" => "New database"
	   , "action" => "database_form.php"
	   , "class"=> "ss_add"
	);


include eiseIntraAbsolutePath."inc-frame_top.php";
?>

<h1>Welcome to server <?php  echo $oSQL->dbhost ; ?>!</h1>

<?php
include eiseIntraAbsolutePath."inc-frame_bottom.php";
?>