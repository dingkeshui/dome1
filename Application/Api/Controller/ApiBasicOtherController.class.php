<?php
namespace Api\Controller;
use Think\Controller;
require_once("./JPush/JPush.php");
/**
 * Class ApiBasicOtherController
 * @package Api\Controller
 */
class ApiBasicOtherController extends Controller {
    private $app_key_shop = '9e9914fd6b0c6e84892c508c';
    private $master_secret_shop = 'ab49d57d1b8d7af14051c7c7';
    private $app_key_mem = '72112b6f9ba0a0b0ef29860d';
    private $master_secret_mem = '6ccdf826b4cf1362638f43be';
    /**
     * 初始化定义
     */
    public $conf = '';//配置全局变量
    public function _initialize(){
//        header ("Content-Type:text/html; charset=utf-8" );
//        if(!empty($_GET['m_id'])){
//            //判断用户是否存在
//            $mem = M('Member')->where(array('m_id'=>$_GET['m_id']))->find();
//            if(!$mem||$mem['status']==9){
//                apiResponse('error','用户不存在');
//            }
//            $this->isMemberRegistHX($_GET['m_id']);
//
//        }
//        if(!empty($_GET['shop_id'])){
//            $this->isShopRegistHX($_GET['shop_id']);
//        }
    }

    /**
     * 获取配置信息
     */
    public function getConfig(){

    }


    /**
     * 用户和商家的信息
     * @author crazy
     * @time 2017-07-03
     * $m_id 用户的id
     * $title 发信息的标题
     * $content 发信息的内容
     * $id_type 身份
     * $price 是否有钱
     */
    public function addMessage($m_id,$title,$content,$id_type,$price=0){
        $data['m_id'] = $m_id;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['ctime'] = time();
        $data['id_type'] = $id_type;
        $data['price'] = $price;
        $res = M("Message")->add($data);
        return $res;
    }

    /**
     * 用户和商家的账单的明细
     * @author crazy
     * @time 2017-07-03
     * $m_id 用户的id
     * $shop_id 商家的id
     * $title 标题
     * $content 内容
     * $price 费用钱数
     * $other_price 其他的费用
     * $monitor 0是加  1是减
     * $pay_type 0：积分 1:支付宝  2:微信  3:银行卡
     * $name 名称
     * $type 1转账  2:收益 3：消费 4：兑换   5：提现
     * $id_type 0用户 1商家
     * $accept_m_id 接收人的id
     * $rank_type 身份0：用户 1商家
     * $total_price 总的钱数
     * $other_b_id 绑定明细id
     * $o_id 订单的id
     */
    public function addBill($m_id,$shop_id,$title,$content,$price,$other_price,$monitor,$pay_type,$name,$type,$id_type,$accept_m_id,$rank_type,$total_price,$other_b_id=0,$o_id=0,$deduction=0){
        $data['m_id'] = $m_id;
        $data['shop_id'] = $shop_id;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['price'] = $price;
        $data['other_price'] = $other_price;
        $data['monitor'] = $monitor;
        $data['pay_type'] = $pay_type;
        $data['name'] = $name;
        $data['type'] = $type;
        $data['id_type'] = $id_type;
        $data['accept_m_id'] = $accept_m_id;
        $data['rank_type'] = $rank_type;
        $data['total_price'] = $total_price;
        $data['other_b_id'] = $other_b_id;
        $data['o_id'] = $o_id;
        $data['deduction'] = $deduction;
        $data['ctime'] = time();
        $res = M("Bill")->add($data);
//        file_put_contents('bill.txt',M("Bill")->getLastSql());
        return $res;
    }

    /**
     * 单图片上传图片
     * @author crazy
     * @time 2017-07-03
     */
    public function uploadImg($file,$name){
        $config = array(
            'subName'    =>    array('date','Y-m-d'), //设置文件名
        );
        $upload = new \Think\Upload($config);// 实例化上传类
        $upload->maxSize   =     3145728 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
        $upload->savePath  =      "$file/"; // 设置附件上传目录    // 上传文件
        $info   =   $upload->upload();
        $savename = $info[$name]['savename'];
        $savepath = $info[$name]['savepath'];
        $a =$savepath.$savename;
        return $a;
    }

