<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');

try
{
    erLhcoreClassRestAPIHandler::validateRequest();

    // init data
    $user_id        = isset($_GET['user_id'])? intval($_GET['user_id']) : 0;
    $username    = isset($_GET['username'])? trim($_GET['username']) : '';
    $email          = isset($_GET['email'])? trim($_GET['email']) : '';
    $password    = isset($_GET['password'])? trim($_GET['password']): '';

    // init param, check what is supplied
    $param          = ($username != '')? array('username' => $username) : array('email' => '00'); // dummy email value to ensure 0 res
    $param          = ($email != '')? array('email' => $email) : $param;

    // init user
    $user = ($user_id > 0)? erLhcoreClassModelUser::fetch($user_id) : erLhcoreClassModelUser::findOne(array('filter' => $param));

    $userGroups = erLhcoreClassModelGroupUser::getList(array('filter' => array('user_id' => $user->id)));

    if (!empty($userGroups)) {
        foreach ($userGroups as $userGroup) {
            $usergroupid = $userGroup->group_id;
        }
    }

    if($usergroupid){
        $roles = erLhcoreClassGroupRole::getGroupRoles($usergroupid);
        foreach($roles as $role){
            $roleid = $role['roleid'];
        }
        //erLhcoreClassRoleFunction::getRoleFunctions();
    }
    $siteurl = 'http://livechat.wisehub.cn';
    $accessArray = erLhcoreClassRole::accessArrayByUserID( $user->id );

    $useQuestionary = $accessArray['lhquestionary']['manage_questionary'];
    if($useQuestionary || $roleid == 1){
        $menu['module'][] = array('name'=>'问卷','url'=>$siteurl.erLhcoreClassDesign::baseurl('site_admin/questionary/list'));
    }
    $useFaq = $accessArray['lhfaq']['manage_faq'];
    if($useFaq || $roleid == 1){
        $menu['module'][] = array('name'=> '问答','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/faq/list'));
    }
    $useChatbox = $accessArray['lhchatbox']['manage_chatbox'];
    if($useChatbox || $roleid == 1){
        $menu['module'][] = array('name'=> '聊天','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/chatbox/configuration'));
    }
    $useBo = $accessArray['lhbrowseoffer']['manage_bo'];
    if($useBo || $roleid == 1){
        $menu['module'][] = array('name'=> '浏览','url'=>$siteurl.erLhcoreClassDesign::baseurl('site_admin/browseoffer/index'));
    }
    $useFm = $accessArray['lhform']['manage_fm'];
    if($useFm || $roleid == 1){
        $menu['module'][] = array('name'=> '表单','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/form/index'));
    }

    $hasExtensionModule = $accessArray['lhlhcxmpp']['configure'];
    if($hasExtensionModule || $roleid == 1){
        $menu['extensionmodule'][] = array('name'=> 'xp','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/xmppservice/index'));
    }

    $hasExtensionModule = $accessArray['lhsugarcrm']['configure'];
    if($hasExtensionModule || $roleid == 1){
        $menu['extensionmodule'][] = array('name'=> 'sg','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/sugarcrm/index'));
    }

    $ischat = $accessArray['lhchat']['allowchattabs'];
    if($ischat || $roleid == 1){
        $menu['chat'][] = array('name' => '聊天','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/chat/chattabs'));
        $menu['chat'][] = array('name' => '会话','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/chat/list'));
        $menu['chat'][] = array('name' => '在线','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/chat/onlineusers'));
        $menu['chat'][] = array('name' => '配置','url'=> $siteurl.erLhcoreClassDesign::baseurl('site_admin/system/configuration'));
    }


    // check we have data
    if (! ($user instanceof erLhcoreClassModelUser))
    {
        throw new Exception('User could not be found!');
    }

    // check if password is given, if so, validate password
    if($password != '')
    {
        // check password encryption type
        if (strlen($user->password) == 40)
        {
            // get password hash
            $cfgSite = erConfigClassLhConfig::getInstance();
            $secretHash = $cfgSite->getSetting( 'site', 'secrethash' );

            $pass_hash   = sha1($password.$secretHash.sha1($password));

            $verified       = ($user->password == $pass_hash)? 1 : 0;
        }
        else
        {
            $verified = (password_verify($password, $user->password))? 1 : 0;
        }

        // set new property to user object
        $user->pass_verified = $verified;
    } // end of if($password != '')

    // loose password
    unset($user->password);

    erLhcoreClassRestAPIHandler::outputResponse(array('error' => false, 'result' => $menu));

} catch (Exception $e) {
    echo erLhcoreClassRestAPIHandler::outputResponse(array(
        'error' => true,
        'result' => $e->getMessage()
    ));
}

exit();
