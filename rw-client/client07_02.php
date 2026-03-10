<?php
/*
 * [rw-client/client07_02.php]
 *  - 【事業所】管理画面 -
 *  メッセージ送信履歴
 *
 * [初版]
 *  2026.3.7
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
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
$searchConditionsSessionKey = 'searchConditions_client07_02';
$pagePrefix = 'cKey07-02_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  #他ページの検索条件はページ移動時に破棄（このページの条件のみ保持）
  $isSearchConditionsKey = ($key === $searchConditionsSessionKey);
  if ($key !== 'sKey' && $key !== 'client_login' && $key !== $noUpDateKey && $isSearchConditionsKey === false) {
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
#事業所ID
$facId = isset($_SESSION['client_login']['facility_id']) ? $_SESSION['client_login']['facility_id'] : null;
$facilityData = getFacility_FindById($facId);
if (!$facilityData) {
  header("Location: ./logout.php");
  exit;
}
#-------------#
#検索・絞り込み条件保持用セッションチェック
$searchConditions = array();
if (isset($_SESSION[$searchConditionsSessionKey]) === false || !is_array($_SESSION[$searchConditionsSessionKey])) {
  #セッション無し：初期化
  $_SESSION[$searchConditionsSessionKey] = array(
    'facilityId' => $facId,
    'category' => '',
    'startDay' => '',
    'endDay' => '',
    'sendedSortOrder' => 'desc',
    'displayNumber' => $initialDisplayNumber,
    'pageNumber' => 1
  );
  #初期値セット
  $searchConditions = $_SESSION[$searchConditionsSessionKey];
} else {
  #既存セッションがあれば変数にセット
  $searchConditions = $_SESSION[$searchConditionsSessionKey];
}
#必須キーが欠けている場合は初期化（運用上は常に揃う前提）
$requiredKeys = ['facilityId', 'category', 'startDay', 'endDay', 'sendedSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $searchConditions)) {
    #欠けているキーがあれば初期化
    $searchConditions = array(
      'facilityId' => $facId,
      'category' => '',
      'startDay' => '',
      'endDay' => '',
      'sendedSortOrder' => 'desc',
      'displayNumber' => $initialDisplayNumber,
      'pageNumber' => 1
    );
    break;
  }
}
$_SESSION[$searchConditionsSessionKey] = $searchConditions;
#-------------#
#件名の初期表示 + checked判定
$subjectHiddenValue = '';
$subjectPlanChecked = '';
$subjectPasswordChecked = '';
$subjectOtherChecked = '';
$subjectDisplayLabel = '選択してください';
switch ($searchConditions['category']) {
  case 'plan':
    $subjectHiddenValue = 'plan';
    $subjectPlanChecked = 'checked';
    $subjectDisplayLabel = 'プランについて';
    break;
  case 'password':
    $subjectHiddenValue = 'password';
    $subjectPasswordChecked = 'checked';
    $subjectDisplayLabel = 'パスワードについて';
    break;
  case 'other':
    $subjectHiddenValue = 'other';
    $subjectOtherChecked = 'checked';
    $subjectDisplayLabel = 'その他';
    break;
  default:
    #その他の値や空文字の場合は初期値のまま
    break;
}
#-------------#
#表示件数ページ・表示件数設定
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
#-------------#
#検索項目フォームのアクティブ判定
$activeSearchForm = '';
if ($searchConditions['category'] !== '' || $searchConditions['startDay'] !== '' || $searchConditions['endDay'] !== '') {
  $activeSearchForm = ' load is-active';
} else {
  $activeSearchForm = '';
}

#==================#
# メッセージ一覧取得
#------------------#
#検索条件を適用してお問い合わせ一覧を取得（必ず facilityId で絞り込み）
$inquiriesList = searchFacilityInquiriesList($searchConditions, $pageNumber, $displayNumber);
#総件数（ページャー用）
$totalInquiriesCount = searchFacilityInquiriesCount($searchConditions);
$totalPages = (int)ceil($totalInquiriesCount / $displayNumber);
if ($totalPages < 1) {
  $totalPages = 1;
}
if ($pageNumber < 1) {
  $pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
  $pageNumber = $totalPages;
}
#該当件数（表示用：総件数）
$inquiriesCount = $totalInquiriesCount;
#ソートボタンのアクティブ判定
$sortSendedDateAscActive = '';
$sortSendedDateDescActive = '';
$sortModeValue = '';
if (strtolower((string)$searchConditions['sendedSortOrder']) === 'asc') {
  $sortSendedDateAscActive = 'is-active';
  $sortModeValue = 'sortSendedDate_asc';
} else {
  $sortSendedDateDescActive = 'is-active';
  $sortModeValue = 'sortSendedDate_desc';
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
    <main class="inner-07-02">
      <section class="page-nav">
        <h2>メッセージ管理</h2>
        <nav>
          <a href="./client07_01.php">メッセージ作成</a>
          <a href="./client07_02.php" class="is-active">送信履歴</a>
        </nav>
      </section>
      <section class="container-detail-list">
        <h2>送信履歴</h2>
        <form name="searchForm" class="block-search">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
          <button type="button" id="btnSwitchSearch" class="btn-switch" aria-controls="innerSearch" aria-expanded="false"></button>
          <h3>条件で検索</h3>
          <article id="innerSearch" class="{$activeSearchForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-application-date">
                  <h4>送信日</h4>
                  <div class="wrap-period">
                    <input type="date" name="searchStartDay" value="{$searchConditions['startDay']}">
                    <span>〜</span>
                    <input type="date" name="searchEndDay" value="{$searchConditions['endDay']}">
                  </div>
                </li>
                <li class="item-subject">
                  <h4>件名</h4>
                  <div class="select-subject" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">
                      <input type="hidden" name="selectSubject" value="{$subjectHiddenValue}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$subjectDisplayLabel}</span>
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">
                        <li>
                          <input type="radio" name="selectSubject" value="plan" id="subject01" {$subjectPlanChecked}>
                          <label for="subject01">プランについて</label>
                        </li>
                        <li>
                          <input type="radio" name="selectSubject" value="password" id="subject02" {$subjectPasswordChecked}>
                          <label for="subject02">パスワードについて</label>
                        </li>
                        <li>
                          <input type="radio" name="selectSubject" value="other" id="subject03" {$subjectOtherChecked}>
                          <label for="subject03">その他</label>
                        </li>
                      </ul>
                    </div>
                  </div>
                </li>
              </ul>
              <div class="box-btn">
                <button type="button" class="item-clear" onclick="searchConditions('reset','none')">条件をクリア</button>
                <button type="button" class="item-search" onclick="searchConditions('search','none')">条件で検索</button>
              </div>
            </div>
          </article>
        </form>
        <article class="block-history-list" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$inquiriesCount}件</span>が該当</p>
            <div class="list-display" data-selectbox>


HTML;
#表示件数格納用変数を初期化
$currentDisplayNumber = isset($displayNumber) ? $displayNumber : $initialDisplayNumber;
#表示数が選択されている場合
foreach ($displayNumberList as $displayNumber) {
  if ($displayNumber === (int)$searchConditions['displayNumber']) {
    $currentDisplayNumber = $displayNumber;
    break;
  }
}
print <<<HTML
              <button type="button" class="selectbox__head" aria-expanded="false">
                <input type="hidden" name="displayNumber" value="{$currentDisplayNumber}" data-selectbox-hidden>
                <span class="selectbox__value" data-selectbox-value>{$currentDisplayNumber}</span>
              </button>
              <div class="list-wrapper">
                <ul class="selectbox__panel">

HTML;
#表示件数選択リストループで差し込む
foreach ($displayNumberList as $number) {
  $checked = ($number === (int)$searchConditions['displayNumber']) ? ' checked' : '';
  print <<<HTML
                  <li>
                    <input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none')">
                    <label for="display{$number}">{$number}</label>
                  </li>

HTML;
}
print <<<HTML
                </ul>
              </div>
            </div>
          </div>
          <ul class="list-search-results">
            <li>
              <div>
                送信日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortSendedDateAscActive}" onclick="searchConditions('search','sortSendedDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortSendedDateDescActive}" onclick="searchConditions('search','sortSendedDate_desc')"></button>
                </span>
              </div>
              <div>件名</div>
              <div>返信希望</div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($inquiriesList) && count($inquiriesList) > 0) {
  foreach ($inquiriesList as $inquiry) {
    #メッセージID
    $inquiryId = isset($inquiry['inquiry_id']) ? (int)$inquiry['inquiry_id'] : 0;
    #送信日
    $sendedDate = isset($inquiry['created_at']) ? date('Y/m/d', strtotime($inquiry['created_at'])) : '';
    #送信時間
    $sendedTime = isset($inquiry['created_at']) ? date('H:i', strtotime($inquiry['created_at'])) : '';
    #タイトル
    $categoryLabel = '';
    switch ($inquiry['category']) {
      case 'plan':
        $categoryLabel = 'プランについて';
        break;
      case 'password':
        $categoryLabel = 'パスワードについて';
        break;
      case 'other':
        $categoryLabel = 'その他';
        break;
      default:
        #その他の値や空文字の場合は空のまま
        break;
    }
    #本文
    $replyMethodLabel = '';
    switch ($inquiry['reply_channel']) {
      case 'email':
        $replyMethodLabel = 'メール';
        break;
      case 'phone':
        $replyMethodLabel = '電話';
        break;
      case 'other':
        $replyMethodLabel = 'その他';
        break;
      default:
        #その他の値や空文字の場合は空のまま
        break;
    }
    print <<<HTML
            <li onclick="makeInquiryModal('openModal', {$inquiryId})" style="cursor:pointer;">
              <div class="item-date">{$sendedDate}<span>{$sendedTime}</span></div>
              <div class="item-name">{$categoryLabel}</div>
              <div class="item-return">{$replyMethodLabel}</div>
            </li>

HTML;
  }
} else {
  print <<<HTML
            <li class="no-data" style="display:flex;justify-content:center;align-items:center;padding:2em 0;">
              <div>該当するデータが存在しません。</div>
            </li>

HTML;
}
print <<<HTML
          </ul>

HTML;
#ページャー表示
print makePagerBoxTag((int)$pageNumber, (int)$totalPages, $pagerDisplayMax, 'movePage');
print <<<HTML
        </article>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="location.href='./client07_02.php'">戻る</button>
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
            <span class="item-date"></span>
            <div class="item-image">
              <picture>
                <source src="#">
                <img src="#" alt="">
              </picture>
            </div>
            <div class="item-text">
              <p>本文が入ります。</p>
            </div>
          </div>
          <button type="button" onclick="closeModal()" class="btn-bottom-close">閉じる</button>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/client07_02.js" defer></script>
  </body>
</html>

HTML;
