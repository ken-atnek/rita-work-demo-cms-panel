<?php
/*
 * [rw-master/assets/function/proc_master05_02.php]
 *  - 管理画面 -
 *  運営管理：転職のヒント「トップ表示設定：並び順変更」（AJAX）
 *
 * [初版]
 *  2026.02.09
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
		jsonExit($makeTag);
	}
}
#応答には常に現行のキーを含め、フロント側のhiddenを更新できるようにする
$makeTag['noUpDateKey'] = ($currentNoUpDateKey !== '' ? $currentNoUpDateKey : $noUpDateKey);
#-------------#
#D&D・ボタンクリック等のアクション
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
#-------------#
$method = isset($_POST['method']) ? (string)$_POST['method'] : '';
$articleId = isset($_POST['articleId']) ? (int)$_POST['articleId'] : 0;
$orderJson = isset($_POST['order']) ? (string)$_POST['order'] : '';

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
 * サムネイル画像パスをDB保存形式から相対パスに変換
 * @param string $path ファイルパス
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
 * サムネイル画像パスを管理画面用URLに変換
 * @param string $path ファイルパス
 */
function tipsStoredPathToAdminUrl($path)
{
	$rel = tipsDbRelFromStoredPath($path);
	if ($rel === '') return '';
	$base = rtrim((string)DOMAIN_NAME, '/');
	return $base . '/db/' . ltrim($rel, '/');
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
 * 転職のヒント：一覧/トップJSON（tipsIndex.json / tipsIndexTop.json）を再生成
 */
function writeTipsIndexJson()
{
	$list = getTipsArticlesList();
	$listOut = array('items' => array());
	$topOut = array('items' => array());
	foreach ($list as $row) {
		if (isset($row['status']) && (string)$row['status'] == 'public') {
			$now = time();
			$pubStart = isset($row['published_start']) ? strtotime((string)$row['published_start']) : 0;
			$pubEnd = isset($row['published_end']) && (string)$row['published_end'] !== '' ? strtotime((string)$row['published_end']) : PHP_INT_MAX;
			if ($now < $pubStart || $now > $pubEnd) {
				continue;
			}
			$bodyText = (string)$row['body_text'];
			$bodyText = preg_replace('/[\r\n]+/', ' ', $bodyText);
			$bodyText = trim($bodyText);
			$tipsImagePath = '';
			if (isset($row['tips_image_path']) && $row['tips_image_path'] != null) {
				$tipsImageJsonDecoded = json_decode($row['tips_image_path'], true);
				if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
					$tipsImagePath = tipsStoredPathToFrontUrl($tipsImageJsonDecoded[0]);
				}
			}
			$publishedAt = (isset($row['published_start']) && (string)$row['published_start'] !== '') ? (string)(date('Y-m-d', strtotime($row['published_start']))) : '';
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
	$baseDir = DEFINE_JSON_DIR_PATH . '/tips';
	ensureDir($baseDir);
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
	if (defined('DEFINE_JSON_MIRROR_ENABLE') && DEFINE_JSON_MIRROR_ENABLE) {
		mirrorDbSelectiveMasterByRsync(
			(string)DEFINE_JSON_MIRROR_SRC_DB_DIR,
			(string)DEFINE_JSON_MIRROR_DEST_DB_DIR,
			(string)DEFINE_JSON_MIRROR_MASTER_ONLY_FILE
		);
	}
}
#===============================#
# 転職のヒントトップページ一覧取得
#-------------------------------#
$tipsArticlesListDb = getTipsArticlesTopList();
$dbIds = [];
$dbMap = [];
if (is_array($tipsArticlesListDb) && count($tipsArticlesListDb) > 0) {
	foreach ($tipsArticlesListDb as $row) {
		$id = isset($row['article_id']) ? (int)$row['article_id'] : 0;
		if ($id < 1) continue;
		$dbIds[] = $id;
		$dbMap[$id] = $row;
	}
}
#==============================#
# SESSION: 並び順の初期化/正規化
#------------------------------#
$sessionKeyOrder = 'tips_top_order';
if (!isset($_SESSION[$noUpDateKey]) || !is_array($_SESSION[$noUpDateKey])) {
	$_SESSION[$noUpDateKey] = [];
}
$order = [];
if (isset($_SESSION[$noUpDateKey][$sessionKeyOrder]) && is_array($_SESSION[$noUpDateKey][$sessionKeyOrder])) {
	$order = array_map('intval', $_SESSION[$noUpDateKey][$sessionKeyOrder]);
}
$seen = [];
$normalized = [];
foreach ($order as $id) {
	if ($id < 1) continue;
	if (!in_array($id, $dbIds, true)) continue;
	if (isset($seen[$id])) continue;
	$seen[$id] = true;
	$normalized[] = $id;
}
foreach ($dbIds as $id) {
	if (!isset($seen[$id])) {
		$seen[$id] = true;
		$normalized[] = $id;
	}
}
$order = $normalized;
$_SESSION[$noUpDateKey][$sessionKeyOrder] = $order;
#==========================#
# 並び替え処理（SESSION更新）
#--------------------------#
if ($action === 'move') {
	if ($method === 'top' || $method === 'bottom') {
		$idx = array_search($articleId, $order, true);
		if ($idx !== false) {
			$idx = (int)$idx;
			if ($method === 'top' && $idx > 0) {
				$tmp = $order[$idx - 1];
				$order[$idx - 1] = $order[$idx];
				$order[$idx] = $tmp;
			} elseif ($method === 'bottom' && $idx < (count($order) - 1)) {
				$tmp = $order[$idx + 1];
				$order[$idx + 1] = $order[$idx];
				$order[$idx] = $tmp;
			}
		}
		$_SESSION[$noUpDateKey][$sessionKeyOrder] = $order;
	} elseif ($method === 'setOrder') {
		$decoded = json_decode($orderJson, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			$newOrder = [];
			$newSeen = [];
			foreach ($decoded as $id) {
				$id = (int)$id;
				if ($id < 1) continue;
				if (!in_array($id, $dbIds, true)) continue;
				if (isset($newSeen[$id])) continue;
				$newSeen[$id] = true;
				$newOrder[] = $id;
			}
			foreach ($dbIds as $id) {
				if (!isset($newSeen[$id])) {
					$newSeen[$id] = true;
					$newOrder[] = $id;
				}
			}
			$order = $newOrder;
			$_SESSION[$noUpDateKey][$sessionKeyOrder] = $order;
		}
	}
}
#===============================#
# 保存処理（DB確定 + JSON書き出し）
#-------------------------------#
if ($action === 'save') {
	try {
		if (!is_array($order) || count($order) === 0) {
			throw new RuntimeException('並び順データが存在しません。ページを再読み込みしてください。');
		}
		$result = DB_Transaction(1);
		if ($result == false) {
			throw new RuntimeException('トランザクション開始に失敗しました。');
		}
		$updatedAt = date('Y-m-d H:i:s');
		$caseParts = [];
		$params = [];
		$inParts = [];
		foreach ($order as $i => $id) {
			$id = (int)$id;
			if ($id < 1) continue;
			$caseIdKey = ':case_id' . $i;
			$sortKey = ':sort' . $i;
			$inIdKey = ':in_id' . $i;
			$caseParts[] = "WHEN {$caseIdKey} THEN {$sortKey}";
			$params[$caseIdKey] = $id;
			$params[$sortKey] = $i + 1;
			$params[$inIdKey] = $id;
			$inParts[] = $inIdKey;
		}
		if (count($inParts) === 0) {
			throw new RuntimeException('並び順データが不正です。');
		}
		$strSQL = "UPDATE tips_articles SET top_sort = CASE article_id " . implode(' ', $caseParts) . " END, updated_at = :updated_at WHERE is_top = 1 AND status = 'public' AND article_id IN (" . implode(',', $inParts) . ")";
		$stmt = $GLOBALS['DB_CONNECT']->prepare($strSQL);
		$stmt->bindValue(':updated_at', $updatedAt, PDO::PARAM_STR);
		foreach ($params as $k => $v) {
			$stmt->bindValue($k, (int)$v, PDO::PARAM_INT);
		}
		$stmt->execute();
		$stmt->closeCursor();
		DB_Transaction(2);
		writeTipsIndexJson();
		$makeTag['status'] = 'success';
		$makeTag['title'] = '並び替え完了';
		$makeTag['msg'] = '並び順を保存しました。';
	} catch (Throwable $e) {
		DB_Transaction(3);
		$makeTag['status'] = 'error';
		$makeTag['title'] = '登録エラー';
		$makeTag['msg'] = '並び順の保存に失敗しました。';
		$makeTag['errorMessage'] = $e->getMessage();
	}
	jsonExit($makeTag);
}
#=============================#
# 応答用リスト（SESSION順に整列）
#-----------------------------#
$tipsArticlesList = [];
foreach ($order as $id) {
	if (isset($dbMap[$id]) && is_array($dbMap[$id])) {
		$tipsArticlesList[] = $dbMap[$id];
	}
}
#-------------#
#inline JS用エスケープ
$jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$noUpDateKeyJs = json_encode((string)$noUpDateKey, $jsonHex);
$noUpDateKeyJsAttr = htmlspecialchars((string)$noUpDateKeyJs, ENT_QUOTES, 'UTF-8');

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
          <ul class="list-search-results">
            <li>
              <div class="title-number">表示順</div>
              <div class="title-article">記事</div>
            </li>

HTML;

if (is_array($tipsArticlesList) && count($tipsArticlesList) > 0) {
	$zIndexNo = count($tipsArticlesList);
	foreach ($tipsArticlesList as $articleKey => $article) {
		#Liのz-index設定
		$zIndexStyle = 'style="z-index:' . ($zIndexNo - $articleKey) . ';"';
		#表示順番号
		$listNo = $articleKey + 1;
		#記事ID
		$articleId = isset($article['article_id']) ? intval($article['article_id']) : 0;
		#記事タイトル
		$articleTitle = isset($article['title']) ? htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') : '';
		#記事本文プレーンテキスト
		$articleBodyText = isset($article['body_text']) ? htmlspecialchars($article['body_text'], ENT_QUOTES, 'UTF-8') : '';
		#サムネイル画像パス取得
		$tipsImagePath = '';
		if (isset($article['tips_image_path']) && $article['tips_image_path'] != null) {
			$tipsImageJsonDecoded = json_decode($article['tips_image_path'], true);
			if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
				$tipsImagePath = tipsStoredPathToAdminUrl($tipsImageJsonDecoded[0]);
			}
		}
		$tipsImagePathEsc = htmlspecialchars((string)$tipsImagePath, ENT_QUOTES, 'UTF-8');
		$makeTag['tag'] .= <<<HTML
            <!-- NOTE インラインでz-indexを付与 -->
            <li {$zIndexStyle} data-article-id="{$articleId}" draggable="true">
              <div class="item-number">{$listNo}</div>
              <div class="item-image">
                <picture>
									<source srcset="{$tipsImagePathEsc}">
									<img src="{$tipsImagePathEsc}" alt="サムネイル">
                </picture>
              </div>
              <div class="item-details">
                <p class="title">{$articleTitle}</p>
                <p class="contents">{$articleBodyText}</p>
              </div>
              <div class="item-icon"><a href="./master05_01_02.php?method=edit&articleId={$articleId}" class="btn-edit"></a></div>
              <div class="item-icon">
                <form>
                  <button type="button" class="btn-top" onclick="moveLows('move','top',{$articleId},{$noUpDateKeyJsAttr})"></button>
                </form>
              </div>
              <div class="item-icon">
                <form>
                  <button type="button" class="btn-bottom" onclick="moveLows('move','bottom',{$articleId},{$noUpDateKeyJsAttr})"></button>
                </form>
              </div>
              <div class="item-icon">
                <form>
                  <button type="button" class="btn-move"></button>
                </form>
              </div>
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
jsonExit($makeTag);

#-------------------------------------------#
/**
 * JSONで応答して終了する（AJAX専用）
 *
 * @param array $payload
 * @return never
 */
function jsonExit($payload)
{
	header('Content-Type: application/json; charset=UTF-8');
	echo json_encode($payload);
	exit;
}
#-------------------------------------------#
#===========================================#
