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
require(__DIR__ . '/../../common/set_function.php');
require(__DIR__ . '/../../database/set_db.php');
require(__DIR__ . '/../../database/db_corporations.php');
require(__DIR__ . '/../../database/db_facilities.php');
require(__DIR__ . '/../../database/db_jobs.php');
#-------------------------------------------#
#===========================================#
#POSTチェック
#$facId = $argv[1];
#デバッグ用
#
$facId = '1';
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
require(__DIR__ . '/../../common/define.php');
$saveIndexAllDir = DEFINE_JSON_DIR_PATH . '/jobs';
#details_list.json
$saveDetailsListDir = DEFINE_JSON_DIR_PATH;
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
#details_list.json
if (!is_dir($saveDetailsListDir)) {
	@mkdir($saveDetailsListDir, 0777, true);
}
$detailsListJson = 'details_list.json';
if (!file_exists($saveDetailsListDir . '/' . $detailsListJson)) {
	makeJson($saveDetailsListDir, $detailsListJson);
}
#===========================================#
#-------------------------------------------#
# フロント側マスタ定義JSONファイル取得
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
	$news_json = json_encode($jobIndexList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($saveIndexAllDir . '/' . $jobsIndexAllJson, "w");
	fwrite($write_json, $news_json);
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
		if ($jobCard['is_active'] != 2) {
			continue;
		}
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
	$news_json = json_encode($detailsList, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($saveDetailsListDir . '/' . $detailsListJson, "w");
	fwrite($write_json, $news_json);
	fclose($write_json);
} else {
	#表示可能リスト無し：空ファイル作成
	makeJson($saveDetailsListDir, $detailsListJson);
}
#===========================================#
#jsonベースファイルを作成：一覧用
function makeJson($saveDir, $makeJson)
{
	#ディレクトリが無い場合は作成
	if (!is_dir($saveDir)) {
		@mkdir($saveDir, 0777, true);
	}
	#ファイルがないなら空ファイル作成
	if (!file_exists($saveDir . '/' . $makeJson)) {
		$news_json = '';
		$write_json = fopen($saveDir . '/' . $makeJson, "w");
		fwrite($write_json, $news_json);
		fclose($write_json);
		#パーミッション変更
		@chmod($saveDir . '/' . $makeJson, octdec("0666"));
	}
}
#===========================================#


#===========================================#
#全JSON生成が完了した「最後」に実行
if (defined('DEFINE_JSON_MIRROR_ENABLE') && DEFINE_JSON_MIRROR_ENABLE) {
	mirrorDbSelectiveMasterByRsync(
		(string)DEFINE_JSON_MIRROR_SRC_DB_DIR,         #初期ドメイン側 /db
		(string)DEFINE_JSON_MIRROR_DEST_DB_DIR,        #正式ドメイン側 /db
		(string)DEFINE_JSON_MIRROR_MASTER_ONLY_FILE    #corporations.json
	);
}
#===========================================#
