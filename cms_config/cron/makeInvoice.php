<?php
/*
 * [cms_config/common/workJson/makeIndexAll.php]
 *  - 管理画面 -
 *  求人カード登録／編集／削除後のJSONファイル作成 (全求人カード情報)
 *
 * [初版]
 *  2026.1.9
 */

#===========================================#
# 基本設定
#-------------------------------------------#
require(__DIR__ . '/../common/define.php');
require(__DIR__ . '/../common/set_function.php');
require(__DIR__ . '/../common/set_contents.php');
require(__DIR__ . '/../database/set_db.php');
require(__DIR__ . '/../database/db_corporations.php');
require(__DIR__ . '/../database/db_facilities.php');
require(__DIR__ . '/../database/db_jobs.php');
#-------------------------------------------#
#===========================================#
# タイムゾーン（仕様：JST）
#-------------------------------------------#
date_default_timezone_set('Asia/Tokyo');

/**
 * 税率文字列（DECIMAL(5,4)想定）を 10000 スケールの整数に変換する
 * 例: "0.1000" -> 1000
 */
function taxRateToScaledInt(string $taxRate, int $scale = 10000): int
{
	$taxRate = trim($taxRate);
	if ($taxRate === '') {
		throw new InvalidArgumentException('tax_rate is empty');
	}
	if (!preg_match('/\A\d+(?:\.\d{1,4})?\z/', $taxRate)) {
		throw new InvalidArgumentException('tax_rate format invalid: ' . $taxRate);
	}
	$parts = explode('.', $taxRate, 2);
	$intPart = (int)$parts[0];
	$fracPart = $parts[1] ?? '';
	$fracPart = substr($fracPart, 0, 4);
	$fracPart = str_pad($fracPart, 4, '0');
	$frac = (int)$fracPart;
	return $intPart * $scale + $frac;
}
/**
 * 税額（整数円）を計算する（明細単位で適用）
 * - amount: 税別金額（整数）
 * - taxRateScaled: 税率を10000スケール整数化した値
 * - rounding: floor|round|ceil
 */
function calcTaxAmount(int $amount, int $taxRateScaled, string $rounding, int $scale = 10000): int
{
	if ($amount <= 0) {
		return 0;
	}
	if ($taxRateScaled <= 0) {
		return 0;
	}
	$numerator = $amount * $taxRateScaled;
	if ($numerator <= 0) {
		return 0;
	}
	switch ($rounding) {
		case 'ceil':
			return intdiv($numerator + ($scale - 1), $scale);
		case 'round':
			return intdiv($numerator + intdiv($scale, 2), $scale);
		case 'floor':
		default:
			return intdiv($numerator, $scale);
	}
}
/**
 * 求人カードが当月請求の対象か判定する
 * 仕様（確定）:
 * - published_start を過ぎたら状態に関わらず契約中
 * - 解約日は published_end（再掲載でnull）
 * - 解約月まで請求（published_end が当月1日以降なら当月分に含める）
 */
function isBillableJobForPeriod(array $job, DateTimeImmutable $periodStart, DateTimeImmutable $cutoffAt): bool
{
	$publishedStartRaw = $job['published_start'] ?? null;
	if (!is_string($publishedStartRaw) || trim($publishedStartRaw) === '') {
		return false;
	}
	$publishedStartTs = strtotime($publishedStartRaw);
	if ($publishedStartTs === false) {
		return false;
	}
	if ($publishedStartTs > $cutoffAt->getTimestamp()) {
		return false;
	}
	$publishedEndRaw = $job['published_end'] ?? null;
	if (is_string($publishedEndRaw) && trim($publishedEndRaw) !== '') {
		$publishedEndTs = strtotime($publishedEndRaw);
		if ($publishedEndTs === false) {
			return false;
		}
		#published_end が当月開始より前なら当月分は請求しない
		if ($publishedEndTs < $periodStart->getTimestamp()) {
			return false;
		}
	}
	return true;
}
/**
 * 契約プラン単価（税別）を解決する
 * 方針（確定）: set_contents.php の固定値のみを使用する
 */
function resolvePlanUnitPrice(string $planId, array $fallbackPlanPrices): int
{
	$planId = trim($planId);
	if ($planId === '') {
		throw new InvalidArgumentException('planId is empty');
	}
	if (isset($fallbackPlanPrices[$planId]) && is_numeric($fallbackPlanPrices[$planId])) {
		$price = (int)$fallbackPlanPrices[$planId];
		if ($price > 0) {
			return $price;
		}
	}
	throw new RuntimeException('unit_price not found for plan_id: ' . $planId);
}
/**
 * 請求ヘッダ（facility_invoices）を取得する
 */
