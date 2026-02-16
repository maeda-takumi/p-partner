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
$ym      = $_GET['ym'] ?? ''; // ✅ 追加（年月引き継ぎ用）

if ($groupId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    exit('invalid parameter');
}

/* ✅ ym が渡ってない/不正なら date から補完（2026-01-20 → 2026-01） */
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
  SELECT user_id
  FROM contributions
  WHERE group_id = ?
    AND target_date = ?
  LIMIT 1
");
$stmt->execute([$groupId, $date]);
$currentContributor = (int)($stmt->fetchColumn() ?: 0);
?>

<div class="container">

  <!-- 戻る（✅ ym引き継ぎ） -->
  <div class="page-nav">
    <a href="calendar.php?group_id=<?= $groupId ?>&ym=<?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?>" class="back-button">
      ← カレンダーへ戻る
    </a>
  </div>

  <!-- ★ フォーム全体カード -->
  <div class="form-card">

    <h2><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="input-date"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></p>

    <form id="balance-form" method="post" action="api/save_input.php">
      <input type="hidden" name="group_id" value="<?= $groupId ?>">
      <input type="hidden" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>">

      <!-- ✅ 保存後の戻り先に使えるように ym も渡す -->
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
        <select name="contributor">
          <option value="">選択してください</option>
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
