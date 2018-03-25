<?php
namespace Api\Controller;
use Think\Controller;
use Think\Huanxun;
use Think\IPSUtils;

/**
 * Class HxUserController
 * @package Api\Controller
 * 环迅
 */

class HxUserController extends ApiBasicController{
    private $_key = 'tFcybKzbyVckNHph159ap9br';
    private $_iv = 'XuDcKdZa';
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 用户开户
     * 传参方式 post
     * @author mss
     * @time 2017-09-28
     * param mix_id 用户或者商家id
     * param type 用户类型，1商家，0用户
     * param userName 用户姓名或企业名称
     * param mobiePhone 手机号
     * param identityType 证件类型，1身份证，2营业执照
     * param identityNo 证件号码
     */
    public function openUser(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $mix_id = $_POST['mix_id'];
        if(empty($_POST['userName'])){
            apiResponse('error','用户名称不能为空');
        }
        if(empty($_POST['mobiePhone'])){
            apiResponse('error','手机号不能为空');
        }elseif(!preg_match(C('MOBILE'),$_POST['mobiePhone'])){
            apiResponse('error','手机号格式不正确');
        }
        /**判断这个用户是否满18周岁*/
        $is_eight = $this->getAgeByID($_POST['identityNo']);
        if($is_eight < 18){
            apiResponse('error','请绑定大于18周岁的身份证');
        }
        $type = !empty($_POST['type'])?$_POST['type']:0;
        $customerCode = $this->setcustomerCode($mix_id,$type);
        if($type==1){
            if($_POST['inself']){
                $asyncurl =U('Merchant/Shop/shopcenter');
            }else{
                $asyncurl =U('Merchant/Shop/paydraw');
            }
            /**更新商家已经提交信息*/
            M('Shop')->where(array('shop_id'=>$mix_id))->limit(1)->setField('opening',1);
        }else{
            if($_POST['inself']){
                $asyncurl ="/index.php?s=/Member/membercenter";
            }else{
                $asyncurl ="/index.php?s=/Member/paydraw";
            }
            /**更新用户已经提交信息*/
            M('Member')->where(array('m_id'=>$mix_id))->limit(1)->setField('opening',1);
            //判断用户是否绑定手机号
            $tel = M('Member')->where(array('m_id'=>$mix_id))->limit(1)->getField('account');
            /**判断这个手机号是否已经被绑定*/
            $is_set_member = M("Member")->where(['status'=>0,'account'=>$_POST['mobiePhone']])->getField('account');
            if(!$tel&&empty($is_set_member)){
                M('Member')->where(array('m_id'=>$mix_id))->limit(1)->setField('account',$_POST['mobiePhone']);
            }
        }
        $identityType = empty($_POST['identityType'])?$_POST['identityType']:1;
        $s2surl = "https://".$_SERVER['HTTP_HOST']."/Api/HxUser/openS2sUrl/type/$type/mix_id/$mix_id";
        $pageurl = "https://".$_SERVER['HTTP_HOST'].$asyncurl;  //同步返回地址

        $identityNo = $_POST['identityNo']?$_POST['identityNo']:'';
        $legalName = $_POST['legalName']?$_POST['legalName']:'';
        $legalCardNo = $_POST['legalCardNo']?$_POST['legalCardNo']:'';
        $mobiePhoneNo = $_POST['mobiePhone'];
        $telPhoneNo = $_POST['telphone']?$_POST['telphone']:'';
        $email = $_POST['email']?$_POST['email']:'';
        $contactAddress = $_POST['address']?$_POST['address']:'';
        $remark = $_POST['remark']?$_POST['remark']:'';
        $directSell  = $_POST['directSell']?$_POST['directSell']:'';
        $stmsAcctNo  = $_POST['stmsAcctNo']?$_POST['stmsAcctNo']:'';
        $ipsusername = $_POST['ipsUserName']?$_POST['ipsUserName']:'';
        $huanxun = Huanxun::getInstance();
        $xml = $huanxun->openUser(2,$customerCode,$identityType,$identityNo,$_POST['userName'],$legalName,$legalCardNo,$mobiePhoneNo,$telPhoneNo,$email,$contactAddress,$remark,$pageurl,$s2surl,$directSell,$stmsAcctNo,$ipsusername);
        $data['ipsRequest'] = $xml;
        $data['url'] = $pageurl;

        apiResponse('success','成功',$data);
    }


