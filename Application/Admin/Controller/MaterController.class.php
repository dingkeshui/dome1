<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * Class MaterController
 * @package Admin\Controller
 * 商品分类
 */
class MaterController extends AdminBasicController {

    public $Mater = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Mater = D('Mater');
    }
    /**
     * 图片列表
     * @author crazy
     * @time 2018-02-08
     */
    public function materList(){
        $where['status'] = array('NEQ',9);
        $mater = $this->Mater->selectMater($where,'ctime desc','15');

        $this->assign('page',$mater['page']);
        $this->assign('list',$mater['list']);

        $this->display('materList');
    }

    /**
     * 添加图片
     * @author crazy
     * @time 2018-02-08
     */
    public function addMater(){
        if(IS_POST){
            /**判断是否已经存在*/
            $is_set = $this->Mater->where(array('mer_id'=>trim(I('post.mer_id'))))->getField('id');
            if($is_set){
                $this->error('图片已经存在！');
            }
            $data = $this->Mater->create();
            if($data){
                $res = $this->Mater->addMater($data);
                if($res){
                    $this->success('添加成功',U('Mater/materList'));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->Mater->getError());
            }
        }else{

            $this->display('addMater');
        }
    }

    /**
     * 编辑分类
     * @author mss
     * @time 2017-10-11
     */
    public function editMater(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->Mater->where(array('mer_id'=>trim(I('post.mer_id')),'mer_id'=>array('neq',$_POST['mer_id'])))->getField('cate_id');
            if($is_set){
                $this->error('图片已经存在！');
            }
            $data = $this->Mater->create();
            if($data){
                $data['utime'] = time();
                $res = $this->Mater->where(array('id'=>$_POST['id']))->limit(1)->save($data);
                if($res){
                    $this->success('编辑成功',U('Mater/materList'));
                }else{
                    $this->error('编辑失败');
                }
            }else{
                $this->error($this->Mater->getError());
            }
        }else{
            $id = I('get.id');
            $mater = M('Mater')->where(array('id'=>$id))->find();
            $this->assign('mater',$mater);
            $this->display('editMater');
        }
    }


    /**
     * 删除操作
     */
    public function deleteMater(){
        if(empty($_REQUEST['id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['id'] = array('IN',I('request.id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->Mater->editMater($where,$data);
        if($upd_res){
            //其他删除操作
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

}