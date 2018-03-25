<?php
namespace Api\Controller;
use Think\Controller;
/**商城的商家api*/
class StoreController extends ApiBasicController
{
    public function _initialize(){
        parent::_initialize();
    }


    /**
     * 商家主页
     * 传参方式 post
     * @author mss
     * @time 2017-11-30
     * @param shop_id
     */

    public function index(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $shop = M('Shop')->where(array('shop_id'=>$shop_id))->field('shop_id,name,head_pic,is_open,click,notice,sign_status,approve_time,q_end_time')->find();
        if(!empty($shop['head_pic'])){
            $shop['head_pic'] = C("API_URL").'/'.$shop['head_pic'];
        }
        //统计今日付款金额(买单金额+商品订单金额)
        $w['shop_id'] = $shop_id;
        $w['ctime'] = array('EGT',strtotime(date('Y-m-d 00:00:00')));
        $o_total = M('Order')->where($w)->sum('total_price')?M('Order')->where($w)->sum('total_price'):0;

        $w['status'] = array('not in','0');
        $w['pay_time'] = array('neq','0');
        $total_real_price = M('ProductOrder')->where($w)->sum('real_price');
        $should_price = floatval($total_real_price)-floatval($this->returnPriceSum($shop_id,array('EGT',strtotime(date('Y-m-d 00:00:00')))));
        $p_total = $should_price<0?0:$should_price;
        $total = $o_total+$p_total;

        //付款订单数
        $w_total['status'] = array('not in','0,9');
        $w_total['shop_id'] = $shop_id;
        $order_num_one = M('ProductOrder')->where($w_total)->count()?M('ProductOrder')->where($w_total)->count():0;
        $order_num_two = M('Order')->where(['shop_id'=>$shop_id])->count();
        $order_num = $order_num_one+$order_num_two;
        $goods_num = M('ProductOrder')->where($w_total)->sum('goods_num')?M('ProductOrder')->where($w_total)->sum('goods_num'):0;
        $data['shop_id'] = $shop_id;
        $data['name'] = $shop['name'];
        $data['head_pic'] = $shop['head_pic']?$shop['head_pic']:C("API_URL").'/'."Uploads/logo.png";
        $data['is_open'] = $shop['is_open'];
        $data['click'] = $shop['click'];
        $data['notice'] = $shop['notice'];
        $data['total'] = sprintf('%.2f',$total);
        $data['order_num'] = $order_num;
        $data['goods_num'] = $goods_num;
        if(time() > strtotime(date("2018-07-01"))){
            $approve_time = timeDiff(time(),$shop['q_end_time'])['day']>=365?1:0;
            if($shop['sign_status'] == 0){
                $data['should_sign'] = 1;
            }elseif($shop['sign_status'] == 1 && $approve_time>0){
                $data['should_sign'] = 1;
            }else{
                $data['should_sign'] = 0;
            }
        }else{
            $data['should_sign'] = 0;
        }
        apiResponse('success','获取成功',$data);

    }

    /**
     * 店铺管理显示的信息
     * 传参方式 POST
     * @author mss
     * @time 2017-11-30
     * @param shop_id 商家id
     */
    public function store(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $shop = M('Shop')->where(array('shop_id'=>$shop_id))->field('shop_id,name,head_pic,time,star,is_open,approve_id,approve_time')->find();
        if(!empty($shop['head_pic'])){
            $shop['head_pic'] = C("API_URL").'/'.$shop['head_pic'];
        }else{
            $shop['head_pic'] = C("API_URL").'/Uploads/logo.png';
        }
        //收藏店铺的人数
        $collect_num = M('Collect')->where(array('status'=>0,'shop_id'=>$shop_id))->count();
        //全部商品数
        $goods_num = M('Product')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->count();
        $shop['collect_num'] = $collect_num?$collect_num:0;
        $shop['goods_num'] = $goods_num?$goods_num:0;
//        $info = M('ShopAuth')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->find();
        /**判断这个用户是否需要去认证*/
        $diff_time = $shop['approve_time']-time();
        if($shop['approve_id'] && $diff_time>0){
            $info = 1;
        }else{
            $info = 0;
        }
        $shop['is_auth'] = $info?"1":"0";
        apiResponse('success','成功',$shop);
    }

