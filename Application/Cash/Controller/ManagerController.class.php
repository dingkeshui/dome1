<?php

namespace Cash\Controller;
use Think\Controller;
/**
 * Class ManagerController
 * @package Cash\Controller
 */
class ManagerController extends AdminBasicController {

    public function newLogin(){
        $session = session('E_ID');
        if(empty($session)){
            $this->display('newLogin');
        }else{
            redirect(U('Index/index'));
        }
    }

    /**
     * 登陆
     */
    public function doLogin(){
        $admin = D('agency_earn');
        if(empty($_POST['account'])){
            $this->ajaxMsg('error','请输入账号');
        }if(empty($_POST['password'])){
            $this->ajaxMsg('error','请输入密码');

        }if($_POST['errorNum']<3){
            $where['account'] = I('post.account');
            $find_res = $admin->where($where)->find();
            if($find_res){
                $where1['account'] = I('post.account');
                $where1['password'] = md5(I('post.password'));
                $find_res1 = $admin->where($where1)->find();
                if($find_res1){
                    //修改最后登录时间和ip 错误次数归零
                    $admin->where(array('e_id'=>$find_res1['e_id']))->limit(1)->save(array('last_login_time'=>time(),'error_num'=>0,'last_login_ip'=>$_SERVER["REMOTE_ADDR"]));
                    ///设置session
                    session('E_ACCOUNT',$find_res1['account']);
                    session('E_ID',$find_res1['e_id']);
                    session('CLASS_E_ID',$find_res1['class_id']);
                    $this->ajaxMsg('success','登陆成功');
                }else{
                    $where2['account'] = I('post.account');
                    $error_num = $admin->where($where2)->find();
                    $data['error_num'] = $error_num['error_num']+1;
                    $admin->where($where2)->limit(1)->save($data);
                    $this->ajaxMsg('error','用户名或密码错误');
                }
            }
        }if($_POST['errorNum']>=3){
            if(!$this->check_verify($_POST['verify'])){
                $this->ajaxMsg('error','验证码错误');
            }else{
                $where3['account'] = I('post.account');
                $where3['password'] = md5(I('post.password'));
                $find_res3 = $admin->where($where3)->find();
                if($find_res3){
                    //修改最后登录时间和ip 错误次数归零
                    $admin->where(array('a_id'=>$find_res3['a_id']))->limit(1)->save(array('last_login_time'=>time(),'error_num'=>0,'last_login_ip'=>$_SERVER["REMOTE_ADDR"]));
                    ///设置session
                    session('E_ACCOUNT',$find_res3['account']);
                    session('E_ID',$find_res3['e_id']);
                    session('CLASS_E_ID',$find_res3['class_id']);
                    $this->ajaxMsg('success','登陆成功');
                }else{
                    $where4['account'] = I('post.account');
                    $error_num1 = $admin->where($where4)->find();
                    $data1['error_num'] = $error_num1['error_num']+1;
                    $admin->where($where4)->limit(1)->save($data1);
                    $this->ajaxMsg('error','用户名或密码错误');
                }
            }
        }
    }
    /**
     * 异步校验用户名
     */
    public function checkAccount(){
        $admin = D('agency_earn');
        $where['account'] = I('post.account');
        $find_res = $admin->where($where)->find();
        if($find_res){
            $today=date('Y-m-d');
            $oldtime=date('Y-m-d',$find_res['last_login_time']);
            if($today==$oldtime){
            }else{
                $where1['account'] = I('post.account');
                $find_res['last_login_time']=time();
                $find_res['error_num']=0;
                $admin->where($where1)->limit(1)->save($find_res);
            }
            $this->ajaxMsg('success',$find_res['error_num']);
        }else{
            $this->ajaxMsg('error','该账户不存在！');
        }
    }
    /**
     * 退出登录
     */
    public function logOut(){
        session('E_ACCOUNT',null);
        session('E_ID',null);
        session('A_GROUP',null);
        redirect(U('Manager/newLogin'));
    }

    /**
     * 生成验证码
     */
    public function verify(){
        $Verify = new \Think\Verify();
        $Verify->entry();
    }

    /**
     * @param $code
     * @param string $id
     * @return bool
     * 验证码检验
     */
    public function check_verify($code,$id=''){
        $verify = new \Think\Verify();
        return $verify->check($code,$id);
    }
}