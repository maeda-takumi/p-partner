<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>P-Partner</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- ✅ ホーム画面追加用アイコン -->
  <link rel="icon" href="img/icon.png">
  <link rel="apple-touch-icon" href="img/icon.png">

  <link rel="stylesheet" href="css/style.css?t=<?= time() ?>">
</head>
<body>
<header class="header">
  <h1>P-Partner</h1>
</header>
<main class="container">
