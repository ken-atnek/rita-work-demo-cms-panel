<?php
/*
 * [rw-master/master04_01_01.php]
 *  - 管理画面 -
 *  応募者一覧
 *
 * [初版]
 *  2026.02.02
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

#================#
# SESSIONチェック
#----------------#
$pagePrefix = 'mKey04-01_';
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
    makeLog('[proc_master04_01_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];

#==============#
# 事業所一覧取得
#--------------#
$facilityList = getFacilityList();

#=============#
# POSTチェック
#-------------#
#応募者LINE ID
$lineUserId = isset($_GET['lineUserId']) ? (string)$_GET['lineUserId'] : null;
#IDがあれば応募者情報取得
$applicationData = 0;
$applicationMemo = 0;
$viewTitleName = '';
$nameEsc = '';
$lineDisplayNameEsc = '';
$appliedDateEsc = '';
$desiredJobTypeEsc = '';
$memoEsc = '';
if ($lineUserId !== null) {
  #応募者情報取得
  $applicationData = getApplicationBylineUserId($lineUserId);
  if ($applicationData) {
    #応募者データあり：表示データ整形
    $nameEsc = htmlspecialchars($applicationData['applicant_name'], ENT_QUOTES, 'UTF-8');
    $lineDisplayNameEsc = htmlspecialchars($applicationData['line_display_name'], ENT_QUOTES, 'UTF-8');
    $appliedDateEsc = !empty($applicationData['created_at']) ? date('Y/m/d', strtotime(htmlspecialchars($applicationData['created_at'], ENT_QUOTES, 'UTF-8'))) : 'ー';
    $desiredJobTypeEsc = htmlspecialchars($applicationData['desired_job_type'], ENT_QUOTES, 'UTF-8');
    $memoEsc = htmlspecialchars($applicationData['memo'], ENT_QUOTES, 'UTF-8');
    #タイトル表示名
    $viewTitleName = $nameEsc !== '' ? $nameEsc : $lineDisplayNameEsc;
    #応募中の求人情報を取得
    $appliedJobs = getAllAppliedJobs($applicationData['line_user_id']);
    #希望職種纏め
    foreach ($appliedJobs as $job) {
      $jobCategoryName = '';
      if (isset($jobCategories) && is_array($jobCategories) && count($jobCategories) > 0) {
        foreach ($jobCategories as $jobCategory) {
          if ($jobCategory['id'] == $job['job_category_id']) {
            $jobCategoryName = $jobCategory['name'];
            break;
          }
        }
      }
      if ($jobCategoryName !== '') {
        if ($desiredJobTypeEsc !== '') {
          #既にある職種はスキップ
          if (strpos($desiredJobTypeEsc, htmlspecialchars($jobCategoryName, ENT_QUOTES, 'UTF-8')) !== false) {
            continue;
          }
          $desiredJobTypeEsc .= '、' . htmlspecialchars($jobCategoryName, ENT_QUOTES, 'UTF-8');
        } else {
          $desiredJobTypeEsc = htmlspecialchars($jobCategoryName, ENT_QUOTES, 'UTF-8');
        }
      }
    }
  } else {
    #応募者データ無し：処理終了
    header("Location: ./master04_01.php");
    exit;
  }
  #応募者メモ取得
  $applicationMemo = getApplicationMemoBylineUserId($lineUserId);
  // applicants_memo 側に名前があれば優先（管理画面で編集した値を表示）
  $applicantNameFromMemo = is_array($applicationMemo) ? trim((string)($applicationMemo['applicant_name'] ?? '')) : '';
  if ($applicantNameFromMemo !== '') {
    $nameEsc = htmlspecialchars($applicantNameFromMemo, ENT_QUOTES, 'UTF-8');
    $viewTitleName = $nameEsc;
  }
  $applicationMemoEsc = htmlspecialchars($applicationMemo['memo'] ?? '', ENT_QUOTES, 'UTF-8');
} else {
  #応募者ID無し：処理終了
  header("Location: ./master04_01.php");
  exit;
}

#-------------#
# master04_01_01.php は「応募状況での絞り込み無し」固定
$searchConditions = array(
  'line_user_id' => (string)$lineUserId,
  'searchMode' => 'all',
  'sortTarget' => 'application_at',
  'applicationSortOrder' => 'desc',
  'interviewSortOrder' => 'desc',
  'displayNumber' => (int)$initialDisplayNumber,
  'pageNumber' => 1
);
$searchMode = 'all';
#-------------#
#必要データHTMLエスケープ処理
$lineUserIdEsc = htmlspecialchars($searchConditions['line_user_id'], ENT_QUOTES, 'UTF-8');
$noUpDateKeyEsc = htmlspecialchars((string)$noUpDateKey, ENT_QUOTES, 'UTF-8');
$searchModeEsc = htmlspecialchars((string)$searchMode, ENT_QUOTES, 'UTF-8');
#-------------#
#表示件数ページ・表示件数設定
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;

#=======================#
# 応募者の応募求人一覧取得
#-----------------------#
$applicationJobList = getApplicationJobList($searchConditions, $pageNumber, $displayNumber);
#該当件数（ページャー用：全件）
$modeCount = (int)searchApplicationJobCount($searchConditions);
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
    <main class="inner-04-01-01">
      <section class="page-nav">
        <h2>求職者管理</h2>
        <nav>
          <a href="./master04_01.php" class="is-active">求職者一覧</a>
        </nav>
      </section>
      <section class="container-applicant-details">
        <h2>求職者情報<span>{$viewTitleName}</span></h2>
        <a href="javascript:history.back()" class="link-page-back">戻る</a>
        <div class="inner-applicant-details">
          <!-- NOTE 応募者情報フォーム -->
          <form name="searchForm" style="display:none;">
            <input type="hidden" name="noUpDateKey" value="{$noUpDateKeyEsc}">
            <input type="hidden" name="lineId" value="{$lineUserIdEsc}">
            <input type="hidden" name="searchMode" value="{$searchModeEsc}">
            <input type="hidden" name="facility_id" value="0">
          </form>
          <form name="inputForm" class="block-applicant-details">
            <dl>
              <dt>名前</dt>
              <dd class="dd-top">
                <input type="text" name="applicant_name" value="{$nameEsc}" style="max-width: 200px">
                <span class="title">ニックネーム</span>
                <div style="border-color:#ababab; background-color:#eee;">{$lineDisplayNameEsc}</div>
              </dd>
            </dl>
            <dl>
              <dt>登録日</dt>
              <dd>
                <div style="max-width: 190px; border-color:#ababab; background-color:#eee;">{$appliedDateEsc}</div>
              </dd>
            </dl>
            <dl>
              <dt>希望職種</dt>
              <dd><div style="border-color:#ababab; background-color:#eee;">{$desiredJobTypeEsc}</div></dd>
            </dl>
            <dl>
              <dt class="position-top">メモ</dt>
              <dd><textarea name="memo">{$applicationMemoEsc}</textarea></dd>
            </dl>
            <div class="box-btn">
              <button type="button" class="item-confirm">登録する</button>
            </div>
          </form>
          <article class="block-applicant-list">
            <div class="box-head" style="z-index:9999;">
              <p class="announce-results">条件に<span>{$modeCount}件</span>が該当</p>
              <div class="list-display" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">

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
              <button type="button" class="item-register"><span>新規</span></button>
            </div>
            <ul class="list-personal-results">
              <li>
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
                <div>職種</div>
                <div>応募先</div>
                <div>応募状況</div>
                <div>削除</div>
              </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($applicationJobList) && count($applicationJobList) > 0) {
  $zIndexNo = count($applicationJobList);
  foreach ($applicationJobList as $applicationKey => $application) {
    #Liのz-index設定
    $zIndexStyle = 'style="z-index:' . ($zIndexNo - $applicationKey) . ';"';
    #事業所ID
    $appliedJobsFacId = isset($application['facility_id']) ? (int)$application['facility_id'] : 0;
    #事業所名
    $facilityName = '';
    if (isset($application['facility_id']) && $application['facility_id'] !== '' && $application['facility_id'] !== null) {
      $facilityData = getFacility_FindById($application['facility_id']);
      $facilityName = $facilityData['name'] ?? '';
    }
    $facilityNameEsc = htmlspecialchars((string)$facilityName, ENT_QUOTES, 'UTF-8');
    #ライン表示名
    $lineDisplayName = isset($application['line_display_name']) ? (string)$application['line_display_name'] : '';
    $lineDisplayNameEsc = htmlspecialchars($lineDisplayName, ENT_QUOTES, 'UTF-8');
    #名前
    $name = isset($application['applicant_name']) ? (string)$application['applicant_name'] : '';
    $nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    #ステータス変更用に名前セット（JS側で使用：$nameがあれば優先）
    $sendStatusChangeName = $name !== '' ? $name : $lineDisplayName;
    #応募日
    $appliedDate = !empty($application['created_at']) ? date('Y/m/d', strtotime($application['created_at'])) : '';
    $appliedDateEsc = htmlspecialchars((string)$appliedDate, ENT_QUOTES, 'UTF-8');
    #面接日（input[type=date] のvalueは YYYY-MM-DD）
    $interviewAtDateValue = !empty($application['interview_at']) ? date('Y-m-d', strtotime($application['interview_at'])) : '';
    $interviewAtDateValueEsc = htmlspecialchars((string)$interviewAtDateValue, ENT_QUOTES, 'UTF-8');
    #応募ステータス
    $db_applicationStatus = $application['status'] ?? '';
    #ステータス「friend_only」は登録中に変換
    if ($db_applicationStatus === 'friend_only') {
      $db_applicationStatus = 'registered';
    }
    #inline JS用エスケープ
    $jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    $lineUserIdJs = json_encode((string)($application['line_user_id'] ?? ''), $jsonHex);
    $facilityNameJs = json_encode((string)$facilityName, $jsonHex);
    $sendStatusChangeNameJs = json_encode((string)$sendStatusChangeName, $jsonHex);
    $searchModeJs = json_encode((string)($searchConditions['searchMode'] ?? ''), $jsonHex);
    $sortModeJs = json_encode((string)$sortMode, $jsonHex);
    $lineUserIdJsAttr = htmlspecialchars((string)$lineUserIdJs, ENT_QUOTES, 'UTF-8');
    $facilityNameJsAttr = htmlspecialchars((string)$facilityNameJs, ENT_QUOTES, 'UTF-8');
    $sendStatusChangeNameJsAttr = htmlspecialchars((string)$sendStatusChangeNameJs, ENT_QUOTES, 'UTF-8');
    $searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
    $sortModeJsAttr = htmlspecialchars((string)$sortModeJs, ENT_QUOTES, 'UTF-8');

    #更新（onchange）用：キー
    $applicationIdInt = (int)($application['application_id'] ?? 0);
    $appliedJobsFacIdInt = (int)$appliedJobsFacId;
    $jobIdInt = (int)($application['job_id'] ?? 0);
    print <<<HTML
              <!-- NOTE インラインでz-indexを付与 -->
              <li {$zIndexStyle} data-application-id="{$applicationIdInt}">
                <div class="item-apply-date">{$appliedDateEsc}</div>
                <form class="item-interview-date">
                  <input type="date" name="interview_at{$applicationKey}" value="{$interviewAtDateValueEsc}" onfocus="this.dataset.prevValue=this.value;" onchange="confirmSetInterviewAt(this, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});" >
                </form>
                <form class="item-job-type">
                  <div class="select-job-type" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
    #職種が選択されていたら
    if (isset($application['job_category_id']) && $application['job_category_id'] != '') {
      #選択中のラベル取得
      foreach ($jobCategories as $jobCategory) {
        if ($application['job_category_id'] == $jobCategory['id']) {
          print <<<HTML
                      <input type="hidden" name="job_category{$applicationKey}" value="{$jobCategory['id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$jobCategory['name']}</span>

HTML;
          break;
        }
      }
    } else {
      print <<<HTML
                      <input type="hidden" name="job_category{$applicationKey}" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>---</span>

HTML;
    }
    print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
    #表示可能リストあればループ処理
    if (isset($jobCategories) && is_array($jobCategories) && count($jobCategories) > 0) {
      foreach ($jobCategories as $jobCategoryKey => $jobCategory) {
        $jobCategoryNameJs = json_encode((string)($jobCategory['name'] ?? ''), $jsonHex);
        $jobCategoryNameJsAttr = htmlspecialchars((string)$jobCategoryNameJs, ENT_QUOTES, 'UTF-8');
        #checked判定
        $checked = ($application['job_category_id'] == $jobCategory['id']) ? 'checked' : '';
        print <<<HTML
                        <li>
                          <input type="radio" name="job_category{$applicationKey}" value="{$jobCategory['id']}" id="job{$applicationKey}_{$jobCategoryKey}" {$checked} onclick="return confirmChangeJobCategory(event, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$facilityNameJsAttr}, {$jobIdInt}, this.value, {$jobCategoryNameJsAttr}, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});">
                          <label for="job{$applicationKey}_{$jobCategoryKey}">{$jobCategory['name']}</label>
                        </li>

HTML;
      }
    } else {
      print <<<HTML
                        <li>
                          <input type="radio" name="job_category{$applicationKey}" value="1" id="job{$applicationKey}_01">
                          <label for="job{$applicationKey}_01">募集職種が未設定です</label>
                        </li>

HTML;
    }
    print <<<HTML
                      </ul>
                    </div>
                  </div>
                </form>
                <form class="item-destination">
                  <div class="select-destination" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
    #応募先が選択されていたら
    if (isset($appliedJobsFacId) && $appliedJobsFacId != 0) {
      #選択中のラベル取得
      foreach ($facilityList as $facility) {
        if ($appliedJobsFacId == $facility['facility_id']) {
          print <<<HTML
                      <input type="hidden" name="facility{$applicationKey}" value="{$facility['facility_id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$facility['name']}</span>

HTML;
          break;
        }
      }
    } else {
      print <<<HTML
                      <input type="hidden" name="facility{$applicationKey}" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
    }
    print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
    #表示可能リストあればループ処理
    if (isset($facilityList) && is_array($facilityList) && count($facilityList) > 0) {
      foreach ($facilityList as $facilityKey => $facility) {
        $destinationNameJs = json_encode((string)($facility['name'] ?? ''), $jsonHex);
        $destinationNameJsAttr = htmlspecialchars((string)$destinationNameJs, ENT_QUOTES, 'UTF-8');
        #checked判定
        $checked = ($appliedJobsFacId == $facility['facility_id']) ? 'checked' : '';
        print <<<HTML
                        <li>
                          <input type="radio" name="facility{$applicationKey}" value="{$facility['facility_id']}" id="destination{$applicationKey}_{$facilityKey}" {$checked} onclick="return confirmChangeDestination(event, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$destinationNameJsAttr}, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});">
                          <label for="destination{$applicationKey}_{$facilityKey}">{$facility['name']}</label>
                        </li>

HTML;
      }
    } else {
      print <<<HTML
                        <li>
                          <input type="radio" name="facility{$applicationKey}" value="1" id="destination{$applicationKey}_01">
                          <label for="destination{$applicationKey}_01">応募先が未設定です</label>
                        </li>

HTML;
    }
    print <<<HTML
                      </ul>
                    </div>
                  </div>
                </form>
                <form class="item-apply-status">
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
                      <input type="hidden" name="application_status{$applicationKey}" value="{$appStatusKeyEsc}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$appStatusEsc}</span>
                      <i></i>

HTML;
        }
      }
    } else {
      print <<<HTML
                      <input type="hidden" name="application_status{$applicationKey}" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>---</span>
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
        print <<<HTML
                        <!-- NOTE インラインでz-indexを付与 -->
                        <li {$zIndexStyleStatus}>
                          <input type="radio" name="application_status{$applicationKey}" value="{$appStatusKeyEsc}" id="application_status{$applicationKey}_{$appStatusKeyEsc}" {$checked} onclick="return confirmChangeStatus(event, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$facilityNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});">
                          <label for="application_status{$applicationKey}_{$appStatusKeyEsc}" class="status-{$appStatusKeyEsc}">{$appStatusEsc}</label>
                        </li>

HTML;
      }
    } else {
      print <<<HTML
                        <!-- NOTE インラインでz-indexを付与 -->
                        <li style="z-index: 2">
                          <input type="radio" name="application_status{$applicationKey}" value="1" id="application_status{$applicationKey}-none" checked>
                          <label for="application_status{$applicationKey}-none" class="status-registered">応募状況ステータスが未設定です</label>
                        </li>

HTML;
    }
    print <<<HTML
                      </ul>
                    </div>
                  </div>
                </form>
                <div class="item-delate"><button type="button"></button></div>
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
        </div>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="location.href='./master04_01.php'">戻る</button>
        </div>
        <button type="button" class="btn-delate-item">削除する</button>
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
    <script src="./assets/js/master04_01_01.js" defer></script>
  </body>
</html>

HTML;
