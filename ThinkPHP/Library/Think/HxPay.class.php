<?php
// +----------------------------------------------------------------------
// | 环迅支付类
// +----------------------------------------------------------------------
// | Author: 刘柱
// | time 2017-09-21
// +----------------------------------------------------------------------
namespace Think;
class HxPay{
    /**定义一个属性，存储对象*/
    private static $_instance = null;
    private function __construct(){

    }
    /**实例化对象*/
    public static function get_instance(){
        if(self::$_instance === null){
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**防止被复制*/
    private function __clone(){
        trigger_error("禁止被复制！",E_USER_ERROR);
    }

    /**
     * 环迅的创建子账户的方法
     * $param $argMerCode
     * $param $reqIp
     * $param $reqDate  格式：yyyy-MM-dd hh24:mi:ss
     * $param $merAcctNo 商户账户号
     * $param $userType 非直销用户类型1:企业,2个人
     * $param $identityType证件类型:1:身份证;2营业执照直销用户可不用填写
     * $param $identityNo 证件号码直销用户可不用填写
     * $param $userName  个人用户名为个人姓名,企业为企业名称
     * $param $mobiePhoneNo 手机号码
     * $param $pageUrl 同步返回地址(页面响应地址)
     * $param $s2sUrl 异步返回地址
    */
    public function createChildAccount($argMerCode,$reqIp,$reqDate,$merAcctNo,$userType,$customerCode,$identityType,$identityNo,$userName,$mobiePhoneNo,$pageUrl,$s2sUrl){
        $signature = $this->md5Method($merAcctNo,$userType,$customerCode,$identityType,$identityNo,$userName,$mobiePhoneNo,$pageUrl,$s2sUrl);
        echo "签名： $signature <br />";
        $head = "<head><version>V1.0.0</version><reqIp>$reqIp</reqIp><reqDate>$reqDate</reqDate><signature>$signature</signature></head>";
        $body = "<body><merAcctNo>$merAcctNo</merAcctNo><userType>$userType</userType><customerCode>$customerCode</customerCode><identityType>$identityType</identityType><identityNo>$identityNo</identityNo><userName>$userName</userName><legalName></legalName><legalCardNo></legalCardNo><mobiePhoneNo>$mobiePhoneNo</mobiePhoneNo><telPhoneNo></telPhoneNo><email></email><contactAddress></contactAddress><remark></remark><pageUrl>$pageUrl</pageUrl><s2sUrl>$s2sUrl</s2sUrl><directSell></directSell><stmsAcctNo></stmsAcctNo></body>";
        //3des秘钥
        $deskey = "tFcybKzbyVckNHph159ap9br";
        //3des向量
        $desiv ="XuDcKdZa";
        $ds3 = new IPSUtils($deskey,$desiv);
        $openUserReqXml = "<openUserReqXml>".$head.$body."</openUserReqXml>";
        echo "openUserReqXml  明文：$openUserReqXml <br />";
        $arg3 = $ds3->encrypt($openUserReqXml);
        echo "openUserReqXml  密文：$arg3  <br />";
        $result = "<ipsRequest><argMerCode>$argMerCode</argMerCode><arg3DesXmlPara>$arg3</arg3DesXmlPara></ipsRequest>";
        echo "$result <br />";
        return $result;
    }

    public function md5Method($merAcctNo,$userType,$customerCode,$identityType,$identityNo,$userName,$mobiePhoneNo,$pageUrl,$s2sUrl){
        $MerCret = "UrCsGRwGx3SyymtH3zzB5DeSqayLQUOyqghnjh2LwvoO9GdDEUggeTsjph50t3Qz5sspHD3ExqjLse71X1uXtDNYsGYu0YAWrdsduM44zz5CWT1Yxq28NlEPe09UfHyU";
        $body = "<body><merAcctNo>$merAcctNo</merAcctNo><userType>$userType</userType><customerCode>$customerCode</customerCode><identityType>$identityType</identityType><identityNo>$identityNo</identityNo><userName>$userName</userName><legalName></legalName><legalCardNo></legalCardNo><mobiePhoneNo>$mobiePhoneNo</mobiePhoneNo><telPhoneNo></telPhoneNo><email></email><contactAddress></contactAddress><remark></remark><pageUrl>$pageUrl</pageUrl><s2sUrl>$s2sUrl</s2sUrl><directSell></directSell><stmsAcctNo></stmsAcctNo></body>";
        return md5($body.$MerCret);
    }

    /**
     * @param $xml
     * @return mixed
     * ：将xml转为array
     */
    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

}
