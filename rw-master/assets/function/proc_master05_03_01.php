<?php
/*
 * [rw-master/assets/function/proc_master05_03_01.php]
 *  - 管理画面 -
 *  事業所へのお知らせ：検索/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.02.10
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
#事業所へのお知らせ
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facility_notifications.php';

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
#-------------#
#検索・リセット
$action = isset($_POST['action']) ? $_POST['action'] : '';
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#-------------#
#最終更新日
$searchStartDay = isset($_POST['searchStartDay']) ? $_POST['searchStartDay'] : null;
$searchEndDay = isset($_POST['searchEndDay']) ? $_POST['searchEndDay'] : null;
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
#-------------#
#ソートモード
$sortTarget = '';
$idSortOrder = '';
$updateDateSortOrder = '';
#-------------#
#前回のソート状態（sortMode=none などのときに維持）
$searchConditionsSessionKey = 'searchConditions_master05_03_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'startDay' => '',
		'endDay' => '',
		'sortTarget' => 'notification_id',
		'idSortOrder' => 'desc',
		'updateDateSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['startDay', 'endDay', 'sortTarget', 'idSortOrder', 'updateDateSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		$prevSearchConditions = [
			'startDay' => '',
			'endDay' => '',
			'sortTarget' => 'notification_id',
			'idSortOrder' => 'desc',
			'updateDateSortOrder' => 'desc',
			'displayNumber' => $initialDisplayNumber,
			'pageNumber' => 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}

$prevSortTarget = isset($prevSearchConditions['sortTarget']) ? (string)$prevSearchConditions['sortTarget'] : 'notification_id';
$prevIdSortOrder = strtolower((string)($prevSearchConditions['idSortOrder'] ?? 'desc'));
$prevUpdateDateSortOrder = strtolower((string)($prevSearchConditions['updateDateSortOrder'] ?? 'desc'));
if ($prevIdSortOrder !== 'asc' && $prevIdSortOrder !== 'desc') {
	$prevIdSortOrder = 'desc';
}
if ($prevUpdateDateSortOrder !== 'asc' && $prevUpdateDateSortOrder !== 'desc') {
	$prevUpdateDateSortOrder = 'desc';
}

$sortTarget = $prevSortTarget;
$idSortOrder = $prevIdSortOrder;
$updateDateSortOrder = $prevUpdateDateSortOrder;

if ($sortMode !== '' && $sortMode !== 'none') {
	switch ($sortMode) {
		#--------------
		# 番号順にソート
		#--------------
		case 'sortId_asc': {
				$sortTarget = 'notification_id';
				$idSortOrder = 'asc';
			}
			break;
		case 'sortId_desc': {
				$sortTarget = 'notification_id';
				$idSortOrder = 'desc';
			}
			break;
		#----------------
		# 更新日順にソート
		#----------------
		case 'sortUpdateDate_asc': {
				$sortTarget = 'updated_at';
				$updateDateSortOrder = 'asc';
			}
			break;
		case 'sortUpdateDate_desc': {
				$sortTarget = 'updated_at';
				$updateDateSortOrder = 'desc';
			}
			break;
		default:
			$sortTarget = $prevSortTarget;
			$idSortOrder = $prevIdSortOrder;
			$updateDateSortOrder = $prevUpdateDateSortOrder;
			break;
	}
}

#ソートモードのアクティブ判定（番号・契約日 両方に付与）
$sortIdAscActive = '';
$sortIdDescActive = '';
$sortUpdateDateAscActive = '';
$sortUpdateDateDescActive = '';
if ($sortTarget === 'updated_at') {
	#主ソート：更新日（更新日のみアクティブ表示）
	$sortUpdateDateAscActive = (strtolower((string)$updateDateSortOrder) === 'asc') ? 'is-active' : '';
	$sortUpdateDateDescActive = (strtolower((string)$updateDateSortOrder) === 'asc') ? '' : 'is-active';
} else {
	#主ソート：番号（番号のみアクティブ表示）
	$sortIdAscActive = (strtolower((string)$idSortOrder) === 'asc') ? 'is-active' : '';
	$sortIdDescActive = (strtolower((string)$idSortOrder) === 'asc') ? '' : 'is-active';
}

#表示側へ渡すソートモード文字列（主ソート：ページ移動等で維持する）
$sortModeValue = 'none';
if ($sortTarget === 'updated_at') {
	$sortModeValue = 'sortUpdateDate_' . strtolower((string)$updateDateSortOrder);
} else {
	$sortModeValue = 'sortId_' . strtolower((string)$idSortOrder);
}

#-------------#
/**
 * notification画像パスを「/db」配下の相対パスへ正規化する
 *  - DB保存用: 例) 'notification/notification_0001/image1.jpg'
 *  - 既存互換: '/db/notification/...' や DOMAIN_NAME 付きも許容
 */
