<?php
/*
 * [rw-master/assets/function/proc_master05_01_01.php]
 *  - 管理画面 -
 *  転職のヒント：検索/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.02.04
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
#転職のヒント
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_tips_articles.php';

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
$searchConditionsSessionKey = 'searchConditions_master05_01_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'startDay' => '',
		'endDay' => '',
		'sortTarget' => 'article_id',
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
			'sortTarget' => 'article_id',
			'idSortOrder' => 'desc',
			'updateDateSortOrder' => 'desc',
			'displayNumber' => $initialDisplayNumber,
			'pageNumber' => 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}
$prevSortTarget = isset($prevSearchConditions['sortTarget']) ? (string)$prevSearchConditions['sortTarget'] : 'article_id';
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
				$sortTarget = 'article_id';
				$idSortOrder = 'asc';
			}
			break;
		case 'sortId_desc': {
				$sortTarget = 'article_id';
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
 * ディレクトリ作成（既存ならtrue）
 */
function ensureDir(string $dir): bool
{
	if ($dir === '') {
		return false;
	}
	if (is_dir($dir)) {
		return true;
	}
	return @mkdir($dir, 0777, true);
}
/**
 * DB保存の body_json を解析し、エディタJSONと表示期間メタを取り出す
 * - 新形式: { editor: <tiptap-json>, period: {...} }
 * - 旧形式: <tiptap-json>
 *
 * @param string $bodyJsonString
 * @return array{0:?array,1:?array} [editorJson|null, period|null]
 */
function decodeBodyPayload($bodyJsonString)
{
	$bodyJsonString = (string)$bodyJsonString;
	if ($bodyJsonString === '') return [null, null];
	$decoded = json_decode($bodyJsonString, true);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
		return [null, null];
	}
	#新形式: { editor: <tiptap json>, period: {...} }
	if (isset($decoded['editor']) && is_array($decoded['editor'])) {
		$period = isset($decoded['period']) && is_array($decoded['period']) ? $decoded['period'] : null;
		return [$decoded['editor'], $period];
	}
	#旧形式: tiptap json そのもの
	if (isset($decoded['type']) && is_string($decoded['type'])) {
		return [$decoded, null];
	}
	return [null, null];
}
/**
 * TipTap JSON内の image.attrs.src を再帰的に書き換える（コールバック指定）
 * - 破壊的変更（$node を参照渡しで更新）
 * - ノード形式の厳密な検証はせず、存在するキーのみを対象にする
 *
 * @param array $node TipTap JSON（node）参照
 * @param callable $callback function(string $src): string
 * @return void
 */
function rewriteEditorJsonImageSrcsByCallback(&$node, $callback)
{
	if (!is_array($node)) return;
	if (isset($node['type']) && $node['type'] === 'image') {
		if (isset($node['attrs']) && is_array($node['attrs']) && isset($node['attrs']['src'])) {
			$src = (string)$node['attrs']['src'];
			$node['attrs']['src'] = (string)call_user_func($callback, $src);
		}
	}
	if (isset($node['content']) && is_array($node['content'])) {
		foreach ($node['content'] as $i => $child) {
			rewriteEditorJsonImageSrcsByCallback($node['content'][$i], $callback);
		}
	}
}
/**
 * tips画像パスを「/db」配下の相対パスへ正規化する
 *  - DB保存用: 例) 'tips/tips_0001/image1.jpg'
 *  - 既存互換: '/db/tips/...' や DOMAIN_NAME 付きも許容
 */
function tipsDbRelFromStoredPath($path)
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
/**
 * tips画像パスをフロント公開用URL（/db/...）へ変換する
 * - 入力は DB相対（tips/...）/「/db/...」/ドメイン付きURL いずれも許容
 *
 * @param mixed $path
 * @return string 例) '/db/tips/tips_0001/image1.jpg'（不正/空なら ''）
 */
function tipsStoredPathToFrontUrl($path)
{
	$rel = tipsDbRelFromStoredPath($path);
	if ($rel === '') return '';
	return '/db/' . ltrim($rel, '/');
}
/**
 * 記事詳細JSON（/db/tips/<code>/detail.json）を書き出す
 * - DBから最新の本文/サムネを取得し、フロント用に /db/... 形式へ展開して出力
 * - TipTap本文中の image.attrs.src も /db/... に統一（外部URLはそのまま）
 *
 * @param int|string $articleId
 * @param string $articleCode
 * @return void
 * @throws RuntimeException JSON書き込みに失敗した場合
 */
