<?php
namespace Admin\Controller;
use Think\Controller;

class WeiXinArticleController extends AdminBasicController{

    public $return = '';
    public $menu = '';
    public function _initialize(){
        $this->checkLogin();
        $this->return = D('Wechat');
        $this->menu = D('Menu');
        header("Content-type: text/html; charset=utf-8");
    }

    /**
     * 添加关注时回复
     */
    public function addFollowBack(){
        if(empty($_POST['wxa_description'])){
            $this->error('回复内容不能为空');
        }else{
            $follow_back = $this->weixin->findWeiXinArticle(array('wxa_type'=>1));
            if($follow_back){
                $where['wxa_id'] = $follow_back['wxa_id'];
                $data['wxa_description'] = $_POST['wxa_description'];
                $data['utime'] = time();
                $edit_res = $this->weixin->editWeiXinArticle($where,$data);
                if($edit_res){
                    $this->success('保存成功',U('WeiXinArticle/backType',array('back_type'=>'FollowBack')));
                }else{
                    $this->error('保存失败');
                }
            }else{
                $data['wxa_type'] = 1;
                $data['wxa_description'] = $_POST['wxa_description'];
                $data['ctime'] = time();
                $edit_res = $this->weixin->addWeiXinArticle($data);
                if($edit_res){
                    $this->success('保存成功',U('WeiXinArticle/backType',array('back_type'=>'FollowBack')));
                }else{
                    $this->error('保存失败');
                }
            }
        }
    }
    /**
     * 添加文本回复
     */
    public function addReturn(){
        if(!IS_POST){
            $this->display("addReturn");
        }else{
            if(empty($_POST['keywords'])){
                $this->error('关键词不能为空');
            }if(empty($_POST['content'])){
                $this->error('内容不能为空');
            }else{
                //判断关键词是否已经存在
                $is_keywords = D('Wechat')->where(array('keywords'=>$_POST['keywords'],'status'=>array('NEQ',9),'m_type'=>1))->find();
                if($is_keywords){
                    $this->error('关键词已经存在');
                }
                $data['keywords'] = $_POST['keywords'];
                $data['content'] = $_POST['content'];
                $data['ctime'] = time();
                $data['m_type'] = 1;
                $add_res = D("Wechat")->add($data);
                if($add_res){
                    $this->success('添加成功',U('WeiXinArticle/returnList'));
                }else{
                    $this->error('添加失败');
                }
            }
        }

    }


    /**
     * 添加文本回复
     */
    public function editReturn(){
        if(!IS_POST){
            $w['w_id'] = I("get.w_id");
            $res = D("Wechat")->where($w)->find();
            $this->assign("res",$res);
            $this->display("editReturn");
        }else{
            if(empty($_POST['keywords'])){
                $this->error('关键词不能为空');
            }if(empty($_POST['content'])){
                $this->error('内容不能为空');
            }else{
                $w['w_id'] = I("post.w_id");
                //判断关键词是否已经存在
                $is_keywords = D('Wechat')->where(array('keywords'=>$_POST['keywords'],'status'=>array('NEQ',9),'m_type'=>1))->find();
                if($is_keywords){
                    $this->error('关键词已经存在');
                }
                $data['keywords'] = $_POST['keywords'];
                $data['content'] = $_POST['content'];
                $data['utime'] = time();
                $add_res = $this->return->where($w)->save($data);
                if($add_res){
                    if($add_res){
                        $this->success('修改成功',U('WeiXinArticle/returnList'));
                    }else{
                        $this->error('修改失败');
                    }
                }
            }
        }

    }

