<?php
namespace Newweb\Controller;
use Think\Controller;
/**
 * Class IndexController
 * @package Home\Controller
 * 微信支付页面
 */
class PayController extends WechatBasicController {

    public function _initialize(){
        parent::_initialize();
//        Vendor('WxPay.lib.WxPay#Api');
//        Vendor('WxPay.WxPay#JsApiPay');
        Vendor('HxPay.IpsPay#Config');
        Vendor('HxPay.lib.IpsPaySubmit#class');
    }

   //支付页面
    public function pay(){
        if(!IS_POST){
            $w['shop_id'] = $_GET['shop_id'];
            $res = M("Shop")->where($w)->find();
            $this->assign('res',$res);
            $this->display('Pay/pay');
        }
    }

    /**
     * 环迅支付
     * @param shop_id商家的id
     * @param m_id 用户的id
     * @param order_price订单金额
     * @param c_m_id 优惠券的id
     * @time 2017-10-11
     * @author crazy
     */
    public function getHxApi(){
        /**记录用户的支付的操作的值*/
        $shop_id = $_POST['shop_id'];
        $m_id = $_POST['m_id'];
        $order_price = $_POST['order_price'];
        if(empty($order_price) || $order_price<=0){
            apiResponse("error","输入的金额无效！");
        }elseif (empty($shop_id)){
            apiResponse("error","参数错误！");
        }elseif (empty($m_id)){
            apiResponse("error","参数错误！");
        }
        $coupon = '';
        if (!empty($_POST['c_m_id'])){
            $data_log['c_m_id'] = $_POST['c_m_id'];
            $cou_mem = M('CouponMember')->where(array('c_m_id'=>$_POST['c_m_id']))->field('coupon_id,status')->find();
            if($cou_mem['status']==1){
                apiResponse("error","优惠券已使用！");
            }elseif($cou_mem['status']==2){
                apiResponse("error","优惠券已失效！");
            }
            $coupon = M('Coupon')->where(array('coupon_id'=>$cou_mem['coupon_id']))->find();
            if($coupon['shop_id']&&$_POST['shop_id']!=$coupon['shop_id']){
                apiResponse("error","优惠券不可在该商家使用！");
            }
            $data_log['coupon_type'] = $coupon['type'];
            switch ($coupon['type']){
                case 1:
                    //定额
                    $data_log['coupon_money'] = $coupon['min_price'];
                    $order_price = $_POST['order_price']-$coupon['min_price']<0?0:$_POST['order_price']-$coupon['min_price'];
                    break;
                case 2;
                    //折扣券
                    $data_log['coupon_money'] = sprintf("%.2f",((1-($coupon['money']/10)))*$_POST['order_price']);
                    $order_price = $_POST['order_price']-$data_log['coupon_money']<0?0:$_POST['order_price']-$data_log['coupon_money'];
                    break;
                case 3:
                    //满减券
                    if($_POST['order_price']<$coupon['max_price']){
                        apiResponse("error","不符合使用条件！");
                    }else{
                        $data_log['coupon_money'] = $coupon['min_price'];
                        $order_price = $_POST['order_price']-$coupon['min_price']<0?0:$_POST['order_price']-$coupon['min_price'];
                    }
                    break;
                case 4:
                    //菜品券
                    break;
            }
        }
        $data_log = array();
        /**判断用户是否使用豆抵扣支付*/
        if(I("post.is_wallet") == 1){
            /**查看用户的金额*/
            $mem_wallet = M("Member")->where(array('m_id'=>$m_id))->limit(1)->getField('wallet');
            /**判断金额和豆的大小，如果豆比订单的金额大，那么直接提示使用豆支付*/
            if($mem_wallet >= $order_price){
                apiResponse("error",'请直接使用豆支付！');
            }else{
                $order_price = sprintf("%.2f",floatval($order_price)-floatval($mem_wallet));
                /**添加记录*/
                $data_log['is_wallet'] = 1;
                $data_log['deduct_price'] = $mem_wallet;
                if($order_price < 0.03){
                    $data_log['deduct_price'] = $mem_wallet-(0.03-floatval($order_price));
                    $order_price = 0.03;
                }
            }
        }
        $ipsConfig = new \IpsPayConfig();
        $pay_time = date("Y-m-d H:i:s");
        $data_log['pay_money'] = $order_price;
        $data_log['order_sn'] = date('YmdHis').mt_rand(1000000000,9999999999);
        $data_log['shop_id'] = $shop_id;
        $data_log['m_id'] = $m_id;
        if($coupon['shop_id']&&$_POST['shop_id']!=$coupon['shop_id']){
            $data_log['price'] = $order_price;
        }else{
            $data_log['price'] = $_POST['order_price'];
        }
        $data_log['ctime'] = time();
        /**以下数据为了验签测试*/
        $data_log['mercode'] =$ipsConfig->getValue()['MerCode'];
        $data_log['name'] = $_POST['shop_name'];
        $data_log['mername'] = $_POST['shop_name'];
        $data_log['account'] = $ipsConfig->getValue()['Account'];
        $data_log['ordtime'] = $pay_time;
        M("Log")->add($data_log);
        /**环迅处理*/
        /**
         * ************************请求参数*************************
         */

        //商户名称
        $MerName = $_POST['shop_name'];
        //商户订单号
        $MerBillno = $data_log['order_sn'];
        //订单金额金额
        $OrdAmt = $order_price;
        //商品名称
        $GoodsName = $_POST['shop_name'];
        //商品数量
        $GoodsCount = 1;
        //支付币种
        $CurrencyType = 156;
        //商户返回地址
        $MerchantUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Bill/billlist";
        //商户S2S返回地址
        $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackHx";
        //超时时间
//        $BillExp = I('post.BillExp');
        //订单签名方式
        $RetEncodeType = 17;
        $BillExp = date('Y-m-d H:i:s',strtotime('+1year'));
        /************************************************************/
        //构造要请求的参数数组
        $parameter = array(
            "MerCode"	=> $ipsConfig->getValue()['MerCode'],
            "MerName"	=> $MerName,
            "Account"	=> $ipsConfig->getValue()['Account'],
            "MerBillno"	=> $MerBillno,
            "OrdAmt"   => $OrdAmt,
            "OrdTime"	=> $pay_time,
            "GoodsName"	=> mb_substr($GoodsName,0,39,"utf-8"),
            "GoodsCount"	=> $GoodsCount,
            "CurrencyType"	=> $CurrencyType,
            "MerchantUrl"	=> $MerchantUrl,
            "ServerUrl"	=> $ServerUrl,
            "BillExp"	=> $BillExp,
            "RetEncodeType"	=> $RetEncodeType
        );
        //file_put_contents('qian_md5.txt',$parameter.'-------------'.$pay_time);
//        dump($parameter);exit();
        //建立请求
        $ipspaySubmit = new \IpsPaySubmit($ipsConfig->getValue());
        $html_text = $ipspaySubmit->buildRequestForm($parameter);
//        dump($html_text);exit();
        apiResponse('success','成功',$html_text);
    }


