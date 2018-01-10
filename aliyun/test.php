<?php
date_default_timezone_set("GMT");
session_start();
//绑定 ip 到域名
$domainRecords = Ali::Obj()->DescribeDomainRecords();
$domainRecords = json_decode($domainRecords, true);
$record = $domainRecords['DomainRecords']['Record'];
foreach($record as $k=>$r){
    if($r['RR'] == 'bizlivechat' || $r['RR'] == 'biz'){
        Ali::Obj()->UpdateDomainRecord($r['RecordId'],$r['RR']);
    }
}


class Ali
{
    private $accessKeyId  = "LTAI6lgb9p2GWWGH";
    private $accessSecrec = "9oytb9lUkyYwHyd3ASl1kLtp9Y0nJN";
    private static $obj  = null;
    public static function Obj ()
    {
        if(is_null(self::$obj))
        {
            self::$obj = new self();
        }
        return self::$obj;
    }

    public function DescribeDomainRecords()
    {
        $requestParams = array(
            "Action"    =>  "DescribeDomainRecords",
            "DomainName"    =>  "ulync.cn"
        );
        $val =  $this->requestAli($requestParams);
        //$this->outPut($val);
        return $val;
    }

    /**
     * 更新 ip
     */
    public function UpdateDomainRecord($recordid,$rr,$type = "A")
    {
        $ip = $this->getClientIP();
        $ip = 'dns1.dns123.net';
        $type = 'NS';

            $requestParams = array(
                "Action"        =>  "UpdateDomainRecord",
                "RecordId"      =>  $recordid,
                "RR"            =>  $rr,
                "Type"          =>  $type,
                "Value"         =>  $ip,
            );
            $val =  $this->requestAli($requestParams);
            $this->outPut($val."  ".$ip);


    }

    private function requestAli($requestParams)
    {
        $publicParams = array(
            "Format"        =>  "JSON",
            "Version"       =>  "2015-01-09",
            "AccessKeyId"   =>  $this->accessKeyId,
            "Timestamp"     =>  date("Y-m-d\TH:i:s\Z"),
            "SignatureMethod"   =>  "HMAC-SHA1",
            "SignatureVersion"  =>  "1.0",
            "SignatureNonce"    =>  substr(md5(rand(1,99999999)),rand(1,9),14),
        );

        $params = array_merge($publicParams, $requestParams);
        $params['Signature'] =  $this->sign($params, $this->accessSecrec);
        $uri = http_build_query($params);
        $url = 'http://alidns.aliyuncs.com/?'.$uri;
        return $this->curl($url);
    }


    private function ip()
    {
        $ip = $this->curl("http://httpbin.org/ip");
        $ip = json_decode($ip,true);
        return $ip['origin'];
    }

    private function getClientIP() {
        static $ip = NULL;
        if ( $ip !== NULL )
        return $ip;
        if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $arr = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $pos = array_search( 'unknown', $arr );
            if ( false !== $pos )
            unset( $arr[$pos] );
            $ip = trim( $arr[0] );
        } elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $ip = ( false !== ip2long( $ip ) ) ? $ip : '';
        return $ip;
    }

    private function sign($params, $accessSecrec, $method="GET")
    {
        ksort($params);
        $stringToSign = strtoupper($method).'&'.$this->percentEncode('/').'&';

        $tmp = "";
        foreach($params as $key=>$val){
            $tmp .= '&'.$this->percentEncode($key).'='.$this->percentEncode($val);
        }
        $tmp = trim($tmp, '&');
        $stringToSign = $stringToSign.$this->percentEncode($tmp);

        $key  = $accessSecrec.'&';
        $hmac = hash_hmac("sha1", $stringToSign, $key, true);

        return base64_encode($hmac);
    }


    private function percentEncode($value=null)
    {
        $en = urlencode($value);
        $en = str_replace("+", "%20", $en);
        $en = str_replace("*", "%2A", $en);
        $en = str_replace("%7E", "~", $en);
        return $en;
    }

    private function curl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        $result=curl_exec ($ch);
        return $result;
    }

    private function outPut($msg)
    {
        echo date("Y-m-d H:i:s")."  ".$msg.PHP_EOL;
    }
}