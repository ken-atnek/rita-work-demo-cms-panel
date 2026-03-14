<?php
/*
 * [登録事業所の最新 facility_id 取得]
 */
function getLastFacilityId()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_workrecruit' AND TABLE_NAME = 'facilities'";
		#$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_demowork' AND TABLE_NAME = 'facilities'";
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
 * [事業所一覧取得]
 *  引数
 *   $includeInactive: true の場合は is_active による絞り込みを行わない
 */
function getFacilityList($includeInactive = false)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				facility_id, facility_code, corporation_id, facility_type_id, name, name_kana, established_date, postal_code, 
				prefecture, city, address_line, recruitment_area, phone, email, map_url, map_link_url, created_at 
			FROM 
				facilities 
		";
		if ($includeInactive !== true) {
			$strSQL .= "
			WHERE 
				is_active = 1 
			";
		}
		$strSQL .= "
			ORDER BY 
				corporation_id DESC
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$corporations = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $corporations ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所一覧検索]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function searchFacilityList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				facility_id, facility_code, corporation_id, facility_type_id, name, name_kana, established_date, postal_code, 
				prefecture, city, address_line, recruitment_area, phone, email, map_url, map_link_url, created_at 
			FROM 
				facilities 
			WHERE 
				is_active = 1
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityHelper($searchConditions);
		$strSQL .= $whereSql;
		#並び順（主ソート + 副ソート）
		$sortTarget = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'facility_id';
		$idSortOrderSql = (isset($searchConditions['idSortOrder']) && strtolower((string)$searchConditions['idSortOrder']) === 'asc') ? 'ASC' : 'DESC';
		$publishedStartSortOrderSql = (isset($searchConditions['publishedStartSortOrder']) && strtolower((string)$searchConditions['publishedStartSortOrder']) === 'asc') ? 'ASC' : 'DESC';
		$allowedSortTargets = ['facility_id', 'published_start'];
		if (!in_array($sortTarget, $allowedSortTargets, true)) {
			$sortTarget = 'facility_id';
		}
		#掲載日（求人カード：jobs.published_start）ソート用：検索条件に寄せた MAX(published_start)
		$subWhere = "j.facility_id = facilities.facility_id";
		$selectedPlan = isset($searchConditions['plan']) && $searchConditions['plan'] !== '' ? (string)$searchConditions['plan'] : '';
		$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? $searchConditions['startDay'] : null;
		$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? $searchConditions['endDay'] : null;
		# NOTE: WHERE側（searchFacilityHelper）でも :contract_plan_id / :startDay / :endDay を使うため、
		# ORDER BY のサブクエリでは別名プレースホルダを使ってHY093（Invalid parameter number）を回避する。
		$sortContractPlanParam = ':sort_contract_plan_id';
		$sortStartParam = ':sortStartDay';
		$sortEndParam = ':sortEndDay';
		#契約プラン（検索時と同じ条件で寄せる）
		if ($selectedPlan === 'ended') {
			$subWhere .= " AND j.is_active = 99";
		} elseif ($selectedPlan !== '') {
			$subWhere .= " AND j.contract_plan_id = {$sortContractPlanParam} AND j.is_active <> 99";
			$sqlParams[$sortContractPlanParam] = $selectedPlan;
		}
		#掲載日
		if ($start !== null && $end !== null) {
			$subWhere .= " AND DATE(j.published_start) BETWEEN {$sortStartParam} AND {$sortEndParam}";
			$sqlParams[$sortStartParam] = $start;
			$sqlParams[$sortEndParam] = $end;
		} elseif ($start !== null) {
			$subWhere .= " AND DATE(j.published_start) >= {$sortStartParam}";
			$sqlParams[$sortStartParam] = $start;
		} elseif ($end !== null) {
			$subWhere .= " AND DATE(j.published_start) <= {$sortEndParam}";
			$sqlParams[$sortEndParam] = $end;
		}
		$publishedMaxExpr = "(SELECT MAX(j.published_start) FROM jobs j WHERE " . $subWhere . ")";
		if ($sortTarget === 'published_start') {
			$strSQL .= " ORDER BY (" . $publishedMaxExpr . " IS NULL) ASC, " . $publishedMaxExpr . " " . $publishedStartSortOrderSql . ", facility_id " . $idSortOrderSql;
		} else {
			$strSQL .= " ORDER BY facility_id " . $idSortOrderSql . ", (" . $publishedMaxExpr . " IS NULL) ASC, " . $publishedMaxExpr . " " . $publishedStartSortOrderSql;
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
		$facilities = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $facilities ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所一覧検索：総件数取得]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchFacilityCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT COUNT(*) AS cnt FROM facilities WHERE is_active = 1";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityHelper($searchConditions);
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
 * [事業所一覧検索用ヘルパー関数]
 *  引数
 *   $searchConditions：検索条件配列
 *  戻り値
 *   array($whereSql, $sqlParams)
 */
