<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class CreeperController
 * @package Api\Controller
 * 爬取商品的相关
 */
class CreeperController extends ApiBasicController{
    public function _initialize(){
        parent::_initialize();

    }


    /**爬取商品
     */
    public function addProduct(){
        $data = [
            'title'=>$_POST['title'],
            'cover_pic'=>$_POST['cover_pic'],
            'pics'=>$_POST['pics'],
            'is_sale'=>0,
            'url'=>$_POST['url'],
            'content'=>$_POST['content'],
            'ctime'=>time()
        ];
        $is_set = M("Product")->where(['cover_pic'=>$_POST['cover_pic']])->find();
        if($is_set){
            if(empty($is_set['content'])){
                $res = M("Product")->where(['cover_pic'=>$_POST['cover_pic']])->limit(1)->save(['utime'=>time(),'content'=>$_POST['content']]);
            }else{
                $res = M("Product")->where(['cover_pic'=>$_POST['cover_pic']])->limit(1)->save(['utime'=>time()]);
            }
        }else{
            $res = M("Product")->add($data);
        }
        if($res){
            apiResponse("success","添加成功");
        }else{
            apiResponse("error","添加失败");
        }
    }











}