<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class BankController
 * @package Api\Controller
 * 银行卡模块
 */
class BankController extends ApiBasicController{

    public $Bank = '';
    public function _initialize(){
        parent::_initialize();
        $this->Bank = D('Bank');
    }

    /**
     * 银行卡列表
     * @author crazy
     * @time 2017-07-03
     */
    public function bankList(){
        if(empty($_GET['m_id'])){
            apiResponse("error","参数错误");
        }elseif(empty($_GET['type'])){
            apiResponse("error","参数错误");
        }
        $where['m_id'] = $_GET['m_id'];
        $where['type'] = $_GET['type'];
        $list = $this->Bank->selectBank($where,"","15","","b_a_id,bank_id,number");
        foreach($list['list'] as $k=>$v){
            $data[$k]['b_a_id'] = $v['b_a_id'];
            $data[$k]['bank_id'] = M("bank")->where(array('bank_id'=>$v['bank_id']))->getField("name");
            $data[$k]['bank_pic'] = C("API_URL").M("bank")->where(array('bank_id'=>$v['bank_id']))->getField("bank_pic");
            $data[$k]['field'] = "尾号".substr($v['number'],-4)."储蓄卡";
        }
        if(empty($data) && $_GET['p']>1){
            apiResponse("error","已加载全部");
        }elseif(empty($data)){
            apiResponse("error","暂无数据");
        }else{
            apiResponse("success","加载成功",$data);
        }
    }
    /**
     * 取消关联
     * @author crazy
     * @time 2017-07-03
     */
    public function deleteBank(){
        if(empty($_POST['b_a_id'])){
            apiResponse("error","参数错误");
        }
        $where['b_a_id'] = $_POST['b_a_id'];
        $res = $this->Bank->deleteBank($where);
        IF($res){
            apiResponse("success","取消成功");
        }else{
            apiResponse("error","取消失败");
        }
    }

