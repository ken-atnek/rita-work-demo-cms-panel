<?php
/*
 * [rw-master/master03_01.php]
 *  - 管理画面 -
 *  事業所一覧
 *
 * [初版]
 *  2025.12.20
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
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$searchConditionsSessionKey = 'searchConditions_master03_01';
$pagePrefix = 'mKey03-01_';
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
    makeLog('[master03_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#契約プランマスタ
$contractPlans = $jsonMasters['contractPlans'] ?? [];

#-------------#
#検索・絞り込み条件保持用セッションチェック
$searchConditions = array();
if (isset($_SESSION[$searchConditionsSessionKey]) === false || !is_array($_SESSION[$searchConditionsSessionKey])) {
  #セッション無し：初期化
  $_SESSION[$searchConditionsSessionKey] = array(
    'facilityId' => '',
    'facilityName' => '',
    'plan' => '',
    'startDay' => '',
    'endDay' => '',
    'initials' => array(),
    'sortTarget' => 'facility_id',
    'idSortOrder' => 'desc',
    'publishedStartSortOrder' => 'desc',
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
$requiredKeys = ['facilityId', 'facilityName', 'plan', 'startDay', 'endDay', 'initials', 'sortTarget', 'idSortOrder', 'publishedStartSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $searchConditions)) {
    #欠けているキーがあれば初期化
    $searchConditions = array(
      'facilityId' => '',
      'facilityName' => '',
      'plan' => '',
      'startDay' => '',
      'endDay' => '',
      'initials' => array(),
      'sortTarget' => 'facility_id',
      'idSortOrder' => 'desc',
      'publishedStartSortOrder' => 'desc',
      'displayNumber' => $initialDisplayNumber,
      'pageNumber' => 1
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
#検索項目フォームのアクティブ判定
$activeSearchForm = '';
if ($searchConditions['facilityId'] !== '' || $searchConditions['facilityName'] !== '' || $searchConditions['plan'] !== '' || $searchConditions['startDay'] !== '' || $searchConditions['endDay'] !== '') {
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
# 事業所一覧取得
#--------------#
#検索条件があれば適用して事業所一覧を取得
if (is_array($searchConditions) && count($searchConditions) > 0) {
  $facilityList = searchFacilityList($searchConditions, $pageNumber, $displayNumber);
} else {
  $facilityList = getFacilityList();
}
#総件数（ページャー用）
$totalFacilityCount = searchFacilityCount($searchConditions);
$totalPages = (int)ceil($totalFacilityCount / $displayNumber);
if ($totalPages < 1) {
  $totalPages = 1;
}
if ($pageNumber < 1) {
  $pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
  $pageNumber = $totalPages;
}
#該当件数（表示用：総件数）
$facilityCount = $totalFacilityCount;
#ソートボタンのアクティブ判定
$sortIdAscActive = '';
$sortIdDescActive = '';
$sortContractDateAscActive = '';
$sortContractDateDescActive = '';
$sortModeValue = '';
if ($searchConditions['sortTarget'] === 'published_start') {
  if (strtolower($searchConditions['publishedStartSortOrder']) === 'asc') {
    $sortContractDateAscActive = 'is-active';
    $sortModeValue = 'sortContractDate_asc';
  } else {
    $sortContractDateDescActive = 'is-active';
    $sortModeValue = 'sortContractDate_desc';
  }
} else {
  if (strtolower($searchConditions['idSortOrder']) === 'asc') {
    $sortIdAscActive = 'is-active';
    $sortModeValue = 'sortId_asc';
  } else {
    $sortIdDescActive = 'is-active';
    $sortModeValue = 'sortId_desc';
  }
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
    <link rel="stylesheet" href="../assets/css/master03.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-03">
      <section class="container-vendor-list">
        <h2>事業所一覧</h2>
        <form name="searchForm" class="block-search">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
          <button type="button" id="btnSwitchSearch" class="btn-switch {$activeSearchForm}" aria-controls="innerSearch" aria-expanded="false"></button>
          <h3>条件で検索</h3>
          <article id="innerSearch" class="{$activeSearchForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-id">
                  <h4>事業所ID</h4>
                  <input type="text" name="searchFacilityId" value="{$searchConditions['facilityId']}">
                </li>
                <li class="item-name">
                  <h4>事業所名</h4>
                  <input type="text" name="searchFacilityName" value="{$searchConditions['facilityName']}">
                </li>
                <li class="item-plan">
                  <h4>契約プラン</h4>
                  <div class="wrap-radio">

HTML;
#checked判定
$checkedLight = '';
$checkedStandard = '';
$checkedPremium = '';
$checkedEnded = '';
if ($searchConditions['plan'] === 'light') {
  $checkedLight = ' checked';
} elseif ($searchConditions['plan'] === 'standard') {
  $checkedStandard = ' checked';
} elseif ($searchConditions['plan'] === 'premium') {
  $checkedPremium = ' checked';
} elseif ($searchConditions['plan'] === 'ended') {
  $checkedEnded = ' checked';
}
print <<<HTML
                    <div class="item-check-box">
                      <input type="radio" id="radio-plan01" name="searchPlan" value="light" {$checkedLight}><label for="radio-plan01">ライトプラン</label>
                    </div>
                    <div class="item-check-box">
                      <input type="radio" id="radio-plan02" name="searchPlan" value="standard" {$checkedStandard}><label for="radio-plan02">スタンダードプラン</label>
                    </div>
                    <div class="item-check-box">
                      <input type="radio" id="radio-plan03" name="searchPlan" value="premium" {$checkedPremium}><label for="radio-plan03">プレミアムプラン</label>
                    </div>
                    <div class="item-check-box">
                      <input type="radio" id="radio-plan04" name="searchPlan" value="ended"{$checkedEnded}><label for="radio-plan04">契約終了</label>
                    </div>
                  </div>
                </li>
                <li class="item-application-date">
                  <h4>掲載日</h4>
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
        <article class="block-vendor-list" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$facilityCount}件</span>が該当</p>
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
                番号
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortIdAscActive}"  onclick="searchConditions('search','sortId_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortIdDescActive}" onclick="searchConditions('search','sortId_desc')"></button>
                </span>
              </div>
              <div>事業所ID</div>
              <div>事業所</div>
              <div>住所</div>
              <div>募集業種</div>
              <div>プラン</div>
              <div>バナー契約</div>
              <div>
                掲載日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortContractDateAscActive}" onclick="searchConditions('search','sortContractDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortContractDateDescActive}" onclick="searchConditions('search','sortContractDate_desc')"></button>
                </span>
              </div>
            </li>

HTML;
#表示可能リストあればループで差し込む
$filterStartDay = isset($searchConditions['startDay']) ? trim((string)$searchConditions['startDay']) : '';
$filterEndDay = isset($searchConditions['endDay']) ? trim((string)$searchConditions['endDay']) : '';
$filterStartTs = ($filterStartDay !== '' ? strtotime($filterStartDay . ' 00:00:00') : null);
$filterEndTs = ($filterEndDay !== '' ? strtotime($filterEndDay . ' 23:59:59') : null);
if ($filterStartTs === false) {
  $filterStartTs = null;
}
if ($filterEndTs === false) {
  $filterEndTs = null;
}
if (is_array($facilityList) && count($facilityList) > 0) {
  foreach ($facilityList as $facility) {
    $facId = $facility['facility_id'];
    $facCode = convertData($facility['facility_code']);
    $facName = convertData($facility['name']);
    $facZipCode = convertData($facility['postal_code']);
    $facAddress = convertData($facility['prefecture'] . $facility['city'] . $facility['address_line']);
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
    #バナー契約情報
    $specialBannerEnabled = '';
    if (isset($facilityDetailsJson['specialBanner']['enabled']) && $facilityDetailsJson['specialBanner']['enabled'] == true) {
      $specialBannerEnabled = 'is-active';
    }
    #求人カード情報
    $jobCardListRaw = getJobList($facId);
    #検索プランが指定されている場合は、表示側でも求人カードを絞り込み
    $jobCardList = [];
    $selectedPlan = isset($searchConditions['plan']) ? (string)$searchConditions['plan'] : '';
    if (!is_array($jobCardListRaw)) {
      $jobCardListRaw = [];
    }
    if ($selectedPlan === '') {
      $jobCardList = $jobCardListRaw;
    } elseif ($selectedPlan === 'ended') {
      foreach ($jobCardListRaw as $jobCard) {
        if (isset($jobCard['is_active']) && (int)$jobCard['is_active'] === 99) {
          $jobCardList[] = $jobCard;
        }
      }
    } else {
      foreach ($jobCardListRaw as $jobCard) {
        if (!isset($jobCard['contract_plan_id'])) {
          continue;
        }
        if ((string)$jobCard['contract_plan_id'] !== $selectedPlan) {
          continue;
        }
        #通常プラン検索時は「契約終了(99)」を表示から除外（SQL側の判定と合わせる）
        if (isset($jobCard['is_active']) && (int)$jobCard['is_active'] === 99) {
          continue;
        }
        $jobCardList[] = $jobCard;
      }
    }
    #掲載日検索（startDay/endDay）が指定されている場合は、表示する求人カードも日付範囲内に限定
    if ($filterStartTs !== null || $filterEndTs !== null) {
      $filteredJobCards = [];
      foreach ($jobCardList as $jobCard) {
        if (!isset($jobCard['published_start'])) {
          continue;
        }
        $publishedTs = strtotime((string)$jobCard['published_start']);
        if ($publishedTs === false) {
          continue;
        }
        if ($filterStartTs !== null && $publishedTs < $filterStartTs) {
          continue;
        }
        if ($filterEndTs !== null && $publishedTs > $filterEndTs) {
          continue;
        }
        $filteredJobCards[] = $jobCard;
      }
      $jobCardList = $filteredJobCards;
    }
    #求人カウント数（表示する分のみ）
    $jobCardCount = count($jobCardList);
    print <<<HTML
            <li onclick="location.href='./master03_01_01.php?method=edit&facId={$facId}'">
              <div class="item-number">{$facId}</div>
              <div class="item-id">{$facCode}</div>
              <div class="item-name">{$facName}</div>
              <div class="item-add"><span>〒{$facZipCode}</span>{$facAddress}</div>
              <!--NOTE リストの数をクラスで付与（list-count-**） -->
              <div class="wrap-items list-count-{$jobCardCount}">
                <ul class="card-list">

HTML;
    if (is_array($jobCardList) && count($jobCardList) > 0) {
      foreach ($jobCardList as $jobCard) {
        #募集職種
        $jobCategoryName = '';
        #選択中のラベル取得
        foreach ($jobCategories as $jobCategory) {
          if ($jobCard['job_category_id'] == $jobCategory['id']) {
            $jobCategoryName = $jobCategory['name'];
            break;
          }
        }
        #契約プラン
        $contractPlanName = '';
        #選択中のラベル取得
        foreach ($contractPlans as $contractPlan) {
          if ($jobCard['contract_plan_id'] == $contractPlan['id']) {
            $contractPlanName = $contractPlan['name'];
            #文字列から「プラン」を削除
            $contractPlanName = str_replace('プラン', '', $contractPlanName);
            break;
          }
        }
        #契約終了（解約）は表示を固定
        if (isset($jobCard['is_active']) && (int)$jobCard['is_active'] === 99) {
          $contractPlanName = '契約終了';
        }
        #掲載日
        $publishedDate = date('Y/m/d', strtotime($jobCard['published_start']));
        print <<<HTML
                  <li>
                    <div class="item-job-category">{$jobCategoryName}</div>
                    <div class="item-plan">{$contractPlanName}</div>
                    <div class="item-date">{$publishedDate}</div>
                  </li>

HTML;
      }
      print <<<HTML
                </ul>
                <div class="item-ban {$specialBannerEnabled}"><i></i></div>
              </div>
            </li>

HTML;
    } else {
      print <<<HTML
                  <li>
                    <div class="item-job-category"></div>
                    <div class="item-plan"></div>
                    <div class="item-date"></div>
                  </li>
                </ul>
                <div class="item-ban"><i></i></div>
              </div>
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
        <div class="bottom-box-btn">
          <button type="button" class="item-register" onclick="checkNewFacility()"><span>新規事業所登録</span></button>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>新規事業所登録</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>新規事業所情報を作成します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="closeModalToPage('master03_01_01.php?method=new')">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/master03_01.js" defer></script>
  </body>
</html>

HTML;
