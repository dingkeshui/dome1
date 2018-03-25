<?php

namespace Home\Model;
use Think\Model;

/**
 * Class MemberOperateModel
 * @package Home\Model
 * 会员操作  找回密码  激活账号
 * 发送邮件 发送短信
 */
class MemberOperateModel extends Model{

    protected $tableName = 'member_operate';

    /**
     * 找回密码 发送邮件
     * @param $email  邮箱
     * @return array
     */
    public function retrieve($email){
        $member_obj = D('Member');
        //验证邮箱是否存在
        $member = $member_obj->findMember(array('m_email'=>$email));
        if($member){
            //查询该邮箱 是否存在找回标记
            $retrieve = $this->where(array('way'=>$email,'type'=>'retrieve'))->find();
            $vc = getVc('char',40);//获取标识
            $expire_time = time()+600;//过期时间
            if($retrieve){
                /**每天只能进行三次操作**/
                if($retrieve['ctime'] > strtotime(date('Y-m-d')) && $retrieve['ctime'] < strtotime(date('Y-m-d 23:59:59')) && intval($retrieve['times'])%3 == 0){
                    return array('error'=>'每天只能进行三次找回密码操作');exit;
                }else{
                    /**后一天操作  次数置一  否则次数加一**/
                    if($retrieve['ctime'] < strtotime(date('Y-m-d'))){
                        $times = 1;
                    }else{
                        $times = $retrieve['times']+1;
                    }
                    //修改记录
                    $res = $this->where(array('id'=>$retrieve['id']))->data(array('vc'=>$vc,'expire_time'=>$expire_time,'times'=>$times,'ctime'=>time()))->save();
                }
            }else{
                //添加记录
                $res = $this->data(array('way'=>$email,'vc'=>$vc,'times'=>1,'expire_time'=>$expire_time,'type'=>'retrieve','ctime'=>time()))->add();
            }
            if($res){
                //发送邮件
                $url = C('API_URL');
                $user = $email;
                //链接地址
                $link = "<a href='$url/index.php/RegisterLog/resetPass?vc=$vc&user=$user'>$url/index.php/RegisterLog/resetPass?vc=$vc&user=$user</a>";
                $body = "尊敬的".$user."，您好：<br>";
                $body .= "您的重设密码地址为  ".$link."<br>";
                $body .= "如果上面的链接无法点击，您也可以复制链接，粘贴到您浏览器的地址栏内，然后按“回车”打开重置密码页面。<br>";
                $body .= '如果您没有进行过找回密码的操作，请不要点击上述链接，并删除此邮件。<br>';
                $body .= '该链接的有效期为10分钟，超时请重新申请连接。<br>';
                $email = D('Email','Service');
                $result = $email->sendEmail($user,$user,'找回密码（重要）',$body);
                if($result == true){
                    return array('success'=>'重置密码的连接已发送至您的邮箱');exit;
                }else{
                    return array('error'=>'邮件发送失败');exit;
                }
            }else{
                return array('error'=>'操作失败');exit;
            }
        }else{
            return array('error'=>'您输入的邮箱不存在');exit;
        }
    }

    /**
     * 注册成功后 发送激活邮件
     */
    public function activate($email){
        $activate = $this->where(array('way'=>$email,'type'=>'activate'))->find();
        //获取标识
        $vc = getVc('char',40);
        if($activate){
            //修改记录
            $res = $this->where(array('id'=>$activate['id']))->data(array('vc'=>$vc))->save();
        }else{
            //添加记录
            $res = $this->data(array('way'=>$email,'vc'=>$vc,'type'=>'activate','ctime'=>time()))->add();
        }
        //添加成功后发送邮件
        if($res){
            $url = C('API_URL');
            $user = $email;
            $link = "<a href='$url/index.php/RegisterLog/activate?vc=$vc&user=$user'>$url/index.php/RegisterLog/activate?vc=$vc&user=$user</a>";
            $body = "尊敬的".$user."，您好：<br>";
            $body .= "您的‘晟轩网络’账号激活链接为  ".$link."<br>";
            $body .= "如果上面的链接无法点击，您也可以复制链接，粘贴到您浏览器的地址栏内，然后按“回车”打开重置密码页面。<br>";
            $body .= '如果您没有在‘晟轩网络’注册过账号，请不要点击上述链接，并删除此邮件。<br>';
            $email = D('Email','Service');
            $result = $email->sendEmail($user,$user,'【晟轩网络】激活链接',$body);
            if($result == true){
                return array('success'=>'注册成功！激活链接已发送至您的邮箱，请尽快激活');exit;
            }else{
                return array('error'=>'邮件发送失败');exit;
            }
        }else{
            return array('error'=>'操作失败');exit;
        }
    }


