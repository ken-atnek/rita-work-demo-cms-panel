<?php
/*
 * [rw-client/inc_header.php]
 *  - 【事業所】管理画面 -
 *  ヘッダー
 *
 * [初版]
 *  2026.1.22
 */

#***** タグ生成開始 *****#
print <<<HTML
<header class="area-client" id="Header">
  <div class="item-logo">
    <span>
      <img src="../assets/images/maun-logo.svg" alt="RITAのロゴ">
    </span>
  </div>
  <div class="box-head">
    <a href="#" class="page-check"><span>サイトを確認</span></a>
    <h1>
      <span>{$facilityData['name']}</span>
      <button type="button" onclick="location.href='./logout.php'">ログアウト</button>
    </h1>
  </div>
  <nav>
    <a href="./client01_01.php" {$client01_active}><span>トップ</span></a>
    <a href="./client02_01.php" {$client02_active}><span>事業所管理</span></a>
    <a href="./client03_01.php" {$client03_active}><span>求人カード</span></a>
    <a href="#"><span>求職者管理</span></a>
    <a href="./client05_01.php" {$client05_active}><span>パスワード設定</span></a>
    <a href="#"><span>明細管理</span></a>
    <a href="#"><span>メッセージ管理</span></a>
  </nav>
</header>

HTML;
