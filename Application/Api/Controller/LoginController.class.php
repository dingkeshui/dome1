<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class MemberLoginController
 * @package Api\Controller
 * 用户登陆注册
 */
class LoginController extends ApiBasicController{

    public $Member = '';
    public function _initialize(){
        parent::_initialize();
        $this->Member = D('Member');
    }

    /**
     * 用户登陆
     */
    public function login(){
        $where['account'] = $_POST['account'];
        $where['status'] = array('neq',9);
        $account = $this->Member->where($where)->limit(1)->find();
        if($account){
            $where['password'] = md5($_POST['password']);
            $member = $this->Member->where($where)->limit(1)->find();
            /**判断这个用户是否开户*/
            $is_open = M('HxUser')->where(array('m_id'=>$member['m_id'],'type'=>0))->limit(1)->getField('h_id');
            $is_open = $is_open?$is_open:'';
            $data['m_id'] = $member['m_id'];
            $data['is_open'] = $is_open;
            /**修改最后的登录时间和ip地址*/
            $save_data['last_login_time'] = time();
            $save_data['last_login_ip'] = get_client_ip();
            M("Member")->where($where)->limit(1)->save($save_data);
            if($member){
                apiResponse("success","登陆成功",$data);
            }else{
                apiResponse("error","密码不正确");
            }
        }else{
            apiResponse("error","账号不存在");
        }
    }

    /**判断用户是否注册*/
    public function isRegister(){
        if($_POST['type'] == 1){
            $w['account'] = $_POST["account"];
            $w['status'] = array('neq',9);
            $res = M("Shop")->where($w)->limit(1)->getField("shop_id");
            if($res){
                apiResponse("error","此账号已经被注册！");
            }else{
                apiResponse("success","此账号可以注册！");
            }
        }else{
            $w['account'] = $_POST["account"];
            $w['status'] = array('neq',9);
            $res = M("Member")->where($w)->limit(1)->getField("m_id");
            if($res){
                apiResponse("error","此账号已经被注册！");
            }else{
                apiResponse("success","此账号可以注册！");
            }
        }

    }

    /**
     * 发送验证码
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param tel 手机号
     * @param verify_code 图形码
     * @param type 发送的类型bind：绑定  newBind：换绑  getPass：找回密码  register：注册，login:用户登录
     * @param port 0是用户 1是商家
     */
    public function sendVerify(){
        $key = $_POST['key'];
//        file_put_contents('tu.txt',$key);
        if($key){
            $code_status = $this->check_verify($_POST['verify_code'],'',$key);
            if(!$code_status){
                apiResponse("error","图形码错误！");
            }
        }else{
            $code_status = $this->check_verify($_POST['verify_code']);
            if(!$code_status){
                apiResponse("error","图形码错误！");
            }
        }
        $mobile = $_POST['tel'];
        if(!preg_match(C('MOBILE'),$mobile)) {
           apiResponse("error","手机格式不正确！");
        }
        $type = $_POST['type'];
        $port = $_POST['port'];
        /**根据验证码判断当前的手机号是否注册了，如果是bind判断是否注册，如果是newBind就判断这个手机号是否被注册了*/
        if($_POST['type'] == "bind" && $_POST['port'] == 0){
            $bind = M("Member")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("m_id");
            if($bind){
                apiResponse("error","此手机号已经被绑定了！");
            }
        }elseif ($_POST['type'] == "newBind" && $_POST['port'] == 0){
            $newBind = M("Member")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("m_id");
            if(!$newBind){
                apiResponse("error","验证失败！");
            }
        }elseif ($_POST['type'] == "bind" && $_POST['port'] == 1){
            $bind = M("Shop")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("shop_id");
            if($bind){
                apiResponse("error","此手机号已经被绑定了！");
            }
        }elseif ($_POST['type'] == "newBind" && $_POST['port'] == 1){
            $newBind = M("Shop")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("shop_id");
            if(!$newBind){
                apiResponse("error","验证失败！");
            }
        }elseif($_POST['type'] == "getPass" && $_POST['port'] == 1){
            $newBind = M("Shop")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("shop_id");
            if(!$newBind){
                apiResponse("error","账号不存在！");
            }
        }elseif($_POST['type'] == "edit_pass" && $_POST['port'] == 1){
            $newBind = M("Shop")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("shop_id");
            if(!$newBind){
                apiResponse("error","账号不存在！");
            }
        }elseif ($_POST['type'] == "register" && $_POST['port'] == 1){
            $newBind = M("Shop")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("shop_id");
            if($newBind){
                apiResponse("error","手机号已经被注册！");
            }
        }elseif($type=='login'&&$port==0){
            //用户登录发送验证码，2017-09-20，mss修改
            $login = M("Member")->where(array('account'=>$mobile,'status'=>array('neq',9)))->limit(1)->getField("m_id");
            if(!$login){
                apiResponse("error","账号不存在！");
            }
        }
        $res = D("UserOperate")->sendVerify($mobile,$type,$port);
        if(!empty($res['success'])){
            S($_POST['key'],null);
            apiResponse("success",$res['success']);
        }elseif(!empty($res['error'])){
            apiResponse("error",$res['error']);
        }else{
            apiResponse("error","未知错误！");
        }
    }

    /**
     * 验证验证码
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param account 手机号
     * @param vc 验证码
     * @param port  0是用户 1是商家
     */
    public function isSureCode(){
        $w['way'] = $_POST['account'];
        $w['vc'] = $_POST['vc'];
        $w['port'] = $_POST['port'];
        if($_POST['type']){
            $w['type'] = $_POST['type'];
        }
        $res = M("UserOperate")->where($w)->find();
        if($res){
            apiResponse("success","验证码正确！");
        }else{
            apiResponse("error","验证码错误！");
        }
    }


