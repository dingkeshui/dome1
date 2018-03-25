<?php
/**
 * User: huhu
 * DateTime: 2017/8/31 14:55
 */

namespace com\jzq\api\model\sign;


use com\jzq\api\model\menu\DealType;
use RuntimeException;

/**
 * hash只保全
 * Class ApplySignHashRequest
 * @package com\jzq\api\model\sign
 */
class ApplySignHashRequest extends ApplySignAbstractRequest{
    static $v="1.0";
    static $method="sign.apply.hash";
    /***
     * hashValue 取文件的sha512
     */
    public $hashValue;


    function validate(){
        if($this->hashValue==null||!is_string($this->hashValue)){
            throw new RuntimeException("hashValue is null or not a string value");
        }
        if(strlen($this->hashValue)!=128){
            throw new RuntimeException("hashValue is null or not a string sha512 value");
        }
        $this->dealType=DealType::$ONLY_HASH_PRES;
        return parent::validate();
    }

}