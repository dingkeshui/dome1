<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 商家模块
 */
class ShopController extends ApiBasicController{

    /**
     * 初始化
     */
    public function _initialize(){
        parent::_initialize();
    }

    /**
     * 商家列表的广告
     * 传参方式 post
     * @time 20170728
     * @author ：刘柱
     * @param lat 纬度
     * @param lnt 经度
     */
    public function advertList(){
        $arr = array();
        if($_POST['lat'] || $_POST['lnt']){
            $lat = $_POST['lat'];
            $lnt = $_POST['lnt'];
            $res = $this->lngLatCityN($lnt,$lat);
            $arr = json_decode($res,true);
        }
        if($_POST['area_id']){
            $w['area'] = $_POST['area_id'];
        }else if($_POST['city_id']){
            $w['city'] = $_POST['city_id'];
        }else if($arr['area_id']){
            $w['city'] = $arr['area_id'];
        }else{
            $w['is_quan'] = 1;
        }
        $w['status'] = array('neq',9);
        $w['position'] = 1;
        $w['is_shop'] = 0;
        if($_POST['is_app']){
            $w['is_app'] = $_POST['is_app']>0?$_POST['is_app']:0;
        }
        $field = "a_id,pic,position,url,shop_id";
        $is_set = M("Advert")->where($w)->count();
        if($is_set>0){
            $list_one = M("Advert")->where($w)->field($field)->order('sort asc')->select();
            $w2 = ['is_quan'=>1,'position'=>1,'status'=>array('neq',9),'is_shop'=>0];
            if($_POST['is_app']){
                $w2['is_app'] = $_POST['is_app']>0?$_POST['is_app']:0;
            }
            $list_two = M("Advert")->where($w2)->field($field)->order('sort asc')->select();
            $list = array_merge($list_one,$list_two?$list_two:[]);
        }else{
            if($_POST['is_app']){
                $w1['is_app'] = $_POST['is_app']>0?$_POST['is_app']:0;
            }
            $w1['is_quan'] = 1;
            $w1['status'] = array('neq',9);
            $w1['position'] = 1;
            $w1['is_shop'] = 0;
            $list = M("Advert")->where($w1)->field($field)->order('sort asc')->select();
        }
        if($list){
            apiResponse("success","获取成功！",$list);
        }else{
            apiResponse("error","获取失败！",[]);
        }
    }

//    public function getAreaCity($name,$type){
//        $id = M("Areas")->where(array('area_name'=>array('like',trim($name).'%'),'area_type'=>($type+1)))->getField("area_id");
//        return $id;
//    }


