<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 加盟商管理
 */
class CashController extends AdminBasicController {
    public $cash = '';
    public function _initialize(){
        $this->checkLogin();
        $this->cash = D('agency_earn');
    }

    /**加盟商列表*/
    public function cashList(){
        //获取所有省份
        $pro = M("areas")->where(array('parent_id'=>1))->order('is_hot desc,sort_order desc')->select();
        $this->assign("pro",$pro);
        $arr = array();
        for ($i=1;$i<=31;$i++){
            $arr[] = $i;
        }
        $this->assign('period',$arr);
        $param = array();
        if(I('request.account')){
            $w['account'] = array('LIKE','%'.I('request.account').'%');
            $request['account'] = I('request.account');
            $param['account'] = I('request.account');
            $this->assign("request",$request);
        }
        if(I('request.type')){
            $w['type'] = I('request.type');
            $request['type'] = I('request.type');
            $param['type'] = I('request.type');
            $this->assign("request",$request);
        }
        if(I('request.module')){
            $w['module'] = I('request.module')-1;
            $request['module'] = I('request.module');
            $param['module'] = I('request.module');
            $this->assign("request",$request);
        }
        if(I('request.payment_days')){
            $w['payment_days'] = I('request.payment_days');
            $request['payment_days'] = I('request.payment_days');
            $param['payment_days'] = I('request.payment_days');
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
        $list = $this->cash->selectShop($w,"ctime desc",15,$param);
        foreach ($list['list'] as $k=>$v){
            $list['list'][$k]['pro_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField("area_name");
            $list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField("area_name");
            $list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField("area_name");
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("cashList");
    }

    /**判断这个手机号是否已经被注册*/
    public function isRegister(){
        $w['status'] = array('neq',9);
        if($_POST['e_id']){
            $w1['e_id'] = $_POST['e_id'];
            $w1['status'] = array('neq',9);
            $res = $this->cash->where($w1)->limit(1)->find();
            if($res['account'] != $_POST['account']){
                $this->ajaxReturn(1);
            }
        }else{
            $w['account'] = $_POST['account'];
            $shop_id = $this->cash->where($w)->limit(1)->getField("e_id");
            if($shop_id){
                $this->ajaxReturn(1);
            }else{
                $this->ajaxReturn(0);
            }
        }
    }

    /**加盟商添加*/
    public function addCash(){
        if(!IS_POST){
            $arr = array();
            for ($i=1;$i<=31;$i++){
                $arr[] = $i;
            }
            $this->assign('period',$arr);
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->order('is_hot desc,sort_order desc')->select();
            $this->assign("pro",$pro);

            //获取商家类型
            $type = M("Class")->where(array("status"=>array('neq',9)))->field("class_id,name")->select();
            $this->assign("type",$type);

            $this->display("addCash");
        }else{
            /**先查看商家的账号是否已经被注册了*/
            $earn_res = $this->cash->where(array('account'=>$_POST['account'],'status'=>array('neq','9')))->getField("e_id");
            if($earn_res){
                $this->error("此手机号已经被注册！");
            }
            $data = $this->cash->create();
            if($data){
                $data['password'] = md5($_POST['password']);
                $data['ctime'] = time();
                $res = $this->cash->add($data);
                if($res){
                    unset($data);
                    /**添加模块*/
                    if(!empty(I("post.cash_type"))){
                        foreach (I("post.cash_type") as $k=>$v){
                            $data['cash_type_id'] = $v;
                            $data['cash_id'] = $res;
                            $data['ctime'] = time();
                            M("CashType")->add($data);
                        }
                    }
                    $this->success("添加成功！",U('Cash/cashList'));
                }else{
                    $this->error("添加失败！");
                }
            }else{
                $this->error($this->cash->getError());
            }

        }
    }


    /**商品修改*/
    public function editCash(){
        if(!IS_POST){
            $arr = array();
            for ($i=1;$i<=31;$i++){
                $arr[] = $i;
            }
            $this->assign('period',$arr);
            $res = $this->cash->where(array('e_id'=>$_GET['e_id']))->find();
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

            //获取商家类型
            $type = M("Class")->where(array("status"=>array('neq',9)))->field("class_id,name")->select();
            $this->assign("type",$type);

            /**加盟商的行业*/
            $cash_type = M("CashType")->where(array("status"=>array('neq',9),'cash_id'=>$_GET['e_id']))->field("cash_type_id")->select();
            foreach ($cash_type as $k=>$v){
                $data[] = $v['cash_type_id'];
            }
            $this->assign("cash_type",$data);

            $this->display("editCash");
        }else{
            /**先查看商家的账号是否已经被注册了*/
            $account = $this->cash->where(array("e_id"=>$_POST['e_id']))->getField("account");
            if($account != $_POST['account']){
                $shop_res = $this->cash->where(array('account'=>$_POST['account'],'status'=>array('not in','1,9')))->getField("e_id");
                if($shop_res){
                    $this->error("此账号已经被注册！");
                }
            }
            $data = $this->cash->create();
            if($data){
                $data['utime'] = time();
                $res = $this->cash->where(array("e_id"=>$_POST['e_id']))->limit(1)->save($data);
                if($res){
                    /**删除行业*/
                    $count = M("CashType")->where(array("status"=>array('neq',9),'cash_id'=>$_POST['e_id']))->count();
                    M("CashType")->where(array("status"=>array('neq',9),'cash_id'=>$_POST['e_id']))->limit($count)->delete();
                    unset($data);
                    /**添加模块*/
                    if(!empty(I("post.cash_type"))){
                        foreach (I("post.cash_type") as $k=>$v){
                            $data['cash_type_id'] = $v;
                            $data['cash_id'] = $_POST['e_id'];
                            $data['ctime'] = time();
                            M("CashType")->add($data);
                        }
                    }
                    $this->success("修改成功！",U("Cash/cashList"));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error($this->cash->getError());
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteCash(){
        if(empty($_REQUEST['e_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['e_id'] = array('IN',I('request.e_id'));
        $data['status'] = 9;
        $upd_res = $this->cash->where($where)->save($data);
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


    /**修改最近的一个账期的日子*/
    public function period(){
        $w['e_id'] = $_POST['e_id'];
        $data['period_time'] = $_POST['period_time'];
        $res = M("Agency_earn")->where($w)->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }

    /**
     * 重置密码
     * @author mss
     * @time 2017-11-13
     */
    public function resetPwd(){
        $e_id = I('get.e_id');
        $data['password'] = md5('zxty888');
        $data['utime'] = time();
        $res = M('AgencyEarn')->where(array('e_id'=>$e_id))->limit(1)->save($data);
        if($res){
            $this->success('重置成功');
        }else{
            $this->error('重置失败');
        }
    }








}