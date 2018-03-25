<?php
namespace Api\Controller;
use Think\Controller;
use Think\Upload;

/**
 * Class FeedbackController
 * @package Api\Controller
 * 滚动的数据
 */
class RollController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 缓存里面保存的用户的消费记录信息，商家的入住的信息，找到二十个用户提现的信息，找到二十个用户签到领取积分的信息
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function rollList(){
        /**以下数据会多余二十条的时候，将截取20条信息，然后存储在S里面*/
        /**找到二十个用户注册的用户*/
        $member_list = S("MEMBER_LIST");
        $arr1 = explode(",",$member_list);
        $arr_one = array_slice($arr1,0,19);
        $string = implode(',',$arr_one);
        S("MEMBER_LIST",$string);
        /**找到二十个消费的用户的信息*/
        $member_order = S("MEMBER_ORDER");
        $arr2 = explode(",",$member_order);
        $arr_two = array_slice($arr2,0,19);
        $string2 = implode(',',$arr_two);
        S("MEMBER_ORDER",$string2);
        /**找到二十个用户签到领取积分的信息*/
        $member_sign = S("MEMBER_SIGN");
        $arr3 = explode(",",$member_sign);
        $arr_third = array_slice($arr3,0,19);
        $string3 = implode(',',$arr_third);
        S("MEMBER_SIGN",$string3);
        /**找到二十个商家注册信息*/
        $shop_list = S("SHOP_LIST");
        $arr4 = explode(",",$shop_list);
        $arr_four = array_slice($arr4,0,19);
        $string4 = implode(',',$arr_four);
        S("SHOP_LIST",$string4);
        /**找到二十个用户提现的信息*/
        $member_with = S("MEMBER_WITH");
        $arr5 = explode(",",$member_with);
        $arr_five = array_slice($arr5,0,19);
        $string5 = implode(',',$arr_five);
        S("MEMBER_SIGN",$string5);
        $total = array_merge($arr_one,$arr_two,$arr_third,$arr_four,$arr_five);
        shuffle($total);
        apiResponse("success","获取成功！",$total);
    }


