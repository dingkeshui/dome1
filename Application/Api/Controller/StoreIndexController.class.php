<?php
namespace Api\Controller;
use Think\Controller;
/**商城的首页api*/
class StoreIndexController extends ApiBasicController
{
    public function _initialize(){
        parent::_initialize();
    }


    public function index(){
        /**查找广告*/
        $advert_list = M('Advert')->where(array('is_shop'=>1,'status'=>array('neq',9)))->field('a_id,name,pic,url')->order('sort asc')->select();
        foreach ($advert_list as $key=>$val){
            if(!empty($val['pic'])){
                $advert_list[$key]['pic'] = C("API_URL")."/Uploads/".$val['pic'];
            }
        }
        /**查找商品的一级分类*/
        $class_list = M('Category')->where(array('is_show'=>1,'status'=>array('neq',9),'parent_id'=>0))->order('sort asc')->field('cate_id,pic,category,pic')->select();
        foreach($class_list as $kk=>$vv){
            if(!empty($vv['pic'])){
                $class_list[$kk]['pic'] = C("API_URL")."/Uploads/".$vv['pic'];
            }
        }
        /**找到楼层*/
        $p = $_GET['p']?($_GET['p']-1):0;
        $list = M("Ladder")->where(array('is_show'=>1,'status'=>array('neq',9)))->order('sort asc')->field('l_id,pic,name')->limit($p*15,15)->select();
        foreach ($list as $k=>$v){
            if(!empty($v['pic'])){
                $list[$k]['pic'] = C("API_URL")."/Uploads/".$v['pic'];
            }
            /**楼层下面的广告*/
            $advert_list_down = M('ShopAdvert')->where(array('is_show'=>1,'status'=>array('neq',9),'ladder_id'=>$v['l_id']))->order('position asc')->field('s_a_id as a_id,title,pic,link_url')->select();
            foreach($advert_list_down as $adv_key=>$adv_val){
                $advert_list_down[$adv_key]['pic'] = C("API_URL")."/Uploads/".$adv_val['pic'];
            }
            $list[$k]['down_advert'] = $advert_list_down?$advert_list_down:"";
        }
        $data['advert_list'] = $advert_list?$advert_list:"";
        $data['class_list'] = $class_list?$class_list:"";
        $data['ladder_list'] = $list?$list:"";
        apiResponse('success','获取成功！',$data);

    }

    /**
     * 商家详情（用户端）
     * 传参方式 POST
     * @author mss
     * @time 2017-11-30
     * @param shop_id 商家id
     * @param m_id 用户id
     */
    public function storeDetail(){
        if(empty($_POST['shop_id'])){
            apiResponse('error','参数错误');
        }
        $shop_id = $_POST['shop_id'];
        $m_id = $_POST['m_id']?$_POST['m_id']:0;
        //查询商家信息
        //$concat = C("API_URL").'/';
        $shop = M('Shop')->where(array('shop_id'=>$shop_id))->field("shop_id,account,name,head_pic,time,star,address,class_id,area,notice")->find();
        $class = M('Class')->where(array('class_id'=>$shop['class_id']))->getField('name');
        $area = '';
        if(!empty($shop['area'])){
            $area = M('Areas')->where(array('area_id'=>$shop['area']))->getField("area_name");
        }
        //商家客服电话
        $tels = M('ShopTel')->where(array('shop_id'=>$shop_id,'status'=>0))->field('tel_one,tel_two,tel_three,tel_four,tel_five')->find();

        //判断用户是否收藏该商家
        $is_collect = 0;
        if($m_id){
            $is_collect = M('Collect')->where(array('status'=>0,'m_id'=>$m_id,'shop_id'=>$shop_id))->getField('c_id');
        }
        $data['shop_id'] = $shop['shop_id'];
        $data['shop_name'] = $shop['name'];
        $data['notice'] = $shop['notice'];
        $data['head_pic'] = $shop['head_pic']?C("API_URL").'/'.$shop['head_pic']:C("API_URL").'/Uploads/logo.png';
        $data['star'] = $shop['star'];
        $data['class'] = $class;
        $data['area'] = $area?$area:"暂无";
        $data['address'] = $shop['address'];
        $data['is_collect'] = $is_collect?1:0;
        $data['time'] = $shop['time'];
        $data['tels'] = $tels?$tels:['tel_one'=>$shop['account']];
        //查询商家的优惠券
        $w['shop_id'] = $shop_id;
        $w['status'] = 0;
        $w['start_time'] = array('ELT',date('Y-m-d'));
        $w['end_time'] = array('GT',date('Y-m-d'));
//        $coupon = M('Coupon')->where($w)->field('coupon_id,title,desc,type,money,start_time,end_time,min_price,max_price')->order('ctime DESC')->select();
        //查询评价列表
        $appraise = array();
        $app_list = M('Appraise')->where(array('shop_id'=>$shop_id,'status'=>1))->field('app_id,m_id,star,content,pic,ctime')->limit(10)->select();
        if(!$app_list){
            $appraise = array();
        }else{
            foreach($app_list as $k=>$v){
                $appraise[$k]['app_id'] = $v['app_id'];
                $appraise[$k]['star'] = $v['star'];
                $appraise[$k]['content'] = $v['content'];
                $member = M('Member')->where(array('m_id'=>$v['m_id']))->field('nick_name,head_pic')->find();
                if(strpos($member['head_pic'],'http://')!==false){
                    $appraise[$k]['head_pic'] = $member['head_pic'];
                }else{
                    $appraise[$k]['head_pic'] = C('API_URL').$member['head_pic'];
                }
                $appraise[$k]['nick_name'] = $member['nick_name'];
            }
        }
        $data['appraise'] = $appraise?$appraise:[];
        /**获取商家的相册的数量*/
        $count = M("Picture")->where(['status'=>0,'shop_id'=>$shop_id])->count();
        $data['album_count'] = $count?$count:0;
        $concat = C('API_URL').'/';
        $album_list = M("Picture")->where(['status'=>0,'shop_id'=>$shop_id])->field("pic_id,CONCAT('$concat',pic) as pic")->order("ctime desc")->limit(0,5)->select();
        $data['album_list'] = $album_list?$album_list:[];
        apiResponse('success','获取成功',$data);
    }


    /**商家的认证费用的列表
     * @author crazy
     * @time 2018-01-01
     * @p 分页
     */
    public function approvePriceList(){
        $p = $_POST['p']?($_POST['p']-1)*10:0;
        $list = M("ApprovePrice")->where(['status'=>0])->field("id as app_id,title,price,content,status")->limit($p,10)->select();
        $arr = $this->getListCodeMessage($list,$_POST['p']);
        apiResponse($arr['code'],$arr['message'],$arr['list']);
    }

}
