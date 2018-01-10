<?php
define("CURL_TIMEOUT",   10);
define("URL",            "http://api.fanyi.baidu.com/api/trans/vip/translate");

class erLhcoreClassTranslateBaidu {

    //翻译入口
    public static function translateBaidu($apiKey, $apiSecret, $query, $from = 'auto', $to)
    {
        $args = array(
            'q' => $query,
            'appid' => $apiKey,
            'salt' => rand(10000,99999),
            'from' => $from,
            'to' => $to,

        );
        $args['sign'] = self::buildSign($query, $apiKey, $args['salt'], $apiSecret);
        $ret = self::call(URL, $args);
        $ret = json_decode($ret, true);
        return $ret;
    }

    //加密
    public static function buildSign($query, $appID, $salt, $secKey)
    {/*{{{*/
        $str = $appID . $query . $salt . $secKey;
        $ret = md5($str);
        return $ret;
    }/*}}}*/

    //发起网络请求
    public static function call($url, $args=null, $method="post", $testflag = 0, $timeout = CURL_TIMEOUT, $headers=array())
    {/*{{{*/
        $ret = false;
        $i = 0;
        while($ret === false)
        {
            if($i > 1)
                break;
            if($i > 0)
            {
                sleep(1);
            }
            $ret = self::callOnce($url, $args, $method, false, $timeout, $headers);
            $i++;
        }
        return $ret;
    }/*}}}*/

    public static function callOnce($url, $args=null, $method="post", $withCookie = false, $timeout = CURL_TIMEOUT, $headers=array())
    {/*{{{*/
        $ch = curl_init();
        if($method == "post")
        {
            $data = self::convert($args);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        else
        {
            $data = self::convert($args);
            if($data)
            {
                if(stripos($url, "?") > 0)
                {
                    $url .= "&$data";
                }
                else
                {
                    $url .= "?$data";
                }
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if(!empty($headers))
        {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if($withCookie)
        {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $_COOKIE);
        }
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }/*}}}*/

    public static function convert(&$args)
    {
        $data = '';
        if (is_array($args))
        {
            foreach ($args as $key=>$val)
            {
                if (is_array($val))
                {
                    foreach ($val as $k=>$v)
                    {
                        $data .= $key.'['.$k.']='.rawurlencode($v).'&';
                    }
                }
                else
                {
                    $data .="$key=".rawurlencode($val)."&";
                }
            }
            return trim($data, "&");
        }
        return $args;
    }

    public static function translate($apiKey,$apiSecret, $word, $from, $to)
    {
        $data = self::translateBaidu($apiKey, $apiSecret, $word, 'auto', $to);

        if (isset($data['trans_result'][0]['dst'])){
            $errors = array();
            erLhcoreClassChatEventDispatcher::getInstance()->dispatch('translate.after_google_translate', array('word' => & $word, 'errors' => & $errors));
            if(!empty($errors)) {
                throw new Exception(erTranslationClassLhTranslation::getInstance()->getTranslation('chat/translation','Could not translate').' - '.implode('; ', $errors));
            }

            return htmlspecialchars_decode($data['trans_result'][0]['dst']);
        };

        throw new Exception(erTranslationClassLhTranslation::getInstance()->getTranslation('chat/translation','Could not translate').' - '.$rsp);
    }
}
?>