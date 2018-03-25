<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 更新模块
 */
class VersionsController extends ApiBasicController{


    public function _initialize(){
        parent::_initialize();
    }



    /**
     * 用户端版本检测
     * 传递参数的方式：post
     * 需要传递的参数：
     * type:1用户端 2商家端
     */
    public function upgrade(){
        if($_POST['type']!=1 && $_POST['type']!=2){
            apiResponse('error','参数错误');
        }
        $config = D('config')->find();
        $result_data = array();
        if($_POST['type']==1){
            $result_data['uri']  = C('API_URL').'/index.php/Api/Versions/memberVersions';
            $result_data['message'] = "众享通赢用户端正式版";  //
            $result_data['member_v'] = $config['member_update'];  //安卓用户端的版本号
            $result_data['company_v'] = $config['company_update'];  //ios用户端的版本号
        }elseif($_POST['type']==2) {
            $result_data['uri'] = C('API_URL') . '/index.php/Api/Versions/shopVersions';
            $result_data['message'] = "众享通赢商家端正式版";  //
            $result_data['member_v'] = $config['member_update'];  //安卓用户端的版本号
            $result_data['company_v'] = $config['company_update'];  //ios用户端的版本号
        }
        apiResponse('success','',$result_data);
    }

    public function memberVersions(){
        $file = "./Uploads/Version/user.apk";
        header("Content-type: application/vnd.android.package-archive;");
        header('Content-Disposition: attachment; filename="' . 'user.apk' . '"');
        header("Content-Length: ". filesize($file));
        readfile($file);
    }
    public function shopVersions(){
        $file = "./Uploads/Version/Business.apk";
        header("Content-type: application/vnd.android.package-archive;");
        header('Content-Disposition: attachment; filename="' . 'Business.apk' . '"');
        header("Content-Length: ". filesize($file));
        readfile($file);
    }

    /*
     *  判断是否需要更新
     */
    public function checkUpdate(){
        $config = D("Config")->field("member_update,company_update")->find();
        apiResponse("success","加载成功",$config);
    }

}