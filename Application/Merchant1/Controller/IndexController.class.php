<?php
namespace Merchant\Controller;
use Think\Controller;
/**
 * Class IndexController
 * @package Shop\Controller
 * PC页面
 */
class IndexController extends MerchantBasicController {

    public function _initialize(){
        parent::_initialize();
    }
   //首页展示
    public function index(){
        $this->display('Shop/index');
    }

    public function loginout(){
    	session("SHOP_ID",null);
    	$this -> display("Shop/login");
    }

}