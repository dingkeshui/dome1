<?php
namespace Admin\Controller;

use Think\Controller;
use Think\Huanxun;

/**
 * 会员管理
 */
class MemberController extends AdminBasicController
{
    public $Member = '';

    public function _initialize()
    {
        $this->checkLogin();
        $this->Member = D('Member');
    }

    /**
     * 显示会员列表
     */
    public function memberList()
    {
        //昵称查找
        $parameter = array();
        $where = array();
        $request = array();
        if (!empty($_REQUEST['nickname'])) {
            $like = trim(I('request.nickname'));
            $where['nick_name|account'] = array('LIKE', "%" . $like . "%");
            $parameter['nickname'] = $like;
            $request['nickname'] = $like;
            $this->assign("request", $request);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $where['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        if(!empty(I('request.recom_str'))){
            $where['recom_str'] = I('request.recom_str');
            $parameter['recom_str'] = I('request.recom_str');
        }
        if(I('request.m_id')){
            $where['m_id'] = I('request.m_id');
        }
        //按用户环信名称查找
        if(!trim(empty(I('request.hx_name')))){
            $where['hx_name'] = trim(I('request.hx_name'));
            $parameter['hx_name'] = trim(I('request.hx_name'));
            $request['hx_name'] = trim(I('request.hx_name'));
            $this->assign("request", $request);
        }
        S('member_w',$where);
        $order = "ctime desc";
        $where['status'] = array("neq", 9);
        $reslist = $this->Member->selectMember($where, $order, '15', $parameter);
        $meet_pay_price = M("Config")->getField("meet_pay_price");
        foreach ($reslist['list'] as $k=>$v){
            if(!empty($_REQUEST['nickname'])){
                $keyword = $_REQUEST['nickname'];
                $reslist['list'][$k]['nick_name'] = str_ireplace($keyword,"<font style='color:red;'>$keyword</font>",$v['nick_name']);
            }
            $reslist['list'][$k]['recommend_name'] = M('Member')->where(array('m_id'=>$v['recommend']))->limit(1)->getField("nick_name");
            $b = sprintf("%.2f",fmod($v['integral'],$meet_pay_price));
            $reslist['list'][$k]['earn_total'] = floatval($meet_pay_price)-floatval($b);
        }
        $this->assign('member', $reslist['list']);
        $this->assign('page', $reslist['page']);
        $pages = ceil(M('Member')->where($where)->count()/15);
        $this->assign('pages',$pages);
        $a_id = session("A_ID");
        if($a_id == 1){
            $this->assign("total",M("Member")->sum("wallet"));
        }
        $this->display("memberList");
    }

    /**
     * 收支明细列表
     */
    public function detailList()
    {
        $parameter['m_id'] = I("get.m_id");
        $w['m_id'] = I("get.m_id");
        $w['rank_type'] = 0;
        $this->assign("m_id", I("get.m_id"));
        $order = "ctime desc";
        $data = D("Bill")->selectBill($w, $order, "15",$parameter);
        $this->assign('list', $data['list']);
        $this->assign('page', $data['page']);
        $this->display('detailList');
    }

    /**删除收支明细*/
    public function deleteDetail()
    {
        if (empty($_REQUEST['d_id'])) {
            $this->error('您未选择任何操作对象');
        }
        $where['d_id'] = array('IN', I('request.d_id'));
        $data['status'] = 9;
        $upd_res = D("Detail")->where($where)->save($data);
        if ($upd_res) {
            $this->success('删除操作成功');
        } else {
            $this->error('删除操作失败');
        }
    }

    /**股数用户的股数*/
    public function selectEarn(){
        $parameter = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $parameter['start_time'] = I('request.start_time');
            $parameter['end_time'] = I('request.end_time');
        }
        $w['mix_id'] = $_GET['m_id'];
        $parameter['m_id'] = $_GET['m_id'];
        $w['type'] = 0;
        $order = "ctime desc";
        $list = D("Pie")->selectEarn($w,$order,15,$parameter);
        //dump(D("invest_earn")->getLastSql());
        $this->assign('list', $list['list']);
        $this->assign('page', $list['page']);
        $this->display('selectEarn');
    }
    
