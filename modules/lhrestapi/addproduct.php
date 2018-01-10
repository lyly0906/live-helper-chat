<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
erLhcoreClassRestAPIHandler::validateRequest();

$Product = new erLhAbstractModelProduct;

$Errors = array();

if (count($Errors) == 0)
{


    $Product->name = $_POST['Name'];

    $Product->departament_id = $_POST['departament_id'];
    erLhcoreClassAbstract::getSession()->save($Product);

    $item = new erLhAbstractModelProductDepartament();
    $item->product_id = $Product->id;
    $item->departament_id = $Product->departament_id;
    $item->saveThis();

    echo json_encode(array('error' => false, 'result' => true, 'Productid' => $Product->id));
    exit;

}  else {
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}
?>
