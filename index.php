<?php
include "common/auth.php";
$arrCSS[] = "eiseAdmin.css";
$defaultPaneSrc = "server_form.php";
$toc_generator = dirname(__FILE__)."inc_toc_generator.php";
$extraHTML = '<div id="header_version_info">'.$intra->translate('Version').' '.$version.'</div>';
include eiseIntraAbsolutePath."inc_index.php";
?>