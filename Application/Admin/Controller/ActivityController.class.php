<?php

namespace Admin\Controller;
use Think\Controller;

/**
 * 桌台（活动控制器）
 * Class ActivityController
 * @package Admin\Controller
 */
class ActivityController extends AdminBasicController {
    public $Desk = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Desk = D('Desk');
    }


    /**
     * 公司新闻列表，广告的新闻列表
     * @author mss
     */
    public function deskList(){
        $par = array();
        $request = array();
        if($_REQUEST['title']){
            $w['title'] = array('LIKE','%'.$_REQUEST['title'].'%');
            $par['title'] = $_REQUEST['title'];
            $request['title'] = $_REQUEST['title'];
            $this->assign("request",$request);
        }
        $w['status'] = array('neq',9);
        $list = $this->Desk->selectDesk($w,"ctime desc",15,$par);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("deskList");
    }
    /**
     * 删除新闻
     */
    public function deleteDesk(){
        if(empty($_REQUEST['id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['id'] = array('IN',I('request.id'));
        $data['status'] = 9;
        $upd_res = D("Desk")->editDesk($where,$data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**发布文章*/
    public function addDesk(){
        if(!IS_POST){
            $this->display("addDesk");
        }else{
            if(empty($_FILES['pic']['name'])){
                $this->error("请上传背景图！");
            }
            $data = D("Desk")->create();
            if($data){
                if($_FILES['pic']['name']){
                    $img = $this->uploadImg("Desk","pic");
                    $data['pic'] = $img;
                }
                $res = D("Desk")->add($data);
                if($res){
                    $this->success("发布成功！",U('Activity/DeskList'));
                }else{
                    $this->error("发布失败！");
                }
            }else{
                $this->error(D("Desk")->getError());
            }
        }
    }

    /**文章修改*/
    public function editDesk(){
        if(!IS_POST){
            $w['id'] = $_GET['id'];
            $res = D("Desk")->where($w)->find();
            $this->assign("res",$res);
            $this->display("editDesk");
        }else{
            $w['id'] = $_POST['id'];
            $data = D("Desk")->create();
            if($data){
                if($_FILES['pic']['name']){
                    $img = $this->uploadImg("Desk","pic");
                    $data['pic'] = $img;
                }
                $res = D("Desk")->where($w)->save($data);
                if($res){
                    $this->success("修改成功！",U('Activity/DeskList'));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error(D("Desk")->getError());
            }
        }
    }


}