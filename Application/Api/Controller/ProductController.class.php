<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class ProductController
 * @package Api\Controller
 * 商品分类、属性
 */
class ProductController extends ApiBasicController
{

    /**初始化*/
    public function _initialize()
    {
        parent::_initialize();
    }


    /**商品列表页面分类显示
     * @author crazy
     * @time 2017-12-20
     */
    public function goodsCateList(){
        /**总的商品数量*/
        $data = [];
        $goods_count_total = M("Product")->where(['status'=>0,'is_sale'=>1])->count();
        $list = $this->getCate();
        $data['goods_total_count'] = $goods_count_total;
        $data['cate_list'] = $list;
        apiResponse('success','成功',$list);
    }

    /**
     * 商品一级分类
     * 传参方式 post
     * @author mss
     * @time 2017-11-10
     */
    public function category(){
        $list = $this->getCate();
        /**设置后台的属性值*/
        $this->addShopAttr($_POST['shop_id']);
        if (!$list){
            $list = array();
            apiResponse('success','暂无分类',$list);
        }else{
            foreach($list as $k=>$v){
                if($v['pic']!=''){
                    $list[$k]['pic'] = C('API_URL').'/Uploads/'.$v['pic'];
                }
            }
            apiResponse('success','成功',$list);
        }
    }

    /**二、三级分类
     * 传参方式 get
     * @author crazy
     * @time 2017-12-18
     */
    public function secondCate(){
        $where = array('status'=>array('NEQ',9),'parent_id'=>$_GET['cate_id']);
        $list = M('Category')->where($where)->field('cate_id,category,pic')->order('sort ASC')->select();
        foreach ($list as $k=>$v){
            $is_set = M('Category')->where(array('parent_id'=>$v['cate_id'],'status'=>array('NEQ',9)))->getField('cate_id');
            $list[$k]['have_other'] = empty($is_set)?0:1;
        }
        if (!$list){
            $list = array();
            apiResponse('success','暂无分类',$list);
        }else{
            foreach($list as $k=>$v){
                if($v['pic']!=''){
                    $list[$k]['pic'] = C('API_URL').'/Uploads/'.$v['pic'];
                }
            }
            apiResponse('success','成功',$list);
        }
    }

