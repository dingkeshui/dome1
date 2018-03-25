<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class FeedbackController
 * @package Api\Controller
 * 用户端首页模块
 */
class IndexController extends ApiBasicController{


    public function _initialize(){
        parent::_initialize();

    }


    /**
     * 首页
     * 传参方式 get
     * @author crazy
     * @time 2017-07-03
     * @param m_id 用户的id
     */
    public function index(){
//        apiResponse("error","请您从后台退出微信重新登陆！！");
        if(empty($_GET['m_id'])){
            apiResponse("error","请使用微信浏览器打开！");
        }
        $w['m_id'] = $_GET['m_id'];
        /*$earn_price = M("Member")->where($w)->getField("earn_price")?M("Member")->where($w)->getField("earn_price"):'0.00';
        $data['earn_price'] = $earn_price;
        $data['shares'] = M("Member")->where($w)->getField("wallet")?M("Member")->where($w)->getField("wallet"):'0.00';
        $data['integral'] = M("Member")->where($w)->getField("integral")?M("Member")->where($w)->getField("integral"):'0.00';*/
        $member = M('Member')->where($w)->field('earn_price,wallet,integral,hx_name,hx_password')->find();
        $data['earn_price'] = $member['earn_price']?$member['earn_price']:'0.00';
        $data['shares'] = $member['wallet']?$member['wallet']:'0.00';
        $data['integral'] = $member['integral']?$member['integral']:'0.00';
        /**判断这个用户是否开户*/
        $is_open = M('HxUser')->where(array('m_id'=>$_GET['m_id'],'type'=>0))->limit(1)->getField('h_id');
        $data['is_open'] = $is_open?$is_open:'';
        if($data){
            apiResponse("success","获取成功！",$data);
        }else{
            apiResponse("error","数据为空！");
        }
    }

    /**
     * 环迅的创建子账户的方法
     * $param $argMerCode
     * $param $reqIp
     * $param $reqDate  格式：yyyy-MM-dd hh24:mi:ss
     * $param $merAcctNo 商户账户号
     * $param $userType 非直销用户类型1:企业,2个人
     * $param $identityType证件类型:1:身份证;2营业执照直销用户可不用填写
     * $param $identityNo 证件号码直销用户可不用填写
     * $param $userName  个人用户名为个人姓名,企业为企业名称
     * $param $mobiePhoneNo 手机号码
     * $param $pageUrl 同步返回地址(页面响应地址)
     * $param $s2sUrl 异步返回地址
     */
    public function test(){
        $xml_string = '';
        $reqip = get_client_ip();
        $xml_string .="<body>";
        $xml_string .="<merAcctNo>2021950015</merAcctNo>";
        $xml_string .="<userType>1</userType>";
        $xml_string .="<customerCode>shop001</customerCode>";
        $xml_string .="<identityType>2</identityType>";
        $xml_string .="<identityNo>91120223MA05QPUQ5Q</identityNo>";
        $xml_string .="<userName>众享通赢（天津）网络科技有限公司</userName>";
        $xml_string .="<legalName>姜华</legalName>";
        $xml_string .="<legalCardNo>130825199003154816</legalCardNo>";
        $xml_string .="<mobiePhoneNo>17602226923</mobiePhoneNo>";
        $xml_string .="<telPhoneNo></telPhoneNo>";
        $xml_string .="<email></email>";
        $xml_string .="<contactAddress></contactAddress>";
        $xml_string .="<remark></remark>";
        $xml_string .="<pageUrl>https://www.zxty.me/index.php/Api/Index/pageUrl</pageUrl>";
        $xml_string .="<s2sUrl>https://www.zxty.me/index.php/Api/Index/ajaxUrl</s2sUrl>";
        $xml_string .="<directSell></directSell>";
        $xml_string .="<stmsAcctNo></stmsAcctNo>";
        $xml_string .="<ipsUserName></ipsUserName>";
        $xml_string .="</body>";
        $merCret = 'DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe';
        $signature = md5($xml_string.$merCret);
        $xml = '<openUserReqXml><head><version>v1.0.1</version><reqIp>'.$reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>'.$xml_string.'</openUserReqXml>';
        dump($xml);
        $des3 = new \Think\IPSUtils("tFcybKzbyVckNHph159ap9br","XuDcKdZa");
        $arg3 = $des3->encrypt($xml);
        $xml = '<ipsRequest><argMerCode>202195</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        dump($xml);
        file_put_contents('xml.txt',$xml);
        $data['ipsRequest'] = $xml;
        $this->request_post('https://ebp.ips.com.cn/fpms-access/action/user/open',$data);

    }



