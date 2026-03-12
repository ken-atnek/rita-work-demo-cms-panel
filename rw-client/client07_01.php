<?php
/*
 * [rw-client/client07_01.php]
 *  - 【事業所】管理画面 -
 *  メッセージ作成
 *
 * [初版]
 *  2026.3.7
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/client/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#問い合わせ情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_inquiries.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'cKey07-01_';
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

#=============#
# POSTチェック
#-------------#
#新規／編集
$method = isset($_GET['method']) ? $_GET['method'] : null;
#checked判定
if ($method === 'plan') {
  $subjectPlanChecked = 'checked';
} else {
  $subjectPlanChecked = '';
}
#件名の初期表示（client03_01.php から method=plan が渡る）
$subjectHiddenValue = '';
$subjectDisplayLabel = '選択してください';
if ($method === 'plan') {
  $subjectHiddenValue = 'plan';
  $subjectDisplayLabel = 'プランについて';
}

#==============#
# 事業所情報取得
#--------------#
#事業所ID
$facId = isset($_SESSION['client_login']['facility_id']) ? $_SESSION['client_login']['facility_id'] : null;
$facilityData = getFacility_FindById($facId);
if (!$facilityData) {
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
    <link rel="stylesheet" href="../assets/css/client07.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-07-01">
      <section class="page-nav">
        <h2>メッセージ管理</h2>
        <nav>
          <a href="./client07_01.php" class="is-active">メッセージ作成</a>
          <a href="./client07_02.php">送信履歴</a>
        </nav>
      </section>
      <section class="container-register">
        <h2>メッセージ作成</h2>
        <form name="inputForm" class="block-form">
          <input type="hidden" name="method" value="{$method}">
          <input type="hidden" name="action" value="checkInput">
          <input type="hidden" name="facId" value="{$facId}">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
          <dl>
            <dt class="is-required">件名</dt>
            <dd>
              <div class="select-subject" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">
                  <input type="hidden" name="selectSubject" value="{$subjectHiddenValue}" data-selectbox-hidden class="required-item" required>
                  <span class="selectbox__value" data-selectbox-value>{$subjectDisplayLabel}</span>
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">
                    <li>
                      <input type="radio" name="selectSubject" value="plan" id="subject01" {$subjectPlanChecked}>
                      <label for="subject01">プランについて</label>
                    </li>
                    <li>
                      <input type="radio" name="selectSubject" value="password" id="subject02">
                      <label for="subject02">パスワードについて</label>
                    </li>
                    <li>
                      <input type="radio" name="selectSubject" value="other" id="subject03">
                      <label for="subject03">その他について</label>
                    </li>
                  </ul>
                </div>
              </div>
            </dd>
          </dl>
          <dl>
            <dt class="is-required">返信方法</dt>
            <dd>
              <div class="select-reply-method" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">
                  <input type="hidden" name="selectReplyMethod" value="" data-selectbox-hidden class="required-item" required>
                  <span class="selectbox__value" data-selectbox-value>選択してください</span>
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">
                    <li>
                      <input type="radio" name="selectReplyMethod" value="email" id="reply01">
                      <label for="reply01">メール</label>
                    </li>
                    <li>
                      <input type="radio" name="selectReplyMethod" value="phone" id="reply02">
                      <label for="reply02">電話</label>
                    </li>
                    <li>
                      <input type="radio" name="selectReplyMethod" value="other" id="reply03">
                      <label for="reply03">その他</label>
                    </li>
                  </ul>
                </div>
              </div>
            </dd>
          </dl>
          <dl>
            <dt class="is-required position-top">メッセージ<br>内容</dt>
            <dd><textarea name="messageBody" class="required-item" required></textarea></dd>
          </dl>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="location.href='./client07_01.php'">戻る</button>
          <button type="button" class="item-check" onclick="checkInput()">入力を確認する</button>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE お知らせモーダル用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-article" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>タイトルが入ります。</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-from">
          <!-- <span class="item-from"></span> -->
          <span class="item-response"></span>
        </div>
        <div class="box-details">
          <div class="wrap-details">
            <div class="item-text">
              <p>本文が入ります。</p>
            </div>
          </div>
          <div class="box-btn">
            <button type="button" onclick="closeModal()" class="btn-bottom-close">戻る</button>
            <button type="button" class="btn-confirm" onclick="">送信する</button>
          </div>
        </div>
      </div>
    </article>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlockAlert">
      <div class="inner-modal">
        <div class="box-title">
          <p>メッセージ作成</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>メッセージの送信が完了しました。</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="location.href='./client07_02.php'">一覧に戻る</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="../assets/js/form.js" defer></script>
    <script src="./assets/js/client07_01.js" defer></script>
  </body>
</html>

HTML;
