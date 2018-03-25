<?php
namespace Api\Controller;
use Think\Controller;
use Think\Huanxun;
use Think\IPSUtils;

/**
 * Class HxUserController
 * @package Api\Controller
 * 环迅APP支付相关
 */

class HxPayController extends ApiBasicController{
    private $_key = 'tFcybKzbyVckNHph159ap9br';
    private $_iv = 'XuDcKdZa';
    private $log_obj = "";
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * app下单
     * 传参方式 post
     * @author mss
     * @time 2017-09-28
     * param shop_id 商家的id
     * param m_id 用户的id
     * param order_price 支付的钱数
     * param c_m_id 优惠券的id
     */
    public function getHxApi(){
        /**记录用户的支付的操作的值*/
        /**开启事务处理*/
        $this->log_obj = M("Log");
        M("Log")->startTrans();
        $shop_id = I("post.shop_id");
        /**找到商家的营业状态*/
        $is_open = M("Shop")->where(['shop_id'=>$shop_id])->getField("is_open");
        if($is_open == 1){
            apiResponse('error',"商家未营业");
        }
        $m_id = I("post.m_id");
        $order_price = I("post.order_price");
//        $shop_name = "商品";
//        $shop_id = 1;
//        $m_id = 1;
//        $order_price = 1;
        if (empty($order_price) || $order_price <= 0) {
            apiResponse("error", "输入的金额无效！");
        } elseif (empty($shop_id)) {
            apiResponse("error", "参数错误！");
        } elseif (empty($m_id)) {
            apiResponse("error", "参数错误！");
        }
        $coupon = '';
        if (!empty($_POST['c_m_id'])) {
            $data_log['c_m_id'] = $_POST['c_m_id'];
            $cou_mem = M('CouponMember')->where(array('c_m_id' => I('post.c_m_id')))->field('coupon_id,status')->find();
            if ($cou_mem['status'] == 1) {
                apiResponse("error", "优惠券已使用！");
            } elseif ($cou_mem['status'] == 2) {
                apiResponse("error", "优惠券已失效！");
            }
            $coupon = M('Coupon')->where(array('coupon_id' => $cou_mem['coupon_id']))->find();
            if ($coupon['shop_id'] && $_POST['shop_id'] != $coupon['shop_id']) {
                apiResponse("error", "优惠券不可在该商家使用！");
            }
            $data_log['coupon_type'] = $coupon['type'];
            switch ($coupon['type']) {
                case 1:
                    //定额
                    $data_log['coupon_money'] = $coupon['min_price'];
                    $order_price = $_POST['order_price'] - $coupon['min_price']<0?0:$_POST['order_price'] - $coupon['min_price'];
                    break;
                case 2;
                    //折扣券
                    $data_log['coupon_money'] = sprintf("%.2f", ((1-($coupon['money']/10))) * $_POST['order_price']);
                    $order_price = $_POST['order_price'] - $data_log['coupon_money']<0?0:$_POST['order_price'] - $data_log['coupon_money'];
                    break;
                case 3:
                    //满减券
                    if ($_POST['order_price'] < $coupon['max_price']) {
                        apiResponse("error", "不符合使用条件！");
                    } else {
                        $data_log['coupon_money'] = $coupon['min_price'];
                        $order_price = $_POST['order_price'] - $coupon['min_price']<0?0:$_POST['order_price'] - $coupon['min_price'];
                    }
                    break;
                case 4:
                    //菜品券
                    break;
            }
        }
        if($order_price < 0.03){
            apiResponse("error",'支付金额不合法！');
        }
        $data_log = array();
        /**判断用户是否使用豆抵扣支付*/
        if(I("post.is_wallet") == 1){
            /**查看用户的金额*/
            $mem_wallet = M("Member")->where(array('m_id'=>$m_id))->limit(1)->getField('wallet');
            /**判断金额和豆的大小，如果豆比订单的金额大，那么直接提示使用豆支付*/
            if($mem_wallet > $order_price){
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
        $data_log['pay_money'] = $order_price;
        $data_log['order_sn'] = date('YmdHis') . mt_rand(1000000000, 9999999999);
        $data_log['shop_id'] = $shop_id;
        $data_log['m_id'] = $m_id;
        if($coupon['shop_id']&&$_POST['shop_id']!=$coupon['shop_id']){
            $data_log['price'] = $order_price;
        }else{
            $data_log['price'] = I('post.order_price');
        }
        $data_log['ctime'] = time();
        $res = $this->log_obj->add($data_log);
        /**********************环迅支付***********************************/
        $order_sn = $data_log['order_sn'];
        $shop_name = I("post.shop_name");
        $test = $this->getHxPayApp($order_sn,$shop_name,$order_price,1);
        if($test['GateWayRsp']['head']['RspCode'] == "000000" && !empty($test['GateWayRsp']['body']['PayInfo']) && $res){
            $this->log_obj->commit();
            $andri = json_decode($test['GateWayRsp']['body']['PayInfo'],true);
            $andri['an_package'] = $andri['package'];
            $andri['order_price'] = $order_price;
            apiResponse("success","下单成功",$andri);
        }elseif ($test['GateWayRsp']['head']['RspCode'] == "999999"){
            apiResponse("error",($test['GateWayRsp']['head']['RspMsg']));
        }else{
            $this->log_obj->rollback();
            apiResponse("error","下单失败,请重试！");
        }
    }


    /**
     * 环迅支付购买商品
     * m_id 用户的id
     * order_sn订单号
     * price 订单的费用
     * goods_name商品的名称
     */
    public function getJsHXApiGoods()
    {
        /**如果i_o_id有值，那么这个就是从订单详情里面支付*/
        M("IntegralOrder")->startTrans();
        if ($_POST['i_o_id']) {
            $IntegralOrder = M("IntegralOrder")->where(array('i_o_id' => $_POST['i_o_id']))->field('order_sn,g_id,mix_id,price,rank_type')->limit(1)->find();
            $data['order_sn'] = $IntegralOrder['order_sn'];
            $data['price'] = $IntegralOrder['price'];
            $w['g_id'] = $IntegralOrder['g_id'];
            $goods = M("Goods")->where($w)->find();
            $integral = 1;
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
            /**添加兑换商品的订单的信息*/
            $data = array();
            $w['g_id'] = $_POST['g_id'];
            $goods = M("Goods")->where($w)->find();
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
            $data['address'] = $_POST['address'];
            if ($goods['freight']) {
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
        /**********************环迅支付***********************************/
        $test = $this->getHxPayApp($data['order_sn'],$goods['name'],$pay_price,0);
//        apiResponse('error',$test);
        if($test['GateWayRsp']['head']['RspCode'] == "000000" && !empty($test['GateWayRsp']['body']['PayInfo']) && $integral){
            M("IntegralOrder")->commit();
            $andri = json_decode($test['GateWayRsp']['body']['PayInfo'],true);
            $andri['an_package'] = $andri['package'];
            $andri['order_price'] = $pay_price;
            apiResponse("success","下单成功",$andri);
        }elseif ($test['GateWayRsp']['head']['RspCode'] == "999999"){
            apiResponse("error",($test['GateWayRsp']['head']['RspMsg']));
        }else{
            M("IntegralOrder")->rollback();
            apiResponse("error","下单失败,请重试！");
        }
    }


    /**
     * app交认证费用
     * 传参方式 post
     * @author crazy
     * @time 2018-01-02
     * param shop_id 商家的id
     * param app_id 用户的id
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
        $data_log['app_id'] = $app_id;
        $data_log['shop_name'] = M("Shop")->where(['shop_id'=>$shop_id])->getField('name');
        $data_log['price'] = $order_price;
        $data_log['order_sn'] = date('YmdHis').mt_rand(100000,999999);
        $data_log['shop_id'] = $shop_id;
        $data_log['ctime'] = time();
        M("ApproveOrder")->add($data_log);
        /**********************环迅支付***********************************/
        $order_sn = $data_log['order_sn'];
        $shop_name = "众享通赢认证服务";
        $test = $this->getHxPayApp($order_sn,$shop_name,$order_price,2);
        if($test['GateWayRsp']['head']['RspCode'] == "000000" && !empty($test['GateWayRsp']['body']['PayInfo'])){
            M()->commit();
            $andri = json_decode($test['GateWayRsp']['body']['PayInfo'],true);
            $andri['an_package'] = $andri['package'];
            $andri['order_price'] = $order_price;
            apiResponse("success","下单成功",$andri);
        }elseif ($test['GateWayRsp']['head']['RspCode'] == "999999"){
            apiResponse("error",($test['GateWayRsp']['head']['RspMsg']));
        }else{
            M()->rollback();
            apiResponse("error","下单失败,请重试！");
        }
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
        $order_price = 0;
        $MerName = "";
        switch($_POST['type']){
            case 0:
                $order_res = M("OrderTotal")->where(['order_sn'=>$order_sn])->field("pay_money,order_id")->find();
//                apiResponse(M("OrderTotal")->getLastSql());
                $order_price = $order_res['pay_money'];
                /**找到子订单*/
                $order_list = M("ProductOrder")->where(['p_o_id'=>['in',$order_res['order_id']]])->getField("shop_id",true);
                //商户名称
                $shop_name = M("Shop")->where(['shop_id'=>['in',$order_list]])->getField('name',true);
                $MerName = implode(";",$shop_name);
                break;
            case 1:
                $order_res = M("ProductOrder")->where(['order_sn'=>$order_sn])->field("o_t_id,real_price,goods_gather,shop_id")->find();
                /**获取订单优惠券的钱数,获取订单抵扣豆的钱数*/
                $cou_money_show = $this->scale($order_res['o_t_id'],$order_res['real_price']);
                $order_price = sprintf("%.2f",$order_res['real_price']-$cou_money_show['cou_scale_price']-$cou_money_show['wallet_scale_price']);
                //商户名称
                $shop_name = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->getField('name',true);
                $MerName = $shop_name;
                break;
        }
        if(empty($order_res)){
            apiResponse("error","订单不存在！");
        }
        $test = $this->getHxPayApp($order_sn,$MerName,$order_price,3,$_POST['type']);
        if($test['GateWayRsp']['head']['RspCode'] == "000000" && !empty($test['GateWayRsp']['body']['PayInfo'])){
            M()->commit();
            $andri = json_decode($test['GateWayRsp']['body']['PayInfo'],true);
            $andri['an_package'] = $andri['package'];
            $andri['order_price'] = $order_price;
            apiResponse("success","下单成功",$andri);
        }elseif ($test['GateWayRsp']['head']['RspCode'] == "999999"){
            apiResponse("error",($test['GateWayRsp']['head']['RspMsg']));
        }else{
            M()->rollback();
            apiResponse("error","下单失败,请重试！");
        }
    }






    /**
     * @param $xml
     * @return mixed
     * ：将xml转为array
     */
    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }




    public function request_post($url = '', $post_data = array()) {
        if (empty($url) || empty($post_data)) {
            return false;
        }

        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        $postUrl = $url;
        $curlPost = $post_data;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);

        return $data;
    }

}