<?php

namespace M\Controller;
use Think\Controller;


/**
 * Class AdminBasicController
 * @package Admin\Controller
 * 父类  添加登陆验证  权限等
 */
class AdminBasicController extends Controller {
    private $app_key_shop = '9e9914fd6b0c6e84892c508c';
    private $master_secret_shop = 'ab49d57d1b8d7af14051c7c7';
    private $app_key_mem = '72112b6f9ba0a0b0ef29860d';
    private $master_secret_mem = '6ccdf826b4cf1362638f43be';

    /**
     * 初始化
     */
    public function _initialize(){

    }

    /**
     * 判断登陆
     */
    public function checkLogin(){
        session('[regenerate]');   // 重新生成sessionID
        $session = session('SHOP_ID');
        if(empty($session)){
            //redirect(U('Manager/newLogin'));
            /**判断登录*/
            echo '<script>var url = "/index.php/M/Manager/newLogin"; window.top.location = url;</script>';
            exit;
        }
    }




    /**
     * 检查权限
     * @param string $model
     * @param string $method
     * @param bool $ajax
     */
    function checkAuth($model = '', $method = '', $ajax = false){
        //参数为空时 无权限
        if(empty($model) || empty($method)){
            if(!$ajax){
                $this->error('没有权限');exit;
            }else{
                $this->ajaxMsg('error','没有权限');
            }
        }else{
            //对管理员的操作 只有超级管理员能够进行
            $method_arr = array('addAdmin','editAdmin','deleteAdmin','editGroup','addGroup','deleteGroup');
            if(session('A_ID') != 3 && in_array($method,$method_arr)){
                if(!$ajax){
                    $this->error('没有权限');exit;
                }else{
                    $this->ajaxMsg('error','没有权限');
                }
            }
            if(session('A_ID') != 1){
                $action = D('AdminAction');
                $action_id = $action->where(array('model'=>$model,'method'=>$method))->getField('action_id');
                if(!empty($action_id)){
                    $admin = D('Admin');
                    //获取当前管理员组ID
                    $group_id = $admin->where(array('a_id'=>session('A_ID')))->getField('group_id');
                    //获取该组的权限
                    $group = D('AdminGroup');
                    $permission = $group->where(array('group_id'=>$group_id,'status'=>0))->getField('permission');
                    $permission = unserialize($permission);
                    //判断是否有权限
                    if(!in_array($action_id,$permission)){
                        if(!$ajax){
                            $this->error('没有权限');exit;
                        }else{
                            $this->ajaxMsg('error','没有权限');
                        }
                    }
                }
            }
        }
    }

    /**
     * 编辑后返回列表跳转路径设置
     */
    public function setEditBack($url){
        cookie("EDIT_BACK",$url,array('expire'=>36000));
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
     * 分页配置信息
     */
    public function getPageNumber(){
        $page_number = D('Config')->where(array('conf_id'=>1))->getField('page_number');
        return $page_number;
    }

    /**发送信息*/
    public function sendMsg($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '38564', //您申请的短信模板ID，根据实际情况修改
            'tpl_value' =>"#code#=$body&#company#=众享通赢" //您设置的模板变量，根据实际情况修改
        );

        $this->juhecurl($sendUrl,$smsConf,1); //请求发送短信
    }
    /**发送信息*/
    public function sendMsgGoods($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '38720', //您申请的短信模板ID，根据实际情况修改
            'tpl_value' =>"#code#=$body&#company#=众享通赢" //您设置的模板变量，根据实际情况修改
        );

