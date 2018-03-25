<?php

namespace Home\Model;
use Think\Model;

/**
 * Class MemberModel
 * @package Common\Model
 * 会员信息相关
 */
class ApplyproductModel extends Model {
    protected $tableName = 'Apply_product';

    /**
     * @var array
     * 自动验证   使用create()方法时自动调用
     */
    protected $_validate = array(
        
    );
    /**
     * 查询多条数据
     */
    public function selectApplyproduct($where = array(),$order = '',$page_size = '',$parameter = array(),$field = array(),$limit = ''){
        if($where['status'] == ''|| empty($where['status']) && $where['status']!=0){
            $where['status'] = array('neq','9');}
        if($page_size == ''){
            $result = $this->where($where)->field($field)->limit($limit)->order($order)->select();
        }else{
            $count = $this->where($where)->field($field)->limit($limit)->count();
            $page = new \Think\Page($count,$page_size);
            $page->parameter = $parameter;
            $page->setConfig('theme',$this->setPageTheme());
            $page_info =$page->show();
            $list = $this->where($where)
                ->limit($limit)
                ->field($field)
                ->order($order)
                ->limit($page->firstRow,$page_size)
                ->select();
            $result = array('page'=>$page_info,'list'=>$list);
        }
        return $result;
    }

    /**
     * 查询一条数据
     */
    public function findApplyproduct($where,$field){
        if($where['status'] == '' || empty($where['status'])){
            $where['status'] = array('neq','9');
        }
        $result = $this->where($where)->field($field)->find();
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