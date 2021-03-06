<?php
namespace Merchant\Controller;
use Think\Controller;
/**
 * Class EmptyController
 * @package Shop\Controller
 * 商家微信页面
 */
class EmptyController extends MerchantBasicController {
   //首页展示
	public function _initialize(){
        parent::_initialize();
	}

    public function _empty(){
        $this->display();
    }


    /**上传*/
    public function uploadPic()
    {
        $pic = $_POST['pic'];
        $pic_name = $_POST['pic_name'];
        $temp = explode('.', $pic_name);
        $ext = uniqid() . '.' . end($temp);
        $base64 = substr(strstr($pic, ","), 1);
        $image_res = base64_decode($base64);
        $pic_link = "Uploads/Member/" . date('Y-m-d') . '/' . $ext;
        $saveRoot = "Uploads/Member/" . date('Y-m-d') . '/';
        //检查目录是否存在  循环创建目录
        if (!is_dir($saveRoot)) {
            mkdir($saveRoot, 0777, true);
        }
        $res = file_put_contents($pic_link, $image_res);
        if ($res) {
            /**修改用户的头像*/
            $w['m_id'] = $_POST['m_id'];
            $member_data['head_pic'] = "/Uploads/Member/" . date('Y-m-d') . '/' . $ext;
            $member_res = D("Member")->where($w)->save($member_data);
            if ($member_res) {
                $data['path'] = '/' . $pic_link;
                $ajaxData = array("flag" => "success", "message" => "上传成功！");
                $result_data['path'] = $data['path'];
                $ajaxData['data'] = $result_data;
                $this->ajaxReturn(json_encode($ajaxData));
            } else {
                $ajaxData = array("flag" => "error", "message" => "上传头像失败", "data" => array());
                $this->ajaxReturn($ajaxData);
            }
        } else {
            $ajaxData = array("flag" => "error", "message" => "上传头像失败", "data" => array());
            $this->ajaxReturn($ajaxData);
        }
    }
}