<?php
/*
 * [事業所へのお知らせの最新 notification_id 取得]
 */
function getLastNotificationId()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_workrecruit' AND TABLE_NAME = 'facility_notifications'";
		#$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_demowork' AND TABLE_NAME = 'facility_notifications'";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$account = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $account ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所へのお知らせ一覧取得]
 *  引数
 *   $whereParam：取得条件
 */
function getFacilityNotificationsList($whereParam = 'all')
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				notification_id, code, status, title, body_json, notification_image_path, published_start, updated_at 
			FROM 
				facility_notifications
		";
		$sqlParams = [];
		$whereParam = is_string($whereParam) ? $whereParam : 'all';
		#取得条件
		# all    : 全て取得
		# public : 表示中のみ取得（公開ステータス かつ 公開開始日時が現在以前）
		if ($whereParam === 'public') {
			$strSQL .= " WHERE status = :status AND (published_start IS NULL OR published_start <= NOW())";
			$sqlParams[':status'] = 'public';
			$strSQL .= " ORDER BY notification_id DESC LIMIT 5";
		} else {
			$strSQL .= " ORDER BY notification_id DESC";
		}
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		foreach ($sqlParams as $paramKey => $paramValue) {
			$newStmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$facilityNotifications = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $facilityNotifications ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所へのお知らせ一覧検索]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function searchFacilityNotificationsList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				notification_id, code, status, title, body_json, notification_image_path, published_start, updated_at 
			FROM 
				facility_notifications
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityNotificationsHelper($searchConditions);
		$strSQL .= $whereSql;
		#並び替え（番号/最終更新日：2軸 + 主キー）
		$sortTarget = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'notification_id';
		if ($sortTarget !== 'notification_id' && $sortTarget !== 'updated_at') {
			$sortTarget = 'notification_id';
		}
		$idSortOrder = isset($searchConditions['idSortOrder']) ? strtolower((string)$searchConditions['idSortOrder']) : '';
		$updatedAtSortOrder = isset($searchConditions['updateDateSortOrder']) ? strtolower((string)$searchConditions['updateDateSortOrder']) : '';
		if ($idSortOrder !== 'asc' && $idSortOrder !== 'desc') {
			$idSortOrder = 'desc';
		}
		if ($updatedAtSortOrder !== 'asc' && $updatedAtSortOrder !== 'desc') {
			$updatedAtSortOrder = 'desc';
		}
		$idSortOrderSql = ($idSortOrder === 'asc') ? 'ASC' : 'DESC';
		$updatedAtSortOrderSql = ($updatedAtSortOrder === 'asc') ? 'ASC' : 'DESC';
		$strSQL .= " ORDER BY ";
		if ($sortTarget === 'updated_at') {
			#主キー：更新日（NULLは最後）、副キー：番号
			$strSQL .= "(updated_at IS NULL) ASC, updated_at {$updatedAtSortOrderSql}, notification_id {$idSortOrderSql}";
		} else {
			#主キー：番号、副キー：更新日（NULLは最後）
			$strSQL .= "notification_id {$idSortOrderSql}, (updated_at IS NULL) ASC, updated_at {$updatedAtSortOrderSql}";
		}
		#ページング
		$offset = ($pageNumber - 1) * $displayNumber;
		$strSQL .= " LIMIT :limit OFFSET :offset";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		foreach ($sqlParams as $paramKey => $paramValue) {
			$newStmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
		}
		$newStmt->bindValue(':limit', (int)$displayNumber, PDO::PARAM_INT);
		$newStmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$facilityNotifications = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $facilityNotifications ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所へのお知らせ一覧検索：総件数取得]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchFacilityNotificationsCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT COUNT(*) AS cnt FROM facility_notifications";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityNotificationsHelper($searchConditions);
		$strSQL .= $whereSql;
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		foreach ($sqlParams as $paramKey => $paramValue) {
			$newStmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$row = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		return isset($row['cnt']) ? (int)$row['cnt'] : 0;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所へのお知らせ一覧検索用ヘルパー関数]
 *  引数
 *   $searchConditions：検索条件配列
 *  戻り値
 *   array($whereSql, $sqlParams)
 */
function searchFacilityNotificationsHelper($searchConditions)
{
	$whereSql = ' WHERE 1=1';
	$sqlParams = array();
	#選択した日付内の最終更新日（updated_at）を持つ記事を抽出
	$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? $searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? $searchConditions['endDay'] : null;
	$searchKey = 'updated_at';
	if ($start !== null && $end !== null) {
		$whereSql .= " AND $searchKey BETWEEN :startDay AND :endDay";
		$sqlParams[':startDay'] = $start . ' 00:00:00';
		$sqlParams[':endDay'] = $end . ' 23:59:59';
	} elseif ($start !== null) {
		$whereSql .= " AND $searchKey >= :startDay";
		$sqlParams[':startDay'] = $start . ' 00:00:00';
	} elseif ($end !== null) {
		$whereSql .= " AND $searchKey <= :endDay";
		$sqlParams[':endDay'] = $end . ' 23:59:59';
	}
	#共通WHERE句を応答
	return array($whereSql, $sqlParams);
}
/*
 * [事業所へのお知らせ取得（ID指定）]
 *  引数
 *   $notificationId：事業所へのお知らせID
 */
function getFacilityNotifications_FindById($notificationId = null)
{
	global $DB_CONNECT;
	try {
		#「$notificationId」で検索
		$strSQL = "
			SELECT 
				notification_id, code, status, title, body_json, notification_image_path, published_start, updated_at 
			FROM 
				facility_notifications 
			WHERE 
				notification_id = :value LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':value', $notificationId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$corporation = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却
		return $corporation ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所へのお知らせを開封済みか取得（ID指定）]
 *  引数
 *   $facilityId    ：事業所ID
 *   $notificationId：事業所へのお知らせID
 */
function isFacilityNotificationOpened($facilityId = null, $notificationId = null)
{
	global $DB_CONNECT;
	try {
		#「$facilityId」「$notificationId」で検索
		$strSQL = "
			SELECT 
				COUNT(*) AS cnt 
			FROM 
				facility_notification_reads 
			WHERE 
				facility_id = :facilityId AND 
				notification_id = :notificationId
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':facilityId', $facilityId, PDO::PARAM_INT);
		$newStmt->bindValue(':notificationId', $notificationId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$row = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		return isset($row['cnt']) && (int)$row['cnt'] > 0;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
