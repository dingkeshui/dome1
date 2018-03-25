<?php
namespace Api\Controller;
use Think\Controller;
/**曲线图交易统计相关*/
class CurveDealController extends ApiBasicController
{
    public function _initialize(){
        parent::_initialize();
    }



    /**交易的曲线图
     * @author crazy
     * @param shop_id 商家的id
    */
    public function dealGraph(){
        $day = $_POST['day'];
        $shop_id = intval($_POST['shop_id']);
        if(!in_array($day,[1,2,3]) || empty($shop_id)){
            apiResponse('error',"参数错误");
        }
        switch($day){
            case 1:
                /**先获取商家的订单的数量*/
                $seven_second = (60*60*24)*15;
                $return['curve'] = $this->income($seven_second,$shop_id);
                //判断起始时间
                $start_time = strtotime(date('Y-m-d', time()-intval($seven_second)));
                $end_time = strtotime(date('Y-m-d', time()))-(60*60*24);
                $return['other_day_price'] = $this->getOtherMes($shop_id,$start_time,$end_time);
                apiResponse("success","获取成功",$return);
                break;
            case 2:
                /**先获取商家的订单的流水*/
                $year = date("Y",time());
                $return['curve'] = $this->getSalesByMonth($year,$shop_id);
                $return['other_day_price'] = $this->getOtherMes($shop_id,strtotime("-1 year"),strtotime($year));
                apiResponse("success","获取成功",$return);
                break;
            case 3:
                /**先获取商家的订单的流水*/
                $nin_second = (60*60*24)*49;
                //判断起始时间
                $start_time = strtotime(date('Y-m-d', time()-intval($nin_second)));
                $end_time = strtotime(date('Y-m-d', time()))-(60*60*24);
                $return['curve'] = $this->income($nin_second,$shop_id,'1');
                $return['other_day_price'] = $this->getOtherMes($shop_id,$start_time,$end_time);
                apiResponse("success","获取成功",$return);
                break;
        }
    }

    public function getOtherMes($shop_id,$start_time,$end_time){
        $w['shop_id'] = $shop_id;
        $w['status'] = array('egt',0);
        $w['ctime'] = array(array('EGT',$start_time),array('ELT',$end_time),'and');
        /**买单的付款的人数*/
        $order_member_num = M("Order")->where($w)->count('distinct(m_id)');

        /**买单的价格和*/
        $order_price = M("Order")->where($w)->sum('total_price');
        /**购买商品订单的人数和*/
        $w['status'] = ['not in','0,9'];
        $product_order_member_num = M("ProductOrder")->where($w)->count('distinct(m_id)');
        $total = floatval($order_member_num)+floatval($product_order_member_num);
        $return['member_num'] = $total;
        /**商品订单的钱数和*/
        $w['status'] = ['not in','0'];
        $w['pay_time'] = ['neq','0'];
        $product_order_price = M("ProductOrder")->where($w)->sum('real_price');
        $total_price = floatval($order_price)+floatval($product_order_price)
            -$this->returnPriceSum($shop_id,array(array('EGT',$start_time),array('ELT',$end_time),'and')); //减去退款金额;
        $return['aver_member_price'] = sprintf("%.2f",$total_price/$total);

        /**转化率*/
        $w['status'] = array('egt',0);
//        $order_member_num_w = M("Order")->where($w)->count('distinct(m_id)');
        /**统计所有下单的用户的数量*/
        $order_count = M("ProductOrder")->where($w)->count('distinct(m_id)');
        if($total == 0){
            $return['day_aver_price'] = "0%";
        }else{
            $return['day_aver_price'] = sprintf("%.2f",(($total/($order_count+$order_member_num))*100))."%";
        }
        return $return;
    }






    /**
     * 用户端的股价的曲线图的数据
     * @author crazy
     * @time 2017-07-03
     * @param shop_id 商家的id
     */
    public function income($time,$shop_id,$zhu=0){
        //判断起始时间
        $start_time = date('Y-m-d', time()-intval($time));
        $end_time = date('Y-m-d', time()-(60*60*24));
        //横坐标赋值时间
        $x_res = $this->createX($start_time,$end_time,$zhu);
        //平台收入统计
        $day = $this->getSalesByDay($start_time,$end_time,$shop_id,$zhu);  //天
        $list = [];
        foreach($day as $k=>$v){
            $list[$k] = [
                "x_date"=>$x_res['x_date'],
                'day_line' =>implode(",",$v)
            ];
        }
        return $list;
    }

