<?php
require_once('vendor/autoload.php');
if(hcgrzh\weixin\Share::isWeixinClient()===true){
	//hcgrzh\weixin\Share::setTimeout(30);//默认超时10秒
	$setAccessTokenCacheDir='./';
	hcgrzh\weixin\Share::setAccessTokenCacheDir($setAccessTokenCacheDir);//缓存跟目录
	$signPackage=hcgrzh\weixin\SharesignPackage::getSignPackage('wx310e0c1f69518001','f01230c64509877cda1a8e8601b083e6');
	if($signPackage){
		$title="";
		$desc="";
		$link="";
		$imgUrl="";
		if($imgUrl!=""){
			$getimgdata=@getimagesize($imgUrl);//注意是否可以访问图片信息 不然会卡死
			if($getimgdata===false){
				$imgUrl=WEIXIN_SHARE_DEFAULTIMG;
			}elseif($getimgdata[0]>300 || $getimgdata[1]>300){
				$imgUrl=$this->baseconfig['site_urlroot'].'/static/defaultwap/img/sharelogo.png';
			}
		}else{//如果不存在图片则设置默认图片
			$imgUrl=WEIXIN_SHARE_DEFAULTIMG;
		}
		$sharedata['title']=$title;
		$sharedata['desc']=$desc==""?$title:$desc;
		$sharedata['link']=$link;
		$sharedata['imgUrl']=$imgUrl;
	}else{
		echo '<script>console.log("'.hcgrzh\weixin\Share::getErrorString().'");</script>';
	}
}else{
	echo '<script>console.log("'.hcgrzh\weixin\Share::getErrorString().'");</script>';
}
?>
<script src="http://res.wx.qq.com/open/js/jweixin-1.0.0.js"></script>
<script>
  var signPackage=''
  var shareData = {
	//标题
	 title: '{$sharedata.title}',
	//摘要
	  desc: '{$sharedata.desc}',
	//链接,可以换主页
	  link: '{$sharedata.link}',
	//缩略图
	  imgUrl: '{$sharedata.imgUrl}',
  };
  wx.config({
    debug: false,
    appId: '{$signPackage.appId}',
    timestamp: '{$signPackage.timestamp}',
    nonceStr: '{$signPackage.nonceStr}',
    signature: '{$signPackage.signature}',
    jsApiList: [
      // 所有要调用的 API 都要加到这个列表中
      	'checkJsApi',
        'onMenuShareTimeline',
        'onMenuShareAppMessage',
        'onMenuShareQQ',
        'onMenuShareQZone'
    ]
  });
  wx.ready(function () {
    //通过ready接口处理成功验证
		wx.checkJsApi({
          jsApiList: [
            'getNetworkType',
            'previewImage',
            'onMenuShareTimeline',
            'onMenuShareAppMessage',
            'onMenuShareQQ',
            'onMenuShareQZone'
          ],
        }); 
       	wx.onMenuShareAppMessage(shareData);
       	wx.onMenuShareTimeline(shareData);
      	wx.onMenuShareQQ(shareData);
      	wx.onMenuShareQZone(shareData);
  });
  wx.error(function(res){
  //通过error接口处理失败验证
  		
  });
</script>