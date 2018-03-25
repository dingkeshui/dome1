<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class AddressController
 * @package Api\Controller
 * 用户收货地址,商家退货地址
 */
class AddressController extends ApiBasicController{
    public function _initialize(){
        parent::_initialize();

    }
    /**
     * 地址列表
     * 传参方式 post
     * @author mss
     * @time 2017-11-14
     * @param mix_id 用户id
     * @param type 用户类型，0用户，1商家
     * @param p 页数
     */
    public function addressList(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $type = $_POST['type']?$_POST['type']:0;
        $mix_id = $_POST['mix_id'];
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $where['status'] = array('NEQ',9);
        $where['mix_id'] = $mix_id;
        $where['type'] = $type;
        $list = M('Address')->where($where)->field('addr_id,name,phone,province,city,area,address,is_default')->order('is_default DESC,ctime DESC')->limit($page,10)->select();
        foreach($list as $k=>$v){
            $list[$k]['province'] = M('Areas')->where(array('area_id'=>$v['province']))->limit(1)->getField('area_name');
            $list[$k]['city'] = M('Areas')->where(array('area_id'=>$v['city']))->limit(1)->getField('area_name');
            $list[$k]['area'] = M('Areas')->where(array('area_id'=>$v['area']))->limit(1)->getField('area_name');
        }
        $arr = $this->getListCodeMessage($list,$_POST['p']);
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }

    /**
     * 地址详情
     * 传参方式 get
     * @author mss
     * @time 2017-11-14
     * @param addr_id 地址id
     */
    public function addressInfo(){
        if(empty($_GET['addr_id'])){
            apiResponse('error','参数错误');
        }
        $areas_obj = M('Areas');
        $a_id = $_GET['addr_id'];
        $info = M('Address')->where(array('addr_id'=>$a_id))->field('addr_id,name,phone,province,city,area,address,is_default')->find();
        $proname = $areas_obj->where(array('area_id'=>$info['province']))->limit(1)->getField('area_name');
        $info['pro_name'] = $proname?$proname:'';
        $city = $areas_obj->where(array('area_id'=>$info['city']))->limit(1)->getField('area_name');
        $info['city_name'] = $city?$city:'';
        $area = $areas_obj->where(array('area_id'=>$info['area']))->limit(1)->getField('area_name');
        $info['area_name'] = $area?$area:'';
        apiResponse('success','成功',$info);
    }

    /**
     * 添加地址
     * 传参方式 post
     * @author mss
     * @time 2017-11-14
     * @param mix_id 用户id
     * @param type 用户类型 0用户，1商家
     * @param name 姓名
     * @param phone 手机号
     * @param province 省id
     * @param city 市id
     * @param area 区县id
     * @param address 具体地址
     * @param is_default 是否默认，0否，1默认
     */
    public function addAddress(){
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $type = $_POST['type']?$_POST['type']:0;
        $tel = $_POST['phone'];
        if(!preg_match(C('MOBILE'),$tel)) {
            apiResponse("error","手机格式不正确！");
        }
        $default = $_POST['is_default'];
        if($default==1){
            M('Address')->where(array('type'=>$type,'mix_id'=>$_POST['mix_id']))->save(['is_default'=>0]);
        }
        $data = $_POST;
        $data['type'] = $type;
        $data['ctime'] = time();
        $res = M('Address')->add($data);
        if($res){
            apiResponse('success','成功');
        }else{
            apiResponse('error','失败');
        }
    }

    /**
     * 修改地址
     * 传参方式 post
     * @author mss
     * @time 2017-11-14
     * @param mix_id 用户id
     * @param type 用户类型，0用户，1商家
     * @param addr_id 地址id
     * @param name 姓名
     * @param phone 手机号
     * @param province 省id
     * @param city 市id
     * @param area 区县id
     * @param address 具体地址
     * @param is_default 是否默认，0否，1默认
     */
    public function editAddress(){
        if(empty($_POST['addr_id'])){
            apiResponse('error','参数错误');
        }
        $a_id = $_POST['addr_id'];
        if(!empty($_POST['name'])){
            $data['name'] = $_POST['name'];
        }
        if(!empty($_POST['phone'])){
            $tel = $_POST['phone'];
            if(!preg_match(C('MOBILE'),$tel)) {
                apiResponse("error","手机格式不正确！");
            }
            $data['phone'] = $_POST['phone'];
        }
        if(!empty($_POST['province'])){
            $data['province'] = $_POST['province'];
        }
        if(!empty($_POST['city'])){
            $data['city'] = $_POST['city'];
        }
        if(!empty($_POST['area'])){
            $data['area'] = $_POST['area'];
        }
        if(!empty($_POST['address'])){
            $data['address'] = $_POST['address'];
        }
        $data['is_default'] = $_POST['is_default'];
        $type = $_POST['type']?$_POST['type']:0;
        if($_POST['is_default']){
            M('Address')->where(array('mix_id'=>$_POST['mix_id'],'type'=>$type,'status'=>array('NEQ',9),'is_default'=>1,'addr_id'=>array('NEQ',$a_id)))->limit(1)->getField('addr_id');
            M('Address')->where(array('mix_id'=>$_POST['mix_id'],'is_default'=>1))->save(['is_default'=>0]);
        }
        $data['utime'] = time();
        //查看是否已经有默认地址
        $res = M('Address')->where(array('addr_id'=>$a_id))->limit(1)->save($data);
        if($res){
            apiResponse('success','编辑成功');
        }else{
            apiResponse('error','编辑失败');
        }

    }

    /**
     * 删除地址
     * 传参方式 post
     * @author mss
     * @time 2017-11-14
     * @param addr_id 地址id
     */
    public function delAddress(){
        if (empty($_POST['addr_id'])){
            apiResponse('error','参数错误');
        }
        $addr_id = $_POST['addr_id'];
        $data['status'] = 9;
        $data['utime'] = time();
        $res = M('Address')->where(array('addr_id'=>$addr_id))->limit(1)->save($data);
        if($res){
            apiResponse('success','删除成功');
        }else{
            apiResponse('error','删除失败');
        }
    }

    /**
     * 将地址设为默认
     * 传参方式 post
     * @author mss
     * @time 2017-11-14
     * @param addr_id 地址id
     * @param m_id 用户id
     * @parma type 用户类型，0用户，1商家
     */
    public function setDefault(){
        if (empty($_POST['addr_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_POST['mix_id'])){
            apiResponse('error','参数错误');
        }
        $a_id = $_POST['addr_id'];
        $mix_id = $_POST['mix_id'];
        $type = $_POST['type']?$_POST['type']:0;
        //查找是否已经存在默认地址
        $default = M('Address')->where(array('status'=>array('NEQ',9),'mix_id'=>$mix_id,'type'=>$type,'is_default'=>1))->limit(1)->getField('addr_id');
        if($default){
            M('Address')->where(array('addr_id'=>$default))->limit(1)->save(array('is_default'=>0,'utime'=>time()));
        }
        $res = M('Address')->where(array('addr_id'=>$a_id))->limit(1)->save(array('is_default'=>1,'utime'=>time()));
        if($res){
            apiResponse('success','设置成功');
        }else{
            apiResponse('error','设置失败');
        }
    }




}