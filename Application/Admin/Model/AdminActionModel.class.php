<?php
namespace Admin\Model;
use Think\Model;

/**
 * Class AdminActionModel
 * @package Admin\Model
 * 管理员权限信息相关
 */
class AdminActionModel extends Model {
    protected $tableName = 'admin_action';
    /**
     * @var array
     * 自动验证   使用create()方法时自动调用
     */
    protected $_validate = array(
        array('group_name','require','组名不能为空！'), //空验证  默认情况下用正则进行验证
        array('model','require','控制器不能为空！'), //空验证  默认情况下用正则进行验证
        array('method','require','方法不能为空！'), //空验证  默认情况下用正则进行验证
        array('action_name','require','权限名称不能为空！'), //空验证  默认情况下用正则进行验证
    );
    /**
     * @var array
     * 自动完成   新增时
     */
    protected $_auto = array (
        array('ctime','time',self::MODEL_INSERT,'function'), // 对ctime字段在插入的时候写入当前时间戳
        array('utime','time',self::MODEL_UPDATE,'function'), // 对utime字段在修改的时候写入当前时间戳
    );
    /**
     * 查询多条数据
     */
    public function selectAdminAction($where = array(),$order = '',$page_size = '',$parameter = array()){
        if($where['status'] == ''|| empty($where['status'])){
            $where['status'] = array('neq',9);
        }
        if($page_size == ''){
            $result = $this->where($where)->order($order)->select();
        }else{
            $count = $this->where($where)->count();
            $page = new \Think\Page($count,$page_size);
            $page->setConfig('theme',$this->setPageTheme());
            $page_info =$page->show();
            $page->parameter = $parameter;
            $list = $this->where($where)
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
    public function findAdmin($where){
        if(empty($where)){
            return false;
        }else{
            $result = $this->where($where)->find();
            return $result;
        }
    }
    /**
     * 分页样式
     */
    private function setPageTheme(){
        $theme = "<ul class='pagination'><li>%TOTAL_ROW%</li><li>%UP_PAGE%</li>%LINK_PAGE%<li>%DOWN_PAGE%</li></ul>";
        return $theme;
    }
}