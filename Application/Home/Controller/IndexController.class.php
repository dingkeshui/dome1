<?php
namespace Home\Controller;
use Think\Controller;
/**
 * Class IndexController
 * @package Home\Controller
 * PC页面
 */
class IndexController extends WechatBasicController {

    public function _initialize(){
        parent::_initialize();
    }
   //首页展示
    public function index(){
        $this->display('index');
    }

}