    /**
     * 日活跃度
     */
    public function getSalesByDay($start_time,$end_time,$shop_id,$zhou){
        //折线图数据 查询条件及对象 下单数
        $sales_line_x_order = array('obj'=>D('Order'),'where'=>array("shop_id"=>$shop_id));
        $sales_line_product_x_order = array('obj'=>D('ProductOrder'),'where'=>array("shop_id"=>$shop_id,'status'=>array('not in','0,9')));

        //折线图数据 查询条件及对象 付款数
        $sales_line_f_order = array('obj'=>D('Order'),'where'=>array("shop_id"=>$shop_id));
        $sales_line_product_f_order = array('obj'=>D('ProductOrder'),'where'=>array("shop_id"=>$shop_id,'status'=>array('not in','0,9')));

        //折线图数据 查询条件及对象 收货数
        $sales_line_send_order = array('obj'=>D('Order'),'where'=>array("shop_id"=>$shop_id));
        $sales_line_product_send_order = array('obj'=>D('ProductOrder'),'where'=>array("shop_id"=>$shop_id,'status'=>array('not in','0,9')));
        //数据参数
        $line_parameter = array($sales_line_x_order,$sales_line_product_x_order,$sales_line_f_order,
            $sales_line_product_f_order,$sales_line_send_order,$sales_line_product_send_order);
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
                    $data[$k][] = $value['obj']->where($value['where'])->count();
                }
            }
        }else{
            //获取统计值
            for($i = 0; $i <= $day; $i++){
                foreach($parameter as $k => $value){
                    $value['where']['ctime'] = array(['EGT',($start_date + $i * 86400)],['ELT',($start_date + ($i+1) * 86400)],'AND');
                    $data[$k][] = $value['obj']->where($value['where'])->count();
                }
            }
        }

        foreach($data[0] as $k=>$v){
            $data[0][$k] = $v+$data[1][$k];
        }
        $return[0] = $data[0];



        foreach($data[2] as $k=>$v){
            $data[2][$k] = $v+$data[3][$k];
        }
        $return[1] = $data[2];

        foreach($data[4] as $k=>$v){
            $data[4][$k] = $v+$data[5][$k];
        }
        $return[2] = $data[4];
        return $return;
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
        //下单的笔数
        $xia_order_count = $this->getMonthLine($year,$shop_id,0);
        $sales_line_data_return[]= [
            'day_line'=>$xia_order_count,
            'x_date'=> $this->createMonthX()
        ];
        //支付的订单
        $pay_order_count = $this->getMonthLine($year,$shop_id,1);
        $sales_line_data_return[]= [
            'day_line'=>$pay_order_count,
            'x_date'=> $this->createMonthX()
        ];
        //发货的订单
        $send_order_count = $this->getMonthLine($year,$shop_id,2);
        $sales_line_data_return[]= [
            'day_line'=>$send_order_count,
            'x_date'=> $this->createMonthX()
        ];
        return $sales_line_data_return;
    }

    /**获取曲线图
     * @author crazy
     * @time 2017-12-25
     */
    public function getMonthLine($year,$shop_id,$num){
        $sales_line_data = [];
        for($month = 1; $month <= 12; $month++){
            $day_num = getDayNum($year,$month);
            $where['ctime'] = array(['EGT',(strtotime("$year-$month-01 00:00:00"))],['ELT',(strtotime("$year-$month-$day_num 23:59:59"))],'AND');
            $where['shop_id']=$shop_id;
            if($num == 0){
                $where['status'] = array('egt',$num);
                $where['status'] = ['neq',9];
            }else{
                $where['status'] = array('not in','0,9');
            }
            $amount_order = M('Order')->where($where)->count();
            $amount_pro_order = M('ProductOrder')->where($where)->count();
            $total =  $amount_pro_order+$amount_order;
            $sales_line_data[] = $total;
            unset($where);
        }
        return implode(",",$sales_line_data);
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
