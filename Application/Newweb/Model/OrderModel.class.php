<?php
namespace Home\Model;
use Think\Model;

class OrderModel extends Model {
    protected $tableName = 'Order';
    /**
     * 自动验证
     */


    /**
     * 查询多条数据
     */
    public function selectOrder($where = array(),$order = '',$page_size = '',$parameter = array()){
        if($where['status'] == ''|| empty($where['status'])){
            $where['status'] = array('neq',9);
        }
        if($page_size == ''){
            $result = $this->where($where)->order($order)->group('order_sn')->select();
        }else{
            $count = count($this->where($where)->group('order_sn')->select());
            $page = new \Think\Page($count,$page_size);
            $page->parameter = $parameter;
            $page->setConfig('theme',$this->setPageTheme());
            $page_info =$page->show();
            $list = $this->where($where)
                ->order($order)
                ->group('order_sn')
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
        $theme = "<ul><li>%TOTAL_ROW%</li><li>%UP_PAGE%</li>%LINK_PAGE%<li>%DOWN_PAGE%</li></ul>";
        return $theme;
    }
    /**
     * 添加数据
     */
    public function addOrder($data){
        if(empty($data)){
            return false;
        }
        $result = $this->data($data)->add();
        return $result;
    }
    /**
     * 查询一条数据
     */
    public function findOrder($where){
        if(empty($where)){
            return false;
        }else{
            if($where['status'] == '' || empty($where['status'])){
                $where['status'] = array('neq','9');
            }
            $result = $this->where($where)->find();
            return $result;
        }
    }
    /**
     * 编辑数据
     */
    public function editOrder($where,$data){
        if(empty($where) || empty($data)){
            return false;
        }
        $result = $this->where($where)->data($data)->save();
        return $result;
    }
}