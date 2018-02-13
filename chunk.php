<?php
require_once("config.php");
require_once("upload.php");

$upload = new Upload(false);
$upload->chunkInit();
?>