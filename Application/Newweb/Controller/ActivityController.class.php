<?php
namespace Newweb\Controller;
use Think\Controller;
/**
 * Class IndexController
 * @package Home\Controller
 * 活动类
 */
class ActivityController extends WechatBasicController {

    public function _initialize(){
        parent::_initialize();
    }
   //首页展示
    public function start(){
        $m_id = session('M_ID');
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        //$redis->flushAll();
        $info = $redis->hGet('moonCake','member_'.$m_id);
        if($info){
            redirect(U('Activity/end',array('bgid'=>$info,'other'=>1)));
        }
        $this->display('start');
    }

    public function end(){
//        /**查找用户的头像并与月饼的图片合成为一张图片*/
//        $member = M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->getField('head_pic');
//        $member = strstr($member,'http')?$member:'https://m.zxty.me'.$member;
//        $img = $this->base64Img($member);
//        $this->assign('img',$img);
        $m_id = session("M_ID");
        $yb_id = $_GET['bgid'];
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $info = $redis->hGet('moonCake','member_'.$m_id);
//        file_put_contents('yuebing.txt',$info);
        if(empty($info)){
            redirect(U('Activity/start'));
        }
        if($_GET['bgid'] && $_GET['other']){
            $url_host = 'https://'.$_SERVER['HTTP_HOST'];
            $this->assign('img_url',$url_host."/Uploads/Activity/Member/mem_".$m_id."_yb_$info.jpg");
        }else{
            /**查找用户的头像并与月饼的图片合成为一张图片*/
            $member = M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->getField('head_pic');
            if(strstr($member,'http')){
                $file = 'down_'.$m_id.'.jpg';
                if(!file_exists($file)){
                    $imgPath = saveImg($member,'Uploads/Activity/Member/','down_'.$m_id);
                }else{
                    $imgPath = "Uploads/Activity/Member/','down_'.$m_id";
                }
                /**处理合成的图片*/
                $image = new \Think\Image();
                // 生成一个缩放后填充大小180*180的缩略图并保存为thumb.jpg
                $image_s = new \Think\ImageNew();
                $image_s->open($imgPath);
                $image_s->thumb(180,180,\Think\Image::IMAGE_THUMB_FIXED)->save("Uploads/Activity/Member/$m_id.jpg");
                $location=array(110,150,180,180);
                // 在图片左上角添加水印（水印文件位于./logo.png） 并保存为mem_id.jpg
                $image->open("Public/Wechat/nowimg/$yb_id.png")->water("Uploads/Activity/Member/$m_id.jpg",$location,100)->save("Uploads/Activity/Member/mem_".$m_id."_yb_$yb_id.jpg");
                $url_host = 'https://'.$_SERVER['HTTP_HOST'];
                $this->assign('img_url',$url_host."/Uploads/Activity/Member/mem_".$m_id."_yb_$yb_id.jpg");
            }else{
                /**处理合成的图片*/
                $image = new \Think\Image();
                // 生成一个缩放后填充大小180*180的缩略图并保存为thumb.jpg
                $image_s = new \Think\ImageNew();
                $image_s->open(ltrim($member, "/"));
                $image_s->thumb(180,180,\Think\Image::IMAGE_THUMB_FIXED)->save("Uploads/Activity/Member/$m_id.jpg");
                $location=array(110,150,180,180);
                // 在图片左上角添加水印（水印文件位于./logo.png） 并保存为mem_id.jpg
                $image->open("Public/Wechat/nowimg/$yb_id.png")->water("Uploads/Activity/Member/$m_id.jpg",$location,100)->save("Uploads/Activity/Member/mem_".$m_id."_yb_$yb_id.jpg");
                $url_host = 'https://'.$_SERVER['HTTP_HOST'];
                $this->assign('img_url',$url_host."/Uploads/Activity/Member/mem_".$m_id."_yb_$yb_id.jpg");
            }
        }
        $this -> display("end");
    }

    public function _empty(){
        $this->display();
    }



    public function linuxImg(){
        if($_GET['bgid']){

        }else{
            $yb_id = 1;
            $m_id = 5;
            /**查找用户的头像并与月饼的图片合成为一张图片*/
            $member = M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->getField('head_pic');
            if(strstr($member,'http')){
                $imgPath = saveImg($member,'Uploads/Activity/Member/');
                /**处理合成的图片*/
                $image = new \Think\Image();
                // 生成一个缩放后填充大小150*150的缩略图并保存为thumb.jpg
                $image_s = new \Think\ImageNew();
                $image_s->open($imgPath);
                $image_s->thumb(180,180,\Think\Image::IMAGE_THUMB_FIXED)->save("Uploads/Activity/Member/$m_id.jpg");
                $location=array(110,150,180,180);
                // 在图片左上角添加水印（水印文件位于./logo.png） 并保存为water.jpg
                $image->open("./Public/Wechat/nowimg/$yb_id.png")->water("Uploads/Activity/Member/$m_id.jpg",$location,100)->save("water.jpg");
            }else{

            }
        }
    }
 
    /**
     *处理微信图片转64位编码
     */
    public function base64Img($url){
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $contents = curl_exec($ch);
        curl_close($ch);
        $other = base64_encode($contents);
        return "data:image/png;base64,$other";
    }

}