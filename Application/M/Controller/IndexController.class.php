<?php
namespace M\Controller;
use Think\Controller;

/**
 * Class IndexController
 * @package Admin\Controller
 * 管理页面
 */
class IndexController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();
    }

    public function index(){
        $this->display('index');
    }

    public function top(){
        $this->assign('account',session('SHOP_ACCOUNT'));
        $this->assign('shop_name',session('SHOP_NAME'));
        $this->display('top');
    }

    public function left(){
        $this->display('left');
    }

    public function main(){
        $this->display('main');
    }

}