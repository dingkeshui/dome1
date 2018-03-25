<?php
namespace Admin\Controller;
use Think\Controller;
/**
 *  认证费用管理
 */
class ApprovePriceController extends AdminBasicController {
    public $ApprovePrice = '';
    public function _initialize(){
        $this->checkLogin();
        $this->ApprovePrice = D('ApprovePrice');
    }

    /**信息的列表*/
    public function approvePriceList(){
        $parameter = [];
        $w['status'] = array("neq",9);
        $list = $this->ApprovePrice->selectApprovePrice($w,"ctime desc",15,$parameter);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("approvePriceList");
    }

    /**发布信息*/
    public function addApprovePrice(){
        if(!IS_POST){
            $this->display("addApprovePrice");
        }else{
            $data = D("ApprovePrice")->create();
            if($data){
                $data['ctime'] = time();
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                $upload = $this->uploadImg("Approve","pic");
                $data['pic'] = $upload;
                $res = $this->ApprovePrice->add($data);
                if($res){
                    $this->success("发布成功！",U("ApprovePrice/approvePriceList"));
                }else{
                    $this->error("发布失败！");
                }
            }else{
                $this->error(M("Message")->getError());
            }
        }
    }

    /**
     * 删除操作
     */
    public function deleteApprovePrice(){
        if(empty($_REQUEST['id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['id'] = array('IN',I('request.id'));
        $data['status'] = 9;
        $upd_res = $this->ApprovePrice->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**修改认证费用
     * @author crazy
     * @time 2018-01-01
     */
    public function editApprovePrice(){
        if(!IS_POST){
            $res = $this->ApprovePrice->where(['id'=>$_GET['id']])->find();
            $this->assign('res',$res);
            $this->display("editApprovePrice");
        }else{
            $data =$this->ApprovePrice->create();
            if ($_FILES['pic']['name']){
                $upload = $this->uploadImg("Approve","pic");
                $data['pic'] = $upload;
            }
            $res = $this->ApprovePrice->where(['id'=>$_POST['id']])->limit(1)->save($data);
            if($res){
                $this->success('修改成功',U("ApprovePrice/approvePriceList"));
            }else{
                $this->error('修改失败');
            }
        }
    }


    /**缴纳认证费的商户*/
    public function approveOrderList(){
        if($_REQUEST['shop_name']){
            $w['shop_name'] = array('LIKE','%'.trim($_REQUEST['shop_name']).'%');
            $par['shop_name'] = trim($_REQUEST['shop_name']);
            $request['shop_name'] = trim($_REQUEST['shop_name']);
            $this->assign("request",$request);
        }
        if($_REQUEST['status']){
            $w['status'] = $_REQUEST['status']-1;
            $param['status'] = I('request.status');
            $request['status'] = I('request.status');
        }else{
            $w['status'] = array('neq',9);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $param['start_time'] = I('request.start_time');
            $param['end_time'] = I('request.end_time');
        }
        $order = "ctime desc";
        $parameter = [];
        $list = D("ApproveOrder")->selectApproveOrder(D("ApproveOrder"),$w,$order,15,$parameter);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("approveOrderList");

    }

}