    /**
     * 商家的列表
     * @time 20170728
     * @author ：刘柱
     * @param name 查找的用户的名称
     * @param class_id 商家的分类
     * @param city_id 城市的id
     * @param area_id 地区的id
     */
    public function shopList(){
        $w = '';
        if($_GET['name']){
            $w.= " AND zxty_shop.name LIKE "."'%".$_GET['name']."%'";
        }
        /**分类筛选，默认进来显示所有分类下面的商家*/
        if($_GET['class_id']){
            $w.= " AND zxty_shop.class_id = ".$_GET['class_id'];
        }
        /**地区筛选（城市）*/
        if($_GET['city_id']){
            $w.= " AND zxty_shop.city = ".$_GET['city_id'];
        }
        /**地区筛选（地区）*/
        if($_GET['area_id']){
            $w.= " AND zxty_shop.area = ".$_GET['area_id'];
        }
//        dump($w);
//        exit();
        /*http://restapi.amap.com/v3/ip?key=您的key&ip=114.247.50.2*/
        $lat = $_REQUEST['lat']?$_REQUEST['lat']:"39.058971";
        $lnt = $_REQUEST['lnt']?$_REQUEST['lnt']:"117.116138";
//        if(empty($lat) || empty($lnt)){
//            $ip = get_client_ip();
//            $url = "http://restapi.amap.com/v3/ip?key=993b99a1a6e33e15d650000b2573d94d&ip=$ip";
//            $json = $this->httpsRequest($url);
//            $arr_t = json_decode($json,true);
////            apiResponse($arr_t);
//            $arr = explode(';',$arr_t['rectangle']);
//            $arr_l = explode(',',$arr[0]);
//            $lat = $arr_l[1];
//            $lnt = $arr_l[0];
//        }
//        apiResponse("error",$lat.'_'.$lnt);
        if($_GET['order']){
            //1综合排序  2人气优先  3离我最近  4销量最高
            switch($_GET['order']){
                case 1:
                    $order = "zxty_shop.sale desc,zxty_shop.ctime desc";
                    break;
                case 2:
                    $order = "zxty_shop.sale desc,zxty_shop.star desc";
                    break;
                case 3:
                    $order = "distance ASC";
                    break;
                case 4:
                    $order = "zxty_shop.sale desc";
                    break;
                default:
                    $order = "zxty_shop.star desc";
            }
            $p = ($_REQUEST['p'] - 1)*30;
            $res = M()->query("SELECT zxty_class.class_id,zxty_class.name as class_name,zxty_shop.shop_id,zxty_shop.sale,zxty_shop.area,zxty_shop.is_open,
                            zxty_shop.address,zxty_shop.name as shop_name,zxty_shop.head_pic,zxty_shop.star,zxty_shop.lnt,zxty_shop.grade_icon,
                          zxty_shop.lat, ROUND( 6378.138 * 2 * ASIN( SQRT( POW( SIN(($lat * PI() / 180 - lat * PI() / 180 ) / 2 ), 2 ) + COS($lat * PI() / 180) 
                          * COS(lat * PI() / 180) * POW( SIN(( $lnt * PI() / 180 - lnt * PI() / 180 ) / 2 ), 2 ))) * 1000 ) AS distance 
                          FROM zxty_shop,zxty_class where zxty_shop.status = 0 AND zxty_class.class_id=zxty_shop.class_id $w ORDER BY $order LIMIT $p,30");
            foreach ($res as $k=>$v){
                if(empty($v['head_pic'])){
                    $res[$k]['head_pic'] = "Uploads/logo.png";
                }
                $area_name = M("Areas")->where(array('area_id'=>$v['area']))->getField("area_name");
                $res[$k]['area_name'] = $area_name?$area_name:"暂无";
                $res[$k]['grade_icon'] = $v['grade_icon']?C("API_URL").'/'.$v['grade_icon']:C("API_URL")."/Uploads/Shop/Grade/default.png";
            }
//            dump(M()->getLastSql());
//            dump($res);
            if(empty($res) && $_GET['p'] > 1){
                apiResponse("success","无数据！",$res);
            }elseif ($res){
                apiResponse("success","获取成功！",$res);
            }elseif(empty($res)){
                apiResponse("success","无数据！");
            }else{
                apiResponse("error","获取失败！");
            }
        }else{
            $p = ($_REQUEST['p'] - 1)*30;
            $res = M()->query("SELECT zxty_class.class_id,zxty_class.name as class_name,zxty_shop.shop_id,zxty_shop.sale,zxty_shop.area,zxty_shop.is_open,
                              zxty_shop.address,zxty_shop.name as shop_name,zxty_shop.head_pic,zxty_shop.star,zxty_shop.lnt,zxty_shop.grade_icon,
                          zxty_shop.lat, ROUND( 6378.138 * 2 * ASIN( SQRT( POW( SIN(($lat * PI() / 180 - lat * PI() / 180 ) / 2 ), 2 ) + COS($lat * PI() / 180) 
                          * COS(lat * PI() / 180) * POW( SIN(( $lnt * PI() / 180 - lnt * PI() / 180 ) / 2 ), 2 ))) * 1000 ) AS distance 
                          FROM zxty_shop,zxty_class where zxty_shop.status = 0 AND zxty_class.class_id=zxty_shop.class_id $w ORDER BY distance ASC LIMIT $p,30");
            foreach ($res as $k=>$v){
                if(empty($v['head_pic'])){
                    $res[$k]['head_pic'] = "Uploads/logo.png";
                }
//                $res[$k]['sale'] = M("Order")->where(array('shop_id'=>$v['shop_id']))->count();
                $area_name = M("Areas")->where(array('area_id'=>$v['area']))->getField("area_name");
                $res[$k]['area_name'] = $area_name?$area_name:"暂无";
                $res[$k]['grade_icon'] = $v['grade_icon']?C("API_URL").'/'.$v['grade_icon']:C("API_URL")."/Uploads/Shop/Grade/default.png";
            }
//            dump($res);
//            dump(M()->getLastSql());
            if(empty($res) && $_GET['p'] > 1){
                apiResponse("success","无数据！",$res);
            }elseif ($res){
                apiResponse("success","获取成功！",$res);
            }elseif(empty($res)){
                apiResponse("success","无数据！");
            }else{
                apiResponse("error","获取失败！");
            }
        }
    }

    /**
     * 获取经纬度的接口
     * @time 20170728
     * @author ：刘柱
     * @param lat 纬度
     * @param lnt 经度
     */
    public function getLntLat(){
        $lat = $_POST['lat'];
        $lnt = $_POST['lnt'];
        $shop_id = $_POST['shop_id'];
        $res = M()->query("SELECT zxty_shop.lnt,zxty_shop.lat, ROUND( 6378.138 * 2 * ASIN( SQRT( POW( SIN(($lat * PI() / 180 - lat * PI() / 180 ) / 2 ), 2 ) + COS($lat * PI() / 180) 
                          * COS(lat * PI() / 180) * POW( SIN(( $lnt * PI() / 180 - lnt * PI() / 180 ) / 2 ), 2 ))) * 1000 ) AS distance 
                          FROM zxty_shop where zxty_shop.shop_id = $shop_id LIMIT 1");
        $data['detail'] = $res[0];
        apiResponse("success","获取成功！",$data);

    }