    /**
     * 上传视频
     * @author crazy
     * @time 2017-07-03
     */
    public function uploadVideo($file,$name){
        $config = array(
            'subName'    =>    array('date','Ym'), //设置文件名
        );
        $upload = new \Think\Upload($config);// 实例化上传类
        $upload->maxSize   =     3145728 ;// 设置附件上传大小
        $upload->savePath  =      "$file/"; // 设置附件上传目录    // 上传文件
        $info   =   $upload->upload();
        $savename = $info[$name]['savename'];
        $savepath = $info[$name]['savepath'];
        $a = $savepath.$savename;
        return $a;
    }
    /**
     * 上传图片
     * @author crazy
     * @time 2017-07-03
     */
    public function uploadImgMore($file){
        $config = array(
            'subName'    =>    array('date','Y-m-d'), //设置文件名
        );
        $upload = new \Think\Upload($config);// 实例化上传类
        $upload->savePath  =      "$file/"; // 设置附件上传目录    // 上传文件
        $info   =   $upload->upload();
        return $info;
    }


    /**
     * 封装用户的分页方法
     * @author crazy
     * @time 2017-07-03
     */
    public function z_page($res,$page,$parameter){
        $list = $res;
        $count = $page; //每页显示的记录数
        $p = new \Think\Page(count($list),$count,$parameter); //实例化分页类 传入总记录数和每页显示的记录数
        $theme = "<ul class='pagination'><li>%TOTAL_ROW%</li><li>%UP_PAGE%</li><li>%LINK_PAGE%</li><li>%DOWN_PAGE%</li></ul>";
        $p->setConfig('theme',$theme);
        $page_info = $p->show();
        $lists = array_slice($list,$p->firstRow,$p->listRows); //在数组中根据条件取出一段值
        $arr['page'] = $page_info;
        $arr['list'] = $lists;
        return $arr;
    }

    

    /**
     *计算两个经纬度之间的距离
     * @author crazy
     * @time 2017-07-03
     *
    */
    public function getDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6367000; //approximate radius of earth in meters

        /*
          Convert these degrees to radians
          to work with the formula
        */

        $lat1 = ($lat1 * pi() ) / 180;
        $lng1 = ($lng1 * pi() ) / 180;

        $lat2 = ($lat2 * pi() ) / 180;
        $lng2 = ($lng2 * pi() ) / 180;

        /*
          Using the
          Haversine formula

          http://en.wikipedia.org/wiki/Haversine_formula

          calculate the distance
        */

