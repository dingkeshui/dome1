<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class DeskController
 * @package Api\Controller
 * 桌台背景的相关
 */
class DeskController extends ApiBasicController{
    public function _initialize(){
        parent::_initialize();

    }

    /**显示商家的桌台二维码图片
     * @author crazy
     * @time 2018-01-09
     */
    public function getDeskPic(){
        $deskPic = M("Shop")->where(['shop_id'=>$_POST['shop_id']])->getField("desk");
        apiResponse("success",'成功',empty($deskPic)?"":C("API_URL").$deskPic);
    }

    /**生成一个桌台的二维码
     * @author crazy
     * @time 2018-01-09
     */
    public function pngDesk()
    {
        $i = $_GET['i']-1;
        $shop_id = $_GET["shop_id"];
        $returnMess = [];
        /**找到桌台背景的列表*/
        $list = M("Desk")->where(['status'=>['neq',9]])->select();
        if(empty($list)){
            apiResponse("error","暂无数据");
        }
        $count = count($list);
        if($i == $count){
            $i = 0;
        }
        $one = array_splice($list,$i,1);
        /**先找到商家*/
        $shop_res = M("Shop")->where(['shop_id'=>$shop_id])->find();
        $code_path = $shop_res['code'];
        $savePath = "Uploads/Desk/shop_{$shop_id}/";
        if(!is_dir($savePath)){
            mkdir($savePath,0777,true);
        }
        $shop_desk_pic = $savePath.$one[0]['id'].".jpg";
        /**如果这张图片已经生成了，那么就直接返回图片路径*/
        if(file_exists($shop_desk_pic)){
            $returnImgPath = C("API_URL").'/'.$shop_desk_pic;
        }else{
            $image_s = new \Think\ImageNew();
            $image_s->open($code_path);
            $image_s->thumb(715,715,\Think\Image::IMAGE_THUMB_FIXED)->save($savePath."code_$shop_id".".jpg");
            /**处理合成的图片*/
            $image = new \Think\Image();
            $location=array(210,480,180,180);
            $image->open('./Uploads/'.$one[0]['pic'])->water($savePath."code_$shop_id".".jpg",$location,100)->save($savePath.$one[0]['id'].".jpg");
            $returnImgPath = C("API_URL").'/'.$savePath.$one[0]['id'].".jpg";
        }
        $returnMess['img_path'] = $returnImgPath;
        $returnMess['i'] = $i;
        apiResponse("success","成功",$returnMess);


    }

    public function test(){
        $i = $_GET['i']-1;
        $list = M("Desk")->where(['status'=>['neq',9]])->select();
        $count = count($list);
        if($i == $count-1){
            dump(1);
        }
        $one = array_splice($list,$i,1);
        dump($one);
    }




}