<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * Class CaseController
 * @package Admin\Controller
 * 退货的原因
 */
class ReturnReasonController extends AdminBasicController {

    public $return = '';
    public function _initialize(){
        $this->checkLogin();
        $this->return = D('ReturnReason');
    }
    /**
     * 分类列表
     * @author mss
     * @time 2017-10-11
     */
    public function reasonList(){
        $where['status'] = array('NEQ',9);
        $case = $this->return->selectReason($where,'sort ASC,ctime desc','15');

        $this->assign('page',$case['page']);
        $this->assign('list',$case['list']);

        $this->display('reasonList');
    }

    /**
     * 添加分类
     * @author mss
     * @time 2017-10-10
     */
    public function addReason(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->return->where(array('title'=>trim(I('post.title'))))->getField('id');
            if($is_set){
                $this->error('原因已经存在！');
            }
            $data = $this->return->create();
            if($data){
                $res = $this->return->addReason($data);
                if($res){
                    $this->success('添加成功',U('ReturnReason/reasonList'));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->return->getError());
            }
        }else{
            $this->display('addReason');
        }
    }

    /**
     * 编辑分类
     * @author mss
     * @time 2017-10-11
     */
    public function editReason(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->return->where(array('title'=>trim(I('post.title')),'id'=>array('neq',$_POST['id'])))->getField('id');
            if($is_set){
                $this->error('原因已经存在！');
            }
            $data = $this->return->create();
            if($data){
                $data['utime'] = time();
                $res = $this->return->where(array('id'=>$_POST['id']))->limit(1)->save($data);
                if($res){
                    $this->success('编辑成功',U('ReturnReason/reasonList'));
                }else{
                    $this->error('编辑失败');
                }
            }else{
                $this->error($this->return->getError());
            }
        }else{
            $id = I('get.id');
            $reason = $this->return->where(array('id'=>$id))->find();
            $this->assign('reason',$reason);
            $this->display('editReason');
        }
    }


    /**
     * 删除操作
     */
    public function deleteReason(){
        if(empty($_REQUEST['id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['id'] = array('IN',I('request.id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->return->editReason($where,$data);
        if($upd_res){
            //其他删除操作

            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * 修改显示顺序
     */
    public function updateSort(){
        $data['sort'] = $_POST['sort'];
        $data['utime'] = time();
        $res = $this->return->where(array('id'=>$_POST['id']))->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }




}