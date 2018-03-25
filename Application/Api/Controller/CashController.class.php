<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class CashController
 * @package Api\Controller
 * 加盟商接口文件
 */
class CashController extends ApiBasicController{

    public $agency_earn = '';
    public function _initialize(){
        parent::_initialize();
        $this->agency_earn = D('agency_earn');
//        $token = $_SERVER['HTTP_TOKEN'];
//        if($token != "9b8993e4f70e4db7f001d338d7797480"){
//            apiResponse("40001","非法调用！");
//        }
    }

    /**
     * 加盟商登录接口
     * 传参方式 post
     * @time 2017-08-03
     * @author crazy
     * @param account 加盟商手机号
     * @param password 加盟商的密码
     */
    public function login(){
        $where['account'] = $_POST['account'];
        $where['status'] = array('neq',9);
        $account = $this->agency_earn->where($where)->limit(1)->find();
        if($account){
            if(md5($_POST['password'])!=$account['password']){
                apiResponse("error","密码不正确!");
            }
            $where['password'] = md5($_POST['password']);
            $data['e_id'] = $account['e_id'];
            /**修改最后的登录时间和ip地址*/
            $save_data['last_login_time'] = time();
            $save_data['last_login_ip'] = get_client_ip();
            M("agency_earn")->where($where)->limit(1)->save($save_data);
            session('E_SHOP_ID',$account['e_id']);
            apiResponse("success","登陆成功",$data);
        }else{
            apiResponse("error","账号不存在");
        }
    }

    /**
     * 加盟商退出页面
     * @time 2017-08-04
     * @author crazy
     */
    public function loginOut(){
        session('E_SHOP_ID',null);
        apiResponse("success","退出成功！");
    }



    /**
     * 修改加盟商的密码
     * 传参的方式 post
     * @time 20171-08-03
     * @param account 加盟商的登录账号
     * @param new_password 新的密码
     * @param r_password 确认密码
     * @param old_password 原密码
     */
    public function editPass(){
        $w['account'] = $_POST['account'];
//        $w['password'] = md5($_POST['old_password']);
        $w['status'] = array('neq',9);
        $res = M("agency_earn")->where($w)->limit(1)->field('account,password')->find();
        if($res){
            if(md5($_POST['old_password'])!=$res['password']){
                apiResponse('error','原密码错误');
            }
            $password = $_POST['new_password'];
            $r_password = $_POST['r_password'];
            if($password != $r_password){
                apiResponse("error",'两次输入的密码不一致！');
            }
            $data['password'] = md5($_POST['new_password']);
            $data['utime'] = time();
            $exc_res = M('agency_earn')->where($w)->limit(1)->save($data);
            if($exc_res){
                apiResponse("success","修改成功！");
            }else{
                apiResponse("error","修改失败！请联系管理员！");
            }
        }else{
            apiResponse("error","账号不存在");
        }
    }

    /**
     * 加盟商首页信息
     * 传参方式：post
     * @time 2017-08-03
     * @author crazy
     * @param e_id
     */
    public function index(){
        if(empty($_POST['e_id'])){
            apiResponse('error','参数错误！');
        }
        $earn = M("agency_earn")->where(array('e_id'=>$_POST['e_id']))->limit(1)->field('type,e_id,city,area,account,scale')->find();
        $area_name = "";
        $where = array();
        $deduct_w = array();
        $other_id = 0;
        $recommend_w = array();
        if($_POST['e_id']!=5){
            switch ($earn['type']){
                case 1:
                    $area_name = M("Areas")->where(array('area_id'=>$earn['city']))->limit(1)->getField('area_name');
                    $where['city'] = $earn['city'];
                    $where_yes['city'] = $earn['city'];
                    $deduct_w['other_id'] = $earn['city'];
                    $other_id = $earn['city'];
                    $recommend_w['area'] = $earn['city'];
                    break;
                case 2:
                    $area_name = M("Areas")->where(array('area_id'=>$earn['area']))->limit(1)->getField('area_name');
                    $where['area'] = $earn['area'];
                    $where_yes['area'] = $earn['area'];
                    $deduct_w['other_id'] = $earn['area'];
                    $other_id = $earn['area'];
                    $recommend_w['area'] = $earn['area'];
                    break;
            }
        }
        $where['status'] = array('NEQ',9);
        /**计算总的销售额*/
        $sum_price = M('Order')->where($where)->sum("total_price");
        /**计算提成的比例*/
        $deduct_price = sprintf("%.2f",($earn['scale']/1000)*$sum_price);
        $data['area_name'] = $area_name?$area_name:"";
        /**总收益*/
        $data['deduct_price'] = $deduct_price?$deduct_price:"0.00";
        if($_POST['e_id'] == 5){
            $data['deduct_price'] = $sum_price?$sum_price:'0.00';
        }
        /**计算昨日的收益*/
        unset($sum_price);
        /**获取昨天的时间戳*/
        $beginYesterday = mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $endYesterday = mktime(23,59,59,date('m'),date('d')-1,date('Y'));
        $where_yes['ctime'] = array(array('EGT',$beginYesterday),array('ELT',$endYesterday),'AND');
        $where_yes['status'] = array('NEQ',9);
        $sum_price = M('Order')->where($where_yes)->sum("total_price");
        $yesterday_price = sprintf("%.2f",($earn['scale']/1000)*$sum_price);
        /**昨天的收益*/
        $data['yesterday_price'] = $yesterday_price?$yesterday_price:"0.00";
        if($_POST['e_id'] == 5){
            $data['yesterday_price'] = $sum_price?$sum_price:'0.00';
        }
        /**昨天新增的商家*/
        $recommend_w['ctime'] = array(array('EGT',$beginYesterday),array('ELT',$endYesterday),'AND');
        $recommend_w['status'] = 0;
        if($_POST['e_id']!=5){
            $recommend_w['recommend'] = array('neq',0);
        }
        $recommend_count = M("Shop")->where($recommend_w)->count();
        $data['recommend_count'] = $recommend_count?$recommend_count:"0";
        /**加盟商的账号*/
        $data['account'] = $earn['account'];
        apiResponse('success',"获取成功！",$data);
    }


