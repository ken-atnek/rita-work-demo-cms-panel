<?php
/*
 * [rw-master/assets/function/proc_master03_02_01.php]
 *  - 管理画面 -
 *  求人カード登録／編集 処理
 *
 * [初版]
 *  2025.12.26
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';
#求人カードDB書き込み共通ヘルパー
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_job_card_write_helpers.php';

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array(
  'tag' => '',
  'status' => '',
  'title' => '',
  'msg' => '',
  'facId' => '',
);

#===================================#
# フロント側マスタ定義JSONファイル取得
#-----------------------------------#
#取得項目一覧
$jsonMasters = [];
try {
  $jsonMasters = getJson_FrontEndMaster_many([
    'jobCategories',
    'employmentTypes',
    'salaryBandsHourly',
    'firstYearIncomeRanges',
    'contractPlans',
    'workEnvironmentMetrics',
    'jobContentOptions',
    'clinicalDepartments',
    'serviceTypeOptions',
    'benefitOptions',
    'workStyleOptions',
    'holidayOptions',
    'applicationRequirementOptions',
    'trainingSupportOptions',
    'accessOptions'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[proc_master03_02_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#雇用形態マスタ
$employmentTypes = $jsonMasters['employmentTypes'] ?? [];
#時給リストマスタ
$salaryBandsHourly = $jsonMasters['salaryBandsHourly'] ?? [];
#初年度年収リストマスタ
$firstYearIncomeRanges = $jsonMasters['firstYearIncomeRanges'] ?? [];
#契約プランマスタ
$contractPlans = $jsonMasters['contractPlans'] ?? [];
#職場環境の特徴マスタ
$workEnvironmentMetrics = $jsonMasters['workEnvironmentMetrics'] ?? [];
#仕事内容マスタ
$jobContentOptions = $jsonMasters['jobContentOptions'] ?? [];
#診療科目マスタ
$clinicalDepartments = $jsonMasters['clinicalDepartments'] ?? [];
#サービス形態マスタ
$serviceTypeOptions = $jsonMasters['serviceTypeOptions'] ?? [];
#待遇マスタ
$benefitOptions = $jsonMasters['benefitOptions'] ?? [];
#勤務時間マスタ
$workStyleOptions = $jsonMasters['workStyleOptions'] ?? [];
#休日マスタ
$holidayOptions = $jsonMasters['holidayOptions'] ?? [];
#応募要件マスタ
$applicationRequirementOptions = $jsonMasters['applicationRequirementOptions'] ?? [];
#教育体制／研修マスタ
$trainingSupportOptions = $jsonMasters['trainingSupportOptions'] ?? [];
#交通アクセスマスタ
$accessOptions = $jsonMasters['accessOptions'] ?? [];

/**
 * 募集職種ごとの詳細情報設定JSONを読み込む
 * @return array|null 設定配列（byJobCategoryNameキーを持つ）
 */
function loadJobDetailByCategoryConfig(): ?array
{
  $candidates = [];
  $candidates[] = DOCUMENT_ROOT_PATH . '/cms_config/common/jobDetailByCategory.json';
  foreach ($candidates as $path) {
    if (!file_exists($path)) {
      continue;
    }
    $jsonText = @file_get_contents($path);
    if ($jsonText === false || $jsonText === '') {
      continue;
    }
    $data = json_decode($jsonText, true);
    if (is_array($data)) {
      return $data;
    }
  }
  return null;
}
/**
 * 募集職種IDから職種名を取得する
 * @param array $jobCategories 職種マスター配列
 * @param string $jobCategoryId 職種ID
 * @return string 職種名（見つからない場合は空文字）
 */
function findJobCategoryNameById(array $jobCategories, string $jobCategoryId): string
{
  foreach ($jobCategories as $jobCategory) {
    if (!is_array($jobCategory)) {
      continue;
    }
    $id = isset($jobCategory['id']) ? (string)$jobCategory['id'] : '';
    if ($id === (string)$jobCategoryId) {
      return isset($jobCategory['name']) ? (string)$jobCategory['name'] : '';
    }
  }
  return '';
}
/**
 * マスター配列からラベル→IDの連想配列を生成
 * @param array $options マスター配列（id, label/nameを持つ）
 * @return array ラベル→IDの連想配列
 */
function buildIdIndexByLabel(array $options): array
{
  $index = [];
  foreach ($options as $opt) {
    if (!is_array($opt)) {
      continue;
    }
    $id = isset($opt['id']) ? (string)$opt['id'] : '';
    if ($id === '') {
      continue;
    }
    $label = '';
    if (isset($opt['name'])) {
      $label = (string)$opt['name'];
    } elseif (isset($opt['label'])) {
      $label = (string)$opt['label'];
    }
    $label = trim($label);
    if ($label === '') {
      continue;
    }
    $index[$label] = $id;
  }
  return $index;
}
/**
 * 許可ラベル配列をID配列に変換する
 * @param array $allowedLabels 許可ラベル配列
 * @param array $labelToIdIndex ラベル→IDの連想配列
 * @return array 許可ID配列（重複除去済み）
 */
function mapAllowedLabelsToIds(array $allowedLabels, array $labelToIdIndex): array
{
  $ids = [];
  foreach ($allowedLabels as $label) {
    $key = trim((string)$label);
    if ($key === '') {
      continue;
    }
    if (isset($labelToIdIndex[$key])) {
      $ids[] = (string)$labelToIdIndex[$key];
    }
  }
  return array_values(array_unique($ids));
}

#=============#
# POSTチェック
#-------------#
#新規／編集／削除
$method = isset($_POST['method']) ? $_POST['method'] : null;
#確認／修正／登録
$action = isset($_POST['action']) ? $_POST['action'] : null;
#事業所ID
$facId = isset($_POST['facId']) ? $_POST['facId'] : null;
#求人カードID（編集／削除時のみ）
$jobId = isset($_POST['jobId']) ? $_POST['jobId'] : null;
#-------------#
#事業所IDがあれば事業所情報取得
if ($facId !== null) {
  #事業所情報取得
  $facilityData = getFacility_FindById($facId);
} else {
  #事業所情報無し：処理終了 (エラー応答)
  $makeTag['status'] = 'error';
}
#-------------#
#求人コード
$job_code = isset($_POST['job_code']) ? $_POST['job_code'] : null;
#ステータス
$job_status = isset($_POST['jobStatus']) ? $_POST['jobStatus'] : null;
#掲載日
$published_start = isset($_POST['published_start']) ? $_POST['published_start'] : null;
#カードタイトル
$card_title = isset($_POST['card_title']) ? $_POST['card_title'] : null;
#LステップURL
$lstep_url = isset($_POST['lstep_url']) ? $_POST['lstep_url'] : null;
#募集職種
$job_category = isset($_POST['job_category']) ? $_POST['job_category'] : null;
#雇用形態
$employment_type = isset($_POST['employment_type']) ? $_POST['employment_type'] : null;
#給与
$select_salary = isset($_POST['select_salary']) ? $_POST['select_salary'] : null;
#月給の場合：最小～最大の入力値
if ($select_salary === 'monthly') {
  $bandIds = [];
  $salary_min = isset($_POST['monthly_min']) ? $_POST['monthly_min'] : null;
  $salary_max = isset($_POST['monthly_max']) ? $_POST['monthly_max'] : null;
  #大文字⇒小文字変換
  $salary_min = strtolower($salary_min);
  $salary_max = strtolower($salary_max);
  #「,」があれば除去
  $salary_min = str_replace(',', '', $salary_min);
  $salary_max = str_replace(',', '', $salary_max);
} else {
  $INF = PHP_INT_MAX;
  $salary_min = isset($_POST['hourly_min']) ? (int)$_POST['hourly_min'] : null;
  $salary_max = isset($_POST['hourly_max']) ? (int)$_POST['hourly_max'] : null;
  #大文字⇒小文字変換
  $salary_min = strtolower($salary_min);
  $salary_max = strtolower($salary_max);
  #「,」があれば除去
  $salary_min = str_replace(',', '', $salary_min);
  $salary_max = str_replace(',', '', $salary_max);
  $salary_max = $salary_max === 'inf' ? $INF : (int)$salary_max;
  #時給レンジ帯の取得
  $bandIds = [];
  foreach ($salaryBandsHourly as $salaryBand) {
    $bandMin = (int)($salaryBand['min'] ?? 0);
    $bandMax = array_key_exists('max', $salaryBand) && $salaryBand['max'] !== null ? (int)$salaryBand['max'] : $INF;
    #重なり判定： [bandMin, bandMax] と [salary_min, salary_max] が重なるか
    if ($bandMin <= $salary_max && $bandMax >= $salary_min) {
      $bandIds[] = $salaryBand['id'];
    }
  }
}
#時給の場合：ラベル選択式
#$salary_range = isset($_POST['salary_range']) ? $_POST['salary_range'] : null;
#給与備考
$salary_note = isset($_POST['salary_note']) ? $_POST['salary_note'] : null;
#賞与
$bonus = isset($_POST['bonus']) ? $_POST['bonus'] : null;
$bonus_note = isset($_POST['bonus_note']) ? $_POST['bonus_note'] : null;
#初年度年収
$yearly_salary = isset($_POST['yearly_salary']) ? $_POST['yearly_salary'] : null;
#-------------#
#契約プラン
$contract_plan = isset($_POST['contract_plan']) ? $_POST['contract_plan'] : null;
#-------------#
#協力者名
$interview_name = isset($_POST['interview_name']) ? $_POST['interview_name'] : null;
#部署名
$interview_role = isset($_POST['interview_role']) ? $_POST['interview_role'] : null;
#記事１：タイトル
$interview1_title = isset($_POST['interview1_title']) ? $_POST['interview1_title'] : null;
#記事１：見出し１
$interview1_heading = isset($_POST['interview1_heading']) ? $_POST['interview1_heading'] : null;
#記事１：本文１
$interview1_body = isset($_POST['interview1_body']) ? $_POST['interview1_body'] : null;
#記事１：見出し２
$interview1_heading1 = isset($_POST['interview1_heading1']) ? $_POST['interview1_heading1'] : null;
#記事１：本文２
$interview1_body1 = isset($_POST['interview1_body1']) ? $_POST['interview1_body1'] : null;
#記事２：タイトル
$interview2_title = isset($_POST['interview2_title']) ? $_POST['interview2_title'] : null;
#記事２：見出し１
$interview2_heading = isset($_POST['interview2_heading']) ? $_POST['interview2_heading'] : null;
#記事２：本文１
$interview2_body = isset($_POST['interview2_body']) ? $_POST['interview2_body'] : null;
#記事２：見出し２
$interview2_heading1 = isset($_POST['interview2_heading1']) ? $_POST['interview2_heading1'] : null;
#記事２：本文２
$interview2_body1 = isset($_POST['interview2_body1']) ? $_POST['interview2_body1'] : null;
#記事３：タイトル
$interview3_title = isset($_POST['interview3_title']) ? $_POST['interview3_title'] : null;
#記事３：見出し１
$interview3_heading = isset($_POST['interview3_heading']) ? $_POST['interview3_heading'] : null;
#記事３：本文１
$interview3_body = isset($_POST['interview3_body']) ? $_POST['interview3_body'] : null;
#記事３：見出し２
$interview3_heading1 = isset($_POST['interview3_heading1']) ? $_POST['interview3_heading1'] : null;
#記事３：本文２
$interview3_body1 = isset($_POST['interview3_body1']) ? $_POST['interview3_body1'] : null;
#-------------#
#職場紹介動画
$video1_url = isset($_POST['video1_url']) ? $_POST['video1_url'] : null;
$video2_url = isset($_POST['video2_url']) ? $_POST['video2_url'] : null;
#-------------#
#福利厚生１：見出し
$benefits1_title = isset($_POST['benefits1_title']) ? $_POST['benefits1_title'] : null;
#福利厚生１：本文
$benefits1_body = isset($_POST['benefits1_body']) ? $_POST['benefits1_body'] : null;
#福利厚生２：見出し
$benefits2_title = isset($_POST['benefits2_title']) ? $_POST['benefits2_title'] : null;
#福利厚生２：本文
$benefits2_body = isset($_POST['benefits2_body']) ? $_POST['benefits2_body'] : null;
#福利厚生３：見出し
$benefits3_title = isset($_POST['benefits3_title']) ? $_POST['benefits3_title'] : null;
#福利厚生３：本文
$benefits3_body = isset($_POST['benefits3_body']) ? $_POST['benefits3_body'] : null;
#福利厚生４：見出し
$benefits4_title = isset($_POST['benefits4_title']) ? $_POST['benefits4_title'] : null;
#福利厚生４：本文
$benefits4_body = isset($_POST['benefits4_body']) ? $_POST['benefits4_body'] : null;
#-------------#
#職場環境の特徴１：選択値
$selectedMetricArea1 = isset($_POST['work_environment_metrics1']) ? $_POST['work_environment_metrics1'] : null;
#職場環境の特徴１：数値入力値
$metricArea1Value = isset($_POST['work_environment_metrics1_value']) ? $_POST['work_environment_metrics1_value'] : null;
#登録するときは「,」あれば除去
if ($metricArea1Value != null) {
  $metricArea1Value = str_replace(',', '', $metricArea1Value);
}
#職場環境の特徴１：単位
$metricArea1Unit = isset($_POST['work_environment_metrics1_unit']) ? $_POST['work_environment_metrics1_unit'] : null;
#職場環境の特徴２：選択値
$selectedMetricArea2 = isset($_POST['work_environment_metrics2']) ? $_POST['work_environment_metrics2'] : null;
#職場環境の特徴２：数値入力値
$metricArea2Value = isset($_POST['work_environment_metrics2_value']) ? $_POST['work_environment_metrics2_value'] : null;
#登録するときは「,」あれば除去
if ($metricArea2Value != null) {
  $metricArea2Value = str_replace(',', '', $metricArea2Value);
}
#職場環境の特徴２：単位
$metricArea2Unit = isset($_POST['work_environment_metrics2_unit']) ? $_POST['work_environment_metrics2_unit'] : null;
#職場環境の特徴３：選択値
$selectedMetricArea3 = isset($_POST['work_environment_metrics3']) ? $_POST['work_environment_metrics3'] : null;
#職場環境の特徴３：数値入力値
$metricArea3Value = isset($_POST['work_environment_metrics3_value']) ? $_POST['work_environment_metrics3_value'] : null;
#登録するときは「,」あれば除去
if ($metricArea3Value != null) {
  $metricArea3Value = str_replace(',', '', $metricArea3Value);
}
#職場環境の特徴３：単位
$metricArea3Unit = isset($_POST['work_environment_metrics3_unit']) ? $_POST['work_environment_metrics3_unit'] : null;
#-------------#
#フリーテキスト：タイトル
$free_text_title = isset($_POST['free_text_title']) ? $_POST['free_text_title'] : null;
#フリーテキスト１：見出し
$free_text1_heading = isset($_POST['free_text1_heading']) ? $_POST['free_text1_heading'] : null;
#フリーテキスト１：本文
$free_text1_body = isset($_POST['free_text1_body']) ? $_POST['free_text1_body'] : null;
#フリーテキスト２：見出し
$free_text2_heading = isset($_POST['free_text2_heading']) ? $_POST['free_text2_heading'] : null;
#フリーテキスト２：本文
$free_text2_body = isset($_POST['free_text2_body']) ? $_POST['free_text2_body'] : null;
#フリーテキスト３：見出し
$free_text3_heading = isset($_POST['free_text3_heading']) ? $_POST['free_text3_heading'] : null;
#フリーテキスト３：本文
$free_text3_body = isset($_POST['free_text3_body']) ? $_POST['free_text3_body'] : null;
#フリーテキスト４：見出し
$free_text4_heading = isset($_POST['free_text4_heading']) ? $_POST['free_text4_heading'] : null;
#フリーテキスト４：本文
$free_text4_body = isset($_POST['free_text4_body']) ? $_POST['free_text4_body'] : null;
#-------------#
#１日の流れ：日勤
$day_list_hour = isset($_POST['day_list_hour']) ? $_POST['day_list_hour'] : null;
$day_list_min = isset($_POST['day_list_min']) ? $_POST['day_list_min'] : null;
$day_list_body = isset($_POST['day_list_body']) ? $_POST['day_list_body'] : null;
#１日の流れ：夜勤
$night_list_hour = isset($_POST['night_list_hour']) ? $_POST['night_list_hour'] : null;
$night_list_min = isset($_POST['night_list_min']) ? $_POST['night_list_min'] : null;
$night_list_body = isset($_POST['night_list_body']) ? $_POST['night_list_body'] : null;
#-------------#
#仕事内容
$job_content = isset($_POST['job_content']) ? $_POST['job_content'] : array();
$job_content_notice = isset($_POST['job_content_notice']) ? $_POST['job_content_notice'] : null;
#-------------#
#診療科目
$clinical_department = isset($_POST['clinical_department']) ? $_POST['clinical_department'] : array();
#-------------#
#サービス形態
$service_type = isset($_POST['service_type']) ? $_POST['service_type'] : array();
#-------------#
#待遇
$benefits = isset($_POST['benefits']) ? $_POST['benefits'] : array();
$benefits_notice = isset($_POST['benefits_notice']) ? $_POST['benefits_notice'] : null;
#-------------#
#勤務時間
$work_style = isset($_POST['work_style']) ? $_POST['work_style'] : array();
$work_style_notice = isset($_POST['work_style_notice']) ? $_POST['work_style_notice'] : null;
#-------------#
#休日
$holidays = isset($_POST['holidays']) ? $_POST['holidays'] : array();
$holidays_notice = isset($_POST['holidays_notice']) ? $_POST['holidays_notice'] : null;
#-------------#
#長期休暇／特別休暇
$long_term_holiday_notice = isset($_POST['long_term_holiday_notice']) ? $_POST['long_term_holiday_notice'] : null;
#-------------#
#応募要件
$requirements = isset($_POST['requirements']) ? $_POST['requirements'] : array();
$requirement_notice = isset($_POST['requirement_notice']) ? $_POST['requirement_notice'] : null;
#-------------#
#歓迎要件
$welcome_notice = isset($_POST['welcome_notice']) ? $_POST['welcome_notice'] : null;
#-------------#
#教育体制／研修
$supports = isset($_POST['supports']) ? $_POST['supports'] : array();
$support_notice = isset($_POST['support_notice']) ? $_POST['support_notice'] : null;
#-------------#
#アクセス
$accesses = isset($_POST['accesses']) ? $_POST['accesses'] : array();
$access_notice = isset($_POST['access_notice']) ? $_POST['access_notice'] : null;
#-------------#
#選考プロセス
$selection_process = isset($_POST['selection_process']) ? $_POST['selection_process'] : null;
#-------------#
#画像アップロード先（セッション領域）
if (isset($_POST['up_image_area'])) {
  $imageUploadSessionKeys = $_POST['up_image_area'];
  if (!is_array($imageUploadSessionKeys)) {
    $imageUploadSessionKeys = array($imageUploadSessionKeys);
  }
} else {
  $imageUploadSessionKeys = array();
}
$targetImageUploadSessionKey = $imageUploadSessionKeys[0] ?? '';

