<?php
namespace Api\Controller;
use Think\Controller;
require_once("./JPush/JPush.php");
/**
 * Class ApiBasicController
 * @package Api\Controller
 */
class ApiBasicController extends Controller {
    private $app_key_shop = '9e9914fd6b0c6e84892c508c';
    private $master_secret_shop = 'ab49d57d1b8d7af14051c7c7';
    private $app_key_mem = '72112b6f9ba0a0b0ef29860d';
    private $master_secret_mem = '6ccdf826b4cf1362638f43be';
    /**
     * 初始化定义
     */
    public $conf = '';//配置全局变量
    public function _initialize(){
        header ("Content-Type:text/html; charset=utf-8" );
        if(!empty($_GET['m_id'])){
            $this->isMemberRegistHX($_GET['m_id']);
        }
        if(!empty($_GET['shop_id'])){
            $this->isShopRegistHX($_GET['shop_id']);
        }
        $time = time();
        $config = M("Config")->field('is_on,start_time,end_time')->find();
        if($config['is_on'] == 1 && $time>$config['start_time'] && $time<$config['end_time']){
            $action = ACTION_NAME;
            $arr = M("ForbidAction")->where(['status'=>0,'type'=>1])->getField('action',true);
            if(in_array($action,$arr)){
                apiResponse('error','该项功能维护中！');
            }
        }
    }

    /**
     * 获取配置信息
     */
    public function getConfig(){

    }

    /**获取一级分类
     * @author crazy
     * @time 2017-12-20
     */
    public function getCate(){
        $where = array('status'=>array('NEQ',9),'parent_id'=>0);
        $list = M('Category')->where($where)->field('cate_id,category,pic')->order('sort ASC')->select();
        foreach ($list as $k=>$v){
            $is_set = M('Category')->where(array('parent_id'=>$v['cate_id'],'status'=>array('NEQ',9)))->getField('cate_id');
            /**多少商品*/
            $list[$k]['goods_count'] = M("Product")->where(['status'=>0,'is_sale'=>1,'parent_id'=>$v['cate_id']])->count();
            $list[$k]['have_other'] = empty($is_set)?0:1;
        }
        return $list?$list:[];
    }

    /**商家添加属性
     * @author crazy
     * @time 2017-12-20
     */
    public function addShopAttr($shop_id){
        /**判断这个商家是否已经设置了*/
        $is_set = M("IsSetAttr")->where(['shop_id'=>$shop_id])->getField('id');
        if(empty($is_set)){
            /**将后台的属性设置到商家的名下*/
            $list = M("Attribute")->where(['shop_id'=>0,'status'=>0])->field("attr_id,cate_id,attr_name")->select();
            foreach($list as $K=>$v){
                $attr_data = [
                    'shop_id'=>$shop_id,
                    'cate_id'=>$v['cate_id'],
                    'attr_name'=>$v['attr_name'],
                    'ctime'=>time(),
                ];
                $res = M("Attribute")->add($attr_data);
                $list_attr_value = M("AttrValue")->where(['shop_id'=>0,'status'=>0,'attr_id'=>$v['attr_id']])->field("attr_id,attr_value")->select();
                foreach($list_attr_value as $Kk=>$vv){
                    $attr_value_data = [
                        'shop_id'=>$shop_id,
                        'attr_id'=>$res,
                        'attr_value'=>$vv['attr_value'],
                        'ctime'=>time(),
                    ];
                    M("AttrValue")->add($attr_value_data);
                }

            }

            $is_set_data = [
                'shop_id'=>$shop_id,
                'ctime'=>time(),
            ];
            M("IsSetAttr")->add($is_set_data);
        }
    }


    /**获取分类的值
     * @author crazy
     * @time 2017-12-19
     */
    public function getCateName($cate_id){
        return M("Category")->where(['cate_id'=>$cate_id])->getField('category');
    }


    /**获取数据列表
     */
    public function getListCodeMessage($list,$p){
        if(empty($list) && $p == 1){
            $data['code'] = "error";
            $data['message'] = "数据为空";
            $data['list'] = [];
            return $data;
        }elseif(empty($list) && $p>1){
            $data['code'] = "success";
            $data['message'] = "已加载全部";
            $data['list'] = [];
            return $data;
        }else{
            $data['code'] = "success";
            $data['message'] = "成功";
            $data['list'] = $list;
            return $data;
        }
    }


