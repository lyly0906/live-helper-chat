<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
erLhcoreClassRestAPIHandler::validateRequest();
$Departament = new erLhcoreClassModelDepartament();

$Errors = array();

if (count($Errors) == 0)
{

    $Departament->id = $_POST['id'];
    $Departament->name = $_POST['Name'];

    erLhcoreClassDepartament::getSession()->update($Departament);
    $DepartamentCustomWorkHours = erLhcoreClassDepartament::validateDepartmentCustomWorkHours($Departament, $DepartamentCustomWorkHours);

    erLhcoreClassDepartament::validateDepartmentProducts($Departament);

    echo json_encode(array('error' => false, 'result' => true, 'Departamentid' => $Departament->id));
    exit;

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}
?>