        $calcLongitude = $lng2 - $lng1;
        $calcLatitude = $lat2 - $lat1;
        $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);
        $stepTwo = 2 * asin(min(1, sqrt($stepOne)));
        $calculatedDistance = $earthRadius * $stepTwo;

        return round($calculatedDistance);
    }

    /**
     * 获取接口信息
     * @author crazy
     * @time 2017-07-03
     */
    public function httpsRequest($url,$data = ""){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }


    /**
     * 发送信息（短信接口发送信息）
     * @author crazy
     * @time 2017-07-03
     */
    public function sendMsg($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '35778', //您申请的短信模板ID，根据实际情况修改
            'tpl_value' =>"#code#=$body&#company#=众享通赢" //您设置的模板变量，根据实际情况修改
        );

        $this->juhecurl($sendUrl,$smsConf,1); //请求发送短信
    }

    /**
     * 请求接口返回内容
     * @author crazy
     * @time 2017-07-03
     * @param  string $url [请求的URL地址]
     * @param  string $params [请求的参数]
     * @param  int $ipost [是否采用POST形式]
     * @return  string
     */
    public function juhecurl($url,$params=false,$ispost=0){
        $httpInfo = array();
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_HTTP_VERSION , CURL_HTTP_VERSION_1_1 );
        curl_setopt( $ch, CURLOPT_USERAGENT , 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.22 (KHTML, like Gecko) Chrome/25.0.1364.172 Safari/537.22' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT , 30 );
        curl_setopt( $ch, CURLOPT_TIMEOUT , 30);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER , true );
        if( $ispost )
        {
            curl_setopt( $ch , CURLOPT_POST , true );
            curl_setopt( $ch , CURLOPT_POSTFIELDS , $params );
            curl_setopt( $ch , CURLOPT_URL , $url );
        }
        else
        {
            if($params){
                curl_setopt( $ch , CURLOPT_URL , $url.'?'.$params );
            }else{
                curl_setopt( $ch , CURLOPT_URL , $url);
            }
        }
        $response = curl_exec( $ch );
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo( $ch , CURLINFO_HTTP_CODE );
        $httpInfo = array_merge( $httpInfo , curl_getinfo( $ch ) );
        curl_close( $ch );
        return $response;
    }

    /****************************************************
     *  微信通过指定模板信息发送给指定用户，发送完成后返回指定JSON数据
     * @author crazy
     * @time 2017-07-03
     ****************************************************/

    public function wxSendTemplate($jsonData){
        $wxAccessToken  = wx_get_token();
        //write_log('moban.txt',$wxAccessToken);
        $url            = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=".$wxAccessToken;
        $result         = $this->httpsRequest($url,$jsonData);
        return $result;
    }

    /****************************************************
     *  发送自定义的模板消息
     * 'data'=>array(
            'first'=>array('value'=>urlencode("您好,您有新的订单请注意查收")),
            'order_sn'=>array('value'=>urlencode("$order_sn")),
            'store'=>array('value'=>urlencode("$s_name")),
            'time'=>array('value'=>urlencode("$time")),
        )
     * @author crazy
     * @time 2017-07-03
     ****************************************************/
    public function wxSetSend($touser, $template_id, $url, $data, $topcolor= '#ccccc'){
        $template= array(
            'touser'=> $touser,
            'template_id'=> $template_id,
            'url'=> $url,
            'topcolor'=> $topcolor,
            'data'=> $data
        );
        $jsonData= json_encode($template);
        $result= $this->wxSendTemplate(urldecode($jsonData));
        return $result;
    }


    /**
     * 注册环信账号
     * @author mss
     */
    public function createHXUser($username,$password,$nickname){
//        $easemob_obj = D('Easemob','Service');
        $options = array();

        $options['client_id']='YXA6l49JUHvVEeeEWg-RAmZikw';
        $options['client_secret']='YXA61KoWjaiFOtvwh2Eqw2U0bnPiD14';
        $options['org_name']='1137170807178088';
        $options['app_name']='zhongxiangtongying';

        $easemob = new \Org\Easemob($options);
        $res = $easemob->createUser($username,$password,$nickname);
        if(empty($res['error'])){
            return true;
        }else{
            return false;
        }
//        $easemob_obj->createUser($username,$password,$nickname);
    }

    /**
     * 判断用户是否注册过环信账号，没有就为用户注册
     * @author mss
     * @time 2017-09-04
     * @param $m_id 用户id
     */
    public function isMemberRegistHX($m_id){
        $member = M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->field('m_id,nick_name,hx_name')->find();
        $data = array();
        if(empty($member['hx_name'])){
            $data['hx_name'] = date('YmdHis').mt_rand(100000,999999);
            $data['hx_password'] = $data['hx_name'];
            $res = $this->createHXUser($data['hx_name'],$data['hx_password'],$member['nick_name']);
            if($res){
                M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->limit(1)->save($data);
            }
        }
        return $data;
    }

    /**
     * 判断商家是否注册过环信账号，没有就为用户注册
     * @author mss
     * @time 2017-09-04
     * @param $shop_id 商家id
     */
    public function isShopRegistHX($shop_id){
        $shop = M('Shop')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->field('shop_id,name,hx_name')->find();
        if(empty($shop['hx_name'])){
            $data['hx_name'] = date('YmdHis').mt_rand(100000,999999);
            $data['hx_password'] = $data['hx_name'];
            $res = $this->createHXUser($data['hx_name'],$data['hx_password'],$shop['name']);
            if($res){
                M('Shop')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->limit(1)->save($data);
            }
            unset($data);
        }
    }

    /**
     * 极光推送
     * @time 2017-09-05
     * @author mss
     * $type 判断推送的端别，1给商家端推送，0给用户端推送
     */
    public function push($tag,$alias,$title,$alert,$extra,$type){
        if($type==1){
            //商家端
            $app_key = $this->app_key_shop;
            $master_secret = $this->master_secret_shop;
        }else{
            //用户端
            $app_key = $this->app_key_mem;
            $master_secret = $this->master_secret_mem;
        }
        $client = new \JPush($app_key,$master_secret);
        $result = $client->push()
            ->setPlatform(array('ios','android'))
            ->addAlias($alias)
//            ->addTag($tag)
            ->setNotificationAlert($alert)
            ->addAndroidNotification($alert,$title,1,$extra)
            ->addIosNotification($alert,'',\JPush::DISABLE_BADGE,true,'IOS category',$extra)
            ->setOptions(100000,3600,null,false)
            ->send();
    }

    /**
     * 环迅接口post提交
     **/
    function request_post($url = '', $post_data = array()) {
        if (empty($url) || empty($post_data)) {
            return false;
        }

        $o = "";
        foreach ( $post_data as $k => $v )
        {
            $o.= "$k=" . urlencode( $v ). "&" ;
        }
        $post_data = substr($o,0,-1);

        $postUrl = $url;
        $curlPost = $post_data;
        $ch = curl_init();//初始化curl
        curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch);//运行curl
        curl_close($ch);
        return $data;
    }


    /**
     * 生成环迅支付的相关的配置
     * @author crazy
     * @time 2017-11-21
     * @param
     */
    public function getHxPayApp($order_sn,$shop_name,$order_price,$type){
        //$time = date('Y-m-d H:i:s',strtotime('+1year'));
        $url = "https://thumbpay.e-years.com/psfp-webscan/services/scan?wsdl";
        /**回调的地址,1是买单支付，0是订单*/
        if($type == 1){
            $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackHx";
        }else{
            $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callPayWechatGoods";
        }
        $reqip = get_client_ip();
        $customerCode = '202195';
        $data = date('YmdHis');
        $other_data = date('Ymd');
        $is_app = "IOS"; //IOS,ANDROID
        $merCret = "DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe";
        $app_id = "wx16f8be3c13a07c9e";
        /**获取商家名称*/
        $body = "<body><MerBillNo>$order_sn</MerBillNo><Service>app.pay.request</Service><GatewayType>10</GatewayType><Date>$other_data</Date><CurrencyType>156</CurrencyType><Amount>$order_price</Amount><Lang>GB</Lang><Attach></Attach><RetEncodeType>17</RetEncodeType><DeviceInfo></DeviceInfo><DeviceType>$is_app</DeviceType><MchCreateIp>$reqip</MchCreateIp><ServerUrl>$ServerUrl</ServerUrl><BillEXP></BillEXP><GoodsName>$shop_name</GoodsName><AppID>$app_id</AppID><Remark></Remark><Extends1></Extends1><Extends2></Extends2><Extends3></Extends3></body>";
        $signature = md5($body.$customerCode.$merCret);
        $head = "<Version>v1.0.1</Version><MerCode>$customerCode</MerCode><MerName>$shop_name</MerName><Account>2021950015</Account><MsgId>1</MsgId><ReqDate>$data</ReqDate><Signature>$signature</Signature>";
        $xml = "<Ips><GateWayReq><head>$head</head>$body</GateWayReq></Ips>";
        ini_set('soap.wsdl_cache_enabled', "0"); //关闭wsdl缓存
        $client = new \SoapClient("https://thumbpay.e-years.com/psfp-webscan/services/scan?wsdl");
        $arr = $client->__soapCall('scanPay',array('parameters' =>$xml));
        $test = $this->xmlToArray($arr);
        return $test;

    }


    /**获取城市*/
    public function lngLatCityN($lnt,$lat){
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
            return json_encode($city);
        }else{
            $area_id = M("Areas")->where(array('area_name'=>array('like','%'.$arr['regeocode']['addressComponent']['city'])))->getField("area_id");
            $city = M("Areas")->where(array('area_id'=>$area_id))->field("area_id,area_name")->find();
            $city['city_name'] = $city['area_name'];
            $city['province_name'] = $arr['regeocode']['addressComponent']['province'];
            $city['area_name'] = $arr['regeocode']['addressComponent']['district'];
            $dis_id = M("Areas")->where(array('parent_id'=>$city['area_id'],'area_name'=>$city['area_name']))->getField("area_id");
            $city['dis_id'] = $dis_id;
            return json_encode($city);
        }
    }



    /**
     * 判断用户是否收藏商品
     * @author mss
     * @time 2017-11-16
     * @param m_id 用户id
     * @param p_id 商品id
     */
    public function isCollect($m_id,$p_id,$type){
        if(empty($m_id)||empty($p_id)){
            return 0;
        }
        $where['m_id'] = $m_id;
        $where['goods_id'] = $p_id;
        $where['type'] = $type;
        $where['status'] = array('NEQ',9);
        $c_id = M('CollectGoods')->where($where)->limit(1)->getField('c_id');
        if($c_id){
            return $c_id;
        }else{
            return 0;
        }

    }


    /**判断是否有未读的消息
     */
    public function isHaveMsg($id,$type){
        /**正常的消息*/
        $w['m_id'] = $id;
        $w['status'] = 0;
        $w['id_type'] = $type;
        $time = M('MessageDelrecord')->where(array('m_id'=>$id,'type'=>$type,'msg_type'=>0))->limit(1)->getField('del_time');
        $time = !empty($time)?$time:0;
        $w['ctime'] = array('EGT',$time);
        $res = M("Message")->where($w)->getField("mess_id");
        $m_id = $id;
        if($res){
            if($res){
                apiResponse("success","有未读消息",$res);
            }else{
                apiResponse("error","暂无消息");
            }
        }else{
            $time_sys_con = M('MessageDelrecord')->where(array('m_id'=>$id,'type'=>$type,'msg_type'=>1))->limit(1)->getField('del_time');
            $time_sys_con = !empty($time_sys_con)?$time_sys_con:0;

            /**群发的消息*/
            $count = M("Message")->where(array('m_id'=>0,'type'=>1,'id_type'=>$type,'ctime'=>array('EGT',$time_sys_con),'status'=>array('NEQ',9)))->count();
            $read_count = M()->query("SELECT zxty_message.mess_id,zxty_message.id_type,zxty_read.mess_id,zxty_read.m_id,zxty_read.id_type
            FROM zxty_message,zxty_read WHERE zxty_message.mess_id = zxty_read.mess_id AND zxty_message.id_type = zxty_read.id_type
            AND zxty_read.m_id = $m_id AND zxty_read.id_type = $type AND zxty_read.ctime >= $time_sys_con");
            $count_arr = count($read_count);
            $is_read = $count - $count_arr;
            if($is_read > 0){
                apiResponse("success","有未读消息",$is_read);
            }else{
                apiResponse("error","暂无消息");
            }
        }
    }


    /**信息页面首页
     * @author crazy
     * @time 2017-12-22
     */
    public function messageIndex($id,$type){
        $arr = [];
        /**正常的消息*/
        $w['m_id'] = $id;
        $w['status'] = 0;
        $w['id_type'] = $type;
        /**普通消息*/
        $time_com = M('MessageDelrecord')->where(array('m_id'=>$id,'type'=>$type,'msg_type'=>0))->limit(1)->getField('del_time');
        $time = !empty($time_com)?$time_com:0;
        $w['ctime'] = array('EGT',$time);
        $res = M("Message")->where($w)->getField("mess_id");
        $m_id = $id;
        $arr['order_mess'] = empty($res)?"0":"1";
        /**群发的消息*/
        $time_sys = M('MessageDelrecord')->where(array('m_id'=>$id,'type'=>$type,'msg_type'=>1))->limit(1)->getField('del_time');
        $time_sys_con = !empty($time_sys)?$time_sys:0;
        $count = M("Message")->where(array('m_id'=>0,'type'=>1,'id_type'=>$type,'ctime'=>array('EGT',$time_sys_con),'status'=>array('NEQ',9)))->count();
        $read_count = M()->query("SELECT zxty_message.mess_id,zxty_message.id_type,zxty_read.mess_id,zxty_read.m_id,zxty_read.id_type FROM zxty_message,zxty_read WHERE zxty_message.mess_id = zxty_read.mess_id
        AND zxty_message.id_type = zxty_read.id_type AND zxty_read.m_id = $m_id AND zxty_read.id_type = $type AND zxty_read.ctime >= $time_sys_con");
        $count_arr = count($read_count);
        $is_read = $count - $count_arr;
        $arr['system_mess'] = empty($is_read)?"0":"1";

        return $arr;

    }

}
