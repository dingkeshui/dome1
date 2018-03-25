<?php
namespace Home\Model;
use Think\Model;

/**
 * Class MemberModel
 * @package Common\Model
 * 会员信息相关
 */
class MemberModel extends Model {
    protected $tableName = 'member';

    /**
     * 查询用户名是否存在
     */
    public function findUserAccount($where){
        if(empty($where)){
            return false;
        }
        $result=$this->where($where)->find();
        return $result;
    }

    /**
     * @var array
     * 自动验证   使用create()方法时自动调用
     */
    protected $_validate = array(
        array('tel','require','手机号不能为空！',1,'',3), //空验证  默认情况下用正则进行验证
        array('tel','/^(0|86|17951)?(13[0-9]|15[012356789]|18[0-9]|14[57])[0-9]{8}$/',"手机号格式不对",self::EXISTS_VALIDATE),
        array('email','email',"邮箱格式不对",self::EXISTS_VALIDATE)
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
    public function selectMember($where = array(),$order = '',$page_size = '',$parameter = array(),$field = array(),$limit = ''){
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
     * 添加会员
     */
    public function addMember($data){
        if(empty($data)){
            return false;
        }
        $result = $this->data($data)->add();
        return $result;
    }
    /**
     * 多条数据同时添加
     */
    public function addMemberAll($data){
        if(empty($data)){
            return false;
        }
        $result = $this->addAll($data);
        return $result;
    }
    /**
     * 查询一条数据
     */
    public function findMember($where,$field){
        if($where['status'] == '' || empty($where['status'])){
            $where['status'] = array('neq','9');
        }
        $result = $this->where($where)->field($field)->find();
        return $result;
    }
    /**
     * 编辑会员
     */
    public function editMember($where,$data){
        if(empty($where) || empty($data)){
            return false;
        }
        $result = $this->where($where)->data($data)->save();
        return $result;
    }

    /**
     * 删除会员
     */
    public function deleteMember($where){
        if(empty($where)){
            return false;
        }
        $result = $this->where($where)->delete();
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
       * 发送邮件
    */
    public function sendEmail($user,$name,$info,$body){
        $email = D('Email','Service');
        $result = $email->sendEmail($user,$name,$info,$body);
        return $result;
    }

}
