<?php
namespace Api\Controller;
use Think\Controller;
/**商城的订单*/
class StoreOrderController extends ApiBasicController
{
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 订单的详情
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param order_id 订单的id
     */
    public function orderDetail(){
        if(empty($_POST['order_id'])){
            apiResponse('error','参数错误');
        }
        $data = array();
        $w['p_o_id'] = $_POST['order_id'];
        $order = M('ProductOrder')->where($w)->find();
        $product = json_decode($order['goods_gather'],true);
        $pro =  array();
        $total_goods_price = 0;
        foreach($product as $k=>$v){
            $pro[$k] = M('Product')->where(array('p_id'=>$v['p_id']))->field('p_id,title,cover_pic')->find();
            $pro[$k]['cover_pic'] = $this->returnPic($pro[$k]['cover_pic']);
            $pro[$k]['attr'] = $v['attr'];
            $pro[$k]['price'] = $v['price'];
            $pro[$k]['num'] = $v['num'];
            $total_goods_price+=$v['price']*$v['num'];
        }
        $data['total_goods_price'] = $total_goods_price;
        $data['cou_money'] = $order['cou_money'];
        $data['postage_price'] = $order['postage_price'];
        $data['order_id'] = $order['p_o_id'];
        $data['order_sn'] = $order['order_sn'];
        $data['total_price'] = $order['total_price'];
        $data['real_price'] = $order['real_price'];
        $data['addr_name'] = $order['addr_name'];
        $data['addr_tel'] = $order['addr_tel'];
        $data['addr_address'] = $order['addr_address'];
        $data['status'] = $order['status'];
        $data['remark'] = $order['remark'];
        $data['delivery_number'] = $order['delivery_number']?$order['delivery_number']:"暂无";
        /**买家的昵称*/
        $data['buy_name'] = M("Member")->where(array('m_id'=>$order['m_id']))->getField('nick_name');
        $data['ctime'] = date('Y-m-d H:i:s',$order['ctime']);
        if($order['affirm_time']>0){
            $data['affirm_time'] = date('Y-m-d H:i:s',$order['affirm_time']);
        }else{
            $data['affirm_time'] = '';
        }
        if($order['pay_time']>0){
            $data['pay_time'] = date('Y-m-d H:i:s',$order['pay_time']);
        }else{
            $data['pay_time'] = '';
        }
        if($order['send_time']>0){
            $data['send_time'] = date('Y-m-d H:i:s',$order['send_time']);
            /**计算还有多久要自动的确认收货*/
            $date_sure_time = timeDiff(time(),($order['send_time']+604800));
            if(($order['send_time']+604800)<time()){
                $data['make_sure_time'] = "";
            }else{
                $data['make_sure_time'] = $date_sure_time['day'].'天'.$date_sure_time['hour'].'小时'.$date_sure_time['min'].'分';
            }
        }else{
            $data['send_time'] = '';
            $data['make_sure_time'] = 0;
        }
        $data['product'] = $pro?$pro:[];
        apiResponse('success','成功',$data);
    }


