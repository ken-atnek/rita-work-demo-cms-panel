<?php
/*
 * [rw-master/inc_header.php]
 *  - 管理画面 -
 *  ヘッダー
 *
 * [初版]
 *  2025.12.15
 */

$previewUrl = DOMAIN_NAME_DEMO;

#***** タグ生成開始 *****#
print <<<HTML
<header class="area-master" id="Header">
  <div class="item-logo">
    <span>
      <img src="../assets/images/maun-logo.svg" alt="RITAのロゴ">
    </span>
  </div>
  <div class="box-head">
    <a href="{$previewUrl}" target="_blank" rel="noopener" class="page-check"><span>サイトを確認</span></a>
    <h1>
      <span>マスターアカウント</span>
      <button type="button" onclick="location.href='./logout.php'">ログアウト</button>
    </h1>
  </div>
  <nav>
    <a href="./master01_01.php" {$master01_active}><span>トップ</span></a>
    <a href="./master02_01.php" {$master02_active}><span>法人管理</span></a>
    <a href="./master03_01.php" {$master03_active}><span>事業所管理</span></a>
    <a href="./master04_01.php" {$master04_active}><span>求職者管理</span></a>
    <a href="#" {$master05_active}><span>運営管理</span></a>
    <a href="#" {$master06_active}><span>明細管理</span></a>
    <a href="#" {$master07_active}><span>メッセージ管理</span></a>
  </nav>
</header>

HTML;
