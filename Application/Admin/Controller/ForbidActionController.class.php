<?php

namespace Admin\Controller;
use Think\Controller;

/**
 * Class ForbidActionController
 * @package Admin\Controller
 */
class ForbidActionController extends AdminBasicController {

    public $forbid_obj = '';
    public function _initialize(){
        $this->checkLogin();
        $this->forbid_obj = D('ForbidAction');
    }

    /**权限的列表*/
    public function forbidActionList(){
        $parm = array();
        if($_REQUEST['type']){
            $w['type'] = $_REQUEST['type'];
            $request['type'] = $_REQUEST['type'];
            $parm['type'] = $_REQUEST['type'];
            $this->assign("request",$request);
        }
        if($_REQUEST['action_name']){
            $w['action_name'] = array('like','%'.$_REQUEST['action_name'].'%');
            $request['action_name'] = $_REQUEST['action_name'];
            $parm['action_name'] = $_REQUEST['action_name'];
            $this->assign("request",$request);
        }
        $w['status'] = array('neq',9);
        $list = $this->forbid_obj->selectForbidAction($w,"ctime desc",15,$parm);
//        foreach ($list['list'] as $k=>$v){
//            $w_g['id'] = $v['group_name'];
//            $group_name = M("Group_name")->where($w_g)->getField("group_name");
//            $list['list'][$k]['group_name'] = $group_name;
//        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("forbidActionList");
    }

    /**添加权限的列表名称*/
    public function addForbidAction(){
        if(!IS_POST){

            $this->display("addForbidAction");
        }else{
            $data = $this->forbid_obj->create();
            $data['ctime'] = time();
            $res = $this->forbid_obj->add($data);
            if($res){
                $this->success("添加成功！",U("ForbidAction/forbidActionList"));
            }else{
                $this->error("添加失败！");
            }
        }

    }

    /**修改权限方法名*/
    public function editForbidAction(){
        if(!IS_POST){
            $w['id'] = $_GET['id'];
            $res = M("ForbidAction")->where($w)->find();
            $this->assign('res',$res);
            $this->display("editForbidAction");
        }else{
            $data = $this->forbid_obj->create();
            $w['id'] = $_POST['id'];
            $data['utime'] = time();
            $res = $this->forbid_obj->editForbidAction($w,$data);
            if($res){
                $this->success("修改成功！",U("ForbidAction/forbidActionList"));
            }else{
                $this->error("修改失败！");
            }
        }
    }

    /**删除权限组*/
    public function delForbidAction(){
        $data['status'] = 9;
        $w['id'] = array('IN',I('request.id'));
        $upd_res = $this->forbid_obj->editForbidAction($w,$data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }


    

}