<?php
error_reporting(0);

//应与添加上传策略时生成的AccessToken值一致
define(ACCESS_KEY, "");

//分片上传最大暂存文件片倍数，超过此值无法继续使用分片上传
define(CHUNK_BUFFER_TIMES, 3);

?>