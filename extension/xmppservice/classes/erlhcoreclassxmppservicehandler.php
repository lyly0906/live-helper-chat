<?php
define('BASE_PATH',str_replace('\\','/',realpath(dirname(dirname(dirname(dirname(__FILE__)))).'/'))."/");
date_default_timezone_set("PRC");

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Fabiang\Xmpp\Options;
use Fabiang\Xmpp\Client;

use Fabiang\Xmpp\Protocol\Roster;
use Fabiang\Xmpp\Protocol\Presence;
use Fabiang\Xmpp\Protocol\Message;
use Fabiang\Xmpp\Protocol\GetRoom;
use Fabiang\Xmpp\Protocol\Chatroom;

class erLhcoreClassExtensionXmppserviceHandler
{

    /**
     * $userPlain remdex2@xmpp.livehelperchat.com/16103000591431105595929303
     *
     * @param string $userPlain            
     */
    public static function parseXMPPUser($userPlain)
    {
        $parts = explode('/', $userPlain);
        
        list ($user, $server) = explode('@', $parts[0]);
        
        if ($user != '' && $server != '') {
            return array(
                'user' => $user,
                'server' => $server,
                'xmppuser' => $parts[0]
            );
        } else {
            throw new Exception('Could not parse user - ' . $userPlain);
        }
    }

    /**
     * Deletes old XMPP accounts based on lactivity field value.
     *
     * Deletes only chat and visitors accounts, NOT operators.
     * Old account is considered if from last login has passed more than 1 day
     *
     * Are called in these methods
     *
     * @see erLhcoreClassExtensionXmppserviceHandler::newChat()
     * @see erLhcoreClassExtensionXmppserviceHandler::newOnlineVisitor()
     */
    public static function cleanupOldXMPPAccounts()
    {
        $oldAccounts = erLhcoreClassModelXMPPAccount::getList(array(
            'filterin' => array(
                'type' => array(
                    erLhcoreClassModelXMPPAccount::USER_TYPE_CHAT,
                    erLhcoreClassModelXMPPAccount::USER_TYPE_VISITOR
                )
            ),
            'filterlt' => array(
                'lactivity' => time() - (24 * 3600)
            )
        ));
        foreach ($oldAccounts as $xmppAccount) {
            $xmppAccount->removeThis();
        }
    }

