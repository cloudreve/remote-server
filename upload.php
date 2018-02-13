<?php
require_once("auth.php");

class Upload{

	private $policy;
	private $signKey;
	private $errMsg;
	private $fileSize;
	private $fileName;
	private $originName;
	private $filePath;
	private $frontPath;
	private $auth;
	private $chunkData;
	private $showMsg;

	public function __construct($msg=true){
		header("Access-Control-Allow-Origin: *");
		$this->showMsg = $msg;
		$this->policy = isset($_POST["token"]) ? $_POST["token"] : $this->quit();
		$this->policy = empty($this->policy) ? $_SERVER['HTTP_AUTHORIZATION'] :$this->policy;
	}

	public function chunkInit(){
		header("access-control-allow-headers:authorization,content-type");
		header("access-control-allow-methods:OPTIONS, HEAD, POST");
		$policyInfo = explode(":", $this->policy);
		$this->signKey = $policyInfo[0];
		$this->policy = json_decode(base64_decode($policyInfo[1]),true);
		$this->auth = new Auth($this->signKey);
		if(!$this->auth->check($this->policy,"UPLOAD") && $_SERVER['REQUEST_METHOD'] !="OPTIONS"){
			self::setError("auth failed.");
		}
		if($_SERVER['REQUEST_METHOD'] !="OPTIONS"){
			$this->chunkData = file_get_contents("php://input");
			$this->fileSize = strlen($this->chunkData);
			if(!$this->validCheck(true)){
				self::setError($this->errMsg);
			}
			$this->saveChunk();
		}
	}

	public function init(){
		header("Content-Type:text/json");
		$policyInfo = explode(":", $this->policy);
		$this->signKey = $policyInfo[0];
		$this->policy = json_decode(base64_decode($policyInfo[1]),true);
		$this->frontPath = isset($_POST["path"]) ? $_POST["path"] : "";
		$this->auth = new Auth($this->signKey);
		if(!$this->auth->check($this->policy,"UPLOAD")){
			self::setError("auth failed.");
		}
		$this->fileSize = $_FILES["file"]["size"];
		if(!$this->validCheck(false)){
			self::setError($this->errMsg);
		}
		$this->saveFile();
	}

	private function quit(){
		if($this->showMsg){
			die ("Cloudreve Remote Server");
		}
	}

	private function validCheck($chunk=false){
		if($chunk && $this->fileSize > 4194350){
			$this->errMsg = "File is to large.";
			return false;
		}
		if($this->fileSize > $this->policy["fsizeLimit"]){
			$this->errMsg = "文件太大";
			return false;
		}
		return true;

	}

	public function saveChunk(){
		$this->fileName=md5(uniqid());
		if(!is_dir("chunks/" . $this->policy["uid"])){
			mkdir("chunks/" . $this->policy["uid"],0777,true);
		}
		if(function_exists("scandir")){
			$chunkList = scandir("chunks/" . $this->policy["uid"]);
			if(count($chunkList)*4194304 >= CHUNK_BUFFER_TIMES*$this->policy["fsizeLimit"]){
				self::setError("分片缓冲区已满，请等待系统回收");
			}
		}
		if(!file_put_contents("chunks/" .$this->policy["uid"] ."/".$this->fileName,$this->chunkData)){
			self::setError("文件转移失败");
		};
		echo json_encode(["ctx" => $this->fileName]);
	}

	public function saveFile(){
		$this->fileName = str_replace('$(fname)', $_FILES["file"]["name"], $this->policy["saveKey"]);
		if (file_exists("uploads/" . $this->fileName)){
			self::setError("文件冲突");
		}
		if(!is_dir("uploads/" . dirname($this->fileName))){
			mkdir("uploads/" . dirname($this->fileName),0777,true);
		}
		$this->originName =  $_FILES["file"]["name"];
		if(!move_uploaded_file($_FILES["file"]["tmp_name"],"uploads/" .$this->fileName)){
			self::setError("文件转移失败");
		};
		$this->filePath = "uploads/" .$this->fileName;
		$this->sendCallback();
	}

	public static function setError($msg){
		header("HTTP/1.1 401 Unauthorized");
		die(json_encode(["error"=> $msg]));
	}

