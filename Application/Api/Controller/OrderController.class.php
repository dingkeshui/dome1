<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 订单
 */
class OrderController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();

    }



    /**
     * 用户使用众享豆进行买单
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param price 支付的豆
     * @param m_id 用户的id
     * @param shop_id 商家的id
     */
    public function payBill(){
        if(empty($_POST['price']) || $_POST['price'] <= 0){
            apiResponse('error',"请输入有效的金额");
        }
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误！");
        }
        if(empty($_POST['shop_id'])){
            apiResponse("error","参数错误！");
        }

        M()->startTrans();
        $data['order_sn'] = date('YmdHis').mt_rand(100000,999999);
        $data['shop_id'] = $_POST['shop_id'];
        $data['m_id'] = $_POST['m_id'];
        $data['pay_money'] = $_POST['price'];

        $coupon_log = 1;
        $coupon_res = 1;
        //是否使用优惠券
        if(!empty($_POST['c_m_id'])){
            $data['c_m_id'] = $_POST['c_m_id'];
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
            $data['coupon_type'] = $coupon['type'];
            switch ($coupon['type']){
                case 1:
                    //定额券
                    $data['coupon_money'] = $coupon['min_price'];
                    $data['pay_money'] = $_POST['price']-$coupon['min_price']<0?0:$_POST['price']-$coupon['min_price'];
                    break;
                case 2:
                    //折扣券
                    $data['coupon_money'] = sprintf("%.2f",((1-($coupon['money']/10)))*$_POST['price']);
                    $data['pay_money'] = $_POST['price']-$data['coupon_money']<0?0:$_POST['price']-$data['coupon_money'];
                    break;
                case 3:
                    //满减券，需要判断消费金额是否满足使用条件
                    if($_POST['price']<$coupon['max_price']){
                        apiResponse("error","不符合使用条件！");
                    }else{
                        $data['coupon_money'] = $coupon['min_price'];
                        $data['pay_money'] = $_POST['price']-$coupon['min_price']>0?0:$_POST['price']-$coupon['min_price'];
                    }
                    break;
                case 4:
                    //菜品券
                    break;
            }
            $cou_data['m_id'] = $_POST['m_id'];
            $cou_data['c_m_id'] = $_POST['c_m_id'];
            $cou_data['ctime'] = time();
            $coupon_log = M('CouponLog')->add($cou_data);
            $coupon_res = M('CouponMember')->where(array('c_m_id'=>$_POST['c_m_id']))->limit(1)->save(array('status'=>1));
        }

        /**通过商家id获取商家的省市区的id*/
        $shop_id = $_POST['shop_id'];
        $shop_res = M("Shop")->where(array('shop_id'=>$shop_id))->field("deduct,is_set,wallet,account,scale_shop,openid,province,name,
        city,area,scale_p,scale_member,earn_total,total,integral,piles,is_recommend,enter_price,recommend")->find();

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
                M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($inter_data_member_shop);
                if($recommend_shop_price){
                    /**给用户添加账单*/
                    $bill_data_shop_member['title'] = "推荐商家的收益！";
                    $bill_data_shop_member['content'] = date('Y-m-d H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆";
                    $bill_data_shop_member['ctime'] = time();
                    $bill_data_shop_member['m_id'] = $shop_res['recommend'];
                    $bill_data_shop_member['monitor'] = 0;
                    $bill_data_shop_member['type'] = 2;
                    $bill_data_shop_member['id_type'] = 1;
                    $bill_data_shop_member['rank_type'] = 0;
                    $bill_data_shop_member['name'] = $shop_res['name'];
                    $bill_data_shop_member['price'] = $shop_member_inter;
                    $bill_data_shop_member['pay_type'] = 2;
                    $bill_data_shop_member['accept_m_id'] = $shop_id;
                    M("Bill")->add($bill_data_shop_member);
                    /**给用户发消息推送推荐收益的信息*/
                    $this->addMessage($shop_res['recommend'],"推荐商家的收益！",
                        date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                        0,$shop_member_inter);
                }
            }
        }

        /**找到用户*/
        $m_wallet = M("Member")->where(array('m_id'=>$_POST['m_id']))->field('nick_name,m_id,wallet,earn_total,total,is_add,recommend,mem_recommend,is_recommend,is_floor')->limit(1)->find();
        /**
         * 判断这个用户是否是被别人推荐的，如果是别人推荐的，当产生第一笔消费的时候，用户将或者一定的众享豆数作为报酬
         * 防止商家和用户同样获取金额，所以同步计算
         */
        $inter_price_member = 0;
        $inter_recommend_member = 0;
        $inter_price = M("Config")->getField("inter");
        if(!empty($m_wallet['recommend']) && $m_wallet['mem_recommend'] !=1){
            $is_first_order_member['m_id'] = $_POST['m_id'];
            $count_member = M("Order")->where($is_first_order_member)->count();
            if($count_member<=1){
                /**添加用户的推荐人的推荐钱数*/
                $inter_recommend_member = $m_wallet['recommend'];
                $inter_price_member = $inter_price;
                /**用户的推荐状态改变成1*/
                $inter_data_member_recommend['mem_recommend'] = 1;
                M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($inter_data_member_recommend);
                if($inter_price_member){
                    /**给用户添加账单*/
                    $m_bill_data['title'] = "推荐人收益！";
                    $m_bill_data['content'] = date('Y-m-d H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆";
                    $m_bill_data['ctime'] = time();
                    $m_bill_data['m_id'] = $m_wallet['recommend'];
                    $m_bill_data['monitor'] = 0;
                    $m_bill_data['type'] = 2;
                    $m_bill_data['id_type'] = 0;
                    $m_bill_data['name'] = $m_wallet['nick_name'];
                    $m_bill_data['price'] = $inter_price;
                    $m_bill_data['pay_type'] = 2;
                    $m_bill_data['accept_m_id'] = $m_wallet['m_id'];
                    M("Bill")->add($m_bill_data);
                    /**给用户发消息推送买单的信息*/
                    $this->addMessage($m_wallet['recommend'],"推荐人收益！",
                        date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                        0,$inter_price);
                }
            }
        }



        /**计算商家的运营费用之外商家应该得到的比例的钱数*/
        $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
        /**判断商家的额度*/
        if($shop_res['enter_price'] >= $_POST['price']){
            /**计算商家应该得的钱数*/
            $price_commission = $_POST['price'];
            /**运营费用*/
            $price_commission_y = 0.00;
            $price_commission_y_m = 0;
        }elseif ($shop_res['enter_price'] == 0.00 || $shop_res['enter_price'] == 0){
            /**计算商家应该得的钱数*/
            $price_commission = sprintf("%.2f",$commission*$_POST['price']);
            /**运营费用*/
            $price_commission_y = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$_POST['price']);
            $price_commission_y_m =  sprintf("%.2f",(($shop_res['deduct'])/100)*$_POST['price']);
        }else{
            /**计算商家应该得的钱数*/
            $c_y_price = floatval($_POST['price'])-floatval($shop_res['enter_price']);
            $price_commission = floatval($_POST['price'])-floatval(sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$c_y_price));
            /**运营费用*/
            $price_commission_y = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$c_y_price);
            $price_commission_y_m =  sprintf("%.2f",(($shop_res['deduct'])/100)*$c_y_price);
        }
        $data['price'] = $price_commission;
        $data['other_price'] = $price_commission_y;
        $data['province'] = $shop_res['province'];
        $data['city'] = $shop_res['city'];
        $data['area'] = $shop_res['area'];
        $data['ip'] = get_client_ip();
        $data['pay_type'] = 0;
        $data['ctime'] = time();
        $data['total_price'] = $_POST['price'];
        $res = M("Order")->add($data);

        /**
         * 判断一下给用户加推荐商家和推荐用户钱数的操作，当前用户的钱包和推荐人的操作会产生冲突，所以要判断执行
         * 1、这个用户和这个商家都是一个人推荐的
         * 2、商家是一个推荐的
         * 3、用户是一个推荐的
         * 4、这个商家是当前消费的用户推荐的
         */
        $m_wallet_res = 0;
        if($recommend_shop_price >0 && $inter_price_member>0){
            if($recommend_shop_person == $inter_recommend_member){
                if($recommend_shop_person == $_POST['m_id']){
                    /**用户的钱包减去买单的钱数*/
                    if($data['pay_money']>0){
                        $m_data['wallet'] = floatval($m_wallet['wallet'])+floatval($recommend_shop_price)+floatval($inter_price_member)-floatval($data['pay_money']);
                    }
                    $m_data['utime'] = time();
                    if(empty($m_wallet)){
                        apiResponse("error","参数错误！");
                    }
                    if($m_data['wallet']<0){
                        apiResponse('error',"账号余额不足！");
                    }
                    $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
                }else{
                    unset($m_data);
                    /**用户的钱包减去买单的钱数*/
                    if($data['pay_money']>0){
                        $m_data['wallet'] = floatval($m_wallet['wallet'])-floatval($data['pay_money']);
                    }
                    $m_data['utime'] = time();
                    if(empty($m_wallet)){
                        apiResponse("error","参数错误！");
                    }
                    if($m_data['wallet']<0){
                        apiResponse('error',"账号余额不足！");
                    }
                    $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member)+floatval($recommend_shop_price);
                    M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
                }
            }elseif($recommend_shop_person != $inter_recommend_member){
                if($recommend_shop_person == $_POST['m_id']){
                    /**用户的钱包减去买单的钱数*/
                    if($data['pay_money']>0){
                        $m_data['wallet'] = floatval($m_wallet['wallet'])+floatval($recommend_shop_price)-floatval($data['pay_money']);
                    }
                    $m_data['utime'] = time();
                    if(empty($m_wallet)){
                        apiResponse("error","参数错误！");
                    }
                    if($m_data['wallet']<0){
                        apiResponse('error',"账号余额不足！");
                    }
                    $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
                }elseif ($inter_recommend_member == $_POST['m_id']){
                    /**用户的钱包减去买单的钱数*/
                    if($data['pay_money']>0){
                        $m_data['wallet'] = floatval($m_wallet['wallet'])+floatval($inter_price_member)-floatval($data['pay_money']);
                    }
                    $m_data['utime'] = time();
                    if(empty($m_wallet)){
                        apiResponse("error","参数错误！");
                    }
                    if($m_data['wallet']<0){
                        apiResponse('error',"账号余额不足！");
                    }
                    $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
                }else{
                    /**用户的钱包减去买单的钱数*/
                    if($data['pay_money']>0){
                        $m_data['wallet'] = floatval($m_wallet['wallet'])-floatval($data['pay_money']);
                    }
                    $m_data['utime'] = time();
                    if(empty($m_wallet)){
                        apiResponse("error","参数错误！");
                    }
                    if($m_data['wallet']<0){
                        apiResponse('error',"账号余额不足！");
                    }
                    $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
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
            if($recommend_shop_price>0 && ($recommend_shop_person == $_POST['m_id'])){
                /**用户的钱包减去买单的钱数*/
                if($data['pay_money']>0){
                    $m_data['wallet'] = floatval($m_wallet['wallet'])+floatval($recommend_shop_price)-floatval($data['pay_money']);
                }
                $m_data['utime'] = time();
                if(empty($m_wallet)){
                    apiResponse("error","参数错误！");
                }
                if($m_data['wallet']<0){
                    apiResponse('error',"账号余额不足！");
                }
                $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
            }elseif ($recommend_shop_price>0 && ($recommend_shop_person != $_POST['m_id'])){
                /**用户的钱包减去买单的钱数*/
                if($data['pay_money']>0){
                    $m_data['wallet'] = floatval($m_wallet['wallet'])-floatval($data['pay_money']);
                }
                $m_data['utime'] = time();
                if(empty($m_wallet)){
                    apiResponse("error","参数错误！");
                }
                if($m_data['wallet']<0){
                    apiResponse('error',"账号余额不足！");
                }
                $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
                /**给推荐商家的用户加钱*/
                /**找到用户的钱包的金额*/
                $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
            }
        }elseif ($inter_price_member>0){
            /**如果这个用户是当前消费的用户推荐的，那么这个用户就要获取推荐用户的钱数*/
            if($inter_price_member>0 && ($inter_recommend_member == $_POST['m_id'])){
                /**用户的钱包减去买单的钱数*/
                if($data['pay_money']>0){
                    $m_data['wallet'] = floatval($m_wallet['wallet'])+floatval($inter_price_member)-floatval($data['pay_money']);
                }
                $m_data['utime'] = time();
                if(empty($m_wallet)){
                    apiResponse("error","参数错误！");
                }
                if($m_data['wallet']<0){
                    apiResponse('error',"账号余额不足！");
                }
                $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
            }elseif ($inter_price_member>0 && ($inter_recommend_member != $_POST['m_id'])){
                /**用户的钱包减去买单的钱数*/
                if($data['pay_money']>0){
                    $m_data['wallet'] = floatval($m_wallet['wallet'])-floatval($data['pay_money']);
                }else{
                    apiResponse("error","系统错误！");
                }
                if(empty($m_wallet)){
                    apiResponse("error","参数错误！");
                }
                if($m_data['wallet']<0){
                    apiResponse('error',"账号余额不足！");
                }
                $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
                /**给推荐商家的用户加钱*/
                $wallet_recommend = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member);
                M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data);
            }
        }else{
            /**用户的钱包减去买单的钱数*/
            if($data['pay_money']>0){
                $m_data['wallet'] = floatval($m_wallet['wallet'])-floatval($data['pay_money']);
            }
            $m_data['utime'] = time();
            if(empty($m_wallet)){
                apiResponse("error","参数错误！");
            }
            if($m_data['wallet']<0){
                apiResponse('error',"账号余额不足！");
            }
            $m_wallet_res =  M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($m_data);
        }


        /**
         * 商家的钱包加上买单的钱数
         */
        /**减少商家的入驻费用*/
        if($shop_res['enter_price'] >= $_POST['price']){
            $shop_data['enter_price'] = floatval($shop_res['enter_price'])-floatval($_POST['price']);
        }elseif ($shop_res['enter_price'] == 0.00 || $shop_res['enter_price'] == 0){
            $shop_data['enter_price'] = 0.00;
        }else{
            $shop_data['enter_price'] = 0.00;
        }
        $shop_data['wallet'] = floatval($shop_res['wallet'])+floatval($data['price']);
        $shop_wallet_res =  M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($shop_data);
        /**用户的账单上添加买单钱数的记录*/
        /**添加用户的账单明细*/
        $m_bill_data['title'] = "买单消费！";
        $m_bill_data['content'] = date('Y-m-d H:i:s',time())."支付给账号为：".$shop_res['account']."商家名称为：".$shop_res['name'].','.$_POST['price']."众享豆";
        $m_bill_data['ctime'] = time();
        $m_bill_data['m_id'] = $_POST['m_id'];
        $m_bill_data['shop_id'] = $_POST['shop_id'];
        $m_bill_data['monitor'] = 1;
        $m_bill_data['type'] = 3;
        $m_bill_data['id_type'] = 1;
        $m_bill_data['name'] = $shop_res['name'];
        $m_bill_data['price'] = $_POST['price'];
        $m_bill_data['accept_m_id'] = $_POST['shop_id'];
        $m_bill_data['other_b_id'] = 0;
        $m_bill_data['o_id'] = $res;
        $m_bill = M("Bill")->add($m_bill_data);
        /**商家的账单上添加买单钱数的记录*/
        $j_bill_data['title'] = "用户买单记录！";
        $j_bill_data['content'] = date('Y-m-d H:i:s',time())."收到用户：".$m_wallet['nick_name'].",".$data['price']."众享豆！";
        $j_bill_data['m_id'] = $_POST['shop_id'];
        $j_bill_data['rank_type'] = 1;
        $j_bill_data['ctime'] = time();
        $j_bill_data['monitor'] = 0;
        $j_bill_data['type'] = 3;
        $j_bill_data['name'] = $m_wallet['nick_name'];
        $j_bill_data['id_type'] = 0;
        $j_bill_data['accept_m_id'] = $_POST['m_id'];
        $j_bill_data['price'] = $data['price'];
        $j_bill_data['total_price'] = $_POST['price'];
        $j_bill_data['other_b_id'] = $m_bill;
        $j_bill_data['o_id'] = $res;
        $j_bill = M("Bill")->add($j_bill_data);
        /**给用户发消息推送买单的信息*/
        $message_res_m = $this->addMessage($_POST['m_id'],"买单消费！",
            date('Y-m-d-H:i:s',time())."支付给账号为：".$shop_res['account']."商家名称为：".$shop_res['name'].','.$_POST['price']."众享豆",
            0,$_POST['price'],$_POST['shop_id'],0);

        /**给商家推送消息买单的信息*/
        $message_res_j = $this->addMessage($_POST['shop_id'],"用户买单记录！",
            date('Y-m-d-H:i:s',time())."收到用户：".$m_wallet['nick_name'].$data['price']."众享豆！运营费用：".$data['other_price'],
            1,$data['price'],$_POST['m_id'],0);

        /**给商家计算股份*/
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        $shop_price_earn = sprintf("%.2f",(($shop_res['deduct'])/100)*$_POST['price']);
        if($shop_res['deduct'] == 0 || $shop_res['deduct'] == 0.00){
            $invest_res_trans_shop = 1;
        }else{
            /**运营费用的返麦穗*/
//            $price_commission_y_m = sprintf("%.2f",(($shop_res['deduct'])/100)*$price_commission_y_x);
            if($shop_res['enter_price'] > 0){
                if((floatval($price_commission_y_m)+floatval($shop_res['integral']))>=$meet_pay_price){
                    /**满足就添加一股*/
                    $a_shop = ((floatval($price_commission_y_m))+floatval($shop_res['integral']))/$meet_pay_price;
                    $piles = floor($a_shop)-$shop_res['piles'];
                    /**添加商家的股数*/
                    for ($i=1;$i<=$piles;$i++){
                        $pie_data['mix_id'] = $_POST['shop_id'];
                        $pie_data['pie'] = 1;
                        $pie_data['ctime'] = time();
                        $pie_data['type'] = 1;
                        M("Pie")->add($pie_data);
                    }
                    /**用户在商家处消费，商家要加上积分*/
                    $shop_inter_data['piles'] = floor($a_shop);
                    $shop_inter_data['integral'] = floatval($shop_res['integral'])+floatval($price_commission_y_m);
                    $shop_inter_data['utime'] = time()+2;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($shop_inter_data);
                }else{
                    unset($after_data_shop);
                    $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($price_commission_y_m);
                    $after_data_shop['utime'] = time()+1;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($after_data_shop);
                }
            }else{
                if((floatval($shop_price_earn)+floatval($shop_res['integral']))>=$meet_pay_price){
                    /**满足就添加一股*/
                    $a_shop = ((floatval($shop_price_earn))+floatval($shop_res['integral']))/$meet_pay_price;
                    $piles = floor($a_shop)-$shop_res['piles'];
                    /**添加商家的股数*/
                    for ($i=1;$i<=$piles;$i++){
                        $pie_data['mix_id'] = $_POST['shop_id'];
                        $pie_data['pie'] = 1;
                        $pie_data['ctime'] = time();
                        $pie_data['type'] = 1;
                        M("Pie")->add($pie_data);
                    }
                    /**用户在商家处消费，商家要加上积分*/
                    $shop_inter_data['piles'] = floor($a_shop);
                    $shop_inter_data['integral'] = floatval($shop_res['integral'])+floatval($shop_price_earn);
                    $shop_inter_data['utime'] = time()+2;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($shop_inter_data);
                }else{
                    unset($after_data_shop);
                    $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($shop_price_earn);
                    $after_data_shop['utime'] = time()+1;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($after_data_shop);
                }
            }
        }
        if($shop_res['deduct'] == 0 || $shop_res['deduct'] == 0.00){
            $total_res_shop = 1;
        }else{
            /**添加商家的提成的比例的和,商家的要减去费用也就是提点的费用*/
            $shop_total = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$_POST['price']);
            $total_data_shop['total'] = floatval($shop_total)+floatval($shop_res['total']);
            $total_data_shop['utime'] = time()+3;
            $total_res_shop = M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($total_data_shop);
        }

        /**添加公司的收入平台费*/
        $price_data_x['price'] = sprintf("%.2f",($shop_res['scale_p']/100)*$_POST['price']);
        $price_data_x['shop_id'] = $_POST['shop_id'];
        $price_data_x['province'] = $shop_res['province'];
        $price_data_x['city'] = $shop_res['city'];
        $price_data_x['area'] = $shop_res['area'];
        $price_data_x['ctime'] = time();
        $price = M("Price")->add($price_data_x);
        /**添加资金池*/
        $pool_data['price'] = sprintf("%.2f",($shop_res['scale_member']/100)*$_POST['price']);
        $pool_data['shop_id'] = $_POST['shop_id'];
        $pool_data['m_id'] = $_POST['m_id'];
        $pool_data['province'] = $shop_res['province'];
        $pool_data['city'] = $shop_res['city'];
        $pool_data['area'] = $shop_res['area'];
        $pool_data['ctime'] = time();
        $pool = M("Pool")->add($pool_data);
