<?php
namespace Admin\Controller;
use Think\Controller;
/**
 *  站内信息管理
 */
class MessageController extends AdminBasicController {
    public $Message = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Message = D('Message');
    }

    /**信息的列表*/
    public function messageList(){
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
        $w['status'] = array("neq",9);
        $list = D("Message")->selectMessage($w,"ctime desc",15,$parameter);
        foreach ($list['list'] as $kk=>$vv){
            /**获取用户的信息*/
            if($vv['id_type'] == 1){
                $list['list'][$kk]['name'] = M('Shop')->where(array('shop_id'=>$vv['m_id']))->getField("name");
            }else{
                $list['list'][$kk]['name'] = M('Member')->where(array('m_id'=>$vv['m_id']))->getField("nick_name");
            }
            $list['list'][$kk]['content'] = filterHtml($vv['content']);
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("messageList");
    }

    /**发布信息*/
    public function addMessage(){
        if(!IS_POST){
            $this->display("addMessage");
        }else{
            $data = M("Message")->create();
            if($data){
                $data['ctime'] = time();
                $data['type'] = 1;
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                $res = M("Message")->add($data);
                if($res){
                    $extra['mess_id'] = ''.$res;
                    $extra['type'] = '3';
                    $this->jPushToAll(filterHtml($data['content']),$extra,$data['id_type']);
                   /* if($data['id_type']==1){
                        //给商家端推送
                        $shop_ids = M('Shop')->where(array('status'=>array('NEQ',9)))->getField('shop_id',true);
                        $count = count($shop_ids);
                        $num = $count/1000;
                        $extra['mess_id'] = ''.$res;
                        $extra['type'] = '3';
                        for($i=0;$i<$num;$i++){
                            $arr = array_slice($shop_ids,$i*1000,1000);
                            $this->push('' , $arr , $data['title'] ,filterHtml($data['content']) , $extra,1);
                        }
                        //$this->push("1" , "1" , $data['title'] , $data['content'] , $extra,1);
                    }else{
                        //给用户端推送
                        $m_ids = M('Member')->where(array('status'=>array('NEQ',9)))->getField('m_id',true);
                        $count = count($m_ids);
                        $num = $count/1000;
                        $extra['mess_id'] = ''.$res;
                        $extra['type'] = '3';
                        for($i=0;$i<$num;$i++){
                            $arr = array_slice($m_ids,$i*1000,1000);
                            $this->push('' , $arr , $data['title'] , $data['content'] , $extra, 0);
                        }
//                        $extra['mess_id'] = ''.$res;
//                        $this->push("1" , "1" , $data['title'] , $data['content'] , $extra, 0);
                    }*/

                    $this->success("发布成功！",U("Message/messageList"));
                }else{
                    $this->error("发布失败！");
                }
            }else{
                $this->error(M("Message")->getError());
            }
        }
    }

    /**
     * 删除操作
     */
    public function deleteMessage(){
        if(empty($_REQUEST['mess_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['mess_id'] = array('IN',I('request.mess_id'));
        $data['status'] = 9;
        $upd_res = $this->Message->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }


}