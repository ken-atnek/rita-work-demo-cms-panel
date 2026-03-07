<?php
/*
 * [請求事業所一覧取得]
 */
function getFacilityInvoiceList()
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT
				fi.invoice_id, fi.facility_id, fi.billing_period, fi.cutoff_at, fi.is_special_banner, fi.amount_total,
				fi.status, fi.created_at, fi.tax_rate, fi.tax_rounding, fi.tax_amount, fi.amount_total_incl_tax,
				f.facility_code, f.name AS facility_name, f.name_kana AS facility_name_kana,
				GROUP_CONCAT(DISTINCT CASE WHEN fii.plan_id <> 'special_banner' THEN fii.plan_id END ORDER BY fii.plan_id SEPARATOR ',') AS plan_ids,
				GROUP_CONCAT(CASE WHEN fii.plan_id <> 'special_banner' THEN CONCAT(fii.plan_id, ':', fii.quantity) END ORDER BY fii.plan_id SEPARATOR ',') AS plan_items
			FROM
				facility_invoices fi
				INNER JOIN facilities f ON f.facility_id = fi.facility_id
				LEFT JOIN facility_invoice_items fii ON fii.invoice_id = fi.invoice_id
			GROUP BY
				fi.invoice_id
			ORDER BY
				fi.invoice_id DESC
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$invoices = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $invoices ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [請求事業所一覧検索]
 *  引数
 *   $searchConditions：検索条件配列
 *   $pageNumber      ：ページ番号
 *   $displayNumber   ：表示件数
 */
