<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 商家端和用户端退款的操作
 */
class BackController extends ApiBasicController{


    public function _initialize(){
        parent::_initialize();

    }

    /**商家发起退款申请
     * @time 2017-10-16
     * @author crazy
     * @param b_id 账单的id
     * @param price 退款的钱数
     */
    public function applyBack(){
        $bill_obj = M("Bill");
        $member_obj = M("Member");
        M()->startTrans();
        if(empty(I('post.b_id'))){
            apiResponse("error",'参数错误');
        }
        $bill_res = $bill_obj->where(array('b_id'=>I('post.b_id')))->field('m_id,accept_m_id,status,total_price,other_b_id,o_id,deduction,pay_type')->limit(1)->find();
        if(empty($bill_res)){
            apiResponse("error","该订单不存在！");
        }
        $integral = $member_obj->where(array('m_id'=>$bill_res['accept_m_id']))->field('m_id,integral,back_inter,is_set')->find();
        $temp = (floatval($bill_res['total_price'])-floatval($bill_res['deduction'])) < (floatval($integral['integral'])-floatval($integral['back_inter']))
            ? (floatval($bill_res['total_price'])-floatval($bill_res['deduction'])) : (floatval($integral['integral'])-floatval($integral['back_inter']));
        if($temp < floatval(I('post.price'))){
            $price_return = $temp<0?0:$temp;
            apiResponse("error","最多可退{$price_return}元");
        }
        $set_status = $bill_obj->where(array('b_id'=>I('post.b_id')))->limit(1)->save(array('status'=>2,'return_price'=>I('post.price')));
        $set_status_other = $bill_obj->where(array('b_id'=>$bill_res['other_b_id']))->limit(1)->save(array('status'=>2,'back_price'=>I('post.price')));
        /**添加用户退款的钱数*/
        $back_inter = sprintf("%.2f",(floatval($integral['back_inter'])+floatval(I('post.price'))));
        $member_res = $member_obj->where(array('m_id'=>$bill_res['accept_m_id']))->limit(1)->save(array('back_inter'=>$back_inter));
//        apiResponse($set_status,$set_status_other,$member_res);
        if($set_status && $set_status_other && $member_res){
            M()->commit();
            /**给用户发送短信*/
            $shop_name = M("Shop")->where(array('shop_id'=>$bill_res['m_id']))->limit(1)->getField('name');
            $price = I('post.price');
            $this->addMessage($bill_res['accept_m_id'],"商家{$shop_name}退款申请","商家{$shop_name}发起{$price}元退款申请！",0,$price,$bill_res['m_id'],0,1);
            if($integral['is_set'] == 1){
                try{
                    $alias = ''.$integral['m_id'];
                    $extra['b_id'] = I('post.b_id');
                    $extra['type'] = '1';
                    $this->push("",$alias,"商家{$shop_name}发起{$price}元申请！","商家{$shop_name}发起{$price}元退款申请！",$extra,0);
                }catch (\Exception $e){
                    apiResponse("success","处理成功！");
                }
            }
            apiResponse("success","处理成功！");
        }else{
            M()->rollback();
            apiResponse("error","处理失败！");
        }
    }

