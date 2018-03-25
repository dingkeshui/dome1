<?php
namespace Merchant\Controller;
use Think\Controller;
/**
 * Class HomeBasicController
 * @package Home\Controller
 * 微信商家端父类
 */
class MerchantBasicController extends Controller {
    /**
     * 判断会员是否登陆
     * PC页面
     */
    public function checkLogin(){

    }
    /**
     * ajax返回数据
     * 2014-6-7
     */
    public function ajaxMsg($f,$m){
        $msg[$f] = $m;
        $this->ajaxReturn($msg,Json);
    }


    /**
     * @var string
     * 网站配置全局变量
     */
    public $conf = '';

    /**
     * 初始化
     */
    public $wx = '';
    public function _initialize() {
        header("Location:https://www.qufutong.me/index.php/Merchant");

//        $date = date('Y-m-d H:i:s',time());
//        file_put_contents('rs7.txt',"我是商家r7{$date}");

        $shop_id = session("SHOP_ID");
        $session = session("OPENID");

        /**兼容app 打开校验session**/ 
        if (empty($shop_id) && isset($_REQUEST["shop_id"])) {
            $shop_id = $_REQUEST["shop_id"];
            session("SHOP_ID",$shop_id);
        }
        /**判断是否商家是否有openid*/
        $isweixin = preg_match('/MicroMessenger/',$_SERVER['HTTP_USER_AGENT']);
        if(empty($shop_id) && empty($session) && $isweixin){            
            $this->wechatLogin();
            $message = $this->message_request($_GET['code']);
            session("OPENID",$message['openid']);
            session("UNIONID",$message['unionid']);
        }
        if (empty($shop_id)) {
            if (ACTION_NAME == "register") {
                $this->display("Shop/register");  
            }else if (ACTION_NAME == "getpass") {
                $this->display("Shop/getpass");  
            }else if (CONTROLLER_NAME == "Pictable") {
                $this->display();  
            }else{
                $this->display("Shop/login");
            }
            exit();
        }
        $this->assign("shop_id",$shop_id);
        if($_GET['shop_id']){
            $shop_id =$_GET['shop_id'];
        }
        /**获取商家的详情*/
        $opening = M('Shop')->where(array('shop_id'=>$shop_id))->limit(1)->getField('opening');
        /**判断商家是否开户环迅*/
        $is_open = M('HxUser')->where(array('m_id'=>$shop_id,'type'=>1))->limit(1)->getField('h_id');
        $is_open = !empty($is_open)?$is_open:'';
        $this->assign('is_open',$is_open);
        $this->assign('opening',$opening);
    }

    public function wechatLogin(){
        $appid = C("APP_ID");
        $m_id = 0;
        $url_host = C("API_URL");
        $redirect_uri = urlencode($url_host.$_SERVER['REQUEST_URI']);
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=$m_id#wechat_redirect";
        header('Location: ' . $url);
    }

    /**判断时间的区间*/
    public function time($time){
        //获取今天凌晨的时间戳
        $day = strtotime(date('Y-m-d',time()));
        //获取昨天凌晨的时间戳
        $pday = strtotime(date('Y-m-d',strtotime('-1 day')));
        //获取现在的时间戳
        $nowtime = time();
        $tc = $nowtime-$time;
        if($time<$pday){
            $str = date('Y-m-d H:i:s',$time);
        }elseif($time<$day && $time>$pday){
            $str = "昨天";
        }elseif($tc>60*60){
            $str = floor($tc/(60*60))."小时前";
        }elseif($tc>60){
            $str = floor($tc/60)."分钟前";
        }else{
            $str = "刚刚";
        }
        return $str;
    }

    /**
     * 获取微信用户个人信息
     */
    public function message_request($code){
        $appid = C("APP_ID");
        $appsecret = C("APP_SECRET");
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appid&secret=$appsecret&code=$code&grant_type=authorization_code";
        $output = $this->httpsRequest($url);
        $jsoninfo = json_decode($output, true);
//        dump($jsoninfo);
//        exit();
        $openid = $jsoninfo["openid"];
        $access_token_x = $jsoninfo['access_token'];
        $url1 = "https://api.weixin.qq.com/sns/userinfo?access_token=$access_token_x&openid=$openid&lang=zh_CN";
        $output1 = $this->httpsRequest($url1);
        //file_put_contents('unionid.txt',$output1);
        $message = json_decode($output1,true);
        return $message;
    }

    /**
     * 获取接口信息
     */
    public function httpsRequest($url,$data = null){
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


    /**微信支付失败，给管理员发送信息*/
    public function sendMsg($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'df3c17a2534919ce681b4c213cf68962', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '34584', //您申请的短信模板ID，根据实际情况修改
            'tpl_value' =>"#code#=$body&#company#=众享通赢" //您设置的模板变量，根据实际情况修改
        );

        $this->juhecurl($sendUrl,$smsConf,1); //请求发送短信
    }

    /**
     * 请求接口返回内容
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

}