    /**上传商品的图库的列表
     * 传参方式 POST
     * @author crazy
     * @time 2017-12-15
     */
    public function albumBaseList($p,$shop_id){
        $arr = [];
        $concat = C("API_URL").'/';
        $list = M("Picture")->field("CONCAT('$concat',pic) as x_pic,pic,pic_id,shop_id")->where(['status'=>0,'shop_id'=>$shop_id])->limit($p*15,15)->select();
        $arr['list'] = $list?$list:[];
        $arr['count'] = M("Picture")->where(['status'=>0,'shop_id'=>$shop_id])->count();
        return $arr;
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
    public function addMessage($m_id,$title,$content,$id_type,$price=0,$obj_id=0,$type=0,$pay_type=0){
        $data['m_id'] = $m_id;
        $data['title'] = $title;
        $data['content'] = $content;
        $data['type'] = $type;
        $data['ctime'] = time();
        $data['id_type'] = $id_type;
        $data['price'] = $price;
        $data['order_mix_id'] = $obj_id;
        $data['pay_type'] = $pay_type;
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
            return 1;
        }else{
            return 0;
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
        //判断用户是否存在
        $member = M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->field('m_id,nick_name,hx_name')->find();
        $data = [];
        if($member){
            $data = array();
            if(empty($member['hx_name'])){
                $data['hx_name'] = date('YmdHis').mt_rand(100000,999999);
                $data['hx_password'] = $data['hx_name'];
                $res = $this->createHXUser($data['hx_name'],$data['hx_password'],$member['nick_name']);
                if($res){
                    M('Member')->where(array('m_id'=>$m_id,'status'=>array('NEQ',9)))->limit(1)->save($data);
                }
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
        $data = [];
        if($shop){
            if(empty($shop['hx_name'])){
                $data['hx_name'] = date('YmdHis').mt_rand(100000,999999);
                $data['hx_password'] = $data['hx_name'];
                $res = $this->createHXUser($data['hx_name'],$data['hx_password'],$shop['name']);
                if($res){
                    M('Shop')->where(array('shop_id'=>$shop_id,'status'=>array('NEQ',9)))->limit(1)->save($data);
                }
            }
        }
        return $data;
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
    public function getHxPayApp($order_sn,$shop_name,$order_price,$type,$other=0){
        //$time = date('Y-m-d H:i:s',strtotime('+1year'));
//        $url = "https://thumbpay.e-years.com/psfp-webscan/services/scan?wsdl";
        /**回调的地址,1是买单支付，0是订单 2是认证费用支付  3是购买商城商品支付回调*/
        $ServerUrl = "";
        $app_id = "wx16f8be3c13a07c9e";
        switch($type){
            case 1:
                $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackHx";
                break;
            case 2:
                $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackApproveOrder";
                $app_id = "wx16f8be3c13a07c9e";
                break;
            case 3:
                switch($other){
                    case 0:
                        $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackShopMixOrder";
                        break;
                    case 1:
                        $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callBackShopOneOrder";
                        break;
                }
                break;
            default:
                $ServerUrl = "https://".$_SERVER['HTTP_HOST']."/index.php/Api/Pay/callPayWechatGoods";
        }
        $reqip = get_client_ip();
        $customerCode = '202195';
        $data = date('YmdHis');
        $other_data = date('Ymd');
        $is_app = "IOS"; //IOS,ANDROID
        $merCret = "DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe";
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
    public function isCollect($m_id,$p_id,$type=0){
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


    /**
     * 判断用户是否收藏商品
     * @author mss
     * @time 2017-11-16
     * @param m_id 用户id
     * @param p_id 商品id
     */
    public function isCollectShop($m_id,$shop_id){
        if(empty($m_id)||empty($shop_id)){
            return 0;
        }
        $where['m_id'] = $m_id;
        $where['shop_id'] = $shop_id;
        $where['status'] = array('NEQ',9);
        $c_id = M('Collect')->where($where)->limit(1)->getField('c_id');
        if($c_id){
            return $c_id;
        }else{
            return 0;
        }

    }


    /**过滤年龄
     * @author crazy
     * @time 2017-12-22
     */
    public function getAgeByID($id){
        //过了这年的生日才算多了1周岁
        if(empty($id)) return '';
        $date=strtotime(substr($id,6,8));
        //获得出生年月日的时间戳
        $today=strtotime('today');
        //获得今日的时间戳
        $diff=floor(($today-$date)/86400/365);
        //得到两个日期相差的大体年数
        //strtotime加上这个年数后得到那日的时间戳后与今日的时间戳相比
        $age=strtotime(substr($id,6,8).' +'.$diff.'years')>$today?($diff+1):$diff;
        return $age;
    }



    /**商品评价和积分的评价共用的方法
     * @author crazy
     * @time 2017-12-22
     */
    public function commonAppraise($w,$page){
        $list = M('ProductAppraise')->where($w)->field('p_a_id,m_id,star,content,pics,ctime')->order('ctime DESC')->limit(($page-1)*10,10)->select();
        if(empty($list) && $page ==1){
            apiResponse('success','暂无数据');
        }elseif(empty($list) && $page !=1){
            apiResponse('success','已加载全部');
        }
        //总的评价数
        $count = M('ProductAppraise')->where($w)->count();
        $data = array();
        $data['total_num'] = $count?$count:0;
        foreach($list as $k=>$v){
            $item = array();
            $pics = array();
            if($v['pics']){
                $pics = explode(',',$v['pics']);
                foreach($pics as $key=>$val){
                    $pics[$key] = C('API_URL').'/'.$val;
                }
            }
            $item['appraise_id'] = $v['p_a_id'];
            $member = M('Member')->where(array('m_id'=>$v['m_id']))->field('nick_name,head_pic')->find();
            $item['nick_name'] = $member['nick_name'];
            $item['head_pic'] = $this->returnPic($member['head_pic']);
            $item['star'] = $v['star'];
            $item['content'] = $v['content'];
            $item['pics'] = $pics;
            $item['ctime'] = date('Y-m-d',$v['ctime']);
            $data['list'][] = $item;
        }
        return $data;
    }


    /**获取用户的头像
     * @author crazy
     * @time 2017-12-22
     */
    public function returnPic($head_pic){
        if(strpos($head_pic,'http://')!==false || strpos($head_pic,'https://')!==false){
            $item = $head_pic;
        }elseif(empty($head_pic)){
            $item = C('API_URL').'/Uploads/logo.png';
        }else{
            $item = C('API_URL').'/'.$head_pic;
        }
        return $item;
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

    /**判断是否有未读的消息（直接返回状态）
     * @author crazy
     * @time 2017-12-22
     */
    public function isHaveMsgCenter($id,$type){
        /**正常的消息*/
        $w['m_id'] = $id;
        $w['status'] = 0;
        $w['id_type'] = $type;
        $time = M('MessageDelrecord')->where(array('m_id'=>$id,'type'=>0,'msg_type'=>0))->limit(1)->getField('del_time');

        $time = !empty($time)?$time:0;
        $w['ctime'] = array('EGT',$time);
        $res = M("Message")->where($w)->getField("mess_id");
        $m_id = $id;
        if($res){
            return "1";
        }else{
            /**群发的消息*/
            $time_sys_con = M('MessageDelrecord')->where(array('m_id'=>$id,'type'=>$type,'msg_type'=>1))->limit(1)->getField('del_time');
            $time_sys_con = !empty($time_sys_con)?$time_sys_con:0;
            $count = M("Message")->where(array('m_id'=>0,'type'=>1,'id_type'=>$type,'ctime'=>array('EGT',$time),'status'=>array('NEQ',9)))->count();
            $read_count = M()->query("SELECT zxty_message.mess_id,zxty_message.id_type,zxty_read.mess_id,zxty_read.m_id,zxty_read.id_type
            FROM zxty_message,zxty_read WHERE zxty_message.mess_id = zxty_read.mess_id AND zxty_message.id_type = zxty_read.id_type AND
            zxty_read.m_id = $m_id AND zxty_read.id_type = $type AND zxty_read.ctime >= $time_sys_con");
            $count_arr = count($read_count);
            $is_read = $count - $count_arr;
            if($is_read > 0){
                return "1";
            }else{
                return "0";
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

    /**计算众享豆抵扣和订单的比例值
     * @author crazy
     * @time 2017-12-29
     * @param
     */
    public function scale($id,$small_order_price){
        $cou_scale_price = 0;
        $wallet_scale_price = 0;
        /**先找到这个大的订单*/
        $big_order_res = M("OrderTotal")->where(['id'=>$id])->field("cou_money,wallet,total_money,real_total_money")->find();
        if(!empty($big_order_res['cou_money'])&&!empty($big_order_res['wallet'])){
            /**小的的订单的钱数/（大的订单优惠券的钱数/大的订单的总钱数-大的订单的豆抵扣的钱数）*大的订单的总的钱数*/
            $cou_scale_price = ($small_order_price/$big_order_res['total_money'])*$big_order_res['cou_money'];

            $wallet_scale_price =  ($small_order_price/$big_order_res['total_money'])*$big_order_res['wallet'];

        }elseif(!empty($big_order_res['cou_money'])){

            $cou_scale_price = ($small_order_price/$big_order_res['total_money'])*$big_order_res['cou_money'];

        }elseif(!empty($big_order_res['wallet'])){

            $wallet_scale_price =  ($small_order_price/$big_order_res['total_money'])*$big_order_res['wallet'];

        }

        $data['cou_scale_price'] = $cou_scale_price?sprintf('%.4f',$cou_scale_price):0;
        $data['wallet_scale_price'] = $wallet_scale_price?sprintf('%.4f',$wallet_scale_price):0;
        return $data;
    }


    /**查看订单使用优惠券的金额问题
     * @author crazy
     * @time 2018-02-08
     */
    public function getCouType($o_t_id){
        /**当有用户在两个商家购买商品的时候并且使用了优惠券，然后取消了一个商家的订单*/
        $cou_id = M("OrderTotal")->where(['id'=>$o_t_id])->getField('cou_id');
        /**通过用户领取的优惠券找到用户的记录*/
        $coupon_id = M("CouponMember")->where(['c_m_id'=>$cou_id])->getField("coupon_id");
        /**找到优惠券*/
        $type = M("Coupon")->where(['coupon_id'=>$coupon_id])->getField("type");
        /**只有当定额的时候才能去执行*/
        if($type == 3){
            return 1;
        }else{
            return 0;
        }
    }



/**
 * 查询购物车数量
 * @author mss
 * @time 2017-12-27
 * param m_id 用户id
 */
    public function cartNum($m_id){
        if(empty($m_id)){
            return 0;
        }
        $total_num = M('Cart')->where(['m_id'=>$m_id])->sum('num');
        return $total_num?$total_num:0;
    }


    /**向缓存中添加用户的信息
     * @author crazy
     * @time 2018-01-07
     */
    public function addMemberCache($m_id){
        $member = M("Member")->where(['id'=>$m_id])->field("nick_name")->find();
        $count = M("Order")->where(array("m_id"=>$m_id))->count();
        $count_shop = M("ProductOrder")->where(array("m_id"=>$m_id,'status'=>['not in','0,9']))->count();
        if($count<=0&&$count_shop<=0){
            /**向缓存中添加新的用户的数据*/
            $list1 = S("MEMBER_LIST");
            $x_string = $list1?"欢迎会员".$member['nick_name'].",".$list1:"".","."欢迎会员".$member['nick_name'];
            S("MEMBER_LIST",$x_string);
        }
    }


    /**计算退款金额
     * @author crazy
     * @time 2018-02-09
     */
    public function returnPriceSum($shop_id,$w = ''){
        $return_real_price_sum = M("ReturnOrder")->where(["ctime"=>$w,'shop_id'=>$shop_id,'real_price'=>['gt',0],'status'=>3])->sum('real_price');
        $return_price_sum = M("ReturnOrder")->where(["ctime"=>$w,'shop_id'=>$shop_id,'real_price'=>0.00,'status'=>3])->sum('price');
        return floatval($return_real_price_sum)+floatval($return_price_sum);
    }


    /**商家商城商品发货的模板消息
     * @author crazy
     * @time 2018-02-25
     */
    public function shopSendTem($nick_name,$openid){
        $time = date('Y-m-d H:i:s',time());
        $data_to = array(
            'first'=>array('value'=>urlencode("新的商城订单消息！")),
            'keyword1'=>array('value'=>urlencode("$time")),
            'keyword2'=>array('value'=>urlencode("$nick_name")),
            'keyword3'=>array('value'=>urlencode("新的商城订单消息！")),
            'keyword4'=>array('value'=>urlencode("您商城的商品！")),
            'keyword5'=>array('value'=>urlencode("请根据您订单实际价格为准！")),
            'Remark'=>array('value'=>urlencode("您好,您商城有新的订单！请尽快安排发货！")),
        );
        $url = C("API_URL");
        $this->wxSetSend($openid,"fEmRAs8p25Khzi5a8emtZV4wHBSiMSiM2HQUs6yJfS0","$url/index.php/Merchant/Order/orderindex",$data_to);
    }

}
