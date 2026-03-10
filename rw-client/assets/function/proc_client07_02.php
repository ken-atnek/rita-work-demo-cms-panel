<?php
/*
 * [rw-client/assets/function/proc_client07_02.php]
 *  - 【事業所】管理画面 -
 *  メッセージ送信履歴：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.3.7
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/client/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#問い合わせ情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_inquiries.php';

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

#==============#
# 事業所情報取得
#--------------#
#事業所ID
$facId = isset($_SESSION['client_login']['facility_id']) ? $_SESSION['client_login']['facility_id'] : null;
$facilityData = getFacility_FindById($facId);
if (!$facilityData) {
	header('Content-Type: application/json; charset=UTF-8');
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'ログインエラー';
	$makeTag['msg'] = 'ログイン情報が確認できません。ページを再読み込みしてください。';
	echo json_encode($makeTag);
	exit;
}

#-------------#
#検索・リセット
$action = isset($_POST['action']) ? $_POST['action'] : '';
#メッセージ表示
if ($action == 'openModal') {
	#=============#
	# POSTチェック
	#-------------#
	#メッセージID
	$inquiryId = isset($_POST['inquiryId']) ? (int)$_POST['inquiryId'] : 0;
	if ($inquiryId > 0) {
		#メッセージ情報取得（事業所スコープ）
		$inquiryData = getFacilityInquiries_FindByIdForFacility($inquiryId, (int)$facId);
		if (is_array($inquiryData) && count($inquiryData) > 0) {
			#タイトル
			$categoryLabel = '';
			switch ($inquiryData['category']) {
				case 'plan':
					$categoryLabel = 'プランについて';
					break;
				case 'password':
					$categoryLabel = 'パスワードについて';
					break;
				case 'other':
					$categoryLabel = 'その他';
					break;
				default:
					#その他の値や空文字の場合は空のまま
					break;
			}
			#返信希望
			$replyMethodLabel = '';
			switch ($inquiryData['reply_channel']) {
				case 'email':
					$replyMethodLabel = 'メール';
					break;
				case 'phone':
					$replyMethodLabel = '電話';
					break;
				case 'other':
					$replyMethodLabel = 'その他';
					break;
				default:
					#その他の値や空文字の場合は空のまま
					break;
			}
			#本文
			$messageBody = nl2br(htmlspecialchars((string)($inquiryData['body'] ?? ''), ENT_QUOTES, 'UTF-8'));
			#送信日時（一覧と合わせて created_at を基準にする）
			$sendedDate = isset($inquiryData['created_at']) ? date('Y/m/d H:i', strtotime($inquiryData['created_at'])) : '';
			$categoryLabelEsc = htmlspecialchars((string)$categoryLabel, ENT_QUOTES, 'UTF-8');
			$replyMethodLabelEsc = htmlspecialchars((string)$replyMethodLabel, ENT_QUOTES, 'UTF-8');
			#応答用タグ生成
			$makeTag['status'] = 'success';
			$makeTag['tag'] .= <<<HTML
      <div class="inner-modal">
        <div class="box-title">
          <p>{$categoryLabelEsc}</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-from">
          <!-- <span class="item-from"></span> -->
          <span class="item-response">{$replyMethodLabelEsc}</span>
        </div>
        <div class="box-details">
          <div class="wrap-details">
            <span class="item-date">{$sendedDate}</span>
            <div class="item-text">
              {$messageBody}
            </div>
          </div>
          <button type="button" onclick="closeModal()" class="btn-bottom-close">閉じる</button>
        </div>
      </div>

HTML;
		} else {
			$makeTag['status'] = 'error';
			$makeTag['title'] = '取得失敗';
			$makeTag['msg'] = 'メッセージが見つかりませんでした。';
		}
	} else {
		$makeTag['status'] = 'error';
		$makeTag['title'] = '入力エラー';
		$makeTag['msg'] = 'メッセージの指定が正しくありません。';
	}
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode($makeTag);
	exit;
}
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#-------------#
#送信日
$searchStartDay = isset($_POST['searchStartDay']) ? $_POST['searchStartDay'] : null;
$searchEndDay = isset($_POST['searchEndDay']) ? $_POST['searchEndDay'] : null;
#件名
$selectSubject = isset($_POST['selectSubject']) ? $_POST['selectSubject'] : null;
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
#-------------#
#ソート（送信日時：created_at）
$searchConditionsSessionKey = 'searchConditions_client07_02';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'facilityId' => (int)$facId,
		'category' => '',
		'startDay' => '',
		'endDay' => '',
		'sendedSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['facilityId', 'category', 'startDay', 'endDay', 'sendedSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		$prevSearchConditions = [
			'facilityId' => (int)$facId,
			'category' => '',
			'startDay' => '',
			'endDay' => '',
			'sendedSortOrder' => 'desc',
			'displayNumber' => $initialDisplayNumber,
			'pageNumber' => 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}
$sendedSortOrder = isset($prevSearchConditions['sendedSortOrder']) ? (string)$prevSearchConditions['sendedSortOrder'] : 'desc';
if ($sortMode === 'sortSendedDate_asc') {
	$sendedSortOrder = 'asc';
} elseif ($sortMode === 'sortSendedDate_desc') {
	$sendedSortOrder = 'desc';
}
$sortSendedDateAscActive = (strtolower($sendedSortOrder) === 'asc') ? 'is-active' : '';
$sortSendedDateDescActive = (strtolower($sendedSortOrder) === 'asc') ? '' : 'is-active';

#入力値の最低限バリデーション
$allowedSubjects = ['plan', 'password', 'other'];
if (!in_array((string)$selectSubject, $allowedSubjects, true)) {
	$selectSubject = '';
}
foreach (['searchStartDay' => &$searchStartDay, 'searchEndDay' => &$searchEndDay] as $k => &$v) {
	if ($v === null || $v === '') {
		$v = '';
		continue;
	}
	$v = (string)$v;
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) !== 1) {
		$v = '';
	}
}
unset($v);

#表示件数バリデーション
$displayNumber = (int)$displayNumber;
if (!in_array($displayNumber, $displayNumberList, true)) {
	$displayNumber = (int)$initialDisplayNumber;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
}
#-------------#
#検索条件配列生成してSESSIONに保存
switch ($action) {
	#条件で検索
	case 'search': {
			$searchConditions = [
				'facilityId' => (int)$facId,
				'category' => (string)$selectSubject,
				'startDay' => (string)$searchStartDay,
				'endDay' => (string)$searchEndDay,
				'sendedSortOrder' => (string)$sendedSortOrder,
				'displayNumber' => (int)$displayNumber,
				'pageNumber' => 1,
			];
		}
		break;
	#条件をクリア
	case 'reset': {
			$searchConditions = [
				'facilityId' => (int)$facId,
				'category' => '',
				'startDay' => '',
				'endDay' => '',
				'sendedSortOrder' => (string)$sendedSortOrder,
				'displayNumber' => (int)$displayNumber,
				'pageNumber' => 1,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'facilityId' => (int)$facId,
				'category' => isset($prevSearchConditions['category']) ? (string)$prevSearchConditions['category'] : '',
				'startDay' => isset($prevSearchConditions['startDay']) ? (string)$prevSearchConditions['startDay'] : '',
				'endDay' => isset($prevSearchConditions['endDay']) ? (string)$prevSearchConditions['endDay'] : '',
				'sendedSortOrder' => (string)$sendedSortOrder,
				'displayNumber' => (int)$displayNumber,
				'pageNumber' => (int)$pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'facilityId' => (int)$facId,
				'category' => '',
				'startDay' => '',
				'endDay' => '',
				'sendedSortOrder' => (string)$sendedSortOrder,
				'displayNumber' => (int)$displayNumber,
				'pageNumber' => 1,
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
$totalInquiriesCount = searchFacilityInquiriesCount($searchConditions);
$totalPages = (int)ceil($totalInquiriesCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
}
#お問い合わせ一覧取得（LIMIT/OFFSET）
$inquiriesList = searchFacilityInquiriesList($searchConditions, $pageNumber, $displayNumber);
#該当件数（表示用：総件数）
$inquiriesCount = $totalInquiriesCount;
#返却HTML：現在のソートモード（JS初期判定用）
$sendedSortOrderSaved = isset($searchConditions['sendedSortOrder']) ? (string)$searchConditions['sendedSortOrder'] : 'desc';
$sortModeValue = (strtolower($sendedSortOrderSaved) === 'asc') ? 'sortSendedDate_asc' : 'sortSendedDate_desc';

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-history-list" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$inquiriesCount}件</span>が該当</p>
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
                送信日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortSendedDateAscActive}" onclick="searchConditions('search','sortSendedDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortSendedDateDescActive}" onclick="searchConditions('search','sortSendedDate_desc')"></button>
                </span>
              </div>
              <div>件名</div>
              <div>返信希望</div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($inquiriesList) && count($inquiriesList) > 0) {
	foreach ($inquiriesList as $inquiry) {
		$inqId = (int)($inquiry['inquiry_id'] ?? 0);
		$sendedDate = isset($inquiry['created_at']) ? date('Y/m/d H:i', strtotime($inquiry['created_at'])) : '';
		$categoryLabel = '';
		switch ($inquiry['category']) {
			case 'plan':
				$categoryLabel = 'プランについて';
				break;
			case 'password':
				$categoryLabel = 'パスワードについて';
				break;
			case 'other':
				$categoryLabel = 'その他';
				break;
			default:
				#その他の値や空文字の場合は空のまま
				break;
		}
		$replyMethodLabel = '';
		switch ($inquiry['reply_channel']) {
			case 'email':
				$replyMethodLabel = 'メール';
				break;
			case 'phone':
				$replyMethodLabel = '電話';
				break;
			case 'other':
				$replyMethodLabel = 'その他';
				break;
			default:
				#その他の値や空文字の場合は空のまま
				break;
		}
		$makeTag['tag'] .= <<<HTML
            <li onclick="makeInquiryModal('openModal', {$inqId})" style="cursor:pointer;">
              <div class="item-date">{$sendedDate}</div>
              <div class="item-name">{$categoryLabel}</div>
              <div class="item-return">{$replyMethodLabel}</div>
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
#===========================================#
