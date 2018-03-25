<?php
require_once "Alipay.Config.php";
require_once 'aop/AopClient.php';
require_once 'aop/request/AlipayTradeAppPayRequest.php';

/**
 * Class Alipay
 * 吊起支付方法
 */
class Alipay
{
	public $notify_url; //回调url

	public $out_trade_no;//订单编号

	public $total_amount;//订单金额

	public $signType;//签名方式

	/**
	 * Alipay constructor.
	 * @param $notify_url
	 * 构造方法
	 */
	public function __construct($notify_url, $out_trade_no, $total_amount, $signType,$body,$subject)
	{
		$this->notify_url = $notify_url;
		$this->out_trade_no = $out_trade_no;
//        $this->total_amount = $total_amount;
		$this->total_amount = '0.01';
		$this->signType = $signType;
		$this->body = $body;
		$this->subject = $subject;
	}

	/**
	 * 生成串去支付
	 */
	public function appPay()
	{
		$aop = new AopClient();
		$aop->gatewayUrl = AlipayConfig::gatewayUrl;
		$aop->appId = AlipayConfig::appId;
		$aop->rsaPrivateKey = AlipayConfig::rsaPrivateKey;
		$aop->format = AlipayConfig::format;
		$aop->charset = AlipayConfig::charset;
		$aop->signType = AlipayConfig::signType;
		$aop->alipayPublicKey = AlipayConfig::alipayrsaPublicKey;

		//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
		$request = new AlipayTradeAppPayRequest();

		//SDK已经封装掉了公共参数，这里只需要传入业务参数
		$data['body'] = $this->body;
		$data['subject'] = $this->subject;
		$data['out_trade_no'] = $this->out_trade_no . '';
		$data['timeout_express'] = "60m";
		$data['total_amount'] = $this->total_amount . '';
		$data['product_code'] = "QUICK_MSECURITY_PAY";
		$bizcontent = json_encode($data);

		$request->setNotifyUrl($this->notify_url);
		$request->setBizContent($bizcontent);

		//这里和普通的接口调用不同，使用的是sdkExecute
		$response = $aop->sdkExecute($request);

		return $response;
	}
}

