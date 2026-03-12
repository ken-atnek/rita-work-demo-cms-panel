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
require(__DIR__ . '/../../common/define.php');
require(__DIR__ . '/../../common/set_function.php');
require(__DIR__ . '/../../database/set_db.php');
require(__DIR__ . '/../../database/db_corporations.php');
require(__DIR__ . '/../../database/db_facilities.php');
require(__DIR__ . '/../../database/db_jobs.php');
#-------------------------------------------#
#===========================================#
#POSTチェック
#
$facId = $argv[1];
#デバッグ用
#$facId = '1';
#-------------------------------------------#
#求人カード情報を取得
$jobsCardData = getJobList();
#求人カード情報が無ければ処理終了
if ($jobsCardData === null) {
	#求人カード情報無し：処理終了
	exit;
}
#-------------------------------------------#
#json保存先
#indexAll.json
$saveIndexAllDir = DEFINE_JSON_DIR_PATH . '/jobs';
#details_list.json
$saveDetailsListDir = DEFINE_JSON_DIR_PATH;
#pickUp.json
$savePickUpDir = DEFINE_JSON_DIR_PATH;
#conditions
$saveConditionsDir = DEFINE_JSON_DIR_PATH . '/jobs/conditions';
#-------------------------------------------#
#===========================================#
#書き込み用ディレクトリが無い場合はベースディレクトリ作成
#indexAll.json
if (!is_dir($saveIndexAllDir)) {
	@mkdir($saveIndexAllDir, 0777, true);
}
#書き込み用jsonファイルが無い場合はベースファイルを作成
$jobsIndexAllJson = 'jobsIndexAll.json';
if (!file_exists($saveIndexAllDir . '/' . $jobsIndexAllJson)) {
	makeJson($saveIndexAllDir, $jobsIndexAllJson);
}
#-------------------------------------------#
#details_list.json
if (!is_dir($saveDetailsListDir)) {
	@mkdir($saveDetailsListDir, 0777, true);
}
#書き込み用jsonファイルが無い場合はベースファイルを作成
$detailsListJson = 'details_list.json';
if (!file_exists($saveDetailsListDir . '/' . $detailsListJson)) {
	makeJson($saveDetailsListDir, $detailsListJson);
}
#-------------------------------------------#
#pickUp.json
if (!is_dir($savePickUpDir)) {
	@mkdir($savePickUpDir, 0777, true);
}
#書き込み用jsonファイルが無い場合はベースファイルを作成
$pickUpJson = 'pickUp.json';
if (!file_exists($savePickUpDir . '/' . $pickUpJson)) {
	makeJson($savePickUpDir, $pickUpJson);
}
#-------------------------------------------#
#conditions
if (!is_dir($saveConditionsDir)) {
	@mkdir($saveConditionsDir, 0777, true);
}
#書き込み用jsonファイルが無い場合はベースファイルを作成
$conditions01Json = 'conditions01.json';
if (!file_exists($saveConditionsDir . '/' . $conditions01Json)) {
	makeJson($saveConditionsDir, $conditions01Json);
}
$conditions02Json = 'conditions02.json';
if (!file_exists($saveConditionsDir . '/' . $conditions02Json)) {
	makeJson($saveConditionsDir, $conditions02Json);
}
$conditions03Json = 'conditions03.json';
if (!file_exists($saveConditionsDir . '/' . $conditions03Json)) {
	makeJson($saveConditionsDir, $conditions03Json);
}
$conditions04Json = 'conditions04.json';
if (!file_exists($saveConditionsDir . '/' . $conditions04Json)) {
	makeJson($saveConditionsDir, $conditions04Json);
}
$conditions05Json = 'conditions05.json';
if (!file_exists($saveConditionsDir . '/' . $conditions05Json)) {
	makeJson($saveConditionsDir, $conditions05Json);
}
$conditions06Json = 'conditions06.json';
if (!file_exists($saveConditionsDir . '/' . $conditions06Json)) {
	makeJson($saveConditionsDir, $conditions06Json);
}
#===========================================#
#-------------------------------------------#
#フロント側マスタ定義JSONファイル取得
#募集職種マスタ
$jobCategoriesJson = file_get_contents(DEFINE_JSON_DIR_PATH . '/master/jobCategories.json');
#$jobCategoriesJson = file_get_contents(__DIR__ . '/../../../2604/public/db/master/jobCategories.json');
$jobCategories = json_decode($jobCategoriesJson, true);
#時給リストマスタ
$salaryBandsHourlyJson = file_get_contents(DEFINE_JSON_DIR_PATH . '/master/salaryBandsHourly.json');
#$salaryBandsHourlyJson = file_get_contents(__DIR__ . '/../../../2604/public/db/master/salaryBandsHourly.json');
$salaryBandsHourly = json_decode($salaryBandsHourlyJson, true);
#===========================================#
#求人カード情報展開
#indexAll.json
if ((is_array($jobsCardData) && count($jobsCardData) > 0) && (is_array($jobsCardData) && count($jobsCardData) > 0)) {
	#jsonデータ生成
	$jobIndexList = [];
	$writeData = [];
	foreach ($jobsCardData as $jobCard) {
		#公開中のみ処理
		if ($jobCard['is_active'] != 2) {
			continue;
		}
		#事業所情報取得
		$facilityData = getFacility_FindById($jobCard['facility_id']);
		#法人情報取得
		$corporationData = getCorporations_FindById_Code($facilityData['corporation_id'], null);
		#給与情報生成
		$salaryJson = [];
		if ($jobCard['salary_unit_id'] == 'monthly') {
			$salaryJson = [
				'unitId' => 'monthly',
				'min' => $jobCard['salary_min'],
				'max' => $jobCard['salary_max'],
			];
		} elseif ($jobCard['salary_unit_id'] == 'hourly') {
			#時給：マスタ内の最大・最小値と入力値を比較してレンジ内情報を全て取得
			$salaryJson = [
				'unitId' => 'hourly',
				'min' => $jobCard['salary_min'],
				'max' => $jobCard['salary_max'],
				'bandId' => $jobCard['salary_range'] ? json_decode($jobCard['salary_range'], true) : [],
			];
		} else {
			$salaryJson = [];
		}
		#勤務先住所
		$locationAddress = $facilityData['prefecture'] . $facilityData['city'] . $facilityData['address_line'];
		#jsonデータ生成
		$writeData = [
			'jobId' => $jobCard['job_code'],
			'facilityId' => $facilityData['facility_code'],
			'title' => $jobCard['card_title'],
			'facilityName' => $facilityData['name'],
			'jobCategoryId' => $jobCard['job_category_id'],
			'employmentTypeId' => $jobCard['employment_type_id'],
			'salary' => $salaryJson,
			'workLocationText' => $locationAddress,
			'areaIds' => [$facilityData['recruitment_area'] ? $facilityData['recruitment_area'] : []],
			'heroImages' => $jobCard['hero_image_primary'] ? json_decode($jobCard['hero_image_primary'], true) : [],
			'publishedPeriod' => [
				'start' => $jobCard['published_start'],
				'end' => null,
			],
			'updatedAt' => date('Y/m/d', strtotime($jobCard['updated_at'] ?? $jobCard['created_at'])),
			'contractPlanId' => $jobCard['contract_plan_id'],
		];
		#初年度年収レンジID
		if ($jobCard['first_year_income_range_id'] !== null) {
			$writeData['firstYearIncomeRangeId'] = $jobCard['first_year_income_range_id'];
		}
		$jobIndexList['items'][] = $writeData;
	}
	#JSONエンコード
	$indexAll_json = json_encode($jobIndexList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($saveIndexAllDir . '/' . $jobsIndexAllJson, "w");
	fwrite($write_json, $indexAll_json);
	fclose($write_json);
} else {
	#表示可能リスト無し：空ファイル作成
	makeJson($saveIndexAllDir, $jobsIndexAllJson);
}
#-------------------------------------------#
#details_list.json
if ((is_array($jobsCardData) && count($jobsCardData) > 0) && (is_array($jobsCardData) && count($jobsCardData) > 0)) {
	#jsonデータ生成
	$detailsList = [];
	$writeData = [];
	foreach ($jobsCardData as $jobCard) {
		#公開中のみ処理
		#if ($jobCard['is_active'] != 2) {
		#	continue;
		#}
		#事業所情報取得
		$facilityData = getFacility_FindById($jobCard['facility_id']);
		#基本情報jsonファイルまでのパス
		$detailJsonPath = '/db/facilities/' . $facilityData['facility_code'] . '/jobs/' . $jobCard['job_code'] . '.json';
		#jsonデータ生成
		$writeData = [
			'jobId' => $jobCard['job_code'],
			'facilityId' => $facilityData['facility_code'],
			'path' => $detailJsonPath,
		];
		$detailsList[] = $writeData;
	}
	#JSONエンコード
	$details_json = json_encode($detailsList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($saveDetailsListDir . '/' . $detailsListJson, "w");
	fwrite($write_json, $details_json);
	fclose($write_json);
} else {
	#表示可能リスト無し：空ファイル作成
	makeJson($saveDetailsListDir, $detailsListJson);
}
#-------------------------------------------#
#pickUp.json
$pickUpList = [];
#事業所情報取得
$facilityList = getFacilityList();
if (is_array($facilityList) && count($facilityList) > 0) {
	foreach ($facilityList as $facility) {
		$facId = $facility['facility_id'];
		$facCode = convertData($facility['facility_code']);
		$facName = convertData($facility['name']);
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
		#特別バナープラン契約中の事業所のみピックアップ
		if (isset($facilityDetailsJson['specialBanner']['enabled']) && $facilityDetailsJson['specialBanner']['enabled'] == true) {
			#求人カード情報取得
			$jobCardList = getJobList($facId);
			#有効な求人カードのみから、募集職種(type)と最新更新日(updatedAt)を作る
			$jobCategoryTypes = [];
			$maxUpdatedAtTimestamp = null;
			$jobTypeMap = [
				'nurse' => 1,
				'care' => 2,
				'pt' => 3,
				'ot' => 4,
				'st' => 5,
			];
			if (isset($jobCardList) && is_array($jobCardList) && count($jobCardList) > 0) {
				foreach ($jobCardList as $jobCard) {
					#公開中じゃなければスキップ
					if ($jobCard['is_active'] != 2) {
						continue;
					}
					#求人カードの最終更新日（施設内で最新のものを採用）
					$cardUpdatedAtTimestamp = null;
					if (!empty($jobCard['updated_at'])) {
						$cardUpdatedAtTimestamp = strtotime($jobCard['updated_at']);
					} elseif (!empty($jobCard['created_at'])) {
						$cardUpdatedAtTimestamp = strtotime($jobCard['created_at']);
					}
					if ($cardUpdatedAtTimestamp !== null && ($maxUpdatedAtTimestamp === null || $cardUpdatedAtTimestamp > $maxUpdatedAtTimestamp)) {
						$maxUpdatedAtTimestamp = $cardUpdatedAtTimestamp;
					}
					#募集職種（重複はまとめる）
					$jobCategoryId = $jobCard['job_category_id'] ?? null;
					if ($jobCategoryId !== null && isset($jobTypeMap[$jobCategoryId])) {
						$typeValue = $jobTypeMap[$jobCategoryId];
						if (!in_array($typeValue, $jobCategoryTypes, true)) {
							$jobCategoryTypes[] = $typeValue;
						}
					}
				}
			}
			#有効な求人が1件も無ければピックアップ対象外
			if ($maxUpdatedAtTimestamp === null || count($jobCategoryTypes) === 0) {
				continue;
			}
			sort($jobCategoryTypes);
			#IDゼロ埋め（ピックアップ連番）
			$listID = sprintf("%03d", $facId);
			#スペシャルバナー画像パス成形
			$logoImagePath = $facilityDetailsJson['specialBanner']['logoImagePath'] ?? '';
			if (is_array($logoImagePath) && count($logoImagePath) > 0) {
				$logoImagePath = $logoImagePath[0] ?? '';
			}
			#ピックアップ用リストに追加
			$pickUpList[] = [
				'id' => $listID,
				'shopName' => $facName,
				'type' => $jobCategoryTypes,
				'image' => $logoImagePath,
				'updatedAt' => date('Y-m-d', $maxUpdatedAtTimestamp),
			];
		}
	}
	#JSONエンコード
	$pickUp_json = json_encode($pickUpList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($savePickUpDir . '/' . $pickUpJson, "w");
	fwrite($write_json, $pickUp_json);
	fclose($write_json);
	#パーミッション変更
	@chmod($savePickUpDir . '/' . $pickUpJson, octdec("0666"));
} else {
	#表示可能リスト無し：空ファイル作成
	makeJson($savePickUpDir, $pickUpJson);
}
#-------------------------------------------#
# 人気の検索条件（conditions01-06）
#  - job_option_links（複数選択）と jobs（単一選択）から抽出
#  - 出力形式は jobsIndexAll.json と同一（items配列）
#条件別リスト（常に items を持つ）
#conditions
$conditions01List = ['items' => []];
$conditions02List = ['items' => []];
$conditions03List = ['items' => []];
$conditions04List = ['items' => []];
$conditions05List = ['items' => []];
$conditions06List = ['items' => []];
#フロント側マスタ定義JSONから「対象ラベルのid」を逆引き
$benefitOptions = [];
$holidayOptions = [];
$applicationRequirementOptions = [];
$trainingSupportOptions = [];
$firstYearIncomeRanges = [];
$employmentTypes = [];
try {
	$jsonMasters = getJson_FrontEndMaster_many([
		'benefitOptions',
		'holidayOptions',
		'applicationRequirementOptions',
		'trainingSupportOptions',
		'firstYearIncomeRanges',
		'employmentTypes',
	]);
	$benefitOptions = $jsonMasters['benefitOptions'] ?? [];
	$holidayOptions = $jsonMasters['holidayOptions'] ?? [];
	$applicationRequirementOptions = $jsonMasters['applicationRequirementOptions'] ?? [];
	$trainingSupportOptions = $jsonMasters['trainingSupportOptions'] ?? [];
	$firstYearIncomeRanges = $jsonMasters['firstYearIncomeRanges'] ?? [];
	$employmentTypes = $jsonMasters['employmentTypes'] ?? [];
} catch (Throwable $e) {
	if (function_exists('makeLog')) {
		makeLog('[makeIndexAll] master JSON load failed (conditions): ' . $e->getMessage());
	}
}
$cond_req_20s30s_id = findMasterOptionId($applicationRequirementOptions, '20代30代活躍中', ['name']);
$cond_benefit_childcare_id = findMasterOptionId($benefitOptions, '託児所・保育支援あり', ['name']);
$cond_benefit_reinstatement_id = findMasterOptionId($benefitOptions, '復職支援', ['name']);
$cond_holiday_childcare_id = findMasterOptionId($holidayOptions, '育児支援あり', ['label']);
$firstYearIncomeRangeMinMap = [];
if (is_array($firstYearIncomeRanges) && count($firstYearIncomeRanges) > 0) {
	foreach ($firstYearIncomeRanges as $r) {
		if (!is_array($r) || !isset($r['id'])) {
			continue;
		}
		$id = (string)$r['id'];
		if ($id === '') {
			continue;
		}
		$min = $r['min'] ?? null;
		if (is_numeric($min)) {
			$firstYearIncomeRangeMinMap[$id] = (int)$min;
		}
	}
}
$cond_support_training_id = findMasterOptionId($trainingSupportOptions, '研修制度あり', ['label']);
$cond_req_newgrad_id = findMasterOptionId($applicationRequirementOptions, '新卒可', ['name']);
$cond_employment_part_id = findMasterOptionId($employmentTypes, 'パート・アルバイト', ['name']);
#求人カード情報を走査して条件別に振り分け
if (is_array($jobsCardData) && count($jobsCardData) > 0) {
	$facilityCache = [];
	foreach ($jobsCardData as $jobCard) {
		#公開中のみ
		if (!isset($jobCard['is_active']) || $jobCard['is_active'] != 2) {
			continue;
		}
		$jobId = isset($jobCard['job_id']) ? (int)$jobCard['job_id'] : 0;
		if ($jobId <= 0) {
			continue;
		}
		$facilityId = isset($jobCard['facility_id']) ? (int)$jobCard['facility_id'] : 0;
		if ($facilityId <= 0) {
			continue;
		}
		#事業所情報（キャッシュ）
		if (!isset($facilityCache[$facilityId])) {
			$facilityCache[$facilityId] = getFacility_FindById($facilityId);
		}
		$facilityData = $facilityCache[$facilityId];
		if (!is_array($facilityData) || empty($facilityData)) {
			continue;
		}
		#給与情報生成（jobsIndexAll.json と同一ロジック）
		$salaryJson = [];
		if (($jobCard['salary_unit_id'] ?? null) == 'monthly') {
			$salaryJson = [
				'unitId' => 'monthly',
				'min' => $jobCard['salary_min'] ?? null,
				'max' => $jobCard['salary_max'] ?? null,
			];
		} elseif (($jobCard['salary_unit_id'] ?? null) == 'hourly') {
			$salaryJson = [
				'unitId' => 'hourly',
				'min' => $jobCard['salary_min'] ?? null,
				'max' => $jobCard['salary_max'] ?? null,
				'bandId' => !empty($jobCard['salary_range']) ? (json_decode($jobCard['salary_range'], true) ?: []) : [],
			];
		}
		#勤務先住所
		$locationAddress = ($facilityData['prefecture'] ?? '') . ($facilityData['city'] ?? '') . ($facilityData['address_line'] ?? '');
		#items要素生成
		$writeData = [
			'jobId' => $jobCard['job_code'],
			'facilityId' => $facilityData['facility_code'],
			'title' => $jobCard['card_title'],
			'facilityName' => $facilityData['name'],
			'jobCategoryId' => $jobCard['job_category_id'],
			'employmentTypeId' => $jobCard['employment_type_id'],
			'salary' => $salaryJson,
			'workLocationText' => $locationAddress,
			'areaIds' => [$facilityData['recruitment_area'] ? $facilityData['recruitment_area'] : []],
			'heroImages' => !empty($jobCard['hero_image_primary']) ? (json_decode($jobCard['hero_image_primary'], true) ?: []) : [],
			'publishedPeriod' => [
				'start' => $jobCard['published_start'],
				'end' => null,
			],
			'updatedAt' => date('Y/m/d', strtotime($jobCard['updated_at'] ?? $jobCard['created_at'])),
			'contractPlanId' => $jobCard['contract_plan_id'],
		];
		if (isset($jobCard['first_year_income_range_id']) && $jobCard['first_year_income_range_id'] !== null) {
			$writeData['firstYearIncomeRangeId'] = $jobCard['first_year_income_range_id'];
		}
		#オプション取得（job_option_links）
		$jobOptionGroupRows = getJobOptionGroup_FindByJobId($jobId);
		$optSet = [];
		if (is_array($jobOptionGroupRows) && count($jobOptionGroupRows) > 0) {
			foreach ($jobOptionGroupRows as $row) {
				$g = $row['option_group_code'] ?? null;
				$o = $row['option_id'] ?? null;
				if (!is_string($g) || $g === '' || !is_string($o) || $o === '') {
					continue;
				}
				if (!isset($optSet[$g])) {
					$optSet[$g] = [];
				}
				$optSet[$g][$o] = true;
			}
		}
		#conditions01:【応募要件】20代30代活躍中
		if ($cond_req_20s30s_id !== null && jobHasOptionId($optSet, 'requirements', $cond_req_20s30s_id)) {
			$conditions01List['items'][] = $writeData;
		}
		#conditions02:【待遇】託児所・保育支援あり or 復職支援 or 【休日】育児支援あり
		$match02 = false;
		if ($cond_benefit_childcare_id !== null && jobHasOptionId($optSet, 'benefits', $cond_benefit_childcare_id)) {
			$match02 = true;
		}
		if ($cond_benefit_reinstatement_id !== null && jobHasOptionId($optSet, 'benefits', $cond_benefit_reinstatement_id)) {
			$match02 = true;
		}
		if ($cond_holiday_childcare_id !== null && jobHasOptionId($optSet, 'holidays', $cond_holiday_childcare_id)) {
			$match02 = true;
		}
		if ($match02) {
			$conditions02List['items'][] = $writeData;
		}
		#conditions03:【給与】月給 + 初年度年収 450万円以上
		$incomeRangeId = (string)($jobCard['first_year_income_range_id'] ?? '');
		if (
			($jobCard['salary_unit_id'] ?? null) === 'monthly'
			&& $incomeRangeId !== ''
			&& isset($firstYearIncomeRangeMinMap[$incomeRangeId])
			&& $firstYearIncomeRangeMinMap[$incomeRangeId] >= 4500000
		) {
			$conditions03List['items'][] = $writeData;
		}
		#conditions04:【教育体制／研修】研修制度あり
		if ($cond_support_training_id !== null && jobHasOptionId($optSet, 'supports', $cond_support_training_id)) {
			$conditions04List['items'][] = $writeData;
		}
		#conditions05:【応募要件】新卒可
		if ($cond_req_newgrad_id !== null && jobHasOptionId($optSet, 'requirements', $cond_req_newgrad_id)) {
			$conditions05List['items'][] = $writeData;
		}
		#conditions06:【雇用形態】パート・アルバイト
		if ($cond_employment_part_id !== null && ($jobCard['employment_type_id'] ?? null) === $cond_employment_part_id) {
			$conditions06List['items'][] = $writeData;
		}
	}
}
#JSON書き出し（常にJSONとして出力する）
$conditionsPayloads = [
	$conditions01Json => $conditions01List,
	$conditions02Json => $conditions02List,
	$conditions03Json => $conditions03List,
	$conditions04Json => $conditions04List,
	$conditions05Json => $conditions05List,
	$conditions06Json => $conditions06List,
];
foreach ($conditionsPayloads as $fileName => $payload) {
	$payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if ($payloadJson === false) {
		$payloadJson = json_encode(['items' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	$write_json = fopen($saveConditionsDir . '/' . $fileName, 'w');
	fwrite($write_json, $payloadJson);
	fclose($write_json);
	@chmod($saveConditionsDir . '/' . $fileName, octdec('0666'));
}
#マスタ配列から name/label 等で id を逆引き
function findMasterOptionId($options, string $label, array $fields)
{
	if (!is_array($options) || $label === '' || empty($fields)) {
		return null;
	}
	#完全一致優先
	foreach ($options as $opt) {
		if (!is_array($opt) || !isset($opt['id'])) {
			continue;
		}
		foreach ($fields as $field) {
			if (isset($opt[$field]) && is_string($opt[$field]) && $opt[$field] === $label) {
				return (string)$opt['id'];
			}
		}
	}
	#部分一致（文言揺れ吸収）
	foreach ($options as $opt) {
		if (!is_array($opt) || !isset($opt['id'])) {
			continue;
		}
		foreach ($fields as $field) {
			if (isset($opt[$field]) && is_string($opt[$field]) && $opt[$field] !== '' && strpos($opt[$field], $label) !== false) {
				return (string)$opt['id'];
			}
		}
	}
	return null;
}
#option_id を持つか判定（optSet[group][id] = true の形式）
function jobHasOptionId($optSet, string $groupCode, string $optionId): bool
{
	return is_array($optSet)
		&& isset($optSet[$groupCode])
		&& is_array($optSet[$groupCode])
		&& isset($optSet[$groupCode][$optionId]);
}
#===========================================#
#jsonベースファイルを作成
function makeJson($saveDir, $makeJson)
{
	#ディレクトリが無い場合は作成
	if (!is_dir($saveDir)) {
		@mkdir($saveDir, 0777, true);
	}
	#ファイルがないなら空ファイル作成
	if (!file_exists($saveDir . '/' . $makeJson)) {
		$make_json = '';
		$write_json = fopen($saveDir . '/' . $makeJson, "w");
		fwrite($write_json, $make_json);
		fclose($write_json);
		#パーミッション変更
		@chmod($saveDir . '/' . $makeJson, octdec("0666"));
	}
}
#===========================================#


#===========================================#
/*
#全JSON生成が完了した「最後」に実行
if (defined('DEFINE_JSON_MIRROR_ENABLE') && DEFINE_JSON_MIRROR_ENABLE) {
	mirrorDbSelectiveMasterByRsync(
		(string)DEFINE_JSON_MIRROR_SRC_DB_DIR,         #初期ドメイン側 /db
		(string)DEFINE_JSON_MIRROR_DEST_DB_DIR,        #正式ドメイン側 /db
		(string)DEFINE_JSON_MIRROR_MASTER_ONLY_FILE    #corporations.json
	);
}
*/
#===========================================#
