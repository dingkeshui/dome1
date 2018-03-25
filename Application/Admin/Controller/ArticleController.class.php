<?php

namespace Admin\Controller;
use Think\Controller;

/**
 * Class NewsController
 * @package Admin\Controller
 */
class ArticleController extends AdminBasicController {
    public $Article = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Article = D('Article');
    }


    /**
     * 公司新闻列表，广告的新闻列表
     * @author mss
     */
    public function articleList(){
        $par = array();
        $request = array();
        if($_REQUEST['title']){
            $w['title'] = array('LIKE','%'.$_REQUEST['title'].'%');
            $par['title'] = $_REQUEST['title'];
            $request['title'] = $_REQUEST['title'];
            $this->assign("request",$request);
        }
        $w['status'] = array('neq',9);
        $list = $this->Article->selectArticle($w,"ctime desc",15,$par);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("articleList");
    }
    /**
     * 删除新闻
     */
    public function deleteArticle(){
        if(empty($_REQUEST['article_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['article_id'] = array('IN',I('request.article_id'));
        $data['status'] = 9;
        $upd_res = D("Article")->editArticle($where,$data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**发布文章*/
    public function addArticle(){
        if(!IS_POST){
            $w['status'] = array('neq',9);
            $res = D("Classify")->where($w)->select();
            $this->assign('list',$res);
            $this->display("addArticle");
        }else{
//            if(empty($_FILES['pic']['name'])){
//                $this->error("请上传文章的封面！");
//            }
            $data = D("Article")->create();
            if($data){
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                if($_FILES['pic']['name']){
                    $img = $this->uploadImg("Article","pic");
                    $data['pic'] = $img;
                }
                $res = D("Article")->add($data);
                if($res){
                    $this->success("发布成功！",U('Article/articleList'));
                }else{
                    $this->error("发布失败！");
                }
            }else{
                $this->error(D("Article")->getError());
            }
        }
    }

    /**文章修改*/
    public function editArticle(){
        if(!IS_POST){
            /**差文章的分类*/
            $c_w['status'] = array('neq',9);
            $list = D("Classify")->where($c_w)->select();
            $this->assign("list",$list);
            $w['article_id'] = $_GET['article_id'];
            $res = D("Article")->where($w)->find();
            $this->assign("res",$res);
            $this->display("editArticle");
        }else{
            $w['article_id'] = $_POST['article_id'];
            $data = D("Article")->create();
            if($data){
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                if($_FILES['pic']['name']){
                    $img = $this->uploadImg("Article","pic");
                    $data['pic'] = $img;
                }
                $res = D("Article")->where($w)->save($data);
                if($res){
                    $this->success("修改成功！",U('Article/articleList'));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error(D("Article")->getError());
            }
        }
    }


}