    /**
     * 商家的分类
     * @time 20170728
     * @author ：刘柱
     */
    public function classList(){
        $w_x = "";
        if($_POST['lat'] && $_POST['lnt'] && empty($_POST['city_id']) && empty($_POST['area_id'])){
            $lat = $_POST['lat'];
            $lnt = $_POST['lnt'];
            $res = $this->lngLatCityN($lnt,$lat);
            $arr = json_decode($res,true);
            $w_x['area'] = $arr['dis_id'];
            $w_x1['area'] = $arr['dis_id'];
        }
        if($_POST['city_id']){
            $w_x['city'] = $_POST['city_id'];
            $w_x1['city'] = $_POST['city_id'];
        }
        if($_POST['area_id']){
            $w_x['area'] = $_POST['area_id'];
            $w_x1['area'] = $_POST['area_id'];
        }
        $w['status'] = array('neq',9);
        $list = M("Class")->where($w)->field("class_id,name,pic")->order('sort asc')->select();
        foreach($list as $k=>$v){
            $w_x['class_id'] = $v['class_id'];
            $w_x['status'] = 0;
            $list[$k]['shop_num'] = M("Shop")->where($w_x)->count();
//            apiResponse(M("Shop")->getLastSql());
            $w_x1['status'] = 0;
            $list[$k]['total_num'] = M("Shop")->where($w_x1)->count();
        }
        if($list){
            apiResponse("success","获取成功！",$list);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 商家的详情
     * 参数方式 get
     * @time 20170728
     * @author ：刘柱
     * @param shop_id 商家的id
     */
    public function shopDetail(){
        $res = M("Shop")->where(array('shop_id'=>$_GET['shop_id']))->find();
        preg_match_all('/src=\"\/?(.*?)\"/',$res['content'],$match);
        foreach($match[1] as $key => $src){
            if(!strpos($src,'://')){
                $res['content'] = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=100%",$res['content']);
            }
        }
        /**处理多个图片*/
        $arr = explode(",",$res['pic']);
        $res['pic_arr'] = $arr;
        if(empty($res['head_pic'])){
            $res['head_pic'] = "Uploads/logo.png";
        }
        $list = M("Appraise")->where(array('shop_id'=>$_GET['shop_id'],'status'=>1))->order('ctime desc')->limit(5)->select();
        //file_put_contents('app.txt',M("Appraise")->getLastSql());
        $arr = array();
        foreach ($list as $k=>$v){
            /**获取公司的名称*/
            $arr[$k]['shop_name'] = M("Shop")->where(array('shop_id'=>$v['shop_id']))->getField('name');
            $arr[$k]['app_id'] = $v['app_id'];
            $arr[$k]['star'] = $v['star'];
            preg_match_all('/src=\"\/?(.*?)\"/',$v['content'],$match);
            foreach($match[1] as $key => $src){
                if(!strpos($src,'://')){
                    $arr[$k]['content'] = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=100%",$v['content']);
                }
            }
            /**获取用户的名称*/
            $member = M("Member")->where(array('m_id'=>$v['m_id']))->field("head_pic,nick_name")->find();
            $arr[$k]['mem_name'] = $member['nick_name'];
            $arr[$k]['head_pic'] = $member['head_pic'];
            /**商家回复和用户回复的信息*/
            $res_list = D("ReplyAppraise")->where(array('app_id'=>$v['app_id']))->field("m_name,r_m_name,content")->select();
            $arr[$k]['list'] = $res_list;
        }
        $res['app_list'] = $arr;
        /**获取当前店铺的评价的总数*/
        $app_count = M("Appraise")->where(array('shop_id'=>$_GET['shop_id'],'status'=>1))->count();
        $res['app_count'] = $app_count;
        $shop_id = $_GET['shop_id'];
        /**获取评价列表*/
        $app_list = M()->query("select zxty_appraise.m_id,zxty_appraise.shop_id,zxty_appraise.pic,zxty_appraise.star,zxty_appraise.content,zxty_appraise.ctime,zxty_member.m_id,zxty_member.nick_name,zxty_member.head_pic from zxty_member,zxty_appraise where zxty_appraise.shop_id = $shop_id AND  zxty_appraise.status = 1 AND zxty_member.m_id = zxty_appraise.m_id ORDER BY zxty_appraise.ctime desc limit 5");
        //dump(M()->getLastSql());
        $pics = [];
        foreach ($app_list as $kk=>$vv){
            $app_list[$kk]['time'] = date("Y-m-d",$vv['ctime']);
            if($vv['pic']){
                $pics = explode(',',$vv['pic']);
                foreach($pics as $key=>$val){
                    $pics[$key] = C('API_URL').'/'.$val;
                }
            }
            $app_list[$kk]['pic'] = $pics;
        }
        /**是够收藏*/
        $res['is_collect'] = $this->isCollectShop($_GET['m_id'],$_GET['shop_id']);
        $res['app_list'] = $app_list;
        $res['class_name'] = M("Class")->where(array('class_id'=>$res['class_id']))->getField("name");
        $area = M("Areas")->where(array('area_id'=>$res['area']))->getField("area_name");
        $res['area_name'] = $area?$area:"暂无";
        $res['grade_icon'] = $res['grade_icon']?C("API_URL").'/'.$res['grade_icon']:C("API_URL")."/Uploads/Shop/Grade/default.png";
        /**判断这个商家是否有商品*/
        $res['is_set_goods'] = M("Product")->where(['status'=>0,'shop_id'=>$_GET['shop_id']])->count();
        if($_GET['lat'] && $_GET['lnt']){
            $lat = $_GET['lat'];
            $lnt = $_GET['lnt'];
            $shop_id = $_GET['shop_id'];
            $res_dis = M()->query("SELECT zxty_shop.lnt,zxty_shop.lat, ROUND( 6378.138 * 2 * ASIN( SQRT( POW( SIN(($lat * PI() / 180 - lat * PI() / 180 ) / 2 ), 2 ) + COS($lat * PI() / 180) 
                          * COS(lat * PI() / 180) * POW( SIN(( $lnt * PI() / 180 - lnt * PI() / 180 ) / 2 ), 2 ))) * 1000 ) AS distance 
                          FROM zxty_shop where zxty_shop.shop_id = $shop_id LIMIT 1");
            $res['detail'] = $res_dis[0];
        }
        M('Shop')->where(array('shop_id'=>$_GET['shop_id']))->limit(1)->setInc('click');
        if($res){
            apiResponse("success","获取成功！",$res);
        }else{
            apiResponse("error","获取失败！");
        }
    }



