<?php
ini_set('date.timezone','Asia/Shanghai');
require_once("IpsPay_MD5.function.php");
require_once 'log.php';

//初始化日志
$logHandler= new CLogFileHandler("./logs/".date('Y-m-d').'.log');
$log = Log::Init($logHandler, 15);

class IpsPaySubmit
{
    var $ipspay_config;
     
    function __construct($ipspay_config){
        $this->ipspay_config = $ipspay_config;
    }
    function IpsPaySubmit($ipspay_config) {
        
        $this->__construct($ipspay_config);
    }
    /**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @return 提交表单HTML文本
     */
    function buildRequestForm($para_temp) {
       
        //待请求参数xml
        $para = $this->buildRequestPara($para_temp);
    
        $sHtml = "<form id='ipspaysubmit' name='ipspaysubmit' method='post' action='".$this->ipspay_config['PostUrl']."'>";
         
        $sHtml .= "<input type='hidden' name='wxPayReq' value='".$para."'/>";
         
        $sHtml .= "<input type='submit' style='display:none;'></form>";
    
        return $sHtml;
    }
    
    /**
     * 生成要请求给IPS的参数XMl
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数XMl
     */
    function buildRequestPara($para_temp) {
        $sReqXml = "<Ips>";
        $sReqXml .= "<WxPayReq>";
        $sReqXml .= $this->buildHead($para_temp);
        $sReqXml .= $this->buildBody($para_temp);
        $sReqXml .= "</WxPayReq>";
        $sReqXml .= "</Ips>";
        Log::DEBUG("请求给IPS的参数XMl:" . $sReqXml);
        return $sReqXml;
    }
    /**
     * 请求报文头
     * @param   $para_temp 请求前的参数数组
     * @return 要请求的报文头
     */
    function buildHead($para_temp){
        $sReqXmlHead = "<head>";
        $sReqXmlHead .= "<Version>".$this->ipspay_config["Version"]."</Version>";
        $sReqXmlHead .= "<MerCode>".$para_temp["MerCode"]."</MerCode>";
        $sReqXmlHead .= "<MerName>".$para_temp["MerName"]."</MerName>";
        $sReqXmlHead .= "<Account>".$para_temp["Account"]."</Account>";
        $sReqXmlHead .= "<MsgId>".$this->ipspay_config["MsgId"]."</MsgId>";
        $sReqXmlHead .= "<ReqDate>".$para_temp["ReqDate"]."</ReqDate>";
        $sReqXmlHead .= "<Signature>".md5Sign($this->buildBody($para_temp),$para_temp["MerCode"],$this->ipspay_config['MerCert'])."</Signature>";
        $sReqXmlHead .= "</head>";
        return $sReqXmlHead;
    }
    /**
     *  请求报文体
     * @param  $para_temp 请求前的参数数组
     * @return 要请求的报文体
     */
    function buildBody($para_temp){
        $sReqXmlBody = "<body>";
        $sReqXmlBody .= "<MerBillno>".$para_temp["MerBillno"]."</MerBillno>";
        $sReqXmlBody .= "<GoodsInfo>";
        $sReqXmlBody .= "<GoodsName>".$para_temp["GoodsName"]."</GoodsName>";
        $sReqXmlBody .= "<GoodsCount>".$para_temp["GoodsCount"]."</GoodsCount>";
        $sReqXmlBody .= "</GoodsInfo>";
        $sReqXmlBody .= "<OrdAmt>".$para_temp["OrdAmt"]."</OrdAmt>";
        $sReqXmlBody .= "<OrdTime>".$para_temp["OrdTime"]."</OrdTime>";
        $sReqXmlBody .= "<MerchantUrl>".$para_temp["MerchantUrl"]."</MerchantUrl>";
        $sReqXmlBody .= "<ServerUrl>".$para_temp["ServerUrl"]."</ServerUrl>";
        $sReqXmlBody .= "<BillEXP>".$para_temp["BillExp"]."</BillEXP>";
        $sReqXmlBody .= "<ReachBy>".$para_temp["ReachBy"]."</ReachBy>";
        $sReqXmlBody .= "<ReachAddress>".$para_temp["ReachAddress"]."</ReachAddress>";
        $sReqXmlBody .= "<CurrencyType>".$para_temp["CurrencyType"]."</CurrencyType>";
        $sReqXmlBody .= "<Attach>".$para_temp["Attach"]."</Attach>";
        $sReqXmlBody .= "<RetEncodeType>".$para_temp["RetEncodeType"]."</RetEncodeType>";
        $sReqXmlBody .= "</body>";
        return $sReqXmlBody;
    }
}

?>