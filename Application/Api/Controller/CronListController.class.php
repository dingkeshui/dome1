<?php
namespace Api\Controller;

class CronListController extends ApiBasicController
{

    public function _initialize()
    {
        parent::_initialize();
    }
    /**七天自动收货的定时任务*/
    public function makeSureOrder(){

        $list = M("IntegralOrder")->where(array('status'=>2))->field('i_o_id,status,f_time')->select();
        foreach ($list as $k=>$v){
            $time = $v['f_time']+604800;
            if($time<time()){
                M("IntegralOrder")->where(array('i_o_id'=>$v['i_o_id']))->limit(1)->save(array('status'=>3));
            }
        }
    }

    public function test(){
        file_put_contents("7.txt",1);
    }

    /**修改商家的返麦穗比例
     * @author crazy
     * @time 2018-01-14
     */
    public function editDeduct(){
        $list = M("Shop")->where(['status'=>['neq',9]])->select();
        foreach($list as $k=>$v){
            M("Shop")->where(['shop_id'=>$v['shop_id']])->limit(1)->save(['deduct'=>0]);
        }
    }
}