    /**商城的订单相关
     * @author crazy
     * @time 2017-12-18
     * 传参方式 post
     * @param shop_id 商家的id
     * @param p 页数
     * @param status 订单状态，1待付款，2待发货，3待收货，4待评价
     * 0:待支付  1：已经支付未发货  2：已经发货 3：已收货待评价 4：订单完成 5：申请退款 6:退款中  7：驳回  8：退款成功 ,9：取消订单
     * @param title 模糊搜索的内容，订单号或收货人姓名或收货人电话
     */
    public function orderList(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $status = $_POST['status'];
        switch($status){
            case 1:
                $where['status'] = 1;
                break;
            case 2:
                $where['status'] = 2;
                break;
            case 3:
                $where['status'] = 4;
                break;
            case 4:
                $where['status'] = ['egt',0];
                break;
        }
        if(!empty($_POST['title'])){
            $where['order_sn|addr_name|addr_tel'] = array('LIKE','%'.$_POST['title'].'%');
        }
        $where['shop_id'] = $shop_id;
        $order = M('ProductOrder')->where($where)->field("p_o_id,shop_id,order_sn,goods_gather,total_price,
        postage_price,status,FROM_UNIXTIME(ctime,'%Y-%m-%d %H:%i:%s') as ctime,delivery_number,pay_time,send_time,affirm_time")->limit($page,15)->order('ctime DESC')->select();
        //file_put_contents("bate5.1.txt",M('ProductOrder')->getLastSql());
        $data = [];
        foreach($order as $key=>$val){
            $total_num = 0;
            $data[$key]['order_sn'] = $val['order_sn'];
            $data[$key]['ctime'] = $val['ctime'];
            $data[$key]['shop_id'] = $val['shop_id'];
            $data[$key]['order_id'] = $val['p_o_id'];
            $data[$key]['shop_name'] = M('Shop')->where(array('shop_id'=>$val['shop_id']))->limit(1)->getField('name');
            $product = json_decode($val['goods_gather'],true);
            $pro = array();
            foreach($product as $keys=>$vals){
//                $concat = C('API_URL') .'/';
                $product = M('Product')->where(array('p_id'=>$vals['p_id']))->field("p_id,title,cover_pic")->find();
                $pro[$keys]['cover_pic'] = $this->returnPic($product['cover_pic']);
                $pro[$keys]['title'] = $product['title'];
                $pro[$keys]['cover_pic'] = $product['cover_pic'];
                $pro[$keys]['attr'] = $vals['attr'];
                $pro[$keys]['num'] = $vals['num'];
                $total_num+= $vals['num'];
                $pro[$keys]['price'] = $vals['price'];
            }
            $data[$key]['product'] = $pro?$pro:[];
            $data[$key]['postage'] = $val['postage_price'];
            $data[$key]['total'] = $val['total_price'];
            $data[$key]['status'] = $val['status'];
            $data[$key]['delivery_number'] = $val['delivery_number']?$val['delivery_number']:"暂无";
            $data[$key]['total_num'] = $total_num;
            $data[$key]['pay_time'] = $val['pay_time']?date("Y-m-d H:i:s",$val['pay_time']):"暂未付款";
            $data[$key]['send_time'] = $val['send_time']?date("Y-m-d H:i:s",$val['send_time']):"暂未发货";
            $data[$key]['affirm_time'] = $val['affirm_time']?date("Y-m-d H:i:s",$val['affirm_time']):"暂无";
        }
        $return_res = $this->getListCodeMessage($data,$p);
        apiResponse($return_res['code'],$return_res['message'],$return_res['list']);

    }



    /**物流
     * @author crazy
     * @time 2017-12-19
     */
    public function deliveryCompanyList(){
        /**判断这个订单是否有未处理的售后申请*/
        $return = [];
        $order_id = I('post.order_id');
        $is_set = M("ReturnOrder")->where(['order_id'=>$order_id,'status'=>['in','0,1']])->getField("id");
        if($is_set){
            $return['is_send'] = 0;
        }else{
            $return['is_send'] = 1;
        }
        $list = M("DeliveryCompany")->where(array('status'=>array('neq',9)))->order('sort asc')->select();
        $return['list'] = $list;
        apiResponse('success','获取成功',$return);
    }

    /**
     * 修改订单的物流的信息
     * @author crazy
     * @time 2017-12-19
     * post
     * @param order_id 订单的id
     * delivery_code 物流的编号
     * delivery_company 物流公司
     * delivery_number 物流的单号
    */
    public function editOrderDeliveryMess(){
        $order_id = I("post.order_id");
        $data = array(
            'delivery_id'=>I("post.delivery_id"),
            'delivery_code'=>I("post.delivery_code"),
            'company_name'=>I("post.delivery_company"),
            'delivery_number'=>I("post.delivery_number")
        );
        $data['status'] = 2;
        $data['send_time'] = time();
        $res = M("ProductOrder")->where(array('p_o_id'=>$order_id))->limit(1)->save($data);
        if($res){
            $goods_name = "";
            /**给用户发送推送消息和发送服务文本消息*/
            $order_res = M("ProductOrder")->where(array('p_o_id'=>$order_id))->field('m_id,shop_id,order_sn,real_price,pay_type,goods_gather')->find();
            /**找到用户*/
            $member = M("Member")->where(['m_id'=>$order_res['m_id']])->field('is_set')->find();
            $goods_list = json_decode($order_res['goods_gather'],true);
            foreach($goods_list as $k=>$v){
                $p_name = M("Product")->where(['p_id'=>$v['p_id']])->getField("title");
                $goods_name .= $p_name.";";
            }
            $title = "发货通知";
            $content = "订单号为：{$order_res['order_sn']}，购买的商品：{$goods_name}，商家已经使用{$data['company_name']}物流，
            物流编号为：{$data['delivery_number']}为您发货了！";
            $message_id = $this->addMessage($order_res['m_id'],$title,$content,0,$order_res['real_price'],$order_res['shop_id'],0,$order_res['pay_type']);
            try{
                if($member['is_set'] == 1){
                    /**给APP用户端推送消息*/
                    $alias = ''.$order_res['m_id'];
                    $alert = $content;
                    $extra['order_id'] = ''.$order_id;
                    $extra['mess_id'] = $message_id;
                    $extra['type'] = '0';
                    $this->push("",$alias,$title,$alert,$extra,0);
                }
            }catch (\Exception $e){
                apiResponse("success","填写成功");
            }
            apiResponse("success","填写成功");
        }else{
            apiResponse("success","填写失败");
        }
    }

    /**订单的客户管理
     * @author crazy
     * @time 2017-12-19
     * post
     * p 分页
     * shop_id 商家的id
     */
    public function memberList(){
        $shop_id = I("post.shop_id");
        $p = I('post.p')?(I('post.p')-1)*15:0;
        $sql = "select distinct zxty_product_order.m_id,zxty_member.head_pic,zxty_member.nick_name,zxty_member.m_id from zxty_member,zxty_product_order where
        zxty_product_order.m_id = zxty_member.m_id and zxty_product_order.shop_id = $shop_id order by zxty_product_order.ctime desc limit $p,15";
        $list = M()->query($sql);
        foreach($list as $k=>$v){
            $list[$k]['head_pic'] = $this->returnPic($v['head_pic']);
        }
        $arr = $this->getListCodeMessage($list,$p);
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }


    /**商家端的订单消费
     * @author crazy
     * @time 2017-12-21
     * post
     * p 分页
     * shop_id 商家的id
     * status 1已消费  2已评价  3退款 默认全部
     */
    public function orderEarnings(){
        $w = [];
        if(I('post.month')){
            $get_start_time = I('post.month').'-01'.' '.'00:00:00';
            $get_end_time = I('post.month').'-31'.' '.'23:59:59';
            $t1 = strtotime($get_start_time);
            $t2 = strtotime($get_end_time);
            $w['ctime'] = array(array('EGT',$t1),array('ELT',$t2),'and');
        }
        if(!empty($_POST['title'])){
            $w['name'] = array('LIKE','%'.$_POST['title'].'%');
//            $m_id = M("Member")->where($w_m)->getField('m_id',true);
//            $w['m_id'] = ['in',$m_id];
        }
        $shop_id = I('post.shop_id');
        $p = I('post.p')?(I('post.p')-1)*15:0;
        $status = I('post.status');
        $w['rank_type'] = 1;
        $w['type'] = 3;
        switch($status){
            case 1:
                $w['status'] = 0;
                $w['m_id'] = $shop_id;
                $list = M("Bill")->where($w)->order("ctime desc")->field("FROM_UNIXTIME(ctime,'%m-%d') as month_time,
                FROM_UNIXTIME(ctime,'%H:%i') as day_time,b_id as order_id,price,other_price,total_price,status,m_id,m_id as shop_id,accept_m_id,type,pay_type,monitor,
                is_appraise")->limit($p,15)->select();
                break;
            case 2:
                $w['is_appraise'] = 1;
                $w['m_id'] = $shop_id;
                $list = M("Bill")->where($w)->order("ctime desc")->field("FROM_UNIXTIME(ctime,'%m-%d') as month_time,
                FROM_UNIXTIME(ctime,'%H:%i') as day_time,b_id as order_id,price,other_price,total_price,status,m_id,m_id as shop_id,accept_m_id,type,pay_type,
                monitor,is_appraise")->limit($p,15)->select();
                break;
            case 3:
                $w['status'] = ['in','2,3'];
                $w['m_id'] = $shop_id;
                $list = M("Bill")->where($w)->order("ctime desc")->field("FROM_UNIXTIME(ctime,'%m-%d') as month_time,
                FROM_UNIXTIME(ctime,'%H:%i') as day_time,b_id as order_id,price,other_price,total_price,status,m_id,m_id as shop_id,accept_m_id,type,pay_type,
                monitor,is_appraise")->limit($p,15)->select();
                break;
            default:
                $w['m_id'] = $shop_id;
                $list = M("Bill")->where($w)->order("ctime desc")->field("FROM_UNIXTIME(ctime,'%m-%d') as month_time,
                FROM_UNIXTIME(ctime,'%H:%i') as day_time,b_id as order_id,price,other_price,total_price,status,m_id,m_id as shop_id,accept_m_id,type,pay_type,
                monitor,is_appraise")->limit($p,15)->select();
        }
        foreach($list as $k=>$v){
            $mem_res = M("Member")->where(['m_id'=>$v['accept_m_id']])->field("m_id,nick_name,head_pic")->find();
            $list[$k]['nick_name'] = $mem_res['nick_name'];
            $list[$k]['m_id'] = $mem_res['m_id'];
            $list[$k]['head_pic'] = $this->returnPic($mem_res['head_pic']);
        }
        $arr = $this->getListCodeMessage($list,I('post.p'));
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }


    /**查看评价，从买单订单那里进去查看评价
     * @author crazy
     * @time 2017-12-22
     * @param order_id 订单的id
     */
    public function orderAppraiseList(){
        /**传的是商家的账单的明细id，要找到用户的账单的明细的id*/
        $order_id = $_POST['order_id'];
        $other_b_id = M("Bill")->where(['b_id'=>$order_id])->getField('other_b_id');
        $w['bill_id'] = $other_b_id;
        $w['status'] = ['neq',9];
        $list = M('Appraise')->where($w)->field('m_id,bill_id,star,shop_id,content,pic,ctime,status')->order('ctime DESC')->select();
        $item = array();
        $pics = array();
        $member = M('Member')->where(array('m_id'=>$list[0]['m_id']))->field('nick_name,head_pic')->find();
        $item_mem['nick_name'] = $member['nick_name'];
        $item_mem['head_pic'] = $this->returnPic($member['head_pic']);
        $item['mem_res'] = $item_mem;
        $appraise_list = [];
        foreach($list as $k=>$v){
            if($v['pic']){
                $pics = explode(',',$v['pic']);
                foreach($pics as $key=>$val){
                    $pics[$key] = C('API_URL').'/'.$val;
                }
            }
            $item_app['appraise_id'] = $v['p_a_id'];
            /**商品相关*/
//            $concat = C("API_URL")."/";
            /**商家的数据*/
            $member_res = M("Member")->where(['m_id'=>$v['m_id']])->field("nick_name,head_pic")->find();
            $item_app['goods_name'] = $member_res['nick_name']?$member_res['nick_name']:"";
            $item_app['goods_cover_pic'] = $this->returnPic($member_res['head_pic']);
            $item_app['star'] = $v['star'];
            $item_app['content'] = $v['content'];
            $item_app['pics'] = $pics;
            $item_app['ctime'] = date('Y-m-d',$v['ctime']);
            $appraise_list [] = $item_app;
        }
        $item['appraise_list'] = $appraise_list;

        apiResponse('success','成功',$item);
    }


    /**查看订单的评价
     * @author crazy
     * @time 2018-01-11
     * @param order_id 订单的id
     */
    public function appraiseOrderList(){
        $order_id = $_POST['order_id'];
        $w['p_o_id'] = $order_id;
        $w['status'] = ['neq',9];
        $list = M('ProductAppraise')->where($w)->field('p_a_id,p_id,m_id,star,shop_id,content,pics,ctime,status')->order('ctime DESC')->select();
//        dump(M('ProductAppraise')->getLastSql());
        $item = array();
        $member = M('Member')->where(array('m_id'=>$list[0]['m_id']))->field('nick_name,head_pic')->find();
        $item_mem['nick_name'] = $member['nick_name'];
        $item_mem['head_pic'] = $this->returnPic($member['head_pic']);
        $item['mem_res'] = $item_mem;
        $appraise_list = [];
        foreach($list as $k=>$v){
            $pics = array();
            if($v['pics']){
                $pics = explode(',',$v['pics']);
                foreach($pics as $key=>$val){
                    $pics[$key] = C('API_URL').'/'.$val;
                }
            }
            $item_app['appraise_id'] = $v['p_a_id'];
            /**商品相关*/
            $concat = C("API_URL")."/";
            /**用户的数据*/
            $goods_res = M("Product")->where(['p_id'=>$v['p_id']])->field("title,CONCAT('$concat',cover_pic) as cover_pic")->find();
            $item_app['goods_name'] = $goods_res['title']?$goods_res['title']:"";
            $item_app['cover_pic'] = $this->returnPic($goods_res['cover_pic']);
//            $item_app['cover_pic'] = $goods_res['cover_pic']?$goods_res['cover_pic']:"";
            $item_app['star'] = $v['star'];
            $item_app['content'] = $v['content'];
            $item_app['pics'] = $pics;
            $item_app['ctime'] = date('Y-m-d',$v['ctime']);
            $appraise_list [] = $item_app;
        }
        $item['appraise_list'] = $appraise_list;

        apiResponse('success','成功',$item);
    }





    /**商家端商城订单数量显示
     * @author crazy
     * @time 2018-01-24
     * @param shop_id 商家的id
     * 0:待支付  1：已经支付未发货  2：已经发货 3：已收货待评价 4：订单完成 5：申请退款 6:退款中  7：驳回  8：退款成功 ,9：取消订单
     */
    public function orderCount(){
        $shop_id = $_POST['shop_id'];
        /**待发货*/
        $res['wait_send_order'] = M("ProductOrder")->where(['status'=>1,'shop_id'=>$shop_id])->count()?M("ProductOrder")->where(['status'=>1,'shop_id'=>$shop_id])->count():"0";
        /**待收货*/
        $res['wait_query_order'] = M("ProductOrder")->where(['status'=>2,'shop_id'=>$shop_id])->count()?M("ProductOrder")->where(['status'=>2,'shop_id'=>$shop_id])->count():"0";
        /**已完成*/
        $res['wait_success_order'] = M("ProductOrder")->where(['status'=>4,'shop_id'=>$shop_id])->count()?M("ProductOrder")->where(['status'=>4,'shop_id'=>$shop_id])->count():"0";
        /**退款售后*/
        $res['wait_tk_order'] = M("ReturnOrder")->where(['status'=>0,'shop_id'=>$shop_id])->count()?M("ReturnOrder")->where(['status'=>0,'shop_id'=>$shop_id])->count():"0";
        if($res){
            apiResponse("success","获取成功！",$res);
        }else{
            apiResponse("error","获取失败！");
        }
    }

}
