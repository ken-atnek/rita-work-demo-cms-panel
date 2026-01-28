<?php
/*
 * [rw-master/assets/function/proc_master01_01.php]
 *  - 管理画面 -
 *  応募者一覧：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.1.27
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
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
		makeLog('[proc_master03_01] master JSON load failed: ' . $e->getMessage());
	}
	$jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];

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
#検索モード
$searchMode = isset($_POST['searchMode']) ? $_POST['searchMode'] : '';
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#-------------#
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
#-------------#
#前回の状態維持
$searchConditionsSessionKey = 'searchConditions_master01_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'searchMode' => $searchMode,
		'sortTarget' => 'application_at',
		'applicationSortOrder' => 'desc',
		'interviewSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['searchMode', 'sortTarget', 'applicationSortOrder', 'interviewSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		#後方互換：旧形式（sortOrderのみ）の場合は主キー側だけ引き継いで補完
		$legacyTarget = isset($prevSearchConditions['sortTarget']) ? (string)$prevSearchConditions['sortTarget'] : 'application_at';
		if ($legacyTarget !== 'application_at' && $legacyTarget !== 'interview_at') {
			$legacyTarget = 'application_at';
		}
		$legacyOrder = isset($prevSearchConditions['sortOrder']) ? strtolower((string)$prevSearchConditions['sortOrder']) : 'desc';
		if ($legacyOrder !== 'asc' && $legacyOrder !== 'desc') {
			$legacyOrder = 'desc';
		}
		$prevSearchConditions = [
			'searchMode' => isset($prevSearchConditions['searchMode']) ? (string)$prevSearchConditions['searchMode'] : $searchMode,
			'sortTarget' => $legacyTarget,
			'applicationSortOrder' => ($legacyTarget === 'application_at') ? $legacyOrder : 'desc',
			'interviewSortOrder' => ($legacyTarget === 'interview_at') ? $legacyOrder : 'desc',
			'displayNumber' => isset($prevSearchConditions['displayNumber']) ? (int)$prevSearchConditions['displayNumber'] : $initialDisplayNumber,
			'pageNumber' => isset($prevSearchConditions['pageNumber']) ? (int)$prevSearchConditions['pageNumber'] : 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}
#-------------#
$prevSearchMode = isset($prevSearchConditions['searchMode']) ? (string)$prevSearchConditions['searchMode'] : 'registered';
#searchMode が none/空 の場合は前回条件を引き継ぐ（表示件数変更など）
if ($searchMode === '' || $searchMode === 'none') {
	$searchMode = $prevSearchMode;
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
	case 'search': {
			#検索条件が変わる操作は原則1ページ目に戻す
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			$searchConditions = [
				'searchMode' => $searchMode,
				'sortTarget' => $sortTarget,
				'applicationSortOrder' => $applicationSortOrder,
				'interviewSortOrder' => $interviewSortOrder,
				#後方互換：主ソートの順序を sortOrder としても保持
				'sortOrder' => ($sortTarget === 'interview_at') ? $interviewSortOrder : $applicationSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'searchMode' => $searchMode,
				'sortTarget' => $sortTarget,
				'applicationSortOrder' => $applicationSortOrder,
				'interviewSortOrder' => $interviewSortOrder,
				'sortOrder' => ($sortTarget === 'interview_at') ? $interviewSortOrder : $applicationSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'searchMode' => 'registered',
				'sortTarget' => 'application_at',
				'applicationSortOrder' => 'desc',
				'interviewSortOrder' => 'desc',
				'sortOrder' => 'desc',
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
$searchMode = isset($searchConditions['searchMode']) && (string)$searchConditions['searchMode'] !== '' ? (string)$searchConditions['searchMode'] : 'registered';
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
if ($displayNumber < 1) {
	$displayNumber = $initialDisplayNumber;
}
#応募人数取得
$applicationCounts = [];
foreach ($applicationMasterSetting as $statusKey => $status) {
	$applicationCounts[$statusKey] = (int)getApplicationCount($statusKey);
}
#キーが無い場合も想定して0で補完
foreach (array_keys($applicationMasterSetting) as $statusKey) {
	if (array_key_exists($statusKey, $applicationCounts) === false) {
		$applicationCounts[$statusKey] = 0;
	}
}
#総件数（ページャー用）
$totalApplicationCount = $applicationCounts[$searchMode];
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

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-search-results" data-current-sort-mode="{$sortModeValue}">
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
										<input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none','{$sortModeValue}')">
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
                  <button type="button" class="arrow-top {$sortApplicationsAscActive}" onclick="searchConditions('search','{$searchMode}','sortApplicationsDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortApplicationsDescActive}" onclick="searchConditions('search','{$searchMode}','sortApplicationsDate_desc')"></button>
                </span>
              </div>
              <div>
                面接日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortInterviewDateAscActive}" onclick="searchConditions('search','{$searchMode}','sortInterviewDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortInterviewDateDescActive}" onclick="searchConditions('search','{$searchMode}','sortInterviewDate_desc')"></button>
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
		#応募中の求人情報を取得
		$appliedJobs = getAllAppliedJobs(
			$application['line_user_id'],
			$statusesForTab,
			$sortTarget,
			$applicationSortOrder,
			$interviewSortOrder
		);
		$makeTag['tag'] .= <<<HTML
            <!-- NOTE  インラインでz-indexを付与 -->
            <li {$zIndexStyle} onclick="location.href='./master01_01_01.php?application_id={$application['application_id']}'">
              <div class="item-name">{$lineDisplayNameEsc}</div>
              <ul class="list-contact">

HTML;
		if (is_array($appliedJobs) && count($appliedJobs) > 0) {
			foreach ($appliedJobs as $jobData) {
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
								$makeTag['tag'] .= <<<HTML
                        <input type="hidden" name="application_status" value="{$appStatusKey}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$appStatus}</span>
                        <i></i>

HTML;
							}
						}
					} else {
						$makeTag['tag'] .= <<<HTML
                        <input type="hidden" name="application_status" value="" data-selectbox-hidden>
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
							$makeTag['tag'] .= <<<HTML
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li {$zIndexStyleStatus}>
                            <input type="radio" name="application_status" value="{$appStatusKey}" id="application_status-{$appStatusKey}" {$checked}>
                            <label for="application_status-{$appStatusKey}" class="status-{$appStatusKey}">{$appStatus}</label>
                          </li>

HTML;
						}
					} else {
						$makeTag['tag'] .= <<<HTML
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input type="radio" name="application_status" value="1" id="application_status-none" checked>
                            <label for="application_status-none" class="status-registered">応募状況ステータスが未設定です</label>
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
            <div class="item-date applied">{$appliedDateEsc}</div>
            <div class="item-date">{$interviewAtDateEsc}</div>

HTML;
				}
				$makeTag['tag'] .= <<<HTML
                </li>

HTML;
			}
			$makeTag['tag'] .= <<<HTML
              </ul>
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
$makeTag['tag'] .= makePagerBoxTag((int)$pageNumber, (int)$totalPages, $pagerDisplayMax, 'movePage');
$makeTag['tag'] .= <<<HTML
        </article>

HTML;
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