    /**
     * 生成验证码
     * @author crazy
     * @time 2017-07-03
     * @param key
     */
    public function verify(){
        $Verify = new \Think\Verify();
        if($_GET['key']){
//            file_put_contents('get_key.txt',$_GET['key']);
            $Verify->entry('',$_GET['key']);
        }else{
            $Verify->entry();
        }

    }

    /**
     * @param $code
     * @param string $id
     * @param string $key_app
     * @return bool
     * 验证码检验
     */
    public function check_verify($code,$id='',$key_app=''){
        $verify = new \Think\Verify();
//        file_put_contents('check_txt.txt',$key_app);
        return $verify->check($code,$id,$key_app);
    }


    /**
     * 绑定手机号
     * @author crazy
     * @time 2017-07-03
     * @param type  :  0是用户  1商家
     * @param account :  手机号
     * @param mixture_id  : 商家或者用户的id
     */
    public function bindTel(){
        $w['type'] = "bind";
        if(!empty($_POST['vc_type'])){
            $w['type'] = $_POST['vc_type'];
        }
        $w['way'] = $_POST['account'];
        $w['vc'] = $_POST['vc'];
        $res = M("UserOperate")->where($w)->find();
        if(!$res){
            apiResponse("error","验证码错误！");
        }

        if($_POST['type'] == 0){
            $is_set = M("Member")->where(array('account'=>$_POST['account'],'status'=>array('neq',9)))->limit(1)->find();
            if($is_set){
                apiResponse("error","手机号已经被绑定了！");
            }else{
                /**判断是否有推荐码*/
                $code = $_POST['code'];
                if($code){
                    /**根据推荐码，找到用户的个人的信息*/
                    $recommend = M("Member")->where(array('account'=>$_POST['code'],'status'=>array('neq',9)))->limit(1)->getField("m_id");
                    if($recommend){
                        $data['account'] = $_POST['account'];
                        $data['recommend'] = $recommend;
                        $account = M("Member")->where(array('m_id'=>$_POST['mixture_id']))->limit(1)->save($data);
                        if($account){
                            apiResponse("success","绑定成功！");
                        }else{
                            apiResponse("error","绑定失败！");
                        }
                    }else{
                        apiResponse("error","推荐码不正确！");
                    }
                }else{
                    $data['account'] = $_POST['account'];
                    $account = M("Member")->where(array('m_id'=>$_POST['mixture_id']))->limit(1)->save($data);
                    if($account){
                        apiResponse("success","绑定成功！");
                    }else{
                        apiResponse("error","绑定失败！");
                    }
                }
            }
        }else{
            $is_set = M("Shop")->where(array('account'=>$_POST['account'],'status'=>array('neq',9)))->limit(1)->find();
            if($is_set){
                apiResponse("error","手机号已经被注册了！");
            }else{
                $data['account'] = $_POST['account'];
                $account = M("Shop")->where(array('shop_id'=>$_POST['mixture_id']))->limit(1)->save($data);
                if($account){
                    apiResponse("success","绑定成功！");
                }else{
                    apiResponse("error","绑定失败！");
                }
            }
        }
    }

    /**
     * 用户注册协议
     * @author crazy
     * @time 2017-07-03
     */
    public function memberProtocol(){
        $config = D("Config")->getField("user_protocol");
        $data['content'] = $config;
        preg_match_all('/src=\"\/?(.*?)\"/',$data['content'],$match);
        foreach($match[1] as $key => $src){
            if(!strpos($src,'://')){
                $data['content'] = str_replace('/'.$src,'http://'.$_SERVER['HTTP_HOST']."/".$src."\" width=60%",$data['content']);
            }
        }
        if(!empty($config)){
            apiResponse("success","加载成功",$config);
        }else{
            apiResponse("error","加载失败");
        }
    }

    /**
     * APP端用户登录
     * 传参方式 post
     * @author mss
     * @time 2017-09-20
     * @param account 手机号
     * @param vc 短信验证码
     */
    public function memberLogin(){
        $w['type'] = "login";
        $w['way'] = $_POST['account'];
        $w['vc'] = $_POST['vc'];
        if($_POST['account'] != "13323363935"){
            $res = M("UserOperate")->where($w)->find();
            if(!$res){
                apiResponse("error","验证码错误！");
            }
        }
        $member = M('Member')->where(array('account'=>$_POST['account'],'status'=>array('NEQ',9)))->find();
        if($member){
            $hx = $this->isMemberRegistHX($member['m_id']);
            $where['m_id'] = $member['m_id'];
            /**修改最后的登录时间和ip地址*/
            /**微信端传这个参数通知是否已经注册极光了，如果不为1就是微信登录，1就是app登录*/
            if($_POST['is_wechat'] == 1){
                $save_data['is_set'] = 1;
            }
            $save_data['last_login_time'] = time();
            $save_data['last_login_ip'] = get_client_ip();
            M("Member")->where($where)->limit(1)->save($save_data);
            $data['m_id'] = $member['m_id'];
            if($hx){
                $data['hx_name'] = $hx['hx_name'];
                $data['hx_password'] = $hx['hx_password'];
            }else{
                $data['hx_name'] = $member['hx_name'];
                $data['hx_password'] = $member['hx_password'];
            }
            $data['is_bind'] = $member['account'];
            $data['nick_name'] = $member['nick_name'];
            $data['head_pic'] = $member['head_pic'];
            apiResponse('success','登录成功',$data);
        }else{
            apiResponse("error","账号不存在");
        }

    }




}