<?php
namespace Admin\Model;
use Think\Model;

class DeductModel extends Model {
    protected $tableName = 'Deduct';
    /**
     * 自动验证
     */
    protected $_validate = array(
        array('price','require','提现阶梯额度不能为空！',1,'',3),
        array('type','require','区级或者市级代理不能为空！',1,'',3),
        array('deduct','require','提成比例不能为空！',1,'',3),
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
    public function selectDeduct($where = array(),$order = '',$page_size = '',$parameter = array()){
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