/**
 * 画像アップロードエリア名からドキュメント種別・パス情報を返す
 * @param string $area エリア名
 * @return array|null ドキュメント種別・パス情報
 */
function jobCardImageAreaInfo(string $area): ?array
{
  if (preg_match('/^interview([1-3])_image$/', $area, $m)) {
    $index = (int)$m[1] - 1;
    return [
      'docType' => 'interview',
      'path' => ['articles', $index, 'image'],
    ];
  }
  if (preg_match('/^benefits([1-4])_image$/', $area, $m)) {
    $index = (int)$m[1] - 1;
    return [
      'docType' => 'benefits',
      'path' => ['sections', $index, 'image'],
    ];
  }
  return null;
}
/**
 * 配列からパス指定で値を取得する
 * @param array $data 対象配列
 * @param array $path キー配列
 * @param mixed $default デフォルト値
 * @return mixed パスで辿った値（存在しない場合はdefault）
 */
function arrayGetByPath(array $data, array $path, $default = '')
{
  $current = $data;
  foreach ($path as $key) {
    if (!is_array($current) || !array_key_exists($key, $current)) {
      return $default;
    }
    $current = $current[$key];
  }
  return $current;
}
/**
 * 配列にパス指定で値をセットする
 * @param array &$data 対象配列（参照渡し）
 * @param array $path キー配列
 * @param mixed $value セットする値
 */
function arraySetByPath(array &$data, array $path, $value): void
{
  $ref = &$data;
  $lastIndex = count($path) - 1;
  foreach ($path as $i => $key) {
    if ($i === $lastIndex) {
      $ref[$key] = $value;
      return;
    }
    if (!isset($ref[$key]) || !is_array($ref[$key])) {
      $ref[$key] = [];
    }
    $ref = &$ref[$key];
  }
}
/**
 * 画像拡張子からMIMEタイプを推定
 */
function jobCardImageMimeTypeFromExt(string $ext): string
{
  $ext = strtolower($ext);
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      return 'image/jpeg';
    case 'png':
      return 'image/png';
    case 'gif':
      return 'image/gif';
    default:
      return '';
  }
}
/**
 * 画像ドラフトセッションが「どの求人(job_id)向けか」を保持するメタキー
 */
function jobCardImageSessionJobIdKey(string $area): string
{
  return $area . '__job_card_job_id';
}
/**
 * DB登録済み画像をセッションへ初期ロード（ドラフト編集用）
 * - $_SESSION[$area] を「プレビュー順のリスト」に統一する
 *   - DB画像: ['kind' => 'db', 'path' => '/db/images/...']
 *   - tmp画像: 既存の ['tmp_name'=>..., 'name'=>..., ...] を使用
 */
function jobCardInitImageSessionFromDB(string $area, int $jobId): void
{
  if ($area === '' || $jobId <= 0) {
    return;
  }
  $metaKey = jobCardImageSessionJobIdKey($area);
  if (isset($_SESSION[$area]) && is_array($_SESSION[$area])) {
    $boundJobId = isset($_SESSION[$metaKey]) ? (int)$_SESSION[$metaKey] : 0;
    if ($boundJobId === $jobId) {
      return;
    }
    #別求人のセッションが残っている場合は破棄して再初期化
    unset($_SESSION[$area]);
    if (isset($_SESSION[$metaKey])) {
      unset($_SESSION[$metaKey]);
    }
  }
  $items = [];
  if ($area === 'hero_image') {
    $jobData = getJob_FindById($jobId);
    $hero = [];
    if (isset($jobData['hero_image_primary']) && $jobData['hero_image_primary'] !== '') {
      $decoded = json_decode($jobData['hero_image_primary'], true);
      if (is_array($decoded)) {
        $hero = $decoded;
      }
    }
    foreach ($hero as $path) {
      if (!is_string($path) || $path === '') {
        continue;
      }
      $items[] = ['kind' => 'db', 'path' => $path];
    }
    $_SESSION[$area] = $items;
    $_SESSION[$metaKey] = $jobId;
    return;
  }
  $areaInfo = jobCardImageAreaInfo($area);
  if (!$areaInfo) {
    $_SESSION[$area] = [];
    $_SESSION[$metaKey] = $jobId;
    return;
  }
  $targetDocument = $areaInfo['docType'];
  $jobCardDocument = getJobCardArticle_FindByJobId($jobId, $targetDocument);
  $jobCardDocumentJson = [];
  if (isset($jobCardDocument['content_json']) && $jobCardDocument['content_json']) {
    $tmp = json_decode($jobCardDocument['content_json'], true);
    if (is_array($tmp)) {
      $jobCardDocumentJson = $tmp;
    }
  }
  $path = (string)arrayGetByPath($jobCardDocumentJson, $areaInfo['path'], '');
  if ($path !== '') {
    $items[] = ['kind' => 'db', 'path' => $path];
  }
  $_SESSION[$area] = $items;
  $_SESSION[$metaKey] = $jobId;
}
/**
 * セッションの画像リストからプレビュータグを生成
 */
function jobCardBuildPreviewTagsFromSession(string $area): string
{
  if ($area === '' || !isset($_SESSION[$area]) || !is_array($_SESSION[$area])) {
    return '';
  }
  $out = '';
  foreach ($_SESSION[$area] as $info) {
    if (!is_array($info)) {
      continue;
    }
    $previewPath = '';
    $ext = '';
    if (isset($info['path']) && is_string($info['path']) && $info['path'] !== '') {
      $previewPath = DOMAIN_NAME . $info['path'];
      $ext = strtolower(pathinfo($info['path'], PATHINFO_EXTENSION));
    } elseif (isset($info['name']) && is_string($info['name']) && $info['name'] !== '') {
      $previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $info['name'];
      $ext = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION));
    } else {
      continue;
    }
    $mimeType = jobCardImageMimeTypeFromExt($ext);
    $out .= <<<HTML
                  <li>
                    <div class="warp-btn">
                      <button type="button" class="btn-change"></button>
                      <button type="button" class="btn-delate"></button>
                    </div>
                    <picture>
                      <source src="{$previewPath}" type="{$mimeType}">
                      <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                    </picture>
                  </li>

HTML;
  }
  return $out;
}
/**
 * DB保存URL（/db/images/...）をファイル実体パスへ変換
 */
function jobCardPublicImagePathToFullPath(string $publicPath): string
{
  $publicPath = (string)$publicPath;
  if ($publicPath === '') {
    return '';
  }
  $prefix = '/db/images/';
  if (strpos($publicPath, $prefix) !== 0) {
    return '';
  }
  $rel = ltrim(substr($publicPath, strlen($prefix)), '/');
  if ($rel === '') {
    return '';
  }
  return rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/' . str_replace('\\', '/', $rel);
}
/**
 * job_documentsテーブルのcontent_jsonを更新する
 * @param int $jobId 求人ID
 * @param string $docType ドキュメント種別
 * @param string $contentJson JSON文字列
 * @return array [成功:bool, メッセージ:string]
 */
function updateJobDocumentContentJson(int $jobId, string $docType, string $contentJson): array
{
  global $DB_CONNECT;
  #トランザクション開始
  # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
  $result = DB_Transaction(1);
  if ($result == false) {
    return [false, 'トランザクション開始失敗'];
  }
  #登録用配列：初期化
  $dbFiledData = array();
  #登録情報セット
  $dbFiledData['content_json'] = array(':content_json', $contentJson, 0);
  $dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
  #更新用キー：初期化
  $dbFiledValue = array();
  $dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
  $dbFiledValue['doc_type'] = array(':doc_type', $docType, 0);
  #処理モード：[1].新規追加｜[2].更新｜[3].削除
  $processFlg = 2;
  #実行モード：[1].トランザクション｜[2].即実行
  $exeFlg = 2;
  #DB更新
  $dbSuccessFlg = SQL_Process($DB_CONNECT, "job_documents", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
  #DB更新結果判定
  if ($dbSuccessFlg == 1) {
    DB_Transaction(2);
    return [true, ''];
  }
  DB_Transaction(3);
  return [false, 'DB更新失敗'];
}
/**
 * jobsテーブルのhero_image_primaryを更新する
 * @param int $jobId 求人ID
 * @param int $facId 事業所ID
 * @param string $heroImagePrimaryJson JSON文字列
 * @return array [成功:bool, メッセージ:string]
 */
function updateJobHeroImagePrimaryJson(int $jobId, int $facId, string $heroImagePrimaryJson): array
{
  global $DB_CONNECT;
  #トランザクション開始
  # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
  $result = DB_Transaction(1);
  if ($result == false) {
    return [false, 'トランザクション開始失敗'];
  }
  #登録用配列：初期化
  $dbFiledData = array();
  $dbFiledData['hero_image_primary'] = array(':hero_image_primary', $heroImagePrimaryJson, 0);
  #更新用キー：初期化
  $dbFiledValue = array();
  $dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
  $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
  #処理モード：[1].新規追加｜[2].更新｜[3].削除
  $processFlg = 2;
  #実行モード：[1].トランザクション｜[2].即実行
  $exeFlg = 2;
  #DB更新
  $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
  #DB更新結果判定
  if ($dbSuccessFlg == 1) {
    DB_Transaction(2);
    return [true, ''];
  }
  DB_Transaction(3);
  return [false, 'DB更新失敗'];
}
/**
 * ドキュメント種別ごとにファイル保存用JSONを整形
 * @param string $docType ドキュメント種別
 * @param array $docJson 元JSON
 * @return array 整形済みJSON
 */
function formatJobDocumentJsonForFile(string $docType, array $docJson): array
{
  if ($docType === 'interview') {
    if (isset($docJson['articles']) && is_array($docJson['articles'])) {
      foreach ($docJson['articles'] as $aIdx => $article) {
        if (!isset($article['sections']) || !is_array($article['sections'])) {
          continue;
        }
        foreach ($article['sections'] as $sIdx => $section) {
          $bodyRaw = $section['body'] ?? '';
          $docJson['articles'][$aIdx]['sections'][$sIdx]['body'] = $bodyRaw ? formatTextareaForDB($bodyRaw) : '';
        }
      }
    }
    return $docJson;
  }
  if ($docType === 'benefits') {
    if (isset($docJson['sections']) && is_array($docJson['sections'])) {
      foreach ($docJson['sections'] as $idx => $section) {
        $bodyRaw = $section['body'] ?? '';
        $docJson['sections'][$idx]['body'] = $bodyRaw ? formatTextareaForDB($bodyRaw) : '';
      }
    }
    return $docJson;
  }
  return $docJson;
}
/**
 * ドキュメントJSONをファイル保存する
 * @param string $docType ドキュメント種別
 * @param array $docJson JSON配列
 * @param string $jobCode 求人コード
 * @param string $facilityCode 事業所コード
 * @return bool 成功時true
 */
function writeJobDocumentJsonFile(string $docType, array $docJson, string $jobCode, string $facilityCode): bool
{
  $suffix = '';
  if ($docType === 'interview') {
    $suffix = "_interview.json";
  } elseif ($docType === 'benefits') {
    $suffix = '_benefits.json';
  } else {
    return false;
  }
  $saveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityCode . '/jobs/';
  if (!is_dir($saveDir)) {
    @mkdir($saveDir, 0777, true);
  }
  $fileName = $jobCode . $suffix;
  if (!file_exists($saveDir . '/' . $fileName)) {
    makeJson($saveDir, $fileName);
  }
  $docJsonForFile = formatJobDocumentJsonForFile($docType, $docJson);
  $jsonText = json_encode($docJsonForFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  if ($jsonText === false) {
    return false;
  }
  $fp = @fopen($saveDir . '/' . $fileName, 'w');
  if ($fp === false) {
    return false;
  }
  fwrite($fp, $jsonText);
  fclose($fp);
  return true;
}
/**
 * 指定パスのファイルを削除する
 * @param string $path ファイルパス
 */
function deleteFileIfExists(string $path): void
{
  if ($path !== '' && file_exists($path)) {
    @unlink($path);
  }
}
/**
 * ディレクトリ配下を再帰的に削除する
 * @param string $dir ディレクトリパス
 * @return bool 成功時true
 */
function deleteDirRecursive(string $dir): bool
{
  if ($dir === '' || !is_dir($dir)) {
    return true;
  }
  $items = @scandir($dir);
  if ($items === false) {
    return false;
  }
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }
    $path = rtrim($dir, '/\\') . '/' . $item;
    if (is_dir($path)) {
      if (!deleteDirRecursive($path)) {
        return false;
      }
    } else {
      @unlink($path);
    }
  }
  return @rmdir($dir);
}
/**
 * ディレクトリが空か判定する
 * @param string $dir ディレクトリパス
 * @return bool 空ならtrue
 */
function isDirEmpty(string $dir): bool
{
  if ($dir === '' || !is_dir($dir)) {
    return false;
  }
  $items = @scandir($dir);
  if ($items === false) {
    return false;
  }
  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }
    return false;
  }
  return true;
}
/**
 * ディレクトリが空なら削除する
 * @param string $dir ディレクトリパス
 * @return bool 削除成功時true
 */
function deleteDirIfEmpty(string $dir): bool
{
  if (isDirEmpty($dir)) {
    return @rmdir($dir);
  }
  return false;
}
/**
 * ディレクトリ作成（親も含めて）
 */
function ensureDir(string $dir): bool
{
  if ($dir === '') {
    return false;
  }
  if (is_dir($dir)) {
    return true;
  }
  return @mkdir($dir, 0777, true);
}
/**
 * ファイル確定（rename優先、失敗時はcopy→unlink）
 */
function safeMoveFile(string $src, string $dst): bool
{
  if ($src === '' || $dst === '') {
    return false;
  }
  if (!file_exists($src)) {
    return false;
  }
  $parent = dirname($dst);
  if ($parent !== '' && !is_dir($parent)) {
    if (!ensureDir($parent)) {
      return false;
    }
  }
  if (@rename($src, $dst)) {
    return true;
  }
  if (@copy($src, $dst)) {
    @unlink($src);
    return true;
  }
  return false;
}
/**
 * tmpファイル掃除（存在すれば削除）
 */
function cleanupFiles(array $paths): void
{
  foreach ($paths as $p) {
    if (!is_string($p) || $p === '') {
      continue;
    }
    if (file_exists($p) && is_file($p)) {
      @unlink($p);
    }
  }
}

