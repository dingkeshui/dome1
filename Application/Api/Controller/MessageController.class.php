<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class MessageController
 * @package Api\Controller
 * 消息模块
 */
class MessageController extends ApiBasicOtherController{

    public $Message = '';
    public function _initialize(){
        parent::_initialize();
        $this->Message = M('Message');
    }

    /**
     * 消息首页（展示所有的信息列表）
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户或者商家的id
     * @param type 0是用户 1是商家
     */
    public function messageList(){
        $m_id = $_GET['m_id'];
        $w['m_id'] = array('in',"0,$m_id");
        $w['status'] = array('neq',9);
        if($_GET['type'] == 1){
            $w['id_type'] = 1;
            if($_GET['is_order'] == 1){
                $w['type'] = ['in','0,2'];
            }else{
                $w['type'] = 1;
            }
        }else{
            $w['id_type'] = 0;
            if($_GET['is_order'] == 1){
                $w['type'] = ['in','0,2'];
            }else{
                $w['type'] = 1;
            }
        }
        $p = ($_GET['p']-1)*15;
        if($_GET['is_order'] == 1){
            $time = M('MessageDelrecord')->where(array('m_id'=>$m_id,'type'=>$_GET['type'],'msg_type'=>0))->limit(1)->getField('del_time');
            $time = !empty($time)?$time:0;
        }else{
            $time = M('MessageDelrecord')->where(array('m_id'=>$m_id,'type'=>$_GET['type'],'msg_type'=>1))->limit(1)->getField('del_time');
            $time = !empty($time)?$time:0;
        }
        $w['ctime'] = array('EGT',$time);
        $list = M("Message")->where($w)->order("status asc,ctime desc")->limit($p,15)->select();
        foreach ($list as $kk=>$vv){
            $name = "";
            $account = "";
            $list[$kk]['content'] = mb_substr(filterHtml($vv['content']), 0, 40, 'utf-8');
            $list[$kk]['time'] = date('H:i:s',$vv['ctime']);
            $list[$kk]['year_time'] = date('Y-m-d',$vv['ctime']);
            /**查看用户或者商家的信息0的话就是应该找商家，如果是1的话就应该找用户的*/
//            $list[$kk]['pay_name'] = $vv['title'];
//            $list[$kk]['pay_account'] = mb_substr(filterHtml($vv['content']), 0, 12, 'utf-8');
            $list[$kk]['pay_name'] = "众享通赢";
            $list[$kk]['pay_account'] = "暂未绑定";
            if($vv['id_type'] == 0&&$vv['order_mix_id']!=0&&$vv['z_type'] == 0){
                $detail_res = M("Shop")->where(['shop_id'=>['in',$vv['order_mix_id']]])->field("name,account")->select();
                foreach($detail_res as $k=>$v){
                    $name.= $v['name'].';';
                    $account.= $v['account'].";";
                }
                $list[$kk]['pay_name'] = $name;
                $list[$kk]['pay_account'] = $account;
            }elseif($vv['id_type'] == 0&&$vv['order_mix_id']!=0&&$vv['z_type'] == 1){
                /**针对转账单独标识用户*/
                $detail_res = M("Member")->where(['m_id'=>$vv['order_mix_id']])->field('nick_name,account')->find();
                $list[$kk]['pay_name'] = $detail_res['nick_name'];
                $list[$kk]['pay_account'] = $detail_res['account']?$detail_res['account']:"暂未绑定";
            }elseif($vv['id_type'] == 1&&$vv['order_mix_id']!=0){
                $detail_res = M("Member")->where(['m_id'=>$vv['order_mix_id']])->field('nick_name,account')->find();
                $list[$kk]['pay_name'] = $detail_res['nick_name'];
                $list[$kk]['pay_account'] = $detail_res['account']?$detail_res['account']:"暂未绑定";
            }
            /**如果是系统消息，查看当前消息是否已经被读取了*/
            if($vv['type'] == 1){
                $read_res = M("Read")->where(array('mess_id'=>$vv['mess_id'],'id_type'=>$vv['id_type'],'m_id'=>$_GET['m_id']))->getField("r_id");
                if($read_res){
                    $list[$kk]['status'] = 1;
                }else{
                    $list[$kk]['status'] = 0;
                }
            }
        }
        if(empty($list) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$list);
        }elseif ($list){
            apiResponse("success","获取成功！",$list);
        }elseif(empty($list)){
            apiResponse("success","无数据！");
        }else{
            apiResponse("error","获取失败！");
        }
    }




    /**
     *  系统信息列表
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param mess_id 消息的id
     */
    public function messageDetail(){
        $w['mess_id'] = $_GET['mess_id'];
        $type = M("Message")->where($w)->field("type,id_type")->find();
        if($type['type'] == 1){
            $data['mess_id'] = $_GET['mess_id'];
            $data['m_id'] = $_GET['m_id'];
            $data['id_type'] = $type['id_type'];
            $is_set = M("Read")->where($data)->getField("r_id");
            if(empty($is_set)){
                $data['ctime'] = time();
                M("Read")->add($data);
            }
            $res = M("Message")->where($w)->limit(1)->find();
            preg_match_all('/src=\"\/?(.*?)\"/',$res['content'],$match);
            foreach($match[1] as $key => $src){
                if(!strpos($src,'://')){
                    $res['content'] = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=60%",$res['content']);
                }
            }
            $res['publish_time'] = date("Y-m-d H:i:s",$res['ctime']);
            if($res){
                apiResponse("success","获取成功！",$res);
            }else{
                apiResponse("error","获取失败！");
            }
        }else{
            $data['status'] = 1;
            $data['utime'] = time();
            M("Message")->where($w)->limit(1)->save($data);
            $res = M("Message")->where($w)->limit(1)->find();
            preg_match_all('/src=\"\/?(.*?)\"/',$res['content'],$match);
            foreach($match[1] as $key => $src){
                if(!strpos($src,'://')){
                    $res['content'] = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=60%",$res['content']);
                }
            }
            $res['publish_time'] = date("Y-m-d H:i:s",$res['ctime']);
            if($res){
                apiResponse("success","获取成功！",$res);
            }else{
                apiResponse("error","获取失败！");
            }

        }
    }


    /**
     * 判断用户是否有未读消息
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function isRead(){
        $this->isHaveMsg($_GET['m_id'],0);
    }

    /**
     * 判断商家是否有未读消息
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function isReadShop(){
        $this->isHaveMsg($_GET['shop_id'],1);
    }

    /**
     * 将用户消息全部清空
     * 传参方式 post
     * @time 2017-09-24
     * @author mss
     * @param m_id 用户的id
     * @param type 用户类型，0用户，1商家
     */
    public function delMessage(){
        if(empty($_POST['m_id'])){
            apiResponse("error","参数错误");
        }
        if($_POST['is_readonly']==1){
            apiResponse('error','无操作权限');
        }
        $m_id = $_POST['m_id'];
        $type = !empty($_POST['type'])?$_POST['type']:0;
        $where['type'] = $type;
        $where['msg_type'] = $_POST['msg_type'];
        $where['m_id'] = $_POST['m_id'];
        $record = M('MessageDelrecord')->where($where)->find();
        $data['del_time'] = time();
        if($record){
            $data['msg_type'] = $_POST['msg_type'];
            $rs = M('MessageDelrecord')->where($where)->limit(1)->save($data);
        }else{
            $data['m_id'] = $m_id;
            $data['type'] = $type;
            $data['msg_type'] = $_POST['msg_type'];
            $rs = M('MessageDelrecord')->add($data);
        }
        if($rs){
            apiResponse('success','清空成功');
        }else{
            apiResponse('error','清空失败');
        }
    }


    /**信息首页
     * @author crazy
     * @time 2017-12-22
     */
    public function messIndexShow(){
        $res = $this->messageIndex($_POST['mix_id'],$_POST['type']);
        apiResponse('success','获取成功',$res);
    }


}