    /**
     * 店铺基本信息
     * 传参方式 post
     * @author mss
     * @time 2017-12-04
     * @param shop_id 商家id
     */
    public function storeInfo(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        //店铺基本信息
        $shop = M('Shop')->where(array('shop_id'=>$shop_id))->field('name,head_pic,address,class_id')->find();
        if(!empty($shop['head_pic'])){
            $head_pic = C("API_URL").'/'.$shop['head_pic'];
        }else{
            $head_pic = C("API_URL").'/Uploads/logo.png';
        }
        $class = M('Class')->where(array('class_id'=>$shop['class_id']))->getField('name');
        $data['shop']['shop_name'] = filterMessage($shop['name']);
        $data['shop']['head_pic'] = $head_pic;
        $data['shop']['class_name'] = filterMessage($class);
        $data['shop']['address'] = filterMessage($shop['address']);
        //店主信息
        $keeper = M('ShopKeeper')->where(array('shop_id'=>$shop_id,'status'=>0))->field('name,phone,weixin,qq_no')->find();
        $data['keeper']['name'] = filterMessage($keeper['name']);
        $data['keeper']['phone'] = filterMessage($keeper['phone']);
        $data['keeper']['weixin'] = filterMessage($keeper['weixin']);
        $data['keeper']['qq'] = filterMessage($keeper['qq_no']);
        apiResponse('success','获取成功',$data);
    }

    /**
     * 商家删除相册照片
     * 传参方式 POST
     * @author mss
     * @time 2017-11-30
     * @param pic_id 图片id json[1,2,3]
     */
    public function delAlbum(){
        if(empty($_POST['pic_id'])){
            apiResponse('error','未选择照片');
        }
        $w['pic_id'] = array('IN',$_POST['pic_id']);
        $w['shop_id'] = $_POST['shop_id'];
        $data['status'] = 9;
        $data['utime'] = time();
        $res = M('Picture')->where($w)->save($data);
        if($res){
            apiResponse('success','操作成功');
        }else{
            apiResponse('error','操作失败');
        }
    }

    /**上传商品的图库的列表
     * 传参方式 POST
     * @author crazy
     * @time 2017-12-15
     */
    public function albumList(){
        $p = $_POST['p']?$_POST['p']-1:$_POST['p'];
        $list = $this->albumBaseList($p,I("post.shop_id"));
        if(empty($list['list'])&&$_POST['p']==1){
            apiResponse('error','暂无数据',$list);
        }
        if(empty($list['list'])&&$_POST['p']>1){
            apiResponse('success','已加载全部',$list);
        }
        apiResponse('success','获取成功',$list);
    }

    /**上传图库*/
    public function upAlbum(){
        $string = I('post.picGather');
        $array = explode(',',$string);
        $res = [];
        foreach ($array as $k=>$v){
            $data['shop_id'] = I("post.shop_id");
            $data['pic'] = $v;
            $data['ctime'] = time();
            $res[$k]['pic_id'] = M('Picture')->add($data);
            $res[$k]['pic_url'] = $v;
        }
        if($res){
            apiResponse('success','上传成功',$res);
        }else{
            apiResponse('error','上传失败');
        }
    }

    /**
     * 编辑店主信息
     * 传参方式 post
     * @author mss
     * @time 2017-12-04
     * @param shop_id 商家id
     * @param name 店主姓名
     * @param phone 手机号
     * @param weixin 微信号
     * @param qq qq号码
     */
    public function editShopkeeper(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $data = array();
        $shop_id = $_POST['shop_id'];
        if(!empty($_POST['name'])){
            $data['name'] = $_POST['name'];
        }
        if(!empty($_POST['phone'])){
            if(!preg_match(C('MOBILE'),$_POST['phone'])) {
                apiResponse("error","手机格式不正确！");
            }
            $data['phone'] = $_POST['phone'];
        }
        if(!empty($_POST['weixin'])){
            $data['weixin'] = $_POST['weixin'];
        }
        if(!empty($_POST['qq'])){
            $data['qq_no'] = $_POST['qq'];
        }
        $keeper = M('ShopKeeper')->where(array('shop_id'=>$shop_id,'status'=>0))->find();
        if(!$keeper){
            $data['shop_id'] = $shop_id;
            $data['ctime'] = time();
            $res = M('ShopKeeper')->add($data);
        }else{
            $res = M('ShopKeeper')->where(array('k_id'=>$keeper['k_id']))->save($data);
        }
        if($res){
            apiResponse('success','保存成功');
        }else{
            apiResponse('error','保存失败');
        }
    }

