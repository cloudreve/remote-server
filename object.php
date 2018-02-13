<?php
require_once("config.php");
require_once("file.php");
require_once("auth.php");

header("Access-Control-Allow-Origin: *");
$auth = new Auth($_GET["auth"]);
$auth->checkGet();
switch ($_GET["action"]) {
	case 'preview':
		File::preview(urldecode($_GET["name"]));
		break;
	case 'download':
		File::preview(urldecode($_GET["name"]),true);
		break;
	case 'thumb':
		File::thumb(urldecode($_GET["name"]),true);
		break;
	case 'clean':
		File::clean();
		break;
	default:
		echo "none";
		break;
}

?>