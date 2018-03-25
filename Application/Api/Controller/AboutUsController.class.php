<?php
namespace Api\Controller;
use Think\Controller;
/**
 * Class AboutUsController
 * @package Api\Controller
 * 关于我们模块
 */
class AboutUsController extends ApiBasicController{
    public $Config = '';
    public function _initialize(){
        parent::_initialize();
        $this->Config = D('Config');

    }
    /**
     * 关于我们
     * @author crazy
     * @time 2017-05-03
     */
    public function aboutUs(){
        $data = array();
        $about = $this->Config->getField("about");
        preg_match_all('/src=\"\/?(.*?)\"/',$about,$match);
        foreach($match[1] as $key => $src){
            if(!strpos($src,'://')){
                $about = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=60%",$about);
            }
        }
        $data['content'] = $about;
        if(empty($data)){
            apiResponse("error","加载失败");
        }else{
            apiResponse("success","加载成功",$data);
        }
    }


    /**
     * 签到展示的图片
     * @param  type 1是用户  0是商家
     * @time 2017-12-07
     * @author crazy
     */
    public function showPic(){
        $type = $_POST['type'];
        $mix_id = $_POST['mix_id'];
        $arr = array();
        if($_POST['lat'] || $_POST['lnt']){
            $lat = $_POST['lat'];
            $lnt = $_POST['lnt'];
            $res = $this->lngLatCityN($lnt,$lat);
            $arr = json_decode($res,true);
        }
        if($arr['area_id']){
            $w['city'] = $arr['area_id'];
        }else{
            $w['is_quan'] = 1;
        }
        $w['status'] = array('neq',9);
        $field = "a_id,name,pic,url";
        if($type == 1){
            $w['position'] = 2;
            $res = M("Advert")->where($w)->order('ctime desc')->field($field)->find();
            if(empty($res['pic'])){
                $other_res = M("Advert")->where(array('status'=>array('neq',9),'position'=>2,'is_quan'=>1))->order('ctime desc')->field($field)->find();
                $res['pic'] = '/Uploads/'.$other_res['pic'];
                $res['url'] = $other_res['url'];
                $res['name'] = $other_res['name'];
                $res['a_id'] = $other_res['a_id'];
            }else{
                $res['pic'] = '/Uploads/'.$res['pic'];
            }
            /**获取用户的签到的次数*/
            $number = M("Sign")->where(array('m_id'=>$mix_id,'type'=>0))->limit(1)->getField('number');
            $res['number'] = $number?$number:"0";
            /**先判断用户是否在满足的条件下*/
            $begin_invest = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $invest_w['ctime'] = array('ELT',$begin_invest);
            $invest_w['mix_id'] = $_POST['mix_id'];
            $invest_w['type'] = 0;
            $count = M("Pie")->where($invest_w)->count();
            //file_put_contents("count.txt",M("Pie")->getLastSql());
            $total_w['id'] = 1;
            $mem_price = M("Total")->where($total_w)->limit(1)->getField("mem_price");
            $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
            $where['m_id'] = $_POST['mix_id'];
            $where['type'] = 0;
            $where['sign_time'] = array(array('EGT',$begin),array('ELT',$end),'and');
            $res_sign = M("Sign")->where($where)->getField("id");
            if($res_sign){
                $res['is_sign'] = 1;
            }else{
                $res['is_sign'] = 0;
            }
            /**先判断用户是否在满足的条件下，这个地方要查找显示昨天之前的满足能消费的用户的标准*/
            $piles = M("Member")->where(array('m_id'=>$_POST['mix_id']))->limit(1)->getField("piles");
            if($piles<$count){
                $price = $mem_price*$piles;
            }else{
                $price = $mem_price*$count;
            }
            if($piles<=0 || $count<=0){
                $res['is_true'] = 1;
            }else{
                $res['is_true'] = 0;
            }
            $res['price'] = $price;
            if(empty($res)){
                apiResponse("error","加载失败");
            }else{
                apiResponse("success","加载成功",$res);
            }
        }else{
            $w['position'] = 3;
            $res = M("Advert")->where($w)->order('ctime desc')->field($field)->limit(1)->find();
            if(empty($res)){
                $other_res = M("Advert")->where(array('status'=>array('neq',9),'position'=>3,'is_quan'=>1))->order('ctime desc')->field($field)->limit(1)->find();
                $res['pic'] = '/Uploads/'.$other_res['pic'];
                $res['url'] = $other_res['url'];
                $res['a_id'] = $other_res['a_id'];
            }else{
                $res['pic'] = '/Uploads/'.$res['pic'];
            }
            /**获取用户的签到的次数*/
            $number = M("Sign")->where(array('m_id'=>$mix_id,'type'=>1))->limit(1)->getField('number');
            $res['number'] = $number?$number:"0";
            /**判断这个用户是今天是否签到了*/
            $begin = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $end = mktime(23,59,59,date('m'),date('d'),date('Y'));
            $where['m_id'] = $mix_id;
            $where['type'] = 1;
            $where['sign_time'] = array(array('EGT',$begin),array('ELT',$end),'and');
            $res_sign = M("Sign")->where($where)->getField("id");
            if($res_sign){
                $res['is_sign'] = 1;
            }else{
                $res['is_sign'] = 0;
            }
            /**先判断用户是否在满足的条件下，这个地方要查找显示昨天之前的满足能消费的用户的标准*/
            $begin_other = mktime(0,0,0,date('m'),date('d'),date('Y'));
            $invest_w['ctime'] = array('ELT',$begin_other);
            $invest_w['mix_id'] = $mix_id;
            $invest_w['type'] = 1;
            $count = M("Pie")->where($invest_w)->count();
            $piles = M("Shop")->where(array('shop_id'=>$mix_id))->limit(1)->getField("piles");
            if($piles<=0 || $count<=0){
                $res['is_true'] = 1;
            }else{
                $res['is_true'] = 0;
            }
            /**找到用户每人可以领的钱数*/
            $total_w['id'] = 1;
            $mem_price = M("Total")->where($total_w)->limit(1)->getField("mem_price");
            if($piles<$count){
                $price = $mem_price*$piles;
            }else{
                $price = $mem_price*$count;
            }
            $res['price'] = $price;
            if(empty($res)){
                apiResponse("error","加载失败");
            }else{
                apiResponse("success","加载成功",$res);
            }
        }
    }

