<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/header.php';

$pdo = db();

/* ==========================
   パラメータチェック
========================== */
$groupId = (int)($_GET['group_id'] ?? 0);
$date    = $_GET['date'] ?? '';
$ym      = $_GET['ym'] ?? ''; // 年月引き継ぎ用
$saved   = (int)($_GET['saved'] ?? 0);

if ($groupId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    exit('invalid parameter');
}

/* ym が渡ってない/不正なら date から補完（2026-01-20 → 2026-01） */
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
    $ym = substr($date, 0, 7);
}

/* ==========================
   グループ名取得
========================== */
$stmt = $pdo->prepare("SELECT name FROM groups WHERE id = ?");
$stmt->execute([$groupId]);
$groupName = (string)$stmt->fetchColumn();

/* ==========================
   グループ所属ユーザー取得
========================== */
$stmt = $pdo->prepare("
  SELECT u.id, u.name
  FROM group_users gu
  JOIN users u ON u.id = gu.user_id
  WHERE gu.group_id = ?
  ORDER BY u.id
");
$stmt->execute([$groupId]);
$users = $stmt->fetchAll();
$userCount = count($users);

/* ==========================
   既存収支データ取得（復元）
========================== */
$stmt = $pdo->prepare("
  SELECT user_id, invest_amount, return_amount
  FROM balances
  WHERE group_id = ?
    AND session_key = ?
");
$stmt->execute([$groupId, $date]);

$balanceMap = [];
foreach ($stmt as $row) {
    $balanceMap[(int)$row['user_id']] = $row;
}

/* ==========================
   既存貢献者取得（1日1人）
========================== */
$stmt = $pdo->prepare("
  SELECT c.user_id, u.name AS contributor_name
  FROM contributions c
  JOIN users u ON u.id = c.user_id
  WHERE c.group_id = ?
    AND c.target_date = ?
  LIMIT 1
");
$stmt->execute([$groupId, $date]);
$contributorRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$currentContributor = (int)($contributorRow['user_id'] ?? 0);
$contributorName = (string)($contributorRow['contributor_name'] ?? '');

/* ==========================
   当日サマリー（保存後表示）
========================== */
$summary = null;
if ($saved === 1) {
    $stmt = $pdo->prepare("
      SELECT
        COALESCE(SUM(invest_amount), 0) AS total_invest,
        COALESCE(SUM(return_amount), 0) AS total_return
      FROM balances
      WHERE group_id = ?
        AND session_key = ?
    ");
    $stmt->execute([$groupId, $date]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_invest' => 0,
        'total_return' => 0,
    ];

    $summaryInvest = (int)$summary['total_invest'];
    $summaryReturn = (int)$summary['total_return'];
    $summaryDiff = $summaryReturn - $summaryInvest;
    $shareAmount = intdiv(abs($summaryDiff), 2);
    $summary = [
        'invest' => $summaryInvest,
        'return' => $summaryReturn,
        'diff' => $summaryDiff,
        'share' => $shareAmount,
    ];
}
?>

<div class="container">

  <!-- 戻る（ym引き継ぎ） -->
  <div class="page-nav">
    <a href="calendar.php?group_id=<?= $groupId ?>&ym=<?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?>" class="back-button">
      ← カレンダーへ戻る
    </a>
  </div>

  <?php if ($summary !== null): ?>
    <div class="summary-card">
      <h3>保存した内容</h3>
      <p class="summary-date"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></p>

      <div class="summary-row">
        <span>投資額</span>
        <strong><?= number_format($summary['invest']) ?>円</strong>
      </div>

      <div class="summary-row">
        <span>回収額</span>
        <strong><?= number_format($summary['return']) ?>円</strong>
      </div>

      <?php if ($contributorName !== ''): ?>
        <div class="summary-row">
          <span>貢献者</span>
          <strong><?= htmlspecialchars($contributorName, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($userCount >= 2): ?>
        <div class="summary-row split <?= $summary['diff'] >= 0 ? 'plus' : 'minus' ?>">
          <?php if ($summary['diff'] > 0): ?>
            <span>2人の勝ち額（分配）</span>
            <strong>+<?= number_format($summary['share']) ?>円 / 人</strong>
          <?php elseif ($summary['diff'] < 0): ?>
            <span>2人の負け額（分配）</span>
            <strong>-<?= number_format($summary['share']) ?>円 / 人</strong>
          <?php else: ?>
            <span>2人の勝ち/負け額（分配）</span>
            <strong>±0円 / 人</strong>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- フォーム全体カード -->
  <div class="form-card">

    <h2><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="input-date"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></p>

    <form id="balance-form" method="post" action="api/save_input.php">
      <input type="hidden" name="group_id" value="<?= $groupId ?>">
      <input type="hidden" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">

      <input type="hidden" name="ym" value="<?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?>">

      <?php foreach ($users as $u):
          $uid = (int)$u['id'];
          $invest = (int)($balanceMap[$uid]['invest_amount'] ?? 0);
          $return = (int)($balanceMap[$uid]['return_amount'] ?? 0);
      ?>
        <div class="user-card">
          <h3><?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?></h3>

          <div class="field-row">
            <label>投資</label>
            <input type="number"
                   name="invest[<?= $uid ?>]"
                   min="0"
                   step="100"
                   value="<?= $invest ?>">
          </div>

          <div class="field-row">
            <label>回収</label>
            <input type="number"
                   name="return_amount[<?= $uid ?>]"
                   min="0"
                   step="100"
                   value="<?= $return ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <!-- ==========================
           貢献者カード（1人選択）
      ========================== -->
      <div class="contributor-card">
        <h3>今日の貢献者</h3>
        <p class="field-help">未選択のまま保存すると「貢献者なし」として保存されます。</p>
        <select name="contributor">
          <option value="">なし（貢献者なし）</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>"
              <?= $currentContributor === (int)$u['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($u['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="submit-area">
        <button type="submit">保存する</button>
      </div>

    </form>

  </div>
</div>

<?php require __DIR__ . '/footer.php'; ?>
