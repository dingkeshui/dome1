<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 支付出现错误信息的记录
 */
class ErrorController extends AdminBasicController {

    public function _initialize(){

    }



    /**平台收入的列表*/
    public function errorList(){
        $pam = array();
        if(!empty(I('request.shop_account'))){
            $shop_account['account'] = I('request.shop_account');
            $shop_account['status'] = array('neq',9);
            $shop_list = M("Shop")->where($shop_account)->field("shop_id")->select();
            $arr1 = array();
            foreach ($shop_list as $kk=>$vv){
                $arr1[] = $vv['shop_id'];
            }
            $string1 = implode(',',$arr1);
            $w['shop_id'] = array('in',$string1);
            $pam['shop_account'] = I('request.shop_account');
            $this->assign("shop_account",I('request.shop_account'));
        }
        if(!empty(I('request.member_account'))){
            $shop_account['account'] = I('request.member_account');
            $shop_account['status'] = array('neq',9);
            $shop_list = M("Member")->where($shop_account)->field("m_id")->select();
            $arr1 = array();
            foreach ($shop_list as $kk=>$vv){
                $arr1[] = $vv['m_id'];
            }
            $string1 = implode(',',$arr1);
            $w['m_id'] = array('in',$string1);
            $pam['member_account'] = I('request.member_account');
            $this->assign("member_account",I('request.member_account'));
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        $list = D("Error")->selectError($w,"ctime desc",15,$pam);
        foreach ($list['list'] as $k=>$v){
            /**找到商家的名称*/
            $list['list'][$k]['shop_name'] = M("Shop")->where(array('shop_id'=>$v['shop_id']))->getField('name');
            $list['list'][$k]['shop_account'] = M("Shop")->where(array('shop_id'=>$v['shop_id']))->getField('account');
            /**找到用户的名称*/
            $list['list'][$k]['member_name'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('nick_name');
            $list['list'][$k]['member_account'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('account');
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("errorList");
    }













}