<?php
/*
 * [rw-master/master03_03.php]
 *  - 管理画面 -
 *  事業所パスワード設定
 *
 * [初版]
 *  2026.1.20
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#アカウント情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_account.php';
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'mKey03-03_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  if ($key !== 'sKey' && $key !== 'master_login' && $key !== $noUpDateKey) {
    unset($_SESSION[$key]);
  }
}
#セッション本体の初期化
$_SESSION[$noUpDateKey] = array();
#アカウントキー
$_SESSION[$noUpDateKey]['masterKey'] = $_SESSION['master_login']['account_id'];
#データ取得エラー
if ($_SESSION[$noUpDateKey]['masterKey'] < 1) {
  header("Location: ./logout.php");
  exit;
}

#=============#
# POSTチェック
#-------------#
#事業所ID（編集／削除時のみ）
$facId = isset($_GET['facId']) ? $_GET['facId'] : null;
#事業所IDがあれば事業所情報取得
$accountData = null;
$accountStatus = 'edit';
$setTitleLabel = 'パスワード変更';
$setBtnLabel = '変更する';
if ($facId !== null) {
  $facilityData = getFacility_FindById($facId);
  #メールアドレスをキーにアカウント情報を取得
  $accountData = null;
  if (isset($facilityData['email']) && $facilityData['email']  !== '') {
    $accountData = accounts_Waiting_FindByEmail($facilityData['email']);
    if (is_array($accountData) && count($accountData) > 0) {
      $accountStatus = 'new';
      $setTitleLabel = 'パスワード設定';
      $setBtnLabel = '登録する';
    }
  }
} else {
  #事業所ID無し：処理終了
  header("Location: ./master03_01.php");
  exit;
}

$facilityName = isset($facilityData['name']) ? htmlspecialchars($facilityData['name'], ENT_QUOTES, 'UTF-8') : '';

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title>リタワーク｜コントロールパネル(管理者)</title>
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
        <h2>事業所管理</h2>
        <nav>
          <a href="./master03_01_01.php?method=edit&facId={$facId}">事業所情報</a>
          <a href="./master03_02.php?facId={$facId}">求人カード一覧</a>
          <a href="./master03_03.php?facId={$facId}" class="is-active">パスワード設定</a>
        </nav>
      </section>
      <section class="container-password">
        <h2>{$setTitleLabel}<span>{$facilityName}</span></h2>
        <form name="inputForm" class="block-form">
          <input type="hidden" name="method" value="{$accountStatus}">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">

HTML;
if ($accountStatus === 'new') {
  print <<<HTML
          <dl>
            <dt>登録するパスワード</dt>
            <!-- NOTE [is-close]でアイコンチェンジ -->
            <dd>
              <input type="password" name="newPassword" class="required-item" required id="newPassword">
              <button type="button" class="is-close" onclick="togglePassword(this, 'newPassword')"></button>
            </dd>
          </dl>
          <dl>
            <dt>登録するパスワード(確認用)</dt>
            <dd>
              <input type="password" name="confirmNewPassword" class="required-item" required id="confirmNewPassword">
              <button type="button" class="is-close" onclick="togglePassword(this, 'confirmNewPassword')"></button>
            </dd>
          </dl>

HTML;
} else {
  print <<<HTML
          <!-- <dl>
            <dt>現在のパスワード</dt>
            <dd>
              <input type="password" name="currentPassword" class="required-item" required id="currentPassword">
              <button type="button" class="is-close" onclick="togglePassword(this, 'currentPassword')"></button>
            </dd>
          </dl> -->
          <dl>
            <dt>新しいパスワード</dt>
            <!-- NOTE [is-close]でアイコンチェンジ -->
            <dd>
              <input type="password" name="newPassword" class="required-item" required id="newPassword">
              <button type="button" class="is-close" onclick="togglePassword(this, 'newPassword')"></button>
            </dd>
          </dl>
          <dl>
            <dt>新しいパスワード(確認用)</dt>
            <dd>
              <input type="password" name="confirmNewPassword" class="required-item" required id="confirmNewPassword">
              <button type="button" class="is-close" onclick="togglePassword(this, 'confirmNewPassword')"></button>
            </dd>
          </dl>

HTML;
}
print <<<HTML
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="history.back()">戻る</button>
          <button type="button" class="item-check" onclick="checkPasswordSetting('{$facId}','{$accountStatus}')">{$setBtnLabel}</button>
        </div>
        <!--NOTE 修正画面のみ表示 -->
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>パスワード設定</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">

HTML;
if ($accountStatus === 'new') {
  print <<<HTML
          <p>パスワードを設定します。よろしいですか？</p>

HTML;
} else {
  print <<<HTML
          <p>パスワードを変更します。よろしいですか？</p>

HTML;
}
print <<<HTML
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/modal.js" defer></script>
    <script src="../assets/js/form.js" defer></script>
    <script src="./assets/js/master03_03.js" defer></script>
  </body>
</html>

HTML;
