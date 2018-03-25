<?php
namespace Api\Controller;

use jzq\test\sign;
use Think\Controller;
require "./Jzq/example/sign.php";
/**
 * Class HtController
 * @package Api\Controller
 * 第三方合同
 */
class HtController extends ApiBasicController{
    public $sign_obj = '';
    public function _initialize(){
        parent::_initialize();
        $this->sign_obj = new sign();
    }

    /**测试是否能够ping通*/
    public function ping(){
        $this->sign_obj->ping();
    }


    /**
     * 签约
     * @author crazy
     * @time 2017-11-29
     * @param shop_id 商家的id
     * name 商家的名称（企业）
     * address 商家的地址
     * moblie 手机号
     * identityCard 身份证号
     * legal_person 法人姓名
     * $url
     */
    public function sign(){
        $shop_id = I('post.shop_id');
        /**添加用户的合同编号*/
        $res = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->find();
        $name= $res['name'];
        $address= $res['address'];
        $moblie= $res['account'];
        $email=$res['email'];
        $legal_person=$res['legal_person'];
        $identityCard=$res['id_number'];
        $applyNo = $this->sign_obj->getApply($name,$address,$moblie,$email,$legal_person,$identityCard);
        if($applyNo['success'] == true){
            $res = $this->sign_obj->sign($applyNo['applyNo'],$legal_person,$email,$identityCard);
            $url = $res['link'].'&backUrl='.urlencode("https://".$_SERVER['HTTP_HOST']."/index.php/Merchant/Shop/shopcenter");
//            $url = $res['link'].'&backUrl='.urlencode("https://".$url);
            $update_apply_no = M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save(array('apply_no'=>$applyNo['applyNo']));
            if(!$update_apply_no){
                apiResponse("error","合同编号生成失败！请联系客服！");
            }else{
                if($res['success']){
                    apiResponse("success","获取成功！",$url);
                }else{
                    $mess = $res['error']['message'];
                    apiResponse("error",$mess);
                }
            }

        }else{
            $mess = $applyNo['error']['message'];
            apiResponse("error",$mess);
        }


    }

    /**回调地址*/
    public function callBack(){
        $result=array(
            "resultCode"=>"",
            "msg"=>"",
            "success"=>true,
        );
        if(!isset($_REQUEST['applyNo'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="applyNo is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }
        if(!isset($_REQUEST['identityType'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="identityType is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }
        if(!isset($_REQUEST['fullName'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="fullName is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }
        if(!isset($_REQUEST['identityCard'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="identityCard is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }
        if(!isset($_REQUEST['optTime'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="optTime is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }
        if(!isset($_REQUEST['signStatus'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="signStatus is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }
        if(!isset($_REQUEST['timestamp'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="timestamp is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }

        if(!isset($_REQUEST['sign'])){
            $result['resultCode']="jsonParamsError";
            $result['msg']="sign is null";
            $result['success']=false;
            echo($this->sign_obj->ropUtils($result));
            exit(0);
        }

        $applyNo=$_REQUEST['applyNo'];
        $identityType=$_REQUEST['identityType'];
        $fullName=$_REQUEST['fullName'];
        $identityCard=$_REQUEST['identityCard'];
        $optTime=$_REQUEST['optTime'];
        $signStatus=$_REQUEST['signStatus'];//签约状态	0未签、1已签、2拒签  3签约成功
        $timestamp=$_REQUEST['timestamp'];
        $sign=$_REQUEST['sign'];
//        file_put_contents('ht_1.txt',$signStatus);
        $bodyParams=array(
            'applyNo'=>$applyNo,
            'identityType'=>$identityType,
            'fullName'=>$fullName,
            'identityCard'=>$identityCard,
            'optTime'=>$optTime,
            'signStatus'=>$signStatus
        );
        try {
            $this->sign_obj->httpSignUtils($bodyParams, $timestamp,$sign);
        } catch (\Exception $e) {
            $result['resultCode']="signError";
            $result['msg']=$e->getMessage();
            $result['success']=false;
        }
        if($result['success']){
            //TODO 做自个的业务相关处理
            /**通过合同的编号，改变商家的签约状态*/
            $w['apply_no'] = $applyNo;
            $shop_id = M("Shop")->where($w)->limit(1)->getField("shop_id");
            unset($data);
            $data['sign_status'] = 1;
            $data['q_start_time'] = time();
            $data['q_end_time'] = strtotime("+1 year");
            M("Shop")->where(array('shop_id'=>$shop_id))->limit(1)->save($data);
            echo '{"success":true}';
        }
        echo($this->sign_obj->ropUtils($result));
    }


    /**用户更新信息*/
    public function editShopInfo(){
        $w['shop_id'] = I('post.shop_id');
        $data = array(
            'legal_person' => $_POST['legal_person']?$_POST['legal_person']:"",
            'id_number' => $_POST['id_number']?$_POST['id_number']:"",
            'email' => $_POST['email']?$_POST['email']:"",
            'utime' =>time()
        );
        $res = M("Shop")->where($w)->limit(1)->save($data);
        if($res){
            apiResponse("success",'更新成功！',$w);
        }else{
            apiResponse("error",'更新失败！');
        }
    }

    /**查看合同的详情*/
    public function detailLink($apply_no){
        $res = $this->sign_obj->linkDetail($apply_no);
        $arr = json_decode($res,true);
        if($arr['success']){
            return $arr['link'];
        }else{
            return null;
        }
    }

}