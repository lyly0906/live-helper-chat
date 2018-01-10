<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');
$lhUser = erLhcoreClassUser::instance();


try {
    
    if (!isset($_POST['token'])) {
        throw new Exception('Token not found!');
    }

    $token = $_POST['token'];

    $uSession = erLhcoreClassModelUserSession::findOne(array('filter' => array('token' => $token)));
        
    if ($uSession instanceof erLhcoreClassModelUserSession)
    {
        $uSession->token = '';

        $uSession->updateThis();

        if (is_numeric($uSession->user_id)) {
            $q = ezcDbInstance::get()->createDeleteQuery();

            // User remember
            $q->deleteFrom( 'lh_users_remember' )->where( $q->expr->eq( 'user_id', $q->bindValue($uSession->user_id) ) );
            $stmt = $q->prepare();
            $stmt->execute();

            $db = ezcDbInstance::get();
            $db->query('UPDATE lh_userdep SET last_activity = 0 WHERE user_id = '.$uSession->user_id);
        }
        echo json_encode(
            array('error' => false, 'msg' => 'Token was revoked')
        );            
    } else {
        http_response_code(400);
        echo json_encode(
            array('error' => true, 'msg' => 'Token not found')
        );
    }    

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(
        array('error' => true, 'msg' => $e->getMessage())
    );
}

exit;
?>