    /**
     * 加盟商下商家的订单
     * 传参方式：get
     * @time 2017-08-03
     * @param e_id
     * @param start_time 开始时间
     * @param end_time 结束时间
     * ========== 2017-11-13 mss 修改 增加筛选条件，统计订单总数和总交易额  ==========
     * @param class_id 分类id
     */
    public function orderList(){
        if(empty($_GET['e_id'])){
            apiResponse('error',"参数错误！");
        }
        $earn = M("agency_earn")->where(array('e_id'=>$_GET['e_id']))->limit(1)->field('type,e_id,city,area,ctime')->find();
        $where = array();
        switch ($earn['type']){
            case 1:
                $where['city'] = $earn['city'];
                break;
            case 2:
                $where['area'] = $earn['area'];
                break;
        }
        /**时间*/
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $after_start_time = $start_time.' '."00:00:00";
            $end_time = I('request.end_time');
            $after_end_time = $end_time.' '."23:59:59";
            $str_startime = strtotime($after_start_time);
            $str_endtime = strtotime($after_end_time);
            if($str_startime<$earn['ctime']&&$_GET['e_id']!=5){
                $str_startime = $earn['ctime'];
            }
        }else{
            if($_GET['e_id']==5){
                $str_startime = 0;
            }else{
                $str_startime = $earn['ctime'];
            }
            $str_endtime = time();
        }
        $where['ctime'] = array(array('EGT',$str_startime),array('ELT',$str_endtime),'and');
        /**商家分类2017-11-13*/
        if(!empty(I('request.class_id'))){
            $class_id = I('request.class_id');
            $shop_ids = M('Shop')->where(array('class_id'=>$class_id,'status'=>array('NEQ',9)))->getField('shop_id',true);
            $where['shop_id'] = array('IN',$shop_ids);
        }
        $where['status'] = array('NEQ',9);
        $p = ($_GET['p']-1)*15;
        $reslist = M('Order')->where($where)->limit($p,15)->order('ctime desc')->field("shop_id,total_price,ctime")->select();
        //apiResponse(M('Order')->getLastSql());
        foreach ($reslist as $k=>$v){
            $shop = M("Shop")->where(array('shop_id'=>$v['shop_id']))->field("name,head_pic")->find();
            $reslist[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
            $reslist[$k]['shop_name'] = $shop['name'];
            $reslist[$k]['head_pic'] ='/'.$shop['head_pic'];
        }
        $data = array();
        /**统计订单数量2017-11-13*/
        $ordernum = M('Order')->where($where)->count();
        $data['order_num'] = $ordernum?$ordernum:'0';
        /**统计订单总交易额2017-11-13*/
        $total = M('order')->where($where)->sum('total_price');
        $data['total'] = $total?$total:'0.00';
        $data['list'] = $reslist?$reslist:array();
        if(empty($reslist) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$data);
        }elseif ($reslist){
            apiResponse("success","获取成功！",$data);
        }elseif(empty($reslist)){
            apiResponse("success","无数据！",$data);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 加盟商下的商家的列表
     * 传参方式：get
     * @time 2017-08-03
     * @author crazy
     * @param e_id 加盟商的id
     * @param p 分页的值
     * @param start_time 开始时间
     * @param end_time 结束时间
     * =========  2017-11-13 mss修改  增加筛选条件，显示商家数量========
     */
    public function shopList(){
        if(empty($_GET['e_id'])){
            apiResponse('error',"参数错误！");
        }
        $earn = M("agency_earn")->where(array('e_id'=>$_GET['e_id']))->limit(1)->field('type,e_id,city,area,ctime')->find();
        $where = array();
        switch ($earn['type']){
            case 1:
                $where['city'] = $earn['city'];
                break;
            case 2:
                $where['area'] = $earn['area'];
                break;
        }
        /**时间*/
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $after_start_time = $start_time.' '."00:00:00";
            $end_time = I('request.end_time');
            $after_end_time = $end_time.' '."23:59:59";
            $str_startime = strtotime($after_start_time);
            $str_endtime = strtotime($after_end_time);
            if($str_startime<$earn['ctime']&&$_GET['e_id']!=5){
                $str_startime = $earn['ctime'];
            }
        }else{
            if($_GET['e_id']==5){
                $str_startime = 0;
            }else{
                $str_startime = $earn['ctime'];
            }
            $str_endtime = time();
        }
        $where['ctime'] = array(array('EGT',$str_startime),array('ELT',$str_endtime),'and');
        /**商家分类2017-11-13*/
        if(!empty(I('request.class_id'))){
            $class_id = I('request.class_id');
            $where['class_id'] = $class_id;
        }

        $where['status'] = array('NEQ',9);
        $p = ($_GET['p']-1)*15;
        $reslist = M('Shop')->where($where)->limit($p,15)->order('ctime desc')->field("shop_id,name,ctime,account,head_pic,address,status")->select();
        //apiResponse(M('Shop')->getLastSql());
        foreach ($reslist as $k=>$v){
            $reslist[$k]['ctime'] = date('Y-m-d H:i:s',$v['ctime']);
            $reslist[$k]['head_pic'] ='/'.$v['head_pic'];
        }
        $data = array();
        /**统计商家数量2017-11-13*/
        $num = M('Shop')->where($where)->count();
        $data['shop_num'] = $num?$num:'0';
        $data['list'] = $reslist?$reslist:array();
        if(empty($reslist) && $_GET['p'] > 1){
            apiResponse("success","无数据！",$data);
        }elseif ($reslist){
            apiResponse("success","获取成功！",$data);
        }elseif(empty($reslist)){
            apiResponse("success","无数据！",$data);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 添加商家
     * 传参方式 post
     * @author mss
     * @time 2017-11-13
     * @param e_id 加盟商id
     * @param account 商家账号
     * @param password 登录密码
     * @param re_password 确认密码
     * @param name 商家名称
     * @param class_id 商家分类id
     * @param app_type app上传图片
     */
    public function addShop(){
        $app_type = $_POST['app_type'];
        $arr = $_POST['pic'];

        /**先判断这个手机号是否被注册*/
        $is_register = M("Shop")->where(array("account"=>$_POST['account'],'status'=>array('neq',9)))->limit(1)->getField("shop_id");
        if($is_register){
            apiResponse("error","此手机号已经被注册！");
        }

        $mobile = $_POST['account'];
        if(!preg_match(C('MOBILE'),$mobile)) {
            apiResponse("error","手机格式不正确！");
        }
        if(filterHtml($_POST['password'])!=filterHtml($_POST['re_password'])){
            apiResponse("error","两次输入的密码不一致！");
        }

        if($app_type == 1){
            $a = $_FILES;
            $pic_arr = $this->uploadImgMore('Shop');
            if(!$pic_arr){
                apiResponse('error','图片上传失败！');
            }
            $_pic = array();
            foreach ($pic_arr as $k=>$v){
                $_pic[] = 'Uploads/'.$v['savepath'].$v['savename'];
            }
            $string = implode(",",$_pic);
        }else{
            $pic_arr = array();
            foreach ($arr as $k=>$v){
                $pic       = $v['pic'];
                $pic_name      = $v['pic_name'];
                $temp = explode('.',$pic_name);
                $ext = uniqid().'.'.end($temp);
                $base64    = substr(strstr($pic, ","), 1);
                $image_res = base64_decode($base64);
                $pic_link  = "Uploads/Shop/".date('Y-m-d').'/'.uniqid().'.jpg';
                $saveRoot = "Uploads/Shop/".date('Y-m-d').'/';
                /**检查目录是否存在  循环创建目录*/
                if(!is_dir($saveRoot)){
                    mkdir($saveRoot, 0777, true);
                }
                $res = file_put_contents($pic_link ,$image_res);
                if($res){
                    $pic_arr[] = $pic_link;
                }else{
                    apiResponse("error","图片上传失败！");
                }
            }
            $string = implode(",",$pic_arr);
        }

        $data['head_pic'] = "Uploads/Shop/logo.png";
        $data['province'] = $_POST['province']?$_POST['province']:0;
        $data['city'] = $_POST['city']?$_POST['city']:0;
        $data['area'] = $_POST['area']?$_POST['area']:0;
        $data['lnt'] = $_POST['lnt']?$_POST['lnt']:0;
        $data['lat'] = $_POST['lat']?$_POST['lat']:0;
        $data['address'] = $_POST['address']?$_POST['address']:0;
        $data['account'] = $mobile;
        $data['name'] = $_POST['name'];
        $data['password'] = md5(filterHtml($_POST['password']));
        $data['recommend'] = 0;
        $data['more_pic'] = $string?$string:"";
        $data['status'] = 1;
        $data['class_id'] = $_POST['class_id'];
        $data['ctime'] = time();

        $res = M("Shop")->add($data);
        unset($data);
        $data['code'] = $this->png($res);
        $hx_name = date('YmdHis').mt_rand(100000,999999);
        $hx_pwd = $hx_name;
        $is_hx = $this->createHXUser($hx_name,$hx_pwd,$_POST['name']);
        if($is_hx){
            $data['hx_name'] = $hx_name;
            $data['hx_password'] = $hx_pwd;
        }
        $is_true = M("Shop")->where(array('shop_id'=>$res))->save($data);
        if($is_true){
            /**向缓存中添加新的用户的数据*/
            $list1 = S("SHOP_LIST");
            $x_string = $list1?"欢迎商家".filterHtml($_POST['name'])."入驻平台".",".$list1:"".","."欢迎商家".filterHtml($_POST['name'])."入驻平台";
            S("SHOP_LIST",$x_string);
            apiResponse("success","注册成功！");
        }else{
            apiResponse("error","注册失败！");
        }

    }

    /**
     * 生成商家二维码
     * @author crazy
     * @time 2017-07-03
     * @param $id 商家的id
     */
    public function png($id){
        vendor("phpqrcode.phpqrcode");
        /**用户的注册*/
        $Submit = new \Vendor\phpqrcode\QRcode();
        $data1 = C("API_URL")."/index.php/".'Pay/pay/shop_id/'.$id;
        //$data1 = "http://www.baidu.com";
        //dump($data);
        // 纠错级别：L、M、Q、H，也就是二维码可以覆盖的区域
        $level = 'H';
        // 点的大小：1到10,用于手机端4就可以了
        $size = 12;
        // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false
        $path = "Uploads/Shop/Code/";
        // 生成的文件名
        $fileName = $path.time().rand(10000,99999).$size.'.png';
        $data['code'] = $fileName;
        //生成二维码图片
        $Submit->png($data1, $fileName, $level, $size , 1);
        $logo = 'Uploads/Shop/Code/logo.png';//准备好的logo图片
        $QR = $fileName;//已经生成的原始二维码图
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring(file_get_contents($QR));
            $logo = imagecreatefromstring(file_get_contents($logo));
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
                $logo_qr_height, $logo_width, $logo_height);
            //输出图片
            $fileName1 = rand(10000,99999).'_'.$id.'.'.'png';
            imagepng($QR, "Uploads/Shop/Code/".$fileName1);
            /**删除没有logo的图片*/
            @unlink($fileName);
            return "Uploads/Shop/Code/".$fileName1;
        }
    }