    /**
     * 删除操作
     */
    public function deleteMember()
    {
        if (empty($_REQUEST['m_id'])) {
            $this->error('您未选择任何操作对象');
        }
        $where['m_id'] = array('IN', I('request.m_id'));
        $data['status'] = 9;
        $upd_res = D("Member")->where($where)->save($data);
        if ($upd_res) {
            $this->success('删除操作成功');
        } else {
            $this->error('删除操作失败');
        }
    }

    /**
     *查看用户的详情操作
     */
    public function oneMember()
    {
        $where['m_id'] = $_GET['m_id'];
        $upd_res = D("Member")->where($where)->find();
        /**查看用户是否有推荐人*/
        $ww['m_id'] = $upd_res['recommend'];
        $province["area_id"] = $upd_res["province"];
        $city["area_id"] = $upd_res["city"];
        $area["area_id"] = $upd_res["area"];
        $res = D("Member")->where($ww)->find();
        $provinces = D("areas")->where($province)->find();
        $citys = D("areas")->where($city)->find();
        $areas = D("areas")->where($area)->find();
        $upd_res['province'] = $provinces["area_name"] ;
        $upd_res['city'] = $citys["area_name"];
        $upd_res['area'] =  $areas["area_name"] ;
        $upd_res['referrer'] = $res['nick_name'];

        /**找到开户信息*/
        $hx_mess = M("HxUser")->where(['type'=>0,'m_id'=>$_GET['m_id']])->find();
        $upd_res['hx_mess'] = $hx_mess;

        $this->assign("res", $upd_res);
        $this->display("oneMember");
    }

    /**
     *编辑用户信息
     */
    public function editMember()
    {
        if (!isset($_POST["id"])) {//展示编辑
            $where["m_id"] = $_GET["m_id"];
            $member = D("Member")->where($where)->find();
            $pro = M("areas")->where(array('parent_id' => 1))->select();
            $this->assign("pro", $pro);
            //获取城市
            $city = M("areas")->where(array('parent_id' => $member['province']))->select();
            $this->assign("city", $city);
            //获取区域
            $area = M("areas")->where(array('parent_id' => $member['city']))->select();
            $this->assign('member', $member);
            $this->assign("area", $area);
            $this->assign('m_id', $_GET["m_id"]);
            $this->display("editMember");
        } else {//编辑
            if (D("Member")->validate(D("Member")->edit)->create()) {
                $where["m_id"] = $_POST["id"];
                D("Member")->where($where)->save() ? $this->success("修改成功", U("editMember", array("m_id" => $_POST["id"]))) : $this->error("修改错误");
            } else {
                $this->error($this->Member->getError());
            }
        }
    }

    /**
     *重置用户密码操作
     */
    public function restPsw()
    {
        echo $_POST["m_id"];
        $where["m_id"] = $_GET["m_id"];
        $save["password"] = md5(123456);
        $save["utime"] = time();
        echo D("Member")->where($where)->save($save) ? 1 : 0;
    }

    /**导出用户excel的表单*/
    public function memberXLS()
    {
        /**查出来你要导出的数据*/
        
        $is_set = S("member_w");
        if($is_set){
            $w = $is_set;
        }
        $w['status'] = array('neq',9);

        $member = $this->Member->where($w)->select();
        $arrordername = array('昵称', '手机号', '推荐人', 'openID', '身份证号', '省级', '市级', '地区','积分', '钱包', '总的消费钱数','股数','满足股数相差金额', '注册时间', '状态', '最后登录时间', '最后登录IP','来源');
        //循环数据
        foreach ($member as $vo) {
            $where["m_id"] = $vo["recommend"];
            $res = D("Member")->where($where)->find();
            $res = $res ? $res["nick_name"] : "无";
            $status = $vo["status"] == 9 ? "删除" : "正常";
            $province_name = M('Areas')->where(array('area_id'=>$vo['province']))->getField('area_name');
            $province = $province_name?$province_name:'未选择';
            $city_name = M('Areas')->where(array('area_id'=>$vo['city']))->getField('area_name');
            $city = $city_name?$city_name:'未选择';
            $area_name = M('Areas')->where(array('area_id'=>$vo['area']))->getField('area_name');
            $area = $area_name?$area_name:'未选择';
            $arrorderlist[] = array($vo["nick_name"], $vo["account"],$res, $vo["openid"], $vo["ident"], $province, $city, $area,$vo['integral'],
                $vo["wallet"], $vo["total"],$vo['piles'] ,$vo['earn_total'], date("Y/m/d H:i:s", $vo["ctime"]), $status, date("Y/m/d H:i:s", $vo["last_login_time"]), $vo["last_login_ip"],$vo['recom_str']);
        }

        exportexcel($arrorderlist, $arrordername, '用户信息' . date("Y/m/d H.i.s"));

    }

