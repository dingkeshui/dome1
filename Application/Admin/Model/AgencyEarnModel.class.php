<?php
namespace Admin\Model;
use Think\Model;

class AgencyEarnModel extends Model {
    protected $tableName = 'agency_earn';
    /**
     * 自动验证
     */
    protected $_validate = array(
        array('account','require','商家的账号不能为空！',1,'',3),
        array('password','require','商家密码不能为空！',1,'',3),
        array('cash_price','require','保证金不能为空！',1,'',3),
        array('province','require','请选择省级！',1,'',3),
        array('city','require','请选择市级！',1,'',3),

    );

    /**
     * @var array
     * 自动完成规则
     */
    protected $_auto = array (
//        array('ctime', 'time', 3, 'function'),
//        array('utime', 'time', 3, 'function'),
    );

    /**
     * 查询多条数据
     */
    public function selectShop($where = array(),$order = '',$page_size = '',$parameter = array()){
        if($where['status'] == ''|| empty($where['status'])){
//            $where['status'] = array('neq',9);
        }
        if($page_size == ''){
            $result = $this->where($where)->order($order)->select();
        }else{
            $count = $this->where($where)->count();
            $page = new \Think\Page($count,$page_size);
            $page->parameter = $parameter;
            $page->setConfig('theme',$this->setPageTheme());
            $page_info =$page->show();
            $list = $this->where($where)
                ->order($order)
                ->limit($page->firstRow,$page_size)
                ->select();
            $result = array('page'=>$page_info,'list'=>$list);
        }
        return $result;
    }

    /**
     * 分页样式
     */
    private function setPageTheme(){
        $theme = "<ul class='pagination'><li>%TOTAL_ROW%</li><li>%UP_PAGE%</li>%LINK_PAGE%<li>%DOWN_PAGE%</li></ul>";
        return $theme;
    }
}