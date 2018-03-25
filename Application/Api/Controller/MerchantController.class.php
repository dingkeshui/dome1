<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 商家模块
 */
class MerchantController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
        $shop_id = session("SHOP_ID");
        if($shop_id){
            $this->assign("shop_id",$shop_id);
        }
    }


    /**
     * 昨日收益，昨日单数（商家端的首页的展示的数据）
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function index(){
        /**昨日的单数*/
        $order_obj = M("Order");
        $begin = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $end = mktime(23,59,59,date('m'),date('d')-1,date('Y'));
        $w['status'] = array("neq",9);
        $w['shop_id'] = $_POST['shop_id'];
        $w['ctime'] =array(array("EGT",$begin),array("ELT",$end),'AND');
        $count = $order_obj->where($w)->count();
        /**昨日的收益*/
        $count_price = $order_obj->where($w)->sum("price");
        $data['count_order'] = $count?$count:"0";;
        $data['sum_price'] = $count_price?$count_price:"0";
        /**商家的钱包*/
        $shop_obj = M("Shop");
        $wallet = $shop_obj->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->getField('wallet');
        $data['wallet'] = $wallet?$wallet:"0";
        /**商家的积分*/
        $integral = $shop_obj->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->getField('integral');
        $data['integral'] = $integral?$integral:"0";
        $is_open = M('HxUser')->where(array('m_id'=>$_POST['shop_id'],'type'=>1))->limit(1)->getField('h_id');
        $data['is_open'] = $is_open?$is_open:'';
        apiResponse("success","获取成功！",$data);
    }
    


    /**
     * 商家签到
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function sign(){
        if(empty($_POST['shop_id'])){
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
        if(empty($_POST['shop_id'])){
            apiResponse("error","参数错误！");
        }
        $sign_obj = M("Sign");
        /**判断这个用户是今天是否签到了*/
        $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
        $where['m_id'] = $_POST['shop_id'];
        $where['type'] = 1;
        $where['sign_time'] = array(array('EGT',$begin),array('ELT',$end),'and');
        $res_sign = $sign_obj->where($where)->getField("id");
        if($res_sign){
            apiResponse("error","您今日已经签到！");
        }
        /**判断这个用户是否领取了*/
        $where_divide['m_id'] = $_POST['shop_id'];
        $where_divide['type'] = 1;
        $where_divide['ctime'] = array(array('EGT',$begin),array('ELT',$end),'and');
        $res_divide = M("Divide_log")->where($where_divide)->getField("id");
        if($res_divide){
            apiResponse("error","您今日已经签到！");
        }
        M()->startTrans();
        /**先判断用户是否在满足的条件下，这个地方要查找显示昨天之前的满足能消费的用户的标准*/
        $begin_other = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $invest_w['ctime'] = array('ELT',$begin_other);
        $invest_w['mix_id'] = $_POST['shop_id'];
        $invest_w['type'] = 1;
        $count = M("Pie")->where($invest_w)->count();
        if($count<=0){
            apiResponse("error","您暂不支持签到！");
        }
        /**找到用户每人可以领的钱数*/
        $total_obj = M("Total");
        $total_w['id'] = 1;
        $mem_price = $total_obj->where($total_w)->limit(1)->getField("mem_price");
        if($mem_price<=0 || $mem_price==0.00){
            apiResponse("error","抱歉今天暂无分成！");
        }
        $total_price = $total_obj->where($total_w)->limit(1)->getField("price");
        if($total_price<($mem_price*$count)){
            apiResponse("error","签到失败，请联系管理员！");
        }
        /**先更改用户的签到的时间戳*/
        $w['m_id'] = $_POST['shop_id'];
        $w['type'] = 1;
        $res = $sign_obj->where($w)->limit(1)->find();
        if($res){
            $data_sign['sign_time'] = time();
            $data_sign['number'] = $res['number']+1;
            $sign_res = $sign_obj->where($w)->limit(1)->save($data_sign);
        }else{
            unset($data_sign);
            $data_sign['sign_time'] = time();
            $data_sign['m_id'] = $_POST['shop_id'];
            $data_sign['type'] = 1;
            $data_sign['number'] = 1;
            $sign_res = $sign_obj->add($data_sign);
        }

        /**处理用户的股份的金额*/
        $shop_obj = M("Shop");
        $save_pie = 0;
        $res_pie_shop = 0;
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        $shop_w['shop_id'] = $_POST['shop_id'];
        $shop_find_res  = $shop_obj->where($shop_w)->limit(1)->find();
        if($shop_find_res['piles'] < $count){
            $price = $shop_find_res['piles']*$mem_price;
        }else{
            $price = $mem_price*$count;
        }
        /**处理用户领取的钱数，然后从新计算用户的积分，之后在从新计算用户的股数*/
        $a = (floatval($shop_find_res['integral'])-floatval($price))/$meet_pay_price;
        /**获取用户数据表里面存的股数*/
        $b = M("Pie")->where(array('mix_id'=>$_POST['shop_id'],'type'=>1))->count();
        /**减少商家的积分数*/
        $inter_data_change['integral'] = floatval($shop_find_res['integral'])-floatval($price);
        $inter_res_change = $shop_obj->where($shop_w)->limit(1)->save($inter_data_change);
        /**计算商家的股份*/
        if(floor($a)<$b){
            $c = $b-floor($a);
            $save_pie = M("Pie")->where(array('mix_id'=>$_POST['shop_id'],'type'=>1))->limit($c)->order("ctime desc")->delete();
            $pie_data_num['piles'] = floor($a);
            $pie_data_num['utime'] = time();
            $res_pie_shop = $shop_obj->where($shop_w)->limit(1)->save($pie_data_num);
        }elseif(floor($a)>$b){
            unset($c);
            $c = floor($a)-$b;
            for ($i=1;$i<=$c;$i++){
                $pie_data['mix_id'] = $_POST['shop_id'];
                $pie_data['pie'] = 1;
                $pie_data['type'] = 1;
                $pie_data['ctime'] = time();
                $save_pie = M("Pie")->add($pie_data);
            }
            $pie_data_num['piles'] = floor($a);
            $pie_data_num['utime'] = time();
            $res_pie_shop = $shop_obj->where($shop_w)->limit(1)->save($pie_data_num);
        }elseif (floor($a)==$b){
            $save_pie = 1;
            $res_pie_shop = 1;
        }
        /**商家钱包加钱*/
        $shop_data['wallet'] = floatval($shop_find_res['wallet'])+floatval($price);
        $shop_data['last_login_time'] = time();
        $shop_data['last_login_ip'] = get_client_ip();
        $shop_data['earn_price'] = $price;
        $shop_res = $shop_obj->where($shop_w)->limit(1)->save($shop_data);
        /**添加领钱的记录*/
        $divide_log_data['price'] = $price;
        $divide_log_data['m_id'] = $_POST['shop_id'];
        $divide_log_data['type'] = 1;
        $divide_log_data['ctime'] = time();
        $divide_log = M("divide_log")->add($divide_log_data);
        /**添加商家的账单的明细*/
        $m_bill_data['title'] = "每日收益！";
        $m_bill_data['content'] = date('Y-m-d H:i:s',time())."签到领取了:".$price."众享豆";
        $m_bill_data['ctime'] = time();
        $m_bill_data['m_id'] = $_POST['shop_id'];
        $m_bill_data['shop_id'] = $_POST['shop_id'];
        $m_bill_data['monitor'] = 0;
        $m_bill_data['type'] = 2;
        $m_bill_data['name'] = $shop_obj->where($shop_w)->limit(1)->getField("name");
        $m_bill_data['price'] = $price;
        $m_bill_data['rank_type'] = 1;
        $m_bill_data['id_type'] = 1;
        $m_bill_data['accept_m_id'] = $_POST['shop_id'];
        $m_bill = M("Bill")->add($m_bill_data);
        /**给商家发消息推送签到收益的信息*/
        $message_res_m = $this->addMessage($_POST['shop_id'],"每日收益！",date('Y-m-d-H:i:s',time())."签到领取了:".$price."众享豆",1,$price);
        /**减去资金池的金额*/
        $total_data['price'] = $total_price-($price);
        $total_j_res = $total_obj->where($total_w)->limit(1)->save($total_data);
