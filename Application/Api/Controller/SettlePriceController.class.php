<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class SettlePriceController
 * @package Api\Controller
 * 商家结算
 */
class SettlePriceController extends ApiBasicController{
    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }

    /**给商家结算订单费用（定时任务，每天夜里1点开始执行）
     * 给商家结算商品订单的金额
     * 1、wallet加钱
     * 2、ice_wallet减钱
     * 3、减去申请退款的钱数
     * 4、给用户加麦穗
     * 5、计算用户的股数
     * 6、添加商家的账单的明细
     *
     * 出现下方情况的订单将延期结算
     * 1、当有退款的请求商家未完成的订单
     * @author crazy
     * @time 2018-01-02
     */
    public function settlePriceShop()
    {
        /**开启事务*/
        M()->startTrans();
        $list = M("ProductOrder")->where(['is_account' => 0, 'status' => ['in', '3,4'], 'affirm_time' => ['neq', 0]])->field('p_o_id,
        real_price,affirm_time,shop_id,m_id,pay_type,o_t_id,order_sn')->select();
//        dump($list);
        /**查看符合标注的购买股份的人员的最低值*/
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        foreach ($list as $k => $v) {
            if (time() > ($v['affirm_time'] + 604810)) {
//                dump("确实收货时间");
                $invest_res_trans = 0;
                /**找到这个订单是否有0,1状态的退款账单,如果有就不能给商户结算，因为有的退款订单还未完成*/
                $is_set = M("ReturnOrder")->where(['order_id' => $v['p_o_id'], 'status' => ['in', [0, 1]]])->find();
                if (empty($is_set)) {
//                    dump("没有退款");
//                    dump($v['p_o_id']);
                    /**修改订单的结算状态*/
                    $edit_is_account = M("ProductOrder")->where(['p_o_id' => $v['p_o_id']])->limit(1)->save(['is_account' => 1]);
                    /**找到商家*/
                    $shop_res = M("Shop")->where(['shop_id' => $v['shop_id']])->field("is_set,scale_p,scale_member,name,
                    ice_wallet,wallet,deduct,integral,piles,province,city,area,enter_price")->find();
                    /**找到用户*/
                    $member = M("Member")->where(['m_id' => $v['m_id']])->field('m_id,nick_name,is_set,integral')->find();
                    /**共计退款的钱数*/
                    $null_price = M("ReturnOrder")->where([['order_id' => $v['p_o_id'], 'status' => 3]])->sum("price");
                    $return_total_price = $null_price?$null_price:0;
//                    dump("退款金额{$return_total_price}");
                    $cou_money_show = $this->scale($v['o_t_id'], $v['real_price']);
//                    dump($cou_money_show);
                    $ear_price = sprintf("%.2f", $v['real_price'] - $cou_money_show['cou_scale_price'] - $cou_money_show['wallet_scale_price']);
//                    dump("ear_price{$ear_price}");
                    if($v['pay_type'] == 0){
                        $invest_res_trans = 1;
                    }else{
                        if($return_total_price<=0){
                            if ((floatval($ear_price) + floatval($member['integral'])) >= $meet_pay_price) {
                                /**满足就添加一股*/
                                $a = (floatval($ear_price) + floatval($member['integral'])) / $meet_pay_price;
                                /**添加用户的股数*/
                                $c = floor($a) - $member['piles'];
                                for ($i = 1; $i <= $c; $i++) {
                                    $pie_data['mix_id'] = $v['m_id'];
                                    $pie_data['pie'] = 1;
                                    $pie_data['ctime'] = time();
                                    $pie_data['type'] = 0;
                                    M("Pie")->add($pie_data);
                                }
                                $after_data['piles'] = floor($a);
                                $after_data['integral'] = floatval($member['integral']) + floatval($ear_price);
                                $after_data['utime'] = time();
                                $invest_res_trans = M("Member")->where(array('m_id' => $v['m_id']))->limit(1)->save($after_data);
                            } else {
                                $after_data['integral'] = floatval($member['integral']) + floatval($ear_price);
                                $after_data['utime'] = time();
                                $invest_res_trans = M("Member")->where(array('m_id' => $v['m_id']))->limit(1)->save($after_data);
//                                dump(M("Member")->getLastSql());
                            }
                        }elseif ( ($v['real_price'] - $cou_money_show['cou_scale_price']) < $return_total_price) {
                            /**如果退款的钱数大于实际支付的微信的钱数，那么就不能加麦穗和股数*/
                            if($return_total_price<$ear_price){
                                $add_inter =  floatval($ear_price) - floatval($return_total_price);
                            }else{
                                $add_inter =  floatval($return_total_price) - floatval($ear_price);
                            }
//                            dump("给用户加的钱数{$add_inter}");
                            if ((floatval($add_inter) + floatval($member['integral'])) >= $meet_pay_price) {
                                /**满足就添加一股*/
                                $a = (floatval($add_inter) + floatval($member['integral'])) / $meet_pay_price;
                                /**添加用户的股数*/
                                $c = floor($a) - $member['piles'];
                                for ($i = 1; $i <= $c; $i++) {
                                    $pie_data['mix_id'] = $v['m_id'];
                                    $pie_data['pie'] = 1;
                                    $pie_data['ctime'] = time();
                                    $pie_data['type'] = 0;
                                    M("Pie")->add($pie_data);
                                }
                                $after_data['piles'] = floor($a);
                                $after_data['integral'] = floatval($member['integral']) + floatval($add_inter);
                                $after_data['utime'] = time();
                                $invest_res_trans = M("Member")->where(array('m_id' => $v['m_id']))->limit(1)->save($after_data);
                            } else {
                                $after_data['integral'] = floatval($member['integral']) + floatval($add_inter);
                                $after_data['utime'] = time();
                                $invest_res_trans = M("Member")->where(array('m_id' => $v['m_id']))->limit(1)->save($after_data);
//                                dump(M("Member")->getLastSql());
                            }
                        }else{
                            $invest_res_trans = 1;
                        }
                    }
                    /**计算商家应该获取的钱数*/
                    /**减少商家的待结算金额,如果取消的话就要把金额全部减掉*/
                    /**计算在取消订单之前已经扣除了多少金额*/
                    $return_real_price_sum = M("ReturnOrder")->where(['order_id'=>$v['p_o_id'],'real_price'=>['gt',0]])->sum('real_price');
//                    dump("退款real_price金额".$return_real_price_sum);
                    $return_price_sum = M("ReturnOrder")->where(['order_id'=>$v['p_o_id'],'real_price'=>0.00])->sum('price');
//                    dump(M("ReturnOrder")->getLastSql());
                    $return_total_price_sum = floatval($return_real_price_sum)+floatval($return_price_sum);
//                    dump("退款的金额",$return_total_price_sum);
//                    dump("商家应该加的麦穗".$price);
                    /**通过额度计算商家的麦穗相关*/
                    $count_price = floatval($v['real_price']) - floatval($return_total_price_sum); //计算商家应该结算的金额
                    $commission = ($shop_res['scale_p'] + $shop_res['scale_member']) / 100;
                    $return_arr = $this->countEnter($shop_res['enter_price'],$count_price,(1-$commission),$shop_res['scale_p'],$shop_res['scale_member'],$shop_res['deduct']);
                    $price = $return_arr['price_commission_y_m'];  //给商家加的麦穗
                    $price_wallet = $return_arr['price_commission']; //给商家加的钱数
                    $other_price = $return_arr['price_commission_y'];  //运营费用

                    $price = $price>0?$price:0;
                    if ($shop_res['deduct'] <= 0) {
                        $invest_res_trans_shop = 1;
                    } else {
                        if ((floatval($price) + floatval($shop_res['integral'])) >= $meet_pay_price) {
                            /**满足就添加一股*/
                            $a_shop = ((floatval($price)) + floatval($shop_res['integral'])) / $meet_pay_price;
                            /**添加商家的股数*/
                            if (floor($a_shop) > $shop_res['piles']) {
                                $shop_x_pie = floor($a_shop) - $shop_res['piles'];
                                for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                                    $pie_data['mix_id'] = $v['shop_id'];
                                    $pie_data['pie'] = 1;
                                    $pie_data['ctime'] = time();
                                    $pie_data['type'] = 1;
                                    M("Pie")->add($pie_data);
                                }
                            }
                            $after_data_shop['piles'] = floor($a_shop);
                            $after_data_shop['integral'] = floatval($shop_res['integral']) + floatval($price);
                            $after_data_shop['utime'] = time() + 2;
                            $invest_res_trans_shop = M("Shop")->where(array('shop_id' => $v['shop_id']))->limit(1)->save($after_data_shop);
                        } else {
                            unset($after_data_shop);
                            $after_data_shop['integral'] = floatval($shop_res['integral']) + floatval($price);
                            $after_data_shop['utime'] = time() + 1;
                            $invest_res_trans_shop = M("Shop")->where(array('shop_id' => $v['shop_id']))->limit(1)->save($after_data_shop);
                        }
                    }
                    /**减少商家的冻结的资金*/
                    /**商家的运营费用*/
//                    $other_price = sprintf("%.2f", (($shop_res['scale_p'] + $shop_res['scale_member']) / 100) * $shop_add_price);  //商家的运营的费用
                    /**如果是直接减去我订单的钱数，那么你就要加上用户退款的钱数，因为退款的钱数已经在退款的时候减去了*/
                    $ice_wallet_price = floatval($shop_res['ice_wallet']) - $count_price;
                    $edit_ice_wallet = M("Shop")->where(['shop_id' => $v['shop_id']])->limit(1)->save(['ice_wallet' => $ice_wallet_price<0?0:$ice_wallet_price]);
                    /**给商家加钱减额度*/
                    if($shop_res['enter_price'] >= $count_price){
                        $shop_data['enter_price'] = floatval($shop_res['enter_price'])-floatval($count_price);
                    }elseif ($shop_res['enter_price'] == 0.00 || $shop_res['enter_price'] == 0){
                        $shop_data['enter_price'] = 0.00;
                    }else{
                        $shop_data['enter_price'] = 0.00;
                    }
//                    dump("给商家加的钱数：".$price_wallet);
                    $edit_wallet = M("Shop")->where(['shop_id' => $v['shop_id']])->limit(1)->save(['wallet' => floatval($shop_res['wallet']) + floatval($price_wallet),
                    'enter_price'=>$shop_data['enter_price']]);
                    /**给商家添加消息*/
                    $message_res_j = $this->addMessage($v['shop_id'], "订单结算！",
                        date('Y-m-d-H:i:s', time()) . "订单号:{$v['order_sn']}的结算了金额：{$price_wallet}元，运营费用{$other_price}元",
                        '1', $price_wallet);
                    /**给商家添加账单*/
                    $j_bill = $this->addBill($v['shop_id'], 0, "订单结算！",
                        date('Y-m-d-H:i:s', time()) . "订单号:{$v['order_sn']}的结算了金额：{$price_wallet}元",
                        $price_wallet,$other_price,'0',2,$member['nick_name'],8,0,$v['m_id'], 1, $v['real_price'], 0, 0);
//                    dump($edit_is_account.'-'.$invest_res_trans.'-'.$edit_ice_wallet.'-'.$edit_wallet.'-'.$message_res_j.'-'.$j_bill.'-'.$invest_res_trans_shop);
                    /**打印计算的结果*/
                    $log = "订单的id为{$v['p_o_id']},订单的编号为：{$v['order_sn']},结算的钱数为：{$price_wallet}元，运营的费用为{$other_price}，执行结果：
                    $edit_is_account.'-'.$invest_res_trans.'-'.$edit_ice_wallet.'-'.$edit_wallet.'-'.$message_res_j.'-'.$j_bill.'-'.$invest_res_trans_shop";
                    write_log("settlePriceShop.txt",$log);

                    /**添加公司的收入平台费*/
                    $price_data_x = array(
                        'price'=> sprintf("%.2f",($shop_res['scale_p']/100)* $count_price),
                        'shop_id'=> $v['shop_id'],
                        'm_id'=> $member['m_id'],
                        'province'=>$shop_res['province']?$shop_res['province']:0,
                        'city'=>$shop_res['city']?$shop_res['city']:0,
                        'area'=>$shop_res['area']?$shop_res['area']:0,
                        'type'      => 1,
                        'ctime'=>time()
                    );
                    $price_p = M("Price")->add($price_data_x);
                    /**添加资金池*/
                    $pool_data = array(
                        'price'     => sprintf("%.2f",($shop_res['scale_member']/100)*$count_price),
                        'shop_id'   => $v['shop_id'],
                        'm_id'      => $member['m_id'],
                        'province'  => $shop_res['province']?$shop_res['province']:0,
                        'city'      => $shop_res['city']?$shop_res['city']:0,
                        'area'      => $shop_res['area']?$shop_res['area']:0,
                        'type'      => 1,
                        'ctime'     => time()
                    );
                    $pool = M("Pool")->add($pool_data);
                    if ($edit_is_account && $invest_res_trans && $edit_ice_wallet && $edit_wallet && $message_res_j && $j_bill
                        && $invest_res_trans_shop && $price_p && $pool) {
                        M()->commit();
                    } else {
                        M()->rollback();
                    }
                }
            }
        }
    }

    /**提前结算金额*/
    public function advancePrice(){
        $p_o_id = $_POST['p_o_id'];
        /**找到这个订单是否有0,1状态的退款账单,如果有就不能给商户结算，因为有的退款订单还未完成*/
        $is_set = M("ReturnOrder")->where(['order_id' => $p_o_id, 'status' => ['in', [0, 1]]])->find();
        $order_res = M("ProductOrder")->where(['p_o_id' => $p_o_id])->find();
        if(empty($order_res)){
            apiResponse("error","暂无此订单");
        }
        if($order_res['status'] <= 2 ){
            apiResponse("error","该订单暂不支持结算");
        }
        if($order_res['is_account'] == 1 ){
            apiResponse("error","该订单已经结算");
        }
        /**查看符合标注的购买股份的人员的最低值*/
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        if (empty($is_set)) {
//                    dump("没有退款");
//                    dump($v['p_o_id']);
            /**修改订单的结算状态*/
            $edit_is_account = M("ProductOrder")->where(['p_o_id' => $p_o_id])->limit(1)->save(['is_account' => 1]);
            /**找到商家*/
            $shop_res = M("Shop")->where(['shop_id' => $order_res['shop_id']])->field("is_set,scale_p,scale_member,name,
                    ice_wallet,wallet,deduct,integral,piles,province,city,area,enter_price")->find();

            /**找到用户*/
            $member = M("Member")->where(['m_id' => $order_res['m_id']])->field('m_id,nick_name,is_set,integral')->find();
            /**共计退款的钱数*/
            $null_price = M("ReturnOrder")->where([['order_id' => $p_o_id, 'status' => 3]])->sum("price");
            $return_total_price = $null_price?$null_price:0;
                    //dump("退款金额{$return_total_price}");
            $cou_money_show = $this->scale($order_res['o_t_id'], $order_res['real_price']);
                    //dump($cou_money_show);
            $ear_price = sprintf("%.2f", $order_res['real_price'] - $cou_money_show['cou_scale_price'] - $cou_money_show['wallet_scale_price']);
                    //dump("ear_price{$ear_price}");
            if($order_res['pay_type'] == 0){
                $invest_res_trans = 1;
            }else{
                /**判断是否有退款*/
                if($return_total_price <=0){
//                  dump("给用户加的钱数{$add_inter}");
                    if ((floatval($ear_price) + floatval($member['integral'])) >= $meet_pay_price) {
                        /**满足就添加一股*/
                        $a = (floatval($ear_price) + floatval($member['integral'])) / $meet_pay_price;
                        /**添加用户的股数*/
                        $c = floor($a) - $member['piles'];
                        for ($i = 1; $i <= $c; $i++) {
                            $pie_data['mix_id'] = $order_res['m_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 0;
                            M("Pie")->add($pie_data);
                        }
                        $after_data['piles'] = floor($a);
                        $after_data['integral'] = floatval($member['integral']) + floatval($ear_price);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id' => $order_res['m_id']))->limit(1)->save($after_data);
                    } else {
                        $after_data['integral'] = floatval($member['integral']) + floatval($ear_price);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id' => $order_res['m_id']))->limit(1)->save($after_data);
                    }
                }elseif ( ($order_res['real_price'] - $cou_money_show['cou_scale_price']) < $return_total_price) {  //只能用实际支付的钱数减去优惠券的金额，豆也能退款
                    /**如果退款的钱数大于实际支付的微信的钱数，那么就不能加麦穗和股数，这个里面已经计算了减去豆的*/
                    if($return_total_price<$ear_price){
                        $add_inter =  floatval($ear_price) - floatval($return_total_price);
                    }else{
                        $add_inter =  floatval($return_total_price) - floatval($ear_price);
                    }
//                            dump("给用户加的钱数{$add_inter}");
                    if ((floatval($add_inter) + floatval($member['integral'])) >= $meet_pay_price) {
                        /**满足就添加一股*/
                        $a = (floatval($add_inter) + floatval($member['integral'])) / $meet_pay_price;
                        /**添加用户的股数*/
                        $c = floor($a) - $member['piles'];
                        for ($i = 1; $i <= $c; $i++) {
                            $pie_data['mix_id'] = $order_res['m_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 0;
                            M("Pie")->add($pie_data);
                        }
                        $after_data['piles'] = floor($a);
                        $after_data['integral'] = floatval($member['integral']) + floatval($add_inter);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id' => $order_res['m_id']))->limit(1)->save($after_data);
                    } else {
                        $after_data['integral'] = floatval($member['integral']) + floatval($add_inter);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id' => $order_res['m_id']))->limit(1)->save($after_data);
                        //dump(M("Member")->getLastSql());
                    }
                }else{
                    $invest_res_trans = 1;
                }
            }
            /**计算商家应该获取的钱数*/
            /**减少商家的待结算金额,如果取消的话就要把金额全部减掉*/
            /**计算在取消订单之前已经扣除了多少金额*/
            $return_real_price_sum = M("ReturnOrder")->where(['order_id'=>$p_o_id,'real_price'=>['gt',0]])->sum('real_price');
//          dump("退款real_price金额".$return_real_price_sum);
            $return_price_sum = M("ReturnOrder")->where(['order_id'=>$p_o_id,'real_price'=>0.00])->sum('price');  //这个是退款用优惠券，然后导致退款金额小于商品金额，然后不能给商家加
//          dump(M("ReturnOrder")->getLastSql());
            $return_total_price_sum = floatval($return_real_price_sum)+floatval($return_price_sum);  //退款的总钱数
            /**通过额度计算商家的麦穗相关*/
            $commission = ($shop_res['scale_p'] + $shop_res['scale_member']) / 100;
            $count_price = floatval($order_res['real_price']) - floatval($return_total_price_sum); //计算商家应该结算的金额
            $return_arr = $this->countEnter($shop_res['enter_price'],$count_price,(1-$commission),$shop_res['scale_p'],$shop_res['scale_member'],$shop_res['deduct']);
            $price = $return_arr['price_commission_y_m'];  //给商家加的麦穗
            $price_wallet = $return_arr['price_commission']; //给商家加的钱数
            $other_price = $return_arr['price_commission_y'];  //运营费用
//          dump("退款的金额",$return_total_price_sum);  dump("商家应该加的麦穗".$price);
            $price = $price>0?$price:0;
            if ($shop_res['deduct'] <= 0 || $shop_res['deduct'] == 0.00) {
                $invest_res_trans_shop = 1;
            } else {
                if ((floatval($price) + floatval($shop_res['integral'])) >= $meet_pay_price) {
                    /**满足就添加一股*/
                    $a_shop = ((floatval($price)) + floatval($shop_res['integral'])) / $meet_pay_price;
                    /**添加商家的股数*/
                    if (floor($a_shop) > $shop_res['piles']) {
                        $shop_x_pie = floor($a_shop) - $shop_res['piles'];
                        for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                            $pie_data['mix_id'] = $order_res['shop_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 1;
                            M("Pie")->add($pie_data);
                        }
                    }
                    $after_data_shop['piles'] = floor($a_shop);
                    $after_data_shop['integral'] = floatval($shop_res['integral']) + floatval($price);
                    $after_data_shop['utime'] = time() + 2;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id' => $order_res['shop_id']))->limit(1)->save($after_data_shop);
                } else {
                    unset($after_data_shop);
                    $after_data_shop['integral'] = floatval($shop_res['integral']) + floatval($price);
                    $after_data_shop['utime'] = time() + 1;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id' => $order_res['shop_id']))->limit(1)->save($after_data_shop);
                }
            }
            /**减少商家的冻结的资金*/
            /**商家的运营费用*/

//            $other_price = sprintf("%.2f", (($shop_res['scale_p'] + $shop_res['scale_member']) / 100) * $shop_add_price);  //商家的运营的费用
            /**如果是直接减去我订单的钱数，那么你就要加上用户退款的钱数，因为退款的钱数已经在退款的时候减去了*/
            $ice_wallet_price = floatval($shop_res['ice_wallet']) - $count_price;
            $edit_ice_wallet = M("Shop")->where(['shop_id' => $order_res['shop_id']])->limit(1)->save(['ice_wallet' => $ice_wallet_price<0?0:$ice_wallet_price]);
            /**给商家加钱减额度*/
            if($shop_res['enter_price'] >= $count_price){
                $shop_data['enter_price'] = floatval($shop_res['enter_price'])-floatval($count_price);
            }elseif ($shop_res['enter_price'] == 0.00 || $shop_res['enter_price'] == 0){
                $shop_data['enter_price'] = 0.00;
            }else{
                $shop_data['enter_price'] = 0.00;
            }
//                    dump("给商家加的钱数：".$price_wallet);
            $edit_wallet = M("Shop")->where(['shop_id' => $order_res['shop_id']])->limit(1)->save(['wallet' => floatval($shop_res['wallet']) + floatval($price_wallet)
            ,'enter_price'=>$shop_data['enter_price']]);
            /**给商家添加消息*/
            $message_res_j = $this->addMessage($order_res['shop_id'], "订单结算！",
                date('Y-m-d-H:i:s', time()) . "订单号:{$order_res['order_sn']}的结算了金额：{$price_wallet}元，运营费用{$other_price}元",
                '1', $price_wallet);
            /**给商家添加账单*/
            $j_bill = $this->addBill($order_res['shop_id'], 0, "订单结算！",
                date('Y-m-d-H:i:s', time()) . "订单号:{$order_res['order_sn']}的结算了金额：{$price_wallet}元",
                $price_wallet,$other_price,'0',2,$member['nick_name'],8,0,$order_res['m_id'], 1, $order_res['real_price'], 0, 0);
//                    dump($edit_is_account.'-'.$invest_res_trans.'-'.$edit_ice_wallet.'-'.$edit_wallet.'-'.$message_res_j.'-'.$j_bill.'-'.$invest_res_trans_shop);
            /**打印计算的结果*/
            $log = "订单的id为{$order_res['p_o_id']},订单的编号为：{$order_res['order_sn']},结算的钱数为：{$price_wallet}元，运营的费用为{$other_price}，执行结果：
                    $edit_is_account.'-'.$invest_res_trans.'-'.$edit_ice_wallet.'-'.$edit_wallet.'-'.$message_res_j.'-'.$j_bill.'-'.$invest_res_trans_shop";
            write_log("settlePriceShop.txt",$log);

            /**添加公司的收入平台费*/
            $price_data_x = array(
                'price'=> sprintf("%.2f",($shop_res['scale_p']/100)* $count_price),
                'shop_id'=> $order_res['shop_id'],
                'm_id'=> $member['m_id'],
                'province'=>$shop_res['province']?$shop_res['province']:0,
                'city'=>$shop_res['city']?$shop_res['city']:0,
                'area'=>$shop_res['area']?$shop_res['area']:0,
                'type'      => 1,
                'ctime'=>time()
            );
            $price_p = M("Price")->add($price_data_x);
            /**添加资金池*/
            $pool_data = array(
                'price'     => sprintf("%.2f",($shop_res['scale_member']/100)*$count_price),
                'shop_id'   => $order_res['shop_id'],
                'm_id'      => $member['m_id'],
                'province'  => $shop_res['province']?$shop_res['province']:0,
                'city'      => $shop_res['city']?$shop_res['city']:0,
                'area'      => $shop_res['area']?$shop_res['area']:0,
                'type'      => 1,
                'ctime'     => time()
            );
            $pool = M("Pool")->add($pool_data);
            if ($edit_is_account && $invest_res_trans && $edit_ice_wallet && $edit_wallet && $message_res_j && $j_bill
                && $invest_res_trans_shop && $price_p && $pool) {
                M()->commit();
                apiResponse("success",'结算成功');
            } else {
                M()->rollback();
                apiResponse("error",'结算失败，请联系客服');
            }
        }else{
            apiResponse("error","您有未退款订单，不支持结算");
        }
    }

    /**根据金额计算额度问题
     * @author crazy
     * @time 2018-03-12
     */
    public function countEnter($enter_price,$shop_add_price,$commission,$scale_p,$scale_member,$deduct){
        /**判断商家的额度*/
        if($enter_price >= $shop_add_price){
            /**计算商家应该得的钱数*/
            $price_commission = $shop_add_price;
            /**运营费用，因为商家有额度所以不扣除运营费用*/
            $price_commission_y = 0.00;
            $price_commission_y_m =  0;
        }elseif ($enter_price == 0.00 || $enter_price == 0){
            /**计算商家应该得的钱数*/
            $price_commission = sprintf("%.2f",$commission*$shop_add_price);
            /**运营费用*/
            $price_commission_y = sprintf("%.2f",(($scale_p+$scale_member)/100)*$shop_add_price);
            $price_commission_y_m =  sprintf("%.2f",(($deduct)/100)*$shop_add_price);
        }else{
            /**计算商家应该得的钱数,只有一部分是扣运营费用的，一部分不扣除*/
            $c_y_price = floatval($shop_add_price)-floatval($enter_price);
            $price_commission = floatval($shop_add_price)-floatval(sprintf("%.2f",(($scale_p+$scale_member)/100)*$c_y_price));
            /**运营费用*/
            $price_commission_y = sprintf("%.2f",(($scale_p+$scale_member)/100)*$c_y_price);
            $price_commission_y_m =  sprintf("%.2f",(($deduct)/100)*$c_y_price);
        }

        $data['price_commission'] = $price_commission;
        $data['price_commission_y'] = $price_commission_y;
        $data['price_commission_y_m'] = $price_commission_y_m;
        return $data;
    }


    /**用户自动确认收货的方法，每天凌晨4点执行
     * @author crazy
     * @time 2018-02-05
     */
    public function autoMakeOrder(){
        $list = M("ProductOrder")->where(['status'=>2,'send_time'=>['neq',0]])->field("p_o_id,send_time")->select();
        foreach($list as $k=>$v){
            if(($v['send_time']+15*24*64*64)<time()){
                $data['order_id'] = $v['p_o_id'];
                $url = C("API_URL")."/Api/ProductOrder/makeSure";
                $this->request_post($url,$data);
            }
        }
    }



    /**商家自动确认售后申请的方法，每天凌晨2点执行
     * @author crazy  仅退款   退货退款
     * @time 2018-02-05
     */
    public function autoReturnOrder(){
        $list = M("ReturnOrder")->where(['status'=>0])->field("id,m_id,shop_id,order_id,goods_id,return_status")->select();
        foreach($list as $k=>$v){
            if(($v['ctime']+3*24*64*64)<time()){
                switch($v['return_status']){
                    case "仅退款":
                        $data['shop_id'] = $v['shop_id'];
                        $data['m_id'] = $v['m_id'];
                        $data['return_id'] = $v['id'];
                        $data['status'] = 3;
                        $url = C("API_URL")."/Api/ReturnOrder/makeSureReturnOrder";
                        $this->request_post($url,$data);
                        break;
                    case "退货退款":
                        $data['return_id'] = $v['id'];
                        $data['status'] = 1;
                        $url = C("API_URL")."/Api/ReturnOrder/backOutReturnOrder";
                        $this->request_post($url,$data);
                        break;

                }
            }
        }
    }


    public function test(){
        $a = "0.00";
        if(35.2==35.20){
            dump(1);
        }
    }


}