    /**
     * 用户确定退款
     * @time 2017-10-16
     * @author crazy
     * @param b_id 账单的id
     * @param m_id 用户的id
     */
    public function makeSure(){
        $bill_obj = M("Bill");
        $member_obj = M("Member");
        $shop_obj = M("Shop");
        $pie_obj = M("Pie");
        M()->startTrans();
        $bill_res = $bill_obj->where(array('b_id'=>I('post.b_id')))->field('m_id,back_price,shop_id,o_id,pay_type')->find();
        if(empty($bill_res)){
            apiResponse("error","该订单不存在！");
        }
        if(I('post.m_id') != $bill_res['m_id']){
            apiResponse("error",'参数不合法');
        }
        if(empty(I('post.b_id'))){
            apiResponse("error",'参数错误');
        }
        if(empty(I('post.m_id'))){
            apiResponse("error",'参数错误');
        }
        /**查看用户信息*/
        $mem_res = $member_obj->where(array('m_id'=>I('post.m_id')))->field('integral,piles,back_inter,nick_name')->limit(1)->find();
        /**修改账单的状态*/
        $set_status = $bill_obj->where(array('b_id'=>I('post.b_id')))->limit(1)->save(array('status'=>3));
        $set_status_other = $bill_obj->where(array('o_id'=>$bill_res['o_id'],'other_b_id'=>I('post.b_id')))->limit(1)->save(array('status'=>3));
        /**查看符合标注的购买股份的人员的最低值*/
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        /**修改用户的股数和麦穗--减去退款的股数*/
        $x_inter = sprintf('%.2f',floatval($mem_res['integral'])-floatval($bill_res['back_price']));
        $last_piles = (floatval($x_inter))/$meet_pay_price;
        $back_inter = sprintf('%.2f',floatval($mem_res['back_inter'])-floatval($bill_res['back_price']))<0?0:sprintf('%.2f',floatval($mem_res['back_inter'])-floatval($bill_res['back_price']));
        $member_save = $member_obj->where(array('m_id'=>I('post.m_id')))->limit(1)->save(array('integral'=>$x_inter,'piles'=>floor($last_piles),'back_inter'=>$back_inter));
        /**删除用户的股数*/
        $del_count = $mem_res['piles']-floor($last_piles);
        if($del_count>0){
            $pie_res = $pie_obj->where(array('mix_id'=>I('post.m_id')))->order('ctime desc')->limit($del_count)->delete();
        }else{
            $pie_res = 1;
        }
        /**查看配置比例*/
        $config_res = M('Config')->getField('reimburse');
        /**计算给商家的麦穗*/
        $back_price = sprintf('%.2f',($config_res*$bill_res['back_price']));
        /**商家计算股份*/
        $shop_res = $shop_obj->where(array('shop_id'=>$bill_res['shop_id']))->limit(1)->field('integral,piles,is_set,name')->find();
        if((floatval($back_price)+floatval($shop_res['integral']))>=$meet_pay_price){
            /**满足就添加一股*/
            $a_shop = ((floatval($back_price))+floatval($shop_res['integral']))/$meet_pay_price;
            /**添加商家的股数*/
            if(floor($a_shop)>$shop_res['piles']) {
                $shop_x_pie = floor($a_shop)-$shop_res['piles'];
                for ($i = 1; $i <= floor($shop_x_pie); $i++) {
                    $pie_data['mix_id'] = $bill_res['shop_id'];
                    $pie_data['pie'] = 1;
                    $pie_data['ctime'] = time();
                    $pie_data['type'] = 1;
                    M("Pie")->add($pie_data);
                }
            }
            $after_data_shop['piles'] = floor($a_shop);
            $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($back_price);
            $after_data_shop['utime'] = time()+1;
            $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$bill_res['shop_id']))->limit(1)->save($after_data_shop);
        }else{
            $after_data_shop['integral'] = floatval($shop_res['integral'])+floatval($back_price);
            $after_data_shop['utime'] = time()+2;
            $invest_res_trans_shop = M("Shop")->where(array('shop_id'=>$bill_res['shop_id']))->limit(1)->save($after_data_shop);
        }
        if($invest_res_trans_shop && $set_status && $member_save && $pie_res && $set_status_other){
            M()->commit();
            $price = $bill_res['back_price'];
            $this->addMessage($bill_res['shop_id'],"用户{$mem_res['nick_name']}确认退款","用户{$mem_res['nick_name']}确认退款！",1,$price,I('post.m_id'),0,1);
            if($shop_res['is_set'] == 1){
                try{
                    $alias = ''.$bill_res['shop_id'];
                    $extra['b_id'] = $bill_obj->where(array('o_id'=>$bill_res['o_id'],'other_b_id'=>I('post.b_id')))->limit(1)->getField('b_id');
                    $extra['type'] = '1';
                    $this->push("",$alias, "用户{$mem_res['nick_name']}确认退款", "用户{$mem_res['nick_name']}确认退款！", $extra, 1);
                }catch (\Exception $e){
                    apiResponse("success","处理成功！");
                }
            }
            apiResponse("success","处理成功！");
        }else{
            M()->rollback();
            apiResponse("error","处理失败！");
        }
    }










































    public function pageUrl(){
        $postStr = file_get_contents("php://input");//返回回复数据
        file_put_contents('xml1.txt',rawurldecode($postStr));
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    }
    public function ajaxUrl(){
        file_put_contents("hxa1.txt",1);
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//返回回复数据
        file_put_contents("hxa.txt",$postStr);
        echo $postStr;
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        echo $postObj;
    }

    public function request_post($url = '', $post_data = array()) {
        if (empty($url) || empty($post_data)) {
            return false;
        }

        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        $postUrl = $url;
        $curlPost = $post_data;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);

        return $data;
    }


    /**
     * @param $xml
     * @return mixed
     * ：将xml转为array
     */
    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }



}