<?php
namespace Admin\Model;
use Think\Model;

class CouponMemberModel extends Model {
    protected $tableName = 'Coupon_Member';
    /**
     * 自动验证
     */
    protected $_validate = array(
//        array('title','require','活动名称不能为空！',1,'',3), //空验证  默认情况下用正则进行验证
//        array('account','require','商家的账号不能为空！',1,'',3),
//        array('address','require','商家详情地址不能为空！',1,'',3),
//        array('time','require','商家的营业时间不能为空！',1,'',3),
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
    public function selectCouponMember($where = array(),$order = '',$page_size = '',$parameter = array()){
//        if($where['status'] == ''|| empty($where['status'])){
//            $where['status'] = array('neq',9);
//        }
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