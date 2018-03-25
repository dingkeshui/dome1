<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 奖品的分类
 */
class TypeController extends AdminBasicController {
    public $Type = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Type = D('Type');
    }

    /**商家的类型列表*/
    public function typeList(){
        if(I("request.name")){
            $w["name"] = array("LIKE","%".I("request.name")."%");
            $parameter['name'] = I("request.name");
            $request['name'] =  I("request.name");
            $this->assign("request",$request);
        }
        $w['status'] = array("neq",9);
        $list = D("Type")->selectType($w,"ctime desc",15,$parameter);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("typeList");
    }

    /**商家的类型添加*/
    public function addType(){
        if(!IS_POST){
            $this->display("addType");
        }else{
            $data = D("Type")->create();
            $data['ctime'] = time();
            $res = D("Type")->add($data);
            if($res){
                $this->success("添加成功！",U("Type/typeList"));
            }else{
                $this->error("添加失败！");
            }

        }

    }

    /**商家的类型修改*/
    public function editType(){
        if(!IS_POST){
            $res = D("Type")->where(array("t_id"=>$_GET['t_id']))->limit(1)->find();
            $this->assign("res",$res);
            $this->display("editType");
        }else{
            $data = D("Type")->create();
            $data['utime'] = time();
            $res = D("Type")->where(array('t_id'=>$_POST['t_id']))->limit(1)->save($data);
            if($res){
                $this->success("修改成功！",U("Type/typeList"));
            }else{
                $this->error("修改失败！");
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteType(){
        if(empty($_REQUEST['t_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['t_id'] = array('IN',I('request.t_id'));
        $data['status'] = 9;
        $upd_res = $this->Type->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**修改分类的排序*/
    public function ajaxSort(){
        $w['class_id'] = $_POST['id'];
        $data['sort'] = $_POST['sort'];
        $res = M("Type")->where($w)->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }


}