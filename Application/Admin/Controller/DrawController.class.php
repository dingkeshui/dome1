<?php
namespace Admin\Controller;
use Think\Controller;
/**
 * 抽奖
 */
class DrawController extends AdminBasicController {
    public $Draw = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Draw = D('Draw');
    }

    /**商家的类型列表*/
    public function drawList(){
        if(I("request.name")){
            $w["zxty_draw.name"] = array("LIKE","%".I("request.name")."%");
            $parameter['name'] = I("request.name");
            $request['name'] =  I("request.name");
            $this->assign("request",$request);
        }
        $w['zxty_draw.status'] = array("neq",9);
        $list = D("Draw")->selectDraw($w,"zxty_draw.ctime desc",15,$parameter);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("drawList");
    }

    /**商家的类型添加*/
    public function addDraw(){
        if(!IS_POST){
            /**找到奖品的分类*/
            $list = M("Type")->where(array('status'=>array('neq',9)))->field("t_id,name")->select();
            $this->assign('type',$list);
            $this->display("addDraw");
        }else{
            $data = D("Draw")->create();
            if($_FILES['img']['name']){
                $data['img'] = $this->uploadImg("Draw","img");
            }
            $data['ctime'] = time();
            $res = D("Draw")->add($data);
            if($res){
                $this->success("添加成功！",U("Draw/drawList"));
            }else{
                $this->error("添加失败！");
            }

        }

    }

    /**商家的类型修改*/
    public function editDraw(){
        if(!IS_POST){
            $res = D("Draw")->where(array("d_id"=>$_GET['d_id']))->find();
            $this->assign("res",$res);
            /**找到奖品的分类*/
            $list = M("Type")->where(array('status'=>array('neq',9)))->field("t_id,name")->select();
            $this->assign('type',$list);
            $this->display("editDraw");
        }else{
            $data = D("Draw")->create();
            if($_FILES['img']['name']){
                $data['img'] = $this->uploadImg("Draw","img");
            }
            $data['utime'] = time();
            $res = D("Draw")->where(array('d_id'=>$_POST['d_id']))->limit(1)->save($data);
            if($res){
                $this->success("修改成功！",U("Draw/drawList"));
            }else{
                $this->error("修改失败！");
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteDraw(){
        if(empty($_REQUEST['d_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['d_id'] = array('IN',I('request.d_id'));
        $data['status'] = 9;
        $upd_res = $this->Draw->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**修改分类的排序*/
    public function ajaxSort(){
        $w['d_id'] = $_POST['id'];
        $data['sort'] = $_POST['sort'];
        $res = M("Draw")->where($w)->limit(1)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }

    /**抽奖记录*/
    public function selectDrawLog(){
        $pam = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        if(I("request.mix_name")){
            $w["mix_name"] = array("LIKE","%".I("request.mix_name")."%");
            $parameter['mix_name'] = I("request.mix_name");
            $this->assign("mix_name",I("request.mix_name"));
        }
        if(I("request.type")){
            $w["type"] = I("request.type")-1;
            $parameter['type'] = I("request.type");
            $this->assign("type",I("request.type"));
        }
        $w['status'] = array('neq',9);
        $list = D("draw_log")->selectDrawLog($w,"ctime desc",15,$pam);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("drawLog");
    }


    /**
     * 收支明细列表
     */
    public function detailList()
    {
        $pam = array();
        if(!empty(I('request.start_time')) && !empty(I('request.end_time'))){
            $start_time = I('request.start_time');
            $this->assign("start_time",$start_time);
            $end_time = I('request.end_time');
            $this->assign("end_time",$end_time);
            $w['ctime'] = array(array('EGT',strtotime($start_time)),array('ELT',strtotime($end_time)),'and');
            $pam['start_time'] = I('request.start_time');
            $pam['end_time'] = I('request.end_time');
        }
        if(I("request.mix_name")){
            $w["mix_name"] = array("LIKE","%".I("request.mix_name")."%");
            $parameter['mix_name'] = I("request.mix_name");
            $this->assign("mix_name",I("request.mix_name"));
        }
        if(I("request.type")){
            $w["type"] = I("request.type")-1;
            $parameter['type'] = I("request.type");
            $this->assign("type",I("request.type"));
        }
        $w['status'] = array('neq',9);
        $list = D("draw_log")->selectDrawLog($w,"ctime desc",15,$pam);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display('detailList');
    }


}