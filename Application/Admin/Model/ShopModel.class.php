<?php
namespace Admin\Model;
use Think\Model;

class ShopModel extends Model {
    protected $tableName = 'Shop';
    /**
     * 自动验证
     */
    protected $_validate = array(
        array('name','require','商家名称不能为空！',1,'',3), //空验证  默认情况下用正则进行验证
        array('account','require','商家的账号不能为空！',1,'',3),
        array('address','require','商家详情地址不能为空！',1,'',3),
        array('time','require','商家的营业时间不能为空！',1,'',3),
        array('account','/^(0|86|17951)?(17[0-9]|13[0-9]|15[012356789]|18[0-9]|14[57])[0-9]{8}$/','手机号格式错误！',self::EXISTS_VALIDATE),
        //array('password','require','商家的登录密码不能为空！',1,'',3),
        array('tel','require','商家的联系电话不能为空！',1,'',3),
        array('scale_p','require','商家提成不能为空！',1,'',3),
        array('scale_member','require','用户提成不能为空！',1,'',3),
        //array('class_id','require','商家类型不能为空！',1,'',3),

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
    /**
     * 添加数据
     */
    public function addShop($data){
        if(empty($data)){
            return false;
        }
        $result = $this->data($data)->add();
        return $result;
    }
    /**
     * 查询一条数据
     */
    public function findShop($where){
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
    public function editShop($where,$data){
        if(empty($where) || empty($data)){
            return false;
        }
        $result = $this->where($where)->save($data);
        return $result;
    }
}