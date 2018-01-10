<?php
function login($url, $post)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 1);//是否显示头信息
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_POST, 1);//post方式提交
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));//要提交的信息
    $result = curl_exec($curl);
    setcookie('PHPSESSID','',time()-31*24*3600);
    setcookie('PHPSESSID','',time()-31*24*3600,'/','livechat.ulyncbiz.com');
    if ( isset($_COOKIE['lhc_rm_u']) ) {
        unset($_COOKIE['lhc_rm_u']);
        setcookie('lhc_rm_u','',time()-31*24*3600,'/');
    };
    echo $result;
}

//设置post的数据
$post = array(
    'token' => $_GET['token']
);
file_put_contents("logoutlogout.txt", "-----addUser-first-------------".var_export($post,true), FILE_APPEND);
$url = "http://livechat.ulyncbiz.com/index.php/restapi/logout";
login($url, $post);
Header('location:'.$_GET['reback']);

