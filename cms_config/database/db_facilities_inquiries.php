<?php

/**
 * - 管理者専用 -
 * [お問い合わせ一覧検索（LIMIT/OFFSET）]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function searchMasterFacilityInquiriesList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT
				i.inquiry_id,
				i.facility_id,
				i.category,
				i.body,
				i.reply_channel,
				i.handling_status,
				i.record_status,
				i.admin_mail_sent_at,
				i.facility_mail_sent_at,
				i.created_at,
				i.updated_at,
				f.name AS facility_name,
				f.name_kana AS facility_name_kana
			FROM
				facility_inquiries i
				INNER JOIN facilities f ON i.facility_id = f.facility_id
			WHERE
				i.record_status = 'active'
				AND f.is_active = 1
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchMasterFacilityInquiriesHelper($searchConditions);
		$strSQL .= $whereSql;
		$sendedSortOrderSql = (isset($searchConditions['sendedSortOrder']) && strtolower((string)$searchConditions['sendedSortOrder']) === 'asc') ? 'ASC' : 'DESC';
		$strSQL .= " ORDER BY i.created_at {$sendedSortOrderSql}, i.inquiry_id {$sendedSortOrderSql}";
		$offset = ((int)$pageNumber - 1) * (int)$displayNumber;
		if ($offset < 0) {
			$offset = 0;
		}
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
		$inquiries = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $inquiries ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/**
 * - 管理者専用 -
 * [お問い合わせ一覧検索：総件数取得]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchMasterFacilityInquiriesCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT COUNT(*) AS cnt
			FROM facility_inquiries i
			INNER JOIN facilities f ON i.facility_id = f.facility_id
			WHERE i.record_status = 'active'
			AND f.is_active = 1
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchMasterFacilityInquiriesHelper($searchConditions);
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
/**
 * - 管理者専用 -
 * [お問い合わせ取得（ID指定）]
 *  引数
 *   $inquiryId：お問い合わせID
 */