    /**
     * 模式介绍
     * @author crazy
     * @time 2017-05-03
     */
    public function pattern(){
        $data = array();
        $pattern = $this->Config->getField("pattern");
        preg_match_all('/src=\"\/?(.*?)\"/',$pattern,$match);
        foreach($match[1] as $key => $src){
            if(!strpos($src,'://')){
                $pattern = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=60%",$pattern);
            }
        }
        $data['content'] = $pattern;
        if(empty($data)){
            apiResponse("error","加载失败");
        }else{
            apiResponse("success","加载成功",$data);
        }
    }
    /**
     * 客服电话
     * @author crazy
     * @time 2017-05-03
     */
    public function service(){
        $service = $this->Config->getField("service");
        $data['tel'] = $service;
        if(empty($data)){
            apiResponse("error","加载失败");
        }else{
            apiResponse("success","加载成功",$data);
        }
    }



    /**
     * 文章详情
     */
    public function articleDetail(){
        $res = M("Article")->where(array('article_id'=>$_GET['article_id']))->field("title,content,author,look,ctime,desc")->limit(1)->find();
        preg_match_all('/src=\"\/?(.*?)\"/',$res['content'],$match);
        foreach($match[1] as $key => $src){
            if(!strpos($src,'://')){
                $res['content'] = str_replace('/'.$src,'https://'.$_SERVER['HTTP_HOST']."/".$src."\" width=80%",$res['content']);
            }
        }
        $res['time'] = date('Y-m-d',$res['ctime']);
        if(empty($res)){
            apiResponse("error","加载失败");
        }else{
            apiResponse("success","加载成功",$res);
        }
    }

}