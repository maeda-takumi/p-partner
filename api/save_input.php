<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
$pdo = db();

$groupId     = (int)($_POST['group_id'] ?? 0);
$date        = $_POST['date'] ?? '';
$contributor = (int)($_POST['contributor'] ?? 0);

if ($groupId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    exit('invalid parameter');
}

$pdo->beginTransaction();

try {
    /* ==========================
       収支保存
    ========================== */
    foreach ($_POST['invest'] as $userId => $invest) {
        $return = (int)($_POST['return_amount'][$userId] ?? 0);

        $stmt = $pdo->prepare("
          INSERT INTO balances
            (group_id, user_id, session_key, invest_amount, return_amount, created_at)
          VALUES (?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            invest_amount = VALUES(invest_amount),
            return_amount = VALUES(return_amount)
        ");
        $stmt->execute([
            $groupId,
            (int)$userId,
            $date,
            (int)$invest,
            $return,
            $date . ' 12:00:00'
        ]);
    }

    /* ==========================
       貢献者（1日1人）
    ========================== */
    $pdo->prepare("
      DELETE FROM contributions
      WHERE group_id = ? AND target_date = ?
    ")->execute([$groupId, $date]);

    if ($contributor > 0) {
        $pdo->prepare("
          INSERT INTO contributions (group_id, user_id, target_date)
          VALUES (?, ?, ?)
        ")->execute([$groupId, $contributor, $date]);
    }

    $pdo->commit();

    /* ==========================
       保存完了 → 入力画面へ戻し、サマリー表示
    ========================== */
    $ym = $_POST['ym'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = substr($date, 0, 7);
    }

    $params = http_build_query([
        'group_id' => $groupId,
        'date' => $date,
        'ym' => $ym,
        'saved' => 1,
    ]);

    header('Location: ../input.php?' . $params);
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    exit('save error: ' . $e->getMessage());
}
