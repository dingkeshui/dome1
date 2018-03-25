<?php
require_once "Alipay.Config.php";
require_once 'aop/AopClient.php';

/**
 * Class Notify
 */
class Notify{
    /**
     * Notify constructor.
     * @param $notify_url
     * 构造方法
     */
    public function __construct()
    {
    }


    /**
     * 支付回调
     */
    public function rsaCheck(){
        $aop = new AopClient();
        $aop->alipayrsaPublicKey = AlipayConfig::alipayrsaPublicKey;
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        return $flag;
    }
}