<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class TransferController
 * @package Api\Controller
 * 后续不返商家麦穗，支持商家把麦穗转给相同手机号的用户进行后续操作
 */
class TransferController extends ApiBasicController{
    public function _initialize(){
        parent::_initialize();

    }


    /**method post
     * 给商家的用户端进行转麦穗的操作
     * @author crazy
     * @time 2018-01-14
     * @param shop_id 商家的id
     */
    public function transferMemberShow(){
        /**找到商家的账号*/
        $account = M("Shop")->where(['shop_id'=>$_POST['shop_id']])->getField('account');
        /**找到用户的账号信息*/
        $member = M("Member")->where(['status'=>0,'account'=>$account])->field("m_id,account,head_pic,nick_name")->find();
        if(empty($member)){
            apiResponse("error","用户不存在！");
        }
        $member['head_pic'] = C("API_URL").$member['head_pic'];
        apiResponse("success","获取成功",$member);
    }


    /**商家给用户转账的操作
     * @author crazy
     * @time 2018-01-14
     * @param shop_id
     * m_id
     * integral
     */
    public function doTransferMember(){
        M()->startTrans();
        if(empty(I("post.m_id"))||empty(I("post.shop_id"))){
            apiResponse("error","参数错误！");
        }
        $m_id = intval(I("post.m_id"));
        $shop_id = intval(I("post.shop_id"));
        $integral = intval(I("post.integral"));
        /**查看符合标注的购买股份的人员的最低值*/
        $meet_pay_price = M("Config")->getField("meet_pay_price");

        /**找到用户的积分*/
        $integral_mem = M("Member")->where(['m_id'=>$m_id])->field("integral,piles,nick_name")->find();

        /**找到商家的积分*/
        $integral_shop = M("Shop")->where(['shop_id'=>$shop_id])->field("integral,name,piles")->find();

        $s = (floatval($integral))/$meet_pay_price;
        $shop_pie_x = $integral_shop['piles'] - floor($s);

        if($integral_shop['integral']<=0){
            apiResponse('error',"您暂无麦穗");
        }
        /**找到商家的股数*/
        $shop_count = M("Pie")->where(['type'=>1,'mix_id'=>$shop_id?$shop_id:0])->count();

        /**计算转了多少麦穗*/
        $transfer_int = floatval($integral_shop['integral'])-floatval($integral);
        /**修改商家的麦穗数*/
        $shop_integral = [
            'integral'=>$transfer_int,
            'piles'=>$shop_pie_x
        ];
        $edit_shop = M("Shop")->where(['shop_id'=>$shop_id])->limit(1)->save($shop_integral);

        /**删除商家的股数*/
        if($shop_count>0){
            $shop_pie = M("Pie")->where(['type'=>1,'mix_id'=>$shop_id?$shop_id:0])->limit(floor($s))->delete();
        }else{
            $shop_pie = 1;
        }
        /**添加用户的股数和麦穗*/
        if((floatval($integral))>=$meet_pay_price){
            /**满足就添加一股*/
            $a = (floatval($integral))/$meet_pay_price;
            if(floor($a)>$integral_mem['piles']){
                /**添加用户的股数*/
                $member_pie_x = floor($a)-$integral_mem['piles'];
                for ($i=1;$i<=floor($member_pie_x);$i++){
                    $pie_data['mix_id'] = $m_id;
                    $pie_data['pie'] = 1;
                    $pie_data['ctime'] = time();
                    $pie_data['type'] = 0;
                    M("Pie")->add($pie_data);
                }
            }
            $after_data['piles'] = floor($a);
            $after_data['integral'] = floatval($integral)+floatval($integral_mem['integral']);
            $after_data['utime'] = time();
            $invest_res_trans = M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($after_data);
        }else{
            $after_data['integral'] = floatval($integral)+floatval($integral_mem['integral']);
            $after_data['utime'] = time();
            $invest_res_trans = M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($after_data);
        }
        /**添加商家麦穗转账消息*/
        $title = "转麦穗操作记录";
        $this->addMessage($shop_id,$title,date("Y-m-d H:i:s")."转给用户{$integral_mem['nick_name']}{$integral}麦穗",1,$integral,$m_id,0,0);
        /**添加用户的麦穗的转账的消息*/
        $this->addMessage($m_id,$title,date("Y-m-d H:i:s")."收到商家{$integral_shop['name']}{$integral}麦穗",0,$integral,$shop_id,0,0);
        if($edit_shop&&$shop_pie&&$invest_res_trans){
            M()->commit();
            apiResponse("success","操作成功");
        }else{
            M()->rollback();
            apiResponse("error","操作失败");

        }

    }





}