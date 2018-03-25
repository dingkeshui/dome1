<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 微信开发
 */
class WechatController extends AdminBasicController {

    public function _initialize(){

    }


    /**验证微信----开始*/
    public function initWechat(){
        define("TOKEN", "zxty");
        //$this->valid();
        $this->responseMsg();
    }

    public function valid()
    {
        $echoStr = $_GET["echostr"];
        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }

    public function responseMsg(){
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];//返回回复数据
        if (!empty($postStr)) {
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $keyword = trim($postObj->Content);
            if(!empty( $keyword ))
            {
//                if($keyword == '加盟商'){
//                    $w['status'] = array('neq',9);
//                    $w['type'] = 1;
//                    $list = M("Wechat")->where($w)->field('w_id,url,pic,title,desc')->select();
//                    $content = array();
//                    foreach ($list as $k=>$v){
//                        $pic_url = "https://".$_SERVER['SERVER_NAME'].'/Uploads/'.$v['pic'];
//                        $content[] = array("Title"=>$v['title'],  "Description"=>$v['desc'], "PicUrl"=>$pic_url, "Url" =>$v['url']);
//                    }
//                    echo $this->transmitPic($postObj,$content);
//                }elseif($keyword == '教师'){
//                    $res = M("Return")->where(array('t_id'=>2))->limit(1)->getField('content');
//                    $contentStr = $res;
//                    echo $this->transmitText($postObj,$contentStr);
//                }
                $keyword_trim = trim($keyword);
                if(in_array($keyword_trim,[1,2,3,4,5,6,7,8,9])){
                    $contentStr = trim(M("Mater")->where(['status'=>0])->order("rand()")->getField("mer_id"));
                    //file_put_contents('mater.txt',M("Mater")->getLastSql());
                    echo $this->transmitImage($postObj,$contentStr);
                }
                //$msg_info = M('Wechat')->where(array('keywords'=>array('like',trim($keyword).'%'),'status'=>array('NEQ',9)))->field('title,desc,url,pic,content,m_type')->find();
                $row = M()->query("SELECT * FROM `zxty_wechat` WHERE FIND_IN_SET('$keyword_trim',keywords) or keywords like '%$keyword_trim%'");
                $msg_info = $row[0];
                if($msg_info&&$msg_info['m_type']==1){
                    echo $this->transmitText($postObj,$msg_info['content']);
                }elseif($msg_info&&$msg_info['m_type']==2){
                    $content = array(0=>array("Title"=>$msg_info['title'],"Description"=>$msg_info['desc'],"PicUrl"=>"https://".$_SERVER['SERVER_NAME']."/Uploads/".$msg_info['pic'],"Url"=>$msg_info['url']));
                    echo $this->transmitPic($postObj,$content);
                }

            }
            $fromUsername = $postObj -> FromUserName;   //消息的来源
            $key = $postObj -> EventKey;                //点击事件的键值
            $MsgType = $postObj->MsgType;//消息类型
            if($MsgType=='event') {
                $MsgEvent = $postObj->Event;//获取事件类型
                if ($MsgEvent=='subscribe') {
                    //获取用户微信信息
                    $access_token = wx_get_token();
                    $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$fromUsername&lang=zh_CN";
                    $res_wechat_json = $this->httpsRequest($url);
                    /**记录错误信息*/
                    write_log("wechat.txt",$res_wechat_json);
                    $res_wechat = json_decode($res_wechat_json,true);
                    if($res_wechat['errcode'] == '40001'){
                        S('other_access_token_hb_v2',null);
                        $access_token = wx_get_token();
                        $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$access_token&openid=$fromUsername&lang=zh_CN";
                        $res_wechat_json = $this->httpsRequest($url);
                        /**记录错误信息*/
                        write_log("wechat_40001.txt",$res_wechat_json);
                        $res_wechat = json_decode($res_wechat_json,true);
                    }
                    //判断用户是否已经是平台会员
                    $is_register = M("Member")->where(array('openid'=>"$fromUsername",'status'=>array('neq',9)))->field('openid,status')->find();
                    if($is_register){
                        $contentStr = "您已经是众享通赢会员了，无需再扫码了";
                        echo $this->transmitText($postObj,$contentStr);
                        //更新用户的关注时间
                        M("Member")->where(array('openid'=>"$fromUsername",'status'=>array('neq',9)))->limit(1)->setField('subscribe_time',$res_wechat['subscribe_time']);
                    }else{
                        /**数据库找文字*/
                        $w['keywords'] = "subscribe";
                        $w['status'] = array("neq",9);
                        $res = M("Return")->where($w)->limit(1)->find();
                        $contentStr = $res['content'];
                        echo $this->transmitText($postObj,$contentStr);
                        /**添加用户*/
                        /**获取一下场景的id*/
                        $sceneid = str_replace("qrscene_","",$key);

                        $z_is_member = M("Member")->where(array('openid'=>$res_wechat['openid'],'status'=>array('neq',9)))->field('m_id')->find();
                        if(empty($z_is_member) && !empty($sceneid)){
                            $data['openid'] = $res_wechat['openid']?$res_wechat['openid']:"0";
                            $data['nick_name'] = $res_wechat['nickname']?$res_wechat['nickname']:"众享通赢(扫码)_".mt_rand(10000,99999);
                            if($sceneid == 'zxty20171y'){
                                $data['integral'] = 0;
                                $data['recom_str'] = $sceneid?$sceneid:"0";
                            }else{
                                $is_set = M("Member")->where(array('m_id'=>$sceneid,'status'=>array('neq',9)))->field('m_id')->find();
                                if($is_set){
                                    $data['recommend'] = $sceneid?$sceneid:"0";
                                }else{
                                    $data['recom_str'] = $sceneid?$sceneid:"0";
                                }
                            }
                            $data['unionid'] = $res_wechat['unionid']?$res_wechat['unionid']:"0";
                            $data['sex'] = $res_wechat['sex']?$res_wechat['sex']:"0";
                            $data['head_pic'] = $res_wechat['headimgurl']?$res_wechat['headimgurl']:"/Uploads/logo.png";
                            $data['ctime'] = time();
                            $data['last_login_time'] = time();
                            $data['last_login_ip'] = get_client_ip();
                            $data['subscribe_time'] = $res_wechat['subscribe_time']?$res_wechat ['subscribe_time']:"0";
                            $add_member_res = M("Member")->add($data);
                            unset($data);
                            if($sceneid == 'chuandan' && !empty($res_wechat['openid'])){
                                /**2017-08-29第一次商家活动赠送（传单标识）*/
                                $data['recom_str'] = $sceneid?$sceneid:"0";
                                M('Member')->where(array('m_id'=>$add_member_res))->limit(1)->save($data);
                                /**给用户添加优惠券*/
                                $this->add_coupon('1,5,7,8,9,10,11',$add_member_res);
                            }
                        }
                    }
                }elseif ($MsgEvent=='CLICK') {
                    //点击事件
                    $EventKey = $postObj->EventKey;//菜单的自定义的key值，可以根据此值判断用户点击了什么内容，从而推送不同信息
                    switch($EventKey)
                    {
                        case "V1001_TODAY_MUSIC" :
                            //要返回相关内容
                            break;
                        case "V1001_TODAY_SINGER" :
                            //要返回相关内容
                            break;
                        case "V1001_HELLO_WORLD" :
                            //要返回相关内容
                            break;
                        case "handbook" :
                            $msg_info = M('Wechat')->where(array('keywords'=>array('like',trim("handbook").'%'),'status'=>array('NEQ',9)))
                                ->field('title,desc,url,pic,content,m_type')->find();
                            $content = array(0=>array("Title"=>$msg_info['title'],
                                "Description"=>$msg_info['desc'],"PicUrl"=>"https://".$_SERVER['SERVER_NAME']."/Uploads/".$msg_info['pic'],"Url"=>$msg_info['url']));
                            echo $this->transmitPic($postObj,$content);
                    }
                }
            }
        } else {
            echo '没有任何消息传递';
        }
    }




    private function checkSignature()
    {
        // you must define TOKEN by yourself
        if (!defined("TOKEN")) {
            throw new Exception('TOKEN is not defined!');
        }
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
    /**验证微信----结束*/

    /**获取微信的永久素材的列表*/
    public function materList(){
        $token = wx_get_token();
        if($token['errcode'] == '40001') {
            S('other_access_token_hb_v2', null);
            $token = wx_get_token();
        }
        $url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=$token";
        /**获取永久素材的总数*/
        $url_c = "https://api.weixin.qq.com/cgi-bin/material/get_materialcount?access_token=$token";
        $count = $this->httpsRequest($url_c);
//        $arr_count = json_decode($count,true);
        $p = empty($_GET['p'])?1:$_GET['p'];
        $a = ($p-1)*20;
        //$json = "{'type':'$type,'offset':".$a.",'count':1}";
        $json = '{"type":"image","offset":'."$a".',"count":10}';
        $res = $this->httpsRequest($url,$json);
        $arr = json_decode($res,true);
        //dump($arr);
        $this->assign("list",$arr['item']);
        //$media_id = $arr['item'][0]['media_id'];
        $this->display("materList");

    }

    /**获取单条详情*/
    public function detailMater(){
        /**获取详情*/
        $token = $this->wx_get_token();
        $url1 = "https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=$token";
        $a = I('get.media_id');
        $json1 = '{"media_id":"'.$a.'"}';
        $res1 = $this->httpsRequest($url1,$json1);
        $detail = json_decode($res1,true);
        preg_match_all('/data-src=\"\(.*?)\"/',$detail['news_item']["content"],$match);
        foreach($match[1] as $key => $src){
            if(!strpos($src,'://')){
                $content = str_replace('/'.$src,'http://'.$_SERVER['HTTP_HOST']."/".$src."\" width=80% height=auto" , $detail['news_item']["content"]);
            }
        }
        $detail['news_item']["content"] = $content;
        //dump($detail);
        $this->assign("res",$detail);
        $this->display("detailMater");
    }




    private function transmitText($object, $content)
    {
        $msgType = "text";
        $textTpl = "<xml>
         <ToUserName><![CDATA[%s]]></ToUserName>
         <FromUserName><![CDATA[%s]]></FromUserName>
         <CreateTime>%s</CreateTime>
         <MsgType><![CDATA[%s]]></MsgType>
         <Content><![CDATA[%s]]></Content>
         <FuncFlag>0</FuncFlag>
         </xml>";
        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName,time(),$msgType, $content);
        return $result;
    }

    private function transmitImage($object, $content)
    {
        $textTpl = "<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[%s]]></MsgType>
                <Image>
                <MediaId><![CDATA[%s]]></MediaId>
                </Image>
                <FuncFlag>0</FuncFlag>
                </xml>";

        $msgType = "image"; //消息类型
//        $contentStr = '填写你上传图片的MediaID'; //返回消息内容

        //格式化消息模板
        $resultStr = sprintf($textTpl,$object->FromUserName,$object->ToUserName,time(),$msgType,$content);
        return $resultStr; //输出结果
    }

    private function transmitPic($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return "";
        }
        $itemTpl = "<item>
                    <Title><![CDATA[%s]]></Title>
                    <Description><![CDATA[%s]]></Description>
                    <PicUrl><![CDATA[%s]]></PicUrl>
                    <Url><![CDATA[%s]]></Url>
                    </item>";
        $item_str = "";
        foreach ($newsArray as $item){
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $xmlTpl = "<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[news]]></MsgType>
                    <ArticleCount>%s</ArticleCount>
                    <Articles>
                    $item_str</Articles>
                   </xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }

    //回复多客服消息
    private function transmitService($object)
    {
        $xmlTpl = "<xml>
         <ToUserName><![CDATA[%s]]></ToUserName>
         <FromUserName><![CDATA[%s]]></FromUserName>
         <CreateTime>%s</CreateTime>
         <MsgType><![CDATA[transfer_customer_service]]></MsgType>
         </xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    /**
     * 场景二维码列表
     * @author mss
     * @time 2017-08-16
     */
    public function codeList(){
        $where['status'] = array('neq',9);
        $list = D('Code')->selectCode($where,"ctime desc",15);
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("codeList");
    }
    /**
     * 添加场景二维码
     * @author mss
     * @time 2017-08-16
     */
    public function addCode(){

        $this->display("addCode");
    }

    /**
     * 下载场景二维码
     * @author mss
     * @time 2017-08-16
     */
    public function downLoadCode(){
        $w['c_id'] = $_GET['c_id'];
        $res = M("Code")->where($w)->find();
        $file =  C('API_URL')."/".$res['img'];
        $name = $res['string'];
        header("Content-type: octet/stream");
        header('Content-Disposition: attachment; filename="' . $name . '.jpg"');
        header("Content-Length: ". filesize($file));
        readfile($file);
    }

    /**
     * 生成带参的二维码
     * @author crazy
     * @time 2017-08-16
     * @param string 二维码参数
     */
    public function newCode(){
        $string = $_POST['string'];
        $access_toke = wx_get_token();
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$access_toke";
        $qrcode = '{"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str": "'.$string.'"}}}';
        $res = $this->httpsRequest($url,$qrcode);
        $last_access = json_decode($res,true);
        if($last_access['errcode'] == '40001'){
            S('other_access_token_hb','');
            $access_toke = wx_get_token();
            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$access_toke";
            $qrcode = '{"action_name": "QR_LIMIT_STR_SCENE", "action_info": {"scene": {"scene_str": "'.$string.'"}}}';
            $res = $this->httpsRequest($url,$qrcode);
        }
        $res_ticket = json_decode($res, true);
        $ticket_other = $res_ticket['ticket'];
        $url1 = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($ticket_other);
        $imageInfo = $this->downloadWeixinFile($url1);

        $filename = "Uploads/Code/".$string.'.jpg';
        $local_file = fopen($filename,'w');
        $res = fwrite($local_file,$imageInfo['body']);
        fclose($local_file);
        if($res){
            $data['string'] = $_POST['string'];
            $data['img'] = $filename;
            $data['ctime'] = time();
            $rs = M("Code")->add($data);
            if($rs){
                $this->success('创建成功',U('Wechat/codeList'));
            }else{
                $this->error('创建失败');
            }
        }else{
            $this->error('创建失败');
        }
    }

    /**
     * 下载图片
     * @author crazy
     * @time 2017-07-03
     */
    public function downloadWeixinFile($url){
        $ch  = curl_init($url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch,CURLOPT_NOBODY,0); //只取body头
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        $package = curl_exec($ch);
        $httpinfo = curl_getinfo($ch);
        curl_close($ch);
        $imageAll = array_merge(array('body'=>$package),array('header'=>$httpinfo));
        return $imageAll;
    }

}