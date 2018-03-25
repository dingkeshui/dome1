<?php
/**
 * Created by PhpStorm.
 * User: mss
 * Date: 2017/9/27
 * Time: 14:19
 */

namespace Think;


class Huanxun
{
    private  static $_instance = null;
    private $_key = 'tFcybKzbyVckNHph159ap9br';//秘钥
    private $_iv = 'XuDcKdZa';          //向量
    private $argMercode = '202195';     //由IPS颁发的商户号
    private $merAcctNo = '2021950015'; //商户账户号
    private $merCret = 'DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe';
    private $reqip = '';

    public static function getInstance(){
        if(! (self::$_instance instanceof self) )
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * 用户开户
     * $param $reqIp    请求地址ip
     * $param $reqDate  请求时间格式：yyyy-MM-dd hh24:mi:ss
     * $param $userType 非直销用户类型1:企业,2个人
     * $param customerCode 客户号,类型为数字或字母，要保证唯一
     * $param $identityType证件类型:1:身份证;2营业执照直销用户可不用填写
     * $param $identityNo 证件号码直销用户可不用填写
     * $param $userName  个人用户名为个人姓名,企业为企业名称
     * $param legalName 法人姓名
     * $param legalCardNo 法人身份证号
     * $param $mobiePhoneNo 手机号码
     * $param telPhoneNo 固定电话
     * $param email 邮箱
     * $param contactAddress 联系地址
     * $param remark 备注
     * $param $pageUrl 同步返回地址(页面响应地址)
     * $param $s2sUrl 异步返回地址
     */
    public function openUser($userType,$customerCode,$identityType,$identityNo,$userName,$legalName='',$legalCardNo='',$mobiePhoneNo,$telPhoneNo='',$email='',$contactAddress='',$remark='',$pageUrl,$s2sUrl,$directSell='',$stmsAcctNo='',$ipsUserName=''){
        $this->reqip = get_client_ip();
        $arg3des_body = '<body><merAcctNo>'.$this->merAcctNo.'</merAcctNo><userType>'.$userType.'</userType><customerCode>'.$customerCode.'</customerCode><identityType>'.$identityType.'</identityType><identityNo>'.$identityNo.'</identityNo><userName>'.$userName.'</userName><legalName>'.$legalName.'</legalName><legalCardNo>'.$legalCardNo.'</legalCardNo><mobiePhoneNo>'.$mobiePhoneNo.'</mobiePhoneNo><telPhoneNo>'.$telPhoneNo.'</telPhoneNo><email>'.$email.'</email><contactAddress>'.$contactAddress.'</contactAddress><remark>'.$remark.'</remark><pageUrl>'.$pageUrl.'</pageUrl><s2sUrl>'.$s2sUrl.'</s2sUrl><directSell>'.$directSell.'</directSell><stmsAcctNo>'.$stmsAcctNo.'</stmsAcctNo><ipsUserName>'.$ipsUserName.'</ipsUserName></body>';
//        file_put_contents('noj.txt', $arg3des_body);
        $signature = md5($arg3des_body.$this->merCret);
        $head = '<head><version>V1.0.1</version><reqIp>'.$this->reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>';
        $xml = '<openUserReqXml>'.$head.$arg3des_body.'</openUserReqXml>';
//        file_put_contents('nojj.txt', $arg3des_body);
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($xml);
        $ipsRequest = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
//        $data['ipsRequest'] = $ipsRequest;
//        apiResponse('success','成功',$data);
        return $ipsRequest;
    }

    /**
     * 查询用户开户结果
     * $param customerCode 商户为经销商设置的客户号
     */
    public function queryUser($customerCode){
        $this->reqip = get_client_ip();
        $body = '<body><customerCode>'.$customerCode.'</customerCode></body>';
        $signature = md5($body.$this->merCret);
        $head = '<head><version>V1.0.1</version><reqIp>'.$this->reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>';
        $queryuser = '<queryUserReqXml>'.$head.$body.'</queryUserReqXml>';
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($queryuser);
        $xml = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        $data['ipsRequest'] = $xml;
        $rs = $this->request_post('https://ebp.ips.com.cn/fpms-access/action/user/query',$data);
        $postObj = simplexml_load_string($rs, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($postObj->rspCode=='M000000'){
            $result = $this->xmlToArray($des3->decrypt($postObj->p3DesXmlPara));
            $arr = $result;
//            $arr = json_decode( json_encode( $result ), true );
        }else{
            $arr = json_decode( json_encode( $postObj ), true );
        }
        return $arr;
    }

    /**
     * 修改开户信息
     * $param customerCode 商户为经销商设置的客户号
     * $param pageUrl 同步返回地址
     * $param s2sUrl 异步返回地址
     */
    public function updateUser($customerCode,$pageUrl,$s2sUrl){
        $this->reqip = get_client_ip();
        $body = '<body><customerCode>'.$customerCode.'</customerCode><pageUrl>'.$pageUrl.'</pageUrl><s2sUrl>'.$s2sUrl.'</s2sUrl></body>';
        $signature = md5($body.$this->merCret);
        $head = '<head><version>V1.0.1</version><reqIp>'.$this->reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>';
        $updateuser = '<updateUserReqXml>'.$head.$body.'</updateUserReqXml>';
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($updateuser);
        $xml = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        
        return $xml;
//        $des3->request_post('https://ebp.ips.com.cn/fpms-access/action/user/query',$data);
    }

    /**
     * 转账
     * $param merBillNo 商户系统的订单号、可以传空，否则必须保证唯一
     * $param transferType 2、商户转到经销商
     * $param customerCode 客户号
     * $param transferAmount 转账金额。最多保留两位小数、如 1000.12
     * $param collectionItemName 付款项目名称
     * $param remark 备注信息 长度不超过300
     */
    public function transfer($merBillNo,$customerCode,$transferAmount,$collectionItemName,$remark=''){
        $this->reqip = get_client_ip();
        $body = '<body><merBillNo>'.$merBillNo.'</merBillNo><transferType>2</transferType><merAcctNo>'.$this->merAcctNo.'</merAcctNo><customerCode>'.$customerCode.'</customerCode><transferAmount>'.$transferAmount.'</transferAmount><collectionItemName>'.$collectionItemName.'</collectionItemName><remark>'.$remark.'</remark></body>';
        $signature = md5($body.$this->merCret);
        $trans = '<transferReqXml><head><version>V1.0.1</version><reqIp>'.$this->reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>'.$body.'</transferReqXml>';
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($trans);
        $xml = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';

        return $xml;
    }

    /**
     * 用户提现
     * $param merBillNo 商户提交的订单号
     * $param customerCode 客户号
     * $param pageUrl 同步返回地址
     * $param s2sUrl 异步返回地址
     * $param bankCard 用户提交的银行卡号
     * $param bankCode 银行类型编号
     */
    public function withdraw($merBillNo='',$customerCode,$pageUrl,$s2sUrl,$bankCard='',$bankCode=''){
        $this->reqip = get_client_ip();
        $body = '<body><merBillNo>'.$merBillNo.'</merBillNo><customerCode>'.$customerCode.'</customerCode><pageUrl>'.$pageUrl.'</pageUrl><s2sUrl>'.$s2sUrl.'</s2sUrl><bankCard>'.$bankCard.'</bankCard><bankCode>'.$bankCode.'</bankCode></body>';
        $signature = md5($body.$this->merCret);
        $trans = '<withdrawalReqXml ><head><version>V1.0.1</version><reqIp>'.$this->reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>'.$body.'</withdrawalReqXml >';
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($trans);
        $xml = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        return $xml;
    }

    /**
     * 查询交易结果
     * param customerCode 客户号
     * param ordersType 交易类型，默认为全部，3收款，4提现
     * param merBillNo 商户订单号
     * param ipsBillNo IPS订单号
     * param startTime 开始时间 格式YYYYMMdd
     * param endTime 结束时间 格式YYYYMMdd
     * param currrentPage 页码 正整数
     * param pageSize 每页返回的数量，最大100
     */
    public function queryResult($customerCode,$ordersType='',$merBillNo='',$ipsBillNo='',$startTime='',$endTime='',$currrentPage='',$pageSize=''){
        $this->reqip = get_client_ip();
        $body = '<body><merAcctNo>'.$this->merAcctNo.'</merAcctNo><customerCode>'.$customerCode.'</customerCode><ordersType>'.$ordersType.'</ordersType><merBillNo>'.$merBillNo.'</merBillNo><ipsBillNo>'.$ipsBillNo.'</ipsBillNo><startTime>'.$startTime.'</startTime><endTime>'.$endTime.'</endTime><currrentPage>'.$currrentPage.'</currrentPage><pageSize>'.$pageSize.'</pageSize></body>';
        $signature = md5($body.$this->merCret);
        $queryOrderReqXml  = '<queryOrderReqXml><head><version>V1.0.1</version><reqIp>'.$this->reqip.'</reqIp><reqDate>'.date('Y-m-d H:i:s').'</reqDate><signature>'.$signature.'</signature></head>'.$body.'</queryOrderReqXml>';
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($queryOrderReqXml);
        $xml = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        $requesturl = 'https://ebp.ips.com.cn/fpms-access/action/trade/queryOrdersList';//接口地址
        $post_data['ipsRequest'] = $xml;
        $res = $this->request_post($requesturl,$post_data);
        $postObj = simplexml_load_string($res, 'SimpleXMLElement', LIBXML_NOCDATA);
        if($postObj->rspCode=='M000000'){
            $data['flag'] = 'success';
            $des3 = new IPSUtils($this->_key,$this->_iv);
            $result = $this->xmlToArray($des3->decrypt($postObj->p3DesXmlPara));
            $data['list'] = $result['body'];
        }else{
            $data['flag'] = 'error';
            $data['rspMsg'] = $postObj->rspMsg;
        }
        return $data;
    }

    /**
     * 更改用户信息
     * @author mss
     * @time 2017-11-26
     * param customerCode用户的客户号 必填
     */
    public function updateUserInfo($customerCode,$username,$identityNo,$mobiePhoneNo){
        $this->reqip = get_client_ip();
        $body = '<body>';
        $body .= '<customerCode>'.$customerCode.'</customerCode>';
        $body .= '<userName>'.$username.'</userName>';
        $body .= '<identityType></identityType>';
        $body .= '<identityNo>'.$identityNo.'</identityNo>';
        $body .= '<legalName></legalName>';
        $body .= '<legalCardNo></legalCardNo>';
        $body .= '<mobiePhoneNo>'.$mobiePhoneNo.'</mobiePhoneNo>';
        $body .= '<telPhoneNo></telPhoneNo>';
        $body .= '<email></email>';
        $body .= '<contactAddr></contactAddr>';
        $body .= '</body>';
        $signature = md5($body.$this->merCret);
        $updateUserInfoReqXml = '<updateUserInfoReqXml>';
        $updateUserInfoReqXml .= '<head>';
        $updateUserInfoReqXml .= '<version>v1.0.1</version>';
        $updateUserInfoReqXml .= '<reqIp>'.$this->reqip.'</reqIp>';
        $updateUserInfoReqXml .= '<reqDate>'.date('Y-m-d H:i:s').'</reqDate>';
        $updateUserInfoReqXml .= '<signature>'.$signature.'</signature>';
        $updateUserInfoReqXml .= '</head>';
        $updateUserInfoReqXml .= $body.'</updateUserInfoReqXml>';
        $des3 = new IPSUtils($this->_key,$this->_iv);
        $arg3 = $des3->encrypt($updateUserInfoReqXml);
        $xml = '<ipsRequest><argMerCode>'.$this->argMercode.'</argMerCode><arg3DesXmlPara>'.$arg3.'</arg3DesXmlPara></ipsRequest>';
        $request_url = "https://ebp.ips.com.cn/fpms-access/action/user/updateUserInfo";
        $post_data['ipsRequest'] = $xml;
        $res = $this->request_post($request_url,$post_data);
        $postObj = $this->xmlToArray($res);
        $data = array();
        if($postObj['rspCode']=='M000000'){
            $data['flag'] = 'success';
            $des3 = new IPSUtils($this->_key,$this->_iv);
            $result = $this->xmlToArray($des3->decrypt($postObj['p3DesXmlPara']));
            $data['data'] = $result['body'];
        }else{
            $data['flag'] = 'error';
            $data['rspMsg'] = $postObj['rspMsg'];
        }
        return $data;
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