    /**
     * 加盟商的所属地区和分类
     * 传参方式 get
     * @author mss
     * @time 2017-11-13
     * @param e_id 加盟商id
     */
    public function agent(){
        if(empty($_GET['e_id'])){
            apiResponse('error','参数错误');
        }
        $e_id = $_GET['e_id'];
        //加盟商信息
        $earn = M('AgencyEarn')->where(array('e_id'=>$e_id))->find();
        //省
        $province = M('Areas')->where(array('area_id'=>$earn['province']))->field('area_id,area_name')->find();
        if(!$province) $province = array();
        //市
        $city = M('Areas')->where(array('area_id'=>$earn['city']))->field('area_id,area_name')->find();
        if(!$city) $city = array();
        //区县
        if($earn['type']==1){
            //市级代理
            $area = M('Areas')->where(array('parent_id'=>$earn['city']))->field('area_id,area_name')->select();
        }else{
            //县级代理
            $area = M('Areas')->where(array('area_id'=>$earn['area']))->field('area_id,area_name')->select();
        }
        if(!$area) $area = array();
        //行业分类
        if($earn['module']==1){
            $class_id = M('CashType')->where(array('cash_id'=>$e_id,'status'=>array('NEQ',9)))->getField('cash_type_id',true);
            $where['class_id'] = array('IN',$class_id);
        }
        $where['status'] = array('NEQ',9);
        $class = M('Class')->where($where)->field('class_id,name')->order('sort ASC')->select();

        if(!$class) $class = array();
        $data['province'] = $province;
        $data['city'] = $city;
        $data['area'] = $area;
        $data['classify'] = $class;
        apiResponse('success','成功',$data);
    }



}