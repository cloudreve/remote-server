<?php

require_once("lib/Thumb.php");

class File{


	static function thumb($fname){
		$height = isset($_GET["h"]) ? $_GET["h"] : 0;
		$width = isset($_GET["w"]) ? $_GET["w"] : 0;
		$picInfo = self::getThumbSize($width,$height);
		if(file_exists( "thumbs/".$fname."_thumb")){
			self::outputThumb("thumbs/".$fname."_thumb");
		}
		$thumbImg = new Thumb("uploads/".$fname);
		$thumbImg->thumb($picInfo[1], $picInfo[0]);
		if(!is_dir(dirname("thumbs/".$fname))){
			mkdir(dirname("thumbs/".$fname),0777,true);
		}
		$thumbImg->out("thumbs/".$fname."_thumb");
		self::outputThumb("thumbs/".$fname."_thumb");
	}

	static function outputThumb($path){
		ob_end_clean();
		header("Cache-Control: max-age=10800");
		header('Content-Type: '.self::getMimetype($path)); 
		$fileObj = fopen($path,"r");
		echo fread($fileObj,filesize($path)); 
		fclose($file); 
	}

	static function update($fname,$content){
		$filePath = 'uploads/' . $fname;
		file_put_contents($filePath, "");
		file_put_contents($filePath, $content);
	}

	static function getThumbSize($width,$height){
		$rate = $width/$height;
		$maxWidth = 90;
		$maxHeight = 39;
		$changeWidth = 39*$rate;
		$changeHeight = 90/$rate;
		if($changeWidth>=$maxWidth){
			return [(int)$changeHeight,90];
		}
		return [39,(int)$changeWidth];
	}

	static function delete($fileList){
		$fileList = json_decode(base64_decode($fileList),true);
		foreach ($fileList as $key => $value) {
			@unlink("uploads/".$value);
			if(file_exists('thumbs/'.$value."_thumb")){
				@unlink('thumbs/'.$value."_thumb");
			}
		}
	}

	static function preview($fname,$download = false){
		$filePath = 'uploads/' . $fname;
		@set_time_limit(0);
		session_write_close();
		$file_size = filesize($filePath);  
		$ranges = self::getRange($file_size);
		if($ranges!=null){
			header('HTTP/1.1 206 Partial Content');  
			header('Accept-Ranges:bytes');  
			header(sprintf('content-length:%u',$ranges['end']-$ranges['start']));  
			header(sprintf('content-range:bytes %s-%s/%s', $ranges['start'], $ranges['end']-1, $file_size));  
		}
		if($download){
			header('Cache-control: private');
			header('Content-Type: application/octet-stream'); 
			header('Content-Length: '.filesize($filePath)); 
			header('Content-Disposition: filename='.str_replace(",","",isset($_GET["attaname"]) ? urldecode($_GET["attaname"]) : "download")); 
			ob_flush();
			flush();
		}
		if(file_exists($filePath)){
			if(!$download){
				header('Content-Type: '.self::getMimetype($filePath)); 
				ob_flush();
				flush();
			}
			$fileObj = fopen($filePath,"rb");
			fseek($fileObj, sprintf('%u', $ranges['start']));
			while(!feof($fileObj)){
				echo fread($fileObj,10240);
				ob_flush();
				flush();
			} 
			fclose($fileObj);
		}
	}

	static function getMimetype($path){
		$finfoObj    = finfo_open(FILEINFO_MIME);
		$mimetype = finfo_file($finfoObj, $path);
		finfo_close($finfoObj);
		return $mimetype;
	}

	static function getRange($file_size){  
		if(isset($_SERVER['HTTP_RANGE']) && !empty($_SERVER['HTTP_RANGE'])){  
			$range = $_SERVER['HTTP_RANGE'];  
			$range = preg_replace('/[\s|,].*/', '', $range);  
			$range = explode('-', substr($range, 6));  
			if(count($range)<2){  
				$range[1] = $file_size;  
			}  
			$range = array_combine(array('start','end'), $range);  
			if(empty($range['start'])){  
				$range['start'] = 0;  
			}  
			if(empty($range['end'])){  
				$range['end'] = $file_size;  
			}  
			return $range;  
		}  
		return null;  
	}

	static function clean(){
		@unlink("chunks");
		@mkdir("chunks");
	}

}
?>