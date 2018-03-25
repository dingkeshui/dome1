<?php
namespace Admin\Model;
use Think\Model;

class HxUserModel extends Model {
    protected $tableName = 'hx_user';


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
    public function selectHxUser($where = array(),$order = '',$page_size = '',$parameter = array()){
        if($where['status'] == ''|| empty($where['status'])){
            $where['status'] = array('neq',9);
        }
        if($page_size == ''){
            $result = $this->where($where)->order($order)->select();
        }else{
            $count = $this->where($where)->count();
            $page = new \Think\Page($count,$page_size);
            $page->parameter = $parameter;
            $page->setConfig('theme',$this->setPageTheme());
            $page->setConfig('first','首页');
            $page->setConfig('last','尾页');
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
        $theme = "<ul class='pagination'><li>%TOTAL_ROW%</li><li>%FIRST%</li><li>%UP_PAGE%</li>%LINK_PAGE%<li>%DOWN_PAGE%</li><li>%END%</li></ul>";
        return $theme;
    }
}