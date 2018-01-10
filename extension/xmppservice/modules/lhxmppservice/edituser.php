<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['enabled'] == false) {
    exit;
}

$content = file_get_contents("php://input");
try {
    erLhcoreClassExtensionXmppserviceHandler::editUser($content);
} catch (Exception $e) {
    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
        erLhcoreClassLog::write(print_r($e,true));
    }
}
exit;

?>