//    public function test(){
//        Vendor('Alipay.Alipay');
//        $refund_alipay = new \Alipay("1","2","3","4");
//        $refund_alipay->appPay();
//
//    }



    /**
     * 微信场景二维码
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function focusWechat(){
        /**判断用户是否已经有推荐的二维码*/
        $code = M("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->getField("code");
        if(empty($code) && $code ==0){
            $m_id = $_POST['m_id'];
            $access_toke = wx_get_token();
            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$access_toke";
            $qrcode = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": '.$m_id.'}}}';
            $res = $this->httpsRequest($url,$qrcode);
            $last_access = json_decode($res,true);
            //file_put_contents("1.txt",$res);
            if($last_access['errcode'] == '40001'){
                S('other_access_token_hb',null);
                $access_toke = wx_get_token();
                $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$access_toke";
                $qrcode = '{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": '.$m_id.'}}}';
                $res = $this->httpsRequest($url,$qrcode);
            }
            //file_put_contents("3.txt",3);
            $res_ticket = json_decode($res, true);
            $ticket_other = $res_ticket['ticket'];
            //file_put_contents("4.txt",$ticket_other);
            $url1 = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($ticket_other);
            $imageInfo = $this->downloadWeixinFile($url1);
            $filename = "Uploads/Member/Code/".$m_id.'.jpg';
            $local_file = fopen($filename,'w');
            $res = fwrite($local_file,$imageInfo['body']);
            fclose($local_file);
            if($res){
                $data['code'] = $filename;
                $data['utime'] = time();
                D("Member")->where(array('m_id'=>$_POST['m_id']))->limit(1)->save($data);
                //file_put_contents("1.txt",$res_member);
                apiResponse("success","获取成功！",$filename);
            }else{
                //file_put_contents("2.txt","2");
                apiResponse("error","生成失败！");
            }
        }else{
            apiResponse("success","获取成功！",$code);
        }
    }


    /**
     * 下载图片
     * @author crazy
     * @time 2017-07-03
    */
    public function downloadWeixinFile($url){
        $ch  = curl_init($url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_NOBODY,0); //只取body头
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        $imageAll = array_merge(array('body'=>$package),array('header'=>$httpinfo));
        return $imageAll;
    }


    /**
     * 抽奖
     * @time 20170728
     * @author ：刘柱
     */
    /**
     * 经典的概率算法，
     * $proArr是一个预先设置的数组，
     * 假设数组为：array(100,200,300，400)，
     * 开始是从1,1000 这个概率范围内筛选第一个数是否在他的出现概率范围之内，
     * 如果不在，则将概率空间，也就是k的值减去刚刚的那个数字的概率空间，
     * 在本例当中就是减去100，也就是说第二个数是在1，900这个范围内筛选的。
     * 这样 筛选到最终，总会有一个数满足要求。
     * 就相当于去一个箱子里摸东西，
     * 第一个不是，第二个不是，第三个还不是，那最后一个一定是。
     * 这个算法简单，而且效率非常 高，
     * 关键是这个算法已在我们以前的项目中有应用，尤其是大数据量的项目中效率非常棒。
     */
    public function get_rand($proArr) {
        $result = '';
        //概率数组的总概率精度
        $proSum = array_sum($proArr);
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset ($proArr);
        return $result;
    }

    /**
     * 奖项数组
     * 是一个二维数组，记录了所有本次抽奖的奖项信息，
     * 其中id表示中奖等级，prize表示奖品，v表示中奖概率。
     * 注意其中的v必须为整数，你可以将对应的 奖项的v设置成0，即意味着该奖项抽中的几率是0，
     * 数组中v的总和（基数），基数越大越能体现概率的准确性。
     * 本例中v的总和为100，那么平板电脑对应的 中奖概率就是1%，
     * 如果v的总和是10000，那中奖概率就是万分之一了。
     *
     */
    public function draw(){
        /**找到当前能抽奖的奖品*/
        $w['is_show'] = 1;
        $prize_arr = M('Draw')->where($w)->field('d_id,img,name,value,type,v')->order('d_id asc')->select();
        $arr = array();
        $pr = array();
        foreach ($prize_arr as $key => $val) {
            $arr[$key] = $val['v'];
        }
        $rid = $this->get_rand($arr); //根据概率获取奖项id

        $res['yes'] = $prize_arr[$rid]; //中奖项

        // unset($prize_arr[$rid-1]); //将中奖项从数组中剔除，剩下未中奖项
        // shuffle($prize_arr); //打乱数组顺序
        // for($i=0;$i<count($prize_arr);$i++){
        //     $pr[] = $prize_arr[$i]['name'];
        // }
        //$res['no'] = $pr;

        return $res;
    }

    /**
     * 抽奖方法
     * 传参的方式 post
     * @time 2017-07-30
     * @author crazy
     * @param mix_id 用户或者商家的id
     * @param type 0是用户  1是商家
    */
    public function raffle(){
        if(!in_array($_POST['type'],array(0,1))){
            apiResponse('error','参数错误！');
        }
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误！');
        }
        if($_POST['is_readonly']==1){
            apiResponse("error", "无操作权限");
        }
        /**开启事务*/
        M()->startTrans();
        /**获取抽奖要消耗的积分*/
        $expend_inter = M("Config")->getField('draw_int');
        /**获取到抽取的奖项*/
        $draw = $this->draw();
        $res_last_piles = 0;
        $inter_res = 0;
        $mess_res = 0;
        $wallet_res = 0;
        $is_draw = 0;
        /**测试*/
        switch ($_POST['type']){
            case 0:
                $inter = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->field("integral,wallet,nick_name")->find();
                if($expend_inter>$inter['integral']){
                    apiResponse('error',"积分不足！");
                }
                /**添加账单明细*/
                $time = date("Y-m-d-H:i:s",time());
                /**当获取的是众享豆，再显示在账单明细里面*/
                /**判断获取的奖项是麦穗还是众享豆*/
                if($draw['yes']['type'] == 1){
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter)+floatval($draw['yes']['value']);
                    $inter_res = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    /**处理用户的股份*/
                    $res_last_piles = $this->reduce(0,$_POST['mix_id'],$data['integral']);
                    $wallet_res = 1;
                    /**添加信息*/
                    $draw_value = $draw['yes']['value'];
                    $this->addMessage($_POST['mix_id'],"恭喜您！抽取了 $draw_value 麦穗！","恭喜您！ $time 消耗 $expend_inter 麦穗抽奖，抽取了 $draw_value 麦穗！",
                        0,$draw_value,0,0,2);
                    /**判断是否还够抽奖的积分数*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                }elseif($draw['yes']['type'] == 2){
                    unset($data);
                    $wallet_data['wallet'] = floatval($inter['wallet'])+floatval($draw['yes']['value']);
                    $wallet_res = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($wallet_data);
                    $draw_value = $draw['yes']['value'];
                    /**添加账单明细*/
                    $this->addBill($_POST['mix_id'],$_POST['mix_id'],"恭喜您！抽取了 $draw_value 众享豆！",
                        "恭喜您！ $time 消耗 $expend_inter 麦穗抽奖，抽取了 $draw_value 众享豆！",
                    $draw_value,0,0,4,$inter['nick_name'],7,0,$_POST['mix_id'],0,$draw_value);
                    /**添加信息*/
                    $this->addMessage($_POST['mix_id'],"恭喜您！抽取了 $draw_value 众享豆！","恭喜您！ $time 消耗 $expend_inter 麦穗抽奖，抽取了 $draw_value 众享豆！",
                        0,$draw_value,0,0,2);
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter);
                    $inter_res = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    /**处理用户的股份*/
                    $res_last_piles = $this->reduce(0,$_POST['mix_id'],$data['integral']);
                    /**判断是否还够抽奖的积分数*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                }elseif($draw['yes']['type'] == 5){
                    /**抽中优惠券（mss 修改）*/
                    unset($data);
                    /**减去用户积分*/
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter);
                    $inter_res = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    /**处理用户的股份*/
                    $res_last_piles = $this->reduce(0,$_POST['mix_id'],$data['integral']);
                    $wallet_res = 1;
                    /**判断积分是否能抽奖*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                    /**增加用户优惠券记录*/
                    $data_cou['coupon_id'] = $draw['yes']['value'];
                    $data_cou['m_id'] = $_POST['mix_id'];
                    $data_cou['ctime'] = time();
                    M('CouponMember')->add($data_cou);

                }else{
                    unset($data);
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter);
                    $inter_res = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    /**处理用户的股份*/
                    $res_last_piles = $this->reduce(0,$_POST['mix_id'],$data['integral']);
                    $wallet_res = 1;
                    /**判断积分是否能抽奖*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                }
                /**添加信息*/
                $mess_res = $this->addMessage($_POST['mix_id'],"消耗 $expend_inter 麦穗抽奖！","$time 消耗 $expend_inter 麦穗抽奖！",
                    0,$expend_inter,0,0,2);
                unset($data);
                /**添加中奖记录*/
                $data['mix_id'] = $_POST['mix_id'];
                $data['mix_name'] = $inter['nick_name'];
                $data['d_id'] = $draw['yes']['d_id'];
                $data['name'] = $draw['yes']['name'];
                $data['value'] = $draw['yes']['value'];
                $data['ctime'] = time();
                $data['inter'] =  $expend_inter;
                D("draw_log")->add($data);
                break;
            case 1:
                unset($data);
                $inter = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->field("integral,wallet,name")->find();
                if($expend_inter>$inter['integral']){
                    apiResponse('error',"积分不足！");
                }
                /**添加账单明细*/
                $time = date("Y-m-d-H:i:s",time());
                /**判断获取的奖项是麦穗还是众享豆*/
                if($draw['yes']['type'] == 1){
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter)+floatval($draw['yes']['value']);
                    $inter_res = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    $wallet_res = 1;
                    /**处理商家的股份*/
                    $res_last_piles = $this->reduce(1,$_POST['mix_id'],$data['integral']);
                    /**添加信息*/
                    $draw_value = $draw['yes']['value'];
                    $this->addMessage($_POST['mix_id'],"恭喜您！抽取了 $draw_value 麦穗！","恭喜您！ $time 消耗 $expend_inter 麦穗抽奖，抽取了 $draw_value 麦穗！",
                        1,$draw_value,0,0,2);
                    /**判断积分是否能抽奖*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                }elseif($draw['yes']['type'] == 2){
                    $wallet_data['wallet'] = floatval($inter['wallet'])+floatval($draw['yes']['value']);
                    $wallet_res = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->save($wallet_data);
                    $draw_value = $draw['yes']['value'];
                    /**添加账单明细*/
                    $this->addBill($_POST['mix_id'],$_POST['mix_id'],"恭喜您！抽取了 $draw_value 众享豆！","恭喜您！ $time 消耗 $expend_inter 麦穗抽奖，抽取了 $draw_value 众享豆！",
                        $draw_value,0,0,4,$inter['name'],7,1,$_POST['mix_id'],1,$draw_value);
                    /**添加信息*/
                    $this->addMessage($_POST['mix_id'],"恭喜您！抽取了 $draw_value 众享豆！","恭喜您！ $time 消耗 $expend_inter 麦穗抽奖，抽取了 $draw_value 众享豆！",
                        1,$draw_value,0,0,2);
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter);
                    $inter_res = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    /**处理用户的股份*/
                    $res_last_piles = $this->reduce(1,$_POST['mix_id'],$data['integral']);
                    /**判断积分是否能抽奖*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                }elseif($draw['yes']['type'] == 5){
                    /**抽中优惠券（mss 修改）*/
                    $this->raffle();
                }else{
                    $data['integral'] = floatval($inter['integral'])-floatval($expend_inter);
                    $inter_res = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->save($data);
                    /**处理用户的股份*/
                    $res_last_piles = $this->reduce(1,$_POST['mix_id'],$data['integral']);
                    $wallet_res = 1;
                    /**判断积分是否能抽奖*/
                    if($data['integral'] > $expend_inter){
                        $is_draw = 1;
                    }
                }
                /**添加信息*/
                $mess_res = $this->addMessage($_POST['mix_id'],"消耗 $expend_inter 麦穗抽奖！","$time 消耗 $expend_inter 麦穗抽奖！",
                    1,$expend_inter,0,0,2);
                /**添加中奖记录*/
                $data['mix_id'] = $_POST['mix_id'];
                $data['mix_name'] = $inter['name'];
                $data['d_id'] = $draw['yes']['d_id'];
                $data['name'] = $draw['yes']['name'];
                $data['value'] = $draw['yes']['value'];
                $data['type'] = 1;
                $data['ctime'] = time();
                $data['inter'] =  $expend_inter;
                D("draw_log")->add($data);
                break;
            default:
                apiResponse('error',"类型参数错误！");
        }
        if($res_last_piles&&$mess_res&&$inter_res&&$wallet_res){
            M()->commit();
            $return_data['data'] = $draw['yes'];
            $return_data['data']['is_draw'] = $is_draw;
            apiResponse('success',"抽奖成功！",$return_data);
        }else{
            M()->rollback();
            apiResponse("error",'抽奖失败！');
        }
    }

    /**
     * 计算用户或者商家的麦穗，然后计算他还有几股
     * @time 2017-07-30
     * @author crazy
     * @param type 0是用户 1是商家
     * @param mix_id:用户或者商家的id
     * @param inter：总的积分数
     */
    public function reduce($type,$mix_id,$inter){
        $res_last_piles = 0;
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        switch ($type){
            case 0:
                /**计算有多少股*/
                $num = floatval($inter)/$meet_pay_price;
                /**获取用户数据表里面存的股数*/
                $b = M("Pie")->where(array('mix_id'=>$mix_id,'type'=>0))->count();
                if(floor($num)<$b){
                    $c = $b-floor($num);
                    M("Pie")->where(array('mix_id'=>$mix_id,'type'=>0))->limit($c)->order("ctime desc")->delete();
                    $last_piles['piles'] = floor($num);
                    $last_piles['utime'] = time()+2;
                    $res_last_piles = M("Member")->where(array('m_id'=>$mix_id))->limit(1)->save($last_piles);
                }elseif(floor($num)>$b){
                    $c = floor($num)-$b;
                    for ($i=1;$i<=$c;$i++){
                        $pie_data['mix_id'] = $mix_id;
                        $pie_data['pie'] = 1;
                        $pie_data['ctime'] = time();
                        M("Pie")->add($pie_data);
                    }
                    $last_piles['piles'] = floor($num);
                    $last_piles['utime'] = time()+3;
                    $res_last_piles = M("Member")->where(array('m_id'=>$mix_id))->limit(1)->save($last_piles);
                }else{
                    $res_last_piles = 1;
                }
                break;
            case 1:
                /**计算有多少股*/
                $num = floatval($inter)/$meet_pay_price;
                /**获取用户数据表里面存的股数*/
                $b = M("Pie")->where(array('mix_id'=>$mix_id,'type'=>1))->count();
                if(floor($num)<$b){
                    $c = $b-floor($num);
                    M("Pie")->where(array('mix_id'=>$mix_id,'type'=>1))->limit($c)->order("ctime desc")->delete();
                    $last_piles['piles'] = floor($num);
                    $last_piles['utime'] = time()+2;
                    $res_last_piles = M("Shop")->where(array('shop_id'=>$mix_id))->limit(1)->save($last_piles);
                }elseif(floor($num)>$b){
                    $c = floor($num)-$b;
                    for ($i=1;$i<=$c;$i++){
                        $pie_data['mix_id'] = $mix_id;
                        $pie_data['pie'] = 1;
                        $pie_data['type'] = 1;
                        $pie_data['ctime'] = time();
                        M("Pie")->add($pie_data);
                    }
                    $last_piles['piles'] = floor($num);
                    $last_piles['utime'] = time()+3;
                    $res_last_piles = M("Shop")->where(array('shop_id'=>$mix_id))->limit(1)->save($last_piles);
                }else{
                    $res_last_piles = 1;
                }
                break;
        }
        return $res_last_piles;
    }

    /**
     * 获取奖品的列表
     * 传参方式 post
     * @time 2017-07-31
     * @author crazy
     * @param mix_id 用户或者商家的id
     * @param type 0是用户 1是商家
     */
    public function drawList(){
        /**获取抽奖要消耗的积分*/
        $expend_inter = M("Config")->getField('draw_int');
        /**找到当前能抽奖的奖品*/
        $w['status'] = array('neq',9);
        $prize_arr = M('Draw')->where($w)->field('d_id,img,name,value,type')->limit(8)->select();
        $data['list'] = $prize_arr;
        if($_POST['type'] == 0){
            $inter = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->getField('integral');
            if($inter < $expend_inter){
                $data['other_draw']['is_draw'] = 0;
            }else{
                $data['other_draw']['is_draw'] = 1;
            }
        }elseif ($_POST['type'] == 1){
            $inter = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->limit(1)->getField('integral');
            if($inter < $expend_inter){
                $data['other_draw']['is_draw'] = 0;
            }else{
                $data['other_draw']['is_draw'] = 1;
            }
        }
        /**获取抽奖记录的用户前20条数据*/
        $log_list = M('draw_log')->where(array('status'=>array('neq',9),'d_id'=>array('neq',1)))->field('mix_name,name')->order('ctime desc')->limit(20)->select();
        $data['other_draw']['log_list'] = $log_list?$log_list:"";
        if(empty($data)){
            apiResponse('error','数据为空！');
        }else{
            apiResponse("success","获取成功！",$data);
        }
    }


    public function test_index(){
        $w['city'] = 343;
        $w['status'] = array('neq',9);
        $w['position'] = 1;
        $list_one = M("Advert")->where($w)->field("pic,position,url,shop_id")->order('sort asc')->select();
        $list_two = M("Advert")->where(['is_quan'=>1,'position'=>1,'status'=>array('neq',9)])->field("pic,position,url,shop_id")->order('sort asc')->select();
        $list = array_merge($list_one,$list_two);
        dump($list);
    }


}