<?php
namespace Admin\Controller;
use Think\Controller;
use Think\Huanxun;
/**
 * 转账
 */
class TransferController extends AdminBasicController {

    public function _initialize(){
      
    }


    /**@从ips账号转账到个人账户列表
     * @author crazy
     * @time 2017-12-13
     */
    public function transfer(){
        $parameter = array();
        $request = array();
        $where = array();
        /**客户号*/
        if(!empty($_REQUEST['ips_account'])){
            $where['ips_account'] = array('LIKE',"%".I('request.ips_account')."%");
            $parameter['ips_account'] = I('request.ips_account');
            $request['ips_account'] = I('request.ips_account');
        }
        /**真实姓名*/
        if(!empty(I('request.remark_account'))){
            $where['remark_account'] = array('LIKE','%'.I('request.remark_account').'%');
            $parameter['remark_account'] = I('request.remark_account');
            $request['remark_account'] = I('request.remark_account');
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $request['start_time'] = $start_time;
            $end_time = I('request.end_time');
            $request['end_time'] = $end_time;
            $where['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        $this->assign("request",$request);
        S('tarns_w',$where);
        $list = D("hx_transfer")->selectHxTransfer($where,"ctime desc",15,$parameter);
        foreach ( $list['list'] as $k=>$v) {
            if(!empty($_REQUEST['ips_account'])){
                $keyword = $_REQUEST['ips_account'];
                $list['list'][$k]['ips_account'] = str_ireplace($keyword,"<font style='color:red;'>$keyword</font>",$v['ips_account']);
            }
            if(!empty($_REQUEST['remark_account'])){
                $keyword = $_REQUEST['remark_account'];
                $list['list'][$k]['remark_account'] = str_ireplace($keyword,"<font style='color:red;'>$keyword</font>",$v['remark_account']);
            }
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("transferList");
    }


    /**@从ips账号转账到个人账户
     * @author crazy
     * @time 2017-12-13
     */
    public function addTransfer(){
        if(!IS_POST){
            $this->display("addTransfer");
        }else{
            M()->startTrans();
            if(I('post.ips_yan') != 'htcz_ips'){
                $this->error("操作失败");
            }
            $w['username'] = I('post.remark_account');
            $w['customer_code'] = I('post.ips_account');
            if(empty(M("HxUser")->where($w)->find())){
                $this->error("ips账号和真实姓名不匹配，请核对！");
            }
            $data = M("HxTransfer")->create();
            $data['ctime'] = time();
            $res = M("HxTransfer")->add($data);
            $res_hx = $this->transferData(I('post.ips_account'),I('post.price'));
            /**添加财务记录*/
//            unset($data);
//            $data['price'] = -I('post.price');
//            $data['ctime'] = time();
//            $data['type'] = 2;
//            $price_res = M("Price")->add($data);
            //file_put_contents('zhuanZ.txt',M("Price")->getLastSql());
            if($res && $res_hx){
                M()->commit();
                $this->success("转账成功",U("Transfer/transfer"));
            }else{
                M()->rollback();
                $this->error("转账失败");
            }
        }
    }


    /**@从ips账号转账到个人账户（转账操作）
     * @author crazy
     * @time 2017-12-13
     */
    public function transferData($user_code,$price)
    {
        $item_name = '提现';        //付款项目名称
        $data['withdraw_sn'] = date('YmdHis') . mt_rand(100000, 999999);
        $requesturl = 'https://ebp.ips.com.cn/fpms-access/action/trade/transfer.do';    //转账接口地址
        $huanxun = Huanxun::getInstance();
        $xml = $huanxun->transfer($data['withdraw_sn'], $user_code, $price, $item_name);
        $data_post['ipsRequest'] = $xml;
        /**调用转账接口*/
        $res_trans = $this->request_post($requesturl, $data_post);
        $postObj = simplexml_load_string($res_trans, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($postObj->rspCode == 'M000000') {
            return true;
        }else{
            return false;
        }
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



    /**@从ips账号转账到个人账户列表
     * @author crazy
     * @time 2017-12-13
     */
    public function hxUserList(){
        $parameter = array();
        $request = array();
        $where = array();
        /**客户号*/
        if(!empty($_REQUEST['customer_code'])){
            $where['customer_code'] = array('LIKE',"%".I('request.customer_code')."%");
            $parameter['customer_code'] = I('request.customer_code');
            $request['customer_code'] = I('request.customer_code');
        }
        /**真实姓名*/
        if(!empty(I('request.username'))){
            $where['username'] = array('LIKE','%'.I('request.username').'%');
            $parameter['username'] = I('request.username');
            $request['username'] = I('request.username');
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $request['start_time'] = $start_time;
            $end_time = I('request.end_time');
            $request['end_time'] = $end_time;
            $where['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        $this->assign("request",$request);
        S('user_w',$where);
        $list = D("hx_user")->selectHxUser($where,"ctime desc",15,$parameter);
        foreach ( $list['list'] as $k=>$v) {
            if(!empty($_REQUEST['customer_code'])){
                $keyword = $_REQUEST['customer_code'];
                $list['list'][$k]['customer_code'] = str_ireplace($keyword,"<font style='color:red;'>$keyword</font>",$v['customer_code']);
            }
            if(!empty($_REQUEST['username'])){
                $keyword = $_REQUEST['username'];
                $list['list'][$k]['username'] = str_ireplace($keyword,"<font style='color:red;'>$keyword</font>",$v['username']);
            }
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("hxUser");
    }

    /**
     * 导出客户号列表的excel
     * @time 2018-01-31
     * @author mss
     */
    public function hxUserXSL(){
        $arrusername = array('编号','注册时间','客户号','真实姓名','手机号','身份');
        $where = S('user_w');
        $list = M('HxUser')->where($where)->select();
        foreach($list as $k=>$v){
            $time = ' '.date('Y-m-d H:i:s',$v['ctime']);
            if($v['type']==1){
                $type = '商家';
            }else{
                $type = '用户';
            }

            $arruserlist[] = [$v['h_id'],$time,$v['customer_code'],$v['username'],$v['mobiephone'],$type];
        }
        exportexcel($arruserlist,$arrusername,'客户号列表'.date("Y-m-d"));
    }


    /**
     * 导出转账记录列表
     * @time 2018-01-31
     * @author mss
     */
    public function transferXSL(){
        $arrname = array('编号','添加时间','接收人ips账号（客户号）','真实姓名','钱数','操作人','备注');
        $where = S('tarns_w');
        $list = M('HxTransfer')->where($where)->select();
        foreach($list as $k=>$v){
            $time = ' '.date('Y-m-d H:i:s',$v['ctime']);

            $arrlist[] = [$v['i_id'],$time,$v['ips_account'],$v['remark_account'],$v['price'],$v['op_person'],$v['remark']];
        }
        exportexcel($arrlist,$arrname,'转账记录列表'.date("Y-m-d"));
    }


}