//        dump($sign_res.','.$shop_res.','.$divide_log.','.$m_bill.','.$message_res_m.','.$total_j_res.','.$invest_res);
//        exit();
        if($sign_res&&$shop_res&&$divide_log&&$m_bill&&$message_res_m&&$total_j_res&&$inter_res_change&&$save_pie&&$res_pie_shop){
            M()->commit();
            apiResponse("success","签到成功！领取了:".$price."众享豆",$price);
        }else{
            M()->rollback();
            apiResponse("error","签到失败！");
        }
    }


    /**
     * 封装商家签到领取钱数，减持股份的操作
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param id 用户的id
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
                $is_two = M("invest_earn")->where(array('m_id'=>$id,'type'=>1,'status'=>array('neq',9)))->limit(1)->find();
                if($is_two){
                    /**计算这个钱数是减去了最少的一笔的钱数*/
                    $is_j_price = floatval($price)-floatval($res['price']);
                    /**计算剩下的钱数，然后计算能有多少个可以执行的数据*/
                    $b_shop = sprintf("%.2f",fmod((floatval($is_j_price)),$meet_pay_price_config));
                    /**满足就减持一股*/
                    $a_shop = floor((floatval($is_j_price))/$meet_pay_price_config);
                    //apiResponse('error',$is_j_price.'_'.$b_shop."_".$a_shop);
                    if($a_shop<1){
                        $is_two_other = M("invest_earn")->where(array('m_id'=>$id,'type'=>1,'status'=>array('neq',9)))->limit(1)->find();
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
                        $m_list = M("invest_earn")->where(array('m_id'=>$id,'type'=>1,'status'=>array('neq',9)))->limit(0,$a_shop)->select();
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
                            $is_two_other = M("invest_earn")->where(array('m_id'=>$id,'type'=>1,'status'=>array('neq',9)))->limit(1)->find();
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
                    $is_two = M("invest_earn")->where(array('m_id'=>$id,'type'=>1,'status'=>array('neq',9)))->limit(1)->find();
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
     * 商家的详情
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function detail(){
        $shop_id = $_POST['shop_id'];
        $w['shop_id'] = $shop_id;
        $res = M("Shop")->where($w)->field("shop_id,name,account,tel,address,time,notice,is_open,wallet,head_pic,code,piles,integral,star,legal_person,id_number,email,apply_no,q_end_time")->limit(1)->find();
        $member_bind['id_type'] = 1;
        $member_bind['mix_id'] = $_POST['shop_id'];
        $member_bind['status'] = array('neq',9);
        $member_with = M("Withdraw_bank")->where($member_bind)->order("ctime desc")->field("width_id,account,name")->find();
        $res['z_id'] = $member_with['width_id']?$member_with['width_id']:"";
        $res['z_account'] = $member_with['account']?$member_with['account']:"";
        $res['z_name'] = $member_with['name']?$member_with['name']:"";
        if($res['head_pic']){
            $res['img'] = C("API_URL").'/'.$res['head_pic'];
        }else{
            $res['img'] = C("API_URL").'/Uploads/logo.png';
        }
        /**判断这个用户是今天是否签到了*/
        $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
        $where['m_id'] = $_POST['shop_id'];
        $where['type'] = 1;
        $where['sign_time'] = array(array('EGT',$begin),array('ELT',$end),'and');
        $res_sign = M("Sign")->where($where)->getField("id");
        if($res_sign){
            $res['is_sign'] = 1;
        }else{
            $res['is_sign'] = 0;
        }
        /**先判断用户是否在满足的条件下，这个地方要查找显示昨天之前的满足能消费的用户的标准*/
        $begin_other = mktime(0,0,0,date('m'),date('d'),date('Y'));
        $invest_w['ctime'] = array('ELT',$begin_other);
        $invest_w['mix_id'] = $_POST['shop_id'];
        $invest_w['type'] = 1;
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
        $res['is_q_start'] = 0;
        $res['detailLink'] = "";
        /**判断商家是否开户环迅*/
        $is_open = M('HxUser')->where(array('m_id'=>$shop_id,'type'=>1))->limit(1)->getField('h_id');
        $is_open = !empty($is_open)?$is_open:'';
        $res['is_open_hx'] = $is_open;
        /**判断一下这个商家是否可以去签约*/
        if($res['apply_no'] && $res['q_end_time'] < time()){
            $res['is_q_start'] = 1;
        }else{
            if($res['apply_no']){
                $ht_obj = new HtController();
                $res['detailLink'] = $ht_obj->detailLink($res['apply_no']);
            }else{
                if(strtotime("2017-12-31") < time()){
                    $res['is_q_start'] = 1;
                }else{
                    $res['detailLink'] = "";
                }
            }
        }

        if($res){
            apiResponse("success","获取成功！",$res);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    public function detailLink(){
        $shop_id = $_POST['shop_id'];
        $w['shop_id'] = $shop_id;
        $res = M("Shop")->where($w)->field("apply_no,q_end_time")->limit(1)->find();
        $res['is_q_start'] = 0;
        $res['detailLink'] = "";
        /**判断一下这个商家是否可以去签约*/
        if($res['apply_no'] && $res['q_end_time'] < time()){
            $res['is_q_start'] = 1;
        }else{
            if($res['apply_no']){
                $ht_obj = new HtController();
                $res['detailLink'] = $ht_obj->detailLink($res['apply_no']);
            }else{
                if(strtotime("2017-12-31") < time()){
                    $res['is_q_start'] = 1;
                }else{
                    $res['detailLink'] = "";
                }
            }
        }
        if($res){
            apiResponse("success","获取成功！",$res);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 商家的登录
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param account 商家的账号
     * @param password 商家的登录的密码
     */
    public function login(){
        $w['account'] = $_POST['account'];
        $w['status'] = array('neq',9);
        $is_account = M("Shop")->where($w)->limit(1)->field("approve_time,shop_id,name,password,head_pic,
        openid,status,hx_name,hx_password,read_password,status,sign_status")->find();

        if(empty($is_account)){
            apiResponse("error","账号不存在，请联系管理员！");
        }
        $data_call["shop_id"] = $is_account["shop_id"];
        $data_call['hx_name'] = $is_account['hx_name'];
        $data_call['hx_password'] = $is_account['hx_password'];
        $data_call['nickname'] = $is_account['name'];
        $data_call['head_pic'] = $is_account['head_pic'];
        $data_call['status'] = $is_account['status'];
        $data_call['sign_status'] = $is_account['sign_status'];
        $data_call['is_readonly'] = 0;
        $data_call['approve_time'] = timeDiff(time(),$is_account['approve_time'])['day']-365<0?0:timeDiff(time(),$is_account['approve_time'])['day']-365;
        if($is_account['password'] == md5($_POST['password'])||$is_account['read_password'] == md5($_POST['password'])){
            $openid = session("OPENID");
            if ($is_account['openid'] == "" && $openid){
                $data['openid'] = $openid;
            }
            $unionid = session("UNIONID");
            if($is_account['unionid'] == "" && $unionid){
                $data['unionid'] = $unionid;
            }
            $data['last_login_time'] = time();
            $data['login_ip'] = get_client_ip();
            /**微信端传这个参数通知是否已经注册极光了，如果不为1就是微信登录，1就是app登录*/
            if($_POST['is_wechat'] != 1){
                $data['is_set'] = 1;
            }
            if(empty($is_account['hx_name'])&&empty($is_account['hx_password'])){
                $data['hx_name'] = date('YmdHis').mt_rand(100000,999999);
                $data['hx_password'] = $data['hx_name'];
                $this->createHXUser($data['hx_name'],$data['hx_password'],$is_account['name']);
                $data_call['hx_name'] = $data['hx_name'];
                $data_call['hx_password'] = $data['hx_password'];
                $data_call['nickname'] = $is_account['name'];
            }
            M("Shop")->where($w)->limit(1)->save($data);
            if($is_account['read_password'] == md5($_POST['password'])){
                $data_call['is_readonly'] = 1;
            }
            if($is_account['status'] == 1&&$is_account['sign_status']==0){
                apiResponse("success","请签约！",$data_call);
            }elseif($is_account['status'] == 1&&$is_account['sign_status']==1){
                apiResponse("error","账号审核中！",$data_call);
            }
            session("SHOP_ID",$is_account["shop_id"]);
            apiResponse("success",'登录成功！',$data_call);
        }else{
            apiResponse("error","账号密码错误！");
        }
    }

    /**
     * 换密码
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03  update:2017-12-15 crazy
     * @param account 商家的账号
     * @param new_password 商家的登录的新密码
     * @param r_password 确认输入的新密码
     */
    public function exchangePassword(){
        $w['type'] = "edit_pass";
        $w['way'] = $_POST['account'];
//        $w['vc'] = $_POST['vc'];
//        $res = M("UserOperate")->where($w)->find();
//        if(!$res){
//            apiResponse("error","验证码错误！");
//        }
        unset($w);
        $w['account'] = $_POST['account'];
        $w['status'] = array('neq',9);
        $res = M("Shop")->where($w)->limit(1)->find();
        if($res){
            $password = $_POST['new_password'];
            $r_password = $_POST['r_password'];
            if($password != $r_password){
                apiResponse("error",'两次输入的密码不一致！');
            }
            if($_POST['is_readonly']==1) {
                $data['read_password'] = md5($_POST['new_password']);
                $data['w_md5_pass'] = $_POST['new_password'];
            }else{
                $data['password'] = md5($_POST['new_password']);
            }
            $data['utime'] = time();
            $exc_res = M('Shop')->where($w)->limit(1)->save($data);
            if($exc_res){
                apiResponse("success","修改成功！");
            }else{
                apiResponse("error","修改失败！请联系管理员！");
            }
        }else{
            apiResponse("error","账号不存在");
        }
    }

    /**
     * 我的账单明细
     * 传参的方式 get
     * @author crazy
     * @time 2017-07-03
     * @param type  1转账  2:收益 3：消费 4：兑换   5：提现  6:退款  7：抽奖 8：订单结算  9商城买单
     * @param month 筛选的月份
     * @param shop_id 商家的id
     */
    public function billList(){

        if($_GET['type']){
            $w['type'] = $_GET['type'];
        }
        if($_GET['month']){
            $get_start_time = $_GET['month'].'-01'.' '.'00:00:00';
            $get_end_time = $_GET['month'].'-31'.' '.'23:59:59';
            $t1 = strtotime($get_start_time);
            $t2 = strtotime($get_end_time);
            $w['ctime'] = array(array('EGT',$t1),array('ELT',$t2),'and');
        }
        $w['rank_type'] = 1;
        $w['m_id'] = $_GET['shop_id'];
        $p = ($_GET['p'] - 1)*15;
        $order = "ctime desc";
        $list = M("Bill")->where($w)->limit($p,15)->order($order)->field("b_id,price,ctime,m_id,pay_type,monitor,shop_id,title,name,type,
        id_type,accept_m_id,other_price,total_price,status,is_appraise")->select();
        foreach ($list as $kk=>$vv){
            $list[$kk]['year_time'] = date("Y-m-d",$vv['ctime']);
            $list[$kk]['time'] = date("H:i",$vv['ctime']);
            /**如果是0的话就显示用户的头像*/
            if($vv['id_type'] == 1){
                $list[$kk]['head_pic'] = '/'.M("Shop")->where(array('shop_id'=>$vv['accept_m_id']))->getField("head_pic");
            }else{
                $list[$kk]['head_pic'] = M("Member")->where(array('m_id'=>$vv['accept_m_id']))->getField("head_pic");
            }
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
     * 找回密码
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param way  手机号
     * @param vc 短信的验证码
     * @param password 密码
     * @param r_password 确认密码
     */
    public function getPass(){
        /**设置密码*/
        $password = $_POST['password'];
        $r_password = $_POST['r_password'];
        if($password != $r_password){
            apiResponse("error","两次输入的密码不一致！");
        }
        $data['password'] = md5($_POST['password']);
        $data['utime'] = time();
        $shop_res = M("Shop")->where(array('account'=>$_POST['way'],'status'=>array('neq',9)))->limit(1)->save($data);
        if($shop_res){
            apiResponse("success","设置成功！");
        }else{
            apiResponse("error","设置失败！");
        }
    }

    /**
     * @param $code
     * @param string $id
     * @return bool
     * 验证码检验
     */
    public function check_verify($code,$id=''){
        $verify = new \Think\Verify();
        return $verify->check($code,$id);
    }

    /**
     * 绑定支付宝
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param type  支付宝或者是微信
     * @param account 账号
     * @param name 名称
     * @param id_type 0:用户 1：商家
     * @param mix_id 商家或者用户的id
     */
    public function bindPay(){
        $is_read = $_POST['is_readonly'];
        if($is_read==1){
            apiResponse("error","无操作权限！");
        }
        $data['type'] = $_POST['type'];
        $data['account'] = $_POST['account'];
        $data['name'] = $_POST['name'];
        $data['id_type'] = $_POST['id_type'];
        $data['mix_id'] = $_POST['mix_id'];
        $data['ctime'] = time();
        $res = M("WithdrawBank")->add($data);
        if($res){
            apiResponse("success","添加成功！");
        }else{
            apiResponse("error","绑定失败！");
        }
    }

    /**
     * 绑定的列表
     * 传参的方式 get
     * @author crazy
     * @time 2017-07-03
     * @param id_type 0:用户 1：商家
     * @param mix_id 商家或者用户的id
     */
    public function bindList(){
        $w['status'] = array('neq',9);
        $w['id_type'] = $_GET['id_type'];
        $w['mix_id'] = $_GET['mix_id'];
        $p = ($_GET['p']-1) * 15;
        $list = M("WithdrawBank")->where($w)->order("ctime desc")->field("width_id,account,type,name")->limit($p,15)->select();
        //apiResponse(M("WithdrawBank")->getLastSql());
        if(empty($list) && $_GET['p']>1){
            apiResponse("success","已加载全部！");
        }elseif (empty($list)){
            apiResponse("success","数据为空！");
        }elseif ($list){
            apiResponse("success","加载成功！",$list);
        }else{
            apiResponse("error","加载失败！");
        }
    }

    /**
     * 删除绑定的号
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param width_id 绑定银行卡的id
     */
    public function delBind(){
        $is_read = $_POST['is_readonly'];
        if($is_read==1){
            apiResponse("error","无操作权限！");
        }
        $w['width_id'] = $_POST['width_id'];
        $data['status'] = 9;
        $res = M("WithdrawBank")->where($w)->limit(1)->save($data);
        //file_put_contents('width.txt',$_POST['width_id'].'-----'.$res);
        if($res){
            apiResponse("success","删除成功！");
        }else{
            apiResponse("error","删除失败！");
        }
    }

    /**
     * 商家或者用户提现
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param mix_id 商家或者用户的id
     * @param type 0是用户  1是商家
     * @param price 提现的钱数
     */
    public function withDraw(){
        /**
         * 判断这个用户或者商家的钱包是否满足提现的人
         * @var type  0：用户   1：商家
         */
        if(empty($_POST['mix_id'])){
            apiResponse("error","参数错误！");
        }
        if(empty($_POST['price'])||$_POST['price']<=0){
            apiResponse("error",'提现金额需大于0元');
        }
        if ($_POST['type'] == 1){
            $is_read = $_POST['is_readonly'];
            if($is_read==1){
                apiResponse("error","无操作权限！");
            }
            $wallet = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->getField("wallet");
            if($wallet<$_POST['price']){
                apiResponse("error","提现金额不足！");
            }
        }elseif ($_POST['type'] == 0){
            /**查看用户满足多少元可以提现*/
            $full_price = M("Config")->getField("full_price");
            $wallet = M("Member")->where(array('m_id'=>$_POST['mix_id']))->getField("wallet");
            if($wallet<$full_price){
                apiResponse("error","满足".$full_price."元才能提现！");
            }
            if($wallet<$_POST['price']){
                apiResponse("error","提现金额不足！");
            }
        }
        /**找到用户提现需要的手续费*/
        $scale_mem = M("Config")->getField("scale_mem");
        $data['mix_id'] = $_POST['mix_id'];
        if($_POST['type'] == 1){
            $data['total_price'] = $_POST['price'];
            $data['price'] = $_POST['price'];
        }elseif ($_POST['type'] == 0){
            $data['total_price'] = $_POST['price'];
            $data['price'] = $_POST['price']*(1-($scale_mem/100));
            $data['other_price'] = sprintf('%.2f',$_POST['price']*($scale_mem/100));
        }
        /**获取绑定的支付宝的相关的信息*/
        $with_brand = M("Withdraw_bank")->where(array("width_id"=>$_POST['width_id']))->limit(1)->find();
        //apiResponse("error",M("Withdraw_bank")->getLastSql());
        $data['width_id'] = $_POST['width_id'];
        $data['type'] = $_POST['type'];
        $data['account'] = $with_brand['account'];
        $data['name'] = $with_brand['name'];
        $data['ctime'] = time();
        $data['withdraw_sn'] = date('YmdHis').mt_rand(100000,999999);
        $res = M("Withdraw")->add($data);
        //apiResponse("error",M("Withdraw")->getLastSql());
        if($res){
            if($_POST['type'] == 0){
                $nick_name = M("Member")->where(array('m_id'=>$_POST['mix_id']))->getField("nick_name");
                $this->sendMsg("13163177870",date("Y-m-d H:i:s")."用户".$nick_name."发起提现请求！，提现金额为：".$_POST['price']);
                /**用户减少钱数*/
                $wallet_member = M("Member")->where(array('m_id'=>$_POST['mix_id']))->getField("wallet");
                $member_data['wallet'] = floatval($wallet_member)-floatval($_POST['price']);
                M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($member_data);
                /**给用户添加账单*/
                $m_bill_data['title'] = "提现！";
                $m_bill_data['content'] = date('Y-m-d H:i:s',time())."提现".$_POST['price'].'元';
                $m_bill_data['ctime'] = time();
                $m_bill_data['m_id'] = $_POST['mix_id'];
                $m_bill_data['shop_id'] = 0;
                $m_bill_data['monitor'] = 1;
                $m_bill_data['type'] = 5;
                $m_bill_data['name'] = $nick_name;
                $m_bill_data['price'] = $_POST['price'];
                $m_bill_data['pay_type'] = 1;
                $m_bill_data['accept_m_id'] = $_POST['mix_id'];
                M("Bill")->add($m_bill_data);
            }elseif ($_POST['type'] == 1){
                $name = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->field("name,account")->find();
                $this->sendMsg("13163177870",date("Y-m-d H:i:s")."商家".$name['name'].",账号为：".$name['account'].",发起提现请求！，提现金额为：".$_POST['price']);
                /**商家减少钱数*/
                $wallet_shop = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->getField("wallet");
                $shop_data['wallet'] = floatval($wallet_shop)-floatval($_POST['price']);
                M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->save($shop_data);
                $m_bill_data_shop['title'] = "提现！";
                $m_bill_data_shop['content'] = date('Y-m-d H:i:s',time())."提现".$_POST['price'].'元';
                $m_bill_data_shop['ctime'] = time();
                $m_bill_data_shop['m_id'] = $_POST['mix_id'];
                $m_bill_data_shop['monitor'] = 1;
                $m_bill_data_shop['type'] = 5;
                $m_bill_data_shop['id_type'] = 1;
                $m_bill_data_shop['rank_type'] = 1;
                $m_bill_data_shop['name'] = $name;
                $m_bill_data_shop['price'] = $_POST['price'];
                $m_bill_data_shop['pay_type'] = 1;
                $m_bill_data_shop['accept_m_id'] = $_POST['mix_id'];
                M("Bill")->add($m_bill_data_shop);
            }
//            if($_POST['type'] = 0){
//                /**向缓存中添加用户的提现的数据*/
//                $list1 = S("MEMBER_WITH");
//                $x_string = $list1?$list1.","."用户".filterHtml($_POST['name'])."提现了".$_POST['price'].'元':"".","."用户".filterHtml($_POST['name'])."提现了".$_POST['price'].'元';
//                S("MEMBER_WITH",$x_string);
//            }
            apiResponse("success","提现申请成功，等待打款！");
        }else{
            apiResponse("error","提现失败！");
        }
    }


    /**
     * 商家的信息的修改
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function editShop(){
        if(empty($_POST['shop_id'])){
           apiResponse("error","参数错误！");
        }
        if($_POST['is_readonly']==1){
            apiResponse("error","无操作权限！");
        }
        $data['name'] = filterHtml(I('post.name'));
        $data['tel'] = filterHtml(I('post.tel'));
        $data['time'] = filterHtml(I('post.time'));
        $data['is_open'] = filterHtml(I('post.is_open'));
        $data['notice'] = filterHtml(I('post.notice'));
        if(!empty(I('post.province'))){
            $data['province'] = I('post.province');
        }
        if(!empty(I('post.city'))){
            $data['city'] = I('post.city');
        }
        if(!empty(I('post.area'))){
            $data['area'] = I('post.area');
        }
        
        if(filterHtml($_POST['address'])){
            $data['lnt'] = I('post.lnt')?I('post.lnt'):"";
            $data['lat'] = I('post.lat')?I('post.lat'):"";
            $data['address'] = filterHtml($_POST['address']);
        }
        $data['utime'] = time();
        $res = M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->limit(1)->save($data);
        if($res){
            apiResponse("success","修改成功！");
        }else{
            apiResponse("error","修改失败！");
        }
    }

    /**
     * 商家注册信息
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param account 商家的账号
     * @param vc 短信验证码
     * @param pic 上传的营业执照和门门头等图片
     * @param name 商家的名称
     * @param password 商家的密码
     * @param class_id 商家的分类
     * @param recommend 商家的推荐人
     * @param app_type app上传图片
     */
    public function shopRegister(){
        $app_type = $_POST['app_type'];
        $w['type'] = "register";
        $w['way'] = $_POST['account'];
        $w['vc'] = $_POST['vc'];
        $arr = $_POST['pic'];
        $res = M("UserOperate")->where($w)->find();
        if(!$res){
            apiResponse("error","验证码错误！");
        }
        /**先判断这个手机号是否被注册*/
        $is_register = M("Shop")->where(array("account"=>$_POST['account'],'status'=>array('neq',9)))->limit(1)->getField("shop_id");
        if($is_register){
            apiResponse("error","此手机号已经被注册！");
        }
        $is_register_member = 0;
        $recommend_num = 0;
        if($_POST['recommend']){
            if(strlen($_POST['recommend']) < 11){
                /**如果传推荐的商家，那么判断这个商家推荐码是否存在*/
                $recommend_num = M("Shop")->where(array("recommend_num"=>$_POST['recommend'],'status'=>array('neq',9)))->limit(1)->getField("shop_id");
                if(empty($recommend_num)){
                    apiResponse("error","推荐码不存在！！");
                }
            }else{
                /**如果传推荐的用户，那么判断这个用户是否存在*/
                $is_register_member = M("Member")->where(array("account"=>$_POST['recommend'],'status'=>array('neq',9)))->limit(1)->getField("m_id");
                if(empty($is_register_member)){
                    apiResponse("error","推荐用户不存在！！");
                }
            }
        }else{
            $is_register_member = 0;
        }

        $mobile = $_POST['account'];
        if(!preg_match(C('MOBILE'),$mobile)) {
            apiResponse("error","手机格式不正确！");
        }
        if(filterHtml($_POST['password'])!=filterHtml($_POST['re_password'])){
            apiResponse("error","两次输入的密码不一致！");
        }

        if($app_type == 1){
//            $a = $_FILES;
//            file_put_contents('B.txt',json_encode($a));
            $pic_arr = $this->uploadImgMore('Shop');
//            file_put_contents('a.txt',json_encode($pic_arr));
            if(!$pic_arr){
                apiResponse('error','图片上传失败！');
            }
//            file_put_contents('pic_head.txt',json_encode($pic_arr));
            $_pic = array();
            foreach ($pic_arr as $k=>$v){
                $_pic[] = 'Uploads/'.$v['savepath'].$v['savename'];
            }
            $string = implode(",",$_pic);
//            file_put_contents('pic.txt',$string);
        }else{
            $pic_arr = array();
            foreach ($arr as $k=>$v){
                $pic       = $v['pic'];
                $pic_name      = $v['pic_name'];
                $temp = explode('.',$pic_name);
                $ext = uniqid().'.'.end($temp);
                $base64    = substr(strstr($pic, ","), 1);
                $image_res = base64_decode($base64);
                $pic_link  = "Uploads/Shop/".date('Y-m-d').'/'.uniqid().'.jpg';
                $saveRoot = "Uploads/Shop/".date('Y-m-d').'/';
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
        }


        $data['province'] = $_POST['province']?$_POST['province']:0;
        $data['city'] = $_POST['city']?$_POST['city']:0;
        $data['area'] = $_POST['area']?$_POST['area']:0;
        $data['lnt'] = $_POST['lnt']?$_POST['lnt']:"";
        $data['lat'] = $_POST['lat']?$_POST['lat']:"";
        $data['address'] = I("post.address")?I("post.address"):"";
        $data['account'] = $mobile;
        $data['name'] = I("post.name")?I("post.name"):"";
        $data['password'] = md5(filterHtml($_POST['password']));
        $data['recommend'] = $is_register_member;
        $data['shop_comm_id'] = $recommend_num;
        $data['more_pic'] = $string?$string:"";
        $data['ctime'] = time();
        $data['status'] = 1;
        $data['class_id'] = $_POST['class_id'];
        $data['last_login_time'] = time();
        /**合同签约*/
        $data['legal_person'] = $_POST['legal_person']?$_POST['legal_person']:"";
        $data['id_number'] = $_POST['id_number']?$_POST['id_number']:"";
        $data['email'] = $_POST['email']?$_POST['email']:"";
        $openid = session("OPENID");
        if($openid){
            $data['openid'] = $openid;
        }
        $unionid = session("UNIONID");
        if($unionid){
            $data['unionid'] = $unionid;
        }
        $res = M("Shop")->add($data);
        unset($data);
        $data['code'] = $this->png($res);
        $data['hx_name'] = date('YmdHis').mt_rand(100000,999999);
        $data['hx_password'] = $data['hx_name'];
        $this->createHXUser($data['hx_name'],$data['hx_password'],$_POST['nickname']);
        $is_true = M("Shop")->where(array('shop_id'=>$res))->save($data);
        if($is_true){
            /**向缓存中添加新的用户的数据*/
            $list1 = S("SHOP_LIST");
            $x_string = $list1?"欢迎商家".filterHtml($_POST['name'])."入驻平台".",".$list1:"".","."欢迎商家".filterHtml($_POST['name'])."入驻平台";
            S("SHOP_LIST",$x_string);
            $return_data['shop_id']=$res;
            apiResponse("success","注册成功！",$return_data);
        }else{
            apiResponse("error","注册失败！");
        }

    }


    /**
     * 我的收藏列表(被用户收藏的列表)
     * 传参的方式 get
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function collectList(){
        $shop_id = $_GET['shop_id'];
        $w = "AND zxty_collect.shop_id = $shop_id AND zxty_collect.status <> 9";
        $p = $_GET['p'];
        $p = ($p-1)*15;
        $list = M()->query("Select zxty_collect.c_id,zxty_collect.shop_id,zxty_collect.ctime,zxty_member.head_pic,zxty_member.nick_name from zxty_collect,zxty_member where zxty_collect.m_id = zxty_member.m_id $w limit $p,15");
//        apiResponse(M()->getLastSql());
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
     * 生成商家二维码
     * @author crazy
     * @time 2017-07-03
     * @param $id 商家的id
     */
    public function png($id){
        vendor("phpqrcode.phpqrcode");
        /**用户的注册*/
        $Submit = new \Vendor\phpqrcode\QRcode();
        $data1 = C("API_URL")."/index.php/".'Pay/pay/shop_id/'.$id;
        //$data1 = "http://www.baidu.com";
        //dump($data);
        // 纠错级别：L、M、Q、H，也就是二维码可以覆盖的区域
        $level = 'H';
        // 点的大小：1到10,用于手机端4就可以了
        $size = 12;
        // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false
        $path = "Uploads/Shop/CodeV2/";
        // 生成的文件名
        $fileName = $path.time().rand(10000,99999).$size.'.png';
        $data['code'] = $fileName;
        //生成二维码图片
        $Submit->png($data1, $fileName, $level, $size , 1);
        $logo = 'Uploads/Shop/Code/logo.png';//准备好的logo图片
        $QR = $fileName;//已经生成的原始二维码图
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring(file_get_contents($QR));
            $logo = imagecreatefromstring(file_get_contents($logo));
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                $logo_qr_height, $logo_width, $logo_height);
            //输出图片
            $fileName1 = rand(10000,99999).'_'.$id.'.'.'png';
            imagepng($QR, "Uploads/Shop/CodeV2/".$fileName1);
            return "Uploads/Shop/CodeV2/".$fileName1;
        }
    }



    /**
     * 用户端的股价的曲线图的数据
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function income(){
        //$_POST['shop_id'] = 1;
        $shop_id = $_POST['shop_id'];
        //判断起始时间
        $start_time = date('Y-m-d',(time()-intval(518400)));
        $end_time = date('Y-m-d',time());
        //横坐标赋值时间
        $x_res = $this->createX($start_time,$end_time);
        //平台收入统计
        $day = $this->getSalesByDay($start_time,$end_time,$shop_id);  //天
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
    public function getSalesByDay($start_time,$end_time,$shop_id){
        //折线图数据 查询条件及对象
        $sales_line_p = array('title'=>'平台收入(元)','obj'=>D('Order'),'where'=>array("shop_id"=>$shop_id),'flag'=>array());
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
        if($start_date == $end_date){
            $day = 1;
        }else{
            //获取天数
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400) : floor(($end_date-$start_date)/86400);
        }
        //获取统计值
        for($i = 0; $i <= $day; $i++){
            foreach($parameter as $k => $value){
                $value['where']['ctime'] = array('between',($start_date + $i * 86400).",".($start_date + ($i+1) * 86400));
                $data[$k][] = $value['obj']->where($value['where'])->sum('price');
            }
        }
        foreach ($data[0] as $k=>$v){
            if($v == null){
                $data[0][$k] = "0";
            }
        }
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
        for($i = 0; $i <= $day; $i++){
            $d = date('d',intval($start_date) + intval($i*86400));
            $date .= "$d,";
        }
        $x_date = substr($date,0,strlen($date)-1);
        return array('x_date'=>$x_date);
    }


    /**
     * 选择省级
     * @time 2017-09-05
     * @author crazy
     */
    public function getProvince(){
        //获取所有省份
        $pro = M("areas")->where(array('parent_id'=>1))->field('area_id,area_name')->order('sort_order DESC,area_id ASC')->select();
        apiResponse("success","获取成功！",$pro);
    }

    /**
     * 选择市级
     * @time 2017-09-05
     * @author crazy
     */
    public function getCity(){
        //获取城市
        $city = M("areas")->where(array('parent_id'=>$_POST['province']))->field('area_id,area_name')->select();
        apiResponse("success","获取成功！",$city);
    }

    /**
     * 选择市级
     * @time 2017-09-05
     * @author crazy
     */
    public function getArea(){
        //获取地区
        $area = M("areas")->where(array('parent_id'=>$_POST['city']))->field('area_id,area_name')->select();
        apiResponse("success","获取成功！",$area);
    }

    /**
     * 商家轮播图，Android平板专用接口
     * 传参方式 get
     * @author mss
     * @time 2017-11-14
     * @param shop_id 商家id
     */
    public function shopBanners(){
        if (empty($_GET['shop_id'])){
            apiResponse('error','参数错误');
        }
        $s_id = $_GET['shop_id'];
        $data = array();
        $string = M('Shop')->where(array('shop_id'=>$s_id))->limit(1)->getField('pic');
        if($string){
            $data = explode(',',$string);
            foreach ($data as $k=>$v){
                $data[$k] = 'https://'.$_SERVER['HTTP_HOST'].'/'.$v;
            }
            apiResponse('success','成功',$data);
        }else{
            apiResponse('success','暂无数据');
        }

    }


    


    



}