<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 用户信息管理
 */
class FeedbackController extends AdminBasicController {
    public $Feed = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Feed = D('Feedback');
    }

    /**反馈的信息列表*/
    public function feedList(){
        $parameter = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        if($_REQUEST['type_feed']){
            $w['type'] = $_REQUEST['type_feed']-1;
            $parameter['type_feed'] = $_REQUEST['type_feed'];
            $this->assign("type_feed",$_REQUEST['type_feed']);
        }
        $w['status'] = array("neq",9);
        $list = D("Feedback")->selectFeed($w,"ctime desc",15,$parameter);
        foreach ($list['list'] as $k=>$v){
            if($v['type'] == 0){
                $list['list'][$k]['nick_name'] = M("Member")->where(['m_id'=>$v['m_id']])->getField('nick_name')?trim(M("Member")->where(['m_id'=>$v['m_id']])->getField('nick_name')):"用户昵称为空";
            }elseif($v['type'] == 1){
                $list['list'][$k]['nick_name'] = "官网反馈";
            }
            if($v['feed_type'] == 1){
                $list['list'][$k]['name'] = M("Shop")->where(['shop_id'=>$v['m_id']])->getField('name')?trim(M("Shop")->where(['shop_id'=>$v['m_id']])->getField('name')):"商家昵称为空";
            }
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("feedList");
    }


    /**
     * 反馈的信息修改
     * @author crazy
     * @time 2017-09-11
     * @update 2017-09-11
     */
    public function editFeedback(){
        if(!IS_POST){
            $res = M("Feedback")->where(array("f_id"=>$_GET['f_id']))->find();
            $this->assign("res",$res);
            $this->display("editFeed");
        }else{
            $data = D("Feedback")->create();
            if (get_magic_quotes_gpc()) {
                $data['re_content'] = stripslashes($_POST['re_content']);
            } else {
                $data['re_content'] = $_POST['re_content'];
            }
            $data['status'] = 1;
            $data['utime'] = time();
            $res = M("Feedback")->where(array('f_id'=>$_POST['f_id']))->limit(1)->save($data);
            /**给反馈的用户发送短信*/
            $this->sendMsgFeed($_POST['tel'],$data['re_content']);
            if($res){
                $this->success("回复成功！",U("Feedback/feedList"));
            }else{
                $this->error("回复失败！");
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteFeedback(){
        if(empty($_REQUEST['f_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['f_id'] = array('IN',I('request.f_id'));
        $data['status'] = 9;
        $upd_res = $this->Feed->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }


}