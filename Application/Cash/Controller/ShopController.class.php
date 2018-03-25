<?php
namespace Cash\Controller;
use Think\Controller;
/**
 * 商家管理
 */
class ShopController extends AdminBasicController {
    public $Goods = '';
    public function _initialize(){
        $this->checkLogin();
        $this->Goods = D('Shop');
    }

    /**商品列表*/
    public function shopList(){
        if(I('request.name')){
            $w['name'] = array('LIKE','%'.I('request.name').'%');
            $request['name'] = I('request.name');
            $param['name'] = I('request.name');
            $this->assign("request",$request);
        }
        if(I('request.account')){
            $w['account'] = array('LIKE','%'.I('request.account').'%');
            $request['account'] = I('request.account');
            $param['account'] = I('request.account');
            $this->assign("request",$request);
        }
        if(I('request.class_id')){
            $w['class_id'] = I('request.class_id');
            $request['class_id'] = I('request.class_id');
            $param['class_id'] = I('request.class_id');
            $this->assign("request",$request);
        }
        if($_REQUEST['status']){
            $w['status'] = $_REQUEST['status']-1;
            $param['status'] = I('request.status');
            $this->assign("status",I('request.status'));
        }else{
            $w['status'] = array('neq',9);
        }
        //获取菜场类型
//        $type = D("Class")->where(array("status"=>array('neq',9)))->field("class_id,name")->select();
//        $this->assign("type",$type);
        /**
         * 找到代理商的信息
         * 1:市级代理
         * 2:区级代理
         */
        $where_e['e_id'] = session('E_ID');
        $res_e = D("agency_earn")->where($where_e)->find();
        if($res_e['type'] == 1){
            $w['city'] = $res_e['city'];
        }elseif ($res_e['type'] == 2){
            $w['area'] = $res_e['area'];
        }
        S('s_where',$w);
        if(session('CLASS_E_ID')){
            $w['class_id'] = session('CLASS_E_ID');
        }
        $list = D("Shop")->selectShop($w,"ctime desc",15,$param);
        foreach ($list['list'] as $k=>$v){
            $list['list'][$k]['class_name'] = M("Class")->where(array('class_id'=>$v['class_id']))->getField("name");
            /**找到省市区级的名称*/
            $list['list'][$k]['province_name'] = M("Areas")->where(array('area_id'=>$v['province']))->getField('area_name');
            $list['list'][$k]['city_name'] = M("Areas")->where(array('area_id'=>$v['city']))->getField('area_name');
            $list['list'][$k]['area_name'] = M("Areas")->where(array('area_id'=>$v['area']))->getField('area_name');
        }
        $this->assign('list',$list['list']);
        $this->assign('page',$list['page']);
        $this->display("shopList");
    }

    /**判断这个手机号是否已经被注册*/
    public function isRegister(){
        $w['status'] = array('neq',9);
        if($_POST['shop_id']){
            $w1['shop_id'] = $_POST['shop_id'];
            $w1['status'] = array('neq',9);
            $res = M("Shop")->where($w1)->limit(1)->find();
            if($res['account'] != $_POST['account']){
                $this->ajaxReturn(1);
            }
        }else{
            $w['account'] = $_POST['account'];
            $shop_id = M("Shop")->where($w)->limit(1)->getField("shop_id");
            if($shop_id){
                $this->ajaxReturn(1);
            }else{
                $this->ajaxReturn(0);
            }
        }
    }

    public function downLoadShopCode(){
        /**获取商家*/
        $w['shop_id'] = $_GET['shop_id'];
        $res = M("Shop")->where($w)->find();
        $file =  C('API_URL')."/".$res['code'];
        $name = $res['name']."商家的二维码";
        header("Content-type: octet/stream");
        header('Content-Disposition: attachment; filename="' . $name . '.png"');
        header("Content-Length: ". filesize($file));
        readfile($file);
    }