    /**
     * 银行卡类型
     * @author crazy
     * @time 2017-07-03
     */
    public function bankType(){
        $list = M("bank")->select();
        foreach($list as $k=>$v){
            $list[$k]['bank_pic'] = C("API_URL").$v['bank_pic'];
        }
        if(empty($list)){
            apiResponse("error","暂无数据");
        }else{
            apiResponse("success","加载成功",$list);
        }
    }
    /**
     * 添加银行卡
     * @author crazy
     * @time 2017-07-03
     */
    public function addBank(){
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误");
        }elseif(empty($_POST['type'])){
            apiResponse("error","参数错误");
        }elseif(empty($_POST['bank_id'])){
            apiResponse("error","请选择卡类型");
        }elseif(empty($_POST['name'])){
            apiResponse("error","持卡人姓名不能为空");
        }elseif(empty($_POST['number'])){
            apiResponse("error","银行卡卡号不能为空");
        }elseif(empty($_POST['card'])){
            apiResponse("error","身份证号不能为空");
        }elseif(empty($_POST['phone'])){
            apiResponse("error","手机号不能为空");
        }elseif(!preg_match("/^(\d{15}$|^\d{18}$|^\d{17}(\d|X|x))$/",$_POST['card'])){
            apiResponse("error","请输入正确身份证号");
        }elseif(!preg_match("/^\d{16}|\d{19}$/",$_POST['number'])){
            apiResponse("error","请输入正确银行卡卡号");
        }elseif(!preg_match("/^0?(13[0-9]|15[012356789]|17[012356789]|18[102356789]|14[57])[0-9]{8}$/",$_POST['phone'])){
            apiResponse("error","请输入正确手机号");
        }
        $data = $this->Bank->create();
        $res = $this->Bank->addBank($data);
        if($res){
            apiResponse("success","添加成功");
        }else{
            apiResponse("error","添加失败");
        }
    }

    /**
     * 提现
     * @author crazy
     * @time 2017-07-03
     */
    public function withDrawal(){
        $data = D("Bill")->create();
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误");
        }elseif(empty($_POST['price'])){
            apiResponse("error","请选择提现金额");
        }elseif(empty($_POST['type'])){
            apiResponse("error","参数错误");
        }elseif($_POST['price']<0){
            apiResponse("error","参数错误");
        }
        if($_POST['pay_type'] == 1){
            if(empty($_POST['account'])){
                apiResponse("error","支付宝账号不能为空!");
            }
            $data['content'] = "支付宝";
        }elseif($_POST['pay_type'] == 3){
            if(empty($_POST['bank_id'])){
                apiResponse("error","请选择银行卡");
            }elseif(empty($_POST['bank'])){
                apiResponse("error","参数错误");
            }
            $data['content'] = $_POST['bank'];
        }

        //获取用户余额并判断余额是否足够
        if($_POST['type'] == 1 or $_POST['type'] == 2){
            $where['com_id'] = $_POST['m_id'];
            $user = D("Company")->findCompany($where,"wallet,freeze_price");
            if($user['wallet'] <= $user['freeze_price']){
                apiResponse("error","可用资金不足!");
            }
        }elseif($_POST['type'] == 3){
            $where['mar_id'] = $_POST['m_id'];
            $user = D("Marki")->findMarki($where,"wallet");
        }
        if($user['wallet']<$_POST['price']){
            apiResponse("error","余额不足！");
        }
        $data['pay_type'] = 3;
        $data['modified'] = 1;
        $data['title'] = "提现";
        $data['way'] = 1;
        $data['ctime'] = time();
        //如果是商家端的提现操作计算配送费并扣除
        if($_POST['type']==1 || $_POST['type']==2){
//            $where1['shop_id'] = $_POST['m_id'];
//            $where1['status'] = array('not in','1,9');
//            $num = "";
//            $last = "";
//            //获取所有去重后订单id
//            $order_id = D("Orderlog")->where($where1)->field("order_id")->group("order_id")->select();
//            foreach($order_id as $k=>$v){
//                $where1['order_id'] = $v['order_id'];
//                $order = D("Orderlog")->where($where1)->select();
//                //获取订单的重量
//                foreach($order as $kk=>$vv){
//                    //修改所有订单状态为1  //1.已经题现过
//                    $status['status'] = 1;
//                    $w['log_id'] = $vv['log_id'];
//                    D("Orderlog")->where($w)->save($status);
//                    $where2['g_id'] = $vv['goods_id'];
//                    $goods = D("Goods")->findGoods($where2);
//                    //计算重量
//                    if($goods['u_id']==1){
//                        $num += $vv['num'];
//                    }else{
//                        $total = $vv['g_price']*$vv['num'];
//                        $half = round($total / $goods['half_price']);
//                        $num += $half;
//                    }
//                }
//                //获取该重量区间的费用
//                $where3['type'] = 1;
//                $where3['start'] = array('elt',$num);
//                $where3['status'] = array('neq',9);
//                $delivery = M("delivery")->where($where3)->order("start desc")->find();
//                $price = $delivery['base_price']+$delivery['price'];
//                $num = "";
//                $last += $price;
//            }
            //如果运营费大于0添加一条纪录

            $user = D("Company")->where(array("com_id"=>$_POST['m_id']))->limit(1)->find();
            $last = $_POST['price']*($user['scale']/100);
            if($last>0){
                $arr['title'] = "运营费";
                $arr['m_id'] = $_POST['m_id'];
                $arr['content'] = "商品结算";
                $arr['modified'] = 1;
                $arr['ctime'] = time();
                $arr['type'] = $_POST['type'];
                $arr['way'] = 3;
                $arr['is_run'] = 1;
                $arr['price'] = $last;
                D("Bill")->addBill($arr);
            }
            //修改提现状态
            $where4['b_id'] = $_POST['id'];
            $data['price'] = $_POST['price']-$last;
            //修改商家结算金额
            $where5['com_id'] = $_POST['m_id'];
            $company = D("Company")->findCompany($where5,"settlement_price");
            $company['settlement_price'] += $data['price'];
            D("Company")->where($where5)->limit(1)->save($company);
        }elseif($_POST['type'] == 3){
            $marki_where['mar_id'] = $_POST['m_id'];
            $marki = D("Marki")->where($marki_where)->limit(1)->find();
            $yunying = ($marki['divide']/100)*$_POST['price'];
            $data['price'] = $_POST['price']-$yunying;
            if($yunying>0){
                $arr['title'] = "运营费";
                $arr['m_id'] = $_POST['m_id'];
                $arr['content'] = "佣金比例";
                $arr['modified'] = 1;
                $arr['ctime'] = time();
                $arr['type'] = $_POST['type'];
                $arr['way'] = 3;
                $arr['is_run'] = 1;
                $arr['price'] = $yunying;
                D("Bill")->addBill($arr);
            }
        }
        $res = D("Bill")->add($data);
        if($res){
            if($_POST['type'] == 1 or $_POST['type'] == 2){
                $list['wallet'] = $user['wallet']-$_POST['price'];
                $result = D("Company")->where($where)->limit(1)->save($list);
            }elseif($_POST['type'] == 3){
                $list['wallet'] = $user['wallet']-$_POST['price'];
                $result = D("Marki")->where($where)->limit(1)->save($list);
            }
            if($result){
                apiResponse("success","提现成功");
            }
        }else{
            apiResponse("success","提现失败");
        }
    }
}