<?php
/*
 * [rw-master/assets/function/proc_master05_03_02.php]
 *  - 管理画面 -
 *  運営管理：事業所へのお知らせ 登録／編集（AJAX）
 *
 * [初版]
 *  2026.02.11
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
#***** TipTapレンダラーファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/assets/lib/TipTap/tiptap_renderer.php';
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
$noUpDateKey = isset($_POST['noUpDateKey']) ? (string)$_POST['noUpDateKey'] : '';
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
#新規／編集／削除
$method = isset($_POST['method']) ? (string)$_POST['method'] : null;
#確認／修正／登録
$action = isset($_POST['action']) ? (string)$_POST['action'] : null;
#お知らせID（編集／削除時のみ）
$notificationId = isset($_POST['notificationId']) ? $_POST['notificationId'] : null;
#画像アップロード先（セッション領域）
if (isset($_POST['up_image_area'])) {
	$imageUploadSessionKey = $_POST['up_image_area'];
	if (!is_array($imageUploadSessionKey)) {
		$imageUploadSessionKey = array($imageUploadSessionKey);
	}
} else {
	$imageUploadSessionKey = array();
}
$targetImageUploadSessionKey = $imageUploadSessionKey[0] ?? '';
#-------------#
#タイトル
$title = isset($_POST['title']) ? (string)$_POST['title'] : '';
#本文
$body_json = isset($_POST['body_json']) ? (string)$_POST['body_json'] : '';
#Topページ表示
$isTop = isset($_POST['is_top']) ? $_POST['is_top'] : 0;

/**
 * 画像MIMEタイプから拡張子を推測
 */
function guessImageExtFromMime($mime)
{
	$mime = strtolower((string)$mime);
	if ($mime === 'image/jpeg' || $mime === 'image/jpg') return 'jpg';
	if ($mime === 'image/png') return 'png';
	if ($mime === 'image/gif') return 'gif';
	return null;
}
/**
 * 一時アップロード用ディレクトリ取得
 */
function getTmpInlineDir($noUpDateKey)
{
	return rtrim(DEFINE_PREVIEW_IMAGE_DIR_PATH, '/\\') . '/notification_inline/' . safeKeySegment($noUpDateKey);
}
/**
 * 安全なキーセグメント生成
 */
function safeKeySegment($s)
{
	$s = (string)$s;
	$s = preg_replace('/[^a-zA-Z0-9_\-]/', '', $s);
	if ($s === '') $s = 'anonymous';
	return $s;
}
/**
 * 再帰ディレクトリ削除
 */
function rrmdir(string $dir): bool
{
	if ($dir === '' || !is_dir($dir)) {
		return true;
	}
	$items = @scandir($dir);
	if ($items === false) {
		return false;
	}
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$path = rtrim($dir, '/\\') . '/' . $item;
		if (is_dir($path)) {
			if (!rrmdir($path)) {
				return false;
			}
		} else {
			@unlink($path);
		}
	}
	return @rmdir($dir);
}
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
 * ファイル確定（rename優先、失敗時はcopy→unlink）
 */
function safeMoveFile(string $src, string $dst): bool
{
	if ($src === '' || $dst === '') {
		return false;
	}
	if (!file_exists($src)) {
		return false;
	}
	$parent = dirname($dst);
	if ($parent !== '' && !is_dir($parent)) {
		if (!ensureDir($parent)) {
			return false;
		}
	}
	if (@rename($src, $dst)) {
		return true;
	}
	if (@copy($src, $dst)) {
		@unlink($src);
		return true;
	}
	return false;
}
/**
 * tmpファイル掃除（存在すれば削除）
 */
function cleanupFiles(array $paths): void
{
	foreach ($paths as $p) {
		if (!is_string($p) || $p === '') {
			continue;
		}
		if (file_exists($p) && is_file($p)) {
			@unlink($p);
		}
	}
}
/**
 * パス/URLから安全なbasenameを抽出する
 * - NULLバイトやディレクトリセパレータは除去する（パストラバーサル対策の補助）
 *
 * @param mixed $path
 * @return string ファイル名のみ（空文字の可能性あり）
 */
function safeBasename($path)
{
	$base = basename((string)$path);
	$base = str_replace(["\0", '/', '\\'], '', $base);
	return $base;
}
/**
 * notification画像パスを「/db」配下の相対パスへ正規化する
 *  - DB保存用: 例) 'notification/notification_0001/image1.jpg'
 *  - 既存互換: '/db/notification/...' や DOMAIN_NAME 付きも許容
 */
function facilityNotificationsDbRelFromStoredPath($path)
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
 * notification画像パスを管理画面表示用URL（DOMAIN_NAME + /db/...）へ変換する
 * - 入力は DB相対（notification/...）/「/db/...」/ドメイン付きURL いずれも許容
 *
 * @param mixed $path
 * @return string 例) 'https://example.com/db/notification/...'（不正/空なら ''）
 */
function notificationStoredPathToAdminUrl($path)
{
	$rel = facilityNotificationsDbRelFromStoredPath($path);
	if ($rel === '') return '';
	$base = defined('DOMAIN_NAME') ? (string)DOMAIN_NAME : '';
	$base = rtrim($base, '/');
	return $base . '/db/' . ltrim($rel, '/');
}
/**
 * notification画像パスをサーバ上の実ファイルパス（DEFINE_JSON_DIR_PATH配下）へ変換する
 * - 入力は DB相対（notification/...）/「/db/...」/ドメイン付きURL いずれも許容
 *
 * @param mixed $path
 * @return string 例) '<...>/db/notification/notification_0001/image1.jpg'（不正/空なら ''）
 */
function facilityNotificationsStoredPathToFsPath($path)
{
	$rel = facilityNotificationsDbRelFromStoredPath($path);
	if ($rel === '') return '';
	return rtrim((string)DEFINE_JSON_DIR_PATH, '/\\') . '/' . ltrim($rel, '/');
}
/**
 * TipTap JSON（旧形式/新形式ラップ両対応）から、当該お知らせディレクトリ配下の画像ファイル名（basename）を収集
 *  - 外部URLや別ディレクトリの画像は対象外
 */
function collectFacilityNotificationsImageBasenamesFromBodyJson($bodyJsonString, $notificationCode)
{
	$bodyJsonString = (string)$bodyJsonString;
	$notificationCode = (string)$notificationCode;
	if ($bodyJsonString === '' || $notificationCode === '') return [];
	$decoded = json_decode($bodyJsonString, true);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return [];
	$editorJson = $decoded;
	if (isset($decoded['editor']) && is_array($decoded['editor'])) {
		$editorJson = $decoded['editor'];
	}
	if (!is_array($editorJson)) return [];
	$keep = [];
	$stack = [$editorJson];
	while (!empty($stack)) {
		$node = array_pop($stack);
		if (!is_array($node)) continue;
		if (($node['type'] ?? null) === 'image') {
			$src = (string)($node['attrs']['src'] ?? '');
			if ($src !== '' && strpos($src, (string)DEFINE_PREVIEW_IMAGE_DIR_PATH) !== 0) {
				$rel = facilityNotificationsDbRelFromStoredPath($src);
				$prefix = 'notification/' . $notificationCode . '/';
				if ($rel !== '' && strpos($rel, $prefix) === 0) {
					$base = basename($rel);
					if (is_string($base) && $base !== '') {
						$keep[$base] = true;
					}
				}
			}
		}
		if (isset($node['content']) && is_array($node['content'])) {
			foreach ($node['content'] as $child) {
				$stack[] = $child;
			}
		}
	}
	return array_keys($keep);
}
/**
 * お知らせディレクトリ（/db/notification/<code>/）内の「参照されていない画像」を削除（ベストエフォート）
 *  - サムネ（notification_image_path）と本文（body_json）双方で参照されている画像を残す
 */
