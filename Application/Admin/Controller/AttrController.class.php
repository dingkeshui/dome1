<?php
namespace Admin\Controller;
use Think\Controller;

/**
 * Class CaseController
 * @package Admin\Controller
 * 商品属性
 */
class AttrController extends AdminBasicController {

    public $attr = '';
    public $cate = '';
    public function _initialize(){
        $this->cate  = D('Category');
        $this->checkLogin();
        $this->attr = D('Attr');
        //查找商品分类
        $cates = M('Category')->where(array('status'=>array('NEQ',9)))->order('sort ASC,ctime DESC')->field('cate_id,category')->select();
        $this->assign('cates',$cates);
    }
    /**
     * 案例列表
     * @author mss
     * @time 2017-10-11
     */
    public function AttrList(){
        $where['status'] = array('NEQ',9);
        $case = $this->attr->selectAttr($where,'ctime desc','15');
        $list = $case['list'];
        foreach($list as $k=>$v){
            $list[$k]['category'] = M('Category')->where(array('cate_id'=>$v['cate_id']))->limit(1)->getField('category');
        }
        $this->assign('page',$case['page']);
        $this->assign('list',$list);

        $this->display('attrList');
    }

    /**
     * 添加属性
     * @author mss
     * @time 2017-10-10
     */
    public function addAttr(){
        if(IS_POST){
            $data = $this->attr->create();
            if($data){
                $vals = $_POST['attr_val'];
                $res = $this->attr->addAttr($data);
                if($res){
                    foreach ($vals as $k=>$v){
                        $attr_data['attr_id'] = $res;
                        $attr_data['ctime'] = time();
                        $attr_data['attr_value'] = $v;
                        M('AttrValue')->add($attr_data);
                    }
                    $this->success('添加成功',U('Attr/attrList'));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->attr->getError());
            }
        }else{
            /**获取分类*/
            $case = $this->cate->where(array('parent_id'=>0))->select();
            foreach ($case as $k=>$v){
                $case[$k]['second_cate'] = $this->cate->where(array('parent_id'=>$v['cate_id']))->select();
                foreach ($case[$k]['second_cate'] as $kk=>$vv){
                    $case[$k]['second_cate'][$kk]['three_cate'] = $this->cate->where(array('parent_id'=>$vv['cate_id']))->select();
                }
            }
            $this->assign('list',$case);
            $this->display('addAttr');
        }
    }

    /**
     * 编辑案例
     * @author mss
     * @time 2017-10-11
     */
    public function editAttr(){
        if(IS_POST){
            $data = $this->attr->create();
            if($data){
                $values = $_POST['attr_val'];
                $data['cate_id'] = $_POST['cate_id'];
                dump($data);
                $data['utime'] = time();
                $where = array('attr_id'=>$_POST['attr_id']);
                $res = $this->attr->editAttr($where,$data);
                if($res){
                    M('AttrValue')->where(array('attr_id'=>$_POST['attr_id'],'shop_id'=>0))->delete();
                    foreach($values as $k=>$v){
                        $val_data['attr_id'] = $_POST['attr_id'];
                        $val_data['attr_value'] = $v;
                        $val_data['ctime'] = time();
                        M('AttrValue')->add($val_data);
                    }
                    $this->success('编辑成功',U('Attr/attrList'));
                }else{
                    $this->error('编辑失败');
                }
            }else{
                $this->error($this->attr->getError());
            }
        }else{
            $id = I('get.id');
            $attrinfo = M('Attribute')->where(array('attr_id'=>$id))->find();
            $this->assign('attrinfo',$attrinfo);
            $vals = M('AttrValue')->where(array('attr_id'=>$id,'shop_id'=>0,'status'=>array('NEQ',9)))->select();
            $this->assign('vals',$vals);
            /**获取分类*/
            $case = $this->cate->where(array('parent_id'=>0))->select();
            foreach ($case as $k=>$v){
                $case[$k]['second_cate'] = $this->cate->where(array('parent_id'=>$v['cate_id']))->select();
                foreach ($case[$k]['second_cate'] as $kk=>$vv){
                    $case[$k]['second_cate'][$kk]['three_cate'] = $this->cate->where(array('parent_id'=>$vv['cate_id']))->select();
                }
            }
            $this->assign('list',$case);
            $this->display('editAttr');
        }
    }


    /**
     * 删除操作
     */
    public function deleteAttr(){
        if(empty($_REQUEST['attr_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['attr_id'] = array('IN',I('request.attr_id'));
        $data['status'] = 9;
        $data['utime'] = time();
        $upd_res = $this->case->editCase($where,$data);
        if($upd_res){
            //其他删除操作

            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
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