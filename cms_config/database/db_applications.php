<?php
/*
 * [応募者一覧取得]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function getApplicationList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#並び替え（応募日/面接日：2軸 + 主キー）
		$sortTarget = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'application_at';
		if ($sortTarget !== 'application_at' && $sortTarget !== 'interview_at') {
			$sortTarget = 'application_at';
		}
		$applicationSortOrder = isset($searchConditions['applicationSortOrder']) ? strtolower((string)$searchConditions['applicationSortOrder']) : '';
		$interviewSortOrder = isset($searchConditions['interviewSortOrder']) ? strtolower((string)$searchConditions['interviewSortOrder']) : '';
		if ($applicationSortOrder !== 'asc' && $applicationSortOrder !== 'desc') {
			$applicationSortOrder = 'desc';
		}
		if ($interviewSortOrder !== 'asc' && $interviewSortOrder !== 'desc') {
			$interviewSortOrder = 'desc';
		}
		$applicationSortOrderSql = ($applicationSortOrder === 'asc') ? 'ASC' : 'DESC';
		$interviewSortOrderSql = ($interviewSortOrder === 'asc') ? 'ASC' : 'DESC';
		#ページング
		$pageNumber = (int)$pageNumber;
		$displayNumber = (int)$displayNumber;
		if ($pageNumber < 1) {
			$pageNumber = 1;
		}
		if ($displayNumber < 1) {
			$displayNumber = 10;
		}
		$offset = ($pageNumber - 1) * $displayNumber;
		#SQL定義（1人=1行：対象ステータスの最新応募を代表として取得）
		$strSQL = "SELECT a.application_id, a.job_id, a.job_category_id, a.facility_id, a.corporation_id, a.line_user_id, a.line_display_name, a.applicant_name, a.status, a.interview_at, a.created_at, a.updated_at\n"
			. "FROM applications a\n"
			. "INNER JOIN (\n";
		$subSql = "  SELECT a2.line_user_id, MAX(a2.application_id) AS latest_application_id\n"
			. "  FROM applications a2";
		list($joinSql, $whereSql, $sqlParams) = searchApplicationsHelper($searchConditions, ['alias' => 'a2']);
		$subSql .= $joinSql . "\n  WHERE 1=1" . $whereSql . "\n  GROUP BY a2.line_user_id\n";
		$strSQL .= $subSql . ") t ON t.latest_application_id = a.application_id\n"
			. "ORDER BY ";
		if ($sortTarget === 'interview_at') {
			#主キー：面接日（NULLは最後）、副キー：応募日
			$strSQL .= "(a.interview_at IS NULL) ASC, a.interview_at {$interviewSortOrderSql}, a.created_at {$applicationSortOrderSql}, a.application_id DESC\n";
		} else {
			#主キー：応募日、副キー：面接日（NULLは最後）
			$strSQL .= "a.created_at {$applicationSortOrderSql}, (a.interview_at IS NULL) ASC, a.interview_at {$interviewSortOrderSql}, a.application_id DESC\n";
		}
		$strSQL .= "LIMIT :limit OFFSET :offset";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド（検索条件）
		foreach ($sqlParams as $paramKey => $paramValue) {
			if ($paramKey === ':facility_id') {
				$newStmt->bindValue($paramKey, (int)$paramValue, PDO::PARAM_INT);
				continue;
			}
			if ($paramKey === ':job_category_id') {
				$newStmt->bindValue($paramKey, (string)$paramValue, PDO::PARAM_STR);
				continue;
			}
			$newStmt->bindValue($paramKey, (string)$paramValue, PDO::PARAM_STR);
		}
		$newStmt->bindValue(':limit', $displayNumber, PDO::PARAM_INT);
		$newStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$applicationList = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $applicationList ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [応募人数取得]
 *  引数
 *   $status：応募状況ステータス（registered/applied/interview/hired/unresponsive）
 */
