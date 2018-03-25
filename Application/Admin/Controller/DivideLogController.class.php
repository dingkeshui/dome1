<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 每天用户签到领取的钱数
 */
class DivideLogController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();

    }



    /**
     * 每天用户签到领取的钱数
     * @time 2017-08-13
     * @author crazy
     */
    public function divideLog(){
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
        /**筛选的状态，0是用户 1是商家*/
        if(!empty(I('request.type'))){
            $type = I('request.type');
            $this->assign("type",$type);
            $w['type'] = I('request.type')-1;
            $pam['type'] = I('request.type');
        }
        /**用户或商家的名称查找*/
        if(!empty(trim(I('request.nick_name')))){
            $nick_name = trim(I('request.nick_name'));
            $this->assign('nick_name',$nick_name);
            $pam['nick_name'] = $nick_name;
            $id_type = I('request.type')?I('request.type')-1:0;
            if($id_type==0){
                $where['nick_name'] = array('LIKE','%'.$nick_name.'%');
                $where['status'] = array('neq',9);
                $ids = M('member')->where($where)->getField('m_id',true); 
            }else{
                $where['name'] = array('LIKE','%'.$nick_name.'%');
                $where['status'] = array('neq',9);
                $ids = M('shop')->where($where)->getField('shop_id',true);
            }
            $w['m_id'] = array('IN',$ids);
            $w['type'] = $id_type;
        }

        $list = D("Divide_Log")->selectDivideLog($w,"ctime desc",15,$pam);
        foreach ($list['list'] as $k=>$v){
            if($v['type'] == 1){
                /**找到商家的名称*/
                $list['list'][$k]['mem_name'] = M("Shop")->where(array('shop_id'=>$v['m_id']))->getField('name');
                $list['list'][$k]['show_pie'] = M("Shop")->where(array('shop_id'=>$v['m_id']))->getField('piles');
                $list['list'][$k]['pie_count'] =M("Pie")->where(array('mix_id'=>$v['m_id'],'type'=>1))->count();
                $list['list'][$k]['integral'] = M("Shop")->where(array('shop_id'=>$v['m_id']))->getField('integral');
            }else{
                /**找到用户的名称*/
                $list['list'][$k]['mem_name'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('nick_name');
                $list['list'][$k]['show_pie'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('piles');
                $list['list'][$k]['pie_count'] =M("Pie")->where(array('mix_id'=>$v['m_id'],'type'=>0))->count();
                $list['list'][$k]['integral'] = M("Member")->where(array('m_id'=>$v['m_id']))->getField('integral');
            }

        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("divideLog");
    }













}