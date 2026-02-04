<?php
/*
 * [rw-master/assets/function/proc_master04_01.php]
 *  - 管理画面 -
 *  応募者一覧：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.1.29
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
		makeLog('[proc_master04_01] master JSON load failed: ' . $e->getMessage());
	}
	$jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];

#JS文脈用のjson_encodeフラグ
$jsonHex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

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
#検索・ステータス変更
$action = isset($_POST['action']) ? $_POST['action'] : '';
#検索モード
$searchMode = isset($_POST['searchMode']) ? $_POST['searchMode'] : '';
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#事業所ID（任意：masterは空=全件）
$facId = isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : 0;
#検索条件（フォーム）
$facilityName = isset($_POST['facility_name']) ? trim((string)$_POST['facility_name']) : '';
$referName = isset($_POST['referName']) ? trim((string)$_POST['referName']) : '';
$referJobType = isset($_POST['referJobType']) ? trim((string)$_POST['referJobType']) : '';
$startDay = isset($_POST['startDay']) ? trim((string)$_POST['startDay']) : '';
$endDay = isset($_POST['endDay']) ? trim((string)$_POST['endDay']) : '';
$postInitials = isset($_POST['searchInitials']) ? $_POST['searchInitials'] : [];
if (!is_array($postInitials)) {
	$postInitials = [];
}
#日付は YYYY-MM-DD のみ許可（不正値は空扱い）
if ($startDay !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDay)) {
	$startDay = '';
}
if ($endDay !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDay)) {
	$endDay = '';
}
#頭文字（あ行〜）はマスタ定義にあるものだけ通す
$allowedInitials = [];
if (isset($filterInitialsList) && is_array($filterInitialsList)) {
	foreach ($filterInitialsList as $v) {
		$allowedInitials[] = (string)str_replace('行', '', (string)$v);
	}
}
$initials = [];
foreach ($postInitials as $ini) {
	$ini = (string)$ini;
	if ($ini === '') continue;
	if (!empty($allowedInitials) && !in_array($ini, $allowedInitials, true)) continue;
	$initials[] = $ini;
}
#-------------#
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
#-------------#
#応募状況ステータス変更
if ($action == 'changeStatus') {
	#=============#
	# POSTチェック
	#-------------#
	#事業所ID
	$appliedJobsFacId = isset($_POST['facId']) ? (int)$_POST['facId'] : 0;
	#求職者LINEユーザーID
	$lineUserId = isset($_POST['lineId']) ? (string)$_POST['lineId'] : '';
	#求職者名
	$userName = isset($_POST['lineName']) ? (string)$_POST['lineName'] : '';
	#求人カードID
	$jobId = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
	#ステータス
	$changeStatus = isset($_POST['changeStatus']) ? (string)$_POST['changeStatus'] : '';
	#入力値バリデーション（不正更新/想定外値を防ぐ）
	$allowedStatuses = [];
	if (isset($applicationStatusMaster) && is_array($applicationStatusMaster)) {
		$allowedStatuses = array_keys($applicationStatusMaster);
	}
	if ($lineUserId === '' || $jobId < 1 || $appliedJobsFacId < 1 || $changeStatus === '' || !in_array($changeStatus, $allowedStatuses, true) || $changeStatus === 'friend_only') {
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = '応募状況変更エラー';
		$makeTag['msg'] = '応募状況変更の入力値が不正です。';
		echo json_encode($makeTag);
		exit;
	}
	try {
		#トランザクション開始
		# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
		$result = DB_Transaction(1);
		if ($result == false) {
			#エラーログ出力
			$data = [
				'pageName' => 'proc_master04_01',
				'reason' => 'トランザクション開始失敗',
			];
			makeLog($data);
			$makeTag['status'] = 'error';
			$makeTag['title'] = '応募状況変更エラー';
			$makeTag['msg'] = 'トランザクション開始に失敗しました。';
			header('Content-Type: application/json');
			echo json_encode($makeTag);
			exit;
		} else {
			#***** ステータス変更 *****#
			#登録用配列：初期化
			$dbFiledData = array();
			#登録情報セット
			$dbFiledData['status'] = array(':status', $changeStatus, 1);
			$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
			#更新用キー：初期化
			$dbFiledValue = array();
			$dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
			$dbFiledValue['facility_id'] = array(':facility_id', $appliedJobsFacId, 1);
			$dbFiledValue['line_user_id'] = array(':line_user_id', $lineUserId, 1);
			#処理モード：[1].新規追加｜[2].更新｜[3].削除
			$processFlg = 2;
			#DB更新
			#実行モード：[1].トランザクション｜[2].即実行
			$exeFlg = 2;
			$dbSuccessFlg = SQL_Process($DB_CONNECT, "applications", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
			if ($dbSuccessFlg == 1) {
				#DBコミット
				# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
				DB_Transaction(2);
				$makeTag['status'] = 'success';
				$makeTag['title'] = '応募状況変更';
				$userNameEsc = htmlspecialchars((string)$userName, ENT_QUOTES, 'UTF-8');
				switch ($changeStatus) {
					#***** 登録中 *****#
					case 'registered': {
							$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">登録中</span>で登録しました。';
						}
						break;
					#***** 応募中 *****#
					case 'applied': {
							$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">応募中</span>で登録しました。';
						}
						break;
					#***** 面接中 *****#
					case 'interview': {
							$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">面接中</span>で登録しました。';
						}
						break;
					#***** 採用 *****#
					case 'hired': {
							$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">採用</span>で登録しました。';
						}
						break;
					#***** 不採用 *****#
					case 'rejected': {
							$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">不採用</span>で登録しました。';
						}
						break;
					#***** 連絡待ち *****#
					case 'unresponsive': {
							$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">連絡待ち</span>で登録しました。';
						}
						break;
					default:
						$makeTag['msg'] = $userNameEsc . '様の応募状況を更新しました。';
						break;
				}
			} else {
				#更新失敗：ロールバックしてエラー返却
				DB_Transaction(3);
				$makeTag['status'] = 'error';
				$makeTag['title'] = '応募状況変更エラー';
				$makeTag['msg'] = '応募状況の更新に失敗しました。';
			}
		}
	} catch (Exception $e) {
		DB_Transaction(3);
		#エラーログ出力
		$data = [
			'pageName' => 'proc_master04_01',
			'reason' => '応募状況更新失敗',
			'errorMessage' => $e->getMessage(),
		];
		makeLog($data);
		$makeTag['status'] = 'error';
		$makeTag['title'] = '応募状況変更エラー';
		$makeTag['msg'] = 'トランザクション開始に失敗しました。';
	}
}
#-------------#
#前回の状態維持
$searchConditionsSessionKey = 'searchConditions_master04_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'facility_id' => $facId,
		'facility_name' => '',
		'referName' => '',
		'referJobType' => '',
		'startDay' => '',
		'endDay' => '',
		'initials' => [],
		'searchMode' => $searchMode,
		'sortTarget' => 'application_at',
		'applicationSortOrder' => 'desc',
		'interviewSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['facility_id', 'facility_name', 'referName', 'referJobType', 'startDay', 'endDay', 'initials', 'searchMode', 'sortTarget', 'applicationSortOrder', 'interviewSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		$allowedSearchModes = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
		$fixedSearchMode = isset($prevSearchConditions['searchMode']) ? (string)$prevSearchConditions['searchMode'] : $searchMode;
		if (!isset($allowedSearchModes[$fixedSearchMode])) {
			$fixedSearchMode = 'all';
		}
		$fixedSortTarget = isset($prevSearchConditions['sortTarget']) ? (string)$prevSearchConditions['sortTarget'] : 'application_at';
		if ($fixedSortTarget !== 'application_at' && $fixedSortTarget !== 'interview_at') {
			$fixedSortTarget = 'application_at';
		}
		$fixedApplicationSortOrder = isset($prevSearchConditions['applicationSortOrder']) ? strtolower((string)$prevSearchConditions['applicationSortOrder']) : 'desc';
		if ($fixedApplicationSortOrder !== 'asc' && $fixedApplicationSortOrder !== 'desc') {
			$fixedApplicationSortOrder = 'desc';
		}
		$fixedInterviewSortOrder = isset($prevSearchConditions['interviewSortOrder']) ? strtolower((string)$prevSearchConditions['interviewSortOrder']) : 'desc';
		if ($fixedInterviewSortOrder !== 'asc' && $fixedInterviewSortOrder !== 'desc') {
			$fixedInterviewSortOrder = 'desc';
		}
		$prevSearchConditions = [
			'facility_id' => isset($prevSearchConditions['facility_id']) ? (int)$prevSearchConditions['facility_id'] : '',
			'facility_name' => isset($prevSearchConditions['facility_name']) ? (string)$prevSearchConditions['facility_name'] : '',
			'referName' => isset($prevSearchConditions['referName']) ? (string)$prevSearchConditions['referName'] : '',
			'referJobType' => isset($prevSearchConditions['referJobType']) ? (string)$prevSearchConditions['referJobType'] : '',
			'startDay' => isset($prevSearchConditions['startDay']) ? (string)$prevSearchConditions['startDay'] : '',
			'endDay' => isset($prevSearchConditions['endDay']) ? (string)$prevSearchConditions['endDay'] : '',
			'initials' => isset($prevSearchConditions['initials']) ? (array)$prevSearchConditions['initials'] : [],
			'searchMode' => $fixedSearchMode,
			'sortTarget' => $fixedSortTarget,
			'applicationSortOrder' => $fixedApplicationSortOrder,
			'interviewSortOrder' => $fixedInterviewSortOrder,
			'displayNumber' => isset($prevSearchConditions['displayNumber']) ? (int)$prevSearchConditions['displayNumber'] : $initialDisplayNumber,
			'pageNumber' => isset($prevSearchConditions['pageNumber']) ? (int)$prevSearchConditions['pageNumber'] : 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}
#-------------#
$prevSearchMode = isset($prevSearchConditions['searchMode']) ? (string)$prevSearchConditions['searchMode'] : 'all';
#前回の検索条件
$prevFacilityName = isset($prevSearchConditions['facility_name']) ? (string)$prevSearchConditions['facility_name'] : '';
$prevReferName = isset($prevSearchConditions['referName']) ? (string)$prevSearchConditions['referName'] : '';
$prevReferJobType = isset($prevSearchConditions['referJobType']) ? (string)$prevSearchConditions['referJobType'] : '';
$prevStartDay = isset($prevSearchConditions['startDay']) ? (string)$prevSearchConditions['startDay'] : '';
$prevEndDay = isset($prevSearchConditions['endDay']) ? (string)$prevSearchConditions['endDay'] : '';
$prevInitials = isset($prevSearchConditions['initials']) ? (array)$prevSearchConditions['initials'] : [];
#facility_id が未指定なら前回条件を引き継ぐ（masterは常に0/空でOK）
if ($facId < 1) {
	$facId = isset($prevSearchConditions['facility_id']) ? (int)$prevSearchConditions['facility_id'] : 0;
}
#searchMode が none/空 の場合は前回条件を引き継ぐ（表示件数変更など）
if ($searchMode === '' || $searchMode === 'none') {
	$searchMode = $prevSearchMode;
}

#searchMode の正規化（想定外キーでのundefined index等を防止）
$allowedSearchModes = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
if (!isset($allowedSearchModes[$searchMode])) {
	$searchMode = 'all';
}
$prevSortTarget = $prevSearchConditions['sortTarget'];
$prevApplicationSortOrder = strtolower((string)$prevSearchConditions['applicationSortOrder']);
$prevInterviewSortOrder = strtolower((string)$prevSearchConditions['interviewSortOrder']);
if ($prevApplicationSortOrder !== 'asc' && $prevApplicationSortOrder !== 'desc') {
	$prevApplicationSortOrder = 'desc';
}
if ($prevInterviewSortOrder !== 'asc' && $prevInterviewSortOrder !== 'desc') {
	$prevInterviewSortOrder = 'desc';
}
$sortTarget = $prevSortTarget;
$applicationSortOrder = $prevApplicationSortOrder;
$interviewSortOrder = $prevInterviewSortOrder;
#ソートモード指定があれば上書き
if ($sortMode !== '' && $sortMode !== 'none') {
	switch ($sortMode) {
		#--------------
		# 応募日順にソート
		#--------------
		#応募日の昇順
		case 'sortApplicationsDate_asc': {
				$sortTarget = 'application_at';
				$applicationSortOrder = 'asc';
			}
			break;
		#応募日の降順
		case 'sortApplicationsDate_desc': {
				$sortTarget = 'application_at';
				$applicationSortOrder = 'desc';
			}
			break;
		#----------------
		# 面接日順にソート
		#----------------
		#面接日の昇順
		case 'sortInterviewDate_asc': {
				$sortTarget = 'interview_at';
				$interviewSortOrder = 'asc';
			}
			break;
		#面接日の降順
		case 'sortInterviewDate_desc': {
				$sortTarget = 'interview_at';
				$interviewSortOrder = 'desc';
			}
			break;
		#デフォルト：前回条件
		default:
			$sortTarget = $prevSortTarget;
			$applicationSortOrder = $prevApplicationSortOrder;
			$interviewSortOrder = $prevInterviewSortOrder;
			break;
	}
}
#ソートモードのアクティブ判定（応募日・面接日 両方に付与）
$sortApplicationsAscActive = (strtolower((string)$applicationSortOrder) === 'asc') ? 'is-active' : '';
$sortApplicationsDescActive = (strtolower((string)$applicationSortOrder) === 'asc') ? '' : 'is-active';
$sortInterviewDateAscActive = (strtolower((string)$interviewSortOrder) === 'asc') ? 'is-active' : '';
$sortInterviewDateDescActive = (strtolower((string)$interviewSortOrder) === 'asc') ? '' : 'is-active';
#表示側へ渡すソートモード文字列（主ソート：ページ移動等で維持する）
$sortModeValue = 'none';
if ($sortTarget === 'application_at') {
	$sortModeValue = 'sortApplicationsDate_' . strtolower((string)$applicationSortOrder);
} elseif ($sortTarget === 'interview_at') {
	$sortModeValue = 'sortInterviewDate_' . strtolower((string)$interviewSortOrder);
}
#-------------#
#検索条件配列生成してSESSIONに保存
switch ($action) {
	#条件で検索
	case 'search':
	case 'release':
	case 'reset':
	case 'changeStatus': {
			#検索条件が変わる操作は原則1ページ目に戻す
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			#検索条件（reset/release含む）
			if ($action === 'reset') {
				$facilityName = '';
				$referName = '';
				$referJobType = '';
				$startDay = '';
				$endDay = '';
				$initials = [];
				$searchMode = 'all';
			}
			if ($action === 'release') {
				$initials = [];
			}
			$searchConditions = [
				'facility_id' => $facId,
				'facility_name' => $facilityName,
				'referName' => $referName,
				'referJobType' => $referJobType,
				'startDay' => $startDay,
				'endDay' => $endDay,
				'initials' => $initials,
				'searchMode' => $searchMode,
				'sortTarget' => $sortTarget,
				'applicationSortOrder' => $applicationSortOrder,
				'interviewSortOrder' => $interviewSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'facility_id' => $facId,
				'facility_name' => $prevFacilityName,
				'referName' => $prevReferName,
				'referJobType' => $prevReferJobType,
				'startDay' => $prevStartDay,
				'endDay' => $prevEndDay,
				'initials' => $prevInitials,
				'searchMode' => $searchMode,
				'sortTarget' => $sortTarget,
				'applicationSortOrder' => $applicationSortOrder,
				'interviewSortOrder' => $interviewSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'facility_id' => $facId,
				'facility_name' => '',
				'referName' => '',
				'referJobType' => '',
				'startDay' => '',
				'endDay' => '',
				'initials' => [],
				'searchMode' => 'all',
				'sortTarget' => 'application_at',
				'applicationSortOrder' => 'desc',
				'interviewSortOrder' => 'desc',
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
$searchMode = isset($searchConditions['searchMode']) && (string)$searchConditions['searchMode'] !== '' ? (string)$searchConditions['searchMode'] : 'all';
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
if ($displayNumber < 1) {
	$displayNumber = $initialDisplayNumber;
}
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
#総件数（ページャー用）
$totalApplicationCount = isset($applicationCounts[$searchMode]) ? (int)$applicationCounts[$searchMode] : 0;
$totalPages = (int)ceil($totalApplicationCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
}
#応募者一覧取得（LIMIT/OFFSET）
$applicationsList = getApplicationList($searchConditions, $pageNumber, $displayNumber);
#該当件数（表示用：総件数）
$applicationCount = $totalApplicationCount;

#inline JS 用（searchConditions() 引数）
$searchModeJs = json_encode((string)$searchMode, $jsonHex);
$sortModeValueJs = json_encode((string)$sortModeValue, $jsonHex);
$sortModeValueEsc = htmlspecialchars((string)$sortModeValue, ENT_QUOTES, 'UTF-8');
$searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
$sortModeValueJsAttr = htmlspecialchars((string)$sortModeValueJs, ENT_QUOTES, 'UTF-8');

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-search-results" data-current-sort-mode="{$sortModeValueEsc}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$applicationCount}件</span>が該当</p>
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
                    <input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none',{$sortModeValueJsAttr})">
                    <label for="display{$number}">{$number}</label>
                  </li>

HTML;
}
$makeTag['tag'] .= <<<HTML
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
	#searchMode='all' は画面上は未選択扱いだが、一覧は全ステータスを表示する
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
			$sortTarget,
			$applicationSortOrder,
			$interviewSortOrder,
			isset($searchConditions['facility_id']) ? (int)$searchConditions['facility_id'] : 0,
			isset($searchConditions['referJobType']) ? (string)$searchConditions['referJobType'] : ''
		);
		$makeTag['tag'] .= <<<HTML
            <!-- NOTE  インラインでz-indexを付与 -->
            <li {$zIndexStyle} onclick="location.href='./master04_01_01.php?appId={$application['application_id']}'">
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
				$makeTag['tag'] .= <<<HTML
                <li>
                  <div class="item-job"><span>{$jobCategoryNameEsc}</span></div>
                  <div class="item-destination">
                    <span>{$facilityNameEsc}</span>
                  </div>

HTML;
				#応募状況ステータスが選択されていたら
				if (isset($db_applicationStatus) && $db_applicationStatus != '' && $db_applicationStatus != 'friend_only') {
					$makeTag['tag'] .= <<<HTML
                  <div class="wrap-apply-status">
                    <!--NOTE  連番注意 list01-status- -->
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
								$makeTag['tag'] .= <<<HTML
										<input type="hidden" name="application_status{$jobKey}" value="{$appStatusKeyEsc}" data-selectbox-hidden>
										<span class="selectbox__value" data-selectbox-value>{$appStatusEsc}</span>
                        <i></i>

HTML;
							}
						}
					} else {
						$makeTag['tag'] .= <<<HTML
                        <input type="hidden" name="application_status{$jobKey}" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>
                        <i></i>

HTML;
					}
					$makeTag['tag'] .= <<<HTML
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
							$lineUserIdJs = json_encode((string)($application['line_user_id'] ?? ''), $jsonHex);
							$sendStatusChangeNameJs = json_encode((string)$sendStatusChangeName, $jsonHex);
							$searchModeJs = json_encode((string)$searchMode, $jsonHex);
							$sortModeJs = json_encode((string)$sortModeValue, $jsonHex);
							$lineUserIdJsAttr = htmlspecialchars((string)$lineUserIdJs, ENT_QUOTES, 'UTF-8');
							$sendStatusChangeNameJsAttr = htmlspecialchars((string)$sendStatusChangeNameJs, ENT_QUOTES, 'UTF-8');
							$searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
							$sortModeJsAttr = htmlspecialchars((string)$sortModeJs, ENT_QUOTES, 'UTF-8');
							$appliedJobsFacIdInt = (int)$appliedJobsFacId;
							$jobIdInt = (int)($jobData['job_id'] ?? 0);
							$makeTag['tag'] .= <<<HTML
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li {$zIndexStyleStatus}>
                            <input type="radio" name="application_status{$jobKey}" value="{$appStatusKeyEsc}" id="application_status{$jobKey}-{$appStatusKeyEsc}" {$checked} onchange="checkApplicationStatus({$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr});">
                            <label for="application_status{$jobKey}-{$appStatusKeyEsc}" class="status-{$appStatusKeyEsc}">{$appStatusEsc}</label>
                          </li>

HTML;
						}
					} else {
						$makeTag['tag'] .= <<<HTML
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input type="radio" name="application_status{$jobKey}" value="1" id="application_status{$jobKey}-none" checked>
                            <label for="application_status{$jobKey}-none" class="status-registered">応募状況ステータスが未設定です</label>
                          </li>

HTML;
					}
					$makeTag['tag'] .= <<<HTML
                        </ul>
                      </div>
                    </div>

HTML;
				} else {
					$makeTag['tag'] .= <<<HTML
                  <div></div>

HTML;
				}
				$makeTag['tag'] .= <<<HTML
                  </div>

HTML;
				if ($db_applicationStatus == 'friend_only') {
					$makeTag['tag'] .= <<<HTML
                  <div class="item-date">{$appliedDateEsc}</div>

HTML;
				} else {
					$makeTag['tag'] .= <<<HTML
                  <div class="item-date date-apply">{$appliedDateEsc}</div>
                  <div class="item-date">{$interviewAtDateEsc}</div>

HTML;
				}
				$makeTag['tag'] .= <<<HTML
                </li>

HTML;
			}
		}
		$makeTag['tag'] .= <<<HTML
              </ul>
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