function writeTipsDetailJson($articleId, $articleCode)
{
	$row = getTipsArticles_FindById((int)$articleId);
	if (!is_array($row)) return;
	$bodyJson = null;
	if (isset($row['body_json']) && (string)$row['body_json'] !== '') {
		list($editorJson, $periodMeta) = decodeBodyPayload((string)$row['body_json']);
		$bodyJson = $editorJson;
	}
	#JSON出力は常にフロント基準の /db/... に統一
	if (is_array($bodyJson)) {
		rewriteEditorJsonImageSrcsByCallback($bodyJson, function ($src) {
			$src = (string)$src;
			if ($src === '') return $src;
			#外部URLはそのまま
			if (preg_match('/^https?:\/\//i', $src)) return $src;
			#tmp_upload はそのまま（通常DBには残らない想定）
			if (strpos($src, (string)DEFINE_PREVIEW_IMAGE_DIR_PATH) === 0) return $src;
			$rel = tipsDbRelFromStoredPath($src);
			return $rel !== '' ? ('/db/' . ltrim($rel, '/')) : $src;
		});
	}
	#サムネイル画像パス
	$tipsImagePath = '';
	if (isset($row['tips_image_path']) && $row['tips_image_path'] != null) {
		$tipsImageJsonDecoded = json_decode($row['tips_image_path'], true);
		if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
			$tipsImagePath = tipsStoredPathToFrontUrl($tipsImageJsonDecoded[0]);
		}
	}
	#公開日フォーマット
	$publishedAt = (isset($row['published_start']) && (string)$row['published_start'] !== '') ? (string)(date('Y-m-d', strtotime($row['published_start']))) : '';
	$out = array(
		'id' => (string)($row['code'] ?? 0),
		'title' => (string)($row['title'] ?? ''),
		'publishedAt' => $publishedAt,
		'heroImage' => $tipsImagePath,
		'body' => $bodyJson,
	);
	#画像パス配列
	$dir = DEFINE_JSON_DIR_PATH . '/tips/' . $articleCode;
	ensureDir($dir);
	$path = rtrim($dir, '/\\') . '/detail.json';
	$tmp = $path . '.tmp';
	$json = json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($json === false) {
		throw new RuntimeException('JSON生成に失敗しました');
	}
	if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
		throw new RuntimeException('JSON書き込みに失敗しました');
	}
	if (@rename($tmp, $path) === false) {
		@unlink($tmp);
		throw new RuntimeException('JSONファイルの更新に失敗しました');
	}
}
/**
 * 記事一覧・トップJSON（/db/tips/tipsIndex.json／tipsIndexTop.json）を書き出す
 * - DBから最新の本文/サムネを取得し、フロント用に /db/... 形式へ展開して出力
 * - TipTap本文中の image.attrs.src も /db/... に統一（外部URLはそのまま）
 *
 * @return void
 * @throws RuntimeException JSON書き込みに失敗した場合
 */
