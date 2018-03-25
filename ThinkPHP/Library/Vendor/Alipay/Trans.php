<?php
// 引入 支付宝 '单笔转账到'
include_once 'Alipay.Config.php';
include_once 'aop/AopClient.php';
include_once 'aop/SignData.php';
include_once 'aop/request/AlipayFundTransToaccountTransferRequest.php';

/**
 * Class Trans
 * 支付宝单笔转账
 */
class Trans
{
	private $out_no;
	private $account;
	private $amount;
	private $payer_name;
	private $name;
	private $remark;

	/**
	 * Trans constructor.
	 * 初始化要和支付宝同流合污的参数
	 * @param $out_no 退款单号
	 * @param $account 支付宝账号
	 * @param $amount 提现金额
	 * @param $payer_name 付款方显示姓名
	 * @param $name 收款方显示姓名
	 * @param $remark 备注
	 */
	public function __construct($out_no, $account, $amount, $payer_name, $name, $remark)
	{
		$this->out_no = $out_no;
		$this->account = $account;
		$this->amount = $amount;
		$this->payer_name = $payer_name;
		$this->name = $name;
		$this->remark = $remark;
	}

	/**
	 * [appTrans 执行单笔转账[无密]]

	 */
	public function appTrans()
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
		$request = new AlipayFundTransToaccountTransferRequest ();
		$json = array(
			"out_biz_no" => $this->out_no, // 订单号
			"payee_type" => "ALIPAY_LOGONID", // ALIPAY_LOGONID : 支付宝登录号，支持邮箱和手机号格式。
			"payee_account" => $this->account, // 支付宝账号 eg : abc@163.com
			"amount" => $this->amount, // 提现金额
			//"amount" => 0.1, // 提现金额
			"payer_show_name" => $this->payer_name, //付款方显示姓名
			"payee_real_name" => $this->name, // 收款方显示姓名
			"remark" => $this->remark // 备注
		);
		$request->setBizContent(json_encode($json)); // 格式化内容

		$result = $aop->execute($request); // 执行
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if( !empty($resultCode) && $resultCode == 10000 ){
			return $result->$responseNode;
		} else {
			return false;
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