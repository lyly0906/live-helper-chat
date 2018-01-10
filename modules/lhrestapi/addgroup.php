<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
erLhcoreClassRestAPIHandler::validateRequest();
$GroupData = new erLhcoreClassModelGroup();


$definition = array(
    'Name' => new ezcInputFormDefinitionElement(
        ezcInputFormDefinitionElement::REQUIRED, 'unsafe_raw'
    ),
    'Disabled' => new ezcInputFormDefinitionElement(
        ezcInputFormDefinitionElement::OPTIONAL, 'boolean'
    ),
    'MemberGroup' => new ezcInputFormDefinitionElement(
        ezcInputFormDefinitionElement::OPTIONAL, 'string', null, FILTER_REQUIRE_ARRAY
    ),
    'Top' => new ezcInputFormDefinitionElement(
        ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw'
    )

);
$form = new ezcInputForm( INPUT_POST, $definition );

$Errors = array();

if ( !$form->hasValidData( 'Name' ) || $form->Name == '' )
{
    $Errors[] =  erTranslationClassLhTranslation::getInstance()->getTranslation('user/new','Please enter a group name');
}

if ( $form->hasValidData( 'Disabled' ) && $form->Disabled == true ) {
    $GroupData->disabled = 1;
} else {
    $GroupData->disabled = 0;
}
if (count($Errors) == 0)
{
    $GroupData->name    = $form->Name;

    erLhcoreClassUser::getSession()->save($GroupData);

    $GroupRole = new erLhcoreClassModelGroupRole();

    $GroupRole->group_id = $GroupData->id;
    if($form->Top == 'Organization'){
        $GroupRole->role_id = 1;
    }else{
        $GroupRole->role_id = 2;
    }

    erLhcoreClassRole::getSession()->save($GroupRole);

    if ($form->hasValidData('MemberGroup') && !empty($form->MemberGroup)) {
        erLhcoreClassGroupRole::assignGroupMembers($GroupData, $form->MemberGroup);
    }

    echo json_encode(array('error' => false, 'result' => true, 'Groupid' => $GroupData->id));
    exit;

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}
?>
