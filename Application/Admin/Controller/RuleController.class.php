<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 签到规则管理
 */
class RuleController extends AdminBasicController {
    public $Goods = '';
    public function _initialize(){
        $this->Goods = D('Rule');
    }

    /**商品列表*/
    public function RuleList(){
        $w['status'] = array('neq',9);
        $list = D("Rule")->where($w)->select();
        $this->assign('list',$list);
        $this->display("ruleList");
    }

    /**商品添加*/
    public function addRule(){
        if(!IS_POST){
            $this->display("addRule");
        }else{
            $data = D("Rule")->create();
            if($data){
                $data['ctime'] = time();
                $res = D("Rule")->add($data);
                if($res){
                    $this->success("添加成功！",U('Rule/RuleList'));
                }else{
                    $this->error("添加失败！");
                }
            }else{
                $this->error(D("Rule")->getError());
            }

        }
    }

    /**商品修改*/
    public function editRule(){
        if(!IS_POST){
            $res = D("Rule")->where(array('r_id'=>$_GET['r_id']))->find();
            $this->assign("res",$res);
            $this->display("editRule");
        }else{
            $data = D("Rule")->create();
            $res = D("Rule")->where(array("r_id"=>$_POST['r_id']))->save($data);
            if($res){
                $this->success("修改成功！",U("Rule/RuleList"));
            }else{
                $this->error("修改失败！");
                }

        }
    }
    /**
     * 删除操作
     */
    public function deleteRule(){
        if(empty($_REQUEST['r_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['r_id'] = array('IN',I('request.r_id'));
        $data['status'] = 9;
        $upd_res = D("Rule")->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }











}