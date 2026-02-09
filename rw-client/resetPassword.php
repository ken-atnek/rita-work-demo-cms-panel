<?php
/*
 * [rw-client/resetPassword.php]
 *  - 【事業所】管理画面 -
 *  パスワード再設定ページ
 *
 * [初版]
 *  2026.1.24
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#アカウント情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_account.php';

#=============#
# GETチェック
#-------------#
$selector = isset($_GET['selector']) ? $_GET['selector'] : '';
$validator = isset($_GET['validator']) ? $_GET['validator'] : '';
$showForm = false;
#DBから該当トークンを検索
$resetData = accountPasswordReset_FindBySelectorAndValidator($selector, $validator);
#有効トークン判定
if ($resetData !== null) {
  $showForm = true;
} else {
  $showForm = false;
  #パスワード再設定トークン削除
  #登録用配列：初期化
  $dbDeleteFiledData = array();
  #更新用キー：初期化
  $dbDeleteFiledValue = array();
  $dbDeleteFiledValue['selector'] = array(':selector', $selector, 1);
  #処理モード：[1].新規追加｜[2].更新｜[3].削除
  $processDeleteFlg = 3;
  #実行モード：[1].トランザクション｜[2].即実行
  $exeDeleteFlg = 2;
  $dbDeleteSuccessFlg = SQL_Process($DB_CONNECT, "account_password_resets", $dbDeleteFiledData, $dbDeleteFiledValue, $processDeleteFlg, $exeDeleteFlg);
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
    <link rel="stylesheet" href="../assets/css/log-in.css">
  </head>
  <body>
    <main class="contents-log-in">
      <article class="block-login">
        <div class="item-logo">
          <img src="../assets/images/maun-logo.svg" alt="RITAのロゴ">
        </div>

HTML;

if ($showForm) {
  print <<<HTML
        <form name="newPwForm" method="post" class="reset-password box-log-in">
          <input type="hidden" name="selector" value="{$selector}">
          <input type="hidden" name="validator" value="{$validator}">
          <input type="hidden" name="mode" value="setNewPassword">
          <p>ログインID（メールアドレス）と<br>新しいパスワードを入力してください。</p>
          <span class="title">メールアドレス</span>
          <input type="text" name="userEmail" inputmode="email">
          <span class="title">新しいパスワード</span>
          <input type="password" name="newPassword" required autocomplete="new-password" id="newPassword">
          <span class="title">新しいパスワード(確認用)</span>
          <input type="password" name="confirmPassword" required autocomplete="new-password" id="confirmPassword">
          <!-- <label for="confirmPassword" onclick="togglePassword('confirmPassword')">パスワードを表示</label> -->
          <button type="button" onclick="sendNewPassword()">登録する</button>
        </form>

HTML;
} else {
  print <<<HTML
        <form name="newPwForm" method="post" class="reset-password box-log-in">
          <h2 style="text-align:center;">パスワード再設定不可</h2>
          <div class="text-caution">パスワード再設定用のURLが無効です。<br>再設定用のURLの有効期限は30分です。</div>
          <a href="javascript:void(0);" class="link-pw" onclick="showResetPassword()">パスワードを再設定する</a>
        </form>

HTML;
}
print <<<HTML
      </article>
    </main>
    <script type="text/javascript" src="./assets/js/login.js"></script>
  </body>
</html>
HTML;
