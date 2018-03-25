<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class ShareController
 * @package Api\Controller
 * 友盟分享
 */
class ShareController extends ApiBasicController{
    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }

    /**商家或者商品的分享
     * @author crazy
     * @time 2018-01-18
     * @param type  1是商城商品 2是积分商品 3是商家
     * @mix_id  商品的id 或者商家的id
     */
    public function shareMessage(){
        $type = I('post.type');
        $mix_id = I('post.mix_id');
        $title = "";
        $desc = "";
        $icon = "";
        $url = "";
        switch($type){
            case 1:
                $product = M("Product")->where(['p_id'=>$mix_id])->find();
                $title = $product['title'];
                $desc = mb_substr(filterHtml($product['content']) , 0 , 300 ,'utf-8');
                $icon = $product['cover_pic'];
                $url = C('API_URL')."/index.php/Newweb/Store/productinfo/p_id/{$mix_id}";
                break;
            case 2:
                $goods = M("Goods")->where(['g_id'=>$mix_id])->find();
                $title = $goods['name'];
                $desc = mb_substr(filterHtml($goods['desc']),0,300,'utf-8');
                $icon = $goods['cover_pic'];
                $url = C('API_URL')."/index.php/Newweb/Goods/goodsinfo/g_id/{$mix_id}";
                break;
            case 3:
                $shop = M("Shop")->where(['shop_id'=>$mix_id])->find();
                $title = $shop['name'];
                $desc = mb_substr(filterHtml($shop['content']),0,300,'utf-8');
                $icon = '/'.$shop['head_pic'];
                $url = C('API_URL')."/index.php/Newweb/Shop/shopdetail/shop_id/{$mix_id}";
                break;
        }

        $return_data = [
            'title'=>$title?$title:"众享通赢",
            'desc'=>$desc?$desc:"众享通赢",
            'icon'=>$icon?C('API_URL').'/'.$icon:C('API_URL').'/Uploads/logo.png',
            'url'=>$url
        ];
        apiResponse("success","获取成功",$return_data);

    }

}