    /**
     * 环迅支付（认证费支付）
     * @time 2018-01-02
     * @param shop_id商家的id
     * @param order_price订单金额
     * app_id 服务器的id
     * @author crazy
     */
    public function getHxApproveOrderApi(){
        /**记录用户的支付的操作的值*/
        $shop_id = $_POST['shop_id'];
        $order_price = $_POST['order_price'];
        $app_id = $_POST['app_id'];
        if(empty($order_price) || $order_price<=0){
            apiResponse("error","输入的金额无效！");
        }elseif (empty($shop_id)){
            apiResponse("error","参数错误！");
        }

        /**环迅处理*/
        /**
         * ************************请求参数*************************
         */
        $ipsConfig = new \IpsPayConfig();
        $pay_time = date("Y-m-d H:i:s");
        $data_log['app_id'] = $app_id;
        $data_log['price'] = $order_price;
        $data_log['shop_name'] = M("Shop")->where(['shop_id'=>$shop_id])->getField('name');
        $data_log['order_sn'] = date('YmdHis').mt_rand(100000,999999);
        $data_log['shop_id'] = $shop_id;
        $data_log['ctime'] = time();
        M("ApproveOrder")->add($data_log);
//        file_put_contents('approve.txt',M("ApproveOrder")->getLastSql());

        //商户名称
        $MerName = "众享通赢认证服务";
        //商户订单号
        $MerBillno = $data_log['order_sn'];
        //订单金额金额
        $OrdAmt = $order_price;
        //商品名称
        $GoodsName = "众享通赢认证服务";
        //商品数量
        $GoodsCount = 1;
        //支付币种
        $CurrencyType = 156;
        //商户返回地址
        $MerchantUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Merchant";
        //商户S2S返回地址
        $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackApproveOrder";
        //订单签名方式
        $RetEncodeType = 17;
        $BillExp = date('Y-m-d H:i:s',strtotime('+1year'));
        /************************************************************/
        //构造要请求的参数数组
        $parameter = array(
            "MerCode"	    => $ipsConfig->getValue()['MerCode'],
            "MerName"	    => $MerName,
            "Account"	    => $ipsConfig->getValue()['Account'],
            "MerBillno"	    => $MerBillno,
            "OrdAmt"        => $OrdAmt,
            "OrdTime"	    => $pay_time,
            "GoodsName"	    => $GoodsName,
            "GoodsCount"	=> $GoodsCount,
            "CurrencyType"	=> $CurrencyType,
            "MerchantUrl"	=> $MerchantUrl,
            "ServerUrl"	    => $ServerUrl,
            "BillExp"	    => $BillExp,
            "RetEncodeType"	=> $RetEncodeType
        );
        //建立请求
        $ipspaySubmit = new \IpsPaySubmit($ipsConfig->getValue());
        $html_text = $ipspaySubmit->buildRequestForm($parameter);
        apiResponse('success','成功',$html_text);
    }


