<?php
namespace com\jzq\api\model\account;
use org\ebq\api\model\RichServiceRequest;
use com\jzq\api\model\menu\OrganizationType;
use org\ebq\api\model\bean\UploadFile;
use RuntimeException;
/**
 * 账户创建相关
 * @edit yfx 2017-06-02
 */
class OrganizationCreateRequest extends RichServiceRequest{

	static $v="1.0";
	static $method="organization.create";

	/**传统的多证方式*/
	static $IDENTIFICATION_TYPE_TRADITIONAL = 0;
	/**多证合一*/
	static $IDENTIFICATION_TYPE_ALLINONE = 1;
	
	/**
	 * 邮箱或手机号|必填
	 */
	public $emailOrMobile;
	
	/**
	 * 公司名称|必填
	 */
	public $name;
	
	/**
	 * 公司类型{@link OrganizationType}
	 */
	public $organizationType;
	
	/**
	 * 证件类型：多证0 和多证合一1
	 */
	public $identificationType;
	
	/**
	 * 组织注册编号，营业执照号或事业单位事证号或统一社会信用代码
	 */
	public $organizationRegNo;
	
	/**
	 * 组织注册证件扫描件，营业执照或事业单位法人证书|{@link UploadFile}
	 */
	public $organizationRegImg;
	
	/**
	 * 组织结构代码
	 */
	public $organizationCode;
	
	/**
	 * 组织结构代码扫描件|{@link UploadFile}
	 */
	public $organizationCodeImg;
	
	/**
	 * 税务登记扫描件,事业单位选填，普通企业必选|{@link UploadFile}
	 */
	public $taxCertificateImg;
	
	/**
	 * 签约申请书扫描图{@link UploadFile}
	 */
	public $signApplication;
	
	/**
	 * 法人身份证号
	 */
	public $legalIdentityCard;
	
	/**
	 * 法人姓名
	 */
	public $legalName;
	
	/**
	 * 法人身份证正面|{@link UploadFile}
	 */
	public $legalIdentityFrontImg;
	
	/**
	 * 法人身份证反面|{@link UploadFile}
	 */
	public $legalIdentityBackImg;


    /**
     * 法人手机号
     */
    public $legalMobile;

    /**
     * 公章图片
     */
    public $signImg;


    function validate(){

		$this->emailOrMobile=self::trim($this->emailOrMobile);
		$this->name=self::trim($this->name);
		$this->organizationType=self::trim($this->organizationType);
		$this->identificationType=self::trim($this->identificationType);
		$this->organizationRegNo=self::trim($this->organizationRegNo);
		$this->organizationCode=self::trim($this->organizationCode);
		//if($this->emailOrMobile==''){
		//	throw new RuntimeException("emailOrMobile is null");
		//}
		if($this->name==''){
			throw new RuntimeException("name is null");
		}
		if($this->organizationType==''||($this->organizationType!=OrganizationType::$ENTERPRISE&&$this->organizationType!=OrganizationType::$PUBLIC_INSTITUTION)){
			throw new RuntimeException("organizationType is null or not a OrganizationType value");
		}
		if($this->identificationType==''||($this->identificationType!="0"&&$this->identificationType!="1")){
			throw new RuntimeException("identificationType is null or not a OrganizationType value");
		}
		if($this->organizationRegNo==''){
			throw new RuntimeException("organizationRegNo is null ");
		}
		if($this->organizationRegImg!=null&&!is_a($this->organizationRegImg, 'org\ebq\api\model\bean\UploadFile')){
			throw new RuntimeException("organizationRegImg is not a UploadFile value");
		}
		if($this->organizationCodeImg!=null&&!is_a($this->organizationCodeImg, 'org\ebq\api\model\bean\UploadFile')){
			throw new RuntimeException("organizationCodeImg is not a UploadFile value");
		}
		if($this->taxCertificateImg!=null&&!is_a($this->taxCertificateImg, 'org\ebq\api\model\bean\UploadFile')){
			throw new RuntimeException("taxCertificateImg is not a UploadFile value");
		}
		if($this->signApplication!=null&&!is_a($this->signApplication, 'org\ebq\api\model\bean\UploadFile')){
			throw new RuntimeException("signApplication is not a UploadFile value");
		}
        if($this->signImg!=null&&!is_a($this->signImg, 'org\ebq\api\model\bean\UploadFile')){
            throw new RuntimeException("signImg is not a UploadFile value");
        }
		
		//必填项校验
		if($this->identificationType=="0"){//多证
			if($this->organizationCode==''){
				throw new RuntimeException("organizationCode is null ");
			}
			if($this->organizationRegImg==null){
				throw new RuntimeException("organizationRegImg is null ");
			}
			if($this->organizationType==OrganizationType::$ENTERPRISE){
				if($this->taxCertificateImg==null){
					throw new RuntimeException("taxCertificateImg is null ");
				}
			}
		}
		
		$this->legalIdentityCard=self::trim($this->legalIdentityCard);
		$this->legalName=self::trim($this->legalName);
		if($this->legalIdentityFrontImg!=null&&!is_a($this->legalIdentityFrontImg, 'org\ebq\api\model\bean\UploadFile')){
			throw new RuntimeException("legalIdentityFrontImg is not a UploadFile value");
		}
		if($this->legalIdentityBackImg!=null&&!is_a($this->legalIdentityBackImg, 'org\ebq\api\model\bean\UploadFile')){
			throw new RuntimeException("legalIdentityBackImg is not a UploadFile value");
		}
		return parent::validate();
	}

	
	/**
	 * 不签名的filed
	 */
	function getIgnoreSign(){
		//确认图片信息不做签名
		$ignoreSign=array('organizationRegImg','organizationCodeImg','taxCertificateImg','signApplication','legalIdentityFrontImg','legalIdentityBackImg',"signImg");
		return $ignoreSign;
	}
}
?>