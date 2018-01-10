<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');

erLhcoreClassRestAPIHandler::validateRequest();
$UserData = new erLhcoreClassModelUser();
$Groups = new erLhcoreClassModelGroup();
$Departments = new erLhcoreClassModelDepartament();
$userParams = $_POST;

$Groupid = $Groups->fetchGroupByName($_POST['Group']);  //通过crm 的角色得到lhc的组id
$userParams['DefaultGroup'] = array($Groupid);
$Departmentid = $Departments->fetchDepartmentByName($_POST['Departament']);  //通过crm 的部门得到lhc的部门id
$userParams['UserDepartament'] = array($Departmentid);

$UserDepartaments = isset($_POST['UserDepartament']) ? $userParams['UserDepartament'] : array();
$userDepartamentsGroup = isset($_POST['DefaultGroup']) ? $userParams['DefaultGroup'] : array();

$userParams = array('show_all_pending' => 1, 'global_departament' => array(), 'groups_can_edit' => true, 'can_edit_groups' => false);
$Errors = erLhcoreClassUserValidator::validateUserNew($UserData, $userParams);

if (count($Errors) == 0) {

    try {

        $db = ezcDbInstance::get();

        $db->beginTransaction();
        $UserData->user_groups_id = array($Groupid);

        erLhcoreClassUser::getSession()->save($UserData);
        $userParams['global_departament'] =  array($Departmentid);

        if (count($userParams['global_departament']) > 0) {
            erLhcoreClassUserDep::addUserDepartaments($userParams['global_departament'], $UserData->id, $UserData);
        }

        $UserData->setUserGroups();

        $userPhotoErrors = erLhcoreClassUserValidator::validateUserPhoto($UserData);

        if ($userPhotoErrors !== false && count($userPhotoErrors) == 0) {
            $UserData->saveThis();
        }

        erLhcoreClassModelDepartamentGroupUser::addUserDepartmentGroups($UserData, erLhcoreClassUserValidator::validateDepartmentsGroup($UserData));

        erLhcoreClassModelUserSetting::setSetting('show_all_pending', $userParams['show_all_pending'], $UserData->id);

        erLhcoreClassChatEventDispatcher::getInstance()->dispatch('user.user_created',array('userData' => & $UserData, 'password' => $UserData->password_front));

        $db->commit();

        echo json_encode(array('error' => false, 'result' => true, 'Userid' => $UserData->id));
        exit;

    } catch (Exception $e) {
        $db->rollback();
        echo json_encode(array('error' => true, 'result' => array($e->getMessage())));
        exit;
    }

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}