    /**
     *发送短信验证码
     *注册时  找回密码时
     * @param $mobile 电话
     * @param $type 操作类型   activate注册   retrieve找回密码
     * @return array
     */
    public function sendVerify($mobile,$type) {
        //验证号码合法性
        if(!preg_match(C('MOBILE'),$mobile)) {
            return array('error'=>'手机号格式不正确！！');exit;
        }
        //操作类型  type activate注册   retrieve找回
        if($type == 'retrieve') {
            $code = 'retrieve';
            //验证手机号是否存在
            $member = M('Member')->where(array('m_account'=>$mobile))->find();
        } elseif($type == 'activate') {
            $code = 'activate';
            $member = true;
        }
        if($member) {
            //是否进行过此操作
            $operate = $this->where(array('way'=>$mobile,'type'=>$type))->find();
            $vc = getVc('num',6);//获取标识
            $expire_time = time()+600;//过期时间
            if($operate) {
                /**每天只能进行三次操作**/
                if($operate['ctime'] > strtotime(date('Y-m-d')) && $operate['ctime'] < strtotime(date('Y-m-d 23:59:59')) && intval($operate['times'])%3 == 0){
                    if($type == 'retrieve') {
                        return array('error'=>'每天只能进行三次找回密码操作！！');exit;
                    } elseif($type == 'activate') {
                        return array('error'=>'获取验证码次数超限，明天请重试！！');exit;
                    }
                } else {
                    /**后一天操作  次数置一 否则次数加一**/
                    if($operate['ctime'] < strtotime(date('Y-m-d'))) {
                        $times = 1;
                    } else {
                        $times = intval($operate['times']) + 1;
                    }
                    //修改记录
                    $res = $this->where(array('id'=>$operate['id']))->data(array('vc'=>$vc,'expire_time'=>$expire_time,'times'=>$times,'ctime'=>time()))->save();
                }
            } else {
                //添加记录
                $res = $this->data(array('way'=>$mobile,'vc'=>$vc,'times'=>1,'expire_time'=>$expire_time,'type'=>$type,'ctime'=>time()))->add();
            }
            if($res){
                //创建发信参数
                $pram = array(
                    'vc' => $vc,
                );
                //创建记录参数
                $data = array(
                    'm_id'      => empty($member['m_id']) ? 0 : $member['m_id'],
                    'mobile'    => $mobile,
                );
                //发信
                if($this->_sendMsg($data,$code,$pram)) {
                    return array('success'=>'信息已送达！');exit;
                } else {
                    return array('error'=>'发信失败！');exit;
                }
            } else {
                return array('error'=>'操作失败！！');exit;
            }
        } else {
            return array('error'=>'您输入的手机号码不存在');exit;
        }
    }

    /**
     * @param $data
     * @param $code
     * @param $pram
     * @return bool
     * 发送短信
     */
    private function _sendMsg($data, $code, $pram) {
        //获取发信模板
        $tpl = M('SendTemplates')->where(array('code'=>$code))->find();
        //赋值
        foreach($pram as $k => $p) {
            $tpl['content'] = preg_replace("/{".$k."}/i",$p,$tpl['content']);
        }
        //发送短息
        $r = D('SendMsg','Service')->sendMsg($data['mobile'],$tpl['content']);
        //创建发信记录参数
        $data_1 = array(
            'm_id'          => $data['m_id'],
            'way'           => $data['mobile'],
            'send_type'     => 2,
            'content'       => $tpl['content'],
            'template_id'   => $tpl['template_id'],
            'ctime'         => time()
        );
        if(empty($r['error'])) {
            //添加发信记录
            $data_1['status'] = 1;
            M('SendLog')->data($data_1)->add();
            return true;
        } else {
            M('SendLog')->data($data_1)->add();
            return false;
        }
    }
}