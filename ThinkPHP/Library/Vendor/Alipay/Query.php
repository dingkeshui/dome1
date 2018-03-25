<?php
// 引入 支付宝 '单笔转账到'
include_once 'Alipay.Config.php';
include_once 'aop/AopClient.php';
include_once 'aop/SignData.php';
include_once 'aop/request/AlipayFundTransOrderQueryRequest.php';

/**
 * Class Query
 * 支付宝单笔转账
 */
class Query
{
	private $out_no;
	private $order_id;

	/**
	 * Trans constructor.
	 * 初始化要和支付宝同流合污的参数
	 * @param $out_no 商户转账唯一订单号：发起转账来源方定义的转账单据ID。
	 * @param $order_id 支付宝转账单据号：和商户转账唯一订单号不能同时为空。当和商户转账唯一订单号同时提供时，将用本参数进行查询，忽略商户转账唯一订单号。
	 */
	public function __construct($out_no, $order_id)
	{
		$this->out_no = $out_no;
		$this->order_id = $order_id;
	}

	/**
	 * [query 执行单笔转账查询[无密]]

	 */
	public function query()
	{
		$aop = new AopClient ();
		$aop->gatewayUrl = AlipayConfig::gatewayUrl; // 网关地址
		$aop->appId = AlipayConfig::appId; //appid
		$aop->rsaPrivateKey = AlipayConfig::rsaPrivateKey; // 开发者私钥
		$aop->alipayrsaPublicKey = AlipayConfig::alipayrsaPublicKey; //支付宝公钥
		$aop->apiVersion = '1.0';
		$aop->signType = AlipayConfig::signType; // 加密方式
		$aop->postCharset = AlipayConfig::charset; // 文字制式
		$aop->format = AlipayConfig::format; // 传入方式
		$request = new AlipayFundTransOrderQueryRequest ();
		$json = array(
			"out_biz_no" => $this->out_no, // 订单号
			"order_id" => $this->order_id, // 订单号
		);
		$request->setBizContent(json_encode($json)); // 格式化内容

		$result = $aop->execute($request); // 执行
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if( !empty($resultCode) && $resultCode == 10000 ){
			return $result->$responseNode;
		} else {
			return $result->$responseNode;
		}
	}

	/**
	 * 销毁罪证
	 */
	public function __destruct()
	{
		// TODO: Implement __destruct() method.
	}
}