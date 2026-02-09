<?php
/*
 * [rw-master/assets/function/proc_master02_01.php]
 *  - 管理画面 -
 *  法人一覧：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2025.12.19
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

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array();
$makeTag['tag'] = '';
$makeTag['status'] = '';
$makeTag['title'] = '';
$makeTag['msg'] = '';

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
#-------------#
#検索・リセット
$action = isset($_POST['action']) ? $_POST['action'] : '';
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#-------------#
#法人名
$searchCompanyName = isset($_POST['searchCompanyName']) ? $_POST['searchCompanyName'] : null;
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
$contractDateSortOrder = '';
#-------------#
#前回のソート状態（sortMode=none などのときに維持）
$searchConditionsSessionKey = 'searchConditions_master02_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'companyName' => '',
		'startDay' => '',
		'endDay' => '',
		'initials' => [],
		'sortTarget' => 'corporation_id',
		'idSortOrder' => 'desc',
		'contractDateSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['companyName', 'startDay', 'endDay', 'initials', 'sortTarget', 'idSortOrder', 'contractDateSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		$prevSearchConditions = [
			'companyName' => '',
			'startDay' => '',
			'endDay' => '',
			'initials' => [],
			'sortTarget' => 'corporation_id',
			'idSortOrder' => 'desc',
			'contractDateSortOrder' => 'desc',
			'displayNumber' => $initialDisplayNumber,
			'pageNumber' => 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}

$prevSortTarget = isset($prevSearchConditions['sortTarget']) ? (string)$prevSearchConditions['sortTarget'] : 'corporation_id';
$prevIdSortOrder = strtolower((string)($prevSearchConditions['idSortOrder'] ?? 'desc'));
$prevContractDateSortOrder = strtolower((string)($prevSearchConditions['contractDateSortOrder'] ?? 'desc'));
if ($prevIdSortOrder !== 'asc' && $prevIdSortOrder !== 'desc') {
	$prevIdSortOrder = 'desc';
}
if ($prevContractDateSortOrder !== 'asc' && $prevContractDateSortOrder !== 'desc') {
	$prevContractDateSortOrder = 'desc';
}

$sortTarget = $prevSortTarget;
$idSortOrder = $prevIdSortOrder;
$contractDateSortOrder = $prevContractDateSortOrder;

if ($sortMode !== '' && $sortMode !== 'none') {
	switch ($sortMode) {
		#--------------
		# 番号順にソート
		#--------------
		case 'sortId_asc': {
				$sortTarget = 'corporation_id';
				$idSortOrder = 'asc';
			}
			break;
		case 'sortId_desc': {
				$sortTarget = 'corporation_id';
				$idSortOrder = 'desc';
			}
			break;
		#----------------
		# 契約日順にソート
		#----------------
		case 'sortContractDate_asc': {
				$sortTarget = 'contract_date';
				$contractDateSortOrder = 'asc';
			}
			break;
		case 'sortContractDate_desc': {
				$sortTarget = 'contract_date';
				$contractDateSortOrder = 'desc';
			}
			break;
		default:
			$sortTarget = $prevSortTarget;
			$idSortOrder = $prevIdSortOrder;
			$contractDateSortOrder = $prevContractDateSortOrder;
			break;
	}
}

#ソートモードのアクティブ判定（番号・契約日 両方に付与）
$sortIdAscActive = '';
$sortIdDescActive = '';
$sortContractDateAscActive = '';
$sortContractDateDescActive = '';
if ($sortTarget === 'contract_date') {
	#主ソート：契約日（契約日のみアクティブ表示）
	$sortContractDateAscActive = (strtolower((string)$contractDateSortOrder) === 'asc') ? 'is-active' : '';
	$sortContractDateDescActive = (strtolower((string)$contractDateSortOrder) === 'asc') ? '' : 'is-active';
} else {
	#主ソート：番号（番号のみアクティブ表示）
	$sortIdAscActive = (strtolower((string)$idSortOrder) === 'asc') ? 'is-active' : '';
	$sortIdDescActive = (strtolower((string)$idSortOrder) === 'asc') ? '' : 'is-active';
}

#表示側へ渡すソートモード文字列（主ソート：ページ移動等で維持する）
$sortModeValue = 'none';
if ($sortTarget === 'contract_date') {
	$sortModeValue = 'sortContractDate_' . strtolower((string)$contractDateSortOrder);
} else {
	$sortModeValue = 'sortId_' . strtolower((string)$idSortOrder);
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
				'companyName' => $searchCompanyName,
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'initials' => $searchInitials,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'contractDateSortOrder' => $contractDateSortOrder,
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
				'companyName' => '',
				'startDay' => '',
				'endDay' => '',
				'initials' => $searchInitials,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'contractDateSortOrder' => $contractDateSortOrder,
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
				'companyName' => $searchCompanyName,
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'initials' => [],
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'contractDateSortOrder' => $contractDateSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'companyName' => $searchCompanyName,
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'initials' => $searchInitials,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'contractDateSortOrder' => $contractDateSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'companyName' => '',
				'startDay' => '',
				'endDay' => '',
				'initials' => [],
				'sortTarget' => 'corporation_id',
				'idSortOrder' => 'desc',
				'contractDateSortOrder' => 'desc',
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
$totalCorporationsCount = searchCorporationCount($searchConditions);
$totalPages = (int)ceil($totalCorporationsCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
}
#法人一覧取得（LIMIT/OFFSET）
$corporationsList = searchCorporationList($searchConditions, $pageNumber, $displayNumber);
#該当件数（表示用：総件数）
$corporationsCount = $totalCorporationsCount;

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-company-list" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$corporationsCount}件</span>が該当</p>
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
                  <button type="button" class="arrow-top {$sortIdAscActive}" onclick="searchConditions('search','sortId_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortIdDescActive}" onclick="searchConditions('search','sortId_desc')"></button>
                </span>
              </div>
              <div>法人名</div>
              <div>住所</div>
              <div>電話番号</div>
              <div>E-mail</div>
              <div>
                契約日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortContractDateAscActive}" onclick="searchConditions('search','sortContractDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortContractDateDescActive}" onclick="searchConditions('search','sortContractDate_desc')"></button>
                </span>
              </div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($corporationsList) && count($corporationsList) > 0) {
	foreach ($corporationsList as $corporation) {
		$corpId = convertData($corporation['corporation_id']);
		$corpName = convertData($corporation['name']);
		$corpZipCode = convertData($corporation['postal_code']);
		$corpAddress = convertData($corporation['prefecture'] . $corporation['city'] . $corporation['address_line']);
		$corpPhone = convertData($corporation['phone']);
		$corpEmail = convertData($corporation['email']);
		$contractDate = date('Y/m/d', strtotime(convertData($corporation['contract_date'])));
		$makeTag['tag'] .= <<<HTML
            <li onclick="location.href='./master02_02.php?method=edit&corpId={$corpId}'">
              <div class="item-number">{$corpId}</div>
              <div class="item-name">{$corpName}</div>
              <div class="item-add"><span>〒{$corpZipCode}</span>{$corpAddress}</div>
              <div class="item-tel">{$corpPhone}</div>
              <div class="item-email">{$corpEmail}</div>
              <div class="item-date">{$contractDate}</div>
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
$makeTag['tag'] .= makePagerBoxTag((int)$pageNumber, (int)$totalPages, $pagerDisplayMax, 'movePage');
$makeTag['tag'] .= <<<HTML
        </article>

HTML;
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