    /**商家添加*/
    public function addShop(){
        if(!IS_POST){
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->select();
            $this->assign("pro",$pro);
            //获取菜场类型
            $type = D("Class")->where(array("status"=>array('neq',9)))->field("class_id,name,pic")->select();
            $this->assign("type",$type);
            $this->display("addShop");
        }else{
            /**先查看商家的账号是否已经被注册了*/
            $shop_res = M("Shop")->where(array('account'=>$_POST['account'],'status'=>array('not in','1,9')))->getField("shop_id");
            if($shop_res){
                $this->error("此手机号已经被注册！");
            }
            M("Shop")->startTrans();
            $data = D("Shop")->create();
            if($data){
                if(I("post.head_pic")){
                    $data['head_pic'] = I("post.head_pic");
                }
                if(I("post.pic")){
                    $string_pic = implode(",",I("post.pic"));
                    $data['pic'] = $string_pic;
                }
                if(I("post.more_pic")){
                    $string_more = implode(",",I("post.more_pic"));
                    $data['more_pic'] = $string_more;
                }
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                $data['ctime'] = time();
                $data['status'] = 1;
                $res = M("Shop")->add($data);
                /**为商户创建一个二维码*/
                unset($data);
                $data['code'] = $this->png($res);
                $data['password'] = md5($_POST['password']);
                $code = D("Shop")->where(array('shop_id'=>$res))->save($data);
                if($res && $code){
                    M("Shop")->commit();
                    $this->success("添加成功！",U('Shop/shopList'));
                }else{
                    M("Shop")->rollback();
                    $this->error("添加失败！");
                }
            }else{
                $this->error(D("Shop")->getError());
            }

        }
    }

