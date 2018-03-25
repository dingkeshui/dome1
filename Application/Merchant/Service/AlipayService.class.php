<?php
namespace Home\Service;
use Think\Model;

/**
 * Class AlipayService
 * @package Common\Service
 * 支付宝交易
 */
class AlipayService extends Model{

    /**
     * @param array $config         支付配置信息
     * @param string $subject       订单名称 必填 通过支付页面的表单进行传递
     * @param int $total_fee        付款金额 必填 通过支付页面的表单进行传递
     * @param string $body          订单描述 通过支付页面的表单进行传递
     * @param string $show_url      商品展示地址 通过支付页面的表单进行传递
     * @param string $out_trade_no  订单号
     * 发送请求,支付宝接口入口
     */
    public function alipay($config = array(),$subject = '',$total_fee = 0,$body = '',$show_url = '',$out_trade_no = ''){
        //引入支付宝各接口请求提交类
        vendor('Alipay.class#submit');
        //初始化该类
        $Submit = new \Vendor\Alipay\AlipaySubmit($config);
        //各个请求参数赋值
        $payment_type       =  "1";                           //支付类型不能修改
        $notify_url         =  $config['NOTIFY_URL'];         //服务器异步通知页面路径
        $return_url         =  $config['RETURN_URL'];         //页面跳转同步通知页面路径
        $seller_email       =  $config['SELLER_EMAIL'];       //卖家支付宝帐户必填
        //$out_trade_no       =  date('Ymd').getVc('num',7);    //订单号 通过支付页面的表单进行传递，注意要唯一！
        //$subject            =  '测试';                         //订单名称 必填 通过支付页面的表单进行传递
        //$total_fee          =  0.01;                          //付款金额 必填 通过支付页面的表单进行传递
        //$body               =  '';                            //订单描述 通过支付页面的表单进行传递
        //$show_url           =  '';                            //商品展示地址 通过支付页面的表单进行传递
        $anti_phishing_key  =  '';                            //防钓鱼时间戳 若要使用请调用类文件submit中的query_timestamp函数 需要有DONDocument类支持
        $exter_invoke_ip    =  get_client_ip();               //客户端的IP地址

        //构造要请求的参数数组
        $parameter = array(
            "service"            => "create_direct_pay_by_user",
            "partner"            => trim($config['PARTNER']),
            "payment_type"       => $payment_type,
            "notify_url"         => $notify_url,
            "return_url"         => $return_url,
            "seller_email"       => $seller_email,
            "out_trade_no"       => $out_trade_no,
            "subject"            => $subject,
            "total_fee"          => $total_fee,
            "body"               => $body,
            "show_url"           => $show_url,
            "anti_phishing_key"  => $anti_phishing_key,
            "exter_invoke_ip"    => $exter_invoke_ip,
            "_input_charset"     => trim(strtolower($config['INPUT_CHARSET'])),
        );
        //调用提交方法
        $html_text = $Submit->buildRequestForm($parameter,"post","确认");
        echo $html_text;
    }
}