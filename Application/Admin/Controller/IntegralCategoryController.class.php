<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * Class CaseController
 * @package Admin\Controller
 * 商品分类(积分商品)
 */
class IntegralCategoryController extends AdminBasicController {

    public $cate = '';
    public function _initialize(){
        $this->checkLogin();
        $this->cate = D('IntegralCategory');
    }
    /**
     * 分类列表
     * @author mss
     * @time 2017-10-11
     */
    public function categoryList(){
        $where['status'] = array('NEQ',9);
        $case = $this->cate->selectCategory($where,'sort ASC,ctime desc','15');

        $this->assign('page',$case['page']);
        $this->assign('list',$case['list']);

        $this->display('categoryList');
    }

    /**
     * 添加分类
     * @author mss
     * @time 2017-10-10
     */
    public function addCategory(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->cate->where(array('category'=>trim(I('post.category'))))->getField('cate_id');
            if($is_set){
                $this->error('分类已经存在！');
            }
            $data = $this->cate->create();
            if($data){
                $res = $this->uploadImg("Advert","pic");
                $data['pic'] = $res;
                $res = $this->cate->addCategory($data);
                if($res){
                    $this->success('添加成功',U('IntegralCategory/categoryList'));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->cate->getError());
            }
        }else{
            $this->display('addCategory');
        }
    }

    /**
     * 编辑分类
     * @author mss
     * @time 2017-10-11
     */
    public function editCategory(){
        if(IS_POST){
            /**判断是否已经存在分类*/
            $is_set = $this->cate->where(array('category'=>trim(I('post.category')),'cate_id'=>array('neq',$_POST['cate_id'])))->getField('cate_id');
            if($is_set){
                $this->error('分类已经存在！');
            }
            $data = $this->cate->create();
            if($data){
                $res = $this->uploadImg("Advert","pic");
                if(!empty($res)){
                    $data['pic'] = $res;
                }
                $data['utime'] = time();
                $res = $this->cate->where(array('cate_id'=>$_POST['cate_id']))->limit(1)->save($data);
                if($res){
                    $this->success('编辑成功',U('IntegralCategory/categoryList'));
                }else{
                    $this->error('编辑失败');
                }
            }else{
                $this->error($this->cate->getError());
            }
        }else{
            $id = I('get.id');
            $category = $this->cate->where(array('cate_id'=>$id))->find();
            $this->assign('category',$category);
            $this->display('editCategory');
        }
    }


    /**
     * 删除操作
     */
    public function deleteCate(){
        if(empty($_REQUEST['cate_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['cate_id'] = array('IN',I('request.cate_id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->cate->editCategory($where,$data);
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
        $data['sort'] = $_POST['sort'];
        $data['utime'] = time();
        $res = $this->cate->where(array('cate_id'=>$_POST['cate_id']))->limit(1)->save($data);
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

}