//        file_put_contents("dz.txt",$res.'_'.$m_wallet_res.'_'.$shop_wallet_res.'_'.$m_bill.'_'.$j_bill.'_'.$message_res_m.'_'.$message_res_j.'_'.$invest_res_trans_shop
//            .'_'.$price.'_'.$pool.'_'.$total_res_shop.'_'.$coupon_log."_".$coupon_res);
        if($res && $m_wallet_res && $shop_wallet_res && $m_bill && $j_bill && $message_res_m && $message_res_j && $invest_res_trans_shop && $price && $pool && $total_res_shop&&$coupon_log&&$coupon_res){
            /**向缓存中添加新的用户的数据*/
            $list1 = S("MEMBER_ORDER");
            $x_string = $list1?"会员".$m_wallet['nick_name']."消费了:".$_POST['price']."元".",".$list1:"".","."会员".$m_wallet['nick_name']."消费了:".$_POST['price']."元";
            S("MEMBER_ORDER",$x_string);
            M()->commit();
            M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->setInc('sale',1);
            /**给商家发送模板消息*/
            $tem_price = $_POST['price'];
            $time = date('Y-m-d H:i:s',time());
            $nick_name = $m_wallet['nick_name'];
            $data_to = array(
                'first'=>array('value'=>urlencode("您好,您有新的款项到账，请注意查收！")),
                'keyword1'=>array('value'=>urlencode("$time")),
                'keyword2'=>array('value'=>urlencode("$nick_name")),
                'keyword3'=>array('value'=>urlencode("豆支付")),
                'keyword4'=>array('value'=>urlencode("买单消费")),
                'keyword5'=>array('value'=>urlencode("$tem_price 众享豆")),
                'Remark'=>array('value'=>urlencode("$time,用户 $nick_name 支付了 $tem_price 众享豆")),
            );
            $url = $_SERVER['SERVER_NAME'];
            $this->wxSetSend($shop_res['openid'],"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","http://$url/index.php/Merchant/Shop/billlist/p/1/shop_id/".$_POST['shop_id'],$data_to);
            /**给APP商家端推送消息*/
            try{
                if($shop_res['is_set'] == 1){
                    $this->push('' ,$_POST['shop_id'] , '您好,您有新的款项到账，请注意查收！' , '用户'.$nick_name.'买单支付了'.$tem_price.'众享豆' , array('order_id'=>''.$res,'mess_id'=>$message_res_j,'b_id'=>$j_bill,'type'=>1),1);
                }
            }catch (\Exception $e){
                apiResponse("success","支付成功！");
            }
            apiResponse("success","支付成功！");
        }else{
            M()->rollback();

            apiResponse("error","支付失败！请联系管理员！");
        }
    }


    /**
     * 麦穗或者豆兑换商品的订单的列表
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param 1:type 是商家  0是用户
     * @param mix_id 用户或者商家的id
     */
    public function orderList(){
        if($_GET['type'] == 1){
            $w = " zxty_integral_order.rank_type = 1 ";
        }else{
            $w = " zxty_integral_order.rank_type = 0 ";
        }
        if($_GET['status']){
            if($_GET['status'] == 10){
                $status = " zxty_integral_order.status = 0 ";
            }else{
                $status = " zxty_integral_order.status = ".$_GET['status'];
            }

        }else{
            $status = " zxty_integral_order.status <> 9 ";
        }
        $p = ($_GET['p']-1)*15;
        $mix_id = $_GET['mix_id'];
        $order = "zxty_integral_order.ctime desc";
        $list = M()->query("select zxty_integral_order.i_o_id,zxty_integral_order.pay_type,zxty_integral_order.order_sn,zxty_integral_order.name,zxty_integral_order.tel,zxty_integral_order.pay_type,
                            zxty_integral_order.address,zxty_integral_order.price,zxty_integral_order.postage,zxty_integral_order.status,zxty_integral_order.ctime,
                            zxty_integral_order.type,zxty_integral_order.d_price,zxty_goods.name as goods_name,zxty_goods.price as goods_price,zxty_goods.cover_pic from zxty_integral_order,zxty_goods where $w 
                            AND zxty_integral_order.mix_id = $mix_id 
                            AND zxty_integral_order.g_id = zxty_goods.g_id AND $status ORDER BY $order limit $p,15");
        foreach($list as $k=>$v){
            $list[$k]['cover_pic'] = $this->returnPic($v['cover_pic']);
        }
        if(empty($list) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$list);
        }elseif ($list){
            apiResponse("success","获取成功！",$list);
        }elseif(empty($list)){
            apiResponse("success","无数据！");
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 确认收货
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param order_id 订单的id
     */
    public function makeSure(){
        if($_POST['is_readonly']==1){
            apiResponse("error","无操作权限！");
        }
        $w['i_o_id'] = $_POST['order_id'];
        $data['status'] = 3;
        $data['complete_time'] = time();
        $res = M("integral_order")->where($w)->limit(1)->save($data);
        if($res){
            apiResponse("success","确认收货成功！");
        }else{
            apiResponse("error","确认收货失败！");
        }
    }

    /**
     * 订单的详情
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param order_id 订单的id
     */
    public function orderDetail(){
        $w['i_o_id'] = $_POST['order_id'];
        $data['status'] = 3;
        $res = M("integral_order")->where($w)->limit(1)->find();
        $goods = M('Goods')->where(array('g_id'=>$res['g_id']))->field('name,price,cover_pic')->find();
        /**商品的名称*/
        $res['goods_name'] = $goods['name'];
        /**商品的图片*/
        $res['goods_price'] = $goods['price'];
        /**商品价格*/
        $res['cover_pic'] = $this->returnPic($goods['cover_pic']);
        $res['ctime'] = empty($res['ctime'])?"":date('Y-m-d H:i:s',$res['ctime']);
        $res['f_time'] = empty($res['f_time'])?"":date('Y-m-d H:i:s',$res['f_time']);
        $res['complete_time'] = empty($res['complete_time'])?"":date('Y-m-d H:i:s',$res['complete_time']);
        $res['t_time'] = empty($res['t_time'])?"":date('Y-m-d H:i:s',$res['t_time']);
        $res['delivery_company'] = empty($res['delivery_company'])?"":$res['delivery_company'];
        $res['delivery_code'] = empty($res['delivery_code'])?"":$res['delivery_code'];
        $res['delivery_number'] = empty($res['delivery_number'])?"":$res['delivery_number'];
        /**还有多久自动收货*/
        $res['take_goods_time'] = empty($res['f_time'])?"":date('d天H小时',($res['f_time']+604800)-time());
        if($res){
            apiResponse("success","获取成功！",$res);
        }else{
            apiResponse("error","获取失败！",$res);
        }
    }

    /**
     * 退货的操作
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param order_id 订单的id
     */
    public function returnOrder(){

        if($_POST['is_readonly']==1){
            apiResponse("error","无操作权限！");
        }
        $w['i_o_id'] = $_POST['order_id'];
        $data['status'] = 4;
        $data['t_time'] = time();
        $data['reason'] = filterHtml($_POST['reason']);
        $res = M("integral_order")->where($w)->limit(1)->save($data);
        if($res){
//            $this->sendMsg("13042231878","用户申请退货，请前去查看！");
            apiResponse("success","退货成功！");
        }else{
            apiResponse("error","退货失败！");
        }
    }


    /**使用豆抵扣的订单，取消订单，退还豆
     * @author crazy
     * @time 2017-12-04
     * @param order_id 订单的id
     */
    public function returnBeanOrder(){
        M()->startTrans();
        $res_order = M("IntegralOrder")->where(['i_o_id'=>I('post.order_id')])->find();
        if(empty($res_order)){
            apiResponse('error',"订单不存在");
        }
        /**给用户退款然后取消订单*/
        $mem_res = M("Member")->where(['m_id'=>$res_order['mix_id'],'status'=>array('neq',9)])->find();
        if(empty($mem_res)){
            apiResponse('error',"订单不存在");
        }
        /**改订单的状态*/
        $res_order_status = M("IntegralOrder")->where(['i_o_id'=>I('post.order_id')])->setField('status',9);
        /**改用户的钱包数*/
        $price = $mem_res['wallet']+$res_order['d_price'];
        $mem_res = M("Member")->where(['m_id'=>$res_order['mix_id']])->limit(1)->save(['wallet'=>$price]);
        if($res_order_status&&$mem_res){
            M()->commit();
            apiResponse('success',"取消成功！");
        }else{
            M()->rollback();
            apiResponse('error',"取消失败！");
        }
    }

    /**
     * 删除订单
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param order_id 订单的id
     */
    public function delOrder(){
        if($_POST['is_readonly']==1){
            apiResponse("error","无操作权限！");
        }
        $w['i_o_id'] = $_POST['order_id'];
        $data['status'] = 9;
        $res = M("integral_order")->where($w)->limit(1)->save($data);
        if($res){
            apiResponse("success","删除成功！");
        }else{
            apiResponse("error","删除失败！");
        }
    }

    /**订单的评价（显示商品第二版）
     * 传参方式 post
     * @author crazy
     * @time 2017-12-22
     */
    public function appraiseIntegralOrder(){
        $w['i_o_id'] = I("post.order_id");
        $order_res = M("IntegralOrder")->where($w)->find();
        $goods_res = M("Goods")->where(['g_id'=>$order_res['g_id']])->field("g_id,name,cover_pic")->find();
        $return= [];
        $return['goods_name'] = $goods_res['name'];
        $return['g_id'] = $goods_res['g_id'];
        $return['order_id'] = I("post.order_id");
        $return['pic'] = $this->returnPic($goods_res['cover_pic']);
        apiResponse("success","成功",$return);
    }


    /**
     * 评价订单，显示商品信息
     * 传参方式 post
     * @author crazy
     * @time 2017-12-21
     * @param order_id 订单id
     */
    public function appraise(){
        M()->startTrans();
        $order_id = $_POST['order_id'];
        $order = M('IntegralOrder')->where(array('i_o_id'=>$order_id))->find();
        $data = [
            'p_o_id'=>$_POST['order_id'],
            'm_id'  =>$_POST['m_id'],
            'p_id'  =>$order['g_id'],
            'star'  =>$_POST['star'],
            'content'  =>$_POST['content'],
            'pics'  =>$_POST['pics'],
            'status'=>1,
            'ctime' =>time(),
            'type'=>1
        ];
        $res = M("ProductAppraise")->add($data);
        /**修改订单的状态*/
        $order_res = M('IntegralOrder')->where(array('i_o_id'=>$order_id))->limit(1)->save(['status'=>6,'utime'=>time()]);
        if($res&&$order_res){
            M()->commit();
            apiResponse('success','评价成功');
        }else{
            M()->rollback();
            apiResponse('error','评价失败');
        }
    }


    /**积分商品的评价列表
     * 传参方式 post
     * @author crazy
     * @time 2017-12-22
     * @param goods_id 商品的id
     */
    public function integralGoodsAppraiseList(){
        $p_id = $_POST['goods_id'];
        $p = $_POST['p']?$_POST['p']:1;
        $w['p_id'] = $p_id;
        $w['status'] = 1;
        $w['type'] = 1;
        $data = $this->commonAppraise($w,$p);
        apiResponse('success','成功',$data);
    }

    /**
     * 评价上传图片
     * @author mss
    */
    public function uploadPics(){
        $arr = $_POST['pics'];
        $pic_arr = array();
        foreach ($arr as $k=>$v){
            $pic       = $v['pic'];
            $pic_name      = $v['pic_name'];
            $temp = explode('.',$pic_name);
            $ext = uniqid().'.'.end($temp);
            $base64    = substr(strstr($pic, ","), 1);
            $image_res = base64_decode($base64);
            $pic_link  = "Uploads/Appraise/".date('Y-m-d').'/'.uniqid().'.jpg';
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
        apiResponse('success','成功',$string);
    }


}