function searchFacilityHelper($searchConditions)
{
	$whereSql = '';
	$sqlParams = array();
	foreach ($searchConditions as $key => $value) {
		#検索条件設定
		if ($key == '' || $value == '') {
			continue;
		}
		switch ($key) {
			#事業所ID
			case 'facilityId':
				$searchKey = 'facility_code';
				$whereSql .= " AND " . $searchKey . " = :" . $searchKey;
				$sqlParams[':' . $searchKey] = $value;
				break;
			#事業所名
			case 'facilityName':
				#入力は1つなので name / name_kana の両方で検索
				$whereSql .= " AND (name LIKE :facility_name_like OR name_kana LIKE :facility_name_kana_like)";
				$sqlParams[':facility_name_like'] = '%' . $value . '%';
				$sqlParams[':facility_name_kana_like'] = '%' . $value . '%';
				break;
			#絞り込み
			case 'initials':
				$searchKey = 'name_kana';
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
						if (isset($gyoMap[$initial])) {
							foreach ($gyoMap[$initial] as $kana) {
								$paramKey = ':' . $searchKey . $paramIdx;
								$likeClauses[] = $searchKey . " LIKE " . $paramKey;
								$sqlParams[$paramKey] = $kana . '%';
								$paramIdx++;
							}
						}
					}
					if (count($likeClauses) > 0) {
						$whereSql .= " AND (" . implode(' OR ', $likeClauses) . ")";
					}
				}
				break;
		}
	}
	#掲載日（求人カード：jobs.published_start）で抽出
	$selectedPlan = isset($searchConditions['plan']) && $searchConditions['plan'] !== '' ? (string)$searchConditions['plan'] : '';
	$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? $searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? $searchConditions['endDay'] : null;
	if ($selectedPlan !== '' || $start !== null || $end !== null) {
		$jobWhere = 'j.facility_id = facilities.facility_id';
		#契約プラン
		#契約終了＝プラン解約：jobs.is_active = 99
		if ($selectedPlan === 'ended') {
			$jobWhere .= ' AND j.is_active = 99';
		} elseif ($selectedPlan !== '') {
			#通常プラン：解約(99)は除外して判定
			$jobWhere .= ' AND j.contract_plan_id = :contract_plan_id AND j.is_active <> 99';
			$sqlParams[':contract_plan_id'] = $selectedPlan;
		}
		#掲載日（DATEで比較して日付入力と揃える）
		if ($start !== null && $end !== null) {
			$jobWhere .= ' AND DATE(j.published_start) BETWEEN :startDay AND :endDay';
			$sqlParams[':startDay'] = $start;
			$sqlParams[':endDay'] = $end;
		} elseif ($start !== null) {
			$jobWhere .= ' AND DATE(j.published_start) >= :startDay';
			$sqlParams[':startDay'] = $start;
		} elseif ($end !== null) {
			$jobWhere .= ' AND DATE(j.published_start) <= :endDay';
			$sqlParams[':endDay'] = $end;
		}
		$whereSql .= ' AND EXISTS (SELECT 1 FROM jobs j WHERE ' . $jobWhere . ')';
	}
	#共通WHERE句を応答
	return array($whereSql, $sqlParams);
}
/*
 * [事業所情報取得]
 *  引数
 *   $facId：事業所ID
 */
function getFacility_FindById($facId)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				facility_id, facility_code, corporation_id, facility_type_id, name, name_kana, established_date, 
				postal_code, prefecture, city, address_line, recruitment_area, phone, email, map_url, map_link_url, created_at 
			FROM 
				facilities 
			WHERE 
				facility_id = :facility_id LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':facility_id', $facId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$corporation = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $corporation ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [事業所詳細情報取得]
 *  引数
 *   $facId：事業所ID
 */
function getFacilityDetails_FindById($facId)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				facility_id, department_name, contact_person, is_emergency_designated, details_json 
			FROM 
				facility_details 
			WHERE 
				facility_id = :facility_id LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':facility_id', $facId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$corporation = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $corporation ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
