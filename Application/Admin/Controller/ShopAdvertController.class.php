<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * Class CaseController
 * @package Admin\Controller
 * 商城层级下方广告图
 */
class ShopAdvertController extends AdminBasicController {

    public $shopAdvert = '';
    public function _initialize(){
        $this->checkLogin();
        $this->shopAdvert = D('ShopAdvert');
    }
    /**
     * 商城楼层下单广告列表
     * @author crazy
     * @time 2017-10-11
     */
    public function shopAdvertList(){

        //导航列表
        $parm = array();
        if(I('request.is_show')){
            $where['is_show'] = I('request.is_show')-1;
            $parm['is_show'] = I('request.is_show');
            $this->assign('is_show',I('request.is_show'));
        }

        if(I('request.l_id')){
            $where['ladder_id'] = I('request.l_id');
            $parm['l_id'] = I('request.l_id');
            $this->assign('l_id',I('request.l_id'));
        }

        $where['status'] = array('NEQ',9);
        $case = $this->shopAdvert->selectShopAdvert($where,'ladder_id ASC,ctime desc','15');
        foreach ($case['list'] as $k=>$v){
            $case['list'][$k]['ladder_name'] = M('Ladder')->where(array('l_id'=>$v['ladder_id']))->getField('name');
            /**查看被点击的次数*/
            $case['list'][$k]['click_num'] = M("ClickAdvert")->where(['advert_id'=>$v['s_a_id'],'type'=>5])->count();
        }
        $this->assign('page',$case['page']);
        $this->assign('list',$case['list']);

        /**查找所有的楼层*/
        $list = M('Ladder')->where(array('status'=>array('neq',9),'is_show'=>1))->order('sort asc')->field('l_id,name')->select();
        $this->assign('list_ladder',$list);

        $this->display('shopAdvertList');
    }

    /**
     * 添加商城楼层
     * @author crazy
     * @time 2017-10-10
     */
    public function addShopAdvert(){
        if(IS_POST){
            $data = $this->shopAdvert->create();
            if($data){
                $res = $this->uploadImg("Advert","pic");
                $data['pic'] = $res;
                $res = $this->shopAdvert->addShopAdvert($data);
                if($res){
                    $this->success('添加成功',U('ShopAdvert/shopAdvertList'));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->shopAdvert->getError());
            }
        }else{
            /**查找所有的楼层*/
            $list = M('Ladder')->where(array('status'=>array('neq',9),'is_show'=>1))->order('sort asc')->field('l_id,name')->select();
            $this->assign('list',$list);
            $this->display('addShopAdvert');
        }
    }

    /**
     * 编辑商城楼层
     * @author crazy
     * @time 2017-10-11
     */
    public function editShopAdvert(){
        if(IS_POST){
            $data = $this->shopAdvert->create();
            if($data){
                $res = $this->uploadImg("Advert","pic");
                if(!empty($res)){
                    $data['pic'] = $res;
                }
                $data['utime'] = time();
                $res = $this->shopAdvert->where(array('s_a_id'=>$_POST['s_a_id']))->limit(1)->save($data);
                if($res){
                    $this->success('编辑成功',U('ShopAdvert/ShopAdvertList'));
                }else{
                    $this->error('编辑失败');
                }
            }else{
                $this->error($this->ShopAdvert->getError());
            }
        }else{
            $id = I('get.s_a_id');
            $ShopAdvert = M('ShopAdvert')->where(array('s_a_id'=>$id))->find();
            $this->assign('shopAdvert',$ShopAdvert);

            /**查找所有的楼层*/
            $list = M('Ladder')->where(array('status'=>array('neq',9),'is_show'=>1))->order('sort asc')->field('l_id,name')->select();
            $this->assign('list',$list);

            $this->display('editShopAdvert');
        }
    }


    /**
     * 删除操作
     */
    public function deleteShopAdvert(){
        if(empty($_REQUEST['l_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['l_id'] = array('IN',I('request.l_id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->ShopAdvert->editShopAdvert($where,$data);
        if($upd_res){
            //其他删除操作
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * 修改显示顺序
     */
    public function updateSort(){
        $data['position'] = $_POST['position'];
        $data['utime'] = time();
        $res = M('ShopAdvert')->where(array('s_a_id'=>$_POST['s_a_id']))->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }



    /**
     * 上传图片
     */
    public function uploadPic(){
        $pic       = $_POST['pic'];
        $pic_name      = $_POST['pic_name'];
        $temp = explode('.',$pic_name);
        $ext = uniqid().'.'.end($temp);
        $base64    = substr(strstr($pic, ","), 1);
        $image_res = base64_decode($base64);
        $pic_link  = "Uploads/Case/".date('Y-m-d').'/'.$ext;
        $saveRoot = "Uploads/Case/".date('Y-m-d').'/';
        /**检查目录是否存在  循环创建目录*/
        if(!is_dir($saveRoot)){
            mkdir($saveRoot, 0777, true);
        }
        $res = file_put_contents($pic_link ,$image_res);
        if($res){
            $ajaxData = array("flag" => "success", "message"=>"上传成功！" );
            $result_data['path'] = '/'.$pic_link;
            $ajaxData['data'] = $result_data;
            $this->ajaxReturn(json_encode($ajaxData));
        }else{
            $ajaxData = array("flag" => "error", "message"=>"上传失败","data" => array());
            $this->ajaxReturn(json_encode($ajaxData));
        }
    }
    /**删除上传的相册的图片*/
    public function delPhoto(){
        $caseid = I('post.case_id');
        $type = I('post.type');
        $pic = "";
        if($caseid){
            if($type==1){
                $data['head_pic'] = "";
            }else{
                $data['mobile_pic'] = "";
            }

            $pic = M("Case")->where(array('case_id'=>$caseid))->limit(1)->save($data);
        }else{
            $pic = $_POST['file_path'];
        }

        $file  = $_POST['file_path'];
        $result = @unlink($file);
        if ($result == false && empty($pic)) {
            $this->ajaxReturn(0);
        } else {
            $this->ajaxReturn(1);
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
        $w['advert_id'] = I('request.s_a_id');
        $w['type'] = 5;
        $param['advert_id'] = I('request.s_a_id');
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