function cleanupOrphanedFacilityNotificationsImages($notificationIdInt, $notificationCode)
{
	$notificationIdInt = (int)$notificationIdInt;
	$notificationCode = (string)$notificationCode;
	if ($notificationIdInt <= 0 || $notificationCode === '') return [];
	$notificationData = getFacilityNotifications_FindById($notificationIdInt);
	if (!$notificationData || !is_array($notificationData)) return [];
	$keepBasenames = [];
	#サムネイル画像パス（notification_image_path）
	$paths = $notificationData['notification_image_path'] ?? [];
	if (is_string($paths) && $paths !== '') {
		$decoded = json_decode($paths, true);
		if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
			$paths = $decoded;
		} else {
			$paths = [];
		}
	}
	if (is_array($paths)) {
		foreach ($paths as $p) {
			if (!is_string($p) || $p === '') continue;
			$rel = facilityNotificationsDbRelFromStoredPath($p);
			$base = basename($rel !== '' ? $rel : $p);
			if (is_string($base) && $base !== '') {
				$keepBasenames[$base] = true;
			}
		}
	}
	#本文（body_json）
	$bodyJson = (string)($notificationData['body_json'] ?? '');
	foreach (collectFacilityNotificationsImageBasenamesFromBodyJson($bodyJson, $notificationCode) as $base) {
		$keepBasenames[(string)$base] = true;
	}
	#$keepBasenames をキー参照するため、配列化は不要（必要になれば array_keys で取得できる）
	$dir = rtrim((string)DEFINE_JSON_DIR_PATH, '/\\') . '/notification/' . $notificationCode;
	if (!is_dir($dir)) return [];
	$deleted = [];
	$items = @scandir($dir);
	if (!is_array($items)) return [];
	foreach ($items as $name) {
		if (!is_string($name) || $name === '' || $name === '.' || $name === '..') continue;
		$ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) continue;
		if (isset($keepBasenames[$name])) continue;
		$full = $dir . '/' . $name;
		if (is_file($full)) {
			if (@unlink($full)) {
				$deleted[] = $full;
			}
		}
	}
	return $deleted;
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
 * TipTap JSON内の image.attrs.src を、マップに従って再帰的に置換する
 * - 破壊的変更（$node を参照渡しで更新）
 *
 * @param array $node TipTap JSON（node）参照
 * @param array $map 置換マップ [fromSrc => toSrc]
 * @return void
 */
function updateEditorJsonImageSrcs(&$node, $map)
{
	if (!is_array($node)) return;
	if (isset($node['type']) && $node['type'] === 'image') {
		if (isset($node['attrs']) && is_array($node['attrs']) && isset($node['attrs']['src'])) {
			$src = (string)$node['attrs']['src'];
			if (isset($map[$src])) {
				$node['attrs']['src'] = (string)$map[$src];
			}
		}
	}
	if (isset($node['content']) && is_array($node['content'])) {
		foreach ($node['content'] as $i => $child) {
			updateEditorJsonImageSrcs($node['content'][$i], $map);
		}
	}
}
/**
 * 本文内のインライン画像（tmp_upload/notification_inline/<noUpDateKey>/...）を本番ディレクトリへ確定し、本文を置換する
 *
 * - TipTap JSON（旧形式/新形式ラップ両対応）の image.attrs.src を、確定後のDB保存用相対パス（notification/<code>/...）に置換
 * - HTML文字列中のURLも同様に置換（ベストエフォート）
 * - ファイル操作（rename/copy/unlink）を行うため副作用あり
 *
 * @param mixed  $notificationId   お知らせID（現状未使用だが将来拡張用）
 * @param string $notificationCode お知らせコード（notification_0001 等）
 * @param string $noUpDateKey 画面インスタンス識別キー（tmp_inline のディレクトリ名）
 * @param string $bodyJsonString DB保存用 body_json（JSON文字列）
 * @param string $bodyHtmlString DB保存用 body_text（HTML文字列）
 * @return array{0:string,1:string} [置換後body_json, 置換後body_text]
 */
function finalizeInlineImagesAndRewriteBody($notificationId, $notificationCode, $noUpDateKey, $bodyJsonString, $bodyHtmlString)
{
	$bodyJsonString = (string)$bodyJsonString;
	$bodyHtmlString = (string)$bodyHtmlString;
	#jsonデコード
	$decoded = json_decode($bodyJsonString, true);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
		return [$bodyJsonString, $bodyHtmlString];
	}
	#editorフィールドの有無でラップモード判定
	$editorJson = null;
	$wrapperMode = false;
	if (isset($decoded['editor']) && is_array($decoded['editor'])) {
		$editorJson = $decoded['editor'];
		$wrapperMode = true;
	} else {
		$editorJson = $decoded;
	}
	if (!is_array($editorJson)) {
		return [$bodyJsonString, $bodyHtmlString];
	}
	#inline画像処理
	$tmpPrefix = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/notification_inline/' . safeKeySegment($noUpDateKey) . '/';
	$moveMap = [];
	$srcList = [];
	#画像src収集
	$stack = [$editorJson];
	while (!empty($stack)) {
		$n = array_pop($stack);
		if (!is_array($n)) continue;
		if (isset($n['type']) && $n['type'] === 'image') {
			$src = isset($n['attrs']['src']) ? (string)$n['attrs']['src'] : '';
			if ($src !== '' && strpos($src, $tmpPrefix) === 0) {
				$srcList[] = $src;
			}
		}
		if (isset($n['content']) && is_array($n['content'])) {
			foreach ($n['content'] as $child) {
				$stack[] = $child;
			}
		}
	}
	$srcList = array_values(array_unique($srcList));
	if (empty($srcList)) {
		return [$bodyJsonString, $bodyHtmlString];
	}
	#画像移動＆URL置換マップ作成
	$notificationDir = DEFINE_JSON_DIR_PATH . '/notification/' . $notificationCode;
	ensureDir($notificationDir);
	$counter = 1;
	foreach ($srcList as $src) {
		$path = parse_url($src, PHP_URL_PATH);
		$baseName = safeBasename($path ?: $src);
		if ($baseName === '') continue;
		$tmpFull = rtrim(getTmpInlineDir($noUpDateKey), '/\\') . '/' . $baseName;
		if (!is_file($tmpFull)) continue;
		$ext = strtolower((string)pathinfo($baseName, PATHINFO_EXTENSION));
		if ($ext === '') $ext = 'jpg';
		$newName = '';
		for ($guard = 0; $guard < 200; $guard++) {
			$candidate = 'image' . $counter . '.' . $ext;
			$destFull = $notificationDir . '/' . $candidate;
			if (!file_exists($destFull)) {
				$newName = $candidate;
				break;
			}
			$counter++;
		}
		if ($newName === '') continue;
		$destFull = $notificationDir . '/' . $newName;
		#rename が失敗したら copy+unlink
		if (@rename($tmpFull, $destFull) === false) {
			if (@copy($tmpFull, $destFull) === false) {
				continue;
			}
			@unlink($tmpFull);
		}
		#移動後のURLを置換マップに登録
		$finalUrl = 'notification/' . $notificationCode . '/' . $newName;
		$moveMap[$src] = $finalUrl;
		$counter++;
	}
	#置換マップが空なら終了
	if (empty($moveMap)) {
		return [$bodyJsonString, $bodyHtmlString];
	}
	#JSON置換
	updateEditorJsonImageSrcs($editorJson, $moveMap);
	if ($wrapperMode) {
		$decoded['editor'] = $editorJson;
	} else {
		$decoded = $editorJson;
	}
	$bodyJsonString = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($bodyJsonString === false) {
		#失敗時は元に戻す
		$bodyJsonString = (string)($_POST['body_json'] ?? '');
	}
	#HTML置換
	foreach ($moveMap as $from => $to) {
		$bodyHtmlString = str_replace($from, $to, $bodyHtmlString);
	}
	#tmpディレクトリが空なら削除（ベストエフォート）
	$tmpDir = getTmpInlineDir($noUpDateKey);
	if (is_dir($tmpDir)) {
		$files = @scandir($tmpDir);
		if (is_array($files)) {
			$left = array_values(array_filter($files, function ($x) {
				return $x !== '.' && $x !== '..';
			}));
			if (count($left) === 0) {
				@rmdir($tmpDir);
			}
		}
	}
	#完了
	return [$bodyJsonString, $bodyHtmlString];
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

