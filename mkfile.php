<?php
require_once("config.php");
require_once("upload.php");

$upload = new Upload(false);
header("access-control-allow-headers:authorization,content-type");
header("access-control-allow-methods:OPTIONS, HEAD, POST");
if($_SERVER['REQUEST_METHOD'] !="OPTIONS"){
	$upload->mkfile();
}
?>