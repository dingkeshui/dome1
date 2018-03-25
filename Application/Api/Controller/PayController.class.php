<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 支付回调
 */
class PayController extends ApiBasicController{
    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
        Vendor('WxPay.lib.WxPay#Api');
        Vendor('WxPay.WxPay#JsApiPay');
    }

    /**
     * 微信成功支付回调
     * @author crazy
     * @time 2017-07-03
     */
    public function callBack(){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml_res = $this->xmlToArray($xml);
        $order_sn = $xml_res["out_trade_no"];
        M()->startTrans();
        /**找到消费的记录*/
        $log_res = M("Log")->where(array('order_sn'=>$order_sn))->limit(1)->find();
        if($log_res['status'] == 1){
            echo "success";
            exit();
        }
        $log_data_s['status'] = 1;
        M("Log")->where(array('order_sn'=>$order_sn))->limit(1)->save($log_data_s);
        if($xml_res['result_code'] == 'SUCCESS'){
            /**缺少判断钱数和当前数据的钱数是否相同，如果不相同就存到error里面，然后发信息*/
            if($log_res['pay_money']!=($xml_res["total_fee"]/100)){
                $error_data_query['error_sn'] = $order_sn;
                $error_data_query['shop_id'] = $log_res['shop_id'];
                $error_data_query['m_id'] = $log_res['m_id'];
                $error_data_query['price'] = $log_res['pay_money'];//$log_res['price'];
                $error_data_query['title'] = "微信支付查询和用户提交的订单不符合!";
                $error_data_query['ctime'] = time();
                M("Error")->add($error_data_query);
                $this->sendMsg("18522713541","微信支付查询和用户提交的订单不符合!" );
                echo "success";
                exit();
            }
            $coupon_mem = 1;
            $coupon_log = 1;
            if($log_res['c_m_id']){
                $coupon_mem = M('CouponMember')->where(array('c_m_id'=>$log_res['c_m_id']))->limit(1)->save(array('status'=>1));
                $data_log['m_id'] = $log_res['m_id'];
                $data_log['c_m_id'] = $log_res['c_m_id'];
                $data_log['ctime'] = time();
                $coupon_log = M('CouponLog')->add($data_log);
            }

            /**通过商家id获取商家的省市区的id*/
            $shop_id = $log_res['shop_id'];
            $shop_res = M("Shop")->where(array('shop_id'=>$shop_id))->field("account,deduct,max_price,is_set,name,province,city,area,scale_p,scale_member,scale_shop,wallet,recommend,is_recommend,earn_total,total,integral,piles,openid")->find();
            /**
             * 给推荐商家的而用户加麦穗
             * 防止用户推荐商家和推荐用户一起累加金额，事物开启之后，锁了当前的用户的记录行，所以不能修改用户的钱包的数据
             */
            $recommend_shop_price = 0;
            $recommend_shop_recommend = 0;
            if($shop_res['is_recommend'] == 0 && $shop_res['recommend'] != 0){
                $shop_member_inter = M("Config")->getField("mem_shop_inter");
                $tj_shop_member = M("Member")->where(array("m_id"=>$shop_res['recommend']))->limit(1)->field("m_id,wallet")->find();
                if($tj_shop_member){
                    /**添加用户的推荐人的推荐钱数*/
                    $recommend_shop_price = $shop_member_inter;
                    $recommend_shop_recommend = $shop_res['recommend'];
                    /**商家的推荐状态改变成1*/
                    $inter_data_member_shop['is_recommend'] = 1;
                    M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($inter_data_member_shop);
                    if($recommend_shop_price){
                        /**给用户添加账单*/
                        $this->addBill($shop_res['recommend'],$shop_id,"推荐商家的收益！",
                            date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                            $shop_member_inter,'0','0',2,$shop_res['name'],2,1,$shop_id,0,0);
                        /**给用户发消息推送推荐收益的信息*/
                        $this->addMessage($shop_res['recommend'],"推荐商家的收益！",
                            date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$shop_member_inter."众享豆",
                            '0',$shop_member_inter);
                    }
                }
            }
            /**找到用户*/
            $member = M("Member")->where(array('m_id'=>$log_res['m_id']))->field('nick_name,m_id,wallet,earn_total,total,is_add,recommend,mem_recommend,is_recommend,is_floor,integral,piles')->limit(1)->find();
            $is_first_order['m_id'] = $log_res['m_id'];
            $count = M("Order")->where($is_first_order)->count();
            if($count<=0){
                /**向缓存中添加新的用户的数据*/
                $list1 = S("MEMBER_LIST");
                $x_string = $list1?"欢迎会员".$member['nick_name'].",".$list1:"".","."欢迎会员".$member['nick_name'];
                S("MEMBER_LIST",$x_string);
            }
            /**判断这个用户是否是第一笔消费，如果是第一笔消费没有被其他人推荐就给商家分成，防止事物的加锁问题，要进行数据的分解判断*/
            $inter_res_shop = 0;
            if($member['is_recommend'] == 0 && $member['recommend'] == 0 && $member['is_floor'] == $shop_id){
                $is_first_order['m_id'] = $log_res['m_id'];
                $count = M("Order")->where($is_first_order)->count();
                if($count<=0){
                    /**添加商家的推荐人的推荐钱数,暂时写成1*/
                    $inter_res_shop = 1;
                    /**用户的推荐状态改变成1*/
                    $inter_data_member_id['is_recommend'] = 1;
                    M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($inter_data_member_id);
                }
            }



            $data_order['c_m_id'] = $log_res['c_m_id'];
            $data_order['coupon_type'] = $log_res['coupon_type'];
            $data_order['coupon_money'] = $log_res['coupon_money'];
            $data_order['pay_money'] = $log_res['pay_money'];
            $data_order['order_sn'] = date('YmdHis').mt_rand(100000,999999);
            $data_order['shop_id'] = $log_res['shop_id'];
            $data_order['m_id'] = $log_res['m_id'];
            $data_order['total_price'] = $log_res['price'];
            $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
            $data_order['price'] = sprintf("%.2f",$commission*$log_res['price']);
            $data_order['other_price'] = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$log_res['price']);
            $data_order['province'] = $shop_res['province'];
            $data_order['city'] = $shop_res['city'];
            $data_order['area'] = $shop_res['area'];
            $data_order['ip'] = get_client_ip();
            $data_order['pay_type'] = 2;
            $data_order['ctime'] = time();
            $res_order = M("Order")->add($data_order);
            /**
             * 判断这个用户是否是被别人推荐的，如果是别人推荐的，当产生第一笔消费的时候，用户将或者一定的麦穗数作为报酬
             * 防止商家和用户同样获取金额，所以同步计算
             */
            $inter_price_member = 0;
            $inter_recommend_member = 0;
            $inter_price = M("Config")->getField("inter");
            if(!empty($member['recommend']) && $member['mem_recommend'] !=1){
                $is_first_order_member['m_id'] = $log_res['m_id'];
                $count_member = M("Order")->where($is_first_order_member)->count();
                //file_put_contents('2.txt',$count_member);
                if($count_member<=1){
                    /**添加用户的推荐人的推荐钱数*/
                    $inter_recommend_member = $member['recommend'];
                    $inter_price_member = $inter_price;
                    /**用户的推荐状态改变成1*/
                    $inter_data_member_recommend['mem_recommend'] = 1;
                    M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($inter_data_member_recommend);
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
            /**计算当天用户的累计消费是否超过设定的值*/
            $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
            $where_order['m_id'] = $log_res['m_id'];
            $where_order['o_id'] = array('neq',$res_order);
            $where_order['ctime'] = array(array('EGT',$begin),array('ELT',$end),'and');
            $order_max_price = M("Order")->where($where_order)->sum("price");

            /**如果当前的消费的钱数+该笔订单的和>最大的值然后就用最大的值-当前的消费额*/
            $max_orderSum_logPrice = floatval($order_max_price)+floatval($log_res['price']);

            /**添加用户的消费的求和*/
            if($max_orderSum_logPrice<$shop_res['max_price']){
                $total_data['total'] = floatval($log_res['price'])+floatval($member['total']);
            }else{
                /**当已经超过了限额，那么就要计算一下应该加的钱数*/
                $last_price = floatval($shop_res['max_price'])-floatval($order_max_price);
                $total_data['total'] = floatval($last_price)+floatval($member['total']);
            }

            $total_res = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($total_data);
            /**添加商家的提成的比例的和,商家的要减去费用也就是提点的费用*/
            $shop_total = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$log_res['price']);
            $total_data_shop['total'] = floatval($shop_total)+floatval($shop_res['total']);
            $total_data_shop['utime'] = time();
            $total_res_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($total_data_shop);
            /**添加公司的收入平台费*/
            $price_data_x['price'] = sprintf("%.2f",($shop_res['scale_p']/100)*$log_res['price']);
            $price_data_x['shop_id'] = $log_res['shop_id'];
            $price_data_x['province'] = $shop_res['province'];
            $price_data_x['city'] = $shop_res['city'];
            $price_data_x['area'] = $shop_res['area'];
            $price_data_x['ctime'] = time();
            $price = M("Price")->add($price_data_x);
            /**添加资金池*/
            $pool_data['price'] = sprintf("%.2f",($shop_res['scale_member']/100)*$log_res['price']);
            $pool_data['shop_id'] = $log_res['shop_id'];
            $pool_data['m_id'] = $log_res['m_id'];
            $pool_data['province'] = $shop_res['province'];
            $pool_data['city'] = $shop_res['city'];
            $pool_data['area'] = $shop_res['area'];
            $pool_data['ctime'] = time();
            $pool = M("Pool")->add($pool_data);
            /**给商家加钱，需要判断商家是否需要加钱(推荐的用户加的钱数)*/
            if($inter_res_shop > 0){
                /**如果这个用户是这个商家推荐的，那么这个用户消费就会给推荐的商家一笔提成的费用*/
                $t_member_add_shop_bl = $shop_res['scale_shop']/1000;
                /**获取分成钱数*/
                $price_tc_member = sprintf("%.2f",$t_member_add_shop_bl*$log_res['price']);
                $shop_data['wallet'] = floatval($shop_res['wallet'])+floatval(sprintf("%.2f",$commission*$log_res['price']))+floatval($price_tc_member);
                $wallet_shop_res = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($shop_data);
                /**给推荐的用户的商家添加账单明细和信息*/
                $this->addBill($member['is_floor'],$member['is_floor'],'推荐用户获取分成','推荐用户获取分成',$price_tc_member,0,0,2,$member['nick_name'],
                    2,0,$log_res['m_id'],1,$price_tc_member);
                $this->addMessage($member['is_floor'],"推荐用户获取分成！",
                    date('Y-m-d-H:i:s',time())."收到用户：".$member['nick_name'].","."推荐分成：$price_tc_member"."元！",
                    '1',$price_tc_member);
            }else{
                $shop_data['wallet'] = floatval($shop_res['wallet'])+floatval(sprintf("%.2f",$commission*$log_res['price']));
                $wallet_shop_res = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($shop_data);
                /**如果这个用户是这个商家推荐的，那么这个用户消费就会给推荐的商家一笔提成的费用*/
                $tc_shop_wallet_scale_shop = M("Shop")->where(array('shop_id'=>$member['is_floor']))->limit(1)->field('wallet,scale_shop')->find();
                /**获取分成比例*/
                $t_member_add_shop_bl = $tc_shop_wallet_scale_shop['scale_shop']/1000;
                /**获取分成钱数*/
                $price_tc_member = sprintf("%.2f",$t_member_add_shop_bl*$log_res['price']);
                $tc_member['wallet'] = floatval($tc_shop_wallet_scale_shop['wallet'])+floatval($price_tc_member);
                $tc_member['utime'] = time()+11;
                $tc_res = M("Shop")->where(array('shop_id'=>$member['is_floor']))->limit(1)->save($tc_member);
                if($tc_res){
                    $this->addBill($member['is_floor'],$member['is_floor'],'推荐用户获取分成','推荐用户获取分成',$price_tc_member,0,0,2,$member['nick_name'],
                        2,0,$log_res['m_id'],1,$price_tc_member);
                    $this->addMessage($member['is_floor'],"推荐用户获取分成！",
                        date('Y-m-d-H:i:s',time())."收到用户：".$member['nick_name'].","."推荐分成：$price_tc_member"."元！",
                        '1',$price_tc_member);
                }
            }
            /**给商家添加账单*/
            $member_pay = $data_order['price'];
            $other_price = $data_order['other_price'];
            $shop_mess_price = $log_res['price'];
            /**给商家添加消息*/
            $message_res_j = $this->addMessage($log_res['shop_id'],"用户买单记录！",
                date('Y-m-d-H:i:s',time())."收到用户：".$member['nick_name']."使用微信支付了：$shop_mess_price"."元！，运营费用：".$other_price."元",
                '0','1',$shop_mess_price,$log_res['m_id']);
            /**给用户添加账单*/
            $m_bill = $this->addBill($log_res['m_id'],$log_res['shop_id'],"买单消费！",
                date('Y-m-d-H:i:s',time())."使用微信支付给账号为：".$shop_res['account']."商家名称为：".$shop_res['name']."金额为：".$log_res['price']."元",
                $log_res['price'],0,'1',2,$shop_res['name'],3,1,$log_res['shop_id'],0,$log_res['price'],0,$res_order);
            /**给商家添加账单*/
            $j_bill = $this->addBill($log_res['shop_id'],0,"用户买单记录！",
                date('Y-m-d-H:i:s',time())."收到用户：".$member['nick_name']."使用微信支付了：$member_pay"."元！，运营费用：".$other_price."元",
                $member_pay,$other_price,'0',2,$member['nick_name'],3,0,$log_res['m_id'],1,$log_res['price'],$m_bill,$res_order);
            /**给用户发消息推送买单的信息*/
            $message_res_m = $this->addMessage($log_res['m_id'],"买单消费！",
                date('Y-m-d-H:i:s',time())."使用微信支付给账号为：".$shop_res['account']."商家名称为：".$shop_res['name'].'金额为'.$log_res['price']."元",
                '0','0',$log_res['price'],$log_res['shop_id']);

            /**查看符合标注的购买股份的人员的最低值*/
            $meet_pay_price = M("Config")->getField("meet_pay_price");
            if($max_orderSum_logPrice<$shop_res['max_price']){
                if((floatval($log_res['price'])+floatval($member['integral']))>=$meet_pay_price){
                    /**满足就添加一股*/
                    $a = (floatval($log_res['price'])+floatval($member['integral']))/$meet_pay_price;
                    if(floor($a)>$member['piles']){
                        /**添加用户的股数*/
                        $member_pie_x = floor($a)-$member['piles'];
                        for ($i=1;$i<=floor($member_pie_x);$i++){
                            $pie_data['mix_id'] = $log_res['m_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 0;
                            M("Pie")->add($pie_data);
                        }
                    }
                    $after_data['piles'] = floor($a);
                    $after_data['integral'] = floatval($member['integral'])+floatval($log_res['price']);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                }else{
                    $after_data['integral'] = floatval($member['integral'])+floatval($log_res['price']);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                }
            }else{
                /**当已经超过了限额，那么就要计算一下应该加的钱数*/
                $last_price = floatval($shop_res['max_price'])-floatval($order_max_price);
                if((floatval($last_price)+floatval($member['integral']))>=$meet_pay_price){
                    /**满足就添加一股*/
                    $a = (floatval($last_price)+floatval($member['integral']))/$meet_pay_price;
                    if(floor($a)>$member['piles']){
                        /**添加用户的股数*/
                        $member_pie_x = floor($a)-$member['piles'];
                        for ($i=1;$i<=floor($member_pie_x);$i++){
                            $pie_data['mix_id'] = $log_res['m_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 0;
                            M("Pie")->add($pie_data);
                        }
                    }
                    $after_data['piles'] = floor($a);
                    $after_data['integral'] = floatval($member['integral'])+floatval($last_price);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                }else{
                    $after_data['integral'] = floatval($member['integral'])+floatval($last_price);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                }
            }

            $shop_price_earn = sprintf("%.2f",(($shop_res['deduct'])/100)*$log_res['price']);
            if($shop_res['deduct']<=0){
                $invest_res_trans_shop = 1;
            }else{
                if((floatval($shop_price_earn)+floatval($shop_res['integral']))>=$meet_pay_price){
                    /**满足就添加一股*/
                    $a_shop = ((floatval($shop_price_earn))+floatval($shop_res['integral']))/$meet_pay_price;
                    /**添加商家的股数*/
                    if(floor($a_shop)>$shop_res['piles']) {
                        $shop_x_pie = floor($a_shop)-$shop_res['piles'];
                        for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                            $pie_data['mix_id'] = $log_res['shop_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 1;
                            M("Pie")->add($pie_data);
                        }
                    }
                    $after_data_shop['piles'] = floor($a_shop);
                    $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($shop_price_earn);
                    $after_data_shop['utime'] = time()+2;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($after_data_shop);
                }else{
                    unset($after_data_shop);
                    $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($shop_price_earn);
                    $after_data_shop['utime'] = time()+1;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($after_data_shop);
                }
            }
            /**计算用户的钱数,由于用户可能同时推荐商家和当前消费的用户，所以要放在一步进行处理*/
            $recommend_one = 0;
            $recommend_two = 0;
            if($inter_price_member>0 && $recommend_shop_price>0){
                if($recommend_shop_recommend == $inter_recommend_member){
                   /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member)+floatval($recommend_shop_price);
                    $recommend_one = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->save($wallet_data);
                    $recommend_two = 1;
                }elseif ($recommend_shop_recommend != $inter_recommend_member){
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                    $recommend_one =M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->save($wallet_data);
                    /**找到用户的钱包的金额*/
                    $wallet_recommend_member = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                    $wallet_data_other['wallet'] = floatval($wallet_recommend_member)+floatval($inter_price_member);
                    $recommend_two =M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data_other);
                }
            }elseif ($recommend_shop_price>0){
                /**找到用户的钱包的金额*/
                $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->getField("wallet");
                $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                $recommend_one = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->save($wallet_data);
                $recommend_two = 1;
            }elseif($inter_price_member>0){
                /**找到用户的钱包的金额*/
                $wallet_recommend_member = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                $wallet_data_other['wallet'] = floatval($wallet_recommend_member)+floatval($inter_price_member);
                $recommend_one = 1;
                $recommend_two = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data_other);
            }else{
                $recommend_one = 1;
                $recommend_two = 1;
            }
//            file_put_contents('pay.txt',$wallet_shop_res.'_'.$recommend_two.'_'.$price.'_'.$pool.'_'.$j_bill.'_'.$message_res_j
//                .'_'.$m_bill.'_'.$message_res_m.'_'.$invest_res_trans.'_'.$total_res.'_'.$invest_res_trans_shop.'_'.$res_order
//                .'_'.$total_res_shop);
            if($wallet_shop_res && $recommend_one && $recommend_two && $price && $pool && $j_bill && $message_res_j && $m_bill && $message_res_m && $invest_res_trans && $res_order && $total_res && $invest_res_trans_shop && $total_res_shop&&$coupon_mem&&$coupon_log){
                /**向缓存中添加新的用户的数据*/
                $list1 = S("MEMBER_ORDER");
                $x_string = $list1?"会员".$member['nick_name']."消费了:".$log_res['price']."元".",".$list1:"".","."会员".$member['nick_name']."消费了:".$log_res['price']."元";
                S("MEMBER_ORDER",$x_string);
                M()->commit();

                echo "success";
                /**给商家发送模板消息*/
                $tem_price = $log_res['price'];
                $time = date('Y-m-d H:i:s',time());
                $nick_name = $member['nick_name'];
                $data_to = array(
                    'first'=>array('value'=>urlencode("您好,您有新的款项到账，请注意查收！")),
                    'keyword1'=>array('value'=>urlencode("$time")),
                    'keyword2'=>array('value'=>urlencode("$nick_name")),
                    'keyword3'=>array('value'=>urlencode("微信支付")),
                    'keyword4'=>array('value'=>urlencode("买单消费")),
                    'keyword5'=>array('value'=>urlencode("$tem_price 元")),
                    'Remark'=>array('value'=>urlencode("$time,用户 $nick_name 支付了 $tem_price 元")),
                );
                $url = $_SERVER['SERVER_NAME'];
                $this->wxSetSend($shop_res['openid'],"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","http://$url/index.php/Merchant/Shop/billlist/p/1/shop_id/".$log_res['shop_id'],$data_to);
                /**给APP商家端推送消息*/
                $alias = ''.$shop_id;//$shop_res['shop_id'];
                $title = '您好,您有新的款项到账，请注意查收！';
                $alert = '用户'.$nick_name.'买单支付了'.$tem_price.'元';
                $extra['order_id'] = ''.$res_order;
                $extra['mess_id'] = $message_res_j;
                $extra['b_id'] = $j_bill;
                $extra['type'] = '1';
                if($shop_res['is_set'] == 1){
                    $this->push("",$alias,$title,$alert,$extra,1);
                }
            }else{
                M()->rollback();

                $log_id_res = M("Error")->where(array('error_sn'=>$order_sn))->getField('error_id');
                if(empty($log_id_res)){
                    $error_data['error_sn'] = $order_sn;
                    $error_data['shop_id'] = $log_res['shop_id'];
                    $error_data['m_id'] = $log_res['m_id'];
                    $error_data['price'] = $log_res['price'];
                    $error_data['title'] = "支付未知错误(程序)！";
                    $error_data['ctime'] = time();
                    M("Error")->add($error_data);
                    $this->sendMsg("18522713541","订单号：".$order_sn."支付发生未知错误(程序)，请前去查看！");
                }
                echo "success";
            }

        }else{
            $log_id_res = M("Error")->where(array('error_sn'=>$order_sn))->getField('error_id');
            if(empty($log_id_res)){
                $error_data['error_sn'] = $order_sn;
                $error_data['shop_id'] = $log_res['shop_id'];
                $error_data['m_id'] = $log_res['m_id'];
                $error_data['price'] = $log_res['price'];
                $error_data['title'] = "支付未知错误（微信）！";
                $error_data['ctime'] = time();
                M("Error")->add($error_data);
                $this->sendMsg("18522713541","订单号：".$order_sn."支付发生未知错误（微信），请前去查看！");
            }
            echo "success";
        }
        echo "success";
    }


    function subStrXml($begin,$end,$str){
        $b= (strpos($str,$begin));
        $c= (strpos($str,$end));

        return substr($str,$b,$c-$b + 7);
    }




    /**
     * 环迅成功支付回调
     * @author crazy
     * @time 2017-07-03
     */
    public function callBackHx(){
        $xml = file_get_contents("php://input");//返回回复数据
        $postStr = str_replace("paymentResult=", "<?xml version='1.0' encoding='UTF-8'?>", rawurldecode($xml));
        $postArray = $this->xmlToArray($postStr);
        if($postArray['GateWayRsp']['body']['MerBillNo']){
            $order_sn = $postArray['GateWayRsp']['body']['MerBillNo'];
            /**找到消费的记录*/
            $log_res = M("Log")->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['GateWayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","订单{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['GateWayRsp']['head']['RspCode']=='000000' && $postArray['GateWayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['GateWayRsp']['body']['Amount'];
        }else{
            $order_sn = $postArray['WxPayRsp']['body']['MerBillno'];
            /**找到消费的记录*/
            $log_res = M("Log")->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['WxPayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","订单{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['WxPayRsp']['head']['RspCode']=='000000' && $postArray['WxPayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['WxPayRsp']['body']['OrdAmt'];
        }
        if($log_res['status'] == 1){
            echo "ipscheckok";
            exit();
        }
        M("Log")->where(array('order_sn'=>$order_sn))->limit(1)->setField('status',1);
        if($is_true){
            /**缺少判断钱数和当前数据的钱数是否相同，如果不相同就存到error里面，然后发信息*/
            if($log_res['pay_money']!= $pay_price){
                $error_data_query = array(
                    'error_sn'      =>$order_sn,
                    'shop_id'       =>$log_res['shop_id'],
                    'm_id'          =>$log_res['m_id'],
                    'price'         =>$log_res['pay_money']?$log_res['pay_money']:0,
                    'title'         =>"微信支付查询和用户提交的订单不符合!",
                    'ctime'         =>time()
                );
                M("Error")->add($error_data_query);
                M("Error")->commit();
                $this->sendMsg("18522713541","微信支付查询和用户提交的订单不符合!" );
                exit();
            }
            M()->startTrans();
            $coupon_mem = 1;
            $coupon_log = 1;
            if($log_res['c_m_id']){
                $coupon_mem = M('CouponMember')->where(array('c_m_id'=>$log_res['c_m_id']))->limit(1)->save(array('status'=>1));
                $data_log = array(
                    'm_id'=>$log_res['m_id'],
                    'c_m_id'=>$log_res['c_m_id'],
                    'ctime'=>time()
                );
                $coupon_log = M('CouponLog')->add($data_log);
            }
            /**通过商家id获取商家的省市区的id*/
            $shop_id = $log_res['shop_id'];
            $shop_res = M("Shop")->where(array('shop_id'=>$shop_id))->field("account,deduct,max_price,is_set,name,province,city,
            area,scale_p,scale_member,scale_shop,wallet,recommend,is_recommend,earn_total,total,
            enter_price,integral,piles,openid")->find();
            /**
             * 给推荐商家的而用户加麦穗
             * 防止用户推荐商家和推荐用户一起累加金额，事物开启之后，锁了当前的用户的记录行，所以不能修改用户的钱包的数据
             */
            $recommend_shop_price = 0;
            $recommend_shop_recommend = 0;
            if($shop_res['is_recommend'] == 0 && $shop_res['recommend'] != 0){
                $recommend_shop_price = M("Config")->getField("mem_shop_inter");
                $tj_shop_member = M("Member")->where(array("m_id"=>$shop_res['recommend']))->limit(1)->field("m_id,wallet")->find();
                if($tj_shop_member){
                    /**添加用户的推荐人的推荐钱数*/
                    $recommend_shop_recommend = $shop_res['recommend'];
                    /**商家的推荐状态改变成1*/
                    M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->setField('is_recommend',1);
                    if($recommend_shop_price){
                        /**给用户添加账单*/
                        $this->addBill($shop_res['recommend'],$shop_id,"推荐商家的收益！",
                            date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$recommend_shop_price."众享豆",
                            $recommend_shop_price,'0','0',2,$shop_res['name'],2,1,$shop_id,0,0);
                        /**给用户发消息推送推荐收益的信息*/
                        $this->addMessage($shop_res['recommend'],"推荐商家的收益！",
                            date('Y-m-d-H:i:s',time())."推荐商家".$shop_res['name']."产生消费获取了".$recommend_shop_price."众享豆",
                            '0',$recommend_shop_price);
                    }
                }
            }
            /**找到用户*/
            $member = M("Member")->where(array('m_id'=>$log_res['m_id']))->field('nick_name,m_id,wallet,earn_total,total,is_add,recommend,mem_recommend,is_recommend,is_floor,integral,piles')->limit(1)->find();
            $count = M("Order")->where(array("m_id"=>$log_res['m_id']))->count();
            if($count<=0){
                /**向缓存中添加新的用户的数据*/
                $list1 = S("MEMBER_LIST");
                $x_string = $list1?"欢迎会员".$member['nick_name'].",".$list1:"".","."欢迎会员".$member['nick_name'];
                S("MEMBER_LIST",$x_string);
            }

            /**计算商家的运营费用之外商家应该得到的比例的钱数*/
            $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
            /**判断商家的额度*/
            if($shop_res['enter_price'] >= $log_res['price']){
                /**计算商家应该得的钱数*/
                $price_commission = $log_res['price'];
                /**运营费用，因为商家有额度所以不扣除运营费用*/
                $price_commission_y = 0.00;
                $price_commission_y_m =  0;
            }elseif ($shop_res['enter_price'] == 0.00 || $shop_res['enter_price'] == 0){
                /**计算商家应该得的钱数*/
                $price_commission = sprintf("%.2f",$commission*$log_res['price']);
                /**运营费用*/
                $price_commission_y = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$log_res['price']);
                $price_commission_y_m =  sprintf("%.2f",(($shop_res['deduct'])/100)*$log_res['price']);
            }else{
                /**计算商家应该得的钱数,只有一部分是扣运营费用的，一部分不扣除*/
                $c_y_price = floatval($log_res['price'])-floatval($shop_res['enter_price']);
                $price_commission = floatval($log_res['price'])-floatval(sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$c_y_price));
                /**运营费用*/
                $price_commission_y = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$c_y_price);
                $price_commission_y_m =  sprintf("%.2f",(($shop_res['deduct'])/100)*$c_y_price);
            }

            /**添加订单*/
            $data_order = array(
                'c_m_id'        =>$log_res['c_m_id']?$log_res['c_m_id']:0,
                'coupon_type'   =>$log_res['coupon_type']?$log_res['coupon_type']:0,
                'coupon_money'  =>$log_res['coupon_money']?$log_res['coupon_money']:0.00,
                'pay_money'     =>$log_res['pay_money']?$log_res['pay_money']:0.00,
                'order_sn'      =>date('YmdHis').mt_rand(100000,999999),
                'shop_id'       =>$log_res['shop_id'],
                'm_id'          =>$log_res['m_id'],
                'total_price'   =>$log_res['price'],
                'price'         =>$price_commission?$price_commission:0.00,
                'other_price'   =>$price_commission_y?$price_commission_y:0.00,
                'province'      =>$shop_res['province']?$shop_res['province']:0,
                'city'          =>$shop_res['city']?$shop_res['city']:0,
                'area'          =>$shop_res['area']?$shop_res['area']:0,
                'ip'            =>get_client_ip(),
                'pay_type'      =>2,
                'ctime'         =>time()
            );
            $res_order = M("Order")->add($data_order);
            /**
             * 判断这个用户是否是被别人推荐的，如果是别人推荐的，当产生第一笔消费的时候，用户将或者一定的麦穗数作为报酬
             * 防止商家和用户同样获取金额，所以同步计算
             */
            $inter_price_member = 0;
            $inter_recommend_member = 0;
            $inter_price = M("Config")->getField("inter");
            if(!empty($member['recommend']) && $member['mem_recommend'] !=1){
                $is_first_order_member['m_id'] = $log_res['m_id'];
                $count_member = M("Order")->where($is_first_order_member)->count();
                if($count_member<=1){
                    /**添加用户的推荐人的推荐钱数*/
                    $inter_recommend_member = $member['recommend'];
                    $inter_price_member = $inter_price;
                    /**用户的推荐状态改变成1*/
                    M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save('mem_recommend',1);
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
            /**计算当天用户的累计消费是否超过设定的值*/
            $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
            $where_order = array(
                'm_id'  =>$log_res['m_id'],
                'shop_id'  =>$log_res['shop_id'],
                'o_id'  =>array('neq',$res_order),
                'ctime' =>array(array('EGT',$begin),array('ELT',$end),'and'),
                'pay_type'=>array('neq',0)
            );
            $order_max_price = M("Order")->where($where_order)->sum("total_price");

            /**如果当前的消费的钱数+该笔订单的和>最大的值然后就用最大的值-当前的消费额*/
            $max_orderSum_logPrice = floatval($order_max_price)+floatval($log_res['price']);

            /**添加用户的消费的求和*/
            if($max_orderSum_logPrice<$shop_res['max_price']){
                $total_data['total'] = floatval($log_res['price'])+floatval($member['total']);
            }else{
                if($shop_res['max_price']>$order_max_price){
                    /**当已经超过了限额，那么就要计算一下应该加的钱数*/
                    $last_price = floatval($shop_res['max_price'])-floatval($order_max_price);
                    $total_data['total'] = floatval($last_price)+floatval($member['total']);
                }else{
                    $total_data['utime'] = time()+12;
                }
            }
            $total_res = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($total_data);
            /**添加商家的提成的比例的和,商家的要减去费用也就是提点的费用，总消费*/
            if($shop_res['deduct'] == 0 || $shop_res['deduct'] == 0.00){
                $total_res_shop = 1;
            }else{
                $shop_total = sprintf("%.2f",(($shop_res['deduct'])/100)*$log_res['price']);
                $total_data_shop = array(
                    'total'=> floatval($shop_total)+floatval($shop_res['total']),
                    'utime'=>time()
                );
                $total_res_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($total_data_shop);
            }

            /**添加公司的收入平台费*/
            $price_data_x = array(
                'price'=> sprintf("%.2f",($shop_res['scale_p']/100)*$log_res['price']),
                'shop_id'=> $shop_id,
                'm_id'=> $log_res['m_id'],
                'province'=>$shop_res['province']?$shop_res['province']:0,
                'city'=>$shop_res['city']?$shop_res['city']:0,
                'area'=>$shop_res['area']?$shop_res['area']:0,
                'ctime'=>time()
            );
            $price = M("Price")->add($price_data_x);
            /**添加资金池*/
            $pool_data = array(
                'price'     => sprintf("%.2f",($shop_res['scale_member']/100)*$log_res['price']),
                'shop_id'   => $log_res['shop_id'],
                'm_id'      => $log_res['m_id'],
                'province'  => $shop_res['province']?$shop_res['province']:0,
                'city'      => $shop_res['city']?$shop_res['city']:0,
                'area'      => $shop_res['area']?$shop_res['area']:0,
                'ctime'     => time()
            );
            $pool = M("Pool")->add($pool_data);
            /**减少商家的入驻费用
             * 1、如果大于当前订单的钱数就一直减少这个额度
             * 2、等于0的时候就是是0
             * 3、当小于当前就直接变成0
             */
            if($shop_res['enter_price'] >= $log_res['price']){
                $shop_data['enter_price'] = floatval($shop_res['enter_price'])-floatval($log_res['price']);
            }elseif ($shop_res['enter_price'] == 0.00 || $shop_res['enter_price'] == 0){
                $shop_data['enter_price'] = 0.00;
            }else{
                $shop_data['enter_price'] = 0.00;
            }
            /**给商家加钱*/
            $shop_data['wallet'] = floatval($shop_res['wallet'])+$price_commission;
            $wallet_shop_res = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($shop_data);
            /**给商家添加账单*/
            $member_pay = $data_order['price'];
            $other_price = $data_order['other_price'];
            $shop_mess_price = $log_res['price'];
            $deduction = 0;
            if($log_res['is_wallet'] == 1) {
                $deduction = $log_res['deduct_price'];
            }
            /**给商家添加消息*/
            $message_res_j = $this->addMessage($log_res['shop_id'],"用户买单记录！",
                date('Y-m-d-H:i:s',time())."收到用户：".$member['nick_name'].","."使用微信支付了：$shop_mess_price"."元！，运营费用：".$other_price."元",
                '1',$shop_mess_price,$log_res['m_id'],0,1);
            /**给用户添加账单*/
            $m_bill = $this->addBill($log_res['m_id'],$log_res['shop_id'],"买单消费！",
                date('Y-m-d-H:i:s',time())."使用微信支付给账号为：".$shop_res['account']."商家名称为：".$shop_res['name'].','.$log_res['price']."元",
                $log_res['price'],0,'1',2,$shop_res['name'],3,1,$log_res['shop_id'],0,$log_res['price'],0,$res_order);
            /**给商家添加账单*/
            $j_bill = $this->addBill($log_res['shop_id'],0,"用户买单记录！",
                date('Y-m-d-H:i:s',time())."收到用户：".$member['nick_name'].","."使用微信支付了：$member_pay"."元！，运营费用：".$other_price."元",
                $member_pay,$other_price,'0',2,$member['nick_name'],3,0,$log_res['m_id'],1,$log_res['price'],$m_bill,$res_order,$deduction);
            /**给用户发消息推送买单的信息*/
            $message_res_m = $this->addMessage($log_res['m_id'],"买单消费！",
                date('Y-m-d-H:i:s',time())."使用微信支付给账号为：".$shop_res['account']."商家名称为：".$shop_res['name'].','.$log_res['price']."元",
                '0',$log_res['price'],$log_res['shop_id'],0,1);

            /**查看符合标注的购买股份的人员的最低值*/
            $meet_pay_price = M("Config")->getField("meet_pay_price");
            /**判断用户是否使用豆抵扣*/
            if($log_res['shop_id'] != 1){
                $real_add_price = $log_res['pay_money'];
                if($max_orderSum_logPrice<=$shop_res['max_price']){
                    if((floatval($real_add_price)+floatval($member['integral']))>=$meet_pay_price){
                        /**满足就添加一股*/
                        $a = (floatval($real_add_price)+floatval($member['integral']))/$meet_pay_price;
                        if(floor($a)>$member['piles']){
                            /**添加用户的股数*/
                            $member_pie_x = floor($a)-$member['piles'];
                            for ($i=1;$i<=floor($member_pie_x);$i++){
                                $pie_data['mix_id'] = $log_res['m_id'];
                                $pie_data['pie'] = 1;
                                $pie_data['ctime'] = time();
                                $pie_data['type'] = 0;
                                M("Pie")->add($pie_data);
                            }
                        }
                        $after_data['piles'] = floor($a);
                        $after_data['integral'] = floatval($member['integral'])+floatval($real_add_price);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                    }else{
                        $after_data['integral'] = floatval($member['integral'])+floatval($real_add_price);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                    }
                }else{
                    if($shop_res['max_price']>$order_max_price){
                        /**当已经超过了限额，那么就要计算一下应该加的钱数*/
                        $last_price = floatval($shop_res['max_price'])-floatval($order_max_price);
                        if($real_add_price < $last_price){
                            $last_price = floatval($last_price)-floatval($real_add_price);
                        }
                        if((floatval($last_price)+floatval($member['integral']))>=$meet_pay_price){
                            /**满足就添加一股*/
                            $a = (floatval($last_price)+floatval($member['integral']))/$meet_pay_price;
                            if(floor($a)>$member['piles']){
                                /**添加用户的股数*/
                                $member_pie_x = floor($a)-$member['piles'];
                                for ($i=1;$i<=floor($member_pie_x);$i++){
                                    $pie_data['mix_id'] = $log_res['m_id'];
                                    $pie_data['pie'] = 1;
                                    $pie_data['ctime'] = time();
                                    $pie_data['type'] = 0;
                                    M("Pie")->add($pie_data);
                                }
                            }
                            $after_data['piles'] = floor($a);
                            $after_data['integral'] = floatval($member['integral'])+floatval($last_price);
                            $after_data['utime'] = time();
                            $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                        }else{
                            $after_data['integral'] = floatval($member['integral'])+floatval($last_price);
                            $after_data['utime'] = time();
                            $invest_res_trans = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($after_data);
                        }
                    }else{
                        $invest_res_trans = 1;
                    }
                }
            }else{
                $invest_res_trans = 1;
            }
            $shop_price_earn = sprintf("%.2f",(($shop_res['deduct'])/100)*$log_res['price']);
            if($shop_res['deduct'] == 0 || $shop_res['deduct'] == 0.00){
                $invest_res_trans_shop = 1;
            }else{
                /**加上商家的运营费的比例，额度够的时候，这个不能给商家添加麦穗*/
//                $price_commission_y_m = sprintf("%.2f",(($shop_res['deduct'])/100)*$price_commission_y_x);
                if($shop_res['enter_price'] > 0){
                    if((floatval($price_commission_y_m)+floatval($shop_res['integral']))>=$meet_pay_price){
                        /**满足就添加一股*/
                        $a_shop = ((floatval($price_commission_y_m))+floatval($shop_res['integral']))/$meet_pay_price;
                        /**添加商家的股数*/
                        if(floor($a_shop)>$shop_res['piles']) {
                            $shop_x_pie = floor($a_shop)-$shop_res['piles'];
                            for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                                $pie_data['mix_id'] = $log_res['shop_id'];
                                $pie_data['pie'] = 1;
                                $pie_data['ctime'] = time();
                                $pie_data['type'] = 1;
                                M("Pie")->add($pie_data);
                            }
                        }
                        $after_data_shop['piles'] = floor($a_shop);
                        $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($price_commission_y_m);
                        $after_data_shop['utime'] = time()+2;
                        $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($after_data_shop);
                    }else{
                        unset($after_data_shop);
                        $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($price_commission_y_m);
                        $after_data_shop['utime'] = time()+1;
                        $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($after_data_shop);
                    }
                }else{
                    /**这个是根据后台设置的返麦穗的比例进行设置的*/
                    if((floatval($shop_price_earn)+floatval($shop_res['integral']))>=$meet_pay_price){
                        /**满足就添加一股*/
                        $a_shop = ((floatval($shop_price_earn))+floatval($shop_res['integral']))/$meet_pay_price;
                        /**添加商家的股数*/
                        if(floor($a_shop)>$shop_res['piles']) {
                            $shop_x_pie = floor($a_shop)-$shop_res['piles'];
                            for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                                $pie_data['mix_id'] = $log_res['shop_id'];
                                $pie_data['pie'] = 1;
                                $pie_data['ctime'] = time();
                                $pie_data['type'] = 1;
                                M("Pie")->add($pie_data);
                            }
                        }
                        $after_data_shop['piles'] = floor($a_shop);
                        $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($shop_price_earn);
                        $after_data_shop['utime'] = time()+2;
                        $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($after_data_shop);
                    }else{
                        unset($after_data_shop);
                        $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($shop_price_earn);
                        $after_data_shop['utime'] = time()+1;
                        $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->save($after_data_shop);
                    }
                }

            }

            /**计算用户的钱数,由于用户可能同时推荐商家和当前消费的用户，所以要放在一步进行处理*/
            $recommend_one = 1;
            $recommend_two = 1;
            $recommend_three = 1;
            if($inter_price_member>0 && $recommend_shop_price>0){
                if($recommend_shop_recommend == $inter_recommend_member){
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($inter_price_member)+floatval($recommend_shop_price);
                    $recommend_one = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->save($wallet_data);
                    /**判断是否支付豆*/
                    if($log_res['is_wallet'] == 1){
                        $wallet_d_member = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->getField("wallet");
                        $wallet_d_other['wallet'] = floatval($wallet_d_member)-floatval($log_res['deduct_price']);
                        $wallet_d_other['utime'] = time()+100;
                        $recommend_three =  M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($wallet_d_other);
                    }
                }elseif ($recommend_shop_recommend != $inter_recommend_member){
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->getField("wallet");
                    /**判断用户是否使用豆抵扣*/
                    if($log_res['is_wallet'] == 1 && $recommend_shop_recommend == $log_res['m_id']){
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price)-floatval($log_res['deduct_price']);
                    }elseif ($log_res['is_wallet'] == 1 && $recommend_shop_recommend != $log_res['m_id'] ){
                        $wallet_d_member = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->getField("wallet");
                        $wallet_d_other['wallet'] = floatval($wallet_d_member)-floatval($log_res['deduct_price']);
                        $wallet_d_other['utime'] = time()+100;
                        $recommend_three =  M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($wallet_d_other);
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                    }else{
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                    }
                    $recommend_one =M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->save($wallet_data);
                    /**找到用户的钱包的金额*/
                    $wallet_recommend_member = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                    $wallet_data_other['wallet'] = floatval($wallet_recommend_member)+floatval($inter_price_member);
                    $recommend_two =M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data_other);
                }
            }elseif ($recommend_shop_price>0){
                /**找到用户的钱包的金额*/
                $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->getField("wallet");
                /**判断用户是否使用豆抵扣*/
                if($log_res['is_wallet'] == 1 && $recommend_shop_recommend == $log_res['m_id']){
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price)-floatval($log_res['deduct_price']);
                }elseif ($log_res['is_wallet'] == 1 && $recommend_shop_recommend != $log_res['m_id'] ){
                    $wallet_d_member = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->getField("wallet");
                    $wallet_d_other['wallet'] = floatval($wallet_d_member)-floatval($log_res['deduct_price']);
                    $wallet_d_other['utime'] = time()+100;
                    $recommend_three =  M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($wallet_d_other);
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                }else{
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                }
                $recommend_one = M("Member")->where(array('m_id'=>$recommend_shop_recommend))->limit(1)->save($wallet_data);
            }elseif($inter_price_member>0){
                /**找到用户的钱包的金额*/
                $wallet_recommend_member = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->getField("wallet");
                $wallet_data_other['wallet'] = floatval($wallet_recommend_member)+floatval($inter_price_member);
                $recommend_two = M("Member")->where(array('m_id'=>$inter_recommend_member))->limit(1)->save($wallet_data_other);
                /**判断是否支付豆*/
                if($log_res['is_wallet'] == 1){
                    $wallet_d_member = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->getField("wallet");
                    $wallet_d_other['wallet'] = floatval($wallet_d_member)-floatval($log_res['deduct_price']);
                    $wallet_d_other['utime'] = time()+100;
                    $recommend_three =  M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($wallet_d_other);
                }
            }else{
                if($log_res['is_wallet'] == 1){
                    $wallet_recommend_member = M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->getField("wallet");
                    $wallet_data_other['wallet'] = floatval($wallet_recommend_member)-floatval($log_res['deduct_price']);
                    $wallet_data_other['utime'] = time()+100;
                    $recommend_three =  M("Member")->where(array('m_id'=>$log_res['m_id']))->limit(1)->save($wallet_data_other);
                }
            }

            if($recommend_three&&$wallet_shop_res && $recommend_one && $recommend_two && $price && $pool &&
                $j_bill && $message_res_j && $m_bill && $message_res_m && $invest_res_trans && $res_order &&
                $total_res && $invest_res_trans_shop && $total_res_shop&&$coupon_mem&&$coupon_log){
                /**向缓存中添加新的用户的数据*/
                $list1 = S("MEMBER_ORDER");
                $x_string = $list1?"会员".$member['nick_name']."消费了:".$log_res['price']."元".",".$list1:"".","."会员".$member['nick_name']."消费了:".$log_res['price']."元";
                S("MEMBER_ORDER",$x_string);
                M()->commit();
                /**商家销量加1*/
                M("Shop")->where(array('shop_id'=>$log_res['shop_id']))->limit(1)->setInc('sale',1);
                /**给商家发送模板消息*/
                $tem_price = $log_res['price'];
                $time = date('Y-m-d H:i:s',time());
                $nick_name = $member['nick_name'];
                $data_to = array(
                    'first'=>array('value'=>urlencode("您好,您有新的款项到账，请注意查收！")),
                    'keyword1'=>array('value'=>urlencode("$time")),
                    'keyword2'=>array('value'=>urlencode("$nick_name")),
                    'keyword3'=>array('value'=>urlencode("微信支付")),
                    'keyword4'=>array('value'=>urlencode("买单消费")),
                    'keyword5'=>array('value'=>urlencode("$tem_price 元")),
                    'Remark'=>array('value'=>urlencode("$time,用户 $nick_name 支付了 $tem_price 元")),
                );
                $url = $_SERVER['SERVER_NAME'];
                $this->wxSetSend($shop_res['openid'],"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","http://$url/index.php/Merchant/Shop/billlist/p/1/shop_id/".$log_res['shop_id'],$data_to);

                /**给APP商家端推送消息*/
                $alias = ''.$shop_id;//$shop_res['shop_id'];
                $title = '您好,您有新的款项到账，请注意查收！';
                $alert = '用户'.$nick_name.'买单支付了'.$tem_price.'元';
                $extra['order_id'] = ''.$res_order;
                $extra['mess_id'] = $message_res_j;
                $extra['b_id'] = $j_bill;
                $extra['type'] = '1';
                try{
                    if($shop_res['is_set'] == 1){
                        $this->push("",$alias,$title,$alert,$extra,1);
                    }
                }catch (\Exception $e){
                    echo "ipscheckok";
                }
            }else{
                M()->rollback();
                $log_id_res = M("Error")->where(array('error_sn'=>$order_sn))->getField('error_id');
                if(empty($log_id_res)){
                    $error_data['error_sn'] = $order_sn;
                    $error_data['shop_id'] = $log_res['shop_id'];
                    $error_data['m_id'] = $log_res['m_id'];
                    $error_data['price'] = $log_res['price'];
                    $error_data['title'] = "支付未知错误(程序)！";
                    $error_data['ctime'] = time();
                    $error_data['list'] =            $recommend_three.'_'.$wallet_shop_res.'_'.$recommend_one.'_'.$recommend_two.'_'.$price.'_'.$pool.'_'.$j_bill.'_'.$message_res_j
                        .'_'.$m_bill.'_'.$message_res_m.'_'.$invest_res_trans.'_'.$res_order.'_'.$total_res.'_'.$invest_res_trans_shop.'_'.$res_order
                        .'_'.$total_res_shop.'_'.$coupon_mem.'_'.$coupon_log;
                    M("Error")->add($error_data);
                    $this->sendMsg("18522713541","订单号：".$order_sn."支付发生未知错误(程序)，请前去查看！");
                }
            }
            echo "ipscheckok";
        }else{
            $log_id_res = M("Error")->where(array('error_sn'=>$order_sn))->getField('error_id');
            if(empty($log_id_res)){
                $error_data['error_sn'] = $order_sn;
                $error_data['shop_id'] = $log_res['shop_id'];
                $error_data['m_id'] = $log_res['m_id'];
                $error_data['price'] = $log_res['price'];
                $error_data['title'] = "支付未知错误（微信）！";
                $error_data['ctime'] = time();
                M("Error")->add($error_data);
                $this->sendMsg("18522713541","订单号：".$order_sn."支付发生未知错误（微信），请前去查看！");
            }
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

    /**
     * 环迅支付验签
     * @time 2017-10-19
     * @author crazy
     * @param 加密的数组数据
     */
    public function md5Param($para_temp){
        $customerCode = "202195";
        $merCret = "DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe";
        $signature = md5($para_temp.$customerCode.$merCret);
        return $signature;
    }


    /**
     * [alipay 调用支付支付宝订单]
     * 传参方式：post
     * @time 2017-08-08
     * @author crazy
     * @param $order_sn  订单的编号
     */
    public function alipay()
    {
        Vendor('Alipay.Alipay');
        //查询订单信息
        $order_sn = I('post.order_sn');
        $goods_name = I('post.goods_name');
        $recharge_info = M('integral_order')->where(array('order_sn' => $order_sn))->find();
        if( !$recharge_info ){
            apiResponse('error', '订单异常！');
        }
        //生成支付字符串
        $notify_url = getServerUrl() . '/index.php/Api/Pay/alipayNotify';
        $out_trade_no = $recharge_info['order_sn'];
        $total_amount = $recharge_info['price'];
        $signType = 'RSA2';
        $payObject = new \Alipay($notify_url, $out_trade_no, $total_amount, $signType,$goods_name,$goods_name);
        $pay_string = $payObject->appPay();
        return $pay_string;
    }


    /**
     * 支付宝购买商品的回调地址
     * @time 2017-08-08
     * @author crazy
    */
    public function alipayNotify(){
        Vendor('Alipay.Notify');
        $notify = new \Notify();
        if( $notify->rsaCheck() ){
            $order_sn = $_POST['out_trade_no'];  // 订单号
            $trade_status = $_POST['trade_status']; // 状态
            if( $trade_status == 'TRADE_SUCCESS' ){
                $status_other = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->field("status,other")->find();
                if($status_other['other'] == 1){
                    echo "success";
                    exit();
                }
                $inter_data['pay_type'] = 3;
                $inter_data['other'] = 1;
                M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->save($inter_data);
                M()->startTrans();
                if($status_other['status'] == 1){
                    echo "success";
                    exit();
                }
                    /**找到兑换商品下单的订单的记录*/
                    $IntegralOrder = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->find();
                    /**找到商品*/
                    $goods = M("Goods")->where(array('g_id'=>$IntegralOrder['g_id']))->limit(1)->find();
                    /**改变订单的状态*/
                    $order_data['status'] = 1;
                    $inter_res = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->save($order_data);
                    $invest_res_trans = 0;
                    $j_bill = 0;
                    $message_res_j = 0;
                    $nick_name = '';
                    $openid = '';
                    if($IntegralOrder['rank_type'] == 0){
                        $member = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->find();
                        $nick_name = $member['nick_name'];
                        $openid = $member['openid'];
                        $inter_price = M("Config")->getField("inter");
                        if(!empty($member['recommend']) && $member['mem_recommend'] !=1){
                            $is_first_order_member['mix_id'] = $IntegralOrder['mix_id'];
                            $is_first_order_member['rank_type'] = 0;
                            $is_first_order_member['type'] = 0;
                            $count_member = M("IntegralOrder")->where($is_first_order_member)->count();
                            if($count_member<=1){
                                /**添加用户的推荐人的推荐钱数*/
                                $re_wallet = M("Member")->where(array(array('m_id'=>$member['recommend'])))->limit(1)->getField('wallet');
                                $mem_where['m_id'] = $member['recommend'];
                                $recommend_data['wallet'] = floatval($re_wallet)+floatval($inter_price);
                                $inter_price_member = M("Member")->where($mem_where)->limit(1)->save($recommend_data);
                                /**用户的推荐状态改变成1*/
                                $inter_data_member_recommend['mem_recommend'] = 1;
                                M("Member")->where(array('m_id'=>$member['m_id']))->limit(1)->save($inter_data_member_recommend);
                                if($inter_price_member){
                                    /**给用户添加账单*/
                                    $this->addBill($member['recommend'],0,"推荐人收益！",
                                        date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                                        $inter_price,'0','0',2,$member['nick_name'],2,0,$member['m_id'],0,0);
                                    /**给用户发消息推送推荐人买单收益的信息*/
                                    $this->addMessage($member['recommend'],"推荐人收益！",
                                        date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                                        '0',$inter_price);
                                }
                            }
                        }
                        /**查看配置文件设置的最大的消费的临界值*/
                        $max_value = M("Config")->getField("max_value");
                        /**添加用户的消费的求和*/
                        if($max_value<$IntegralOrder['price']){
                            $total_data['total'] = floatval($max_value)+floatval($member['total']);
                        }else{
                            $total_data['total'] = floatval($IntegralOrder['price'])+floatval($member['total']);
                        }
                        M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->save($total_data);
                        /**查看符合标注的购买股份的人员的最低值*/
                        $meet_pay_price = M("Config")->getField("meet_pay_price");
                        if((floatval($IntegralOrder['price'])+floatval($member['integral']))>=$meet_pay_price){
                            /**计算当天用户的累计消费是否超过设定的值*/
                            $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
                            $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
                            $where_order['mix_id'] = $IntegralOrder['mix_id'];
                            $where_order['o_id'] = array('neq',$IntegralOrder['i_o_id']);
                            $where_order['rank_type'] = 0;
                            $where_order['type'] = 0;
                            $where_order['ctime'] = array(array('EGT',$begin),array('ELT',$end),'and');
                            $order_max_price = M("IntegralOrder")->where($where_order)->sum("price");
                            if($order_max_price<$max_value){
                                if($IntegralOrder['price']>=$max_value){
                                    /**消费数取余,也就是满足消费的钱数剩下的不够股份的钱数*/
                                    $a = (floatval($max_value)+floatval($member['integral']))/$meet_pay_price;
                                }else{
                                    /**满足就添加一股*/
                                    $a = (floatval($IntegralOrder['price'])+floatval($member['integral']))/$meet_pay_price;
                                }
                                /**添加用户的股数*/
                                $c = floor($a)-$member['piles'];
                                for ($i=1;$i<=$c;$i++){
                                    $pie_data['mix_id'] = $IntegralOrder['mix_id'];
                                    $pie_data['pie'] = 1;
                                    $pie_data['ctime'] = time();
                                    $pie_data['type'] = 0;
                                    M("Pie")->add($pie_data);
                                }
                                $after_data['piles'] = floor($a);
                                if($IntegralOrder['price']>=$max_value){
                                    $after_data['integral'] = floatval($member['integral'])+floatval($max_value);
                                }else{
                                    $after_data['integral'] = floatval($member['integral'])+floatval($IntegralOrder['price']);
                                }
                                $after_data['utime'] = time();
                                $invest_res_trans = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                            }else{
                                $invest_res_trans = 1;
                            }
                        }else{
//                    /**查看配置文件设置的最大的消费的临界值*/
//                    $max_value = M("Config")->getField("max_value");
                            /**计算当天用户的累计消费是否超过设定的值*/
                            $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
                            $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
                            $where_order['m_id'] = $IntegralOrder['mix_id'];
                            $where_order['o_id'] = array('neq',$IntegralOrder['i_o_id']);
                            $where_order['ctime'] = array(array('EGT',$begin),array('ELT',$end),'and');
                            $order_max_price = M("Order")->where($where_order)->sum("price");
                            if($order_max_price<$max_value){
                                $after_data['integral'] = floatval($member['integral'])+floatval($IntegralOrder['price']);
                                $after_data['utime'] = time();
                                $invest_res_trans = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                            }else{
                                $invest_res_trans = 1;
                            }
                        }
                        /**给用户添加账单*/
                        $j_bill = $this->addBill($IntegralOrder['mix_id'],0,"微信支付购买商品！",
                            date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                            $IntegralOrder['price'],'0','1',2,$goods['name'],4,0,$IntegralOrder['mix_id'],0,0);
                        /**给用户发消息推送推荐人买单收益的信息*/
                        $message_res_j = $this->addMessage($IntegralOrder['mix_id'],"微信支付购买商品！",
                            date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                            '0',$IntegralOrder['price']);
                    }elseif ($IntegralOrder['rank_type'] == 1){
                        $shop = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->find();
                        $nick_name = $shop['name'];
                        $openid = $shop['openid'];
                        /**查看符合标注的购买股份的人员的最低值*/
                        $meet_pay_price = M("Config")->getField("meet_pay_price");
                        if((floatval($IntegralOrder['price'])+floatval($shop['integral']))>=$meet_pay_price){
                            /**消费数取余,也就是满足消费的钱数剩下的不够股份的钱数*/
                            $a = (floatval($IntegralOrder['price'])+floatval($shop['integral']))/$meet_pay_price;
                            /**添加商家的股数*/
                            $c = floor($a)-$shop['piles'];
                            for ($i=1;$i<=$c;$i++){
                                $pie_data['mix_id'] = $IntegralOrder['mix_id'];
                                $pie_data['pie'] = 1;
                                $pie_data['ctime'] = time();
                                $pie_data['type'] = 1;
                                M("Pie")->add($pie_data);
                            }
                            $after_data['piles'] = floor($a);
                            $after_data['integral'] = floatval($shop['integral'])+floatval($IntegralOrder['price']);
                            $after_data['utime'] = time();
                            $invest_res_trans = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                        }else{
                            $after_data['integral'] = floatval($shop['integral'])+floatval($IntegralOrder['price']);
                            $after_data['utime'] = time();
                            $invest_res_trans = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                        }
                        /**给商家添加账单*/
                        $j_bill = $this->addBill($IntegralOrder['mix_id'],0,"微信支付购买商品！",
                            date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                            $IntegralOrder['price'],'0','1',2,$goods['name'],4,1,$IntegralOrder['mix_id'],1,0);
                        /**给商家发消息推送推荐人买单收益的信息*/
                        $message_res_j = $this->addMessage($IntegralOrder['mix_id'],"微信支付购买商品！",
                            date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                            '1',$IntegralOrder['price']);
                        unset($total_data);
                        $total_data['total'] = floatval($IntegralOrder['price'])+floatval($shop['total']);
                        M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->save($total_data);
                    }
                    if($inter_res && $invest_res_trans && $j_bill && $message_res_j){
                        $tem_price = ($IntegralOrder['price']);
                        $time = date('Y-m-d H:i:s',time());
                        $s_name = $goods['name'];
                        $data_to = array(
                            'first'=>array('value'=>urlencode("微信支付兑换商品下单成功！")),
                            'keyword1'=>array('value'=>urlencode("$time")),
                            'keyword2'=>array('value'=>urlencode("$nick_name")),
                            'keyword3'=>array('value'=>urlencode("微信支付兑换商品！")),
                            'keyword4'=>array('value'=>urlencode("$s_name")),
                            'keyword5'=>array('value'=>urlencode("$tem_price 元")),
                            'Remark'=>array('value'=>urlencode("您好,您于 $time 兑换的商品：$s_name 已经下单成功！平台尽快安排为您发货！")),
                        );
                        $url = $_SERVER['SERVER_NAME'];
                        if($IntegralOrder['rank_type'] == 1){
                            $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Merchant/Order/orderlist/type/1/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
                        }else{
                            $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Order/orderlist/type/0/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
                        }
                        echo "success";
                        M()->commit();
                    }else{
                        echo "success";
                        M()->rollback();
                        $this->sendMsg("18522713541","兑换商品，微信支付，订单号：".$order_sn."支付发生未知错误(程序)，请前去查看！");
                    }
            }else{
                echo "success";
                $this->sendMsg("18522713541","兑换商品，微信支付，订单号：".$order_sn."支付发生未知错误(微信)，请前去查看！");
            }
            echo "success";
        }
    }

    /**支付宝的回调地址*/
    public function goBackAliPay(){
    }

    /**
     * 微信支付兑换麦穗商品支付邮费的微信回调
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     */
    public function callPayInterGoods(){
        M("IntegralOrder")->startTrans();
        M("Member")->startTrans();
        M("Shop")->startTrans();
        M("Message")->startTrans();
        M("Bill")->startTrans();
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $xml_res = $this->xmlToArray($xml);
        $order_sn = $xml_res["out_trade_no"];
        $status = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->getField("status");
        if($status == 1){
            echo "success";
            exit();
        }
        if($xml_res['result_code'] == 'SUCCESS'){
            /**找到兑换的订单*/
            $inter_order = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->find();
            /**找到商品*/
            $goods = M("Goods")->where(array('g_id'=>$inter_order['g_id']))->limit(1)->find();
            /**改变订单的状态*/
            $order_data['status'] = 1;
            $inter_res = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->save($order_data);
            /**判断这个订单是商家还是用户*/
            $mix_res = 0;
            $j_bill = 0;
            $message_res_j = 0;
            $piles = 0;
            $save_pie = 0;
            if($inter_order['rank_type'] == 0){
                /**找到这个用户*/
                $w_m['m_id'] = $inter_order['mix_id'];
                $mem_res = M("Member")->where($w_m)->find();
                /**修改这个用户的麦穗*/
                $mem_data['integral'] = floatval($mem_res['integral'])-floatval($inter_order['price']);
                $mix_res = M("Member")->where($w_m)->limit(1)->save($mem_data);
                $j_bill = $this->addBill($inter_order['mix_id'],0,"兑换商品！",
                    date('Y-m-d  H:i:s',time())."花费".(floatval($inter_order['price']))."麦穗,兑换了商品：".$goods['name'],
                    $inter_order['price'],0,'1',4,$goods['name'],4,0,$inter_order['mix_id'],0,$inter_order['price']);
                /**给商家添加消息*/
                $message_res_j = $this->addMessage($inter_order['mix_id'],"兑换商品！",
                    date('Y-m-d-H:i:s',time())."花费".(floatval($inter_order['price']))."麦穗,兑换了商品：".$goods['name'].',支付了配送费：'.$inter_order['postage'],
                    '0',$inter_order['postage']);
                /**计算用户的麦穗股份*/
                $meet_pay_price = M("Config")->getField("meet_pay_price");
                /**当前用户消费完的股数*/
                $a = (floatval($mem_res['integral'])-floatval($inter_order['price']))/$meet_pay_price;
                /**获取用户数据表里面存的股数*/
                $b = M("Pie")->where(array('mix_id'=>$inter_order['mix_id'],'type'=>0))->count();
                $after_data['piles'] = floor($a);
                $after_data['utime'] = time();
                $piles = M("Member")->where($w_m)->limit(1)->save($after_data);
                if(floor($a)<$b){
                    $c = $b-floor($a);
                    $save_pie = M("Pie")->where(array('mix_id'=>$inter_order['mix_id'],'type'=>0))->limit($c)->order("ctime desc")->delete();
                }elseif(floor($a)>$b){
                    unset($c);
                    $c = floor($a)-$b;
                    for ($i=1;$i<=$c;$i++){
                        $pie_data['mix_id'] = $inter_order['mix_id'];
                        $pie_data['pie'] = 1;
                        $pie_data['ctime'] = time();
                        $save_pie = M("Pie")->add($pie_data);
                    }
                }elseif (floor($a)==$b){
                    $save_pie = 1;
                }
            }elseif ($inter_order['	rank_type'] == 1){
                /**找到这个商家*/
                $w_shop['shop_id'] = $inter_order['mix_id'];
                $shop_res = M("Shop")->where($w_shop)->find();
                /**修改这个用户的麦穗*/
                $shop_data['integral'] = floatval($shop_res['integral'])-floatval($inter_order['price']);
                $shop_data['utime'] = time();
                $mix_res = M("Shop")->where($w_shop)->limit(1)->save($shop_data);
                $j_bill = $this->addBill($inter_order['mix_id'],0,"兑换商品！",
                    date('Y-m-d  H:i:s',time())."花费".(floatval($inter_order['price']))."麦穗,兑换了商品：".$goods['name'],
                    $inter_order['price'],0,'1',4,$goods['name'],4,1,$inter_order['mix_id'],1,$inter_order['price']);
                /**给商家添加消息*/
                $message_res_j = $this->addMessage($inter_order['mix_id'],"兑换商品！",
                    date('Y-m-d-H:i:s',time())."花费".(floatval($inter_order['price']))."麦穗,兑换了商品：".$goods['name'].',支付了配送费：'.$inter_order['postage'],
                    '1',$inter_order['postage']);
                $meet_pay_price = M("Config")->getField("meet_pay_price");
                /**获取商家的股数*/
                $a = (floatval($shop_res['integral'])-floatval($inter_order['price']))/$meet_pay_price;
                $after_data['piles'] = floor($a);
                $after_data['utime'] = time();
                $piles = M("Shop")->where($w_shop)->limit(1)->save($after_data);
                /**获取商家数据表里面存的股数*/
                $b = M("Pie")->where(array('mix_id'=>$inter_order['mix_id'],'type'=>1))->count();
                if(floor($a)<$b){
                    $c = $b-floor($a);
                    $save_pie = M("Pie")->where(array('mix_id'=>$inter_order['mix_id'],'type'=>1))->limit($c)->order("ctime desc")->delete();
                }elseif(floor($a)>$b){
                    unset($c);
                    $c = floor($a)-$b;
                    for ($i=1;$i<=$c;$i++){
                        $pie_data['mix_id'] = $inter_order['mix_id'];
                        $pie_data['pie'] = 1;
                        $pie_data['type'] = 1;
                        $pie_data['ctime'] = time();
                        $save_pie = M("Pie")->add($pie_data);
                    }
                }elseif (floor($a)==$b){
                    $save_pie = 1;
                }
            }
            if($inter_res && $mix_res && $j_bill && $message_res_j && $piles && $save_pie){
                M("IntegralOrder")->commit();
                M("Member")->commit();
                M("Shop")->commit();
                M("Message")->commit();
                M("Bill")->commit();
                M("Goods")->where(array('g_id'=>$inter_order['g_id']))->setInc('sales',1); // 商品销量加1
            }else{
                M("IntegralOrder")->rollback();
                M("Member")->rollback();
                M("Shop")->rollback();
                M("Message")->rollback();
                M("Bill")->rollback();
                $this->sendMsg("18522713541","兑换商品，支付运费方法，订单号：".$order_sn."支付发生未知错误(程序)，请前去查看！");
            }
            echo "success";
        }else{
            $this->sendMsg("18522713541","兑换商品，支付运费方法，订单号：".$order_sn."支付发生未知错误(微信)，请前去查看！");
            echo "success";
        }
        echo "success";
    }

    /**
     * 微信支付兑换商品支付的微信回调和环迅支付回调
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     */
    public function callPayWechatGoods(){
        $xml = file_get_contents("php://input");//返回回复数据
        $postStr = str_replace("paymentResult=", "<?xml version='1.0' encoding='UTF-8'?>", rawurldecode($xml));
        $postArray = $this->xmlToArray($postStr);
        if($postArray['GateWayRsp']['body']['MerBillNo']){
            $order_sn = $postArray['GateWayRsp']['body']['MerBillNo'];
            /**找到消费的记录*/
            $IntegralOrder = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['GateWayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","订单{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['GateWayRsp']['head']['RspCode']=='000000' && $postArray['GateWayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['GateWayRsp']['body']['Amount'];
        }else{
            $order_sn = $postArray['WxPayRsp']['body']['MerBillno'];
            /**找到消费的记录*/
            $IntegralOrder = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['WxPayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","订单{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['WxPayRsp']['head']['RspCode']=='000000' && $postArray['WxPayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['WxPayRsp']['body']['OrdAmt'];
        }

        if($IntegralOrder['other'] == 1){
            echo "ipscheckok";
            exit();
        }
        M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->setField("other",1);
        M()->startTrans();
        if($IntegralOrder['status'] == 1){
            echo "success";
            exit();
        }
        if($is_true){
            /**缺少判断钱数和当前数据的钱数是否相同，如果不相同就存到error里面，然后发信息*/
            if($IntegralOrder['price']!= $pay_price){
                $this->sendMsg("18522713541","微信兑换商品支付查询订单{$order_sn}和用户提交的订单不符合!" );
                exit();
            }
            /**找到商品*/
            $goods = M("Goods")->where(array('g_id'=>$IntegralOrder['g_id']))->limit(1)->find();
            /**改变订单的状态*/
            $inter_res = M("IntegralOrder")->where(array('order_sn'=>$order_sn))->limit(1)->setField("status",1);
            $invest_res_trans = 0;
            $j_bill = 0;
            $message_res_j = 0;
            $nick_name = '';
            $openid = '';
            if($IntegralOrder['rank_type'] == 0){
                $member = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->find();
                $nick_name = $member['nick_name'];
                $openid = $member['openid'];
                $inter_price = M("Config")->getField("inter");
                if(!empty($member['recommend']) && $member['mem_recommend'] !=1){
                    $is_first_order_member['mix_id'] = $IntegralOrder['mix_id'];
                    $is_first_order_member['rank_type'] = 0;
                    $is_first_order_member['type'] = 0;
                    $count_member = M("IntegralOrder")->where($is_first_order_member)->count();
                    if($count_member<=1){
                        /**添加用户的推荐人的推荐钱数*/
                        $re_wallet = M("Member")->where(array(array('m_id'=>$member['recommend'])))->limit(1)->getField('wallet');
                        $mem_where['m_id'] = $member['recommend'];
                        $recommend_data = floatval($re_wallet)+floatval($inter_price);
                        $inter_price_member = M("Member")->where($mem_where)->limit(1)->setField("wallet",$recommend_data);
                        /**用户的推荐状态改变成1*/
                        M("Member")->where(array('m_id'=>$member['m_id']))->limit(1)->setField("mem_recommend",1);
                        if($inter_price_member){
                            /**给用户添加账单*/
                            $this->addBill($member['recommend'],0,"推荐人收益！",
                                date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                                $inter_price,'0','0',2,$member['nick_name'],2,0,$member['m_id'],0,0);
                            /**给用户发消息推送推荐人买单收益的信息*/
                            $this->addMessage($member['recommend'],"推荐人收益！",
                                date('Y-m-d-H:i:s',time())."推荐人产生消费获取了".$inter_price."众享豆",
                                '0',$inter_price);
                        }
                    }
                }

                /**添加用户的消费的求和*/
                $total_data = floatval($IntegralOrder['price'])+floatval($member['total']);
                M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->setField("total",$total_data);
                /**查看符合标注的购买股份的人员的最低值*/
                $meet_pay_price = M("Config")->getField("meet_pay_price");
                if((floatval($IntegralOrder['price'])+floatval($member['integral']))>=$meet_pay_price){
                        /**满足就添加一股*/
                        $a = (floatval($IntegralOrder['price'])+floatval($member['integral']))/$meet_pay_price;
                        /**添加用户的股数*/
                        $c = floor($a)-$member['piles'];
                        for ($i=1;$i<=$c;$i++){
                            $pie_data['mix_id'] = $IntegralOrder['mix_id'];
                            $pie_data['pie'] = 1;
                            $pie_data['ctime'] = time();
                            $pie_data['type'] = 0;
                            M("Pie")->add($pie_data);
                        }
                        $after_data['piles'] = floor($a);
                        $after_data['integral'] = floatval($member['integral'])+floatval($IntegralOrder['price']);
                        $after_data['utime'] = time();
                        $invest_res_trans = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                }else{
                    $after_data['integral'] = floatval($member['integral'])+floatval($IntegralOrder['price']);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Member")->where(array('m_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                }
                /**给用户添加账单*/
                $j_bill = $this->addBill($IntegralOrder['mix_id'],0,"微信支付购买商品！",
                    date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                    $IntegralOrder['price'],'0','1',2,$goods['name'],4,0,$IntegralOrder['mix_id'],0,0);
                /**给用户发消息推送推荐人买单收益的信息*/
                $message_res_j = $this->addMessage($IntegralOrder['mix_id'],"微信支付购买商品！",
                    date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                    '0',$IntegralOrder['price']);
            }elseif ($IntegralOrder['rank_type'] == 1){
                $shop = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->find();
                $nick_name = $shop['name'];
                $openid = $shop['openid'];
                /**查看符合标注的购买股份的人员的最低值*/
                $meet_pay_price = M("Config")->getField("meet_pay_price");
                if((floatval($IntegralOrder['price'])+floatval($shop['integral']))>=$meet_pay_price){
                    /**消费数取余,也就是满足消费的钱数剩下的不够股份的钱数*/
                    $a = (floatval($IntegralOrder['price'])+floatval($shop['integral']))/$meet_pay_price;
                    /**添加商家的股数*/
                    $c = floor($a)-$shop['piles'];
                    for ($i=1;$i<=$c;$i++){
                        $pie_data['mix_id'] = $IntegralOrder['mix_id'];
                        $pie_data['pie'] = 1;
                        $pie_data['ctime'] = time();
                        $pie_data['type'] = 1;
                        M("Pie")->add($pie_data);
                    }
                    $after_data['piles'] = floor($a);
                    $after_data['integral'] = floatval($shop['integral'])+floatval($IntegralOrder['price']);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                }else{
                    $after_data['integral'] = floatval($shop['integral'])+floatval($IntegralOrder['price']);
                    $after_data['utime'] = time();
                    $invest_res_trans = M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->save($after_data);
                }
                /**给商家添加账单*/
                $j_bill = $this->addBill($IntegralOrder['mix_id'],0,"微信支付购买商品！",
                    date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                    $IntegralOrder['price'],'0','1',2,$goods['name'],4,1,$IntegralOrder['mix_id'],1,0);
                /**给商家发消息推送推荐人买单收益的信息*/
                $message_res_j = $this->addMessage($IntegralOrder['mix_id'],"微信支付购买商品！",
                    date('Y-m-d-H:i:s',time())."微信支付".$IntegralOrder['price']."元，购买了商品：".$goods['name'],
                    '1',$IntegralOrder['price']);
                unset($total_data);
                $total_data['total'] = floatval($IntegralOrder['price'])+floatval($shop['total']);
                M("Shop")->where(array('shop_id'=>$IntegralOrder['mix_id']))->limit(1)->save($total_data);
            }
            //file_put_contents('goods_pay.txt',$inter_res.'_'.$invest_res_trans.'_'.$j_bill.'_'.$message_res_j);
            if($inter_res && $invest_res_trans && $j_bill && $message_res_j){
                $tem_price = ($IntegralOrder['price']);
                $time = date('Y-m-d H:i:s',time());
                $s_name = $goods['name'];
                $data_to = array(
                    'first'=>array('value'=>urlencode("微信支付兑换商品下单成功！")),
                    'keyword1'=>array('value'=>urlencode("$time")),
                    'keyword2'=>array('value'=>urlencode("$nick_name")),
                    'keyword3'=>array('value'=>urlencode("微信支付兑换商品！")),
                    'keyword4'=>array('value'=>urlencode("$s_name")),
                    'keyword5'=>array('value'=>urlencode("$tem_price 元")),
                    'Remark'=>array('value'=>urlencode("您好,您于 $time 兑换的商品：$s_name 已经下单成功！平台尽快安排为您发货！")),
                );
                $url = $_SERVER['SERVER_NAME'];
                if($IntegralOrder['rank_type'] == 1){
                    $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Merchant/Order/orderlist/type/1/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
                }else{
                    $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Order/orderlist/type/0/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
                }
                echo "ipscheckok";
                M()->commit();
            }else{
                M()->rollback();
                echo "ipscheckok";
                $this->sendMsg("18522713541","兑换商品，微信支付，订单号：".$order_sn."支付发生未知错误(程序)，请前去查看！");
            }
        }else{
            echo "ipscheckok";
            $this->sendMsg("18522713541","兑换商品，微信支付，订单号：".$order_sn."支付发生未知错误(微信)，请前去查看！");
        }
    }



    /**环迅支付认证费用的回调地址
     * @author crazy
     * @time 2018-01-02
     */
    public function callBackApproveOrder(){
        file_put_contents('approve1.txt',1);
        $xml = file_get_contents("php://input");//返回回复数据
        $postStr = str_replace("paymentResult=", "<?xml version='1.0' encoding='UTF-8'?>", rawurldecode($xml));
        $postArray = $this->xmlToArray($postStr);
        $approve = M("ApproveOrder");
        if($postArray['GateWayRsp']['body']['MerBillNo']){
            $order_sn = $postArray['GateWayRsp']['body']['MerBillNo'];
            /**找到消费的记录*/
            $approve_res = $approve->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['GateWayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","认证费用订单{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['GateWayRsp']['head']['RspCode']=='000000' && $postArray['GateWayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['GateWayRsp']['body']['Amount'];
        }else{
            $order_sn = $postArray['WxPayRsp']['body']['MerBillno'];
            /**找到消费的记录*/
            $approve_res = $approve->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['WxPayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","认证费用订单{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['WxPayRsp']['head']['RspCode']=='000000' && $postArray['WxPayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['WxPayRsp']['body']['OrdAmt'];
        }
        if($approve_res['is_check'] == 1){
            echo "ipscheckok";
            exit();
        }
        $shop_id = $approve_res['shop_id'];
        $approve->where(array('order_sn'=>$order_sn))->limit(1)->setField('is_check',1);
        /**找到商家还未用完的入驻费用*/
        $enter_price = M("Shop")->where(['shop_id'=>$shop_id])->getField("enter_price");
        if($is_true) {
            /**缺少判断钱数和当前数据的钱数是否相同，如果不相同就存到error里面，然后发信息*/
            if ($approve_res['price'] != $pay_price) {
                $error_data_query = array(
                    'error_sn' => $order_sn,
                    'shop_id' => $shop_id,
                    'price' => $approve_res['price'] ? $approve_res['price'] : 0,
                    'title' => "微信支付查询和用户提交的订单不符合!",
                    'ctime' => time()
                );
                M("Error")->add($error_data_query);
                $this->sendMsg("18522713541", "微信支付查询和用户提交的订单不符合!");
                echo "ipscheckok";
                exit();
            }
            M()->startTrans();
            $update_time = [
                'enter_price'=>floatval($enter_price)+floatval($approve_res['price']/0.15),
                'approve_time'=>strtotime('+1year'),
                'approve_id'=>$approve_res['app_id'],
                'grade_icon'=>'Uploads/'.M('ApprovePrice')->where(['id'=>$approve_res['app_id']])->getField('pic')
            ];
            /**给商家添加消息*/
            $message_res_j = $this->addMessage($shop_id,"认证费用！",
                date('Y-m-d-H:i:s',time())."支付了{$approve_res['price']}认证费",
                '1',$approve_res['price']);
//            /**给商家添加账单*/
//            $j_bill = $this->addBill($shop_id,0,"认证费用！",
//                date('Y-m-d-H:i:s',time())."支付了{$approve_res['price']}认证费",
//                $approve_res['price'],0,'1',2,"",3,0,0,1,$approve_res['price'],0,0);
            $is_pay = M("Shop")->where(['shop_id'=>$shop_id])->limit(1)->save($update_time);
            file_put_contents('approve.txt',M("Shop")->getLastSql());
            /**修改订单的状态*/
            $order_res = $approve->where(array('order_sn'=>$order_sn))->limit(1)->save(['status'=>1]);
            if($is_pay && $message_res_j && $order_res){
                M()->commit();
                echo "ipscheckok";
            }else{
                M()->rollback();
                echo "ipscheckok";
            }
        }
    }


    /**大的订单直接支付的接口
     * @author crazy
     * @time 2018-01-03
     */
    public function callBackShopMixOrder(){
        $xml = file_get_contents("php://input");//返回回复数据
        $postStr = str_replace("paymentResult=", "<?xml version='1.0' encoding='UTF-8'?>", rawurldecode($xml));
        $postArray = $this->xmlToArray($postStr);
        $orderTotal = M("OrderTotal");
        if($postArray['GateWayRsp']['body']['MerBillNo']){
            $order_sn = $postArray['GateWayRsp']['body']['MerBillNo'];
            /**找到消费的记录*/
            $order_res = $orderTotal->where(array('order_sn'=>$order_sn))->limit(1)->field("order_id,m_id,status,real_total_money")->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['GateWayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","商城支付订单（大的订单）{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['GateWayRsp']['head']['RspCode']=='000000' && $postArray['GateWayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['GateWayRsp']['body']['Amount'];
        }else{
            $order_sn = $postArray['WxPayRsp']['body']['MerBillno'];
            /**找到消费的记录*/
            $order_res = $orderTotal->where(array('order_sn'=>$order_sn))->limit(1)->field("order_id,m_id,status,real_total_money")->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['WxPayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","商城支付订单（大的订单）{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['WxPayRsp']['head']['RspCode']=='000000' && $postArray['WxPayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['WxPayRsp']['body']['OrdAmt'];
        }
        if($order_res['status'] == 1){
            echo "ipscheckok";
            exit();
        }
        if($is_true) {
            M()->startTrans();
            /**找到子订单*/
            $shop_id_list = M("ProductOrder")->where(['p_o_id'=>['in',$order_res['order_id']]])->field("shop_id,real_price")->select();
            /**找到用户*/
            $member = M("Member")->where(['m_id'=>$order_res['m_id']])->field('m_id,nick_name,is_set,mem_recommend,recommend')->find();

            $this->addMemberCache($order_res['m_id']);

            $shop_id_push = [];
            $shop_openid_push = [];
            $shop_name = [];
            foreach($shop_id_list as $k=>$v){
                $shop_res = M("Shop")->where(['shop_id'=>$v['shop_id']])->field("is_set,scale_p,scale_member,name,ice_wallet,is_recommend,recommend,openid")->find();
                /**计算商家应该获取的钱数*/
//                $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
//                $price = sprintf("%.2f",$commission*$v['real_price']);
                $price = $v['real_price'];
                /**商家的运营费用*/
//                $other_price = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$v['real_price']);
                /**给商家添加消息*/
                $this->addMessage($v['shop_id'],"用户购买商品支付！",
                    "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$v['real_price']}元，请您尽快安排发货",'1',
                    $price,$order_res['m_id'],2,1);
                /**增加商家的冻结的资金*/
                M("Shop")->where(['shop_id'=>$v['shop_id']])->limit(1)->save(['ice_wallet'=>floatval($shop_res['ice_wallet'])+floatval($price)]);
                /**给商家添加账单*/
                $this->addBill($v['shop_id'],0,"用户购买商品支付！",
                    "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$v['real_price']}元",
                    $v['real_price'],0,'0',2,$member['nick_name'],9,0,$order_res['m_id'],1,$v['real_price'],0,0);
                if($shop_res['is_set'] == 1){
                    $shop_id_push[] = $v['shop_id'];
                }
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
                            /**找到用户的钱包的金额*/
                            $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                            $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                            M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
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
                        /**找到用户的钱包的金额*/
                        $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                        M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
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


                $shop_name[] = $shop_res['name'];
            }
            $MerName = implode(";",$shop_name);
            /**添加用户的账单和信息*/
            $this->addMessage($order_res['m_id'],"订单号{$order_sn}支付成功！",
                "订单号{$order_sn}支付成功,商家会为您尽快发货！",'0',
                $order_res['real_total_money'],implode(",",$shop_id_push),2,1);
            $this->addBill($order_res['m_id'],0,"订单号{$order_sn}支付成功！",
                "订单号{$order_sn}支付成功,商家会为您尽快发货",
                $order_res['real_total_money'],0,'1',2,$MerName,9,0,$order_res['m_id'],0,$order_res['real_total_money'],0,0);

            /**修改订单的状态*/
            $is_pay_total = $orderTotal->where(array('order_sn'=>$order_sn))->limit(1)->save(['status'=>1,'pay_time'=>time(),'pay_type'=>1]);
            $small_order = M("ProductOrder")->where(['p_o_id'=>['in',$order_res['order_id']]])->save(['status'=>1,'pay_time'=>time(),'pay_type'=>1]);
            if($is_pay_total && $small_order){
                M()->commit();

                try{
                    if(count($shop_openid_push)>0){
                        foreach($shop_openid_push as $kk=>$vv){
                            $this->shopSendTem($member['nick_name'],$vv);
                        }
                    }
                }catch (\Exception $e){
                    echo "ipscheckok";
                }

                try{
                    if(count($shop_id_push)>0){
                        /**给APP商家端推送消息*/
                        $alias = ''.implode(',',$shop_id_push);
                        $alert = "您有新的订单请注意查收！";
                        $extra['type'] = '1';
                        $this->push("",$alias,$alert,$alert,$extra,1);
                    }
                }catch (\Exception $e){
                    echo "ipscheckok";
                }
                echo "ipscheckok";
            }else{
                M()->rollback();
                echo "ipscheckok";
            }
        }
    }


    /**商家的订单直接支付的接口
     * @author crazy
     * @time 2018-01-03
     */
    public function callBackShopOneOrder(){
        $xml = file_get_contents("php://input");//返回回复数据
        $postStr = str_replace("paymentResult=", "<?xml version='1.0' encoding='UTF-8'?>", rawurldecode($xml));
        $postArray = $this->xmlToArray($postStr);
//        file_put_contents('pay_status.txt',json_encode($postArray));
        $productOrder = M("ProductOrder");
        if($postArray['GateWayRsp']['body']['MerBillNo']){
            $order_sn = $postArray['GateWayRsp']['body']['MerBillNo'];
            /**找到消费的记录*/
            $order_res = $productOrder->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['GateWayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","商城支付订单（单个订单）{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['GateWayRsp']['head']['RspCode']=='000000' && $postArray['GateWayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['GateWayRsp']['body']['Amount'];
        }else{
            $order_sn = $postArray['WxPayRsp']['body']['MerBillno'];
            /**找到消费的记录*/
            $order_res = $productOrder->where(array('order_sn'=>$order_sn))->limit(1)->find();
            $paymentResult = $_REQUEST['paymentResult'];
            $strBody = $this->subStrXml("<body>","</body>",$paymentResult);
            $sing = $this->md5Param($strBody);
            if($sing!=$postArray['WxPayRsp']['head']['Signature']){
                $this->sendMsg("18522713541","商城支付订单（单个订单）{$order_sn}签名验证失败!" );
                exit();
            }
            $is_true = $postArray['WxPayRsp']['head']['RspCode']=='000000' && $postArray['WxPayRsp']['body']['Status']== "Y";
            $pay_price = $postArray['WxPayRsp']['body']['OrdAmt'];
        }
        if($order_res['status'] == 1){
            echo "ipscheckok";
            exit();
        }

        if($is_true) {
            M()->startTrans();
            /**找到商家*/
            $shop_res = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->field("is_set,scale_p,scale_member,name,ice_wallet,is_recommend,recommend,openid")->find();
            /**找到用户*/
            $member = M("Member")->where(['m_id'=>$order_res['m_id']])->field('nick_name,recommend,is_set,mem_recommend')->find();
            /**计算商家应该获取的钱数*/
//            $commission = 1-(($shop_res['scale_p']+$shop_res['scale_member'])/100);
//            $price = sprintf("%.2f",$commission*$order_res['real_price']);
            $price = $order_res['real_price'];
            /**商家的运营费用*/
//            $other_price = sprintf("%.2f",(($shop_res['scale_p']+$shop_res['scale_member'])/100)*$order_res['real_price']);
            /**给商家添加消息*/
            $message_res_j = $this->addMessage($order_res['shop_id'],"用户购买商品支付！",
                "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$order_res['real_price']}元，请您尽快安排发货",'1',
                $price,$order_res['m_id'],2,1);
            /**给商家添加账单*/
            $j_bill = $this->addBill($order_res['shop_id'],0,"用户购买商品支付！",
                "用户:{$member['nick_name']}".date('Y-m-d-H:i:s',time())."购买商品支付了{$order_res['real_price']}元",
                $order_res['real_price'],0,'0',2,$member['nick_name'],9,0,$order_res['m_id'],1,$order_res['real_price'],0,0);

            $this->addMemberCache($order_res['m_id']);

            /**增加商家的冻结的资金*/
            $ice_wallet = M("Shop")->where(['shop_id'=>$order_res['shop_id']])->limit(1)->save(['ice_wallet'=>floatval($shop_res['ice_wallet'])+floatval($price)]);

            /**添加用户的账单和信息*/
            $this->addMessage($order_res['m_id'],"订单号{$order_sn}支付成功！",
                "订单号{$order_sn}支付成功,商家会为您尽快发货！",'0',
                $order_res['real_price'],$order_res['shop_id'],2,1);
            $this->addBill($order_res['m_id'],0,"订单号{$order_sn}支付成功！",
                "订单号{$order_sn}支付成功,商家会为您尽快发货",
                $order_res['real_price'],0,'1',2,$shop_res['name'],9,1,$order_res['shop_id'],0,$order_res['real_price'],0,0);

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

            /**计算用豆抵扣的操作*/
//            $cou_money_show= $this->scale($order_res['o_t_id'],$order_res['real_price']);

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
                        /**找到用户的钱包的金额*/
                        $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                        $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                        M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
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
                    /**找到用户的钱包的金额*/
                    $wallet_recommend = M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->getField("wallet");
                    $wallet_data['wallet'] = floatval($wallet_recommend)+floatval($recommend_shop_price);
                    M("Member")->where(array('m_id'=>$recommend_shop_person))->limit(1)->save($wallet_data);
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

            /**修改订单的状态*/
            $small_order = M("ProductOrder")->where(['p_o_id'=>$order_res['p_o_id']])->limit(1)->save(['status'=>1,'pay_time'=>time(),'pay_type'=>1]);
//            file_put_contents('is_app.txt',$small_order,FILE_APPEND);
//            file_put_contents('is_app.txt',M("ProductOrder")->getLastSql(),FILE_APPEND);
            if($ice_wallet&&$small_order){
                M()->commit();

                try{
                    $openid = $shop_res['openid'];
                    $nick_name = $member['nick_name'];
                    $this->shopSendTem($nick_name,$openid);
                }catch (\Exception $e){
                    echo "ipscheckok";
                }

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
                    echo "ipscheckok";
                }
                echo "ipscheckok";
            }else{
                M()->rollback();
                echo "ipscheckok";
            }
        }

    }


}