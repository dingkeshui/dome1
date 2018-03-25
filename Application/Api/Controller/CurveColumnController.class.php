<?php
namespace Api\Controller;
use Think\Controller;
/**曲线图相关*/
class CurveColumnController extends ApiBasicController
{
    public function _initialize(){
        parent::_initialize();
    }



    /**营业的曲线图
     * @author crazy
     * @param shop_id 商家的id
     * type 1每天   2每月
     * time 每天或者每月
    */
    public function businessGraph(){
        $day = $_POST['day'];
        $shop_id = intval($_POST['shop_id']);
        $time = $_POST['time'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        if(!in_array($day,[1,2,3]) || empty($shop_id)){
            apiResponse('error',"参数错误");
        }
        switch($day){
            case 1:
                if(empty($start_time)&&empty($end_time)){
                    $start_time = date('Y-m-d',(time()-(60*60*24*7)));
                    $end_time = date('Y-m-d',(time()-(60*60*24)));
                }
                $return['curve'] = $this->income($start_time,$end_time,$shop_id);

                //判断起始时间
                $return['other_day_price'] = $this->getPrice($shop_id,$day,$start_time,$end_time,$time);
                apiResponse("success","获取成功",$return);
                break;
            case 2:
                /**先获取商家的订单的流水*/
                $year = date("Y",time());
                $return['curve'] = $this->getSalesByMonth($year,$shop_id);
                $return['other_day_price'] = $this->getPrice($shop_id,$day,$start_time,$end_time,$time);
                apiResponse("success","获取成功",$return);
                break;
        }
    }

    public function getPrice($shop_id,$type,$start_z_time,$end_z_time,$time){
        if($type == 1){
            $start_time = strtotime(date("{$start_z_time}"));
            $end_time = strtotime(date("{$end_z_time} 23:59:59"));
        }elseif($type == 2){
            $time_m = empty($time)?date("m"):$time;
            $start_time = strtotime(date("Y-{$time_m}-01"));
            $end_time = strtotime(date("Y-{$time_m}-31"));
        }else{
            $start_time = strtotime(date("d"))-(3600*24*7);
            $end_time = strtotime(date("Y-m-d 23:59:59"))-(3600*24);
        }
        $return['day_sum_price'] = $this->orderPrice($shop_id,$start_time,$end_time,2);

        /**下单人数*/
        $return['member_num'] = $this->getMemberNum($shop_id,$start_time,$end_time,0);
        /**买单人数*/
        $return['pay_member_num'] = $this->getMemberNum($shop_id,$start_time,$end_time,0);

        /**下单订单订单数*/
        $return['xia_member_num'] = $this->orderNum($shop_id,$start_time,$end_time,0);
        /**下单订单付款订单数*/
        $return['pay_member_total_num'] = $this->orderNum($shop_id,$start_time,$end_time,1);

        /**下单金额*/
        $return['place_order_price'] = $this->orderPrice($shop_id,$start_time,$end_time,1);
        /**下单付款金额*/
        $return['pay_order_price'] = $this->orderPrice($shop_id,$start_time,$end_time,2);

        return $return;
    }

    /**统计人数的方法
     * @author crazy
     * @time 2017-12-25
     */
    public function getMemberNum($shop_id,$start_time,$end_time,$num){
        $w['shop_id'] = $shop_id;
        $w['status'] = array('egt',$num);
        $w['ctime'] = array(array('EGT',$start_time),array('ELT',$end_time),'and');
        /**买单的付款的人数*/
        $order_member_num = M("Order")->where($w)->count('distinct(m_id)');
        return $order_member_num;
    }


    /**统计订单数
     * @author crazy
     * @time 2017-12-25
     */
    public function orderNum($shop_id,$start_time,$end_time,$num){
        $w['ctime'] = array(array('EGT',$start_time),array('ELT',$end_time),'and');
        /**购买商品订单的人数和*/
        $w['shop_id'] = $shop_id;
        if($num == 0){
            $w['status'] = ['neq',9];
            $product_order_member_num = M("ProductOrder")->where($w)->count();
        }else{
            $w['status'] = ['not in','0,9'];
            $product_order_member_num = M("ProductOrder")->where($w)->count('distinct(m_id)');
        }
        return $product_order_member_num;
    }


    /**统计订单金额
     * @author crazy
     * @time 2017-12-25
     */
    public function orderPrice($shop_id,$start_time,$end_time,$num){
        $w['shop_id'] = $shop_id;
        $w['ctime'] = array(array('EGT',$start_time),array('ELT',$end_time),'and');
        /**购买商品订单的人数和*/
        if($num == 1){
            $w['status'] = ['not in','0,9'];
            $w['pay_time'] = array('neq','0');
            $product_order_member_price = M("ProductOrder")->where($w)->sum("real_price");
        }else{
            $w['pay_time'] = array('neq','0');
            $product_order_member_price = M("ProductOrder")->where($w)->sum("real_price")
                -$this->returnPriceSum($shop_id,array(array('EGT',$start_time),array('ELT',$end_time),'and')); //减去退款金额
        }
        /**买单的付款的人数*/
        $w['status'] = array('egt',0);
        $order_member_price = M("Order")->where($w)->sum("total_price");
        $total = sprintf("%.2f",(floatval($product_order_member_price)+floatval($order_member_price)));
        return $total;
    }





    public function getMonthPrice($day_line){
        $day_line_arr = explode(',',$day_line);
        $return['month_sum_price'] = array_sum($day_line_arr)?array_sum($day_line_arr):"0.00";
        return $return;
    }



    /**
     * 用户端的股价的曲线图的数据
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function income($start_time,$end_time,$shop_id){
        //判断起始时间
//        $start_time = date('Y-m-d',(time()-intval($time)));
//        $end_time = date('Y-m-d',(time()-(60*60*24)));
        //横坐标赋值时间
        $x_res = $this->createX($start_time,$end_time);
        //平台收入统计
        $day = $this->getSalesByDay($start_time,$end_time,$shop_id);  //天
        $x_res['day_line'] = $day;
        return $x_res;
    }

    /**
     * 日活跃度
     */
    public function getSalesByDay($start_time,$end_time,$shop_id){
        //折线图数据 查询条件及对象
        $sales_line_order = array('obj'=>D('Order'),'where'=>array("shop_id"=>$shop_id),'field'=>"total_price");
        $sales_line_product_order = array('obj'=>D('ProductOrder'),'where'=>array("shop_id"=>$shop_id,'status'=>array('not in','0'),'pay_time'=>['neq',0]),'field'=>"real_price");
        //数据参数
        $line_parameter = array($sales_line_order,$sales_line_product_order);
        //获取数据
        $sales_line_data = $this->getLineData($start_time,$end_time,$line_parameter);
        return $sales_line_data;
        //创建折线
    }


    /**
     * 获取折线统计数据
     * @param $start_date
     * @param $end_date
     * @param $parameter  相关参数 包含 (标题  查询条件  对象)
     * @return mixed
     */
    public function getLineData($start_date,$end_date,$parameter){
        $start_date = strtotime($start_date);  //起始时间 时间戳转化
        $end_date   = strtotime($end_date);
        if($start_date == $end_date){
            $day = 1;
        }else{
            //获取天数
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400) : floor(($end_date-$start_date)/86400);
        }
        $data = [];
        //获取统计值
        for($i = 0; $i <= $day; $i++){
            foreach($parameter as $k => $value){
                $value['where']['ctime'] = array(['EGT',($start_date + $i * 86400)],['ELT',($start_date + ($i+1) * 86400)],'AND');
                /**没有减去退款的金额的数据*/
                $yuan_price = $value['obj']->where($value['where'])->sum($value['field']);
                if($value['field'] == "real_price"){
                    $return_price = $this->returnPriceSum($value['where']['shop_id'],
                        array(['EGT',($start_date + $i * 86400)],['ELT',($start_date + ($i+1) * 86400)],'AND'));
//                    dump($return_price);
                    $data[$k][] = floatval($yuan_price)-floatval($return_price)<0?0:floatval($yuan_price)-floatval($return_price);
                }else{
                    $data[$k][] =  $yuan_price;
                }

            }
        }

        foreach($data[0] as $k=>$v){
            $data[0][$k] = $v+$data[1][$k];
        }
        $string = implode(",",$data[0]);
        return $string;
    }





