<?php
require_once ("IpsPay.Config.php");
require_once ("lib/IpsPayNotify.class.php");
?>
<!DOCTYPE HTML>
<html>
<head>
<link href="source/showLoading.css" rel="stylesheet" />
<link href="source/style-05.css" rel="stylesheet" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<?php
$ipspayNotify = new IpsPayNotify($ipspay_config);
$verify_result = $ipspayNotify->verifyReturn();

        /***
         商户在处理数据时一定要按照文档中’交易返回接口验证事项‘进行判断处理
         1：先判断签名是否正确
         2：判断支付状态
         3：判断订单交易时间，订单号，金额，订单状态，
    	**/
if ($verify_result) { // 验证成功
    
    $paymentResult = $_REQUEST['paymentResult'];
    $xmlResult = new SimpleXMLElement($paymentResult);
    $status = $xmlResult->WxPayRsp->body->Status;
    if($status == "Y")
    {
        $merBillNo = $xmlResult->WxPayRsp->body->MerBillno;
        $MerCode = $xmlResult->WxPayRsp->body->MerCode;
        $Account = $xmlResult->WxPayRsp->body->Account;
        $IpsBillNo = $xmlResult->WxPayRsp->body->IpsBillNo;
        $ordAmt = $xmlResult->WxPayRsp->body->OrdAmt;
        $message = "支付成功";
    }elseif($status == "N")
    {
        $message = "交易失败";
    }else {
        $message = "交易处理中";
    }
   
} else {
    $message = "支付失败";
}

?>
 
<title>IPS订单支付接口返回</title>
</head>
<body>
	<form id="form1">
		<div class="roll-out-container">
			<ul>
				<li><span class="set-title">交易结果:</span> 
				<span class="set-label"> <label id="Message"><?php echo $message?></label>
				</span></li>
			</ul>
		</div>
		<div class="roll-out-container">
			<ul>
				<li><span class="set-title">商户号:</span> <span
					class="set-label"> <label id="MerCode"><?php echo $MerCode?></label>
				</span></li>
			</ul>
		</div>
		<div class="roll-out-container">
			<ul>
				<li><span class="set-title">商户账户号:</span> <span class="set-label">
				 <label id="Account"><?php echo $Account?></label>
				</span></li>
			</ul>
		</div>
		<div class="roll-out-container">
			<ul>
				<li><span class="set-title">商户订单号:</span> <span class="set-label"> 
				<label id="MerBillNo"><?php echo $merBillNo?></label>
				</span></li>
			</ul>
		</div>
        <div class="roll-out-container">
			<ul>
				<li><span class="set-title">IPS订单号:</span> <span class="set-label"> 
				<label id="IpsBillNo"><?php echo $IpsBillNo?></label>
				</span></li>
			</ul>
		</div>
		<div class="roll-out-container">
			<ul>
				<li><span class="set-title">金额</span> <span class="set-label"> 
				<label id="OrdAmt"><?php echo $ordAmt?></label>
				</span></li>
			</ul>
		</div>
	</form>
</body>
</html>