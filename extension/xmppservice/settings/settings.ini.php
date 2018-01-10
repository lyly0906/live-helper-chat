<?php 

return array(
    'ahosting' => false,                        // Is it automated hosting enviroment? At the moment keep it false untill automated hosting will be supported
    'subdomain' => '',                          // Under what subdomain it's running
    
    'enabled' => true,                          // Is this enabled in general
    'online_visitors_tracking' => true,         // Should each online visitor get it's own xmpp account?
    'xmpp_host' => 'livechat.ulyncbiz.com',               // E.g xmpp.livehelperchat.com
    
    'xmpp_port' => '5222',                      // 5222 used just for representation purposes
    
    // How many seconds append to last activity. Usefull to force xmpp after last ping to be long
    // Or if you are experience ping tracking issues, this extendes operator timeout by this value
    'append_time' => 0,    
    
    // Should last activity be reset if we found that related web operator logged out
    // But there is xmpp timeout shorter then last activity timeout?
    'check_xmpp_activity_on_web_logout' => true,
    
    // Debug settings
    'debug' => true,                            // Write exceptions in cache/default.log use it for debuging purposes

    // Handler, either rpc either node
    'handler' => 'rpc',
    
    /**
     * NodeJS settings if handler is node
     * */
    'node_api_server' => 'http://117.34.80.209:4567', // E.g http://127.0.0.1:4567', not used if RPC is used
    'secret_key' => '397126845',              // Secret key, node will accept commands only if this key is provided. It must match in nodejs settings defined secret key.
    'host_login' => '117.34.80.209',                // Host where node server should login as user
    
    /**
     * RPC settings
     * */
    // Host where ejabberd RPC server is running. This should be available only to LHC IP, and not available publicly. By default ejabberd listens on 4560 port
    'rpc_server' => 'http://180.76.184.72:4560',
    
    'rpc_username' => 'admin',    // E.g admin
    
    'rpc_password' => '397126845',// E.g password
    
    'rpc_account_host' => 'localhost',// E.g xmpp.example.com

    // Web socket address, it can be also nginx proxy
    // If you are using nginx proxy. Config line could look like 
    // 'ws://'.$_SERVER['HTTP_HOST'].'/websocket'
    // Nginx config example you can find in doc folder
    'bosh_service' => 'ws://180.76.184.72:5280/websocket', // ws://xmpp.livehelperchat.com:5280/websocket

    // Then operator writes a message we can track that event, should on this event message be synced from back office
    // This gives some real time UX, use it only if you are not using nodejs extensions, otherwise it's no point to have it enabled
    'use_notification' => false,

    // Not used at the moment, but may be used in the future
    'prebind_host' => '',

    // Should we create XMPP users when lhc user is created
    'create_xmpp_username_by_lhc_username' => true,

    // On what attribute based XMPP user should be created
    // username or email supported
    'type_for_xmpp_username' => 'username',
    
    // Should new accounts automatically receive all new chat requests
    'xmpp_send_messages' => true,
    
    // Should we delete XMPP related account then LHC user is removed
    'delete_xmpp_by_user_removement' => true
);

?>