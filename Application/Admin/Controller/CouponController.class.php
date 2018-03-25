<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 优惠券活动管理
 */
class CouponController extends AdminBasicController {
    public $coupon = '';
    public function _initialize(){
        $this->checkLogin();
        $this->coupon = D('Coupon');
    }

    /**
     * 优惠券活动列表
     * @time 2017-08-29
     * @author mss
    */
    public function couponList(){
        $where['status'] = array('neq',9);
        $param = array();
        $request = array();
        if(!empty(I('request.start_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $where['start_time'] = array('EGT',$start_time);
            $param['start_time'] = $start_time;
        }
        if(!empty(I('request.end_time'))){
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $where['end_time'] = array('ELT',$end_time);
            $param['end_time'] = $end_time;
        }
        if(!empty(I('request.title'))){
            $title = I('request.title');
            $request['title'] = $title;
            $where['title'] = array('LIKE','%'.$title.'%');
            $param['title'] = $title;
        }
        if(!empty(I('request.name'))){
            $name = I('request.name');
            $request['name'] = $name;
            $w['name'] = array('LIKE','%'.$name.'%');
            $w['status'] = array('NEQ',9);
            $shopid = M('Shop')->where($w)->getField('shop_id',true);
            $where['shop_id'] = array('IN',$shopid);
            $param['name'] = $name;
        }
        $this->assign('request',$request);

        $list = $this->coupon->selectCoupon($where,'ctime desc',15,$param);
        if($list['list']){
            foreach($list['list'] as $k=>$v){
                $list['list'][$k]['s_name'] = M('Shop')->where(array('shop_id'=>$v['shop_id']))->limit(1)->getField('name');
                //统计订单数量
                $list['list'][$k]['order_sum'] = M('Order')->where(array('shop_id'=>$v['shop_id'],'ctime'=>array('BETWEEN',array(strtotime($v['start_time']),strtotime($v['end_time'])))))->count();
                //统计已领取的用户的数量
                $list['list'][$k]['mem_num'] = M('CouponMember')->where(array('coupon_id'=>$v['coupon_id'],'status'=>array('NEQ',9)))->count();
                //统计已使用的数量
                $list['list'][$k]['used_num'] = M('CouponMember')->where(array('coupon_id'=>$v['coupon_id'],'status'=>1))->count();
            }
        }
        $this->assign('coupon',$list['list']);
        $this->assign('page',$list['page']);

        //总的页数
        $pages = ceil(M('Coupon')->where($where)->count/15);
        $this->assign('pages',$pages);

        $this->display("couponList");
    }


    /**
     * 添加优惠券
     * @time 2017-08-29
     * @author mss
    */
    public function addCoupon(){
        if(!IS_POST){

            $this->display("addCoupon");
        }else{
            $data = $this->coupon->create();
            if($data){
                $data['ctime'] = time();
                $res = $this->coupon->add($data);
                if($res){
                    $this->success("添加成功！",U('Coupon/couponList'));
                }else{
                    $this->error("添加失败！");
                }
            }else{
                $this->error($this->coupon->getError());
            }

        }
    }

    /**
     * 判断关联的商家id是否合法
     * @time 2017-08-29
     * @author mss
     * @param shop_id 商家id
     */
//    public function isShop(){
//        $shop = M('Shop')->where(array('shop_id'=>$_POST['shop_id'],'status'=>array('neq','9')))->getField("shop_id");
//        if(!$shop){
//            $this->ajaxReturn(1);
//        }
//    }


    /**
     * 优惠券修改
     * @time 2017-08-29
     * @author mss
     * @param coupon_id 优惠券id
    */
    public function editCoupon(){
        if(!IS_POST){
            $where['coupon_id'] = $_GET['coupon_id'];
            $coupon = $this->coupon->where($where)->limit(1)->find();
            $this->assign('coupon',$coupon);

            $this->display("editCoupon");
        }else{
            $data = $this->coupon->create();
            if($data){
                $data['utime'] = time();
                $res = $this->coupon->where(array("coupon_id"=>$_POST['coupon_id']))->limit(1)->save($data);
                if($res){
                    $this->success("修改成功！",U("Coupon/couponList"));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error($this->coupon->getError());
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteCoupon(){
        if(empty($_REQUEST['coupon_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['coupon_id'] = array('IN',I('request.coupon_id'));
        $data['status'] = 9;
        $upd_res = $this->coupon->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * 领取优惠券的会员列表
     * @time 2017-08-29
     * @author mss
     * @param coupon_id 优惠券id
     */
    public function memList(){
        $param = array();
        $where = array();
        $coupon_id = I('request.coupon_id');
        $param['coupon_id'] = $coupon_id;
        $where['coupon_id'] = $coupon_id;
        if(!empty(I('request.start_time'))&&!empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign('start_time',$start_time);
            $end_time = I('request.end_time');
            $this->assign('end_time',$end_time);
            $where['ctime'] = array('BETWEEN',array(strtotime($start_time),strtotime($end_time)));
            $param['start_time'] = $start_time;
            $param['end_time'] = $end_time;
        }
        if(!empty(I('request.status'))){
            $status = I('request.status');
            $this->assign('status',$status);
            $where['status'] = $status-1;
            $param['status'] = $status;
        }
        $list = D('CouponMember')->where($where)->selectCouponMember($where,'ctime DESC',20,$param);
        if($list['list']){
            foreach($list['list'] as $k=>$v){
                $list['list'][$k]['m_name'] = M('Member')->where(array('m_id'=>$v['m_id']))->limit(1)->getField('nick_name');
                $list['list'][$k]['use_time'] = M('CouponLog')->where(array('m_id'=>$v['m_id'],'c_m_id'=>$v['c_m_id']))->limit(1)->getField('ctime');
            }
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->assign('coupon_id',$coupon_id);

        $this->display('memList');
    }





}