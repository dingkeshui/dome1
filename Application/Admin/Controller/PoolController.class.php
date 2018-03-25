<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 用户消费的钱数
 */
class PoolController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();

    }



    /**平台收入的列表*/
    public function poolList(){
        $pam = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        $w['status'] = array('neq',9);
        $list = D("Pool")->selectPool($w,"ctime desc",15,$pam);
        foreach ($list['list'] as $k=>$v){
            /**找到用户的名称*/
            $list['list'][$k]['mem_name'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('nick_name');
            /**找到商家的名称*/
            $list['list'][$k]['name'] = M("Shop")->where(array('shop_id'=>$v['shop_id']))->getField('name');
            /**找到省市区级的名称*/
            $list['list'][$k]['province_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField('area_name');
            $list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField('area_name');
            $list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField('area_name');
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("poolList");
    }













}