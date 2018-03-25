<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 商城商品管理
 */
class ProductController extends AdminBasicController {
    public $product = '';
    public function _initialize(){
        $this->checkLogin();
        $this->product = D('product');
    }

    /**商品列表*/
    public function productList(){
        $par = array();
        $request = array();
        if($_REQUEST['name']){
            $w['title'] = array('LIKE','%'.$_REQUEST['name'].'%');
            $par['name'] = $_REQUEST['name'];
            $request['name'] = $_REQUEST['name'];
            $this->assign("request",$request);
        }
        //销量排序
        if($_REQUEST['sale_sort'] == 1){
            $sale_order = "sales desc";
            $par['sale_sort'] = $_REQUEST['sale_sort'];
            $this->assign("sale_sort",$_REQUEST['sale_sort']);
            $par['sale_sort'] = $_REQUEST['sale_sort'];
        }elseif($_REQUEST['sale_sort'] == 2){
            $sale_order = "sales asc";
            $par['sale_sort'] = $_REQUEST['sale_sort'];
            $this->assign("sale_sort",$_REQUEST['sale_sort']);
            $par['sale_sort'] = $_REQUEST['sale_sort'];
        }
        //价格排序
        /*if ($_REQUEST['price_sort'] == 1){
            $price_order = "price desc";
            $par['price'] = $_REQUEST['price_sort'];
            $this->assign("price_sort",$_REQUEST['price_sort']);
            $par['price_sort'] = $_REQUEST['price_sort'];
        }elseif ($_REQUEST['price_sort'] == 2){
            $price_order = "price asc";
            $par['price'] = $_REQUEST['price_sort'];
            $this->assign("price_sort",$_REQUEST['price_sort']);
            $par['price_sort'] = $_REQUEST['price_sort'];
        }*/
        if($sale_order){
            $order = "$sale_order";
        }else{
            $order = "p_id desc";
        }
        if($_REQUEST['type']){
            $w['type'] = $_REQUEST['type']-1;
            $par['type'] = $_REQUEST['type'];
            $request['type'] = $_REQUEST['type'];
            $this->assign("type",$_REQUEST['type']);
        }
        if($_REQUEST['cate_id']){
            $w['parent_id'] = $_REQUEST['cate_id'];
            $par['cate_id'] = $_REQUEST['cate_id'];
            $request['cate_id'] = $_REQUEST['cate_id'];
            $this->assign("cate_id",$_REQUEST['cate_id']);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $par['start_time'] = I('request.start_time');
            $par['end_time'] = I('request.end_time');
        }
        $w['status'] = array('neq',9);
        $res = $this->product->selectProduct($w,$order,15,$par);
        foreach($res['list'] as $k=>$v){
            $cate = M('Category')->where(['cate_id'=>$v['parent_id']])->getField('category');
            if($v['second_id']){
                $cate2 = M('Category')->where(['cate_id'=>$v['second_id']])->getField('category');
                $cate .= ' >> '.$cate2;
            }
            if($v['three_id']){
                $cate3 = M('Category')->where(['cate_id'=>$v['three_id']])->getField('category');
                $cate .= ' >> '.$cate3;
            }
            $res['list'][$k]['class_name'] = $cate;
        }
        $this->assign("list",$res['list']);
        $this->assign("page",$res['page']);

        //总的页数
        $pages = ceil(M('Product')->where($w)->count()/15);
        $this->assign('pages',$pages);

        /**获取分类*/
        $list_cate = M("Category")->where(['status'=>0,'parent_id'=>0])->field("cate_id,category")->select();
        $this->assign("list_cate",$list_cate);

        $this->display("productList");
    }


