<?php
namespace Cash\Controller;
use Think\Controller;
/**
 * Class OrderController
 * @package Admin\Controller
 */
class OrderController extends AdminBasicController {
    public $Order = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Order = D('Order_class');
    }
    /**
     * 获取全部的订单（非积分）
     */
    public function orderList(){
        //昵称查找
        $where = array();
        $request = array();
        if(!empty($_REQUEST['order_sn'])){
            $where['order_sn'] = array('LIKE',"%".I('request.order_sn')."%");
            $parameter['order_sn'] = I('request.order_sn');
            $request['order_sn'] = I('request.order_sn');
            $this->assign("request",$request);
        }
        /**
         * 找到代理商的信息
         * 1:市级代理
         * 2:区级代理
         */
        $where_e['e_id'] = session('E_ID');

        $res_e = D("agency_earn")->where($where_e)->find();
        if($res_e['type'] == 1){
            $where['city'] = $res_e['city'];
        }elseif ($res_e['type'] == 2){
            $where['area'] = $res_e['area'];
        }
        S('o_where',$where);
        $where['status'] = array('NEQ',9);
        if(session('CLASS_E_ID')){
            $where['class_id'] = session('CLASS_E_ID');
        }
        $reslist = $this->Order->selectOrder($where,"ctime desc",'15',$parameter);
        $resarr = $reslist['list'];
        foreach($resarr as $key=>$val){
            /**查看用户的信息*/
            $ww['m_id'] = $val['m_id'];
            $res = D("Member")->where($ww)->find();
            $reslist['list'][$key]['member'] = $res;
            /**查看商家的信息*/
            $www['shop_id'] = $val['shop_id'];
            $shop = D("Shop")->where($www)->find();
            $reslist['list'][$key]['shop'] = $shop;
            /**找到省市区级的名称*/
            $reslist['list'][$key]['province_name'] = M("Areas")->where(array('area_id'=>$val['province']))->getField('area_name');
            $reslist['list'][$key]['city_name'] = M("Areas")->where(array('area_id'=>$val['city']))->getField('area_name');
            $reslist['list'][$key]['area_name'] = M("Areas")->where(array('area_id'=>$val['area']))->getField('area_name');
        }
        $this->assign('Order',$reslist['list']);
        $this->assign('page',$reslist['page']);

        $this->display("orderList");
    }

    /**
     * 删除订单
     */
    public function deleteOrder(){
        if(empty($_REQUEST['o_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['o_id'] = array('IN',I('request.o_id'));
        $data['status'] = 9;
        $upd_res = $this->Order->editOrder($where,$data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * 单条订单
     */
    public function oneOrder(){
        $w['o_id'] = I('get.o_id');
        $res = D("Order")->where($w)->find();
        /**找到用户*/
        $ww['m_id'] = $res['m_id'];
        $res1 = D("Member")->where($ww)->find();
        $res['member'] = $res1;
        $this->assign("res",$res);
        $this->display("oneOrder");
    }
    
    /**导出订单excel的表单*/
    public function orderXLS(){
        /**获取Excel导出数据*/
        //$list = S("re_list");
        $is_set = S('o_where');
        if($is_set){
            $where = $is_set;
        }
        $where['status'] = array('neq',9);
        if(session('CLASS_E_ID')){
            $where['class_id'] = session('CLASS_E_ID');
        }
        $list = M('Order_class')->where($where)->select();
        $arrordername = array('下单时间','订单号','订单金额','用户名称','商家名称','支付方式','省','市','区','用户IP地址');
        foreach($list as $key=>$val){

            $val['m_name'] = M('Member')->where(array('m_id'=>$val['m_id']))->getField('nick_name');
            $val['s_name'] = M('Shop')->where(array('shop_id'=>$val['shop_id']))->getField('name');
            if($val['pay_type']==0){
                $pay_type = '众享豆';
            }elseif($val['pay_type']==1){
                $pay_type = '支付宝';
            }elseif($val['pay_type']==2){
                $pay_type = '微信';
            }
            $val['province_name'] = M('Areas')->where(array('area_id'=>$val['province']))->getField('area_name');
            $val['city_name'] = M('Areas')->where(array('area_id'=>$val['city']))->getField('area_name');
            $val['area_name'] = M('Areas')->where(array('area_id'=>$val['area']))->getField('area_name');
            $arrorderlist[] = array(' '.date('Y-m-d H:i:s',$val['ctime']),$val['order_sn'],$val['total_price'],$val['m_name'],$val['s_name'],$pay_type,$val['province_name'],$val['city_name'],$val['area_name'],$val['ip']);

        }
//        //dump($arrorderlist);
//        exit;
        exportexcel($arrorderlist,$arrordername,'订单信息' . date("Y/m/d H.i.s"));

    }

}