    /**
     * 开通的城市
     * 参数方式 post
     * @time 2017-07-28
     * @author ：刘柱
     * @param area_name 开通的城市的名称
     */
    public function openCity(){
        if($_POST['area_name']){
            $dis_id = M("Areas")->where(array('area_name'=>array('like',trim($_POST['area_name']).'%'),'area_type'=>2))->getField("area_id",true);

            $w['city'] = array('in',$dis_id);
        }else{
            $w['province'] = array('neq',0);
            $w['city'] = array('neq',0);
            $w['area'] = array('neq',0);
        }
        $w['status'] = array('neq',9);
        $list = M("Shop")->where($w)->distinct(true)->field('city')->select();
        foreach ($list as $k => $v){
            $list[$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField("area_name");
        }
        if($list){
            apiResponse("success","获取成功！",$list);
        }else{
            apiResponse("error","暂无开通！");
        }
    }

    /**
     * 通过城市id获取开发的地区的商家
     * 参数方式 get
     * @time 2017-07-28
     * @author ：刘柱
     * @param city_id 城市的id
     */
    public function region(){
        $w['city'] = $_GET['city_id'];
        if(empty($_GET['city_id'])){
            apiResponse("error","参数错误！");
        }
        $w['area'] = array('neq',0);
        $list = M("Shop")->where($w)->distinct(true)->field('area')->select();
        //file_put_contents('area.txt',M("Shop")->getLastSql());
        foreach ($list as $k => $v){
            $list[$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField("area_name");
        }
        apiResponse("success","获取成功！",$list);
    }

    /**
     * 通过ip获取城市
     * @time 2017-07-30
     * @author ：刘柱
     */
    public function ipCity(){
        $ip =get_client_ip();
        $url = "http://restapi.amap.com/v3/ip?ip=$ip&output=JSON&key=993b99a1a6e33e15d650000b2573d94d";
        $res = $this->httpsRequest($url);
        $arr = json_decode($res,true);
        $city_id = M("Areas")->where(array('area_name'=>$arr['city']))->getField("area_id");
        $list['city_id'] = $city_id;
        $list['city_name'] = $arr['city'];
        if($list){
            apiResponse("success","获取成功！",$list);
        }else{
            apiResponse("error","获取失败！");
        }
    }

    /**
     * 通过经纬度获取城市
     * 参数方式 post
     * @time 2017-07-28
     * @author ：刘柱
     * @param lnt 经度
     * @param lat 纬度
     */
    public function lngLatCity(){
        $lnt = $_POST['lnt'];
        $lat = $_POST['lat'];
        $url = "http://restapi.amap.com/v3/geocode/regeo?location=$lnt,$lat&key=993b99a1a6e33e15d650000b2573d94d&radius=1000&extensions=all&output=JSON";
        $json = $this->httpsRequest($url);
        $arr = json_decode($json,true);
        /**直辖市不显示市级，所以要使用省找市级*/
        if(empty($arr['regeocode']['addressComponent']['city'])){
            $area_id = M("Areas")->where(array('area_name'=>$arr['regeocode']['addressComponent']['province']))->getField("area_id");
            $city = M("Areas")->where(array('area_id'=>$area_id))->field("area_id,area_name")->find();
            $city['city_name'] = $city['area_name'];
            $city['province_name'] = $arr['regeocode']['addressComponent']['province'];
            $city['area_name'] = $arr['regeocode']['addressComponent']['district'];
            $dis_id = M("Areas")->where(array('parent_id'=>$city['area_id'],'area_name'=>$city['area_name']))->getField("area_id");
            $city['dis_id'] = $dis_id;
            if($city){
                apiResponse("success","获取成功！",$city);
            }else{
                apiResponse("error","获取失败！");
            }
        }else{
            $area_id = M("Areas")->where(array('area_name'=>$arr['regeocode']['addressComponent']['city']))->getField("area_id");
            $city = M("Areas")->where(array('area_id'=>$area_id))->field("area_id,area_name")->find();
            $city['city_name'] = $city['area_name'];
            $city['province_name'] = $arr['regeocode']['addressComponent']['province'];
            $city['area_name'] = $arr['regeocode']['addressComponent']['district'];
            $dis_id = M("Areas")->where(array('parent_id'=>$city['area_id'],'area_name'=>$city['area_name']))->getField("area_id");
            $city['dis_id'] = $dis_id;
            if($city){
                apiResponse("success","获取成功！",$city);
            }else{
                apiResponse("error","获取失败！");
            }
        }
    }


    /**
     * 商家的评价
     * 传参的方式 post
     * @time 2017-07-30
     * @author ：刘柱
     * @param m_id 用户的id
     * @param shop_id 商家的id
     * @param star 评价的星级
     * @param shop_id 商家的id
     * @param content 评价的内容
     * @param bill_id 账单的id
     */
    public function appraise(){
        $app_type = $_POST['app_type'];
        $data['m_id'] = $_POST['m_id'];
        $data['shop_id'] = $_POST['shop_id'];
        $data['star'] = $_POST['star'];
        $data['content'] = $_POST['content'];
        $data['bill_id'] = $_POST['bill_id'];
        $pic_arr_more = array();
        if($app_type == 1){
            $pic_arr = $this->uploadImgMore('Appraise');
            if(!$pic_arr){
                apiResponse('error','图片上传失败！');
            }
            foreach ($pic_arr as $k=>$v){
                $pic_arr_more[] = 'Uploads/'.$v['savepath'].$v['savename'];
            }
        }else{
            $arr = $_POST['pic'];
            foreach ($arr as $k=>$v){
                $pic       = $v['pic'];
                $pic_name      = $v['pic_name'];
                $temp = explode('.',$pic_name);
                $ext = uniqid().'.'.end($temp);
                $base64    = substr(strstr($pic, ","), 1);
                $image_res = base64_decode($base64);
                $pic_link  = "Uploads/Appraise/".date('Y-m-d').'/'.uniqid().".{$ext}";
                $saveRoot = "Uploads/Appraise/".date('Y-m-d').'/';
                /**检查目录是否存在  循环创建目录*/
                if(!is_dir($saveRoot)){
                    mkdir($saveRoot, 0777, true);
                }
                $res = file_put_contents($pic_link ,$image_res);
                if($res){
                    $pic_arr_more[] = $pic_link;
                }else{
                    apiResponse("error","图片上传失败！");
                }
            }
        }
        $string = implode(",",$pic_arr_more);
        $data['pic'] = $string;
        $data['ctime'] = time();
        $res = M("Appraise")->add($data);
        /**评价的分数总和*/
        M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->setInc('total_grade',$_POST['star']);
        M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->setInc('num');
        /**修改另一个订单的评价状态*/
        M("Bill")->where(['other_b_id'=>$_POST['bill_id']])->limit(1)->save(['is_appraise'=>1]);
        /**添加用户的评价星级*/
        $shop_data_star['star'] = (M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->getField("total_grade"))/(M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->getField("num"));
        M("Shop")->where(array('shop_id'=>$_POST['shop_id']))->save($shop_data_star);
        if($res){
            apiResponse("success","评价成功！");
        }else{
            apiResponse("error","评价失败！");
        }
    }

    /**
     * 获取所有商家的经纬度等等信息
     * 传参方式 post
     * @time 2017-07-30
     * @author ：刘柱
     * @param lat 纬度
     * @param lnt 经度
     */
    public function markerJson(){
        $lat = $_POST['lat'];
        $lnt = $_POST['lnt'];
        $list = M()->query("SELECT zxty_shop.name,zxty_shop.lnt,zxty_shop.lat,zxty_shop.head_pic, ROUND( 6378.138 * 2 * ASIN( SQRT( POW( SIN(($lat * PI() / 180 - lat * PI() / 180 ) / 2 ), 2 ) + COS($lat * PI() / 180) 
                          * COS(lat * PI() / 180) * POW( SIN(( $lnt * PI() / 180 - lnt * PI() / 180 ) / 2 ), 2 ))) * 1000 ) AS distance 
                          FROM zxty_shop,zxty_class where zxty_shop.status <> 9 ORDER BY distance ASC LIMIT 0,30");
        $arr = array();
        $arr1 = array();
        foreach ($list as $kk=>$vv){
            $arr['name'] = $vv['name'];
            $arr['center'] = $vv['lnt'].','.$vv['lat'];
            $arr['type'] = 1;
            $arr1[] = $arr;
        }
        if($arr1){
            apiResponse("success","获取成功！",$arr1);
        }else{
            apiResponse("error","获取失败！");
        }
    }


    /**
     * 商家的公告
     * @time 2017-07-30
     * @author ：刘柱
     */
    public function notice(){
        $notice = M("Shop")->where(array('shop_id'=>$_REQUEST['shop_id']))->getField("notice");
        if($notice){
            apiResponse("success","获取成功！",$notice);
        }else{
            apiResponse("error","暂无公告！");
        }
    }


    /**如果注册失败就改变is_set
     * @author crazy
     * @time 2018-01-03
     */
    public function editSet(){
        switch($_POST['type']){
            case 0:
                M("Member")->where(['m_id'=>$_POST['mix_id']])->limit(1)->save(['is_set'=>0]);
                break;
            case 1:
                M("Shop")->where(['shop_id'=>$_POST['mix_id']])->limit(1)->save(['is_set'=>0]);
                break;
        }
        apiResponse("success","成功！");
    }



}