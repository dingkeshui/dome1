<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class BillController
 * @package Api\Controller
 * 菜场详情和商家以及商品的的详情模块
 */
class BillController extends ApiBasicController
{

    /**初始化*/
    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 我的收益
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function myEarnList(){
        $w['m_id'] = $_GET['m_id'];
        $earn = M("Member")->where($w)->getField("earn_price");
        $arr = explode(".",$earn);
        /**获取用户的收益的列表信息*/
        $p = ($_GET['p'] - 1)*15;
        if($_GET['time']){
            $get_start_time = $_GET['time'].'-01'.' '.'00:00:00';
            $get_end_time = $_GET['time'].'-31'.' '.'23:59:59';
            $t1 = strtotime($get_start_time);
            $t2 = strtotime($get_end_time);
            $w['ctime'] = array(array('EGT',$t1),array('ELT',$t2),'and');
        }
        $w['type'] = 2;
        $w['rank_type'] = 0;
        $order = "ctime desc";
        $list = M("Bill")->where($w)->limit($p,15)->order($order)->field("price,ctime")->select();
        foreach ($list as $kk=>$vv){
            $list[$kk]['ctime'] = date("Y-m-d H:i:s",$vv['ctime']);
        }
        $count_price =  M("Bill")->where($w)->sum("price");
        $bill['price'] = $arr;
        $bill['list'] = $list?$list:[];
        $bill['count_price'] = $count_price?$count_price:0;
        if(empty($bill) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$bill);
        }elseif ($bill){
            apiResponse("success","获取成功！",$bill);
        }elseif(empty($bill)){
            apiResponse("success","无数据！",[]);
        }else{ 
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 我的账单明细
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     * @param type 账单的详情的分类 ：1转账  2:收益 3：消费 4：兑换   5：提现  6:退款  7：抽奖 9商城商品买单  10商城退款
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
        $w['rank_type'] = 0;
        $w['m_id'] = $_GET['m_id'];
        $p = ($_GET['p'] - 1)*15;
        $order = "ctime desc";
        $list = M("Bill")->where($w)->limit($p,15)->order($order)->field("back_price,return_price,b_id,price,ctime,m_id,pay_type,
        monitor,shop_id,title,name,type,id_type,accept_m_id,other_price,status")->select();
        foreach ($list as $kk=>$vv){
            $list[$kk]['year_time'] = date("m-d",$vv['ctime']);
            $list[$kk]['time'] = date("H:i",$vv['ctime']);
            $list[$kk]['return_price'] = $vv['back_price'];
            /**如果是0的话就显示用户的头像*/
            if($vv['id_type'] == 1){
                $shop_pic = M("Shop")->where(array('shop_id'=>$vv['accept_m_id']))->getField("head_pic");
                $list[$kk]['head_pic'] = $shop_pic?'/'.$shop_pic:"";
            }else{
                $mem_pic = M("Member")->where(array('m_id'=>$vv['accept_m_id']))->getField("head_pic");
                $list[$kk]['head_pic'] = $mem_pic?$mem_pic:"";
            }
            /**如果这个是消费，要查看这个单子是否被评价*/
            if($vv['type'] == 3){
                $is_appraise['m_id'] = $_GET['m_id'];
                $is_appraise['bill_id'] = $vv['b_id'];
                $is_appraise['shop_id'] = $vv['shop_id'];
                $app_id = M("Appraise")->where($is_appraise)->getField("app_id");
                if($app_id){
                    $list[$kk]['is_appraise'] = 1;
                }else{
                    $list[$kk]['is_appraise'] = 0;
                }
            }else{
                $list[$kk]['is_appraise'] = 0;
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


}