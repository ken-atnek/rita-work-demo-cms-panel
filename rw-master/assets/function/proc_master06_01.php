<?php
/*
 * [rw-master/assets/function/proc_master06_01.php]
 *  - 管理画面 -
 *  明細一覧（請求一覧）：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.3.5
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
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
#事業所請求情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_invoice.php';

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array(
	'tag' => '',
	'status' => '',
	'title' => '',
	'msg' => '',
);

#=============#
# POSTチェック
#-------------#
#セッションキー
$noUpDateKey = isset($_POST['noUpDateKey']) ? $_POST['noUpDateKey'] : '';
#noUpDateKey は「画面インスタンス識別用」。
#画面遷移/マルチタブ等でキーが更新されている場合があるため、
#POSTキーが無効ならセッション側の現行キーへフォールバックする。
$currentNoUpDateKey = isset($_SESSION['sKey']) ? (string)$_SESSION['sKey'] : '';
if ($noUpDateKey === '' || isset($_SESSION[$noUpDateKey]) === false) {
	if ($currentNoUpDateKey !== '' && isset($_SESSION[$currentNoUpDateKey])) {
		$noUpDateKey = $currentNoUpDateKey;
	} else {
		#AJAX向け：JSONでエラー返却（fetch側で画面リロード誘導）
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = 'セッションエラー';
		$makeTag['msg'] = 'セッションが切れました。ページを再読み込みしてください。';
		$makeTag['noUpDateKey'] = $currentNoUpDateKey;
		echo json_encode($makeTag);
		exit;
	}
}
#応答には常に現行のキーを含め、フロント側のhiddenを更新できるようにする
$makeTag['noUpDateKey'] = ($currentNoUpDateKey !== '' ? $currentNoUpDateKey : $noUpDateKey);

#===================================#
# フロント側マスタ定義JSONファイル取得
#-----------------------------------#
$jsonMasters = [];
try {
	$jsonMasters = getJson_FrontEndMaster_many([
		'contractPlans'
	]);
} catch (Throwable $e) {
	if (function_exists('makeLog')) {
		makeLog('[proc_master06_01] master JSON load failed: ' . $e->getMessage());
	}
	$jsonMasters = [];
}
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
#検索・リセット
#-------------#
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$sortMode = isset($_POST['sortMode']) ? (string)$_POST['sortMode'] : '';
#事業所名
$searchFacilityName = isset($_POST['searchFacilityName']) ? (string)$_POST['searchFacilityName'] : '';
#請求月（開始/終了）
$searchStartDay = isset($_POST['searchStartDay']) ? (string)$_POST['searchStartDay'] : '';
$searchEndDay = isset($_POST['searchEndDay']) ? (string)$_POST['searchEndDay'] : '';
#絞り込み：あ～わ行
$searchInitials = isset($_POST['searchInitials']) ? $_POST['searchInitials'] : [];
if (!is_array($searchInitials)) {
	$searchInitials = [];
}
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
if ($displayNumber < 1) {
	$displayNumber = $initialDisplayNumber;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
}
#初期値：前月（JST）
$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
$prevMonth = $today->modify('first day of last month')->format('Y-m');
#-------------#
#ソート（請求月）
#-------------#
$searchConditionsSessionKey = 'searchConditions_master06_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'facilityName' => '',
		'startDay' => $prevMonth,
		'endDay' => $prevMonth,
		'initials' => [],
		'sortTarget' => 'billing_period',
		'billingPeriodSortOrder' => 'desc',
		'idSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
#ソートモードのアクティブ判定
$prevBillingPeriodSortOrder = isset($prevSearchConditions['billingPeriodSortOrder']) ? (string)$prevSearchConditions['billingPeriodSortOrder'] : 'desc';
$billingPeriodSortOrder = $prevBillingPeriodSortOrder;
if ($sortMode !== '' && $sortMode !== 'none') {
	switch ($sortMode) {
		case 'sortContractDate_asc':
			$billingPeriodSortOrder = 'asc';
			break;
		case 'sortContractDate_desc':
			$billingPeriodSortOrder = 'desc';
			break;
		default:
			$billingPeriodSortOrder = $prevBillingPeriodSortOrder;
			break;
	}
}
$sortContractDateAscActive = (strtolower($billingPeriodSortOrder) === 'asc') ? 'is-active' : '';
$sortContractDateDescActive = (strtolower($billingPeriodSortOrder) === 'asc') ? '' : 'is-active';

#========================#
# 検索条件配列生成→SESSION
#------------------------#
#検索条件が変わる操作は原則1ページ目に戻す
if ($action === 'search' || $action === 'reset' || $action === 'release') {
	if (!isset($_POST['pageNumber'])) {
		$pageNumber = 1;
	}
}
#-------------#
#検索条件配列生成してSESSIONに保存
switch ($action) {
	#条件で検索
	case 'search':
		$searchConditions = [
			'facilityName' => $searchFacilityName,
			'startDay' => $searchStartDay,
			'endDay' => $searchEndDay,
			'initials' => $searchInitials,
			'sortTarget' => 'billing_period',
			'billingPeriodSortOrder' => $billingPeriodSortOrder,
			'idSortOrder' => 'desc',
			'displayNumber' => $displayNumber,
			'pageNumber' => $pageNumber,
		];
		break;
	#条件をクリア
	case 'reset':
		$searchConditions = [
			'facilityName' => '',
			'startDay' => $prevMonth,
			'endDay' => $prevMonth,
			'initials' => [],
			'sortTarget' => 'billing_period',
			'billingPeriodSortOrder' => $billingPeriodSortOrder,
			'idSortOrder' => 'desc',
			'displayNumber' => $displayNumber,
			'pageNumber' => $pageNumber,
		];
		break;
	#絞り込み解除
	case 'release':
		$searchConditions = [
			'facilityName' => $searchFacilityName,
			'startDay' => $searchStartDay,
			'endDay' => $searchEndDay,
			'initials' => [],
			'sortTarget' => 'billing_period',
			'billingPeriodSortOrder' => $billingPeriodSortOrder,
			'idSortOrder' => 'desc',
			'displayNumber' => $displayNumber,
			'pageNumber' => $pageNumber,
		];
		break;
	#ページ移動
	case 'page':
		$searchConditions = [
			'facilityName' => $searchFacilityName,
			'startDay' => $searchStartDay,
			'endDay' => $searchEndDay,
			'initials' => $searchInitials,
			'sortTarget' => 'billing_period',
			'billingPeriodSortOrder' => $billingPeriodSortOrder,
			'idSortOrder' => 'desc',
			'displayNumber' => $displayNumber,
			'pageNumber' => $pageNumber,
		];
		break;
	default:
		$searchConditions = $prevSearchConditions;
		break;
}
#SESSIONに保存
$_SESSION[$searchConditionsSessionKey] = $searchConditions;
#ページ番号・表示件数
$searchConditions = $_SESSION[$searchConditionsSessionKey];
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
if ($displayNumber < 1) {
	$displayNumber = $initialDisplayNumber;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
}
#総件数（ページャー用）
$totalCount = searchFacilityInvoiceCount($searchConditions);
$totalPages = (int)ceil($totalCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
	$searchConditions['pageNumber'] = $pageNumber;
	$_SESSION[$searchConditionsSessionKey] = $searchConditions;
}
$invoiceList = searchFacilityInvoiceList($searchConditions, $pageNumber, $displayNumber);
#-------------#
# 返却HTML生成
#-------------#
$billingPeriodSortOrderSaved = isset($searchConditions['billingPeriodSortOrder']) ? (string)$searchConditions['billingPeriodSortOrder'] : 'desc';
$sortModeValue = (strtolower($billingPeriodSortOrderSaved) === 'asc') ? 'sortContractDate_asc' : 'sortContractDate_desc';

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-vendor-list status-master" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$totalCount}件</span>が該当</p>
            <div class="list-display" data-selectbox>

HTML;
#表示件数格納用変数を初期化
$currentDisplayNumber = isset($displayNumber) ? $displayNumber : $initialDisplayNumber;
foreach ($displayNumberList as $dn) {
	if ((int)$dn === (int)$searchConditions['displayNumber']) {
		$currentDisplayNumber = (int)$dn;
		break;
	}
}
$makeTag['tag'] .= <<<HTML
              <button type="button" class="selectbox__head" aria-expanded="false">
                <input type="hidden" name="displayNumber" value="{$currentDisplayNumber}" data-selectbox-hidden>
                <span class="selectbox__value" data-selectbox-value>{$currentDisplayNumber}</span>
              </button>
              <div class="list-wrapper">
                <ul class="selectbox__panel">

HTML;
#表示件数選択リストループで差し込む
foreach ($displayNumberList as $number) {
	$checked = ((int)$number === (int)$searchConditions['displayNumber']) ? ' checked' : '';
	$makeTag['tag'] .= <<<HTML
                  <li>
                    <input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none')">
                    <label for="display{$number}">{$number}</label>
                  </li>

HTML;
}
$makeTag['tag'] .= <<<HTML
                </ul>
              </div>
            </div>
          </div>
          <ul class="list-search-results">
            <li>
              <div>
                請求月
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortContractDateAscActive}" onclick="searchConditions('search','sortContractDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortContractDateDescActive}" onclick="searchConditions('search','sortContractDate_desc')"></button>
                </span>
              </div>
              <div>事業所名</div>
              <div>契約プラン</div>
              <div>請求額<small>(税別)</small></div>
              <div>ダウンロード</div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($invoiceList) && count($invoiceList) > 0) {
	foreach ($invoiceList as $row) {
		$billingPeriodRaw = isset($row['billing_period']) ? (string)$row['billing_period'] : '';
		$billingMonth = '';
		if ($billingPeriodRaw !== '') {
			$ts = strtotime($billingPeriodRaw);
			if ($ts !== false) {
				$billingMonth = date('Y/m', $ts);
			}
		}
		$facName = convertData((string)($row['facility_name'] ?? ''));
		#契約プラン（請求スナップショット：facility_invoice_items.plan_id + quantity）
		# - 同一プランでも quantity 分すべて表示する（重複削除しない）
		$planNames = [];
		$planItemsRaw = isset($row['plan_items']) ? (string)$row['plan_items'] : '';
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
			$planIdsRaw = isset($row['plan_ids']) ? (string)$row['plan_ids'] : '';
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
		$isSpecialBanner = (isset($row['is_special_banner']) && (int)$row['is_special_banner'] === 1);
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
		$amountTotal = isset($row['amount_total']) ? (int)$row['amount_total'] : 0;
		$amountTotalText = number_format($amountTotal) . '円';
		$makeTag['tag'] .= <<<HTML
            <li>
              <div class="item-date">{$billingMonth}</div>
              <div class="item-name">{$facName}</div>
              <div class="item-plan">
                {$contractPlanNameTag}
              </div>
              <div class="item-price">{$amountTotalText}</div>
              <div class="item-btn"><button type="button"></button></div>
            </li>

HTML;
	}
} else {
	$makeTag['tag'] .= <<<HTML
            <li class="no-data" style="display:flex;justify-content:center;align-items:center;padding:2em 0;">
              <div>該当するデータが存在しません。</div>
            </li>

HTML;
}
$makeTag['tag'] .= <<<HTML
          </ul>

HTML;
#ページャー表示
$makeTag['tag'] .= makePagerBoxTag((int)$pageNumber, (int)$totalPages, $pagerDisplayMax, 'movePage');
$makeTag['tag'] .= <<<HTML
        </article>

HTML;

#-------------------------------------------#
#json 応答
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($makeTag);
#-------------------------------------------#
