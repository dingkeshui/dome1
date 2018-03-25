<?php
namespace Home\Controller;
use Think\Controller;
/**
 * Class HomeBasicController
 * @package Home\Controller
 * 微信前台父类
 */
class WechatBasicController extends Controller {
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
        $action = ACTION_NAME;
        header("Location:https://www.qufutong.me/");
        if($action != 'getjsapigoods'){
            $m_id = session("M_ID");
            $share_m_id = $_GET['m_id']?$_GET['m_id']:"0";
            $shop_id = $_GET['shop_id'];
            $isweixin = preg_match('/MicroMessenger/',$_SERVER['HTTP_USER_AGENT']);
            if($isweixin){
                $is_true_id = M("Member")->where(array('m_id'=>$m_id,'status'=>array('neq',9)))->field('m_id')->find();
                if($_GET['code']&&empty($is_true_id)){
                    $message = $this->message_request($_GET['code']);
                    if($message['openid'] == "" || empty($message['openid'])){
                        $this->wechatLogin($share_m_id);
                        exit;
                    }
                    $member_is = M("Member")->where(array('openid'=>$message['openid'],'status'=>array('neq',9)))->field('m_id,account,openid,recommend,wallet,unionid,opening')->limit(1)->find();
                    if(empty($member_is)){
                        $data['openid'] = $message['openid']?$message['openid']:"0";
                        $data['nick_name'] = $message['nickname']?$message['nickname']:"众享通赢（授权）_".mt_rand(10000,99999);
                        $is_set = M("Member")->where(array('m_id'=>$share_m_id,'status'=>array('neq',9)))->field('m_id')->find();
                        if($is_set){
                            $data['recommend'] = $share_m_id?$share_m_id:"0";
                        }
                        if($shop_id){
                            $data['is_floor'] = $shop_id?$shop_id:"0";
                        }
                        $data['sex'] = $message['sex']?$message['sex']:"0";
                        $data['unionid'] = $message['unionid']?$message['unionid']:"0";
                        $data['head_pic'] = $message['headimgurl']?$message['headimgurl']:"/Uploads/logo.png";
                        $data['ctime'] = time();
                        $res = M("Member")->add($data);
                        if($res){
                            session("M_ID",$res);
                            $this->assign('m_id',$res);
                            $this->assign("is_bind","0");
                            /**判断这个用户是否开户*/
                            $is_open = M('HxUser')->where(array('m_id'=>$res,'type'=>0))->limit(1)->getField('h_id');
                            $is_open = $is_open?$is_open:'';
                            $this->assign('opening',0);
                            $this->assign('wallet',0);
                            $this->assign('is_open',$is_open);
                        }
                    }else{
                        $update_data_other['last_login_time'] = time();
                        $update_data_other['last_login_ip'] = get_client_ip();
                        if(empty($member_is['unionid'])){
                            $update_data_other['unionid'] = $message['unionid']?$message['unionid']:"0";
                        }
                        M("Member")->where(array('m_id'=>$member_is['m_id']))->limit(1)->save($update_data_other);
                        session("M_ID",$member_is['m_id']);
                        /**判断这个用户是否开户*/
                        $is_open = M('HxUser')->where(array('m_id'=>$member_is['m_id'],'type'=>0))->limit(1)->getField('h_id');
                        $is_open = $is_open?$is_open:'';
                        $this->assign('opening',$member_is['opening']);
                        $this->assign('wallet',$member_is['wallet']);
                        $this->assign('is_open',$is_open);
                        $this->assign('m_id',$member_is['m_id']);
                        $this->assign("is_bind",$member_is['account']?$member_is['account']:"0");
                    }
                }else{
                    $is_true_id = M("Member")->where(array('m_id'=>$m_id,'status'=>array('neq',9)))->field('m_id')->find();
                    if(empty($m_id) || empty($is_true_id)){
                        $this->wechatLogin($share_m_id);
                    }else{
                        $member_is = M("Member")->where(array('m_id'=>$m_id))->field('m_id,account,openid,recommend,opening,wallet')->find();
                        $update_data['last_login_time'] = time();
                        $update_data['last_login_ip'] = get_client_ip();
                        M("Member")->where(array('m_id'=>$m_id))->limit(1)->save($update_data);
                        /**判断这个用户是否开户*/
                        $is_open = M('HxUser')->where(array('m_id'=>$member_is['m_id'],'type'=>0))->limit(1)->getField('h_id');
                        $is_open = $is_open?$is_open:'';
                        $this->assign('opening',$member_is['opening']);
                        $this->assign('wallet',$member_is['wallet']);
                        $this->assign('is_open',$is_open);
                        $this->assign('m_id',$member_is['m_id']);
                        $this->assign("is_bind",$member_is['account']?$member_is['account']:"0");
                    }
                }
            }else{
                $m_id = session("M_ID");
                if (!empty($_GET['m_id'])) {
                    $m_id = $_GET['m_id'];
                    session("M_ID",$m_id);
                }
                $wallet = M("Member")->where(array('m_id'=>$m_id))->field('wallet,opening')->find();
                $is_open = M('HxUser')->where(array('m_id'=>$m_id,'type'=>0))->limit(1)->getField('h_id');
                $this->assign('is_open',$is_open);
                $this->assign('wallet',$wallet['wallet']);
                $this->assign('opening',$wallet['opening']);
            }
            /**jsapi*/
            /**jsapi的appid和appsercert的问题*/
            if (!empty($_GET['m_id'])) {
                $this -> assign("m_id",$_GET['m_id']);
            }
            $appid = C("APP_ID");
            $appsecret = C("APP_SECRET");
            $this->assign('appid',$appid);
            $this->wx = new \Think\Jssdk($appid,$appsecret);
            $this->assign('wx', $this->wx->GetSignPackage());
        }
    }

    public function wechatLogin($share_m_id){
        $appid = C("APP_ID");
        $m_id = $share_m_id?$share_m_id:0;
        $url_host = C("API_URL");
        $redirect_uri = urlencode($url_host.$_SERVER['REQUEST_URI']);
        if(stripos($url_host.$_SERVER['REQUEST_URI'],"code=")!== false){
            $redirect_uri = urlencode($url_host.str_replace('code=','',$_SERVER['REQUEST_URI']));
        }
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
        write_log('sq.txt',$output);
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
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '35777', //您申请的短信模板ID，根据实际情况修改
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