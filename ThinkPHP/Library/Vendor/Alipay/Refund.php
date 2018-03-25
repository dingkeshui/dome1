<?php
require_once "Alipay.Config.php";
require_once 'aop/AopClient.php';
require_once 'aop/SignData.php';
require_once 'aop/request/AlipayTradeRefundRequest.php';

/**
 * Class Refund
 * 吊起支付方法
 */
class Refund
{

	public $out_trade_no;//商户订单号

	public $refund_amount;//订单金额

	public $signType;//签名方式

	public $trade_no; // 支付宝交易号，和商户订单号不能同时为空

	/**
	 * Alipay constructor.
	 * @param $notify_url
	 * 构造方法
	 */
	public function __construct($out_trade_no, $total_amount, $signType, $trade_no)
	{
		$this->out_trade_no = $out_trade_no;
		$this->refund_amount = $total_amount;
		$this->signType = $signType;
		$this->trade_no = $trade_no;
	}


	public function appRefund()
	{
		$aop = new AopClient();
		$aop->gatewayUrl = AlipayConfig::gatewayUrl;
		$aop->appId = AlipayConfig::appId;
		$aop->rsaPrivateKey = AlipayConfig::rsaPrivateKey;
		$aop->alipayrsaPublicKey = AlipayConfig::alipayrsaPublicKey;
		$aop->apiVersion = '1.0';
		$aop->signType = AlipayConfig::signType;
		$aop->postCharset = AlipayConfig::charset;
		$aop->format = AlipayConfig::format;
		$request = new AlipayTradeRefundRequest ();
		$data['out_trade_no'] = $this->out_trade_no;	
		$data['trade_no'] = $this->trade_no;
		$data['refund_amount'] = 0.01;
		$data['refund_reason'] = '提现打款';
		$data['out_request_no'] = $this->trade_no;
		$bizContent = json_encode($data);
		$request->setBizContent($bizContent);

		$result = $aop->execute($request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if( !empty($resultCode) && $resultCode == 10000 ){
			return true;
		} else {
			return false;
		}
	}

}