 
<?php
require_once ("IpsPay.Config.php");
require_once ("lib/IpsPaySubmit.class.php");

/**
 * ************************请求参数*************************
 */
//  var_dump($_POST);
// 商户号
$MerCode = $_REQUEST['MerCode'];
//商户名称
$MerName = $_REQUEST['MerName'];
//商户账户号
$Account = $_REQUEST['Account'];
//商户订单号
$MerBillno = $_REQUEST['MerBillno'];
//订单金额金额
$OrdAmt = $_REQUEST['OrdAmt'];
//订单时间
$OrdTime = $_REQUEST['OrdTime'];
//商品名称
$GoodsName = $_REQUEST['GoodsName'];
//商品数量
$GoodsCount = $_REQUEST['GoodsCount'];
//支付币种
$CurrencyType = $_REQUEST['CurrencyType'];
//商户返回地址
$MerchantUrl = $_REQUEST['MerchantUrl'];
//商户S2S返回地址
$ServerUrl = $_REQUEST['ServerUrl'];
//超时时间
$BillExp = $_REQUEST['BillExp'];
////收货人地址
//$ReachAddress = $_REQUEST['ReachAddress'];
////买家留言
//$Attach = $_REQUEST['Attach'];
//订单签名方式
$RetEncodeType = $_REQUEST['RetEncodeType'];
////收货人姓名
//$ReachBy= $_REQUEST['ReachBy'];

/************************************************************/

//构造要请求的参数数组
$parameter = array(
    "MerCode"	=> $MerCode,
    "MerName"	=> $MerName,
    "Account"	=> $Account,
    "MerBillno"	=> $MerBillno,
    "OrdAmt"   => $OrdAmt, 
    "OrdTime"	=> $OrdTime, 
    "ReqDate"	=> date("YmdHis"),
    "GoodsName"	=> $GoodsName,
    "GoodsCount"	=> $GoodsCount,
    "CurrencyType"	=> $CurrencyType,
    "MerchantUrl"	=> $MerchantUrl,
    "ServerUrl"	=> $ServerUrl,
    "BillExp"	=> $BillExp,
//    "ReachAddress"	=> $ReachAddress,
    "RetEncodeType"	=> $RetEncodeType,
//    "ReachBy"	=> $ReachBy,
//    "Attach"	=> $Attach
    
);
 
// //建立请求
$ipspaySubmit = new IpsPaySubmit($ipspay_config);
$html_text = $ipspaySubmit->buildRequestForm($parameter);
echo $html_text;

?>
 