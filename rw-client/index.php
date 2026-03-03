<?php
/*
 * [rw-client/index.php]
 *  - 【事業所】管理画面 -
 *  ログインページ
 *
 * [初版]
 *  2026.1.22
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/client/start_processing.php';

#PW再設定後のメッセージ表示
#eMailNote = 'rita@rita-work.jp';
#$pwNote = 'afact-rita';
#echo password_hash("afact-rita", PASSWORD_BCRYPT) . PHP_EOL;
#exit();

#=============#
# POSTチェック
#-------------#
#ログインエラー
$loginErr = isset($_REQUEST['loginERR']) ? $_REQUEST['loginERR'] : null;

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
        <!-- ログインフォーム -->
        <form name="loginForm" action="check.php" method="post" class="box-log-in">
          <span class="title">メールアドレス</span>
          <input type="text" name="userEmail" inputmode="email">
          <span class="title">パスワード</span>
          <input type="password" name="userPassword" inputmode="email">

HTML;
#ログインエラーが発生しているときだけ警告メッセージを差し込む
if ((isset($_SESSION['login_err']) && $_SESSION['login_err'] != "") && $loginErr == 1) {
  print <<<HTML
          <div class="text-caution">{$_SESSION['login_err']}</div>

HTML;
}
print <<<HTML
          <button type="submit">ログイン</button>
          <a href="javascript:void(0);" class="link-pw" onclick="showResetPassword()">パスワードをお忘れの方はこちら</a>
        </form>
      </article>
    </main>
    <script type="text/javascript" src="./assets/js/login.js"></script>
    <script>
      //IDとPWの両方が入力されているときだけログインボタンを有効化する
      const loginForm = document.forms['loginForm'];
      const userIdInput = loginForm.elements['userEmail'];
      const userPasswordInput = loginForm.elements['userPassword'];
      const submitButton = loginForm.querySelector('button[type="submit"]');
      function toggleSubmitButton() {
        const isUserIdFilled = userIdInput.value.trim() !== '';
        const isUserPasswordFilled = userPasswordInput.value.trim() !== '';
        submitButton.disabled = !(isUserIdFilled && isUserPasswordFilled);
      }
      userIdInput.addEventListener('input', toggleSubmitButton);
      userPasswordInput.addEventListener('input', toggleSubmitButton);
      //初期状態はログインボタンを無効化する
      submitButton.disabled = true;
      toggleSubmitButton();
      //Enterキーでの送信を有効化する
      loginForm.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && !submitButton.disabled) {
          event.preventDefault();
          submitButton.click();
        }
      });
      //IDとPWにフォーカスがあればエラーメッセージ削除
      function clearErrorMessage() {
        const errorMessageElement = document.querySelector('.text-caution');
        if (errorMessageElement) {
          errorMessageElement.style.display = 'none';
        }
      }
      userIdInput.addEventListener('input', clearErrorMessage);
      userPasswordInput.addEventListener('input', clearErrorMessage);
    </script>
  </body>
</html>

HTML;