    /**
     * Sends request as JSON content and returns response in plain text
     *
     * @param
     *            url to request
     *            
     * @param array $data
     *            which later is encoded in json_encode
     *            
     * @param bool $asJson            
     *
     *
     */
    public static function sendRequest($url, $data, $asJson = true)
    {
        // Append secret key
        $data['secret_key'] = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['secret_key'];
        
        $data_string = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ));
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            throw new Exception('Empty response');
        }
        
        if ($asJson == true) {
            
            $jsonObject = json_decode($response, true);
            
            if ($jsonObject === false) {
                throw new Exception('Could not decode JSON, response - ' . $response);
            }
            
            if ($jsonObject === null) {
                throw new Exception('Could not decode JSON, response - ' . $response);
            }
            
            return $jsonObject;
        }
        
        return $response;
    }

    /**
     * Updates last activity of LHC internal user
     *
     * @param int $userId            
     *
     * @param string $lastActivity            
     */
    public static function updateActivityByUserId($userId, $lastActivity = false)
    {
        if ($lastActivity === false) {
            $lastActivity = time();
        }
        
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('UPDATE lh_userdep SET last_activity = :last_activity WHERE user_id = :user_id');
        $stmt->bindValue(':last_activity', $lastActivity, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Update last activity field value in XMPP accounts table
        if ($lastActivity !== false && $lastActivity > 0) {
            $stmt = $db->prepare('UPDATE lhc_xmpp_service_account SET lactivity = :lactivity WHERE user_id = :user_id');
            $stmt->bindValue(':lactivity', $lastActivity, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    /**
     * Updates XMPP account activity by account ID
     */
    public static function updateActivityByXMPPAccountId($id)
    {
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('UPDATE lhc_xmpp_service_account SET lactivity = :lactivity WHERE id = :id');
        $stmt->bindValue(':lactivity', time(), PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     *
     * @param
     *            XMPP username
     *            
     * @return int related lhc user id
     *        
     */
    public static function getUserIDByXMPPUsername($xmppUsername)
    {
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('SELECT user_id FROM lhc_xmpp_service_account WHERE username = :username');
        $stmt->bindValue(':username', $xmppUsername, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_COLUMN);
    }

    /**
     * delete xmpp user operator/visitor/chat based
     *
     * @param unknown $params            
     */
    public static function deleteXMPPUser($params)
    {
        $xmppAccount = $params['xmpp_account'];
        
        $userParts = explode('@', $xmppAccount->username);
        
        // Append automated hosting subdomain if required
        $subdomainUser = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['subdomain'];
        if ($subdomainUser != '') {
            $subdomainUser = '.' . $subdomainUser;
        }
        
        // Delete from shared roaster first
        $data = array(
            "user" => $userParts[0],
            "host" => $params['xmpp_host'],
            "grouphost" => $params['xmpp_host'],
            "group" => $xmppAccount->type == erLhcoreClassModelXMPPAccount::USER_TYPE_OPERATOR ? 'operators' . $subdomainUser : 'visitors' . $subdomainUser
        );
        
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->deleteUserSharedRosterGroup($data['user'], $data['group']);
            } else {
                
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-delete-user-from-roaster', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            }
        } catch (Exception $e) {
            
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
            
            throw new Exception('Could not delete user from roaster!');
        }
        
        // Delete user
        $data = array(
            "user" => $userParts[0],
            "host" => $params['xmpp_host']
        );
        
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->unregisterUser($data['user']);
            } else {
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-unregister', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            }
        } catch (Exception $e) {
            
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
            
            throw new Exception('Could not delete user!');
        }
    }

    /**
     * Used then visitor writes a message but chat is not accepted and we send message to all connected resposible department operators from this user with next his message.
     *
     * @todo add RPC support
     *      
     *      
     */
    public static function sendMessageByVisitorDirect($params = array())
    {
        file_put_contents("sendMessageByVisitorDirect.txt", "-----addUser-first-------------".var_export($params,true), FILE_APPEND);
        $paramsOnlineUser = self::getNickAndStatusByChat($params['chat']);
        
        $data = array(
            "jid" => $params['xmpp_account']->username,
            "pass" => $params['xmpp_account']->password,
            "host" => $params['host_login'],
            "operator_username" => $params['xmpp_account_operator']->username,
            "message" => $params['msg'],
            "nick" => $paramsOnlineUser['nick'],
            "status" => $paramsOnlineUser['status']
        );
        
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->sendMessageChat($data['jid'], $data['operator_username'], $data['message']);
            } else {
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-send-message', $data, false);
                
                if ($response != 'ok') {
                    throw new Exception('ok as response not received');
                }
            }
        } catch (Exception $e) {
            
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
        }
    }

    /**
     * Used then chat is accepted by one of operators and visitor writes a message
     */
    public static function sendMessageByVisitor($params = array())
    {

        $xmppAccount = $params['xmpp_account'];
        file_put_contents("sendMessageByVisitor_language_1.txt", "-----addUser-first-------------".var_export($xmppAccount,true), FILE_APPEND);
        $page = '';
        
        if ($params['chat']->online_user !== false) {
            $page = $params['chat']->online_user->current_page;
        }
        
        $paramsOnlineUser = self::getNickAndStatusByChat($params['chat'], $page);
        file_put_contents("sendMessageByVisitor_language_chat.txt", "-----addUser-first-------------".var_export($params['chat'],true), FILE_APPEND);
        $translationConfig = erLhcoreClassModelChatConfig::fetch('translation_data');
        $translationData = $translationConfig->data;
        if(isset($translationData['translation_handler']) && $translationData['translation_handler'] == 'baidu' && $params['chat']->chat_locale_to != ''){
            $msgTranslated = erLhcoreClassTranslateBaidu::translate($translationData['baidu_client_id'], $translationData['baidu_client_secret'], $params['msg']->msg, $params['chat']->chat_locale, $params['chat']->chat_locale_to);
            if($msgTranslated){
                $params['msg']->msg = $msgTranslated;
            }
        }
        file_put_contents("sendMessageByVisitor_language.txt", "-----addUser-first-------------".var_export($translationData,true), FILE_APPEND);
        file_put_contents("sendMessageByVisitor2.txt", "-----addUser-first-------------".var_export($params['chat'],true), FILE_APPEND);
        if ($params['chat']->user_id > 0) {
            
            $xmppAccountOperator = erLhcoreClassModelXMPPAccount::findOne(array(
                'filter' => array(
                    'type' => erLhcoreClassModelXMPPAccount::USER_TYPE_OPERATOR,
                    'user_id' => $params['chat']->user_id
                )
            ));
            file_put_contents("sendMessageByVisitor1.txt", "-----addUser-first-------------".var_export($xmppAccountOperator,true), FILE_APPEND);
            if ($xmppAccountOperator !== false) {
                $data = array(
                    "jid" => $xmppAccount->username,
                    "pass" => $xmppAccount->password,
                    "host" => $params['host_login'],
                    "operator_username" => $xmppAccountOperator->username,
                    "message" => $params['msg']->msg,
                    "nick" => $paramsOnlineUser['nick'],
                    "status" => $paramsOnlineUser['status']
                );
                
                try {
                    
                    if ($params['handler'] == 'rpc') {
                        
                        $rpc = new \GameNet\Jabber\RpcClient(array(
                            'server' => $params['rpc_server'],
                            'host' => $params['xmpp_host'],
                            'account_host' => $params['rpc_account_host'],
                            'username' => $params['rpc_username'],
                            'password' => $params['rpc_password']
                        ));
                        file_put_contents("sendMessageByVisitor.txt", "-----addUser-first-------------".var_export($data,true), FILE_APPEND);
                        $rpc->sendMessageChat($data['jid'], $data['operator_username'], $data['message']);
                    } else {
                        $response = self::sendRequest($params['node_api_server'] . '/xmpp-send-message', $data, false);
                        
                        if ($response != 'ok') {
                            throw new Exception('ok as response not received');
                        }
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
                
                self::updateActivityByXMPPAccountId($xmppAccount->id);
            }
        }
    }

    /**
     * Used then chat is started and we send messages to all online operators
     *
     * @todo remake using rpc method
     *      
     *      
     */
    public static function sendMessageStartChat($params = array())
    {
        $paramsOnlineUser = self::getNickAndStatusByChat($params['chat']);
        
        $data = array(
            "jid" => $params['xmpp_account']->username,
            "pass" => $params['xmpp_account']->password,
            "host" => $params['host_login'],
            "operator_username" => $params['xmpp_account_operator']->username,
            "message" => $params['msg']->msg,
            "nick" => $paramsOnlineUser['nick'],
            "status" => $paramsOnlineUser['status']
        );
        
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->sendMessageChat($data['jid'], $data['operator_username'], $data['message']);
            } else {
                
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-send-message', $data, false);
                
                if ($response != 'ok') {
                    throw new Exception('ok as response not received');
                }
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
        }
    }

    /**
     * Sumarizes attributes if it's chat
     */
    public static function getNickAndStatusByChat(erLhcoreClassModelChat $chat, $page = '')
    {
        $paramsReturn = array();
        $paramsOnlineUser = array();
        
        $paramsOnlineUser[] = (string) $chat->department;
        
        if ($chat->country_code != '') {
            $paramsOnlineUser[] = $chat->country_code . ($chat->city != '' ? ' (' . $chat->city . ')' : '');
        }
        
        if ($page == '' && $chat->referrer != '') {
            $paramsOnlineUser[] = $chat->referrer;
        } elseif ($page != '') {
            $paramsOnlineUser[] = $page;
        }
        
        if ($chat->user_tz_identifier != '') {
            $paramsOnlineUser[] = 'Time zone: ' . $chat->user_tz_identifier . ' (' . $chat->user_tz_identifier_time . ')';
        }
        
        if ($chat->status == erLhcoreClassModelChat::STATUS_ACTIVE_CHAT) {
            $paramsOnlineUser[] = 'Active chat';
        } elseif ($chat->status == erLhcoreClassModelChat::STATUS_PENDING_CHAT) {
            $paramsOnlineUser[] = 'Pending chat';
        }
        
        $paramsReturn['status'] = implode("\n| ", $paramsOnlineUser);
        
        $paramsReturn['nick'] = $chat->nick . ' #' . $chat->id;
        
        return $paramsReturn;
    }

    /**
     * Sumarizes online visitor attribtues
     *
     * @param erLhcoreClassModelChatOnlineUser $onlineUser            
     *
     * @return array
     */
    public static function getNickAndStatusByOnlineVisitor(erLhcoreClassModelChatOnlineUser $onlineUser)
    {
        $paramsReturn = array();
        
        $paramsOnlineUser = array();
        
        if ($onlineUser->chat_id > 0 && $onlineUser->chat !== false) {
            return self::getNickAndStatusByChat($onlineUser->chat, $onlineUser->current_page);
        }
        
        if ($onlineUser->user_country_code != '') {
            $paramsOnlineUser[] = $onlineUser->user_country_code . ($onlineUser->city != '' ? ' (' . $onlineUser->city . ')' : '');
        }
        
        if ($onlineUser->current_page != '') {
            $paramsOnlineUser[] = $onlineUser->current_page;
        }
        
        if ($onlineUser->visitor_tz != '') {
            $paramsOnlineUser[] = 'Time zone: ' . $onlineUser->visitor_tz . ' (' . $onlineUser->visitor_tz_time . ')';
        }
        
        if ($onlineUser->lastactivity_ago != '') {
            $paramsOnlineUser[] = 'Last activity ago: ' . $onlineUser->lastactivity_ago;
        }
        
        $paramsOnlineUser[] = 'Visits - ' . $onlineUser->total_visits;
        
        $paramsReturn['status'] = implode("\n| ", $paramsOnlineUser);
        
        $paramsReturn['nick'] = "Online visitor";
        
        return $paramsReturn;
    }

    /**
     * Prebind xmpp sessions, it's faster this way. At the moment it's not used because websocket does not supports attach method
     */
    public static function prebindSession($params)
    {
        $ch = curl_init($params['host']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERPWD, $params['username'] . ':' . $params['password']);
        $response = curl_exec($ch);
        
        if ($response !== false && $response != '') {
            
            list ($JID, $SID, $RID) = explode("\n", $response);
            
            return array(
                'jid' => $JID,
                'sid' => $SID,
                'rid' => $RID
            );
        }
        
        return false;
    }

    /**
     * called then online visityor does a pageview
     */
    public static function onlineUserPageViewLogged($params = array())
    {
        $xmppAccount = $params['xmpp_account'];
        
        // We execute only if nodejs handler is used, otherwise visitor connects himself
        if ($params['handler'] != 'rpc') {
            
            $paramsOnlineUser = self::getNickAndStatusByOnlineVisitor($params['ou']);
            
            $data = array(
                "jid" => $xmppAccount->username,
                "pass" => $xmppAccount->password,
                "host" => $params['host_login'],
                "nick" => $paramsOnlineUser['nick'],
                "status" => $paramsOnlineUser['status']
            );
            
            try {
                $response = self::sendRequest($params['node_api_server'] . '/xmpp', $data, false);
                
                if ($response != 'ok') {
                    throw new Exception('ok as response not received');
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        }
        
        self::updateActivityByXMPPAccountId($xmppAccount->id);
    }

    /**
     * Called then new chat is initiated
     *
     * register chat request as XMPP user and sends message to all online operators responsible to department
     *
     * @param array $params            
     *
     * @throws Exception
     *
     */
    public static function newChat($params = array())
    {
        $xmppAccount = $params['xmpp_account'];
        
        $userParts = explode('@', $xmppAccount->username);
        
        $paramsChat = self::getNickAndStatusByChat($params['chat']);
        
        // Append automated hosting subdomain if required
        $subdomainUser = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['subdomain'];
        if ($subdomainUser != '') {
            $subdomainUser = '.' . $subdomainUser;
        }
        
        $data = array(
            "user" => $userParts[0],
            "host" => $params['xmpp_host'],
            "password" => $xmppAccount->password,
            "hostlogin" => $params['host_login'],
            "nick" => $paramsChat['nick'],
            "status" => $paramsChat['status'],
            'group' => 'visitors' . $subdomainUser
        );
        
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->createUser($data['user'], $data['password']);
                
                $rpc->addUserToSharedRosterGroup($data['user'], $data['group']);
            } else {
                
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-register-online-visitor', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
        }
        
        // Cleanup is made then new account is created
        self::cleanupOldXMPPAccounts();
    }

    /**
     * Changes password for operator
     *
     * @param array $params            
     *
     * @throws Exception
     */
    public static function changeOperatorPassword($params = array())
    {
        $data = array(
            "user" => $params['xmpp_account']->username_plain,
            "host" => $params['xmpp_host'],
            "password" => $params['xmpp_account']->password
        );
        
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->changePassword($data['user'], $data['password']);
            } else {
                
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-change-password', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
            
            throw new Exception('Could not change operator password in XMPP server!');
        }
    }

    /**
     * registers operator and assigns to shared operators roaster
     *
     * @param array $params            
     */
    public static function registerOperator($params = array())
    {

        try {
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                if(stripos($params['xmpp_account']->username,'@')){
                    $params['xmpp_account']->username = str_replace('@'.$params['xmpp_host'],'',$params['xmpp_account']->username);
                }
                file_put_contents("logregisterOperator1.txt", "-----addUser-first-------------".var_export($params,true), FILE_APPEND);
                $rpc->createUser($params['xmpp_account']->username, $params['xmpp_account']->password);
            } else {
                
                $data = array(
                    "user" => $params['xmpp_account']->username_plain,
                    "host" => $params['xmpp_host'],
                    "password" => $params['xmpp_account']->password
                );
                
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-register', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
            
            throw new Exception('Could not register operator in XMPP server!');
        }
        
        // Append automated hosting subdomain if required
        $subdomainUser = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['subdomain'];
        if ($subdomainUser != '') {
            $subdomainUser = '.' . $subdomainUser;
        }
               
        try {
            
            if ($params['handler'] == 'rpc') {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->addUserToSharedRosterGroup($params['xmpp_account']->username_plain, 'operators' . $subdomainUser);
            } else {
                
                // Assign user to operators roaster
                $data = array(
                    "user" => $params['xmpp_account']->username_plain,
                    "host" => $params['xmpp_host'],
                    "group" => 'operators' . $subdomainUser,
                    "grouphost" => $params['xmpp_host']
                );
                
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-assign-user-to-roaster', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
            
            throw new Exception('Could not assign operator to operators shared roaster!');
        }
    }

    /**
     * Is executed then new online visitor account is created
     *
     * @todo test
     *      
     * @param array $params            
     */
    public static function newOnlineVisitor($params = array())
    {
        $xmppAccount = $params['xmpp_account'];
        
        $userParts = explode('@', $xmppAccount->username);
        
        $paramsOnlineUser = self::getNickAndStatusByOnlineVisitor($params['ou']);
        
        // Append automated hosting subdomain if required
        $subdomainUser = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['subdomain'];
        
        if ($subdomainUser != '') {
            $subdomainUser = '.' . $subdomainUser;
        }
        
        if ($params['handler'] == 'rpc') {
            
            try {
                
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                $rpc->createUser($userParts[0], $xmppAccount->password);
                
                $rpc->addUserToSharedRosterGroup($userParts[0], 'visitors' . $subdomainUser);
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            $data = array(
                "user" => $userParts[0],
                "host" => $params['xmpp_host'],
                "password" => $xmppAccount->password,
                "hostlogin" => $params['host_login'],
                "nick" => $paramsOnlineUser['nick'],
                "status" => $paramsOnlineUser['status'],
                "group" => 'visitors' . $subdomainUser
            );
            
            try {
                $response = self::sendRequest($params['node_api_server'] . '/xmpp-register-online-visitor', $data);
                
                if ($response['error'] == true) {
                    throw new Exception($response['msg']);
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        }
        
        // Cleanup is made then new account is created
        self::cleanupOldXMPPAccounts();
    }

    /**
     * Checks that instance of shared roaster existed
     */
    public static function checkSharedRoasters($params)
    {
        // Delete visitors shared roaster
        $data[] = array(
            "group" => "visitors." . $params['subdomain'],
            "host" => $params['xmpp_host']
        );
        
        $data[] = array(
            "group" => "operators." . $params['subdomain'],
            "host" => $params['xmpp_host']
        );
        
        // First register visitors shared roaster
        $dataRegister["visitors." . $params['subdomain']] = array(
            "group" => "visitors." . $params['subdomain'],
            "host" => $params['xmpp_host'],
            "name" => "Visitors",
            "desc" => "Visitors",
            "display" => '\"\"',
            "display_array" => array()
        );
        
        // Register operators shared roaster
        $dataRegister["operators." . $params['subdomain']] = array(
            "group" => "operators." . $params['subdomain'],
            "host" => $params['xmpp_host'],
            "name" => "Operators",
            "desc" => "Operators",
            "display" => '\"operators.' . $params['subdomain'] . '\\\nvisitors.' . $params['subdomain'] . '\"',
            "display_array" => array(
                'operators.' . $params['subdomain'],
                'visitors.' . $params['subdomain']
            )
        );
        
        if ($params['handler'] == 'rpc') {
            
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                foreach ($data as $groupData) {
                    $infoSharedRoaster = $rpc->getInfoSharedRosterGroup($groupData['group']);
                    
                    if (empty($infoSharedRoaster)) {
                        $rpc->createSharedRosterGroup($dataRegister[$groupData['group']]['group'], $dataRegister[$groupData['group']]['name'], $dataRegister[$groupData['group']]['desc'], $dataRegister[$groupData['group']]['display_array']);
                    }
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
                
                throw $e;
            }
        } else {
            // Iterates through groups and checks des it exists, if not creates
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-does-shared-roaster-exists', $groupData);
                    
                    if ($response['error'] == true) {
                        try {
                            $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $dataRegister[$groupData['group']]);
                            
                            if ($response['error'] == true) {
                                throw new Exception($response['msg']);
                            }
                        } catch (Exception $e) {
                            throw $e;
                        }
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                    
                    throw $e;
                }
            }
        }
    }

    /**
     * Get's called then instance is destroyed
     */
    public static function instanceDestroyed($params)
    {
        // Delete visitors shared roaster
        $data[] = array(
            "group" => "visitors." . $params['subdomain'],
            "host" => $params['xmpp_host']
        );
        
        $data[] = array(
            "group" => "operators." . $params['subdomain'],
            "host" => $params['xmpp_host']
        );
        
        foreach ($data as $groupData) {
            if ($params['handler'] == 'rpc') {
                try {
                    $rpc = new \GameNet\Jabber\RpcClient(array(
                        'server' => $params['rpc_server'],
                        'host' => $params['xmpp_host'],
                        'account_host' => $params['rpc_account_host'],
                        'username' => $params['rpc_username'],
                        'password' => $params['rpc_password']
                    ));
                    
                    foreach ($data as $groupData) {
                        $rpc->deleteSharedRosterGroup($groupData['group']);
                    }
                } catch (Exception $e) {
                    
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                    
                    // To terminate instance termination
                    throw $e;
                }
            } else {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-delete-instance-roasters', $groupData);
                    
                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                    
                    // To terminate instance termination
                    throw $e;
                }
            }
        }
    }

    /**
     * Creates required shared roasters
     *
     * @todo add support for RPC
     *      
     *      
     */
    public static function registerInstanceRoasters($params)
    {
        
        // First register visitors shared roaster
        $data[] = array(
            "group" => "visitors." . $params['subdomain'],
            "host" => $params['xmpp_host'],
            "name" => "Visitors",
            "desc" => "Visitors",
            "display" => '\"\"',
            "display_array" => array()
        );
        
        // Register operators shared roaster
        $data[] = array(
            "group" => "operators." . $params['subdomain'],
            "host" => $params['xmpp_host'],
            "name" => "Operators",
            "desc" => "Operators",
            "display" => '\"operators.' . $params['subdomain'] . '\\\nvisitors.' . $params['subdomain'] . '\"',
            "display_array" => array(
                'operators.' . $params['subdomain'],
                'visitors.' . $params['subdomain']
            )
        );
        
        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                
                foreach ($data as $groupData) {
                    $infoSharedRoaster = $rpc->getInfoSharedRosterGroup($groupData['group']);
                    
                    if (empty($infoSharedRoaster)) {
                        $rpc->createSharedRosterGroup($groupData['group'], $groupData['name'], $groupData['desc'], $groupData['display_array']);
                    }
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);
                    
                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    /**
     * 添加好友关系 liuyue
     *
     * @todo add support for RPC
     *
     *
     */
    public static function addRoasters($params)
    {
        $params = json_decode($params, true);
        // Register operators shared roaster
        //addRosterItem($user, $contact, $nickname, $group = '', $subs = 'both')
        $data[] = array(
            "user" => $params['user'],
            "contact" => $params['contact'],
            "nickname" => $params['nickname'],
            "group" => $params['group'],
            "subs" => $params['subs']
        );

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                foreach ($data as $groupData) {
                    $infoSharedRoaster = $rpc->getRoster($params['user']);
                    var_dump($infoSharedRoaster);
                    foreach($infoSharedRoaster as $roster){
                        if ($roster['jid'] != $params['contact'].'@'.$params['xmpp_host']) {
                            $backinfo = $rpc->addRosterItem($groupData['user'], $groupData['contact'], $params['nickname'], $params['group'], $params['subs']);
                            if($backinfo['res'] == 0){
                                $backinfo = $rpc->addRosterItem($groupData['contact'], $groupData['user'], $params['nickname'], $params['group'], $params['subs']);
                            }
                            echo json_encode($backinfo);
                        }
                    }

                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    /**
     * 添加XMPP用户
     *
     * @todo add support for RPC
     *
     *
     */
    public static function addUser($params)
    {
        $params = json_decode($params, true);
        // Register operators shared roaster
        //addRosterItem($user, $contact, $nickname, $group = '', $subs = 'both')
        $data[] = array(
            "user" => $params['user'],
            "password" => $params['password'] ? $params['password'] : '397126845',
            "nickname" => $params['nickname']
        );
      
        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                foreach ($data as $groupData) {
                    $infoUser = $rpc->checkAccount($params['user']);
                    file_put_contents("log.txt", "-----addUser-first1-------------".var_export($infoUser,true), FILE_APPEND);
		  $infos = $rpc->createUser($groupData['user'],$groupData['password']);
                    file_put_contents("log.txt", "-----addUser-second2-------------".var_export($infos,true), FILE_APPEND);
                    if($groupData['nickname']){
                        $rpc->setNickname($groupData['user'],$groupData['nickname']);
                    } 
                   
                    $contents['handler'] = $params['handler'];
                    $contents['rpc_server'] = $params['rpc_server'];
                    $contents['rpc_account_host'] = $params['rpc_account_host'];
                    $contents['rpc_username'] = $params['rpc_username'];
                    $contents['rpc_password'] = $params['rpc_password'];
                    $contents['xmpp_host'] = $params['xmpp_host'];
                    $contents['group'] = $params['group'];
                    $contents['user'] = $params['user'].'@'.$params['xmpp_host'];
                    $contents['name'] = $params['name'];
                    $contents['setchat'] = 'add';
                    file_put_contents("log.txt", "-----addUser3-------------".var_export($contents,true), FILE_APPEND);
                    self::setUserToGroup(json_encode($contents));
  		  exit;
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    /**
     * 修改XMPP用户
     *
     * @todo add support for RPC
     *
     *
     */
    public static function editUser($params)
    {
        $params = json_decode($params, true);
        // Register operators shared roaster
        //addRosterItem($user, $contact, $nickname, $group = '', $subs = 'both')
        $data[] = array(
            "user" => $params['user'],
            "password" => $params['password'],
            "del" => $params['del']
        );

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                foreach ($data as $groupData) {
                    if($groupData['del'] == 1){
                        $info = $rpc->unregisterUser($groupData['user']);
                    }else{
                        $info = $rpc->changePassword($groupData['user'], $groupData['password']);
                    }
                }
                file_put_contents("log_edituser.txt", "-----addUser-------------".var_export($info,true), FILE_APPEND);
                echo json_encode($info);
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    public static function deleteUser($params){
        $params = json_decode($params, true);
        // Register operators shared roaster
        $data[] = array(
            "user" => $params['user'],
            "group" => $params['group']
        );

        try {
            $rpc = new \GameNet\Jabber\RpcClient(array(
                'server' => $params['rpc_server'],
                'host' => $params['xmpp_host'],
                'account_host' => $params['rpc_account_host'],
                'username' => $params['rpc_username'],
                'password' => $params['rpc_password']
            ));

            foreach ($data as $groupData) {
                $infoMembersSharedRosterGroup = $rpc->getMembersSharedRosterGroup($groupData['group']);
                if(is_array($infoMembersSharedRosterGroup['members'])){
                        foreach($infoMembersSharedRosterGroup['members'] as $k=>$r){
                            $infoGroup[] = $r['member'];
                        }
                }else{
                     $infoGroup = $infoMembersSharedRosterGroup;
                }
        
                if(in_array($groupData['user'].'@'.$params['xmpp_host'], $infoGroup)){
                    $info = $rpc->deleteUserSharedRosterGroup($groupData['user'], $groupData['group']);

                    echo json_encode($info);
                }
                exit;
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
        }
    }

   public static function setUserCard($params){
        $params = json_decode($params, true);
        // Register operators shared roaster
        $data[] = array(
            "user" => $params['user'],
            "name" => $params['name'],
            "value" => $params['value']
        );
        try {
            $rpc = new \GameNet\Jabber\RpcClient(array(
                'server' => $params['rpc_server'],
                'host' => $params['xmpp_host'],
                'account_host' => $params['rpc_account_host'],
                'username' => $params['rpc_username'],
                'password' => $params['rpc_password']
            ));

            foreach ($data as $groupData) {
                $infoSharedRoaster = $rpc->setVCard($groupData['user'],$groupData['name'],$groupData['value']);
                exit;
            }
        } catch (Exception $e) {
            if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                erLhcoreClassLog::write(print_r($e, true));
            }
        }
    }
    /**
     * Creates required shared roasters
     *
     * @todo add support for RPC
     *
     *
     */
    public static function addInstanceRoasters($params)
    {
        $params = json_decode($params, true);
        // Register operators shared roaster
        $data[] = array(
            "group" => $params['group'],
            "host" => $params['xmpp_host'],
            "name" => $params['name'],
            "desc" => $params['desc'],
            "display" => '',
            "display_array" => array($params['departs'])
        );

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                foreach ($data as $groupData) {
                    $infoSharedRoaster = $rpc->getInfoSharedRosterGroup($groupData['group']);
                    if (empty($infoSharedRoaster)) {
                        $infoSharedRoaster = $rpc->createSharedRosterGroup($groupData['group'], $groupData['name'], $groupData['desc'], $groupData['display_array']);
                    }
                    /* 创建group成功后，是否需要同步创建聊天室
                     *
                     * chatopen true 创建
                     */
                    if($infoSharedRoaster['res'] == 0 && $params['chatopen'] = true){
                        //{"handler":"rpc","rpc_server":"http://117.34.80.209:4560","rpc_account_host":"vtiger.club","rpc_username":"testxmpp","rpc_password":"397126845","xmpp_host":"vtiger.club","name":"app6","dealtype":"add","chat_name":"产品策划部"}
                        $contents['handler'] = $params['handler'];
                        $contents['rpc_server'] = $params['rpc_server'];
                        $contents['rpc_account_host'] = $params['rpc_account_host'];
                        $contents['rpc_username'] = $params['rpc_username'];
                        $contents['rpc_password'] = $params['rpc_password'];
                        $contents['xmpp_host'] = $params['xmpp_host'];
                        $contents['name'] = $params['group'];
                        $contents['chat_name'] = $params['name'];
                        $contents['dealtype'] = 'add';
                        echo self::roomOperate(json_encode($contents));
                    }
                    exit;
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    /**
     * Creates Group by product
     *
     * @todo add support for RPC
     * 产品 赋予 角色或者部门，角色可能对应多个部门
     *
     */
    public static function addOtherGroup($params)
    {
        $params = json_decode($params, true);
        // Register operators shared roaster
        $chatname = $params['role'] ? 'chat_role_'.$params['role'] : 'chat_'.$params['departs'][0];

        $data[] = array(
            "group" => $chatname,
            "host" => $params['xmpp_host'],
            "name" => $params['name'],
            "desc" => $params['desc']
        );

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                /* 创建group成功后，是否需要同步创建聊天室
                 *
                 * chatopen true 创建
                 */
                $contents['handler'] = $params['handler'];
                $contents['rpc_server'] = $params['rpc_server'];
                $contents['rpc_account_host'] = $params['rpc_account_host'];
                $contents['rpc_username'] = $params['rpc_username'];
                $contents['rpc_password'] = $params['rpc_password'];
                $contents['xmpp_host'] = $params['xmpp_host'];
                $contents['name'] = $chatname;
                $contents['chat_name'] = $params['name'];
                $contents['dealtype'] = 'add';
                self::roomOperate(json_encode($contents));

                /*
                 * 创建完chatroom后需要将所有人拉进该聊天室
                 *
                 */
                $rosterGroupList = array();

                foreach($params['departs'] as $k=>$r){
                    $infoShareRosterGroup = $rpc->getMembersSharedRosterGroup($r);
                    foreach($infoShareRosterGroup['members'] as $v){
                        if(!in_array($v['member'],$rosterGroupList)){
                            //array_push($rosterGroupList,$v['member']);
                            $contents['handler'] = $params['handler'];
                            $contents['rpc_server'] = $params['rpc_server'];
                            $contents['rpc_account_host'] = $params['rpc_account_host'];
                            $contents['rpc_username'] = $params['rpc_username'];
                            $contents['rpc_password'] = $params['rpc_password'];
                            $contents['xmpp_host'] = $params['xmpp_host'];
                            $contents['name'] = $chatname;
                            $contents['user'] = $v['member'];
                            $contents['type'] = 'member';
                            self::setRoomAffiliation(json_encode($contents));

                            // ejabberd 更新bookmarks
                            $bookmark['xmpp_host'] = $params['xmpp_host'];
                            $bookmark['username'] = $v['member'];
                            $bookmark['chatjid'] = $chatname;
                            $bookmark['name'] = $params['name'];
                            $bookmark['dealtype'] = 'add';
                            self::iqSend(json_encode($bookmark));
                        }
                    }
                }

            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    /**
     * Creates Group by product
     *
     * @todo add support for RPC
     * 产品 赋予 角色或者部门，角色可能对应多个部门
     *
     */
    public static function editGroup($params)
    {
        // 修改部门名称
        $params = json_decode($params, true);
        // Register operators shared roaster
        $data[] = array(
            "group" => $params['group'],
            "togroup" => $params['togroup'],
            "host" => $params['xmpp_host'],
            "name" => $params['name'],
            "desc" => $params['desc'],
            "display" => '',
            "display_array" => $params['departs']
        );

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));
                /* 创建group成功后，是否需要同步创建聊天室
                 *
                 * chatopen true 创建
                 */
                //此处可能追加了显示的组信息，需要更新过来

                foreach ($data as $groupData) {
                    $infoEditGroup = $rpc->createSharedRosterGroup($groupData['togroup'], $groupData['name'], $groupData['desc'], $groupData['display_array']);
                }

                /*
                 * 创建完chatroom后需要将所有人拉进该聊天室
                 *
                 */
                $rosterGroupList = array();

                $infoShareRosterGroup = $rpc->getMembersSharedRosterGroup($params['group']);
                if(is_array($infoShareRosterGroup['members'])){
                                $RosterGroup = $infoShareRosterGroup['members'];
                            }else{
                                $RosterGroup = $infoShareRosterGroup;
                            }
                            
                foreach($RosterGroup as $v){
                    if(!in_array($v,$rosterGroupList)){
                        // ejabberd 更新bookmarks
                        array_push($rosterGroupList,$v);
                        $bookmark['xmpp_host'] = $params['xmpp_host'];
                        $bookmark['username'] = $v;
                        $bookmark['chatjid'] = $params['group'];
                        $bookmark['tochatjid'] = $params['togroup'];
                        $bookmark['name'] = $params['name'];
                        $bookmark['dealtype'] = 'transform';
                        self::iqSend(json_encode($bookmark));
                    }
                }


            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    public static function setUserToGroup($params)
    {
        $params = json_decode($params, true);
        // Register operators shared roaster
        if(stripos($params['user'],'@')){
            $user = explode('@', $params['user']);
            $user = $user[0];
        }else{
            $user = $params['user'];
            $params['user'] = $params['user'].'@'.$params['xmpp_host'];
        }

        $data[] = array(
            "group" => $params['group'],
            "user" =>  $user,
            "togroup" => $params['togroup']
        );

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                foreach ($data as $groupData) {
                    $infoMembersSharedRosterGroup = $rpc->getMembersSharedRosterGroup($groupData['group']);
                    if(is_array($infoMembersSharedRosterGroup['members'])){
                        foreach($infoMembersSharedRosterGroup['members'] as $k=>$r){
                            $infoGroup[] = $r['member'];
                        }
                    }else{
                                       $infoGroup = $infoMembersSharedRosterGroup;
                                   }
 
                    file_put_contents("log.txt", "-----setUserToGroup-shareRosterGroup--".var_export($infoGroup,true), FILE_APPEND);
                    /*
                     * togroup 作为判断是否有user转移到别的部门，这样需要把group 和 chatroom 做相应的修改
                     */
                    if($groupData['togroup']){
                        // 从原来的group删除user

                        if (in_array($params['user'], $infoGroup)) {
                            $infoDeleteUser = $rpc->deleteUserSharedRosterGroup($groupData['user'],$groupData['group']);
                            file_put_contents("log.txt", "-----setUserToGroup-DeleteRosterGroup--".var_export($infoDeleteUser,true), FILE_APPEND);

                        }
                        // user加入到togroup里
                        $infoMembersSharedRosterGroup = $rpc->addUserToSharedRosterGroup($groupData['user'],$groupData['togroup']);
                        // 给user 附chatroom member
                        if($params['setchat'] == true){
                            $contents['handler'] = $params['handler'];
                            $contents['rpc_server'] = $params['rpc_server'];
                            $contents['rpc_account_host'] = $params['rpc_account_host'];
                            $contents['rpc_username'] = $params['rpc_username'];
                            $contents['rpc_password'] = $params['rpc_password'];
                            $contents['xmpp_host'] = $params['xmpp_host'];
                            $contents['name'] = $params['togroup'];
                            $contents['user'] = $params['user'];
                            $contents['type'] = 'member';
                            self::setRoomAffiliation(json_encode($contents));

                            // ejabberd 更新bookmarks
                            $bookmark['xmpp_host'] = $params['xmpp_host'];
                            $bookmark['username'] = $params['user'];
                            $bookmark['chatjid'] = $params['group'];
                            $bookmark['tochatjid'] = $params['togroup'];
                            $bookmark['name'] = $params['name'];
                            $bookmark['dealtype'] = 'transform';
                            self::iqSend(json_encode($bookmark));
                        }

                    }else{
                        if($params['setchat'] == false){
                            // group 删除这个user
                            // 从原来的group删除user

                            $info = $rpc->deleteUserSharedRosterGroup($groupData['user'],$groupData['group']);
                            echo json_encode($info);
                            // ejabberd 删除bookmarks
                            $bookmark['xmpp_host'] = $params['xmpp_host'];
                            $bookmark['username'] = $params['user'];
                            $bookmark['chatjid'] = $params['group'];
                            $bookmark['name'] = $params['name'];
                            $bookmark['dealtype'] = 'del';
                            self::iqSend(json_encode($bookmark));
                            exit;
                        }

                        if (!in_array($params['user'], $infoGroup)) {
                            $infoMembersSharedRosterGroup = $rpc->addUserToSharedRosterGroup($groupData['user'],$groupData['group']);
                        }

                        // 给user 附chatroom member
                        if($params['setchat'] == true){
                            $contents['handler'] = $params['handler'];
                            $contents['rpc_server'] = $params['rpc_server'];
                            $contents['rpc_account_host'] = $params['rpc_account_host'];
                            $contents['rpc_username'] = $params['rpc_username'];
                            $contents['rpc_password'] = $params['rpc_password'];
                            $contents['xmpp_host'] = $params['xmpp_host'];
                            $contents['name'] = $params['group'];
                            $contents['user'] = $params['user'];
                            $contents['type'] = 'member';
                            self::setRoomAffiliation(json_encode($contents));

                            // ejabberd 端创建bookmarks
                            $bookmark['xmpp_host'] = $params['xmpp_host'];
                            $bookmark['username'] = $params['user'];
                            $bookmark['chatjid'] = $params['group'];
                            $bookmark['name'] = $params['name'];
                            $bookmark['dealtype'] = 'add';

                            self::iqSend(json_encode($bookmark));
                        }
                    }
                    exit;
                }
            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        } else {
            foreach ($data as $groupData) {
                try {
                    $response = self::sendRequest($params['node_api_server'] . '/xmpp-setup-instance-roasters', $groupData);

                    if ($response['error'] == true) {
                        throw new Exception($response['msg']);
                    }
                } catch (Exception $e) {
                    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                        erLhcoreClassLog::write(print_r($e, true));
                    }
                }
            }
        }
    }

    /**
     * Executed in erLhcoreClassExtensionXmppserviceHandler::handleMessageFromOperator send message to user from operator.
     * if provided message is a command to operator is send command response
     *
     * @param erLhcoreClassModelChat $chat            
     *
     * @param erLhcoreClassModelXMPPAccount $xmppUser            
     *
     * @param string $body            
     *
     * @throws Exception
     */
    public static function sendMessageToChat(erLhcoreClassModelChat $chat, erLhcoreClassModelXMPPAccount $xmppUser, $body)
    {
        $db = ezcDbInstance::get();
        $db->beginTransaction();
        
        try {
            
            $user = $xmppUser->user;
            
            $messageUserId = $user->id;
            
            $ignoreMessage = false;
            
            // Predefine
            $statusCommand = array(
                'processed' => false,
                'process_status' => ''
            );
            
            if (strpos(trim($body), '!') === 0) {
                
                $statusCommand = erLhcoreClassChatCommand::processCommand(array(
                    'no_ui_update' => true,
                    'msg' => $body,
                    'chat' => & $chat,
                    'user' => $user
                ));
                
                if ($statusCommand['processed'] === true) {
                    $messageUserId = - 1; // Message was processed set as internal message
                    
                    $rawMessage = !isset($statusCommand['raw_message']) ? $body : $statusCommand['raw_message'];
                    
                    $body = '[b]' . $userData->name_support . '[/b]: ' . $rawMessage . ' ' . ($statusCommand['process_status'] != '' ? '|| ' . $statusCommand['process_status'] : '');
                }
                
                if (isset($statusCommand['ignore']) && $statusCommand['ignore'] == true) {
                    $ignoreMessage = true;
                }
                
                if (isset($statusCommand['info'])) {
                    $xmppService = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice');
                    $xmppService->sendMessageToOperatorAsUserByChat(array(
                        'xmpp_account_operator' => $xmppUser,
                        'chat' => $chat,
                        'msg' => '[[System Assistant]] ' . $statusCommand['info']
                    ));
                }
            }
            
            if ($ignoreMessage == false) {
                $msg = new erLhcoreClassModelmsg();
                $msg->msg = $body;
                $msg->chat_id = $chat->id;
                $msg->user_id = $messageUserId;
                $msg->time = time();
                $msg->name_support = $user->name_support;
                
                if ($messageUserId > 0 && $chat->chat_locale != '' && $chat->chat_locale_to != '') {
                    erLhcoreClassTranslate::translateChatMsgOperator($chat, $msg);
                }
                
                erLhcoreClassChat::getSession()->save($msg);
                
                // Set last message ID
                if ($chat->last_msg_id < $msg->id) {
                    
                    $userChange = '';
                    
                    // Assign operator if chat does not have one
                    if ($chat->user_id == 0) {
                        $userChange = ',user_id = :user_id';
                    }
                    
                    $stmt = $db->prepare("UPDATE lh_chat SET status = :status, user_status = :user_status, last_msg_id = :last_msg_id{$userChange} WHERE id = :id");
                    $stmt->bindValue(':id', $chat->id, PDO::PARAM_INT);
                    $stmt->bindValue(':last_msg_id', $msg->id, PDO::PARAM_INT);
                    
                    $changeStatus = false;
                    
                    if ($user->invisible_mode == 0) {
                        if ($chat->status == erLhcoreClassModelChat::STATUS_PENDING_CHAT) {
                            $chat->status = erLhcoreClassModelChat::STATUS_ACTIVE_CHAT;
                            $changeStatus = true;
                        }
                    }
                    
                    if ($chat->user_status == erLhcoreClassModelChat::USER_STATUS_CLOSED_CHAT) {
                        $chat->user_status = erLhcoreClassModelChat::USER_STATUS_PENDING_REOPEN;
                        
                        if (($onlineuser = $chat->online_user) !== false) {
                            $onlineuser->reopen_chat = 1;
                            $onlineuser->saveThis();
                        }
                    }
                    
                    $stmt->bindValue(':user_status', $chat->user_status, PDO::PARAM_INT);
                    $stmt->bindValue(':status', $chat->status, PDO::PARAM_INT);
                    
                    if ($userChange != '') {
                        $stmt->bindValue(':user_id', $msg->user_id, PDO::PARAM_INT);
                    }
                    
                    $stmt->execute();
                }
                
                // If chat status changes update statistic
                if ($changeStatus == true) {
                    
                    if ($chat->department !== false) {
                        erLhcoreClassChat::updateDepartmentStats($chat->department);
                    }
                    
                    erLhcoreClassChat::updateActiveChats($chat->user_id);
                }
            }
            
            $db->commit();
            
            // Inform operator about command status
            if ($statusCommand['processed'] == true && $statusCommand['process_status'] != '') {
                $xmppService = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice');
                $xmppService->sendMessageToOperatorAsUserByChat(array(
                    'xmpp_account_operator' => $xmppUser,
                    'chat' => $chat,
                    'msg' => '[[System Assistant]] ' . $statusCommand['process_status']
                ));
            }
            
            // For nodejs plugin
            erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.desktop_client_admin_msg', array(
                'msg' => & $msg,
                'chat' => & $chat
            ));
            
            // For general listeners
            erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.web_add_msg_admin', array(
                'msg' => & $msg,
                'chat' => & $chat
            ));
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * handles messages from operator.
     * Workflow sounds like that
     * 1. Check that we can determine an operator
     * 2. Check to what messages was send, to chat or online visitor
     * 3. If message is send to online visitor check perphaps visitor has active chat
     * 4. If active chat found, send a message
     * 5. If active chat not found send message as proactive invitation
     *
     * @param array $params            
     *
     */
    public static function handleMessageFromOperator($params)
    {
        try {
            $parts = explode('.', $params['receiver']);

            if (isset($parts[1])) {
                
                $xmppUserLogin = $params['sender'] . '@' . $params['server'];
                
                $xmppUser = erLhcoreClassModelXMPPAccount::findOne(array(
                    'filter' => array(
                        'username' => $xmppUserLogin
                    )
                ));

                if ($xmppUser !== false) {
                    
                    // It's message to online visitor
                    if (isset($parts[2]) && $parts[2] == 'chat') {
                        
                        $chat = erLhcoreClassModelChat::fetch($parts[1]);
                        $user = $xmppUser->user;
                        
                        // Messages to chat is only send if chat is not accepted or sender is chat owner
                        if ($chat->user_id == $user->id || $chat->user_id == 0) {
                            self::sendMessageToChat($chat, $xmppUser, $params['body']);
                        } else {
                            $xmppService = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice');
                            $xmppService->sendMessageToOperatorAsUserByChat(array(
                                'xmpp_account_operator' => $xmppUser,
                                'chat' => $chat,
                                'msg' => '[[System Assistant]] Chat was already accepted by [' . $chat->user . '], your messages are now ignored'
                            ));
                        }
                    } else {
                        
                        $visitorId = $parts[1];
                        $visitor = erLhcoreClassModelChatOnlineUser::fetch($visitorId);

                        // We do not have any active chat
                        if ($visitor->chat === false || ! in_array($visitor->chat->status, array(
                            erLhcoreClassModelChat::STATUS_ACTIVE_CHAT,
                            erLhcoreClassModelChat::STATUS_PENDING_CHAT
                        ))) {
                            $visitor->operator_message = $params['body'];
                            $visitor->message_seen = 0;
                            $visitor->invitation_id = - 1;
                            $visitor->operator_user_id = $xmppUser->user_id;
                            $visitor->saveThis();
                            
                            // We have active chat
                        } else {
                            
                            self::sendMessageToChat($visitor->chat, $xmppUser, $params['body']);
                        }
                    }
                } else {
                    throw new Exception('Could not find a operator');
                }
            } else {
                throw new Exception('Could not extract visitor ID');
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Handlers requests like
     *
     * May 08 23:02:11 [Warning] [default] [default] {"action":"ping","user":"remdex2@xmpp.livehelperchat.com/25304460891431118632139491"}
     * May 08 23:02:14 [Warning] [default] [default] {"action":"disconnect","user":"remdex2","server":"xmpp.livehelperchat.com"}
     * May 08 23:21:52 [Warning] [default] [default] {"action":"connect","user":"remdex2","server":"xmpp.livehelperchat.com"}
     */
    public static function handleOperatorPing($jsonContent)
    {
        $params = json_decode($jsonContent, true);

        $xmppService = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice');
        
        // If ping just update last action
        if ($params['action'] == 'ping') {
            
            // Parse user parts
            $userParts = self::parseXMPPUser($params['user']);
            
            // Fetches user id by xmpp username
            $userId = self::getUserIDByXMPPUsername($userParts['xmppuser']);
            
            // Updates last activity
            if (is_numeric($userId)) {
                self::updateActivityByUserId($userId, time() + $xmppService->settings['append_time']);
            } else {
                throw new Exception("Could not find LHC user by user - " . $userParts['xmppuser']);
            }
            
        } elseif ($params['action'] == 'disconnect' || $params['action'] == 'connect') {
            
            // Fetches user id by xmpp username
            $userId = self::getUserIDByXMPPUsername($params['user'] . '@' . $params['server']);
            
            // Updates last activity to zero
            if (is_numeric($userId)) {
                self::updateActivityByUserId($userId, $params['action'] == 'connect' ? time() + $xmppService->settings['append_time'] : 0);
                
                if ($params['action'] == 'connect') {
                    
                    $userData = erLhcoreClassModelUser::fetch($userId);
                                        
                    if ($userData instanceof erLhcoreClassModelUser && $userData->hide_online == 1) {
                        $userData->hide_online = 0;
                        erLhcoreClassUser::getSession()->update($userData);
                        erLhcoreClassUserDep::setHideOnlineStatus($userData);
                    }
                }               
                
            } else {
                throw new Exception("Could not find LHC user by user - " . $params['user'] . '@' . $params['server']);
            }
        }
        
        return true;
    }

    public function roomOperate($jsonContent){
        $params = json_decode($jsonContent, true);
        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                if($params['dealtype'] == 'add'){
                    $infoOnlineRooms = $rpc->getOnlineRooms();
                    $nowRoom = $params['name'].'@muc.'.$params['xmpp_host'];  // 查找该房间是否存在
                    if(empty($infoOnlineRooms) || !in_array($nowRoom, $infoOnlineRooms)){
                        $infocreateRoom = $rpc->createRoom($params['name']);
                    }
                    $room_name = $params['name'];
                    $rpc->setRoomOption($room_name, 'title', trim($params['chat_name']));
                    $rpc->setRoomOption($room_name, 'description', '');
                    $rpc->setRoomOption($room_name, 'allow_change_subj', true);
                    $rpc->setRoomOption($room_name, 'allow_query_users', true);
                    $rpc->setRoomOption($room_name, 'allow_private_messages', true);
                    $rpc->setRoomOption($room_name, 'allow_private_messages_from_visitors', 'anyone');
                    $rpc->setRoomOption($room_name, 'allow_visitor_status', true);
                    $rpc->setRoomOption($room_name, 'allow_visitor_nickchange', true);
                    //$rpc->setRoomOption($room_name, 'semi-anonymous', false);
                    $rpc->setRoomOption($room_name, 'public', true);
                    $rpc->setRoomOption($room_name, 'public_list', true);
                    $rpc->setRoomOption($room_name, 'persistent', true);
                    $rpc->setRoomOption($room_name, 'muc_unmoderated', true);
                    //$rpc->setRoomOption($room_name, 'unmoderated', true);
                    $rpc->setRoomOption($room_name, 'moderated', false);
                    $rpc->setRoomOption($room_name, 'members_by_default', true);
                    $rpc->setRoomOption($room_name, 'members_only', false);
                    $rpc->setRoomOption($room_name, 'allow_user_invites', true);
                    $rpc->setRoomOption($room_name, 'password_protected', false);
                    $rpc->setRoomOption($room_name, 'captcha_protected', false);
                    $rpc->setRoomOption($room_name, 'password', '');
                    $rpc->setRoomOption($room_name, 'anonymous', true);
                    $rpc->setRoomOption($room_name, 'logging', false);
                    $rpc->setRoomOption($room_name, 'max_users', 200);
                    $rpc->setRoomOption($room_name, 'allow_voice_requests', true);
                    $rpc->setRoomOption($room_name, 'mam', true);
                    $rpc->setRoomOption($room_name, 'voice_request_min_interval', 1800);
                }

                if($params['dealtype'] == 'del') {
                    $infocreateRoom = $rpc->deleteRoom($params['name']);
                }

                echo json_encode($infocreateRoom);

            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        }
    }

    public function getRooms($jsonContent){
        $params = json_decode($jsonContent, true);
        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                $infocreateRoom = $rpc->getRoom($params['user']);
                echo json_encode($infocreateRoom);
                exit;

            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        }
    }
    /**
     *
     * 创建群聊后，给用户发邀请
     */
    public function SendMucToUser($jsonContent){
        $params = json_decode($jsonContent, true);
        // Register operators shared roaster
        //inviteToRoom($name, $password, $reason, array $users)
        /*$data[] = array(
            "name" => $params['name'],
            "password" => $params['password'],
            "reason" => $params['reason'],
            'users' => json_decode($params['users'], true)
        );*/

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                $infoOnlineRooms = $rpc->getOnlineRooms();

                $nowRoom = $params['name'].'@muc.'.$params['xmpp_host'];  // 查找该房间是否存在
                if(empty($infoOnlineRooms)){
                    $infocreateRoom = $rpc->createRoom($params['name']);
                }

                if(in_array($nowRoom, $infoOnlineRooms)){   // 存在
                    $infoInvite = $rpc->inviteToRoom($params['name'],$params['password'], $params['reason'],$params['users']);
                }
                echo json_encode($infoInvite);

            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        }
    }

    /**
     *
     * 创建chat的用户赋值身份
     */
    public function setRoomAffiliation($jsonContent){
        $params = json_decode($jsonContent, true);
        // Register operators shared roaster
        //inviteToRoom($name, $password, $reason, array $users)
        /*$data[] = array(
            "name" => $params['name'],
            "password" => $params['password'],
            "reason" => $params['reason'],
            'users' => json_decode($params['users'], true)
        );*/

        if ($params['handler'] == 'rpc') {
            try {
                $rpc = new \GameNet\Jabber\RpcClient(array(
                    'server' => $params['rpc_server'],
                    'host' => $params['xmpp_host'],
                    'account_host' => $params['rpc_account_host'],
                    'username' => $params['rpc_username'],
                    'password' => $params['rpc_password']
                ));

                $infoAffiliation = $rpc->setRoomAffiliation($params['name'],$params['user'], $params['type']);
                echo json_encode($infoAffiliation);

            } catch (Exception $e) {
                if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
                    erLhcoreClassLog::write(print_r($e, true));
                }
            }
        }
    }

    public function offlinePost($jsonContent){
        //file_put_contents("logOffline.txt", "-----addUser-first-------------".var_export($jsonContent,true), FILE_APPEND);
        list($to, $from, $body, $token) = explode('&', $jsonContent);
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('insert into lh_xmpp_offline_message (`from`,`to`,`body`,`addtime`) values (:from,:to,:body,:addtime)');
        $stmt->bindValue( ':from', str_replace('from=','',$from));
        $stmt->bindValue( ':to', str_replace('to=','',$to));
        $stmt->bindValue( ':body', str_replace('body=','',$body));
        $stmt->bindValue( ':addtime', date('Y-m-d H:i:s', time()));
        $stmt->execute();
    }

    public function sendOfflineMessage($jsonContent){
        $params = json_decode($jsonContent, true);
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('select * from lh_xmpp_offline_message where `to`=:to and `isread`=0');
        $stmt->bindValue( ':to', $params['to']);
        $stmt->execute();
        $offlinemessage = $stmt->fetchAll();
        foreach($offlinemessage as $k=>$r){
            $message['xmpp_host'] = $params['xmpp_host'];
            $message['from'] = $r['from'];
            $message['to'] = $r['to'];
            $message['body'] = urldecode($r['body']);
            $stmt1 = $db->prepare('update lh_xmpp_offline_message set `isread`=1 where `id`='.$r['id']);
            $stmt1->execute();
            self::iqSendMessage(json_encode($message));
        }
    }

    public function deep_in_array($value, $array) {
        foreach($array as $item) {
            if(!is_array($item)) {
                if ($item == $value) {
                    return true;
                } else {
                    continue;
                }
            }

            if(in_array($value, $item)) {
                return true;
            } else if(self::deep_in_array($value, $item)) {
                return true;
            }
        }
        return false;
    }

    public function iqSendMessage($jsonContent){
        require_once BASE_PATH.'/vendor/autoload.php';
        $logger = new Logger('xmpp');
        $logger->pushHandler(new StreamHandler(BASE_PATH.'\\log.txt', Logger::DEBUG));
        $_POST = json_decode($jsonContent, true);
        $hostname       = $_POST['xmpp_host'] ? $_POST['xmpp_host'] : 'live.vtiger.club';
        if($_POST['xmpp_host'] == 'vtiger.club'){
            $hostname = 'live.vtiger.club';
        }
        $conference     = 'muc.'.$hostname;
        $port           = 5222;
        $connectionType = 'tcp'; //http://live.vtiger.club:5280/http-bind
        $address        = "$connectionType://".$hostname.":$port";

        $username = $_POST['from'].'@'.$hostname; //'admin@vtiger.club';
        $password = '397126845';
        $to = $_POST['to'].'@'.$hostname;
        $body  = $_POST['body'];

        $options = new Options($address);
        $options->setLogger($logger)
            ->setUsername($username)
            ->setPassword($password);
        $client = new Client($options);

        $client->connect();

        $message = new Message;
        $message->setMessage($body)
            ->setTo($to);
        $client->send($message);
        $client->disconnect();
    }

    public function iqSend($jsonContent){
        
        require_once BASE_PATH.'/vendor/autoload.php';
        $logger = new Logger('xmpp');
        $logger->pushHandler(new StreamHandler(BASE_PATH.'\\log.txt', Logger::DEBUG));
        $_POST = json_decode($jsonContent, true);

        $hostname       = $_POST['xmpp_host'] ? $_POST['xmpp_host'] : 'biz.wisehub.cn';
        if($_POST['xmpp_host'] == 'wisehub.cn'){
            $hostname = 'biz.wisehub.cn';
        }
        $conference     = 'muc.'.$hostname;
        $port           = 5222;
        $connectionType = 'tcp'; //http://live.vtiger.club:5280/http-bind
        $address        = "$connectionType://180.76.184.72".":$port";

        /*
         *
         * username xmpp 用户
         * chatjid dep_id/pro_id
         * name 部门或者产品名称
         * dealtype  add del
         */
        $username = $_POST['username']; //'admin@vtiger.club';
        $password = '397126845';
        $dealtype = $_POST['dealtype'];
        $chatjid  = $_POST['chatjid'];
        $tochatjid = $_POST['tochatjid'];
        $name     = $_POST['name'];
        if(!$chatjid){
            echo json_encode(array('chatjid' => 'not empty!'));exit;
        }
        
        $options = new Options($address);
        $options->setLogger($logger)
            ->setTo($hostname)
            ->setUsername($username)
            ->setPassword($password);

        $client = new Client($options);
        
        $client->connect();

        //$client->send(new Roster);
        $rooms = $client->send(new GetRoom);
    
        $xml=simplexml_load_string($rooms);
        $data = json_decode(json_encode($xml),true);

        $nickname = explode('@',$username);

        if(empty($data['pubsub']['items']['item']['storage']) || empty($data)){
            $Chats = array();
        }else{
            $chatrooms = $data['pubsub']['items']['item']['storage']['conference'];
            $i = 0;
            if(is_array($chatrooms[1])){
                foreach($chatrooms as $k=>$r){
                    if(is_array($r)){
                        $Chats[$i]['jid'] = $r['@attributes']['jid'];
                        $Chats[$i]['autojoin'] = $r['@attributes']['autojoin'];
                        $Chats[$i]['name'] = $r['@attributes']['name'];
                        $Chats[$i]['nick'] = $nickname[0];
                        $i++;
                    }
                }
            }else{
                foreach($chatrooms as $k=>$r){
                    if(is_array($r)){
                        $Chats[$i]['jid'] = $r['jid'];
                        $Chats[$i]['autojoin'] = $r['autojoin'];
                        $Chats[$i]['name'] = $r['name'];
                        $Chats[$i]['nick'] = $nickname[0];
                        $i++;
                    }
                }
            }
        }

        //  如何需要删除聊天
        if($dealtype == 'transform'){
            foreach( $Chats as $key => $value ) {
                if(in_array($chatjid.'@'.$conference,$value)) unset($Chats[$key]);
            }

            if(!self::deep_in_array($tochatjid.'@'.$conference, $Chats)){
                array_push($Chats,array('jid' => $tochatjid.'@'.$conference,'autojoin' => 'true','name' => $name, 'nick' => $nickname[0]));
            }
        }else{
            if($dealtype == 'del'){
                foreach( $Chats as $key => $value ) {
                    if(in_array($chatjid.'@'.$conference,$value)) unset($Chats[$key]);
                }
            }else{
                if(!self::deep_in_array($chatjid.'@'.$conference, $Chats)){
                    array_push($Chats,array('jid' => $chatjid.'@'.$conference,'autojoin' => 'true','name' => $name, 'nick' => $nickname[0]));
                }
            }
        }

        // 整理chat 数据
        foreach( $Chats as $key => $value ) {
            if(stripos($value['jid'], $conference) == null || $value['jid'] == '@'.$conference){
                unset($Chats[$key]);
            }
        }
        $presence = '';
        foreach($Chats as $k=>$c){
            $presence .= "<conference jid='".$c['jid']."' autojoin='true' name='".$c['name']."'><nick>".$c['nick']."</nick></conference>";
        }
        //echo $presence;die;

        //$client->send(new Presence(1,'mjmj@conference.vtiger.club','admin'));
        //$client->send(new Message('你好','testxmpp@vtiger.club'));


        // fetch roster list; users and their groups
        //$Roster = $client->send(new Roster);
        // set status to online
        //$client->send(new Presence);

        /*
        // send a message to another user
        $message = new Message;
        $message->setMessage('hello')
            ->setTo('testxmpp@vtiger.club');
        $client->send($message);


        // join a channel
        $channel = new Presence;
        $channel->setTo('ghghgh@conference.vtiger.club')
            ->setPassword('397126845')
            ->setNickName('mynick');
        $client->send($channel);

        // send a message to the above channel
        $message = new Message;
        $message->setMessage('我来了!!!')
            ->setTo('ghghgh@conference.vtiger.club')
            ->setType(Message::TYPE_GROUPCHAT);
        $client->send($message);
        */





        $chatRoom = new Chatroom;
        $chatRoom->setNickname($nickname[0] ? $nickname[0] : $username);
        $chatRoom->setTo($presence);
        $client->send($chatRoom);
        $client->disconnect();


    }
}
