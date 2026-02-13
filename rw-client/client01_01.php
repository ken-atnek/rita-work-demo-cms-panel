<?php
/*
 * [rw-client/client01_01.php]
 *  - 【事業所】管理画面 -
 *  応募者一覧(トップ)
 *
 * [初版]
 *  2026.1.22
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
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#応募者情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_applications.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';
#事業所へのお知らせ
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facility_notifications.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$searchConditionsSessionKey = 'searchConditions_client01_01';
$pagePrefix = 'cKey01-01_';
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

#=========================#
# 事業所へのお知らせ一覧取得
#-------------------------#
$FacilityNotificationsList = getFacilityNotificationsList('public');

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
    makeLog('[proc_client01_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];

#=============#
# POSTチェック
#-------------#
#事業所ID（編集／削除時のみ）
$facId = isset($_SESSION['client_login']['facility_id']) ? $_SESSION['client_login']['facility_id'] : null;
#事業所IDがあれば事業所情報取得
$jobCardCount = 0;
if ($facId !== null) {
  $facilityData = getFacility_FindById($facId);
  #詳細情報も取得
  $facilityDetails = getFacilityDetails_FindById($facId);
  #テーブル内JSONデコード（安全化）
  $facilityDetailsJson = [];
  if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
    $facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
    if (!is_array($facilityDetailsJson)) {
      $facilityDetailsJson = [];
    }
  }
  #求人カード情報取得
  $jobCardList = getJobList($facId);
  $jobCardCount = count($jobCardList);
} else {
  #事業所ID無し：処理終了
  header("Location: ./client01_01.php");
  exit;
}

#-------------#
#検索・絞り込み条件保持用セッションチェック
$searchConditions = array();
if (isset($_SESSION[$searchConditionsSessionKey]) === false || !is_array($_SESSION[$searchConditionsSessionKey])) {
  #セッション無し：初期化
  $_SESSION[$searchConditionsSessionKey] = array(
    'facility_id' => $facId,
    'searchMode' => 'applied',
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
$requiredKeys = ['facility_id', 'searchMode', 'sortTarget', 'applicationSortOrder', 'interviewSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $searchConditions)) {
    $fixedSearchMode = isset($searchConditions['searchMode']) ? (string)$searchConditions['searchMode'] : 'applied';
    if (!isset($applicationClientSetting[$fixedSearchMode])) {
      $fixedSearchMode = 'applied';
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
      'facility_id' => isset($searchConditions['facility_id']) ? (string)$searchConditions['facility_id'] : '',
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
#表示件数ページ・表示件数設定
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
#-------------#
#検索モード
$searchMode = isset($searchConditions['searchMode']) ? (string)$searchConditions['searchMode'] : 'applied';

#==============#
# 応募者一覧取得
#--------------#
$applicationsList = getApplicationList($searchConditions, $pageNumber, $displayNumber);
#応募人数取得
$applicationCounts = [];
foreach ($applicationClientSetting as $statusKey => $status) {
  $facilityIdForFilter = isset($searchConditions['facility_id']) ? (int)$searchConditions['facility_id'] : 0;
  $applicationCounts[$statusKey] = (int)getApplicationCount($statusKey, $facilityIdForFilter);
}
#キーが無い場合も想定して0で補完
foreach (array_keys($applicationClientSetting) as $statusKey) {
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
#アクティブボタンタグ生成
foreach ($applicationCounts as $statusKey => $count) {
  ${'statusClass_' . $statusKey} = '';
  ${'statusClass_' . $statusKey} = ($searchConditions['searchMode'] === $statusKey) ? ' is-active' : '';
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
#-------------#
#inline JS用エスケープ宣言
$jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

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
    <link rel="stylesheet" href="../assets/css/tiptap_app.css">
    <link rel="stylesheet" href="../assets/css/master01.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-01-01">
      <section class="container-status">
        <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
        <h2>現在の応募状況</h2>
        <nav class="block-status is-client">
          <button type="button" class="status-applied {$statusClass_applied}" onclick="searchConditions('search','applied','{$sortMode}')">
            <span class="label">{$applicationClientSetting['applied']}</span>
            <span class="count">{$applicationCounts['applied']}</span>
          </button>
          <button type="button" class="status-interview {$statusClass_interview}" onclick="searchConditions('search','interview','{$sortMode}')">
            <span class="label">{$applicationClientSetting['interview']}</span>
            <span class="count">{$applicationCounts['interview']}</span>
          </button>
          <button type="button" class="status-hired {$statusClass_hired}" onclick="searchConditions('search','hired','{$sortMode}')">
            <span class="label">{$applicationClientSetting['hired']}</span>
            <span class="count">{$applicationCounts['hired']}</span>
          </button>
          <button type="button" class="status-rejected {$statusClass_rejected}" onclick="searchConditions('search','rejected','{$sortMode}')">
            <span class="label">{$applicationClientSetting['rejected']}</span>
            <span class="count">{$applicationCounts['rejected']}</span>
          </button>
        </nav>
        <article class="block-search-results" data-current-sort-mode="{$sortMode}">
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
                    <input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none','{$sortMode}')">
                    <label for="display{$number}">{$number}</label>
                  </li>

HTML;
}
print <<<HTML
                </ul>
              </div>
            </div>
          </div>
          <ul class="list-search-results is-client">
            <li>
              <div>名前</div>
              <div>職種</div>
              <div>応募状況</div>
              <div>
                応募日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortApplicationsAscActive}" onclick="searchConditions('search','{$searchConditions['searchMode']}','sortApplicationsDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortApplicationsDescActive}" onclick="searchConditions('search','{$searchConditions['searchMode']}','sortApplicationsDate_desc')"></button>
                </span>
              </div>
              <div>
                面接日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortInterviewDateAscActive}" onclick="searchConditions('search','{$searchConditions['searchMode']}','sortInterviewDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortInterviewDateDescActive}" onclick="searchConditions('search','{$searchConditions['searchMode']}','sortInterviewDate_desc')"></button>
                </span>
              </div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($applicationsList) && count($applicationsList) > 0) {
  $zIndexNo = count($applicationsList);
  #タブごとの表示status（registeredは friend_only を含める）
  $statusesForTab = ($searchMode === 'registered') ? ['registered', 'friend_only'] : [$searchMode];
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
      isset($searchConditions['facility_id']) ? (int)$searchConditions['facility_id'] : 0
    );
    print <<<HTML
            <!-- NOTE インラインでz-indexを付与 -->
            <li {$zIndexStyle}>
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
        #inline JS用エスケープ（属性崩壊・注入対策）
        $lineUserIdJs = json_encode((string)($application['line_user_id'] ?? ''), $jsonHex);
        $sendStatusChangeNameJs = json_encode((string)$sendStatusChangeName, $jsonHex);
        $searchModeJs = json_encode((string)($searchConditions['searchMode'] ?? ''), $jsonHex);
        $sortModeJs = json_encode((string)$sortMode, $jsonHex);
        $lineUserIdJsAttr = htmlspecialchars((string)$lineUserIdJs, ENT_QUOTES, 'UTF-8');
        $sendStatusChangeNameJsAttr = htmlspecialchars((string)$sendStatusChangeNameJs, ENT_QUOTES, 'UTF-8');
        $searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
        $sortModeJsAttr = htmlspecialchars((string)$sortModeJs, ENT_QUOTES, 'UTF-8');
        $jobIdInt = (int)($jobData['job_id'] ?? 0);
        print <<<HTML
                <li>
                  <div class="item-job"><span>{$jobCategoryNameEsc}</span></div>

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
            foreach ($applicationStatusClient as $appStatusKey => $appStatus) {
              if ($db_applicationStatus == $appStatusKey) {
                print <<<HTML
                        <input type="hidden" name="application_status{$jobKey}" value="{$appStatusKey}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$appStatus}</span>
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
          if (isset($applicationStatusClient) && is_array($applicationStatusClient) && count($applicationStatusClient) > 0) {
            foreach ($applicationStatusClient as $appStatusKey => $appStatus) {
              #checked判定
              $checked = ($db_applicationStatus == $appStatusKey) ? 'checked' : '';
              #zIndex設定
              $zIndexStyleStatus = '';
              if ($appStatusKey === 'applied') {
                $zIndexStyleStatus = 'style="z-index:2;"';
              }
              print <<<HTML
                          <!-- NOTE インラインでz-indexを付与 -->
                          <li {$zIndexStyleStatus}>
                            <input type="radio" name="application_status{$jobKey}" value="{$appStatusKey}" id="application_status{$jobKey}-{$appStatusKey}" {$checked} onchange="changeApplicationStatus({$appliedJobsFacId}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr});">
                            <label for="application_status{$jobKey}-{$appStatusKey}" class="status-{$appStatusKey}">{$appStatus}</label>
                          </li>

HTML;
            }
          } else {
            print <<<HTML
                          <!-- NOTE インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input type="radio" name="application_status{$jobKey}" value="1" id="application_status{$jobKey}-none" checked>
                            <label for="application_status{$jobKey}-none" class="status-applied">応募状況ステータスが未設定です</label>
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
                  <div class="item-date applied">{$appliedDateEsc}</div>
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
      <section class="container-announcement">
        <h2>運営からのお知らせ</h2>
        <ul class="list-announcement">

HTML;
#表示可能リストあればループ処理
if (isset($FacilityNotificationsList) && is_array($FacilityNotificationsList) && count($FacilityNotificationsList) > 0) {
  foreach ($FacilityNotificationsList as $notification) {
    #お知らせ日付
    $notificationDate = !empty($notification['published_start']) ? date('Y/m/d', strtotime($notification['published_start'])) : '';
    $notificationDateEsc = htmlspecialchars((string)$notificationDate, ENT_QUOTES, 'UTF-8');
    #お知らせタイトル
    $notificationTitle = isset($notification['title']) ? (string)$notification['title'] : '';
    $notificationTitleEsc = htmlspecialchars((string)$notificationTitle, ENT_QUOTES, 'UTF-8');
    #inline JS用エスケープ（属性崩壊・注入対策）
    $actionJs = json_encode('openModal', $jsonHex);
    $actionJsAttr = htmlspecialchars((string)$actionJs, ENT_QUOTES, 'UTF-8');
    $notificationId = (int)($notification['notification_id'] ?? 0);
    #お知らせが開封済みかどうかチェック
    $isOpened = isFacilityNotificationOpened((int)$facId, $notificationId);
    print <<<HTML
          <li onclick="makeNotificationsModal({$actionJsAttr}, {$notificationId})">
            <div class="item-date">{$notificationDateEsc}</div>
            <p>{$notificationTitleEsc}</p>
          </li>

HTML;
  }
} else {
  print <<<HTML
          <li class="no-data" style="display:flex;justify-content:center;align-items:center;padding:2em 0;">
            <div>お知らせはありません。</div>
          </li>

HTML;
}
print <<<HTML
        </ul>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <article class="modal-article" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>タイトルが入ります。</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
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
    <script src="./assets/js/client01_01.js" defer></script>
  </body>
</html>

HTML;