    /**
     * 商家订单列表
     * @param shop_id 商家id
     */
    public function orderList(){

    }

    /**
     * 设置运费价格
     * 传参方式 POST
     * @author mss
     * @time 2017-12-03
     * @param shop_id 商家id
     * @param price 运费价格
     */
    public function setPostage(){
        if(!IS_POST){
            $shop_id = $_GET['shop_id'];
            $postage = M('Shop')->where(array('shop_id'=>$shop_id))->getField('postage');
            apiResponse('success','成功',$postage?$postage:"0.00");
        }else{
            if(empty($_POST['shop_id'])){
                apiResponse('error','参数错误');
            }
            $shop_id = $_POST['shop_id'];
            $price = $_POST['price']?$_POST['price']:0;
            M('Shop')->where(array('shop_id'=>$shop_id))->setField('postage',$price);
            apiResponse('success','设置成功');
        }
    }

    /**
     * 设置客服电话
     * 传参方式 POST
     * @author mss
     * @time 2017-12-03
     * @param shop_id 商家id
     * @param tel_one 客服电话1
     * @param tel_two 客服电话2
     * @param tel_three 客服电话3
     * @param tel_four 客服电话4
     * @param tel_five 客服电话5
     */
    public function setPhone(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $data['tel_one'] = $_POST['tel_one'];
        $data['tel_two'] = $_POST['tel_two'];
        $data['tel_three'] = $_POST['tel_three'];
        $data['tel_four'] = $_POST['tel_four'];
        $data['tel_five'] = $_POST['tel_five'];
        $tel_id = M('ShopTel')->where(array('shop_id'=>$shop_id,'status'=>0))->getField('tel_id');
        if($tel_id){
            $data['utime'] = time();
            $res = M('ShopTel')->where(array('tel_id'=>$tel_id))->limit(1)->save($data);
        }else{
            $data['shop_id'] = $shop_id;
            $data['ctime'] = time();
            $res = M('ShopTel')->add($data);
        }
        if($res){
            apiResponse('success','保存成功');
        }else{
            apiResponse('error','保存失败');
        }
    }

    /**
     * 显示客服电话信息
     * 传参方式 post
     * @author mss
     * @time 2017-12-03
     * @param shop_id 商家id
     */
    public function shopTel(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $info = M('ShopTel')->where(array('shop_id'=>$shop_id,'status'=>0))->field('tel_one,tel_two,tel_three,tel_four,tel_five')->find();
        $shop = M('Shop')->where(array('shop_id'=>$shop_id))->field("account")->find();
        apiResponse('success','获取成功',$info?$info:['tel_one'=>$shop['account']]);

    }

