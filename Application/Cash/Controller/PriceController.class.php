<?php
namespace Cash\Controller;
use Think\Controller;
/**
 * 订单量管理
 */
class PriceController extends AdminBasicController {

    public function _initialize(){
        $this->checkLogin();
    }



    /**订单量的列表*/
    public function orderList(){
        //判断起始时间
        if(empty($_POST['start_time']) && empty($_POST['end_time'])){
            $start_time = date('Y-m-d',(time()-intval(864000)));
            $end_time = date('Y-m-d',time());
        }else{
            $start_time = I('post.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('post.end_time');
            $this->assign("end_time",$end_time);
        }
        //年份判空
        $year = empty($_POST['year']) ? date('Y') : I('post.year');
        //横坐标赋值时间
        $x_res = $this->createX($start_time,$end_time,"订单量(个)");
        //订单量统计
        $this->getSalesByDay($start_time,$end_time);  //天
        $this->getSalesByMonth($year); //月

        $this->assign('x_date',$x_res['x_date']);
        //相隔区间
        $this->assign('step',intval($x_res['step']));
        //标题
        $this->assign('title',$x_res['title']);
        $this->display("orderList");
    }

    /**
     * 日活跃度
     */
    public function getSalesByDay($start_time,$end_time){
        //折线图数据 查询条件及对象
        $sales_line_p = array('title'=>'订单量(个)','obj'=>D('OrderClass'),'flag'=>array());
        //数据参数
        $line_parameter = array($sales_line_p);
        //获取数据
        $sales_line_data = $this->getLineData($start_time,$end_time,$line_parameter);
        //创建折线
        $this->assign('day_line',$this->createLine($sales_line_data));
        //顶部文字subtitle
        $this->assign('day_date_flag','【日增加量(个)】　'.$start_time.'至'.$end_time);
    }

    /**
     * 月活跃度
     */
    public function getSalesByMonth($year){
        //获取统计值
        for($month = 1; $month <= 12; $month++){
            $day_num = getDayNum($year,$month);
            $where['ctime'] = array('between',(strtotime("$year-$month-01 00:00:00")).",".(strtotime("$year-$month-$day_num 23:59:59")));
            //订单量
            $where_e['e_id'] = session('E_ID');
            $res_e = D("agency_earn")->where($where_e)->find();
            if($res_e['type'] == 1){
                $where['city'] = $res_e['city'];
            }elseif ($res_e['type'] == 2){
                $where['area'] = $res_e['area'];
            }
            $where['class_id'] = session('CLASS_E_ID');
            $amount = D('OrderClass')->where($where)->count();
            $total =  sprintf("%.2f", $amount);
            $sales_line_data['订单量(个)'][] = $total;
        }
        //创建折线
        $this->assign('month_line',$this->createLine($sales_line_data));
        //顶部文字subtitle
        $this->assign('month_date_flag','【月增加量(个)】　'.$year.'年');
        $this->assign('month_x_date',$this->createMonthX());
        //创建年份下拉菜单
        $this->assign('year_sel',$this->createYearSelect($year));
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
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400)+1 : floor(($end_date-$start_date)/86400);
        }
        //获取统计值
        for($i = 0; $i <= $day; $i++){
            foreach($parameter as $k => $value){
                $value['where']['ctime'] = array('between',($start_date + $i * 86400).",".($start_date + ($i+1) * 86400));
                $where_e['e_id'] = session('E_ID');
                $res_e = D("agency_earn")->where($where_e)->find();
                if($res_e['type'] == 1){
                    $value['where']['city'] = $res_e['city'];
                }elseif ($res_e['type'] == 2){
                    $value['where']['area'] = $res_e['area'];
                }
                $value['where']['class_id'] = session('CLASS_E_ID');
                $data[$k][] = $value['obj']->where($value['where'])->count();
            }
        }
        foreach ($data[0] as $k=>$v){
            if($v == null){
                $data[0][$k] = "0";
            }
        }
        //添加标题
        foreach($parameter as $k => $value){
            $result[$value['title']] = $data[$k];
        }
        return $result;
    }

    /**
     * @param $year        年份
     * @param $parameter   相关参数 包含 (标题  查询条件  对象)
     * @return mixed
     * 2014-8-18  add by <黑暗中的武者>
     */
    public function getLineDataByMonth($year,$parameter){
        //获取统计值
        for($month = 1; $month <= 12; $month++){
            foreach($parameter as $k => $value){
                $day_num = getDayNum($year,$month);
                $value['where']['ctime'] = array('between',(strtotime("$year-$month-01 00:00:00")).",".(strtotime("$year-$month-$day_num 23:59:59")));
                $where_e['e_id'] = session('E_ID');
                $res_e = D("agency_earn")->where($where_e)->find();
                if($res_e['type'] == 1){
                    $value['where']['city'] = $res_e['city'];
                }elseif ($res_e['type'] == 2){
                    $value['where']['area'] = $res_e['area'];
                }
                $data[$k][$month] = $value['obj']->where($value['where'])->count();
            }
        }
        //添加标题
        foreach($parameter as $k => $value){
            $result[$value['title']] = $data[$k];
        }
        return $result;
    }
    /**
     * 折线参数处理
     * 2014-6-3
     * @param $data 数据格式   $data["商铺统计 **折线名称**"] = array(4,5,...)数组中存入每个时间段的统计数量;
     * @return string
     */
    public function createLine($data){
        //创建折线参数字符串
        $line = '';
        foreach($data as $key => $value){
            $line_data = '';
            $line.= "{name: '".$key."',data:[";
            foreach($value as $v){
                $line_data.=$v.',';
            }
            $line.=substr($line_data,0,strlen($line_data)-1);
            $line.="]},";
        }
        //去除字符串末尾的逗号
        $line = substr($line,0,strlen($line)-1);
        //返回highcharts格式的字符串
        return $line;
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
            $day = ($end_date-$start_date)%86400 > 0 ? floor(($end_date-$start_date)/86400)+1 : floor(($end_date-$start_date)/86400);
        }
        //创建横坐标显示
        $date = "";
        for($i = 0; $i <= $day; $i++){
            $d = date('y/m/d',intval($start_date) + intval($i*86400));
            $date .= "'$d',";
        }
        $x_date = substr($date,0,strlen($date)-1);
        //横坐标区间
        $step = floor($day/15);
        return array('x_date'=>$x_date,'step'=>$step);
    }

    /**
     * 月份横坐标
     * @return array
     * 2014-8-18  add by <黑暗中的武者>
     */
    public function createMonthX(){
        $date = "";
        for($month = 1; $month <= 12; $month++){
            $month_x = $month.'月';
            $date .= "'$month_x',";
        }
        $month_x_date = substr($date,0,strlen($date)-1);
        return $month_x_date;
    }

    /**
     * 创建年份下拉
     * @param $year
     * @return string
     * 2014-8-18  add by <黑暗中的武者>
     */
    public function createYearSelect($year){
        //创建年份的下拉菜单
        $year_sel = "<select name='year' class='small-input text-input'>";
        //从当前年向前循环14年
        for($j = 14; $j >= 0; $j--){
            //选中年份
            if(intval($year) == (intval(date('Y'))-$j)){
                $year_sel.="<option value='".(intval(date('Y'))-$j)."' selected='true'>".(intval(date('Y'))-$j)."年</option>";
            }else{
                $year_sel.="<option value='".(intval(date('Y'))-$j)."'>".(intval(date('Y'))-$j)."年</option>";
            }
        }
        $year_sel.= "</select>";
        return $year_sel;
    }













}