<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 商家的类型
 */
class ClassController extends AdminBasicController {
    public $Class = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Class = D('Class');
    }

    /**商家的类型列表*/
    public function classList(){
        if(I("request.name")){
            $w["name"] = array("LIKE","%".I("request.name")."%");
            $parameter['name'] = I("request.name");
            $request['name'] =  I("request.name");
            $this->assign("request",$request);
        }
        $w['status'] = array("neq",9);
        $list = D("Class")->selectClass($w,"sort asc,ctime desc",15,$parameter);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("classList");
    }

    /**商家的类型添加*/
    public function addClass(){
        if(!IS_POST){
            $this->display("addClass");
        }else{
            $data = D("Class")->create();
            if($_FILES['img']['name']){
                $data['pic'] = $this->uploadImg("Class","img");
            }
            $data['ctime'] = time();
            $res = D("Class")->add($data);
            if($res){
                $this->success("添加成功！",U("Class/classList"));
            }else{
                $this->error("添加失败！");
            }

        }

    }

    /**商家的类型修改*/
    public function editClass(){
        if(!IS_POST){
            $res = D("Class")->where(array("class_id"=>$_GET['class_id']))->find();
            $this->assign("res",$res);
            $this->display("editClass");
        }else{
            $data = D("Class")->create();
            if($_FILES['img']['name']){
                $data['pic'] = $this->uploadImg("Class","img");
            }
            $data['utime'] = time();
            $res = D("Class")->where(array('class_id'=>$_POST['class_id']))->limit(1)->save($data);
            if($res){
                $this->success("修改成功！",U("Class/classList"));
            }else{
                $this->error("修改失败！");
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteClass(){
        if(empty($_REQUEST['class_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['class_id'] = array('IN',I('request.class_id'));
        $data['status'] = 9;
        $upd_res = $this->Class->where($where)->save($data);
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
        $res = M("Class")->where($w)->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }


}