#***** タグ生成開始 *****#
switch ($action) {
  #***** 画像ドラフト破棄（ページ離脱・リロード時） *****#
  case 'discardUploadDraft': {
      $areas = $imageUploadSessionKeys ?? [];
      if (!is_array($areas)) {
        $areas = [$areas];
      }
      $jobIdForDraft = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
      $deletedTmpCount = 0;
      $clearedAreas = [];
      foreach ($areas as $area) {
        if (!is_string($area) || $area === '') {
          continue;
        }
        $metaKey = jobCardImageSessionJobIdKey($area);
        if (!isset($_SESSION[$area]) || !is_array($_SESSION[$area])) {
          if (isset($_SESSION[$metaKey])) {
            unset($_SESSION[$metaKey]);
          }
          continue;
        }
        foreach ($_SESSION[$area] as $info) {
          if (!is_array($info)) {
            continue;
          }
          $tmpPath = $info['tmp_name'] ?? '';
          if (is_string($tmpPath) && $tmpPath !== '' && file_exists($tmpPath) && is_file($tmpPath)) {
            if (@unlink($tmpPath)) {
              $deletedTmpCount++;
            }
          }
        }
        unset($_SESSION[$area]);
        if (isset($_SESSION[$metaKey])) {
          unset($_SESSION[$metaKey]);
        }
        $clearedAreas[] = $area;
      }
      #削除予約（DB画像）は破棄（＝本番削除しない）
      if ($jobIdForDraft > 0 && isset($_SESSION['job_card_image_pending_deletes']) && is_array($_SESSION['job_card_image_pending_deletes'])) {
        $k = (string)$jobIdForDraft;
        if (isset($_SESSION['job_card_image_pending_deletes'][$k])) {
          unset($_SESSION['job_card_image_pending_deletes'][$k]);
        }
      }
      header('Content-Type: application/json');
      echo json_encode([
        'status' => 'success',
        'deletedTmpCount' => $deletedTmpCount,
        'clearedAreas' => $clearedAreas,
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }
    #***** 募集職種ごとの詳細情報選択肢取得（AJAX） *****#
  case 'getJobDetailOptionsByCategory': {
      global $jobCategories;
      global $jobContentOptions;
      global $clinicalDepartments;
      global $serviceTypeOptions;
      global $benefitOptions;
      global $workStyleOptions;
      global $holidayOptions;
      global $applicationRequirementOptions;
      global $trainingSupportOptions;
      global $accessOptions;
      #募集職種ID取得
      $jobCategoryId = isset($_POST['job_category_id']) ? (string)$_POST['job_category_id'] : '';
      if ($jobCategoryId === '') {
        header('Content-Type: application/json');
        echo json_encode([
          'status' => 'error',
          'title' => '取得失敗',
          'msg' => '募集職種が未指定です。',
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
      #募集職種名取得
      $jobCategoryName = trim(findJobCategoryNameById($jobCategories ?? [], $jobCategoryId));
      $config = loadJobDetailByCategoryConfig();
      $byName = null;
      if (is_array($config) && isset($config['byJobCategoryName']) && is_array($config['byJobCategoryName'])) {
        if ($jobCategoryName !== '' && isset($config['byJobCategoryName'][$jobCategoryName])) {
          $byName = $config['byJobCategoryName'][$jobCategoryName];
        }
      }
      #設定が無い場合は「全表示」扱い（configuredGroups空）
      if (!is_array($byName)) {
        header('Content-Type: application/json');
        echo json_encode([
          'status' => 'success',
          'jobCategoryId' => $jobCategoryId,
          'jobCategoryName' => $jobCategoryName,
          'configuredGroups' => [],
          'allowedOptionIds' => new stdClass(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }
      #構成されている選択肢グループ一覧取得
      $configuredGroups = array_keys($byName);
      $indexJobContent = buildIdIndexByLabel($jobContentOptions ?? []);
      $indexClinical = buildIdIndexByLabel($clinicalDepartments ?? []);
      $indexServiceType = buildIdIndexByLabel($serviceTypeOptions ?? []);
      $indexBenefits = buildIdIndexByLabel($benefitOptions ?? []);
      $indexWorkStyle = buildIdIndexByLabel($workStyleOptions ?? []);
      $indexHoliday = buildIdIndexByLabel($holidayOptions ?? []);
      $indexRequirement = buildIdIndexByLabel($applicationRequirementOptions ?? []);
      $indexSupport = buildIdIndexByLabel($trainingSupportOptions ?? []);
      $indexAccess = buildIdIndexByLabel($accessOptions ?? []);
      #表示可能な選択肢IDリストを構築
      $allowedOptionIds = [];
      #仕事内容
      if (array_key_exists('job_content', $byName)) {
        $allowedOptionIds['job_content'] = mapAllowedLabelsToIds((array)$byName['job_content'], $indexJobContent);
      }
      #診療科目（"診療科目なし" 指定は空配列で返す＝全非表示）
      if (array_key_exists('clinical_department', $byName)) {
        $labels = (array)$byName['clinical_department'];
        $noDept = false;
        foreach ($labels as $l) {
          if (strpos((string)$l, '診療科目なし') !== false) {
            $noDept = true;
            break;
          }
        }
        $allowedOptionIds['clinical_department'] = $noDept ? [] : mapAllowedLabelsToIds($labels, $indexClinical);
      }
      #サービス形態
      if (array_key_exists('service_type', $byName)) {
        $allowedOptionIds['service_type'] = mapAllowedLabelsToIds((array)$byName['service_type'], $indexServiceType);
      }
      #待遇
      if (array_key_exists('benefits', $byName)) {
        $allowedOptionIds['benefits'] = mapAllowedLabelsToIds((array)$byName['benefits'], $indexBenefits);
      }
      #勤務時間
      if (array_key_exists('work_style', $byName)) {
        $allowedOptionIds['work_style'] = mapAllowedLabelsToIds((array)$byName['work_style'], $indexWorkStyle);
      }
      #休日
      if (array_key_exists('holidays', $byName)) {
        $allowedOptionIds['holidays'] = mapAllowedLabelsToIds((array)$byName['holidays'], $indexHoliday);
      }
      #応募要件
      if (array_key_exists('requirements', $byName)) {
        $allowedOptionIds['requirements'] = mapAllowedLabelsToIds((array)$byName['requirements'], $indexRequirement);
      }
      #教育体制・研修
      if (array_key_exists('supports', $byName)) {
        $allowedOptionIds['supports'] = mapAllowedLabelsToIds((array)$byName['supports'], $indexSupport);
      }
      #アクセス
      if (array_key_exists('accesses', $byName)) {
        $allowedOptionIds['accesses'] = mapAllowedLabelsToIds((array)$byName['accesses'], $indexAccess);
      }
      #応答
      header('Content-Type: application/json');
      echo json_encode([
        'status' => 'success',
        'jobCategoryId' => $jobCategoryId,
        'jobCategoryName' => $jobCategoryName,
        'configuredGroups' => $configuredGroups,
        'allowedOptionIds' => $allowedOptionIds,
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }
    #***** 画像プレビューチェック（ドラッグ＆ドロップ/ファイル選択アップロード） *****#
  case 'preUploadImage': {
      #エリア名が未指定の場合はエラー応答
      if (empty($targetImageUploadSessionKey)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = 'アップロードエリア名が指定されていません。';
        echo json_encode($makeTag);
        exit;
      }
      $makeTag['file_url'] = '';
      $makeTag['file_name'] = '';
      $upImageMode = isset($_POST['up_image_mode']) ? (string)$_POST['up_image_mode'] : '';
      $jobIdForDraft = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
      if ($jobIdForDraft > 0) {
        jobCardInitImageSessionFromDB($targetImageUploadSessionKey, $jobIdForDraft);
      }
      if (isset($_FILES['images_tmp']) && is_uploaded_file($_FILES['images_tmp']['tmp_name'])) {
        $file = $_FILES['images_tmp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($ext, $allowed)) {
          $makeTag['status'] = 'error';
          $makeTag['msg'] = '許可されていないファイル形式です。';
        } else {
          #一時保存先
          $tmpDir = __DIR__ . '/../../../tmp_upload/';
          if (!file_exists($tmpDir)) mkdir($tmpDir, 0777, true);
          $uniqueName = 'image_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $savePath = $tmpDir . $uniqueName;
          $previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $uniqueName;
          if (move_uploaded_file($file['tmp_name'], $savePath)) {
            #onlyモードの場合は、既存（DB/tmp）を置換扱いにしてドラフトを1枚に揃える
            if ($upImageMode === 'only' && isset($_SESSION[$targetImageUploadSessionKey]) && is_array($_SESSION[$targetImageUploadSessionKey]) && !empty($_SESSION[$targetImageUploadSessionKey])) {
              foreach ($_SESSION[$targetImageUploadSessionKey] as $oldInfo) {
                if (!is_array($oldInfo)) {
                  continue;
                }
                if (isset($oldInfo['tmp_name']) && is_string($oldInfo['tmp_name']) && $oldInfo['tmp_name'] !== '' && file_exists($oldInfo['tmp_name'])) {
                  @unlink($oldInfo['tmp_name']);
                } elseif (isset($oldInfo['path']) && is_string($oldInfo['path']) && $oldInfo['path'] !== '' && $jobIdForDraft > 0) {
                  if (!isset($_SESSION['job_card_image_pending_deletes'])) {
                    $_SESSION['job_card_image_pending_deletes'] = [];
                  }
                  $k = (string)$jobIdForDraft;
                  if (!isset($_SESSION['job_card_image_pending_deletes'][$k]) || !is_array($_SESSION['job_card_image_pending_deletes'][$k])) {
                    $_SESSION['job_card_image_pending_deletes'][$k] = [];
                  }
                  $_SESSION['job_card_image_pending_deletes'][$k][] = $oldInfo['path'];
                }
              }
              $_SESSION[$targetImageUploadSessionKey] = [];
            }
            #セッションにファイル情報を保存
            if (!isset($_SESSION[$targetImageUploadSessionKey])) {
              $_SESSION[$targetImageUploadSessionKey] = [];
            }
            $_SESSION[$targetImageUploadSessionKey][] = [
              'kind' => 'tmp',
              'tmp_name' => $savePath,
              'preview' => $previewPath,
              'name' => $uniqueName,
              'original' => $file['name'],
              'type' => $file['type'],
              'size' => $file['size'],
              'uploaded_at' => time(),
            ];
            $makeTag['status'] = 'success';
            $makeTag['file_url'] = '/tmp_upload/' . $uniqueName;
            $makeTag['file_name'] = $uniqueName;
            #sourceタグ用MIMEタイプ設定
            $mimeType = jobCardImageMimeTypeFromExt($ext);
            #プレビュー用タグ生成
            $makeTag['tag'] .= <<<HTML
                  <li>
                    <div class="warp-btn">
                      <button type="button" class="btn-change"></button>
                      <button type="button" class="btn-delate"></button>
                    </div>
                    <picture>
                      <source src="{$previewPath}" type="{$mimeType}">
                      <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                    </picture>
                  </li>

HTML;
          } else {
            $makeTag['status'] = 'error';
            $makeTag['title'] = 'アップロード失敗';
            $makeTag['msg'] = 'ファイルの保存に失敗しました。';
          }
        }
      } else {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = 'ファイルがアップロードされていません。';
      }
      header('Content-Type: application/json');
      echo json_encode($makeTag);
      exit;
    }
    #***** 画像入れ替え（プレビューからの入れ替え） *****#
  case 'replaceUploadImage': {
      #エリア名が未指定の場合はエラー応答
      if (empty($targetImageUploadSessionKey)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = 'アップロードエリア名が指定されていません。';
        echo json_encode($makeTag);
        exit;
      }
      $replaceIndex = isset($_POST['replace_index']) ? intval($_POST['replace_index']) : null;
      $makeTag['file_url'] = '';
      $makeTag['file_name'] = '';
      $file = isset($_FILES['images_tmp']) ? $_FILES['images_tmp'] : null;
      $ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
      $allowed = ['jpg', 'jpeg', 'png', 'gif'];
      if ($replaceIndex !== null && $file && is_uploaded_file($file['tmp_name']) && in_array($ext, $allowed)) {
        $jobIdForDraft = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
        if ($jobIdForDraft > 0) {
          jobCardInitImageSessionFromDB($targetImageUploadSessionKey, $jobIdForDraft);
        }
        if (!isset($_SESSION[$targetImageUploadSessionKey]) || !is_array($_SESSION[$targetImageUploadSessionKey])) {
          $_SESSION[$targetImageUploadSessionKey] = [];
        }
        if (!isset($_SESSION[$targetImageUploadSessionKey][$replaceIndex])) {
          $makeTag['status'] = 'error';
          $makeTag['title'] = 'アップロード失敗';
          $makeTag['msg'] = '入れ替え対象がありません。';
        } else {
          $tmpDir = __DIR__ . '/../../../tmp_upload/';
          if (!file_exists($tmpDir)) mkdir($tmpDir, 0777, true);
          $uniqueName = 'image_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $savePath = $tmpDir . $uniqueName;
          $previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $uniqueName;
          if (move_uploaded_file($file['tmp_name'], $savePath)) {
            $old = $_SESSION[$targetImageUploadSessionKey][$replaceIndex];
            if (is_array($old)) {
              if (isset($old['tmp_name']) && is_string($old['tmp_name']) && $old['tmp_name'] !== '' && file_exists($old['tmp_name'])) {
                @unlink($old['tmp_name']);
              } elseif (isset($old['path']) && is_string($old['path']) && $old['path'] !== '' && $jobIdForDraft > 0) {
                if (!isset($_SESSION['job_card_image_pending_deletes'])) {
                  $_SESSION['job_card_image_pending_deletes'] = [];
                }
                $k = (string)$jobIdForDraft;
                if (!isset($_SESSION['job_card_image_pending_deletes'][$k]) || !is_array($_SESSION['job_card_image_pending_deletes'][$k])) {
                  $_SESSION['job_card_image_pending_deletes'][$k] = [];
                }
                $_SESSION['job_card_image_pending_deletes'][$k][] = $old['path'];
              }
            }
            $_SESSION[$targetImageUploadSessionKey][$replaceIndex] = [
              'kind' => 'tmp',
              'tmp_name' => $savePath,
              'preview' => $previewPath,
              'name' => $uniqueName,
              'original' => $file['name'],
              'type' => $file['type'],
              'size' => $file['size'],
              'uploaded_at' => time(),
            ];
            $makeTag['status'] = 'success';
            $makeTag['file_url'] = '/tmp_upload/' . $uniqueName;
            $makeTag['file_name'] = $uniqueName;
          } else {
            $makeTag['status'] = 'error';
            $makeTag['title'] = 'アップロード失敗';
            $makeTag['msg'] = 'ファイルの保存に失敗しました。';
          }
        }
      } else {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = '入れ替え対象がありません。';
      }
      #プレビュータグ再生成（セッションのドラフト状態から）
      $makeTag['tag'] = jobCardBuildPreviewTagsFromSession($targetImageUploadSessionKey);
      header('Content-Type: application/json');
      echo json_encode($makeTag);
      exit;
    }
    #***** 画像削除（プレビュー or 本体からの削除） *****#
  case 'deleteUploadImage': {
      #エリア名が未指定の場合はエラー応答
      if (empty($targetImageUploadSessionKey)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = 'アップロードエリア名が指定されていません。';
        echo json_encode($makeTag);
        exit;
      }
      $fileName = isset($_POST['file_name']) ? $_POST['file_name'] : '';
      $jobIdForDraft = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
      if ($jobIdForDraft > 0) {
        jobCardInitImageSessionFromDB($targetImageUploadSessionKey, $jobIdForDraft);
      }
      if ($fileName === '') {
        $makeTag['status'] = 'error';
        $makeTag['title'] = '削除失敗';
        $makeTag['msg'] = '削除対象が指定されていません。';
      } else {
        if (!isset($_SESSION[$targetImageUploadSessionKey]) || !is_array($_SESSION[$targetImageUploadSessionKey])) {
          $_SESSION[$targetImageUploadSessionKey] = [];
        }
        $found = false;
        foreach ($_SESSION[$targetImageUploadSessionKey] as $idx => $info) {
          if (!is_array($info)) {
            continue;
          }
          $isTmp = (isset($info['name']) && is_string($info['name']) && $info['name'] !== '');
          $isDb = (isset($info['path']) && is_string($info['path']) && $info['path'] !== '');
          $match = false;
          if ($isTmp && $info['name'] === $fileName) {
            $match = true;
          } elseif ($isDb) {
            $base = pathinfo((string)$info['path'])['basename'] ?? '';
            if ($base === $fileName) {
              $match = true;
            }
          }
          if (!$match) {
            continue;
          }
          if ($isTmp && isset($info['tmp_name']) && is_string($info['tmp_name']) && $info['tmp_name'] !== '' && file_exists($info['tmp_name'])) {
            @unlink($info['tmp_name']);
          } elseif ($isDb && $jobIdForDraft > 0) {
            if (!isset($_SESSION['job_card_image_pending_deletes'])) {
              $_SESSION['job_card_image_pending_deletes'] = [];
            }
            $k = (string)$jobIdForDraft;
            if (!isset($_SESSION['job_card_image_pending_deletes'][$k]) || !is_array($_SESSION['job_card_image_pending_deletes'][$k])) {
              $_SESSION['job_card_image_pending_deletes'][$k] = [];
            }
            $_SESSION['job_card_image_pending_deletes'][$k][] = $info['path'];
          }
          array_splice($_SESSION[$targetImageUploadSessionKey], $idx, 1);
          $found = true;
          break;
        }
        if ($found) {
          $makeTag['status'] = 'success';
        } else {
          $makeTag['status'] = 'error';
          $makeTag['title'] = '削除失敗';
          $makeTag['msg'] = '削除対象がありません。';
        }
      }
      header('Content-Type: application/json');
      echo json_encode($makeTag);
      exit;
    }
    break;
  #***** 登録 *****#
  case 'sendInput': {
      try {
        #トランザクション開始
        # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
        $result = DB_Transaction(1);
        if ($result == false) {
          #エラーログ出力
          $data = [
            'pageName' => 'proc_master03_02_01',
            'reason' => 'トランザクション開始失敗',
          ];
          makeLog($data);
          $makeTag['status'] = 'error';
          $makeTag['title'] = '登録エラー';
          $makeTag['msg'] = 'トランザクション開始に失敗しました。';
          header('Content-Type: application/json');
          echo json_encode($makeTag);
          exit;
        } else {
          #DB登録結果フラグ：初期化
          $dbCompleteFlg = true;
          #---------------------------------------------
          # ファイル操作は「コミット後」に確定する
          #  - DB失敗/ロールバック時は tmp/セッションを掃除
          #---------------------------------------------
          $pendingMkdirDirs = [];
          $pendingMoves = [];
          $pendingTmpFiles = [];
          $pendingClearSessions = [];
          $pendingDeleteFiles = [];
          $pendingDeleteDirs = [];
          $imageDraftTouched = false;
          #DB登録情報準備
          $lstepURLRaw = (!empty($lstep_url)) ? trim($lstep_url) : '';
          #URL用：スペース等（半角/全角含むホワイトスペース）を全て削除
          $lstepURL = preg_replace('/[\s　]+/u', '', $lstepURLRaw);
          if ($lstepURL === '') {
            DB_Transaction(3);
            $makeTag['status'] = 'error';
            $makeTag['title'] = '登録エラー';
            $makeTag['msg'] = 'Lステップの友達追加URLを入力してください。';
            header('Content-Type: application/json');
            echo json_encode($makeTag);
            exit;
          }
          switch ($method) {
            #***** 新規登録 *****#
            case 'new': {
                #登録用配列：初期化
                $dbFiledData = array();
                #登録情報セット
                $dbFiledData['job_code'] = array(':job_code', $job_code, 0);
                $dbFiledData['facility_id'] = array(':facility_id', $facId, 1);
                $dbFiledData['job_category_id'] = array(':job_category_id', $job_category, 0);
                $dbFiledData['employment_type_id'] = array(':employment_type_id', $employment_type, 0);
                $dbFiledData['first_year_income_range_id'] = array(':first_year_income_range_id', $yearly_salary, 0);
                $dbFiledData['card_title'] = array(':card_title', $card_title, 0);
                $dbFiledData['published_start'] = array(':published_start', $published_start, 0);
                $dbFiledData['contract_plan_id'] = array(':contract_plan_id', $contract_plan, 0);
                $dbFiledData['salary_unit_id'] = array(':salary_unit_id', $select_salary, 0);
                $dbFiledData['salary_min'] = array(':salary_min', $salary_min, 1);
                $dbFiledData['salary_max'] = array(':salary_max', $salary_max, 1);
                $dbFiledData['salary_range'] = array(':salary_range', json_encode($bandIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1);
                $dbFiledData['bonus_has_bonus'] = array(':bonus_has_bonus', $bonus, 0);
                $dbFiledData['bonus_note'] = array(':bonus_note', $bonus_note, 0);
                $dbFiledData['lstep_url'] = array(':lstep_url', $lstepURL, 0);
                $dbFiledData['is_active'] = array(':is_active', 1, 1);
                $dbFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
                #更新用キー：初期化
                $dbFiledValue = array();
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 1;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                #求人カード登録完了後に詳細情報を登録する
                if ($dbSuccessFlg == 1) {
                  #追加した求人カードIDを取得（0/不正は致命扱い）
                  $newJobCardIdRaw = $DB_CONNECT->lastInsertId();
                  if (!is_numeric($newJobCardIdRaw) || (int)$newJobCardIdRaw <= 0) {
                    $data = [
                      'pageName' => 'proc_master03_02_01',
                      'reason' => '求人カードID採番失敗（lastInsertId不正）: ' . (string)$newJobCardIdRaw,
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                    break;
                  }
                  $newJobCardId = (int)$newJobCardIdRaw;
                  #--- 画像の本登録処理 ---#
                  #バナー画像登録先ディレクトリ確認・作成
                  $imagePathArr = [];
                  if (!empty($imageUploadSessionKeys)) {
                    foreach ($imageUploadSessionKeys as $area) {
                      if (!isset($_SESSION[$area]) || !is_array($_SESSION[$area])) {
                        continue;
                      }
                      $imageDraftTouched = true;
                      #このエリアは「上書き対象」として明示（空配列も意味を持つ）
                      $imagePathArr[$area] = [];
                      foreach ($_SESSION[$area] as $img) {
                        if (!is_array($img)) {
                          continue;
                        }
                        #DB登録済み画像（ドラフト維持）
                        if (isset($img['path']) && is_string($img['path']) && $img['path'] !== '') {
                          $imagePathArr[$area][] = $img['path'];
                          continue;
                        }
                        #画像アップロード先セッション領域により保存先ディレクトリを変更
                        $ImageDir = rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/facilities/' . $facilityData['facility_code'] . '/';
                        $imageSaveDirName = '';
                        switch ($area) {
                          #PR画像
                          case 'hero_image': {
                              $imageSaveDirName = '';
                            }
                            break;
                          #職場インタビュー
                          case 'interview1_image':
                          case 'interview2_image':
                          case 'interview3_image': {
                              $imageSaveDirName = 'jobs/' . $job_code . '/interview/';
                            }
                            break;
                          #福利厚生
                          case 'benefits1_image':
                          case 'benefits2_image':
                          case 'benefits3_image':
                          case 'benefits4_image': {
                              $imageSaveDirName = 'jobs/' . $job_code . '/benefits/';
                            }
                            break;
                          default:
                            $imageSaveDirName = '';
                            break;
                        }
                        $ImageDir .= $imageSaveDirName;
                        $src = $img['tmp_name'];
                        $dst = rtrim($ImageDir, '/\\') . '/' . $img['name'];
                        if (file_exists($src)) {
                          $pendingMkdirDirs[] = rtrim($ImageDir, '/\\');
                          $pendingMoves[] = ['src' => $src, 'dst' => $dst];
                          $pendingTmpFiles[] = $src;
                          $imagePathArr[$area][] = '/db/images/facilities/' . $facilityData['facility_code'] . '/' . $imageSaveDirName . $img['name'];
                        }
                      }
                      #セッション削除（エリア単位）はコミット後にまとめて実施
                      $pendingClearSessions[] = $area;
                      $pendingClearSessions[] = jobCardImageSessionJobIdKey($area);
                    }
                  }
                  #DB登録済み画像の削除予約（コミット後に実ファイル削除）
                  if ($imageDraftTouched && isset($_SESSION['job_card_image_pending_deletes']) && is_array($_SESSION['job_card_image_pending_deletes'])) {
                    $k = (string)$newJobCardId;
                    if (isset($_SESSION['job_card_image_pending_deletes'][$k]) && is_array($_SESSION['job_card_image_pending_deletes'][$k])) {
                      foreach ($_SESSION['job_card_image_pending_deletes'][$k] as $publicPath) {
                        $full = jobCardPublicImagePathToFullPath((string)$publicPath);
                        if ($full !== '') {
                          $pendingDeleteFiles[] = $full;
                        }
                      }
                    }
                  }
                  #PR画像パスをDBに登録する
                  if (!empty($imageUploadSessionKeys)) {
                    foreach ($imageUploadSessionKeys as $area) {
                      if (isset($area) && $area === 'hero_image') {
                        $saveImagePathArr = json_encode($imagePathArr['hero_image'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($saveImagePathArr === '[]' || $saveImagePathArr === false) {
                          $saveImagePathArr = '[]';
                        }
                        #登録用配列：初期化
                        $dbImageFiledData = array();
                        #登録情報セット
                        $dbImageFiledData['hero_image_primary'] = array(':hero_image_primary', $saveImagePathArr, 0);
                        #更新用キー：初期化
                        $dbImageFiledValue = array();
                        $dbImageFiledValue['job_id'] = array(':job_id', $newJobCardId, 1);
                        $dbImageFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                        #処理モード：[1].新規追加｜[2].更新｜[3].削除
                        $imageProcessFlg = 2;
                        #実行モード：[1].トランザクション｜[2].即実行
                        $imageExeFlg = 2;
                        #DB更新
                        $dbImageSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbImageFiledData, $dbImageFiledValue, $imageProcessFlg, $imageExeFlg);
                        if ($dbImageSuccessFlg != 1) {
                          #エラーログ出力
                          $data = [
                            'pageName' => 'proc_master03_02_01',
                            'reason' => 'PR画像パスDB登録失敗',
                          ];
                          makeLog($data);
                          #DB登録失敗
                          $dbCompleteFlg = false;
                        }
                      } else {
                        continue;
                      }
                    }
                  }
                  #--- ここまで画像の本登録処理 ---#

                  #--- 各ドキュメントjsonデータ作成 ---#
                  #契約プランによる処理分岐
                  switch ($contract_plan) {
                    #スタンダードプラン／プレミアムプラン
                    case 'standard':
                    case 'premium': {
                        #スタンダードプラン用JSONデータ作成
                        $jobCardPlanResult = createJobCard_Plan_JSON($newJobCardId, $contract_plan, $imagePathArr);
                        if ($jobCardPlanResult == false) {
                          #エラーログ出力
                          $data = [
                            'pageName' => 'proc_master03_02_01',
                            'reason' => '求人カードプラン用JSON作成失敗',
                          ];
                          makeLog($data);
                          #DB登録失敗
                          $dbCompleteFlg = false;
                        }
                      }
                      break;
                    default:
                      break;
                  }
                  #フリーテキストJSON作成
                  $db_sections = [];
                  for ($i = 1; $i <= 4; $i++) {
                    $heading = ${"free_text{$i}_heading"} ?? '';
                    $body    = ${"free_text{$i}_body"} ?? '';
                    if (!empty($heading) || !empty($body)) {
                      $db_sections[] = [
                        'heading' => $heading,
                        'body'    => $body,
                      ];
                    }
                  }
                  #タイトル・セクション配列が有ればDB登録
                  if (!empty($free_text_title) || !empty($db_sections)) {
                    $makeFreeTextJson = array(
                      'title' => $free_text_title ?? '',
                      'sections' => $db_sections,
                    );
                    $freeTextJson = json_encode($makeFreeTextJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    #フリーテキスト登録用配列：初期化
                    $dbFreeTextFiledData = array();
                    #登録情報セット
                    $dbFreeTextFiledData['job_id'] = array(':job_id', $newJobCardId, 1);
                    $dbFreeTextFiledData['doc_type'] = array(':doc_type', 'freespace', 0);
                    $dbFreeTextFiledData['enabled'] = array(':enabled', 1, 1);
                    $dbFreeTextFiledData['content_json'] = array(':content_json', $freeTextJson, 0);
                    $dbFreeTextFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
                    #更新用キー：初期化
                    $dbFreeTextFiledValue = array();
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $freeTextProcessFlg = 1;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $freeTextExeFlg = 2;
                    #DB更新
                    $dbFreeTextSuccessFlg = SQL_Process($DB_CONNECT, "job_documents", $dbFreeTextFiledData, $dbFreeTextFiledValue, $freeTextProcessFlg, $freeTextExeFlg);
                    if ($dbFreeTextSuccessFlg == 1) {
                      #------------------------------------------------------------------------------
                      # 求人カード、フリーテキストDB更新完了のJSONファイル作成；job_〇〇〇_freespace.json
                      #------------------------------------------------------------------------------
                      #json保存先
                      $freeSpaceJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
                      if (!is_dir($freeSpaceJsonSaveDir)) {
                        @mkdir($freeSpaceJsonSaveDir, 0777, true);
                      }
                      #書き込み用jsonファイルが無い場合はベースファイルを作成
                      $freeSpaceJsonFile = $job_code . '_freespace.json';
                      if (!file_exists($freeSpaceJsonSaveDir . '/' . $freeSpaceJsonFile)) {
                        makeJson($freeSpaceJsonSaveDir, $freeSpaceJsonFile);
                      }
                      #テキストデータフォーマットと入力チェック
                      $sections = [];
                      for ($i = 1; $i <= 4; $i++) {
                        $heading = ${"free_text{$i}_heading"} ?? '';
                        $bodyRaw = ${"free_text{$i}_body"} ?? '';
                        $body = $bodyRaw ? formatTextareaForDB($bodyRaw) : '';
                        if (!empty($heading) || !empty($body)) {
                          $sections[] = [
                            'heading' => $heading,
                            'body'    => $body,
                          ];
                        }
                      }
                      #JSONデータ生成
                      $freeTextJsonData = [];
                      $freeTextJsonData = [
                        'title'    => $free_text_title ?? '',
                        'sections' => $sections,
                      ];
                      #JSONエンコード
                      $news_freeText_json = json_encode($freeTextJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                      #ファイル書き込み
                      $write_freeText_json = fopen($freeSpaceJsonSaveDir . '/' . $freeSpaceJsonFile, "w");
                      fwrite($write_freeText_json, $news_freeText_json);
                      fclose($write_freeText_json);
                    } else {
                      #エラーログ出力
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'フリーテキストDB登録失敗',
                      ];
                      makeLog($data);
                      #DB登録失敗
                      $dbCompleteFlg = false;
                    }
                  }
                  #--- ここまで各ドキュメントjsonデータ作成 ---#

                  #--- オプション情報登録 ---#
                  #給与備考
                  if (!DB_jobSet_option_text($DB_CONNECT, $newJobCardId, 'salary_note', $salary_note ?? '')) {
                    $data = [
                      'pageName' => 'proc_master03_02_01',
                      'reason' => '給与備考DB登録失敗',
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                  }
                  #------------------------------
                  #職場環境の特徴
                  $WorkEnvironmentMetrics = [];
                  for ($i = 1; $i <= 3; $i++) {
                    $WorkEnvironmentMetric = ${"selectedMetricArea" . $i} ?? '';
                    $WorkEnvironmentMetricValue = ${"metricArea" . $i . "Value"} ?? '';
                    $WorkEnvironmentMetricUnit = ${"metricArea" . $i . "Unit"} ?? '';
                    if ($WorkEnvironmentMetric !== '' && $WorkEnvironmentMetricValue !== '') {
                      $WorkEnvironmentMetrics[] = [
                        'metric_id' => $WorkEnvironmentMetric,
                        'value' => $WorkEnvironmentMetricValue,
                        'unit' => $WorkEnvironmentMetricUnit,
                      ];
                    }
                  }
                  if (!DB_jobReplaceWorkEnvironment_metrics($DB_CONNECT, $newJobCardId, $WorkEnvironmentMetrics)) {
                    $data = [
                      'pageName' => 'proc_master03_02_01',
                      'reason' => '職場環境の特徴DB登録失敗',
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                  }
                  #------------------------------
                  #一日の流れ
                  $dailySchedule = [];
                  #日勤
                  $dayShift = [];
                  for ($i = 0; $i < count($day_list_hour); $i++) {
                    $time = $day_list_hour[$i] ?? '';
                    $min = $day_list_min[$i] ?? '00';
                    $body = $day_list_body[$i] ?? '';
                    if ($time !== '' && $body !== '') {
                      $dayShift[] = [
                        'time' => $time . ':' . $min,
                        'body' => $body
                      ];
                    }
                  }
                  #夜勤
                  $nightShift = [];
                  for ($i = 0; $i < count($night_list_hour); $i++) {
                    $time = $night_list_hour[$i] ?? '';
                    $min = $night_list_min[$i] ?? '00';
                    $body = $night_list_body[$i] ?? '';
                    if ($time !== '' && $body !== '') {
                      $nightShift[] = [
                        'time' => $time . ':' . $min,
                        'body' => $body
                      ];
                    }
                  }
                  if (!empty($dayShift)) {
                    $dailySchedule['dayShift'] = $dayShift;
                  }
                  if (!empty($nightShift)) {
                    $dailySchedule['nightShift'] = $nightShift;
                  }
                  if (!empty($dailySchedule)) {
                    $dailyScheduleJson = json_encode($dailySchedule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (!DB_jobSet_document_json($DB_CONNECT, $newJobCardId, 'dailySchedule', $dailyScheduleJson, 1)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => '1日の流れDB登録失敗',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  } else {
                    if (!DB_jobSet_document_json($DB_CONNECT, $newJobCardId, 'dailySchedule', '', 1)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => '1日の流れDB削除失敗（空登録）',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                  #----------------------------------
                  #複数選択オプション
                  $optionLinkGroups = [
                    'job_content' => $job_content ?? [],
                    'clinical_department' => $clinical_department ?? [],
                    'service_type' => $service_type ?? [],
                    'benefits' => $benefits ?? [],
                    'work_style' => $work_style ?? [],
                    'holidays' => $holidays ?? [],
                    'requirements' => $requirements ?? [],
                    'supports' => $supports ?? [],
                    'accesses' => $accesses ?? [],
                  ];
                  foreach ($optionLinkGroups as $groupCode => $optionIds) {
                    if (!DB_jobReplace_option_links($DB_CONNECT, $newJobCardId, $groupCode, $optionIds)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'オプションDB登録失敗(' . $groupCode . ')',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                  #----------------------------------
                  #オプション備考
                  $optionTextGroups = [
                    'job_content' => $job_content_notice ?? '',
                    'benefits' => $benefits_notice ?? '',
                    'work_style' => $work_style_notice ?? '',
                    'holidays' => $holidays_notice ?? '',
                    'long_term_holiday' => $long_term_holiday_notice ?? '',
                    'requirements' => $requirement_notice ?? '',
                    'welcome_requirements' => $welcome_notice ?? '',
                    'supports' => $support_notice ?? '',
                    'accesses' => $access_notice ?? '',
                    'selection_process' => $selection_process ?? '',
                  ];
                  foreach ($optionTextGroups as $groupCode => $text) {
                    if (!DB_jobSet_option_text($DB_CONNECT, $newJobCardId, $groupCode, $text)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'オプション備考DB登録失敗(' . $groupCode . ')',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                  #--- ここまでオプション情報登録 ---#
                } else {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カードDB登録失敗',
                  ];
                  makeLog($data);
                  #DB登録失敗
                  $dbCompleteFlg = false;
                }
              }
              break;
            #***** 編集 *****#
            case 'edit': {
                if (!is_numeric($jobId) || (int)$jobId <= 0) {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カードID未指定（編集）',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                  break;
                }
                $jobId = (int)$jobId;
                #登録用配列：初期化
                $dbFiledData = array();
                #登録情報セット
                $dbFiledData['job_category_id'] = array(':job_category_id', $job_category, 0);
                $dbFiledData['employment_type_id'] = array(':employment_type_id', $employment_type, 0);
                $dbFiledData['first_year_income_range_id'] = array(':first_year_income_range_id', $yearly_salary, 0);
                $dbFiledData['card_title'] = array(':card_title', $card_title, 0);
                $dbFiledData['published_start'] = array(':published_start', $published_start, 0);
                $dbFiledData['contract_plan_id'] = array(':contract_plan_id', $contract_plan, 0);
                $dbFiledData['salary_unit_id'] = array(':salary_unit_id', $select_salary, 0);
                $dbFiledData['salary_min'] = array(':salary_min', $salary_min, 1);
                $dbFiledData['salary_max'] = array(':salary_max', $salary_max, 1);
                $dbFiledData['salary_range'] = array(':salary_range', json_encode($bandIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1);
                $dbFiledData['bonus_has_bonus'] = array(':bonus_has_bonus', $bonus, 0);
                $dbFiledData['bonus_note'] = array(':bonus_note', $bonus_note, 0);
                $dbFiledData['lstep_url'] = array(':lstep_url', $lstepURL, 0);
                $dbFiledData['is_active'] = array(':is_active', $job_status, 1);
                $dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
                #更新用キー：初期化
                $dbFiledValue = array();
                $dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
                $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 2;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                #求人カード登録完了後に詳細情報を登録する
                if ($dbSuccessFlg == 1) {

                  #--- 画像の本登録処理 ---#
                  #バナー画像登録先ディレクトリ確認・作成
                  $imagePathArr = [];
                  if (!empty($imageUploadSessionKeys)) {
                    foreach ($imageUploadSessionKeys as $area) {
                      if (!isset($_SESSION[$area]) || !is_array($_SESSION[$area])) {
                        continue;
                      }
                      $imageDraftTouched = true;
                      #このエリアは「上書き対象」として明示（空配列も意味を持つ）
                      $imagePathArr[$area] = [];
                      foreach ($_SESSION[$area] as $img) {
                        if (!is_array($img)) {
                          continue;
                        }
                        #DB登録済み画像（ドラフト維持）
                        if (isset($img['path']) && is_string($img['path']) && $img['path'] !== '') {
                          $imagePathArr[$area][] = $img['path'];
                          continue;
                        }
                        #画像アップロード先セッション領域により保存先ディレクトリを変更
                        $ImageDir = rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/facilities/' . $facilityData['facility_code'] . '/';
                        $imageSaveDirName = '';
                        switch ($area) {
                          #PR画像
                          case 'hero_image': {
                              $imageSaveDirName = '';
                            }
                            break;
                          #職場インタビュー
                          case 'interview1_image':
                          case 'interview2_image':
                          case 'interview3_image': {
                              $imageSaveDirName = 'jobs/' . $job_code . '/interview/';
                            }
                            break;
                          #福利厚生
                          case 'benefits1_image':
                          case 'benefits2_image':
                          case 'benefits3_image':
                          case 'benefits4_image': {
                              $imageSaveDirName = 'jobs/' . $job_code . '/benefits/';
                            }
                            break;
                          default:
                            $imageSaveDirName = '';
                            break;
                        }
                        $ImageDir .= $imageSaveDirName;
                        $src = $img['tmp_name'];
                        $dst = rtrim($ImageDir, '/\\') . '/' . $img['name'];
                        if (file_exists($src)) {
                          $pendingMkdirDirs[] = rtrim($ImageDir, '/\\');
                          $pendingMoves[] = ['src' => $src, 'dst' => $dst];
                          $pendingTmpFiles[] = $src;
                          $imagePathArr[$area][] = '/db/images/facilities/' . $facilityData['facility_code'] . '/' . $imageSaveDirName . $img['name'];
                        }
                      }
                      #セッション削除（エリア単位）はコミット後にまとめて実施
                      $pendingClearSessions[] = $area;
                    }
                  }
                  #DB登録済み画像の削除予約（コミット後に実ファイル削除）
                  if ($imageDraftTouched && isset($_SESSION['job_card_image_pending_deletes']) && is_array($_SESSION['job_card_image_pending_deletes'])) {
                    $k = (string)$jobId;
                    if (isset($_SESSION['job_card_image_pending_deletes'][$k]) && is_array($_SESSION['job_card_image_pending_deletes'][$k])) {
                      foreach ($_SESSION['job_card_image_pending_deletes'][$k] as $publicPath) {
                        $full = jobCardPublicImagePathToFullPath((string)$publicPath);
                        if ($full !== '') {
                          $pendingDeleteFiles[] = $full;
                        }
                      }
                    }
                  }
                  #PR画像パスをDBに登録する
                  if (!empty($imageUploadSessionKeys)) {
                    #求人カード情報取得
                    $jobData = getJob_FindById($jobId);
                    #登録済み画像リスト取得
                    $heroImagePrimary = json_decode($jobData['hero_image_primary'], true);
                    foreach ($imageUploadSessionKeys as $area) {
                      if (isset($area) && $area === 'hero_image' && $targetImageUploadSessionKey === 'hero_image') {
                        $sessionHeroExists = (isset($_SESSION['hero_image']) && is_array($_SESSION['hero_image']));
                        $pendingDeletesForJob = [];
                        if (isset($_SESSION['job_card_image_pending_deletes']) && is_array($_SESSION['job_card_image_pending_deletes'])) {
                          $k = (string)$jobId;
                          if (isset($_SESSION['job_card_image_pending_deletes'][$k]) && is_array($_SESSION['job_card_image_pending_deletes'][$k])) {
                            $pendingDeletesForJob = $_SESSION['job_card_image_pending_deletes'][$k];
                          }
                        }
                        $newHeroImages = $imagePathArr['hero_image'] ?? null;
                        #セッションが無い場合は何もしない（既存維持）
                        if (!$sessionHeroExists) {
                          continue;
                        }
                        if (!is_array($newHeroImages)) {
                          $newHeroImages = [];
                        }
                        #セッションが「新規追加だけ」（DB画像を持っていない）ケースは、削除予約が無い限り既存とマージ
                        $heroSessionHasDb = false;
                        foreach ($_SESSION['hero_image'] as $it) {
                          if (is_array($it) && isset($it['path']) && is_string($it['path']) && $it['path'] !== '') {
                            $heroSessionHasDb = true;
                            break;
                          }
                        }
                        if (!$heroSessionHasDb && empty($pendingDeletesForJob)) {
                          if (!is_array($heroImagePrimary)) {
                            $heroImagePrimary = [];
                          }
                          $newHeroImages = array_merge($heroImagePrimary, $newHeroImages);
                        }
                        $saveImagePathArr = json_encode(array_values($newHeroImages), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        if ($saveImagePathArr === false) {
                          continue;
                        }
                        #登録用配列：初期化
                        $dbImageFiledData = array();
                        #登録情報セット
                        $dbImageFiledData['hero_image_primary'] = array(':hero_image_primary', $saveImagePathArr, 0);
                        #更新用キー：初期化
                        $dbImageFiledValue = array();
                        $dbImageFiledValue['job_id'] = array(':job_id', $jobId, 1);
                        $dbImageFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                        #処理モード：[1].新規追加｜[2].更新｜[3].削除
                        $imageProcessFlg = 2;
                        #実行モード：[1].トランザクション｜[2].即実行
                        $imageExeFlg = 2;
                        #DB更新
                        $dbImageSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbImageFiledData, $dbImageFiledValue, $imageProcessFlg, $imageExeFlg);
                        if ($dbImageSuccessFlg != 1) {
                          #エラーログ出力
                          $data = [
                            'pageName' => 'proc_master03_02_01',
                            'reason' => 'PR画像パスDB登録失敗',
                          ];
                          makeLog($data);
                          #DB登録失敗
                          $dbCompleteFlg = false;
                        }
                      } else {
                        continue;
                      }
                    }
                  }
                  #--- ここまで画像の本登録処理 ---#

                  #--- 各ドキュメントjsonデータ作成 ---#
                  #契約プランによる処理分岐
                  switch ($contract_plan) {
                    #スタンダードプラン／プレミアムプラン
                    case 'standard':
                    case 'premium': {
                        #スタンダードプラン用JSONデータ作成
                        $jobCardPlanResult = createJobCard_Plan_JSON($jobId, $contract_plan, $imagePathArr);
                        if ($jobCardPlanResult == false) {
                          #エラーログ出力
                          $data = [
                            'pageName' => 'proc_master03_02_01',
                            'reason' => '求人カードプラン用JSON作成失敗',
                          ];
                          makeLog($data);
                          #DB登録失敗
                          $dbCompleteFlg = false;
                        }
                      }
                      break;
                    default:
                      break;
                  }
                  #フリーテキストJSON作成
                  $db_sections = [];
                  for ($i = 1; $i <= 4; $i++) {
                    $heading = ${"free_text{$i}_heading"} ?? '';
                    $body    = ${"free_text{$i}_body"} ?? '';
                    if (!empty($heading) || !empty($body)) {
                      $db_sections[] = [
                        'heading' => $heading,
                        'body'    => $body,
                      ];
                    }
                  }
                  #タイトル・セクション配列が有ればDB登録
                  if (!empty($free_text_title) || !empty($db_sections)) {
                    $makeFreeTextJson = array(
                      'title' => $free_text_title ?? '',
                      'sections' => $db_sections,
                    );
                    $freeTextJson = json_encode($makeFreeTextJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    #
                    $dbFreeTextSuccessFlg = DB_jobSet_document_json($DB_CONNECT, $jobId, 'freespace', $freeTextJson, 1);
                    if ($dbFreeTextSuccessFlg) {
                      #------------------------------------------------------------------------------
                      # 求人カード、フリーテキストDB更新完了のJSONファイル作成；job_〇〇〇_freespace.json
                      #------------------------------------------------------------------------------
                      #json保存先
                      $freeSpaceJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
                      if (!is_dir($freeSpaceJsonSaveDir)) {
                        @mkdir($freeSpaceJsonSaveDir, 0777, true);
                      }
                      #書き込み用jsonファイルが無い場合はベースファイルを作成
                      $freeSpaceJsonFile = $job_code . '_freespace.json';
                      if (!file_exists($freeSpaceJsonSaveDir . '/' . $freeSpaceJsonFile)) {
                        makeJson($freeSpaceJsonSaveDir, $freeSpaceJsonFile);
                      }
                      #テキストデータフォーマットと入力チェック
                      $sections = [];
                      for ($i = 1; $i <= 4; $i++) {
                        $heading = ${"free_text{$i}_heading"} ?? '';
                        $bodyRaw = ${"free_text{$i}_body"} ?? '';
                        $body = $bodyRaw ? formatTextareaForDB($bodyRaw) : '';
                        if (!empty($heading) || !empty($body)) {
                          $sections[] = [
                            'heading' => $heading,
                            'body'    => $body,
                          ];
                        }
                      }
                      #JSONデータ生成
                      $freeTextJsonData = [];
                      $freeTextJsonData = [
                        'title'    => $free_text_title ?? '',
                        'sections' => $sections,
                      ];
                      #JSONエンコード
                      $news_freeText_json = json_encode($freeTextJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                      #ファイル書き込み
                      $write_freeText_json = fopen($freeSpaceJsonSaveDir . '/' . $freeSpaceJsonFile, "w");
                      fwrite($write_freeText_json, $news_freeText_json);
                      fclose($write_freeText_json);
                    } else {
                      #エラーログ出力
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'フリーテキストDB更新失敗',
                      ];
                      makeLog($data);
                      #DB更新失敗
                      $dbCompleteFlg = false;
                    }
                  } else {
                    #入力が空の場合は既存フリーテキストを削除
                    if (!DB_jobSet_document_json($DB_CONNECT, $jobId, 'freespace', '', 1)) {
                      #エラーログ出力
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'フリーテキストDB削除失敗（空更新）',
                      ];
                      makeLog($data);
                      #DB更新失敗
                      $dbCompleteFlg = false;
                    }
                  }
                  #--- ここまで各ドキュメントjsonデータ作成 ---#

                  #--- オプション情報登録 ---#
                  #----------------------------------
                  #給与備考
                  if (!DB_jobSet_option_text($DB_CONNECT, $jobId, 'salary_note', $salary_note ?? '')) {
                    $data = [
                      'pageName' => 'proc_master03_02_01',
                      'reason' => '給与備考DB登録失敗',
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                  }
                  #-------------------------------
                  #職場環境の特徴
                  $WorkEnvironmentMetrics = [];
                  for ($i = 1; $i <= 3; $i++) {
                    $WorkEnvironmentMetric = ${"selectedMetricArea" . $i} ?? '';
                    $WorkEnvironmentMetricValue = ${"metricArea" . $i . "Value"} ?? '';
                    $WorkEnvironmentMetricUnit = ${"metricArea" . $i . "Unit"} ?? '';
                    if ($WorkEnvironmentMetric !== '' && $WorkEnvironmentMetricValue !== '') {
                      $WorkEnvironmentMetrics[] = [
                        'metric_id' => $WorkEnvironmentMetric,
                        'value' => $WorkEnvironmentMetricValue,
                        'unit' => $WorkEnvironmentMetricUnit,
                      ];
                    }
                  }
                  if (!DB_jobReplaceWorkEnvironment_metrics($DB_CONNECT, $jobId, $WorkEnvironmentMetrics)) {
                    $data = [
                      'pageName' => 'proc_master03_02_01',
                      'reason' => '職場環境の特徴DB登録失敗',
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                  }
                  #------------------------------
                  #一日の流れ
                  $dailySchedule = [];
                  #日勤
                  $dayShift = [];
                  for ($i = 0; $i < count($day_list_hour); $i++) {
                    $time = $day_list_hour[$i] ?? '';
                    $min = $day_list_min[$i] ?? '00';
                    $body = $day_list_body[$i] ?? '';
                    if ($time !== '' && $body !== '') {
                      $dayShift[] = [
                        'time' => $time . ':' . $min,
                        'body' => $body
                      ];
                    }
                  }
                  #夜勤
                  $nightShift = [];
                  for ($i = 0; $i < count($night_list_hour); $i++) {
                    $time = $night_list_hour[$i] ?? '';
                    $min = $night_list_min[$i] ?? '00';
                    $body = $night_list_body[$i] ?? '';
                    if ($time !== '' && $body !== '') {
                      $nightShift[] = [
                        'time' => $time . ':' . $min,
                        'body' => $body
                      ];
                    }
                  }
                  if (!empty($dayShift)) {
                    $dailySchedule['dayShift'] = $dayShift;
                  }
                  if (!empty($nightShift)) {
                    $dailySchedule['nightShift'] = $nightShift;
                  }
                  if (!empty($dailySchedule)) {
                    $dailyScheduleJson = json_encode($dailySchedule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (!DB_jobSet_document_json($DB_CONNECT, $jobId, 'dailySchedule', $dailyScheduleJson, 1)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => '1日の流れDB登録失敗',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  } else {
                    if (!DB_jobSet_document_json($DB_CONNECT, $jobId, 'dailySchedule', '', 1)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => '1日の流れDB削除失敗（空更新）',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                  #----------------------------------
                  #複数選択オプション
                  $optionLinkGroups = [
                    'job_content' => $job_content ?? [],
                    'clinical_department' => $clinical_department ?? [],
                    'service_type' => $service_type ?? [],
                    'benefits' => $benefits ?? [],
                    'work_style' => $work_style ?? [],
                    'holidays' => $holidays ?? [],
                    'requirements' => $requirements ?? [],
                    'supports' => $supports ?? [],
                    'accesses' => $accesses ?? [],
                  ];
                  foreach ($optionLinkGroups as $groupCode => $optionIds) {
                    if (!DB_jobReplace_option_links($DB_CONNECT, $jobId, $groupCode, $optionIds)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'オプションDB登録失敗(' . $groupCode . ')',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                  #----------------------------------
                  #オプション備考
                  $optionTextGroups = [
                    'job_content' => $job_content_notice ?? '',
                    'benefits' => $benefits_notice ?? '',
                    'work_style' => $work_style_notice ?? '',
                    'holidays' => $holidays_notice ?? '',
                    'long_term_holiday' => $long_term_holiday_notice ?? '',
                    'requirements' => $requirement_notice ?? '',
                    'welcome_requirements' => $welcome_notice ?? '',
                    'supports' => $support_notice ?? '',
                    'accesses' => $access_notice ?? '',
                    'selection_process' => $selection_process ?? '',
                  ];
                  foreach ($optionTextGroups as $groupCode => $text) {
                    if (!DB_jobSet_option_text($DB_CONNECT, $jobId, $groupCode, $text)) {
                      $data = [
                        'pageName' => 'proc_master03_02_01',
                        'reason' => 'オプション備考DB登録失敗(' . $groupCode . ')',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                  #--- ここまでオプション情報登録 ---#
                } else {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カードDB更新失敗',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                }
              }
              break;
            #***** プラン変更 *****#
            case 'changePlan': {
                if (!is_numeric($jobId) || (int)$jobId <= 0) {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カードID未指定（編集）',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                  break;
                }
                $jobId = (int)$jobId;
                #=============#
                # POSTチェック
                #-------------#
                #変更するプラン
                $changePlanName = isset($_POST['changePlanName']) ? trim($_POST['changePlanName']) : '';
                if (empty($changePlanName)) {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '変更プラン名未指定（編集）',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                  break;
                }

                #既存情報取得（ダウングレード判定・ファイルパス解決用）
                $jobDataBeforePlanChange = getJob_FindById($jobId);
                $oldPlanName = isset($jobDataBeforePlanChange['contract_plan_id']) ? (string)$jobDataBeforePlanChange['contract_plan_id'] : '';
                $jobCodeForPlanChange = isset($jobDataBeforePlanChange['job_code']) ? (string)$jobDataBeforePlanChange['job_code'] : (string)($job_code ?? '');
                $planRank = function (string $plan): int {
                  switch ($plan) {
                    case 'premium':
                      return 3;
                    case 'standard':
                      return 2;
                    case 'light':
                      return 1;
                    default:
                      return 0;
                  }
                };
                $isDowngrade = ($planRank($changePlanName) > 0 && $planRank($oldPlanName) > 0 && $planRank($changePlanName) < $planRank($oldPlanName));
                #登録用配列：初期化
                $dbFiledData = array();
                #登録情報セット
                $dbFiledData['contract_plan_id'] = array(':contract_plan_id', $changePlanName, 0);
                $dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
                #更新用キー：初期化
                $dbFiledValue = array();
                $dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
                $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 2;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                if ($dbSuccessFlg == 1) {
                  #-------------------------------
                  # プランを「落とした」場合のみ不要データを削除する
                  #  - DB: job_documents（doc_type単位）
                  #  - 画像: jobs/{job_code}/interview, jobs/{job_code}/benefits をコミット後に削除
                  #  - JSON: 新プランで不要なものだけ削除
                  #-------------------------------
                  if ($isDowngrade) {
                    #不要になるdoc_type
                    $purgeDocTypes = [];
                    if ($changePlanName === 'standard') {
                      #premium → standard
                      $purgeDocTypes = ['movie', 'benefits'];
                    } elseif ($changePlanName === 'light') {
                      #premium/standard → light
                      $purgeDocTypes = ['interview', 'movie', 'benefits'];
                    }
                    foreach ($purgeDocTypes as $docType) {
                      if (!DB_jobSet_document_json($DB_CONNECT, $jobId, $docType, '', 1)) {
                        $data = [
                          'pageName' => 'proc_master03_02_01',
                          'reason' => 'プランダウングレード：不要ドキュメント削除失敗(' . $docType . ')',
                        ];
                        makeLog($data);
                        $dbCompleteFlg = false;
                      }
                    }
                    #不要画像ディレクトリ（コミット後に削除）
                    $facilityCodeForPlanChange = isset($facilityData['facility_code']) ? (string)$facilityData['facility_code'] : '';
                    if ($facilityCodeForPlanChange !== '' && $jobCodeForPlanChange !== '') {
                      $jobAssetBaseDir = rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/facilities/' . $facilityCodeForPlanChange . '/jobs/' . $jobCodeForPlanChange;
                      if (in_array('interview', $purgeDocTypes, true)) {
                        $pendingDeleteDirs[] = $jobAssetBaseDir . '/interview';
                      }
                      if (in_array('benefits', $purgeDocTypes, true)) {
                        $pendingDeleteDirs[] = $jobAssetBaseDir . '/benefits';
                      }
                    }
                    #不要JSONファイル削除（job_codeが取れない場合は安全側でスキップ）
                    if ($jobCodeForPlanChange !== '') {
                      $planJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
                      if (!is_dir($planJsonSaveDir)) {
                        @mkdir($planJsonSaveDir, 0777, true);
                      }
                      $planJsonFilesToDelete = [];
                      if ($changePlanName === 'standard') {
                        $planJsonFilesToDelete = [
                          $jobCodeForPlanChange . '_premium.json',
                          $jobCodeForPlanChange . '_benefits.json',
                          $jobCodeForPlanChange . '_movie.json',
                        ];
                      } elseif ($changePlanName === 'light') {
                        $planJsonFilesToDelete = [
                          $jobCodeForPlanChange . '_premium.json',
                          $jobCodeForPlanChange . '_standard.json',
                          $jobCodeForPlanChange . '_interview.json',
                          $jobCodeForPlanChange . '_benefits.json',
                          $jobCodeForPlanChange . '_movie.json',
                        ];
                      }
                      foreach ($planJsonFilesToDelete as $planJson) {
                        deleteFileIfExists($planJsonSaveDir . '/' . $planJson);
                      }
                    }
                  }
                } else {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カードDB更新失敗（プラン変更）',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                }
              }
              break;
            #***** 削除 *****#
            case 'delete': {
                if (!is_numeric($jobId) || (int)$jobId <= 0) {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カードID未指定（削除）',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                  break;
                }
                $jobId = (int)$jobId;
                #登録用配列：初期化
                $dbFiledData = array();
                #削除後に参照できないため、必要情報を先に取得
                $jobDataForDelete = getJob_FindById($jobId);
                $jobCodeForDelete = $jobDataForDelete['job_code'] ?? '';
                $heroImagePrimaryForDelete = $jobDataForDelete['hero_image_primary'] ?? '';
                #更新用キー：初期化
                $dbFiledValue = array();
                $dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
                $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 3;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                if ($dbSuccessFlg == 1) {
                  #関連する登録情報を削除（DB）
                  $deleteOk = true;
                  #ドキュメント（未入力扱い＝DELETEのみ）
                  $docTypesToDelete = ['freespace', 'dailySchedule', 'interview', 'movie', 'benefits'];
                  foreach ($docTypesToDelete as $docType) {
                    if (!DB_jobSet_document_json($DB_CONNECT, $jobId, $docType, '', 1)) {
                      $deleteOk = false;
                    }
                  }
                  #指標（job_id単位で全削除）
                  if (!DB_jobReplaceWorkEnvironment_metrics($DB_CONNECT, $jobId, [])) {
                    $deleteOk = false;
                  }
                  #複数選択オプション（グループ単位で削除）
                  $optionLinkGroupsToDelete = [
                    'job_content',
                    'clinical_department',
                    'service_type',
                    'benefits',
                    'work_style',
                    'holidays',
                    'requirements',
                    'supports',
                    'accesses',
                  ];
                  foreach ($optionLinkGroupsToDelete as $groupCode) {
                    if (!DB_jobReplace_option_links($DB_CONNECT, $jobId, $groupCode, [])) {
                      $deleteOk = false;
                    }
                  }
                  #オプション備考（グループ単位で削除）
                  $optionTextGroupsToDelete = [
                    'salary_note',
                    'job_content',
                    'benefits',
                    'work_style',
                    'holidays',
                    'long_term_holiday',
                    'requirements',
                    'welcome_requirements',
                    'supports',
                    'accesses',
                    'selection_process',
                  ];
                  foreach ($optionTextGroupsToDelete as $groupCode) {
                    if (!DB_jobSet_option_text($DB_CONNECT, $jobId, $groupCode, '')) {
                      $deleteOk = false;
                    }
                  }
                  if (!$deleteOk) {
                    $data = [
                      'pageName' => 'proc_master03_02_01',
                      'reason' => '求人カード関連データ削除（DB）失敗',
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                  }
                  #関連ファイル削除（JSON/画像）
                  if ($dbCompleteFlg === true) {
                    $facilityCodeForDelete = $facilityData['facility_code'] ?? '';
                    #JSONファイル削除
                    if (!empty($jobCodeForDelete) && !empty($facilityCodeForDelete)) {
                      $facilityJsonDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityCodeForDelete;
                      $jobsJsonDir = $facilityJsonDir . '/jobs/';
                      $jsonCandidates = [
                        $jobCodeForDelete . '.json',
                        $jobCodeForDelete . '_standard.json',
                        $jobCodeForDelete . '_premium.json',
                        $jobCodeForDelete . '_freespace.json',
                        $jobCodeForDelete . '_interview.json',
                        $jobCodeForDelete . '_benefits.json',
                        $jobCodeForDelete . '_video.json',
                        $jobCodeForDelete . '_movie.json',
                      ];
                      foreach ($jsonCandidates as $file) {
                        deleteFileIfExists($jobsJsonDir . '/' . $file);
                      }
                      #ディレクトリが空になれば削除
                      deleteDirIfEmpty(rtrim($jobsJsonDir, '/\\'));
                      deleteDirIfEmpty(rtrim($facilityJsonDir, '/\\'));
                    }
                    #画像データ削除（jobs/{job_code}/ 配下を再帰削除）
                    if (!empty($jobCodeForDelete) && !empty($facilityCodeForDelete)) {
                      $facilityImageDir = rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/facilities/' . $facilityCodeForDelete;
                      $jobsImageDir = $facilityImageDir . '/jobs';
                      $jobImageDir = $jobsImageDir . '/' . $jobCodeForDelete;
                      deleteDirRecursive($jobImageDir);
                      #空になれば親ディレクトリも削除
                      deleteDirIfEmpty($jobsImageDir);
                      deleteDirIfEmpty($facilityImageDir);
                    }
                    #PR画像（hero_image_primary）を削除
                    if (!empty($heroImagePrimaryForDelete)) {
                      $decodedHeroImages = json_decode($heroImagePrimaryForDelete, true);
                      if (is_array($decodedHeroImages)) {
                        foreach ($decodedHeroImages as $url) {
                          if (!is_string($url) || $url === '') {
                            continue;
                          }
                          $prefix = '/db/images/';
                          if (strpos($url, $prefix) !== 0) {
                            continue;
                          }
                          $rel = ltrim(substr($url, strlen($prefix)), '/');
                          if ($rel === '') {
                            continue;
                          }
                          $filePath = rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/' . str_replace('\\', '/', $rel);
                          deleteFileIfExists($filePath);
                          #ファイルを削除した結果、空になったディレクトリを片付ける
                          $parentDir = dirname($filePath);
                          deleteDirIfEmpty($parentDir);
                        }
                        #facility配下が空なら削除
                        if (!empty($facilityCodeForDelete)) {
                          $facilityImageDir = rtrim(DEFINE_FILE_DIR_PATH, '/\\') . '/facilities/' . $facilityCodeForDelete;
                          deleteDirIfEmpty($facilityImageDir);
                        }
                      }
                    }
                  }
                } else {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_master03_02_01',
                    'reason' => '求人カード削除（論理削除）DB更新失敗',
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                }
              }
              break;
          }
          #全ての処理成功
          if ($dbCompleteFlg == true) {
            #DBコミット
            # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
            DB_Transaction(2);
            #-------------------------------
            # コミット後の画像ファイル確定処理
            #-------------------------------
            $fsErrors = [];
            $pendingMkdirDirs = array_values(array_unique(array_filter($pendingMkdirDirs, 'is_string')));
            foreach ($pendingMkdirDirs as $dir) {
              if ($dir === '') continue;
              if (!ensureDir($dir)) {
                $fsErrors[] = 'mkdir失敗: ' . $dir;
              }
            }
            $movedTmpFiles = [];
            foreach ($pendingMoves as $m) {
              $src = $m['src'] ?? '';
              $dst = $m['dst'] ?? '';
              if (!is_string($src) || $src === '' || !is_string($dst) || $dst === '') {
                continue;
              }
              if (!safeMoveFile($src, $dst)) {
                $fsErrors[] = 'move失敗: ' . $src . ' -> ' . $dst;
              } else {
                $movedTmpFiles[] = $src;
              }
            }
            #moveに成功したtmpだけ掃除（失敗時にtmpを消すと画像が失われるため）
            cleanupFiles($movedTmpFiles);
            if (!empty($pendingDeleteFiles) && is_array($pendingDeleteFiles)) {
              $pendingDeleteFiles = array_values(array_unique(array_filter($pendingDeleteFiles, 'is_string')));
              cleanupFiles($pendingDeleteFiles);
              foreach ($pendingDeleteFiles as $p) {
                if (!is_string($p) || $p === '') {
                  continue;
                }
                deleteDirIfEmpty(dirname($p));
              }
            }
            #ディレクトリ削除（プランダウングレード等で使用）
            if (!empty($pendingDeleteDirs) && is_array($pendingDeleteDirs)) {
              $pendingDeleteDirs = array_values(array_unique(array_filter($pendingDeleteDirs, 'is_string')));
              foreach ($pendingDeleteDirs as $dir) {
                if ($dir === '' || !is_string($dir)) {
                  continue;
                }
                if (is_dir($dir)) {
                  if (!deleteDirRecursive($dir)) {
                    $fsErrors[] = 'dir削除失敗: ' . $dir;
                  }
                } else {
                  #念のためファイルとしても削除（存在すれば）
                  deleteFileIfExists($dir);
                }
                #親を空判定で削除
                $parent = dirname($dir);
                if (is_string($parent) && $parent !== '') {
                  deleteDirIfEmpty($parent);
                }
              }
            }
            $pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
            foreach ($pendingClearSessions as $sKey) {
              unset($_SESSION[$sKey]);
            }
            #削除予約セッションを掃除
            if (isset($_SESSION['job_card_image_pending_deletes']) && is_array($_SESSION['job_card_image_pending_deletes'])) {
              $clearKey = '';
              if ($method === 'new' && isset($newJobCardId)) {
                $clearKey = (string)$newJobCardId;
              } elseif (isset($jobId) && !empty($jobId)) {
                $clearKey = (string)$jobId;
              }
              if ($clearKey !== '' && isset($_SESSION['job_card_image_pending_deletes'][$clearKey])) {
                unset($_SESSION['job_card_image_pending_deletes'][$clearKey]);
              }
            }
            $makeTag['status'] = 'success';
            switch ($method) {
              #***** 新規登録 *****#
              case 'new': {
                  $makeTag['title'] = '新規求人カード';
                  $makeTag['msg'] = '登録が完了しました。<br>カードは<span style="font-weight:bold;">下書き中</span>で保存されます。<br>公開は、一覧ページより行ってください。';
                  $makeTag['facId'] = $facId;
                  #----------------------------
                  # DB更新完了のJSONファイル作成
                  #----------------------------
                  $cmd = '/usr/bin/php8.3 ' . DEFINE_JSON_FUNCTION_MASTER . '/workJson/makeFacility.php ' . $facId . ' 2>&1 &';
                  exec($cmd, $output, $return_var);
                }
                break;
              #***** 編集 *****#
              case 'edit': {
                  $makeTag['title'] = '求人カード編集';
                  $makeTag['msg'] = '更新が完了しました。';
                  $makeTag['facId'] = $facId;
                  #----------------------------
                  # DB更新完了のJSONファイル作成
                  #----------------------------
                  $cmd = '/usr/bin/php8.3 ' . DEFINE_JSON_FUNCTION_MASTER . '/workJson/makeFacility.php ' . $facId . ' 2>&1 &';
                  exec($cmd, $output, $return_var);
                }
                break;
              #***** プラン変更 *****#
              case 'changePlan': {
                  $makeTag['title'] = '契約プラン';
                  $makeTag['msg'] = 'プランの変更が完了しました。<br>求人カードの内容を確認後「更新」してください。';
                  $makeTag['facId'] = $facId;
                }
                break;
              #***** 削除 *****#
              case 'delete': {
                  $makeTag['title'] = '求人カード削除';
                  $makeTag['msg'] = '削除が完了しました。';
                  $makeTag['facId'] = $facId;
                }
                break;
            }
            if (count($fsErrors) > 0) {
              $data = [
                'pageName' => 'proc_master03_02_01',
                'reason' => 'コミット後の画像確定処理で失敗',
                'fsErrors' => $fsErrors,
              ];
              makeLog($data);
              $makeTag['msg'] .= '<br>※画像確定処理で一部失敗しました。ログをご確認ください。';
            }
            #-----------------------------------------------------
            # DB更新完了のJSONファイル作成（delete、changePlan時以外）
            #-----------------------------------------------------
            if ($method != 'delete' && $method != 'changePlan') {
              #１）カードマスターjson作成：job_〇〇〇.json
              #json保存先
              $jobsCardMasterJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
              if (!is_dir($jobsCardMasterJsonSaveDir)) {
                @mkdir($jobsCardMasterJsonSaveDir, 0777, true);
              }
              #書き込み用jsonファイルが無い場合はベースファイルを作成
              $jobsCardMasterJson = $job_code . '.json';
              if (!file_exists($jobsCardMasterJsonSaveDir . '/' . $jobsCardMasterJson)) {
                makeJson($jobsCardMasterJsonSaveDir, $jobsCardMasterJson);
              }
              #ステータス判定
              switch ($job_status) {
                #下書き中：draft
                case 1:
                  $statusText = 'draft';
                  break;
                #公開中：public
                case 2:
                  $statusText = 'public';
                  break;
                #掲載停止中：private
                case 99:
                  $statusText = 'private';
                  break;
                #デフォルト：下書き中
                default:
                  $statusText = 'draft';
                  break;
              }
              #給与情報
              $salaryData = [];
              $firstYearIncomeRangeId = null;
              if ($select_salary === 'monthly') {
                $salaryData = [
                  'unitId' => $select_salary,
                  'min' => (int)$salary_min,
                  'max' => (int)$salary_max,
                  'bonus' => [
                    'hasBonus' => ($bonus == 1) ? true : false,
                    'note' => $bonus_note,
                  ],
                ];
                if (!empty($yearly_salary)) {
                  $firstYearIncomeRangeId = $yearly_salary;
                }
              } elseif ($select_salary === 'hourly') {
                $salaryData = [
                  'unitId' => $select_salary,
                  'min' => (int)$salary_min,
                  'max' => (int)$salary_max,
                  #'bandId' => $salary_range,
                ];
                $firstYearIncomeRangeId = null;
              }
              #職場環境の特徴
              $WorkEnvironmentMetricData = [];
              for ($i = 1; $i <= 3; $i++) {
                $WorkEnvironmentMetric = ${"selectedMetricArea" . $i} ?? '';
                $WorkEnvironmentMetricValue = ${"metricArea" . $i . "Value"} ?? '';
                if ($WorkEnvironmentMetric !== '' && $WorkEnvironmentMetricValue !== '') {
                  $WorkEnvironmentMetricData[] = [
                    'metricId' => $WorkEnvironmentMetric,
                    'value' => is_numeric($WorkEnvironmentMetricValue) ? (float)$WorkEnvironmentMetricValue : $WorkEnvironmentMetricValue,
                  ];
                }
              }
              #プランにより書き出し情報を変更
              $splitLines = function ($text): array {
                if (is_array($text)) {
                  return $text;
                }
                if ($text === null) {
                  return [];
                }
                $text = (string)$text;
                if ($text === '') {
                  return [];
                }
                return preg_split('/\\R/u', $text);
              };
              $hasNonEmptyLine = function (array $lines): bool {
                foreach ($lines as $line) {
                  if (trim((string)$line) !== '') {
                    return true;
                  }
                }
                return false;
              };
              $planRefs = [];
              $facilityCode = $facilityData['facility_code'];
              $jobsUrlBase = '/db/facilities/' . $facilityCode . '/jobs/';
              #スタンダード/プレミアム：インタビュー参照あり
              if ($contract_plan === 'standard' || $contract_plan === 'premium') {
                $interviewFile = $job_code . '_interview.json';
                $planRefs['interview'] = [
                  'enabled' => file_exists($jobsCardMasterJsonSaveDir . '/' . $interviewFile),
                  'path' => $jobsUrlBase . $interviewFile,
                ];
              }
              #プレミアムのみ：動画/福利厚生/premium参照
              if ($contract_plan === 'premium') {
                $movieFile = $job_code . '_movie.json';
                $benefitsFile = $job_code . '_benefits.json';
                $premiumFile = $job_code . '_premium.json';
                $planRefs = array_merge($planRefs, [
                  'jobVideos' => [
                    'enabled' => file_exists($jobsCardMasterJsonSaveDir . '/' . $movieFile),
                    'path' => $jobsUrlBase . $movieFile,
                  ],
                  'benefitsDetailRef' => [
                    'enabled' => file_exists($jobsCardMasterJsonSaveDir . '/' . $benefitsFile),
                    'path' => $jobsUrlBase . $benefitsFile,
                  ],
                  'premium' => [
                    'enabled' => true,
                    'path' => $jobsUrlBase . $premiumFile,
                  ],
                ]);
              }
              #jsonデータ生成
              $heroImagesForMaster = isset($imagePathArr['hero_image']) ? $imagePathArr['hero_image'] : null;
              if (!is_array($heroImagesForMaster) || empty($heroImagesForMaster)) {
                $jsonJobIdForHero = ($method === 'new') ? $newJobCardId : $jobId;
                $jobDataForHero = getJob_FindById($jsonJobIdForHero);
                if (isset($jobDataForHero['hero_image_primary']) && $jobDataForHero['hero_image_primary'] !== '') {
                  $decodedHero = json_decode($jobDataForHero['hero_image_primary'], true);
                  if (is_array($decodedHero)) {
                    $heroImagesForMaster = $decodedHero;
                  }
                }
              }
              if (!is_array($heroImagesForMaster)) {
                $heroImagesForMaster = [];
              }
              #LステップURL：スペース等（半角/全角含むホワイトスペース）を全て削除
              $lstepURLRaw = (!empty($lstep_url)) ? trim($lstep_url) : '';
              $lstepURL = preg_replace('/[\s　]+/u', '', $lstepURLRaw);
              #job_〇〇〇.json
              $masterJsonData = [];
              $masterJsonData = [
                'id' => $job_code,
                'facilityId' => $facilityData['facility_code'],
                'status' => $statusText,
                'publishedPeriod' => [
                  'start' => $published_start,
                  'end' => null,
                ],
                'lStepUrl' => $lstepURL,
                'jobCategoryId' => $job_category,
                'employmentTypeId' => $employment_type,
                'title' => $card_title,
                'heroImages' => $heroImagesForMaster,
                'clinicalDepartments' => $clinical_department ?? [],
                'salary' => $salaryData,
                'workEnvironmentStats' => $WorkEnvironmentMetricData,
                'contractPlanId' => $contract_plan,
                'updatedAt' => date('Y-m-d'),
              ];
              #----------------------------
              # 任意項目（未入力なら出力しない）
              #----------------------------
              #給与備考（改行配列）
              $salaryNotesLines = $splitLines($salary_note);
              if ($hasNonEmptyLine($salaryNotesLines)) {
                $masterJsonData['salaryNotes'] = $salaryNotesLines;
              }
              #仕事内容
              $jobContentIds = $job_content ?? [];
              $jobContentNoteLines = $splitLines($job_content_notice ?? '');
              if (!empty($jobContentIds) || $hasNonEmptyLine($jobContentNoteLines)) {
                $masterJsonData['jobContents'] = [
                  'optionIds' => $jobContentIds,
                ];
                if ($hasNonEmptyLine($jobContentNoteLines)) {
                  $masterJsonData['jobContents']['note'] = $jobContentNoteLines;
                }
              }
              #サービス形態
              $serviceTypeIds = $service_type ?? [];
              if (!empty($serviceTypeIds)) {
                $masterJsonData['serviceTypes'] = [
                  'optionIds' => $serviceTypeIds,
                ];
              }
              #フリーテキスト参照（TS型都合で常に出力）
              $freeTextFile = $job_code . '_freespace.json';
              $freeTextExists = file_exists($jobsCardMasterJsonSaveDir . '/' . $freeTextFile);
              $masterJsonData['freeText'] = [
                'enabled' => $freeTextExists,
                'path' => $freeTextExists ? ($jobsUrlBase . $freeTextFile) : '',
              ];
              #1日の流れ（TS型都合で常に出力）
              $dailyScheduleOut = [
                'dayShift' => [],
                'nightShift' => [],
              ];
              $dayShiftOut = [];
              if (isset($day_list_hour) && is_array($day_list_hour)) {
                for ($i = 0; $i < count($day_list_hour); $i++) {
                  $time = $day_list_hour[$i] ?? '';
                  $min = $day_list_min[$i] ?? '00';
                  $body = $day_list_body[$i] ?? '';
                  $bodyLines = $splitLines($body);
                  if ($time !== '' && $hasNonEmptyLine($bodyLines)) {
                    $dayShiftOut[] = [
                      'time' => $time . ':' . $min,
                      'body' => $bodyLines,
                    ];
                  }
                }
              }
              $nightShiftOut = [];
              if (isset($night_list_hour) && is_array($night_list_hour)) {
                for ($i = 0; $i < count($night_list_hour); $i++) {
                  $time = $night_list_hour[$i] ?? '';
                  $min = $night_list_min[$i] ?? '00';
                  $body = $night_list_body[$i] ?? '';
                  $bodyLines = $splitLines($body);
                  if ($time !== '' && $hasNonEmptyLine($bodyLines)) {
                    $nightShiftOut[] = [
                      'time' => $time . ':' . $min,
                      'body' => $bodyLines,
                    ];
                  }
                }
              }
              $dailyScheduleOut['dayShift'] = $dayShiftOut;
              $dailyScheduleOut['nightShift'] = $nightShiftOut;
              $masterJsonData['dailySchedule'] = $dailyScheduleOut;
              #応募要件
              $applicationRequirementIds = $requirements ?? [];
              $applicationRequirementNoteLines = $splitLines($requirement_notice ?? '');
              if (!empty($applicationRequirementIds) || $hasNonEmptyLine($applicationRequirementNoteLines)) {
                $masterJsonData['applicationRequirements'] = [
                  'optionIds' => $applicationRequirementIds,
                ];
                if ($hasNonEmptyLine($applicationRequirementNoteLines)) {
                  $masterJsonData['applicationRequirements']['note'] = $applicationRequirementNoteLines;
                }
              }
              #働き方
              $workStyleIds = $work_style ?? [];
              $workStyleNoteLines = $splitLines($work_style_notice ?? '');
              if (!empty($workStyleIds) || $hasNonEmptyLine($workStyleNoteLines)) {
                $masterJsonData['workStyle'] = [
                  'optionIds' => $workStyleIds,
                ];
                if ($hasNonEmptyLine($workStyleNoteLines)) {
                  $masterJsonData['workStyle']['note'] = $workStyleNoteLines;
                }
              }
              #福利厚生（簡易）
              $benefitsIds = $benefits ?? [];
              $benefitsNoteLines = $splitLines($benefits_notice ?? '');
              if (!empty($benefitsIds) || $hasNonEmptyLine($benefitsNoteLines)) {
                $masterJsonData['benefits'] = [
                  'optionIds' => $benefitsIds,
                ];
                if ($hasNonEmptyLine($benefitsNoteLines)) {
                  $masterJsonData['benefits']['note'] = $benefitsNoteLines;
                }
              }
              #休日条件
              $holidayIds = $holidays ?? [];
              $holidayNoteLines = $splitLines($holidays_notice ?? '');
              if (!empty($holidayIds) || $hasNonEmptyLine($holidayNoteLines)) {
                $masterJsonData['holidayConditions'] = [
                  'optionIds' => $holidayIds,
                ];
                if ($hasNonEmptyLine($holidayNoteLines)) {
                  $masterJsonData['holidayConditions']['note'] = $holidayNoteLines;
                }
              }
              #長期休暇（文字列配列）
              $longHolidayLines = $splitLines($long_term_holiday_notice ?? '');
              if ($hasNonEmptyLine($longHolidayLines)) {
                $masterJsonData['longHolidays'] = $longHolidayLines;
              }
              #歓迎要件（文字列配列）
              $welcomeLines = $splitLines($welcome_notice ?? '');
              if ($hasNonEmptyLine($welcomeLines)) {
                $masterJsonData['welcomeRequirements'] = $welcomeLines;
              }
              #研修・サポート
              $trainingOptions = $supports ?? [];
              $trainingNoteLines = $splitLines($support_notice ?? '');
              if (!empty($trainingOptions) || $hasNonEmptyLine($trainingNoteLines)) {
                $masterJsonData['trainingSupport'] = [
                  'options' => $trainingOptions,
                ];
                if ($hasNonEmptyLine($trainingNoteLines)) {
                  $masterJsonData['trainingSupport']['note'] = $trainingNoteLines;
                }
              }
              #アクセス
              $accessOptions = $accesses ?? [];
              if (!empty($accessOptions)) {
                $masterJsonData['access'] = [
                  'options' => $accessOptions,
                ];
              }
              #選考プロセス（文字列配列）
              $selectionLines = $splitLines($selection_process ?? '');
              if ($hasNonEmptyLine($selectionLines)) {
                $masterJsonData['selectionProcess'] = $selectionLines;
              }
              if (!empty($firstYearIncomeRangeId)) {
                $masterJsonData['firstYearIncomeRangeId'] = $firstYearIncomeRangeId;
              }
              if (!empty($planRefs)) {
                $masterJsonData = array_merge($masterJsonData, $planRefs);
              }
              #JSONエンコード
              $news_master_json = json_encode($masterJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
              #ファイル書き込み
              $write_master_json = fopen($jobsCardMasterJsonSaveDir . '/' . $jobsCardMasterJson, "w");
              fwrite($write_master_json, $news_master_json);
              fclose($write_master_json);
            }
            #---------------------------------------------------------------------------------
            # 求人カード基本データDB更新完了のJSONファイル作成；jobsIndex.jsonから該当求人カード削除
            #---------------------------------------------------------------------------------
            #json保存先
            $jobsIndexJsonSaveDir = rtrim(DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs', '/\\');
            $jobsIndexJson = 'jobsIndex.json';
            #jsonデータ生成（表示対象0件の場合は jobsIndex.json を削除する）
            $jobIndexResult = createJobIndex_JSON($jobsIndexJsonSaveDir, $jobsIndexJson, $facId, $facilityData);
            if (is_array($jobIndexResult) && (int)($jobIndexResult['count'] ?? 0) === 0) {
              #最後の求人が消えたら jobsIndex.json を消して jobs ディレクトリも消す
              deleteDirIfEmpty($jobsIndexJsonSaveDir);
              $facilityJobsParent = dirname($jobsIndexJsonSaveDir);
              if (is_string($facilityJobsParent) && $facilityJobsParent !== '') {
                deleteDirIfEmpty($facilityJobsParent);
              }
            }
            #求人カード全インデックスJSON作成：jobsIndexAll.jsonから該当求人カード削除
            $cmd = '/usr/bin/php8.3 ' . DEFINE_JSON_FUNCTION_MASTER . '/workJson/makeIndexAll.php 2>&1 &';
            exec($cmd, $output, $return_var);
          } else {
            #DBロールバック
            # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
            DB_Transaction(3);
            #失敗時クリーンアップ（tmpファイルとセッション）
            cleanupFiles($pendingTmpFiles);
            $pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
            foreach ($pendingClearSessions as $sKey) {
              unset($_SESSION[$sKey]);
            }
            #削除予約セッションを掃除
            if (isset($_SESSION['job_card_image_pending_deletes']) && is_array($_SESSION['job_card_image_pending_deletes'])) {
              $clearKey = '';
              if ($method === 'new' && isset($newJobCardId)) {
                $clearKey = (string)$newJobCardId;
              } elseif (isset($jobId) && !empty($jobId)) {
                $clearKey = (string)$jobId;
              }
              if ($clearKey !== '' && isset($_SESSION['job_card_image_pending_deletes'][$clearKey])) {
                unset($_SESSION['job_card_image_pending_deletes'][$clearKey]);
              }
            }
            $makeTag['status'] = 'error';
          }
        }
      } catch (Exception $e) {
        #エラーログ出力
        $data = [
          'pageName' => 'proc_master03_02_01',
          'reason' => 'トランザクション開始失敗',
          'errorMessage' => $e->getMessage(),
        ];
        makeLog($data);
        $makeTag['status'] = 'error';
      }
    }
    break;
}
#-------------------------------------------#
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
#jobIndex.json作成関数
function createJobIndex_JSON($jobsIndexJsonSaveDir, $jobsIndexJson, $facId, $facilityData)
{
  #登録済み求人カード情報を取得
  $jobCardList = getJobList($facId);
  $jobsIndexWriteData = [
    'facilityId' => $facilityData['facility_code'],
    'items' => [],
  ];
  #表示可能リストあればループ処理
  if (isset($jobCardList) && is_array($jobCardList) && count($jobCardList) > 0) {
    #住所データ作成
    $workLocation = $facilityData['prefecture'] . $facilityData['city'] . $facilityData['address_line'];
    foreach ($jobCardList as $jobCard) {
      #ステータス判定
      #if ($jobCard['is_active'] != 2) {
      #  continue;
      #}
      #契約日
      $contractDate = date("Y/m/d", strtotime($jobCard['published_start']));
      #時給／月給により生成するデータを変更
      $salaryJson = [];
      if ($jobCard['salary_unit_id'] == 'monthly') {
        $hasBonus = false;
        if ($jobCard['bonus_has_bonus'] == 1) {
          $hasBonus = true;
        } else {
          $hasBonus = false;
        }
        $salaryJson = [
          'unitId' => $jobCard['salary_unit_id'],
          'min' => $jobCard['salary_min'],
          'max' => $jobCard['salary_max'],
          #'bonus' => [
          #  'hasBonus' => $hasBonus,
          #  'note' => $bonus_note,
          #],
        ];
      } elseif ($jobCard['salary_unit_id'] == 'hourly') {
        $salaryJson = [
          'unitId' => $jobCard['salary_unit_id'],
          'min' => $jobCard['salary_min'],
          'max' => $jobCard['salary_max'],
          #'bandId' => $salary_range,
        ];
      }
      $jobsIndexWriteItems = [
        'jobId' => $jobCard['job_code'],
        'publishedPeriod' => [
          'start' => $contractDate,
          'end' => null,
        ],
        'title' => $jobCard['card_title'],
        'facilityName' => $facilityData['name'],
        'heroImages' => $jobCard['hero_image_primary'] ? json_decode($jobCard['hero_image_primary'], true) : [],
        'employmentTypeId' => $jobCard['employment_type_id'],
        'jobCategoryId' => $jobCard['job_category_id'],
        'salary' => $salaryJson,
        'workLocationText' => $workLocation,
        'updatedAt' => date('Y/m/d', strtotime($jobCard['updated_at'] ?? $jobCard['created_at'])),
      ];
      #配列に追加
      $jobsIndexWriteData['items'][] = $jobsIndexWriteItems;
    }
  }
  $jobsIndexPath = rtrim($jobsIndexJsonSaveDir, '/\\') . '/' . $jobsIndexJson;
  $activeCount = isset($jobsIndexWriteData['items']) && is_array($jobsIndexWriteData['items']) ? count($jobsIndexWriteData['items']) : 0;
  #表示対象0件の場合は jobsIndex.json を削除（ディレクトリは呼び出し側で空判定削除）
  if ($activeCount === 0) {
    if (file_exists($jobsIndexPath)) {
      @unlink($jobsIndexPath);
    }
    return [
      'count' => 0,
      'deleted' => true,
      'path' => $jobsIndexPath,
    ];
  }
  if (!is_dir($jobsIndexJsonSaveDir)) {
    @mkdir($jobsIndexJsonSaveDir, 0777, true);
  }
  #JSONエンコード
  $news_jobsIndex_json = json_encode($jobsIndexWriteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  #ファイル書き込み
  $write_jobsIndex_json = fopen($jobsIndexPath, "w");
  fwrite($write_jobsIndex_json, $news_jobsIndex_json);
  fclose($write_jobsIndex_json);
  @chmod($jobsIndexPath, octdec("0666"));
  return [
    'count' => $activeCount,
    'deleted' => false,
    'path' => $jobsIndexPath,
  ];
}
#-------------------------------------------#
#スタンダードプラン用JSONデータ作成関数読み込み
function createJobCard_Plan_JSON($jobCardId, $contract_plan = 'standard', $imagePathArr = [])
{
  global $DB_CONNECT, $method, $job_code, $facId, $jobId, $facilityData;
  global $interview_name, $interview_role;
  global $interview1_title, $interview1_heading, $interview1_body, $interview1_heading1, $interview1_body1;
  global $interview2_title, $interview2_heading, $interview2_body, $interview2_heading1, $interview2_body1;
  global $interview3_title, $interview3_heading, $interview3_body, $interview3_heading1, $interview3_body1;
  global $video1_url, $video2_url;
  global $benefits1_title, $benefits1_body;
  global $benefits2_title, $benefits2_body;
  global $benefits3_title, $benefits3_body;
  global $benefits4_title, $benefits4_body;
  #応答変数：初期化
  $result = true;
  $pickFirstImagePath = function ($value): string {
    if (is_array($value)) {
      return isset($value[0]) ? (string)$value[0] : '';
    }
    return $value ? (string)$value : '';
  };
  #新規 or 編集
  if ($method === 'new') {
    $setJobId = $jobCardId;
  } else {
    $setJobId = $jobId;
  }
  #画像未変更時のために、DB保存済みドキュメントから画像パスを取得しておく
  $existingInterviewJson = [];
  $existingBenefitsJson = [];
  try {
    $existingInterviewRow = getJobCardArticle_FindByJobId($setJobId, 'interview');
    if (isset($existingInterviewRow['content_json']) && $existingInterviewRow['content_json']) {
      $tmp = json_decode($existingInterviewRow['content_json'], true);
      if (is_array($tmp)) {
        $existingInterviewJson = $tmp;
      }
    }
    $existingBenefitsRow = getJobCardArticle_FindByJobId($setJobId, 'benefits');
    if (isset($existingBenefitsRow['content_json']) && $existingBenefitsRow['content_json']) {
      $tmp = json_decode($existingBenefitsRow['content_json'], true);
      if (is_array($tmp)) {
        $existingBenefitsJson = $tmp;
      }
    }
  } catch (Exception $e) {
    #フォールバック取得失敗時は空のまま（既存挙動と同等）
  }
  #インタビュー情報JSON作成
  $db_interviewArticles = [];
  for ($i = 1; $i <= 3; $i++) {
    $title = ${"interview{$i}_title"} ?? '';
    if (empty($title)) continue;
    $db_interviewSections = [];
    for ($j = 0; $j < 2; $j++) {
      $heading = ${"interview{$i}_heading" . ($j == 0 ? '' : $j)} ?? '';
      $bodyVar = "interview{$i}_body" . ($j == 0 ? '' : $j);
      $body = isset(${$bodyVar}) ? ${$bodyVar} : '';
      if (!empty($heading) || !empty($body)) {
        $db_interviewSections[] = [
          'heading' => $heading,
          'body'    => $body,
        ];
      }
    }
    $areaKey = "interview{$i}_image";
    if (array_key_exists($areaKey, $imagePathArr)) {
      $image = $pickFirstImagePath($imagePathArr[$areaKey]);
    } else {
      $image = (string)arrayGetByPath($existingInterviewJson, ['articles', $i - 1, 'image'], '');
    }
    $db_interviewArticles[] = array(
      'id' => $title ? $i : '',
      'title' => $title,
      'image' => $image,
      'sections' => $db_interviewSections,
    );
  }
  $makeInterviewJson = array(
    'interviewee' => array(
      'name' => $interview_name ?? '',
      'role' => $interview_role ?? '',
    ),
    'articles' => $db_interviewArticles,
  );
  $interviewJson = json_encode($makeInterviewJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  #職場紹介動画JSON作成（URLが空の場合は生成しない）
  $videos = [];
  if (!empty($video1_url)) {
    $videos[] = [
      'id' => 'movie_001',
      'url' => $video1_url,
      'title' => '紹介動画',
    ];
  }
  if (!empty($video2_url)) {
    $videos[] = [
      'id' => 'movie_002',
      'url' => $video2_url,
      'title' => '360度カメラ',
    ];
  }
  $makeVideoJson = array(
    'videos' => $videos,
  );
  $videoJson = json_encode($makeVideoJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  #福利厚生JSON作成（title/bodyが空の場合は生成しない）
  $db_benefitSections = [];
  for ($i = 1; $i <= 4; $i++) {
    $title = ${"benefits{$i}_title"} ?? '';
    $body = ${"benefits{$i}_body"} ?? '';
    if (!empty($title) || !empty($body)) {
      $areaKey = "benefits{$i}_image";
      if (array_key_exists($areaKey, $imagePathArr)) {
        $image = $pickFirstImagePath($imagePathArr[$areaKey]);
      } else {
        $image = (string)arrayGetByPath($existingBenefitsJson, ['sections', $i - 1, 'image'], '');
      }
      $db_benefitSections[] = [
        'id' => $title ? sprintf('benefit_%03d', $i) : '',
        'title' => $title,
        'body' => $body,
        'image' => $image,
      ];
    }
  }
  $makeBenefitsJson = array(
    'sections' => $db_benefitSections,
  );
  $benefitsJson = json_encode($makeBenefitsJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  #--------------------------------------
  # スタンダードプラン／プレミアムプラン共通
  #--------------------------------------
  #インタビュー：未入力ならDELETEのみ（DBとJSON）
  $interviewHasContent = (!empty($interview_name) || !empty($interview_role) || !empty($db_interviewArticles));
  $dbInterviewOk = DB_jobSet_document_json($DB_CONNECT, $setJobId, 'interview', $interviewHasContent ? $interviewJson : '', 1);
  if ($dbInterviewOk) {
    $interviewJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
    $interviewJsonFile = $job_code . '_interview.json';
    if ($interviewHasContent) {
      if (!is_dir($interviewJsonSaveDir)) {
        @mkdir($interviewJsonSaveDir, 0777, true);
      }
      if (!file_exists($interviewJsonSaveDir . '/' . $interviewJsonFile)) {
        makeJson($interviewJsonSaveDir, $interviewJsonFile);
      }
      #テキストデータフォーマットとJSONデータ生成
      $json_interviewArticles = [];
      for ($i = 1; $i <= 3; $i++) {
        $title = ${"interview{$i}_title"} ?? '';
        if (empty($title)) continue;
        $json_interviewSections = [];
        for ($j = 0; $j <= 2; $j++) {
          $heading = ${"interview{$i}_heading" . ($j == 0 ? '' : $j)} ?? '';
          $bodyVar = "interview{$i}_body" . ($j == 0 ? '' : $j);
          $body = isset(${$bodyVar}) ? formatTextareaForDB(${$bodyVar}) : '';
          if (!empty($heading) || !empty($body)) {
            $json_interviewSections[] = [
              'heading' => $heading,
              'body'    => $body,
            ];
          }
        }
        $areaKey = "interview{$i}_image";
        if (array_key_exists($areaKey, $imagePathArr)) {
          $image = $pickFirstImagePath($imagePathArr[$areaKey]);
        } else {
          $image = (string)arrayGetByPath($existingInterviewJson, ['articles', $i - 1, 'image'], '');
        }
        $json_interviewArticles[] = array(
          'id' => $title ? $i : '',
          'title' => $title,
          'image' => $image,
          'sections' => $json_interviewSections,
        );
      }
      $interviewJsonData = [
        'interviewee' => [
          'name' => $interview_name ?? '',
          'role' => $interview_role ?? '',
        ],
        'articles' => $json_interviewArticles,
      ];
      $news_interview_json = json_encode($interviewJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      $write_interview_json = fopen($interviewJsonSaveDir . '/' . $interviewJsonFile, "w");
      fwrite($write_interview_json, $news_interview_json);
      fclose($write_interview_json);
    } else {
      if (file_exists($interviewJsonSaveDir . '/' . $interviewJsonFile)) {
        @unlink($interviewJsonSaveDir . '/' . $interviewJsonFile);
      }
    }
  } else {
    $data = [
      'pageName' => 'proc_master03_02_01',
      'reason' => 'インタビューデータDB登録失敗',
    ];
    makeLog($data);
    $result = false;
  }
  #------------------
  # スタンダードプラン
  #------------------
  if ($contract_plan == 'standard') {
    #--------------------------------------------------------------
    # スタンダードプラン用のJSONファイル作成；job_〇〇〇_standard.json
    #--------------------------------------------------------------
    #json保存先
    $standardJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
    if (!is_dir($standardJsonSaveDir)) {
      @mkdir($standardJsonSaveDir, 0777, true);
    }
    #書き込み用jsonファイルが無い場合はベースファイルを作成
    $standardJsonFile = $job_code . '_standard.json';
    if (!file_exists($standardJsonSaveDir . '/' . $standardJsonFile)) {
      makeJson($standardJsonSaveDir, $standardJsonFile);
    }
    #プレミアム専用コンテンツはスタンダードでは保持しない（DB/JSONを削除）
    DB_jobSet_document_json($DB_CONNECT, $setJobId, 'movie', '', 1);
    DB_jobSet_document_json($DB_CONNECT, $setJobId, 'benefits', '', 1);
    $jobsJsonDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
    if (file_exists($jobsJsonDir . '/' . ($job_code . '_movie.json'))) {
      @unlink($jobsJsonDir . '/' . ($job_code . '_movie.json'));
    }
    if (file_exists($jobsJsonDir . '/' . ($job_code . '_benefits.json'))) {
      @unlink($jobsJsonDir . '/' . ($job_code . '_benefits.json'));
    }
  }
  #----------------
  # プレミアムプラン
  #----------------
  if ($contract_plan == 'premium') {
    #----------------------------
    #動画：未入力ならDELETEのみ（DBとJSON）
    $movieHasContent = (!empty($video1_url) || !empty($video2_url));
    $dbMovieOk = DB_jobSet_document_json($DB_CONNECT, $setJobId, 'movie', $movieHasContent ? $videoJson : '', 1);
    if ($dbMovieOk) {
      $videoJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
      $videoJsonFile = $job_code . '_movie.json';
      if ($movieHasContent) {
        if (!is_dir($videoJsonSaveDir)) {
          @mkdir($videoJsonSaveDir, 0777, true);
        }
        if (!file_exists($videoJsonSaveDir . '/' . $videoJsonFile)) {
          makeJson($videoJsonSaveDir, $videoJsonFile);
        }
        $news_video_json = json_encode($makeVideoJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $write_video_json = fopen($videoJsonSaveDir . '/' . $videoJsonFile, "w");
        fwrite($write_video_json, $news_video_json);
        fclose($write_video_json);
      } else {
        if (file_exists($videoJsonSaveDir . '/' . $videoJsonFile)) {
          @unlink($videoJsonSaveDir . '/' . $videoJsonFile);
        }
      }
    } else {
      $data = [
        'pageName' => 'proc_master03_02_01',
        'reason' => '職場紹介動画DB登録失敗',
      ];
      makeLog($data);
      $result = false;
    }
    #------------------------
    #福利厚生：未入力ならDELETEのみ（DBとJSON）
    $benefitsHasContent = (!empty($db_benefitSections));
    $dbBenefitsOk = DB_jobSet_document_json($DB_CONNECT, $setJobId, 'benefits', $benefitsHasContent ? $benefitsJson : '', 1);
    if ($dbBenefitsOk) {
      $benefitsJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
      $benefitsJsonFile = $job_code . '_benefits.json';
      if ($benefitsHasContent) {
        if (!is_dir($benefitsJsonSaveDir)) {
          @mkdir($benefitsJsonSaveDir, 0777, true);
        }
        if (!file_exists($benefitsJsonSaveDir . '/' . $benefitsJsonFile)) {
          makeJson($benefitsJsonSaveDir, $benefitsJsonFile);
        }
        #福利厚生JSONデータ生成（title/bodyが空なら出力しない、bodyはテキストフォーマット）
        $json_benefitSections = [];
        for ($i = 1; $i <= 4; $i++) {
          $title = ${"benefits{$i}_title"} ?? '';
          $bodyRaw = ${"benefits{$i}_body"} ?? '';
          $body = $bodyRaw ? formatTextareaForDB($bodyRaw) : '';
          if (!empty($title) || !empty($body)) {
            $areaKey = "benefits{$i}_image";
            if (array_key_exists($areaKey, $imagePathArr)) {
              $image = $pickFirstImagePath($imagePathArr[$areaKey]);
            } else {
              $image = (string)arrayGetByPath($existingBenefitsJson, ['sections', $i - 1, 'image'], '');
            }
            $json_benefitSections[] = [
              'id' => $title ? sprintf('benefit_%03d', $i) : '',
              'title' => $title,
              'body' => $body,
              'image' => $image,
            ];
          }
        }
        $benefitsJsonData = [
          'sections' => $json_benefitSections,
        ];
        $news_benefits_json = json_encode($benefitsJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $write_benefits_json = fopen($benefitsJsonSaveDir . '/' . $benefitsJsonFile, "w");
        fwrite($write_benefits_json, $news_benefits_json);
        fclose($write_benefits_json);
      } else {
        if (file_exists($benefitsJsonSaveDir . '/' . $benefitsJsonFile)) {
          @unlink($benefitsJsonSaveDir . '/' . $benefitsJsonFile);
        }
      }
    } else {
      $data = [
        'pageName' => 'proc_master03_02_01',
        'reason' => '福利厚生DB登録失敗',
      ];
      makeLog($data);
      $result = false;
    }
    #-----------------------------------------------------------
    # プレミアムプラン用のJSONファイル作成；job_〇〇〇_premium.json
    #-----------------------------------------------------------
    #json保存先
    $premiumJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
    if (!is_dir($premiumJsonSaveDir)) {
      @mkdir($premiumJsonSaveDir, 0777, true);
    }
    #書き込み用jsonファイルが無い場合はベースファイルを作成
    $premiumJsonFile = $job_code . '_premium.json';
    if (!file_exists($premiumJsonSaveDir . '/' . $premiumJsonFile)) {
      makeJson($premiumJsonSaveDir, $premiumJsonFile);
    }
    #JSONデータ生成
    #動画（urlが無い場合は出力しない）
    $premiumVideos = [];
    if (!empty($video1_url)) {
      $premiumVideos['intro'] = [
        'title' => '紹介動画',
        'youtubeUrl' => $video1_url,
      ];
    }
    if (!empty($video2_url)) {
      $premiumVideos['camera360'] = [
        'title' => '360度カメラ',
        'youtubeUrl' => $video2_url,
      ];
    }
    #福利厚生（title/bodyが空なら出力しない、bodyはテキストフォーマット）
    $premiumBenefits = [];
    for ($i = 1; $i <= 4; $i++) {
      $title = ${"benefits{$i}_title"} ?? '';
      $bodyRaw = ${"benefits{$i}_body"} ?? '';
      $body = $bodyRaw ? formatTextareaForDB($bodyRaw) : '';
      if (!empty($title) || !empty($body)) {
        $areaKey = "benefits{$i}_image";
        if (array_key_exists($areaKey, $imagePathArr)) {
          $image = $pickFirstImagePath($imagePathArr[$areaKey]);
        } else {
          $image = (string)arrayGetByPath($existingBenefitsJson, ['sections', $i - 1, 'image'], '');
        }
        $premiumBenefits[] = [
          'id' => $title ? sprintf('benefit_%03d', $i) : '',
          'title' => $title,
          'body' => $body,
          'image' => $image,
        ];
      }
    }
    $premiumJsonData = [];
    if (!empty($premiumVideos)) {
      $premiumJsonData['videos'] = $premiumVideos;
    }
    if (!empty($premiumBenefits)) {
      $premiumJsonData['benefits'] = $premiumBenefits;
    }
    #JSONエンコード
    $news_premium_json = json_encode($premiumJsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    #ファイル書き込み
    $write_premium_json = fopen($premiumJsonSaveDir . '/' . $premiumJsonFile, "w");
    fwrite($write_premium_json, $news_premium_json);
    fclose($write_premium_json);
  }
  #応答
  return $result;
}
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
