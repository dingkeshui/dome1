<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 资金池
 */
class PoolController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 定时任务（计算一天的股价，分成的钱数）
     * @author crazy
     * @time 2017-07-03
    */
    public function managePool(){
        $time = time();
        $config = M("Config")->field('is_on,start_time,end_time')->find();
        if($config['is_on'] == 1 && $time>$config['start_time'] && $time< 1519315200){
            exit;
        }

        M("Pool")->startTrans();
        M("Total")->startTrans();
        M("Day_divide")->startTrans();
        M("Scale")->startTrans();
        /**获取昨天的时间戳*/
        $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $endYesterday=mktime(23,59,59,date('m'),date('d')-1,date('Y'));
        /**获取能分成的用户的数量*/
        $member_w['ctime'] = array('ELT',$endYesterday);
        $member_count = M("Pie")->where($member_w)->count();
        /**获取系统设置的最多领取的钱数*/
        $scale_price = M("Config")->getField("share_scale");
        /**获取资金池的昨天的交易额的总和*/
        $pool_w['is_count'] = array('neq',9);
        $pool_w['ctime'] = array(array('EGT',$beginYesterday),array('ELT',$endYesterday),'AND');
        $pool_sum = M("Pool")->where($pool_w)->sum("price")?M("Pool")->where($pool_w)->sum("price"):"0";
        if($pool_sum<=0){
            $pool_status = 1;
        }else{
            /**符合条件的去除*/
            $pool_data['is_count'] = 9;
            $pool_status = M("Pool")->where($pool_w)->save($pool_data);
        }
        /**把交易额存到大的资金池里面*/
        /**先找到这个资金池的金额*/
        $total_w['id'] = 1;
        $total_price = 0;
        /**判断一下这个值是否大于设定的最大的值，如果大于这个值我们就要判断一下每个人领取的钱数不能大于我们设定的值*/
        $is_glt_scale_price = sprintf("%.2f",((floatval($pool_sum))/$member_count));
        if($is_glt_scale_price>$scale_price){
            $total_data['price'] = sprintf("%.2f",((floatval($scale_price))*$member_count));
            /**计算多出来的那一部分钱数，也就是超出我们设定的最高的钱数剩下的一分部钱数*/
            $zj_price_sum = floatval($pool_sum)-floatval($total_data['price']);
            /**找到备用资金池的钱数*/
            $by_price = M("Scale")->where(array('id'=>1))->limit(1)->getField('price');
            $share_scale_data['price'] = floatval($by_price)+floatval($zj_price_sum);
            M("Scale")->where(array('id'=>1))->limit(1)->save($share_scale_data);
        }elseif ($is_glt_scale_price == $scale_price){
            $total_data['price'] = sprintf("%.2f",((floatval($is_glt_scale_price))*$member_count));
        }elseif ($is_glt_scale_price<$scale_price){
            /**这种是领取的钱数超过预设定的钱数，然后把多余的钱数放在备用池子里面*/
            /**小于设定的钱数就要从资金池里面去找补差值*/
            $diff_value = floatval($scale_price)-floatval($is_glt_scale_price);
            /**从备用资金池里面计算钱数*/
            $diff_value_zj = $diff_value*$member_count;
            $zj_price_value = M("Scale")->where(array('id'=>1))->getField('price');
            if($diff_value_zj<=$zj_price_value){
                /**这种是备用池子里面的钱数还有很多，然后可以拿出来一部分的钱数给分成*/
                $total_data['price'] = floatval($diff_value_zj)+floatval($pool_sum);
                /**备用资金池的处理*/
                $by_price = M("Scale")->where(array('id'=>1))->limit(1)->getField('price');
                $share_scale_data['price'] = floatval($by_price)-floatval($diff_value_zj);
                M("Scale")->where(array('id'=>1))->limit(1)->save($share_scale_data);
            }elseif($diff_value_zj>$zj_price_value){
                /**这种情况是需要补得差价备用池里面没有那么多的钱数，所以直接把备用池子的钱数给了分成*/
                $total_data['price'] = floatval($zj_price_value)+floatval($pool_sum);
                /**备用资金池的处理*/
                $share_scale_data['price'] = 0;
                M("Scale")->where(array('id'=>1))->limit(1)->save($share_scale_data);
            }
        }
        $total_data['last_price'] = $total_price;
        $total_data['interval'] = $pool_sum;
        $total_data['utime'] = time();
        $one = M("Total")->where($total_w)->limit(1)->save($total_data);
        $two = 0;
        $mem_y_price = floor(((floatval($total_data['price']))/$member_count)*1000);
        if($one){
            /**计算用户每个人能领取的钱数*/
            $mem_price['mem_price'] =  floatval($mem_y_price)/1000;
            $mem_price['utime'] = time();
            $mem_price['ctime'] = time();
            $two = M("Total")->where($total_w)->limit(1)->save($mem_price);
        }
        /**记录每天分成的钱数，后面统计使用,如果是没用用户满足这个领取钱数的规定，那么这个股价的这个钱数就设置为默认值10*/
        $day_divide_data['price'] = (floatval($mem_y_price)/1000)?(floatval($mem_y_price)/1000):$scale_price;
        $day_divide_data['ctime'] = mktime(03,0,0,date('m'),date('d')-1,date('Y'));
        $day_divide = M("Day_divide")->add($day_divide_data);
        if($one && $two && $day_divide && $pool_status){
            M("Pool")->commit();
            M("Total")->commit();
            M("Day_divide")->commit();
            M("Scale")->commit();
            /**发送邮箱和发送短信*/
            $body = date("Y-m-d H:i:s").'昨天交易额：'.$pool_sum."元,能领取奖励的人数为：".$member_count."个,每人能领取的钱数为：".(floatval($mem_y_price)/1000)."元，当前的资金池金额为：".$total_data['price']."元";
            $this->sendMsg("18522713541",$body);
//            $res = SendMail("476319748@qq.com",$body,$body);
        }else{
            M("Pool")->rollback();
            M("Total")->rollback();
            M("Day_divide")->rollback();
            M("Scale")->rollback();
        }
    }


    /**测试*/
    public function test(){
//        dump(S("151310193413374"));
        //file_put_contents("1.txt","crontab");
        $body ="111";
        $res = SendMail("476319748@qq.com",$body,$body);
        dump($res);
    }


    /**
     * 我的提现记录表
     * 传参的方式 get
     * @author crazy
     * @time 2017-07-03
     * @param type:1：是用户 2：是商家
     */
    public function withList(){
        if($_GET['type'] == 1){
            $w['type'] = 0;
        }elseif ($_GET['type'] == 2){
            $w['type'] = 1;
        }
        $w['mix_id'] = $_GET['mix_id'];
        $p = ($_GET['p']-1)*15;
        $list = M("Withdraw")->where($w)->order("ctime desc")->limit($p,15)->field("w_id,mix_id,ctime,name,account,pay_type,status,total_price")->select();
        //apiResponse(M("WithDraw")->getLastSql());
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
     * 驳回的修改支付宝的账号或者姓名
     * 传参的方式 post
     * @author crazy
     * @time 2017-07-03
     * @param w_id:提现的id
     */
    public function editWith(){
        if($_POST['is_readonly']==1){
            apiResponse("error", "无操作权限");
        }
        M()->startTrans();
        $w['w_id'] = $_POST['w_id'];
        $res = M("Withdraw")->where($w)->limit(1)->find();
        /**找到绑定提现的的ID的支付宝的账号密码*/
        $w_b['width_id'] = $res['width_id'];
        $w_data_bank['account'] = $_POST['account'];
        $w_data_bank['name'] = $_POST['name'];
        $with_res = M("WithdrawBank")->where($w_b)->limit(1)->save($w_data_bank);
        /**修改当前提现记录的支付宝的账号和名称*/
        $w_data_bank['status'] = 0;
        $with_bank_res = M("Withdraw")->where($w)->limit(1)->save($w_data_bank);
        //apiResponse(M("WithdrawBank")->getLastSql().','.M("Withdraw")->getLastSql());
        if($with_res && $with_bank_res){
            M()->commit();
            apiResponse("success","修改成功！");
        }else{
            M()->rollback();
            apiResponse("error","修改失败！");
        }
    }

    /**
     * 商家和用户的总消费已经应该返的钱数
     * @author crazy
     * @time 2017-07-03
     * @param 1用户：type  2商家：type
     */
    public function pilesOther(){
        if($_POST['type'] == 1){
            $arr = M("Member")->where(array('m_id'=>$_POST['mix_id']))->field("	total,earn_total")->limit(1)->find();
            $meet_pay_price = M("Config")->getField("meet_pay_price");
            $arr['earn_total'] = floatval($meet_pay_price)-floatval($arr['earn_total']);
            apiResponse("success",'获取成功！',$arr);
        }elseif ($_POST['type'] == 2){
            $arr = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->field("total,earn_total")->limit(1)->find();
            $meet_pay_price = M("Config")->getField("meet_pay_price");
            $arr['earn_total'] = floatval($meet_pay_price)-floatval($arr['earn_total']);
            apiResponse("success",'获取成功！',$arr);
        }else{
            apiResponse("error",'标识错误！');
        }
    }


    /**
     * 把当前的用户的数据转换成我们现在的思路下的数据
     * 首先要把用户的积分转换到integer里面
     *  然后把股票算进去
     */
    public function tranTest(){
        $w['total'] = array('neq',0);
        $w['status'] = array('neq',9);
        $list = M("Member")->where($w)->select();
        foreach ($list as $k=>$v){
            /**找到用户的股份*/
            $g_w['type'] = 0;
            $g_w['status'] = array('neq',9);
            $g_w['m_id'] = $v['m_id'];
            $invest_price = M('invest_earn')->where($g_w)->sum('price');
            /**计算积分*/
            /**计算用户的股数*/
            $g_w1['type'] = 0;
            $g_w1['m_id'] = $v['m_id'];
            $count = M('invest_earn')->where($g_w1)->count();
            $data['integral'] = $invest_price+floatval($v['total'])-floatval($count*500);
            M("Member")->where(array('m_id'=>$v['m_id']))->limit(1)->save($data);
            /**计算股份*/
            $a = floor(floatval($data['integral'])/500);
            $data1['piles'] = $a;
            M("Member")->where(array('m_id'=>$v['m_id']))->limit(1)->save($data1);
            /**添加股份*/
            for ($i=1;$i<=floor($a);$i++){
                $pie_data['mix_id'] = $v['m_id'];
                $pie_data['pie'] = 1;
                $pie_data['ctime'] = time();
                $pie_data['type'] = 0;
                M("Pie")->add($pie_data);
            }
        }
    }

    /**
     * 把当前的用户的数据转换成我们现在的思路下的数据
     * 首先要把商家的积分转换到integer里面
     *  然后把股票算进去
     */
    public function tranTestShop(){
        $w['total'] = array('neq',0);
        $w['status'] = array('neq',9);
        $list = M("Shop")->where($w)->select();
        foreach ($list as $k=>$v){
            /**找到用户的股份*/
            $g_w['type'] = 1;
            $g_w['status'] = array('neq',9);
            $g_w['m_id'] = $v['shop_id'];
            $invest_price = M('invest_earn')->where($g_w)->sum('price');
            /**计算积分*/
            /**计算商家的股数*/
            $g_w1['type'] = 1;
            $g_w1['m_id'] = $v['shop_id'];
            $count = M('invest_earn')->where($g_w1)->count();
            $data['integral'] = $invest_price+floatval($v['total'])-floatval($count*500);
            M("Shop")->where(array('shop_id'=>$v['shop_id']))->limit(1)->save($data);
            /**计算股份*/
            $a = floor(floatval($data['integral'])/500);
            $data1['piles'] = $a;
            M("Shop")->where(array('shop_id'=>$v['shop_id']))->limit(1)->save($data1);
            /**添加股份*/
            for ($i=1;$i<=floor($a);$i++){
                $pie_data['mix_id'] = $v['shop_id'];
                $pie_data['pie'] = 1;
                $pie_data['ctime'] = time();
                $pie_data['type'] = 1;
                M("Pie")->add($pie_data);
            }
        }
    }

    /**环迅支付测试*/
    public function xhTest(){
        file_put_contents('xh.txt',111);
    }



}