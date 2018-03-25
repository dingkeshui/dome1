<?php
namespace Admin\Controller;
use Think\Controller;
use Think\WechatSign;
use Think\Huanxun;
/**
 * 提现管理管理
 */
class WithdrawController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();
        Vendor('WxPay.lib.WechatSign#Zxty');
    }



    /**平台收入的列表*/
    public function withdrawList(){
        $pam = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        if(!empty($_REQUEST['type'])){
            $w['type'] = $_REQUEST['type']-1;
            $pam['type'] = I('request.type');
            $request['type'] = $_REQUEST['type'];
            $this->assign("request",$request);
        }
        if(!empty($_REQUEST['withdraw_sn'])){
            $w['withdraw_sn'] = $_REQUEST['withdraw_sn'];
            $pam['withdraw_sn'] = I('request.withdraw_sn');
            $request['withdraw_sn'] = $_REQUEST['withdraw_sn'];
            $this->assign("withdraw_sn",$_REQUEST['withdraw_sn']);
        }
        if(!empty($_REQUEST['name'])){
            $w['name|account'] = array('LIKE','%'.$_REQUEST['name'].'%');
            $pam['name'] = $_REQUEST['name'];
            $this->assign('name',$_REQUEST['name']);
        }
        if(!empty($_REQUEST['trade_state'])){
            $w['trade_state'] = $_REQUEST['trade_state'];
            $pam['trade_state'] = I('request.trade_state');
            $request['trade_state'] = $_REQUEST['trade_state'];
            $this->assign("request",$request);
        }else{
            $w['status'] = array('neq',9);
        }
        $list = D("Withdraw")->selectWithdraw($w,"ctime desc",15,$pam);
        //dump(D("Withdraw")->getLastSql());
        foreach ($list['list'] as $k=>$v){
            if($v['type'] == 0){
                /**找到用户的名称*/
                $res_other = M("Member")->where(array('m_id'=>$v['mix_id']))->field('nick_name,openid')->find();
                $list['list'][$k]['other_name'] = $res_other['nick_name'];
                $list['list'][$k]['openid'] = $res_other['openid'];
            }elseif($v['type'] == 1){
                /**找到商家的名称*/
                $res_other = M("Shop")->where(array('shop_id'=>$v['mix_id']))->field('name,openid')->find();
                $list['list'][$k]['other_name'] = $res_other['name'];
                $list['list'][$k]['openid'] = $res_other['openid'];
            }
            /**获取提现的绑定的支付宝或者微信*/
            $list['list'][$k]['withdraw_bank'] = M("withdraw_bank")->where(array('width_id'=>$v['width_id']))->find();
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("withdrawList");
    }

    /**给商家打钱*/
    public function changeStatus(){
        if(I("get.sign") != "zxty_cz"){
            $this->error('参数错误！');
        }
        M("Withdraw")->startTrans();
        M("Pay_log")->startTrans();
        $refund = M("Withdraw")->where(array("w_id"=>$_GET['w_id']))->limit(1)->find();
        /**支付宝进行打款*/
        Vendor('Alipay.Trans');
        $refund_alipay = new \Trans($refund['withdraw_sn'], $refund['account'], $refund['price'], "众享通赢（天津）网络有限公司",$refund['name'],"提现打款");
        $refund_do = $refund_alipay->appTrans();
        if(!$refund_do){
            $this->error("打款失败！请稍后重试！");
        }else{
            $order_id = $refund_do->order_id;
            $out_biz_no = $refund_do->out_biz_no;
            $pay_date = $refund_do->pay_date;
            Vendor('Alipay.Query');
            $query = new \Query($out_biz_no,$order_id);
            $query_res = $query->query();
            if ($query_res->code == "10000" && $query_res->msg == "Success") {
                /**给商家或者用户发消息推送买单的信息*/
                $mess_data_shop['title'] = "提现到账通知！";
                if($refund['type'] == 1){
                    $mess_data_shop['content'] = date('Y-m-d H:i:s',$refund['ctime'])."申请提现的".$refund['price'].'元已经到账！';
                }else{
                    $mess_data_shop['content'] = date('Y-m-d H:i:s',$refund['ctime'])."申请提现的".$refund['total_price'].'元已经到账！扣除手续费'.$refund['other_price'].'元';
                }
                $mess_data_shop['m_id'] = $refund['mix_id'];
                $mess_data_shop['ctime'] = time();
                $mess_data_shop['id_type'] =$refund['type'];
                $mess_data_shop['price'] = $refund['price'];
                M("Message")->add($mess_data_shop);

                /**防止重复填充，判断是否存在*/
                $is_set_res = M("Pay_log")->where(array('out_biz_no'=>$out_biz_no))->limit(1)->find();
                if(empty($is_set_res)){
                    /**先修改打款的状态*/
                    $with_data['status'] = 1;
                    $with_res = M("Withdraw")->where(array("w_id" => $_GET['w_id']))->limit(1)->save($with_data);
                    /**添加支付成功的记录*/
                    $pay_log_data['code'] = $query_res->code;
                    $pay_log_data['msg'] = $query_res->msg;
                    $pay_log_data['order_id'] = $order_id;
                    $pay_log_data['out_biz_no'] = $out_biz_no;
                    $pay_log_data['pay_date'] = $pay_date;
                    $pay_log_data['ctime'] = time();
                    if ($query_res->fail_reason) {
                        $pay_log_data['fail_reason'] = $query_res->fail_reason;
                    }
                    if ($query_res->arrival_time_end) {
                        $pay_log_data['arrival_time_end'] = $query_res->arrival_time_end;
                    }
                    if ($query_res->error_code) {
                        $pay_log_data['error_code'] = $query_res->error_code;
                    }
                    $pay_log_res = M("Pay_log")->add($pay_log_data);
                    if ($with_res && $pay_log_res) {
                        M("Pay_log")->commit();
                        M("Withdraw")->commit();
                        $this->success("打款成功！");
                    } else {
                        M("Pay_log")->rollback();
                        M("Withdraw")->rollback();
                        $this->error("打款失败！请联系管理员进行查看！");
                    }
                }else{
                    $this->success("打款成功！");
                }
            } else {
                /**防止重复填充，判断是否存在*/
                $is_set_error = M("Alipay_error")->where(array('w_id'=>$_GET['w_id']))->limit(1)->find();
                if(empty($is_set_error)) {
                    $error_log_data['w_id'] = $_GET['w_id'];
                    $error_log_data['withdraw_sn'] = $refund['withdraw_sn'];
                    $error_log_data['code'] = $query_res->code;
                    $error_log_data['msg'] = $query_res->msg;
                    $error_log_data['sub_code'] = $query_res->sub_code;
                    $error_log_data['sub_msg'] = $query_res->sub_msg;
                    $error_log_data['ctime'] = time();
                    M("Alipay_error")->add($error_log_data);
                    M("Alipay_error")->commit();
                }
                $this->error("打款失败！请联系管理员！");
            }
        }
    }

    /**
     * 驳回提现申请
     * @time 2017-09-05
     * @author crazy
     * @param w_id 提现的id
     * @param body提现的文字
     */
    public function turnDown(){
        $data['status'] = 9;
        $res = M("Withdraw")->where(array('w_id'=>$_POST['w_id']))->limit(1)->save($data);
        if($res){
            $this->success("驳回成功！，并已通知用户！");
            /**找到用户或者商家的手机号*/
            $with_res = M("Withdraw")->where(array('w_id'=>$_POST['w_id']))->limit(1)->find();
            $body = $_POST['body'];
            if($with_res['type'] == 1){
                $tel = M("Shop")->where(array('shop_id'=>$with_res['mix_id']))->getField('account');
                $this->sendMsg($tel,$body);
            }else{
                $tel = M("Member")->where(array('m_id'=>$with_res['mix_id']))->getField('account');
                $this->sendMsg($tel,$body);
            }

        }else{
            $this->error("驳回失败！");
        }
    }


    /**打款成功的记录*/
    public function paySuccess(){
        $w = array();
        $pam = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        if(!empty($_REQUEST['withdraw_sn'])){
            $w['out_biz_no'] = $_REQUEST['withdraw_sn'];
            $pam['withdraw_sn'] = I('request.withdraw_sn');
            $request['withdraw_sn'] = $_REQUEST['withdraw_sn'];
            $this->assign("withdraw_sn",$_REQUEST['withdraw_sn']);
        }
        if(!empty($_REQUEST['w_id'])){
            $w['w_id'] = $_REQUEST['w_id'];
            $pam['w_id'] = I('request.w_id');
            $request['w_id'] = $_REQUEST['w_id'];
            $this->assign("w_id",$_REQUEST['w_id']);
        }
        $list = D("Pay_log")->selectSuccess($w,"ctime desc",15,$pam);
        //dump(D("Pay_log")->getLastSql());
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("paySuccess");
    }

    /**打款失败的记录*/
    public function errorPayList(){
        $w = array();
        $pam = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        if(!empty($_REQUEST['withdraw_sn'])){
            $w['withdraw_sn'] = $_REQUEST['withdraw_sn'];
            $pam['withdraw_sn'] = I('request.withdraw_sn');
            $request['withdraw_sn'] = $_REQUEST['withdraw_sn'];
            $this->assign("withdraw_sn",$_REQUEST['withdraw_sn']);
        }
        if(!empty($_REQUEST['w_id'])){
            $w['w_id'] = $_REQUEST['w_id'];
            $pam['w_id'] = I('request.w_id');
            $request['w_id'] = $_REQUEST['w_id'];
            $this->assign("w_id",$_REQUEST['w_id']);
        }
        $list = D("Alipay_error")->selectWithdraw($w,"ctime desc",15,$pam);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("errorPayList");
    }






    public function changeWechatStatus(){
        if(I("get.sign") != "zxty_cz"){
            $this->error('参数错误！');
        }
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        $w_id = I("get.w_id");
        $refund = M("Withdraw")->where(array("w_id"=>$w_id))->limit(1)->find();
        $refund['type'] == 0 && $refund['pay_type'] == 1 ?$openid = M("Member")->where(array('m_id'=>$refund['mix_id']))->limit(1)->getField('openid'):
            $openid = M("Shop")->where(array('shop_id'=>$refund['mix_id']))->limit(1)->getField('openid');
        /**随机字符串*/
        $rand_string = $this->getNonceStr();
        $real_name = $refund['name'];
        $price = $refund['price']*100;
        $order_sn = $refund['withdraw_sn'];
        if(empty($real_name) || empty($openid) || empty($refund['withdraw_sn'])){
            $this->error('参数错误！');
        }

        $ip = "59.110.16.129";
        //封装成数据（签名）
        $dataArr=array();
        $dataArr['amount']=$price;
        $dataArr['check_name']="FORCE_CHECK";
        $dataArr['desc']="众享通赢提现打款!";
        $dataArr['mch_appid']="wx323d496753eb8b10";
        $dataArr['mchid']="1483725242";
        $dataArr['nonce_str']= $rand_string;
        $dataArr['openid']=$openid;
        $dataArr['partner_trade_no']=$order_sn;
        $dataArr['re_user_name']=$real_name;
        $dataArr['spbill_create_ip']=$ip;

        $sign_obj = WechatSign::getInstance();
        $sign = $sign_obj->getSign($dataArr);

        $dataArr['sign']=$sign;
        /**调用微信付款*/
        $xml = $this->arrayToXml($dataArr);

        $info = $sign_obj->httpsRequest($url,$xml);
        $infos=$sign_obj->xmlToArray($info);
        M("Withdraw")->startTrans();
        M("Message")->startTrans();
        if($infos['return_code'] == "SUCCESS" && $infos['result_code'] == "SUCCESS"){
            /**先修改打款的状态*/
            $with_data['status'] = 1;
            $with_res = M("Withdraw")->where(array("w_id" => $_GET['w_id']))->limit(1)->save($with_data);
            if($with_res){
                /**给商家或者用户发消息推送买单的信息*/
                $mess_data_shop['title'] = "提现到账通知！";
                if($refund['type'] == 1){
                    $mess_data_shop['content'] = date('Y-m-d H:i:s',$refund['ctime'])."申请提现的".$refund['price'].'元已经到账！';
                }else{
                    $mess_data_shop['content'] = date('Y-m-d H:i:s',$refund['ctime'])."申请提现的".$refund['total_price'].'元已经到账！扣除手续费'.$refund['other_price'].'元';
                }
                $mess_data_shop['m_id'] = $refund['mix_id'];
                $mess_data_shop['ctime'] = time();
                $mess_data_shop['id_type'] =$refund['type'];
                $mess_data_shop['price'] = $refund['price'];
                $mess_res = M("Message")->add($mess_data_shop);
                if($mess_res){
                    M("Withdraw")->commit();
                    M("Message")->commit();
                    $this->success("打款成功！微信程序执行成功！");
                }else{
                    M("Withdraw")->rollback();
                    M("Message")->rollback();
                    $this->error("打款失败！微信程序执行成功！");
                }
            }
        }else{
            $this->error("打款失败！微信程序执行失败！");
        }
    }


    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    public function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        return $xml;
    }

    /**
     * 更新环迅订单状态
     * @author mss
     * @time 2017-12-05
     */
    public function updateOrder(){
        $id = I('w_id');
        $with = M('Withdraw')->where(array('w_id'=>$id))->find();
//        $usercode = $this->setcustomerCode($with['mix_id'],$with['type']);
        $usercode = M('HxUser')->where(array('m_id'=>$with['mix_id'],'type'=>$with['type']))->getField('customer_code');
        if(!$usercode){
            $res['msg'] = '用户未开户';
            $this->ajaxReturn($res);
        }
        $huanxun = Huanxun::getInstance();
        $result = $huanxun->queryResult($usercode,'','',$with['ips_billno']);
        if($result['flag']=='success'&&!empty($result['list']['orderDetails'])){
            $is_array = $result['list']['orderDetails']['orderDetail'][0];
            if(!is_array($is_array)){
                $res = $result['list']['orderDetails']['orderDetail'];
                $data['amount'] = $res['orderAmount'];
                $data['trade_state'] = $res['orderState'];
                $data['account'] = $res['bankCard'];
                M('Withdraw')->where(array('w_id'=>$id))->limit(1)->save($data);
                if($res){
                    $res['flag'] = 1;
                }else{
                    $res['flag'] = 0;
                    $res['msg'] = '查询失败';
                }
            }
        }else{
            $res['flag'] = 0;
            if(empty($result['rspMsg'])){
                $res['msg'] = $result['rspMsg'];
            }
            $res['msg'] = '查询失败';
        }
        $this->ajaxReturn($res);
    }









}