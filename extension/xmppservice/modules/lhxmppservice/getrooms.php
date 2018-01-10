<?php
header('Content-type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['enabled'] == false) {
    exit;
}

$content = file_get_contents("php://input");
try {
    erLhcoreClassExtensionXmppserviceHandler::getRooms($content);
} catch (Exception $e) {
    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
        erLhcoreClassLog::write(print_r($e,true));
    }
}
exit;

?>