    /**
     * 创建横坐标
     * 2014-6-3
     * @param $start_date  开始时间  时间戳
     * @param $end_date  结束时间
     * @return array
     */
    public function createX($start_date,$end_date){
        $start_date = strtotime($start_date);  //起始时间 时间戳转化
        $end_date   = strtotime($end_date);
        //连个时间相同  就是一天
        if($start_date == $end_date){
            $day = 1;
        }else{
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400) : floor(($end_date-$start_date)/86400);
        }
        //创建横坐标显示
        $date = "";
        for($i = 0; $i <= $day; $i++){
            $d = date('Y年m月d日',intval($start_date) + intval($i*86400));
            $date .= "$d,";
        }
        $x_date = substr($date,0,strlen($date)-1);
        return array('x_date'=>$x_date);
    }



    /**
     * 月活跃度
     */
    public function getSalesByMonth($year,$shop_id){
        //获取统计值
        $sales_line_data = [];
        for($month = 1; $month <= 12; $month++){
            $day_num = getDayNum($year,$month);
            $where['ctime'] = array(['EGT',(strtotime("$year-$month-01 00:00:00"))],['ELT',(strtotime("$year-$month-$day_num 23:59:59"))],'AND');
            $where['shop_id']=$shop_id;
            $field_order = "sum(total_price)";
            $amount_order = M('Order')->where($where)->getField($field_order);
            $field_pro_order = "sum(real_price)";
            $where['status']=['not in','0'];
            $where['pay_time']=['neq','0'];
            $amount_pro_order = M('ProductOrder')->where($where)->getField($field_pro_order);
            $total =  sprintf("%.2f", ($amount_pro_order+$amount_order));
            $sales_line_data[] = floatval($total)-floatval($this->returnPriceSum($shop_id,
                    array(['EGT',(strtotime("$year-$month-01 00:00:00"))],['ELT',(strtotime("$year-$month-$day_num 23:59:59"))],'AND')));
            unset($where);
        }
        $sales_line_data_return['day_line'] = implode(",",$sales_line_data);
        $sales_line_data_return['x_date'] = $this->createMonthX();
        return $sales_line_data_return;
    }


    /**
     * 月份横坐标
     * @return array
     * 2017-12-25  add by crazy
     */
    public function createMonthX(){
        $date = "";
        for($month = 1; $month <= 12; $month++){
            $month_x = $month.'月';
            $date .= "$month_x,";
        }
        $month_x_date = substr($date,0,strlen($date)-1);
        return $month_x_date;
    }







}