    /**商品列表*/
    public function productListTest(){
        $par = array();
        $request = array();
        if($_REQUEST['name']){
            $w['title'] = array('LIKE','%'.$_REQUEST['name'].'%');
            $par['name'] = $_REQUEST['name'];
            $request['name'] = $_REQUEST['name'];
            $this->assign("request",$request);
        }
        //销量排序
        if($_REQUEST['sale_sort'] == 1){
            $sale_order = "sales desc";
            $par['sale_sort'] = $_REQUEST['sale_sort'];
            $this->assign("sale_sort",$_REQUEST['sale_sort']);
            $par['sale_sort'] = $_REQUEST['sale_sort'];
        }elseif($_REQUEST['sale_sort'] == 2){
            $sale_order = "sales asc";
            $par['sale_sort'] = $_REQUEST['sale_sort'];
            $this->assign("sale_sort",$_REQUEST['sale_sort']);
            $par['sale_sort'] = $_REQUEST['sale_sort'];
        }
        //价格排序
        /*if ($_REQUEST['price_sort'] == 1){
            $price_order = "price desc";
            $par['price'] = $_REQUEST['price_sort'];
            $this->assign("price_sort",$_REQUEST['price_sort']);
            $par['price_sort'] = $_REQUEST['price_sort'];
        }elseif ($_REQUEST['price_sort'] == 2){
            $price_order = "price asc";
            $par['price'] = $_REQUEST['price_sort'];
            $this->assign("price_sort",$_REQUEST['price_sort']);
            $par['price_sort'] = $_REQUEST['price_sort'];
        }*/
        if($sale_order){
            $order = "$sale_order";
        }else{
            $order = "p_id desc";
        }
        if($_REQUEST['type']){
            $w['type'] = $_REQUEST['type']-1;
            $par['type'] = $_REQUEST['type'];
            $request['type'] = $_REQUEST['type'];
            $this->assign("type",$_REQUEST['type']);
        }
        if($_REQUEST['cate_id']){
            $w['cate_id'] = $_REQUEST['cate_id'];
            $par['cate_id'] = $_REQUEST['cate_id'];
            $request['cate_id'] = $_REQUEST['cate_id'];
            $this->assign("cate_id",$_REQUEST['cate_id']);
        }
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $par['start_time'] = I('request.start_time');
            $par['end_time'] = I('request.end_time');
        }
        $w['status'] = array('neq',9);
        $res = D("product_test")->selectProduct($w,$order,15,$par);
        foreach($res['list'] as $k=>$v){
            $cate = M('Category')->where(['cate_id'=>$v['parent_id']])->getField('category');
            if($v['second_id']){
                $cate2 = M('Category')->where(['cate_id'=>$v['second_id']])->getField('category');
                $cate .= ' >> '.$cate2;
            }
            if($v['three_id']){
                $cate3 = M('Category')->where(['cate_id'=>$v['three_id']])->getField('category');
                $cate .= ' >> '.$cate3;
            }
            $res['list'][$k]['class_name'] = $cate;
        }
        $this->assign("list",$res['list']);
        $this->assign("page",$res['page']);

        /**获取分类*/
        $list_cate = M("Category")->where(['status'=>0,'parent_id'=>0])->field("cate_id,category")->select();
        $this->assign("list_cate",$list_cate);

