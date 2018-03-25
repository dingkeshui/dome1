<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * Class ConfigController
 * @package Admin\Controller
 * 2014-5-30   系统配置类
 */
class ConfigController extends AdminBasicController{

    public $config_obj = '';
    public function _initialize(){
        $this->checkLogin();
        $this->config_obj = D('Config');
    }

    /**
     * 网站配置
     */
    public function config(){
        if(empty($_POST)){
            $config = $this->config_obj->findConfig();
            $config['start_time'] = date('Y-m-d H:i:s',$config['start_time']);
            $config['end_time'] = date('Y-m-d H:i:s',$config['end_time']);
            $this->assign('config',$config);
            $this->display('config');
        }else{
            $this->checkAuth('Config','config');
            $data = $this->config_obj->create();
            if($data){
                //是否上传了LOGO
                if(!empty($_FILES['site_logo']['name'])){
                    $res = uploadFile('','Config');
                    if(empty($res['error'])){
                        $data['site_logo'] = $res['success'];
                    }else{
                        $this->error($res['error']);
                    }
                }
                if (get_magic_quotes_gpc()) {
                    $data['about'] = stripslashes($_POST['about']);
                } else {
                    $data['about'] = $_POST['about'];
                }
                if (get_magic_quotes_gpc()) {
                    $data['pattern'] = stripslashes($_POST['pattern']);
                } else {
                    $data['pattern'] = $_POST['pattern'];
                }
                if (get_magic_quotes_gpc()) {
                    $data['user_protocol'] = stripslashes($_POST['user_protocol']);
                } else {
                    $data['user_protocol'] = $_POST['user_protocol'];
                }
                if($_POST['start_time']){
                    $data['start_time'] = strtotime($_POST['start_time']);
                }
                if($_POST['end_time']){
                    $data['end_time'] = strtotime($_POST['end_time']);
                }
                $upd_res = $this->config_obj->editConfig($data);
                if($upd_res){
                    $this->success('保存成功');
                }else{
                    $this->error('保存失败，原因可能是未修改任何值');
                }
            }else{
                $this->error($this->config_obj->getError());
            }
        }
    }
}