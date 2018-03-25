<?php
namespace Merchant\Controller;
use Think\Controller;
/**
 * Class IndexController
 * @package Home\Controller
 * 微信支付页面
 */
class PayController extends MerchantBasicController {

    public function _initialize(){
        parent::_initialize();
        Vendor('WxPay.lib.WxPay#Api');
        Vendor('WxPay.WxPay#JsApiPay');
    }

   //支付页面
    public function pay(){
        if(!IS_POST){
            $w['shop_id'] = $_GET['shop_id'];
            $res = M("Shop")->where($w)->find();
            $this->assign('res',$res);
            $this->display('Pay/pay');
        }else{

        }
    }


    public function getJsApi(){
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
        $data_log['order_sn'] = date('YmdHis').mt_rand(1000000000,9999999999);
        $data_log['shop_id'] = $shop_id;
        $data_log['m_id'] = $m_id;
        $data_log['price'] = $order_price;
        $data_log['ctime'] = time();
        M("Log")->add($data_log);

        $callback = $_SERVER['HTTP_HOST'].'/index.php/Api/Pay/callBack';
        $tools = new \JsApiPay();
        /**通过用户的id获取用户的openid*/
        $openId = M("Member")->where(array('m_id'=>$m_id))->getField("openid");
        //②、统一下单
        $input = new \WxPayUnifiedOrder();
        if($_POST['shop_name']){
            $input->SetBody($_POST['shop_name']);
            $input->SetAttach($_POST['shop_name']);
            $input->SetGoods_tag($_POST['shop_name']);
        }else{
            $input->SetBody("众享通赢");
            $input->SetAttach("众享通赢");
            $input->SetGoods_tag("众享通赢");
        }
        $input->SetOut_trade_no($data_log['order_sn']);
        $input->SetTotal_fee($order_price*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));