    /**
     * 添加/编辑店铺认证消息
     * 传参方式 POST
     * @author mss
     * @time 2017-12-03
     * @param shop_id 商家id
     * @param company 公司名称
     * @param licence_no 营业执照号
     * @param licence_pic 营业执照照片
     * @param name 负责人姓名
     * @param card_pic_one 身份证正面照
     * @param card_pic_two 身份证背面照
     */
    public function setAuthInfo(){
        if(empty($_POST['shop_id'])||empty($_POST['company'])||empty($_POST['licence_no'])||empty($_POST['licence_pic'])||empty($_POST['name'])||empty($_POST['card_pic_one'])||empty($_POST['card_pic_two'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $info = M('ShopAuth')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->field('auth_id,status')->find();
        if($info['status']==1){
            apiResponse('error','信息已认证，不可修改');
        }
        $data['company'] = $_POST['company'];
        $data['licence_no'] = $_POST['licence_no'];
        $data['licence_pic'] = $_POST['licence_pic'];
        $data['name'] = $_POST['name'];
        $data['card_pic_one'] = $_POST['card_pic_one'];
        $data['card_pic_two'] = $_POST['card_pic_two'];
        if($info){
            $data['utime'] = time();
            $res = M('ShopAuth')->where(array('auth_id'=>$info['auth_id']))->limit(1)->save($data);
        }else{
            $data['shop_id'] = $shop_id;
            $data['ctime'] = time();
            $res = M('ShopAuth')->add($data);
        }
        if($res){
            apiResponse('success','成功');
        }else{
            apiResponse('error','失败');
        }
    }

    /**
     * 显示认证信息
     * 传参方式 post
     * @author mss
     * @time 2017-12-03
     * @param shop_id
     */
    public function shopAuthInfo(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $info = M('ShopAuth')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->find();
        if(!$info){
            apiResponse('error','未提交认证信息');
        }
        $data['shop_id'] = $info['shop_id'];
        $data['licence_no'] = filterMessage($info['licence_no']);
        $data['licence_pic'] = $info['licence_pic']?C("API_URL").'/'.$info['licence_pic']:"";
        if($data){
            apiResponse('success','成功',$data);
        }else{
            apiResponse('error','失败');
        }
    }

    /**店铺的公告
     * @author crazy
     * @param shop_id 商家的id
     * notice 店铺的公告
     */
    public function addEditNotice(){
        if(!IS_POST){
            $shop_id = I("get.shop_id");
            $notice = M("Shop")->where(['shop_id'=>$shop_id])->getField('notice');
            if($notice){
                apiResponse('success','成功',$notice?$notice:"");
            }else{
                apiResponse('error','公告为空');
            }
        }else{
            unset($shop_id);
            $shop_id = I("post.shop_id");
            $res = M("Shop")->where(['shop_id'=>$shop_id])->limit(1)->save(['notice'=>filterHtml(I("post.notice"))]);
            if($res){
                apiResponse('success','成功');
            }else{
                apiResponse('error','失败');
            }
        }
    }


    /**店铺的是否营业和营业时间
     * @author crazy
     * @param shop_id 商家的id
     * time 营业时间
     * is_open 1 营业 2不营业
     */
    public function editMixFun(){
        $shop_id = I("post.shop_id");
        $data = [];
        if(I("post.time")){
            $data['time'] = I("post.time");
        }
        if(I("post.is_open") == 0 || I("post.is_open")){
            $data['is_open'] = I("post.is_open");
        }
        $data['utime'] = time();
        $res = M("Shop")->where(['shop_id'=>$shop_id])->limit(1)->save($data);
        if($res){
            apiResponse('success','成功');
        }else{
            apiResponse('error','失败');
        }
    }

    /**商家的只读密码
     * @author crazy
     * @param shop_id 商家的id
     */
    public function getPass(){
        if(!IS_POST){
            $shop_id = I("get.shop_id");
            $read_password = M("Shop")->where(['shop_id'=>$shop_id])->field('w_md5_pass,account')->find();;
            if($read_password){
                apiResponse('success','成功',$read_password?$read_password:"");
            }else{
                apiResponse('error','密码为空');
            }
        }
    }


    /**收益管理
     * @author crazy
     * @time 2017-12-25
     * @param shop_id 商家的id
     */
    public function earnMan(){
        $shop_id = I("post.shop_id");
        $return = [];
        $res = M("Shop")->where(['shop_id'=>$shop_id])->field('wallet,ice_wallet')->find();
        $return['wallet'] = $res['wallet'];
//        /**获取商家的待结算的one 不算退款的钱数*/
//        $no_return_sum_price = M("ProductOrder")->where(['shop_id'=>I("post.shop_id"),
//            'is_account'=>0,'status'=>array('not in','0,9')])->sum('real_price');
//        $return_price = M("ProductOrder")->where(['shop_id'=>I("post.shop_id"),
//            'is_account'=>0,'status'=>array('not in','0,9')])->sum('return_price');
//        $total = sprintf("%.2f",floatval($no_return_sum_price)-floatval($return_price));
        $return['ice_price'] = empty($res['ice_wallet'])?"0.00":$res['ice_wallet'];
        /**判断商家是否开户环迅*/
        $is_open = M('HxUser')->where(array('m_id'=>$shop_id,'type'=>1))->limit(1)->getField('h_id');
        $is_open = !empty($is_open)?$is_open:'';
        $return['is_open_hx'] = $is_open;
        apiResponse('success','获取成功',$return);

    }

    /**生成商家的推荐码*/
    public function createRecommendNum(){
        $shop_id = $_POST['shop_id'];
        $is_set = M("Shop")->where(['shop_id'=>$shop_id])->getField('recommend_num');
        if($is_set){
            apiResponse('success','获取成功',$is_set);
        }else{
            $recommend_num['recommend_num'] = "zxty".rand(10,99).$shop_id;
            M("Shop")->where(['shop_id'=>$shop_id])->limit(1)->save($recommend_num);
            apiResponse('success','获取成功',$recommend_num['recommend_num']);
        }

    }

}
