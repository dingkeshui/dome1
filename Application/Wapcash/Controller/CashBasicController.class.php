<?php
namespace Wapcash\Controller;
use Think\Controller;
/**
 * Class WapcashController
 * @package Home\Controller
 * 微信加盟商父类
 */
class CashBasicController extends Controller {
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
    public function _initialize() {
        $e_id = session("E_SHOP_ID");
        if (empty($e_id) && ACTION_NAME != "login") {
            $this->display("Login/login");
            exit();
        }
        $this->assign("e_id",$e_id);
    }

    public function wechatLogin(){
        $appid = C("APP_ID");
        $m_id = 0;
        $url_host = C("API_URL");
        $redirect_uri = urlencode($url_host.$_SERVER['REQUEST_URI']);
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_base&state=$m_id#wechat_redirect";
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
        return $openid;
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
    /**获取access_token*/
    function wx_get_token() {
        $token = S('x_access_token');
        if (!$token) {
            $appid = "wx177e70eba12752af";
            $appsecret = "ee729d356793ad7c12d4e71aac20f23c";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret";
            $output = $this->httpsRequest($url);
            $res = json_decode($output, true);
            $token = $res['access_token'];
            // 注意：这里需要将获取到的token缓存起来（或写到数据库中）
            // 不能频繁的访问https://api.weixin.qq.com/cgi-bin/token，每日有次数限制
            // 通过此接口返回的token的有效期目前为2小时。令牌失效后，JS-SDK也就不能用了。
            // 因此，这里将token值缓存1小时，比2小时小。缓存失效后，再从接口获取新的token，这样
            // 就可以避免token失效。
            // S()是ThinkPhp的缓存函数，如果使用的是不ThinkPhp框架，可以使用你的缓存函数，或使用数据库来保存。
            S('x_access_token', $token, 3600);
        }
        return $token;
    }

    /**消息模板*/
    public function sendTemplate(){
        $token = $this->wx_get_token();
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";
        $output = $this->httpsRequest($url);
        return  json_decode($output,true);
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