<?php 

$sugarcrm = erLhcoreClassModule::getExtensionInstance('erLhcoreClassExtensionSugarcrm');

$chat = erLhcoreClassChat::getSession()->load( 'erLhcoreClassModelChat', $Params['user_parameters']['lead_id']);

$leadid = $chat->chat_variables_array['sugarcrm_lead_id'];

$lead = $sugarcrm->getLeadById($leadid);

$tpl = erLhcoreClassTemplate::getInstance('lhsugarcrm/getleadfields.tpl.php');
$tpl->set('lead',$lead);
$tpl->set('chat',$chat);
echo json_encode(array('result' => $tpl->fetch()));

exit;
?>