<?php
namespace Api\Controller;
use Think\Controller;
/**曲线图相关*/
class CurveController extends ApiBasicController
{
    public function _initialize(){
        parent::_initialize();
    }



    /**营业的曲线图
     * @author crazy
     * @param shop_id 商家的id
    */
    public function businessGraph(){
        $day = $_POST['day'];
        $shop_id = $_POST['shop_id'];
        if(!in_array($day,[1,2,3]) || empty($shop_id)){
            apiResponse('error',"参数错误");
        }
        switch($day){
            case 1:
                /**先获取商家的订单的流水*/
                $seven_second = (60*60*24)*15;
                $return['curve'] = $this->income($seven_second,$shop_id);
                $return['other_day_price'] = $this->getPrice($return['curve']['day_line']);
                apiResponse("success","获取成功",$return);
                break;
            case 2:
                /**先获取商家的订单的流水*/
                $year = date("Y",time());
                $return['curve'] = $this->getSalesByMonth($year,$shop_id);
                $return['other_day_price'] = $this->getMonthPrice($return['curve']['day_line']);
                apiResponse("success","获取成功",$return);
                break;
            case 3:
                /**先获取商家的订单的流水*/
                $nin_second = (60*60*24)*49;
                $return['curve'] = $this->income($nin_second,$shop_id,'1');
                $return['other_day_price'] = $this->getPrice($return['curve']['day_line']);
                apiResponse("success","获取成功",$return);
                break;
        }
    }

    public function getPrice($day_line){
        $day_line_arr = explode(',',$day_line);
        $return['day_sum_price'] = array_sum($day_line_arr)?array_sum($day_line_arr):"0.00";
        $return['day_max_price'] = max($day_line_arr)?max($day_line_arr):"0.00";
        $return['day_aver_price'] = sprintf("%.2f",array_sum($day_line_arr)/count($day_line_arr));
        return $return;
    }

    public function getMonthPrice($day_line){
        $day_line_arr = explode(',',$day_line);
        $return['day_sum_price'] = array_sum($day_line_arr)?array_sum($day_line_arr):"0.00";
        $return['day_max_price'] = max($day_line_arr)?max($day_line_arr):"0.00";
        $return['day_aver_price'] = sprintf("%.2f",array_sum($day_line_arr)/count($day_line_arr));
        return $return;
    }



    /**
     * 用户端的股价的曲线图的数据
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function income($time,$shop_id,$zhou=0){
        //判断起始时间
        $start_time = date('Y-m-d',(time()-intval($time)));
        $end_time = date('Y-m-d',(time()-(60*60*24)));
        //横坐标赋值时间
        $x_res = $this->createX($start_time,$end_time,$zhou);
        //平台收入统计
        $day = $this->getSalesByDay($start_time,$end_time,$shop_id,$zhou);  //天
        $x_res['day_line'] = $day;
        return $x_res;
    }

    /**
     * 日统计
     */
    public function getSalesByDay($start_time,$end_time,$shop_id,$zhou){
        //折线图数据 查询条件及对象
        $sales_line_order = array('obj'=>D('Order'),'where'=>array("shop_id"=>$shop_id),'field'=>"total_price");
        $sales_line_product_order = array('obj'=>D('ProductOrder'),'where'=>array("shop_id"=>$shop_id,'status'=>array('not in','0'),'pay_time'=>['neq',9]),'field'=>"real_price");
        //数据参数
        $line_parameter = array($sales_line_order,$sales_line_product_order);
        //获取数据
        $sales_line_data = $this->getLineData($start_time,$end_time,$line_parameter,$zhou);
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
    public function getLineData($start_date,$end_date,$parameter,$zhou){
        $start_date = strtotime($start_date);  //起始时间 时间戳转化
        $end_date   = strtotime($end_date);
        if($start_date == $end_date){
            $day = 1;
        }elseif($zhou){
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400*7) : floor(($end_date-$start_date)/86400*7);
        }else{
            //获取天数
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400) : floor(($end_date-$start_date)/86400);
        }
        $data = [];
        if($zhou == 1){
            //获取统计值
            for($i = 0; $i <= 6; $i++){
                foreach($parameter as $k => $value){
                    $value['where']['ctime'] = array(['EGT',($start_date + $i * 7 * 86400)],['ELT',($start_date + ($i+1) * 7 * 86400)],'AND');
                    /**没有减去退款的金额的数据*/
                    $yuan_price = $value['obj']->where($value['where'])->sum($value['field']);
                    if($value['field'] == "real_price"){
                        $return_price = $this->returnPriceSum($value['where']['shop_id'],
                            array(['EGT',($start_date + $i * 7 * 86400)],['ELT',($start_date + ($i+1) * 7 * 86400)],'AND'));
                        $data[$k][] = floatval($yuan_price)-floatval($return_price)<0?0:floatval($yuan_price)-floatval($return_price);
                    }else{
                        $data[$k][] = $yuan_price;
                    }

                }
            }
        }else{
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
    public function createX($start_date,$end_date,$zhou){
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
        if($zhou){
            for($i = 0; $i <= $day/7; $i++){
                $d = "第".($i+1)."周";
                $date .= "$d,";
            }
        }else{
            for($i = 0; $i <= $day; $i++){
                $d = date('m月d日',intval($start_date) + intval($i*86400));
                $date .= "$d,";
            }
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
            $amount_pro_order = M('ProductOrder')
                ->where($where)->getField($field_pro_order);
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
