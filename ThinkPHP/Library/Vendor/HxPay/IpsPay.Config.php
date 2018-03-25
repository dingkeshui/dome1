<?php
class IpsPayConfig{
    public function __construct()
    {

    }
    public function getValue(){
        $ipspay_config['Version']	 = 'v1.0.0';
        //商戶號
        $ipspay_config['MerCode']	 = '202195';
        //交易賬戶號
        $ipspay_config['Account']	 = '2021950015';
        //商戶證書
        $ipspay_config['MerCert']	 = 'DBah0MNAw76eoXE0bK0LQwdvYXt7bUEJ6W4wBuuV2QlAAf3WTLM1ufCObsWphYvxAKSq0yNKb1f6BgqTmFRHegr2qeAByKBsEuz93ZN8RkkSmFHALzeoccga6XylNQZe';
        //請求地址
        $ipspay_config['PostUrl']	 = 'https://thumbpay.e-years.com/psfp-webscan/onlinePay.do';
        //服务器S2S通知页面路径
        $ipspay_config['S2Snotify_url'] = "https://www.zxty.me";
        //页面跳转同步通知页面路径
        $ipspay_config['return_url'] = "https://www.zxty.me";
        $ipspay_config['MsgId'] = "";
        return $ipspay_config;
    }
}

