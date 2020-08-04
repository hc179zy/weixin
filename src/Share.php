<?php
namespace hcgrzh\weixin;
class Share{
	private static $appid;
	private static $appSecret;
	private static $access_token_suffix_filename;//保存accesstoken 数据
	private static $jsapi_ticket_suffix_fileaname;//保存jsapi_ticket数据
	private static $message=array();
	private static $timeout=10;
	private static $access_token_cache_dir="./";//微信生成access_token 缓存保存目录
	private static function setError($message=''):void {
		self::$message[]=$message;
	}	
	public static function getErrorArray():array{
		return self::$message;
	}	
	public static function getErrorString($role=','):string{
		return implode(',',self::$message);
	}
	//判断是否json 数据、
	private static function is_json($string):bool{
 		json_decode($string);
 		return (json_last_error() == JSON_ERROR_NONE);
	}
	//设置超时
	public static function setTimeout($timeout):void{
		self::$timeout=$time;
	}
	//判断是否微信客服端打开
	public static function isWeixinClient():bool{
		if(strpos($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')!==false) {
			return true;
		}else{
			self::setError("不是微信客服端打开,无分享");
			return false;
		}
	}
	//access_token 缓存目录设置
	public static function setAccessTokenCacheDir($dir):void{
		self::$access_token_cache_dir=rtrim($dir,'/').'/';
	}
	private static function request($url='',$type='get',$data =array(),$header=array()){
		$error='';
		if($url==''){$error='url不能为空';return $error;}
	    $curl=curl_init();
	    curl_setopt($curl, CURLOPT_URL, $url);
	    curl_setopt($curl, CURLOPT_TIMEOUT,self::$timeout);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
	    if ($type=='post'){
	        curl_setopt($curl, CURLOPT_POST, 1);
	        // if(is_array($data)){
	        //     $data=http_build_query($data);
	        // }
	        curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
	    }
	    if(!empty($header)){
	        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	    }
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	    $output = curl_exec($curl);
	    // 检查是否有错误发生
	    if(curl_errno($curl)){
	        $error='Curl error: ' . curl_error($curl);
	    }
	    curl_close($curl);
	    if($error!=''){
	        return $error;
	    }
	    return $output;
	}
	public static function getSignPackage($appid,$appSecret,$suffix='share'){
		self::$appid=$appid;
		self::$appSecret=$appSecret;
		if(is_writable(self::$access_token_cache_dir)===false){
			self::setError("access_token 缓存目录不可写,无法生成缓存文件");
			return false;
		}
		self::$access_token_suffix_filename=self::$access_token_cache_dir."access_token_".$suffix.".json";
		if(is_writable(self::$access_token_suffix_filename)===false){
			self::setError("access_token 缓存文件不可写");
			return false;
		}
		self::$jsapi_ticket_suffix_fileaname=self::$access_token_cache_dir.'jsapi_ticket_'.$suffix.".json";
		if(is_writable(self::$jsapi_ticket_suffix_fileaname)===false){
			self::setError("jsapi_ticket缓存文件不可写");
			return false;
		}
		$access_token=self::getAccessToken();
		if($access_token===false){return false;}
		$jsapiTicket=self::getTicket($access_token);
		if($jsapiTicket===false){return false;}
	    // 注意 URL 一定要动态获取，不能 hardcode.
	    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
	    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	    $timestamp = time();
	    $nonceStr = self::createNonceStr();
	    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
	    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
	    $signature = sha1($string);
	    $signPackage = array(
	      "appId"     => $appid,
	      "nonceStr"  => $nonceStr,
	      "timestamp" => $timestamp,
	      "url"       => $url,
	      "signature" => $signature,
	      "rawString" => $string
	    );
	    return $signPackage; 
	}
	private static function createNonceStr($length = 16) {
	    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	    $str = "";
	    for ($i = 0; $i < $length; $i++) {
	      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
	    }
	    return $str;
  	}
	private static function getAccessToken(){
		if(!is_file(self::$access_token_suffix_filename)){//判断token文件是否存在
			$access_token=self::getCurlAccessToken();
			return $access_token;
		}else{
			$access_token_json=file_get_contents(self::$access_token_suffix_filename);
			$access_token_data=json_decode($access_token_json,true);
			$time=time();
			//判断acctoken_token 是否过期
			if(isset($access_token_data['access_token']) && isset($access_token_data['access_token_endtime']) && $access_token_data['access_token_endtime']>$time){//access_token有效
				$access_token=$access_token_data['access_token'];
			}else{
				$access_token=self::getCurlAccessToken();
			}
			return $access_token;
		}
	}
	//access_token
	private static function getCurlAccessToken(){
		$url='https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.self::$appid.'&secret='.self::$appSecret;
		$result=self::request($url);
		if(!self::is_json($result)){
			self::setError("获取微信分享access_token错误".$result);
			return false;
		}
		$access_token_data=json_decode($result,true);
		if(isset($access_token_data['errcode'])){
			self::setError("获取微信分享access_token错误".$result);
			return false;
		}
		$time=time();
		$access_token_data['access_token_endtime']=$time+7000;
		$access_token_data['start_time']=time();
		$access_token_json=json_encode($access_token_data);
		$write_file=@file_put_contents(self::$access_token_suffix_filename,$access_token_json);
		if(!$write_file){
			self::setError("(没有写入权限)获取微信分享写入日志access_token错误".$result);
			return false;
		}
		if(isset($access_token_data['access_token'])){
			return $access_token_data['access_token'];
		}else{
			self::setError("获取微信分享access_token错误".$result);
			return false;
		}
	}
	private static function getTicket($access_token){
		if(!is_file(self::$jsapi_ticket_suffix_fileaname)){//判断token文件是否存在
			$ticket=self::getCurlTicket($access_token);
			return $ticket;
		}else{
			$ticket_json=file_get_contents(self::$jsapi_ticket_suffix_fileaname);
			$ticket_data=json_decode($ticket_json,true);
			$time=time();
			//判断acctoken_token 是否过期
			if(isset($ticket_data['ticket']) && isset($ticket_data['ticket_endtime']) && $ticket_data['ticket_endtime']>$time){//access_token有效
				$ticket=$ticket_data['ticket'];
			}else{
				$ticket=self::getCurlTicket($access_token);
			}
			return $ticket;
		}
	}
	//获取ticket值
	private static function getCurlTicket($access_token){
		$url='https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
		$result=self::request($url);
		if(!self::is_json($result)){
			self::setError("获取微信分享getticket错误".$result);
			return false;
		}
		$ticket_data=json_decode($result,true);

		if(!isset($ticket_data['errcode']) || $ticket_data['errcode']!=0){
			self::setError("获取微信分享getticket错误".$result);
			return false;
		}
		$time=time();
		$ticket_data['ticket_endtime']=$time+7000;
		$ticket_data['start_time']=time();
		$ticket_data_json=json_encode($ticket_data);
		$write_file=@file_put_contents(self::$jsapi_ticket_suffix_fileaname,$ticket_data_json);
		if(!$write_file){
			self::setError("(没有写入权限)获取微信分享写入日志获取ticket值错误".$result);
			return false;
		}
		if(isset($ticket_data['ticket'])){
			return $ticket_data['ticket'];
		}else{
			self::setError("获取微信分享getticket错误".$result);
			return false;
		}
	}
}
?>