    public function test1(){
        $body = "<body><merAcctNo>2021950015</merAcctNo><userType>2</userType><customerCode>18522713541</customerCode><identityType>1</identityType><identityNo>120225199302152090</identityNo><userName>天津众享通赢网络科技有限公司</userName><legalName></legalName><legalCardNo></legalCardNo><mobiePhoneNo>18522713541</mobiePhoneNo><telPhoneNo></telPhoneNo><email></email><contactAddress></contactAddress><remark></remark><pageUrl>https://te.zxty.me/index.php/Api/Index/pageUrl</pageUrl><s2sUrl>https://te.zxty.me/index.php/Api/Index/ajaxUrl</s2sUrl><directSell></directSell><stmsAcctNo></stmsAcctNo></body>";
        $MerCret = "DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe";
        $signature = md5($body.$MerCret);
        $arg = "<head><version>v1.0.1</version><reqIp>172.16.6.81</reqIp><reqDate>2017-09-01 16:18:23</reqDate><signature>$signature</signature></head>";
        $openUserReqXml = "<openUserReqXml>$arg$body</openUserReqXml>";
        //3des秘钥
        $deskey = "tFcybKzbyVckNHph159ap9br";
        //3des向量
        $desiv ="XuDcKdZa";
        $ds3 = new \Think\IPSUtils($deskey,$desiv);
        dump($openUserReqXml);

        $arg3 = $ds3->encrypt($openUserReqXml);
        dump($arg3);
        $ret = "<?xml version=\"1.0\" encoding=\"utf-8\"?><ipsRequest><argMerCode>202195</argMerCode><arg3DesXmlPara>[string]</arg3DesXmlPara></ipsRequest>";
    }

    public function queryUser(){
        $reqip = get_client_ip();
        $body = '<body><customerCode>member942</customerCode></body>';
        $signature = md5($body.'DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe');
        $head = '<head><version>V1.0.1</version><reqIp>172.16.6.81</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>';

        $queryuser = '<queryUserReqXml>'.$head.$body.'</queryUserReqXml>';
        dump($queryuser);
        $des3 = new \Think\IPSUtils('tFcybKzbyVckNHph159ap9br','XuDcKdZa');
        $arg3 = $des3->encrypt($queryuser);
        $xml = '<ipsRequest><argMerCode>202195</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        dump($xml);
        $data['ipsRequest'] = $xml;
        $rs = $this->request_post('https://ebp.ips.com.cn/fpms-access/action/user/query',$data);
        dump($rs);
        $postObj = simplexml_load_string($rs, 'SimpleXMLElement', LIBXML_NOCDATA);
        dump($postObj);
        if($postObj->rspCode=='M000000'){
            $result = $des3->decrypt($postObj->p3DesXmlPara);
            $arr = json_decode( json_encode( $result ), true );
        }else{
            $arr = json_decode( json_encode( $postObj ), true );
        }
        dump($arr);
//        return $arr;
    }










































    public function pageUrl(){
        $postStr = file_get_contents("php://input");//返回回复数据
        file_put_contents('xml1.txt',rawurldecode($postStr));
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    }
    public function ajaxUrl(){
        file_put_contents("hxa1.txt",1);
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//返回回复数据
        file_put_contents("hxa.txt",$postStr);
        echo $postStr;
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        echo $postObj;
    }

    public function request_post($url = '', $post_data = array()) {
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
     * @param $xml
     * @return mixed
     * ：将xml转为array
     */
    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }



}