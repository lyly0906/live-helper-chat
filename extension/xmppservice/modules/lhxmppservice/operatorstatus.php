<?php 
if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['enabled'] == false) {
    exit;
}

$content = file_get_contents("php://input");
file_put_contents("handleOperatorPing.txt", "-----addUser-first-------------".var_export($content,true), FILE_APPEND);
try {
    erLhcoreClassExtensionXmppserviceHandler::handleOperatorPing($content);
} catch (Exception $e) {
    if (erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionXmppservice')->settings['debug'] == true) {
        erLhcoreClassLog::write(print_r($e,true));
    }
}
exit;

?>