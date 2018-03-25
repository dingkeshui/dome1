<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 商品管理
 */
class GoodsController extends AdminBasicController {
    public $Goods = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Goods = D('Goods');
    }

    /**商品列表*/
    public function goodsList(){
        $par = array();
        $request = array();
        if($_REQUEST['name']){
            $w['name'] = array('LIKE','%'.$_REQUEST['name'].'%');
            $par['name'] = $_REQUEST['name'];
            $request['name'] = $_REQUEST['name'];
            $this->assign("request",$request);
        }
        if($_REQUEST['sale_sort'] == 1){
            $order = "sales desc";
            $par['sale_sort'] = $_REQUEST['sale_sort'];
            $this->assign("sale_sort",$_REQUEST['sale_sort']);
            $par['sale_sort'] = $_REQUEST['sale_sort'];
        }elseif($_REQUEST['sale_sort'] == 2){
            $order = "sales asc";
            $par['sale_sort'] = $_REQUEST['sale_sort'];
            $this->assign("sale_sort",$_REQUEST['sale_sort']);
            $par['sale_sort'] = $_REQUEST['sale_sort'];
        }elseif ($_REQUEST['price_sort'] == 1){
            $order = "price desc";
            $par['price'] = $_REQUEST['price_sort'];
            $this->assign("price_sort",$_REQUEST['price_sort']);
            $par['price_sort'] = $_REQUEST['price_sort'];
        }elseif ($_REQUEST['price_sort'] == 2){
            $order = "price asc";
            $par['price'] = $_REQUEST['price_sort'];
            $this->assign("price_sort",$_REQUEST['price_sort']);
            $par['price_sort'] = $_REQUEST['price_sort'];
        }else{
            $order = "g_id desc";
        }
        if($_REQUEST['type']){
            $w['type'] = $_REQUEST['type']-1;
            $par['type'] = $_REQUEST['type'];
            $request['type'] = $_REQUEST['type'];
            $this->assign("type",$_REQUEST['type']);
        }
        if($_REQUEST['cate_id']){
            $w['cate_id'] = $_REQUEST['cate_id'];
            $par['cate_id'] = $_REQUEST['cate_id'];
            $request['cate_id'] = $_REQUEST['cate_id'];
            $this->assign("cate_id",$_REQUEST['cate_id']);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $par['start_time'] = I('request.start_time');
            $par['end_time'] = I('request.end_time');
        }
        $w['status'] = array('neq',9);
        $res = D("Goods")->selectGoods($w,$order,15,$par);
        foreach($res['list'] as $k=>$v){
            $res['list'][$k]['class_name'] = M("IntegralCategory")->where(['cate_id'=>$v['cate_id']])->getField('category');
        }
        $this->assign("list",$res['list']);
        $this->assign("page",$res['page']);

        /**获取分类*/
        $list_cate = M("IntegralCategory")->where(['status'=>0])->field("cate_id,category")->select();
        $this->assign("list_cate",$list_cate);

        //总的页数
        $pages = ceil(M('Goods')->where($w)->count()/15);
        $this->assign('pages',$pages);

        $this->display("goodsList");
    }

    /**商品添加*/
    public function addGoods(){
        if(!IS_POST){
            /**获取分类*/
            $list = M("IntegralCategory")->where(['status'=>0])->field("cate_id,category")->select();
            $this->assign("list",$list);
            $this->display("addGoods");
        }else{
            $data = D("Goods")->create();
            if($data){
                if(I("post.cover_pic")){
                    $data['cover_pic'] = I("post.cover_pic");
                }
                if(I("post.pic")){
                    $string = implode(",",I("post.pic"));
                    $data['pic'] = $string;
                }
                if (get_magic_quotes_gpc()) {
                    $data['desc'] = stripslashes($_POST['desc']);
                } else {
                    $data['desc'] = $_POST['desc'];
                }
                $res = D("Goods")->add($data);
                if($res){
                    $this->success('添加成功',U("Goods/goodsList"));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error(D("Goods")->getError());
            }
        }

    }

    /**商品修改*/
    public function editGoods(){
        if(!IS_POST){
            $res = D("Goods")->where(array('g_id'=>$_GET['g_id']))->find();
            /**商家的多个图片*/
            $list_img = explode(",",$res['pic']);
            $res['list_img'] = $list_img;
            $this->assign("res",$res);
            /**获取分类*/
            $list = M("IntegralCategory")->where(['status'=>0])->field("cate_id,category")->select();
            $this->assign("list",$list);
            $this->display("editGoods");
        }else{
            $data = D("Goods")->create();
            if($data){
                $w['g_id'] = I("post.g_id");
                if(I("post.cover_pic")){
                    $data['cover_pic'] = I("post.cover_pic");
                }
                if(I("post.pic")){
                    $string = implode(",",I("post.pic"));
                    $data['pic'] = $string;
                }
                if (get_magic_quotes_gpc()) {
                    $data['desc'] = stripslashes($_POST['desc']);
                } else {
                    $data['desc'] = $_POST['desc'];
                }
                $res = D("Goods")->where($w)->save($data);
                if($res){
                    $this->success('修改成功',U("Goods/goodsList"));
                }else{
                    $this->error('修改失败');
                }
            }else{
                $this->error(D("Goods")->getError());
            }
        }
    }

    /**ajax上传图片*/
    public function uploadPic(){
        $pic       = $_POST['pic'];
        $pic_name      = $_POST['pic_name'];
        $temp = explode('.',$pic_name);
        $ext = uniqid().'.'.end($temp);
        $base64    = substr(strstr($pic, ","), 1);
        $image_res = base64_decode($base64);
        $pic_link  = "Uploads/Goods/".date('Y-m-d').'/'.$ext;
        $saveRoot = "Uploads/Goods/".date('Y-m-d').'/';
        /**检查目录是否存在  循环创建目录*/
        if(!is_dir($saveRoot)){
            mkdir($saveRoot, 0777, true);
        }
        $res = file_put_contents($pic_link ,$image_res);
        if($res){
            $ajaxData = array("flag" => "success", "message"=>"上传成功！" );
            $result_data['path'] = $pic_link;
            $ajaxData['data'] = $result_data;
            $this->ajaxReturn(json_encode($ajaxData));
        }else{
            $ajaxData = array("flag" => "error", "message"=>"上传失败","data" => array());
            $this->ajaxReturn(json_encode($ajaxData));
        }
    }

    /**删除上传的乡相册的图片*/
    public function delPhoto(){
        /**判断删除的商品的图片type:1头像 type2商品展示图*/
        $w['g_id'] = $_POST['g_id'];
        $res = M("Goods")->where($w)->limit(1)->find();
        $pic = "";
        if($_POST['type'] == 1){
            $data['cover_pic'] = "";
            $pic = M("Goods")->where($w)->limit(1)->save($data);
        }elseif ($_POST['type'] == 2){
            $string1 = explode(",",$res['pic']);
            foreach ($string1 as $k=>$v){
                if($v == $_POST['file_path']){
                    unset($string1[$k]);
                }
            }
            if(!empty($string1)){
                $string_other = implode(',',$string1);
                $data['pic'] = $string_other;
                $pic = M("Goods")->where($w)->limit(1)->save($data);
            }else{
                $data['pic'] = "";
                $pic = M("Goods")->where($w)->limit(1)->save($data);
            }
        }
        $file  = $_POST['file_path'];
        $result = @unlink($file);
        if ($result == false && empty($pic)) {
            $this->ajaxReturn(0);
        } else {
            $this->ajaxReturn(1);
        }
    }


    /**
     * 删除操作
     */
    public function deleteGoods(){
        if(empty($_REQUEST['g_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['g_id'] = array('IN',I('request.g_id'));
        $data['status'] = 9;
        $upd_res = D("Goods")->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**修改商品的显示和不显示*/
    public function ajaxUp(){
        $data['is_show'] = $_POST['status'];
        $res = D("Goods")->where(array('g_id'=>$_POST['id']))->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }

    /**修改一些数据*/
    public function ajaxMore(){
        $w['g_id'] = $_POST['id'];
        $field =  $_POST['field'];
        $value = $_POST['value'];
//        $res = M()->query("Update zxty_goods set '$field' = $value ");
        $data[$field] = $value;
        $res = M("Goods")->where($w)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }

    }



    /**
     * 兑换商品的订单的管理
     * 传参方式 get
     * @author crazy  mashanshan修改，修改时间：2017-08-04
     */
    public function integralOrder(){
        $par = array();
        if($_POST['order_sn']){
            $w['order_sn'] = array('LIKE','%'.$_REQUEST['order_sn'].'%');
            $par['order_sn'] = $_REQUEST['order_sn'];
            $request['order_sn'] = $_REQUEST['order_sn'];
            $this->assign("request",$request);
        }
        if($_POST['name']){
            $w['name'] = array('LIKE','%'.$_REQUEST['name'].'%');
            $par['name'] = $_REQUEST['name'];
            $request['name'] = $_REQUEST['name'];
            $this->assign("request",$request);
        }
        if($_POST['tel']){
            $w['tel'] = array('LIKE','%'.$_REQUEST['tel'].'%');
            $par['tel'] = $_REQUEST['tel'];
            $request['tel'] = $_REQUEST['tel'];
            $this->assign("request",$request);
        }
        if($_POST['address']){
            $w['address'] = array('LIKE','%'.$_REQUEST['address'].'%');
            $par['address'] = $_REQUEST['address'];
            $request['address'] = $_REQUEST['address'];
            $this->assign("request",$request);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $par['start_time'] = I('request.start_time');
            $par['end_time'] = I('request.end_time');
        }
        if(!empty(I('request.status'))){
            $w['status'] = I('request.status')-1;
            $this->assign("status",I('request.status'));
            $par['status'] = I('request.status');
        }else{
            //$w['status'] = array('neq',9);
        }
        /**用户昵称查找(未选择用户类型默认显示用户的订单)*/
        if(!empty(I('request.nick_name'))){
            $rank_type = I('request.rank_type')?I('request.rank_type')-1:0;
            if($rank_type==0){
                $where['nick_name'] = array('LIKE','%'.I('request.nick_name').'%');
                $where['status'] = array('neq',9);
                $ids = M('member')->where($where)->getField('m_id',true);
            }else{
                $where['name'] = array('LIKE','%'.I('request.nick_name').'%');
                $where['status'] = array('neq',9);
                $ids = M('shop')->where($where)->getField('shop_id',true);
            }

            $w['mix_id'] = array('IN',$ids);
            $w['rank_type'] = $rank_type;
            $request['nick_name'] = I('request.nick_name');
            $par['nick_name'] = I('request.nick_name');
            $this->assign("request",$request);
        }


        /**筛选一下是豆订单和麦穗订单*/
        if(!empty(I('request.type'))){
            $w['type'] = I('request.type')-1;
            $this->assign("type",I('request.type'));
            $par['type'] = I('request.type');
        }
        /**筛选一下是商家还是用户*/
        if(!empty(I('request.rank_type'))){
            $w['rank_type'] = I('request.rank_type')-1;
            $this->assign("rank_type",I('request.rank_type'));
            $par['rank_type'] = I('request.rank_type');
        }
        S('integralorder_w',$w);
        $list = D("IntegralOrder")->selectOrder($w,"ctime desc",15,$par);
        foreach ($list['list'] as $k=>$v){
            $list['list'][$k]['goods'] = M("Goods")->where(array('g_id'=>$v['g_id']))->field("g_id,name")->find();
            if($v['rank_type'] == 1){
                $list['list'][$k]['mem_name'] = M("Shop")->where(array('shop_id'=>$v['mix_id']))->getField('name');
            }else{
                $list['list'][$k]['mem_name'] = M("Member")->where(array('m_id'=>$v['mix_id']))->getField('nick_name');
            }

        }
        $list_delivery = M("DeliveryCompany")->where(array('status'=>array('neq',9)))->order('sort asc')->select();
        $html = '';
        $html.="<select name='delivery_id' id='' style='width:340px;height:40px;margin:5px'>";
        $html.="<option value='0'>请选择物流公司...</option>";
        foreach($list_delivery as $kk=>$vv){
            $html.="<option value='{$vv['id']}'>{$vv['company_name']}</option>";
        }
        $html.="</select>";
        $this->assign("html",$html);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        //总的页数
        $pages = ceil(M('IntegralOrder')->where($w)->count()/15);
        $this->assign('pages',$pages);
        
        $this->display("integralOrder");
    }

    /**商品发货，发送短信通知用户*/
    public function postGoods(){
        $data['status'] = 2;
        $data['f_time'] = time();
        /**找到物流的信息*/
        $delivery_res = M("DeliveryCompany")->where(['id'=>I('post.delivery_id')])->find();
        $data['delivery_code'] = $delivery_res['delivery_code'];
        $data['delivery_company'] = $delivery_res['company_name'];
        $data['delivery_number'] = I('post.delivery_number');
        $res = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->save($data);
        if($res){
            $order = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->field('tel,mix_id,rank_type')->find();
            $body = $_POST['body'];
            $this->sendMsgGoods($order['tel'],$body);
            /**给下单的用户推送消息*/
            if($order['rank_type'] == 1){
                $is_set = M("Shop")->where(array('shop_id'=>$order['mix_id']))->limit(1)->getField('is_set');
                if($is_set == 1){
                    $this->push('',$order['mix_id'],'发货成功',$body,array('type'=>2),$order['rank_type']);
                }
            }else{
                $is_set = M("Member")->where(array('m_id'=>$order['mix_id']))->limit(1)->getField('is_set');
                if($is_set == 1){
                    $this->push('',$order['mix_id'],'发货成功',$body,array('type'=>2),$order['rank_type']);
                }
            }
            $this->success('操作成功！');
        }else{
            $this->error('操作失败！');
        }
    }

    /**查看订单的详情*/
    public function orderDetail(){
        $res = M("integral_order")->where(array('i_o_id'=>$_GET['id']))->find();
        $goods = M("Goods")->where(array('g_id'=>$res['g_id']))->find();
        $res['goods'] = $goods;
        $this->assign('res',$res);
        $this->display("orderDetail");
    }

    /**给用户退款一部分豆
     * time:2017-12-04
     * author:crazy
     */
    public function returnBean(){
        M()->startTrans();
        $order = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->find();
        $name = M('Goods')->where(array('g_id'=>$order['g_id']))->getField('name');
        $data['status'] = 5;
        $res = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->save($data);
        if($order){
            $order['rank_type']==1?$this->error('商家下单支付，订单无效！'):"";
            /**把用户用微信支付的钱数转换成豆*/
            /**如果是0就要返还用户的众享豆*/
            $wallet = M("Member")->where(array('m_id'=>$order['mix_id']))->limit(1)->getField('wallet');
            $data_member_wallet['wallet'] = floatval($wallet)+ floatval($order['price']);
            $member_wallet_int = M("Member")->where(array('m_id'=>$order['mix_id']))->limit(1)->save($data_member_wallet);
            /**给用户发送信息*/
            $member_message = $this->addMessage($order['mix_id'],"兑换商品".$name."已经退款了,请到钱包处提现！",$_POST['body'],0,$order['price'],0,0,1);
            /**用户添加账单明细*/
            $res_bill = $this->addBill($order['mix_id'],$order['mix_id'],"兑换商品退货","已经退款了,请到钱包处提现！",$order['price'],'0','0','4',$name,'6','0',$order['mix_id'],0,$order['price']);
            $tel = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->getField("tel");
            $body = $_POST['body'];
            if($member_wallet_int && $member_message && $res && $res_bill){
                $this->sendMsgReturnGoods($tel,$body);
                M()->commit();
                $this->success('操作成功！');
            }else{
                M()->rollback();
                $this->success('操作失败！');
            }
        }
    }

    /**
     * 确认商家或者用户退换货
     * sendMsgReturnGoods
     * 处理退货的订单，商家或者用户
     * 众享豆还是积分订单，如果是积分订单则要从新计算股份
     * time:2017-07-19
     * author:crazy
     */
    public function makeSureReturn(){
        $order = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->find();
        $name = M('Goods')->where(array('g_id'=>$order['g_id']))->getField('name');
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        if($order){
            $shop_wallet_inter = 0;
            $shop_message = 0;
            $pie_res = 0;
            $res_bill = 0;
            /**rank_type*/
            if($order['rank_type'] == 1){
                M()->startTrans();
                $data['status'] = 5;
                $res = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->save($data);
                $type = $order['type'];
                $price = $order['price'];
                $shop_id = $order['mix_id'];
                switch ($type){
                    case 0:
                        /**如果是0就要返还商家的众享豆*/
                        $wallet = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->getField('wallet');
                        $data_shop_wallet['wallet'] = floatval($wallet)+ floatval($price);
                        $shop_wallet_inter = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($data_shop_wallet);
                        /**给商家发送信息*/
                        $shop_message = $this->addMessage($shop_id,"兑换商品".$name."已经退款了！",$_POST['body'],1,$price,0,0,2);
                        /**商家添加账单明细*/
                        $res_bill = $this->addBill($shop_id,$shop_id,"兑换商品退货","兑换商品退货",$price,'0','0','4',$name,'6','1',$shop_id,1,$price);
                        $pie_res = 1;
                        break;
                    case 1:
                        /**如果是1就要返还商家的麦穗，然后从新计算股数*/
                        $integral = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->getField('integral');
                        $data_shop_wallet['integral'] = floatval($integral)+ floatval($price);
                        $shop_wallet_inter = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($data_shop_wallet);
                        /**给商家发送信息*/
                        $shop_message = $this->addMessage($shop_id,date("Y-m-d-H:i:s",$order['ctime'])."兑换商品".$name."已经退款了，请注意查收！",$_POST['body'],1,
                            $price,0,0,2);
                        /**计算有多少股*/
                        $num = floatval($data_shop_wallet['integral'])/$meet_pay_price;
                        /**获取用户数据表里面存的股数*/
                        $b = M("Pie")->where(array('mix_id'=>$shop_id,'type'=>1))->count();
                        if(floor($num)>$b){
                            $c = floor($num)-$b;
                            for ($i=1;$i<=$c;$i++){
                                $pie_data['mix_id'] = $shop_id;
                                $pie_data['pie'] = 1;
                                $pie_data['type'] = 1;
                                $pie_data['ctime'] = time();
                                M("Pie")->add($pie_data);
                            }
                            $data_shop['piles'] = floor($num);
                            $data_shop['utime'] = time()+1;
                            $pie_res = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($data_shop);
                        }else{
                            $data_shop['piles'] = $b;
                            $data_shop['utime'] = time()+1;
                            $pie_res = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($data_shop);
                        }
                        $res_bill = 1;
                        break;
                }
                $tel = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->getField("tel");
                $body = $_POST['body'];
                if($shop_wallet_inter && $shop_message && $pie_res && $res && $res_bill){
                    $this->sendMsgReturnGoods($tel,$body);
                    M()->commit();
                    $this->success('操作成功！');
                }else{
                    M()->rollback();
                    $this->success('操作失败！');
                }
            }else{
                M()->startTrans();
                $data['status'] = 5;
                $res = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->save($data);
                $member_wallet_int = 0;
                $member_message = 0;
                $pie_res = 0;
                $res_bill = 0;
                $type = $order['type'];
                $price = $order['price'];
                $m_id = $order['mix_id'];
                switch ($type){
                    case 0:
                        /**如果是0就要返还用户的众享豆*/
                        $wallet = M("Member")->where(array('m_id'=>$m_id))->limit(1)->getField('wallet');
                        $data_member_wallet['wallet'] = floatval($wallet)+ floatval($price);
                        $member_wallet_int = M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($data_member_wallet);
                        /**给用户发送信息*/
                        $member_message = $this->addMessage($m_id,"兑换商品".$name."已经退款了！",$_POST['body'],0,$price,0,0,1);
                        /**用户添加账单明细*/
                        $res_bill = $this->addBill($m_id,$m_id,"兑换商品退货","兑换商品退货",$price,'0','0','4',$name,'6','0',$m_id,0,$price);
                        $pie_res = 1;
                        break;
                    case 1:
                        /**如果是1就要返还用户的麦穗，然后从新计算股数*/
                        $integral = M("Member")->where(array('m_id'=>$m_id))->limit(1)->getField('integral');
                        $data_member_wallet['integral'] = floatval($integral)+ floatval($price);
                        $member_wallet_int =M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($data_member_wallet);
                        /**给用户发送信息*/
                        $member_message =$this->addMessage($m_id,"兑换商品".$name."已经退款了！",$_POST['body'],0,$price,0,0,1);
                        /**计算有多少股*/
                        $num = floatval($data_member_wallet['integral'])/$meet_pay_price;
                        /**获取用户数据表里面存的股数*/
                        $b = M("Pie")->where(array('mix_id'=>$m_id,'type'=>0))->count();
                        if(floor($num)>$b){
                            $c = floor($num)-$b;
                            for ($i=1;$i<=$c;$i++){
                                $pie_data['mix_id'] = $m_id;
                                $pie_data['pie'] = 1;
                                $pie_data['type'] = 0;
                                $pie_data['ctime'] = time();
                                M("Pie")->add($pie_data);
                            }
                            $data_member['piles'] = floor($num);
                            $data_member['utime'] = time()+1;
                            $pie_res = M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($data_member);
                        }else{
                            $data_member['piles'] = $b;
                            $data_member['utime'] = time()+1;
                            $pie_res = M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($data_member);
                        }
                        $res_bill = 1;
                        break;
                }
                $tel = M("integral_order")->where(array('i_o_id'=>$_POST['id']))->getField("tel");
                $body = $_POST['body'];
                if($member_wallet_int && $member_message && $pie_res&&$res&&$res_bill){
                    $this->sendMsgReturnGoods($tel,$body);
                    M()->commit();
                    $this->success('操作成功！');
                }else{
                    M()->rollback();
                    $this->success('操作失败！');
                }
            }
        }else{
            $this->error('操作失败！');
        }
    }


    /**
     * 删除操作
     */
    public function deleteOrder(){
        if(empty($_REQUEST['i_o_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['i_o_id'] = array('IN',I('request.i_o_id'));
        $data['status'] = 9;
        $upd_res = D("IntegralOrder")->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }


    /**导出积分订单excel的表单*/
    public function integralOrderXLS(){
        
        /**获取Excel导出数据*/
        $where = S('integralorder_w');
        $list = M('IntegralOrder')->where($where)->select();
        $arrordername = array('订单号','下单时间','订单类型','麦穗/豆','商品名称','商家/用户','商家/用户名称','收货人姓名','收货人电话','收货地址','订单状态');
        foreach($list as $k=>$v){
            switch ($v['status']){
                case 0:
                    $source="未付款";
                    break;
                case 1:
                    $source="支付成功(待发货)";
                    break;
                case 2:
                    $source="发货成功";
                    break;
                case 3:
                    $source="用户确认收货";
                    break;
                case 4:
                    $source="申请退货";
                    break;
                case 5:
                    $source="退货成功";
                    break;
                case 9:
                    $source="用户删除订单";
                    break;
            }
            $v['goods'] = M("Goods")->where(array('g_id'=>$v['g_id']))->getField("name");
            if($v['rank_type'] == 1){
                $rank_type = '商家';
                $v['mem_name'] = M("Shop")->where(array('shop_id'=>$v['mix_id']))->getField('name');
            }else{
                $rank_type = '用户';
                $v['mem_name'] = M("Member")->where(array('m_id'=>$v['mix_id']))->getField('nick_name');
            }
            if($v['type']==1){
                $type = '麦穗';
            }else{
                $type = '豆';
            }
            $arrorderlist[] = array($v['order_sn'],' '.date('Y-m-d H:i:s',$v['ctime']),$type,$v['price'],$v['goods'],$rank_type,$v['mem_name'],$v['name'],$v['tel'],$v['address'],$source);
        }
        exportexcel($arrorderlist,$arrordername,'积分订单信息');

    }



}