<?php
$host='localhost'; //database host
$database_username='skj_brightree'; //database username
$database_password='@dmin123';  //database password
$database="skj_brightree_patient_app"; //database name

$link = mysql_connect($host, $database_username, $database_password) or die(''.mysql_error());
mysql_select_db($database);
?>