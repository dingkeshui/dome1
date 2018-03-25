<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class MemberLoginController
 * @package Api\Controller
 * 商品的类
 */
class GoodsController extends ApiBasicController{
    public $Goods = '';
    public function _initialize(){
        parent::_initialize();
        $this->Goods = D('Goods');
    }
    public function index(){
        $this->display('index');
    }

    /**
     * 商品的列表
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param type 1是积分商品  0是豆商品
     */
    public function goodsList(){
        if($_GET['cate_id']){
            $w['cate_id'] = $_GET['cate_id'];
        }
        if($_GET['title']){
            $w['name'] = ['LIKE','%'.$_GET['title'].'%'];
        }
        $sort = $_GET['sort'];
        switch($sort){
            case 1:
                $order = "sales desc,sort asc,ctime desc";
                break;
            case 2:
                $order = "sales desc";
                break;
            case 3:
                $order = "price asc";
                break;
            default:
                $order = "sort desc,ctime desc";
        }
        $w['type'] = 1;
        $w['status'] = array('not in','1,9');
        $w['is_show'] = 1;
        $p = ($_GET['p'] - 1)*15;
        $list = M("Goods")->where($w)->limit($p,15)->field("g_id,name,cover_pic,unit,price,freight,sales,type,is_send")->order($order)->select();
        foreach($list as $k=>$v){
            $list[$k]['cover_pic'] = $this->returnPic($v['cover_pic']);
        }
        $arr = $this->getListCodeMessage($list,$p);
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }


    /**麦穗的首页
     * @author crazy
     * @time 2017-012-20
     */
    public function goodIndex(){
        $data = [];
        $data['cate_list'] = $this->integralCateList();
        /**获取商品*/
        $list_arr = [];
        foreach($data['cate_list'] as $k=>$v){
            $cate_name = M("integral_category")->where(['cate_id'=>$v['cate_id']])->getField('category');
            $list_arr[$k]['cate_name'] = $cate_name;
            /**找到商品*/
//            $concat = C("API_URL").'/';
            $list = M("Goods")->where(['cate_id'=>$v['cate_id']])->field("g_id,name,cover_pic,price")
                ->order('is_show desc,sales desc')->limit(2)->select();
            foreach($list as $kk=>$vv){
                $list[$kk]['cover_pic'] = $this->returnPic($vv['cover_pic']);
            }
            $list_arr[$k]['goods_list'] = $list?$list:[];

        }
        $data['goods_list'] = $list_arr;
        apiResponse("success","无数据！",$data);
    }

    /**获取积分商品的分类
     * @author crazy
     * @time 2017-012-20
     */
    public function integralCateList(){
        $concat = C("API_URL").'/Uploads/';
        $list = M("integral_category")->where(['status'=>0,'is_show'=>1])->field("cate_id,category,CONCAT('$concat',pic) as pic")->select();

        return $list;
    }


    /**获取积分商品的分类*/
    public function integralCate(){
        $list = $this->integralCateList();
        apiResponse('success','获取成功',$list?$list:[]);
    }