function writeTipsIndexJson()
{
	$list = getTipsArticlesList();
	// JSONの形を固定（itemsキーは常に配列）
	$listOut = array('items' => array());
	$topOut = array('items' => array());
	foreach ($list as $row) {
		#公開中データのみ表示
		if (isset($row['status']) && (string)$row['status'] == 'public') {
			#公開前・公開終了済みは除外
			$now = time();
			$pubStart = isset($row['published_start']) ? strtotime((string)$row['published_start']) : 0;
			$pubEnd = isset($row['published_end']) && (string)$row['published_end'] !== '' ? strtotime((string)$row['published_end']) : PHP_INT_MAX;
			if ($now < $pubStart || $now > $pubEnd) {
				continue;
			}
			#テキストデータを再度調整
			$bodyText = (string)$row['body_text'];
			#不要な改行除去
			$bodyText = preg_replace('/[\r\n]+/', ' ', $bodyText);
			$bodyText = trim($bodyText);
			#画像パスを /db/... 形式に変換（ベストエフォート）
			$tipsImagePath = '';
			if (isset($row['tips_image_path']) && $row['tips_image_path'] != null) {
				$tipsImageJsonDecoded = json_decode($row['tips_image_path'], true);
				if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
					$tipsImagePath = tipsStoredPathToFrontUrl($tipsImageJsonDecoded[0]);
				}
			}
			#公開日フォーマット
			$publishedAt = (isset($row['published_start']) && (string)$row['published_start'] !== '') ? (string)(date('Y-m-d', strtotime($row['published_start']))) : '';
			#一覧用
			$item = array(
				'id' => (string)($row['code'] ?? ''),
				'title' => (string)($row['title'] ?? ''),
				'summary' => $bodyText,
				'thumbnail' => $tipsImagePath,
				'publishedAt' => $publishedAt,
			);
			$listOut['items'][] = $item;
			if ((int)($row['is_top'] ?? 0) === 1) {
				$itemTop = $item;
				$itemTop['top_sort'] = (int)($row['top_sort'] ?? 0);
				$itemTop['_article_id'] = (int)($row['article_id'] ?? 0);
				$topOut['items'][] = $itemTop;
			}
		}
	}
	#トップページ用 (top_sort：昇順 → id：降順)
	if (isset($topOut['items']) && is_array($topOut['items']) && count($topOut['items']) > 1) {
		usort($topOut['items'], function ($a, $b) {
			$as = (int)($a['top_sort'] ?? 0);
			$bs = (int)($b['top_sort'] ?? 0);
			if ($as !== $bs) return $as <=> $bs;
			$ai = (int)($a['_article_id'] ?? 0);
			$bi = (int)($b['_article_id'] ?? 0);
			return $bi <=> $ai;
		});
	}
	#JSON書き出し
	$baseDir = DEFINE_JSON_DIR_PATH . '/tips';
	ensureDir($baseDir);
	#出力前に内部キーを除去（フロントの仕様を汚さない）
	if (isset($topOut['items']) && is_array($topOut['items'])) {
		foreach ($topOut['items'] as &$it) {
			if (is_array($it) && array_key_exists('top_sort', $it)) {
				unset($it['top_sort']);
			}
			if (is_array($it) && array_key_exists('_article_id', $it)) {
				unset($it['_article_id']);
			}
		}
		unset($it);
	}
	#トップページ用
	$pathTop = rtrim($baseDir, '/\\') . '/tipsIndexTop.json';
	$tmpTop = $pathTop . '.tmp';
	$jsonTop = json_encode($topOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($jsonTop === false) {
		throw new RuntimeException('JSON生成に失敗しました');
	}
	if (file_put_contents($tmpTop, $jsonTop . PHP_EOL, LOCK_EX) === false) {
		throw new RuntimeException('JSON書き込みに失敗しました');
	}
	if (@rename($tmpTop, $pathTop) === false) {
		@unlink($tmpTop);
		throw new RuntimeException('JSONファイルの更新に失敗しました');
	}
	#一覧ページ用
	$pathList = rtrim($baseDir, '/\\') . '/tipsIndex.json';
	$tmpList = $pathList . '.tmp';
	$jsonList = json_encode($listOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($jsonList === false) {
		throw new RuntimeException('JSON生成に失敗しました');
	}
	if (file_put_contents($tmpList, $jsonList . PHP_EOL, LOCK_EX) === false) {
		throw new RuntimeException('JSON書き込みに失敗しました');
	}
	if (@rename($tmpList, $pathList) === false) {
		@unlink($tmpList);
		throw new RuntimeException('JSONファイルの更新に失敗しました');
	}

	#===========================================#
	#全JSON生成が完了した「最後」に実行
	if (defined('DEFINE_JSON_MIRROR_ENABLE') && DEFINE_JSON_MIRROR_ENABLE) {
		mirrorDbSelectiveMasterByRsync(
			(string)DEFINE_JSON_MIRROR_SRC_DB_DIR,         #初期ドメイン側 /db
			(string)DEFINE_JSON_MIRROR_DEST_DB_DIR,        #正式ドメイン側 /db
			(string)DEFINE_JSON_MIRROR_MASTER_ONLY_FILE    #corporations.json
		);
	}
	#===========================================#

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
						'pageName' => 'proc_master05_01_01',
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
					#ステータス変更記事ID
					$changeArticleId = isset($_POST['changeArticleId']) ? intval($_POST['changeArticleId']) : 0;
					#ステータス変更記事コード
					$changeArticleCode = isset($_POST['changeArticleCode']) ? trim((string)$_POST['changeArticleCode']) : '';
					#変更後ステータス
					$change_status = isset($_POST['changeStatus']) ? trim((string)$_POST['changeStatus']) : 'draft';
					#入力バリデーション（最小）
					if ($changeArticleId <= 0) {
						DB_Transaction(3);
						$makeTag['status'] = 'error';
						$makeTag['title'] = '入力エラー';
						$makeTag['msg'] = '不正なリクエストです。';
						header('Content-Type: application/json');
						echo json_encode($makeTag);
						exit;
					}
					if ($changeArticleCode === '' || strlen($changeArticleCode) > 64 || !preg_match('/\Atips_\d{1,20}\z/', $changeArticleCode)) {
						DB_Transaction(3);
						$makeTag['status'] = 'error';
						$makeTag['title'] = '入力エラー';
						$makeTag['msg'] = '記事コードが不正です。';
						header('Content-Type: application/json');
						echo json_encode($makeTag);
						exit;
					}
					if (!in_array($change_status, ['draft', 'public'], true)) {
						DB_Transaction(3);
						$makeTag['status'] = 'error';
						$makeTag['title'] = '入力エラー';
						$makeTag['msg'] = 'ステータスが不正です。';
						header('Content-Type: application/json');
						echo json_encode($makeTag);
						exit;
					}
					#登録用配列：初期化
					$dbFiledData = array();
					#登録情報セット
					$dbFiledData['status'] = array(':status', $change_status, 1);
					if ($change_status === 'public') {
						$dbFiledData['published_start'] = array(':published_start', date("Y-m-d H:i:s"), 0);
					} else {
						$dbFiledData['published_start'] = array(':published_start', null, 1);
					}
					$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
					#更新用キー：初期化
					$dbFiledValue = array();
					$dbFiledValue['article_id'] = array(':article_id', $changeArticleId, 1);
					#処理モード：[1].新規追加｜[2].更新｜[3].削除
					$processFlg = 2;
					#DB更新
					#実行モード：[1].トランザクション｜[2].即実行
					$exeFlg = 2;
					$dbSuccessFlg = SQL_Process($DB_CONNECT, "tips_articles", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
					if ($dbSuccessFlg == 1) {
						#DBコミット
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(2);
						#---------------------------
						# JSON書き出し（DB更新完了後）
						#--------------------------
						#記事詳細JSON書き出し
						writeTipsDetailJson($changeArticleId, $changeArticleCode);
						writeTipsIndexJson();
						$makeTag['status'] = 'success';
						$makeTag['title'] = 'ステータス';
						switch ($change_status) {
							#***** 下書き中 *****#
							case 'draft': {
									$makeTag['msg'] = '記事番号：' . $changeArticleId . 'を<span style="font-weight:bold;">下書き中</span>で登録しました。';
								}
								break;
							#***** 公開中 *****#
							case 'public': {
									$makeTag['msg'] = '記事番号：' . $changeArticleId . 'を<span style="font-weight:bold;">公開中</span>で登録しました。';
								}
								break;
						}
					} else {
						#DBロールバック
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(3);
						$makeTag['status'] = 'error';
						$makeTag['title'] = 'ステータス';
						$makeTag['msg'] = '記事ステータスの更新に失敗しました。';
					}
				}
			} catch (Exception $e) {
				#エラーログ出力
				$data = [
					'pageName' => 'proc_master05_01_01',
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
				'sortTarget' => 'article_id',
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
$totalTipsArticlesCount = searchTipsArticlesCount($searchConditions);
$totalPages = (int)ceil($totalTipsArticlesCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
}
#記事一覧取得（LIMIT/OFFSET）
$tipsArticlesList = searchTipsArticlesList($searchConditions, $pageNumber, $displayNumber);
#該当件数（表示用：総件数）
$tipsArticlesCount = $totalTipsArticlesCount;

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
        <article class="block-search-results" data-current-sort-mode="{$sortModeValue}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$tipsArticlesCount}件</span>が該当</p>
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
              <div>サムネイル</div>
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
if (is_array($tipsArticlesList) && count($tipsArticlesList) > 0) {
	$zIndexNo = count($tipsArticlesList);
	foreach ($tipsArticlesList as $articleKey => $article) {
		#Liのz-index設定
		$zIndexStyle = 'style="z-index:' . ($zIndexNo - $articleKey) . ';"';
		#記事ID
		$articleId = isset($article['article_id']) ? intval($article['article_id']) : 0;
		#記事コード（JS送信用）
		$articleCode = isset($article['code']) ? (string)$article['code'] : '';
		$articleCodeAttr = htmlspecialchars($articleCode, ENT_QUOTES, 'UTF-8');
		#記事タイトル
		$articleTitle = isset($article['title']) ? htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') : '';
		#記事本文プレーンテキスト
		$articleBodyText = isset($article['body_text']) ? htmlspecialchars($article['body_text'], ENT_QUOTES, 'UTF-8') : '';
		#公開ステータス
		$articleStatus = isset($article['status']) ? intval($article['status']) : 0;
		#登録日・更新日
		$articleCreatedAt = isset($article['created_at']) ? htmlspecialchars($article['created_at'], ENT_QUOTES, 'UTF-8') : '';
		$articleUpdatedAt = isset($article['updated_at']) ? htmlspecialchars($article['updated_at'], ENT_QUOTES, 'UTF-8') : '';
		#更新日フォーマット
		$formattedUpdatedAt = '';
		if ($articleUpdatedAt !== '') {
			$formattedUpdatedAt = date('Y/m/d', strtotime($articleUpdatedAt));
		} elseif ($articleCreatedAt !== '') {
			$formattedUpdatedAt = date('Y/m/d', strtotime($articleCreatedAt));
		}
		#サムネイル画像パス取得
		$tipsImagePath = '';
		if (isset($article['tips_image_path']) && $article['tips_image_path'] != null) {
			$tipsImageJsonDecoded = json_decode($article['tips_image_path'], true);
			if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
				$rel = tipsDbRelFromStoredPath($tipsImageJsonDecoded[0]);
				if ($rel !== '') {
					$tipsImagePath = rtrim((string)DOMAIN_NAME, '/') . '/db/' . ltrim($rel, '/');
				}
			}
		}
		$tipsImagePathEsc = htmlspecialchars((string)$tipsImagePath, ENT_QUOTES, 'UTF-8');
		#公開ステータス「name」属性連番対応
		$statusName = 'list_status' . $articleId;
		#checked判定
		$checkedDraft = ($article['status'] == 'draft') ? 'checked' : '';
		$checkedPublic = ($article['status'] == 'public') ? 'checked' : '';
		#value値／label設定
		$valueName = ($article['status'] == 'draft') ? 'draft' : 'public';
		$labelName = ($article['status'] == 'draft') ? '下書き中' : '公開中';
		$makeTag['tag'] .= <<<HTML
            <!-- NOTE  インラインでz-indexを付与 -->
            <li {$zIndexStyle} onclick="location.href='./master05_01_02.php?method=edit&articleId={$articleId}'">
              <div class="item-number">{$articleId}</div>
              <div class="item-image">
                <picture>
                  <source src="{$tipsImagePathEsc}">
                  <img src="{$tipsImagePathEsc}">
                </picture>
              </div>
              <div class="item-details">
                <p class="title">{$articleTitle}</p>
                <p class="contents">
                  {$articleBodyText}
                </p>
              </div>
              <div class="box-status" onclick="event.stopPropagation();">
                <div class="select-status" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">
                    <input type="hidden" name="{$statusName}" value="{$valueName}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$labelName}</span>
                    <i></i>
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">
                      <li>
                        <input type="radio" name="{$statusName}" value="draft" id="list{$articleId}-status01" {$checkedDraft} data-article-code="{$articleCodeAttr}" onchange="checkTipsStatus({$articleId}, this.getAttribute('data-article-code'), this.value);">
                        <label for="list{$articleId}-status01" class="status-draft">下書き中</label>
                      </li>
                      <li>
                        <input type="radio" name="{$statusName}" value="public" id="list{$articleId}-status02" {$checkedPublic} data-article-code="{$articleCodeAttr}" onchange="checkTipsStatus({$articleId}, this.getAttribute('data-article-code'), this.value);">
                        <label for="list{$articleId}-status02" class="status-published">公開中</label>
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
