<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class AdvertController
 * @package Api\Controller
 * 广告模板
 */
class AdvertController extends ApiBasicController{
    public function _initialize(){
        parent::_initialize();

    }
    /**
     * 点击广告的次数统计
     * @author crazy
     * @time 2018-01-24
     * type 1用户签到图  2商家签到图  3用户端滚动图片  4用户端首页广告  5用户端商城首页广告 6、商城首页轮播广告 port 端别 ios Android yd
     */
    public function clickAdvert(){
        $data = [
            'm_id'     =>I('post.m_id')?I('post.m_id'):0,
            'advert_id'=>I('post.a_id'),
            'ctime'    =>time(),
            'type'     =>I('post.type'),
            'port'     =>trim(I('post.port'))
        ];
        $res = M("ClickAdvert")->add($data);
        if($res){
            apiResponse("success","成功");
        }else{
            apiResponse("error",'失败');
        }
    }


}