    /**
     * 环迅支付（支付商城订单）新版本里面众享商品的订单
     * @time 2018-01-02
     * @author crazy
     * @param order_sn 订单号
     * type 0是下单就支付   1是从订单列表或者详情进行支付的
     */
    public function getHxShopOrderApi(){
        $order_sn = $_POST['order_sn'];
        //商户S2S返回地址
        $ServerUrl = "";
        $order_price = 0;
        $MerName = "";
        $goods_str = "";
        $GoodsName = "";
        $num = 0;
        switch($_POST['type']){
            case 0:
                $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackShopMixOrder";
                $order_res = M("OrderTotal")->where(['order_sn'=>$order_sn])->field("pay_money,order_id,goods_gather")->find();
                $order_price = $order_res['pay_money'];
                /**找到子订单*/
                $order_list = M("ProductOrder")->where(['p_o_id'=>['in',$order_res['order_id']]])->getField("shop_id",true);
                //商户名称
                $shop_name = M("Shop")->where(['shop_id'=>['in',$order_list]])->getField('name',true);
                $MerName = implode(";",$shop_name);
                /**商品的名称*/
                $goods_list = json_decode($order_res['goods_gather'],true);
                foreach($goods_list as $k=>$v){
                    $goods_str.=$v['title'].";";
                    $num+=$v['num'];
                }
                //商品名称
                $GoodsName = $goods_str;
                break;
            case 1:
                $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackShopOneOrder";
                $order_res = M("ProductOrder")->where(['order_sn'=>$order_sn])->field("o_t_id,real_price,goods_gather,shop_id")->find();
                /**获取订单优惠券的钱数,获取订单抵扣豆的钱数*/
                $cou_money_show = $this->scale($order_res['o_t_id'],$order_res['real_price']);
                $order_price = sprintf("%.2f",$order_res['real_price']-$cou_money_show['cou_scale_price']-$cou_money_show['wallet_scale_price']);
                //商户名称
                $shop_name = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->getField('name',true);
                $MerName = $shop_name;
                /**商品的名称*/
                $goods_list = json_decode($order_res['goods_gather'],true);
                foreach($goods_list as $k=>$v){
                    $goods_str.=M("Product")->where(['p_id'=>$v['p_id']])->getField("title").";";
                    $num+=$v['num'];
                }
                //商品名称
                $GoodsName = $goods_str;
                break;
        }
        if(empty($order_res)){
            apiResponse("error","订单不存在！");
        }
        $ipsConfig = new \IpsPayConfig();
        $pay_time = date("Y-m-d H:i:s");

        //商户订单号
        $MerBillno = $order_sn;
        //订单金额金额
        $OrdAmt = $order_price;
        //商品数量
        $GoodsCount = $num;
        //支付币种
        $CurrencyType = 156;
        //商户返回地址
        $MerchantUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Newweb/Order/myorder";
        //订单签名方式
        $RetEncodeType = 17;
        $BillExp = date('Y-m-d H:i:s',strtotime('+1year'));
        /************************************************************/
        //构造要请求的参数数组
        $parameter = array(
            "MerCode"	    => $ipsConfig->getValue()['MerCode'],
            "MerName"	    => $MerName,
            "Account"	    => $ipsConfig->getValue()['Account'],
            "MerBillno"	    => $MerBillno,
            "OrdAmt"        => $OrdAmt,
            "OrdTime"	    => $pay_time,
            "GoodsName"	    => mb_substr($GoodsName,0,39,"utf-8"),
            "GoodsCount"	=> $GoodsCount,
            "CurrencyType"	=> $CurrencyType,
            "MerchantUrl"	=> $MerchantUrl,
            "ServerUrl"	    => $ServerUrl,
            "BillExp"	    => $BillExp,
            "RetEncodeType"	=> $RetEncodeType
        );
        //建立请求
        $ipspaySubmit = new \IpsPaySubmit($ipsConfig->getValue());
        $html_text = $ipspaySubmit->buildRequestForm($parameter);
        apiResponse('success','成功',$html_text);
    }




