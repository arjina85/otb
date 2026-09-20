<?php
require_once 'config/config.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

try {
    if (!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')) {
        throw new RuntimeException('Invalid security token. Please reload the page and try again.');
    }

    $token=trim($_POST['token']??'');
    if ($token==='' || strlen($token)>128) throw new RuntimeException('Invalid viewing session.');

    $p=db();
    $s=$p->prepare('SELECT v.*,a.status FROM ad_views v JOIN ads a ON a.id=v.ad_id WHERE v.session_token=? AND v.viewer_id=? LIMIT 1');
    $s->execute([$token,user()['id']]);
    $v=$s->fetch();

    if(!$v || $v['status']!=='approved') throw new RuntimeException('Invalid or unavailable ad session.');
    if((int)$v['credits_awarded']===1) throw new RuntimeException('This ad has already been completed.');

    $elapsed=time()-strtotime($v['started_at']);
    if($elapsed < VIEW_SECONDS) throw new RuntimeException('Viewing time has not completed yet.');

    $p->beginTransaction();
    $p->prepare('UPDATE ad_views SET completed_at=NOW(),seconds_viewed=?,credits_awarded=1 WHERE id=? AND credits_awarded=0')
      ->execute([$elapsed,$v['id']]);
    if($p->lastInsertId() || true){
        $p->prepare('UPDATE users SET credits=credits+? WHERE id=?')->execute([VIEW_REWARD,user()['id']]);
        $p->prepare('INSERT INTO credit_transactions(user_id,amount,type,reference_id) VALUES(?,?,?,?)')
          ->execute([user()['id'],VIEW_REWARD,'ad_view',$v['id']]);
    }
    $p->commit();
    echo json_encode(['ok'=>true,'message'=>'Credit awarded successfully.']);
} catch(Throwable $e) {
    if(isset($p) && $p instanceof PDO && $p->inTransaction()) $p->rollBack();
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
}