        $input->SetNotify_url($callback);
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order = \WxPayApi::unifiedOrder($input);
        if($order['return_code'] == "FAIL"){
            apiResponse("error","未知错误，请联系管理员！");
        }
        $jsApiParameters = $tools->GetJsApiParameters($order);
        $jsApiParameters = stripslashes($jsApiParameters);
        header('Content-Type:application/json; charset=utf-8');
        $this->ajaxReturn(json_encode(array('jsApiParameters'=>$jsApiParameters)));
    }


    /**
     * 积分兑换商品的运费的支付
     * m_id 用户的id
     * order_sn订单号
     * freight邮费
     * goods_name商品的名称
     */
    public function getJsApiFreight(){
        /**记录用户的支付的操作的值*/
        $m_id = $_POST['m_id'];
        $order_sn = $_POST['order_sn'];
        $freight = $_POST['freight'];
        if(empty($freight) || $freight<=0){
            apiResponse("error","输入的金额无效！");
        }elseif (empty($order_sn)){
            apiResponse("error","参数错误！");
        }elseif (empty($m_id)){
            apiResponse("error","参数错误！");
        }

        $callback = $_SERVER['HTTP_HOST'].'/index.php/Api/Pay/callPayInterGoods';
        $tools = new \JsApiPay();
        /**通过用户的id获取用户的openid*/
        $openId = M("Member")->where(array('m_id'=>$m_id))->getField("openid");
        //②、统一下单
        $input = new \WxPayUnifiedOrder();
        if($_POST['goods_name']){
            $input->SetBody("众享通赢-".$_POST['goods_name']);
            $input->SetAttach("众享通赢-".$_POST['goods_name']);
            $input->SetGoods_tag("众享通赢-".$_POST['goods_name']);
        }else{
            $input->SetBody("众享通赢");
            $input->SetAttach("众享通赢");
            $input->SetGoods_tag("众享通赢");
        }
        $input->SetOut_trade_no($order_sn);
        $input->SetTotal_fee($freight*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));

        $input->SetNotify_url($callback);
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order = \WxPayApi::unifiedOrder($input);
        if($order['return_code'] == "FAIL"){
            apiResponse("error","未知错误，请联系管理员！");
        }
        $jsApiParameters = $tools->GetJsApiParameters($order);
        $jsApiParameters = stripslashes($jsApiParameters);
        header('Content-Type:application/json; charset=utf-8');
        $this->ajaxReturn(json_encode(array('jsApiParameters'=>$jsApiParameters)));
    }

    /**
     * 微信支付购买商品
     * m_id 用户的id
     * order_sn订单号
     * price 订单的费用
     * goods_name商品的名称
     */
    public function getJsApiGoods(){
        M("IntegralOrder")->startTrans();
        /**如果i_o_id有值，那么这个就是从订单详情里面支付*/
        $IntegralOrder = array();
        if($_POST['i_o_id']){
            $IntegralOrder = M("IntegralOrder")->where(array('i_o_id'=>$_POST['i_o_id']))->field('order_sn,g_id,mix_id,price,rank_type')->limit(1)->find();
            $w['g_id'] = $IntegralOrder['g_id'];
            $goods = M("Goods")->where($w)->find();
            $data['order_sn'] = $IntegralOrder['order_sn'];
            $data['price'] = $IntegralOrder['price'];
        }else{
            if(empty($_POST['mix_id'])){
                apiResponse("error", "参数错误");
            }
            if(empty($_POST['g_id'])){
                apiResponse("error", "参数错误");
            }
            if(empty($_POST['name'])){
                apiResponse("error", "请输入联系人！");
            }
            if(empty($_POST['tel'])){
                apiResponse("error", "请输入手机号！");
            }
            if(empty($_POST['address'])){
                apiResponse("error", "请输入地址！");
            }
            if(!preg_match(C("MOBILE"),$_POST['tel'])){
                apiResponse("error","手机号格式错误");
            }
            /**添加兑换商品的订单的信息*/
            $w['g_id'] = $_POST['g_id'];
            $goods = M("Goods")->where($w)->find();
            $data['name'] = $_POST['name'];
            $data['tel'] = $_POST['tel'];
            $data['address'] = $_POST['address'];
            $data['price'] = floatval($goods['price'])+floatval($goods['freight']);
            if($goods['freight']){
                $data['postage'] = $goods['freight'];
            }
            $data['mix_id'] = $_POST['mix_id'];
            $data['rank_type'] = $_POST['type'];
            $data['pay_type'] = 2;
            $data['g_id'] = $_POST['g_id'];
            if(empty($_POST['id'])){
                $data['ctime'] = time();
                $data['order_sn'] = date('YmdHis',time()).mt_rand(10000,99999);
                $integral = M("IntegralOrder")->add($data);
                $id = $integral;
            }else{
                $data['order_sn'] = M("IntegralOrder")->where(array('i_o_id'=>$_POST['id']))->limit(1)->getField('order_sn');
                $data['utime'] = time();
                $integral = M("IntegralOrder")->where(array('i_o_id'=>$_POST['id']))->limit(1)->save($data);
                $id = $_POST['id'];
            }
            if(!$integral){
                apiResponse("error", "系统错误，请联系客服！");
            }
        }
        /**微信支付相关参数*/
        $callback = $_SERVER['HTTP_HOST'].'/index.php/Api/Pay/callPayWechatGoods';
        $tools = new \JsApiPay();
        /**通过用户或者商家的id获取用户的openid*/
        $openId = '';
        if($IntegralOrder){
            if($IntegralOrder['rank_type'] == 0){
                $openId = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->getField("openid");
            }elseif($IntegralOrder['rank_type'] == 1){
                $openId = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->getField("openid");
            }
        }else{
            if($_POST['type'] == 0){
                $openId = M("Member")->where(array('m_id'=>$_POST['mix_id']))->getField("openid");
            }elseif($_POST['type'] == 1){
                $openId = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->getField("openid");
            }
        }
        dump($openId);
        //②、统一下单
        $input = new \WxPayUnifiedOrder();
        if($goods['name']){
            $input->SetBody("众享通赢-".$goods['name']);
            $input->SetAttach("众享通赢-".$goods['name']);
            $input->SetGoods_tag("众享通赢-".$goods['name']);
        }else{
            $input->SetBody("众享通赢");
            $input->SetAttach("众享通赢");
            $input->SetGoods_tag("众享通赢");
        }
        $input->SetOut_trade_no($data['order_sn']);
        $input->SetTotal_fee($data['price']*100);
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 600));

        $input->SetNotify_url($callback);
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid($openId);
        $order = \WxPayApi::unifiedOrder($input);
//        dump($order);
//        exit();
        if($order['return_code'] == "FAIL"){
            apiResponse("error","未知错误，请联系管理员！");
            M("IntegralOrder")->rollback();
        }else{
            M("IntegralOrder")->commit();
        }
        $jsApiParameters = $tools->GetJsApiParameters($order);
        if(!empty($id)){
            $arr = json_decode($jsApiParameters,true);
            $arr['id'] = $id;
            $jsApiParameters = json_encode($arr);
        }
        $jsApiParameters = stripslashes($jsApiParameters);
        header('Content-Type:application/json; charset=utf-8');
        $this->ajaxReturn(json_encode(array('jsApiParameters'=>$jsApiParameters)));
    }


    


}