    /**
     * 查询开户结果
     * 传参方式 post
     * @author mss
     * @time 2017-09-28
     * param mix_id 用户id
     * param type 用户类型，1商家，0用户
     */
    public function queryUser(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $m_id = $_POST['mix_id'];
        $type = !empty($_POST['type'])?$_POST['type']:0;
        $field = 'customer_code,identity_type,identity,username,mobiephone,email,address,remark';
        $res = M('HxUser')->where(array('m_id'=>$m_id,'type'=>$type))->field($field)->find();
        if(!$res||$res['status']==9){
            apiResponse('error','用户不存在');
        }
        apiResponse('success','获取成功',empty($res)?"":$res);
    }

    /**
     * 转账提现
     * 传参方式 post
     * @author mss
     * @time 2017-09-28
     * param mix_id 用户id
     * param type 用户类型 1商家，0用户
     * param money 转账金额
     * param is_readonly 商家是否只读
     */
    public function transfer(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_POST['money'])||$_POST['money']<=0){
            apiResponse("error",'提现金额需大于0元');
        }
        $mix_id = $_POST['mix_id'];
        $type = !empty($_POST['type'])?$_POST['type']:0;
        M('Withdraw')->startTrans();
        M('Bill')->startTrans();
        M('Message')->startTrans();
        /**找到用户提现需要的手续费*/
        $scale_mem = M("Config")->getField("scale_mem");
        $data['mix_id'] = $mix_id;
        $usercode = M('HxUser')->where(array('m_id'=>$mix_id,'type'=>$type))->getField('customer_code');
