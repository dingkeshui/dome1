<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 豆支付商城的订单
 */
class BeanPayController extends ApiBasicController{
    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }


    /**大的订单直接支付的接口（豆支付）
     * @author crazy
     * @time 2018-01-03
     */
    public function BeanShopMixOrder(){
        $orderTotal = M("OrderTotal");
        $order_sn = $_POST['order_sn'];
        $pay_price = $_POST['price'];
        $order_res = $orderTotal->where(array('order_sn'=>$order_sn))->limit(1)->field("order_id,m_id,status,wallet,real_total_money")->find();
        M()->startTrans();
        /**找到子订单*/
        $shop_id_list = M("ProductOrder")->where(['p_o_id'=>['in',$order_res['order_id']]])->field("shop_id,real_price")->select();
        /**找到用户*/
        $member = M("Member")->where(['m_id'=>$order_res['m_id']])->field('m_id,nick_name,is_set,wallet,recommend,mem_recommend')->find();

        $this->addMemberCache($order_res['m_id']);

        $shop_id_push = [];
        $shop_openid_push = [];
        $shop_name = [];
        $commend_price_total = 0;
        /**计算用户的钱数*/
        foreach($shop_id_list as $k=>$v){
            $shop_res = M("Shop")->where(['shop_id'=>$v['shop_id']])->field("is_set,scale_p,scale_member,name,ice_wallet,is_recommend,recommend,openid")->find();
            /**计算商家应该获取的钱数*/
//            $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
//            $price = sprintf("%.2f",$commission*$v['real_price']);
            $price = $v['real_price'];
            /**商家的运营费用*/
//            $other_price = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$v['real_price']);
            /**给商家添加消息*/
            $this->addMessage($v['shop_id'],"用户购买商品支付！",
                "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$v['real_price']}元，请您尽快安排发货",'1',
                $price,$order_res['m_id'],2);
            /**增加商家的冻结的资金*/
            M("Shop")->where(['shop_id'=>$v['shop_id']])->limit(1)->save(['ice_wallet'=>floatval($shop_res['ice_wallet'])+floatval($price)]);
            /**给商家添加账单*/
            $this->addBill($v['shop_id'],0,"用户购买商品支付！",
                "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$v['real_price']}元",
                $v['real_price'],0,'0',0,$member['nick_name'],9,0,$order_res['m_id'],1,$v['real_price'],0,0);
            if($shop_res['is_set'] == 1){
                $shop_id_push[] = $v['shop_id'];
            }
            $shop_name[] = $shop_res['name'];
            /**通知微信公众平台商家发货的模板消息*/
            $shop_openid_push[] = $shop_res['openid'];
            /**
             * 给推荐商家的而用户加众享豆
             * 防止用户推荐商家和推荐用户一起累加金额，事物开启之后，锁了当前的用户的记录行，所以不能修改用户的钱包的数据
             */
            $recommend_shop_price = 0;
            $recommend_shop_person = 0;
            if($shop_res['is_recommend'] == 0 && $shop_res['recommend'] != 0){
                $shop_member_inter = M("Config")->getField("mem_shop_inter");
                $tj_shop_member = M("Member")->where(array("m_id"=>$shop_res['recommend']))->limit(1)->field("m_id,wallet")->find();
                if($tj_shop_member){
                    /**添加用户的推荐人的推荐钱数*/
                    $recommend_shop_price = $shop_member_inter;
                    $recommend_shop_person = $shop_res['recommend'];
                    /**商家的推荐状态改变成1*/
                    $inter_data_member_shop['is_recommend'] = 1;
                    M("Shop")->where(array('shop_id'=>$v['shop_id']))->limit(1)->save($inter_data_member_shop);
                    if($shop_member_inter){
                        /**给用户添加账单*/
                        $this->addBill($shop_res['recommend'],$v['shop_id'],"推荐商家的收益！",
                            date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                            $shop_member_inter,'0','0',2,$shop_res['name'],2,1,$v['shop_id'],0,0);
                        /**给用户发消息推送推荐收益的信息*/
                        $this->addMessage($shop_res['recommend'],"推荐商家的收益！",
                            date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                            '0',$shop_member_inter);
                    }
                }
            }

            $inter_price_member = 0;
            $inter_recommend_member = 0;
            $inter_price = M("Config")->getField("inter");
            if(!empty($member['recommend']) && $member['mem_recommend'] !=1){
                $is_first_order_member['m_id'] = $order_res['m_id'];
                $count_member = M("Order")->where($is_first_order_member)->count();
                if($count_member<=1){
                    /**添加用户的推荐人的推荐钱数*/
                    $inter_recommend_member = $member['recommend'];
                    $inter_price_member = $inter_price;
                    /**用户的推荐状态改变成1*/
                    $inter_data_member_recommend['mem_recommend'] = 1;
                    M("Member")->where(array('m_id'=>$order_res['m_id']))->limit(1)->save($inter_data_member_recommend);
                    if($inter_price_member){
                        /**给用户添加账单*/
                        $this->addBill($member['recommend'],0,"推荐人收益！",
                            date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                            $inter_price,'0','0',2,$member['nick_name'],2,0,$member['m_id'],0,0);
                        /**给用户发消息推送买单的信息*/
                        $this->addMessage($member['recommend'],"推荐人收益！",
                            date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                            '0',$inter_price);
                    }
                }
            }

            /**
             * 判断一下给用户加推荐商家和推荐用户钱数的操作，当前用户的钱包和推荐人的操作会产生冲突，所以要判断执行
             * 1、这个用户和这个商家都是一个人推荐的
             * 2、商家是一个推荐的
             * 3、用户是一个推荐的
             * 4、这个商家是当前消费的用户推荐的
             */
            if($recommend_shop_price >0 && $inter_price_member>0){
                if($recommend_shop_person == $inter_recommend_member){
                        unset($m_data);
                        /**找到用户的钱包的金额*/
                        $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member)+floatval($recommend_shop_price);
                        M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
                }elseif($recommend_shop_person != $inter_recommend_member){
                    if($recommend_shop_person == $order_res['m_id']){
                        $commend_price_total += $recommend_shop_price;
                    }else{
                        /**找到推荐商家的用户的钱包的金额*/
                        $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                        M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
                        /**找到推荐用户的钱包的金额*/
                        $wallet_recommend_member = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                        $wallet_data_member['wallet'] = floatval($wallet_recommend_member)+floatval($inter_price_member);
                        M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data_member);
                    }
                }
            }elseif ($recommend_shop_price>0){
                /**如果这个商家是当前消费的用户推荐的，那么这个用户就要获取推荐商家的钱数*/
                if($recommend_shop_price>0 && ($recommend_shop_person == $order_res['m_id'])){
                    $commend_price_total += $recommend_shop_price;
                }elseif ($recommend_shop_price>0 && ($recommend_shop_person != $order_res['m_id'])){
                    /**给推荐商家的用户加钱*/
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                    M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
                }
            }elseif ($inter_price_member>0){
                if ($inter_price_member>0 && ($inter_recommend_member != $order_res['m_id'])){
                    /**给推荐商家的用户加钱*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member);
                    M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data);
                }
            }
        }
        $MerName = implode(";",$shop_name);
        /**添加用户的账单和信息*/
        $this->addMessage($order_res['m_id'],"订单号{$order_sn}支付成功！",
            "订单号{$order_sn}支付成功,商家会为您尽快发货！",0,
            $order_res['real_total_money'],implode(',',$shop_id_push),2);
        $this->addBill($order_res['m_id'],0,"订单号{$order_sn}支付成功！",
            "订单号{$order_sn}支付成功,商家会为您尽快发货",
            $order_res['real_total_money'],0,'1',0,$MerName,9,0,$order_res['m_id'],0,$order_res['real_total_money'],0,0);

        /**用户减去钱数和增加推荐的钱数*/
//        if($pay_price == 0){
//            $member_wallet_res = 1;
//        }else{
//            $member_wallet_res = 1;
//            $member_wallet_res = M("Member")->where(['m_id'=>$order_res['m_id']])->limit(1)->save(['wallet'=>floatval($commend_price_total)+(
//                    floatval($member['wallet'])-floatval($pay_price))]);
//        }

//        apiResponse(M("Member")->getLastSql());
        /**修改订单的状态*/
        $is_pay_total = $orderTotal->where(array('order_sn'=>$order_sn))->limit(1)->setField('status',1);
        $small_order = M("ProductOrder")->where(['p_o_id'=>['in',$order_res['order_id']]])->save(['status'=>1,'pay_time'=>time()]);
        //dump($is_pay_total."_".$small_order."_".$member_wallet_res);
        if($is_pay_total && $small_order){
            M()->commit();
            try{
                if(count($shop_openid_push)>0){
                    foreach($shop_openid_push as $kk=>$vv){
                        $this->shopSendTem($member['nick_name'],$vv);
                    }
                }
            }catch (\Exception $e){
                apiResponse("success","支付成功");
            }
            if(count($shop_id_push)>0){
                try{
                    /**给APP商家端推送消息*/
                    $alias = ''.implode(',',$shop_id_push);
                    $alert = "您有新的订单请注意查收！";
                    $extra['type'] = '1';
                    $this->push("",$alias,$alert,$alert,$extra,1);
                }catch (\Exception $e){
                    apiResponse("success","支付成功");
                }
            }
            apiResponse("success","支付成功");
        }else{
            M()->rollback();
            apiResponse("error","支付失败");
        }
    }


    /**商家的订单直接支付的接口
     * @author crazy
     * @time 2018-01-03
     */
    public function BeanShopOneOrder(){
        $productOrder = M("ProductOrder");
        $order_sn = $_POST['order_sn'];
        $pay_price = $_POST['price'];
        $order_res = $productOrder->where(array('order_sn'=>$order_sn))->limit(1)->find();
        M()->startTrans();
        /**找到商家*/
        $shop_res = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->field("is_set,scale_p,scale_member,name,ice_wallet,openid")->find();
        /**找到用户*/
        $member = M("Member")->where(['m_id'=>$order_res['m_id']])->field('nick_name')->find();
        /**计算商家应该获取的钱数*/
//        $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
//        $price = sprintf("%.2f",$commission*$order_res['real_price']);
        $price = $order_res['real_price'];
        /**商家的运营费用*/
//        $other_price = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$order_res['real_price']);
        /**给商家添加消息*/
        $message_res_j = $this->addMessage($order_res['shop_id'],"用户购买商品支付！",
            "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$order_res['real_price']}元，请您尽快安排发货",'1',
            $price,$order_res['m_id'],2);
        /**给商家添加账单*/
        $j_bill = $this->addBill($order_res['shop_id'],0,"用户购买商品支付！",
            "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$order_res['real_price']}元",
            $order_res['real_price'],0,'0',0,$member['nick_name'],9,0,$order_res['m_id'],1,$order_res['real_price'],0,0);

        $this->addMemberCache($order_res['m_id']);

        /**增加商家的冻结的资金*/
        M("Shop")->where(['shop_id'=>$order_res['shop_id']])->limit(1)->save(['ice_wallet'=>floatval($shop_res['ice_wallet'])+floatval($price)]);

        /**添加用户的账单和信息*/
        $this->addMessage($order_res['m_id'],"订单号{$order_sn}支付成功！",
            "订单号{$order_sn}支付成功,商家会为您尽快发货！",'0',
            $order_res['real_price'],0,$order_res['real_price'],$order_res['shop_id'],2);
        $this->addBill($order_res['m_id'],0,"订单号{$order_sn}支付成功！",
            "订单号{$order_sn}支付成功,商家会为您尽快发货",
            $order_res['real_price'],0,'1',2,$shop_res['name'],9,1,$order_res['shop_id'],0,$order_res['real_price'],0,0);


        /**
         * 给推荐商家的而用户加众享豆
         * 防止用户推荐商家和推荐用户一起累加金额，事物开启之后，锁了当前的用户的记录行，所以不能修改用户的钱包的数据
         */
        $recommend_shop_price = 0;
        $recommend_shop_person = 0;
        $commend_price_total = 0;
        if($shop_res['is_recommend'] == 0 && $shop_res['recommend'] != 0){
            $shop_member_inter = M("Config")->getField("mem_shop_inter");
            $tj_shop_member = M("Member")->where(array("m_id"=>$shop_res['recommend']))->limit(1)->field("m_id,wallet")->find();
            if($tj_shop_member){
                /**添加用户的推荐人的推荐钱数*/
                $recommend_shop_price = $shop_member_inter;
                $recommend_shop_person = $shop_res['recommend'];
                /**商家的推荐状态改变成1*/
                $inter_data_member_shop['is_recommend'] = 1;
                M("Shop")->where(array('shop_id'=>$order_res['shop_id']))->limit(1)->save($inter_data_member_shop);
                if($shop_member_inter){
                    /**给用户添加账单*/
                    $this->addBill($shop_res['recommend'],$order_res['shop_id'],"推荐商家的收益！",
                        date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                        $shop_member_inter,'0','0',2,$shop_res['name'],2,1,$order_res['shop_id'],0,0);
                    /**给用户发消息推送推荐收益的信息*/
                    $this->addMessage($shop_res['recommend'],"推荐商家的收益！",
                        date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                        '0',$shop_member_inter);
                }
            }
        }

        $inter_price_member = 0;
        $inter_recommend_member = 0;
        $inter_price = M("Config")->getField("inter");
        if(!empty($member['recommend']) && $member['mem_recommend'] !=1){
            $is_first_order_member['m_id'] = $order_res['m_id'];
            $count_member = M("Order")->where($is_first_order_member)->count();
            if($count_member<=1){
                /**添加用户的推荐人的推荐钱数*/
                $inter_recommend_member = $member['recommend'];
                $inter_price_member = $inter_price;
                /**用户的推荐状态改变成1*/
                $inter_data_member_recommend['mem_recommend'] = 1;
                M("Member")->where(array('m_id'=>$order_res['m_id']))->limit(1)->save($inter_data_member_recommend);
                if($inter_price_member){
                    /**给用户添加账单*/
                    $this->addBill($member['recommend'],0,"推荐人收益！",
                        date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                        $inter_price,'0','0',2,$member['nick_name'],2,0,$member['m_id'],0,0);
                    /**给用户发消息推送买单的信息*/
                    $this->addMessage($member['recommend'],"推荐人收益！",
                        date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                        '0',$inter_price);
                }
            }
        }

        /**
         * 判断一下给用户加推荐商家和推荐用户钱数的操作，当前用户的钱包和推荐人的操作会产生冲突，所以要判断执行
         * 1、这个用户和这个商家都是一个人推荐的
         * 2、商家是一个推荐的
         * 3、用户是一个推荐的
         * 4、这个商家是当前消费的用户推荐的
         */
        if($recommend_shop_price >0 && $inter_price_member>0){
            if($recommend_shop_person == $inter_recommend_member){
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member)+floatval($recommend_shop_price);
                    M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
            }elseif($recommend_shop_person != $inter_recommend_member){
                if($recommend_shop_person == $order_res['m_id']){
                    $commend_price_total += $recommend_shop_price;
                }else{
                    /**找到推荐商家的用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                    M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
                    /**找到推荐用户的钱包的金额*/
                    $wallet_recommend_member = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                    $wallet_data_member['wallet'] = floatval($wallet_recommend_member)+floatval($inter_price_member);
                    M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data_member);
                }
            }
        }elseif ($recommend_shop_price>0){
            /**如果这个商家是当前消费的用户推荐的，那么这个用户就要获取推荐商家的钱数*/
            if($recommend_shop_price>0 && ($recommend_shop_person == $order_res['m_id'])){
                $commend_price_total += $recommend_shop_price;
            }elseif ($recommend_shop_price>0 && ($recommend_shop_person != $order_res['m_id'])){
                /**给推荐商家的用户加钱*/
                /**找到用户的钱包的金额*/
                $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
            }
        }elseif ($inter_price_member>0){
            if ($inter_price_member>0 && ($inter_recommend_member != $order_res['m_id'])){
                /**给推荐商家的用户加钱*/
                $wallet_recommend = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member);
                M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data);
            }
        }

        /**计算用豆抵扣的操作*/
