<?php

class Auth{

	private $sign;

	public function __construct($sign){
		$this->sign = $sign;
	}

	public function check($policy,$method){
		$signingKey = hash_hmac("sha256",json_encode($policy),$method.ACCESS_KEY);
		return ($signingKey==$this->sign);
	}

	public function sign($content,$method = null){
		return hash_hmac("sha256",base64_encode(json_encode($content)),$method.ACCESS_KEY);
	}

	public function checkPost(){
		$signingKey = hash_hmac("sha256",$_POST["object"],$_POST["action"].ACCESS_KEY);
		if($signingKey != $this->sign){
			die("auth failed");
		}
	}

	public function checkGET(){
		$urlNow = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$signingKey = hash_hmac("sha256",str_replace("&auth=".$this->sign,"",$urlNow),"GET".ACCESS_KEY);
		if($signingKey != $this->sign || $_GET["expires"] < time()){
			die("auth failed");
		}
	}

}

?>