function getApplicationCount($status)
{
	global $DB_CONNECT;
	try {
		$facilityId = 0;
		if (func_num_args() >= 2) {
			$facilityId = (int)func_get_arg(1);
		}
		$hasFacilityFilter = ($facilityId > 0);
		#SQL定義（対象ステータスの応募が存在する人数を数える）
		if ((string)$status === 'registered') {
			$strSQL = "SELECT COUNT(DISTINCT line_user_id) FROM applications WHERE status IN ('registered','friend_only')";
			if ($hasFacilityFilter) {
				$strSQL .= " AND facility_id = :facility_id";
			}
			$newStmt = $DB_CONNECT->prepare($strSQL);
		} else {
			$strSQL = "SELECT COUNT(DISTINCT line_user_id) FROM applications WHERE status = :status";
			if ($hasFacilityFilter) {
				$strSQL .= " AND facility_id = :facility_id";
			}
			$newStmt = $DB_CONNECT->prepare($strSQL);
			$newStmt->bindValue(':status', $status, PDO::PARAM_STR);
		}
		if ($hasFacilityFilter) {
			$newStmt->bindValue(':facility_id', $facilityId, PDO::PARAM_INT);
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$applicationCount = (int)$newStmt->fetchColumn();
		#ステートメントクローズ
		$newStmt->closeCursor();
		#応答
		return $applicationCount;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [応募者一覧検索用ヘルパー関数]
 *  引数
 *   $searchConditions：検索条件配列
 *   $options：['alias' => 'a'] のようにテーブル別名を指定
 *  戻り値
 *   array($joinSql, $whereSql, $sqlParams)
 */
function searchApplicationsHelper($searchConditions, $options = [])
{
	$alias = isset($options['alias']) && (string)$options['alias'] !== '' ? (string)$options['alias'] : 'a';
	$whereSql = '';
	$joinSql = '';
	$sqlParams = [];
	#検索モード（ステータス）
	$searchMode = isset($searchConditions['searchMode']) ? (string)$searchConditions['searchMode'] : '';
	if ($searchMode === '') {
		$searchMode = 'registered';
	}
	if ($searchMode === 'all') {
		#全件（ステータス条件なし）
	} elseif ($searchMode === 'registered') {
		$whereSql .= " AND {$alias}.status IN ('registered','friend_only')";
	} else {
		$whereSql .= " AND {$alias}.status = :status";
		$sqlParams[':status'] = $searchMode;
	}
	#除外ステータス（searchMode=all のときのみ適用）
	if ($searchMode === 'all') {
		$excludeStatuses = isset($searchConditions['excludeStatuses']) ? $searchConditions['excludeStatuses'] : [];
		if (!is_array($excludeStatuses)) {
			$excludeStatuses = [];
		}
		$excludeStatuses = array_values(array_unique(array_filter(array_map('strval', $excludeStatuses), function ($v) {
			return $v !== '';
		})));
		if (count($excludeStatuses) > 0) {
			$placeholders = [];
			foreach ($excludeStatuses as $idx => $st) {
				$ph = ':exclude_status_' . $idx;
				$placeholders[] = $ph;
				$sqlParams[$ph] = $st;
			}
			$whereSql .= " AND {$alias}.status NOT IN (" . implode(',', $placeholders) . ")";
		}
	}
	#事業所ID（任意：未指定なら全件）
	$facilityId = isset($searchConditions['facility_id']) ? (int)$searchConditions['facility_id'] : 0;
	if ($facilityId > 0) {
		$whereSql .= " AND {$alias}.facility_id = :facility_id";
		$sqlParams[':facility_id'] = $facilityId;
	}
	#応募先（事業所名）検索（master側のみ：facility_idしか無いのでJOIN）
	$facilityName = isset($searchConditions['facility_name']) ? trim((string)$searchConditions['facility_name']) : '';
	if ($facilityName !== '') {
		$joinSql .= " INNER JOIN facilities f ON f.facility_id = {$alias}.facility_id AND f.is_active = 1";
		$whereSql .= ' AND (f.name LIKE :facility_name_like OR f.name_kana LIKE :facility_name_kana_like)';
		$sqlParams[':facility_name_like'] = '%' . $facilityName . '%';
		$sqlParams[':facility_name_kana_like'] = '%' . $facilityName . '%';
	}
	#名前（応募者）
	$referName = isset($searchConditions['referName']) ? trim((string)$searchConditions['referName']) : '';
	if ($referName !== '') {
		$whereSql .= " AND ({$alias}.applicant_name LIKE :applicant_name_like OR {$alias}.line_display_name LIKE :line_display_name_like)";
		$sqlParams[':applicant_name_like'] = '%' . $referName . '%';
		$sqlParams[':line_display_name_like'] = '%' . $referName . '%';
	}
	#職種
	$referJobType = isset($searchConditions['referJobType']) ? trim((string)$searchConditions['referJobType']) : '';
	if ($referJobType !== '') {
		$whereSql .= " AND {$alias}.job_category_id = :job_category_id";
		$sqlParams[':job_category_id'] = $referJobType;
	}
	#最終更新日（DATEで比較して日付入力と揃える）
	$start = isset($searchConditions['startDay']) && (string)$searchConditions['startDay'] !== '' ? (string)$searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && (string)$searchConditions['endDay'] !== '' ? (string)$searchConditions['endDay'] : null;
	if ($start !== null && $end !== null) {
		$whereSql .= " AND DATE({$alias}.updated_at) BETWEEN :startDay AND :endDay";
		$sqlParams[':startDay'] = $start;
		$sqlParams[':endDay'] = $end;
	} elseif ($start !== null) {
		$whereSql .= " AND DATE({$alias}.updated_at) >= :startDay";
		$sqlParams[':startDay'] = $start;
	} elseif ($end !== null) {
		$whereSql .= " AND DATE({$alias}.updated_at) <= :endDay";
		$sqlParams[':endDay'] = $end;
	}
	#絞り込み（頭文字：あ行〜）
	$initials = isset($searchConditions['initials']) ? $searchConditions['initials'] : [];
	if (!is_array($initials)) {
		$initials = [];
	}
	if (count($initials) > 0) {
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
		$likeClauses = [];
		$paramIdx = 0;
		foreach ($initials as $initial) {
			$initial = (string)$initial;
			if ($initial === '' || !isset($gyoMap[$initial])) continue;
			foreach ($gyoMap[$initial] as $kana) {
				#PDO(MySQL)で同名プレースホルダを複数回使うと HY093 になる環境があるため、列ごとに別名を使う
				$paramKeyA = ':initial' . $paramIdx . '_a';
				$paramKeyB = ':initial' . $paramIdx . '_b';
				$likeClauses[] = "({$alias}.applicant_name LIKE {$paramKeyA} OR {$alias}.line_display_name LIKE {$paramKeyB})";
				$sqlParams[$paramKeyA] = $kana . '%';
				$sqlParams[$paramKeyB] = $kana . '%';
				$paramIdx++;
			}
		}
		if (count($likeClauses) > 0) {
			$whereSql .= ' AND (' . implode(' OR ', $likeClauses) . ')';
		}
	}
	return [$joinSql, $whereSql, $sqlParams];
}
/*
 * [応募人数取得（検索条件反映）]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchApplicationCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		$strSQL = "SELECT COUNT(DISTINCT a.line_user_id) FROM applications a";
		list($joinSql, $whereSql, $sqlParams) = searchApplicationsHelper($searchConditions, ['alias' => 'a']);
		$strSQL .= $joinSql . ' WHERE 1=1' . $whereSql;
		$newStmt = $DB_CONNECT->prepare($strSQL);
		foreach ($sqlParams as $paramKey => $paramValue) {
			if ($paramKey === ':facility_id') {
				$newStmt->bindValue($paramKey, (int)$paramValue, PDO::PARAM_INT);
				continue;
			}
			if ($paramKey === ':job_category_id') {
				$newStmt->bindValue($paramKey, (string)$paramValue, PDO::PARAM_STR);
				continue;
			}
			$newStmt->bindValue($paramKey, (string)$paramValue, PDO::PARAM_STR);
		}
		$newStmt->execute();
		$cnt = (int)$newStmt->fetchColumn();
		$newStmt->closeCursor();
		return $cnt;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [応募中の求人一覧取得]
 *  引数
 *   $lineUserId：LINEユーザーID
 */
function getAllAppliedJobs($lineUserId)
{
	global $DB_CONNECT;
	try {
		$statuses = null;
		$sortTarget = 'application_at';
		$applicationSortOrder = 'desc';
		$interviewSortOrder = 'desc';
		$facilityId = 0;
		$jobCategoryId = '';
		if (func_num_args() >= 2) {
			$statuses = func_get_arg(1);
		}
		if (func_num_args() >= 3) {
			$sortTarget = (string)func_get_arg(2);
		}
		if (func_num_args() >= 4) {
			$applicationSortOrder = strtolower((string)func_get_arg(3));
		}
		if (func_num_args() >= 5) {
			$interviewSortOrder = strtolower((string)func_get_arg(4));
		}
		if (func_num_args() >= 6) {
			$facilityId = (int)func_get_arg(5);
		}
		if (func_num_args() >= 7) {
			$jobCategoryId = trim((string)func_get_arg(6));
		}
		$hasFacilityFilter = ($facilityId > 0);
		$hasJobCategoryFilter = ($jobCategoryId !== '');
		if ($sortTarget !== 'application_at' && $sortTarget !== 'interview_at') {
			$sortTarget = 'application_at';
		}
		if ($applicationSortOrder !== 'asc' && $applicationSortOrder !== 'desc') {
			$applicationSortOrder = 'desc';
		}
		if ($interviewSortOrder !== 'asc' && $interviewSortOrder !== 'desc') {
			$interviewSortOrder = 'desc';
		}
		$applicationSortOrderSql = ($applicationSortOrder === 'asc') ? 'ASC' : 'DESC';
		$interviewSortOrderSql = ($interviewSortOrder === 'asc') ? 'ASC' : 'DESC';
		#status指定があれば配列化
		if (is_string($statuses)) {
			$statuses = trim($statuses);
			$statuses = ($statuses !== '') ? [$statuses] : null;
		}
		if (is_array($statuses) && count($statuses) < 1) {
			$statuses = null;
		}
		#SQL定義
		$strSQL = "SELECT application_id, job_id, job_category_id, facility_id, corporation_id, line_display_name, applicant_name, status, interview_at, created_at, updated_at\n"
			. "FROM applications\n"
			. "WHERE line_user_id = :line_user_id\n";
		if ($hasFacilityFilter) {
			$strSQL .= "AND facility_id = :facility_id\n";
		}
		if ($hasJobCategoryFilter) {
			$strSQL .= "AND job_category_id = :job_category_id\n";
		}
		if (is_array($statuses)) {
			$placeholders = [];
			foreach (array_values($statuses) as $idx => $st) {
				$placeholders[] = ':status' . $idx;
			}
			$strSQL .= "AND status IN (" . implode(',', $placeholders) . ")\n";
		}
		$strSQL .= "ORDER BY ";
		if ($sortTarget === 'interview_at') {
			$strSQL .= "(interview_at IS NULL) ASC, interview_at {$interviewSortOrderSql}, created_at {$applicationSortOrderSql}, application_id DESC";
		} else {
			$strSQL .= "created_at {$applicationSortOrderSql}, (interview_at IS NULL) ASC, interview_at {$interviewSortOrderSql}, application_id DESC";
		}
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':line_user_id', $lineUserId, PDO::PARAM_STR);
		if ($hasFacilityFilter) {
			$newStmt->bindValue(':facility_id', $facilityId, PDO::PARAM_INT);
		}
		if ($hasJobCategoryFilter) {
			$newStmt->bindValue(':job_category_id', $jobCategoryId, PDO::PARAM_STR);
		}
		if (is_array($statuses)) {
			foreach (array_values($statuses) as $idx => $st) {
				$newStmt->bindValue(':status' . $idx, (string)$st, PDO::PARAM_STR);
			}
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$applicationList = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $applicationList ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [応募者詳細取得]
 *  引数
 *   $applicationId：application_id
 */
function getApplicationById($applicationId)
{
	global $DB_CONNECT;
	try {
		$strSQL = 'SELECT * FROM applications WHERE application_id = :application_id LIMIT 1';
		$newStmt = $DB_CONNECT->prepare($strSQL);
		$newStmt->bindValue(':application_id', (int)$applicationId, PDO::PARAM_INT);
		$newStmt->execute();
		$application = $newStmt->fetch(PDO::FETCH_ASSOC);
		$newStmt->closeCursor();
		return is_array($application) ? $application : null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
