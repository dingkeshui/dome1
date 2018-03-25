<?php
/* *
 * MD5
 * 详细：MD5加密
 * 日期：2016-12-15
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */
 
/**
 * 签名字符串
 * @param $prestr 需要签名的字符串
 * @param $key 私钥
 * @param $merCode 商戶號
 * return 签名结果
 */
function md5Sign($prestr,$merCode, $key) {
	$prestr = $prestr . $merCode . $key;
	return md5($prestr);
}

/**
 * 验证签名
 * @param $prestr 需要签名的字符串
 * @param $sign 签名结果
 * @param $merCode 商戶號
 * @param $key 私钥
 * return 签名结果
 */
function md5Verify($prestr, $sign,$merCode, $key) {
	$prestr = $prestr .$merCode. $key;
	$mysgin = md5($prestr);
	 
	if($mysgin == $sign) {
		return true;
	}
	else {
		return false;
	}
}

/**
 * php截取<body>和</body>之間字符串
 * @param string $begin 开始字符串
 * @param string $end 结束字符串
 * @param string $str 需要截取的字符串
 * @return string
 */
function subStrXml($begin,$end,$str){
    $b= (strpos($str,$begin));
    $c= (strpos($str,$end));

    return substr($str,$b,$c-$b + 7);
}
?>