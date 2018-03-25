<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class ProductOrderController
 * @package Api\Controller
 * 商品订单
 */
class ProductOrderController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();

    }

    /**
     * 用户订单列表
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param m_id 用户id
     * @param p 页数
     * @param status 订单状态，1待付款，2待发货，3待收货，4待评价
     * 0:待支付  1：已经支付未发货  2：已经发货 3：已收货待评价 4：订单完成 5：申请退款 6:退款中  7：驳回  8：退款成功 ,9：取消订单
     * @param title 模糊搜索的内容，订单号或收货人姓名或收货人电话
     */
    public function orderList(){
        if(empty($_POST['m_id'])){
            apiResponse('error','参数错误');
        }
        $m_id = $_POST['m_id'];
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $status = $_POST['status'];
        $order = 'ctime DESC';
        switch($status){
            case 1:
                $where['status'] = 0;
                $order = 'ctime DESC';
                break;
            case 2:
                $where['status'] = 1;
                $order = 'pay_time DESC';
                break;
            case 3:
                $where['status'] = 2;
                $order = 'send_time DESC';
                break;
            case 4:
                $where['status'] = 3;
                $order = 'affirm_time DESC';
                break;
        }
        if(!empty($_POST['title'])){
            $where['order_sn|addr_name|addr_tel'] = array('LIKE','%'.$_POST['title'].'%');
        }
        $where['m_id'] = $m_id;
        $where['is_del'] = 0;
        $order = M('ProductOrder')->where($where)->field('p_o_id as order_id,o_t_id,order_sn,real_price,
        shop_id,goods_gather,total_price,postage_price,ctime,status,delivery_number,is_account')
            ->order($order)->limit($page,10)->select();
        $data = [];
        foreach($order as $key=>$val){
            $data[$key]['order_id'] = $val['order_id'];
            $data[$key]['shop_id'] = $val['shop_id'];
            $data[$key]['shop_name'] = M('Shop')->where(array('shop_id'=>$val['shop_id']))->limit(1)->getField('name');
            $product = json_decode($val['goods_gather'],true);
            $pro = array();
            $num = 0;
            foreach($product as $keys=>$vals){
                $pro[$keys] = M('Product')->where(array('p_id'=>$vals['p_id']))->field('p_id,title,cover_pic')->find();
                $pro[$keys]['cover_pic'] = $this->returnPic($pro[$keys]['cover_pic']);
                $pro[$keys]['attr'] = $vals['attr'];
                $pro[$keys]['num'] = $vals['num'];
                $pro[$keys]['price'] = $vals['price'];
                $num+=$vals['num'];
            }
            $price_real = $this->scale($val['o_t_id'],$val['real_price']);
            $return_price_sum = M("ReturnOrder")->where(['order_id'=>$val['order_id'],'status'=>['in','0,1,3']])->sum('price');
            $price_real_com = floatval($val['real_price'])-floatval($price_real['cou_scale_price']);
            if(sprintf("%.2f",$price_real_com) == sprintf("%.2f",$return_price_sum) && $return_price_sum>0){
                $data[$key]['is_show_button'] = 0;
            }else{
                $data[$key]['is_show_button'] = 1;
            }
            /**判断是否使用定额优惠券，如果使用就提示取消订单，相关联订单也将取消*/
            $data[$key]['is_cou'] = $this->getCouType($val['o_t_id']);
            $data[$key]['product'] = $pro;
            $data[$key]['postage'] = $val['postage_price'];
            $data[$key]['total'] = $val['total_price'];
            $data[$key]['status'] = $val['status'];
            $data[$key]['delivery_number'] = $val['delivery_number'];
            $data[$key]['goods_total'] = $num;
            $data[$key]['order_sn'] = $val['order_sn'];
            $data[$key]['ctime'] = date('Y-m-d H:i:s',$val['ctime']);
            $data[$key]['is_account'] = $val['is_account'];
            $data[$key]['cou_money_show'] = $this->scale($val['o_t_id'],$val['real_price']);
            $real_price = sprintf("%.2f",$val['real_price']-$data[$key]['cou_money_show']['cou_scale_price']-$data[$key]['cou_money_show']['wallet_scale_price']);
            $data[$key]['real_price'] = $real_price==-0.00?0.00:$real_price;
            /**获取商家的信息*/
            $data[$key]['shop_name'] = M("Shop")->where(['shop_id'=>$val['shop_id']])->getField('name');
        }
        $return_res = $this->getListCodeMessage($data,$p);
        apiResponse($return_res['code'],$return_res['message'],$return_res['list']);
    }

    /**
     * 确认订单，从购物车进入
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param m_id 用户id
     * @param cart_id 购物车id（[1,2,3]）
     */
    public function firmOrder(){
        if(empty($_POST['m_id'])||empty($_POST['cart_id'])){
            apiResponse('error','参数错误');
        }
        $m_id = $_POST['m_id'];
        $list = array();
        if($_POST['addr_id']){
            //获取用户的地址信息
            $list['address'] = $this->address(0,$_POST['addr_id']);
        }else{
            //获取用户的地址信息
            $list['address'] = $this->address($m_id,0);
        }
        //查看用户的可用众享豆
        $list['wallet'] = M('Member')->where(array('m_id'=>$m_id))->getField('wallet');
        $list['total'] = 0;
        $cart_ids = json_decode($_POST['cart_id'],true);
        $str_cart = implode(',',$cart_ids);
        $w['cart_id'] = array('IN',$cart_ids);
        $shop_id = M('Cart')->where($w)->distinct(true)->order('ctime DESC')->getField('shop_id',true);
        foreach($shop_id as $k=>$v){
            $num = 0;
            $postage = 0;
            $list['detail'][$k]['shop_id'] = $v;
            $list['detail'][$k]['shop_name'] = M('Shop')->where(array('shop_id'=>$v))->limit(1)->getField('name');
            $sql = "SELECT c.cart_id,c.p_id,c.num,c.price,c.attr_val,p.title,p.postage,p.cover_pic FROM zxty_cart AS c ,zxty_product AS p WHERE
            p.p_id=c.p_id AND c.cart_id in ($str_cart) AND c.shop_id=".$v." ORDER BY c.ctime DESC";
            $pro = M()->query($sql);
            $pro_total = 0;
            foreach($pro as $key=>$val){
                $pro_total += $val['num']*$val['price'];
                $pro[$key]['cover_pic'] = $this->returnPic($val['cover_pic']);
                $pro[$key]['attr_val_id'] = $val['attr_val'];
                $pro[$key]['p_id'] = $val['p_id'];
                $arr_val = explode('|',$val['attr_val']);
                $arr_val_return = "";
                foreach($arr_val as $kk=>$vv){
                    $arr_val_return .= M("AttrValue")->where(['val_id'=>$vv])->getField("attr_value").";";
                }
                $pro[$key]['attr_val'] = substr($arr_val_return,0,-1);
                $num+=$val['num'];
                $postage +=  $val['postage']*$val['num'];
            }
            //$list['detail'][$k]['postage'] = $this->postage($v,$pro_total);
            $list['detail'][$k]['total'] = $pro_total+$postage;
            $list['detail'][$k]['product'] = $pro;
            $list['detail'][$k]['product_num'] = $num;
            $list['total']+=$list['detail'][$k]['total'];
        }
        apiResponse('success','成功',$list);
    }

    /**
     * 判断用户的运费价格
     * @author mss
     * @time 2017-11-17
     * param shop_id 商家id
     * param price 商品的总价格
     */
    private function postage($shop_id,$price){
        $postage = 0;
        //查找商家的运费价格
        $postage = M('Shop')->where(array('shop_id'=>$shop_id))->getField('postage');
        //查看商家免运费的下限
        $postage_info = M('Postage')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->field('id,full_price')->find();
        if(!$postage_info){
            return $postage;
        }
        if($price>=$postage_info['full_price']){
            return 0;
        }else{
            return $postage;
        }
    }

    /**
     * 获取用户收货地址信息，先找用户默认地址，没有默认地址返回最新添加的地址
     * @author mss
     * @time 2017-11-17
     * param m_id 用户id
     */
    private function address($m_id,$addr_id){
        if($addr_id){
            $address = M('Address')->where(array('addr_id'=>$addr_id,'status'=>array('NEQ',9)))->field('addr_id,name,phone,province,city,area,address')->order('is_default DESC,ctime DESC')->find();
            if($address){
                $address['province'] = M('Areas')->where(array('area_id'=>$address['province']))->getField('area_name');
                $address['city'] = M('Areas')->where(array('area_id'=>$address['city']))->getField('area_name');
                $address['area'] = M('Areas')->where(array('area_id'=>$address['area']))->getField('area_name');
            }else{
                $address = [];
            }
        }else{
            $address = M('Address')->where(array('mix_id'=>$m_id,'status'=>array('NEQ',9)))->field('addr_id,name,phone,province,city,area,address')->order('is_default DESC,ctime DESC')->find();
            if($address){
                $address['province'] = M('Areas')->where(array('area_id'=>$address['province']))->getField('area_name');
                $address['city'] = M('Areas')->where(array('area_id'=>$address['city']))->getField('area_name');
                $address['area'] = M('Areas')->where(array('area_id'=>$address['area']))->getField('area_name');
            }else{
                $address = [];
            }
        }

        return $address;
    }

    /**
     * 用户提交订单
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param m_id 用户id
     * @param product 商品信息 比如$product=array(0=>array('p_id'=>1,'attr'=>'商品属性','num'=>3,'price'=>'商品价格',));
     * @param cou_id 优惠券id
     * @param addr_id 收货地址id
     * @param is_wallet 是否用豆抵扣，1是，0否
     * detail  	[ { "shop_id": 1, "cou_id": 0, "postage": 1, "remark": "test", "product": [ { "cart_id": 2, "num": 1, "price": 6, "p_id": 17, "attr": "红|XL" }, { "cart_id": 3, "num": 1, "price": 1, "p_id": 19, "attr": "红|L" } ] } ]
     */
    public function addOrder(){
        /**先往商品订单里添加数据，再往总订单表里添加数据，数据添加成功删除购物车*/
        M()->startTrans();
        $where_cart_new['cart_id'] = array('in',json_decode($_POST['cart_id'],true));
        $cart_list = M("Cart")->where($where_cart_new)->field("num,p_id,price,shop_id,attr_val")->select();
        $new_info = [];
        foreach($cart_list as $k=>$v){
            $product_title = M("Product")->where(['p_id'=>$v['p_id']])->getField("title");
            $new_info[$k]['p_id'] = $v['p_id'];
            $new_info[$k]['title'] = $product_title;
            $new_info[$k]['price'] = $v['price'];
            $new_info[$k]['num'] = $v['num'];
            $new_info[$k]['attr'] = $v['attr_val'];
            $new_info[$k]['shop_id'] = $v['shop_id'];
        }
        $address_obj = M('Address');
        $areas_obj = M('Areas');
        $is_wallet = $_POST['is_wallet'];  //用户是否选择豆抵扣
        $m_id = $_POST['m_id'];
        //添加大订单表
        $all_order_sn = date('YmdHis').mt_rand(100000,999999);
        $all_data['order_sn'] = $all_order_sn;
        $all_data['goods_gather'] = json_encode($new_info);
        $all_data['m_id'] = $m_id;
        $all_data['ctime'] = time();
        $all_res = M('OrderTotal')->add($all_data);
        unset($all_data);
        //查看用户的钱包余额
        $wallet = M('Member')->where(array('m_id'=>$m_id))->getField('wallet');
        $addr_id = $_POST['addr_id'];
        //查询用户地址信息
        $addr = $address_obj->where(array('addr_id'=>$addr_id))->find();
        $province = $areas_obj->where(array('area_id'=>$addr['province']))->limit(1)->getField('area_name');
        $city = $areas_obj->where(array('area_id'=>$addr['city']))->limit(1)->getField('area_name');
        $area = $areas_obj->where(array('area_id'=>$addr['area']))->limit(1)->getField('area_name');
        $detail = $_POST['detail'];
        $detail_array = json_decode($detail,true);
        $cou_id = $_POST['cou_id']?$_POST['cou_id']:0;
        $all_total = 0;
        $all_real = 0;
        $res = [];
        $cart_id = [];
        $total = 0;
        $pro = [];
        $num = 0;
        $postage = 0;
        foreach($detail_array as $k=>$v){
            $data = array();
            $data['o_t_id'] = $all_res;
            $data['m_id'] = $m_id;
            $data['addr_id'] = $addr_id;
            $data['addr_name'] = $addr['name'];
            $data['addr_tel'] = $addr['phone'];
            $data['addr_address'] = $province.$city.$area.$addr['address'];
            $data['order_sn'] = date('YmdHis').mt_rand(100000,999999);
            $data['shop_id'] = $v['shop_id'];
            foreach($v['product'] as $key=>$val){
                $total += $val['price']*$val['num'];
                $cart_id[] = $val['cart_id'];
                $pro[$key]['p_id'] = $val['p_id'];
                $pro[$key]['price'] = $val['price'];
                $pro[$key]['num'] = $val['num'];
                $num += $val['num'];
                $pro[$key]['attr'] = $val['attr']?$val['attr']:0;
                $pro[$key]['attr_group'] = $val['attr_val_id']?$val['attr_val_id']:0;
                //增加销量
                M('Product')->where(array('p_id'=>$val['p_id']))->setInc('sales',$val['num']);
                //减少库存
                if(!empty($val['attr'])){
                    M('ProductPrice')->where(array('p_id'=>$val['p_id'],'attr_group'=>$val['attr_val_id'],'status'=>0))->setDec('stock',$val['num']);
                    /**减少库存为了匹配售罄*/
                    M('Product')->where(array('p_id'=>$val['p_id']))->setDec('stock',$val['num']);
                }else{
                    M('Product')->where(array('p_id'=>$val['p_id']))->setDec('stock',$val['num']);
                }
                $postage += M("Product")->where(['p_id'=>$val['p_id']])->getField("postage")*$val['num'];
            }
            $data['goods_num'] = $num;
            $data['goods_gather'] = json_encode($pro);
            $data['total_price'] = $total+$postage;
            if(!empty($v['cou_id'])){
                $data['cou_id'] = $v['cou_id'];
                $data['cou_money'] = 0;
                $data['real_price'] = $data['total_price']-$data['cou_money']<0?0:$data['total_price']-$data['cou_money'];
                $this->changeCoupon($m_id,$v['cou_id']);
            }else{
                $data['real_price'] = $data['total_price'];
            }
            if($is_wallet){
                $data['bean_deduction'] = 0;
            }
            $data['postage_price'] = $postage;
            $all_total += $data['total_price'];
            $all_real += $data['real_price'];
            $data['remark'] = $v['remark']?$v['remark']:'';
            $data['ctime'] = time();
            $res[] = M('ProductOrder')->add($data);
            $total = 0;
            $postage = 0;
            $num = 0;
            $pro = [];
        }
        $all_data['order_id'] = implode(',',$res);
        $all_data['addr_name'] = $addr['name'];
        $all_data['addr_tel'] = $addr['phone'];
        $all_data['total_money'] = $all_total;
        $all_data['real_total_money'] = $all_real;
        if($cou_id){
            //如果选择了平台优惠券
            $all_data['cou_id'] = $cou_id;
            $cou_money = $this->couponMoney($cou_id,$all_real);
            $all_data['cou_money'] = $cou_money>$all_total?$all_total:$cou_money;
            $paymoney = $all_real-$cou_money<0?0:$all_real-$cou_money;
            $this->changeCoupon($m_id,$cou_id);
        }else{
            $paymoney = $all_real;
        }

        //当用户选择豆抵扣时，判断用户的豆是否够支付整个大订单，如果够直接支付成功，如果不够则用豆抵扣后微信支付
        $min_wxpay = 0.03;
        if($is_wallet){
            if($wallet>=$paymoney){
                $all_data['wallet'] = $paymoney;
                $all_data['pay_money'] = 0;
                $all_data['pay_type'] = 0;
            }else{
                //判断用豆抵扣后微信支付的钱数是否小于环迅支付的最小金额
                if(($paymoney-$wallet)<$min_wxpay){
                    $all_data['wallet'] = $paymoney-$min_wxpay<0?0:$paymoney-$min_wxpay;
                    $all_data['pay_money'] = $min_wxpay;
                }else{
                    $all_data['wallet'] = $wallet;
                    $all_data['pay_money'] = $paymoney-$wallet<0?0:$paymoney-$wallet;
                }
                $all_data['pay_type'] = 1;
            }
            /**减去用户的豆*/
            if($all_data['wallet']>0){
                M("Member")->where(['m_id'=>$m_id])->limit(1)->save(['wallet'=>$wallet-$all_data['wallet']]);
            }
        }else{
            $all_data['wallet'] = 0;
            $all_data['pay_money'] = $paymoney;
            $all_data['pay_type'] = 1;
        }
        $all_res_cart = M('OrderTotal')->where(array('id'=>$all_res))->limit(1)->save($all_data);;
        if($res&&$all_res&&$all_res_cart){
            //下单成功，删除购物车信息
            M('Cart')->where(array('cart_id'=>array('IN',$cart_id)))->delete();
            M()->commit();
            //下单成功
            $res_data['order_sn'] = $all_order_sn;
            apiResponse('success','订单提交成功',$res_data);
        }else{
            M()->rollback();
            apiResponse('error','订单提交失败');
        }

    }


    /**
     * 计算优惠券可优惠金额
     * @author mss
     * @time 2017-11-20
     * param cou_id 优惠券id
     * param price 总价格
     */
    private function couponMoney($cou_id,$price=0){
        $cou = M('CouponMember')->where(array('c_m_id'=>$cou_id))->find();
        if(!$cou||$cou['status']==1||$cou['status']==2){
            //优惠券已使用或已过期
            return 0;
        }
        $coupon = M('Coupon')->where(array('coupon_id'=>$cou['coupon_id']))->find();
        $money = 0;
        switch($coupon['type']){
            case 1:
                //定额代金券
                $money = $coupon['min_price'];
                break;
            case 2:
                //折扣代金券
                $money = sprintf("%.2f",floatval($price) - floatval(sprintf("%.2f",(($coupon['money']/10))*$price)));
                break;
            case 3:
                //满减代金券
                if($price>=$coupon['max_price']){
                    $money = $coupon['min_price'];
                }
                break;
        }
        return $money;
    }



    /**
     * 确认收货
     * 传参方式 post
     * @author mss
     * @time 2017-11-19
     * @param order_id 订单的id
     */
    public function makeSure(){
        M()->startTrans();
        $pro_order = M("ProductOrder");
        $id = $_POST['order_id'];
        $w['p_o_id'] = $id;
        $data['status'] = 3;
        $data['affirm_time'] = time();
        $res = $pro_order->where($w)->limit(1)->save($data);
        $is_status = $pro_order->where(['status'=>2,'p_o_id'=>array('neq',$id)])->getField("p_o_id");
        if(empty($is_status)){
            $total_res = M('OrderTotal')->where(array('id'=>$pro_order->where(['p_o_id'=>$id])->getField('o_t_id')))->limit(1)->save(['affirm_time'=>time(),'status'=>3]);
        }else{
            $total_res = 1;
        }
        /**找到订单*/
        $order_res = $pro_order->where(['p_o_id'=>$id])->find();
        /**给商家添加消息*/
        $mess_res = $this->addMessage($order_res['shop_id'],"用户确认收货！",
            "用户已经确认了订单号为：{$order_res['order_sn']}的订单",'1',
            $order_res['real_price'],$order_res['m_id'],2);
        if($res && $total_res&&$mess_res){
            M()->commit();
            try{
                /**给APP商家端推送消息*/
                $alias = ''.$order_res['shop_id'];
                $alert = "用户已经确认了订单号为：{$order_res['order_sn']}的订单";
                $extra['type'] = '1';
                $this->push("",$alias,$alert,$alert,$extra,1);
            }catch (\Exception $e){
                apiResponse("success","确认收货成功");
            }
            apiResponse("success","确认收货成功！");
        }else{
            M()->rollback();
            apiResponse("error","确认收货失败！");
        }
    }

    /**
     * 订单的详情
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param order_id 订单的id
     */
    public function orderDetail(){
        if(empty($_POST['order_id'])){
            apiResponse('error','参数错误');
        }
        $data = array();
        $w['p_o_id'] = $_POST['order_id'];
        $order = M('ProductOrder')->where($w)->find();
        /**获取大的订单的信息*/
//        $total_order_res = M("OrderTotal")->where(['id'=>$order['o_t_id']])->find();
        /**找到付款的订单号*/
        //$data['pay_order_sn'] = M("OrderTotal")->where(['id'=>$order['o_t_id']])->getField('order_sn');
        $product = json_decode($order['goods_gather'],true);
        $pro =  array();
        foreach($product as $k=>$v){
            $pro[$k] = M('Product')->where(array('p_id'=>$v['p_id']))->field('p_id,title,cover_pic')->find();
            $pro[$k]['cover_pic'] = $this->returnPic($pro[$k]['cover_pic']);
            $pro[$k]['attr'] = $v['attr'];
            $pro[$k]['price'] = $v['price'];
            $pro[$k]['num'] = $v['num'];
            $pro[$k]['attr_group'] = $v['attr_group'];
            /**商品是否在退货中*/
            $is_set = M("ReturnOrder")->where(['order_id'=>$_POST['order_id'],'goods_id'=>$v['p_id'],'attr_val'=>$v['attr']])
                ->order("ctime desc")->field('id,status,real_price')->find();
//            apiResponse(M("ReturnOrder")->getLastSql());
            if($is_set){
                $pro[$k]['return_id'] = $is_set['id'];
                $pro[$k]['status'] = $is_set['status'];
            }else{
                $pro[$k]['return_id'] = "";
                $pro[$k]['status'] = "";
            }
        }
        $price_real = $this->scale($order['o_t_id'],$order['real_price']);
        $return_price_sum = M("ReturnOrder")->where(['order_id'=>$_POST['order_id'],'status'=>['in','0,1,3']])->sum('price');
        $price_real_com = floatval($order['real_price'])-floatval($price_real['cou_scale_price']);
        if(sprintf("%.2f",$price_real_com) == sprintf("%.2f",$return_price_sum) && $return_price_sum>0){
            $data['is_show_button'] = 0;
        }else{
            $data['is_show_button'] = 1;
        }
        /**判断是否使用定额优惠券，如果使用就提示取消订单，相关联订单也将取消*/
        $data['is_cou'] = $this->getCouType($order['o_t_id']);
        $data['postage_price'] = $order['postage_price'];
        $data['total_price'] = $order['total_price'];
        $data['order_id'] = $order['p_o_id'];
        $data['order_sn'] = $order['order_sn'];
        $data['total_price'] = $order['total_price'];
        $data['goods_total_price'] = $order['total_price']-$order['postage_price'];
        $data['addr_name'] = $order['addr_name'];
        $data['addr_tel'] = $order['addr_tel'];
        $data['addr_address'] = $order['addr_address'];
        $data['status'] = $order['status'];
        $data['shop_id'] = $order['shop_id'];
        $data['remark'] = empty($order['remark'])?"":$order['remark'];
        $data['cancel_reason'] = empty($order['cancel_reason'])?"暂无":$order['cancel_reason'];
        $data['delivery_code'] = $order['delivery_code'];
        $data['company_name'] = $order['company_name'];
        $data['delivery_number'] = $order['delivery_number'];
        $data['is_account'] = $order['is_account'];
        /**获取订单优惠券的钱数,获取订单抵扣豆的钱数*/
        $data['cou_money_show'] = $this->scale($order['o_t_id'],$order['real_price']);
        $real_price = sprintf("%.2f",$order['real_price']-$data['cou_money_show']['cou_scale_price']-$data['cou_money_show']['wallet_scale_price']);
        $data['real_price'] = $real_price==-0.00?0.00:$real_price;
        /**获取商家的名称*/
        $data['shop_name'] = M("Shop")->where(['shop_id'=>$order['shop_id']])->getField('name');
        $data['ctime'] = date('Y-m-d H:i:s',$order['ctime']);
        /**获取商家的环信的账号*/
        $shop_res = M("Shop")->where(['shop_id'=>$order['shop_id']])->field('shop_id,hx_name')->find();
        $data['hx_name'] = $shop_res['hx_name'];
        /**还有多久不能退换货*/
        if($order['affirm_time']>0){
            $data['affirm_time'] = date('Y-m-d H:i:s',$order['affirm_time']);
            $data['refund_time'] = 7-(timeDiff(time(),($order['affirm_time']+604800))['day']);
        }else{
            $data['affirm_time'] = '';
            $data['refund_time'] = 0;
        }
        if($order['send_time']>0){
            $data['send_time'] = date('Y-m-d H:i:s',$order['send_time']);
            /**计算还有多久要自动的确认收货*/
            $date_sure_time = timeDiff(time(),($order['send_time']+604800));
            if(($order['send_time']+604800)<time()){
                $data['make_sure_time'] = "";
            }else{
                $data['make_sure_time'] = $date_sure_time['day'].'天'.$date_sure_time['hour'].'小时'.$date_sure_time['min'].'分';
            }
        }else{
            $data['send_time'] = '';
            $data['make_sure_time'] = "";
        }
        if($order['pay_time']>0){
            $data['pay_time'] = date('Y-m-d H:i:s',$order['pay_time']);
        }else{
            $data['pay_time'] = '';
        }
        if($order['cancel_time']>0){
            $data['cancel_time'] = date('Y-m-d H:i:s',$order['cancel_time']);
        }else{
            $data['cancel_time'] = '';
        }
        $data['product'] = $pro;
        apiResponse('success','成功',$data);
    }


    /**
     * 退货的操作
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param order_id 订单的id
     */
    public function returnOrder(){
        if(empty($_POST['order_id'])){
            apiResponse('error','参数错误');
        }
        $w['p_o_id'] = $_POST['order_id'];
        $data['status'] = 5;
        $data['utime'] = time();
        $res = M("ProductOrder")->where($w)->limit(1)->save($data);
        if($res){
//            $this->sendMsg("13042231878","用户申请退货，请前去查看！");
            apiResponse("success","申请成功！");
        }else{
            apiResponse("error","申请失败！");
        }
    }

    /**
     * 评价订单，显示商品信息
     * 传参方式 post
     * @author mss
     * @time 2017-11-19
     * @param order_id 订单id
     */
    public function appraise(){
        $order_id = $_POST['order_id'];
        $order = M('ProductOrder')->where(array('p_o_id'=>$order_id))->find();
        $pro = array();
        $product = json_decode($order['goods_gather'],true);
        foreach($product as $k=>$v){
            $pro[$k] = M('Product')->where(array('p_id'=>$v['p_id']))->field('p_id,title,cover_pic')->find();
        }
        $data['order_id'] = $order_id;
        $data['product'] = $pro;
        apiResponse('success','成功',$data);
    }

    /**
     * 提交评价
     * 传参方式 post
     * @author mss
     * @time 2017-11-19
     * @param m_id 用户id
     * @param order_id 订单id
     * @param detail 评价的集合
     */
    public function addAppraise(){
        M()->startTrans();
        $pro_order = M('ProductOrder');
        $m_id = $_POST['m_id'];
        $order_id = $_POST['order_id'];
        $detail = json_decode($_POST['detail'],true);
        foreach($detail as $k=>$v){
            $data = array();
            $data['p_o_id'] = $order_id;
            $data['m_id'] = $m_id;
            $data['shop_id'] = $pro_order->where(['p_o_id'=>$order_id])->getField("shop_id");
            $data['p_id'] = $v['p_id'];
            $data['star'] = $v['star'];
            $data['content'] = $v['content']?filterHtml($v['content']):"";
            if(!empty($v['picGather'])){
                $arr = $v['picGather'];
                $pic_arr = array();
                foreach ($arr as $key=>$val){
                    $pic       = $val['pic'];
                    $pic_name      = $val['pic_name'];
                    $temp = explode('.',$pic_name);
                    $ext = uniqid().'.'.end($temp);
                    $base64    = substr(strstr($pic, ","), 1);
                    $image_res = base64_decode($base64);
                    $pic_link  = "Uploads/Appraise/".date('Y-m-d').'/'.uniqid().".{$ext}";
                    $saveRoot = "Uploads/Appraise/".date('Y-m-d').'/';
                    /**检查目录是否存在  循环创建目录*/
                    if(!is_dir($saveRoot)){
                        mkdir($saveRoot, 0777, true);
                    }
                    $res = file_put_contents($pic_link ,$image_res);
                    if($res){
                        $pic_arr[] = $pic_link;
                    }else{
                        apiResponse("error","图片上传失败！");
                    }
                }
                $string = implode(",",$pic_arr);
                $data['pics'] = $string;
            }
            $data['status'] = 1;
            $data['ctime'] = time();
            M('ProductAppraise')->add($data);
        }
        $res = $pro_order->where(array('p_o_id'=>$order_id))->limit(1)->save(array('status'=>4,'utime'=>time()));
        $is_status = $pro_order->where(['status'=>3,'p_o_id'=>array('neq',$order_id)])->getField("o_t_id");

        if(empty($is_status)){
            $total_res = M('OrderTotal')->where(array('id'=>$pro_order->where(['id'=>$order_id])->getField('o_t_id')))->limit(1)->save(['status'=>4,'utime'=>time()]);
        }else{
            $total_res = 1;
        }
        if($res&&$total_res){
            M()->commit();
            apiResponse('success','评价成功');
        }else{
            M()->rollback();
            apiResponse('error','评价失败');
        }
    }


    /**
     * 删除订单
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param order_id 订单的id
     */
    public function delOrder(){
        if(empty($_POST['order_id'])){
            apiResponse('error','参数错误');
        }
        $w['p_o_id'] = $_POST['order_id'];
        $data['is_del'] = 1;
        $data['utime'] = time();
        $res = M("ProductOrder")->where($w)->limit(1)->save($data);
        if($res){
            apiResponse("success","删除成功！");
        }else{
            apiResponse("error","删除失败！");
        }
    }

    /**
     * 取消订单
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param order_id 订单id
     */
    public function cancelOrder(){
        M()->startTrans();
        if(empty($_POST['order_id'])){
            apiResponse('error','参数错误');
        }
        $pro_order = M('ProductOrder');
        $order = M('ProductOrder')->where(['p_o_id'=>$_POST['order_id']])->field('o_t_id,goods_gather,real_price,m_id')->find();
        /**当有用户在两个或者多个商家购买商品的时候并且使用了定额优惠券，然后取消了相关联的订单*/
        $o_t_id = M("OrderTotal")->where(['id'=>$order['o_t_id']])->field('cou_id,order_id')->find();

        /**通过用户领取的优惠券找到用户的记录*/
        $coupon_id = M("CouponMember")->where(['c_m_id'=>$o_t_id['cou_id']])->getField("coupon_id");
        /**找到优惠券的使用条件金额*/
        $cou_res = M("Coupon")->where(['coupon_id'=>$coupon_id])->field("type")->find();
        /**只有当定额的时候才能去执行*/
        if($cou_res['type'] == 3){
            $all_order = M('ProductOrder')->where(array('p_o_id'=>['in',$o_t_id['order_id']]))->field('o_t_id,goods_gather,real_price,m_id')->select();
            $total_price = 0;
            foreach($all_order as $kk=>$vv){
                //增加库存,如果用户使用豆支付，将返回用户豆
                $order_attr = json_decode($vv['goods_gather'],true);
                if($order_attr){
                    foreach($order_attr as $k=>$v){
                        M('ProductPrice')->where(['p_id'=>$v['p_id'],'attr_group'=>$v['attr_group']])->setInc('stock',$v['num']);
                        M('Product')->where(['p_id'=>$v['p_id']])->setInc('stock',$v['num']);

                    }
                }
                /**取消订单并返还豆*/
                $cou_money_show = $this->scale($vv['o_t_id'],$vv['real_price']);
                if($cou_money_show['wallet_scale_price']>0){
                    $total_price+=$cou_money_show['wallet_scale_price'];
                }
            }
            /**给用户返还豆*/
            $wallet = M("Member")->where(['m_id'=>$order['m_id']])->getField("wallet");
            $wallet_member = M("Member")->where(['m_id'=>$order['m_id']])->limit(1)->save(['wallet'=>floatval($wallet)+floatval($total_price)]);
            /**取消相关联的订单*/
            $w['p_o_id'] = ['in',$o_t_id['order_id']];
            $data['status'] = 9;
            $data['cancel_reason'] = $_POST['cancel_reason'];
            $data['cancel_time'] = time();
            $res = M('ProductOrder')->where($w)->save($data);
            /**取消大的订单*/
            $total_res = M('OrderTotal')->where(array('id'=>$order['o_t_id']))->limit(1)->save(['status'=>9]);
//            apiResponse(M('ProductOrder')->getLastSql(),$total_price);
        }else{
            $w['p_o_id'] = $_POST['order_id'];
            $data['status'] = 9;
            $data['cancel_reason'] = $_POST['cancel_reason'];
            $data['cancel_time'] = time();
            $res = M('ProductOrder')->where($w)->limit(1)->save($data);
            //增加库存
            $order_attr = json_decode($order['goods_gather'],true);
            if($order_attr){
                foreach($order_attr as $k=>$v){
                    M('ProductPrice')->where(['p_id'=>$v['p_id'],'attr_group'=>$v['attr_group']])->setInc('stock',$v['num']);
                    M('Product')->where(['p_id'=>$v['p_id']])->setInc('stock',$v['num']);
                }
            }
            $is_status = $pro_order->where(['status'=>array('neq',9),'p_o_id'=>array('neq',$_POST['order_id'])])->getField("o_t_id");
            if(empty($is_status)){
                $total_res = M('OrderTotal')->where(array('id'=>$order['o_t_id']))->limit(1)->save(['status'=>9]);
            }else{
                $total_res = 1;
            }
            /**取消订单并返还豆*/
            $cou_money_show = $this->scale($order['o_t_id'],$order['real_price']);
            if($cou_money_show['wallet_scale_price']>0){
                $wallet = M("Member")->where(['m_id'=>$order['m_id']])->getField("wallet");
                $wallet_member = M("Member")->where(['m_id'=>$order['m_id']])->limit(1)->save(['wallet'=>$wallet+$cou_money_show['wallet_scale_price']]);
            }else{
                $wallet_member = 1;
            }
        }
//        dump(M('OrderTotal')->getLastSql());
//        dump($res."--".$total_res."--".$wallet_member."--".$total_big_order_price);
        if($res&&$total_res&&$wallet_member){
            M()->commit();
            apiResponse('success','操作成功');
        }else{
            M()->rollback();
            apiResponse('error','操作失败');
        }
    }

    /**
     * 查看用户在对应商家可使用的优惠券
     * @param m_id 用户id
     * @param shop_id 商家id
     */
    public function checkCoupon($m_id,$shop_id){
        $where['shop_id'] = $shop_id;
        $where['status'] = array('NEQ',9);
        $where['start_time'] = array('elt',date('Y-m-d'));
        $where['end_time'] = array('egt',date('Y-m-d'));
        $where['type'] = array('lt',4);
        $coupon = M('Coupon')->where($where)->getField('coupon_id',true);
        if(!$coupon){
            return false;
        }
        $cou_mem = M('CouponMember')->where(array('status'=>0,'coupon_id'=>array('IN',$coupon)))->order('ctime DESC')->select();
        if(!$cou_mem){
            return false;
        }
        $list = array();
        foreach($cou_mem as $k=>$v){
            $cou = M('Coupon')->where(array('coupon_id'=>$v['coupon_id']))->field('title,desc,type,money,min_price,max_price')->find();
            $list[$k]['title'] = $cou['title'];
            $list[$k]['desc'] = $cou['desc'];
            $list[$k]['type'] = $cou['type'];
            if($cou['type']==1){
                //定额代金券
                $list[$k]['money'] = $cou['min_price'];
            }elseif($cou['type']==2){
                //折扣券
                $list[$k]['discount'] = $cou['money'];
            }elseif($cou['type']==3){
                //满减券
                $list[$k]['money'] = $cou['min_price'];
                $list[$k]['full_money'] = $cou['max_price'];
            }
            $list[$k]['cou_id'] = $v['c_m_id'];
        }
        return $list;
    }

    /**
     * 修改用户优惠券的状态，添加使用记录
     * @author mss
     * @time 2017-11-28
     * param m_id 用户id
     * param c_m_id 优惠券id
     */
    public function changeCoupon($m_id,$c_m_id){
        M()->startTrans();
        $res = M('CouponMember')->where(array('c_m_id'=>$c_m_id))->limit(1)->save(array('status'=>1,'utime'=>time()));
        $data['m_id'] = $m_id;
        $data['c_m_id'] = $c_m_id;
        $data['ctime'] = time();
        $log_res = M('CouponLog')->add($data);
        if($res&&$log_res){
            M()->commit();
            return true;
        }else{
            M()->rollback();
            return false;
        }
    }

    /**
     * 上传评价图片
     */
    public function uploadPic($arr){
        $pic_arr = array();
        $string = '';
        foreach ($arr as $k=>$v){
            $pic       = $v['pic'];
            $pic_name      = $v['pic_name'];
            $temp = explode('.',$pic_name);
            $ext = uniqid().'.'.end($temp);
            $base64    = substr(strstr($pic, ","), 1);
            $image_res = base64_decode($base64);
            $pic_link  = "/Uploads/Appraise/".date('Y-m-d').'/'.uniqid().'.jpg';
            $saveRoot = "Uploads/Appraise/".date('Y-m-d').'/';
            /**检查目录是否存在  循环创建目录*/
            if(!is_dir($saveRoot)){
                mkdir($saveRoot, 0777, true);
            }
            $res = file_put_contents($pic_link ,$image_res);
            if($res){
                $pic_arr[] = $pic_link;
            }else{
                apiResponse("error","图片上传失败！");
            }
        }
        $string = implode(",",$pic_arr);
        return $string;
    }


    /**通知商家发货
     * @author crazy
     * @time 2017-12-31
     * @time order_id 订单的id
     */
    public function informPush(){
        M()->startTrans();
        $order_res = M("ProductOrder")->where(['p_o_id'=>$_POST['order_id']])->field("m_id,shop_id,is_push,order_sn,real_price,pay_type")->find();
        if($order_res['is_push'] == 1){
            apiResponse("error",'您已经通知了！');
            exit;
        }
        /**找到商家*/
        $shop_res = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->field("is_set,shop_id")->find();
        /**修改订单的通知状态*/
        $is_success_order = M("ProductOrder")->where(['p_o_id'=>$_POST['order_id']])->limit(1)->save(['is_push'=>1]);
        /**添加商家的消息*/
        $is_success_mess =$this->addMessage($order_res['shop_id'],"用户提醒发货通知","用户提醒您订单号：{$order_res['order_sn']}的单子记得发货！",
            1,$order_res['real_price'],$order_res['m_id'],2,$order_res['pay_type']);
        if($shop_res['is_set'] == 1){
            /**给APP商家端推送消息*/
            $alias = ''.$order_res['shop_id'];
            $title = '用户提醒发货通知';
            $alert = "用户提醒您订单号：{$order_res['order_sn']}的单子记得发货！";
            $extra['order_id'] = ''.$_POST['order_id'];
            $extra['mess_id'] = $is_success_mess;
            $extra['type'] = '1';
            try{
                if($shop_res['is_set'] == 1){
                    $this->push("",$alias,$title,$alert,$extra,1);
                }
            }catch (\Exception $e){
                if($is_success_order&&$is_success_mess){
                    M()->commit();
                    apiResponse("success",'通知成功');
                }else{
                    M()->rollback();
                    apiResponse("error",'通知失败');
                }
            }
        }
        if($is_success_order&&$is_success_mess){
            M()->commit();
            apiResponse("success",'通知成功');
        }else{
            M()->rollback();
            apiResponse("error",'通知失败');
        }
    }


    /**通知用户付款
     * @author crazy
     * @time 2017-12-31
     * @time order_id 订单的id
     */
    public function informPushMember(){
        M()->startTrans();
        $order_res = M("ProductOrder")->where(['p_o_id'=>$_POST['order_id']])->field("real_price,shop_id,m_id,is_push_mem,order_sn,pay_type")->find();
        if($order_res['is_push_mem'] == 1){
            apiResponse("error",'您已经通知了！');
            exit;
        }
        /**找到用户*/
        $mem_res = M("Member")->where(['m_id'=>$order_res['m_id']])->field("is_set,m_id")->find();
        /**修改订单的通知状态*/
        $is_success_order = M("ProductOrder")->where(['p_o_id'=>$_POST['order_id']])->limit(1)->save(['is_push_mem'=>1]);
        /**添加用户的消息*/
        $is_success_mess = $this->addMessage($order_res['m_id'],"商家提醒付款通知","商家提醒您订单号：{$order_res['order_sn']}的单子付款咯！",
            0,$order_res['real_price'],$order_res['shop_id'],2,$order_res['pay_type']);
        if($mem_res['is_set'] == 1){
            /**给APP商家端推送消息*/
            $alias = ''.$order_res['m_id'];
            $title = '商家提醒付款通知';
            $alert = "商家提醒您订单号：{$order_res['order_sn']}的单子付款咯！";
            $extra['order_id'] = ''.$_POST['order_id'];
            $extra['mess_id'] = $is_success_mess;
            $extra['type'] = '0';
            try {
                if ($mem_res['is_set'] == 1) {
                    $this->push("", $alias, $title, $alert, $extra, 1);
                }
            }catch (\Exception $e){
                if($is_success_order&&$is_success_mess){
                    M()->commit();
                    apiResponse("success",'通知成功');
                }else{
                    M()->rollback();
                    apiResponse("error",'通知失败');
                }
            }
        }
        if($is_success_order&&$is_success_mess){
            M()->commit();
            apiResponse("success",'通知成功');
        }else{
            M()->rollback();
            apiResponse("error",'通知失败');
        }
    }

}