    /**
     * 判断这个商品这用户是都够众享豆购买
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     *  @param type 1商家商家  0是用户
     *  @param exchange_type 1是麦穗 0是众享豆
     *  @param mix_id  用户或者商家的id
     *  @param g_id 商品的id
     */
    public function isTrue(){
        if($_POST['type'] == 1){
            /**判断一下是麦穗还是众享豆*/
            if($_POST['exchange_type'] == 1){
                $integral = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->getField("integral");
                $goods = M("Goods")->where(array('g_id'=>$_POST['g_id']))->field("price,freight")->find();
                if($integral < (floatval($goods['price'])+floatval($goods['freight']))){
                    apiResponse("error","麦穗不足！");
                }else{
                    apiResponse("success","可以兑换！");
                }
            }else{
                $wallet = M("Shop")->where(array('shop_id'=>$_POST['mix_id']))->getField("wallet");
                $goods = M("Goods")->where(array('g_id'=>$_POST['g_id']))->field("price,freight")->find();
                if($wallet < (floatval($goods['price'])+floatval($goods['freight']))){
                    apiResponse("error","众享豆不足！");
                }else{
                    apiResponse("success","可以兑换！");
                }
            }
        }else{
            /**判断一下是麦穗还是众享豆*/
            if($_POST['exchange_type'] == 1){
                $integral = M("Member")->where(array('m_id'=>$_POST['mix_id']))->getField("integral");
                $goods = M("Goods")->where(array('g_id'=>$_POST['g_id']))->field("price,freight")->find();
                if($integral < (floatval($goods['price'])+floatval($goods['freight']))){
                    apiResponse("error","麦穗不足！");
                }else{
                    apiResponse("success","可以兑换！");
                }
            }else{
                $wallet = M("Member")->where(array('m_id'=>$_POST['mix_id']))->getField("wallet");
                $goods = M("Goods")->where(array('g_id'=>$_POST['g_id']))->field("price,freight")->find();
                if($wallet < (floatval($goods['price'])+floatval($goods['freight']))){
                    apiResponse("error","众享豆不足！");
                }else{
                    apiResponse("success","可以兑换！");
                }
            }
        }

    }
    /**
     * 商品的详情
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param g_id 商品的id
     */
    public function  goodsDetail(){
        $w['g_id'] = $_GET['g_id'];
        $res = M("Goods")->where($w)->find();
        $res['cover_pic'] = $this->returnPic($res['cover_pic']);
        $pic = explode(",",$res['pic']);
        $more_pic = [];
        foreach($pic as $k=>$v){
            $more_pic[] = $this->returnPic($v);
        }
        $res['pic'] = $more_pic?$more_pic:[];
        $res['is_collect'] = $this->isCollect($_GET['m_id'],$_GET['g_id'],1);
        /**计算一共有多少个评价*/
        $app_count = M("ProductAppraise")->where(['type'=>1,'p_id'=>$_GET['g_id'],'status'=>1])->count();
        $res['app_count'] = $app_count?$app_count:0;
        $list = M("ProductAppraise")->where(['type'=>1,'p_id'=>$_GET['g_id']])->field("m_id,star,content,pics,ctime")->order('ctime DESC')->limit(5)->select();
        foreach($list as $k=>$v){
            $list[$k]['content'] = mb_substr($v['content'],0,30,'utf-8');
            $mem = M("Member")->where(['m_id'=>$v['m_id']])->find();
            $list[$k]['nick_name'] = $mem['nick_name'];
            $list[$k]['head_pic'] = $this->returnPic($mem['head_pic']);
            $list[$k]['ctime'] = date('Y-m-d',$v['ctime']);
            if(!empty($v['pics'])){
                $list[$k]['pics'] = explode(',',$v['pics']);
                foreach($list[$k]['pics'] as $key=>$val){
                    $list[$k]['pics'][$key] = C('API_URL').'/'.$val;
                }
            }

        }
        $res['app_list'] = $list?$list:[];
        if($res){
            apiResponse("success", "获取成功", $res?$res:[]);
        }else{
            apiResponse("error", "获取成功");
        }
    }

