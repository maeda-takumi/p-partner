<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/header.php';

$pdo = db();

$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId <= 0) {
  exit('group_id required');
}

/* ==========================
   表示対象の年月（ym優先）
========================== */
$ym = $_GET['ym'] ?? null;

if ($ym && preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $year  = (int)substr($ym, 0, 4);
  $month = (int)substr($ym, 5, 2);
} else {
  // 旧仕様 y/m でも動くように残す
  $year  = (int)($_GET['y'] ?? date('Y'));
  $month = (int)($_GET['m'] ?? date('n'));
  $ym = sprintf('%04d-%02d', $year, $month);
}

$firstDay = strtotime("$year-$month-01");
$daysInMonth = (int)date('t', $firstDay);

/* 前月/翌月のym */
$prevYm = date('Y-m', strtotime($ym . '-01 -1 month'));
$nextYm = date('Y-m', strtotime($ym . '-01 +1 month'));

/* 収支がある日を取得 */
$stmt = $pdo->prepare("
  SELECT
    DATE(created_at) AS d,
    SUM(return_amount - invest_amount) AS diff
  FROM balances
  WHERE group_id = ?
    AND created_at >= ?
    AND created_at <  ?
  GROUP BY DATE(created_at)
");
$stmt->execute([
  $groupId,
  date('Y-m-01 00:00:00', $firstDay),
  date('Y-m-01 00:00:00', strtotime('+1 month', $firstDay)),
]);

$results = [];
foreach ($stmt as $r) {
  $results[$r['d']] = (int)$r['diff'];
}
?>

<div class="container">

  <!-- ✅ 戻る：index.php に ym を引き継ぐ -->
  <div class="page-nav">
    <a href="index.php?ym=<?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?>" class="back-button">
      ← グループ一覧へ戻る
    </a>
  </div>

  <!-- カレンダーヘッダー -->
  <div class="card calendar-header">

    <!-- ✅ 前月へ（ymを使う） -->
    <!-- <a href="?group_id=<?= $groupId ?>&ym=<?= $prevYm ?>">‹</a> -->

    <h2><?= $year ?>年 <?= $month ?>月</h2>

    <!-- ✅ 翌月へ（ymを使う） -->
    <!-- <a href="?group_id=<?= $groupId ?>&ym=<?= $nextYm ?>">›</a> -->

  </div>

  <!-- 曜日 -->
  <div class="calendar-week">
    <div>日</div><div>月</div><div>火</div><div>水</div>
    <div>木</div><div>金</div><div>土</div>
  </div>

  <!-- カレンダー本体 -->
  <div class="calendar-grid">
    <?php
    $startWeek = (int)date('w', $firstDay);
    for ($i = 0; $i < $startWeek; $i++) {
      echo '<div class="day empty"></div>';
    }

    for ($day = 1; $day <= $daysInMonth; $day++) {
      $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
      $diff = $results[$date] ?? null;

      $class = 'day';
      if ($diff !== null) {
        $class .= $diff >= 0 ? ' plus' : ' minus';
      }

      echo <<<HTML
      <div class="$class" data-date="$date" data-group="$groupId">
        <span class="num">$day</span>
      </div>
      HTML;
    }
    ?>
  </div>

</div>

<?php require __DIR__ . '/footer.php'; ?>
