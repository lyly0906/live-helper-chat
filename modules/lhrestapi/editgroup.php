<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
erLhcoreClassRestAPIHandler::validateRequest();
$Groups = new erLhcoreClassModelGroup();
//$Groups->fetchGroupByName($_POST['originName']
$Group = erLhcoreClassUser::getSession()->load( 'erLhcoreClassModelGroup',$_POST['Groupid']);

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
    $Group->disabled = 1;
} else {
    $Group->disabled = 0;
}
if (count($Errors) == 0)
{
    $Group->name    = $form->Name;

    erLhcoreClassUser::getSession()->update($Group);

    if ($form->hasValidData('MemberGroup') && !empty($form->MemberGroup)) {
        erLhcoreClassGroupRole::assignGroupMembers($Group, $form->MemberGroup);
    }

    echo json_encode(array('error' => false, 'result' => true));

    exit;

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}
?>
