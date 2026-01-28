<?php
/*
 * [rw-client/client01_01.php]
 *  - 【事業所】管理画面 -
 *  パスワード確認
 *
 * [初版]
 *  2026.1.23
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/client/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#アカウント情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_account.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'cKey05-01_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  if ($key !== 'sKey' && $key !== 'client_login' && $key !== $noUpDateKey) {
    unset($_SESSION[$key]);
  }
}
#セッション本体の初期化
$_SESSION[$noUpDateKey] = array();
#アカウントキー
$_SESSION[$noUpDateKey]['clientKey'] = $_SESSION['client_login']['account_id'];
#データ取得エラー
if ($_SESSION[$noUpDateKey]['clientKey'] < 1) {
  header("Location: ./logout.php");
  exit;
}

#==============#
# 事業所情報取得
#--------------#
$facilityData = getFacility_FindById($_SESSION['client_login']['facility_id']);
if (!$facilityData) {
  header("Location: ./logout.php");
  exit;
}

#==================#
# アカウント情報取得
#------------------#
$accountData = accounts_FindByEmail($facilityData['email']);
if (!$accountData) {
  header("Location: ./logout.php");
  exit;
}


#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title>リタワーク｜コントロールパネル(事業所)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master03-02.css">
  </head>

  <body>
HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-03-03">
      <section class="page-nav">
        <h2>パスワード管理</h2>
        <nav>
          <a href="javascript:void(0);" class="is-active">パスワードの確認</a>
        </nav>
      </section>
      <section class="container-password">
        <h2>パスワード確認</h2>
        <article class="block-form">
          <dl>
            <dt>現在のパスワード</dt>
            <dd>
              <input type="password" value="{$accountData['password_plain']}" readonly>
              <button type="button"></button>
            </dd>
          </dl>
        </article>
        <div class="bottom-box-btn">
        <button type="button" class="item-back" onclick="history.back();">戻る</button>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <script src="../assets/js/common.js" defer></script>
  </body>
</html>

HTML;
