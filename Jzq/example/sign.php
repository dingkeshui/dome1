<?php
namespace jzq\test;
/**
 * User: huhu
 * DateTime: 2017-06-12 0012 17:34
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/clientInfo.php';
use com\jzq\api\model\bean\Signatory;
use com\jzq\api\model\menu\AuthLevel;
use com\jzq\api\model\menu\DealType;
use com\jzq\api\model\menu\IdentityType;
use com\jzq\api\model\menu\SignLevel;
use com\jzq\api\model\sign\ApplySignFileRequest;
use com\jzq\api\model\sign\ApplySignTmplRequest;
use com\jzq\api\model\sign\DetailAnonyLinkRequest;
use com\jzq\api\model\sign\SignLinkRequest;
use org\ebq\api\model\PingRequest;
use org\ebq\api\tool\HttpSignUtils;
use org\ebq\api\tool\RopUtils;
class sign
{

    public function __construct()
    {
    }

    /**
     * 测试是否能够连接上服务器
     * @author crazy
     * @time 2017-11-28
     */
    public function ping(){
        //组建请求参数
        $requestObj=new PingRequest();
        //请求
        $response=RopUtils::doPostByObj($requestObj,ClientInfo::$app_key,ClientInfo::$app_secret,ClientInfo::$services_url);
        //以下为返回的一些处理
        $responseJson=json_decode($response);
        print_r("response:".$response."</br>");
        print_r("format:</br>");
        var_dump($responseJson); //null
        if($responseJson->success){
            echo $requestObj->getMethod()."->处理成功";
        }else{
            echo $requestObj->getMethod()."->处理失败";
        }
    }


    /**
     * 获取签约的applyNO
     * @author crazy
     * @time 2017-11-28
     * @param
     * name 乙方的姓名
     * address 地址
     * moblie 联系电话
     * email 邮箱地址
     * legal_person 企业名称
     * identityCard 身份证号
     */
    public function getApply($name,$address,$moblie,$email,$legal_person,$identityCard){
        //实例对象 合同相关的设置
        $requestObj=new ApplySignTmplRequest();
        //* 合同名称
        $requestObj->contractName=date("YmdHis");
        //* 模板编号
        $requestObj->templateNo="zxtyrz2017001";
        $requestObj->signatory=2;
        $requestObj->authLevel=[AuthLevel::$BANKTHREE];
        $requestObj->dealType=DealType::$AUTH_SIGN_PART;
        /**使用云证书*/
        $requestObj->serverCa=1;
        /**模板参数设置*/
        $start_time=date("Y年m月d日",time());
        $end_time=date("Y年m月d日",strtotime("+1 year"));
        $requestObj->contractParams=array(
            "contractAmount"=> 15,
            "start_time"    =>$start_time,
            "ht_num"        =>'zxty'.date("YmdHis").rand(1000,9999),
            "end_time"      =>$end_time,
            "signatory"     =>$name,
            "address"       =>$address,
            "moblie"        =>$moblie,
            "email"         =>$email,
            "sign_j_time"   =>$start_time,
            "sign_y_time"   =>$start_time
        );
        /**签约方1*/
        //签合同方
        $signatories=array();
        $signatory=new Signatory();
        /**证件类型*/
        $signatory->setSignatoryIdentityType(IdentityType::$IDCARD);
        $signatory->fullName=$legal_person;
        $signatory->identityCard=$identityCard;
        $signatory->mobile=$moblie;
        $signatory->email=$email;
        $signatory->signLevel = SignLevel::$SEAL;
        $signatory->orderNum=1;
        $signatory->ServerCaAuto=0;; //0、手动签约,；1、自
        $signatory->readTime=3; //强制阅读时间30秒
        $signatory->setChapteJson(array(array(
                'page'=>2,
                'chaptes'=>array(
                    array("offsetX"=>0.41,"offsetY"=>0.28)
                )
            )
            )
        );
        array_push($signatories, $signatory);
        //$signatory->dealType=DealType::$AUTH_SIGN_PART;

        /**签约方2*/
        $signatory2=new Signatory();
        $signatory2->setSignatoryIdentityType(IdentityType::$BIZLIC);
//        $signatory2->setSignatoryIdentityType(IdentityType::$IDCARD);
        $signatory2->orderNum=2;
        $signatory2->ServerCaAuto=1;; //0、手动签约,；1、自
        $signatory2->orderNum=2;
        $signatory2->fullName="众享通赢（天津）网络科技有限公司";
        $signatory2->identityCard="91120223MA05QPUQ5Q";
        $signatory2->email="jgts@zxty.me";
        $signatory2->setChapteJson(array(array(
                'page'=>2,
                'chaptes'=>array(
                    array("offsetX"=>0.11,"offsetY"=>0.28)
                    )
                )
            )
        );
        array_push($signatories, $signatory2);


        $requestObj->signatories=$signatories;
        $requestObj->orderFlag=1;

        $response=RopUtils::doPostByObj($requestObj,ClientInfo::$app_key,ClientInfo::$app_secret,ClientInfo::$services_url);
        //以下为返回的一些处理
       return json_decode($response,true);


        print_r("response:".$response."</br>");
        print_r("format:</br>");
////
//        if($responseJson->success){
//            echo $requestObj->getMethod()."->处理成功";
//        }else{
//            echo $requestObj->getMethod()."->处理失败";
//        }

    }


    /**
     * 生成签约地址
     * @author crazy
     * @time 2017-11-28
     * @param
     * name 乙方的姓名
     * address 地址
     * moblie 联系电话
     * email 邮箱地址
     * company_name 企业名称
     * identityCard 营业执照
     */

    public function sign($applyNo,$name,$email,$identityCard){
        $requestObj = new SignLinkRequest();
        $requestObj->applyNo = $applyNo;
//
        //签合同方
        //测试时请改为自己的个人信息进行测试（姓名、身份证号、手机号不能部分或全部隐藏）
        $signatory=new Signatory();
        $signatory->setSignatoryIdentityType(IdentityType::$IDCARD);
        $signatory->fullName=$name;
        $signatory->email=$email;
        $signatory->identityCard =$identityCard;
        $requestObj->signatory = $signatory;

        //请求
        $response=RopUtils::doPostByObj($requestObj,ClientInfo::$app_key,ClientInfo::$app_secret,ClientInfo::$services_url);
        //以下为返回的一些处理
        return json_decode($response,true);
//        $responseJson=json_decode($response);
//        print_r("response:".$response."</br>");
//        print_r("format:</br>");
//        var_dump($responseJson); //null
//        if($responseJson->success){
//            echo $requestObj->getMethod()."->处理成功";
//        }else{
//            echo $requestObj->getMethod()."->处理失败";
//        }
    }

    /**整理给君子签返回的数据*/
    public function ropUtils($result){
        RopUtils::json_encode($result);
    }

    /**验签*/
    public function httpSignUtils($bodyParams,$timestamp,$sign){
        HttpSignUtils::checkHttpSign($bodyParams,$timestamp,ClientInfo::$app_key, ClientInfo::$app_secret,$sign);
    }



    /**获取合同的详情*/
    public function linkDetail($applyNo){
        $requestObj = new DetailAnonyLinkRequest();
        $requestObj->applyNo = $applyNo;
        $response =RopUtils::doPostByObj($requestObj,ClientInfo::$app_key,ClientInfo::$app_secret,ClientInfo::$services_url);
        return $response;
    }



}