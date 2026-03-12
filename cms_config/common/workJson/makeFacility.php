<?php
/*
 * [cms_config/common/workJson/makeFacility.php]
 *  - 管理画面 -
 *  新規事業所登録／編集／削除後のJSONファイル作成
 *
 * [初版]
 *  2025.12.26
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
#$facId = '3';
#-------------------------------------------#
#事業所IDがあれば事業所情報取得
if ($facId !== null) {
	$facilityData = getFacility_FindById($facId);
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
} else {
	#事業所ID無し：処理終了
	exit;
}
#事業所データ無し：処理終了
if (!is_array($facilityData) || count($facilityData) === 0) {
	exit;
}
#-------------------------------------------#
#json保存先
$saveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'];
#-------------------------------------------#
#===========================================#
#書き込み用ディレクトリが無い場合はベースディレクトリ作成
if (!is_dir($saveDir)) {
	@mkdir($saveDir, 0777, true);
}
#書き込み用jsonファイルが無い場合はベースファイルを作成
$facilityJson = 'facility.json';
if (!file_exists($saveDir . '/' . $facilityJson)) {
	makeJson($saveDir, $facilityJson);
}
#===========================================#
#事業所情報展開
if ((is_array($facilityData) && count($facilityData) > 0) && (is_array($facilityDetails) && count($facilityDetails) > 0)) {
	#法人情報取得
	$corporationData = getCorporations_FindById_Code($facilityData['corporation_id'], null);
	#訪問エリア整形
	if (isset($facilityDetailsJson['typeSpecific']['visitArea']) && $facilityDetailsJson['typeSpecific']['visitArea'] != '') {
		$visitArea = $facilityDetailsJson['typeSpecific']['visitArea'];
	} else {
		$visitArea = '';
	}
	#求人カードデータから募集職種情報取得
	$jobCardList = getJobList($facId);
	$jobCategoryList = [];
	$jobCategoryData = [];
	if (is_array($jobCardList) && count($jobCardList) > 0) {
		foreach ($jobCardList as $jobCard) {
			$jobCategoryData = [
				'jobCategoryId' => $jobCard['job_category_id'],
				'employmentTypeId' => $jobCard['employment_type_id'],
			];
			$jobCategoryList[] = $jobCategoryData;
		}
	}

	#スペシャルバナー画像パス成形
	$logoImagePath = $facilityDetailsJson['specialBanner']['logoImagePath'] ?? '';
	if (is_array($logoImagePath) && count($logoImagePath) > 0) {
		$logoImagePath = $logoImagePath[0] ?? '';
	}
	#jsonデータ生成
	$writeData = [];
	$writeData = [
		'id' => $facilityData['facility_code'],
		'corporationId' => $corporationData['corporation_code'],
		'facilityTypeId' => $facilityData['facility_type_id'],
		'facilityName' => $facilityData['name'],
		'mapUrl' => $facilityData['map_url'] ?? '',
		'mapLinkUrl' => $facilityData['map_link_url'] ?? '',
		'establishedDate' => $facilityData['established_date'],
		'postalCode' => $facilityData['postal_code'],
		'prefecture' => $facilityData['prefecture'],
		'city' => trim($facilityData['city']),
		'addressLine' => trim($facilityData['address_line']),
		'phone' => $facilityData['phone'],
		'email' => $facilityData['email'],
		'contactPerson' => $facilityDetails['contact_person'],
		'isEmergencyDesignated' => $facilityDetails['is_emergency_designated'] == 1 ? true : false,
		'facilityScale' => $facilityDetailsJson['facilityScale'] ? formatTextareaForDB($facilityDetailsJson['facilityScale']) : '',
		'averagePatients' => $facilityDetailsJson['averagePatients'] ? formatTextareaForDB($facilityDetailsJson['averagePatients']) : '',
		'businessHours' => $facilityDetailsJson['businessHours'] ? formatTextareaForDB($facilityDetailsJson['businessHours']) : '',
		'holidays' => $facilityDetailsJson['holidays'] ? formatTextareaForDB($facilityDetailsJson['holidays']) : '',
		'staffComposition' => $facilityDetailsJson['staffComposition'] ? formatTextareaForDB($facilityDetailsJson['staffComposition']) : '',
		'typeSpecific' => [
			'visitArea' => $visitArea ?? '',
		],
		'recruitJobs' => $jobCategoryList,
		'specialBanner' => [
			'enabled' => (bool)($facilityDetailsJson['specialBanner']['enabled'] ?? false),
			'logoImagePath' => $logoImagePath ?? '',
			'introVideoUrl' => $facilityDetailsJson['specialBanner']['introVideoUrl'] ?? '',
		],
	];
	#JSONエンコード
	$news_json = json_encode($writeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($saveDir . '/' . $facilityJson, "w");
	fwrite($write_json, $news_json);
	fclose($write_json);
} else {
	#表示可能リスト無し：空ファイル作成
	makeJson($saveDir, $facilityJson);
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
