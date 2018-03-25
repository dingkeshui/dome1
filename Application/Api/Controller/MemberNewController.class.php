<?php
namespace Api\Controller;
/**商家端第二版功能集合*/
class MemberNewController extends ApiBasicController
{
    public function _initialize()
    {
        parent::_initialize();
    }


    /**
     * 商家端首页广告图片
     * @author crazy
     * @time 2017-11-27
     */
    public function advertIndex(){
        $page = $_GET['p']?$_GET['p']:1;
        $p = ( $page - 1)*15;
        /**查找广告*/
        /**查找广告*/
        if($_GET['is_app'] == 1){
            $advert_list = M('Advert')->where(array('is_shop'=>2,'is_app'=>1,'status'=>array('neq',9)))->field('a_id,name,pic,url')->order('sort asc')->limit($p,15)->select();
        }else{
            $advert_list = M('Advert')->where(array('is_shop'=>2,'status'=>array('neq',9)))->field('a_id,name,pic,url')->order('sort asc')->limit($p,15)->select();
        }
        foreach ($advert_list as $k=>$v){
            $advert_list[$k]['pic'] = C("API_URL").'/Uploads/'.$v['pic'];
        }
        apiResponse("success","获取成功！",$advert_list?$advert_list:[]);
    }


}