function searchFacilityInvoiceList($searchConditions, $pageNumber, $displayNumber)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT
				fi.invoice_id, fi.facility_id, fi.billing_period, fi.cutoff_at, fi.is_special_banner, fi.amount_total,
				fi.status, fi.created_at, fi.tax_rate, fi.tax_rounding, fi.tax_amount, fi.amount_total_incl_tax,
				f.facility_code, f.name AS facility_name, f.name_kana AS facility_name_kana,
				GROUP_CONCAT(DISTINCT CASE WHEN fii.plan_id <> 'special_banner' THEN fii.plan_id END ORDER BY fii.plan_id SEPARATOR ',') AS plan_ids,
				GROUP_CONCAT(CASE WHEN fii.plan_id <> 'special_banner' THEN CONCAT(fii.plan_id, ':', fii.quantity) END ORDER BY fii.plan_id SEPARATOR ',') AS plan_items
			FROM
				facility_invoices fi
				INNER JOIN facilities f ON f.facility_id = fi.facility_id
				LEFT JOIN facility_invoice_items fii ON fii.invoice_id = fi.invoice_id
			WHERE
				1 = 1
			";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityInvoiceHelper($searchConditions);
		$strSQL .= $whereSql;
		#集計（invoice_id単位）
		$strSQL .= " GROUP BY fi.invoice_id";
		#並び順（主ソート + 副ソート）
		$sortTarget = isset($searchConditions['sortTarget']) ? (string)$searchConditions['sortTarget'] : 'billing_period';
		$idSortOrderSql = (isset($searchConditions['idSortOrder']) && strtolower((string)$searchConditions['idSortOrder']) === 'asc') ? 'ASC' : 'DESC';
		$billingPeriodSortOrderSql = (isset($searchConditions['billingPeriodSortOrder']) && strtolower((string)$searchConditions['billingPeriodSortOrder']) === 'asc') ? 'ASC' : 'DESC';
		$allowedSortTargets = ['billing_period', 'invoice_id', 'facility_id'];
		if (!in_array($sortTarget, $allowedSortTargets, true)) {
			$sortTarget = 'billing_period';
		}
		if ($sortTarget === 'invoice_id') {
			$strSQL .= " ORDER BY fi.invoice_id " . $idSortOrderSql;
		} elseif ($sortTarget === 'facility_id') {
			$strSQL .= " ORDER BY fi.facility_id " . $idSortOrderSql . ", fi.billing_period " . $billingPeriodSortOrderSql . ", fi.invoice_id DESC";
		} else {
			// billing_period
			$strSQL .= " ORDER BY fi.billing_period " . $billingPeriodSortOrderSql . ", fi.invoice_id DESC";
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
		$invoices = $newStmt->fetchAll(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合は空配列を返却
		return $invoices ?: [];
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [請求事業所一覧検索：総件数取得]
 *  引数
 *   $searchConditions：検索条件配列
 */
function searchFacilityInvoiceCount($searchConditions)
{
	global $DB_CONNECT;
	try {
		#SQL定義（invoice_id単位で件数）
		$strSQL = "
			SELECT COUNT(DISTINCT fi.invoice_id) AS cnt
			FROM facility_invoices fi
			INNER JOIN facilities f ON f.facility_id = fi.facility_id
			WHERE 1 = 1
		";
		#WHERE句生成：ヘルパー関数呼び出し
		list($whereSql, $sqlParams) = searchFacilityInvoiceHelper($searchConditions);
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
 * [請求事業所一覧検索用ヘルパー関数]
 *  引数
 *   $searchConditions：検索条件配列
 *  戻り値
 *   array($whereSql, $sqlParams)
 */
function searchFacilityInvoiceHelper($searchConditions)
{
	$whereSql = '';
	$sqlParams = array();
	foreach ($searchConditions as $key => $value) {
		#検索条件設定
		if ($key == '' || $value == '') {
			continue;
		}
		switch ($key) {
			#請求ID
			case 'invoiceId':
				$whereSql .= " AND fi.invoice_id = :invoice_id";
				$sqlParams[':invoice_id'] = $value;
				break;
			#事業所ID
			case 'facilityId':
				$whereSql .= " AND fi.facility_id = :facility_id";
				$sqlParams[':facility_id'] = $value;
				break;
			#事業所名
			case 'facilityName':
				#入力は1つなので name / name_kana の両方で検索
				$whereSql .= " AND (f.name LIKE :facility_name_like OR f.name_kana LIKE :facility_name_kana_like)";
				$sqlParams[':facility_name_like'] = '%' . $value . '%';
				$sqlParams[':facility_name_kana_like'] = '%' . $value . '%';
				break;
			#絞り込み
			case 'initials':
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
						if (isset($gyoMap[$initial])) {
							foreach ($gyoMap[$initial] as $kana) {
								$paramKey = ':name_kana_' . $paramIdx;
								$likeClauses[] = $searchColumn . " LIKE " . $paramKey;
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
	#請求月（billing_period）で抽出
	$start = isset($searchConditions['startDay']) && $searchConditions['startDay'] !== '' ? (string)$searchConditions['startDay'] : null;
	$end = isset($searchConditions['endDay']) && $searchConditions['endDay'] !== '' ? (string)$searchConditions['endDay'] : null;
	list($start, $end) = normalizeBillingPeriodDateRange($start, $end);
	if ($start !== null && $end !== null) {
		$whereSql .= ' AND DATE(fi.billing_period) BETWEEN :startDay AND :endDay';
		$sqlParams[':startDay'] = $start;
		$sqlParams[':endDay'] = $end;
	} elseif ($start !== null) {
		$whereSql .= ' AND DATE(fi.billing_period) >= :startDay';
		$sqlParams[':startDay'] = $start;
	} elseif ($end !== null) {
		$whereSql .= ' AND DATE(fi.billing_period) <= :endDay';
		$sqlParams[':endDay'] = $end;
	}
	#共通WHERE句を応答
	return array($whereSql, $sqlParams);
}

/*
 * 請求月検索の入力正規化
 * - input[type=month] は YYYY-MM を返すため、月初/月末のYYYY-MM-DDへ変換
 * - input[type=date] の YYYY-MM-DD はそのまま
 */
function normalizeBillingPeriodDateRange($start, $end)
{
	$tz = new DateTimeZone('Asia/Tokyo');
	$startNorm = $start;
	$endNorm = $end;

	// YYYY-MM → 月初
	if (is_string($startNorm) && preg_match('/^\d{4}-\d{2}$/', $startNorm)) {
		try {
			$dt = new DateTimeImmutable($startNorm . '-01', $tz);
			$startNorm = $dt->format('Y-m-d');
		} catch (Throwable $e) {
			$startNorm = null;
		}
	}
	// YYYY-MM → 月末
	if (is_string($endNorm) && preg_match('/^\d{4}-\d{2}$/', $endNorm)) {
		try {
			$dt = new DateTimeImmutable($endNorm . '-01', $tz);
			$endNorm = $dt->modify('last day of this month')->format('Y-m-d');
		} catch (Throwable $e) {
			$endNorm = null;
		}
	}

	// 空文字はnullへ
	if ($startNorm === '') {
		$startNorm = null;
	}
	if ($endNorm === '') {
		$endNorm = null;
	}

	return array($startNorm, $endNorm);
}
