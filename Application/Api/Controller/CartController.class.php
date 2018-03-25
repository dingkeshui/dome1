<?php
/**
 * Created by PhpStorm.
 * User: zxty
 * Date: 2017/11/15
 * Time: 9:53
 */

namespace Api\Controller;
/**
 * Class CartController
 * @package Api\Controller
 * 用户购物车
 */

class CartController extends ApiBasicController
{
    /**
     * 购物车列表
     * 传参方式 post
     * @author mss
     * @time 2017-11-15
     * @param m_id 用户id
     */
    public function cartList(){
        if(empty($_POST['m_id'])){
            apiResponse('error','参数错误');
        }
        $m_id = $_POST['m_id'];
        $where['m_id'] = $m_id;
        $shop_id = M('Cart')->where($where)->distinct(true)->order('ctime DESC')->getField('shop_id',true);
        if(!$shop_id){
            apiResponse('success','暂无数据');
        }
        $list = array();
        foreach($shop_id as $k=>$v){
            $list[$k]['shop_id'] = $v;
            $list[$k]['shop_name'] = M('Shop')->where(array('shop_id'=>$v))->limit(1)->getField('name');
//            $concat = C("API_URL").'/';
            $sql = "SELECT c.cart_id,c.num,c.p_id,c.price,c.attr_val,p.title,p.cover_pic,p.`status`,p.`type`,p.`is_sale` from zxty_cart as c ,zxty_product AS p WHERE p.p_id=c.p_id AND c.m_id=$m_id AND c.shop_id=".$v." ORDER BY c.ctime DESC";
            $pro = M()->query($sql);
            foreach($pro as $key=>$val){
                if($val['status']!=9){
                    if($val['is_sale'] == 0){
                        $pro[$key]['status'] = 9;
                    }
                }
                if($val['attr_val']){
                    /**转换属性值*/
                    $pro[$key]['attr_val_id'] = $val['attr_val'];
                    $arr_val = explode('|',$val['attr_val']);
                    $arr_val_return = "";
                    foreach($arr_val as $kk=>$vv){
                        $arr_val_return .= M("AttrValue")->where(['val_id'=>$vv])->getField("attr_value").";";
                    }
                    $pro[$key]['attr_val'] = substr($arr_val_return,0,-1);
                    $stock = $this->checkStock($val['p_id'],$val['num'],$val['attr_val']);
                    if($stock){
                        $pro[$key]['is_stock'] = 1;
                    }else{
                        $pro[$key]['is_stock'] = 0;
                    }
                }else{
                    $w_attr = M("Product")->where(['p_id'=>$val['p_id']])->field("goods_attr,stock")->find();
                    $pro[$key]['attr_val'] =$w_attr['goods_attr'];
                    $pro[$key]['is_stock'] = $w_attr['stock']?1:0;
                }
                $pro[$key]['stock'] = $this->stock($val['p_id'],$val['attr_val']);
                $pro[$key]['cover_pic'] = $this->returnPic($val['cover_pic']);
            }
            $list[$k]['product'] = $pro;

        }
        apiResponse('success','成功',$list);
    }


    /**
     * 检查商品库存是否足够
     * @author mss
     * @time 2017-12-01
     * param p_id 商品id
     * param num 商品数量
     * param attr 商品规格属性
     */
    private function stock($p_id,$attr){
        if(empty($attr)){
            $stock = M('Product')->where(array('p_id'=>$p_id))->getField('stock');
        }else{
            $stock = M('ProductPrice')->where(array('p_id'=>$p_id,'attr_group'=>$attr,'status'=>0))->getField('stock');
        }
        return $stock;
    }


    /**
     * 加入购物车
     * 传参方式 post
     * @author mss
     * @time 2017-11-15
     * @param m_id 用户id
     * @param shop_id 商家id
     * @param p_id 商品id
     * @param num 商品数量
     * @param price 商品价格
     * @param attr 商品属性(如 红色;M;其他)
     */
    public function addCart(){
        if(empty($_POST['m_id'])||empty($_POST['shop_id'])||empty($_POST['p_id'])||empty($_POST['num'])||empty($_POST['price'])){
            apiResponse('error','参数错误');
        }
        //检查商品库存
        if(empty($_POST['attr'])){
            $w_attr_stock = M("Product")->where(['p_id'=>$_POST['p_id']])->getField("stock");
            if($w_attr_stock<=0){
                apiResponse('error','商品库存不足');
            }
        }else{
            if(!$this->checkStock($_POST['p_id'],$_POST['num'],$_POST['attr'])){
                apiResponse('error','商品库存不足');
            }
        }
        $data = array();
        $data['m_id'] = $_POST['m_id'];
        $data['shop_id'] = $_POST['shop_id'];
        $data['p_id'] = $_POST['p_id'];
        $data['attr_val'] = $_POST['attr']?$_POST['attr']:'';
        $data['price'] = $_POST['price'];
        $num = $_POST['num'];
        //查看购物车中是否存在该商品
        $cart = M('Cart')->where($data)->field('cart_id,num')->find();
        if($cart){
            $res = M('Cart')->where(array('cart_id'=>$cart['cart_id']))->limit(1)->save(array('num'=>($num+$cart['num']),'utime'=>time()));
        }else{
            $data['num'] = $num;
            $data['ctime'] = time();
            $res = M('Cart')->add($data);
        }
        if($res){   
            apiResponse('success','添加成功');
        }else{
            apiResponse('error','添加失败');
        }
    }

