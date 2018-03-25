<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * Class OrderController
 * @package Admin\Controller
 */
class OrderController extends AdminBasicController {
    public $Order = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Order = D('Order');
    }
    /**
     * 获取全部的订单（非积分）
     * 传参方式 get
     * @author crazy  mashanshan修改，修改时间：2017-08-06
     */
    public function orderList(){
        $a_id = session('A_ID');
            //昵称查找
            $where = array();
            $request = array();
            $parameter = array();
            if (!empty($_REQUEST['order_sn'])) {
                $where['order_sn'] = array('LIKE', "%" . I('request.order_sn') . "%");
                $parameter['order_sn'] = I('request.order_sn');
                $request['order_sn'] = I('request.order_sn');
                $this->assign("request", $request);
            }
            /**用户昵称查找*/
            if (!empty(I('request.nick_name'))) {
                $w['nick_name'] = array('LIKE', '%' . I('request.nick_name') . '%');
                $w['status'] = array('neq', 9);
                $m_ids = M('member')->where($w)->getField('m_id', true);
                $where['m_id'] = array('IN', $m_ids);
                $parameter['nick_name'] = I('request.nick_name');
                $request['nick_name'] = I('request.nick_name');
                $this->assign('request', $request);
            }
            if (!empty(I('request.start_time')) && !empty(I('request.end_time'))) {
                $start_time = I('request.start_time');
                $this->assign("start_time", $start_time);
                $end_time = I('request.end_time');
                $this->assign("end_time", $end_time);
                $where['ctime'] = array(array('EGT', strtotime($start_time)), array('ELT', strtotime($end_time)), 'and');
                $parameter['start_time'] = I('request.start_time');
                $parameter['end_time'] = I('request.end_time');
            }
            if (!empty($_REQUEST['pay_type'])) {
                $where['pay_type'] = $_REQUEST['pay_type'] - 1;
                $parameter['pay_type'] = I('request.pay_type');
                $this->assign("pay_type", I('request.pay_type'));
            }

            //商家名称查找，2017-12-05mss添加
            if ($_REQUEST['shop_name']) {
                $name = $_REQUEST['shop_name'];
                $shop_id = M('Shop')->where(array('status' => array('NEQ', 9), 'name' => array('LIKE', '%' . $name . '%')))->getField('shop_id', true);
                $where['shop_id'] = array('IN', $shop_id);
                $parameter['shop_name'] = $name;
                $request['shop_name'] = $name;
                $this->assign('request', $request);
            }

            if ($_REQUEST['shop_id']) {
                $where['shop_id'] = $_REQUEST['shop_id'];
                $parameter['shop_id'] = I('request.shop_id');
                $this->assign("shop_id", I('request.shop_id'));
            }
            $where['status'] = array('NEQ', 9);
        if($a_id != 14) {
            S('order_w', $where);
            $reslist = $this->Order->selectOrder($where, "ctime desc", '15', $parameter);
            $resarr = $reslist['list'];
            foreach ($resarr as $key => $val) {
                /**查看用户的信息*/
                $ww['m_id'] = $val['m_id'];
                $res = D("Member")->where($ww)->find();
                $reslist['list'][$key]['member'] = $res;
                /**查看商家的信息*/
                $www['shop_id'] = $val['shop_id'];
                $shop = D("Shop")->where($www)->find();
                $reslist['list'][$key]['shop'] = $shop;
                /**找到省市区级的名称*/
                $reslist['list'][$key]['province_name'] = M("Areas")->where(array('area_id' => $val['province']))->getField('area_name');
                $reslist['list'][$key]['city_name'] = M("Areas")->where(array('area_id' => $val['city']))->getField('area_name');
                $reslist['list'][$key]['area_name'] = M("Areas")->where(array('area_id' => $val['area']))->getField('area_name');
            }
            $this->assign('Order', $reslist['list']);
            $this->assign('page', $reslist['page']);
        }
        //统计订单总额
        $total = M('Order')->where($where)->sum('total_price');
        $this->assign('total',$total?$total:0);

        $this->display("orderList");
    }


    /**
     * 获取全部的订单（非积分）
     */
    public function orderListCash(){
        //昵称查找
        $parameter = array();
        $where = array();
        $request = array();
        if(!empty($_REQUEST['order_sn'])){
            $where['order_sn'] = array('LIKE',"%".I('request.order_sn')."%");
            $parameter['order_sn'] = I('request.order_sn');
            $request['order_sn'] = I('request.order_sn');
            $this->assign("request",$request);
        }
        /**时间*/
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $res_e = "";
            /**判断商家入住的时间和加盟商入驻的时间*/
            if(I('request.e_id') != 5 && !empty(I('request.e_id'))) {
                $where_e['e_id'] = I('request.e_id');
                $res_e = D("agency_earn")->where($where_e)->find();
                if(strtotime(I('request.start_time'))>intval($res_e['ctime'])){
                    $start_time = date("Y-m-d",$res_e['ctime']);
                }else{
                    $start_time = I('request.start_time');
                }
            }else{
                $start_time = I('request.start_time');
            }
            $after_start_time = $start_time.' '."00:00:00";
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $after_end_time = $end_time.' '."23:59:59";
            $this->assign("end_time",$end_time);
            $where['ctime'] = array(array('EGT',strtotime($after_start_time)),array('ELT',strtotime($after_end_time)),'and');
            $parameter['start_time'] = $start_time;
            $parameter['e_id'] = I('request.e_id');
            $parameter['end_time'] = I('request.end_time');
        }else{
            if(I('request.e_id') != 5) {
                $where_e['e_id'] = I('request.e_id');
                $res_e = D("agency_earn")->where($where_e)->find();
                /**加盟商入住的日期开始*/
                $where['ctime'] = array('EGT', $res_e['ctime']);
            }
        }
        if(I('request.e_id') != 5){
            $where_e['e_id'] = I('request.e_id');
            $res_e = D("agency_earn")->where($where_e)->find();
            /**最近一次打款的日期*/
            $this->assign("period_time",$res_e['period_time']);
            /**获取行业分类*/
            $w_data = array();
            $class_list = M('CashType')->where(array('status'=>array('neq',9),'cash_id'=>I('request.e_id')))->field('cash_type_id')->select();
            foreach ($class_list as $k=>$v){
                $w_data[] = $v['cash_type_id'];
            }
            $string = implode(',',$w_data);
            if($res_e['module'] == 1){
                $where['city'] = $res_e['city'];
                $where['class_id'] = array('in',$string);
            }else{
                if($res_e['type'] == 1){
                    $where['city'] = $res_e['city'];
                }elseif ($res_e['type'] == 2){
                    $where['area'] = $res_e['area'];
                }
            }
        }
        $where['status'] = array('NEQ',9);
        $reslist = $this->Order->selectOrder($where,"ctime desc",'15',$parameter);
        /**计算总的销售额*/
        $sum_price = $this->Order->where($where)->sum("total_price");
        $this->assign("sum_price",$sum_price);
        /**
         * 计算应该打款的额度
         */
        $deduct_price = sprintf("%.2f",($res_e['scale']/1000)*$sum_price);
        $this->assign("deduct_price",sprintf("%.2f",$deduct_price));

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

        $this->display("orderListCash");
    }


    /**商家列表*/
    public function shopList(){
        $request = array();
        if(I('request.name')){
            $w['name'] = array('LIKE','%'.I('request.name').'%');
            $request['name'] = I('request.name');
            $param['name'] = I('request.name');

        }
        if(I('request.account')){
            $w['account'] = array('LIKE','%'.I('request.account').'%');
            $request['account'] = I('request.account');
            $param['account'] = I('request.account');
        }
        if(I('request.class_id')){
            $w['class_id'] = I('request.class_id');
            $request['class_id'] = I('request.class_id');
            $param['class_id'] = I('request.class_id');
        }
        if($_REQUEST['status']){
            $w['status'] = $_REQUEST['status']-1;
            $param['status'] = I('request.status');
        }else{
            $w['status'] = array('neq',9);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            /**判断商家入住的时间和加盟商入驻的时间*/
            if(I('request.e_id') != 5 && !empty(I('request.e_id'))) {
                $where_e['e_id'] = I('request.e_id');
                $res_e = D("agency_earn")->where($where_e)->find();
                if(strtotime(I('request.start_time'))>intval($res_e['ctime'])){
                    $start_time = date("Y-m-d H:i:s",$res_e['ctime']);
                }else{
                    $start_time = I('request.start_time');
                }
            }else{
                $start_time = I('request.start_time');
            }
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $param['start_time'] = $start_time;
            $param['end_time'] = I('request.end_time');
            $param['e_id'] = I('request.e_id');
        }else{
            if(I('request.e_id') != 5) {
                $where_e['e_id'] = I('request.e_id');
                $res_e = D("agency_earn")->where($where_e)->find();
                /**加盟商入住的日期开始*/
                $w['ctime'] = array('EGT', $res_e['ctime']);
            }
        }
        if (I('request.shop_id')){
            $w['shop_id'] = I('request.shop_id');
        }
        /**通过用户的手机号找到他推荐的商家*/
        if(!empty(I('request.mem_account'))){
            $m_id = M("Member")->where(array('account'=>I('request.mem_account'),'status'=>array('neq',9)))->getField("m_id");
            $w['recommend'] = $m_id;
            $request['mem_account'] = I('request.mem_account');
            $param['mem_account'] = I('request.mem_account');
        }
        $request['e_id'] = I('request.e_id');
        $param['e_id'] = I('request.e_id');
        if(I('request.e_id') != 5){
            /**传给前端，筛选*/
            $this->assign("request",$request);
            $where_e['e_id'] = I('request.e_id');
            $res_e = D("agency_earn")->where($where_e)->find();
            /**获取行业分类*/
            $w_data = array();
            $class_list = M('CashType')->where(array('status'=>array('neq',9),'cash_id'=>I('request.e_id')))->field('cash_type_id')->select();
            foreach ($class_list as $k=>$v){
                $w_data[] = $v['cash_type_id'];
            }
            $string = implode(',',$w_data);
            if($res_e['module'] == 1){
                $w['city'] = $res_e['city'];
                $w['class_id'] = array('in',$string);
            }else{
                if($res_e['type'] == 1){
                    $w['city'] = $res_e['city'];
                }elseif ($res_e['type'] == 2){
                    $w['area'] = $res_e['area'];
                }
            }
        }
        //获取菜场类型
        $type = D("Class")->where(array("status"=>array('neq',9)))->field("class_id,name")->select();
        $this->assign("type",$type);
        $list = D("Shop")->selectShop($w,"ctime desc",15,$param);
        foreach ($list['list'] as $k=>$v){
            if(!empty($_REQUEST['name'])){
                $keyword = $_REQUEST['name'];
                $list['list'][$k]['name'] = str_ireplace($keyword,"<font style='color:red;'>$keyword</font>",$v['name']);
            }
            $list['list'][$k]['class_name'] = M("Class")->where(array('class_id'=>$v['class_id']))->getField("name");
            $list['list'][$k]['recommend_account'] = M("Member")->where(array('m_id'=>$v['recommend']))->getField("account");
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("shopList");
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

    /**
     * 发货
     */
    public function sendOrder(){
        $w['o_id'] = I("get.o_id");
        $data['order_status'] = 2;
        $res = D("Order")->where($w)->save($data);
        if($res){
            $this->success('发货成功');
        }else{
            $this->error('发货失败');
        }
    }

    /**导出订单excel的表单*/
    public function orderXLS(){

        /**获取Excel导出数据*/
        $where = S('order_w');
        $list = M('Order')->where($where)->order('ctime DESC')->select();
        $arrordername = array('编号','订单号','下单时间','订单金额','应付商家金额','分成费用','用户名称','商家名称','支付方式','是否优惠','优惠类型','优惠金额','实际支付金额','省','市','区','用户IP地址');
        foreach($list as $key=>$val){

            /*switch ($val['order_status']){
                case 0:
                    $source="待付款";
                    break;
                case 1:
                    $source="待发货";
                    break;
                case 2:
                    $source="待收货";
                    break;
                case 3:
                    $source="确认收货（待评价）";
                    break;
                case 4:
                    $source="完成";
                    break;
            }*/
            $val['m_name'] = M('Member')->where(array('m_id'=>$val['m_id']))->getField('nick_name');
            $val['s_name'] = M('Shop')->where(array('shop_id'=>$val['shop_id']))->getField('name');
            if($val['pay_type']==0){
                $pay_type = '众享豆';
            }elseif($val['pay_type']==1){
                $pay_type = '支付宝';
            }elseif($val['pay_type']==2){
                $pay_type = '微信';
            }
            if($val['c_m_id']){
                $is_coupon = '是';
            }else{
                $is_coupon = '否';
            }
            switch($val['coupon_type']){
                case 1:
                    $type = '定额现金券';
                    break;
                case 2:
                    $type = '折扣券';
                    break;
                case 3:
                    $type = '满减券';
                    break;
                case 4:
                    $type = '菜品券';
                    break;
                default:
                    $type = '无';
                    break;
            }
            if($val['pay_money']>0){
                $pay_money = $val['pay_money'];
            }else{
                $pay_money = $val['total_price'];
            }
            $val['province_name'] = M('Areas')->where(array('area_id'=>$val['province']))->getField('area_name');
            $val['city_name'] = M('Areas')->where(array('area_id'=>$val['city']))->getField('area_name');
            $val['area_name'] = M('Areas')->where(array('area_id'=>$val['area']))->getField('area_name');
            $arrorderlist[] = array($val['o_id'],$val['order_sn'],' '.date('Y-m-d H:i:s',$val['ctime']),$val['total_price'],$val['price'],$val['other_price'],$val['m_name'],$val['s_name'],$pay_type,$is_coupon,$type,$val['coupon_money'],$pay_money,$val['province_name'],$val['city_name'],$val['area_name'],$val['ip']);

        }

        exportexcel($arrorderlist,$arrordername,'订单信息');

    }

}