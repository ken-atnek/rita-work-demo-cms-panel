<?php
/*
 * [rw-master/master07_01.php]
 *  - 管理画面 -
 *  メッセージ一覧
 *
 * [初版]
 *  2026.3.6
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#問い合わせ情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_inquiries.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$searchConditionsSessionKey = 'searchConditions_master07_01';
$pagePrefix = 'mKey07-01_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  #他ページの検索条件はページ移動時に破棄（このページの条件のみ保持）
  $isSearchConditionsKey = ($key === $searchConditionsSessionKey);
  if ($key !== 'sKey' && $key !== 'master_login' && $key !== $noUpDateKey && $isSearchConditionsKey === false) {
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

#-------------#
#検索・絞り込み条件保持用セッションチェック
$searchConditions = array();
if (isset($_SESSION[$searchConditionsSessionKey]) === false || !is_array($_SESSION[$searchConditionsSessionKey])) {
  #セッション無し：初期化
  $_SESSION[$searchConditionsSessionKey] = array(
    'facilityId' => '',
    'facilityName' => '',
    'startDay' => '',
    'endDay' => '',
    'initials' => array(),
    'handledStatus' => '',
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
$requiredKeys = ['facilityId', 'facilityName', 'startDay', 'endDay', 'initials', 'handledStatus', 'sendedSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $searchConditions)) {
    #欠けているキーがあれば初期化
    $searchConditions = array(
      'facilityId' => '',
      'facilityName' => '',
      'startDay' => '',
      'endDay' => '',
      'initials' => array(),
      'handledStatus' => '',
      'sendedSortOrder' => 'desc',
      'displayNumber' => $initialDisplayNumber,
      'pageNumber' => 1
    );
    break;
  }
}

// マスター側では件名（category）検索は不要のため、残っていても無視する
if (isset($searchConditions['category'])) {
  unset($searchConditions['category']);
}
$_SESSION[$searchConditionsSessionKey] = $searchConditions;
#-------------#
#表示件数ページ・表示件数設定
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
#-------------#
#検索項目フォームのアクティブ判定
$activeSearchForm = '';
if ($searchConditions['facilityName'] !== '' || $searchConditions['startDay'] !== '' || $searchConditions['endDay'] !== '') {
  $activeSearchForm = ' load is-active';
} else {
  $activeSearchForm = '';
}
$activeFilterForm = '';
if ((is_array($searchConditions['initials']) && count($searchConditions['initials']) > 0) || $searchConditions['handledStatus'] !== '') {
  $activeFilterForm = ' load is-active';
} else {
  $activeFilterForm = '';
}

#==================#
# メッセージ一覧取得
#------------------#
#検索条件を適用してお問い合わせ一覧を取得（施設JOIN + 有効施設のみ表示）
$inquiriesList = searchMasterFacilityInquiriesList($searchConditions, $pageNumber, $displayNumber);
#総件数（ページャー用）
$totalInquiriesCount = searchMasterFacilityInquiriesCount($searchConditions);
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
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master07.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-06-01">
      <section class="page-nav">
        <h2>メッセージ管理</h2>
        <nav>
          <a href="./master07_01.php" class="is-active">メッセージ一覧</a>
        </nav>
      </section>
      <section class="container-message-list">
        <h2>メッセージ一覧</h2>
        <form name="searchForm" class="block-search">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
          <button type="button" id="btnSwitchSearch" class="btn-switch {$activeSearchForm}" aria-controls="innerSearch" aria-expanded="false"></button>
          <h3>条件で検索</h3>
          <article id="innerSearch" class="{$activeSearchForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-name">
                  <h4>事業所名</h4>
                  <input type="text" name="searchFacilityName" value="{$searchConditions['facilityName']}">
                </li>
                <li class="item-application-date">
                  <h4>受信日</h4>
                  <div class="wrap-period">
                    <input type="date" name="searchStartDay" value="{$searchConditions['startDay']}">
                    <span>〜</span>
                    <input type="date" name="searchEndDay" value="{$searchConditions['endDay']}">
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
        <form name="filterForm" class="block-filter">
          <button type="button" id="btnSwitchFilter" class="btn-switch {$activeFilterForm}" aria-controls="innerFilter" aria-expanded="false"></button>
          <h3>絞り込み</h3>
          <article id="innerFilter" class="{$activeFilterForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-name">
                  <h4>事業所名</h4>
                  <div class="wrap-select">

HTML;
#表示件数選択リストループで差し込む
foreach ($filterInitialsList as $filterInitialsKey => $filterInitialsValue) {
  #checked判定
  $checked = '';
  if (!is_array($searchConditions['initials'])) {
    $searchConditions['initials'] = array();
  }
  #value設定値生成(「行」を削除)
  $setFilterInitialsValue = str_replace('行', '', $filterInitialsValue);
  foreach ($searchConditions['initials'] as $selectedInitials) {
    if ($selectedInitials === (string)$setFilterInitialsValue) {
      $checked = ' checked';
      break;
    } else {
      $checked = '';
    }
  }
  print <<<HTML
                    <div class="item-check-box">
                      <input type="checkbox" name="searchInitials[]" value="{$setFilterInitialsValue}" id="select-initials{$filterInitialsKey}" {$checked} onchange="searchConditions('search','none')">
                      <label for="select-initials{$filterInitialsKey}">{$filterInitialsValue}</label>
                    </div>


HTML;
}

$statusDoneChecked = ((string)$searchConditions['handledStatus'] === 'done') ? ' checked' : '';
$statusNewChecked = ((string)$searchConditions['handledStatus'] === 'new') ? ' checked' : '';

print <<<HTML
                  </div>
                </li>
                <li class="item-status">
                  <h4>対応</h4>
                  <div>
                    <input type="radio" name="filterStatus" value="done" id="status01" {$statusDoneChecked} onchange="searchConditions('search','none')" />
                    <label for="status01">対応済</label>
                  </div>
                  <div>
                    <input type="radio" name="filterStatus" value="new" id="status02" {$statusNewChecked} onchange="searchConditions('search','none')" />
                    <label for="status02">未対応</label>
                  </div>
                </li>
              </ul>
              <div class="box-btn">
                <button type="button" class="item-clear" onclick="searchConditions('release','none')">絞り込みを解除</button>
              </div>
            </div>
          </article>
        </form>
        <article class="block-vendor-list status-master" data-current-sort-mode="{$sortModeValue}">
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
                受信日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortSendedDateAscActive}" onclick="searchConditions('search','sortSendedDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortSendedDateDescActive}" onclick="searchConditions('search','sortSendedDate_desc')"></button>
                </span>
              </div>
              <div>送信元</div>
              <div>件名</div>
              <div>返信希望</div>
              <div>対応</div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($inquiriesList) && count($inquiriesList) > 0) {
  $zIndexNo = count($inquiriesList);
  foreach ($inquiriesList as $inquiryKey => $inquiry) {
    #Liのz-index設定
    $zIndexStyle = 'style="cursor:pointer; z-index:' . ($zIndexNo - $inquiryKey) . ';"';
    #メッセージID
    $inquiryId = isset($inquiry['inquiry_id']) ? (int)$inquiry['inquiry_id'] : 0;
    #送信日
    $sendedDate = isset($inquiry['created_at']) ? date('Y/m/d', strtotime($inquiry['created_at'])) : '';
    #送信時間
    $sendedTime = isset($inquiry['created_at']) ? date('H:i', strtotime($inquiry['created_at'])) : '';
    #事業所名
    $facilityName = htmlspecialchars((string)($inquiry['facility_name'] ?? ''), ENT_QUOTES, 'UTF-8');
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
    #対応状況（handling_status: new/in_progress/done）
    $handledStatus = isset($inquiry['handling_status']) ? (string)$inquiry['handling_status'] : '';
    $handledStatusLabel = '';
    $checkedNew = '';
    $checkedInProgress = '';
    $checkedDone = '';
    switch ($handledStatus) {
      case 'new':
        $handledStatusLabel = '未対応';
        $checkedNew = ' checked';
        break;
      case 'done':
        $handledStatusLabel = '対応済';
        $checkedDone = ' checked';
        break;
      default:
        break;
    }
    $categoryLabelEsc = htmlspecialchars((string)$categoryLabel, ENT_QUOTES, 'UTF-8');
    $replyMethodLabelEsc = htmlspecialchars((string)$replyMethodLabel, ENT_QUOTES, 'UTF-8');
    $handledStatusLabelEsc = htmlspecialchars((string)$handledStatusLabel, ENT_QUOTES, 'UTF-8');
    print <<<HTML
            <li onclick="makeInquiryModal('openModal', {$inquiryId})" {$zIndexStyle}>
              <div class="item-date">{$sendedDate}<span>{$sendedTime}</span></div>
              <div class="item-name">{$facilityName}</div>
              <div class="item-subject">{$categoryLabelEsc}</div>
              <div class="item-reply">{$replyMethodLabelEsc}</div>
              <div class="item-status" onclick="event.stopPropagation();">
                <div class="select-status" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">
                    <input type="hidden" name="list{$inquiryId}statusMethod" value="{$handledStatus}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$handledStatusLabelEsc}</span>
                    <i></i>
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">
                      <li>
                        <input type="radio" name="list{$inquiryId}statusMethod" value="new" id="list{$inquiryId}-status01" {$checkedNew} onchange="checkInquiriesStatus({$inquiryId}, '{$facilityName}', this.value);">
                        <label for="list{$inquiryId}-status01" class="status-draft">未対応</label>
                      </li>
                      <li>
                        <input type="radio" name="list{$inquiryId}statusMethod" value="done" id="list{$inquiryId}-status03" {$checkedDone} onchange="checkInquiriesStatus({$inquiryId}, '{$facilityName}', this.value);">
                        <label for="list{$inquiryId}-status03" class="status-published">対応済</label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
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
          <button type="button" class="item-back" onclick="location.href='./master07_01.php'">戻る</button>
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
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlockAlert">
      <div class="inner-modal">
        <div class="box-title">
          <p>対応ステータス変更</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>対応ステータスを変更します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="javascript:void(0);">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/master07_01.js" defer></script>
  </body>
</html>

HTML;
