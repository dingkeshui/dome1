<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 优惠券模块
 */
class CouponController extends ApiBasicController{


    public function _initialize(){
        parent::_initialize();

    }


    /**
     * 优惠券列表
     * 传参方式 post
     * @author mss
     * @time 2017-08-29
     * @param m_id 用户的id
     */
    public function couponList(){
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误");
        }
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $this->updateCoupon($_POST['m_id']);
        $m_id = $_POST['m_id'];
        if(!empty($_POST['status'])){
            if($_POST['status']==1){
                $where['cm.status'] = 0;
            }else{
                $where['cm.status'] = array('BETWEEN',array(1,2));
            }
        }
        $where['cm.m_id'] = $m_id;
        $where['c.status'] = array('NEQ',9);
        $field = 'cm.c_m_id,cm.`status`,c.title,c.`desc`,c.start_time,c.end_time,c.type,c.min_price,c.max_price,c.money';
        $list = M('CouponMember')->alias('cm')->join('LEFT JOIN zxty_coupon as c ON c.coupon_id=cm.coupon_id')->field($field)->where($where)->order('cm.status ASC,c.end_time ASC')->limit($page,10)->select();
        if(empty($list) && $p > 1){
            apiResponse("success","暂无数据！",$list);
        }elseif ($list){
            apiResponse("success","获取成功！",$list);
        }elseif(empty($res)){
            apiResponse("success","暂无数据！");
        }else{
            apiResponse("error","获取失败！");
        }
        
    }

    /**
     * 用户使用优惠券
     * 传参方式 post
     * @author mss
     * @time 2017-08-29
     * @param c_m_id 领取的优惠券id
     */
    public function useCoupon(){
        if(empty($_POST['c_m_id'])){
            apiResponse("error","参数错误");
        }
        M()->startTrans();
        $c_m_id = $_POST['c_m_id'];
        $res = M('CouponMember')->where(array('c_m_id'=>$c_m_id))->find();
        if($res['status']==2){
            apiResponse("error","优惠券已失效");
        }
        //先判断用户的优惠券是否已经使用，避免重复操作
        $log = M('CouponLog')->where(array('c_m_id'=>$c_m_id))->find();
        if($log){
            if($res['status']==0){
                M('CouponMember')->where(array('c_m_id'=>$c_m_id))->limit(1)->save(array('status'=>1));
            }
            apiResponse("error","优惠券已使用");
        }
        $data['m_id'] = $res['m_id'];
        $data['c_m_id'] = $c_m_id;
        $data['ctime'] = time();
        $log_res = M('CouponLog')->add($data);
        $rs = M('CouponMember')->where(array('c_m_id'=>$c_m_id))->limit(1)->save(array('status'=>1));
        if($log_res&&$rs){
            M()->commit();
            apiResponse("success","使用成功");
        }else{
            M()->rollback();
            apiResponse("error","使用失败");
        }

    }

    /**
     * 更改未使用已过期的优惠券的状态
     * @time 2017-08-29
     * @author mss
     */
    public function updateCoupon($m_id){
        $where['cm.m_id'] = $m_id;
        $where['cm.status'] = 0;
        $field = 'cm.c_m_id,c.start_time,c.end_time';
        $list = M('CouponMember')->alias('cm')->join('LEFT JOIN zxty_coupon AS c ON c.coupon_id=cm.coupon_id')->field($field)->where($where)->select();
        if($list){
            foreach($list as $k=>$v){
                if(date('Y-m-d')>$v['end_time']){
                    M('CouponMember')->where(array('c_m_id'=>$v['c_m_id']))->limit(1)->save(array('status'=>2));
                }
            }
        }
    }

    /**
     * 删除单张优惠券
     * 传参方式 post
     * @author mss
     * @time 2017-09-22
     * @param c_m_id 优惠券的id
     */
    public function delCoupon(){
        if(empty($_POST['c_m_id'])){
            apiResponse("error","参数错误");
        }
        $c_m_id = $_POST['c_m_id'];
        $rs = M('CouponMember')->where(array('c_m_id'=>$c_m_id))->limit(1)->setField('status',9);
        if($rs){
            apiResponse("success","删除成功");
        }else{
            apiResponse("error","删除失败");
        }
    }




}