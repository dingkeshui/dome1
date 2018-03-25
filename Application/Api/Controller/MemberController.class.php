<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 用户个人中心
 */
class MemberController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 用户的个人中心
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function memberCenter(){
        if(empty($_GET['m_id'])){
            apiResponse("error","请用微信浏览器打开！");
        }
        $m_id = $_GET['m_id'];
        $member_obj = M("Member");
        $res = $member_obj->where(array('m_id'=>$m_id))->field('m_id,nick_name,account,head_pic,recommend,wallet,code,piles,integral')->limit(1)->find();
        $nick_name = $member_obj->where(array("m_id"=>$res['recommend']))->getField("nick_name");
        $res['recommend'] = $nick_name?$nick_name:"0";
        /**判断这个用户是今天是否签到了*/
        $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
        $where['m_id'] = $m_id;
        $where['type'] = 0;
        $where['sign_time'] = array(array('EGT',$begin),array('ELT',$end),'and');
        $res_sign = M("Sign")->where($where)->getField("id");
        if($res_sign){
            $res['is_sign'] = 1;
        }else{
            $res['is_sign'] = 0;
        }
        $member_bind['id_type'] = 0;
        $member_bind['mix_id'] = $m_id;
        $member_bind['status'] = array('neq',9);
        $member_with = M("Withdraw_bank")->where($member_bind)->order("ctime desc")->field("width_id,account,name")->find();
        $res['z_id'] = $member_with['width_id']?$member_with['width_id']:"";
        $res['z_account'] = $member_with['account']?$member_with['account']:"";
        $res['z_name'] = $member_with['name']?$member_with['name']:"";
        /**先判断用户是否在满足的条件下，这个地方要查找显示昨天之前的满足能消费的用户的标准*/
        $begin_other = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $invest_w['ctime'] = array('ELT',$begin_other);
        $invest_w['mix_id'] = $m_id;
        $invest_w['type'] = 0;
        $count = M("Pie")->where($invest_w)->count();
        /**判断一下还差多少积分就满足一股*/
        /**查看符合标注的购买股份的人员的最低值*/
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        $b = sprintf("%.2f",fmod($res['integral'],$meet_pay_price));
        $res['remain'] = floatval($meet_pay_price)-floatval($b);
        if($res['piles']<=0 || $count<=0){
            $res['is_true'] = 1;
        }else{
            $res['is_true'] = 0;
        }
        /**判断这个用户是否开户*/
        $is_open = M('HxUser')->where(array('m_id'=>$res['m_id'],'type'=>0))->limit(1)->getField('h_id');
        $is_open = $is_open?$is_open:'';
        $res['is_open'] = $is_open;
        /**判断这个用户是否有未读的消息*/
        $is_read = $this->isHaveMsgCenter($m_id,0);
        $res['is_read'] = $is_read;
        /**订单的数量*/
        $res['wait_pay_order'] = M("ProductOrder")->where(['status'=>0,'m_id'=>$m_id])->count()?M("ProductOrder")->where(['status'=>0,'m_id'=>$m_id])->count():"0";
        $res['wait_send_order'] = M("ProductOrder")->where(['status'=>1,'m_id'=>$m_id])->count()?M("ProductOrder")->where(['status'=>1  ,'m_id'=>$m_id])->count():"0";
        $res['wait_make_order'] = M("ProductOrder")->where(['status'=>2,'m_id'=>$m_id])->count()?M("ProductOrder")->where(['status'=>2,'m_id'=>$m_id])->count():"0";
        $res['wait_appraise_order'] = M("ProductOrder")->where(['status'=>3,'m_id'=>$m_id])->count()?M("ProductOrder")->where(['status'=>3,'m_id'=>$m_id])->count():"0";
        $res['wait_tk_order'] = M("ReturnOrder")->where(['status'=>0,'m_id'=>$m_id])->count()?M("ReturnOrder")->where(['status'=>0,'m_id'=>$m_id])->count():"0";
        if($res){
            apiResponse("success","获取成功！",$res);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 用户签到
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function sign(){
        if(empty($_POST['m_id'])){
            apiResponse('error',"参数错误！请联系客服");
        }
        /**判断昨天股价是否为0，如果为0则不能签到*/
        /**获取昨天的时间戳*/
        $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $endYesterday=mktime(23,59,59,date('m'),date('d')-1,date('Y'));
        $where_divide['ctime'] = array(array('EGT',$beginYesterday),array('ELT',$endYesterday),'and');
        $day_divide_res = M("Day_divide")->where($where_divide)->limit(1)->getField("d_id");
        if(empty($day_divide_res)){
            apiResponse('error',"未到签到时间，请稍后重试！");
        }
        /**先判断程序是否执行了，如果没有执行就不能让用户签到进行分钱的操作*/
        $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $total_w['id'] = 1;
        $ctime = M("Total")->where($total_w)->limit(1)->getField("ctime");
        if($ctime<$begin){
            apiResponse("error","暂不支持签到，稍后重试！");
        }
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误！");
        }
        /**判断这个用户是今天是否签到了*/
        $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
        $where['m_id'] = $_POST['m_id'];
        $where['type'] = 0;
        $where['sign_time'] = array(array('EGT',$begin),array('ELT',$end),'and');
        $res_sign = M("Sign")->where($where)->getField("id");
        if($res_sign){
            apiResponse("error","您今日已经签到！");
        }
        /**判断这个用户是否领取了*/
        $where_divide['m_id'] = $_POST['m_id'];
        $where_divide['type'] = 0;
        $where_divide['ctime'] = array(array('EGT',$begin),array('ELT',$end),'and');
        $res_divide = M("Divide_log")->where($where_divide)->getField("id");
        if($res_divide){
            apiResponse("error","您今日已经签到！");
        }
        M()->startTrans();
        /**先判断用户是否在满足的条件下*/
        $begin_invest = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $invest_w['ctime'] = array('ELT',$begin_invest);
        $invest_w['mix_id'] = $_POST['m_id'];
        $invest_w['type'] = 0;
        $count = M("Pie")->where($invest_w)->count();
        if($count<=0){
            apiResponse("error","您暂不支持签到！");
        }
        /**找到用户每人可以领的钱数*/
        $total_w['id'] = 1;
        $mem_price = M("Total")->where($total_w)->limit(1)->getField("mem_price");
        if($mem_price<=0 || $mem_price==0.00){
            apiResponse("error","抱歉今天暂无分成！");
        }
        $total_price = M("Total")->where($total_w)->limit(1)->getField("price");
        if($total_price<($mem_price*$count)){
            apiResponse("error","签到失败，请联系管理员！");
        }
        /**先更改用户的签到的时间戳*/
        $w['m_id'] = $_POST['m_id'];
        $w['type'] = 0;
        $res = M("Sign")->where($w)->limit(1)->find();
        if($res){
            $data_sign['sign_time'] = time();
            $data_sign['number'] = $res['number']+1;
            $sign_res = M("Sign")->where($w)->limit(1)->save($data_sign);
        }else{
            unset($data_sign);
            $data_sign['sign_time'] = time();
            $data_sign['m_id'] = $_POST['m_id'];
            $data_sign['number'] = 1;
            $sign_res = M("Sign")->add($data_sign);
        }
        /**处理用户的股份的金额*/
        $member_obj = M("Member");
        $save_pie = 0;
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        $member_w['m_id'] = $_POST['m_id'];
        $mem_find_res  = $member_obj->where($member_w)->limit(1)->find();
        if($mem_find_res['piles'] < $count){
            $price = $mem_price*$mem_find_res['piles'];
        }else{
            $price = $mem_price*$count;
        }
        /**处理用户领取的钱数，然后从新计算用户的积分，之后在从新计算用户的股数*/
        $a = (floatval($mem_find_res['integral'])-floatval($price))/$meet_pay_price;
        /**获取用户数据表里面存的股数*/
        $b = M("Pie")->where(array('mix_id'=>$_POST['m_id'],'type'=>0))->count();
        /**减少用户的积分数*/
        $inter_data_change['integral'] = floatval($mem_find_res['integral'])-floatval($price);
        $inter_res_change = $member_obj->where($member_w)->limit(1)->save($inter_data_change);
        /**计算用户的股份*/
        $res_last_piles = 0;
        if(floor($a)<$b){
            $c = $b-floor($a);
            $save_pie = M("Pie")->where(array('mix_id'=>$_POST['m_id'],'type'=>0))->limit($c)->order("ctime desc")->delete();
            $after_data['piles'] = floor($a);
            $after_data['utime'] = time()+1;
            $res_last_piles = $member_obj->where($member_w)->limit(1)->save($after_data);
        }elseif(floor($a)>$b){
            unset($c);
            $c = floor($a)-$b;
            for ($i=1;$i<=$c;$i++){
                $pie_data['mix_id'] = $_POST['m_id'];
                $pie_data['pie'] = 1;
                $pie_data['ctime'] = time();
                $save_pie = M("Pie")->add($pie_data);
            }
            $last_piles['piles'] = floor($a);
            $last_piles['utime'] = time()+1;
            $res_last_piles = $member_obj->where($member_w)->limit(1)->save($last_piles);
        }elseif (floor($a)==$b){
            $save_pie = 1;
            $res_last_piles = 1;
        }
        /**用户钱包加钱*/
        $member_data['wallet'] = floatval($mem_find_res['wallet'])+floatval($price);
        $member_data['last_login_time'] = time();
        $member_data['last_login_ip'] = get_client_ip();
        $member_data['earn_price'] = $price;
        $member_res = $member_obj->where($member_w)->limit(1)->save($member_data);
        /**添加领钱的记录*/
        $divide_log_data['price'] = $price;
        $divide_log_data['m_id'] = $_POST['m_id'];
        $divide_log_data['ctime'] = time();
        $divide_log = M("divide_log")->add($divide_log_data);
        /**添加用户的账单的明细*/
        $m_bill_data['title'] = "每日收益！";
        $m_bill_data['content'] = date('Y-m-d H:i:s',time())."签到领取了:".$price."众享豆";
        $m_bill_data['ctime'] = time();
        $m_bill_data['m_id'] = $_POST['m_id'];
        $m_bill_data['shop_id'] = 0;
        $m_bill_data['monitor'] = 0;
        $m_bill_data['type'] = 2;
        $m_bill_data['accept_m_id'] = $_POST['m_id'];
        $m_bill_data['name'] = $member_obj->where($member_w)->limit(1)->getField("nick_name");
        $m_bill_data['price'] = $price;
        $m_bill = M("Bill")->add($m_bill_data);
        /**给用户发消息推送买单的信息*/
        $message_res_m = $this->addMessage($_POST['m_id'],"每日收益！",date('Y-m-d-H:i:s',time())."签到领取了:".$price."众享豆",0,$price);
        /**减去资金池的金额*/
        $total_data['price'] = $total_price-($price);
        $total_j_res = M("Total")->where($total_w)->limit(1)->save($total_data);
        if($sign_res&&$member_res&&$divide_log&&$m_bill&&$message_res_m&&$total_j_res&&$save_pie&&$inter_res_change&&$res_last_piles){
            /**向缓存中添加新的用户的数据*/
            $list1 = S("MEMBER_SIGN");
            $x_string = $list1?"会员".$m_bill_data['name']."签到领取了:".$price."众享豆".",".$list1:"".","."会员".$m_bill_data['name']."签到领取了:".$price."众享豆";
            S("MEMBER_SIGN",$x_string);
            M()->commit();
            apiResponse("success","签到成功！领取了:".$price."众享豆",$price);
        }else{
            M()->rollback();
            apiResponse("error","签到失败！");
        }
    }


    /**
     * 封装用户签到领取钱数，减持股份的操作
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     * @param price 领取的钱数
     */
    public function reduce($id,$price,$i_a_id){
        M("Invest_earn")->startTrans();
        $res = M("invest_earn")->where(array('i_a_id'=>$i_a_id))->field("i_a_id,price,surplus_price")->limit(1)->find();
        $meet_pay_price_config = M("Config")->getField("meet_pay_price");
        if($meet_pay_price_config<$price){
            $data_lq_glt['price'] = 0;
            $data_lq_glt['surplus_price'] = $meet_pay_price_config;
            $data_lq_glt['status'] = 9;
            $invest_res_glt = M("invest_earn")->where(array('i_a_id'=>$res['i_a_id']))->limit(1)->save($data_lq_glt);
            if($invest_res_glt){
                /**判断用户是否还有未返股数*/
                $is_two = M("invest_earn")->where(array('m_id'=>$id,'type'=>0,'status'=>array('neq',9)))->limit(1)->find();
                if($is_two){
                    /**计算这个钱数是减去了最少的一笔的钱数*/
                    $is_j_price = floatval($price)-floatval($res['price']);
                    /**计算剩下的钱数，然后计算能有多少个可以执行的数据*/
                    $b_shop = sprintf("%.2f",fmod((floatval($is_j_price)),$meet_pay_price_config));
                    /**满足就减持一股*/
                    $a_shop = floor((floatval($is_j_price))/$meet_pay_price_config);
//                    apiResponse('error',$is_j_price.'_'.$b_shop.'_'.$a_shop);
                    if($a_shop<1){
                        $is_two_other = M("invest_earn")->where(array('m_id'=>$id,'type'=>0,'status'=>array('neq',9)))->limit(1)->find();
                        $data_lq_two['price'] = floatval($is_two_other['price'])-floatval($b_shop);
                        $data_lq_two['surplus_price'] = floatval($is_two_other['surplus_price'])+floatval($b_shop);
                        $invest_res_other = M("invest_earn")->where(array('i_a_id'=>$is_two_other['i_a_id']))->limit(1)->save($data_lq_two);
                        if($invest_res_other){
                            M("Invest_earn")->commit();
                            return floatval($res['price'])+floatval($b_shop);
                        }else{
                            M("Invest_earn")->rollback();
                            return 0;
                        }
                    }else{
                        $m_list = M("invest_earn")->where(array('m_id'=>$id,'type'=>0,'status'=>array('neq',9)))->limit(0,$a_shop)->select();
                        $price_x = 0;
                        foreach ($m_list as $kk=>$vv){
                            $price_x += $vv['price'];
                            $invest_data['status'] = 9;
                            $invest_data['price'] = 0;
                            $invest_data['surplus_price'] = $meet_pay_price_config;
                            $invest_res = M("invest_earn")->where(array('i_a_id'=>$vv['i_a_id']))->save($invest_data);
                        }
                        if($invest_res){
                            /**判断用户是否还有未返股数*/
                            $is_two_other = M("invest_earn")->where(array('m_id'=>$id,'type'=>0,'status'=>array('neq',9)))->limit(1)->find();
                            if($is_two_other){
                                $data_lq_two['price'] = floatval($is_two_other['price'])-floatval($b_shop);
                                $data_lq_two['surplus_price'] = floatval($is_two_other['surplus_price'])+floatval($b_shop);
                                $invest_res_other = M("invest_earn")->where(array('i_a_id'=>$is_two_other['i_a_id']))->limit(1)->save($data_lq_two);
                                if($invest_res_other){
                                    M("Invest_earn")->commit();
                                    return floatval($res['price'])+floatval($price_x)+floatval($b_shop);
                                }else{
                                    M("Invest_earn")->rollback();
                                    return 0;
                                }
                            }else{
                                M("Invest_earn")->commit();
                                return floatval($res['price'])+floatval($price_x);
                            }
                        }else{
                            apiResponse("error","签到失败，请联系管理员！");
                        }
                    }
                }else{
                    M("Invest_earn")->commit();
                    return $res['price'];
                }
            }else{
                M("Invest_earn")->rollback();
                return 0;
            }
        }elseif($meet_pay_price_config>=$price){
            /**当领取的这个钱数小于最大值也就是股份值的时候，那么也就要减去两次的钱数*/
            if($res['price']<$price){
                $data_lq['price'] = 0;
                $data_lq['surplus_price'] = $meet_pay_price_config;
                $data_lq['status'] = 9;
                $invest_res = M("invest_earn")->where(array('i_a_id'=>$res['i_a_id']))->limit(1)->save($data_lq);
                if($invest_res){
                    /**判断用户是否还有未返股数*/
                    $is_two = M("invest_earn")->where(array('m_id'=>$id,'type'=>0,'status'=>array('neq',9)))->order("price asc")->limit(1)->find();
                    if($is_two){
                        $is_j_price = floatval($price)-floatval($res['price']);
                        $data_lq_two['price'] = floatval($is_two['price'])-floatval($is_j_price);
                        $data_lq_two['surplus_price'] = floatval($is_two['surplus_price'])+floatval($is_j_price);
                        $invest_res_two = M("invest_earn")->where(array('i_a_id'=>$is_two['i_a_id']))->limit(1)->save($data_lq_two);
                        if($invest_res_two){
                            M("Invest_earn")->commit();
                            return floatval($res['price'])+floatval($is_j_price);
                        }else{
                            M("Invest_earn")->rollback();
                            return 0;
                        }
                    }else{
                        M("Invest_earn")->commit();
                        return $res['price'];
                    }
                }else{
                    M("Invest_earn")->rollback();
                    return 0;
                }
            }elseif($res['price']>$price){
                $data_lq['price'] = floatval($res['price'])-floatval($price);
                $data_lq['surplus_price'] = floatval($res['surplus_price'])+floatval($price);
                $data_lq['status'] = 9;
                $invest_res = M("invest_earn")->where(array('i_a_id'=>$res['i_a_id']))->limit(1)->save($data_lq);
                if($invest_res){
                    M("Invest_earn")->commit();
                    return $price;
                }else{
                    M("Invest_earn")->rollback();
                    return 0;
                }
            }else{
                M("Invest_earn")->rollback();
                return 0;
            }

        }
    }

    /**
     * 用户的设置
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     * @param nick_name 用户的昵称
     * @param head_pic 头像
     */
    public function configMember(){
        $w['m_id'] = $_POST['m_id'];
        $nickname = myTrim($_POST['nick_name']);
        if (!empty($nickname)){
            $data['nick_name'] = $nickname;
        }
        if($_POST['app_pic'] == 1){
            $file_res = $this->uploadImg('Member','member');
            $data['head_pic'] = '/Uploads/'.$file_res;
        }else{
            if ($_POST['head_pic']){
                $data['head_pic'] = $_POST['head_pic'];
            }
        }
        $data['utime'] = time();
        $res = M("Member")->where($w)->save($data);
        if($res){
            apiResponse("success","保存成功！",$res);
        }else{
            apiResponse("error","保存失败！");
        }
    }


    /**
     * one:用户的转账(展示)
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param account 转账人的手机号
     *
     * two:用户转账（处理业务逻辑）
     * @param m_id  用户的id
     * @param price 转账的钱数
     * @param j_m_id 接受用户的id
     */
    public function transferMember(){
        if(!IS_POST){
            /**先展示用户的信息*/
            $w['account'] = $_GET['account'];
            $w['status'] = array('neq',9);
            $res = M("Member")->where($w)->field("m_id,account,nick_name,head_pic")->limit(1)->find();
            /**转账人的信息*/
            $z_w['m_id'] = $_GET['m_id'];
            $wallet = M("Member")->where($z_w)->getField("wallet");
            $res['show_wallet'] = $wallet?$wallet:"0";
            if($res){
                apiResponse("success","获取成功！",$res);
            }else{
                apiResponse("error","获取失败！");
            }
        }else{
            $member_obj = M("Member");
            if(empty($_POST['m_id'])){
                apiResponse("error","参数错误！");
            }
            if(empty($_POST['price'])){
                apiResponse("error","参数错误！");
            }
            if(empty($_POST['j_m_id'])){
                apiResponse("error","参数错误！");
            }
            if($_POST['m_id'] == $_POST['j_m_id']){
                apiResponse("error","不能给自己转账！");
            }
            /**用户转账(转账的人)*/
            M()->startTrans();
            $w['m_id'] = $_POST['m_id'];
            $m_wallet = $member_obj->where($w)->field('is_set,wallet,account,nick_name,m_id')->limit(1)->find();
            if(empty($m_wallet)){
                apiResponse("error","参数错误，用户不存在！");
            }
            $data['wallet'] = $m_wallet['wallet']-$_POST['price'];
            if($data['wallet']<0){
                apiResponse('error',"账号众享豆不足，最多只能转".$m_wallet['wallet']);
            }
            $m_res = $member_obj->where($w)->limit(1)->save($data);

            /**接受转账的人*/
            $j_w['m_id'] = $_POST['j_m_id'];
            $j_w['status']  = array('neq',9);
            $j_wallet = $member_obj->where($j_w)->field("wallet,m_id,account,nick_name")->limit(1)->find();

            /**添加用户的账单明细*/
            $m_bill_data['title'] = "转账记录！";
            $m_bill_data['content'] = date('Y-m-d H:i:s',time())."转给账号为：".$j_wallet['account']."的用户".$_POST['price']."众享豆！";
            $m_bill_data['ctime'] = time();
            $m_bill_data['m_id'] = $_POST['m_id'];
            $m_bill_data['monitor'] = 1;
            $m_bill_data['type'] = 1;
            $m_bill_data['name'] = $j_wallet['nick_name'];
            $m_bill_data['id_type'] = 0;
            $m_bill_data['price'] = $_POST['price'];
            $m_bill_data['accept_m_id'] = $_POST['j_m_id'];
            $m_bill = M("Bill")->add($m_bill_data);
            /**添加消息记录(转账人的)*/
            $mess_data['title'] = "转账记录！";
            $mess_data['content'] = date('Y-m-d H:i:s',time())."转给账号为：".$j_wallet['account']."的用户".$_POST['price']."众享豆！";
            $mess_data['m_id'] = $_POST['m_id'];
            $mess_data['ctime'] = time();
            $mess_data['price'] = $_POST['price'];
            $mess_data['z_type'] = 1;
            $mess_data['order_mix_id'] = $_POST['j_m_id'];
            $message_res_m = M("Message")->add($mess_data);

            /**修改接受转账用户的钱包信息*/
            $j_data['wallet'] = $j_wallet['wallet']+$_POST['price'];
            $j_res = $member_obj->where($j_w)->limit(1)->save($j_data);
            /**添加用户的账单明细*/
            $j_bill_data['title'] = "转账记录！";
            $j_bill_data['content'] = date('Y-m-d H:i:s',time())."收到账号为：".$m_wallet['account']."的用户转账".$_POST['price']."众享豆！";
            $j_bill_data['m_id'] = $j_wallet['m_id'];
            $j_bill_data['ctime'] = time();
            $j_bill_data['monitor'] = 0;
            $j_bill_data['type'] = 1;
            $j_bill_data['name'] = $m_wallet['nick_name'];
            $j_bill_data['id_type'] = 0;
            $j_bill_data['price'] = $_POST['price'];
            $j_bill_data['accept_m_id'] = $_POST['m_id'];
            $j_bill = M("Bill")->add($j_bill_data);

            /**添加消息记录(接受转账的)*/
            $j_mess_data['title'] = "转账记录！";
            $j_mess_data['content'] = date('Y-m-d H:i:s',time())."收到账号为：".$m_wallet['account']."的用户转账".$_POST['price']."众享豆！";
            $j_mess_data['m_id'] = $j_wallet['m_id'];
            $j_mess_data['ctime'] = time();
            $j_mess_data['price'] = $_POST['price'];
            $j_mess_data['z_type'] = 1;
            $j_mess_data['order_mix_id'] = $_POST['m_id'];
            $message_res_j = M("Message")->add($j_mess_data);

            if($m_res && $j_res && $m_bill && $j_bill && $message_res_m && $message_res_j){
                M()->commit();
                /**转账成功，给接受转账的人推送消息*/
                try{
                    if($m_wallet['is_set'] == 1){
                        $this->push('',$_POST['j_m_id'],'转账成功',"收到账号为：".$m_wallet['account']."的用户转账".$_POST['price']."众享豆！",array('mess_id'=>$message_res_j,'b_id'=>$j_bill,'type'=>1),0);
                    }
                }catch (\Exception $e){
                    apiResponse("success","转账成功！");
                }
                apiResponse("success","转账成功！");
            }else{
                M()->rollback();
                apiResponse("error","转账失败！请联系管理员！");
            }
        }
    }

    /**
     * 给商家转账
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     */
    public function transferShop(){
        if(!IS_POST){
            /**先展示商家的信息*/
            $w['account'] = I("get.account");
            $w['status'] = array('neq',9);
            $res = M("Shop")->where($w)->field("shop_id,account,name,head_pic")->limit(1)->find();
            /**转账人的信息*/
            $z_w['m_id'] = $_GET['m_id'];
            $wallet = M("Member")->where($z_w)->getField("wallet");
            $res['show_wallet'] = $wallet?$wallet:"0";
            if($res){
                apiResponse("success","获取成功！",$res);
            }else{
                apiResponse("error","获取失败！");
            }

        }else{
            /**用户转账(转账的人)*/
            $member_obj = M("Member");
            $shop_obj = M("Shop");
            M()->startTrans();
            $w['m_id'] = $_POST['m_id'];
            $m_wallet = $member_obj->where($w)->field('wallet,account,nick_name,m_id')->limit(1)->find();
            if(empty($m_wallet)){
                apiResponse("error","参数错误！");
            }
            $data['wallet'] = $m_wallet['wallet']-$_POST['price'];
            if($data['wallet']<0){
                apiResponse('error',"账号众享豆不足，最多只能转".$m_wallet['wallet']);
            }
            $m_res = $member_obj->where($w)->limit(1)->save($data);

            /**接受转账的人*/
            $j_w['shop_id'] = $_POST['shop_id'];
            $j_w['status']  = array('neq',9);
            $j_wallet = $shop_obj->where($j_w)->field("deduct,is_set,openid,province,city,area,wallet,shop_id,account,name,scale_p,scale_member,earn_total,total,integral,piles")->limit(1)->find();
            /**商家扣除手续费的比例值*/
            $commission = 1-(($j_wallet['scale_p']+$j_wallet['scale_member'])/100);
            $bl_price = sprintf("%.2f",$commission*$_POST['price']);
            $other_price = sprintf("%.2f",(($j_wallet['scale_p']+$j_wallet['scale_member'])/100)*$_POST['price']);

            /**通过商家id获取商家的省市区的id，转账就是买单，所以要记录买单的记录*/
            $data_order['order_sn'] = date('YmdHis').mt_rand(100000,999999);
            $data_order['price'] = sprintf("%.2f",$commission*$_POST['price']);
            $data_order['other_price'] = sprintf("%.2f",(($j_wallet['scale_p']+$j_wallet['scale_member'])/100)*$_POST['price']);
            $data_order['province'] = $j_wallet['province'];
            $data_order['city'] = $j_wallet['city'];
            $data_order['area'] = $j_wallet['area'];
            $data_order['ip'] = get_client_ip();
            $data_order['pay_type'] = 0;
            $data_order['ctime'] = time();
            $data_order['m_id'] = $_POST['m_id'];
            $data_order['shop_id'] = $_POST['shop_id'];
            $data_order['total_price'] = $_POST['price'];
            $res_order = M("Order")->add($data_order);
            /**添加用户的账单明细*/
            $m_bill_data['title'] = "转账记录！";
            $m_bill_data['content'] = date('Y-m-d H:i:s',time())."转给账号为：".$j_wallet['account']."的商家".$_POST['price']."众享豆！";
            $m_bill_data['ctime'] = time();
            $m_bill_data['m_id'] = $_POST['m_id'];
            $m_bill_data['monitor'] = 1;
            $m_bill_data['type'] = 1;
            $m_bill_data['id_type'] = 1;
            $m_bill_data['name'] = $j_wallet['name'];
            $m_bill_data['price'] = $_POST['price'];
            $m_bill_data['accept_m_id'] = $_POST['shop_id'];
            $m_bill = M("Bill")->add($m_bill_data);

            /**添加消息记录(转账人的)*/
            $mess_data['title'] = "转账记录！";
            $mess_data['content'] = date('Y-m-d H:i:s',time())."转给账号为：".$j_wallet['account']."的账户".$_POST['price']."众享豆！";
            $mess_data['m_id'] = $_POST['m_id'];
            $mess_data['ctime'] = time();
            $mess_data['price'] = $_POST['price'];
            $mess_data['order_mix_id'] = $_POST['shop_id'];
            $message_res_m = M("Message")->add($mess_data);

            /**修改接受转账商家的钱包信息*/
            $j_data['wallet'] = floatval($j_wallet['wallet'])+floatval($bl_price);
            $j_res = $shop_obj->where($j_w)->limit(1)->save($j_data);
            /**添加商家的账单明细*/
            $j_bill_data['title'] = "转账记录！";
            $j_bill_data['content'] = date('Y-m-d H:i:s',time())."收到账号为：".$m_wallet['account']."的用户转账".$bl_price."众享豆！";
            $j_bill_data['m_id'] = $j_wallet['shop_id'];
            $j_bill_data['ctime'] = time();
            $j_bill_data['monitor'] = 0;
            $j_bill_data['type'] = 1;
            $j_bill_data['name'] = $m_wallet['nick_name'];
            $j_bill_data['id_type'] = 0;
            $j_bill_data['rank_type'] = 1;
            $j_bill_data['accept_m_id'] = $_POST['m_id'];
            $j_bill_data['price'] = $bl_price;
            $j_bill_data['total_price'] = $_POST['price'];
            $j_bill = M("Bill")->add($j_bill_data);

            /**添加消息记录(接受人)*/
            $j_mess_data['title'] = "转账记录！";
            $j_mess_data['content'] =  date('Y-m-d H:i:s',time())."收到账号为：".$m_wallet['account']."的用户转账".$bl_price."众享豆！运营费用：".$other_price;
            $j_mess_data['m_id'] =  $_POST['shop_id'];
            $j_mess_data['ctime'] = time();
            $j_mess_data['price'] = $_POST['price'];
            $j_mess_data['id_type'] = 1;
            $j_mess_data['order_mix_id'] = $_POST['m_id'];
            $message_res_j = M("Message")->add($j_mess_data);


            /**给商家计算股份*/
            $meet_pay_price = M("Config")->getField("meet_pay_price");
            $shop_price_earn = sprintf("%.2f",(($j_wallet['deduct'])/100)*$_POST['price']);
            if($j_wallet['deduct'] == 0 || $j_wallet['deduct'] == 0.00){
                $invest_res_trans_shop = 1;
            }else {
                if ((floatval($shop_price_earn) + floatval($j_wallet['integral'])) >= $meet_pay_price) {
                    /**满足就添加一股*/
                    $a_shop = ((floatval($shop_price_earn)) + floatval($j_wallet['integral'])) / $meet_pay_price;
                    /**添加商家的股数*/
                    $shop_x_pie = floor($a_shop) - $j_wallet['piles'];
                    for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                        $pie_data['mix_id'] = $_POST['shop_id'];
                        $pie_data['pie'] = 1;
                        $pie_data['ctime'] = time();
                        $pie_data['type'] = 1;
                        M("Pie")->add($pie_data);
                    }
                    $after_data_shop['piles'] = floor($a_shop);
                    $after_data_shop['integral'] = floatval($j_wallet['integral']) + floatval($shop_price_earn);
                    $after_data_shop['utime'] = time() + 2;
                    $invest_res_trans_shop = $shop_obj->where(array('shop_id' => $_POST['shop_id']))->limit(1)->save($after_data_shop);
                } else {
                    unset($after_data_shop);
                    $after_data_shop['integral'] = floatval($j_wallet['integral']) + floatval($shop_price_earn);
                    $after_data_shop['utime'] = time() + 1;
                    $invest_res_trans_shop = M("Shop")->where(array('shop_id' => $_POST['shop_id']))->limit(1)->save($after_data_shop);
                }
            }

            /**添加商家的提成的比例的和,商家的要减去费用也就是提点的费用*/
            $shop_total = sprintf("%.2f",(($j_wallet['scale_p']+$j_wallet['scale_member'])/100)*$_POST['price']);
            $total_data_shop['total'] = floatval($shop_total)+floatval($j_wallet['total']);
            $total_data_shop['utime'] = time()+12;
            $total_res_shop = $shop_obj->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($total_data_shop);
            //file_put_contents('1.txt',$m_res.','.$j_res.','.$m_bill.','.$j_bill.','.$message_res_m.','.$message_res_j.','.$invest_res_trans_shop.','.$res_order.','.$total_res_shop);

            /**添加公司的收入平台费*/
            $price_data_x['price'] = sprintf("%.2f",($j_wallet['scale_p']/100)*$_POST['price']);
            $price_data_x['shop_id'] = $_POST['shop_id'];
            $price_data_x['province'] = $j_wallet['province'];
            $price_data_x['city'] = $j_wallet['city'];
            $price_data_x['area'] = $j_wallet['area'];
            $price_data_x['ctime'] = time();
            $price = M("Price")->add($price_data_x);
            /**添加资金池*/
            $pool_data['price'] = sprintf("%.2f",($j_wallet['scale_member']/100)*$_POST['price']);
            $pool_data['shop_id'] = $_POST['shop_id'];
            $pool_data['m_id'] = $_POST['m_id'];
            $pool_data['province'] = $j_wallet['province'];
            $pool_data['city'] = $j_wallet['city'];
            $pool_data['area'] = $j_wallet['area'];
            $pool_data['ctime'] = time();
            $pool = M("Pool")->add($pool_data);

            if($m_res && $j_res && $m_bill && $j_bill && $message_res_m && $message_res_j && $invest_res_trans_shop && $res_order && $total_res_shop && $price && $pool){
                /**向缓存中添加新的用户的数据*/
                $list1 = S("MEMBER_ORDER");
                $x_string = $list1?"会员".$m_wallet['nick_name']."消费了:".$_POST['price']."元".",".$list1:"".","."会员".$m_wallet['nick_name']."消费了:".$_POST['price']."元";
                S("MEMBER_ORDER",$x_string);
                $tem_price = $_POST['price'];
                $time = date('Y-m-d H:i:s',time());
                $s_name = $j_wallet['name'];
                $nick_name = $m_wallet['nick_name'];
                $data_to = array(
                    'first'=>array('value'=>urlencode("您好,您有新的款项到账，请注意查收！")),
                    'keyword1'=>array('value'=>urlencode("$time")),
                    'keyword2'=>array('value'=>urlencode("$nick_name")),
                    'keyword3'=>array('value'=>urlencode("转账")),
                    'keyword4'=>array('value'=>urlencode("转账消费")),
                    'keyword5'=>array('value'=>urlencode("$tem_price 众享豆")),
                    'Remark'=>array('value'=>urlencode("$time,用户 $nick_name 转了 $tem_price 众享豆")),
                );
                $url = $_SERVER['SERVER_NAME'];
                $this->wxSetSend($j_wallet['openid'],"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","http://$url/index.php/Merchant/Shop/billlist/p/1/shop_id/".$_POST['shop_id'],$data_to);
                //file_put_contents('tem_a.txt',$res);
                M()->commit();

                /**转账成功，给接受转账的商家推送消息*/
                try{
                    if($j_wallet['is_set'] == 1){
                        $this->push('',$_POST['shop_id'],'转账成功',
                            "您好,您有{$tem_price}元到账，请注意查收！",array('mess_id'=>$message_res_j,'b_id'=>$j_bill,'type'=>1),1);
                    }
                }catch (\Exception $e){
                    apiResponse("success","转账成功！");
                }
                apiResponse("success","转账成功！");
            }else{
                M()->rollback();
                apiResponse("error","转账失败！请联系管理员！");
            }
        }
    }

    /**
     * 收藏
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户的id
     * @param shop_id 商家的id
     */
    public function collect(){
        $data['m_id'] = $_POST['m_id'];
        $data['shop_id'] = $_POST['shop_id'];
        $data['status'] = array('neq',9);
        /**是否已经收藏了*/
        $collect_is = M("Collect")->where($data)->getField("c_id");
        if($collect_is){
            apiResponse("error","此商家已经被收藏了！");
        }else{
            $data['ctime'] = time();
            $res = D("Collect")->add($data);
            if($res){
                apiResponse("success","收藏成功！",$res);
            }else{
                apiResponse("error","收藏失败！");
            }
        }

    }

    /**
     * 我的收藏列表
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户的id
     */
    public function collectList(){
        $m_id = $_GET['m_id'];
        $w = "AND zxty_collect.m_id = $m_id AND zxty_collect.status <> 9";
        $lat = $_REQUEST['lat']?$_REQUEST['lat']:"39.058971";
        $lnt = $_REQUEST['lnt']?$_REQUEST['lnt']:"117.116138";
        $p = $_GET['p'];
        $p = ($p-1)*15;
        $list = M()->query("Select zxty_collect.c_id,zxty_collect.shop_id,zxty_shop.head_pic,zxty_shop.sale,zxty_shop.lat,zxty_shop.lnt,zxty_shop.is_open,
        zxty_shop.class_id,zxty_shop.area,zxty_shop.name,zxty_shop.address,zxty_shop.is_open,zxty_shop.shop_id,zxty_shop.grade_icon,
        zxty_shop.star,ROUND( 6378.138 * 2 * ASIN( SQRT( POW( SIN(($lat * PI() / 180 - lat * PI() / 180 ) / 2 ), 2 ) + COS($lat * PI() / 180)
         * COS(lat * PI() / 180) * POW( SIN(( $lnt * PI() / 180 - lnt * PI() / 180 ) / 2 ), 2 ))) * 1000 ) AS distance
         from zxty_collect,zxty_shop where zxty_collect.shop_id = zxty_shop.shop_id $w ORDER BY distance ASC  limit $p,15");
        foreach($list as $k=>$v){
            $area = M("Areas")->where(array('area_id'=>$v['area']))->getField("area_name");
            $list[$k]['area_name'] = empty($area)?"":$area;
//            $list[$k]['sale'] = M("Order")->where(array('shop_id'=>$v['shop_id']))->count();
            $class_name = M("Class")->where(array('class_id'=>$v['class_id']))->getField("name");
            $list[$k]['class_name'] = empty($class_name)?"":$class_name;
            $list[$k]['grade_icon'] = $v['grade_icon']?C("API_URL").'/'.$v['grade_icon']:C("API_URL")."/Uploads/Shop/Grade/default.png";
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
     * 取消收藏
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  c_id  收藏的id
     */
    public function delCollect(){
        $w['c_id'] = $_GET['c_id'];
        $data['status'] = 9;
        $res = D("Collect")->where($w)->save($data);
        if($res){
            apiResponse("success","取消收藏成功！");
        }else{
            apiResponse("error","取消收藏失败！");
        }
    }

    /**
     * 我推荐的人
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户得id
     */
    public function recommend(){
        $w['recommend'] = $_GET['m_id'];
        $w['status'] = array('NEQ',9);
        $p = $_GET['p'];
        $p = ($p-1)*15;
        $list = D("Member")->where($w)->field('account,head_pic,ctime,nick_name')->order("ctime desc")->limit($p,15)->select();
        foreach ($list as $kk=>$vv){
            $list[$kk]['time'] = date("Y-m-d H:i:s",$vv['ctime']);
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
     * 我的推荐的商家
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户得id
     */
    public function recommendShop(){
        $w['recommend'] = $_GET['m_id'];
        $w['status'] = array('NEQ',9);
        $p = $_GET['p'];
        $p = ($p-1)*15;
        $list = D("Shop")->where($w)->field('account,head_pic,ctime,name')->order("ctime desc")->limit($p,15)->select();
        foreach ($list as $kk=>$vv){
            $list[$kk]['time'] = date("Y-m-d H:i:s",$vv['ctime']);
            $list[$kk]['nick_name'] = $vv['name'];
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
     * 评价商家
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户得id
     * @param  shop_id  商家的id
     * @param  star  评价的星级
     */
    public function appraise(){
        $data['m_id'] = $_POST['m_id'];
        $data['shop_id'] = $_POST['shop_id'];
        $data['star'] = $_POST['star'];
        $data['ctime'] = time();
        $res = M("Appraise")->add($data);
        if($res){
            apiResponse("success","评价成功！");
        }else{
            apiResponse("error","评价失败！");
        }
    }

    /**
     * 我的评价
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户得id
     * @param  shop_id  商家的id
     */
    public function myAppraise(){
        if($_GET['shop_id']){
            $w['shop_id'] = $_GET['shop_id'];
        }else{
            $w['m_id'] = $_GET['m_id'];
        }
        $p = ($_GET['p'] - 1)*15;
        $w['status'] = array('not in','0,9');
        $list = M("Appraise")->where($w)->limit($p,15)->order("ctime desc")->select();
        //$count = M("Appraise")->where($w)->count();
        $arr = array();
        foreach ($list as $k=>$v){
            /**获取公司的名称*/
            $shop = M("Shop")->where(array('shop_id'=>$v['shop_id']))->field('name,head_pic')->find();
            $arr[$k]['shop_name'] = $shop['name'];
            $arr[$k]['shop_head_pic'] = C("API_URL").'/'.$shop['head_pic'];
            $arr[$k]['app_id'] = $v['app_id'];
            $arr[$k]['star'] = $v['star'];
            $arr[$k]['time'] = date("m/d H:i:s",$v['ctime']);
            $arr[$k]['content'] = $v['content'];
            /**获取用户的名称*/
            $member = M("Member")->where(array('m_id'=>$v['m_id']))->field("head_pic,nick_name")->find();
            $arr[$k]['mem_name'] = $member['nick_name'];
            $arr[$k]['head_pic'] = $this->returnPic($member['head_pic']);
            /**商家回复和用户回复的信息*/
            $res_list = D("ReplyAppraise")->where(array('app_id'=>$v['app_id']))->field("m_name,r_m_name,content")->select();
            $arr[$k]['list'] = $res_list?$res_list:[];
            $pics = [];
            if($v['pic']){
                $pics = explode(',',$v['pic']);
                foreach($pics as $key=>$val){
                        $pics[$key] = C('API_URL').'/'.$val;
                }
            }
            $arr[$k]['pic'] = $pics?$pics:[];
        }
        //$arr['count'] = $count;
        if(empty($arr) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$arr);
        }elseif ($arr){
            apiResponse("success","获取成功！",$arr);
        }elseif(empty($arr)){
            apiResponse("success","无数据！");
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 我的评价
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户得id
     * @param  shop_id  商家的id
     */
    public function myAppAppraise(){
        if($_GET['shop_id']){
            $w['shop_id'] = $_GET['shop_id'];
        }else{
            $w['m_id'] = $_GET['m_id'];
        }
        $p = ($_GET['p'] - 1)*15;
        $w['status'] = array('not in','0,9');
        $list = M("Appraise")->where($w)->limit($p,15)->order("ctime desc")->select();
        $count = M("Appraise")->where($w)->count();
        $arr = array();
        $pics = [];
        foreach ($list as $k=>$v){
            /**获取公司的名称*/
            $arr[$k]['shop_name'] = M("Shop")->where(array('shop_id'=>$v['shop_id']))->getField('name');
            $arr[$k]['app_id'] = $v['app_id'];
            $arr[$k]['star'] = $v['star'];
            $arr[$k]['time'] = date("m/d H:i:s",$v['ctime']);
            $arr[$k]['content'] = $v['content'];
            /**获取用户的名称*/
            $member = M("Member")->where(array('m_id'=>$v['m_id']))->field("head_pic,nick_name")->find();
            $arr[$k]['mem_name'] = $member['nick_name'];
            $arr[$k]['head_pic'] = $member['head_pic'];
            /**商家回复和用户回复的信息*/
            $res_list = D("ReplyAppraise")->where(array('app_id'=>$v['app_id']))->field("m_name,r_m_name,content")->select();
            $arr[$k]['list'] = $res_list?$res_list:[];
            if($v['pic']){
                $pics = explode(',',$v['pic']);
                foreach($pics as $key=>$val){
                    $pics[$key] = C('API_URL').'/'.$val;
                }
            }
            $arr[$k]['pic'] = $pics?$pics:[];
        }
        $list_arr['list'] = $arr;
        $list_arr['count'] = $count;
        if(empty($list_arr) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$list_arr);
        }elseif ($list_arr){
            apiResponse("success","获取成功！",$list_arr);
        }elseif(empty($list_arr)){
            apiResponse("success","无数据！");
        }else{
            apiResponse("error","获取失败！");
        }
    }


    /**
     * 转账的消费记录
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户得id
     */
    public function transferRecord(){
        $w['status'] = 0;
        $w['m_id'] = $_GET['m_id'];
        $w['type'] = 1;
        $w['rank_type'] = 0;
        $p = ($_GET['p'] - 1) * 15;
        $list = M("Bill")->where($w)->order("ctime desc")->limit($p,15)->field("b_id,m_id,title,price,ctime,id_type,accept_m_id")->select();
        foreach ($list as $k=>$v){
           /**判断是商家还是用户，显示商家或者用户的名称和账号1:商家  0：用户*/
            if($v['id_type'] == 1){
                $other_res = M("Shop")->where(array('shop_id'=>$v['accept_m_id']))->field("shop_id as mix_id,name,account,head_pic")->find();
                $other_res['head_pic'] = C("API_URL").'/'.$other_res['head_pic'];
            }else{
                $other_res = M("Member")->where(array('m_id'=>$v['accept_m_id']))->field("m_id as mix_id,nick_name,account,head_pic")->find();
                $other_res['head_pic'] = $this->returnPic($other_res['head_pic']);
                $other_res['name'] = $other_res['nick_name'];
            }
            $usable_wallet = M("Member")->where(['m_id'=>$_GET['m_id']])->getField("wallet");
            $list[$k]['usable_wallet'] = $usable_wallet?$usable_wallet:"0.00";
            $list[$k]['ctime'] = date("Y-m-d H:i:s",$v['ctime']);
            $list[$k]['other_message'] = $other_res?$other_res:[];
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
     * 判断这个用户或者这个商家是否存在
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  account  用户或者商家的id
     * @param type 0是用户 1是商家
     */
    public function is_set(){
        /**添加兑换商品的订单的信息*/
        if(!preg_match(C("MOBILE"),$_GET['account'])){
            apiResponse("error","手机号格式错误");
        }
        if($_GET['type'] == 1){
            $m_id = M("Member")->where(array("account"=>$_GET['account'],'status'=>array('neq',9)))->getField("m_id");
            /**判断是否是自己的给自己转账*/
            $account = M("Member")->where(array("m_id"=>$_GET['m_id'],'status'=>array('neq',9)))->getField("account");
            if($_GET['account'] == $account){
                apiResponse("error","不能给自己转账！");
            }
            if($m_id){
                apiResponse("success","该用户存在！");
            }else{
                apiResponse("error","该用户不存在！");
            }
        }else{
            $Shop = M("Shop")->where(array("account"=>$_GET['account'],'status'=>array('neq',9)))->getField("shop_id");
            if($Shop){
                apiResponse("success","该商家存在！");
            }else{
                apiResponse("error","该商家不存在！");
            }
        }
    }

    /**
     * 用户的股价
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param  m_id  用户的id
     */
    public function shares(){
        if(empty($_GET['m_id'])){
            $w['m_id'] = $_GET['shop_id'];
            $w['type'] = 1;
        }else{
            $w['m_id'] = $_GET['m_id'];
            $w['type'] = 0;
        }
        $p = ($_GET['p']-1)*15;
        $list = M("Invest_earn")->where($w)->limit($p,15)->field("m_id,price,surplus_price,status")->order("status asc,price asc")->select();
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
     * 用户端的股价的曲线图的数据
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     */
    public function income(){
        //判断起始时间
        $start_time = date('Y-m-d',(time()-intval(604800)));
        $end_time = date('Y-m-d',time()-86400);
        //横坐标赋值时间
        $x_res = $this->createX($start_time,$end_time);
        //平台收入统计
        $day = $this->getSalesByDay($start_time,$end_time);  //天
        $x_res['day_line'] = $day;
        if($x_res){
            apiResponse("success","获取成功！",$x_res);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 日活跃度
     */
    public function getSalesByDay($start_time,$end_time){
        //折线图数据 查询条件及对象
        $sales_line_p = array('title'=>'平台收入(元)','obj'=>D('day_divide'),'flag'=>array());
        //数据参数
        $line_parameter = array($sales_line_p);
        //获取数据
        $sales_line_data = $this->getLineData($start_time,$end_time,$line_parameter);
        return $sales_line_data;
        //创建折线
    }


    /**
     * 获取折线统计数据
     * @param $start_date
     * @param $end_date
     * @param $parameter  相关参数 包含 (标题  查询条件  对象)
     * @return mixed
     */
    public function getLineData($start_date,$end_date,$parameter){
        $start_date = strtotime($start_date);  //起始时间 时间戳转化
        $end_date   = strtotime($end_date);
        //获取统计值
        $data = array();
        for($i = 0; $i <= 6; $i++){
            foreach($parameter as $k => $value){
                $val = $value['obj']->field('price')->order('ctime desc')->limit($i,1)->select();
                $data[$k][] = $val[0]['price'];
            }
        }
        foreach ($data[0] as $k=>$v){
            if($v == null){
                $data[0][$k] = "0";
            }
        }
        krsort($data[0]);
        $string = implode(",",$data[0]);

        return $string;
    }


    /**
     * 创建横坐标
     * 2014-6-3
     * @param $start_date  开始时间  时间戳
     * @param $end_date  结束时间
     * @return array
     */
    public function createX($start_date,$end_date){
        $start_date = strtotime($start_date);  //起始时间 时间戳转化
        $end_date   = strtotime($end_date);
        //连个时间相同  就是一天
        if($start_date == $end_date){
            $day = 1;
        }else{
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400) : floor(($end_date-$start_date)/86400);
        }
        //创建横坐标显示
        $date = "";
        $d = array();
        for($i = 0; $i <= 6; $i++){
//            $d = date('d',intval($start_date) + intval($i*86400));
            $val = M('day_divide')->field('ctime')->order('ctime desc')->limit($i,1)->select();
            $d[] = $val[0]['ctime'];
        }
        asort($d);
        foreach ($d as $val){
            $date .= date('m/d',$val).",";
        }
        $x_date = substr($date,0,strlen($date)-1);
        return array('x_date'=>$x_date);
    }

    /**
     * 计算用户当天的消费总额
     * 传参方式 get
     * @time 2017-08-21
     * @author mss
     * @param $m_id 用户id
     */
    public function memberTotal(){
        $m_id = $_GET['m_id']; //用户id
        $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));   //当天开始时间
        $end = mktime(23,59,59,date('m'),date('d'),date('Y'));  //当天结束时间
        $where = array();
        $where['m_id'] = $m_id;
        $where['status'] = array('NEQ',9);
        $where['pay_type'] = array('GT',0);
        $where['ctime'] = array('BETWEEN',array($begin,$end));
        $total = M('Order')->where($where)->sum('total_price');
        if(!$total) $total = 0;
        $data['total'] = $total;
        apiResponse("success","获取成功！",$data);
    }




}