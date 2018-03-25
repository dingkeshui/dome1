<?php
/**
 * User: huhu
 * DateTime: 2017-06-06 0006 14:27
 */
namespace com\jzq\api\model\ocr;
use org\ebq\api\model\RichServiceRequest;

/**
 * 身份证OCR识别
 * Class IdentityOCRRequest
 * @package com\jzq\api\model\bank
 */
class IdentityOCRRequest extends RichServiceRequest{

    static $v="1.0";
    static $method="ocr.identity";
    /**
     * 待识别的身份证
     */
    public $file;
    /**
     * 是否压缩图片。如果不压缩图片，可能会影响识别效率。默认0(压缩),1不压缩
     * 如果图片压缩后不能正确的识别银行卡信息，请把这个值设置为1再试。
     */
    public $isCompress;

    function validate(){
        assert(!is_null($this->file),"待识别的身份证不能为空");
        assert(is_a($this->file,'org\ebq\api\model\bean\UploadFile'),"file isn't a UploadFile value");
        $this->isCompress = static::trim($this->isCompress);
        if($this->isCompress==''){
            $this->isCompress="0";
        }
        return parent::validate();
    }

    /**
     * 不签名的filed
     */
    function getIgnoreSign(){
        $ignoreSign=array('file');
        $parr=parent::getIgnoreSign();
        if(is_array($parr)){
            $ignoreSign=array_merge($ignoreSign,$parr);
        }
        return $ignoreSign;
    }
}