function getMasterFacilityInquiry_FindById($inquiryId = null)
{
	global $DB_CONNECT;
	try {
		$inquiryId = (int)$inquiryId;
		if ($inquiryId < 1) {
			return null;
		}
		#SQL定義
		$strSQL = "
			SELECT
				i.inquiry_id,
				i.facility_id,
				i.category,
				i.body,
				i.reply_channel,
				i.handling_status,
				i.record_status,
				i.admin_mail_sent_at,
				i.facility_mail_sent_at,
				i.created_at,
				i.updated_at,
				f.name AS facility_name,
				f.name_kana AS facility_name_kana
			FROM facility_inquiries i
			INNER JOIN facilities f ON i.facility_id = f.facility_id
			WHERE
				i.inquiry_id = :inquiry_id
				AND i.record_status = 'active'
				AND f.is_active = 1
			LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':inquiry_id', $inquiryId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$inquiry = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		return $inquiry ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/**
 * - 管理者専用 -
 * [お問い合わせ一覧検索用ヘルパー]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchMasterFacilityInquiriesHelper($searchConditions)
{
	$whereSql = '';
	$sqlParams = array();
	foreach ((array)$searchConditions as $key => $value) {
		if ($key === '' || $value === '' || $value === null) {
			continue;
		}
		switch ($key) {
			#事業所名
			case 'facilityName': {
					$whereSql .= " AND (f.name LIKE :facility_name_like OR f.name_kana LIKE :facility_name_kana_like)";
					$sqlParams[':facility_name_like'] = '%' . (string)$value . '%';
					$sqlParams[':facility_name_kana_like'] = '%' . (string)$value . '%';
				}
				break;
			#絞り込み
			case 'initials': {
					$searchColumn = 'f.name_kana';
					#ひらがな行のマッピング
					$gyoMap = [
						'あ' => ['あ', 'い', 'う', 'え', 'お'],
						'か' => ['か', 'き', 'く', 'け', 'こ'],
						'さ' => ['さ', 'し', 'す', 'せ', 'そ'],
						'た' => ['た', 'ち', 'つ', 'て', 'と'],
						'な' => ['な', 'に', 'ぬ', 'ね', 'の'],
						'は' => ['は', 'ひ', 'ふ', 'へ', 'ほ'],
						'ま' => ['ま', 'み', 'む', 'め', 'も'],
						'や' => ['や', 'ゆ', 'よ'],
						'ら' => ['ら', 'り', 'る', 'れ', 'ろ'],
						'わ' => ['わ', 'を', 'ん'],
					];
					if (is_array($value) && count($value) > 0) {
						$likeClauses = [];
						$paramIdx = 0;
						foreach ($value as $initial) {
							$initial = (string)$initial;
							if (!isset($gyoMap[$initial])) {
								continue;
							}
							foreach ($gyoMap[$initial] as $kana) {
								$paramKey = ':name_kana_' . $paramIdx;
								$likeClauses[] = $searchColumn . ' LIKE ' . $paramKey;
								$sqlParams[$paramKey] = $kana . '%';
								$paramIdx++;
							}
						}
						if (count($likeClauses) > 0) {
							$whereSql .= ' AND (' . implode(' OR ', $likeClauses) . ')';
						}
					}
				}
				break;
			#対応ステータス
			case 'handledStatus': {
					$allowed = ['new', 'in_progress', 'done'];
					if (!in_array((string)$value, $allowed, true)) {
						break;
					}
					$whereSql .= " AND i.handling_status = :handling_status";
					$sqlParams[':handling_status'] = (string)$value;
				}
				break;
		}
	}
	$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? (string)$searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? (string)$searchConditions['endDay'] : null;
	if ($start !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1) {
		$start = null;
	}
	if ($end !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
		$end = null;
	}
	if ($start !== null && $end !== null) {
		$whereSql .= " AND DATE(i.created_at) BETWEEN :startDay AND :endDay";
		$sqlParams[':startDay'] = $start;
		$sqlParams[':endDay'] = $end;
	} elseif ($start !== null) {
		$whereSql .= " AND DATE(i.created_at) >= :startDay";
		$sqlParams[':startDay'] = $start;
	} elseif ($end !== null) {
		$whereSql .= " AND DATE(i.created_at) <= :endDay";
		$sqlParams[':endDay'] = $end;
	}
	return array($whereSql, $sqlParams);
}

/*
 * - 事業所専用 -
 * [お問い合わせ一覧取得]
 */
function getFacilityInquiriesList()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				inquiry_id, facility_id, category, body, reply_channel, handling_status, record_status,
				admin_mail_sent_at, facility_mail_sent_at, created_at, updated_at
			FROM 
				facility_inquiries
			WHERE
				record_status = 'active'
			ORDER BY 
				created_at DESC, inquiry_id DESC
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$inquiries = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $inquiries ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * - 事業所専用 -
 * [お問い合わせ一覧検索]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function searchFacilityInquiriesList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				inquiry_id, facility_id, category, body, reply_channel, handling_status, record_status,
				admin_mail_sent_at, facility_mail_sent_at, created_at, updated_at
			FROM 
				facility_inquiries
			WHERE
				record_status = 'active'
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityInquiriesHelper($searchConditions);
		$strSQL .= $whereSql;
		#並び順（送信日時：created_at）
		$sendedSortOrderSql = (isset($searchConditions['sendedSortOrder']) && strtolower((string)$searchConditions['sendedSortOrder']) === 'asc') ? 'ASC' : 'DESC';
		$strSQL .= " ORDER BY created_at {$sendedSortOrderSql}, inquiry_id {$sendedSortOrderSql}";
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
		$inquiries = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $inquiries ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * - 事業所専用 -
 * [お問い合わせ一覧検索：総件数取得]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchFacilityInquiriesCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT COUNT(*) AS cnt FROM facility_inquiries WHERE record_status = 'active'";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityInquiriesHelper($searchConditions);
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
 * - 事業所専用 -
 * [お問い合わせ一覧検索用ヘルパー関数]
 *  引数
 *   $searchConditions：検索条件配列
 *  戻り値
 *   array($whereSql, $sqlParams)
 */
function searchFacilityInquiriesHelper($searchConditions)
{
	$whereSql = '';
	$sqlParams = array();
	#事業所ID（必須。無い場合は漏洩防止で0件にする）
	$facilityId = isset($searchConditions['facilityId']) ? (int)$searchConditions['facilityId'] : 0;
	if ($facilityId < 1) {
		$whereSql .= " AND 1 = 0";
		return array($whereSql, $sqlParams);
	}
	$whereSql .= " AND facility_id = :facility_id";
	$sqlParams[':facility_id'] = $facilityId;
	foreach ($searchConditions as $key => $value) {
		#検索条件設定
		if ($key == '' || $value == '') {
			continue;
		}
		switch ($key) {
			#件名
			case 'category':
				$allowed = ['plan', 'password', 'other'];
				if (!in_array((string)$value, $allowed, true)) {
					break;
				}
				$whereSql .= " AND category = :category";
				$sqlParams[':category'] = (string)$value;
				break;
		}
	}
	#送信日（created_at）で抽出
	$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? (string)$searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? (string)$searchConditions['endDay'] : null;
	if ($start !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1) {
		$start = null;
	}
	if ($end !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1) {
		$end = null;
	}
	if ($start !== null && $end !== null) {
		$whereSql .= " AND DATE(created_at) BETWEEN :startDay AND :endDay";
		$sqlParams[':startDay'] = $start;
		$sqlParams[':endDay'] = $end;
	} elseif ($start !== null) {
		$whereSql .= " AND DATE(created_at) >= :startDay";
		$sqlParams[':startDay'] = $start;
	} elseif ($end !== null) {
		$whereSql .= " AND DATE(created_at) <= :endDay";
		$sqlParams[':endDay'] = $end;
	}

	#共通WHERE句を応答
	return array($whereSql, $sqlParams);
}
/*
 * - 事業所専用 -
 * [お問い合わせ取得（ID指定）]
 *  引数
 *   $inquiryId：お問い合わせID
 */
function getFacilityInquiries_FindById($inquiryId = null)
{
	global $DB_CONNECT;
	try {
		#「$inquiryId」で検索
		$strSQL = "
			SELECT 
				inquiry_id, facility_id, category, body, reply_channel, handling_status, record_status,
				admin_mail_sent_at, facility_mail_sent_at, created_at, updated_at
			FROM 
				facility_inquiries 
			WHERE 
				inquiry_id = :value LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':value', $inquiryId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$inquiry = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却
		return $inquiry ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * - 事業所専用 -
 * [お問い合わせ取得（ID指定：事業所スコープ）]
 *  引数
 *   $inquiryId ：お問い合わせID
 *   $facilityId：事業所ID（セッション由来）
 */
function getFacilityInquiries_FindByIdForFacility($inquiryId = null, $facilityId = null)
{
	global $DB_CONNECT;
	try {
		$inquiryId = (int)$inquiryId;
		$facilityId = (int)$facilityId;
		if ($inquiryId < 1 || $facilityId < 1) {
			return null;
		}
		$strSQL = "
			SELECT
				inquiry_id, facility_id, category, body, reply_channel, handling_status, record_status,
				admin_mail_sent_at, facility_mail_sent_at, created_at, updated_at
			FROM
				facility_inquiries
			WHERE
				inquiry_id = :inquiry_id
				AND facility_id = :facility_id
				AND record_status = 'active'
			LIMIT 1
		";
		$newStmt = $DB_CONNECT->prepare($strSQL);
		$newStmt->bindValue(':inquiry_id', $inquiryId, PDO::PARAM_INT);
		$newStmt->bindValue(':facility_id', $facilityId, PDO::PARAM_INT);
		$newStmt->execute();
		$inquiry = $newStmt->fetch(PDO::FETCH_ASSOC);
		$newStmt->closeCursor();
		return $inquiry ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
