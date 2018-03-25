<?php
namespace Api\Controller;
use Think\Controller;
class ExtendController extends ApiBasicController{

    public function _initialize(){
        parent::_initialize();

    }


    /**开启抽奖和关闭抽奖
     * @author crazy
     * @time 2018-03-02
     */
    public function getDrawBut(){
        $draw_but = M("Config")->getField('draw_but');
        apiResponse("success","获取成功",$draw_but);
    }
}