    public function png($id){
        vendor("phpqrcode.phpqrcode");
        /**用户的注册*/
        $Submit = new \Vendor\phpqrcode\QRcode();
        $data1 = C("API_URL")."/index.php/".'Pay/pay/shop_id/'.$id;
        //$data1 = "http://www.baidu.com";
        //dump($data);
        // 纠错级别：L、M、Q、H，也就是二维码可以覆盖的区域
        $level = 'H';
        // 点的大小：1到10,用于手机端4就可以了
        $size = 12;
        // 下面注释了把二维码图片保存到本地的代码,如果要保存图片,用$fileName替换第二个参数false
        $path = "Uploads/Shop/Code/";
        // 生成的文件名
        $fileName = $path.time().rand(10000,99999).$size.'.png';
        $data['code'] = $fileName;
        //生成二维码图片
        $Submit->png($data1, $fileName, $level, $size , 1);
        $logo = 'Uploads/Shop/Code/logo.png';//准备好的logo图片
        $QR = $fileName;//已经生成的原始二维码图
        if ($logo !== FALSE) {
            $QR = imagecreatefromstring(file_get_contents($QR));
            $logo = imagecreatefromstring(file_get_contents($logo));
            $QR_width = imagesx($QR);//二维码图片宽度
            $QR_height = imagesy($QR);//二维码图片高度
            $logo_width = imagesx($logo);//logo图片宽度
            $logo_height = imagesy($logo);//logo图片高度
            $logo_qr_width = $QR_width / 5;
            $scale = $logo_width/$logo_qr_width;
            $logo_qr_height = $logo_height/$scale;
            $from_width = ($QR_width - $logo_qr_width) / 2;
            //重新组合图片并调整大小
            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,
            $logo_qr_height, $logo_width, $logo_height);
            //输出图片
            $fileName1 = rand(10000,99999).'_'.$id.'.'.'png';
            imagepng($QR, "Uploads/Shop/Code/".$fileName1);
            /**删除没有logo的图片*/
            @unlink($fileName);
            return "Uploads/Shop/Code/".$fileName1;
        }
    }

    /**商家修改*/
    public function editShop(){
        if(!IS_POST){
            $res = D("Shop")->where(array('shop_id'=>$_GET['shop_id']))->find();
            /**商家的多个图片*/
            if(!empty($res['pic'])){
                $list_img = explode(",",$res['pic']);
                $res['list_img'] = $list_img;
            }else{
                $res['list_img'] = "";
            }
            /**商家的资质证明等等图片*/
            if(!empty($res['more_pic'])){
                $more_img = explode(",",$res['more_pic']);
                $res['more_img'] = $more_img;
            }else{
                $res['more_img'] = "";
            }
            $this->assign("res",$res);
            //获取所有省份
            $pro = M("areas")->where(array('parent_id'=>1))->select();
            $this->assign("pro",$pro);
            //获取城市
            $city = M("areas")->where(array('parent_id'=>$res['province']))->select();
            $this->assign("city",$city);
            //获取区域
            $area = M("areas")->where(array('parent_id'=>$res['city']))->select();
            $this->assign("area",$area);
            //获取菜场类型
            $type = D("Class")->where(array("status"=>array('neq',9)))->field("class_id,name,pic")->select();
            $this->assign("type",$type);

            $this->display("editShop");
        }else{
            /**先查看商家的账号是否已经被注册了*/
            $account = D("Shop")->where(array("shop_id"=>$_POST['shop_id']))->getField("account");
            if($account != $_POST['account']){
                $shop_res = M("Shop")->where(array('account'=>$_POST['account'],'status'=>array('not in','1,9')))->getField("account");
                if($shop_res){
                    $this->error("此手机号已经被注册！");
                }
            }
            $data = D("Shop")->create();
            if($data){
                if(I("post.head_pic")){
                    $data['head_pic'] = I("post.head_pic");
                }
                if(I("post.pic")){
                    $string = implode(",",I("post.pic"));
                    $data['pic'] = $string;
                }
                if(I("post.more_pic")){
                    $string1 = implode(",",I("post.more_pic"));
                    $data['more_pic'] = $string1;
                }
//                dump(I("post.head_pic"));
//                dump(I("post.pic"));
//                dump(I("post.more_pic"));
//                exit();
                if (get_magic_quotes_gpc()) {
                    $data['content'] = stripslashes($_POST['content']);
                } else {
                    $data['content'] = $_POST['content'];
                }
                if(!empty($_POST['password'])){
                    $data['password'] = md5($_POST['password']);
                }
                $res = D("Shop")->where(array("shop_id"=>$_POST['shop_id']))->limit(1)->save($data);
                if($res){
                    $this->success("修改成功！",U("Shop/shopList"));
                }else{
                    $this->error("修改失败！");
                }
            }else{
                $this->error(D("Shop")->getError());
            }
        }
    }
    /**
     * 删除操作
     */
    public function deleteShop(){
        if(empty($_REQUEST['shop_id'])){
            $this->error('您未选择任何操作对象');
        }
        $where['shop_id'] = array('IN',I('request.shop_id'));
        $data['status'] = 9;
        $upd_res = D("Shop")->where($where)->save($data);
        if($upd_res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }
    

    /**
     * ajax获取城市
     */
    public function ajaxCity(){
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("areas")->where($where)->select();
        echo json_encode($data);
    }
    /**
     * ajax获取区域
     */
    public function ajaxArea(){
        $where["parent_id"] = $_GET['parent_id'];
        $data = M("areas")->where($where)->select();
        echo json_encode($data);
    }

    /**ajax上传图片*/
    public function uploadPic(){
        $pic       = $_POST['pic'];
        $pic_name      = $_POST['pic_name'];
        $temp = explode('.',$pic_name);
        $ext = uniqid().'.'.end($temp);
        $base64    = substr(strstr($pic, ","), 1);
        $image_res = base64_decode($base64);
        $pic_link  = "Uploads/Shop/".date('Y-m-d').'/'.$ext;
        $saveRoot = "Uploads/Shop/".date('Y-m-d').'/';
        /**检查目录是否存在  循环创建目录*/
        if(!is_dir($saveRoot)){
            mkdir($saveRoot, 0777, true);
        }
        $res = file_put_contents($pic_link ,$image_res);
        if($res){
                $ajaxData = array("flag" => "success", "message"=>"上传成功！" );
                $result_data['path'] = $pic_link;
                $ajaxData['data'] = $result_data;
                $this->ajaxReturn(json_encode($ajaxData));
        }else{
            $ajaxData = array("flag" => "error", "message"=>"上传失败","data" => array());
            $this->ajaxReturn(json_encode($ajaxData));
        }
    }

    /**删除上传的乡相册的图片*/
    public function delPhoto(){
        /**判断删除的商家的图片type:1头像 type2商家展示图 3商家的资质证明图*/
        $w['shop_id'] = $_POST['shop_id'];
        $res = M("Shop")->where($w)->limit(1)->find();
        $pic = "";
        if($_POST['type'] == 1){
            $data['head_pic'] = "";
            $pic = M("Shop")->where($w)->limit(1)->save($data);
        }elseif ($_POST['type'] == 2){
            $string1 = explode(",",$res['pic']);
            foreach ($string1 as $k=>$v){
                if($v == $_POST['file_path']){
                    unset($string1[$k]);
                }
            }
            if(!empty($string1)){
                $string_other = implode(',',$string1);
                $data['pic'] = $string_other;
                $pic = M("Shop")->where($w)->limit(1)->save($data);
            }else{
                $data['pic'] = "";
                $pic = M("Shop")->where($w)->limit(1)->save($data);
            }
        }elseif ($_POST['type'] == 3){
            $string2 = explode(",",$res['more_pic']);
            foreach ($string2 as $k=>$v){
                if($v == $_POST['file_path']){
                    unset($string2[$k]);
                }
            }
            if(!empty($string2)){
                $string_other = implode(',',$string2);
                $data['more_pic'] = $string_other;
                $pic = M("Shop")->where($w)->limit(1)->save($data);
            }else{
                $data['more_pic'] = "";
                $pic = M("Shop")->where($w)->limit(1)->save($data);
            }
        }
        $file  = $_POST['file_path'];
        $result = @unlink($file);
        if ($result == false && empty($pic)) {
            $this->ajaxReturn(0);
        } else {
            $this->ajaxReturn(1);
        }
    }


//    /**商家的评价信息*/
//    public function appraiseList(){
//        $w['']
//    }


    /**商家的评价*/
    public function appraise(){
        $parm = array();
        if($_GET['shop_id']){
            $w['shop_id'] = $_GET['shop_id'];
            $parm['shop_id'] = $_GET['shop_id'];
        }
        $w['status'] = array('neq',9);

        $list = D("Appraise")->selectAppraise($w,'ctime desc',15,$parm);
        foreach ($list['list'] as $kk=>$vv){
            $list['list'][$kk]['mem_name'] = M("Member")->where(array('m_id'=>$vv['m_id']))->getField('nick_name');
        }
        $this->assign("list",$list['list']);
        $this->assign("page",$list['page']);
        $this->display("appraise");
    }

    /**删除评价*/
    public function delAppraise(){
        $w['app_id'] = $_GET['app_id'];
        $data['status'] = 9;
        $res = M("Appraise")->where($w)->save($data);
        if($res){
            $this->success('删除操作成功');
        }else{
            $this->error('删除操作失败');
        }
    }


    /**异步修改用户的提成*/
    public function ajaxAppraise(){
        $w['app_id'] = I("post.id");
        $data['status'] = I("post.status");
        $res = D("Appraise")->where($w)->save($data);
        if($res){
            $this->ajaxReturn(1);
        }else{
            $this->ajaxReturn(0);
        }
    }

    /**查看评价的详情*/
    public function oneAppraise(){
        $w['app_id'] = $_GET['app_id'];
        $res = M("Appraise")->where($w)->find();
        /**获取用户的名称*/
        $member = M("Member")->where(array('m_id'=>$res['m_id']))->field("head_pic,nick_name")->find();
        $res['mem_name'] = $member['nick_name'];
        $res['head_pic'] = $member['head_pic'];
        /**查看评价的回复*/
        /**商家回复和用户回复的信息*/
        $res_list = D("ReplyAppraise")->where(array('app_id'=>$res['app_id']))->field("m_name,r_m_name,content")->select();
        $res['list'] = $res_list;
        $this->assign('res',$res);
        $this->assign('shop_id',$_GET['shop_id']);
        $this->display("oneDetail");
    }

    /**
     * 导出商家excel的表单
     * @time 2017-08-18
     * @author mss
     */
    public function shopXLS()
    {
        $is_set = S("s_where");
        if($is_set){
            $w = $is_set;
        }
        //$w['status'] = array('neq',9);
        $arrordername = array( '商家名称', '入驻时间','省','市','区', '商家账号', '商家类型', '营业时间', '联系电话', '详细地址', '余额','状态','总的消费', '满足股数相差金额');
        //循环数据
        $arrorderlist = array();
        $list = M("Shop")->where($w)->select();
        foreach ($list as $k=>$v){
            $class_name = M("Class")->where(array('class_id'=>$v['class_id']))->getField("name");
            $val['province_name'] = M('Areas')->where(array('area_id'=>$v['province']))->getField('area_name');
            $val['city_name'] = M('Areas')->where(array('area_id'=>$v['city']))->getField('area_name');
            $val['area_name'] = M('Areas')->where(array('area_id'=>$v['area']))->getField('area_name');
            if($v['status']==0){
                $state = '正常';
            }elseif($v['status']==1){
                $state = '待审核';
            }elseif($v['status']==2){
                $state = '审核失败';
            }
            $arrorderlist[] = array($v['name'],date('Y-m-d H:i:s',$v['ctime']),$v['province_name']?$v['province_name']:'未填写',$v['city_name']?$v['city_name']:'未填写',$v['area_name']?$v['area_name']:'未填写',$v['account'],$class_name,
                $v['time'],$v['tel'],$v['address'],$v['wallet'],$state,$v['total'],$v['earn_total']);
        }
        exportexcel($arrorderlist, $arrordername, '商家信息' . date("Y/m/d H.i.s"));

    }






}