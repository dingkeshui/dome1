<?php
namespace com\jzq\api\model\pres;
use com\jzq\api\model\bean\Signatory;
use org\ebq\api\model\RichServiceRequest;
use RuntimeException;
/**
 * 请求得到签约保全的文件下载地址
 * @edit yfx 2016-09-02
 */
class PresFileLinkRequest extends RichServiceRequest{

	static $v="1.0";
	static $method="pres.link.file";

	/**
	 * 发起签约的申请编号,由发起签约接口返回
	 */
	public $applyNo;
	
	/**
	 * 签约人，必须是发起签约接口传入签约人中，姓名、身份证件一致的签约人
	 * 手机号码和地址可以不一样
	 */
    /**
     * @var Signatory
     */
	public $signatory;
	
	function validate(){
		$this->applyNo=self::trim($this->applyNo);
		if($this->applyNo==''){
			throw new RuntimeException("applyNo is null");
		}
		if($this->signatory==null||!is_a($this->signatory,'com\jzq\api\model\bean\Signatory')){
			throw new RuntimeException("signatory is null or not a Signatory value");
		}
		if(!$this->signatory->validate()){
			return false;
		}
		$this->signatory=$this->signatory->toJson();
		return parent::validate();
	}
}
?>