//        $usercode = $this->setcustomerCode($mix_id,$type);
        if($type==1){
            /**判读商家是否只读*/
            $is_read = $_POST['is_readonly'];
            if($is_read==1){
                apiResponse("error","无操作权限！");
            }
            /**查询商家钱包余额*/
            $wallet = M("Shop")->where(array('shop_id'=>$mix_id))->limit(1)->getField("wallet");
            if($wallet<$_POST['money']){
                apiResponse("error","提现金额不足！");
            }
            $asyncurl = U('Merchant/Shop/billlist'); //同步回调地址
            $data['total_price'] = $_POST['money'];
            $data['price'] = $_POST['money'];
            $item_name = C("TRANSFER_VALUE");        //付款项目名称
        }else{
            /**查看用户满足多少元可以提现*/
            $full_price = M("Config")->getField("full_price");
            /**查看用户钱包余额*/
            $wallet = M("Member")->where(array('m_id'=>$mix_id))->limit(1)->getField("wallet");
            if($wallet<$full_price){
                apiResponse("error","满足".$full_price."元才能提现！");
            }
            if($wallet<$_POST['money']){
                apiResponse("error","提现金额不足！");
            }
            $asyncurl = '/index.php?s=Bill/billlist'; //同步回调地址
            $data['total_price'] = $_POST['money'];
            $data['price'] = sprintf('%.2f',$_POST['money']*(1-($scale_mem/100)));
            $data['other_price'] = sprintf('%.2f',$_POST['money']*($scale_mem/100));
            $item_name = C("TRANSFER_VALUE");    //付款项目名称
        }
        //查询用户的开户信息
        $mem_name = M('HxUser')->where(array('customer_code'=>$usercode))->limit(1)->getField('username');
        $data['name'] = $mem_name?$mem_name:'';
        $data['withdraw_sn'] = date('YmdHis').mt_rand(100000,999999);
        $requesturl = 'https://ebp.ips.com.cn/fpms-access/action/trade/transfer.do';    //转账接口地址
        $huanxun = Huanxun::getInstance();
        $xml = $huanxun->transfer($data['withdraw_sn'],$usercode,$data['price'],$item_name);
        $data_post['ipsRequest'] = $xml;
        /**调用转账接口*/
        $res_trans = $this->request_post($requesturl,$data_post);
        $postObj = simplexml_load_string($res_trans, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($postObj->rspCode=='M000000'){
            $des3 = new \Think\IPSUtils($this->_key,$this->_iv);
            $result = $this->xmlToArray( $des3->decrypt($postObj->p3DesXmlPara));
            //转账成功
            $data['pay_type'] = 3;
            $data['type'] = $type;
            $data['ctime'] = time();
            $flag = M('withdraw')->add($data);
            //信息保存成功
            if($type==1){
                /**商家减少钱数*/
                $shop_data['wallet'] = floatval($wallet)-floatval($_POST['money']);
                M("Shop")->where(array('shop_id'=>$mix_id))->limit(1)->save($shop_data);
                $name = M("Shop")->where(array('shop_id'=>$mix_id))->getField('name');
                /**给商家添加账单记录*/
                $bill_res = M('Bill')->add(array(
                    'm_id' => $mix_id,
                    'title' => '提现',
                    'content' => date('Y-m-d-H:i:s',time())."提现".$_POST['money'].'元',
                    'price' => $_POST['money'],
                    'monitor' => 1,
                    'pay_type' => 3,
                    'name' => $name?$name:"",
                    'type' => 5,
                    'id_type' => 1,
                    'accept_m_id' => $mix_id,
                    'rank_type' => 1,
                    'ctime' =>time(),
                    'ips_billno' => $result['body']['ipsBillNo'],
                    'trade_id' =>$result['body']['tradeId'],
                    'trade_state' => $result['body']['tradeState']
                ));
                /**添加消息记录*/
                $msg_res = $this->addMessage($mix_id,'提现',date('Y-m-d-H:i:s',time())."提现".$_POST['money'].'元',1,$_POST['money']);
            }else{
                $nick_name = M("Member")->where(array('m_id'=>$mix_id))->getField("nick_name");
                /**用户减少钱数*/
                $member_data['wallet'] = floatval($wallet)-floatval($_POST['money']);
                M("Member")->where(array('m_id'=>$mix_id))->limit(1)->save($member_data);
                /**给用户添加账单*/
                $bill_res = M('Bill')->add(array(
                    'm_id' => $mix_id,
                    'title' => '提现',
                    'content' => date('Y-m-d-H:i:s',time())."提现".$_POST['money'].'元',
                    'price' => $_POST['money'],
                    'monitor' => 1,
                    'pay_type' => 3,
                    'name' => $nick_name?$nick_name:"",
                    'type' => 5,
                    'id_type' => 0,
                    'accept_m_id' => $mix_id,
                    'rank_type' => 0,
                    'ctime' =>time(),
                    'total_price' => $_POST['money'],
                    'ips_billno' => $result['body']['ipsBillNo'],
                    'trade_id' =>$result['body']['tradeId'],
                    'trade_state' => $result['body']['tradeState']
                ));
                /**添加消息记录*/
                $msg_res = $this->addMessage($mix_id,'提现',date('Y-m-d-H:i:s',time())."提现".$_POST['money'].'元',0,$_POST['money']);
            }
            if($flag&&$bill_res&&$msg_res){
                M('Withdraw')->commit();
                M('Bill')->commit();
                M('Message')->commit();
            }else{
                M('Withdraw')->rollback();
                M('Bill')->rollback();
                M('Message')->rollback();
            }

            //调用提现接口
            $with_id = $des3->encrypt($flag);
            $pageUrl = 'https://'.$_SERVER['HTTP_HOST'].$asyncurl;  //同步地址
            $s2sUrl = "https://".$_SERVER['HTTP_HOST']."/Api/HxUser/withdrawS2sUrl/with_id/".$with_id;    //异步地址
            $xml_withdraw = $huanxun->withdraw($data['withdraw_sn'],$usercode,$pageUrl,$s2sUrl);
            apiResponse('success','提现成功',array('ipsRequest'=>$xml_withdraw));

        }else{
            $postObj = $this->xmlToArray($res_trans);
            apiResponse('error',$postObj['rspMsg']);
        }
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

    /**
     * 直接提现,返回提现的xml，直接跳转去ips提现页面
     * 传参方式 post
     * @author mss
     * @time 2017-10-24
     * param mix_id 用户id
     * param type 用户类型 1商家，0用户
     * param w_id 提现的id
     * param is_readonly 商家是否只读
     */
    public function withdraw(){
        if($_POST['is_readonly']==1){
            apiResponse("error","无操作权限！");
        }
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
//        if(empty($_POST['w_id'])){
//            apiResponse('error','参数错误');
//        }
        $mix_id = $_POST['mix_id'];
//        $w_id = $_POST['w_id'];
        $type = !empty($_POST['type'])?$_POST['type']:0;
        $usercode = M('HxUser')->where(array('m_id'=>$mix_id,'type'=>$type))->getField('customer_code');
//        $usercode = $this->setcustomerCode($mix_id,$type);
        if($type==1){
            $asyncurl = U('Merchant/Shop/billlist');;
        }else{
            $asyncurl = '/index.php?s=Bill/billlist';
        }
        //查询提现订单
//        $withdraw = M('Withdraw')->where(array('w_id'=>$w_id))->find();
        $huanxun = Huanxun::getInstance();
        //调用提现接口
//        $des3 = new \Think\IPSUtils($this->_key,$this->_iv);
//        $with_id = $des3->encrypt($w_id);
        $pageUrl = 'https://'.$_SERVER['HTTP_HOST'].$asyncurl;  //同步地址
        $s2sUrl = "https://".$_SERVER['HTTP_HOST']."/Api/HxUser/withdrawS2sUrl";    //异步地址
        $xml_withdraw = $huanxun->withdraw('',$usercode,$pageUrl,$s2sUrl);
        $data['ipsRequest'] = $xml_withdraw;
        apiResponse('success','成功',$data);
    }

    /**
     * 用户提现到银行卡的ips订单记录
     * 传参方式 post
     * @author mss
     * @time 2017-12-06
     * @param mix_id 用户id
     * @param type 用户类型，0用户，1商家
     * @param p 分页页数
     */
    public function withdrawList(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $mix_id = $_POST['mix_id'];
        $type = $_POST['type']?$_POST['type']:0;
        $p = $_POST['p']?$_POST['p']:1;
//        $code = $this->setcustomerCode($mix_id,$type);
        $code = M('HxUser')->where(array('m_id'=>$mix_id,'type'=>$type))->getField('customer_code');

        $ordertype = 4;
        $huanxun = Huanxun::getInstance();
        $result = $huanxun->queryResult($code,$ordertype,'','','','',$p,15);
        $list = array();
        if($result['flag']=='success'&&!empty($result['list']['orderDetails'])){
            $is_array = $result['list']['orderDetails']['orderDetail'][0];
            if(!is_array($is_array)){
                $list[] = $result['list']['orderDetails']['orderDetail'];
            }else{
                $list = $result['list']['orderDetails']['orderDetail'];
            }
        }elseif ($result['flag']=='success'&&empty($result['list']['orderDetails'])){
            apiResponse('success','暂无数据');
        }elseif($result['flag']=='success'){
            apiResponse('error',$result['rspMsg']);
        }
        if(!$list){
            apiResponse('success','暂无数据');
        }
        $data = array();
        foreach($list as $k=>$v){
            $data[$k]['ctime'] = $v['createTime'];
            $data[$k]['amount'] = $v['orderAmount'];
            $data[$k]['orderType'] = '提现';
            if($v['orderState']==4){
                $data[$k]['state'] = '已退款';
            }else if($v['orderState']==8){
                $data[$k]['state'] = '处理中';
            }else if($v['orderState']==9){
                $data[$k]['state'] = '提现失败';
            }else if($v['orderState']==10){
                $data[$k]['state'] = '提现成功';
            }
            $data[$k]['bankCard'] = $v['bankCard'];
        }
        apiResponse('success','获取成功',$data);
    }

    /**
     * 查询更新提现订单信息
     * 传参方式 post
     * @author mss
     * @time 2017-10-24
     * @param w_id 提现id
     */
    public function updateWithdraw(){
        if(empty($_POST['w_id'])){
            apiResponse('error','参数错误');
        }
        $w_id = $_POST['w_id'];
        $withdraw = M('Withdraw')->where(array('w_id'=>$w_id))->find();
//        $usercode = $this->setcustomerCode($withdraw['mix_id'],$withdraw['type']);
        $usercode = M('HxUser')->where(array('m_id'=>$withdraw['mix_type'],'type'=>$withdraw['type']))->getField('customer_code');
        $huanxun = Huanxun::getInstance();
        $result = $huanxun->queryResult($usercode,'','',$withdraw['ips_billno']);
        if($result['flag']=='success'&&!empty($result['list']['orderDetails'])){
            $is_array = $result['list']['orderDetails']['orderDetail'][0];
            if(!is_array($is_array)){
                $res = $result['list']['orderDetails']['orderDetail'];
                $data['amount'] = $res['orderAmount'];
                $data['trade_state'] = $res['orderState'];
                $data['account'] = $res['bankCard'];
                M('Withdraw')->where(array('w_id'=>$w_id))->limit(1)->save($data);
                if($res){
                    apiResponse('success','查询成功',$res);
                }else{
                    apiResponse('error','查询失败');
                }
            }
        }else{
            apiResponse('error',$result['rspMsg']);
        }
    }


    /**
     * 修改开户信息，返回xml
     * 传参方式 post
     * @author mss
     * @time 2017-09-29
     * param mix_id 用户id
     * param type 用户类型，0用户，1商家
     */
    public function updateUser(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $m_id = $_POST['mix_id'];
        $type = !empty($_POST['type'])?$_POST['type']:0;
        $usercode = M('HxUser')->where(array('m_id'=>$m_id,'type'=>$type))->getField('customer_code');
//        $customercode = $this->setcustomerCode($m_id,$type);
        if($type==1){
            $asyncurl = U('Merchant/Shop/shopcenter');
        }else{
            $asyncurl = '/index.php?s=/Member/membercenter';
        }
//        $pageurl ="https://".$_SERVER['HTTP_HOST']."/Api/HxUser/updatePageUrl";
        $pageurl = "https://".$_SERVER['HTTP_HOST'].$asyncurl;
        $s2surl = "https://".$_SERVER['HTTP_HOST']."/Api/HxUser/updateS2sUrl";
        $huanxun = Huanxun::getInstance();
        $xml = $huanxun->updateUser($usercode,$pageurl,$s2surl);
        $data['ipsRequest'] = $xml;
        apiResponse('success','获取成功',$data);
    }



    /**
     * 更新用户开户信息
     * 传参方式 post
     * @author mss
     * @time 2017-11-26
     * @param mix_id 商家或用户id
     * @param type 用户类型，1商家，0用户
     * @param name 用户姓名
     * @param identity 身份证号
     * @param phone 手机号
     */
    public function updateUserInfo(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $mix_id = $_POST['mix_id'];
        $type = $_POST['type']?$_POST['type']:0;
//        $cuscode = $this->setcustomerCode($mix_id,$type);
        $cuscode = M('HxUser')->where(array('m_id'=>$mix_id,'type'=>$type))->getField('customer_code');

        $name = $_POST['name']?$_POST['name']:'';
        $identity = $_POST['identity']?$_POST['identity']:'';
        $phone = $_POST['phone']?$_POST['phone']:'';
        if(!empty($phone)&&!preg_match(C('MOBILE'),$phone)){
            apiResponse('error','手机号格式不正确');
        }
        $huanxun = Huanxun::getInstance();
        $res = $huanxun->updateUserInfo($cuscode,$name,$identity,$phone);
        if($res['flag']=='success'){
            if($name!=''){
                $data['username'] = $name;
            }
            if($identity!=''){
                $data['identity'] = $identity;
            }
            if($phone!=''){
                $data['mobiephone'] = $phone;
            }
            M('HxUser')->where(array('customer_code'=>$cuscode,'status'=>0))->limit(1)->save($data);
            apiResponse('success','修改成功');
        }else{
            apiResponse('error','修改失败',$res['rspMsg']);
        }
    }


    /**
     * 开户异步返回地址
     */
    public function openS2sUrl(){
         $postStr = file_get_contents("php://input");//返回回复数据
         $postStr = str_replace("ipsResponse=", "<?xml version='1.0' encoding='UTF-8'?>", rawurldecode($postStr));
         $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);

        if($postObj->rspCode=='M000000'){
            $des3 = new IPSUtils($this->_key,$this->_iv);
            $res = simplexml_load_string( $des3->decrypt($postObj->p3DesXmlPara),'SimpleXMLElement', LIBXML_NOCDATA );
            $res = json_decode(json_encode($res),true);

            if($res['body']['status']=='10'){
                $type = !empty($_GET['type'])?$_GET['type']:0;
                $mix_id = !empty($_GET['mix_id'])?$_GET['mix_id']:0;
                $data['type'] = $type;
                if($type==1){
                    /**更新商家已经提交信息*/
                    M('Shop')->where(array('shop_id'=>$mix_id))->limit(1)->setField('opening',0);
                }else{
                    /**更新用户已经提交信息*/
                    M('Member')->where(array('m_id'=>$mix_id))->limit(1)->setField('opening',0);
                }
                /**查询用户是否已经开户*/
                $user = M('HxUser')->where(array('m_id'=>$mix_id,'type'=>$type))->field('customer_code,identity_type,identity,username,mobiephone,ips_username')->find();
                if($user){
                    echo 'ipsCheckOk';exit;
                }
                /**添加用户注册信息*/
                $data['m_id'] = $mix_id;
                $data['customer_code'] = $res['body']['customerCode'];
                $data['identity_type'] = $res['body']['identityType'];
                $data['identity'] = $res['body']['identityNo'];
                $data['username'] = $res['body']['userName'];
                $data['mobiephone'] = $res['body']['mobiePhoneNo'];
                $data['telphone'] = $res['body']['telPhoneNo']?$res['body']['telPhoneNo']:'';
                $data['legal_name'] = $res['body']['legalName']?$res['body']['legalName']:'';
                $data['legal_card'] = $res['body']['legalCardNo']?$res['body']['legalCardNo']:'';
                $data['email'] = $res['body']['email']?$res['body']['email']:'';
                $data['address'] = $res['body']['contactAddress']?$res['body']['contactAddress']:'';
                $data['remark'] = $res['body']['remark']?$res['body']['remark']:'';
                $data['directsell'] = $res['body']['directSell']?$res['body']['directSell']:'';
                $data['stms_acct'] = $res['body']['stmsAcctNo ']?$res['body']['stmsAcctNo ']:'';
                $data['ips_username'] = $res['body']['ipsUserName']?$res['body']['ipsUserName']:'';
                $data['ctime'] = time();
                $flag = M('HxUser')->add($data);

                if($flag){
                    echo 'ipsCheckOk';exit;
                }else{
                    //file_put_contents('/OpenUserError/'.$res['body']['customerCode'].'.txt',json_encode($res['body']));
                    $this->sendMsg('18522713541','用户开户信息保存失败,名称：'.$res['body']['customerCode']);
                }
            }else{
                apiResponse('error','开户失败');
            }
        }else{
            $this->sendMsg('18522713541','开户回调没有注册成功！');
            apiResponse('error',$postObj->rspMsg);
        }
    }

    /**
     * 修改开户信息同步返回地址
     */
    public function updatePageUrl(){
        $postStr = file_get_contents("php://input");//返回回复数据
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($postObj->rspCode=='M000000'){
            $des3 = new IPSUtils($this->_key,$this->_iv);
            $res = $this->xmlToArray($des3->decrypt($postObj->p3DesXmlPara));
            if($res['body']['status']=='10'){
                $customerCode = $res['body']['customerCode'];
                $huanxun = Huanxun::getInstance();
                $info = $huanxun->queryUser($customerCode);
                $data['username'] = $info['body']['userName'];
                $data['identity_type'] = (int)$info['body']['identityType'];
                $data['mobiephone'] = $info['body']['mobiePhoneNo']?$info['body']['mobiePhoneNo']:'';
                $data['telphone'] = $info['body']['telPhoneNo']?$info['body']['telPhoneNo']:'';
                $data['email'] = $info['body']['email']?$info['body']['email']:'';
                $data['address'] = $info['body']['contactAddress']?$info['body']['contactAddress']:'';
                $data['ips_username'] = $info['body']['ipsUserName']?$info['body']['ipsUserName']:'';
                $data['utime'] = time();
                M('HxUser')->where(array('customer_code'=>$customerCode))->limit(1)->save($data);
                apiResponse('success','更新成功');
            }else{
                apiResponse('error',$res['body']['reason']);
            }
        }else{
            apiResponse('error',$postObj->rspMsg);
        }
    }
    /**
     * 修改开户信息异步返回地址
     */
    public function updateS2sUrl(){
        $postStr = file_get_contents("php://input");//返回回复数据
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($postObj->rspCode=='M000000'){
            $des3 = new IPSUtils($this->_key,$this->_iv);
            $res = $this->xmlToArray($des3->decrypt($postObj->p3DesXmlPara));
            if($res['body']['status']=='10'){
                $customerCode = $res['body']['customerCode'];
                $huanxun = Huanxun::getInstance();
                $info = $huanxun->queryUser($customerCode);
                $data['username'] = $info['body']['userName'];
                $data['identity_type'] = (int)$info['body']['identityType'];
                $data['mobiephone'] = $info['body']['mobiePhoneNo']?$info['body']['mobiePhoneNo']:'';
                $data['telphone'] = $info['body']['telPhoneNo']?$info['body']['telPhoneNo']:'';
                $data['email'] = $info['body']['email']?$info['body']['email']:'';
                $data['address'] = $info['body']['contactAddress']?$info['body']['contactAddress']:'';
                $data['ips_username'] = $info['body']['ipsUserName']?$info['body']['ipsUserName']:'';
                $data['utime'] = time();
                M('HxUser')->where(array('customer_code'=>$customerCode))->limit(1)->save($data);
                //apiResponse('success','更新成功');
            }else{
                echo $res['body']['reason']."<br/>";
//                apiResponse('error',$res['body']['reason']);
            }
        }else{
            echo $postObj->rspMsg."<br/>";
//            apiResponse('error',$postObj->rspMsg);
        }

        echo 'ipsCheckOk';exit;
    }

    /**
     * 提现异步地址
     */
    public function withdrawS2sUrl(){
        $postStr = file_get_contents("php://input");//返回回复数据
        $postStr = str_replace("ipsResponse=", "", rawurldecode($postStr));
        $postObj = $this->xmlToArray($postStr);
        if($postObj['rspCode'] == 'M000000') {
            $des3 = new IPSUtils($this->_key, $this->_iv);
            $res_arr = $this->xmlToArray($des3->decrypt($postObj['p3DesXmlPara']));
            $ipsutil = new IPSUtils($this->_key,$this->_iv);
            $with_id = $_GET['with_id'];
            if($with_id){
                $w_id = $ipsutil->decrypt($with_id);
                $withdraw = M('Withdraw')->where(array('w_id'=>$w_id))->getField('w_id');
                $data['ips_billno'] = $res_arr['body']['ipsBillNo'];
                $data['amount'] = $res_arr['body']['amount'];
                $data['trade_state'] = $res_arr['body']['tradeState'];
                $data['fail_msg'] = $res_arr['body']['failMsg']?$res_arr['body']['failMsg']:"";
                $data['utime'] = time();
                M('Withdraw')->where(array('w_id'=>$withdraw))->limit(1)->save($data);
            }

        }else{
            echo $postObj->rspMsg."<br/>";
        }

        echo 'ipsCheckOk';exit;
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

    public function test(){
        $id = $_REQUEST['mix_id'];
        $type = $_REQUEST['type']?$_REQUEST['type']:0;
        if($type==1){
            $code = 'shop'.$id;
        }else{
            $code = 'member'.$id;
        }
        $huanxun = Huanxun::getInstance();
        $res = $huanxun->queryUser($code);
        dump($res);
    }

    /**
     * 生成客户号
     * @author mss
     * @time 2017-12-08
     * param mix_id 用户id
     * param type  用户类型，0用户，1商家
     */
    private function setcustomerCode($mix_id,$type){
        if($type==1){
            $code = 'zxtymerchant'.$mix_id;
        }else{
            $code = 'zxtyuser'.$mix_id;
        }
        return $code;
    }

}