<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/6/26 0026
 * Time: 下午 2:04
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');

erLhcoreClassRestAPIHandler::validateRequest();
$Groups = new erLhcoreClassModelGroup();

$GroupUser = new erLhcoreClassModelGroupUser();
// 老角色名称
$OriginGroupid = $Groups->fetchGroupByName($_POST['originGroupName']);
// 新角色名称
if(!$OriginGroupid){
    $Errors[] = "老角色不存在！";
}
$NewGroupid = $Groups->fetchGroupByName($_POST['newGroupName']);
if(!$NewGroupid){
    $Errors[] = "新角色不存在！";
}

if (count($Errors) == 0) {
    try {
        $OriginGroup = $GroupUser->getList(array('filter' => array('group_id' => $OriginGroupid)));

        $arr = object2array($OriginGroup);

        foreach ($arr as $k => $r) {
            $db = ezcDbInstance::get();
            $stmt = $db->prepare('UPDATE lh_groupuser SET group_id = :group_id WHERE id = :id');
            $stmt->bindValue(':group_id',$NewGroupid);
            $stmt->bindValue(':id',$r->id);
            $stmt->execute();
        }


        $q = ezcDbInstance::get()->createDeleteQuery();
        $q->deleteFrom( 'lh_group' )->where( $q->expr->eq( 'id', $OriginGroupid ) );
        $stmt = $q->prepare();
        $stmt->execute();
        // Transfered chats to user
        $db = ezcDbInstance::get();
        $stmt = $db->prepare('DELETE from lh_grouprole WHERE group_id = :group_id');
        $stmt->bindValue(':group_id',$OriginGroupid);
        $stmt->execute();



        echo json_encode(array('error' => false, 'result' => true));
        exit();
    } catch (Exception $e) {
        echo json_encode(array('error' => true, 'result' => array($e->getMessage())));
        exit;
    }
}else{
    echo json_encode(array('error' => true, 'result' => $Errors));
    exit;
}

function object2array($object)
{
    if (is_object($object)) {
        foreach ($object as $key => $value) {
            $array[$key] = $value;
        }
    } else {
        $array = $object;
    }
    return $array;
}