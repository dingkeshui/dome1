<?php
/**
 * 支付宝配置参数
 */
return array(
    'ALIPAY_CONFIG' => array(
        //合作身份者id，以2088开头的16位纯数字，卖家成功申请支付宝接口后获取到的PID；
        'PARTNER'              => '2088911701471438',

        //安全检验码，以数字和字母组成的32位字符，卖家成功申请支付宝接口后获取到的Key
        'KEY'                  => '7oplwpoz93cn5fs91hxowah7m4x9zg3m',

        //签名方式 不需修改 MD5加密
        'SIGN_TYPE'            => strtoupper('MD5'),

        //字符编码格式 目前支持 gbk或utf-8
        'INPUT_CHARSET'        => strtolower('utf-8'),

        //ca证书路径地址，用于curl中ssl校验 请保证cacert.pem文件在当前文件夹目录中
        'CACERT'               => getcwd().'\\cacert.pem',

        //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        'TRANSPORT'            => 'http',

        //卖家支付宝账号
        'SELLER_EMAIL'         =>'txunda@163.com',

        //这里是异步通知页面url
        'NOTIFY_URL'           =>'http://'.$_SERVER['HTTP_HOST'].'/index.php/Alipay/notifyUrl',

        //这里是页面跳转通知url
        'RETURN_URL'           =>'http://'.$_SERVER['HTTP_HOST'].'/index.php/Alipay/returnUrl',

        //支付成功跳转到的页面
        'SUCCESS_PAGE'         =>'http://'.$_SERVER['HTTP_HOST'].'/index.php/Alipay/paySuccess',

        //支付失败跳转到的页面
        'ERROR_PAGE'           =>'http://'.$_SERVER['HTTP_HOST'].'/index.php/Alipay/payError',
    )
);
