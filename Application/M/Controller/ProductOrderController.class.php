<?php
namespace M\Controller;
use Think\Controller;
/**
 * Class ProductOrderController
 * @package Admin\Controller
 */
class ProductOrderController extends AdminBasicController {
    public $Order = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Order = D('product_order');
    }

    /**
     * 获取全部的订单（非积分）
     * 传参方式 get
     * @author crazy  mashanshan修改，修改时间：2017-08-06
     */
    public function orderList(){
        //昵称查找
        $where = array();
        $request = array();
        $parameter = array();
        if(!empty($_REQUEST['order_sn'])){
            $where['order_sn'] = array('LIKE',"%".I('request.order_sn')."%");
            $parameter['order_sn'] = I('request.order_sn');
            $request['order_sn'] = I('request.order_sn');
            $this->assign("request",$request);
        }
        /**用户昵称查找*/
        if(!empty(I('request.nick_name'))){
            $w['nick_name'] = array('LIKE','%'.I('request.nick_name').'%');
            $w['status'] = array('neq',9);
            $m_ids = M('member')->where($w)->getField('m_id',true);
            $where['m_id'] = array('IN',$m_ids);
            $parameter['nick_name'] = I('request.nick_name');
            $request['nick_name'] = I('request.nick_name');
            $this->assign('request',$request);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $where['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        if(!empty($_REQUEST['pay_type'])){
            $where['pay_type'] = $_REQUEST['pay_type']-1;
            $parameter['pay_type'] = I('request.pay_type');
            $this->assign("pay_type",I('request.pay_type'));
        }

        //商家名称查找，2017-12-05mss添加
        if($_REQUEST['shop_name']){
            $name = $_REQUEST['shop_name'];
            $shop_id = M('Shop')->where(array('status'=>array('NEQ',9),'name'=>array('LIKE','%'.$name.'%')))->getField('shop_id',true);
            $where['shop_id'] = array('IN',$shop_id);
            $parameter['shop_name'] = $name;
            $request['shop_name'] = $name;
            $this->assign('request',$request);
        }

        if($_REQUEST['shop_id']){
            $where['shop_id'] = $_REQUEST['shop_id'];
            $parameter['shop_id'] = I('request.shop_id');
            $this->assign("shop_id",I('request.shop_id'));
        }
        //结算状态
        if($_REQUEST['is_account']){
            $where['is_account'] = $_REQUEST['is_account']-1;
            $parameter['is_account'] = $_REQUEST['is_account'];
            $this->assign('is_account',$_REQUEST['is_account']);
        }
        //订单状态筛选
        if($_REQUEST['status']!=""){
            $where['status'] = $_REQUEST['status'];
            $parameter['status'] = $_REQUEST['status'];
            $this->assign('status',$_REQUEST['status']);
        }
//        $where['status'] = array('NEQ',9);
        S('order_w',$where);
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
            $address = M("Address")->where(['addr_id'=>$val['addr_id']])->find();
            $reslist['list'][$key]['province_name'] = M("Areas")->where(array('area_id'=>$address['province']))->getField('area_name');
            $reslist['list'][$key]['city_name'] = M("Areas")->where(array('area_id'=>$address['city']))->getField('area_name');
            $reslist['list'][$key]['area_name'] = M("Areas")->where(array('area_id'=>$address['area']))->getField('area_name');
        }
        $this->assign('Order',$reslist['list']);
        $this->assign('page',$reslist['page']);
        //总的页数
        $pages = ceil(M('ProductOrder')->where($where)->count()/15);
        $this->assign('pages',$pages);
        //统计订单总额
        $total = $this->Order->where($where)->sum('total_price');
        $this->assign('total',$total?$total:0);

        $this->display("orderList");
    }



    /**
     * 单条订单
     */
    public function oneOrder(){
        $w['p_o_id'] = I('get.p_o_id');
        $res = D("ProductOrder")->where($w)->find();
        $goods_list = json_decode($res['goods_gather'],true);
        $pro = [];
        foreach($goods_list as $k=>$v){
            $pro[$k] = M('Product')->where(array('p_id'=>$v['p_id']))->field('p_id,title,cover_pic')->find();
            if(!empty($pro[$k]['cover_pic'])){
                $pro[$k]['cover_pic'] = C('API_URL').'/'.$pro[$k]['cover_pic'];
            }
            $pro[$k]['attr'] = $v['attr'];
            $pro[$k]['price'] = $v['price'];
            $pro[$k]['num'] = $v['num'];
        }
        $res['goods_list'] = $pro;
        /**找到用户*/
        $ww['m_id'] = $res['m_id'];
        $res_member = D("Member")->where($ww)->find();
        $res['member'] = $res_member;
        /**找到地址*/
        $address = M("Address")->where(['addr_id'=>$res['addr_id']])->find();
        $res['member']['province_name'] = M("Areas")->where(array('area_id'=>$address['province']))->getField('area_name');
        $res['member']['city_name'] = M("Areas")->where(array('area_id'=>$address['city']))->getField('area_name');
        $res['member']['area_name'] = M("Areas")->where(array('area_id'=>$address['area']))->getField('area_name');
        $this->assign("res",$res);
        $this->display("oneOrder");
    }


    /**导出订单excel的表单*/
    public function orderXLS(){

        /**获取Excel导出数据*/
        $where = S('order_w');
        $list = M('ProductOrder')->where($where)->order('ctime DESC')->select();
        $arrOrderName = array('编号','订单号','下单时间','订单总金额','实际支付','用户名称','商家名称','支付方式','是否优惠','优惠金额','省','市','区');
        $arrOrderList = [];
        foreach($list as $key=>$val){
            $pay_type = 0;
            $val['m_name'] = M('Member')->where(array('m_id'=>$val['m_id']))->getField('nick_name');
            $val['s_name'] = M('Shop')->where(array('shop_id'=>$val['shop_id']))->getField('name');
            if($val['pay_type']==0){
                $pay_type = '众享豆';
            }elseif($val['pay_type']==1){
                $pay_type = '支付宝';
            }elseif($val['pay_type']==2){
                $pay_type = '微信';
            }
            if($val['cou_id']){
                $is_coupon = '是';
            }else{
                $is_coupon = '否';
            }
            /**找到省市区级的名称*/
            $address = M("Address")->where(['addr_id'=>$val['addr_id']])->find();
            $province_name = M("Areas")->where(array('area_id'=>$address['province']))->getField('area_name');
            $city_name = M("Areas")->where(array('area_id'=>$address['city']))->getField('area_name');
            $area_name = M("Areas")->where(array('area_id'=>$address['area']))->getField('area_name');
            $arrOrderList[] = array($val['p_o_id'],$val['order_sn'],date('Y-m-d H:i:s',$val['ctime']),$val['total_price'],$val['real_price'],
                $val['m_name'],$val['s_name'],$pay_type,
                $is_coupon,$val['coupon_money']?$val['coupon_money']:0.00,$province_name,$city_name,$area_name);

        }

        exportexcel($arrOrderList,$arrOrderName,'订单信息');

    }

}