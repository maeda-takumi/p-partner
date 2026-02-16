<?php
$pdo = require __DIR__ . '/../config.php';

$session = $_POST['session_key'];
$user_id = (int)$_POST['user_id'];
$invest  = (int)$_POST['invest_amount'];
$return  = (int)$_POST['return_amount'];

$stmt = $pdo->prepare("
  INSERT INTO balances (session_key, user_id, invest_amount, return_amount)
  VALUES (?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    invest_amount = VALUES(invest_amount),
    return_amount = VALUES(return_amount)
");
$stmt->execute([$session, $user_id, $invest, $return]);

echo json_encode(['status' => 'ok']);
