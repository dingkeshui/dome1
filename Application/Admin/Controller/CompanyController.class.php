<?php

namespace Admin\Controller;
use Think\Controller;


class CompanyController extends AdminBasicController {
    public $Company = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Company = D('Company');
    }
    /**
     *联系我们
     */
    public function addAbout(){
        if(empty($_POST)){
            $where['com_id'] = 1;
            $Company = $this ->Company-> findCompany($where);
            $this->assign("Company",$Company);
            $this->display("addAbout");
        }else{
            $where['com_id'] = 1;
            if (get_magic_quotes_gpc()) {
                $date['content'] = stripslashes($_POST['content']);
            } else {
                $date['content'] = $_POST['content'];
            }
            $res = $this->Company->editFunction($where,$date);
            if($res){
                $this->success("添加成功",U("Company/addCompany"));
            }else{

            }
        }
    }
    /**
     *公司简介添加
     */
    public function addCompany(){
        if(empty($_POST)){
            $where['com_id'] = 2;
            $Company = $this ->Company->  findCompany($where);
            $this->assign("Company",$Company);
            $this->display("addCompany");
        }else{
            $where['com_id'] = 2;
            if (get_magic_quotes_gpc()) {
                $date['content'] = stripslashes($_POST['content']);
            } else {
                $date['content'] = $_POST['content'];
            }
            $res = $this->Company->editFunction($where,$date);
            if($res){
                $this->success("添加成功",U("Company/addCompany"));
            }else{

            }
        }
    }

    /**
     *分销说明
     */
    public function protocol(){
        if(empty($_POST)){
            $where['com_id'] = 3;
            $Company = $this ->Company->  findCompany($where);
            $this->assign("Company",$Company);
            $this->display("protocol");
        }else{
            $where['com_id'] = 3;
            if (get_magic_quotes_gpc()) {
                $date['content'] = stripslashes($_POST['content']);
            } else {
                $date['content'] = $_POST['content'];
            }
            $res = $this->Company->editFunction($where,$date);
            if($res){
                $this->success("添加成功",U("Company/protocol"));
            }else{
                $this->success("添加成功",U("Company/protocol"));
            }
        }
    }

    
}