	static function urlsafe_b64decode($string) {
		$data = str_replace(array('-','_'),array('+','/'),$string);
		$mod4 = strlen($data) % 4;
		if ($mod4) {
			$data .= substr('====', $mod4);
		}
		return base64_decode($data);
	}

	public function mkfile(){
		$policyInfo = explode(":", $this->policy);
		$this->signKey = $policyInfo[0];
		$this->policy = json_decode(base64_decode($policyInfo[1]),true);
		$this->auth = new Auth($this->signKey);
		if(!$this->auth->check($this->policy,"UPLOAD")){
			self::setError("auth failed.");
		}
		$this->originName = self::urlsafe_b64decode($_GET["fname"]);
		$chunkList = explode(",",file_get_contents("php://input"));
		$this->combineChunk($chunkList);
	}

	public function combineChunk($chunkList){
		$fileName = "file_".md5(uniqid());
		$fileObj=fopen ('chunks/'.$this->policy["uid"]."/".$fileName,"a+");
		foreach ($chunkList as $key => $value) {
			$chunkObj = fopen('chunks/'.$this->policy["uid"]."/".$value, "rb");
			if(!$fileObj || !$chunkObj){
				self::setError("文件创建失败");
			}
			$content = fread($chunkObj, 4195304);
			fwrite($fileObj, $content, 4195304);
			unset($content);
			fclose($chunkObj);
			unlink('chunks/'.$this->policy["uid"]."/".$value);
		}
		fclose($fileObj);
		$this->generateFile($fileName);
	}

	private function generateFile($fileName){
		$this->fileSize = filesize('chunks/'.$this->policy["uid"]."/".$fileName);
		if(!$this->validCheck(false)){
			unlink('chunks/'.$this->policy["uid"]."/".$fileName);
			self::setError($this->errMsg);
		}
		$this->fileName = str_replace('$(fname)', $this->originName, $this->policy["saveKey"]);
		if (file_exists("uploads/" . $this->fileName)){
			unlink('chunks/'.$this->policy["uid"]."/".$fileName);
			self::setError("文件冲突");
		}
		if(!is_dir("uploads/" . dirname($this->fileName))){
			mkdir("uploads/" . dirname($this->fileName),0777,true);
		}
		if(!@rename('chunks/'.$this->policy["uid"]."/".$fileName,"uploads/" .$this->fileName)){
			@unlink('chunks/'.$this->policy["uid"]."/".$fileName);
			self::setError("文件转移失败");
		};
		$this->filePath = "uploads/" .$this->fileName;
		$this->sendCallback();
	}

	public function sendCallback(){
		@list($width, $height, $img_type, $attr) = getimagesize($this->filePath);
		$picInfo = empty($width)?" ":$width.",".$height;
		$callbackBody = [
			"fname" => $this->originName,
			"objname" => $this->fileName,
			"path" => $this->frontPath,
			"fsize" => $this->fileSize,
			"callbackkey" => $this->policy["callbackKey"],
			"picinfo" => $picInfo,
		];
		$session = curl_init($this->policy["callbackUrl"]);
		$headers = array();
		$headers[] = "Authorization: " . $this->auth->sign($callbackBody);
		curl_setopt($session, CURLOPT_HTTPHEADER, $headers); 
		curl_setopt($session, CURLOPT_POST, 1);
		curl_setopt($session, CURLOPT_POSTFIELDS, base64_encode(json_encode($callbackBody)));
		curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($session, CURLOPT_SSL_VERIFYHOST, false);
		$server_output = curl_exec($session);
		$httpCode = curl_getinfo($session,CURLINFO_HTTP_CODE); 
		curl_close ($session);
		if(!$server_output){
			unlink("uploads/" .$this->fileName);
			self::setError("回调无法发起");
		}
		if($httpCode != 200){
			$resutltData = json_decode($server_output,true);
			$errorMsg = isset($resutltData["error"]) ? $resutltData["error"] : "回调失败，未知错误";
			unlink("uploads/" .$this->fileName);
			self::setError($errorMsg);
		}else{
			echo '{"key":"success"}';
		}
	}

}

?>