    /**
     * 编辑购物车信息
     * 传参方式 post
     * @author mss
     * @time 2017-11-15
     * @param cart_id 购物车id
     * @param num 数量
     * @param attr 属性
     */
    public function editCart(){
        $cart_id = $_POST['cart_id'];
        $data = [];
        if(!empty($_POST['num'])){
            $data['num'] = $_POST['num'];
        }
        $attr = $_POST['attr'];

        //查看购物车中是否存在该属性的商品
        $cart = M('Cart')->where(array('cart_id'=>$cart_id))->find();
        $is_cart = M('Cart')->where(array('p_id'=>$cart['p_id'],'m_id'=>$cart['m_id'],'attr_val'=>$attr,'cart_id'=>array('neq',$cart_id)))->
        field('cart_id,p_id,num,attr_val')->find();

        /**只有当有属性的时候才会更改价格*/
        if(!empty($attr)){
            $price = M('ProductPrice')->where(array('p_id'=>$cart['p_id'],'attr_group'=>$attr,'status'=>0))->getField('price');
            $data['attr_val'] = $attr;
            $data['price'] = $price;
        }
        //如果存在，则删除当前的购物车信息，如果不存在，则对当前信息进行修改
        if($is_cart){
            if(empty($attr)){
                $w_attr_stock = M("Product")->where(['p_id'=>$cart['p_id']])->getField("stock");
                if($w_attr_stock<=$_POST['num']){
                    apiResponse('error','商品库存不足');
                }
            }else{
                if(!$this->checkStock($is_cart['p_id'],$is_cart['num'],$is_cart['attr_val'])){
                    apiResponse('error','商品库存不足');
                }
            }
            $res = M('Cart')->where(array('cart_id'=>$cart_id))->limit(1)->delete();
        }else{
            if(empty($attr)){
                $w_attr_stock = M("Product")->where(['p_id'=>$cart['p_id']])->getField("stock");
                if($w_attr_stock<=$_POST['num']){
                    apiResponse('error','商品库存不足');
                }
            }else{
                if(!$this->checkStock($cart['p_id'],$data['num'],$data['attr_val'])){
                    apiResponse('error','商品库存不足');
                }
            }
            $data['utime'] = time();
            $res = M('Cart')->where(array('cart_id'=>$cart_id))->limit(1)->save($data);
        }
        if($res){
            apiResponse('success','操作成功');
        }else{
            apiResponse('error','操作失败');
        }
    }

    /**
     * 删除购物车信息
     * 传参方式 post
     * @author mss
     * @time 2017-11-15
     * @param cart_id 购物车id(数组)
     */
    public function delCart(){
        if(empty($_POST['cart_id'])){
            apiResponse('error','参数错误');
        }
        $cart_id = $_POST['cart_id'];
        $where['cart_id'] = array('IN',$cart_id);
        $res = M('Cart')->where($where)->delete();
        if($res){
            apiResponse('success','删除成功');
        }else{
            apiResponse('error','删除失败');
        }
    }

    /**
     * 检查商品库存是否足够
     * @author mss
     * @time 2017-12-01
     * param p_id 商品id
     * param num 商品数量
     * param attr 商品规格属性
     */
    private function checkStock($p_id,$num,$attr){
        if(empty($p_id)||empty($num)){
            apiResponse('error','参数错误');
        }
        if(empty($attr)){
            $stock = M('Product')->where(array('p_id'=>$p_id))->getField('stock');

        }else{
            $stock = M('ProductPrice')->where(array('p_id'=>$p_id,'attr_group'=>$attr,'status'=>0))->getField('stock');
        }
        if($stock<$num){
            return false;
        }else{
            return $stock;
        }
    }


    /**
     * 显示购物车数量
     * @author mss
     * @time mss
     * @param m_id 用户id
     */
    public function cartNumber(){
        $m_id = $_POST['m_id']?$_POST['m_id']:0;
        $data = $this->cartNum($m_id);
        apiResponse('success','成功',$data);
    }

}