        $this->juhecurl($sendUrl,$smsConf,1); //请求发送短信
    }

    /**发送信息*/
    public function sendMsgReturnGoods($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '40113', //您申请的短信模板ID，根据实际情况修改
            'tpl_value' =>"#code#=$body&#company#=众享通赢" //您设置的模板变量，根据实际情况修改
        );

        $this->juhecurl($sendUrl,$smsConf,1); //请求发送短信
    }



    /**发送信息*/
    public function sendMsgFeed($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '45112', //您申请的短信模板ID，根据实际情况修改
            'tpl_value' =>"#code#=$body&#company#=众享通赢" //您设置的模板变量，根据实际情况修改
        );

        $this->juhecurl($sendUrl,$smsConf,1); //请求发送短信
    }


    /**
     * 发送信息(商家审核通过之后发生短信)
     * @author crazy
     * @time 2017-10-09
     */
    public function sendMsgStatus($tel,$body){
        $sendUrl = 'http://v.juhe.cn/sms/send'; //短信接口的URL

        $smsConf = array(
            'key'   => 'cdc9f5868e431340cf6fcdf6cb86c102', //您申请的APPKEY
            'mobile'    => "$tel", //接受短信的用户手机号码
            'tpl_id'    => '47223', //您申请的短信模板ID，根据实际情况修改
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

    /**
     * 添加行为日志
     */
    protected function addActionLog($title = '', $table_name = '', $record_id = 0, $remark = '') {
        $data['a_id']       = session('A_ID');
        $data['account']    = session('A_ACCOUNT');
        $data['title']      = $title;
        $data['table_name'] = C('DB_PREFIX').$table_name;
        $data['record_id']  = $record_id;
        $data['remark']     = empty($remark) ? '操作url：'.$_SERVER['REQUEST_URI'] : $remark;
        $data['ctime']      = time();

        $r = M('ActionLog')->data($data)->add();

        if($r) {
            return true;
        } else {
            return false;
        }
    }
    /**
     * 单图片上传图片
     */
    public function uploadImg($file,$name){
        $config = array(
            'subName'    =>    array('date','Ym'), //设置文件名
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
     * 上传图片
     */
    public function uploadImgMore($file){
        $config = array(
            'subName'    =>    array('date','Ym'), //设置文件名
        );
        $upload = new \Think\Upload($config);// 实例化上传类
        $upload->maxSize   =     3145728 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
        $upload->savePath  =      "$file/"; // 设置附件上传目录    // 上传文件
        $info   =   $upload->upload();
        return $info;
    }


    /**封装用户的分页方法*/
    /**分页*/
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
     * 获取接口信息，这个是万能的调用微信api方法的函数，可以自定的加载url，然后返回你想得到的数据
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

    /**获取access_token，封装好的一个方法，可以利用缓存，减少我们调用的频次*/
    function wx_get_token() {
        $token_other = S('other_access_token_z');
        if (!$token_other) {
            $appid = "wx323d496753eb8b10";
            $appsecret = "ec3ecb2394f95046745749801119f6f7";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret";
            $output = $this->httpsRequest($url);
            $res = json_decode($output, true);
            $token_other = $res['access_token'];
            // 注意：这里需要将获取到的token缓存起来（或写到数据库中）
            // 不能频繁的访问https://api.weixin.qq.com/cgi-bin/token，每日有次数限制
            // 通过此接口返回的token的有效期目前为2小时。令牌失效后，JS-SDK也就不能用了。
            // 因此，这里将token值缓存1小时，比2小时小。缓存失效后，再从接口获取新的token，这样
            // 就可以避免token失效。
            // S()是ThinkPhp的缓存函数，如果使用的是不ThinkPhp框架，可以使用你的缓存函数，或使用数据库来保存。
            S('other_access_token_z', $token_other, 3600);
        }
        return $token_other;
    }


    /**
     * method:用户和商家的信息
     * @param $m_id 用户的id
     * $title 发信息的标题
     * $content 发信息的内容
     * $id_type 身份
     * $price 是否有钱
     * @time 2017-07-20
     * @author crazy
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
     * method:用户和商家的账单的明细
     * @param $m_id 用户的id
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
     * @time 2017-07-20
     * @author crazy
     */
    public function addBill($m_id,$shop_id,$title,$content,$price,$other_price,$monitor,$pay_type,$name,$type,$id_type,$accept_m_id,$rank_type,$total_price){
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
        $data['ctime'] = time();
        $res = M("Bill")->add($data);
        return $res;
    }


    /**
     * 添加新用户的优惠券信息
     * @author crazy
     * @time 2017-08-29
     * @param id优惠券的id集合
     * @param m_id用户的id
     */
    public function add_coupon($id,$m_id){
        /**给用户添加优惠券*/
        $c_w['coupon_id'] = array('in',"$id");
        $c_w['status'] = array('neq',9);
        $c_list = M("Coupon")->where($c_w)->field('coupon_id')->select();
        foreach ($c_list as $k=>$v){
            $add_mem_cou['coupon_id'] = $v['coupon_id'];
            $add_mem_cou['m_id'] = $m_id;
            $add_mem_cou['ctime'] = time();
            M("CouponMember")->add($add_mem_cou);
        }
    }

    /**
     * 极光推送
     * @time 2017-09-10
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
     * 极光推送，推送给所有人
     * @param $con
     */
    public function jPushToAll($con,$extra,$type){
        //调用极光推送
//        $extra['m_id']     = '';
        $this->push1($con,$extra,$type);
    }

    public function push1($con,$extra,$type){
        if($type==1) {
            $client = new \JPush($this->app_key_shop, $this->master_secret_shop);
        }else{
            $client = new \JPush($this->app_key_mem, $this->master_secret_mem);
        }
        $result = $client->push()
            ->setPlatform('all')
            ->addAllAudience()
            ->setNotificationAlert($con)
            ->addIosNotification($con, 'iOS sound','+1',true, 'iOS category', $extra)  //这句是为ios加的声音设置
            ->setOptions(100000, 0, null, false)
            ->send();
    }

}