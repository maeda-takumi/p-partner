<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/header.php';

$pdo = db();

/* ==========================
   表示対象月（ym）
========================== */
$ym = $_GET['ym'] ?? date('Y-m'); // 例: 2026-01
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $ym = date('Y-m');
}

$start = $ym . '-01';
$end   = date('Y-m-d', strtotime($start . ' +1 month'));

/* ==========================
   スライダーの min/max（月範囲）
   balances の created_at 最古〜最新 を基準にする
========================== */
$range = $pdo->query("
  SELECT
    MIN(created_at) AS min_dt,
    MAX(created_at) AS max_dt
  FROM balances
")->fetch(PDO::FETCH_ASSOC);

$now = new DateTimeImmutable(date('Y-m-01'));

$minBase = !empty($range['min_dt'])
  ? (new DateTimeImmutable(substr($range['min_dt'], 0, 10)))->modify('first day of this month')
  : $now->modify('-24 months');

$maxBase = !empty($range['max_dt'])
  ? (new DateTimeImmutable(substr($range['max_dt'], 0, 10)))->modify('first day of this month')
  : $now->modify('+3 months');

/* バッファ（少し広めに） */
$minDt = $minBase->modify('-2 months');
$maxDt = $maxBase->modify('+2 months');

$target = new DateTimeImmutable($ym . '-01');

/* monthIndex = Y*12 + (m-1) */
$toIndex = fn(DateTimeImmutable $d) => ((int)$d->format('Y')) * 12 + ((int)$d->format('n') - 1);

$minIndex = $toIndex($minDt);
$maxIndex = $toIndex($maxDt);
$curIndex = $toIndex($target);

/* ==========================
   グループ一覧＋収支集計
   - total_* : 全期間
   - month_* : 指定月（created_at）
========================== */
$stmt = $pdo->prepare("
  SELECT
    g.id,
    g.name,
    g.memo,
    g.created_at,

    /* 全期間 */
    COALESCE(t.total_invest, 0) AS total_invest,
    COALESCE(t.total_return, 0) AS total_return,
    COALESCE(t.total_diff, 0) AS total_diff,

    /* 指定月 */
    COALESCE(m.month_invest, 0) AS month_invest,
    COALESCE(m.month_return, 0) AS month_return,
    COALESCE(m.month_diff, 0) AS month_diff

  FROM groups g

  /* 全期間集計 */
  LEFT JOIN (
    SELECT
      group_id,
      SUM(invest_amount) AS total_invest,
      SUM(return_amount) AS total_return,
      SUM(return_amount - invest_amount) AS total_diff
    FROM balances
    GROUP BY group_id
  ) t ON t.group_id = g.id

  /* 月別集計（ここでstart/endを1回しか使わない） */
  LEFT JOIN (
    SELECT
      group_id,
      SUM(invest_amount) AS month_invest,
      SUM(return_amount) AS month_return,
      SUM(return_amount - invest_amount) AS month_diff
    FROM balances
    WHERE created_at >= :start AND created_at < :end
    GROUP BY group_id
  ) m ON m.group_id = g.id

  WHERE g.is_active = 1
  ORDER BY g.created_at DESC
");

$stmt->execute([
  ':start' => $start,
  ':end'   => $end,
]);

$groups = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ==========================
   貢献回数集計（円グラフ用）
   ※月ナビ ym で target_date を絞る
========================== */
$contribStmt = $pdo->prepare("
  SELECT
    c.group_id,
    u.name,
    COUNT(*) AS cnt
  FROM contributions c
  JOIN users u ON u.id = c.user_id
  WHERE c.target_date >= :start
    AND c.target_date <  :end
  GROUP BY c.group_id, c.user_id
");
$contribStmt->execute([
  ':start' => $start, // 例: 2026-01-01
  ':end'   => $end,   // 例: 2026-02-01
]);

$contribRows = $contribStmt->fetchAll(PDO::FETCH_ASSOC);


/* group_id => contrib配列 */
$contribMap = [];
foreach ($contribRows as $r) {
  $gid = (int)$r['group_id'];
  $contribMap[$gid][] = [
    'name' => $r['name'],
    'rate' => (int)$r['cnt'],
  ];
}
?>

<div class="page-index">

  <div class="month-panel">
    <div class="month-nav">
      <a class="month-nav-btn" href="?ym=<?= date('Y-m', strtotime($ym.'-01 -1 month')) ?>" aria-label="前月">←</a>

      <div class="month-nav-label" id="monthLabel">
        <?= date('Y年n月', strtotime($ym . '-01')) ?>
      </div>

      <a class="month-nav-btn" href="?ym=<?= date('Y-m', strtotime($ym.'-01 +1 month')) ?>" aria-label="翌月">→</a>
    </div>

    <input
      type="range"
      id="monthSlider"
      class="month-slider"
      min="<?= $minIndex ?>"
      max="<?= $maxIndex ?>"
      value="<?= $curIndex ?>"
      step="1"
      aria-label="表示月の選択"
    >
  </div>
  <div class="group-list">

    <?php foreach ($groups as $g): ?>
      <?php
        $gid = (int)$g['id'];
        $contribs = $contribMap[$gid] ?? [];
        $monthDiff = (int)$g['month_diff'];
        $totalDiff = (int)$g['total_diff'];
      ?>


      <a href="calendar.php?group_id=<?= $gid ?>&ym=<?= urlencode($ym) ?>"
         class="group-card"
         data-contrib='<?= json_encode($contribs, JSON_UNESCAPED_UNICODE) ?>'>

        <!-- ヘッダ -->
        <div class="group-header">
          <div class="group-title">
            <?= htmlspecialchars($g['name'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="group-sub">
            <?= htmlspecialchars($g['memo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>

        <!-- グラフ -->
        <div class="chart-area">
          <canvas class="contrib-chart"></canvas>
        </div>

        <!-- ✅ 月別収支（合計より上） -->
        <div class="group-stats month">


          <div class="stats-title"><?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?> の収支</div>

          <div>
            <span class="label">投資</span>
            <span class="value"><?= number_format((int)$g['month_invest']) ?>円</span>
          </div>
          <div>
            <span class="label">回収</span>
            <span class="value"><?= number_format((int)$g['month_return']) ?>円</span>
          </div>
          <div>
            <span class="label">差額</span>
            <span class="value <?= $monthDiff >= 0 ? 'plus' : 'minus' ?>">
              <?= number_format($monthDiff) ?>円
            </span>
          </div>
        </div>

        <!-- 合計収支 -->
        <!-- <div class="group-stats total">
          <div class="stats-title">合計収支</div>

          <div>
            <span class="label">合計投資</span>
            <span class="value"><?= number_format((int)$g['total_invest']) ?>円</span>
          </div>
          <div>
            <span class="label">合計回収</span>
            <span class="value"><?= number_format((int)$g['total_return']) ?>円</span>
          </div>
          <div>
            <span class="label">差額</span>
            <span class="value <?= $totalDiff >= 0 ? 'plus' : 'minus' ?>">
              <?= number_format($totalDiff) ?>円
            </span>
          </div>
        </div> -->

      </a>

    <?php endforeach; ?>

  </div>
</div>

<!-- ✅ 月スライダー JS（ym を URL に反映してリロード） -->
<script>
(function(){
  const slider = document.getElementById('monthSlider');
  const label  = document.getElementById('monthLabel');

  if (!slider || !label) return;
  function indexToYm(idx){
    const y = Math.floor(idx / 12);
    const m = (idx % 12) + 1;
    return String(y).padStart(4,'0') + '-' + String(m).padStart(2,'0');
  }

  function indexToLabel(idx){
    const y = Math.floor(idx / 12);
    const m = (idx % 12) + 1;
    return `${y}年${m}月`;
  }

  slider.addEventListener('input', () => {
    label.textContent = indexToLabel(parseInt(slider.value, 10));
  });

  slider.addEventListener('change', () => {
    const ym = indexToYm(parseInt(slider.value, 10));
    const url = new URL(location.href);
    url.searchParams.set('ym', ym);
    location.href = url.toString();
  });
})();
</script>

<?php require __DIR__ . '/footer.php'; ?>