#***** タグ生成開始 *****#
switch ($action) {
	#***** 本文画像仮登録（ドラッグ＆ドロップ/ファイル選択アップロード） *****#
	case 'uploadInlineImage': {
			if (!isset($_FILES['file'])) {
				throw new RuntimeException('画像ファイルが見つかりません');
			}
			$f = $_FILES['file'];
			if (!is_array($f) || !isset($f['tmp_name']) || $f['tmp_name'] === '') {
				throw new RuntimeException('画像ファイルが不正です');
			}
			if (!is_uploaded_file($f['tmp_name'])) {
				throw new RuntimeException('アップロードに失敗しました');
			}
			$maxBytes = 5 * 1024 * 1024;
			$size = isset($f['size']) ? (int)$f['size'] : 0;
			if ($size <= 0 || $size > $maxBytes) {
				throw new RuntimeException('ファイルサイズが大きすぎます（最大5MB）');
			}
			$ext = guessImageExtFromMime($f['type'] ?? '');
			if ($ext === null) {
				throw new RuntimeException('対応していない画像形式です');
			}
			$dir = getTmpInlineDir($noUpDateKey);
			ensureDir($dir);
			$name = 'inline_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
			$dest = rtrim($dir, '/\\') . '/' . $name;
			if (@move_uploaded_file($f['tmp_name'], $dest) === false) {
				throw new RuntimeException('画像保存に失敗しました');
			}
			$makeTag['status'] = 'success';
			$makeTag['url'] = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/notification_inline/' . safeKeySegment($noUpDateKey) . '/' . $name;
			jsonExit($makeTag);
		}
		break;
	#***** ページ離脱・リロード時：アップロードドラフト破棄（tmp_upload + session） *****#
	case 'discardUploadDraft': {
			#up_image_area[] で指定されたセッション領域のみ破棄
			if (!isset($imageUploadSessionKey) || !is_array($imageUploadSessionKey)) {
				$imageUploadSessionKey = [];
			}
			$pathsToDelete = [];
			foreach ($imageUploadSessionKey as $sKey) {
				if (!is_string($sKey) || $sKey === '') {
					continue;
				}
				if (isset($_SESSION[$sKey]) && is_array($_SESSION[$sKey])) {
					foreach ($_SESSION[$sKey] as $row) {
						if (!is_array($row)) {
							continue;
						}
						$tmp = $row['tmp_name'] ?? '';
						if (is_string($tmp) && $tmp !== '') {
							$pathsToDelete[] = $tmp;
						}
					}
				}
				unset($_SESSION[$sKey]);
			}
			cleanupFiles($pathsToDelete);
			$makeTag['status'] = 'success';
			$makeTag['title'] = 'discarded';
			$makeTag['msg'] = '';
			header('Content-Type: application/json');
			echo json_encode($makeTag);
			exit;
		}
		break;
	#***** 画像プレビューチェック（ドラッグ＆ドロップ/ファイル選択アップロード） *****#
	case 'preUploadImage': {
			#エリア名が未指定の場合はエラー応答
			if (empty($targetImageUploadSessionKey)) {
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'アップロード失敗';
				$makeTag['msg'] = 'アップロードエリア名が指定されていません。';
				echo json_encode($makeTag);
				exit;
			}
			$makeTag['file_url'] = '';
			$makeTag['file_name'] = '';
			$upImageMode = isset($_POST['up_image_mode']) ? (string)$_POST['up_image_mode'] : '';
			if (isset($_FILES['images_tmp']) && is_uploaded_file($_FILES['images_tmp']['tmp_name'])) {
				$file = $_FILES['images_tmp'];
				$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
				$allowed = ['jpg', 'jpeg', 'png', 'gif'];
				if (!in_array($ext, $allowed)) {
					$makeTag['status'] = 'error';
					$makeTag['msg'] = '許可されていないファイル形式です。';
				} else {
					#一時保存先
					$tmpDir = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/';
					if (!file_exists($tmpDir)) mkdir($tmpDir, 0777, true);
					$uniqueName = 'notification_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
					$savePath = $tmpDir . $uniqueName;
					$previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $uniqueName;
					if (move_uploaded_file($file['tmp_name'], $savePath)) {
						#セッションにファイル情報を保存
						# - onlyモードは「1枠」のため、成功時に既存を掃除して置換する
						if (!isset($_SESSION[$targetImageUploadSessionKey]) || !is_array($_SESSION[$targetImageUploadSessionKey])) {
							$_SESSION[$targetImageUploadSessionKey] = [];
						}
						if ($upImageMode === 'only') {
							foreach ($_SESSION[$targetImageUploadSessionKey] as $old) {
								if (!is_array($old)) {
									continue;
								}
								$oldTmp = $old['tmp_name'] ?? '';
								if (is_string($oldTmp) && $oldTmp !== '' && file_exists($oldTmp) && is_file($oldTmp)) {
									@unlink($oldTmp);
								}
							}
							$_SESSION[$targetImageUploadSessionKey] = [];
						}
						$_SESSION[$targetImageUploadSessionKey][] = [
							'tmp_name' => $savePath,
							'preview' => $previewPath,
							'name' => $uniqueName,
							'original' => $file['name'],
							'type' => $file['type'],
							'size' => $file['size'],
							'uploaded_at' => time(),
						];
						$makeTag['status'] = 'success';
						$makeTag['file_url'] = '/tmp_upload/' . $uniqueName;
						$makeTag['file_name'] = $uniqueName;
						#sourceタグ用MIMEタイプ設定
						switch ($ext) {
							case 'jpg':
							case 'jpeg':
								$mimeType = 'image/jpeg';
								break;
							case 'png':
								$mimeType = 'image/png';
								break;
							case 'gif':
								$mimeType = 'image/gif';
								break;
							default:
								$mimeType = '';
								break;
						}
						#プレビュー用タグ生成
						$makeTag['tag'] .= <<<HTML
                <li>
                  <div class="warp-btn">
                    <button type="button" class="btn-change"></button>
                    <button type="button" class="btn-delate"></button>
                  </div>
                  <picture>
                    <source src="{$previewPath}" type="{$mimeType}">
                    <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                  </picture>
                </li>

HTML;
					} else {
						$makeTag['status'] = 'error';
						$makeTag['title'] = 'アップロード失敗';
						$makeTag['msg'] = 'ファイルの保存に失敗しました。';
					}
				}
			} else {
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'アップロード失敗';
				$makeTag['msg'] = 'ファイルがアップロードされていません。';
			}
			header('Content-Type: application/json');
			echo json_encode($makeTag);
			exit;
		}
		break;
	#***** 画像入れ替え（プレビューからの入れ替え） *****#
	case 'replaceUploadImage': {
			if (empty($targetImageUploadSessionKey)) {
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'アップロード失敗';
				$makeTag['msg'] = 'アップロードエリア名が指定されていません。';
				header('Content-Type: application/json');
				echo json_encode($makeTag);
				exit;
			}
			$replaceIndex = isset($_POST['replace_index']) ? intval($_POST['replace_index']) : null;
			$makeTag['file_url'] = '';
			$makeTag['file_name'] = '';
			$file = isset($_FILES['images_tmp']) ? $_FILES['images_tmp'] : null;
			$ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
			$allowed = ['jpg', 'jpeg', 'png', 'gif'];
			if ($replaceIndex === null || !$file || !is_uploaded_file($file['tmp_name']) || !in_array($ext, $allowed, true)) {
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'アップロード失敗';
				$makeTag['msg'] = '入れ替え画像が指定されていません。';
				header('Content-Type: application/json');
				echo json_encode($makeTag);
				exit;
			}
			$tmpDir = __DIR__ . '/../../../tmp_upload/';
			if (!file_exists($tmpDir)) {
				@mkdir($tmpDir, 0777, true);
			}
			$uniqueName = 'notification_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
			$savePath = $tmpDir . $uniqueName;
			$previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $uniqueName;
			if (!move_uploaded_file($file['tmp_name'], $savePath)) {
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'アップロード失敗';
				$makeTag['msg'] = 'ファイルの保存に失敗しました。';
				header('Content-Type: application/json');
				echo json_encode($makeTag);
				exit;
			}
			#DB登録済み画像の入れ替えでも扱えるよう、必要ならセッションをDBから展開する
			if (!isset($_SESSION[$targetImageUploadSessionKey]) || !is_array($_SESSION[$targetImageUploadSessionKey])) {
				$_SESSION[$targetImageUploadSessionKey] = [];
			}
			if (!array_key_exists($replaceIndex, $_SESSION[$targetImageUploadSessionKey])) {
				$imageList = [];
				if ($notificationId !== null && $notificationId !== '') {
					$notificationData = getFacilityNotifications_FindById($notificationId);
					$imageList = $notificationData['notification_image_path'] ?? [];
					if (is_string($imageList) && $imageList !== '') {
						$decodedPaths = json_decode($imageList, true);
						if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPaths)) {
							$imageList = $decodedPaths;
						}
					}
					if (!is_array($imageList)) {
						$imageList = [];
					}
				}
				$_SESSION[$targetImageUploadSessionKey] = [];
				foreach ($imageList as $p) {
					if (!is_string($p) || $p === '') {
						$_SESSION[$targetImageUploadSessionKey][] = [];
						continue;
					}
					$rel = facilityNotificationsDbRelFromStoredPath($p);
					$_SESSION[$targetImageUploadSessionKey][] = [
						'tmp_name' => '',
						'preview' => notificationStoredPathToAdminUrl($p),
						'name' => basename($rel !== '' ? $rel : $p),
						'path' => $rel !== '' ? $rel : $p,
						'is_db' => true,
					];
				}
			}
			#既存tmpがあれば削除
			$old = $_SESSION[$targetImageUploadSessionKey][$replaceIndex] ?? null;
			if (is_array($old)) {
				$oldTmp = $old['tmp_name'] ?? '';
				if (is_string($oldTmp) && $oldTmp !== '' && file_exists($oldTmp) && is_file($oldTmp)) {
					@unlink($oldTmp);
				}
			}
			$replaceFrom = (is_array($old) && isset($old['path']) && is_string($old['path'])) ? $old['path'] : '';
			$newRow = [
				'tmp_name' => $savePath,
				'preview' => $previewPath,
				'name' => $uniqueName,
				'original' => $file['name'],
				'type' => $file['type'],
				'size' => $file['size'],
				'uploaded_at' => time(),
			];
			if (is_string($replaceFrom) && $replaceFrom !== '') {
				$newRow['replace_from_path'] = $replaceFrom;
			}
			$_SESSION[$targetImageUploadSessionKey][$replaceIndex] = $newRow;
			#応答
			$makeTag['status'] = 'success';
			$makeTag['file_url'] = '/tmp_upload/' . $uniqueName;
			$makeTag['file_name'] = $uniqueName;
			#プレビュー用タグ生成（セッションの並びをそのまま反映）
			$makeTag['tag'] = '';
			foreach ($_SESSION[$targetImageUploadSessionKey] as $row) {
				if (!is_array($row)) {
					continue;
				}
				$src = '';
				if (isset($row['tmp_name']) && is_string($row['tmp_name']) && $row['tmp_name'] !== '') {
					$src = $row['preview'] ?? '';
				} elseif (isset($row['preview']) && is_string($row['preview']) && $row['preview'] !== '') {
					$src = $row['preview'];
				} elseif (isset($row['path']) && is_string($row['path']) && $row['path'] !== '') {
					$src = notificationStoredPathToAdminUrl($row['path']);
				}
				if (!is_string($src) || $src === '') {
					continue;
				}
				$srcExt = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
				switch ($srcExt) {
					case 'jpg':
					case 'jpeg':
						$mimeType = 'image/jpeg';
						break;
					case 'png':
						$mimeType = 'image/png';
						break;
					case 'gif':
						$mimeType = 'image/gif';
						break;
					default:
						$mimeType = '';
						break;
				}
				$makeTag['tag'] .= <<<HTML
                <li>
                  <div class="warp-btn">
                    <button type="button" class="btn-change"></button>
                    <button type="button" class="btn-delate"></button>
                  </div>
                  <picture>
                    <source src="{$src}" type="{$mimeType}">
                    <img src="{$src}" alt="ロゴ画像プレビュー">
                  </picture>
                </li>

HTML;
			}
			#応答
			header('Content-Type: application/json');
			echo json_encode($makeTag);
			exit;
		}
		break;
	#***** 画像削除（プレビュー or 本体からの削除） *****#
	case 'deleteUploadImage': {
			#エリア名が未指定の場合はエラー応答
			if (empty($targetImageUploadSessionKey)) {
				$makeTag['status'] = 'error';
				$makeTag['title'] = '削除失敗';
				$makeTag['msg'] = 'アップロードエリア名が指定されていません。';
				header('Content-Type: application/json');
				echo json_encode($makeTag);
				exit;
			}
			$fileName = isset($_POST['file_name']) ? $_POST['file_name'] : '';
			if (!is_string($fileName) || $fileName === '') {
				$makeTag['status'] = 'error';
				$makeTag['title'] = '削除失敗';
				$makeTag['msg'] = '削除対象ファイルが指定されていません。';
				header('Content-Type: application/json');
				echo json_encode($makeTag);
				exit;
			}
			#セッションがある場合：tmp/擬似DBリストから削除
			if (isset($_SESSION[$targetImageUploadSessionKey]) && is_array($_SESSION[$targetImageUploadSessionKey])) {
				$wasMaterialized = false;
				foreach ($_SESSION[$targetImageUploadSessionKey] as $row) {
					if (!is_array($row)) continue;
					if ((isset($row['is_db']) && $row['is_db'] === true) || (isset($row['path']) && is_string($row['path']) && $row['path'] !== '')) {
						$wasMaterialized = true;
						break;
					}
				}
				$deleted = false;
				foreach ($_SESSION[$targetImageUploadSessionKey] as $idx => $info) {
					if (($info['name'] ?? '') === $fileName) {
						if (isset($info['tmp_name']) && is_string($info['tmp_name']) && $info['tmp_name'] !== '' && file_exists($info['tmp_name'])) {
							@unlink($info['tmp_name']);
						}
						array_splice($_SESSION[$targetImageUploadSessionKey], $idx, 1);
						$deleted = true;
						break;
					}
				}
				#削除応答
				if ($deleted) {
					$makeTag['status'] = 'success';
				} else {
					$makeTag['status'] = 'error';
					$makeTag['title'] = '削除失敗';
					$makeTag['msg'] = '削除対象が見つかりませんでした。';
				}
				#空になった場合：DB由来のmaterializedなら空を保持（保存時に全削除を反映）
				if (empty($_SESSION[$targetImageUploadSessionKey])) {
					if ($wasMaterialized) {
						$_SESSION[$targetImageUploadSessionKey] = [
							['is_db' => true],
						];
					} else {
						unset($_SESSION[$targetImageUploadSessionKey]);
					}
				}
				#応答
				header('Content-Type: application/json');
				echo json_encode($makeTag);
				exit;
			}
			#セッションが無い場合：DBの現状リストをセッションへ展開し、そこから除去（DB/本番は保存で確定）
			$notificationData = getFacilityNotifications_FindById($notificationId);
			$imageList = $notificationData['notification_image_path'] ?? [];
			if (is_string($imageList) && $imageList !== '') {
				$decodedPaths = json_decode($imageList, true);
				if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPaths)) {
					$imageList = $decodedPaths;
				}
			}
			if (!is_array($imageList)) {
				$imageList = [];
			}
			$_SESSION[$targetImageUploadSessionKey] = [];
			foreach ($imageList as $p) {
				if (!is_string($p) || $p === '') {
					continue;
				}
				$rel = facilityNotificationsDbRelFromStoredPath($p);
				$_SESSION[$targetImageUploadSessionKey][] = [
					'tmp_name' => '',
					'preview' => notificationStoredPathToAdminUrl($p),
					'name' => basename($rel !== '' ? $rel : $p),
					'path' => $rel !== '' ? $rel : $p,
					'is_db' => true,
				];
			}
			$deleted = false;
			foreach ($_SESSION[$targetImageUploadSessionKey] as $idx => $info) {
				if (($info['name'] ?? '') === $fileName) {
					array_splice($_SESSION[$targetImageUploadSessionKey], $idx, 1);
					$deleted = true;
					break;
				}
			}
			#削除応答
			if ($deleted) {
				$makeTag['status'] = 'success';
			} else {
				$makeTag['status'] = 'error';
				$makeTag['title'] = '削除失敗';
				$makeTag['msg'] = '削除対象が見つかりませんでした。';
			}
			#空になった場合：DB由来のmaterializedなら空を保持（保存時に全削除を反映）
			if (empty($_SESSION[$targetImageUploadSessionKey])) {
				$_SESSION[$targetImageUploadSessionKey] = [
					['is_db' => true],
				];
			}
			#応答
			header('Content-Type: application/json');
			echo json_encode($makeTag);
			exit;
		}
		break;
	#***** 入力チェック *****#
	case 'checkInput': {
			$titleEsc = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
			$isTopChecked = (int)($isTop ?? 0) === 1 ? ' checked' : '';
			#本文jsonデコード
			$decoded = json_decode($body_json, true);
			#本文json→html変換
			$body_html = tt_render_article($decoded);
			$editorJson = $decoded;
			$period = null;
			if (is_array($decoded) && isset($decoded['editor'])) {
				$editorJson = $decoded['editor'];
				if (isset($decoded['period']) && is_array($decoded['period'])) {
					$period = $decoded['period'];
				}
			}
			#表示期間テキスト生成
			$periodText = '';
			if (is_array($period)) {
				$type = isset($period['type']) ? (string)$period['type'] : 'none';
				$from = isset($period['from']) ? (string)$period['from'] : '';
				$to = isset($period['to']) ? (string)$period['to'] : '';
				$fromEsc = htmlspecialchars($from, ENT_QUOTES, 'UTF-8');
				$toEsc = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
				if ($type === 'from') {
					$periodText = $from !== '' ? '開始日：' . $fromEsc : '開始日：未設定';
				} elseif ($type === 'registered_to') {
					$periodText = $to !== '' ? '終了日：' . $toEsc : '終了日：未設定';
				} elseif ($type === 'from_to') {
					$periodText = '開始日：' . ($from !== '' ? $fromEsc : '未設定') . ' ／ 終了日：' . ($to !== '' ? $toEsc : '未設定');
				} else {
					#type=none は「機能無効化」扱い（表示しない）
					$periodText = '';
				}
			}
			$periodTextEsc = htmlspecialchars($periodText, ENT_QUOTES, 'UTF-8');
			$periodRow = '';
			if ($periodText !== '') {
				$periodRow = '<p>表示期間：' . $periodTextEsc . '</p>';
			}
			#サムネイル画像
			$previewImageTag = '';
			if (isset($_SESSION[$targetImageUploadSessionKey]) && is_array($_SESSION[$targetImageUploadSessionKey])) {
				$imgSessions = $_SESSION[$targetImageUploadSessionKey];
				foreach ($imgSessions as $imgSession) {
					$previewPath = $imgSession['preview'] ?? '';
					if ($previewPath !== '') {
						$previewImageTag .= '<img src="' . htmlspecialchars($previewPath, ENT_QUOTES, 'UTF-8') . '" alt="サムネイル画像プレビュー">';
					}
				}
			} else {
				#既に登録されている画像がある場合はそれを表示
				if ($method === 'edit' && isset($notificationId)) {
					$notificationData = getFacilityNotifications_FindById($notificationId);
					$previewImageTag = '';
					if (isset($notificationData['notification_image_path']) && $notificationData['notification_image_path'] != null) {
						$notificationImageJsonDecoded = json_decode($notificationData['notification_image_path'], true);
						$frontUrl = notificationStoredPathToAdminUrl($notificationImageJsonDecoded[0] ?? '');
						$previewImageTag = '<img src="' . htmlspecialchars($frontUrl, ENT_QUOTES, 'UTF-8') . '" alt="サムネイル画像プレビュー">';
					}
				}
			}
			#$confirmDate = date('Y.m.d');
			#下書き登録中：公開日未設定の為「yyyy-mm-dd」固定表示
			$confirmDate = 'yyyy-mm-dd';
			$makeTag['tag'] .= <<<HTML
      <section class="container-confirm">
        <a href="./master05_03_01.php" class="link-page-back">戻る</a>
        <h2>事業所へのお知らせ<span>入力内容確認</span></h2>
        <div class="block-confirm">
          <div class="confirm-meta">
            <div class="item-switch">
            </div>
            {$periodRow}
            <div class="status-draft">下書き中</div>
          </div>
          <h3 class="title-confirm">{$titleEsc}</h3>
          <div class="box-time">{$confirmDate}</div>
          <div class="box-image">{$previewImageTag}</div>
          <div class="box-body">{$body_html}</div>
        </div>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="fixInput()">戻る</button>
          <button type="button" class="item-check" onclick="sendInput()">登録する</button>
        </div>
      </section>

HTML;
			$makeTag['status'] = 'success';
			jsonExit($makeTag);
		}
		break;
	#***** 登録 *****#
	case 'sendInput': {
			try {
				#トランザクション開始
				# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
				$result = DB_Transaction(1);
				if ($result == false) {
					#エラーログ出力
					$data = [
						'pageName' => 'proc_master05_03_02',
						'reason' => 'トランザクション開始失敗',
					];
					makeLog($data);
					$makeTag['status'] = 'error';
					$makeTag['title'] = '登録エラー';
					$makeTag['msg'] = 'トランザクション開始に失敗しました。';
					jsonExit($makeTag);
				} else {
					#DB登録結果フラグ：初期化
					$dbCompleteFlg = true;
					#POST整形
					$notificationIdInt = (int)($notificationId ?? 0);
					$isTopInt = (int)$isTop;
					#本文（wrapper/editor 対応）
					$decodedBody = json_decode($body_json, true);
					$editorJson = $decodedBody;
					$wrapperMode = false;
					if (is_array($decodedBody) && isset($decodedBody['editor']) && is_array($decodedBody['editor'])) {
						$editorJson = $decodedBody['editor'];
						$wrapperMode = true;
					}
					#DB保存用の body_json は、管理画面向けURL（DOMAIN_NAME付き等）を /db 相対へ正規化して保存する
					$bodyJsonForDb = (string)$body_json;
					if (is_array($editorJson)) {
						$normalizedEditor = $editorJson;
						rewriteEditorJsonImageSrcsByCallback($normalizedEditor, function ($src) use ($noUpDateKey) {
							$src = (string)$src;
							if ($src === '') return $src;
							#tmp_inline は finalizeInlineImagesAndRewriteBody で確定させるので温存
							$tmpPrefix = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/notification_inline/' . safeKeySegment($noUpDateKey) . '/';
							if (strpos($src, $tmpPrefix) === 0) return $src;
							#http(s) でも自サイトの /db/notification/... はDB相対へ正規化する（外部URLは温存）
							if (preg_match('/^https?:\/\//i', $src)) {
								$rel = facilityNotificationsDbRelFromStoredPath($src);
								if ($rel !== '' && strpos($rel, 'notification/') === 0) {
									return $rel;
								}
								return $src;
							}
							$rel = facilityNotificationsDbRelFromStoredPath($src);
							return $rel !== '' ? $rel : $src;
						});
						if ($wrapperMode && is_array($decodedBody)) {
							$decodedBody['editor'] = $normalizedEditor;
							$tmp = json_encode($decodedBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
							if (is_string($tmp) && $tmp !== '' && $tmp !== 'null') {
								$bodyJsonForDb = $tmp;
							}
						} else {
							$tmp = json_encode($normalizedEditor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
							if (is_string($tmp) && $tmp !== '' && $tmp !== 'null') {
								$bodyJsonForDb = $tmp;
							}
						}
					}
					$bodyHtml = '';
					$bodyPlainText = '';
					if (is_array($editorJson)) {
						$bodyHtml = tt_render_article($editorJson);
						$bodyPlainText = tt_render_article_plaintext($editorJson);
					}
					#------------------------------------
					# ファイル操作は「コミット後」に確定する
					#  - DB失敗/ロールバック時は tmp を掃除
					#------------------------------------
					$pendingMkdirDirs = [];
					$pendingMoves = [];
					$pendingDeleteDirs = [];
					$pendingDeleteFiles = [];
					$pendingTmpFiles = [];
					$pendingClearSessions = [];
					#サムネ（notification_image_path）DB保存用の値
					$notificationImagePaths = [];
					#編集時の既存画像パス
					$existingImagePaths = [];
					if ($method === 'edit' && $notificationIdInt > 0) {
						$notificationData = getFacilityNotifications_FindById($notificationIdInt);
						$existingImagePaths = $notificationData['notification_image_path'] ?? [];
						if (is_string($existingImagePaths) && $existingImagePaths !== '') {
							$decodedPaths = json_decode($existingImagePaths, true);
							if (json_last_error() === JSON_ERROR_NONE && is_array($decodedPaths)) {
								$existingImagePaths = $decodedPaths;
							} else {
								$existingImagePaths = [];
							}
						}
						if (!is_array($existingImagePaths)) {
							$existingImagePaths = [];
						}
						$existingImagePaths = array_values(array_filter(array_map('facilityNotificationsDbRelFromStoredPath', $existingImagePaths), function ($x) {
							return is_string($x) && $x !== '';
						}));
					}
					#DB登録情報準備
					switch ($method) {
						#***** 新規登録 *****#
						case 'new': {
								$getNotificationId = getLastNotificationId();
								if (!is_array($getNotificationId) || !isset($getNotificationId['AUTO_INCREMENT'])) {
									#例外処理へ
									throw new Exception('最新記事ID取得失敗');
								} else {
									#最新記事ID取得成功
									$newNotificationCode = 'notification' . sprintf("%04d", $getNotificationId['AUTO_INCREMENT']);
								}
								#登録用配列：初期化
								$dbFiledData = array();
								#登録情報セット
								$dbFiledData['code'] = array(':code', $newNotificationCode, 0);
								$dbFiledData['status'] = array(':status', 'draft', 0);
								$dbFiledData['title'] = array(':title', $title, 0);
								$dbFiledData['body_json'] = array(':body_json', $bodyJsonForDb, 0);
								$dbFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
								#更新用キー：初期化
								$dbFiledValue = array();
								#処理モード：[1].新規追加｜[2].更新｜[3].削除
								$processFlg = 1;
								#実行モード：[1].トランザクション｜[2].即実行
								$exeFlg = 2;
								#DB更新
								$dbSuccessFlg = SQL_Process($DB_CONNECT, "facility_notifications", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
								#追加した記事IDを取得
								$newNotificationId = $DB_CONNECT->lastInsertId();
								$notificationIdInt = (int)$newNotificationId;
								#記事登録完了後に画像系処理を実行する
								if ($dbSuccessFlg == 1) {
									#JSON/画像ディレクトリ作成はコミット後に実施
									$notificationJsonDir = DEFINE_JSON_DIR_PATH . '/notification/' . $newNotificationCode . '/';
									$pendingMkdirDirs[] = $notificationJsonDir;
									#--- サムネイル画像の確定予約（コミット後に移動） ---#
									$bannerImageDir = DEFINE_JSON_DIR_PATH . '/notification/' . $newNotificationCode . '/';
									$pendingMkdirDirs[] = $bannerImageDir;
									$imagePathArr = [];
									foreach ($imageUploadSessionKey as $area) {
										if (!isset($_SESSION[$area]) || !is_array($_SESSION[$area])) {
											continue;
										}
										foreach ($_SESSION[$area] as $imgIndex => $img) {
											if (!is_array($img)) continue;
											$src = $img['tmp_name'] ?? '';
											$name = safeBasename($img['name'] ?? '');
											if (!is_string($src) || $src === '' || $name === '') {
												continue;
											}
											$dst = rtrim($bannerImageDir, '/\\') . '/' . $name;
											$pendingMoves[] = ['src' => $src, 'dst' => $dst];
											$pendingTmpFiles[] = $src;
											$newPublicPath = 'notification/' . $newNotificationCode . '/' . $name;
											if (is_int($imgIndex) && $imgIndex >= 0 && $imgIndex < count($imagePathArr)) {
												$imagePathArr[$imgIndex] = $newPublicPath;
											} else {
												$imagePathArr[] = $newPublicPath;
											}
										}
										$pendingClearSessions[] = $area;
									}
									$notificationImagePaths = array_values(array_filter(array_map('facilityNotificationsDbRelFromStoredPath', $imagePathArr), function ($x) {
										return is_string($x) && $x !== '';
									}));
									#notification_image_path を更新（DB）
									$dbFiledDataImg = array(
										'notification_image_path' => array(':notification_image_path', json_encode($notificationImagePaths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0),
										'updated_at' => array(':updated_at', date('Y-m-d H:i:s'), 0),
									);
									$dbWhereImg = array('notification_id' => array(':notification_id', $notificationIdInt, 1));
									$dbSuccessFlgImg = SQL_Process($DB_CONNECT, 'facility_notifications', $dbFiledDataImg, $dbWhereImg, 2, 2);
									if ($dbSuccessFlgImg !== 1) {
										throw new Exception('サムネイル画像パスの保存に失敗しました');
									}
								} else {
									#エラーログ出力
									$data = [
										'pageName' => 'proc_master05_03_02',
										'reason' => '記事DB登録失敗',
									];
									makeLog($data);
									#DB登録失敗
									$dbCompleteFlg = false;
								}
							}
							break;
						#***** 編集 *****#
						case 'edit': {
								#登録済み記事情報取得
								$notificationData = getFacilityNotifications_FindById($notificationIdInt);
								#登録用配列：初期化
								$dbFiledData = array();
								#登録情報セット
								$dbFiledData['title'] = array(':title', $title, 0);
								$dbFiledData['body_json'] = array(':body_json', $bodyJsonForDb, 0);
								$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
								#更新用キー：初期化
								$dbFiledValue = array();
								$dbFiledValue['notification_id'] = array(':notification_id', $notificationIdInt, 1);
								#処理モード：[1].新規追加｜[2].更新｜[3].削除
								$processFlg = 2;
								#実行モード：[1].トランザクション｜[2].即実行
								$exeFlg = 2;
								#DB更新
								$dbSuccessFlg = SQL_Process($DB_CONNECT, "facility_notifications", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
								#記事登録完了後に画像系処理を実行する
								if ($dbSuccessFlg == 1) {
									#画像ディレクトリ作成はコミット後に実施
									$notificationJsonDir = DEFINE_JSON_DIR_PATH . '/notification/' . $notificationData['code'] . '/';
									$pendingMkdirDirs[] = $notificationJsonDir;

									#--- サムネイル画像の確定予約（コミット後に移動） ---#
									$bannerImageDir = DEFINE_JSON_DIR_PATH . '/notification/' . $notificationData['code'] . '/';
									$pendingMkdirDirs[] = $bannerImageDir;
									$imagePathArr = [];
									if (isset($existingImagePaths) && is_array($existingImagePaths)) {
										foreach ($existingImagePaths as $path) {
											if (is_string($path) && $path !== '') {
												$imagePathArr[] = $path;
											}
										}
									}
									#--- セッション画像の反映処理 ---#
									foreach ($imageUploadSessionKey as $area) {
										if (!(isset($_SESSION[$area]) && is_array($_SESSION[$area]))) {
											continue;
										}
										#セッションが「DBリストを展開済み（materialized）」かどうか判定
										$isMaterialized = false;
										foreach ($_SESSION[$area] as $row) {
											if (!is_array($row)) continue;
											if ((isset($row['is_db']) && $row['is_db'] === true) || (isset($row['path']) && is_string($row['path']) && $row['path'] !== '')) {
												$isMaterialized = true;
												break;
											}
										}
										if ($isMaterialized) {
											#セッションの並び順を最終形として採用（削除/置換/追加を反映）
											$finalPaths = [];
											$finalBasenames = [];
											foreach ($_SESSION[$area] as $imgIndex => $img) {
												if (!is_array($img)) continue;
												$src = $img['tmp_name'] ?? '';
												$name = safeBasename($img['name'] ?? '');
												$path = $img['path'] ?? '';
												if (is_string($src) && $src !== '' && is_string($name) && $name !== '') {
													$dst = rtrim($bannerImageDir, '/\\') . '/' . $name;
													$pendingMoves[] = ['src' => $src, 'dst' => $dst];
													$pendingTmpFiles[] = $src;
													$newPublicPath = 'notification/' . $notificationData['code'] . '/' . $name;
													$finalPaths[] = $newPublicPath;
													$finalBasenames[] = basename($newPublicPath);
													#置換元が分かるならコミット後に削除
													$replaceFrom = $img['replace_from_path'] ?? ($existingImagePaths[$imgIndex] ?? '');
													if (is_string($replaceFrom) && $replaceFrom !== '') {
														$oldBase = basename($replaceFrom);
														if (is_string($oldBase) && $oldBase !== '') {
															$pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
														}
													}
												} elseif (is_string($path) && $path !== '') {
													$relPath = facilityNotificationsDbRelFromStoredPath($path);
													$finalPaths[] = $relPath !== '' ? $relPath : $path;
													$finalBasenames[] = basename($relPath !== '' ? $relPath : $path);
												} else {
													#削除済み/空
													continue;
												}
											}
											#削除されたDB画像をコミット後に削除
											foreach ($existingImagePaths as $oldPath) {
												if (!is_string($oldPath) || $oldPath === '') continue;
												$oldBase = basename($oldPath);
												if ($oldBase === '') continue;
												if (!in_array($oldBase, $finalBasenames, true)) {
													$pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
												}
											}
											$imagePathArr = $finalPaths;
										} else {
											#従来どおり：DBの既存 + セッションの新規（追加/置換）
											foreach ($_SESSION[$area] as $imgIndex => $img) {
												$src = $img['tmp_name'] ?? '';
												$name = safeBasename($img['name'] ?? '');
												if (!is_string($src) || $src === '' || !is_string($name) || $name === '') {
													continue;
												}
												$dst = rtrim($bannerImageDir, '/\\') . '/' . $name;
												$pendingMoves[] = ['src' => $src, 'dst' => $dst];
												$pendingTmpFiles[] = $src;
												$newPublicPath = 'notification/' . $notificationData['code'] . '/' . $name;
												if (is_int($imgIndex) && $imgIndex >= 0 && $imgIndex < count($imagePathArr)) {
													$oldPublicPath = $imagePathArr[$imgIndex];
													$imagePathArr[$imgIndex] = $newPublicPath;
													if (is_string($oldPublicPath) && $oldPublicPath !== '') {
														$oldBase = basename($oldPublicPath);
														if (is_string($oldBase) && $oldBase !== '') {
															$pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
														}
													}
												} else {
													$imagePathArr[] = $newPublicPath;
												}
											}
										}
										$pendingClearSessions[] = $area;
									}
									#--- ここまでセッション画像の反映処理 ---#

									$notificationImagePaths = array_values(array_filter(array_map('facilityNotificationsDbRelFromStoredPath', $imagePathArr), function ($x) {
										return is_string($x) && $x !== '';
									}));
									#「削除して再登録」などで不要になった旧ファイルを確実に削除予約
									$oldRelPaths = $existingImagePaths;
									$newRelPaths = $notificationImagePaths;
									$toDeleteRel = array_values(array_diff($oldRelPaths, $newRelPaths));
									foreach ($toDeleteRel as $rel) {
										$fs = facilityNotificationsStoredPathToFsPath($rel);
										if (is_string($fs) && $fs !== '') {
											$pendingDeleteFiles[] = $fs;
										}
									}
									#notification_image_path を更新（DB）
									$dbFiledDataImg = array(
										'notification_image_path' => array(':notification_image_path', json_encode($notificationImagePaths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0),
										'updated_at' => array(':updated_at', date('Y-m-d H:i:s'), 0),
									);
									$dbWhereImg = array('notification_id' => array(':notification_id', $notificationIdInt, 1));
									$dbSuccessFlgImg = SQL_Process($DB_CONNECT, 'facility_notifications', $dbFiledDataImg, $dbWhereImg, 2, 2);
									if ($dbSuccessFlgImg !== 1) {
										throw new Exception('サムネイル画像パスの保存に失敗しました');
									}
								} else {
									#エラーログ出力
									$data = [
										'pageName' => 'proc_master05_03_02',
										'reason' => '記事DB登録失敗',
									];
									makeLog($data);
									#DB登録失敗
									$dbCompleteFlg = false;
								}
							}
							break;
						#***** 削除 *****#
						case 'delete': {
								#登録済み記事情報取得
								$notificationData = getFacilityNotifications_FindById($notificationIdInt);
								if (!$notificationData) {
									throw new Exception('削除対象の記事が見つかりません');
								}
								#登録用配列：初期化
								$dbFiledData = array();
								#更新用キー：初期化
								$dbFiledValue = array();
								$dbFiledValue['notification_id'] = array(':notification_id', $notificationIdInt, 1);
								#処理モード：[1].新規追加｜[2].更新｜[3].削除
								$processFlg = 3;
								#実行モード：[1].トランザクション｜[2].即実行
								$exeFlg = 2;
								#DB更新
								$dbSuccessFlg = SQL_Process($DB_CONNECT, "facility_notifications", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
								#記事の基本情報削除完了後に詳細情報を削除する
								if ($dbSuccessFlg == 1) {
									#記事ディレクトリごと削除予約（コミット後に削除）
									$notificationDir = DEFINE_JSON_DIR_PATH . '/notification/' . $notificationData['code'] . '/';
									$pendingDeleteDirs[] = $notificationDir;
									#JSON側も削除予約
									$notificationJsonDir = DEFINE_JSON_DIR_PATH . '/notification/' . $notificationData['code'] . '/';
									$pendingDeleteDirs[] = $notificationJsonDir;
								} else {
									#エラーログ出力
									$data = [
										'pageName' => 'proc_master05_03_02',
										'reason' => '記事DB削除失敗',
									];
									makeLog($data);
									#DB登録失敗
									$dbCompleteFlg = false;
								}
							}
							break;
					}
					#全ての処理成功
					if ($dbCompleteFlg == true) {
						#DBコミット
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(2);
						$makeTag['status'] = 'success';
						switch ($method) {
							#***** 新規登録 *****#
							case 'new': {
									$makeTag['title'] = '新規お知らせ登録';
									$makeTag['msg'] = 'お知らせの登録が完了しました。';
									$makeTag['notificationId'] = $newNotificationId;
								}
								break;
							#***** 編集 *****#
							case 'edit': {
									$makeTag['title'] = 'お知らせ情報編集';
									$makeTag['msg'] = 'お知らせの更新が完了しました。';
									$makeTag['notificationId'] = $notificationId;
								}
								break;
							#***** 削除 *****#
							case 'delete': {
									$makeTag['title'] = 'お知らせ情報削除';
									$makeTag['msg'] = 'お知らせの削除が完了しました。';
									$makeTag['notificationId'] = $notificationId;
								}
								break;
						}
						#----------------------------
						# コミット後のファイル確定処理
						#----------------------------
						$fsErrors = [];
						#mkdir
						$pendingMkdirDirs = array_values(array_unique(array_filter($pendingMkdirDirs, 'is_string')));
						foreach ($pendingMkdirDirs as $dir) {
							if ($dir === '') continue;
							if (!ensureDir($dir)) {
								$fsErrors[] = 'mkdir失敗: ' . $dir;
							}
						}
						#move
						$movedTmpFiles = [];
						foreach ($pendingMoves as $m) {
							$src = $m['src'] ?? '';
							$dst = $m['dst'] ?? '';
							if (!is_string($src) || $src === '' || !is_string($dst) || $dst === '') {
								continue;
							}
							if (!safeMoveFile($src, $dst)) {
								$fsErrors[] = 'move失敗: ' . $src . ' -> ' . $dst;
							} else {
								$movedTmpFiles[] = $src;
							}
						}
						#delete files (置換で不要になった古いファイルなど)
						$pendingDeleteFiles = array_values(array_unique(array_filter($pendingDeleteFiles, 'is_string')));
						foreach ($pendingDeleteFiles as $f) {
							if ($f === '') continue;
							if (file_exists($f) && is_file($f)) {
								if (@unlink($f) === false) {
									$fsErrors[] = 'file削除失敗: ' . $f;
								}
							}
						}
						#delete dirs
						$pendingDeleteDirs = array_values(array_unique(array_filter($pendingDeleteDirs, 'is_string')));
						foreach ($pendingDeleteDirs as $dir) {
							if ($dir === '') continue;
							if (is_dir($dir)) {
								if (!rrmdir($dir)) {
									$fsErrors[] = 'dir削除失敗: ' . $dir;
								}
							}
						}
						#tmp掃除（move成功したtmpだけ掃除。失敗時にtmpを消すと画像が失われるため）
						cleanupFiles($movedTmpFiles);
						#セッション掃除（コミット後にまとめて）
						$pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
						foreach ($pendingClearSessions as $sKey) {
							unset($_SESSION[$sKey]);
						}
						if (count($fsErrors) > 0) {
							$data = [
								'pageName' => 'proc_master05_03_02',
								'reason' => 'コミット後のファイル確定処理で失敗',
								'fsErrors' => $fsErrors,
							];
							makeLog($data);
							#UI上は登録成功扱い（DBは確定済み）にして注意文を付与
							$makeTag['msg'] .= '<br>※画像/ディレクトリ確定処理で一部失敗しました。ログをご確認ください。';
						}
						#対象お知らせコード（JSON/画像パス用）
						$notificationCode = '';
						if ($method === 'new') {
							$notificationCode = $newNotificationCode;
						} elseif (isset($notificationData['code'])) {
							$notificationCode = (string)$notificationData['code'];
						}
						#DB登録/更新完了後に、画像を最終ディレクトリへ移動し、body_json内URLを書き換える（削除以外）
						if ($method !== 'delete' && $notificationCode !== '') {
							$originalBodyJsonForCompare = isset($bodyJsonForDb) ? (string)$bodyJsonForDb : (string)$body_json;
							list($finalBodyJson, $finalBodyPlainText) = finalizeInlineImagesAndRewriteBody($notificationIdInt, $notificationCode, $noUpDateKey, $originalBodyJsonForCompare, $bodyPlainText);
							if ($finalBodyJson !== $originalBodyJsonForCompare) {
								DB_Transaction(1);
								$dbFiledData2 = array(
									'body_json' => array(':body_json', $finalBodyJson, 0),
									'updated_at' => array(':updated_at', date('Y-m-d H:i:s'), 0),
								);
								$dbWhereParams2 = array(
									'notification_id' => array(':notification_id', $notificationIdInt, 1),
								);
								$dbSuccessFlg2 = SQL_Process($DB_CONNECT, 'facility_notifications', $dbFiledData2, $dbWhereParams2, 2, 2);
								if ($dbSuccessFlg2 !== 1) {
									throw new RuntimeException('DB更新に失敗しました');
								}
								DB_Transaction(2);
								$decodedForReturn = json_decode((string)$finalBodyJson, true);
								if (json_last_error() === JSON_ERROR_NONE && is_array($decodedForReturn)) {
									$makeTag['body_json'] = $decodedForReturn;
								}
							}
							#参照されない画像の後始末（削除以外）
							try {
								$deletedOrphans = cleanupOrphanedFacilityNotificationsImages($notificationIdInt, $notificationCode);
								if (is_array($deletedOrphans) && count($deletedOrphans) > 0) {
									$data = [
										'pageName' => 'proc_master05_03_02',
										'reason' => 'orphan images deleted',
										'notificationCode' => $notificationCode,
										'deleted' => $deletedOrphans,
									];
									makeLog($data);
								}
							} catch (Throwable $e) {
								$data = [
									'pageName' => 'proc_master05_03_02',
									'reason' => 'orphan images cleanup failed',
									'notificationCode' => $notificationCode,
									'errorMessage' => $e->getMessage(),
								];
								makeLog($data);
							}
						}
					} else {
						#DBロールバック
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(3);
						#失敗時クリーンアップ（tmpファイルとセッション）
						cleanupFiles($pendingTmpFiles);
						$pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
						foreach ($pendingClearSessions as $sKey) {
							unset($_SESSION[$sKey]);
						}
						$makeTag['status'] = 'error';
					}
				}
			} catch (Exception $e) {
				#エラーログ出力
				$data = [
					'pageName' => 'proc_master05_03_02',
					'reason' => 'トランザクション開始失敗',
					'errorMessage' => $e->getMessage(),
				];
				makeLog($data);
				#例外時もクリーンアップ（tmpファイルとセッション）
				cleanupFiles($pendingTmpFiles ?? []);
				if (isset($pendingClearSessions) && is_array($pendingClearSessions)) {
					$pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
					foreach ($pendingClearSessions as $sKey) {
						unset($_SESSION[$sKey]);
					}
				}
				$makeTag['status'] = 'error';
			}
			#応答
			jsonExit($makeTag);
		}
		break;
}
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
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}
#-------------------------------------------#
#===========================================#