function findFacilityInvoice(string $facilityId, string $billingPeriod): ?array
{
	global $DB_CONNECT;
	$stmt = $DB_CONNECT->prepare('SELECT invoice_id, status FROM facility_invoices WHERE facility_id = :facility_id AND billing_period = :billing_period LIMIT 1');
	$stmt->bindValue(':facility_id', (int)$facilityId, PDO::PARAM_INT);
	$stmt->bindValue(':billing_period', $billingPeriod, PDO::PARAM_STR);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$stmt->closeCursor();
	return $row ?: null;
}
/**
 * 請求ヘッダをUPSERTする（statusは既存があれば維持）
 */
function upsertFacilityInvoiceHeader(array $row): void
{
	global $DB_CONNECT;
	$sql = "
		INSERT INTO facility_invoices (
			facility_id, billing_period, cutoff_at,
			is_special_banner,
			amount_total,
			tax_rate, tax_rounding, tax_amount, amount_total_incl_tax,
			status
		) VALUES (
			:facility_id, :billing_period, :cutoff_at,
			:is_special_banner,
			:amount_total,
			:tax_rate, :tax_rounding, :tax_amount, :amount_total_incl_tax,
			'final'
		)
		ON DUPLICATE KEY UPDATE
			cutoff_at = VALUES(cutoff_at),
			is_special_banner = VALUES(is_special_banner),
			amount_total = VALUES(amount_total),
			tax_rate = VALUES(tax_rate),
			tax_rounding = VALUES(tax_rounding),
			tax_amount = VALUES(tax_amount),
			amount_total_incl_tax = VALUES(amount_total_incl_tax),
			updated_at = CURRENT_TIMESTAMP
	";
	$stmt = $DB_CONNECT->prepare($sql);
	$stmt->bindValue(':facility_id', (int)$row['facility_id'], PDO::PARAM_INT);
	$stmt->bindValue(':billing_period', (string)$row['billing_period'], PDO::PARAM_STR);
	$stmt->bindValue(':cutoff_at', (string)$row['cutoff_at'], PDO::PARAM_STR);
	$stmt->bindValue(':is_special_banner', (int)$row['is_special_banner'], PDO::PARAM_INT);
	$stmt->bindValue(':amount_total', (int)$row['amount_total'], PDO::PARAM_INT);
	$stmt->bindValue(':tax_rate', (string)$row['tax_rate'], PDO::PARAM_STR);
	$stmt->bindValue(':tax_rounding', (string)$row['tax_rounding'], PDO::PARAM_STR);
	$stmt->bindValue(':tax_amount', (int)$row['tax_amount'], PDO::PARAM_INT);
	$stmt->bindValue(':amount_total_incl_tax', (int)$row['amount_total_incl_tax'], PDO::PARAM_INT);
	$stmt->execute();
	$stmt->closeCursor();
}
/**
 * 請求明細を入れ替える（全削除→INSERT）
 */
function replaceFacilityInvoiceItems(int $invoiceId, array $items): void
{
	global $DB_CONNECT;
	$del = $DB_CONNECT->prepare('DELETE FROM facility_invoice_items WHERE invoice_id = :invoice_id');
	$del->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
	$del->execute();
	$del->closeCursor();
	$ins = $DB_CONNECT->prepare(
		'INSERT INTO facility_invoice_items (invoice_id, plan_id, quantity, unit_price, amount, meta_json) VALUES (:invoice_id, :plan_id, :quantity, :unit_price, :amount, :meta_json)'
	);
	foreach ($items as $item) {
		$ins->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
		$ins->bindValue(':plan_id', (string)$item['plan_id'], PDO::PARAM_STR);
		$ins->bindValue(':quantity', (int)$item['quantity'], PDO::PARAM_INT);
		$ins->bindValue(':unit_price', (int)$item['unit_price'], PDO::PARAM_INT);
		$ins->bindValue(':amount', (int)$item['amount'], PDO::PARAM_INT);
		$meta = $item['meta_json'] ?? null;
		if ($meta === null || $meta === '') {
			$ins->bindValue(':meta_json', null, PDO::PARAM_NULL);
		} else {
			$ins->bindValue(':meta_json', (string)$meta, PDO::PARAM_STR);
		}
		$ins->execute();
	}
	$ins->closeCursor();
}
#===========================================#
#事業所一覧取得
$facilityList = getFacilityList();
#事業所情報が無ければ処理終了
if ($facilityList === null) {
	#事業所情報無し：処理終了
	exit;
}
#-------------------------------------------#
#===========================================#
# 請求生成の基準時刻（cutoff_at = cron実行時刻）
#-------------------------------------------#
$cutoffAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
$billingPeriod = $cutoffAt->format('Y-m-01');
$periodStart = $cutoffAt
	->setDate((int)$cutoffAt->format('Y'), (int)$cutoffAt->format('m'), 1)
	->setTime(0, 0, 0);