function notificationDbRelFromStoredPath($path)
{
	$path = (string)$path;
	if ($path === '') return '';
	$parsedPath = parse_url($path, PHP_URL_PATH);
	if (is_string($parsedPath) && $parsedPath !== '') {
		$path = $parsedPath;
	}
	$path = str_replace('\\', '/', $path);
	$pos = strpos($path, '/db/');
	if ($pos !== false) {
		return ltrim(substr($path, $pos + 4), '/');
	}
	if (strpos($path, 'db/') === 0) {
		return substr($path, 3);
	}
	return ltrim($path, '/');
}

#-------------#
#検索条件配列生成してSESSIONに保存
switch ($action) {
	#***** ステータス変更 *****#
	case 'changeStatus': {
			try {
				#トランザクション開始
				# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
				$result = DB_Transaction(1);
				if ($result == false) {
					#エラーログ出力
					$data = [
						'pageName' => 'proc_master05_03_01',
						'reason' => 'トランザクション開始失敗',
					];
					makeLog($data);
					$makeTag['status'] = 'error';
					$makeTag['title'] = '登録エラー';
					$makeTag['msg'] = 'トランザクション開始に失敗しました。';
					header('Content-Type: application/json');
					echo json_encode($makeTag);
					exit;
				} else {
					#ステータス変更お知らせID
					$changeNotificationId = isset($_POST['changeNotificationId']) ? intval($_POST['changeNotificationId']) : 0;
					#ステータス変更お知らせコード
					$changeNotificationCode = isset($_POST['changeNotificationCode']) ? trim((string)$_POST['changeNotificationCode']) : '';
					#変更後ステータス
					$change_status = isset($_POST['changeStatus']) ? trim((string)$_POST['changeStatus']) : 'draft';
					#登録用配列：初期化
					$dbFiledData = array();
					#登録情報セット
					$dbFiledData['status'] = array(':status', $change_status, 1);
					if ($change_status === 'public') {
						$dbFiledData['published_start'] = array(':published_start', date("Y-m-d H:i:s"), 0);
					} else {
						$dbFiledData['published_start'] = array(':published_start', null, 0);
					}
					$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
					#更新用キー：初期化
					$dbFiledValue = array();
					$dbFiledValue['notification_id'] = array(':notification_id', $changeNotificationId, 1);
					#処理モード：[1].新規追加｜[2].更新｜[3].削除
					$processFlg = 2;
					#DB更新
					#実行モード：[1].トランザクション｜[2].即実行
					$exeFlg = 2;
					$dbSuccessFlg = SQL_Process($DB_CONNECT, "facility_notifications", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
					if ($dbSuccessFlg == 1) {
						#DBコミット
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(2);
						#応答
						$makeTag['status'] = 'success';
						$makeTag['title'] = 'ステータス';
						switch ($change_status) {
							#***** 下書き中 *****#
							case 'draft': {
									$makeTag['msg'] = 'お知らせ番号：' . $changeNotificationId . 'を<span style="font-weight:bold;">下書き中</span>で登録しました。';
								}
								break;
							#***** 公開中 *****#
							case 'public': {
									$makeTag['msg'] = 'お知らせ番号：' . $changeNotificationId . 'を<span style="font-weight:bold;">公開中</span>で登録しました。';
								}
								break;
						}
					} else {
						#DBロールバック
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(3);
						$makeTag['status'] = 'error';
						$makeTag['title'] = 'ステータス';
						$makeTag['msg'] = 'ステータスの更新に失敗しました。';
					}
				}
			} catch (Exception $e) {
				#エラーログ出力
				$data = [
					'pageName' => 'proc_master05_03_01',
					'reason' => 'トランザクション開始失敗',
					'errorMessage' => $e->getMessage(),
				];
				makeLog($data);
				$makeTag['status'] = 'error';
			}
		}
		#json応答
		header('Content-Type: application/json');
		echo json_encode($makeTag);
		exit;
		break;
	#条件で検索
	case 'search': {
			#検索条件が変わる操作は原則1ページ目に戻す
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			$searchConditions = [
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'updateDateSortOrder' => $updateDateSortOrder,
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
				'startDay' => '',
				'endDay' => '',
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'updateDateSortOrder' => $updateDateSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'startDay' => $searchStartDay,
				'endDay' => $searchEndDay,
				'sortTarget' => $sortTarget,
				'idSortOrder' => $idSortOrder,
				'updateDateSortOrder' => $updateDateSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'startDay' => '',
				'endDay' => '',
				'sortTarget' => 'notification_id',
				'idSortOrder' => 'desc',
				'updateDateSortOrder' => 'desc',
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
$totalNotificationsCount = searchFacilityNotificationsCount($searchConditions);
$totalPages = (int)ceil($totalNotificationsCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
}
#お知らせ一覧取得（LIMIT/OFFSET）
$notificationsList = searchFacilityNotificationsList($searchConditions, $pageNumber, $displayNumber);
#該当件数（表示用：総件数）
$notificationsCount = $totalNotificationsCount;

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-search-results" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$notificationsCount}件</span>が該当</p>
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
              <div>記事</div>
              <div>ステータス</div>
              <div>
                最終更新日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortUpdateDateAscActive}" onclick="searchConditions('search','sortUpdateDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortUpdateDateDescActive}" onclick="searchConditions('search','sortUpdateDate_desc')"></button>
                </span>
              </div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($notificationsList) && count($notificationsList) > 0) {
	$zIndexNo = count($notificationsList);
	foreach ($notificationsList as $notificationKey => $notificationData) {
		#Liのz-index設定
		$zIndexStyle = 'style="z-index:' . ($zIndexNo - $notificationKey) . ';"';
		#お知らせID
		$notificationId = isset($notificationData['notification_id']) ? intval($notificationData['notification_id']) : 0;
		#お知らせコード
		$notificationCode = isset($notificationData['code']) ? (string)$notificationData['code'] : '';
		$notificationCodeAttr = htmlspecialchars($notificationCode, ENT_QUOTES, 'UTF-8');
		#お知らせタイトル
		$notificationTitle = isset($notificationData['title']) ? htmlspecialchars($notificationData['title'], ENT_QUOTES, 'UTF-8') : '';
		#お知らせ本文プレーンテキスト
		$notificationBodyText = isset($notificationData['body_text']) ? htmlspecialchars($notificationData['body_text'], ENT_QUOTES, 'UTF-8') : '';
		#公開ステータス
		$notificationStatus = isset($notificationData['status']) ? intval($notificationData['status']) : 0;
		#登録日・更新日
		$notificationCreatedAt = isset($notificationData['created_at']) ? htmlspecialchars($notificationData['created_at'], ENT_QUOTES, 'UTF-8') : '';
		$notificationUpdatedAt = isset($notificationData['updated_at']) ? htmlspecialchars($notificationData['updated_at'], ENT_QUOTES, 'UTF-8') : '';
		#更新日フォーマット
		$formattedUpdatedAt = '';
		if ($notificationUpdatedAt !== '') {
			$formattedUpdatedAt = date('Y/m/d', strtotime($notificationUpdatedAt));
		} elseif ($notificationCreatedAt !== '') {
			$formattedUpdatedAt = date('Y/m/d', strtotime($notificationCreatedAt));
		}
		#公開ステータス「name」属性連番対応
		$statusName = 'list_status' . $notificationId;
		#checked判定
		$checkedDraft = ($notificationData['status'] == 'draft') ? 'checked' : '';
		$checkedPublic = ($notificationData['status'] == 'public') ? 'checked' : '';
		#value値／label設定
		$valueName = ($notificationData['status'] == 'draft') ? 'draft' : 'public';
		$labelName = ($notificationData['status'] == 'draft') ? '下書き中' : '公開中';
		$makeTag['tag'] .= <<<HTML
            <!-- NOTE  インラインでz-indexを付与 -->
            <li {$zIndexStyle} onclick="location.href='./master05_03_02.php?method=edit&notificationId={$notificationId}'">
              <div class="item-number">{$notificationId}</div>
              <div class="item-title">{$notificationTitle}</div>
              <div class="box-status">
                <div class="select-status" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">
                    <input type="hidden" name="{$statusName}" value="{$valueName}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$labelName}</span>
                    <i></i>
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">
                      <li>
                        <input type="radio" name="{$statusName}" value="draft" id="list{$notificationId}-status01" {$checkedDraft} data-notification-code="{$notificationCodeAttr}" onclick="event.stopPropagation();checkNotificationStatus({$notificationId}, this.getAttribute('data-notification-code'), 'draft');">
                        <label for="list{$notificationId}-status01" class="status-draft">下書き中</label>
                      </li>
                      <li>
                        <input type="radio" name="{$statusName}" value="public" id="list{$notificationId}-status02" {$checkedPublic} data-notification-code="{$notificationCodeAttr}" onclick="event.stopPropagation();checkNotificationStatus({$notificationId}, this.getAttribute('data-notification-code'), 'public');">
                        <label for="list{$notificationId}-status02" class="status-published">公開中</label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="item-date">{$formattedUpdatedAt}</div>
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
