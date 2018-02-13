<?php
require_once("config.php");
require_once("file.php");
require_once("auth.php");

$auth = new Auth($_POST["auth"]);
$auth->checkPost();
switch ($_POST["action"]) {
	case 'DELETE':
		File::delete($_POST["object"]);
		break;

	case 'UPDATE':
		$reqInfo = json_decode(base64_decode($_POST["object"]),true);
		File::update($reqInfo["fname"],$reqInfo["content"]);
		break;
	default:
		# code...
		break;
}

?>