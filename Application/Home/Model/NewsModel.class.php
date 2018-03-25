<?php
namespace Home\Model;
use Think\Model;

class NewsModel extends Model {
    protected $tableName = 'News';
    /**
     * 自动验证
     */
    protected $_validate = array(
        array('title','require','新闻标题不能为空！',1,'',3), //空验证  默认情况下用正则进行验证
        array('content','require','新闻内容不能为空！',1,'',3), //空验证  默认情况下用正则进行验证
    );

    /**
     * @var array
     * 自动完成规则
     */
    protected $_auto = array (
        array('ctime', 'time', 1, 'function'),
        array('utime', 'time', 3, 'function'),
    );

    /**
     * 查询多条数据
     */
    public function selectNews($where = array(),$order = '',$page_size = '',$parameter = array()){
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
    /**
     * 添加数据
     */
    public function addNews($data){
        if(empty($data)){
            return false;
        }
        $result = $this->data($data)->add();
        return $result;
    }
    /**
     * 查询一条数据
     */
    public function findNews($where){
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
    public function editNews($where,$data){
        if(empty($where) || empty($data)){
            return false;
        }
        $result = $this->where($where)->save($data);
        return $result;
    }
}