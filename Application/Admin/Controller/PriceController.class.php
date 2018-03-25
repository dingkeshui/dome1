<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 签到规则管理
 */
class PriceController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();

    }



    /**平台收入的列表*/
    public function priceList(){
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
        S('xls_w',$w);
        $list = D("Price")->selectPrice($w,"ctime desc",15,$pam);
        $total = M('Price')->where($w)->sum('price');
        foreach ($list['list'] as $k=>$v){
            /**找到商家的名称*/
            $list['list'][$k]['name'] = M("Shop")->where(array('shop_id'=>$v['shop_id']))->getField('name');
            /**找到用户的名称*/
            $list['list'][$k]['nick_name'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('nick_name');
            /**找到省市区级的名称*/
            $list['list'][$k]['province_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField('area_name');
            $list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField('area_name');
            $list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField('area_name');
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->assign('total',$total?$total:0);
        $this->display("priceList");
    }


    /**导出平台收入excel的表单*/
    public function priceXLS(){

        /**获取Excel导出数据*/
        $where = S('xls_w');
        $list = M('Price')->where($where)->select();
        $arrordername = array('编号','平台收入钱数','收入时间','商家ID','商家名称','用户ID','用户名称','省','市','区');
        foreach($list as $k=>$v){
            $id = $v['id'];
            $money = $v['price'];
            $time = date(' Y-m-d H:i:s',$v['ctime']);
            $shop_id = $v['shop_id'];
            $shop_name = M('Shop')->where(['shop_id'=>$v['shop_id']])->getField('name');
            $m_id = $v['m_id'];
            $m_name = M('Member')->where(['m_id'=>$v['m_id']])->getField('nick_name');
            $mem_name = $m_name?$m_name:'暂无';
            $province = M('Areas')->where(['area_id'=>$v['province']])->getField('area_name');
            $city = M('Areas')->where(['area_id'=>$v['city']])->getField('area_name');
            $area = M('Areas')->where(['area_id'=>$v['area']])->getField('area_name');
            $arrorderlist[] = array($id,$money,$time,$shop_id,$shop_name,$m_id,$mem_name,$province,$city,$area);
        }
        exportexcel($arrorderlist,$arrordername,'平台收入'.date("Y-m-d"));

    }













}