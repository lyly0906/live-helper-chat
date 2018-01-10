<?php
$chat = erLhcoreClassChat::getSession()->load( 'erLhcoreClassModelChat', $Params['user_parameters']['lead_id']);

if ( erLhcoreClassChat::hasAccessToRead($chat) )
{
    $errors = [];
    erLhcoreClassChatEventDispatcher::getInstance()->dispatch('sugarcrm.createorupdatelead', array('errors' => & $errors));
    if (empty($errors)) {
        try {
            $sugarcrm = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionSugarcrm');
            $lead_id = $chat->chat_variables_array['sugarcrm_lead_id'];
            $leadId = $sugarcrm->doUpdateLeadId($lead_id);

            $tpl = erLhcoreClassTemplate::getInstance('lhsugarcrm/createorupdatelead.tpl.php');
            $tpl->set('lead',$leadId);
            $tpl->set('chat',$chat);

            echo json_encode(array('result' => $tpl->fetch()));
        } catch (Exception $e) {
            $tpl = erLhcoreClassTemplate::getInstance('lhkernel/validation_error.tpl.php');
            $tpl->set('errors',array($e->getMessage()));
            echo json_encode(array('result' => $tpl->fetch()));
        }
    } else {
        $tpl = erLhcoreClassTemplate::getInstance('lhkernel/validation_error.tpl.php');
        $tpl->set('errors',$errors);
        echo json_encode(array('result' => $tpl->fetch()));
    }
}
exit;
?>