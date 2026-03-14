<?php
/*
 * [rw-client/client06_01.php]
 *  - 【事業所】管理画面 -
 *  明細一覧
 *
 * [初版]
 *  2026.3.10
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
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#事業所請求情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_invoice.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$searchConditionsSessionKey = 'searchConditions_client06_01';
$pagePrefix = 'cKey06-01_';
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

#===================================#
# フロント側マスタ定義JSONファイル取得
#-----------------------------------#
#取得項目一覧
$jsonMasters = [];
try {
	$jsonMasters = getJson_FrontEndMaster_many([
		'contractPlans'
	]);
} catch (Throwable $e) {
	if (function_exists('makeLog')) {
		makeLog('[client06_01] master JSON load failed: ' . $e->getMessage());
	}
	$jsonMasters = [];
}
#契約プランマスタ
$contractPlans = $jsonMasters['contractPlans'] ?? [];
#契約プランIDをキー、プラン名を値とする連想配列を生成（ループ内での参照用）
$contractPlanNameById = [];
if (is_array($contractPlans)) {
	foreach ($contractPlans as $p) {
		if (!is_array($p) || !isset($p['id'])) {
			continue;
		}
		$contractPlanNameById[(string)$p['id']] = (string)($p['name'] ?? '');
	}
}
#-------------#
#検索・絞り込み条件保持用セッションチェック
$searchConditions = array();
if (isset($_SESSION[$searchConditionsSessionKey]) === false || !is_array($_SESSION[$searchConditionsSessionKey])) {
	// 初回表示は前月の請求一覧
	$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
	$prevMonth = $today->modify('first day of last month')->format('Y-m');
	#セッション無し：初期化
	$_SESSION[$searchConditionsSessionKey] = array(
		'facilityId' => $facId,
		'startDay' => $prevMonth,
		'endDay' => $prevMonth,
		'sortTarget' => 'billing_period',
		'billingPeriodSortOrder' => 'desc',
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
$requiredKeys = ['facilityId', 'startDay', 'endDay', 'sortTarget', 'billingPeriodSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $searchConditions)) {
		$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
		$prevMonth = $today->modify('first day of last month')->format('Y-m');
		#欠けているキーがあれば初期化
		$searchConditions = array(
			'facilityId' => $facId,
			'startDay' => $prevMonth,
			'endDay' => $prevMonth,
			'sortTarget' => 'billing_period',
			'billingPeriodSortOrder' => 'desc',
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
if ($searchConditions['facilityId'] !== '' || $searchConditions['startDay'] !== '' || $searchConditions['endDay'] !== '') {
	$activeSearchForm = ' load is-active';
} else {
	$activeSearchForm = '';
}

#=====================#
# 事業所請求情報一覧取得
#---------------------#
#検索条件があれば適用して事業所請求情報一覧を取得
if (is_array($searchConditions) && count($searchConditions) > 0) {
	$facilityInvoiceList = searchFacilityInvoiceList($searchConditions, $pageNumber, $displayNumber);
} else {
	$facilityInvoiceList = getFacilityInvoiceList();
}
#総件数（ページャー用）
$totalFacilityCount = searchFacilityInvoiceCount($searchConditions);
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
$sortInvoiceDateAscActive = '';
$sortInvoiceDateDescActive = '';
$sortModeValue = '';
if (strtolower((string)$searchConditions['billingPeriodSortOrder']) === 'asc') {
	$sortInvoiceDateAscActive = 'is-active';
	$sortModeValue = 'sortInvoiceDate_asc';
} else {
	$sortInvoiceDateDescActive = 'is-active';
	$sortModeValue = 'sortInvoiceDate_desc';
}

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title>リタワーク｜コントロールパネル(事業所)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net;">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master06.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-06-01">
      <section class="page-nav">
        <h2>明細管理</h2>
        <nav>
          <a href="./client06_01.php" class="is-active">明細一覧</a>
        </nav>
      </section>
      <section class="container-detail-list">
        <h2>明細一覧</h2>
        <form name="searchForm" class="block-search">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
          <button type="button" id="btnSwitchSearch" class="btn-switch {$activeSearchForm}" aria-controls="innerSearch" aria-expanded="false"></button>
          <h3>条件で検索</h3>
          <article id="innerSearch" class="{$activeSearchForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-application-date">
                  <h4>請求月</h4>
                  <div class="wrap-period">
                    <input type="month" name="searchStartDay" value="{$searchConditions['startDay']}">
                    <span>〜</span>
                    <input type="month" name="searchEndDay" value="{$searchConditions['endDay']}">
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
        <article class="block-vendor-list status-client">
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
                請求月
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortInvoiceDateAscActive}" onclick="searchConditions('search','sortInvoiceDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortInvoiceDateDescActive}" onclick="searchConditions('search','sortInvoiceDate_desc')"></button>
                </span>
              </div>
              <div>契約プラン</div>
              <div>請求額<small>(税別)</small></div>
              <div>ダウンロード</div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($facilityInvoiceList) && count($facilityInvoiceList) > 0) {
	foreach ($facilityInvoiceList as $facility) {
		#明細ID
		$invoiceId = isset($facility['invoice_id']) ? (int)$facility['invoice_id'] : 0;
		$billingPeriodRaw = isset($facility['billing_period']) ? (string)$facility['billing_period'] : '';
		#請求期間
		$billingMonth = '';
		if ($billingPeriodRaw !== '') {
			$ts = strtotime($billingPeriodRaw);
			if ($ts !== false) {
				$billingMonth = date('Y/m', $ts);
			}
		}
		#契約プラン（請求スナップショット：facility_invoice_items.plan_id + quantity）
		# - 同一プランでも quantity 分すべて表示する（重複削除しない）
		$planNames = [];
		$planItemsRaw = isset($facility['plan_items']) ? (string)$facility['plan_items'] : '';
		if ($planItemsRaw !== '') {
			$planItems = array_filter(array_map('trim', explode(',', $planItemsRaw)), function ($v) {
				return $v !== '';
			});
			foreach ($planItems as $item) {
				$parts = explode(':', $item, 2);
				$planId = isset($parts[0]) ? trim((string)$parts[0]) : '';
				$qty = isset($parts[1]) ? (int)$parts[1] : 1;
				if ($planId === '') {
					continue;
				}
				if ($qty < 1) {
					$qty = 1;
				}
				$name = isset($contractPlanNameById[$planId]) ? trim((string)$contractPlanNameById[$planId]) : '';
				if ($name === '') {
					continue;
				}
				for ($i = 0; $i < $qty; $i++) {
					$planNames[] = $name;
				}
			}
		} else {
			#互換：plan_items が無い場合は plan_ids で1回ずつ表示
			$planIdsRaw = isset($facility['plan_ids']) ? (string)$facility['plan_ids'] : '';
			if ($planIdsRaw !== '') {
				$planIds = array_filter(array_map('trim', explode(',', $planIdsRaw)), function ($v) {
					return $v !== '';
				});
				foreach ($planIds as $planId) {
					$name = isset($contractPlanNameById[$planId]) ? trim((string)$contractPlanNameById[$planId]) : '';
					if ($name !== '') {
						$planNames[] = $name;
					}
				}
			}
		}
		#特別バナー契約の判定
		$isSpecialBanner = (isset($facility['is_special_banner']) && (int)$facility['is_special_banner'] === 1);
		#契約プラン表示タグ生成
		$contractPlanNameTag = '';
		if ($isSpecialBanner) {
			$inner = '';
			if (count($planNames) === 0) {
				$inner = '<span>-</span>';
			} else {
				foreach ($planNames as $name) {
					$inner .= '<span>' . convertData($name) . '</span>';
				}
			}
			$contractPlanNameTag = '
                <span class="head-item">
                  特別バナープラン
                  <button type="button" class="btn-arrow"></button>
                </span>
                <div class="inner-plan">
                  <div class="wrap-plan">' . $inner . '</div>
                </div>
      ';
		} else {
			if (count($planNames) <= 1) {
				$label = (count($planNames) === 1) ? convertData($planNames[0]) : '-';
				$contractPlanNameTag = '
                <span class="head-item">' . $label . '</span>
        ';
			} else {
				$inner = '';
				for ($i = 1; $i < count($planNames); $i++) {
					$inner .= '<span>' . convertData($planNames[$i]) . '</span>';
				}
				$contractPlanNameTag = '
                <span class="head-item">
                  ' . convertData($planNames[0]) . '
                  <button type="button" class="btn-arrow"></button>
                </span>
                <div class="inner-plan">
                  <div class="wrap-plan">' . $inner . '</div>
                </div>
        ';
			}
		}
		#請求額（税別）表示用に金額を整形
		$amountTotal = isset($facility['amount_total']) ? (int)$facility['amount_total'] : 0;
		$amountTotalText = number_format($amountTotal) . '円';
		print <<<HTML
            <li>
              <div class="item-date">{$billingMonth}</div>
              <div class="item-plan">
                {$contractPlanNameTag}
              </div>
              <div class="item-price">{$amountTotalText}</div>
              <div class="item-btn"><button type="button" onclick="makeReceiptPDF({$facId}, {$invoiceId})"></button></div>
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
          <button type="button" class="item-back" onclick="location.href='./client06_01.php'">戻る</button>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <script src="../assets/js/common.js" defer></script>
    <script src="./assets/js/client06_01.js" defer></script>
    <!-- 必須：html2canvas + jsPDF（defer + 順番重要） -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js" defer></script>
    <script src="../assets/lib/jsPDF/js/jspdf_app.js" defer></script>
  </body>
</html>

HTML;