#税（方針: set_contents.php の $taxRate を採用）
$invoiceTaxRateNum = (isset($taxRate) && is_numeric($taxRate)) ? (float)$taxRate : 0.1;
$invoiceTaxRateStr = number_format($invoiceTaxRateNum, 4, '.', '');
$invoiceTaxRounding = 'floor';
#$taxRounding: 1=切り捨て, 2=切り上げ, 3=四捨五入（set_contents.php）
if (isset($taxRounding)) {
	if (is_numeric($taxRounding)) {
		switch ((int)$taxRounding) {
			case 2:
				$invoiceTaxRounding = 'ceil';
				break;
			case 3:
				$invoiceTaxRounding = 'round';
				break;
			case 1:
			default:
				$invoiceTaxRounding = 'floor';
				break;
		}
	}
}
$taxRateScaled = taxRateToScaledInt($invoiceTaxRateStr);

#-------------------------------------------#
# フロント側マスタ定義JSONファイル取得（契約プラン）
#-------------------------------------------#
$contractPlans = [];
try {
	$contractPlans = getJson_FrontEndMaster('contractPlans');
} catch (Throwable $e) {
	#cron用途のため、標準エラーログへ
	error_log('[makeInvoice] contractPlans master load failed: ' . $e->getMessage());
	$contractPlans = [];
}
$contractPlanById = [];
if (is_array($contractPlans)) {
	foreach ($contractPlans as $p) {
		if (!is_array($p) || !isset($p['id'])) {
			continue;
		}
		$contractPlanById[(string)$p['id']] = $p;
	}
}
# 既定価格（set_contents.php）
$fallbackPlanPrices = [];
if (isset($premiumPlanPrice) && is_numeric($premiumPlanPrice)) {
	$fallbackPlanPrices['premium'] = (int)$premiumPlanPrice;
}
if (isset($standardPlanPrice) && is_numeric($standardPlanPrice)) {
	$fallbackPlanPrices['standard'] = (int)$standardPlanPrice;
}
if (isset($lightPlanPrice) && is_numeric($lightPlanPrice)) {
	$fallbackPlanPrices['light'] = (int)$lightPlanPrice;
}
$specialBannerUnitPrice = (isset($specialBannerPrice) && is_numeric($specialBannerPrice)) ? (int)$specialBannerPrice : 0;
#===========================================#
#事業所情報展開
if (is_array($facilityList) && count($facilityList) > 0) {
	foreach ($facilityList as $facility) {
		$facId = (int)($facility['facility_id'] ?? 0);
		if ($facId <= 0) {
			continue;
		}
		#詳細情報も取得
		$facilityDetails = getFacilityDetails_FindById($facId);
		#テーブル内JSONデコード（安全化）
		$facilityDetailsJson = [];
		if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
			$facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
			if (!is_array($facilityDetailsJson)) {
				$facilityDetailsJson = [];
			}
		}
		#バナー契約情報（締め時点）
		$isSpecialBanner = 0;
		if (isset($facilityDetailsJson['specialBanner']['enabled']) && $facilityDetailsJson['specialBanner']['enabled'] == true) {
			$isSpecialBanner = 1;
		}
		#求人カード情報
		$jobCardList = getJobList($facId);
		#当月請求対象の求人のみ抽出し、plan_id別に集計
		$planAgg = [];
		#plan_id => ['quantity'=>int, 'job_ids'=>[], 'job_codes'=>[]]
		if (is_array($jobCardList) && count($jobCardList) > 0) {
			foreach ($jobCardList as $jobCard) {
				if (!is_array($jobCard)) {
					continue;
				}
				if (!isBillableJobForPeriod($jobCard, $periodStart, $cutoffAt)) {
					continue;
				}
				$planId = isset($jobCard['contract_plan_id']) ? (string)$jobCard['contract_plan_id'] : '';
				$planId = trim($planId);
				if ($planId === '') {
					continue;
				}
				if (!isset($planAgg[$planId])) {
					$planAgg[$planId] = [
						'quantity' => 0,
						'job_ids' => [],
						'job_codes' => [],
					];
				}
				$planAgg[$planId]['quantity']++;
				if (isset($jobCard['job_id'])) {
					$planAgg[$planId]['job_ids'][] = (int)$jobCard['job_id'];
				}
				if (isset($jobCard['job_code'])) {
					$planAgg[$planId]['job_codes'][] = (string)$jobCard['job_code'];
				}
			}
		}
		#施設が当月に契約ゼロ（通常プランも特別バナーも無し）ならスキップ
		if (count($planAgg) === 0 && $isSpecialBanner !== 1) {
			continue;
		}
		#明細生成
		$items = [];
		$amountTotalExTax = 0;
		$taxAmountTotal = 0;
		foreach ($planAgg as $planId => $agg) {
			$quantity = (int)($agg['quantity'] ?? 0);
			if ($quantity <= 0) {
				continue;
			}
			try {
				$unitPrice = resolvePlanUnitPrice($planId, $fallbackPlanPrices);
			} catch (Throwable $e) {
				error_log('[makeInvoice] unit price resolve failed: facility_id=' . $facId . ' plan_id=' . $planId . ' error=' . $e->getMessage());
				continue;
			}
			$amount = $unitPrice * $quantity;
			$meta = [
				'planId' => $planId,
				'planName' => (string)($contractPlanById[$planId]['name'] ?? ''),
				'jobIds' => $agg['job_ids'] ?? [],
				'jobCodes' => $agg['job_codes'] ?? [],
			];
			$metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			$items[] = [
				'plan_id' => $planId,
				'quantity' => $quantity,
				'unit_price' => $unitPrice,
				'amount' => $amount,
				'meta_json' => $metaJson,
			];
			$amountTotalExTax += $amount;
			$taxAmountTotal += calcTaxAmount($amount, $taxRateScaled, $invoiceTaxRounding);
		}
		#特別バナー明細
		if ($isSpecialBanner === 1) {
			if ($specialBannerUnitPrice <= 0) {
				error_log('[makeInvoice] special_banner unit price is not configured. facility_id=' . $facId);
			} else {
				$amount = $specialBannerUnitPrice;
				$items[] = [
					'plan_id' => 'special_banner',
					'quantity' => 1,
					'unit_price' => $specialBannerUnitPrice,
					'amount' => $amount,
					'meta_json' => json_encode(['type' => 'special_banner'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				];
				$amountTotalExTax += $amount;
				$taxAmountTotal += calcTaxAmount($amount, $taxRateScaled, $invoiceTaxRounding);
			}
		}
		#明細が1件も作れなかった場合はスキップ
		if (count($items) === 0) {
			continue;
		}
		$amountTotalInclTax = $amountTotalExTax + $taxAmountTotal;
		#DB保存（トランザクション）
		try {
			DB_Transaction(1);
			$existing = findFacilityInvoice((string)$facId, $billingPeriod);
			if (is_array($existing) && isset($existing['status'])) {
				$status = (string)$existing['status'];
				if (in_array($status, ['paid', 'void'], true)) {
					DB_Transaction(2);
					continue;
				}
			}
			upsertFacilityInvoiceHeader([
				'facility_id' => $facId,
				'billing_period' => $billingPeriod,
				'cutoff_at' => $cutoffAt->format('Y-m-d H:i:s'),
				'is_special_banner' => $isSpecialBanner,
				'amount_total' => $amountTotalExTax,
				'tax_rate' => $invoiceTaxRateStr,
				'tax_rounding' => $invoiceTaxRounding,
				'tax_amount' => $taxAmountTotal,
				'amount_total_incl_tax' => $amountTotalInclTax,
			]);
			$header = findFacilityInvoice((string)$facId, $billingPeriod);
			if (!is_array($header) || !isset($header['invoice_id'])) {
				throw new RuntimeException('invoice header not found after upsert. facility_id=' . $facId . ' period=' . $billingPeriod);
			}
			$invoiceId = (int)$header['invoice_id'];
			if ($invoiceId <= 0) {
				throw new RuntimeException('invoice_id invalid after upsert. facility_id=' . $facId . ' period=' . $billingPeriod);
			}
			replaceFacilityInvoiceItems($invoiceId, $items);
			DB_Transaction(2);
		} catch (Throwable $e) {
			DB_Transaction(3);
			error_log('[makeInvoice] invoice save failed: facility_id=' . $facId . ' period=' . $billingPeriod . ' error=' . $e->getMessage());
			continue;
		}
	}
}
#===========================================#
