<?php 
include "common/auth.php";
include eiseIntraAbsolutePath."inc_dbsv.php";

$dbsvPath = (isset($dbsvPath) ? $dbsvPath : "./.SQL");

set_time_limit(600);

$n = ob_get_level();
for ($i=0; $i<$n; $i++) {ob_end_flush();}
ob_implicit_flush(1);

echo str_repeat(" ", 256);
ob_flush();

?><!DOCTYPE html>
<html><head>
<title>DBSV SQL script application</title>
</head>
<body>
<pre>
/**************************************************************************/
/* PHP DBSV for MySQL                                                     */
/* (c)2008-2012 Ilya S. Eliseev                                           */ 
/**************************************************************************/
<?php 
if (!$intra->arrUsrData["FlagWrite"]) {
    echo "Permission denied for user {$usrID}";
    die();
}

$dbsv = new eiseDBSV($oSQL, $dbsvPath);

$dbsv->Execute();
 ?>
</pre>
</body>
</html>