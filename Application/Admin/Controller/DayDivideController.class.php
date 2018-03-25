<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 每天分的钱数
 */
class DayDivideController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();

    }



    /**每天分的钱数*/
    public function dayDivide(){
        $pam = array();
        $w = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        $list = D("Day_Divide")->selectDayDivide($w,"ctime desc",15,$pam);
        foreach ($list['list'] as $k=>$v){
            /**找到省市区级的名称*/
            $list['list'][$k]['province_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField('area_name');
            $list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField('area_name');
            $list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField('area_name');
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("dayDivide");
    }

    /**ajax修改股价表*/
    public function ajaxDivide(){
        $w['d_id'] = $_POST['d_id'];
        $data['price'] = $_POST['value'];
        $res = M("day_divide")->where($w)->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }













}