<?php
/*
 * [rw-master/assets/function/proc_master03_01.php]
 *  - 管理画面 -
 *  事業所一覧：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.1.19
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
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';

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
#取得項目一覧
$jsonMasters = [];
try {
	$jsonMasters = getJson_FrontEndMaster_many([
		'jobCategories',
		'contractPlans'
	]);
} catch (Throwable $e) {
	if (function_exists('makeLog')) {
		makeLog('[proc_master03_01] master JSON load failed: ' . $e->getMessage());
	}
	$jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#契約プランマスタ
$contractPlans = $jsonMasters['contractPlans'] ?? [];

#-------------#
#検索・リセット
$action = isset($_POST['action']) ? $_POST['action'] : '';
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#-------------#
#事業所ID
$searchFacilityId = isset($_POST['searchFacilityId']) ? $_POST['searchFacilityId'] : null;
#事業所名
$searchFacilityName = isset($_POST['searchFacilityName']) ? $_POST['searchFacilityName'] : null;
#契約プラン
$searchPlan = isset($_POST['searchPlan']) ? $_POST['searchPlan'] : '';
#契約日
$searchStartDay = isset($_POST['searchStartDay']) ? $_POST['searchStartDay'] : null;
$searchEndDay = isset($_POST['searchEndDay']) ? $_POST['searchEndDay'] : null;
#絞り込み：あ～わ行
$searchInitials = isset($_POST['searchInitials']) ? $_POST['searchInitials'] : [];
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
#-------------#
#ソートモード
$sortTarget = '';
$idSortOrder = '';
$publishedStartSortOrder = '';
#-------------#
#前回のソート状態（sortMode=none などのときに維持）
$searchConditionsSessionKey = 'searchConditions_master03_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'facilityId' => '',
		'facilityName' => '',
		'plan' => '',
		'startDay' => '',
		'endDay' => '',
		'initials' => [],
		'sortTarget' => 'facility_id',
		'idSortOrder' => 'desc',
		'publishedStartSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['facilityId', 'facilityName', 'plan', 'startDay', 'endDay', 'initials', 'sortTarget', 'idSortOrder', 'publishedStartSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		$prevSearchConditions = [
			'facilityId' => '',
			'facilityName' => '',
			'plan' => '',
			'startDay' => '',
			'endDay' => '',
			'initials' => [],
			'sortTarget' => 'facility_id',
			'idSortOrder' => 'desc',
			'publishedStartSortOrder' => 'desc',
			'displayNumber' => $initialDisplayNumber,
			'pageNumber' => 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}
$prevSortTarget = $prevSearchConditions['sortTarget'];
$prevIdSortOrder = $prevSearchConditions['idSortOrder'];
$prevPublishedStartSortOrder = $prevSearchConditions['publishedStartSortOrder'];
#ソートモードのアクティブ判定
$sortIdAscActive = '';
$sortIdDescActive = '';
$sortContractDateAscActive = '';
$sortContractDateDescActive = '';
if ($sortMode === '' || $sortMode === 'none') {
	$sortTarget = $prevSortTarget;
	$idSortOrder = $prevIdSortOrder;
	$publishedStartSortOrder = $prevPublishedStartSortOrder;
	if ($sortTarget === 'published_start') {
		if (strtolower($publishedStartSortOrder) === 'asc') {
			$sortContractDateAscActive = 'is-active';
		} else {
			$sortContractDateDescActive = 'is-active';
		}
	} else {
		if (strtolower($idSortOrder) === 'asc') {
			$sortIdAscActive = 'is-active';
		} else {
			$sortIdDescActive = 'is-active';
		}
	}
} else {
	switch ($sortMode) {
		#--------------
		# 番号順にソート
		#--------------
		#IDの昇順
		case 'sortId_asc': {
				$sortTarget = 'facility_id';
				$idSortOrder = 'asc';
				$publishedStartSortOrder = $prevPublishedStartSortOrder;
			}
			break;
		#IDの降順
		case 'sortId_desc': {
				$sortTarget = 'facility_id';
				$idSortOrder = 'desc';
				$publishedStartSortOrder = $prevPublishedStartSortOrder;
			}
			break;
		#----------------
		# 契約日順にソート
		#----------------
		#契約日の昇順
		case 'sortContractDate_asc': {
				$sortTarget = 'published_start';
				$publishedStartSortOrder = 'asc';
				$idSortOrder = $prevIdSortOrder;
			}
			break;
		#契約日の降順
		case 'sortContractDate_desc': {
				$sortTarget = 'published_start';
				$publishedStartSortOrder = 'desc';
				$idSortOrder = $prevIdSortOrder;
			}
			break;
		#デフォルト：IDの降順
		default:
			$sortTarget = $prevSortTarget;
			$idSortOrder = $prevIdSortOrder;
			$publishedStartSortOrder = $prevPublishedStartSortOrder;
			if ($sortTarget === 'published_start') {
				if (strtolower($publishedStartSortOrder) === 'asc') {
					$sortContractDateAscActive = 'is-active';
				} else {
					$sortContractDateDescActive = 'is-active';
				}
			} else {
				if (strtolower($idSortOrder) === 'asc') {
					$sortIdAscActive = 'is-active';
				} else {
					$sortIdDescActive = 'is-active';
				}
			}
			break;
	}
}
#主ソート列のみアクティブ（副ソートは保持するが表示上は混乱防止で非表示）
$sortIdAscActive = '';
$sortIdDescActive = '';
$sortContractDateAscActive = '';
$sortContractDateDescActive = '';
if ($sortTarget === 'published_start') {
	if (strtolower($publishedStartSortOrder) === 'asc') {
		$sortContractDateAscActive = 'is-active';
	} else {
		$sortContractDateDescActive = 'is-active';
	}
} else {
	if (strtolower($idSortOrder) === 'asc') {
		$sortIdAscActive = 'is-active';
	} else {
		$sortIdDescActive = 'is-active';
	}
}
#-------------#
#検索条件配列生成してSESSIONに保存
switch ($action) {
	#条件で検索
	case 'search': {
			#検索条件が変わる操作は原則1ページ目に戻す
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			$searchConditions = [
				'facilityId' => $searchFacilityId,
				'facilityName' => $searchFacilityName,
				'plan' => $searchPlan,
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'initials' => $searchInitials,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'publishedStartSortOrder' => $publishedStartSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#条件をクリア
	case 'reset': {
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			$searchConditions = [
				'facilityId' => '',
				'facilityName' => '',
				'plan' => '',
				'startDay' => '',
				'endDay' => '',
				'initials' => $searchInitials,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'publishedStartSortOrder' => $publishedStartSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#絞り込み解除
	case 'release': {
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			$searchConditions = [
				'facilityId' => $searchFacilityId,
				'facilityName' => $searchFacilityName,
				'plan' => $searchPlan,
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'initials' => [],
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'publishedStartSortOrder' => $publishedStartSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'facilityId' => $searchFacilityId,
				'facilityName' => $searchFacilityName,
				'plan' => $searchPlan,
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'initials' => $searchInitials,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'publishedStartSortOrder' => $publishedStartSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'facilityId' => '',
				'facilityName' => '',
				'plan' => '',
				'startDay' => '',
				'endDay' => '',
				'initials' => [],
				'sortTarget' => 'facility_id',
				'idSortOrder' => 'desc',
				'publishedStartSortOrder' => 'desc',
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
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
#事業所一覧取得（LIMIT/OFFSET）
$facilityList = searchFacilityList($searchConditions, $pageNumber, $displayNumber);
#該当件数（表示用：総件数）
$facilityCount = $totalFacilityCount;
#返却HTML：現在の主ソートモード（JS初期判定用）
$sortModeValue = '';
$sortTargetSaved = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'facility_id';
$idSortOrderSaved = isset($searchConditions['idSortOrder']) ? (string)$searchConditions['idSortOrder'] : 'desc';
$publishedStartSortOrderSaved = isset($searchConditions['publishedStartSortOrder']) ? (string)$searchConditions['publishedStartSortOrder'] : 'desc';
if ($sortTargetSaved === 'published_start') {
	$sortModeValue = (strtolower($publishedStartSortOrderSaved) === 'asc') ? 'sortContractDate_asc' : 'sortContractDate_desc';
} else {
	$sortModeValue = (strtolower($idSortOrderSaved) === 'asc') ? 'sortId_asc' : 'sortId_desc';
}

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-vendor-list" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$facilityCount}件</span>が該当</p>
            <div class="list-display" data-selectbox>

HTML;
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
#表示件数格納用変数を初期化
$currentDisplayNumber = isset($displayNumber) ? $displayNumber : $initialDisplayNumber;
#表示数が選択されている場合
foreach ($displayNumberList as $displayNumber) {
	if ($displayNumber === (int)$searchConditions['displayNumber']) {
		$currentDisplayNumber = $displayNumber;
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
	$checked = ($number === (int)$searchConditions['displayNumber']) ? ' checked' : '';
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
		$makeTag['tag'] .= <<<HTML
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
				$makeTag['tag'] .= <<<HTML
                  <li>
                    <div class="item-job-category">{$jobCategoryName}</div>
                    <div class="item-plan">{$contractPlanName}</div>
                    <div class="item-date">{$publishedDate}</div>
                  </li>

HTML;
			}
			$makeTag['tag'] .= <<<HTML
                </ul>
                <div class="item-ban {$specialBannerEnabled}"><i></i></div>
              </div>
            </li>

HTML;
		} else {
			$makeTag['tag'] .= <<<HTML
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
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
