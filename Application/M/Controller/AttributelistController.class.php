<?php
namespace M\Controller;
use Think\Controller;

class AttributelistController extends AdminBasicController {
	public function addAttribute(){
		# code...
		$this->assign('shop_id',session('SHOP_ID'));
		$this->display("addAttribute");
	}
}