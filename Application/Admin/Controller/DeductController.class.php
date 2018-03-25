<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 加盟商提成比例管理
 */
class DeductController extends AdminBasicController {
    public $deduct = '';
    public function _initialize(){
        $this->checkLogin();
        $this->deduct = D('deduct');
    }

    /**加盟商比例列表*/
    public function deductList(){
        //获取所有省份
        $pro = M("areas")->where(array('parent_id'=>1))->order('is_hot desc,sort_order desc')->select();
        $this->assign("pro",$pro);
        $param = array();
        if(I('request.type')){
            $w['type'] = I('request.type');
            $request['type'] = I('request.type');
            $param['type'] = I('request.type');
            $this->assign("request",$request);
        }
        if(I('request.province') || I('request.city') || I('request.area')){
            if(I('request.province')){
                $param['province'] = I('request.province');
                $w['province'] = I('request.province');
            }
            if(I('request.city')){
                $w['city'] = I('request.city');
                $param['city'] = I('request.city');
            }
            if(I('request.area')){
                $w['area'] = I('request.area');
                $param['area'] = I('request.area');
            }
            $this->assign("province",I('request.province'));
            //获取城市
            $city = M("areas")->where(array('parent_id'=>I('request.province')))->select();
            $this->assign("city_s",I('request.city'));
            $this->assign("city",$city);
            //获取区域
            $area = M("areas")->where(array('parent_id'=>I('request.city')))->select();
            $this->assign("area_s",I('request.area'));
            $this->assign("area",$area);
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
        $w['status'] = array('neq',9);
        $list = $this->deduct->selectDeduct($w,"ctime desc",15,$param);
        foreach ($list['list'] as $k=>$v){
            if($v['type'] == 1){
                $list['list'][$k]['name'] = M("Areas")->where(array('area_id'=>$v['other_id']))->getField("area_name");
            }elseif ($v['type'] == 2){
                $list['list'][$k]['name'] = M("Areas")->where(array('area_id'=>$v['other_id']))->getField("area_name");
            }
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("deductList");
    }


    /**加盟商添加*/
    public function addDeduct(){
        if(!IS_POST){
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->order('is_hot desc,sort_order desc')->select();
            $this->assign("pro",$pro);
            $this->display("addDeduct");
        }else{
            /**判断一下销售额是否已经被添加*/
            $w['status'] = array('neq',9);
            $w['type'] = $_POST['type'];
            if($_POST['type'] == 1){
                $w['other_id'] =$_POST['city'];
            }elseif ($_POST['type'] == 2){
                $w['other_id'] =$_POST['area'];
            }
            $w['price'] = $_POST['price'];
            $deduct = M("Deduct")->where($w)->find();
            if($deduct){
                $this->error("此阶梯销售额已经存在！");
            }
            $data = $this->deduct->create();
            if($data){
                if($_POST['type'] == 1){
                    $data['other_id'] =$_POST['city'];
                }elseif ($_POST['type'] == 2){
                    $data['other_id'] =$_POST['area'];
                }
                $data['ctime'] = time();
                $res = $this->deduct->add($data);
                if($res){
                    $this->success("添加成功！",U('Deduct/deductList'));
                }else{
                    $this->error("添加失败！");
                }
            }else{
                $this->error($this->deduct->getError());
            }

        }
    }


    /**商品修改*/
    public function editDeduct(){
        if(!IS_POST){
            $res = $this->deduct->where(array('id'=>$_GET['id']))->find();
            $this->assign("res",$res);
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->order('is_hot desc,sort_order desc')->select();
            $this->assign("pro",$pro);
            //获取城市
            $city = M("areas")->where(array('parent_id'=>$res['province']))->select();
            $this->assign("city",$city);
            //获取区域
            $area = M("areas")->where(array('parent_id'=>$res['city']))->select();
            $this->assign("area",$area);
            $this->display("editDeduct");
        }else{
            $deduct = $this->deduct->where(array("id"=>$_POST['id']))->limit(1)->find();
            if($deduct['price'] != $_POST['price']){
                /**判断一下销售额是否已经被添加*/
                $w['status'] = array('neq',9);
                $w['type'] = $_POST['type'];
                if($_POST['type'] == 1){
                    $w['other_id'] =$_POST['city'];
                }elseif ($_POST['type'] == 2){
                    $w['other_id'] =$_POST['area'];
                }
                $w['price'] = $_POST['price'];
                $deduct_is = M("Deduct")->where($w)->find();
                if($deduct_is){
                    $this->error("此阶梯销售额已经存在！");
                }
            }
            $data = $this->deduct->create();
            if($data){
                if($_POST['type'] == 1){
                    $data['other_id'] = $_POST['city'];
                }elseif ($_POST['type'] == 2){
                    $data['other_id'] = $_POST['area'];
                }
                $data['utime'] = time();
                $res = $this->deduct->where(array("id"=>$_POST['id']))->limit(1)->save($data);
                if($res){
                    $this->success("修改成功！",U('Deduct/deductList'));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error($this->deduct->getError());
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteDeduct(){
        if(empty($_REQUEST['id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['id'] = array('IN',I('request.id'));
        $data['status'] = 9;
        $upd_res = $this->deduct->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * ajax获取城市
     */
    public function ajaxCity(){
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("areas")->where($where)->select();
        echo json_encode($data);
    }
    /**
     * ajax获取区域
     */
    public function ajaxArea(){
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("areas")->where($where)->select();
        echo json_encode($data);
    }









}