    /**
     * ajax获取城市
     */
    public function ajaxCity()
    {
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("areas")->where($where)->select();
        echo json_encode($data);
    }

    /**
     * ajax获取区域
     */
    public function ajaxArea()
    {
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("areas")->where($where)->select();
        echo json_encode($data);
    }

    /**ajax上传图片*/
    public function uploadPic()
    {
        $pic = $_POST['pic'];
        $pic_name = $_POST['pic_name'];
        $temp = explode('.', $pic_name);
        $ext = uniqid() . '.' . end($temp);
        $base64 = substr(strstr($pic, ","), 1);
        $image_res = base64_decode($base64);
        $pic_link = "Uploads/Member/" . date('Y-m-d') . '/' . $ext;
        $saveRoot = "Uploads/Member/" . date('Y-m-d') . '/';
        /**检查目录是否存在  循环创建目录*/
        if (!is_dir($saveRoot)) {
            mkdir($saveRoot, 0777, true);
        }
        $res = file_put_contents($pic_link, $image_res);
        if ($res) {
            $ajaxData = array("flag" => "success", "message" => "上传成功！");
            $result_data['path'] = $pic_link;
            $ajaxData['data'] = $result_data;
            $this->ajaxReturn(json_encode($ajaxData));
        } else {
            $ajaxData = array("flag" => "error", "message" => "上传失败", "data" => array());
            $this->ajaxReturn(json_encode($ajaxData));
        }
    }

    /**删除上传的乡相册的图片*/
    public function delPhoto()
    {
        $file = $_POST['file_path'];
        $result = @unlink($file);
        if ($result == false) {
            $this->ajaxReturn(0);
        } else {
            $this->ajaxReturn(1);
        }
    }
    
    /**
     * 用户或商家的提现信息
     * @author mss
     * @time 2017-10-14
     * param
     * param
     * param
     * param
     * param
     * param
     */
    public function withdrawList(){
        $mix_id = $_REQUEST['mix_id'];
        $type = $_REQUEST['type']!=''?$_REQUEST['type']:0;
        $p = $_REQUEST['p'];
//        $custCode = $this->setcustomerCode($mix_id,$type);
        $custCode = M('HxUser')->where(array('m_id'=>$mix_id,'type'=>$type))->getField('customer_code');
        if(!$custCode){
            $this->error('用户未开户');
        }
        if($type==1){
            $title = '商家管理';
            $url = U('Shop/shopList');
        }else{
            $title = '会员管理';
            $url = U('Member/memberList');
        }
        $ordersn = $_REQUEST['ordersn'];
        $ipsorder = $_REQUEST['ipsorder'];
        $ordertype = $_REQUEST['ordertype'];
        $starttime = $_REQUEST['start_time'];
        $endtime = $_REQUEST['end_time'];
        if($endtime>date('Ymd')){
            $endtime = date('Ymd');
        }

        $list = array();
        $huanxun = Huanxun::getInstance();
        $result = $huanxun->queryResult($custCode,$ordertype,$ordersn,$ipsorder,$starttime,$endtime,$p,10);

        if($result['flag']=='success'&&!empty($result['list']['orderDetails'])){
            $is_array = $result['list']['orderDetails']['orderDetail'][0];
            if(!is_array($is_array)){
                $list[] = $result['list']['orderDetails']['orderDetail'];
            }else{
                $list = $result['list']['orderDetails']['orderDetail'];
            }
        }else{
            echo $result['rspMsg'];
           // $this->error($result['rspMsg']);
        }

        $this->assign('list',$list);

        $totalpage = $result['list']['totalPage']?$result['list']['totalPage']:'0';
        $nowpage = $result['list']['currrentPage']?$result['list']['currrentPage']:'0';
        $count = $result['list']['totalCount']?$result['list']['totalCount']:'0';

        $this->assign('title',$title);
        $this->assign('url',$url);
        $this->assign('request',$_REQUEST);
        $this->assign('start_time',$starttime);
        $this->assign('end_time',$endtime);
        $this->assign('totalpage',$totalpage);
        $this->assign('nowpage',$nowpage);
        $this->assign('count',$count);

        $this->display('withdrawList');
    }

}