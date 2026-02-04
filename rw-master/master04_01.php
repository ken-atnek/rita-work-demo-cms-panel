<?php
/*
 * [rw-master/master04_01.php]
 *  - 管理画面 -
 *  応募者一覧
 *
 * [初版]
 *  2025.12.15
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
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#応募者情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_applications.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';

#===================================#
# フロント側マスタ定義JSONファイル取得
#-----------------------------------#
#取得項目一覧
$jsonMasters = [];
try {
  $jsonMasters = getJson_FrontEndMaster_many([
    'jobCategories',
    'contractPlans'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[master04_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}

#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#================#
# SESSIONチェック
#----------------#
#セッションキー
$searchConditionsSessionKey = 'searchConditions_master04_01';
$pagePrefix = 'mKey04-01_';
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
    'facility_id' => '',
    'facility_name' => '',
    'referName' => '',
    'referJobType' => '',
    'startDay' => '',
    'endDay' => '',
    'initials' => array(),
    'searchMode' => 'all',
    'sortTarget' => 'application_at',
    'applicationSortOrder' => 'desc',
    'interviewSortOrder' => 'desc',
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
$requiredKeys = ['facility_id', 'facility_name', 'referName', 'referJobType', 'startDay', 'endDay', 'initials', 'searchMode', 'sortTarget', 'applicationSortOrder', 'interviewSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $searchConditions)) {
    $allowedSearchModes = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
    $fixedSearchMode = isset($searchConditions['searchMode']) ? (string)$searchConditions['searchMode'] : 'all';
    if (!isset($allowedSearchModes[$fixedSearchMode])) {
      $fixedSearchMode = 'all';
    }
    $fixedSortTarget = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'application_at';
    if ($fixedSortTarget !== 'application_at' && $fixedSortTarget !== 'interview_at') {
      $fixedSortTarget = 'application_at';
    }
    $fixedApplicationSortOrder = isset($searchConditions['applicationSortOrder']) ? strtolower((string)$searchConditions['applicationSortOrder']) : 'desc';
    if ($fixedApplicationSortOrder !== 'asc' && $fixedApplicationSortOrder !== 'desc') {
      $fixedApplicationSortOrder = 'desc';
    }
    $fixedInterviewSortOrder = isset($searchConditions['interviewSortOrder']) ? strtolower((string)$searchConditions['interviewSortOrder']) : 'desc';
    if ($fixedInterviewSortOrder !== 'asc' && $fixedInterviewSortOrder !== 'desc') {
      $fixedInterviewSortOrder = 'desc';
    }
    $searchConditions = array(
      'facility_id' => isset($searchConditions['facility_id']) ? (int)$searchConditions['facility_id'] : '',
      'facility_name' => isset($searchConditions['facility_name']) ? (string)$searchConditions['facility_name'] : '',
      'referName' => isset($searchConditions['referName']) ? (string)$searchConditions['referName'] : '',
      'referJobType' => isset($searchConditions['referJobType']) ? (string)$searchConditions['referJobType'] : '',
      'startDay' => isset($searchConditions['startDay']) ? (string)$searchConditions['startDay'] : '',
      'endDay' => isset($searchConditions['endDay']) ? (string)$searchConditions['endDay'] : '',
      'initials' => isset($searchConditions['initials']) ? (array)$searchConditions['initials'] : array(),
      'searchMode' => $fixedSearchMode,
      'sortTarget' => $fixedSortTarget,
      'applicationSortOrder' => $fixedApplicationSortOrder,
      'interviewSortOrder' => $fixedInterviewSortOrder,
      'displayNumber' => isset($searchConditions['displayNumber']) ? (int)$searchConditions['displayNumber'] : $initialDisplayNumber,
      'pageNumber' => isset($searchConditions['pageNumber']) ? (int)$searchConditions['pageNumber'] : 1
    );
    break;
  }
}
$_SESSION[$searchConditionsSessionKey] = $searchConditions;
#-------------#
#必要データHTMLエスケープ処理
$facilityNameEsc = htmlspecialchars($searchConditions['facility_name'], ENT_QUOTES, 'UTF-8');
$referNameEsc = htmlspecialchars($searchConditions['referName'], ENT_QUOTES, 'UTF-8');
$startDayEsc = htmlspecialchars($searchConditions['startDay'], ENT_QUOTES, 'UTF-8');
$endDayEsc = htmlspecialchars($searchConditions['endDay'], ENT_QUOTES, 'UTF-8');
$noUpDateKeyEsc = htmlspecialchars((string)$noUpDateKey, ENT_QUOTES, 'UTF-8');
#-------------#
#表示件数ページ・表示件数設定
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
#-------------#
#検索モード
$searchMode = isset($searchConditions['searchMode']) ? (string)$searchConditions['searchMode'] : 'all';
#-------------#
#検索項目フォームのアクティブ判定
$activeSearchForm = '';
if ($searchConditions['referName'] !== '' || ($searchConditions['searchMode'] !== '' && $searchConditions['searchMode'] !== 'all') || $searchConditions['referJobType'] !== '' || $searchConditions['startDay'] !== '' || $searchConditions['endDay'] !== '') {
  $activeSearchForm = ' load is-active';
} else {
  $activeSearchForm = '';
}
$activeFilterForm = '';
if (is_array($searchConditions['initials']) && count($searchConditions['initials']) > 0) {
  $activeFilterForm = ' load is-active';
} else {
  $activeFilterForm = '';
}

#==============#
# 応募者一覧取得
#--------------#
$applicationsList = getApplicationList($searchConditions, $pageNumber, $displayNumber);
#応募人数取得
$applicationCounts = [];
$searchModeOptions = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
foreach ($searchModeOptions as $statusKey => $status) {
  $tmpConditions = $searchConditions;
  $tmpConditions['searchMode'] = (string)$statusKey;
  $applicationCounts[$statusKey] = (int)searchApplicationCount($tmpConditions);
}
#キーが無い場合も想定して0で補完
foreach (array_keys($searchModeOptions) as $statusKey) {
  if (array_key_exists($statusKey, $applicationCounts) === false) {
    $applicationCounts[$statusKey] = 0;
  }
}
#応募者表示人数
$modeCount = $applicationCounts[$searchConditions['searchMode']];
#総件数（ページャー用）
$totalPages = (int)ceil($modeCount / $displayNumber);
if ($totalPages < 1) {
  $totalPages = 1;
}
if ($pageNumber < 1) {
  $pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
  $pageNumber = $totalPages;
}
#ソートボタンのアクティブ判定（応募日・面接日 両方に付与）
$applicationSortOrder = isset($searchConditions['applicationSortOrder']) ? strtolower((string)$searchConditions['applicationSortOrder']) : 'desc';
$interviewSortOrder = isset($searchConditions['interviewSortOrder']) ? strtolower((string)$searchConditions['interviewSortOrder']) : 'desc';
if ($applicationSortOrder !== 'asc' && $applicationSortOrder !== 'desc') {
  $applicationSortOrder = 'desc';
}
if ($interviewSortOrder !== 'asc' && $interviewSortOrder !== 'desc') {
  $interviewSortOrder = 'desc';
}
#ソートボタンアクティブクラス
$sortApplicationsAscActive = ($applicationSortOrder === 'asc') ? 'is-active' : '';
$sortApplicationsDescActive = ($applicationSortOrder === 'asc') ? '' : 'is-active';
$sortInterviewDateAscActive = ($interviewSortOrder === 'asc') ? 'is-active' : '';
$sortInterviewDateDescActive = ($interviewSortOrder === 'asc') ? '' : 'is-active';
#ソートモード判別（主ソートのみ：ページ移動等で維持する）
$sortMode = '';
if ($searchConditions['sortTarget'] === 'interview_at') {
  $sortMode = 'sortInterviewDate_' . strtolower($interviewSortOrder);
} else {
  $sortMode = 'sortApplicationsDate_' . strtolower($applicationSortOrder);
}

#inline JS（onclick等）用
$jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$searchModeJs = json_encode((string)($searchConditions['searchMode'] ?? ''), $jsonHex);
$sortModeJs = json_encode((string)$sortMode, $jsonHex);
$searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
$sortModeJsAttr = htmlspecialchars((string)$sortModeJs, ENT_QUOTES, 'UTF-8');

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
    <link rel="stylesheet" href="../assets/css/master04.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-04-01">
      <section class="page-nav">
        <h2>求職者管理</h2>
        <nav>
          <a href="javascript:void(0);" class="is-active">求職者一覧</a>
        </nav>
      </section>
      <section class="container-applicant-list">
        <h2>求職者一覧</h2>
        <form name="searchForm" class="block-search">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKeyEsc}">
          <button type="button" id="btnSwitchSearch" class="btn-switch {$activeSearchForm}" aria-controls="innerSearch" aria-expanded="false"></button>
          <h3>条件で検索</h3>
          <article id="innerSearch" class="{$activeSearchForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-name">
                  <h4>名前</h4>
                  <input type="text" name="referName" value="{$referNameEsc}">
                </li>
                <li class="item-job">
                  <h4>職種</h4>
                  <div class="select-job-type" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#職種が選択されている場合
if (isset($searchConditions['referJobType']) && $searchConditions['referJobType'] != '') {
  foreach ($jobCategories as $jobCategory) {
    if ((string)$searchConditions['referJobType'] === (string)$jobCategory['id']) {
      print <<<HTML
                      <input type="hidden" name="referJobType" value="{$jobCategory['id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$jobCategory['name']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                      <input type="hidden" name="referJobType" value="" data-selectbox-hidden />
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($jobCategories) && is_array($jobCategories) && count($jobCategories) > 0) {
  foreach ($jobCategories as $jobCategory) {
    $jobCategoryIdEsc = htmlspecialchars((string)$jobCategory['id'], ENT_QUOTES, 'UTF-8');
    $jobCategoryNameEsc = htmlspecialchars((string)$jobCategory['name'], ENT_QUOTES, 'UTF-8');
    #checked判定
    $checked = ((string)$searchConditions['referJobType'] === (string)$jobCategory['id']) ? 'checked' : '';
    print <<<HTML
                        <li>
                          <input type="radio" name="referJobType" value="{$jobCategoryIdEsc}" id="job{$jobCategoryIdEsc}" {$checked}>
                          <label for="job{$jobCategoryIdEsc}">{$jobCategoryNameEsc}</label>
                        </li>

HTML;
  }
} else {
  print <<<HTML
                      <li>
                        <input type="radio" name="referJobType" value="1" id="job01">
                        <label for="job01">募集職種が未設定です</label>
                      </li>

HTML;
}
print <<<HTML
                      </ul>
                    </div>
                  </div>
                </li>
                <li class="item-status">
                  <h4>応募状況</h4>
                  <div class="apply-status" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#応募状況ステータスが選択されていたら（UI上は all を表示しない）
$currentSearchMode = $searchConditions['searchMode'] ?? '';
$currentSearchModeUi = '';
if ($currentSearchMode !== '' && isset($applicationStatusMaster[(string)$currentSearchMode])) {
  $currentSearchModeUi = (string)$currentSearchMode;
}
if ($currentSearchModeUi !== '') {
  $appStatusKeyEsc = htmlspecialchars((string)$currentSearchModeUi, ENT_QUOTES, 'UTF-8');
  $appStatusEsc = htmlspecialchars((string)$applicationStatusMaster[$currentSearchModeUi], ENT_QUOTES, 'UTF-8');
  print <<<HTML
                      <input type="hidden" name="searchMode" value="{$appStatusKeyEsc}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$appStatusEsc}</span>
                      <i></i>

HTML;
} else {
  print <<<HTML
                      <input type="hidden" name="searchMode" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>
                      <i></i>

HTML;
}
print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($applicationStatusMaster) && is_array($applicationStatusMaster) && count($applicationStatusMaster) > 0) {
  foreach ($applicationStatusMaster as $appStatusKey => $appStatus) {
    #checked判定
    $checked = ($currentSearchModeUi == $appStatusKey) ? 'checked' : '';
    #zIndex設定
    $zIndexStyleStatus = '';
    if ($appStatusKey === 'registered') {
      $zIndexStyleStatus = 'style="z-index:2;"';
    }
    $appStatusKeyEsc = htmlspecialchars((string)$appStatusKey, ENT_QUOTES, 'UTF-8');
    $appStatusEsc = htmlspecialchars((string)$appStatus, ENT_QUOTES, 'UTF-8');
    print <<<HTML
                        <!-- NOTE インラインでz-indexを付与 -->
                        <li {$zIndexStyleStatus}>
                          <input type="radio" name="searchMode" value="{$appStatusKeyEsc}" id="application_status-{$appStatusKeyEsc}" {$checked}>
                          <label for="application_status-{$appStatusKeyEsc}" class="status-{$appStatusKeyEsc}">{$appStatusEsc}</label>
                        </li>

HTML;
  }
} else {
  print <<<HTML
                        <!-- NOTE インラインでz-indexを付与 -->
                        <li style="z-index: 2">
                          <input type="radio" name="searchMode" value="1" id="application_status-none">
                          <label for="application_status-none" class="status-registered">応募状況ステータスが未設定です</label>
                        </li>

HTML;
}
print <<<HTML
                      </ul>
                    </div>
                  </div>
                </li>
                <li class="item-destination">
                  <h4>応募先</h4>
                  <input type="text" name="facility_name" value="{$facilityNameEsc}">
                </li>
                <li class="item-last-update">
                  <h4>最終更新日</h4>
                  <div class="wrap-period">
                    <input type="date" name="startDay" value="{$startDayEsc}">
                    <span>〜</span>
                    <input type="date" name="endDay" value="{$endDayEsc}">
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
                  <h4>名前</h4>
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
print <<<HTML
                  </div>
                </li>
              </ul>
              <div class="box-btn">
                <button type="button" class="item-clear" onclick="searchConditions('release','none')">絞り込みを解除</button>
              </div>
            </div>
          </article>
        </form>
        <article class="block-search-results">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$modeCount}件</span>が該当</p>
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
                    <input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none',{$sortModeJsAttr})">
                    <label for="display{$number}">{$number}</label>
                  </li>

HTML;
}
print <<<HTML
                </ul>
              </div>
            </div>
          </div>
          <ul class="list-search-results is-admin">
            <li>
              <div>名前</div>
              <div>職種</div>
              <div>応募先</div>
              <div>応募状況</div>
              <div>
                応募日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortApplicationsAscActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortApplicationsDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortApplicationsDescActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortApplicationsDate_desc')"></button>
                </span>
              </div>
              <div>
                面接日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortInterviewDateAscActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortInterviewDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortInterviewDateDescActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortInterviewDate_desc')"></button>
                </span>
              </div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($applicationsList) && count($applicationsList) > 0) {
  $zIndexNo = count($applicationsList);
  #タブごとの表示status（registeredは friend_only を含める）
  if ($searchMode === 'all') {
    $statusesForTab = ['registered', 'friend_only', 'applied', 'interview', 'hired', 'rejected', 'unresponsive'];
  } elseif ($searchMode === 'registered') {
    $statusesForTab = ['registered', 'friend_only'];
  } else {
    $statusesForTab = [$searchMode];
  }
  foreach ($applicationsList as $applicationKey => $application) {
    #Liのz-index設定
    $zIndexStyle = 'style="z-index:' . ($zIndexNo - $applicationKey) . ';"';
    #ライン表示名
    $lineDisplayName = isset($application['line_display_name']) ? (string)$application['line_display_name'] : '';
    $lineDisplayNameEsc = htmlspecialchars($lineDisplayName, ENT_QUOTES, 'UTF-8');
    #名前
    $name = isset($application['applicant_name']) ? (string)$application['applicant_name'] : '';
    $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    #ステータス変更用に名前セット（JS側で使用：$nameがあれば優先）
    $sendStatusChangeName = $name !== '' ? $name : $lineDisplayName;
    #応募中の求人情報を取得
    $appliedJobs = getAllAppliedJobs(
      $application['line_user_id'],
      $statusesForTab,
      $searchConditions['sortTarget'],
      $applicationSortOrder,
      $interviewSortOrder,
      isset($searchConditions['facility_id']) ? (int)$searchConditions['facility_id'] : 0,
      isset($searchConditions['referJobType']) ? (string)$searchConditions['referJobType'] : ''
    );
    print <<<HTML
            <!-- NOTE  インラインでz-indexを付与 -->
            <li {$zIndexStyle} onclick="location.href='./master04_01_01.php?lineUserId={$application['line_user_id']}'">
              <div class="item-name">{$lineDisplayNameEsc}</div>
              <ul class="list-contact">

HTML;
    if (is_array($appliedJobs) && count($appliedJobs) > 0) {
      foreach ($appliedJobs as $jobKey => $jobData) {
        #募集職種
        $jobCategoryName = '';
        if (isset($jobData['status']) && $jobData['status'] === 'friend_only') {
          $jobCategoryName = '';
        } elseif (isset($jobData['job_category_id'])) {
          #選択中のラベル取得
          foreach ($jobCategories as $jobCategory) {
            if ($jobData['job_category_id'] == $jobCategory['id']) {
              $jobCategoryName = $jobCategory['name'];
              break;
            }
          }
        }
        $jobCategoryNameEsc = htmlspecialchars((string)$jobCategoryName, ENT_QUOTES, 'UTF-8');
        #事業所名
        $facilityName = '';
        if (isset($jobData['facility_id']) && $jobData['facility_id'] !== '' && $jobData['facility_id'] !== null) {
          $facilityData = getFacility_FindById($jobData['facility_id']);
          $facilityName = $facilityData['name'] ?? '';
        }
        $facilityNameEsc = htmlspecialchars((string)$facilityName, ENT_QUOTES, 'UTF-8');
        #事業所ID
        $appliedJobsFacId = isset($jobData['facility_id']) ? (int)$jobData['facility_id'] : 0;
        #応募ステータス
        $db_applicationStatus = $jobData['status'] ?? '';
        #応募日
        $appliedDate = !empty($jobData['created_at']) ? date('Y/m/d', strtotime($jobData['created_at'])) : '';
        $appliedDateEsc = htmlspecialchars((string)$appliedDate, ENT_QUOTES, 'UTF-8');
        #面接日
        $interviewAtDate = !empty($jobData['interview_at']) ? date('Y/m/d', strtotime($jobData['interview_at'])) : 'ー';
        $interviewAtDateEsc = htmlspecialchars((string)$interviewAtDate, ENT_QUOTES, 'UTF-8');
        #inline JS用エスケープ
        $jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
        $lineUserIdJs = json_encode((string)($application['line_user_id'] ?? ''), $jsonHex);
        $sendStatusChangeNameJs = json_encode((string)$sendStatusChangeName, $jsonHex);
        $searchModeJs = json_encode((string)($searchConditions['searchMode'] ?? ''), $jsonHex);
        $sortModeJs = json_encode((string)$sortMode, $jsonHex);
        $lineUserIdJsAttr = htmlspecialchars((string)$lineUserIdJs, ENT_QUOTES, 'UTF-8');
        $sendStatusChangeNameJsAttr = htmlspecialchars((string)$sendStatusChangeNameJs, ENT_QUOTES, 'UTF-8');
        $searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
        $sortModeJsAttr = htmlspecialchars((string)$sortModeJs, ENT_QUOTES, 'UTF-8');
        print <<<HTML
                <li>
                  <div class="item-job"><span>{$jobCategoryNameEsc}</span></div>
                  <div class="item-destination">
                    <span>{$facilityNameEsc}</span>
                  </div>

HTML;
        #応募状況ステータスが選択されていたら
        if (isset($db_applicationStatus) && $db_applicationStatus != '' && $db_applicationStatus != 'friend_only') {
          print <<<HTML
                  <div class="wrap-apply-status">
                    <!--NOTE 連番注意 list01-status- -->
                    <div class="apply-status" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
          #応募状況ステータスが選択されていたら
          if (isset($db_applicationStatus) && $db_applicationStatus != '') {
            #選択中のラベル取得
            foreach ($applicationStatusMaster as $appStatusKey => $appStatus) {
              if ($db_applicationStatus == $appStatusKey) {
                $appStatusKeyEsc = htmlspecialchars((string)$appStatusKey, ENT_QUOTES, 'UTF-8');
                $appStatusEsc = htmlspecialchars((string)$appStatus, ENT_QUOTES, 'UTF-8');
                print <<<HTML
                        <input type="hidden" name="application_status{$jobKey}" value="{$appStatusKeyEsc}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$appStatusEsc}</span>
                        <i></i>

HTML;
              }
            }
          } else {
            print <<<HTML
                        <input type="hidden" name="application_status{$jobKey}" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>
                        <i></i>

HTML;
          }
          print <<<HTML
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
          #表示可能リストあればループ処理
          if (isset($applicationStatusMaster) && is_array($applicationStatusMaster) && count($applicationStatusMaster) > 0) {
            foreach ($applicationStatusMaster as $appStatusKey => $appStatus) {
              #checked判定
              $checked = ($db_applicationStatus == $appStatusKey) ? 'checked' : '';
              #zIndex設定
              $zIndexStyleStatus = '';
              if ($appStatusKey === 'registered') {
                $zIndexStyleStatus = 'style="z-index:2;"';
              }
              $appStatusKeyEsc = htmlspecialchars((string)$appStatusKey, ENT_QUOTES, 'UTF-8');
              $appStatusEsc = htmlspecialchars((string)$appStatus, ENT_QUOTES, 'UTF-8');
              $appliedJobsFacIdInt = (int)$appliedJobsFacId;
              $jobIdInt = (int)($jobData['job_id'] ?? 0);
              print <<<HTML
                          <!-- NOTE インラインでz-indexを付与 -->
                          <li {$zIndexStyleStatus}>
                            <input type="radio" name="application_status{$jobKey}" value="{$appStatusKeyEsc}" id="application_status{$jobKey}-{$appStatusKeyEsc}" {$checked} onchange="checkApplicationStatus({$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr});">
                            <label for="application_status{$jobKey}-{$appStatusKeyEsc}" class="status-{$appStatusKeyEsc}">{$appStatusEsc}</label>
                          </li>

HTML;
            }
          } else {
            print <<<HTML
                          <!-- NOTE インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input type="radio" name="application_status{$jobKey}" value="1" id="application_status{$jobKey}-none" checked>
                            <label for="application_status{$jobKey}-none" class="status-registered">応募状況ステータスが未設定です</label>
                          </li>

HTML;
          }
          print <<<HTML
                        </ul>
                      </div>
                    </div>

HTML;
        } else {
          print <<<HTML
                  <div></div>

HTML;
        }
        print <<<HTML
                  </div>

HTML;
        if ($db_applicationStatus == 'friend_only') {
          print <<<HTML
                  <div class="item-date">{$appliedDateEsc}</div>

HTML;
        } else {
          print <<<HTML
                  <div class="item-date date-apply">{$appliedDateEsc}</div>
                  <div class="item-date">{$interviewAtDateEsc}</div>

HTML;
        }
        print <<<HTML
                </li>

HTML;
      }
      print <<<HTML
              </ul>
            </li>

HTML;
    }
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
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>応募状況変更</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>応募状況を変更します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="closeModalToPage('master02_02.php?method=new')">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/master04_01.js" defer></script>
  </body>
</html>

HTML;
