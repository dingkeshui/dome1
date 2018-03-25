<?php

namespace Admin\Controller;
use Think\Controller;

/**
 * Class HXChatController
 * @package Admin\Controller
 * 2017-8-09  add by mashan
 */
class HXChatController extends Controller {
    /**
     * 保存文本信息
     * 传参方式 post
     * @author mashan
     * @time 2017-08-09
     * @param content 文本消息内容
     * @param sender 消息发送者
     * @param recipient 消息接收者
     * @param chatType 消息类型
     */
    public function addTextInfo(){
        $data['sender'] = I('post.sender');
        $data['recipient'] = I('post.recipient');
        $data['content'] = htmlspecialchars_decode(I('post.content'));
        $data['type'] = 0;
        $chattype = strtolower(I('post.chatType'));
        if ($chattype=='groupchat'||$chattype=='groups'){
            $data['type'] = 1;
        }elseif($chattype=='chatroom'||$chattype=='chatrooms'){
            $data['type'] = 2;
        }

        $data['ctime'] = time();
        $rs = M('easemob')->add($data);
        if($rs){
            $this->success('保存成功');
        }else{
            $this->error('保存失败');
        }
    }
    /**
     * 查询用户的聊天信息
     * 传参方式 get
     * @author mashan
     * @time 2017-08-09
     * @param nowuser 当前登录的用户名
     * @param touser 对应聊天的用户名
     * @param chatType 聊天类型
     */
    public function chatInfo(){
        $where['status'] = 0;
        $sender = I('get.nowuser');
        $recipient = I('get.touser');
        if(I('get.type')=='groups'){
            $where['type'] = 1;
            $where['recipient'] = $recipient;
        }elseif(I('get.type')=='chatrooms'){
            $where['type'] = 2;
            $where['recipient'] = $recipient;
        }else{
            $where['type'] = 0;
            $where['_string'] = " (sender='$sender' and recipient='$recipient') or (sender='$recipient' and recipient='$sender') ";
        }

        $list = M('easemob')->where($where)->order('ctime ASC')->select();
        foreach($list as $k=>$v){
            $list[$k]['time'] = date('Y/m/d H:i:s',$v['ctime']);
        }
        echo str_replace('\/', '/',json_encode($list,JSON_UNESCAPED_UNICODE));exit();
    }

}