<?php
namespace Think;
require_once "WxPay.Config.php";
require_once "WxPay.Data.php";
class WechatSign{


    private static $instance = null;
    private function __construct(){
    }

    public static function getInstance(){
        if(self::$instance === null){
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     *  作用：格式化参数，签名过程需要使用
     */
    function formatBizQueryParaMap($paraMap)
    {
        $buff = "";
        foreach ($paraMap as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     *  作用：生成签名
     */
    function getSign($Obj)
    {
        //签名步骤一：按字典序排序参数
        ksort($Obj);
        //dump($Obj);
        $String = $this->formatBizQueryParaMap($Obj);
        //echo '【string1】'.$String.'</br>';
        //签名步骤二：在string后加入KEY
        $String = $String."&key=fcabc10f4d855cbeb9f8ae0a060164ed";
        //echo "【string2】".$String."</br>";
        //签名步骤三：MD5加密
        $String = md5($String);
        //echo "【string3】 ".$String."</br>";
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        //echo "【result】 ".$result_."</br>";
        return $result_;
    }


    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }



    /**
     * 获取接口信息，这个是万能的调用微信api方法的函数，可以自定的加载url，然后返回你想得到的数据
     */
    public function httpsRequest($url,$data = null){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl,CURLOPT_SSLCERT,getcwd()."/cert/apiclient_cert.pem");
        curl_setopt($curl,CURLOPT_SSLKEY,getcwd()."/cert/apiclient_key.pem");
        curl_setopt($curl,CURLOPT_CAINFO,getcwd()."/cert/rootca.pem");
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

}