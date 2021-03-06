<?php
require_once ("IpsPay.Config.php");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>商户模拟测试(微信商城线上支付)</title>
<link href="source/showLoading.css" rel="stylesheet" />
<link href="source/style-05.css" rel="stylesheet" />
 <script src="source/jquery-1.7.1.js"></script>  
</head>
<body>
    <form id="callAjaxForm">  
        <div class="haeder-tittle">
            <span>商户模拟测试(微信商城线上支付)
            </span>
        </div>
        <div class="roll-out-container">
            <ul>
                <li>
                    <span class="set-title">商户号</span>
                    <span>
                        <input type="text"  id="MerCode" name ="MerCode" class="set-input" value="<?php echo $ipspay_config['MerCode'];?>"/>
                     </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商户名称</span>
                    <span>
                         <input type="text"  id="MerName" name ="MerName" class="set-input" value ="测试商户"/>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商户账户号</span>
                    <span>
                        <input type="text" id="Account" name ="Account" class="set-input" value="<?php echo $ipspay_config['Account'];?>"/> 
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商户订单号</span>
                    <span>
                        <input type="text"  id="MerBillno" name ="MerBillno" class="set-input"/>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">订单金额金额</span>
                    <span>
                        <input type="text"  id="OrdAmt" name ="OrdAmt" class="set-input" value="0.02"/>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">订单时间</span>
                    <span>
                        <input type="text"  id="OrdTime" name ="OrdTime" class="set-input"/>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商品名称</span>
                    <span>
                        <input type="text"  id="GoodsName" name ="GoodsName" class="set-input" value="测试商品"/>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商品数量</span>
                    <span>
                        <input type="text"  id="GoodsCount" name ="GoodsCount" class="set-input" value="1"/>
                     </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">支付币种</span>
                    <span>
                         <select id="CurrencyType" name="CurrencyType">
							<option value="156">人民币</option>
						 </select>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商户返回地址</span>
                    <span>
                        <input type="text"  id="MerchantUrl" name ="MerchantUrl" class="set-input" value="<?php echo $ipspay_config['return_url'];?>"/>
                    </span>
                </li>
            </ul>
            <ul>
                <li>
                    <span class="set-title">商户S2S返回地址</span>
                    <span>
                        <input type="text"  id="ServerUrl" name ="ServerUrl" class="set-input" value="<?php echo $ipspay_config['S2Snotify_url'];?>"/>
                    </span>
                </li>
            </ul>
<!--            <ul>-->
<!--                <li>-->
<!--                    <span class="set-title">超时时间</span>-->
<!--                    <span>-->
<!--                        <input type="text"  id="BillExp" name ="BillExp" class="set-input"/>-->
<!--                     </span>-->
<!--                </li>-->
<!--            </ul>-->
<!--            <ul>-->
<!--                <li>-->
<!--                    <span class="set-title">收货人地址</span>-->
<!--                    <span>-->
<!--                        <input type="text"  id="ReachAddress" name ="ReachAddress" class="set-input" value="天钥桥路1178号"/>-->
<!--                    </span>-->
<!--                </li>-->
<!--            </ul>-->
<!--            <ul>-->
<!--                <li>-->
<!--                    <span class="set-title">收货人姓名</span>-->
<!--                    <span>-->
<!--                        <input type="text"  id="ReachBy" name ="ReachBy" class="set-input" value="阿拉伯神灯"/>-->
<!--                    </span>-->
<!--                </li>-->
<!--            </ul>-->
<!--            <ul>-->
<!--                <li>-->
<!--                    <span class="set-title">买家留言</span>-->
<!--                    <span>-->
<!--                        <input type="text"  id="Attach" name ="Attach" class="set-input" value="this is merchant attach"/>-->
<!--                    </span>-->
<!--                </li>-->
<!--            </ul>-->
            <ul>
                <li>
                    <span class="set-title">订单签名方式</span>
                    <span>
                         <select id="RetEncodeType" name="RetEncodeType">
							<option value="17">MD5</option>
						 </select>
                    </span>
                </li>
            </ul>
        </div>
        <div id="disableBtn" class="bill-btn-container">
            <button id="submit" type="submit" class="bill-sub-btn">支付</button>  
        </div>
    </form>
</body>
<script type="text/javascript">
function onSuccess(data, status)  
{  
	document.getElementsByTagName("body")[0].innerHTML = data;
	document.forms['ipspaysubmit'].submit()
}  

function onError(data, status)  
{  
    // handle an error 
    alert(data); 
}   
$(document).ready(function() {  
    $("#submit").click(function(){  
        var formData = $("#callAjaxForm").serialize();  
        $.ajax({  
            type: "POST",  
            url: "IpsPayApi.php",  
            cache: false,  
            data: formData,  
            success: onSuccess,  
            error: onError 
        });  
        return false;  
    });  
});  

var inDate = document.getElementById("OrdTime");
var billExp = document.getElementById("BillExp");
var out_MerTrade_no = document.getElementById("MerBillno");
//将指定的小时数加到此实例的值上
Date.prototype.addHours = function (value) {
    var hour = this.getHours();
    this.setHours(hour + value);
    return this;
}; 

//设定时间格式化函数
Date.prototype.format = function (format) {
    var args = {
        "M+": this.getMonth() + 1,
        "d+": this.getDate(),
        "h+": this.getHours(),
        "m+": this.getMinutes(),
        "s+": this.getSeconds(),
    };
    if (/(y+)/.test(format))
        format = format.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
    for (var i in args) {
        var n = args[i];
        if (new RegExp("(" + i + ")").test(format))
            format = format.replace(RegExp.$1, RegExp.$1.length == 1 ? n : ("00" + n).substr(("" + n).length));
    }
    return format;
};
out_MerTrade_no.value = 'Mer'+ new Date().format("yyyyMMddhhmmss");
inDate.value = new Date().format('yyyy-MM-dd hh:mm:ss');
billExp.value = new Date().addHours(1).format('yyyy-MM-dd hh:mm:ss');
</script>
</html>