    public function delReturn(){
        if(empty($_REQUEST['w_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['w_id'] = array('IN',I('request.w_id'));
        $data['status'] = 9;
        $upd_res = D("Wechat")->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**关键词回复列表*/
    public function returnList(){
        $par = array();
        $request = array();
        if($_REQUEST['title']){
            $w['title'] = array('LIKE','%'.$_REQUEST['title'].'%');
            $par['title'] = $_REQUEST['title'];
            $request['title'] = $_REQUEST['title'];
            $this->assign("request",$request);
        }
        $w['status'] = array('neq',9);
        $w['m_type'] = 1;
        $list = D("Wechat")->selectWechat($w,"ctime desc",15,$par);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("returnList");
    }



    /**
     * 添加菜单
     */
    public function createMenu(){
        if(empty($_POST)){
            $menu_list = $this->menu->selectMenu('','sort desc','');
            $sel_menu_list = $this->menu->selectMenu(array('parent'=>0),'sort desc','');
            foreach($menu_list as $key=>$value){
                if($value['parent_id'] != 0){
                    $menu_list[$key]['parent_id'] = $this->menu->where(array('id'=>$value['parent_id']))->getField('name');
                }
            }
            $this->assign('menu_list',$menu_list);
            $this->assign('sel_menu_list',$sel_menu_list);
            $this->display('createMenu');
        }else{
            if(empty($_POST['name'])){
                $this->error('菜单名称必填');
            }
            if(empty($_POST['keywords']) && empty($_POST['url']) && empty($_POST['media_id'])){
                $this->error('关联关键字、外链URL和图文id选填');
            }
            if(!empty($_POST['url']) && !preg_match(C('URL'),$_POST['url'])){
                $this->error('外链URL格式不正确');
            }else{
                /**判断是否已经存在*/
                $is_set = $this->menu->where(array('parent'=>$_POST['parent'],'status'=>0,'name'=>$_POST['name']))->find();
                if($is_set){
                    $this->error('该菜单栏已经存在了');
                }
                $count = $this->menu->where(array('parent'=>$_POST['parent'],'status'=>0))->count();
                if($_POST['parent'] == 0){
                    if($count >= 3){
                        $this->error('一级菜单最多可以创建三个');
                    }
                }else{
                    if($count >= 5){
                        $this->error('二级菜单最多可以创建五个');
                    }
                }
                $data = $this->menu->create();
                if($data){
                    $data['ctime'] = time();
                    $result = $this->menu->addMenu($data);
                    if($result){
                        $this->success('保存成功',U('WeiXinArticle/menuList'));
                    }else{
                        $this->error('保存失败');
                    }
                }else{
                    $this->error('创建数据对象失败');
                }
            }
        }
    }
    /**
     * 编辑菜单
     */
    public function editMenu(){
        if(empty($_POST)){
            $sel_menu_list = $this->menu->selectMenu(array('parent'=>0),'sort desc','');
            $this->assign('sel_menu_list',$sel_menu_list);
            $menu_res = $this->menu->where(array('me_id'=>$_GET['me_id']))->find();
            $this->assign('res',$menu_res);
            $this->display('editMenu');
        }else{
            if(empty($_POST['name'])){
                $this->error('菜单名称必填');
            }if(empty($_POST['keywords']) && empty($_POST['url']) && empty($_POST['media_id'])){
                $this->error('关联关键字、外链URL和图文id选填');
            }if(!empty($_POST['url']) && !preg_match(C('URL'),$_POST['url'])){
                $this->error('外链URL格式不正确');
            }else{

                /**判断是否已经存在*/
                $is_set = $this->menu->where(array('parent'=>$_POST['parent'],'status'=>0,
                    'name'=>$_POST['name'],'me_id'=>['neq',I("post.me_id")]))->find();
                if($is_set){
                    $this->error('该菜单栏已经存在了');
                }

                $w['me_id'] = I("post.me_id");
                $count = $this->menu->where(array('parent'=>$_POST['parent'],'me_id'=>['neq',I("post.me_id")],'status'=>0))->count();
                if($_POST['parent'] == 0){
                    if($count >= 3){
                        $this->error('一级菜单最多可以创建三个');
                    }
                }else{
                    if($count >= 5){
                        $this->error('二级菜单最多可以创建五个');
                    }
                }
                $data = $this->menu->create();
                if($data){
                    $data['utime'] = time();
                    $result = $this->menu->where($w)->save($data);
                    if($result){
                        $this->success('修改成功',U('WeiXinArticle/menuList'));
                    }else{
                        $this->error('修改失败');
                    }
                }else{
                    $this->error('创建数据对象失败');
                }
            }
        }
    }
    /**
     * 创建菜单
     */
    public function doCreateMenu(){
        $data = $this->getData();
        if($data == ''){
            $this->error('您还没有创建任何菜单');
        }
        $acs_token = wx_get_token();
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$acs_token;
        $result = $this->https_request($url,$data);
        $result = json_decode($result);
        if($result->errcode == 0){
            $this->success('创建菜单成功，需重新关注或隔天才能看到效果',U("WeiXinArticle/menuList"));
        }else{
            $this->error('创建菜单失败，稍后请重试');
        }
    }


    /**获取菜单*/
    public function menuList(){
        $root_menu_list = $this->menu->selectMenu("",'sort asc');
        foreach($root_menu_list as $k=>$v){
            if(!empty($v['parent'])){
                $w['me_id'] =  $v['parent'];
                $res = D("Menu")->where($w)->find();
                $root_menu_list[$k]['p_name'] = $res['name'];
            }
        }
        $this->assign("list",$root_menu_list);
        $this->display("menuList");
    }
    /**
     * 获取数据
     */
    public function getData(){
        $data = '{"button" : [';
        $root_menu_list = $this->menu->selectMenu(array('parent'=>0),'sort asc','');
        if($root_menu_list){
            foreach($root_menu_list as $key1=>$root){
                $son_menu_list = $this->menu->selectMenu(array('parent'=>$root['me_id']),'sort desc','');
                if($son_menu_list){        //二级分类
                    $data .= '{"name" : "'.$root['name'].'","sub_button" : [';
                    foreach($son_menu_list as $key2=>$son){
                        if(!empty($son['url'])){
                            $data .= '{
                                          "type" : "view",
                                          "name" : "'.$son['name'].'",
                                          "url"  : "'.$son['url'].'"
                                       }';
                        }
                        if(empty($son['url']) && !empty($son['keywords'])){
                            $data .= '{
                                          "type" : "click",
                                          "name" : "'.$son['name'].'",
                                          "key"  : "'.$son['keywords'].'"
                                       }';
                        }
                        if(empty($son['url']) && empty($son['keywords']) && !empty($son['media_id'])){
                            $data .= '{
                                          "type" : "view_limited",
                                          "name" : "'.$son['name'].'",
                                          "media_id"  : "'.$son['media_id'].'"
                                       }';
                        }
                        if(count($son_menu_list)-$key2 != 1){
                            $data .= ",";
                        }
                    }
                    $data .= "]}";
                    if(count($root_menu_list)-$key1 > 1){
                        $data .= ",";
                    }
                }else{
                    if(!empty($root['url'])){
                        $data .= '{
                                      "type" : "view",
                                      "name" : "'.$root['name'].'",
                                      "url"  : "'.$root['url'].'"
                                   }';
                    }
                    if(empty($root['url']) && !empty($root['keywords'])){
                        $data .= '{
                                      "type" : "click",
                                      "name" : "'.$root['name'].'",
                                      "key"  : "'.$root['keywords'].'"
                                   }';
                    }
                    if(empty($son['url']) && empty($son['keywords']) && !empty($son['media_id'])){
                        $data .= '{
                                      "type" : "view_limited",
                                      "name" : "'.$son['name'].'",
                                      "media_id"  : "'.$son['media_id'].'"
                                    }';
                    }
                    if(count($root_menu_list)-$key1 > 1){
                        $data .= ",";
                    }
                }
            }
            return $data."]}";
        }else{
            return  '';
        }
    }

    /**
     * 创建菜单方法
     */
    function https_request($url,$data = null){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * 删除菜单
     */
    public function delMenu(){
        $where['id'] = $_GET['id'];
        $result = $this->menu->deleteMenu($where);
        if($result){
            $this->success('删除成功');
        }else{
            $this->error('删除失败');
        }
    }


    /**
     * 添加图文回复
     * @time 2017-08-03
     * @author crazy
     */
    public function addWechat(){
        if(!IS_POST){
            $this->display('addWechat');
        }else{
            $data = D("Wechat")->create();
            if($data){
                //判断关键词是否已经存在
                $is_keywords = D('Wechat')->where(array('keywords'=>$_POST['keywords'],'status'=>array('NEQ',9),'m_type'=>2))->find();
                if($is_keywords){
                    $this->error('关键词已经存在');
                }
                if(empty($_FILES['pic']['name'])){
                    $this->error("请上传封面！");
                }
                if($_FILES['pic']['name']){
                    $img = $this->uploadImg("Wechat","pic");
                    $data['pic'] = $img;
                }
                $keywords =str_replace('，',',',$_POST['keywords']);
                $end = substr($keywords,-1,1);
                if($end == "," || $end == "，"){
                    $keywords = substr($keywords,0,strlen($keywords)-1);
                }
                $data['keywords'] = $keywords;
                $data['m_type'] = 2;
                $res = D("Wechat")->add($data);
                if($res){
                    $this->success("发布成功！",U('WeiXinArticle/wechatList'));
                }else{
                    $this->error("发布失败！");
                }
            }else{
                $this->error(D("Wechat")->getError());
            }
        }
    }


    /**
     * 修改图文回复
     * @time 2017-08-03
     * @author crazy
     */
    public function editWechat(){
        if(!IS_POST){
            $res = D("Wechat")->where(array('w_id'=>$_GET['w_id']))->limit(1)->field('w_id,title,desc,pic,url,type,keywords')->find();
            $this->assign('res',$res);
            $this->display('editWechat');
        }else{
            $data = D("Wechat")->create();
            if($data){
                //判断关键词是否已经存在
                $is_keywords = D('Wechat')->where(array('keywords'=>$_POST['keywords'],'status'=>array('NEQ',9),'m_type'=>2))->find();
                if($is_keywords){
                    $this->error('关键词已经存在');
                }

                if($_FILES['pic']['name']){
                    $img = $this->uploadImg("Wechat","pic");
                    $data['pic'] = $img;
                }
                $keywords =str_replace('，',',',$_POST['keywords']);
                $end = substr($keywords,-1,1);
                if($end == "," || $end == "，"){
                    $keywords = substr($keywords,0,strlen($keywords)-1);
                }
                $data['keywords'] = $keywords;
                $res = D("Wechat")->where(array('w_id'=>$_POST['w_id']))->limit(1)->save($data);
                if($res){
                    $this->success("修改成功！",U('WeiXinArticle/wechatList'));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error(D("Wechat")->getError());
            }
        }
    }


    /**
     * 删除菜单
     * @time 2017-08-03
     * @author crazy
     */
    public function delWechat(){
        if(empty($_REQUEST['w_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['w_id'] = array('IN',I('request.w_id'));
        $data['status'] = 9;
        $upd_res = D("Wechat")->editWechat($where,$data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }

    /**
     * 微信图文消息
     * @time 2017-08-03
     * @author crazy
     */
    public function wechatList(){
        $par = array();
        $request = array();
        if($_REQUEST['title']){
            $w['title'] = array('LIKE','%'.$_REQUEST['title'].'%');
            $par['title'] = $_REQUEST['title'];
            $request['title'] = $_REQUEST['title'];
            $this->assign("request",$request);
        }
        $w['status'] = array('neq',9);
        $w['m_type'] = 2;
        $list = D("Wechat")->selectWechat($w,"ctime desc",15,$par);
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("wechatList");
    }


}