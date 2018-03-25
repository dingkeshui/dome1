<?php
namespace Common\Model;
use Think\Model;

/**
 * Class AdvertModel
 * @package Common\Model
 */
class ClickAdvertModel extends Model {
    /**
     * @var array
     * 自动验证
     */
    protected $_validate = array (
        array('position','require','图片位置不能为空！'),
    );
    /**
     * @var array
     * 自动完成
     */
    protected $_auto = array (
        array('ctime','time',self::MODEL_INSERT,'function'), // 对ctime字段在插入的时候写入当前时间戳
        array('utime','time',self::MODEL_UPDATE,'function'), // 对ctime字段在插入的时候写入当前时间戳
    );

    /**
     * 查询多条数据
     */
    public function selectAdvert($where = array(),$order = '',$page_size = '',$parameter = array()){
        if($where['status'] == ''|| empty($where['status']) && $where['status']!=0){
            $where['status'] = array('neq','9');
        }if($page_size == ''){
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
     * 添加数据
     */
    public function addAdvert($data){
        if(empty($data)){
            return false;
        }else{
            $result = $this->data($data)->add();
            return $result;
        }
    }
    /**
     * 查询一条数据
     */
    public function findAdvert($where,$field){
        if($where['status'] == '' || empty($where['status'])){
            $where['status'] = array('neq','9');
        }
        $result = $this->where($where)->field($field)->find();
        return $result;
    }
    /**
     * 编辑数据
     */
    public function editAdvert($where,$data){
        if(empty($where) || empty($data)){
            return false;
        }
        $result = $this->where($where)->data($data)->save();
        return $result;
    }
    /**
     * 删除数据
     */
    public function deleteAdvert($where){
        if(empty($where)){
            return false;
        }
        $data['status'] = 9;
        $result = $this->where($where)->save($data);
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