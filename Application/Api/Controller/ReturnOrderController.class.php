<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 退款订单的接口
 */
class ReturnOrderController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();

    }


    /**退货的原因
     * @author crazy
     * @time 2017-12-25
     */
    public function returnReason(){
        $list = M("ReturnReason")->where(['status'=>0])->field("id,title")->order("sort asc")->select();
        apiResponse("success",'获取成功',$list);
    }


    /**退款订单展示商品的信息
     * @author crazy
     * @time 2017-12-25
     * @param order_id 订单的id
     * @param p_id 商品的ID
     */
    public function returnGoodsMess(){
        /**获取商品的属性*防止用户使用了优惠券然后退款的信息。1首先要看一下订单的实际支付金额，2、是否有其他的退款信息*/
        $return = [];
        $order_id = $_POST['order_id'];
        $return_id = $_POST['return_id']?$_POST['return_id']:0;
        /**当用户修改退换货的时候*/
        if($return_id){
            $pic = [];
            $js_price = $this->returnPrice($order_id,$return_id);
            $return = M("ReturnOrder")->where(['id'=>$return_id])->field('id as return_id,goods_id,m_id,shop_id,order_id,
            attr_val,name,return_sn,return_status,ctime,price as return_price,reason,status,pic,remark')->find();
            $pic_arr = [];
            if($return['pic']){
                $pic_arr = explode(",",$return['pic']);
            }
            $return['pic'] = $pic_arr;
            /**计算还有多久要自动的确认退换货*/
            $date_sure_time = timeDiff(time(),($return['ctime']+3*24*64*64));
            $return['ctime'] = date("Y-m-d H:i:s",$return['ctime']);
            $return['residue_time'] = $date_sure_time['day'].'天'.$date_sure_time['hour'].'小时'.$date_sure_time['min'].'分';;
        }else{
            $js_price = $this->returnPrice($order_id);
        }
        $order_res = M("ProductOrder")->where(['p_o_id'=>$order_id])->field("goods_gather,real_price,o_t_id")->find();
        $goods_gather_arr = json_decode($order_res['goods_gather'],true);
        $val_arr = $this->findId($goods_gather_arr,"{$_POST['p_id']}",$_POST['attr_group']?$_POST['attr_group']:0);
        if($val_arr != false){
            $return['attr_val'] = $val_arr['attr'];
            $return['num'] = $val_arr['num'];
            $return_price = $val_arr['price']*$val_arr['num'];
            /**商品的运费*/
            $postage = M("Product")->where(['p_id'=>$_POST['p_id']])->getField("postage");
            $return_price_total = floatval($return_price)+floatval($postage*$val_arr['num']);
            $return['price'] = $return_price_total>$js_price?$js_price:$return_price_total;
        }else{
            apiResponse("error",'查不到订单');
        }
        $goods_res = M("Product")->where(['p_id'=>$_POST['p_id']])->field("p_id,title,cover_pic")->find();
        $return['p_id'] = $goods_res['p_id'];
        $return['title'] = $goods_res['title'];
        $return['cover_pic'] = $this->returnPic($goods_res['cover_pic']);
        apiResponse("success",'获取成功',$return);
    }


    /**获取退款金额
     * @author crazy
     * @time 2018-02-05
     */
    public function returnPrice($order_id,$return_id=0){
        $order_res = M("ProductOrder")->where(['p_o_id'=>$order_id])->field("goods_gather,real_price,o_t_id")->find();
        /**退款中的商品的金额的总和*/
        if($return_id){
            $return_price_sum = M("ReturnOrder")->where(array('status'=>['in','0,1,3'],'order_id'=>$order_id,'id'=>['neq',$return_id]))->sum('price');
        }else{
            $return_price_sum = M("ReturnOrder")->where(array('status'=>['in','0,1,3'],'order_id'=>$order_id))->sum('price');
        }
        /**计算一下金额*/
        /**通过大的订单计算一下优惠券*/
        $data = $this->scale($order_res['o_t_id'],$order_res['real_price']);
        $small_price = sprintf("%.2f",$order_res['real_price']-$data['cou_scale_price']);
        $js_price = (floatval($small_price)-floatval($return_price_sum))>0?(floatval($small_price)-floatval($return_price_sum)):0;
        return $js_price;
    }

    /**获取单个商品的价格（退款商品）和退款的价格比较
     * @author crazy
     * @time 2018-02-04
     */
    public function bigPrice($goods_gather,$goods_id,$attr_val,$js_price){
        $goods_gather_arr = json_decode($goods_gather,true);
        $val_arr = $this->findId($goods_gather_arr,"{$goods_id}",$attr_val?$attr_val:0);
        $return_price = $val_arr['price']*$val_arr['num'];
        /**商品的运费*/
        $postage = M("Product")->where(['p_id'=>$goods_id])->getField("postage");
        $return_price_total = floatval($return_price)+floatval($postage*$val_arr['num']);
        $price = $return_price_total>$js_price?$js_price:$return_price_total;
        return $price;
    }

    /**单纯的获取商品的金额
     * @author crazy
     * @time 2018-02-06
     */
    public function pureGoodsPrice($goods_gather,$goods_id,$attr_val){
        $goods_gather_arr = json_decode($goods_gather,true);
        $val_arr = $this->findId($goods_gather_arr,"{$goods_id}",$attr_val?$attr_val:0);
        $return_price = $val_arr['price']*$val_arr['num'];
        /**商品的运费*/
        $postage = M("Product")->where(['p_id'=>$goods_id])->getField("postage");
        $return_price_total = floatval($return_price)+floatval($postage*$val_arr['num']);
        return $return_price_total;
    }

    /**查找退款订单的商品
     * @author crazy
     * @time 2017-12-28
     */
    public function findId(&$a,$id,$attr_group){
        $arr = [];
        foreach($a as $k=>$t){
            if(in_array($id,$t)){
                $arr[] = $t;
            }
        }
        foreach($arr as $kk=>$tt){
            if(in_array($attr_group,$tt)){
                return $tt;
            }
        }
        return false;
    }


    /**通过大的订单找到用户付款的钱数，配合退款商品功能实现
     * @author crazy
     * @time 2018-02-04
     */
    public function bigOrderPrice($id,$order_id){
        $res = M("OrderTotal")->where(['id'=>$id])->field('pay_money,wallet,cou_money')->find();
        /**计算和这个大的订单一起下单的小的订单的钱数*/
        $other_price_sum = M("ProductOrder")->where(['o_t_id'=>$id,'p_o_id'=>['neq',$order_id]])->field('real_price')->select();
        /**当使用优惠券的时候，涉及到订单退款金额不是real_price字段，而是按照比例分配的值*/
        $small_price = 0;
        foreach($other_price_sum as $k=>$v){
            /**通过大的订单计算一下优惠券*/
            $data = $this->scale($id,$v['real_price']);
            $small_price+= $v['real_price']-$data['cou_scale_price'];
        }
        return sprintf("%.2f",floatval($res['pay_money'])+floatval($res['wallet'])-floatval($small_price));
    }


    /**使用优惠券全款退要把商家的待结算金额
     * @author crazy
     * @time 2018-02-06
     */
    public function makeSurePrice($order_id,$price){
        $order_res = M("ProductOrder")->where(['p_o_id'=>$_POST['order_id']])->field("o_t_id,real_price")->find();

        $return_price = M("ReturnOrder")->where(['status'=>['in','0,1,3'],'order_id'=>$order_id])->sum('price');
        $total_price = floatval($return_price)+floatval($price);

        $data = $this->scale($order_res['o_t_id'],$order_res['real_price']);
        $small_price = sprintf("%.2f",$order_res['real_price']-$data['cou_scale_price']);
//        file_put_contents('return_price.txt',$small_price.'-'.$total_price);
        if(sprintf("%.2f",$small_price)==sprintf("%.2f",$total_price)){
            return true;
        }else{
            return false;
        }
    }

    /**退款的方法
     * @author crazy
     * @time 2017-12-28
     */
    public function returnMethod(){
        M()->startTrans();
        if($_POST['price']<=0){
            apiResponse("error",'金额不合法');
        }
        /**判断是否存在*/
        $is_set_w['shop_id'] = $_POST['shop_id'];
        $is_set_w['m_id'] = $_POST['m_id'];
        $is_set_w['goods_id'] = $_POST['goods_id'];
        $is_set_w['attr_val'] = $_POST['attr_val'];
        $is_set_w['order_id']=$_POST['order_id'];
        $is_set = M("ReturnOrder")->where($is_set_w)->getField('id');
        if($is_set){
            apiResponse("error",'您已申请过，不能重复提交');
        }
        $order_id = $_POST['order_id'];
        $order_res = M("ProductOrder")->where(['p_o_id'=>$_POST['order_id']])->field("o_t_id,shop_id,goods_gather,is_push,order_sn,pay_type,real_price")->find();
        $js_price = $this->returnPrice($order_id);
        if($_POST['price']>sprintf("%.2f",$js_price)){
            apiResponse("error",'金额不合法');
        }
        /**商品的价格*/
        $goods_price_postage = $this->pureGoodsPrice($order_res['goods_gather'],$_POST['goods_id'],$_POST['attr_val'],$js_price);
        /**可以退的价格*/
        $can_back_price = $this->bigPrice($order_res['goods_gather'],$_POST['goods_id'],$_POST['attr_val'],$js_price);

        /**找到商家*/
        $shop_res = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->field("is_set,shop_id,name")->find();
        $pic_arr = $_POST['pic'];
        $string = implode(",",$pic_arr);
//        file_put_contents("1.txt",json_decode($pic_arr));
        $return_data = [
            'return_sn'=>date("YmdHis").mt_rand(10000,99999),
            'm_id'=>$_POST['m_id'],
            'shop_id'=>$_POST['shop_id'],
            'name'=>$shop_res['name'],
            'attr_val'=>$_POST['attr_val'],
            'order_id'=>$_POST['order_id'],
            'goods_id'=>$_POST['goods_id'],
            'return_status'=>$_POST['return_status'],
            'reason'=>$_POST['reason']?$_POST['reason']:"未填写",
            'goods_status'=>$_POST['goods_status'],
            'price'=>$_POST['price'],
            'remark'=>$_POST['remark']?$_POST['remark']:"未填写",
            'pic'=>$string?$string:'',
            'ctime'=>time()
        ];
        /**当使用优惠券用户要退款时候扣除的真实金额，如果金额存在就优先减去这个金额
         * 1、当可退金额
         * 2、
         */
        //file_put_contents('can.txt',$can_back_price.'-'.$_POST['price']);
        if($_POST['price']==sprintf("%.2f",$can_back_price)){
            $return_data['real_price'] = $goods_price_postage;
        }
//        file_put_contents("return_price1.txt",json_encode($return_data));
        /**添加商家的消息*/
        $is_success_mess = $this->addMessage($order_res['shop_id'],"用户发起退货退款申请！","订单号：{$order_res['order_sn']}的单子用户发起了退货退款的申请！",
            1,$_POST['price'],$_POST['m_id'],0,$order_res['pay_type']);
        $res = M("ReturnOrder")->add($return_data);
        if($res && $is_success_mess){
            M()->commit();
            try{
                if($shop_res['is_set'] == 1){
                    /**给APP商家端推送消息*/
                    $alias = ''.$order_res['shop_id'];
                    $title = '用户发起退货退款申请';
                    $alert = "订单号：{$order_res['order_sn']}的单子用户发起了退货退款的申请！";
                    $extra['order_id'] = ''.$_POST['order_id'];
                    $extra['mess_id'] = $is_success_mess;
                    $extra['type'] = '1';
                    $this->push("",$alias,$title,$alert,$extra,1);
                }
            }catch (\Exception $e){
                apiResponse("success",'申请成功');
            }
            apiResponse("success",'申请成功');
        }else{
            M()->rollback();
            apiResponse("error",'申请失败');
        }
    }

    /**修改退换货的申请
     * @author crazy
     * @time 2018-01-01
     * @param
     */
    public function editReturnOrder(){
        if($_POST['price']<=0){
            apiResponse("error",'金额不合法');
        }
        if(empty($_POST['return_id'])){
            apiResponse("error",'参数错误');
        }
        $order_id =  M("ReturnOrder")->where(['id'=>$_POST['return_id']])->getField('order_id');
        $order_res = M("ProductOrder")->where(['p_o_id'=>$order_id])->field("shop_id,m_id,is_push,order_sn,real_price")->find();
        $js_price = $this->returnPrice($order_id,$_POST['return_id']);
        if(sprintf("%.2f",$js_price)<$_POST['price']){
            apiResponse("error",'金额不合法');
        }
        $arr = $_POST['pic'];
        $string = implode(",",$arr);
        $return_data = [
            'return_status'=>$_POST['return_status'],
            'reason'=>$_POST['reason'],
            'goods_status'=>$_POST['goods_status'],
            'price'=>$_POST['price'],
            'remark'=>$_POST['remark'],
            'pic'=>$string?$string:"",
            'utime'=>time()
        ];
        /**当使用优惠券用户要退款时候扣除的真实金额，如果金额存在就优先减去这个金额*/
        /**当使用优惠券用户要退款时候扣除的真实金额，如果金额存在就优先减去这个金额
         * 1判断一下这个订单在使用优惠券的情况下，支付的金额
         * 2、计算它一共退的金额的和
         */
//        if($this->makeSurePrice($order_id,$_POST['price'])){
//            $return_data['real_price'] = $order_res['real_price'];
//        }
        /**找到商家*/
        $shop_res = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->field("is_set,name,head_pic")->find();
        $res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->limit(1)->save($return_data);
        if($res){
            if($shop_res['is_set'] == 1){
                try{
                    $title = "用户售后申请修改通知";
                    $content = "用户修改了订单号{$order_res['order_sn']}的售后申请！";
                    /**给APP商家端推送消息*/
                    $alias = ''.$order_res['shop_id'];
                    $extra['order_id'] = ''.$order_id;
                    $extra['type'] = 1;
                    $this->push("",$alias,$title,$content,$extra,1);
                }catch (\Exception $e){
                    apiResponse("success","修改成功");
                }
            }
            apiResponse("success",'修改成功');
        }else{
            apiResponse("error",'修改失败');
        }
    }


    /**售后的列表
     * @author crazy
     * @time 2017-12-31
     * @param mix_id 商家或者用户的id
     * @param type 0是用户 1是商家
     * p  分页
     */
    public function returnOrderList(){
        $w = [];
        switch($_POST['type']){
            case 0:
                $w['m_id'] = $_POST['mix_id'];
                break;
            case 1:
                $w['shop_id'] = $_POST['mix_id'];
                break;
        }
        switch($_POST['status']){
            case 1:
                $w['status'] = 0;
                break;
            case 2:
                $w['status'] = 1;
                break;
            case 3:
                $w['status'] = 2;
                break;
            case 4:
                $w['status'] = 3;
                break;
            default:
                $w['status'] = ['neq',9];
        }
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $list = M("ReturnOrder")->where($w)->order("ctime desc")->limit($page,10)->field('id as return_id,goods_id,m_id,shop_id,order_id,
        attr_val,name,return_sn,return_status,ctime,status')->select();
        //file_put_contents('return_order.txt',M("ReturnOrder")->getLastSql());
        foreach($list as $k=>$v){
            /**获取商品的信息*/
            $goods_res = M("Product")->where(['p_id'=>$v['goods_id']])->field('title,cover_pic')->find();
            $list[$k]['cover_pic'] = $this->returnPic($goods_res['cover_pic']);
            $list[$k]['ctime'] = date("Y-m-d H:i:s",$v['ctime']);
            $list[$k]['title'] = $goods_res['title'];
        }
        $arr = $this->getListCodeMessage($list,$p);
        apiResponse($arr['code'],$arr['message'],$arr['list']);

    }

    /**退款的详情
     * @author crazy
     * @time 2017-12-31
     * @param return_id 退款订单的id
     */
    public function returnOrderDetail(){
        if(empty($_POST['return_id'])){
            apiResponse("error",'参数错误');
        }
        $res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->field('id as return_id,goods_id,m_id,shop_id,order_id,
        attr_val,name,return_sn,return_status,ctime,price,reason,status')->find();
        /**计算还有多久要自动的确认收货*/
        $date_sure_time = timeDiff(time(),($res['ctime']+3*24*64*64));
        /**获取商品的信息*/
        $goods_res = M("Product")->where(['p_id'=>$res['goods_id']])->field('title,cover_pic')->find();
        $res['cover_pic'] = $this->returnPic($goods_res['cover_pic']);
        $res['ctime'] = date("Y-m-d H:i:s",$res['ctime']);
        $res['title'] = $goods_res['title'];
        $res['residue_time'] = $date_sure_time['day'].'天'.$date_sure_time['hour'].'小时'.$date_sure_time['min'].'分';;
        apiResponse('success',"获取成功",$res);
    }

    /**撤销申请(同意，拒绝)
     * @author crazy
     * @time 2018-01-01
     * @param return_id
     */
    public function backOutReturnOrder(){
        M()->startTrans();
        if(empty($_POST['return_id'])){
            apiResponse("error",'参数错误');
        }
        /**添加商家的消息*/
        $order_id =  M("ReturnOrder")->where(['id'=>$_POST['return_id']])->field('order_id,shop_id,price,goods_id,attr_val')->find();
        $order_res = M("ProductOrder")->where(['p_o_id'=>$order_id['order_id']])->field("shop_id,m_id,is_push,order_sn,pay_type,goods_gather,o_t_id,status")->find();
        /**找到商家*/
        $shop_res = M("Shop")->where(['shop_id'=>$order_id['shop_id']])->field("is_set,name,head_pic")->find();
        /**找到用户*/
        $member_res = M("Shop")->where(['m_id'=>$order_res['m_id']])->field("is_set,nick_name,head_pic")->find();
        $title = "";
        $content = "";
        $type = 0;
        $id = 0;
        $is_set = 0;
//        $pro_status = 0;
        switch($_POST['status']){
            case 1:
                $title = "售后申请同意通知";
                $content = "订单号{$order_res['order_sn']}售后申请商家已经同意了！";
                $type = 0;
                $id = $order_res['m_id'];
                $is_set = $member_res['is_set'];
                $return_data['shop_agree_mess'] = json_encode(
                    [
                        "head_pic"=>C("API_URL").'/'.$shop_res['head_pic'],
                        "name"=>$shop_res['name'],
                        "time"=>date("Y-m-d H:i:s",time()),
                        "other_one"=>"卖家已经同意售后申请",
                        "other_two"=>"如收到货物请退货",
                    ]
                );

                /**判断一下，如果这个订单使用了优惠券，并且订单的金额小于单个商品的金额，那么这个订单将变成取消状态
                 * 查找一下这个订单还能退款多少(待定，因为用户可能中途取消退款操作)
                 */
//                $js_price = $this->returnPrice($order_id['order_id'],$_POST['return_id']);
//
//                /**获取退款的总和*/
//                $return_price_sum = M("ReturnOrder")->where(array('status'=>['in','0,1,3'],'order_id'=>$order_id['order_id'],
//                    'id'=>['neq',$_POST['return_id']]))->sum('price');
//
//                /**获取一下单个商品的价格，比较单个商品的价格和总的订单的价格钱数*/
//                $return_price = $this->bigPrice($order_res['goods_gather'],$order_id['goods_id'],$order_id['attr_val'],$js_price);
//
//                /**通过大的订单找到用户付款的钱数，配合退款商品功能实现*/
//                $bigOrderPrice = $this->bigOrderPrice($order_res['o_t_id'],$order_id['order_id']);
//
//                /**判断是否还有未退款的订单*/
//                $is_set_r = M("ReturnOrder")->where(array('status'=>['in','0,1'],'order_id'=>$order_id['order_id'],
//                    'id'=>['neq',$_POST['return_id']]))->getField('id');
//
//                if($bigOrderPrice<=floatval($return_price)+floatval($return_price_sum) && empty($is_set_r)){
//                    $pro_status = M("ProductOrder")->where(['p_o_id'=>$order_id['order_id']])->limit(1)->save(['status'=>9,'cancel_time'=>time()]);
//                }else{
//                    $pro_status = 1;
//                }
                break;
            case 2:
                $title = "售后申请拒绝通知";
                $content = "订单号{$order_res['order_sn']}售后申请商家拒绝了，请联系商家了解详情！";
                $type = 0;
                $id = $order_res['m_id'];
                $is_set = $member_res['is_set'];
                $return_data['shop_agree_mess'] = json_encode(
                    [
                        "head_pic"=>C("API_URL").'/'.$shop_res['head_pic'],
                        "name"=>$shop_res['name'],
                        "time"=>date("Y-m-d H:i:s",time()),
                        "other_one"=>"卖家已经拒绝售后申请",
                    ]
                );
//                $pro_status = 1;
                break;
            case 9:
                $title = "售后申请用户已经撤销了";
                $content = "订单号{$order_res['order_sn']}售后申请用户已经撤销了！";
                $type = 1;
                $id = $order_res['shop_id'];
                $is_set = $shop_res['is_set'];
//                $pro_status = 1;
                break;
        }
        $is_success_mess =$this->addMessage($id,$title,$content,$type,$order_id['price'],$type?$order_res['m_id']:$order_res['shop_id'],0,$order_res['pay_type']);
        /**修改售后订单的状态*/
        $return_data['status'] = $_POST['status'];
        $res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->limit(1)->save($return_data);
//        apiResponse($res,$is_success_mess,M("ReturnOrder")->getLastSql());
        if($res&&$is_success_mess){
            M()->commit();
            if($is_set == 1){
                /**给APP商家端推送消息*/
                try {
                    $alias = '' . $id;
                    $extra['order_id'] = '' . $order_id['order_id'];
                    $extra['mess_id'] = $is_success_mess;
                    $extra['type'] = $type;
                    $this->push("", $alias, $title, $content, $extra, $type);
                }catch (\Exception $e){
                    apiResponse("success","操作成功");
                }
            }
            apiResponse("success",'操作成功');
        }else{
            M()->rollback();
            apiResponse("error","操作失败");
        }
    }

    /**添加退货地址
     * @author crazy
     * @time 2018-01-01
     * @param shop_id  商家的id
     * addr_id  地址的id
     * return_id 退款的id
     * m_id 用户的id
     * status 1是收货地址  2是物流  3是确认收货并退款
     *
     *
     * company_name 物流公司
     * logistic_number 物流的单号
     * delivery_code 物流的编号
     */
    public function makeSureReturnOrder(){
        $returnRow = M("ReturnOrder")->where(['id' => $_POST['return_id']])->find();
        /**查看订单的支付方式*/
        $product_res = M("ProductOrder")->where(['p_o_id'=>$returnRow['order_id']])->find();
        if($returnRow['status'] == 3){
            apiResponse("error","该笔退款已经完成，请勿重复操作！");
        }
        $shop_id = $returnRow['shop_id'];
        $m_id = $returnRow['m_id'];
        /**找到商家*/
        $areas_obj = M('Areas');
        $shop_res = M("Shop")->where(['shop_id'=>$shop_id])->field("is_set,name,head_pic,ice_wallet")->find();
        $address = M("Address")->where(['addr_id'=>$_POST['addr_id']])->field("mix_id,name,phone,address,province,city,area")->find();
        $proname = $areas_obj->where(array('area_id'=>$address['province']))->limit(1)->getField('area_name');
        $city = $areas_obj->where(array('area_id'=>$address['city']))->limit(1)->getField('area_name');
        $area = $areas_obj->where(array('area_id'=>$address['area']))->limit(1)->getField('area_name');
        /**找到用户*/
        $member = M("Member")->where(['m_id'=>$m_id])->field('is_set,nick_name,head_pic,wallet')->find();
        $return_data = [];
        $is_set = 0;
        $type = 0;
        $id = 0;
        $title = "";
        $content = "";
        $pro_status = 1;
        $wallet_mem=1;
        $ice_wallet=1;
        M()->startTrans();
        switch($_POST['status']){
            case 1:
                $return_data['shop_logistics_address'] = json_encode(
                    [
                        "head_pic"=>C("API_URL").'/'.$shop_res['head_pic'],
                        "name"=>$shop_res['name'],
                        "time"=>date("Y-m-d H:i:s",time()),
                        "other_one"=>"商家确认退货地址",
                        "other_two"=>"收货人:{$address['name']}",
                        "other_three"=>"电话:{$address['phone']}",
                        "other_four"=>"地址:{$proname}{$city}{$area}{$address['address']}",
                        "status"=>"1",
                    ]
                );
                $is_set = $shop_res['is_set'];
                $type = 0;
                $id = $m_id;
                $title = "商家确认退货地址";
                $content = "请您确认商家退货的收货地址并发货";
                break;
            case 2:
                $return_data['express'] = json_encode(
                    [
                        "head_pic"=>strpos($member['head_pic'],'http://')!==false?$member['head_pic']:C("API_URL").$member['head_pic'],
                        "name"=>$member['nick_name'],
                        "time"=>date("Y-m-d H:i:s",time()),
                        "other_one"=>"{$_POST['company_name']}",
                        "other_two"=>"{$_POST['logistic_number']}",
                        "other_three"=>"{$_POST['delivery_code']}",
                    ]
                );
                $is_set = $shop_res['is_set'];
                $type = 1;
                $id = $shop_id;
                $title = "买家已经将您的货物发货了！";
                $content = "买家{$member['nick_name']}使用{$_POST['company_name']}物流，单号为{$_POST['logistic_number']}，请您注意查收！";
                break;
            case 3:
                $return_data['sign_mess'] = json_encode(
                    [
                        "head_pic"=>C("API_URL")."/".$shop_res['head_pic'],
                        "name"=>$shop_res['name'],
                        "time"=>date("Y-m-d H:i:s",time()),
                        "other_one"=>"卖家已签收退款，请前往钱包中查看...",
                    ]
                );

                $return_data['price_mess'] = json_encode(
                    [
                        "head_pic"=>C("API_URL")."/".$shop_res['head_pic'],
                        "name"=>$shop_res['name'],
                        "time"=>date("Y-m-d H:i:s",time()),
                        "other_one"=>"卖家已退款，请前往钱包中查看...",
                    ]
                );
                $is_set = $member['is_set'];
                $type = 0;
                $id = $m_id;
                $title = "卖家已签收您的货物";
                $content = "卖家已签收您的货物并确认退款中，请前往钱包中查看...";

                /**判断一下，如果这个订单使用了优惠券，并且订单的金额小于单个商品的金额，那么这个订单将变成取消状态
                 * 查找一下这个订单还能退款多少
                 */
                $js_price = $this->returnPrice($returnRow['order_id'],$_POST['return_id']);

                /**获取退款的总和，不包含我当前的退款的订单*/
                $return_price_sum = M("ReturnOrder")->where(array('status'=>['in','0,1,3'],'order_id'=>$returnRow['order_id'],
                    'id'=>['neq',$_POST['return_id']]))->sum('price');

                /**获取一下单个商品的价格，比较单个商品的价格和总的订单的价格钱数，也就是多个商品相加-优惠券就是我们可退款的金额，不能把优惠券金额退给用户*/
                $return_price = $this->bigPrice($product_res['goods_gather'],$returnRow['goods_id'],$returnRow['attr_val'],$js_price);

                /**通过大的订单找到用户付款的钱数，配合退款商品功能实现,也就是用户实际付款的钱数，里面会扣除平台优惠券*/
                $bigOrderPrice = $this->bigOrderPrice($product_res['o_t_id'],$returnRow['order_id']);
                /**判断是否还有未退款的订单*/
                $is_set_r = M("ReturnOrder")->where(array('status'=>['in','0,1'],'order_id'=>$returnRow['order_id'],
                    'id'=>['neq',$_POST['return_id']]))->getField('id');
                /**如果大的订单有优惠券的话，这个才能走下面的第一个方法*/
                $cou_money = M("OrderTotal")->where(['id'=>$product_res['o_t_id']])->getField('cou_money');
                if(sprintf("%.2f",$bigOrderPrice)<=sprintf("%.2f",floatval($return_price)+floatval($return_price_sum)) && empty($is_set_r)
                    && sprintf("%.2f",$return_price) == sprintf("%.2f",$returnRow['price']) && $cou_money>0){

                    $pro_status = M("ProductOrder")->where(['p_o_id'=>$returnRow['order_id']])->limit(1)->save(['status'=>9,'cancel_time'=>time()]);

//                    apiResponse($bigOrderPrice,floatval($return_price)+floatval($return_price_sum));
                    /**减少商家的待结算金额,如果取消的话就要把金额全部减掉*/
                    /**计算在取消订单之前已经扣除了多少金额,要不包含我当前的值，因为当使用了优惠券的时候，有一笔会少于所退金额*/
                    $return_real_price_sum = M("ReturnOrder")->where(['order_id'=>$returnRow['order_id'],'status'=>['neq',2],'real_price'=>['gt',0],'id'=>['neq',$_POST['return_id']]])->sum('real_price');
                    $return_price_sum = M("ReturnOrder")->where(['order_id'=>$returnRow['order_id'],'status'=>['neq',2],'real_price'=>0.00,'id'=>['neq',$_POST['return_id']]])->sum('price');
//                    dump($return_real_price_sum);
//                    dump($return_price_sum);
//                    dump($product_res['real_price']);
//                    dump(M("ReturnOrder")->getLastSql());
                    /**应该减去这个订单剩下的钱数*/
                    $remain = floatval($product_res['real_price'])-floatval($return_real_price_sum)-floatval($return_price_sum);
                    $ice_wallet_data = floatval($shop_res['ice_wallet']) - $remain;
                    $ice_wallet_data = $ice_wallet_data<0?0:$ice_wallet_data;
                    $ice_wallet = M("Shop")->where(['shop_id' => $shop_id])->limit(1)->save(['ice_wallet'=>$ice_wallet_data]);
//                  dump("a:".M("Shop")->getLastSql());
                }elseif($bigOrderPrice<=floatval($return_price)+floatval($return_price_sum) && empty($is_set_r) && $return_price == $returnRow['price']){
                    $pro_status = M("ProductOrder")->where(['p_o_id'=>$returnRow['order_id']])->limit(1)->save(['status'=>9,'cancel_time'=>time()]);
                    /**减少商家的待结算金额*/
                    if($returnRow['real_price']>0){
                        /**以防重复减少钱数，要把退款的钱数减去其他的钱数*/
                        $ice_wallet_data = floatval($shop_res['ice_wallet']) - floatval($returnRow['real_price']);
                        //file_put_contents('sm.txt',$return_price_other.'='.(floatval($shop_res['ice_wallet']) - floatval($returnRow['real_price'])+floatval($return_price_other)));
                    }else{
                        $ice_wallet_data = floatval($shop_res['ice_wallet']) - floatval($returnRow['price']);
                    }
                    $ice_wallet_data = $ice_wallet_data<0?0:$ice_wallet_data;
                    $ice_wallet = M("Shop")->where(['shop_id' => $shop_id])->limit(1)->save(['ice_wallet'=>$ice_wallet_data]);
                }else{
                    /**减少商家的待结算金额*/
                    if($returnRow['real_price']>0){
                        /**以防重复减少钱数，要把退款的钱数减去其他的钱数*/
                        $ice_wallet_data = floatval($shop_res['ice_wallet']) - floatval($returnRow['real_price']);
                        //file_put_contents('sm.txt',$return_price_other.'='.(floatval($shop_res['ice_wallet']) - floatval($returnRow['real_price'])+floatval($return_price_other)));
                    }else{
                        $ice_wallet_data = floatval($shop_res['ice_wallet']) - floatval($returnRow['price']);
                    }
                    $ice_wallet_data = $ice_wallet_data<0?0:$ice_wallet_data;
                    $ice_wallet = M("Shop")->where(['shop_id' => $shop_id])->limit(1)->save(['ice_wallet'=>$ice_wallet_data]);
                }

                /**给用户钱包价钱*/
                $wallet_data = [
                    'wallet' => floatval($member['wallet']) + floatval($returnRow['price'])
                ];
                $wallet_mem = M("Member")->where(['m_id' => $m_id])->limit(1)->save($wallet_data);
                $this->addMessage($m_id,"退货退款通知","商家确认退款{$returnRow['price']}元，请前往钱包查看！",
                    0,$returnRow['price'],$returnRow['shop_id']);
                $this->addBill($m_id,$shop_id,"退货退款通知",
                    "商家确认退款{$returnRow['price']}元，请前往钱包查看！",
                    $returnRow['price'],0,'0',2,$shop_res['name'],10,1,$shop_id,0,$returnRow['price'],0,0);
                break;
        }
        $is_success_mess =$this->addMessage($id,$title,$content,$type,$returnRow['price'],$type?$returnRow['m_id']:$returnRow['shop_id'],0,$product_res['pay_type']);
        if($_POST['status'] == 3){
            $return_data['status'] = 3;
        }
        $res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->limit(1)->save($return_data);
//        dump($res.'-'.$pro_status.'-'.$wallet_mem.'-'.$ice_wallet);
//        exit;
        if($res && $pro_status&&$wallet_mem&&$ice_wallet){
            M()->commit();
            if($is_set == 1){
                try {
                    /**给APP商家端推送消息*/
                    $alias = '' . $id;
                    $extra['mess_id'] = $is_success_mess;
                    $extra['type'] = $type;
                    $this->push("", $alias, $title, $content, $extra, $type);
                }catch (\Exception $e){
                    apiResponse("success","操作成功");
                }
            }
            apiResponse("success",'操作成功');
        }else{
            M()->rollback();
            apiResponse("error","操作失败");
        }
    }


    /**商家确认退货地址的操作
     * @author crazy
     * @time 2018-01-02
     * @param return_id
     */
    public function makeSureAddress(){
        $res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->field("shop_logistics_address")->limit(1)->find();
        $arr = json_decode($res['shop_logistics_address'],true);
        $arr['status'] = 1;
        $return_data['shop_logistics_address'] = json_encode($arr);
        $edit_res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->limit(1)->save($return_data);
        if($edit_res){
            apiResponse("success",'操作成功');
        }else{
            apiResponse("error","操作失败");
        }

    }

    /**协商历史
     * @author crazy
     * @time 2018-01-02
     * @param return_id 1
     */
    public function returnOrderHistoryDetail(){
        $res = M("ReturnOrder")->where(['id'=>$_POST['return_id']])->find();
        /**退款的图片*/
        $arr_pic = [];
        if(!empty($res['pic'])){
            $arr = explode(',',$res['pic']);
            foreach($arr as $k=>$v){
                $arr_pic[] = C("API_URL")."/".$v;
            }
        }
        /**找到用户*/
        $member = M("Member")->where(['m_id'=>$res['m_id']])->field('is_set,nick_name,head_pic,account')->find();
        $return = [
            'application_mess'=>[
                "head_pic"=>strpos($member['head_pic'],'http://')!==false?$member['head_pic']:C("API_URL").$member['head_pic'],
                "name"=>$member['nick_name'],
                "time"=>date("Y-m-d H:i:s",time()),
                "return_status"=>"退款类型：{$res['return_status']}",
                "price"=>"退款金额：{$res['price']}",
                "reason"=>"退款原因：{$res['reason']}",
                "remark"=>"退款说明：{$res['remark']}",
                'pic'=>$arr_pic?$arr_pic:[]

            ],
            'shop_agree_mess'=>$res['shop_agree_mess']?json_decode($res['shop_agree_mess'],true):"",
            'shop_logistics_address'=>$res['shop_logistics_address']?json_decode($res['shop_logistics_address'],true):"",
            'express'=>$res['express']?json_decode($res['express'],true):"",
            'sign_mess'=>$res['sign_mess']?json_decode($res['sign_mess'],true):"",
            'price_mess'=>$res['price_mess']?json_decode($res['price_mess'],true):"",
            'member_account'=>$member['account']
        ];

        apiResponse("success",'成功',$return);
    }
}
