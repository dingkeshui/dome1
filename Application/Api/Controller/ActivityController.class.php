<?php
namespace Api\Controller;
use Think\Controller;
use Think\Image;

/**
 * Class FeedbackController
 * @package Api\Controller
 * 活动
 */
class ActivityController extends ApiBasicController{


    public function _initialize(){
        parent::_initialize();

    }


    /**
     * 中秋节月饼活动,选择月饼
     * 传参方式 post
     * @author mss  update_author crazy @time 2017-09-19
     * @time 2017-09-18
     * @param m_id 用户的id
     * @param yb 月饼id
     */
    public function chooseMoon(){
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误");
        }
        if(empty($_POST['yb'])){
            apiResponse("error","参数错误");
        }
        $m_id = $_POST['m_id'];
        $yb = $_POST['yb'];
        $infoSet = 'moonCake';
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        if($redis->hSetNx($infoSet,'member_'.$m_id,$yb)){
            apiResponse('success','选择成功');
        }else{
            apiResponse('error','已选择月饼');
        }
//        $info = $redis->hGet($infoSet,'member_'.$m_id);
//        $data = array('info'=>$info);
    }
    /**
     * 中秋节月饼活动,判断用户是否已经选择过月饼
     * 传参方式 get
     * @author mss
     * @time 2017-09-18
     * @param m_id 用户的id
     */
    public function isChooseMoon(){
        if(empty($_GET['m_id'])){
            apiResponse("error","参数错误");
        }
        $m_id = $_GET['m_id'];
        $redis = new \Redis();
        $redis->connect('127.0.0.1', 6379);
        $info = $redis->hGet('moonCake','member_'.$m_id);
//        $info = '2';
        $member = M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->field('nick_name,head_pic')->find();
        if($info){
            $data = array('info'=>$info,'name'=>$member['nick_name'],'head_pic'=>$member['head_pic']);
            apiResponse('success','获取成功',$data);
        }else{
            apiResponse('error','还未参加活动');
        }

    }

    /**
     * test图片下载，合成，压缩，打水印的功能，打水印针对压缩的图片无法添加水印
     * 故此不适用打水印功能
     * @author crazy
     * @time 2017-09-20
    */
    public function linuxImg(){
            $yb_id = 1;
            $m_id = 2;
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
                $location=array(110,130,180,180);
                // 在图片左上角添加水印（水印文件位于./logo.png） 并保存为water.jpg
                $image->open("./Public/Wechat/nowimg/$yb_id.png")->water("Uploads/Activity/Member/$m_id.jpg",$location,100)->save("water.jpg");
                $location1=array(0,0,11,11);
                $image->open("Public/Wechat/nowimg/yb1.png")->text('liuzhu','./simhei.ttf',30,'#e53535',$location1)->save("water.jpg");
            }else{

            }
    }




    public function index(){
        $appId = "wx6f7120af2ddc526c";
        $appSecert = "b3d82c76ac9b7fe264ba5081e9ac66a0";
        $code = I('get.code');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appId&secret=$appSecert&js_code=$code&grant_type=authorization_code";
        $row = $this->httpsRequest($url);
        dump($row);
    }



    /**
     * 获取接口信息
     * @author crazy
     * @time 2017-07-03
     */
    public function httpsRequest($url,$data = ""){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

}