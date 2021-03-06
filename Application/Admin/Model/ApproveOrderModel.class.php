<?php
namespace Admin\Model;
use Think\Model;

class ApproveOrderModel extends Model {

    /**
     * @var array
     * 自动完成规则
     */
    protected $_auto = array (
        array('utime', 'time', 3, 'function'),
    );

    /**
     * 查询多条数据
     */
    public function selectApproveOrder($obj,$where = array(),$order = '',$page_size = '',$parameter = array()){
        if($page_size == ''){
            $result = $obj->where($where)->order($order)->select();
        }else{
            $count = $obj->where($where)->count();
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