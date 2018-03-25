<?php
namespace jzq\test\keysearch;
require_once __DIR__ . '/../../vendor/autoload.php';
//* 注意端口号要指定好
define("JAVA_HOSTS","127.0.0.1:8999");
require_once("../../java-bridge/java/Java.inc");

use com\jzq\api\model\bean\Signatory;
use com\jzq\api\model\menu\DealType;
use com\jzq\api\model\menu\IdentityType;
use com\jzq\api\model\sign\ApplySignFileRequest;
use Java;
use jzq\test\ClientInfo;
use org\ebq\api\model\bean\UploadFile;
use org\ebq\api\tool\RopUtils;

/***
 * 这个是一个使用关键字查找位置并上传文件签约多方例子
 * 使用一次$KeySearchUtil->matchPage取到签字位置后再分开上传
 */
//组建请求参数
$requestObj=new ApplySignFileRequest();
//组建请求参数
//* 签约文件
$requestObj->file=new UploadFile("/tmp/test.pdf");
//* 合同名称
$requestObj->contractName="合同0001";
//是否使用云证书1使用,其它:不使用
$requestObj->serverCa=1;
//签约处理类型
$requestObj->dealType=DealType::$AUTH_SIGN;

/**************************使用关键字查找签字位置 start*******************************/
java_set_file_encoding("UTF-8");
java_set_encoding("UTF-8");
//--实例化java工具类
$KeySearchUtil=new Java("com.junziqian.api.common.keysearch.KeySearchUtil");
//查找关键字下的所有匹配位置
$matchMap=$KeySearchUtil->matchPage("/tmp/test.pdf",array("人脸","测试"));
/**************************使用关键字查找签字位置 end*******************************/
//签约方
$signatories=array();
//签约方1
$signatory=new Signatory();
//* 证件类型
$signatory->setSignatoryIdentityType(IdentityType::$IDCARD);
//* 名称或公司名称
$signatory->fullName="//TODO *名称或公司名称";
//* 证件号码、营业执照号、社会信用号
$signatory->identityCard="//TODO 证件号";
//* 手机号码
$signatory->mobile='//TODO *手机号码';
// 签字位置
//将位置转换为签约位置(和上一步分开，是使关键字位置点与签约方签约位置解耦)
$chapteJsonArray=java_values($KeySearchUtil->convertChapteJson( array($matchMap->get("人脸")) ));
//assert(is_array($chapteJsonArray)&&count($chapteJsonArray)>0,"通过关键字没有查找到可用签字的位置");
$signatory->chapteJson=$chapteJsonArray;
array_push($signatories, $signatory);

//签约方2
$signatory=new Signatory();
//* 证件类型
$signatory->setSignatoryIdentityType(IdentityType::$IDCARD);
//* 名称或公司名称
$signatory->fullName="//TODO *名称或公司名称";
//* 证件号码、营业执照号、社会信用号
$signatory->identityCard="//TODO 证件号";
//* 手机号码
$signatory->mobile='//TODO *手机号码';
// 签字位置
$chapteJsonArray=java_values($KeySearchUtil->convertChapteJson( array($matchMap->get("测试")) ));
//assert(is_array($chapteJsonArray)&&count($chapteJsonArray)>0,"通过关键字没有查找到可用签字的位置");
$signatory->chapteJson=$chapteJsonArray;
array_push($signatories, $signatory);

$requestObj->signatories=$signatories;
//请求
$response=RopUtils::doPostByObj($requestObj,ClientInfo::$app_key,ClientInfo::$app_secret,ClientInfo::$services_url);
//以下为返回的一些处理
$responseJson=json_decode($response);
print_r("response:".$response."</br>");
print_r("format:</br>");
var_dump($responseJson); //null
if($responseJson->success){
    echo $requestObj->getMethod()."->处理成功";
}else{
    echo $requestObj->getMethod()."->处理失败";
}