    /**
     * 兑换豆商品，使用豆支付的
     * 传参方式 post
     * @author crazy
     * @time 2017-07-03
     * @param mix_id 商家或者用户的id
     * @param g_id 商品的id
     * @param name 兑换商品的收货人的姓名
     * @param tel 兑换商品的收货人的联系电话
     * @param address 兑换商品的收货人的收货地址
     * @param g_id 兑换商品的id
     * @param type 0是用户 1是商家
     *
     */
    public function exchange(){
        M()->startTrans();
        if($_POST['is_readonly']==1){
            apiResponse("error", "无操作权限");
        }
        if(empty($_POST['mix_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['g_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['name'])){
            apiResponse("error", "请输入联系人！");
        }
        if(empty($_POST['tel'])){
            apiResponse("error", "请输入手机号！");
        }
        if(empty($_POST['address'])){
            apiResponse("error", "请输入地址！");
        }
        /**添加兑换商品的订单的信息*/
        if(!preg_match(C("MOBILE"),$_POST['tel'])){
            apiResponse("error","手机号格式错误");
        }
        $g_id = $_POST['g_id'];
        $goods = M("Goods")->where(array('g_id'=>$g_id))->field('freight,name,price')->limit(1)->find();
        $wallet_res = 0;
        $bill_res = 0;
        $mess_res = 0;
        $nick_name = '';
        $openid = '';
        if($_POST['type'] == 0){
            $w_m['m_id'] = $_POST['mix_id'];
            $mem_res = M("Member")->where($w_m)->field('nick_name,openid,wallet')->limit(1)->find();
            $nick_name = $mem_res['nick_name'];
            $openid = $mem_res['openid'];
            if((floatval($goods['price'])+floatval($goods['freight'])) > $mem_res['wallet']){
                apiResponse("error", "众享豆不足！");
            }
            /**修改用户的众享豆数*/
            $mem_data['wallet'] = floatval($mem_res['wallet'])-(floatval($goods['price'])+floatval($goods['freight']));
            $mem_data['utime'] = time();
            $wallet_res = M("Member")->where($w_m)->limit(1)->save($mem_data);
            /**添加用户的账单的明细*/
            $bill_m = array(
                'm_id' => $_POST['mix_id'],
                'title' => "兑换商品",
                'content' => date('Y-m-d H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."众享豆,兑换了商品：".$goods['name'],
                'price' => (floatval($goods['price'])+floatval($goods['freight'])),
                'type' => 4,
                'accept_m_id' => $_POST['mix_id'],
                'monitor' => 1,
                'ctime' => time(),
                'name' => $goods['name'],
            );
            $bill_res = M("Bill")->add($bill_m);
            /**添加用户的消息记录*/
            $mess_m = array(
                'm_id'=>$_POST['mix_id'],
                'title'=>"兑换商品",
                'content'=>date('Y-m-d H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."众享豆,兑换了商品：".$goods['name'],
                'price'=>(floatval($goods['price'])+floatval($goods['freight'])),
                'ctime'=>time()
            );
            $mess_res = M("Message")->add($mess_m);
        }elseif ($_POST['type'] == 1){
            $shop_id = $_POST['mix_id'];
            $shop_res = M("Shop")->where(array('shop_id'=>$shop_id))->field("name,openid,wallet")->limit(1)->find();
            $nick_name = $shop_res['name'];
            $openid = $shop_res['openid'];
            if((floatval($goods['price'])+floatval($goods['freight'])) > $shop_res['wallet']){
                apiResponse("error", "众享豆不足！");
            }
            /**修改商家的众享豆数*/
            $shop_data['wallet'] = floatval($shop_res['wallet'])-(floatval($goods['price'])+floatval($goods['freight']));
            $shop_data['utime'] = time();
            $wallet_res = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($shop_data);
            /**添加用户的账单的明细*/
            $bill_m = array(
                'm_id'=>$_POST['mix_id'],
                'title'=>"兑换商品",
                'content'=>date('Y-m-d H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."众享豆,兑换了商品：".$goods['name'],
                'price'=>(floatval($goods['price'])+floatval($goods['freight'])),
                'type'=>4,
                'accept_m_id'=>$_POST['mix_id'],
                'monitor'=>1,
                'id_type'=>1,
                'rank_type'=>1,
                'ctime'=>time(),
                'name'=>$goods['name'],
            );
            $bill_res = M("Bill")->add($bill_m);
            /**添加用户的消息记录*/
            $mess_m = array(
                'm_id'=>$_POST['mix_id'],
                'title'=>"兑换商品",
                'content'=>date('Y-m-d H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."众享豆,兑换了商品：".$goods['name'],
                'price'=>(floatval($goods['price'])+floatval($goods['freight'])),
                'ctime'=>time(),
                'id_type'=>1,
            );
            $mess_res = M("Message")->add($mess_m);
        }
        $data['name'] = filterHtml(trim(I("post.name")));
        $data['g_id'] = $_POST['g_id'];
        $data['tel'] = filterHtml(trim(I("post.tel")));
        $data['address'] = filterHtml(trim(I("post.address")));
        $data['pay_type'] = 0;
        $data['price'] = floatval($goods['price'])+floatval($goods['freight']);
        $data['total_price'] = floatval($goods['price'])+floatval($goods['freight']);
        if($goods['freight']){
            $data['postage'] = $goods['freight'];
        }
        $data['mix_id'] = $_POST['mix_id'];
        $data['rank_type'] = $_POST['type'];
        $data['ctime'] = time();
        $data['status'] = 1;
        $data['type'] = 0;
        if(empty($_POST['id'])){
            $data['ctime'] = time();
            $data['order_sn'] = date('YmdHis',time()).mt_rand(10000,99999);
            $integral = M("IntegralOrder")->add($data);
        }else{
            $data['utime'] = time();
            $integral = M("IntegralOrder")->where(array('i_o_id'=>$_POST['id']))->limit(1)->save($data);
        }
        if($integral && $wallet_res && $bill_res && $mess_res){
            $tem_price = (floatval($goods['price'])+floatval($goods['freight']));
            $time = date('Y-m-d H:i:s',time());
            $s_name = $goods['name'];
            $data_to = array(
                'first'=>array('value'=>urlencode("众享豆兑换商品下单成功！")),
                'keyword1'=>array('value'=>urlencode("$time")),
                'keyword2'=>array('value'=>urlencode("$nick_name")),
                'keyword3'=>array('value'=>urlencode("众享豆兑换商品！")),
                'keyword4'=>array('value'=>urlencode("$s_name")),
                'keyword5'=>array('value'=>urlencode("$tem_price 众享豆")),
                'Remark'=>array('value'=>urlencode("您好,您于 $time 兑换的商品：$s_name 已经下单成功！平台尽快安排为您发货！")),
            );
            $url = $_SERVER['SERVER_NAME'];
            if($_POST['type'] == 1){
                $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Merchant/Order/orderlist/type/1/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
            }else{
                $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Order/orderlist/type/0/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
            }
            M()->commit();
            M("Goods")->where(array('g_id'=>$g_id))->setInc('sales',1); // 商品销量加1
            apiResponse("success", "兑换商品成功！");
        }else{
            M()->rollback();
            apiResponse("error", "兑换商品失败！");
        }
    }



    /**兑换豆商品，使用麦穗支付的*/
    public function exchangeInter(){
        if($_POST['is_readonly']==1){
            apiResponse("error", "无操作权限");
        }
        M()->startTrans();
        if(empty($_POST['mix_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['g_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['name'])){
            apiResponse("error", "请输入联系人！");
        }
        if(empty($_POST['tel'])){
            apiResponse("error", "请输入手机号！");
        }
        if(empty($_POST['address'])){
            apiResponse("error", "请输入地址！");
        }
        /**添加兑换商品的订单的信息*/
        if(!preg_match(C("MOBILE"),$_POST['tel'])){
            apiResponse("error","手机号格式错误");
        }
        $w['g_id'] = $_POST['g_id'];
        $goods = M("Goods")->where($w)->field("freight,price,name,stock")->limit(1)->find();
        if($goods['stock'] <=0){
            apiResponse('error','库存不足，无法兑换商品');
        }
        $wallet_res = 0;
        $bill_res = 0;
        $mess_res = 0;
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        $res_last_piles = 0;
        $nick_name = '';
        $openid = '';
        if($_POST['type'] == 0){
            $w_m['m_id'] = $_POST['mix_id'];
            $mem_res = M("Member")->where($w_m)->field("nick_name,openid,integral")->limit(1)->find();
            $nick_name = $mem_res['nick_name'];
            $openid = $mem_res['openid'];
            if((floatval($goods['price'])+floatval($goods['freight'])) > $mem_res['integral']){
                apiResponse("error", "麦穗不足！");
            }
            /**修改用户的众享豆数*/
            $mem_data['integral'] = floatval($mem_res['integral'])-(floatval($goods['price'])+floatval($goods['freight']));
            $mem_data['utime'] = time();
            $wallet_res = M("Member")->where($w_m)->limit(1)->save($mem_data);
            /**添加用户的账单的明细*/
            $bill_m['m_id'] = $_POST['mix_id'];
            $bill_m['title'] = "兑换商品";
            $bill_m['content'] = date('Y-m-d-H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."麦穗,兑换了商品：".$goods['name'];
            $bill_m['price'] = (floatval($goods['price'])+floatval($goods['freight']));
            $bill_m['type'] = 4;
            $bill_m['accept_m_id'] = $_POST['mix_id'];
            $bill_m['monitor'] = 1;
            $bill_m['ctime'] = time();
            $bill_m['name'] = $goods['name'];
            $bill_res = M("Bill")->add($bill_m);
            /**添加用户的消息记录*/
            $mess_res = $this->addMessage($_POST['mix_id'],"兑换商品",
                date('Y-m-d-H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."麦穗,兑换了商品：".$goods['name'],
                0,(floatval($goods['price'])+floatval($goods['freight'])));
            /**计算用户的积分*/
            /**计算有多少股*/
            $num = floatval($mem_data['integral'])/$meet_pay_price;
            /**获取用户数据表里面存的股数*/
            $b = M("Pie")->where(array('mix_id'=>$_POST['mix_id'],'type'=>0))->count();
            if(floor($num)<$b){
                $c = $b-floor($num);
                M("Pie")->where(array('mix_id'=>$_POST['mix_id'],'type'=>0))->limit($c)->order("ctime desc")->delete();
                $last_piles['piles'] = floor($num);
                $last_piles['utime'] = time()+3;
                $res_last_piles = M("Member")->where($w_m)->limit(1)->save($last_piles);
            }elseif(floor($num)>$b){
                $c = floor($num)-$b;
                for ($i=1;$i<=$c;$i++){
                    $pie_data['mix_id'] = $_POST['mix_id'];
                    $pie_data['pie'] = 1;
                    $pie_data['ctime'] = time();
                    M("Pie")->add($pie_data);
                }
                $last_piles['piles'] = floor($num);
                $last_piles['utime'] = time()+2;
                $res_last_piles = M("Member")->where($w_m)->limit(1)->save($last_piles);
            }else{
                $last_piles['piles'] = floor($num);
                $last_piles['utime'] = time()+1;
                $res_last_piles = M("Member")->where($w_m)->limit(1)->save($last_piles);
            }
        }elseif ($_POST['type'] == 1){
            $w_shop['shop_id'] = $_POST['mix_id'];
            $shop_res = M("Shop")->where($w_shop)->find();
            $nick_name = $shop_res['name'];
            $openid = $shop_res['openid'];
            if((floatval($goods['price'])+floatval($goods['freight'])) > $shop_res['integral']){
                apiResponse("error", "麦穗不足！");
            }
            /**修改商家的众享豆数*/
            $shop_data['integral'] = floatval($shop_res['integral'])-(floatval($goods['price'])+floatval($goods['freight']));
            $shop_data['utime'] = time();
            $wallet_res = M("Shop")->where($w_shop)->limit(1)->save($shop_data);
            /**添加用户的账单的明细*/
            $bill_m['m_id'] = $_POST['mix_id'];
            $bill_m['title'] = "兑换商品";
            $bill_m['content'] = date('Y-m-d H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."麦穗,兑换了商品：".$goods['name'];
            $bill_m['price'] = (floatval($goods['price'])+floatval($goods['freight']));
            $bill_m['type'] = 4;
            $bill_m['accept_m_id'] = $_POST['mix_id'];
            $bill_m['monitor'] = 1;
            $bill_m['id_type'] = 1;
            $bill_m['rank_type'] = 1;
            $bill_m['ctime'] = time();
            $bill_m['name'] = $goods['name'];
            $bill_res = M("Bill")->add($bill_m);
            /**添加用户的消息记录*/
            $mess_res = $this->addMessage($_POST['mix_id'],"兑换商品",
                date('Y-m-d-H:i:s',time())."花费".(floatval($goods['price'])+floatval($goods['freight']))."麦穗,兑换了商品：".$goods['name'],
                1,(floatval($goods['price'])+floatval($goods['freight'])));
            /**计算有多少股*/
            $num = floatval($shop_data['integral'])/$meet_pay_price;
            /**获取用户数据表里面存的股数*/
            $b = M("Pie")->where(array('mix_id'=>$_POST['mix_id'],'type'=>1))->count();
            if(floor($num)<$b){
                $c = $b-floor($num);
                M("Pie")->where(array('mix_id'=>$_POST['mix_id'],'type'=>1))->limit($c)->order("ctime desc")->delete();
                $last_piles['piles'] = floor($num);
                $last_piles['utime'] = time()+2;
                $res_last_piles = M("Shop")->where($w_shop)->limit(1)->save($last_piles);
            }elseif(floor($num)>$b){
                $c = floor($num)-$b;
                for ($i=1;$i<=$c;$i++){
                    $pie_data['mix_id'] = $_POST['mix_id'];
                    $pie_data['pie'] = 1;
                    $pie_data['type'] = 1;
                    $pie_data['ctime'] = time();
                    M("Pie")->add($pie_data);
                }
                $last_piles['piles'] = floor($num);
                $last_piles['utime'] = time()+3;
                $res_last_piles = M("Shop")->where($w_shop)->limit(1)->save($last_piles);
            }else{
                $last_piles['piles'] = floor($num);
                $last_piles['utime'] = time()+1;
                $res_last_piles = M("Shop")->where($w_shop)->limit(1)->save($last_piles);
            }
        }
        $data['order_sn'] = date('YmdHis',time()).mt_rand(10000,99999);
        $data['name'] = filterHtml(trim(I("post.name")));
        $data['tel'] = filterHtml(trim(I("post.tel")));
        $data['address'] = filterHtml(trim(I("post.address")));
        $data['price'] = floatval($goods['price'])+floatval($goods['freight']);
        $data['total_price'] = floatval($goods['price'])+floatval($goods['freight']);
        if($goods['freight']){
            $data['postage'] = $goods['freight'];
        }
        $data['g_id'] = $_POST['g_id'];
        $data['mix_id'] = $_POST['mix_id'];
        $data['rank_type'] = $_POST['type'];
        $data['ctime'] = time();
        $data['status'] = 1;
        $data['pay_type'] = 1;
        $data['type'] = 1;
        $data['remark'] = filterHtml($_POST['remark']);
        $integral = M("integralOrder")->add($data);
        if($integral && $wallet_res && $bill_res && $mess_res && $res_last_piles){
            $tem_price = (floatval($goods['price'])+floatval($goods['freight']));
            $time = date('Y-m-d H:i:s',time());
            $s_name = $goods['name'];
            $data_to = array(
                'first'=>array('value'=>urlencode("麦穗兑换商品下单成功！")),
                'keyword1'=>array('value'=>urlencode("$time")),
                'keyword2'=>array('value'=>urlencode("$nick_name")),
                'keyword3'=>array('value'=>urlencode("麦穗兑换商品！")),
                'keyword4'=>array('value'=>urlencode("$s_name")),
                'keyword5'=>array('value'=>urlencode("$tem_price 麦穗")),
                'Remark'=>array('value'=>urlencode("您好,您于 $time 兑换的商品：$s_name 已经下单成功！平台尽快安排为您发货！")),
            );
            $url = $_SERVER['SERVER_NAME'];
            if($_POST['type'] == 1){
                $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Merchant/Order/orderlist/type/1/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
            }else{
                $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","https://$url/index.php/Order/orderlist/type/0/status/10/p/1/mix_id/".$_POST['mix_id'],$data_to);
            }
            M()->commit();
            M("Goods")->where($w)->setInc('sales',1); // 商品销量加1
            M("Goods")->where($w)->setDec('stock',1); // 商品库存减1
            apiResponse("success", "兑换商品成功！");
        }else{
            M()->rollback();
            apiResponse("error", "兑换商品失败！");
        }
    }



    /**麦穗兑换商品，兑换麦穗商品，这里下单，后面要用微信支付运费*/
    public function exchangeInterGoods(){
        M("IntegralOrder")->startTrans();
        if(empty($_POST['mix_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['g_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['name'])){
            apiResponse("error", "请输入联系人！");
        }
        if(empty($_POST['tel'])){
            apiResponse("error", "请输入手机号！");
        }
        if(empty($_POST['address'])){
            apiResponse("error", "请输入地址！");
        }
        /**添加兑换商品的订单的信息*/
        if(!preg_match(C("MOBILE"),$_POST['tel'])){
            apiResponse("error","手机号格式错误");
        }
        $w['g_id'] = $_POST['g_id'];
        $goods = M("Goods")->where($w)->find();
        if($_POST['type'] == 0){
            $w_m['m_id'] = $_POST['mix_id'];
            $mem_res = M("Member")->where($w_m)->find();
            if($goods['price'] > $mem_res['integral']){
                apiResponse("error", "麦穗不足！");
            }
        }elseif ($_POST['type'] == 1){
            $w_shop['shop_id'] = $_POST['mix_id'];
            $shop_res = M("Shop")->where($w_shop)->find();
            if($goods['price'] > $shop_res['integral']){
                apiResponse("error", "麦穗不足！");
            }
        }
        $data['order_sn'] = date('YmdHis',time()).mt_rand(10000,99999);
        $data['name'] = $_POST['name'];
        $data['tel'] = $_POST['tel'];
        $data['address'] = $_POST['address'];
        $data['price'] = floatval($goods['price']);
        if($goods['freight']){
            $data['postage'] = $goods['freight'];
        }
        $data['g_id'] = $_POST['g_id'];
        $data['mix_id'] = $_POST['mix_id'];
        $data['rank_type'] = $_POST['type'];
        $data['type'] = 1;
        $data['pay_type'] = 1;
        $data['ctime'] = time();
        $integral = M("IntegralOrder")->add($data);
        /**要返回订单的订单号，和订单的商品的麦穗*/
        $return_data['order_sn'] = $data['order_sn'];
        $return_data['freight'] = $goods['freight'];
        $return_data['goods_name'] = $goods['name'];
        if($integral){
            M("IntegralOrder")->commit();
            M("Goods")->where($w)->setInc('sales',1); // 商品销量加1
            apiResponse("success", "下单成功！",$return_data);
        }else{
            M("IntegralOrder")->rollback();
            apiResponse("error", "下单失败！");
        }
    }


    /**兑换商品使用微信支付*/
    public function PayGoodsExchange(){
        M("IntegralOrder")->startTrans();
        if(empty($_POST['mix_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['g_id'])){
            apiResponse("error", "参数错误");
        }
        if(empty($_POST['name'])){
            apiResponse("error", "请输入联系人！");
        }
        if(empty($_POST['tel'])){
            apiResponse("error", "请输入手机号！");
        }
        if(empty($_POST['address'])){
            apiResponse("error", "请输入地址！");
        }
        /**添加兑换商品的订单的信息*/
        if(!preg_match(C("MOBILE"),$_POST['tel'])){
            apiResponse("error","手机号格式错误");
        }

        $w['g_id'] = $_POST['g_id'];
        $goods = M("Goods")->where($w)->field("price,freight,name")->limit(1)->find();
        $data['order_sn'] = date('YmdHis',time()).mt_rand(10000,99999);
        $data['name'] = filterHtml(trim(I("post.name")));
        $data['tel'] = filterHtml(trim(I("post.tel")));
        $data['address'] = filterHtml(trim(I("post.address")));
        /**判断用户是否使用豆抵扣支付*/
        $order_price = floatval($goods['price'])+floatval($goods['freight']);
        $data['price'] = $order_price;
        $data['total_price'] = $order_price;
        if($goods['freight']){
            $data['postage'] = $goods['freight'];
        }
        $data['mix_id'] = $_POST['mix_id'];
        $data['rank_type'] = $_POST['type'];
        $data['ctime'] = time();
        $data['pay_type'] = 2;
        $integral = M("IntegralOrder")->add($data);
        /**要返回订单的订单号，和订单的商品的价格*/
        $return_data['order_sn'] = $data['order_sn'];
        $return_data['price'] = $data['price'];
        $return_data['goods_name'] = $goods['name'];
        if($integral){
            M("IntegralOrder")->commit();
            apiResponse("success", "下单成功！",$return_data);
        }else{
            M("IntegralOrder")->rollback();
            apiResponse("error", "下单失败！");
        }
    }


    /**兑换商品确认下单
     * @author crazy
     * @time 2017-12-25
     * @param m_id 用户的id
     * goods_id 商品的id
     */
    public function makeSureOrder(){
        /**获取用户的默认的地址*/
        $return = [];
        if($_POST['addr_id']){
            $add_res = M("Address")->where(['addr_id'=>$_POST['addr_id']])->field("addr_id,province,city,area,name,address,phone")->find();
            $province = M('Areas')->where(array('area_id'=>$add_res['province']))->limit(1)->getField('area_name');
            $city = M('Areas')->where(array('area_id'=>$add_res['city']))->limit(1)->getField('area_name');
            $area = M('Areas')->where(array('area_id'=>$add_res['area']))->limit(1)->getField('area_name');
            $return['address']['addr_id'] = $add_res['addr_id'];
            $return['address']['phone'] = $add_res['phone'];
            $return['address']['name'] = $add_res['name'];
            $return['address']['address'] = $province.$city.$area.$add_res['address'];
        }else{
            /**获取用户地址的信息*/
            $add_res = M("Address")->where(['mix_id'=>$_POST['m_id'],'type'=>0,'status'=>0])->field("addr_id,province,city,area,name,address,phone")->order('is_default DESC,addr_id DESC')->find();
            if($add_res){
                $province = M('Areas')->where(array('area_id'=>$add_res['province']))->limit(1)->getField('area_name');
                $city = M('Areas')->where(array('area_id'=>$add_res['city']))->limit(1)->getField('area_name');
                $area = M('Areas')->where(array('area_id'=>$add_res['area']))->limit(1)->getField('area_name');
                $return['address']['addr_id'] = $add_res['addr_id'];
                $return['address']['phone'] = $add_res['phone'];
                $return['address']['name'] = $add_res['name'];
                $return['address']['address'] = $province.$city.$area.$add_res['address'];
            }else{
                $return['address']['addr_id'] = "";
                $return['address']['phone'] = "";
                $return['address']['name'] = "";
                $return['address']['address'] = "";
            }
        }
        /**获取商品的信息*/
        $concat = C("API_URL")."/";
        $goods = M("Goods")->where(['g_id'=>$_POST['goods_id']])->field("g_id,name,CONCAT('$concat',cover_pic) as cover_pic,price,freight,price")->find();
        $return['goods']['goods_id'] = $goods['g_id'];
        $return['goods']['name'] = $goods['name'];
        $return['goods']['cover_pic'] = $goods['cover_pic'];
        $return['goods']['freight'] = $goods['freight'];
        $return['goods']['price'] = $goods['price'];
        apiResponse("success", "成功！",$return);

    }





}
