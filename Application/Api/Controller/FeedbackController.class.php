<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 意见反馈模块
 */
class FeedbackController extends ApiBasicController{

    public $Feedback = '';
    public function _initialize(){
        parent::_initialize();
        $this->Feedback = D('Feedback');
    }
    /**
     * 添加意见反馈
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param content 反馈的内容
     * @param tel 反馈人的手机号
     * @param name 反馈人的姓名
     * @param m_id 反馈人的id
     */
    public function addFeedback(){

        if(empty($_POST['content'])){
            apiResponse("error","请输入反馈内容！");
        }elseif(empty($_POST['tel'])){
            apiResponse("error","请输入手机号！");
        }elseif(!preg_match(C('MOBILE'),$_POST['tel'])){
            apiResponse("error","手机号格式不正确！");
        }elseif(empty($_POST['name'])){
            apiResponse("error","请输入您的姓名或称呼，方便客服人员与您联系！");
        }elseif(empty($_POST['m_id'])){
            apiResponse("error","参数错误！");
        }
        $data = $this->Feedback->create();
        $data['content'] = strip_tags($_POST['content']);
        if(empty($data)){
            apiResponse("error","提交失败");
        }else{
            $res = $this->Feedback->add($data);
            if($res){
                apiResponse("success","提交成功",$res);
            }else{
                apiResponse("error","提交失败");
            }
        }
    }


}