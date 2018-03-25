<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 资金池
 */
class DivideController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();

    }

    /**平台收入的列表*/
    public function divide(){
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
        $list = D("Divide")->selectDivide($w,"ctime desc",15,$pam);
        foreach ($list['list'] as $k=>$v){
            /**找到省市区级的名称*/
            $list['list'][$k]['province_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField('area_name');
            $list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField('area_name');
            $list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField('area_name');
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("divide");
    }

    /**资金池（临时表）*/
    public function total(){
        $w['id'] = 1;
        $res = M("Total")->where($w)->find();
        $this->assign("res",$res);
        $total = M("Scale")->where($w)->find();
        $this->assign("total",$total);
        $this->display("total");
    }


    /**股数用户的股数*/
    public function selectEarn(){
        $parameter = array();
        $w = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        if(I('request.type')){
            $w['type'] = I('request.type')-1;
            $this->assign("type",I('request.type'));
            $parameter['type'] = I('request.type');
        }
        $order = "ctime desc";
        $list = D("Pie")->selectEarn($w,$order,15,$parameter);
        foreach ($list['list'] as $k=>$v){
            if($v['type'] == 1){
                $list['list'][$k]['name'] = M("Shop")->where(array('shop_id'=>$v['mix_id']))->limit(1)->getField('name');
            }else{
                $list['list'][$k]['name'] = M("Member")->where(array('m_id'=>$v['mix_id']))->limit(1)->getField('nick_name');
            }
        }
        //dump(D("invest_earn")->getLastSql());
        $this->assign('list', $list['list']);
        $this->assign('page', $list['page']);
        $this->display('selectEarn');
    }











}