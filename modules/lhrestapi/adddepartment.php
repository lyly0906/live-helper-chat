<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
erLhcoreClassRestAPIHandler::validateRequest();
$Departament = new erLhcoreClassModelDepartament();

$Errors = array();

if (count($Errors) == 0)
{
    $Departament->name = $_POST['Name'];
    $Departament->product_configuration = json_encode($_POST['product_configuration']);
    file_put_contents("log.txt", "-----2-------_POST['Name']--".$_POST['Name'], FILE_APPEND);
    erLhcoreClassDepartament::getSession()->save($Departament);

    erLhcoreClassDepartament::validateDepartmentCustomWorkHours($Departament);

    erLhcoreClassDepartament::validateDepartmentProducts($Departament);

    file_put_contents("log.txt", "-----2-------Departament--".var_export($Departament,true), FILE_APPEND);

    echo json_encode(array('error' => false, 'result' => true, 'Departamentid' => $Departament->id));
    exit;

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}
?>
