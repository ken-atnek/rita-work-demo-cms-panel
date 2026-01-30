<?php
/*
 * [登録求人カードの最新 job_id 取得]
 */
function getLastJobId()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		#$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_workRecruit' AND TABLE_NAME = 'jobs'";
		$strSQL = "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'rita5258_demowork' AND TABLE_NAME = 'jobs'";
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
 * [求人カード一覧取得]
 *  引数
 *   $facId：事業所ID
 */
function getJobList($facId = null)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_id, job_code, facility_id, job_category_id, employment_type_id, first_year_income_range_id, card_title, published_start, published_end, contract_plan_id, salary_unit_id, salary_min, salary_max, salary_range, bonus_has_bonus, bonus_note, hero_image_primary, is_active, created_at, updated_at FROM jobs";
		if ($facId !== null) {
			$strSQL .= " WHERE facility_id = :fac_id";
		}
		$strSQL .= " ORDER BY job_id DESC";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		if ($facId !== null) {
			$newStmt->bindValue(':fac_id', $facId, PDO::PARAM_INT);
		}
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
 * [求人カード情報取得]
 *  引数
 *   $jobCode：求人カードコード
 */
function getJob_FindByCode($jobCode)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_id, job_code, facility_id, job_category_id, employment_type_id, first_year_income_range_id, card_title, published_start, published_end, contract_plan_id, salary_unit_id, salary_min, salary_max, salary_range,bonus_has_bonus, bonus_note, hero_image_primary, is_active FROM jobs WHERE job_code = :job_code LIMIT 1";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':job_code', $jobCode, PDO::PARAM_STR);
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
 * [求人カード情報取得]
 *  引数
 *   $jobId：求人カードID
 */
function getJob_FindById($jobId)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_id, job_code, facility_id, job_category_id, employment_type_id, first_year_income_range_id, card_title, published_start, published_end, contract_plan_id, salary_unit_id, salary_min, salary_max, salary_range,bonus_has_bonus, bonus_note, hero_image_primary, is_active FROM jobs WHERE job_id = :job_id LIMIT 1";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
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
 * [求人カード記事取得]
 *  引数
 *   $jobId  ：求人カードID
 *   $docType：ドキュメントタイプ
 */
function getJobCardArticle_FindByJobId($jobId, $docType = null)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_document_id, job_id, doc_type, enabled, content_json FROM job_documents WHERE job_id = :job_id";
		if ($docType !== null) {
			$strSQL .= " AND doc_type = :document_type";
		}
		$strSQL .= " LIMIT 1";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
		if ($docType !== null) {
			$newStmt->bindValue(':document_type', $docType, PDO::PARAM_STR);
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$article = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $article ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [職場環境の特徴を取得]
 *  引数
 *   $jobId：求人カードID
 */
function getJobWorkEnvironmentMetrics_FindByJobId($jobId)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_metric_value_id, job_id, metric_id, value FROM job_metric_values WHERE job_id = :job_id";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$metrics = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $metrics ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [仕事の詳細情報取得]
 *  引数
 *   $jobId       ：求人カードID
 *   $optGroupCode：オプショングループコード
 */
function getJobOptionGroup_FindByJobId($jobId, $optGroupCode = null)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_option_link_id, job_id, option_group_code, option_id FROM job_option_links WHERE job_id = :job_id";
		if ($optGroupCode !== null) {
			$strSQL .= " AND option_group_code = :option_group_code";
		}
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
		if ($optGroupCode !== null) {
			$newStmt->bindValue(':option_group_code', $optGroupCode, PDO::PARAM_STR);
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$article = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はarray()を返却して呼び出し側で判定
		return $article ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [仕事の詳細テキスト情報取得]
 *  引数
 *   $jobId       ：求人カードID
 *   $optGroupCode：オプショングループコード
 */
function getJobOptionText_FindByJobId($jobId, $optGroupCode = null)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "SELECT job_option_link_extra_id, job_id, option_group_code, option_text FROM job_option_link_extras WHERE job_id = :job_id";
		if ($optGroupCode !== null) {
			$strSQL .= " AND option_group_code = :option_group_code";
		}
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
		if ($optGroupCode !== null) {
			$newStmt->bindValue(':option_group_code', $optGroupCode, PDO::PARAM_STR);
		}
		#SQL実行
		$newStmt->execute();
		#実行結果取得：グループコード無しは複数レコード／有りは単一レコードで返却
		if ($optGroupCode !== null) {
			$article = $newStmt->fetch(PDO::FETCH_ASSOC);
		} else {
			$article = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		}
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はarray()を返却して呼び出し側で判定
		return $article ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
