<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 发送验证码
 */
class CodeController extends AdminBasicController {

    public function _initialize(){

    }



    /**平台收入的列表*/
    public function codeList(){
        $pam = array();
        if(!empty(I('request.account'))){
            $w['way'] = I('request.account');
            $pam['account'] = I('request.account');
            $this->assign("account",I('request.account'));
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
        $list = D("UserOperate")->selectUserOperate($w,"ctime desc",15,$pam);
        foreach ($list['list'] as $k=>$v){
            $list['list'][$k]['type_value'] = $v['port']?"商家":"用户";
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("codeList");
    }













}