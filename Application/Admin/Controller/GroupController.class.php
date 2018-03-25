<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * 权限组类
 */
class GroupController extends AdminBasicController {

    public $Group = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Group = D('AdminGroup');
    }

    /**
     * 权限组列表
     */
    public function groupList(){
        $w['status'] = ['neq',9];
        $list = $this->Group->selectAdminGroup($w,'',15);
        foreach($list['list'] as $k=>$v){
            $list['list'][$k]['permission'] = unserialize($v['permission']);
            foreach($list['list'][$k]['permission'] as $kk=>$vv){
                $list['list'][$k]['per'] .= D("AdminAction")->where(array('action_id'=>$vv))->getField("action_name")." ";
            }
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display('groupList');
    }

    /**
     * 添加权限组
     */
    public function addGroup(){
        if(empty($_POST)){
            //按分组获取所有权限
            $w['status'] = ['neq',9];
            $per = D("AdminAction")->where($w)->group("group_name")->field("group_name")->select();
            foreach($per as $k=>$v){
                $arr = D("AdminAction")->where(array('group_name'=>$v['group_name'],'status'=>0))->select();
                $list[$k]['name'] = M("Group_name")->where(array('id'=>$v['group_name']))->getField('group_name');
                $list[$k]['list'] = $arr;
            }
            $this->assign("list",$list);
            $this->display("addGroup");
        }else{
            $data = $this->Group->create();
            if($data){
                if(empty($_POST['permission'])){
                    $this->error("只少选择一种权限！");
                }
                $data['permission'] = serialize($_POST['permission']);
                $res = $this->Group->addAdminGroup($data);
                if($res){
                    $this->success("添加成功",U("Group/groupList"));
                }else{
                    $this->error("添加失败");
                }
            }else{
                $this->error($this->Group->getError());
            }
        }
    }

    /**
     * 修该权限组
     */
    public function editGroup(){
        if(empty($_POST)){
            $where['group_id'] = $_GET['group_id'];
            $list = $this->Group->findAdminGroup($where);
            $list['permission'] = unserialize($list['permission']);
            $this->assign("list",$list);
            //按分组获取所有权限
            $w['status'] = ['neq',9];
            $per = D("AdminAction")->where($w)->group("group_name")->field("group_name")->select();
            foreach($per as $k=>$v){
                $arr = D("AdminAction")->where(array('group_name'=>$v['group_name'],'status'=>0))->select();
                $per[$k]['name'] = M("Group_name")->where(array('id'=>$v['group_name']))->getField('group_name');
                $per[$k]['list'] = $arr;
            }
            $this->assign("per",$per);
            $this->display("editGroup");
        }else{
            $data = $this->Group->create();
            $where['group_id'] = $_POST['group_id'];
            if($data){
                if(empty($_POST['permission'])){
                    $this->error("只少选择一种权限！");
                }
                $data['permission'] = serialize($_POST['permission']);
                $res = $this->Group->editAdminGroup($where,$data);
                if($res){
                    $this->success("修改成功",U("Group/groupList"));
                }else{
                    $this->error("修改失败");
                }
            }else{
                $this->error($this->Group->getError());
            }
        }
    }

    /**
     * 删除权限组
     */
    public function deleteGroup(){
        $where['group_id'] = array('in',$_REQUEST['group_id']);
        $res =$this->Group->deleteGroup($where);
        if($res){
            $this->success("删除成功");
        }else{
            $this->error("删除失败");
        }
    }
}