//        $cou_money_show= $this->scale($order_res['o_t_id'],$order_res['real_price']);

        /**用户减去钱数和增加推荐的钱数*/
//        if($pay_price == 0){
//            $member_wallet_res = 1;
//        }else{
//            $member_wallet_res = M("Member")->where(['m_id'=>$order_res['m_id']])->limit(1)->save(['wallet'=>floatval($commend_price_total)+(
//                    floatval($member['wallet'])-floatval($pay_price))]);
//        }
        /**修改订单的状态*/
        $small_order = M("ProductOrder")->where(['p_o_id'=>$order_res['p_o_id']])->limit(1)->save(['status'=>1,'pay_time'=>time()]);
        //dump($small_order."-".$small_order."-".$member_wallet_res);
        if($small_order){
            M()->commit();
            try{
                $openid = $shop_res['openid'];
                $nick_name = $member['nick_name'];
                $this->shopSendTem($nick_name,$openid);
            }catch (\Exception $e){
                apiResponse("success","支付成功");
            }


            if($shop_res['is_set']==1){
                try{
                    /**给APP商家端推送消息*/
                    $alias = ''.$order_res['shop_id'];
                    $alert = "您有新的订单请注意查收！";
                    $extra['order_id'] = ''.$order_res['order_id'];
                    $extra['mess_id'] = $message_res_j;
                    $extra['b_id'] = $j_bill;
                    $extra['type'] = '1';
                    if($shop_res['is_set'] == 1){
                        $this->push("",$alias,$alert,$alert,$extra,1);
                    }
                }catch (\Exception $e){
                    apiResponse("success","支付成功");
                }
            }
            apiResponse("success","支付成功");
        }else{
            M()->rollback();
            apiResponse("error","支付失败");
        }
    }


}