    /**
     * 指定分类的属性（商家端）
     * 传参方式 get
     * @author mss
     * @time 2017-11-10
     * @param cate_id 分类id
     * @param shop_id 商家id
     */
    public function attributes(){
        if(empty($_GET['cate_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_GET['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_GET['shop_id'];
        $cate_id = $_GET['cate_id'];
        $where['shop_id'] = $shop_id;
        $where['cate_id'] = $cate_id;
        $where['status'] = array('NEQ',9);
        $attrs = M('Attribute')->where($where)->field('attr_id,shop_id,attr_name')->select();
        $list = array();
        foreach($attrs as $k=>$v){
            $list[$k]['attr_id'] = $v['attr_id'];
            $list[$k]['attr_name'] = $v['attr_name'];
            $list[$k]['shop_id'] = $v['shop_id'];
            $v_where['attr_id'] = $v['attr_id'];
            $v_where['shop_id'] = $shop_id;
            $vals_array = M('AttrValue')->where($v_where)->field('shop_id,val_id,attr_value')->select();
            $list[$k]['vals'] = $vals_array?$vals_array:[];
        }

        if($list){
            apiResponse('success','成功',$list);
        }else{
            apiResponse('success','暂无属性');
        }
    }

    /**
     * 添加分类的属性（商家端）
     * 传参方式 post
     * @author mss
     * @time 2017-11-10
     * @param cate_id 分类id
     * @param shop_id 商家id
     * @param attr_name 属性名称  {["id":"1",'attr':"颜色"],['id':"0","attr":"尺码"]}
     */
    public function addEditAttr(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_POST['cate_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_POST['attr_name'])){
            apiResponse('error','属性名称不能为空');
        }
        $shop_id = $_POST['shop_id'];
        $cate_id = $_POST['cate_id'];
        /**属性值数组*/
        $arr = json_decode($_POST['attr_name'],true);
        $num = 0;
        $num1 = 0;
        $num2 = 0;
        $data_return = [];
        foreach($arr as $k=>$v){
            //查看当前分类下的属性是否存在
            $is_attr = M('Attribute')->where(array('cate_id'=>$cate_id,'status'=>array('NEQ',9),'attr_name'=>$v['attr'],'shop_id'=>$shop_id))->getField('shop_id');
            if($v['id'] == 0 && empty($is_attr)){
                $data['shop_id'] = $shop_id;
                $data['cate_id'] = $cate_id;
                $data['attr_name'] = $v['attr'];
                $data['ctime'] = time();
                $is_true = M('Attribute')->add($data);
                if($is_true!=false){
                    $num1 +=1;
                }
                $arr[$k]['id'] = $is_true;
            }else{
                $data['attr_name'] = $v['attr'];
                $is_true = M('Attribute')->where(['attr_id'=>$v['id']])->limit(1)->save($data);
                if($is_true!=false){
                    $num2 +=1;
                }
            }
            $num = $num1+$num2;
        }
        $data_return['num'] = $num;
        $data_return['attr_list'] = $arr?$arr:[];
        apiResponse('success','操作成功',$data_return);
    }
    /**
     * 编辑属性名（商家端）
     * 传参方式 post
     * @author mss
     * @time 2017-11-12
     * @param attr_id 属性id
     * @param type 操作类型 1修改，2删除
     * @param attr_name 属性名称
     */
//    public function editAttr(){
//        if (empty($_POST['attr_id'])){
//            apiResponse('error','参数错误');
//        }
//        $attr_id = $_POST['attr_id'];
//        $type = $_POST['type']?$_POST['type']:'1';
//        $attr = M('Attribute')->where(array('attr_id'=>$attr_id))->find();
//        if($attr['shop_id']==0){
//            apiResponse('error','非法操作');
//        }
//        if($type == 1){
//            $data['attr_name'] = $_POST['attr_name'];
//        }else{
//            $data['status'] = 9;
//        }
//        $data['utime'] = time();
//        $res = M('Attribute')->where(array('attr_id'=>$attr_id))->limit(1)->save($data);
//        if($res){
//            apiResponse('success','操作成功');
//        }else{
//            apiResponse('error','操作失败');
//        }
//    }

    /**
     * 添加属性值（商家端）
     * 传参方式 post
     * @author mss
     * @time 2017-11-10
     * @param shop_id 商家id
     * @param attr_id 属性id
     * @param attr_val 属性值  [{"id":"1","attr_value":"白色"},{"id":"0","attr_value":"红色"}]
     */
    public function addAttrEditValue(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_POST['attr_id'])){
            apiResponse('error','参数错误');
        }
        if(empty($_POST['attr_val'])){
            apiResponse('error','属性值不能为空');
        }
        $shop_id = $_POST['shop_id'];
        $attr_id = $_POST['attr_id'];
        /**属性值数组*/
        $arr = json_decode($_POST['attr_val'],true);
        $num = 0;
        $num1 = 0;
        $num2 = 0;
        foreach($arr as $k=>$v){
            $is_val = M('AttrValue')->where(array('attr_id'=>$attr_id,'attr_value'=>$v['attr_value'],'shop_id'=>$shop_id,'status'=>array('NEQ',9)))->find();
            if($v['id'] == 0 && empty($is_val)){
                $data['attr_id'] = $attr_id;
                $data['shop_id'] = $shop_id;
                $data['attr_value'] = $v['attr_value'];
                $data['ctime'] = time();
                $is_true = M('AttrValue')->add($data);
                if($is_true!=false){
                    $num1 +=1;
                }
                $arr[$k]['id'] = $is_true;
            }else{
                $data['attr_value'] = $v['attr_value'];
                $is_true = M('AttrValue')->where(['val_id'=>$v['id']])->limit(1)->save($data);
                if($is_true!=false){
                    $num2 +=1;
                }
            }
            $num = $num1+$num2;
        }
        $data_return['num'] = $num;
        $data_return['attr_list'] = $arr?$arr:[];
        apiResponse('success','添加成功',$data_return);

    }

    /**
     * 编辑属性值（商家端）
     * 传参方式 post
     * @authro mss
     * @time 2017-11-12
     * @param val_id 属性值id
     * @param type 操作类型，1编辑，2删除
     * @param attr_val 属性值
     */
//    public function editAttrValue(){
//        if(empty($_POST['val_id'])){
//            apiResponse('error','参数错误');
//        }
//        $id = $_POST['val_id'];
//        $type = $_POST['type']?$_POST['type']:'1';
//        $val = M('AttrValue')->where(array('val_id'=>$id))->find();
//        if($val['shop_id']==0){
//            apiResponse('error','非法操作');
//        }
//        if($type==1){
//            $data['attr_value'] = $_POST['attr_val'];
//        }else{
//            $data['status'] = 9;
//        }
//        $data['utime'] = time();
//        $res = M('AttrValue')->where(array('val_id'=>$id))->limit(1)->save($data);
//        if($res){
//            apiResponse('success','操作成功');
//        }else{
//            apiResponse('error','操作失败');
//        }
//    }

    public function test(){
        $arr = array(0=>array('attr'=>'红色|L','trade_price'=>1,'retail_price'=>2));
        $json = json_encode($arr);
        dump($json);
    }

    /**
     * 商家发布商品（商家端）
     * 传参方式 post
     * @author mss
     * @time 2017-11-10
     * @param shop_id 商家id
     * @param cate_id 商品分类
     * @param title 商品名称
     * @param price 商品价格
     * @param type 商品类型，1实物商品，2电子卡券
     * @param content 商品详情
     * @param attrs 属性 (数组)
     * @param prices 价格（数组）
     * @param pic 封面图
     * @param pic_more 轮播图
     * @param sales 销量
     * @param stock 库存
     * @param sort 显示顺序
     */
    public function addGoods(){
        if(empty($_POST['shop_id'])||empty($_POST['title'])){
            apiResponse('error','参数错误');
        }
        M()->startTrans();
        $data = array();
        $data['shop_id'] = $_POST['shop_id'];
        $data['parent_id'] = $_POST['parent_id']?$_POST['parent_id']:0;
        $data['second_id'] = $_POST['second_id']?$_POST['second_id']:0;
        $data['three_id'] = $_POST['three_id']?$_POST['three_id']:0;
        $data['title'] = $_POST['title'];
        $data['price'] = $_POST['price']?$_POST['price']:0;
//        $data['sales'] = $_POST['sales']?$_POST['sales']:0;
        $data['stock'] = $_POST['stock']?$_POST['stock']:0;
        $data['goods_attr'] = $_POST['goods_attr']?$_POST['goods_attr']:"";
        $data['sort'] = $_POST['sort']?$_POST['sort']:0;
        $data['type'] = $_POST['type'];
        $data['is_sale'] = $_POST['is_sale'];
        $data['postage']=$_POST['postage'];
        if (get_magic_quotes_gpc()) {
            $data['content'] = stripslashes($_POST['content']);
        } else {
            $data['content'] = $_POST['content'];
        }
        $attrs = json_decode($_POST['attrs'],true);
        if($attrs){
            $data['attr'] = serialize($attrs);
        }

        $data['cover_pic'] = $_POST['pic'];
        $data['pics'] = $_POST['pic_more'];

        $data['ctime'] = time();
        $pro_res = M('Product')->add($data);
        $prices = json_decode($_POST['prices'],true);
        $pri_res = array();
        $stock = 0;
        $minprice = null;
        if($prices){
            foreach($prices as $k=>$v){
                $price_data['p_id'] = $pro_res;
                $price_data['attr_group'] = $v['attr'];
                $price_data['price'] = $v['price'];
                $price_data['stock'] = $v['stock'];
                $price_data['ctime'] = time();
                $pri_res[$k] = M('ProductPrice')->add($price_data);
                $stock+=$v['stock'];
                if($minprice === null){
                    $minprice = $price_data['price'];
                }else if($price_data['price'] < $minprice){
                    $minprice = $price_data['price'];
                }
                $price_data = array();
            }
        }
        if($_POST['type'] == 1){
            $stock_res = M('Product')->where(['p_id'=>$pro_res])->limit(1)->save(['utime'=>time()]);
        }else{
            $stock_res = M('Product')->where(['p_id'=>$pro_res])->limit(1)->save(['stock'=>$stock,'utime'=>time(),'price'=>$minprice]);
        }
        if($pro_res&&!in_array(false,$pri_res)&&$stock_res){
            M()->commit();
            apiResponse('success','发布成功');
        }else{
            M()->rollback();
            apiResponse('error','发布失败');
        }

    }


    /**修改商品
     * 传参方式 POST
     * @author mss
     * @time 2017-12-19
     * @param
     */
    public function editGoods(){
        if(!IS_POST){
            $p_id = I('get.p_id');
            if(empty($p_id)){
                apiResponse('error','参数错误');
            }
            $res = M("Product")->where(['p_id'=>$p_id])->find();
            $arr = explode(',',$res['pics']);
            $res['pics'] = $arr;
            $res['attr'] = unserialize($res['attr']);
            /**获取一级，二级，三级分类名称*/
            $res['first_name'] = $this->getCateName($res['parent_id']);
            $res['second_name'] = $this->getCateName($res['second_id']);
            $res['three_name'] = $this->getCateName($res['three_id']);
//            /**获取运费*/
//            $res['postage'] = M('Shop')->where(array('shop_id'=>$res['shop_id']))->getField('postage');
            /**获取商品的属性的组合*/
            $attr_list = M('ProductPrice')->where(['p_id'=>$p_id,'status'=>['neq',9]])->field('id,p_id,attr_group,price,stock')->select();
            $att = '';
            foreach($attr_list as $k=>$v){
                $arr = explode('|',$v['attr_group']);
                foreach($arr as $kk=>$vv){
                    $value = M("AttrValue")->where(['val_id'=>$vv])->getField("attr_value");
                    $att[]=$vv.','.$value;
                    $attr_list[$k]['attr'] = implode("|",$att);
                }
                $att = [];
            }
            $res['attr_value'] = $attr_list;
            apiResponse('success','成功',$res);
        }else{
            M()->startTrans();
            $data = array();
            $data['parent_id'] = $_POST['parent_id']?$_POST['parent_id']:0;
            $data['second_id'] = $_POST['second_id']?$_POST['second_id']:0;
            $data['three_id'] = $_POST['three_id']?$_POST['three_id']:0;
            $data['title'] = $_POST['title'];
            $data['price'] = $_POST['price']?$_POST['price']:0;
            $data['stock'] = $_POST['stock']?$_POST['stock']:0;
            $data['goods_attr'] = $_POST['goods_attr']?$_POST['goods_attr']:"";
            $data['sort'] = $_POST['sort']?$_POST['sort']:0;
            $data['type'] = $_POST['type'];
            $data['postage']=$_POST['postage'];
//            $data['is_sale'] = $_POST['is_sale'];
            if (get_magic_quotes_gpc()) {
                $data['content'] = stripslashes($_POST['content']);
            } else {
                $data['content'] = $_POST['content'];
            }
            $attrs = json_decode($_POST['attrs'],true);
            if($attrs){
                $data['attr'] = serialize($attrs);
            }

            $data['cover_pic'] = $_POST['pic'];
            $data['pics'] = $_POST['pic_more'];

            $data['ctime'] = time();
            $pro_res = M('Product')->where(['p_id'=>$_POST['p_id']])->limit(1)->save($data);
            $prices = json_decode($_POST['prices'],true);
            $pri_res = array();
            $stock = 0;
            $id_del = [];
            $minprice = null;
            if($prices){
                foreach($prices as $k=>$v){
                    $price_data['p_id'] = $_POST['p_id'];
                    $price_data['attr_group'] = $v['attr'];
                    $price_data['price'] = $v['price'];
                    $price_data['stock'] = $v['stock'];
                    if(!empty($v['id'])){
                        $price_data['utime'] = time();
                        $pri_res[] = M('ProductPrice')->where(['id'=>$v['id']])->limit(1)->save($price_data);
                        $id_del[] = $v['id'];
                    }else{
                        $price_data['ctime'] = time();
                        $id = M('ProductPrice')->add($price_data);
                        $pri_res[] = $id;
                        $id_del[] = $id;
                    }
                    $stock+=$v['stock'];
                    if($minprice === null){
                        $minprice = $price_data['price'];
                    }else if($price_data['price'] < $minprice){
                        $minprice = $price_data['price'];
                    }
                    $price_data = [];
                }
            }
            if($_POST['type'] == 1){
                $stock_res = M('Product')->where(['p_id'=>$_POST['p_id']])->limit(1)->save(['utime'=>time()]);
            }else{
                $stock_res = M('Product')->where(['p_id'=>$_POST['p_id']])->limit(1)->save(['stock'=>$stock,'utime'=>time(),'price'=>$minprice]);
            }

            /**删除那些商品的属性*/
            M("ProductPrice")->where(['p_id'=>$_POST['p_id'],'id'=>array('not in',$id_del)])->save(['status'=>9]);
            if($pro_res&&!in_array(false,$pri_res)&&$stock_res){
                M()->commit();
                apiResponse('success','修改成功');
            }else{
                M()->rollback();
                apiResponse('error','修改失败');
            }
        }

    }

    /**商品的上下架
     * @author crazy
     * @time 2017-12-25
     * p_id 商品的id
     * is_sale 0下架 1上架
     */
    public function editSale(){
        $res = M("Product")->where(['p_id'=>$_POST['p_id']])->limit(1)->save(['is_sale'=>$_POST['is_sale'],'utime'=>time()]);
        if($res){
            switch($_POST['is_sale']){
                case 0:
                    apiResponse('success','下架成功');
                    break;
                case 1:
                    apiResponse('success','上架成功');
                    break;
            }

        }else{
            switch($_POST['is_sale']){
                case 0:
                    apiResponse('error','下架失败');
                    break;
                case 1:
                    apiResponse('error','上架失败');
                    break;
            }
        }
    }



    /**
     * 商品列表（商家端）
     * 传参方式 POST
     * @author mss
     * @time 2017-11-29
     * @param shop_id 商家id
     * @param state 商品状态，1出售中，2已售罄，3仓库中
     * @return p 分页页数
     */
    public function shopProList(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $state = $_POST['state'];
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $w['shop_id'] = $shop_id;
        $w['status'] = 0;
        switch($state){
            case 1:
                $w['is_sale'] = 1;
                $w['stock'] = array('GT',0);
                break;
            case 2:
                $w['is_sale'] = 1;
                $w['stock'] = 0;
                break;
            case 3:
                $w['is_sale'] = 0;
                break;
        }
        $concat = C("API_URL").'/';
        $field = "p_id,title,price,cover_pic,sales,stock,is_sale,type";
        $arr_list = [];
        $w['title'] = array('like','%'.$_POST['title'].'%');
        $list = M('Product')->where($w)->field($field)->order('sort ASC,ctime DESC')->limit($page,10)->select();
        foreach($list as $k=>$v){
            $list[$k]['cover_pic'] = $this->returnPic($v['cover_pic']);
        }
        $arr_list['list'] = $list?$list:[];
        if($_POST['title']){
            $arr_list['count_ing'] = M('Product')->where(['shop_id'=>$shop_id,'status'=>0,'is_sale'=>1,'stock'=>array('GT',0),'title'=>array('like','%'.$_POST['title'].'%')])->count();
            $arr_list['count_end'] = M('Product')->where(['shop_id'=>$shop_id,'status'=>0,'is_sale'=>1,'stock'=>0,'title'=>array('like','%'.$_POST['title'].'%')])->count();
            $arr_list['count_ware'] = M('Product')->where(['shop_id'=>$shop_id,'status'=>0,'is_sale'=>0,'title'=>array('like','%'.$_POST['title'].'%')])->count();
        }else{
            $arr_list['count_ing'] = M('Product')->where(['shop_id'=>$shop_id,'status'=>0,'is_sale'=>1,'stock'=>array('GT',0)])->count();
            $arr_list['count_end'] = M('Product')->where(['shop_id'=>$shop_id,'status'=>0,'is_sale'=>1,'stock'=>0])->count();
            $arr_list['count_ware'] = M('Product')->where(['shop_id'=>$shop_id,'status'=>0,'is_sale'=>0])->count();
        }
        if(empty($arr_list['list']) && $_GET['p']==1){
            apiResponse('error','暂无数据',$arr_list);
        }
        if(empty($arr_list['list'])&& $_GET['p']>1){
            apiResponse('success','已加载全部',$arr_list);
        }
        apiResponse("success",'获取成功',$arr_list);
    }

    /**
     * 商品上下架（商家端）
     * 传参方式 POST
     * @author mss
     * @time 2017-11-29
     * @param p_id 商品id
     * @param is_sale 将商品上架或下架，1上架，2下架
     */
    public function changeProState(){
        if(empty($_POST['p_id'])||empty($_POST['is_sale'])){
            apiResponse('error','参数错误');
        }
        $p_id = $_POST['p_id'];
        $state = $_POST['is_sale'];
        $product = M('Product')->where(array('p_id'=>$p_id))->field('p_id,is_sale,status')->find();
        if($state==1){
            if($product['is_sale']==1){
                apiResponse('error','商品已上架');
            }
            $data['is_sale'] = 1;
        }else{
            if($product['is_sale']==0){
                apiResponse('error','商品已下架');
            }
            $data['is_sale'] = 0;
        }
        $data['utime'] = time();
        $res = M('Product')->where(array('p_id'=>$p_id))->limit(1)->save($data);
        if($res){
            apiResponse('success','操作成功');
        }else{
            apiResponse('error','操作失败');
        }

    }
    
    /**删除商品
     * @author crazy
     * @time 2017-12-15
     * post
     * @param
     */
    public function delProduct(){
        $res = M("Product")->where(['p_id'=>I("post.p_id")])->limit(1)->save(['status'=>9]);
        if($res){
            apiResponse('success','操作成功');
        }else{
            apiResponse('error','操作失败');
        }
    }


    public function uploadPic($shop_id,$arr){
        $pic_arr = array();
        $string = '';
        foreach ($arr as $k=>$v){
            $pic       = $v['pic'];
            $pic_name      = $v['pic_name'];
            $temp = explode('.',$pic_name);
            $ext = uniqid().'.'.end($temp);
            $base64    = substr(strstr($pic, ","), 1);
            $image_res = base64_decode($base64);
            $pic_link  = "/Uploads/Product/".date('Y-m-d').'/'.uniqid().'.jpg';
            $saveRoot = "Uploads/Product/".date('Y-m-d').'/';
            /**检查目录是否存在  循环创建目录*/
            if(!is_dir($saveRoot)){
                mkdir($saveRoot, 0777, true);
            }
            $res = file_put_contents($pic_link ,$image_res);
            if($res){
                $pic_arr[] = $pic_link;
                $pics[$k] = M('Picture')->add(array('shop_id'=>$shop_id,'pic'=>$pic_link,'ctime'=>time()));
            }else{
                apiResponse("error","图片上传失败！");
            }
        }
        $string = implode(",",$pic_arr);
        $pic_str = implode(',',$pics);
//        return $pic_str;
        apiResponse('success','图片上传成功',$pic_str);
    }

    /**
     * 商品列表（用户端）
     * 传参方式 get
     * @author mss
     * @time 2017-11-16
     * @param p 页数
     * @param cate_id 分类id
     * @param shop_id 商家id，查看商家的商品时传
     * @param order 排序 1综合排序，2销量最高，3价格最低
     * @param  title 商品名称模糊匹配
     */
    public function productList(){
        $p = $_GET['p']?$_GET['p']:1;
        $page = ($p-1)*10;
        $where['status'] = array('NEQ',9);
        $where['is_sale'] = 1;
        $where['stock'] = array('GT',0);
        if(!empty($_GET['parent_id'])){
            $where['parent_id'] = $_GET['parent_id'];
        }
        if(!empty($_GET['second_id'])){
            $where['second_id'] = $_GET['second_id'];
        }
        if(!empty($_GET['three_id'])){
            $where['three_id'] = $_GET['three_id'];
        }
        if(!empty($_GET['title'])){
            $where['title'] = array('LIKE','%'.$_GET['title'].'%');
        }
        if(!empty($_GET['shop_id'])){
            $where['shop_id'] = $_GET['shop_id'];
        }
        $order = $_GET['order'];
        switch($order){
            case 1:
                $order = 'sales DESC,price ASC,sort ASC';
                break;
            case 2:
                $order = 'sales DESC';
                break;
            case 3:
                $order = 'price ASC';
                break;
            default:
                $order = 'sort ASC';
                break;
        }
//        $concat = C("API_URL").'/';
        $field = "p_id,title,price,cover_pic,sales,stock,type";
        $list = M('Product')->where($where)->field($field)->order($order)->limit($page,10)->select();
        foreach($list as $k=>$v){
            $list[$k]['cover_pic'] = $this->returnPic($v['cover_pic']);
        }
        $arr = $this->getListCodeMessage($list,$_GET['p']);
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }

    /**
     * 商品详情（用户端）
     * 传参方式 get
     * @author mss
     * @time 2017-11-16
     * @param p_id 商品id
     * @param m_id 用户id
     */
    public function productDetail(){
        if(empty($_GET['p_id'])){
            apiResponse('error','参数错误');
        }
        $p_id = $_GET['p_id'];
        $info = M('Product')->where(array('p_id'=>$p_id,'status'=>array('NEQ',9)))->field('p_id,shop_id,type,price,cover_pic,title,content,pics,sales,stock,attr,postage,goods_attr')->find();
        if(!$info){
            apiResponse('error','商品不存在');
        }
        $pics = array();
        if($info['pics']){
            $pics = explode(',',$info['pics']);//商品轮播图
            foreach($pics as $key=>$val){
                $pics[$key] = $this->returnPic($val);
            }
        }
        $data = array();
        $postage = $info['postage']?$info['postage']:"0.00";
        if($info['type'] == 1){
            $price = $info['price'];
            $data['price'] = $price?$price:"0.00";
            $data['stock'] = $info['stock']?$info['stock']:0;
        }else{
//            //查询商家的运费
//            $postage = M('Shop')->where(array('shop_id'=>$info['shop_id']))->limit(1)->getField('postage');
            $price = M("ProductPrice")->where(['p_id'=>$p_id,'status'=>0])->order('price asc')->field("price,stock")->find();
            $data['price'] = $info['price']?$info['price']:"0.00";
            $data['stock'] = $price['stock']?$price['stock']:0;
        }
        //商家信息
        $shop = M('Shop')->where(array('shop_id'=>$info['shop_id']))->find();
        //收藏商家的人数
        $c_num = M('Collect')->where(array('shop_id'=>$info['shop_id'],'status'=>0))->count();
        //商家的商品数量
        $p_num = M('Product')->where(array('shop_id'=>$info['shop_id'],'status'=>0,'is_sale'=>1))->count();
        $data['p_id'] = $info['p_id'];
        $data['shop_id'] = $info['shop_id'];
        $data['shop_name'] = $shop['name'];
        $data['shop_pic'] = $shop['head_pic']?C('API_URL').'/'.$shop['head_pic']:C('API_URL').'/Uploads/logo.png';
        $data['shop_star'] = $shop['star'];
        $data['collect_num'] = $c_num;
        $data['product_num'] = $p_num;
        $data['title'] = $info['title'];

        $data['pic'] = $this->returnPic($info['cover_pic']);
        $data['sales'] = $info['sales'];
        $data['pics'] = $pics;
        $data['type'] = $info['type'];
        $data['goods_attr'] = $info['goods_attr']?$info['goods_attr']:"";
        $data['content'] = $info['content'];
        $data['collect'] = $this->isCollect($_GET['m_id'],$p_id);
        $data['postage'] = $postage;
        apiResponse('success','成功',$data);
    }

    /**
     * 指定商品的规格属性（用户端）
     * 传参方式 get
     * @author mss
     * @time 2017-11-15
     * @param p_id 商品id
     */
    public function productAttr(){
        if(empty($_GET['p_id'])){
            apiResponse('error','参数错误');
        }
        $p_id = $_GET['p_id'];
        $attr = M('Product')->where(array('p_id'=>$p_id))->limit(1)->getField('attr');
        if(!$attr){
            apiResponse('success','暂无属性');
        }
//        $attr = 'a:2:{i:0;a:2:{s:4:"attr";s:6:"颜色";s:3:"val";s:20:"红色|黄色|蓝色";}i:1;a:2:{s:4:"attr";s:6:"尺码";s:3:"val";s:5:"L|M|S";}}';
        $attrs = unserialize($attr);
        $list = array();
        foreach($attrs as $k=>$v){
            $arr_val_return = [];
            /**存的id，然后获取一下名称*/
            $val_arr = M("Attribute")->where(['attr_id'=>$v['attr']])->field("attr_id,attr_name")->find();
            $list[$k]['attr_name'] = $val_arr;
            $arr_val = explode('|',$v['val']);
            foreach($arr_val as $kk=>$vv){
                $arr_val_return[] = M("AttrValue")->where(['val_id'=>$vv])->field("val_id,attr_value")->find();
            }
            $list[$k]['attr_val'] = $arr_val_return;
        }
        apiResponse('success','成功',$list);
    }

    /**
     * 商品属性规格下的价格（用户端）
     * 传参方式 post
     * @author mss
     * @time 2017-11-17
     * @param p_id 商品id
     * @param attr 属性 如 红色|L
     */
    public function productPrice(){
        if(empty($_POST['p_id'])){
            apiResponse('error','参数错误');
        }
        $p_id = $_POST['p_id'];
        $attr = $_POST['attr'];
        $res = M('ProductPrice')->where(array('p_id'=>$p_id,'attr_group'=>$attr,'status'=>0))->field('price,stock')->find();
        $data = array();
        if($res){
            $data['price'] = $res['price'];
            $data['stock'] = $res['stock'];
        }else{
            $data['price'] = 0;
            $data['stock'] = 0;
        }
        apiResponse('success','成功',$data);
    }

    /**
     * 商品的评价（用户端和商家端公用）
     * 传参方式 POST
     * @author mss
     * @time 2017-11-16
     * @param p_id 商品id
     * @param p 页数
     * @param shop_id 商家id
     */
    public function appraise(){
        if(empty($_POST['p_id'])){
            apiResponse('error','参数错误');
        }
        $p_id = $_POST['p_id'];
        $p = $_POST['p']?$_POST['p']:1;
        $w['p_id'] = $p_id;
        if(!empty($_POST['shop_id'])){
            $w['status'] = array('NEQ',9);
        }else{
            $w['status'] = 1;
        }
        $data = $this->commonAppraise($w,$p);
        apiResponse('success','成功',$data);

    }

    /**
     * 用户收藏商品
     * 传参方式 post
     * @author mss
     * @time 2017-11-16
     * @param m_id 用户id
     * @param p_id 商品id
     */
    public function collect(){
        if(empty($_POST['m_id'])||empty($_POST['p_id'])){
            apiResponse('error','参数错误');
        }
        $type = $_POST['type'];
        $is_collect = $this->isCollect($_POST['m_id'],$_POST['p_id'],$type);
        if($is_collect == 0){
            $data['m_id'] = $_POST['m_id'];
            $data['goods_id'] = $_POST['p_id'];
            $data['type'] = $_POST['type'];
            $data['ctime'] = time();
            $res = M('CollectGoods')->add($data);
        }else{
            $res = $is_collect;
        }
        if($res){
            apiResponse('success','收藏成功',$res);
        }else{
            apiResponse('error','收藏失败');
        }
    }

    /**
     * 用户取消收藏
     * 传参方式 post
     * @author mss
     * @time 2017-11-16
     * @param c_id 收藏id
     */
    public function cancelCollect(){
        if(empty($_POST['c_id'])){
            apiResponse('error','参数错误');
        }
        if($_POST['type'] == 0){
            $where['c_id'] = ['in',json_decode($_POST['c_id'],true)];
            $data['status'] = 9;
            $data['utime'] = time();
            $res = M('CollectGoods')->where($where)->save($data);
        }else{
            $where['c_id'] = ['in',json_decode($_POST['c_id'],true)];
            $data['status'] = 9;
            $data['utime'] = time();
            $res = M('Collect')->where($where)->save($data);
        }
        if($res){
            apiResponse('success','取消收藏成功');
        }else{
            apiResponse('error','取消收藏失败');
        }
    }

    /**
     * 收藏的商品列表
     * 传参方式 post
     * @author mss
     * @time 2017-11-16
     * @param m_id 用户id
     * @pram p 页数
     */
    public function collectList(){
        if(empty($_POST['m_id'])){
            apiResponse('error','参数错误');
        }
        $p = $_POST['p']?$_POST['p']:1;
        $page = ($p-1)*10;
        $m_id = $_POST['m_id'];
        $where['m_id'] = $m_id;
        $where['status'] = array('NEQ',9);
        $list = M('CollectGoods')->where($where)->field('c_id,goods_id,type')->order('ctime DESC')->limit($page,10)->select();
        $data = array();
        foreach($list as $k=>$v){
            $concat = C("API_URL").'/';
            $data[$k]['c_id'] = $v['c_id'];
            $data[$k]['p_id'] = $v['goods_id'];
            if($v['type'] == 0){
                $pro = M('Product')->where(array('p_id'=>$v['goods_id']))->field("title,cover_pic,price,sales,type,is_sale,status")->order('sort ASC')->find();
//                apiResponse(M('Product')->getLastSql());
                if($pro['type'] == 1){
                    $price = $pro['price'];
                }else{
                    $price = M("ProductPrice")->where(['p_id'=>$v['goods_id']])->order('ctime asc')->getField("price");
                }
                $data[$k]['price'] = $price?$price:"0.00";
                $data[$k]['title'] = $pro['title'];
                $data[$k]['pic']   = $this->returnPic($pro['cover_pic']);
                $data[$k]['sales'] = $pro['sales'];
                $data[$k]['type'] = $v['type'];
                $data[$k]['is_sale'] = $pro['is_sale']?0:1;
                $data[$k]['status'] = $pro['status'];
            }else{
                $pro = M('Goods')->where(array('g_id'=>$v['goods_id']))->field("name,CONCAT('$concat',cover_pic) AS cover_pic,price,sales,is_show,status")->order('sort ASC')->find();
                $data[$k]['title'] = $pro['name'];
                $data[$k]['pic'] = $this->returnPic($pro['cover_pic']);;
                $data[$k]['price'] = $pro['price'];
                $data[$k]['sales'] = $pro['sales'];
                $data[$k]['type'] = $v['type'];
                $data[$k]['is_sale'] = $pro['is_show']?0:1;
                $data[$k]['status'] = $pro['status'];
            }

        }
        $arr = $this->getListCodeMessage($data,$page);
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }


    /**上传图片
     * 传参方式 post
     * @author crazy
     * @time 2017-12-21
     */
    public function uploadImgPhone(){
        $res = $this->uploadImg("Product",'pic');
        $data_url = "/Uploads/".$res;
        $url = C("API_URL");
        $img_info = getimagesize($url.$data_url);
        $img_width = $img_info[0];
        $img_height = $img_info[1];
        $data['code'] = "success";
        $data['data']['width'] = $img_width;
        $data['data']['height'] = $img_height;
        $data['data']['url'] = $url.$data_url;
        print json_encode($data);
    }


  
}