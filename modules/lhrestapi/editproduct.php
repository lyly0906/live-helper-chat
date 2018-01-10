<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
erLhcoreClassRestAPIHandler::validateRequest();
file_put_contents("log.txt", "-------add--product---1----", FILE_APPEND);
$Departament = new erLhAbstractModelProduct;

$Errors = array();

if (count($Errors) == 0)
{

    $Departament->id = $_POST['id'];
    $Departament->name = $_POST['Name'];
    $Departament->departament_id = $_POST['departament_id'];
    erLhcoreClassAbstract::getSession()->update($Departament);
    echo json_encode(array('error' => false, 'result' => true, 'Productid' => $Departament->id));
    exit;

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}
?>