        $this->display("productListTest");
    }

    /**商品添加*/
    public function addProduct(){
        if(!IS_POST){
            /**获取分类*/
            $list = M("Category")->where(['status'=>0,'parent_id'=>0])->field("cate_id,category")->select();
            $this->assign("list",$list);
            $this->display("addProduct");
        }else{
            $data = D("product")->create();
            if($data){
                if(I("post.cover_pic")){
                    $data['cover_pic'] = I("post.cover_pic");
                }
                if(I("post.pic")){
                    $string = implode(",",I("post.pic"));
                    $data['pics'] = $string;
                }
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                $res = $this->product->add($data);
                if($res){
                    $this->success('添加成功',U("product/productList"));
                }else{
                    $this->error('添加失败');
                }
            }else{
                $this->error($this->product->getError());
            }
        }

    }


    /**商品修改*/
    public function editProductTest(){
        if(!IS_POST){
            $res = M("product_test")->where(array('p_id'=>$_GET['p_id']))->find();
            /**商家的多个图片*/
            $list_img = explode(",",$res['pics']);
            $res['list_img'] = $list_img;
            $this->assign("res",$res);
            if($res['second_id']){
                $second_list = M('Category')->where(['parent_id'=>$res['parent_id'],'status'=>0])->field('cate_id,category')->select();
                $this->assign('second_list',$second_list);
            }
            if($res['three_id']){
                $three_list = M('Category')->where(['parent_id'=>$res['second_id'],'status'=>0])->field('cate_id,category')->select();
                $this->assign('three_list',$three_list);
            }
            /**获取分类*/
            $list = M("Category")->where(['status'=>0,'parent_id'=>0])->field("cate_id,category")->select();
            $this->assign("list",$list);
            $this->display("editProductTest");
        }else{
            $data = M("product_test")->create();
            if($data){
                $w['p_id'] = I("post.p_id");
                if(I("post.cover_pic")){
                    $data['cover_pic'] = I("post.cover_pic");
                }
                if(I("post.pic")){
                    $string = implode(",",I("post.pic"));
                    $data['pics'] = $string;
                }
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                $res = M("product_test")->where($w)->save($data);
                if($res){
                    $this->success('修改成功',U("Product/ProductListTest"));
                }else{
                    $this->error('修改失败');
                }
            }else{
                $this->error($this->product->getError());
            }
        }
    }

    /**商品修改*/
    public function editProduct(){
        if(!IS_POST){
            $res = $this->product->where(array('p_id'=>$_GET['p_id']))->find();
            /**商家的多个图片*/
            $list_img = explode(",",$res['pics']);
            $res['list_img'] = $list_img;
            $this->assign("res",$res);
            if($res['second_id']){
                $second_list = M('Category')->where(['parent_id'=>$res['parent_id'],'status'=>0])->field('cate_id,category')->select();
                $this->assign('second_list',$second_list);
            }
            if($res['three_id']){
                $three_list = M('Category')->where(['parent_id'=>$res['second_id'],'status'=>0])->field('cate_id,category')->select();
                $this->assign('three_list',$three_list);
            }
            /**获取分类*/
            $list = M("Category")->where(['status'=>0,'parent_id'=>0])->field("cate_id,category")->select();
            $this->assign("list",$list);
            $this->display("editProduct");
        }else{
            $data = $this->product->create();
            if($data){
                $w['p_id'] = I("post.p_id");
                if(I("post.cover_pic")){
                    $data['cover_pic'] = I("post.cover_pic");
                }
                if(I("post.pic")){
                    $string = implode(",",I("post.pic"));
                    $data['pics'] = $string;
                }
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                $res = $this->product->where($w)->save($data);
                if($res){
                    $this->success('修改成功',U("Product/ProductList",['p'=>$_POST['p']]));
                }else{
                    $this->error('修改失败');
                }
            }else{
                $this->error($this->product->getError());
            }
        }
    }


    /**
     * ajax获取城市
     */
    public function ajaxSecond(){
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("Category")->where($where)->select();
        $list = $data?$data:[];
        echo json_encode($list);
    }
    /**
     * ajax获取区域
     */
    public function ajaxThird(){
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("Category")->where($where)->select();
        $list = $data?$data:[];
        echo json_encode($list);
    }

    /**ajax上传图片*/
    public function uploadPic(){
        $pic       = $_POST['pic'];
        $pic_name      = $_POST['pic_name'];
        $temp = explode('.',$pic_name);
        $ext = uniqid().'.'.end($temp);
        $base64    = substr(strstr($pic, ","), 1);
        $image_res = base64_decode($base64);
        $pic_link  = "Uploads/Product/".date('Y-m-d').'/'.$ext;
        $saveRoot = "Uploads/Product/".date('Y-m-d').'/';
        /**检查目录是否存在  循环创建目录*/
        if(!is_dir($saveRoot)){
            mkdir($saveRoot, 0777, true);
        }
        $res = file_put_contents($pic_link ,$image_res);
        if($res){
            $ajaxData = array("flag" => "success", "message"=>"上传成功！" );
            $result_data['path'] = $pic_link;
            $ajaxData['data'] = $result_data;
            $this->ajaxReturn(json_encode($ajaxData));
        }else{
            $ajaxData = array("flag" => "error", "message"=>"上传失败","data" => array());
            $this->ajaxReturn(json_encode($ajaxData));
        }
    }

    /**删除上传的乡相册的图片*/
    public function delPhoto(){
        /**判断删除的商品的图片type:1头像 type2商品展示图*/
        if($_POST['p_id']){
            $w['p_id'] = $_POST['p_id'];
            $res = M("product")->where($w)->limit(1)->find();
            if($_POST['type'] == 1){
                $data['cover_pic'] = "";
                M("product")->where($w)->limit(1)->save($data);
            }elseif ($_POST['type'] == 2){
                $string1 = explode(",",$res['pics']);
                foreach ($string1 as $k=>$v){
                    if($v == $_POST['file_path']){
                        unset($string1[$k]);
                    }
                }
                if(!empty($string1)){
                    $string_other = implode(',',$string1);
                    $data['pics'] = $string_other;
                    M("product")->where($w)->limit(1)->save($data);
                }else{
                    $data['pics'] = "";
                    M("product")->where($w)->limit(1)->save($data);
                }
            }
        }
        $file  = $_POST['file_path'];
        $result = @unlink($file);
        if ($result == false) {
            $this->ajaxReturn(0);
        } else {
            $this->ajaxReturn(1);
        }
    }


    /**
     * 删除操作
     */
    public function deleteProduct(){
        if(empty($_REQUEST['p_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['p_id'] = array('IN',I('request.p_id'));
        $data['status'] = 9;
        $upd_res = D("product")->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }


    /**修改一些数据*/
    public function ajaxMore(){
        $w['p_id'] = $_POST['id'];
        $field =  $_POST['field'];
        $value = $_POST['value'];
        $data[$field] = $value;
        $res = $this->product->where($w)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }

    }

    /**
     * 商品上下架
     */
    public function ajaxUp(){
        $w['p_id'] = I('post.id');
        $data['is_sale'] = I('post.status');
        $data['utime'] = time();
        $res = M('Product')->where($w)->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }






    /**导出积分订单excel的表单*/
    public function integralOrderXLS(){
        
        /**获取Excel导出数据*/
        $where = S('integralorder_w');
        $list = M('IntegralOrder')->where($where)->select();
        $arrordername = array('订单号','下单时间','订单类型','麦穗/豆','商品名称','商家/用户','商家/用户名称','收货人姓名','收货人电话','收货地址','订单状态');
        foreach($list as $k=>$v){
            switch ($v['status']){
                case 0:
                    $source="未付款";
                    break;
                case 1:
                    $source="支付成功(待发货)";
                    break;
                case 2:
                    $source="发货成功";
                    break;
                case 3:
                    $source="用户确认收货";
                    break;
                case 4:
                    $source="申请退货";
                    break;
                case 5:
                    $source="退货成功";
                    break;
                case 9:
                    $source="用户删除订单";
                    break;
            }
            $v['product'] = M("product")->where(array('g_id'=>$v['g_id']))->getField("name");
            if($v['rank_type'] == 1){
                $rank_type = '商家';
                $v['mem_name'] = M("Shop")->where(array('shop_id'=>$v['mix_id']))->getField('name');
            }else{
                $rank_type = '用户';
                $v['mem_name'] = M("Member")->where(array('m_id'=>$v['mix_id']))->getField('nick_name');
            }
            if($v['type']==1){
                $type = '麦穗';
            }else{
                $type = '豆';
            }
            $arrorderlist[] = array($v['order_sn'],' '.date('Y-m-d H:i:s',$v['ctime']),$type,$v['price'],$v['product'],$rank_type,$v['mem_name'],$v['name'],$v['tel'],$v['address'],$source);
        }
        exportexcel($arrorderlist,$arrordername,'积分订单信息');

    }



}