<?php
/*
 * [登録法人の最新 corporation_id 取得]
 */
function getLastCorporationId()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_workrecruit' AND TABLE_NAME = 'corporations'";
		#$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_demowork' AND TABLE_NAME = 'corporations'";
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
 * [法人一覧取得]
 */
function getCorporationList()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				corporation_id, corporation_code, contract_date, name, name_kana, postal_code, prefecture, city, address_line, phone, email, created_at 
			FROM 
				corporations 
			WHERE 
				is_active = 1 
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
 * [法人一覧検索]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function searchCorporationList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				corporation_id, corporation_code, contract_date, name, name_kana, postal_code, prefecture, city, address_line, phone, email, created_at 
			FROM 
				corporations 
			WHERE 
				is_active = 1
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchCorporationHelper($searchConditions);
		$strSQL .= $whereSql;
		#並び替え（番号/契約日：2軸 + 主キー）
		$sortTarget = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'corporation_id';
		if ($sortTarget !== 'corporation_id' && $sortTarget !== 'contract_date') {
			$sortTarget = 'corporation_id';
		}
		$idSortOrder = isset($searchConditions['idSortOrder']) ? strtolower((string)$searchConditions['idSortOrder']) : '';
		$contractDateSortOrder = isset($searchConditions['contractDateSortOrder']) ? strtolower((string)$searchConditions['contractDateSortOrder']) : '';
		if ($idSortOrder !== 'asc' && $idSortOrder !== 'desc') {
			$idSortOrder = 'desc';
		}
		if ($contractDateSortOrder !== 'asc' && $contractDateSortOrder !== 'desc') {
			$contractDateSortOrder = 'desc';
		}
		$idSortOrderSql = ($idSortOrder === 'asc') ? 'ASC' : 'DESC';
		$contractDateSortOrderSql = ($contractDateSortOrder === 'asc') ? 'ASC' : 'DESC';
		$strSQL .= " ORDER BY ";
		if ($sortTarget === 'contract_date') {
			#主キー：契約日（NULLは最後）、副キー：番号
			$strSQL .= "(contract_date IS NULL) ASC, contract_date {$contractDateSortOrderSql}, corporation_id {$idSortOrderSql}";
		} else {
			#主キー：番号、副キー：契約日（NULLは最後）
			$strSQL .= "corporation_id {$idSortOrderSql}, (contract_date IS NULL) ASC, contract_date {$contractDateSortOrderSql}";
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
 * [法人一覧検索：総件数取得]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchCorporationCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT COUNT(*) AS cnt FROM corporations WHERE is_active = 1";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchCorporationHelper($searchConditions);
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
 * [法人一覧検索用ヘルパー関数]
 *  引数
 *   $searchConditions：検索条件配列
 *  戻り値
 *   array($whereSql, $sqlParams)
 */
function searchCorporationHelper($searchConditions)
{
	$whereSql = '';
	$sqlParams = array();
	foreach ($searchConditions as $key => $value) {
		#検索条件設定
		if ($key == '' || $value == '') {
			continue;
		}
		switch ($key) {
			#法人名
			case 'companyName':
				#入力は1つなので name / name_kana の両方で検索
				$whereSql .= " AND (name LIKE :company_name_like OR name_kana LIKE :company_name_kana_like)";
				$sqlParams[':company_name_like'] = '%' . $value . '%';
				$sqlParams[':company_name_kana_like'] = '%' . $value . '%';
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
	#選択した日付内の契約日を持つ法人を抽出
	$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? $searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? $searchConditions['endDay'] : null;
	$searchKey = 'contract_date';
	if ($start !== null && $end !== null) {
		$whereSql .= " AND $searchKey BETWEEN :startDay AND :endDay";
		$sqlParams[':startDay'] = $start;
		$sqlParams[':endDay'] = $end;
	} elseif ($start !== null) {
		$whereSql .= " AND $searchKey >= :startDay";
		$sqlParams[':startDay'] = $start;
	} elseif ($end !== null) {
		$whereSql .= " AND $searchKey <= :endDay";
		$sqlParams[':endDay'] = $end;
	}
	#共通WHERE句を応答
	return array($whereSql, $sqlParams);
}
/*
 * [法人情報取得（IDまたはコード指定）]
 *  引数
 *   $corpId  ：法人ID
 *   $corpCode：法人コード
 */
function getCorporations_FindById_Code($corpId = null, $corpCode = null)
{
	global $DB_CONNECT;
	try {
		if ($corpId !== null) {
			#「$corpId」で検索
			$strSQL = "
				SELECT 
					corporation_id, corporation_code, contract_date, name, name_kana, postal_code, prefecture, city, address_line, phone, email, created_at 
				FROM 
					corporations 
				WHERE 
					corporation_id = :value LIMIT 1
			";
		} elseif ($corpCode !== null) {
			#「$corpCode」で検索
			$strSQL = "
				SELECT 
					corporation_id, corporation_code, contract_date, name, name_kana, postal_code, prefecture, city, address_line, phone, email, created_at 
				FROM 
					corporations 
				WHERE 
					corporation_code = :value LIMIT 1
			";
		} else {
			#どちらも指定されていない場合
			return null;
		}
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':value', $corpId !== null ? $corpId : $corpCode, $corpId !== null ? PDO::PARAM_INT : PDO::PARAM_STR);
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
