<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * Class CaseController
 * @package Admin\Controller
 * 物流公司
 */
class DeliveryCompanyController extends AdminBasicController {

    public $delivery_company = '';
    public function _initialize(){
        $this->checkLogin();
        $this->delivery_company = D('DeliveryCompany');
    }
    /**
     * 分类列表
     * @author mss
     * @time 2017-10-11
     */
    public function deliveryList(){
        $where['status'] = array('NEQ',9);
        $case = $this->delivery_company->selectDelivery($where,'sort ASC,ctime desc','15');

        $this->assign('page',$case['page']);
        $this->assign('list',$case['list']);

        $this->display('deliveryList');
    }

    /**
     * 添加分类
     * @author mss
     * @time 2017-10-10
     */
    public function addDelivery(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->delivery_company->where(array('company_name'=>trim(I('post.company_name')),'status'=>0))->getField('id');
            if($is_set){
                $this->error('物流公司已经存在！');
            }
            $data = $this->delivery_company->create();
            if($data){
                $res = $this->delivery_company->add($data);
                if($res){
                    $this->success('添加成功',U('DeliveryCompany/deliveryList'));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->delivery_company->getError());
            }
        }else{
            $this->display('addDelivery');
        }
    }

    /**
     * 编辑分类
     * @author mss
     * @time 2017-10-11
     */
    public function editDelivery(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->delivery_company->where(array('company_name'=>trim(I('post.company_name')),'status'=>0,'id'=>array('neq',$_POST['id'])))->getField('id');
            if($is_set){
                $this->error('物流公司已经存在！');
            }
            $data = $this->delivery_company->create();
            if($data){
                $data['utime'] = time();
                $res = $this->delivery_company->where(array('id'=>$_POST['id']))->limit(1)->save($data);
                if($res){
                    $this->success('编辑成功',U('DeliveryCompany/deliveryList'));
                }else{
                    $this->error('编辑失败');
                }
            }else{
                $this->error($this->delivery_company->getError());
            }
        }else{
            $id = I('get.id');
            $Delivery = $this->delivery_company->where(array('id'=>$id))->find();
            $this->assign('res',$Delivery);
            $this->display('editDelivery');
        }
    }


    /**
     * 删除操作
     */
    public function deleteDelivery(){
        if(empty($_REQUEST['id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['id'] = array('IN',I('request.id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->delivery_company->editDelivery($where,$data);
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
        $res = $this->delivery_company->where(array('id'=>$_POST['id']))->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }


}