    /**
     * 环迅支付购买商品，积分商品支付
     * m_id 用户的id
     * order_sn订单号
     * price 订单的费用
     * goods_name商品的名称
     */
    public function getJsHXApiGoods()
    {
        /**如果i_o_id有值，那么这个就是从订单详情里面支付*/
        if ($_POST['i_o_id']) {
            $IntegralOrder = M("IntegralOrder")->where(array('i_o_id' => $_POST['i_o_id']))->field('order_sn,g_id,mix_id,price,rank_type')->limit(1)->find();
            $data['order_sn'] = $IntegralOrder['order_sn'];
            $data['price'] = $IntegralOrder['price'];
            $w['g_id'] = $IntegralOrder['g_id'];
            $goods = M("Goods")->where($w)->find();
        } else {
            if (empty($_POST['mix_id'])) {
                apiResponse("error", "参数错误");
            }
            if (empty($_POST['g_id'])) {
                apiResponse("error", "参数错误");
            }
            if (empty($_POST['name'])) {
                apiResponse("error", "请输入联系人！");
            }
            if (empty($_POST['tel'])) {
                apiResponse("error", "请输入手机号！");
            }
            if (empty($_POST['address'])) {
                apiResponse("error", "请输入地址！");
            }
            if (!preg_match(C("MOBILE"), $_POST['tel'])) {
                apiResponse("error", "手机号格式错误");
            }
            $data = array();
            /**添加兑换商品的订单的信息*/
            $w['g_id'] = $_POST['g_id'];
            $goods = M("Goods")->where($w)->find();
            /**判断用户是否使用豆抵扣支付*/
            $order_price = floatval($goods['price'])+floatval($goods['freight']);
            $data['total_price'] = $order_price;
            if(I("post.is_wallet") == 1){
                /**查看用户的金额*/
                $mem_wallet = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->getField('wallet');
                /**判断金额和豆的大小，如果豆比订单的金额大，那么直接提示使用豆支付*/
                if($mem_wallet >= $order_price){
                    apiResponse("error",'请直接使用豆支付！');
                }else{
                    $order_price = sprintf("%.2f",floatval($order_price)-floatval($mem_wallet));
                    /**添加记录*/
                    $data['d_price'] = $mem_wallet;
                    if($order_price < 0.03){
                        $data['d_price'] = $mem_wallet-(0.03-floatval($order_price));
                        $order_price = 0.03;
                    }
                    $data['price'] = $order_price;
                }
                $s_wallet['wallet'] = floatval($mem_wallet)-floatval($data['d_price']);
                $mem_res = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($s_wallet);
                if (!$mem_res) {
                    apiResponse("error", "系统错误，请联系客服！");
                }
            }else{
                $data['price'] = $order_price;
            }
            $data['name'] = $_POST['name'];
            $data['tel'] = $_POST['tel'];
            $data['address'] = $_POST['address'];if ($goods['freight']) {
                $data['postage'] = $goods['freight'];
            }
            $data['mix_id'] = $_POST['mix_id'];
            $data['rank_type'] = $_POST['type'];
            $data['pay_type'] = 2;
            $data['g_id'] = $_POST['g_id'];
            $data['ctime'] = time();
            $data['order_sn'] = date('YmdHis', time()) . mt_rand(10000, 99999);
            $integral = M("IntegralOrder")->add($data);
            if (!$integral) {
                apiResponse("error", "系统错误，请联系客服！");
            }
        }
        $pay_price = $data['price'];
        /**环迅处理*/
        /**
         * ************************请求参数*************************
         */

        //商户名称
        $MerName = $goods['name'];
        //商户订单号
        $MerBillno = $data['order_sn'];
        //订单金额金额
        $OrdAmt = $pay_price;
        //商品名称
        $GoodsName = $goods['name'];
        //商品数量
        $GoodsCount = 1;
        //支付币种
        $CurrencyType = 156;
        //商户返回地址
        $MerchantUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Order/orderList";
        //商户S2S返回地址
        $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callPayWechatGoods";
        //超时时间
        //订单签名方式
        $RetEncodeType = 17;
        $BillExp = date('Y-m-d H:i:s',strtotime('+1year'));
        /************************************************************/
        //构造要请求的参数数组
        $ipsConfig = new \IpsPayConfig();
        $pay_time = date("Y-m-d H:i:s");
        $parameter = array(
            "MerCode"	=> $ipsConfig->getValue()['MerCode'],
            "MerName"	=> $MerName,
            "Account"	=> $ipsConfig->getValue()['Account'],
            "MerBillno"	=> $MerBillno,
            "OrdAmt"   => $OrdAmt,
            "OrdTime"	=> $pay_time,
            "GoodsName"	=> mb_substr($GoodsName,0,39,"utf-8"),
            "GoodsCount"	=> $GoodsCount,
            "CurrencyType"	=> $CurrencyType,
            "MerchantUrl"	=> $MerchantUrl,
            "ServerUrl"	=> $ServerUrl,
            "BillExp"	=> $BillExp,
            "RetEncodeType"	=> $RetEncodeType
        );
        //建立请求
        $ipspaySubmit = new \IpsPaySubmit($ipsConfig->getValue());
        $html_text = $ipspaySubmit->buildRequestForm($parameter);
        apiResponse('success','成功',$html_text);
    }


    


}

