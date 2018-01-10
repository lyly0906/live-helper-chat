<?php
class RandChar{

    function getRandChar($length){
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol)-1;

        for($i=0;$i<$length;$i++){
            $str.=$strPol[rand(0,$max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }

        return $str;
    }
}

function login($url, $post)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 1);//是否显示头信息
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_COOKIE,1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_POST, 1);//post方式提交
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));//要提交的信息
    $result = curl_exec($curl);

    if($result){
        preg_match('/(?=PHPSESSID).*?(?=;)/',$result,$m);
        $cookie = trim(str_replace('PHPSESSID=','',$m[0]));
        setcookie('PHPSESSID','',time()-31*24*3600);
        setcookie('PHPSESSID',$cookie,time()+31*24*3600,'/','bizlivechat.ulync.cn');
    }

    curl_close($curl);


}
$randCharObj = new RandChar();
//设置post的数据
$post = array(
    'username' => $_GET['outuser'],
    'password' => $_GET['outpwd'],
    'generate_token' => 'true',
    'device' => 'unknown',
    'device_token' => $randCharObj->getRandChar(32),
);;


$url = "http://bizlivechat.ulync.cn/index.php/restapi/login";
$url2 = "http://bizlivechat.ulync.cn/index.php/site_admin/user/account";
if($_GET['reback']){
    $url2 = $_GET['reback'];
}
login($url, $post);
Header('location:'.$url2);
//删除cookie文件
//@ unlink($cookie);
//echo $content;
?>