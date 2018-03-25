<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * 广告控制类
 */
class AdvertController extends AdminBasicController {
    public $ad_obj = '';
    public function _initialize(){
        $this->checkLogin();
        $this->ad_obj = D('advert');
    }

    /**
     * 广告列表
     * @author crazy
     * @time 2017-11-24
     */
    public function advertList(){
        //导航列表
        $parm = array();
        if(I('request.is_shop')){
            $where['is_shop'] = I('request.is_shop')-1;
            $parm['is_shop'] = I('request.is_shop');
            $this->assign('is_shop',I('request.is_shop'));
        }
        $where['status'] = array('neq',9);
        $ad_list = $this->ad_obj->selectAdvert($where,'sort desc',15,$parm);
        foreach ($ad_list['list'] as $k=>$v){
            /**找到省市区级的名称*/
            $ad_list['list'][$k]['province_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField('area_name');
            $ad_list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField('area_name');
            $ad_list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField('area_name');
            /**查看被点击的次数*/
            $ad_list['list'][$k]['click_num'] = M("ClickAdvert")->where(['advert_id'=>$v['a_id'],'type'=>['neq',5]])->count();
        }
        $this->assign('list',$ad_list['list']);
        $this->assign('page',$ad_list['page']);
        $this->display('advertList');
    }

    /**
     * 添加广告
     */
    public function addAdvert(){
        if(empty($_POST)){
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->select();
            $this->assign("pro",$pro);
            //广告位置
            $this->display('addAdvert');
        }else{
                $data = M("Advert")->create();
                $res = $this->uploadImg("Advert","pic");
                $data['pic'] = $res;
                $data['ctime'] = time();
                $add_res = $this->ad_obj->addAdvert($data);
                if($add_res){
                    $this->success('添加广告成功',U('Advert/advertList'));
                }else{
                    $this->error('添加广告失败');
                }

        }
    }

    /**
     * 修改广告
     */
    public function editAdvert(){
        $this->checkAuth('Advert','editAdvert');
        if(empty($_POST)){
            $where['a_id'] = I('get.a_id');
            $ad = $this->ad_obj->findAdvert($where);
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->order('is_hot desc,sort_order desc')->select();
            $this->assign("pro",$pro);
            //获取城市
            $city = M("areas")->where(array('parent_id'=>$ad['province']))->select();
            $this->assign("city",$city);
            //获取区域
            $area = M("areas")->where(array('parent_id'=>$ad['city']))->select();
            $this->assign("area",$area);
            if($ad){
                //广告位置
                $this->assign('ad_position',C('AD_POSITION'));
                $this->assign('ad',$ad);
                $this->display('editAdvert');
            }else{
                $this->error('该广告不存在或被删除');
            }
        }else{
            $where['a_id'] = I('post.a_id');
            $data = $this->ad_obj->create();
            if($data){
                if ($_FILES['pic']['name']){
                    $res = $this->uploadImg("Advert","pic");
                    $data['pic'] = $res;
                }
                $data['utime'] = time();
                $upd_res = $this->ad_obj->editAdvert($where,$data);
                if($upd_res){
                    $this->success('编辑广告成功',U("Advert/advertList"));
                }else{
                    $this->error('编辑广告失败');
                }
            }else{
                $this->error($this->ad_obj->getError());
            }
        }
    }

    /**
     * 删除操作
     */
    public function deleteAdvert(){
        $this->checkAuth('Advert','deleteAdvert');
        if(empty($_REQUEST['a_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['a_id'] = array('IN',I('request.a_id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->ad_obj->editAdvert($where,$data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * 编辑排序
     */
    public function editSort(){
        //修改条件 ID
        $where['ad_id'] = I('post.id');
        $data['sort_order'] = I('post.sort');
        //修改操作
        $upd_res = $this->ad_obj->editAdvert($where,$data);
        if($upd_res){
            $this->ajaxMsg('success','修改排序成功');
        }else{
            $this->ajaxMsg('error','修改排序失败，可能是未改变排序值');
        }
    }




    /**查看广告被点击的次数的详情
     * @author crazy
     * @time 2018-01-24
     */
    public function clickAdvertList(){
        if(I('request.port')){
            $w['port'] = I('request.port');
            $param['port'] = I('request.port');
            $this->assign("port",I('request.port'));
        }
        $w['advert_id'] = I('request.a_id');
        $w['type'] = ['neq',5];
        $param['a_id'] = I('request.a_id');
        $ad_list = D("ClickAdvert")->selectAdvert($w,'ctime desc',15,$param);
        foreach($ad_list['list'] as $k=>$v){
            if($v['m_id']){
                $ad_list['list'][$k]['mem_name'] = M("Member")->where(['m_id'=>$v['m_id']])->getField('nick_name');
            }else{
                $ad_list['list'][$k]['mem_name'] = "未登陆点击";
            }

        }
        $this->assign('list',$ad_list['list']);
        $this->assign('page',$ad_list['page']);
        $this->display("clickAdvertList");
    }
}