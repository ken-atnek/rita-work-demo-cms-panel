<?php
/*
 * [rw-client/client03_01_01.php]
 *  - 【事業所】管理画面 -
 *  求人カード登録／編集
 *
 * [初版]
 *  2026.1.23
 */

#***** 定数定義ファイル：インクルード *****#
require_once '../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once '../../cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once '../../cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once '../../cms_config/client/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#法人情報
require_once '../../cms_config/database/db_corporations.php';
#事業所情報
require_once '../../cms_config/database/db_facilities.php';
#求人カード情報
require_once '../../cms_config/database/db_jobs.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'cKey03-01_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  if ($key !== 'sKey' && $key !== 'client_login' && $key !== $noUpDateKey) {
    unset($_SESSION[$key]);
  }
}
#セッション本体の初期化
$_SESSION[$noUpDateKey] = array();
#アカウントキー
$_SESSION[$noUpDateKey]['clientKey'] = $_SESSION['client_login']['account_id'];
#データ取得エラー
if ($_SESSION[$noUpDateKey]['clientKey'] < 1) {
  header("Location: ./logout.php");
  exit;
}

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
    makeLog('[client03_01_01] master JSON load failed: ' . $e->getMessage());
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

#=============#
# 法人一覧取得
#-------------#
$corporationsList = getCorporationList();

#=============#
# POSTチェック
#-------------#
#求人カードID（編集／削除時のみ）
$jobId = isset($_GET['jobId']) ? $_GET['jobId'] : null;
#事業所ID
$facId = isset($_GET['facId']) ? $_GET['facId'] : null;
#事業所IDがあれば事業所情報取得
if ($facId !== null) {
  #事業所情報取得
  $facilityData = getFacility_FindById($facId);
  #求人カード情報取得
  $jobData = getJob_FindById($jobId);
  #職場環境の特徴を取得
  $workEnvironmentMetricsData = getJobWorkEnvironmentMetrics_FindByJobId($jobId);
  #仕事の詳細情報取得
  $jobOptionGroupData = getJobOptionGroup_FindByJobId($jobId);
  #仕事の詳細テキスト情報取得
  $jobOptionTextData = getJobOptionText_FindByJobId($jobId);
} else {
  $facilityData = array(
    'name' => '',
    'name_kana' => '',
    'facility_code' => '',
    'established_date' => '',
    'postal_code' => '',
    'prefecture' => '',
    'city' => '',
    'address_line' => '',
    'phone' => '',
    'email' => '',
    'map_url' => '',
    'map_link_url' => '',
  );
}
#求人カード情報が無ければ初期化
if (is_array($jobData) === false || count($jobData) === 0) {
  $jobData = array(
    'job_id' => '',
    'job_code' => '',
    'job_category_id' => '',
    'employment_type_id' => '',
    'first_year_income_range_id' => '',
    'card_title' => '',
    'published_start' => '',
    'published_end' => '',
    'contract_plan_id' => '',
    'salary_unit_id' => '',
    'salary_min' => '',
    'salary_max' => '',
    'salary_range' => '',
    'bonus_has_bonus' => '',
    'bonus_note' => '',
    'hero_image_primary' => '',
    'is_active' => 1,
  );
}
#-------------#
#求人コード生成
$job_code = $jobData['job_code'];
#-------------#
#職場環境の特徴が無ければ初期化
if (is_array($workEnvironmentMetricsData) === false || count($workEnvironmentMetricsData) === 0) {
  #job_code
  $workEnvironmentMetricsData = array(
    'job_metric_value_id' => '',
    'job_id' => '',
    'metric_id' => '',
    'value' => '',
  );
}
#-------------#
#仕事の詳細情報が無ければ初期化
if (is_array($jobOptionGroupData) === false || count($jobOptionGroupData) === 0) {
  #job_code
  $jobOptionGroupData = array(
    'job_option_link_id' => '',
    'job_id' => '',
    'option_group_code' => '',
    'option_id' => '',
  );
}
#仕事の詳細テキスト情報が無ければ初期化
if (is_array($jobOptionTextData) === false || count($jobOptionTextData) === 0) {
  #job_code
  $jobOptionTextData = array(
    'job_option_link_extra_id' => '',
    'job_id' => '',
    'option_group_code' => '',
    'option_text' => '',
  );
}

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title>リタワーク｜コントロールパネル(事業所)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master03-02.css">
  </head>

  <body>
HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-03-02-01">
      <section class="page-nav">
        <h2>求人カード管理</h2>
        <nav>
          <a href="javascript:void(0);" class="is-active">求人カード一覧</a>
        </nav>
      </section>
      <section class="container-job-card-register">
        <nav class="block-nav" id="blockNavMenu">
          <a href="#blockBasicInfo" class="is-active"><i></i>基本情報</a>
          <a href="#blockInterview"><i></i>機能拡張プラン</a>
          <a href="#blockWorkSpace"><i></i>職場環境の特徴</a>
          <a href="#blockFreeText"><i></i>フリーテキスト枠</a>
          <a href="#blockSchedule"><i></i>1日の流れ</a>
          <a href="#blockDetailInfo"><i></i>詳細情報</a>
        </nav>
        <a href="./client03_01.php?facId={$facId}" class="link-page-back">戻る</a>
        <h2>求人カード情報<span>機能回復を支える理学療法士</span></h2>
        <form name="inputForm" class="block-form">
          <input type="hidden" name="method" value="edit">
          <input type="hidden" name="action" value="checkInput">
          <input type="hidden" name="facId" value="{$facId}">
          <input type="hidden" name="jobId" value="{$jobData['job_id']}">
          <input type="hidden" name="jobStatus" value="{$jobData['is_active']}">
          <article class="block-basic-info" id="blockBasicInfo">
            <div class="box-head">
              <h3>基本情報</h3>
              <p>求人カードの基本情報を入力ください</p>
              <!-- NOTE 状態をクラスで付与 [公開中：status-active 下書き中：status-draft] -->

HTML;
#公開中判定：published_endが未来日付の場合は公開中とする
if (isset($jobData['is_active']) && $jobData['is_active'] == '2') {
  print <<<HTML
              <div class="item-status status-active">公開中</div>

HTML;
} else {
  print <<<HTML
              <div class="item-status status-draft">下書き中</div>

HTML;
}
print <<<HTML
            </div>
            <dl>
              <dt>求人ID</dt>
              <dd class="dd-top">
                <input type="text" name="job_code" value="{$job_code}" readonly>
                <div class="title">掲載日</div>
                <div class="input-date"><input type="date" name="published_start" value="{$jobData['published_start']}"></div>
              </dd>
            </dl>
            <dl>
              <dt>募集職種</dt>
              <dd>
                <div class="select-job-type" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#事業形態が選択されていたら
if (isset($jobData['job_category_id']) && $jobData['job_category_id'] != '') {
  #選択中のラベル取得
  foreach ($jobCategories as $jobCategory) {
    if ($jobData['job_category_id'] == $jobCategory['id']) {
      print <<<HTML
                    <input type="hidden" name="job_category" value="{$jobCategory['id']}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$jobCategory['name']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                    <input type="hidden" name="job_category" value="" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($jobCategories) && is_array($jobCategories) && count($jobCategories) > 0) {
  foreach ($jobCategories as $jobCategory) {
    #checked判定
    $checked = ($jobData['job_category_id'] == $jobCategory['id']) ? 'checked' : '';
    print <<<HTML
                      <li>
                        <input type="radio" name="job_category" value="{$jobCategory['id']}" id="job{$jobCategory['id']}" {$checked}>
                        <label for="job{$jobCategory['id']}">{$jobCategory['name']}</label>
                      </li>

HTML;
  }
} else {
  print <<<HTML
                      <li>
                        <input type="radio" name="job_category" value="1" id="job01">
                        <label for="job01">募集職種が未設定です</label>
                      </li>

HTML;
}
print <<<HTML
                    </ul>
                  </div>
                </div>
              </dd>
            </dl>
            <dl>
              <dt class="position-top">PR画像</dt>
              <dd class="dd-select-image">
                <!-- NOTE 画像登録時は [is-active]付与 -->
                <div class="select-image" id="js-dragDrop-heroImage">
                  <h4>ここにファイルをドロップ</h4>
                  <span>または</span>
                  <input type="file" name="images_tmp" id="js-fileElem-heroImage" multiple accept="image/*" style="display:none">
                  <input type="hidden" name="upload_image_mode" value="multiple" id="js-uploadImageMode-heroImage">
                  <input type="hidden" name="upload_image_area" value="hero_image" id="js-uploadImageArea-heroImage">
                  <input type="hidden" name="up_image_area[]" value="hero_image">
                  <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                  <button type="button" id="js-fileSelect-heroImage">ファイルを選択</button>
                  <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                  <!-- NOTE 警告用表示 -->
                  <div class="wrap-caution" id="js-fileError-heroImage" style="display: none;">
                    <h5>ファイルサイズが大きすぎます</h5>
                    <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                  </div>
                </div>

HTML;
#登録画像リスト表示
$heroImagePrimary = json_decode($jobData['hero_image_primary'], true);
if (isset($heroImagePrimary) && is_array($heroImagePrimary) && count($heroImagePrimary) > 0) {
  print <<<HTML
                <ul class="selected-image-list" id="js-previewBlock-heroImage">

HTML;
  #登録画像リスト展開
  foreach ($heroImagePrimary as $info) {
    $ext = strtolower(pathinfo($info, PATHINFO_EXTENSION));
    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        $mimeType = 'image/jpeg';
        break;
      case 'png':
        $mimeType = 'image/png';
        break;
      case 'gif':
        $mimeType = 'image/gif';
        break;
      default:
        $mimeType = '';
        break;
    }
    $previewPath = DOMAIN_NAME . $info;
    print <<<HTML
                  <li>
                    <div class="warp-btn">
                      <button type="button" class="btn-change"></button>
                      <button type="button" class="btn-delate"></button>
                    </div>
                    <picture>
                      <source src="{$previewPath}" type="{$mimeType}">
                      <img src="{$previewPath}" alt="PR画像">
                    </picture>
                  </li>

HTML;
  }
  print <<<HTML
                </ul>

HTML;
} else {
  print <<<HTML
                <ul class="selected-image-list" id="js-previewBlock-heroImage" style="display: none;"></ul>

HTML;
}
print <<<HTML
                <div class="wrap-notice">
                  <p>※画像は最大10枚まで登録可能です</p>
                  <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                  <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                  <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                </div>
              </dd>
            </dl>
            <dl>
              <dt style="align-items: flex-start">カード<br>タイトル</dt>
              <dd>
                <input type="text" name="card_title" value="{$jobData['card_title']}">
                <div class="wrap-notice">
                  <p>※既存の求人と同一の訴求文をつけることはできません</p>
                  <p>※他サイトからの引用・転載およびRITAWORKに掲載する文章・写真の無断転載は固く禁止します</p>
                </div>
              </dd>
            </dl>
            <dl>
              <dt>雇用形態</dt>
              <dd>
                <div class="select-job-category" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#雇用形態が選択されていたら
if (isset($employmentTypes['employment_type_id']) && $employmentTypes['employment_type_id'] != '') {
  #選択中のラベル取得
  foreach ($employmentTypes as $employmentType) {
    if ($employmentTypes['employment_type_id'] == $employmentType['id']) {
      print <<<HTML
                    <input type="hidden" name="employment_type" value="{$employmentType['id']}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$employmentType['name']}</span>

HTML;
    }
  }
} else {
  print <<<HTML
                    <input type="hidden" name="employment_type" value="" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($employmentTypes) && is_array($employmentTypes) && count($employmentTypes) > 0) {
  foreach ($employmentTypes as $employmentType) {
    #checked判定
    $checked = ($jobData['employment_type_id'] == $employmentType['id']) ? 'checked' : '';
    print <<<HTML
                      <li>
                        <input type="radio" name="employment_type" value="{$employmentType['id']}" id="{$employmentType['id']}" {$checked}>
                        <label for="{$employmentType['id']}">{$employmentType['name']}</label>
                      </li>

HTML;
  }
} else {
  print <<<HTML
                      <li>
                        <input type="radio" name="employment_type" value="1" id="employment01">
                        <label for="employment01">雇用形態が未設定です</label>
                      </li>

HTML;
}
print <<<HTML
                    </ul>
                  </div>
                </div>
              </dd>
            </dl>
            <dl>
              <dt>給与</dt>
              <dd class="dd-salary">
                <div class="wrap-head">
                  <div class="item-salary">
                    <div class="wrap-radio">

HTML;
#月給／時給ラジオボタン
$monthlySalaryFlag = 'is-block-active';
$hourlySalaryFlag = '';
$yearlySalaryFlag = 'is-inactive';
#月給／時給入力情報
$monthlySalaryMin = '';
$monthlySalaryMax = '';
$hourlySalaryMin = '';
$hourlySalaryMax = '';
if (isset($jobData['salary_unit_id']) && $jobData['salary_unit_id'] == 'monthly') {
  #月給
  $monthlySalaryFlag = 'is-block-active';
  $hourlySalaryFlag = '';
  $yearlySalaryFlag = '';
  #月給入力情報
  $monthlySalaryMin = $jobData['salary_min'];
  $monthlySalaryMax = $jobData['salary_max'];
  print <<<HTML
                      <div class="item-check-box">
                        <input type="radio" id="radio-salary01" name="select_salary" value="monthly" checked onclick="toggleSalaryType('monthly');"><label for="radio-salary01">月給</label>
                      </div>
                      <div class="item-check-box">
                        <input type="radio" id="radio-salary02" name="select_salary" value="hourly" onclick="toggleSalaryType('hourly');"><label for="radio-salary02">時給</label>
                      </div>

HTML;
} elseif (isset($jobData['salary_unit_id']) && $jobData['salary_unit_id'] == 'hourly') {
  #時給
  $monthlySalaryFlag = '';
  $hourlySalaryFlag = 'is-block-active';
  $yearlySalaryFlag = 'is-inactive';
  #時給入力情報
  $hourlySalaryMin = $jobData['salary_min'];
  $hourlySalaryMax = $jobData['salary_max'];
  print <<<HTML
                      <div class="item-check-box">
                        <input type="radio" id="radio-salary01" name="select_salary" value="monthly" onclick="toggleSalaryType('monthly');"><label for="radio-salary01">月給</label>
                      </div>
                      <div class="item-check-box">
                        <input type="radio" id="radio-salary02" name="select_salary" value="hourly" checked onclick="toggleSalaryType('hourly');"><label for="radio-salary02">時給</label>
                      </div>

HTML;
} else {
  #初期値：月給選択
  $monthlySalaryFlag = 'is-block-active';
  $hourlySalaryFlag = '';
  $yearlySalaryFlag = '';
  print <<<HTML
                      <div class="item-check-box">
                        <input type="radio" id="radio-salary01" name="select_salary" value="monthly" checked onclick="toggleSalaryType('monthly');"><label for="radio-salary01">月給</label>
                      </div>
                      <div class="item-check-box">
                        <input type="radio" id="radio-salary02" name="select_salary" value="hourly" onclick="toggleSalaryType('hourly')"><label for="radio-salary02">時給</label>
                      </div>

HTML;
}
print <<<HTML
                    </div>
                    <!-- NOTE 月給選択時 [is-block-active]付与 -->
                    <span class="wrap-price {$monthlySalaryFlag}">
                      <input type="text" name="monthly_min" value="{$monthlySalaryMin}" class="item-price"><i>〜</i>
                      <input type="text" name="monthly_max" value="{$monthlySalaryMax}" class="item-price"><i>円</i>
                    </span>
                    <!-- NOTE 時給選択時 [is-block-active]付与 -->
                    <span class="wrap-price select-salary02 {$hourlySalaryFlag}">
                      <input type="text" name="hourly_min" value="{$hourlySalaryMin}" class="item-price"><i>〜</i>
                      <input type="text" name="hourly_max" value="{$hourlySalaryMax}" class="item-price"><i>円</i>
                    </span>
                  </div>
                  <div class="item-bonus">
                    <h4>賞与</h4>
                    <label class="toggle-button">

HTML;
#賞与有り／無し判定
if (isset($jobData['bonus_has_bonus']) && $jobData['bonus_has_bonus'] == 1) {
  print <<<HTML
                      <input type="checkbox" name="bonus" value="1" checked id="bonusCheckbox" onclick="toggleBonusType();">

HTML;
} else {
  print <<<HTML
                      <input type="checkbox" name="bonus" value="1" id="bonusCheckbox" onclick="toggleBonusType();">

HTML;
}
print <<<HTML
                    </label>
                    <!--NOTE  賞与有りの場合表示 -->
                    <input type="text" name="bonus_note" id="bonusAmount" value="{$jobData['bonus_note']}" style="display: none;">
                  </div>
                </div>
                <!--NOTE 「時給」選択でis-inactiveを付与 -->
                <div class="wrap-yearly-salary {$yearlySalaryFlag}">
                  <div class="inner-yearly-salary">
                    <h4>初年度年収</h4>
                    <div class="select-yearly-salary" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#初年度年収が選択されていたら
if (isset($jobData['first_year_income_range_id']) && $jobData['first_year_income_range_id'] != '') {
  #選択中のラベル取得
  foreach ($firstYearIncomeRanges as $firstYearIncomeRange) {
    if ($jobData['first_year_income_range_id'] == $firstYearIncomeRange['id']) {
      print <<<HTML
                        <input type="hidden" name="yearly_salary" value="{$firstYearIncomeRange['id']}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$firstYearIncomeRange['label']}</span>

HTML;
    }
  }
} else {
  print <<<HTML
                        <input type="hidden" name="yearly_salary" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($firstYearIncomeRanges) && is_array($firstYearIncomeRanges) && count($firstYearIncomeRanges) > 0) {
  foreach ($firstYearIncomeRanges as $firstYearIncomeRange) {
    #checked判定
    $checked = ($jobData['first_year_income_range_id'] == $firstYearIncomeRange['id']) ? 'checked' : '';
    print <<<HTML
                          <li>
                            <input type="radio" name="yearly_salary" value="{$firstYearIncomeRange['id']}" id="{$firstYearIncomeRange['id']}" {$checked}>
                            <label for="{$firstYearIncomeRange['id']}">{$firstYearIncomeRange['label']}</label>
                          </li>

HTML;
  }
}
#テキスト入力チェック：給与備考
$salaryNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'salary_note') {
    $salaryNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
              </dd>
            </dl>
            <dl>
              <dt class="position-top">給与備考</dt>
              <dd><textarea name="salary_note">{$salaryNotice}</textarea></dd>
            </dl>
            <dl>
              <dt>契約プラン</dt>
              <dd>
                <div class="select-plan" data-selectbox style="pointer-events: none">
                  <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#事業形態が選択されていたら
if (isset($jobData['contract_plan_id']) && $jobData['contract_plan_id'] != '') {
  #選択中のラベル取得
  foreach ($contractPlans as $contractPlan) {
    if ($jobData['contract_plan_id'] == $contractPlan['id']) {
      print <<<HTML
                    <input type="hidden" name="contract_plan" value="{$contractPlan['id']}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$contractPlan['name']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                    <input type="hidden" name="contract_plan" value="" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($contractPlans) && is_array($contractPlans) && count($contractPlans) > 0) {
  #onchangeボタンタグ生成
  $planChangeButton = '';
  if ($planAction === 'change') {
    $planChangeButton = 'onchange="checkChangeJobCardPlan(this, ' . $facId . ', ' . $jobId . ', \'' . $jobData['job_code'] . '\');"';
  }
  foreach ($contractPlans as $contractPlan) {
    #checked判定
    $checked = ($jobData['contract_plan_id'] == $contractPlan['id']) ? 'checked' : '';
    print <<<HTML
                      <li>
                        <input type="radio" name="contract_plan" value="{$contractPlan['id']}" id="{$contractPlan['id']}" {$checked} {$planChangeButton}>
                        <label for="{$contractPlan['id']}">{$contractPlan['name']}</label>
                      </li>

HTML;
  }
} else {
  print <<<HTML
                      <li>
                        <input type="radio" name="contract_plan" value="1" id="plan01">
                        <label for="plan01">プラン形態が未設定です</label>
                      </li>

HTML;
}
print <<<HTML
                    </ul>
                  </div>
                </div>
              </dd>
            </dl>
          </article>
          <hr>
          <article class="block-interview" id="blockInterview" data-field="block_interview">
            <div class="box-head">
              <h3>職場インタビュー</h3>
              <p>インタビュー記事の内容を入力ください</p>
            </div>

HTML;
#インタビュー記事取得
$jobInterviewData = getJobCardArticle_FindByJobId($jobId, 'interview');
$jobInterviewDataJson = array();
#インタビュー記事が無ければ初期化
if (is_array($jobInterviewData) === false || count($jobInterviewData) === 0) {
  $jobInterviewData = array(
    'job_document_id' => '',
    'job_id' => '',
    'doc_type' => '',
    'enabled' => '',
    'content_json' => '',
  );
  #初期化構造体生成
  $articlesMap = array(
    'id' => '',
    'title' => '',
    'image' => '',
    'sections' => array(
      array('heading' => '', 'body' => ''),
      array('heading' => '', 'body' => ''),
    ),
  );
  $jobInterviewDataJson = array(
    'interviewee' => array(
      'name' => '',
      'role' => '',
    ),
    'articles' => array_fill(0, 3, $articlesMap),
  );
} else {
  #記事内容デコード
  $jobInterviewDataJson = json_decode($jobInterviewData['content_json'], true);
}
print <<<HTML
            <dl>
              <dt class="position-top">協力者名</dt>
              <dd class="dd-interviewer">
                <div class="wrap-head">
                  <input type="text" name="interview_name" value="{$jobInterviewDataJson['interviewee']['name']}" style="max-width: 34rem"><i>さん</i>
                </div>
                <div class="wrap-notice">
                  <p>※フルネームが不可の場合はイニシャルを入力ください</p>
                </div>
              </dd>
            </dl>
            <dl>
              <dt>部署名</dt>
              <dd><input type="text" name="interview_role" value="{$jobInterviewDataJson['interviewee']['role']}" style="max-width: 34rem"></dd>
            </dl>
            <ul class="box-interview">
              <li>

HTML;
#タイトル入力チェック
$interview1Title = isset($jobInterviewDataJson['articles'][0]['title']) ? $jobInterviewDataJson['articles'][0]['title'] : '';
print <<<HTML
                <div class="wrap-head">
                  <h4>#1の記事</h4>
                  <button type="button" id="btnSwitchDetails01" class="btn-switch is-active" aria-controls="interviewDetails01" aria-expanded="true"></button>
                </div>
                <div class="wrap-details is-active" id="interviewDetails01">
                  <div class="inner-details">
                    <dl>
                      <dt>タイトル</dt>
                      <dd><input type="text" name="interview1_title" value="{$interview1Title}" style="max-width: 34rem"></dd>
                    </dl>
                    <dl style="margin-top: 1.6rem">
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-interview1Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-interview1Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-interview1Image">
                          <input type="hidden" name="upload_image_area" value="interview1_image" id="js-uploadImageArea-interview1Image">
                          <input type="hidden" name="up_image_area[]" value="interview1_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-interview1Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-interview1Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
#登録画像リスト表示
if (isset($jobInterviewDataJson['articles'][0]['image']) && $jobInterviewDataJson['articles'][0]['image'] != '') {
  #登録画像リスト展開
  $ext = strtolower(pathinfo($jobInterviewDataJson['articles'][0]['image'], PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $jobInterviewDataJson['articles'][0]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-interview1Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-interview1Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
for ($i = 0; $i < 2; $i++) {
  $key = $i + 1;
  $interview1HeadingValue = isset($jobInterviewDataJson['articles'][0]['sections'][$i]['heading']) ? $jobInterviewDataJson['articles'][0]['sections'][$i]['heading'] : '';
  $interview1BodyValue = isset($jobInterviewDataJson['articles'][0]['sections'][$i]['body']) ? $jobInterviewDataJson['articles'][0]['sections'][$i]['body'] : '';
  #name属性に連番を付与
  if ($i == 0) {
    $interview1HeadingName = 'interview1_heading';
    $interview1BodyName = 'interview1_body';
  } else {
    $interview1HeadingName = 'interview1_heading' . $i;
    $interview1BodyName = 'interview1_body' . $i;
  }
  print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し{$key}</dt>
                      <dd><input type="text" name="{$interview1HeadingName}" value="{$interview1HeadingValue}" style="max-width: 34rem"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="{$interview1BodyName}">{$interview1BodyValue}</textarea></dd>
                    </dl>

HTML;
}
print <<<HTML
                  </div>
                </div>
              </li>
              <li>

HTML;
#タイトル入力チェック
$interview2Title = isset($jobInterviewDataJson['articles'][1]['title']) ? $jobInterviewDataJson['articles'][1]['title'] : '';
#記事２ステータス
$interview2Status = '';
$interview2AriaExpanded = false;
if (isset($interview2Title) && $interview2Title != '') {
  $interview2Status = 'is-active';
  $interview2AriaExpanded = true;
}
print <<<HTML
                <div class="wrap-head">
                  <h4>#2の記事</h4>
                  <button type="button" id="btnSwitchDetails02" class="btn-switch {$interview2Status}" aria-controls="interviewDetails02" aria-expanded="{$interview2AriaExpanded}"></button>
                </div>
                <div class="wrap-details {$interview2Status}" id="interviewDetails02">
                  <div class="inner-details">
                    <dl>
                      <dt>タイトル</dt>
                      <dd><input type="text" name="interview2_title" value="{$interview2Title}"></dd>
                    </dl>
                    <dl style="margin-top: 1.6rem">
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-interview2Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-interview2Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-interview2Image">
                          <input type="hidden" name="upload_image_area" value="interview2_image" id="js-uploadImageArea-interview2Image">
                          <input type="hidden" name="up_image_area[]" value="interview2_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-interview2Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-interview2Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
if (isset($jobInterviewDataJson['articles'][1]['image']) && $jobInterviewDataJson['articles'][1]['image'] != '') {
  #登録画像リスト展開
  $ext = strtolower(pathinfo($jobInterviewDataJson['articles'][1]['image'], PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $jobInterviewDataJson['articles'][1]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-interview2Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-interview2Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
for ($i = 0; $i < 2; $i++) {
  $key = $i + 1;
  $interview2HeadingValue = isset($jobInterviewDataJson['articles'][1]['sections'][$i]['heading']) ? $jobInterviewDataJson['articles'][1]['sections'][$i]['heading'] : '';
  $interview2BodyValue = isset($jobInterviewDataJson['articles'][1]['sections'][$i]['body']) ? $jobInterviewDataJson['articles'][1]['sections'][$i]['body'] : '';
  #name属性に連番を付与
  if ($i == 0) {
    $interview2HeadingName = 'interview2_heading';
    $interview2BodyName = 'interview2_body';
  } else {
    $interview2HeadingName = 'interview2_heading' . $i;
    $interview2BodyName = 'interview2_body' . $i;
  }
  print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し{$key}</dt>
                      <dd><input type="text" name="{$interview2HeadingName}" value="{$interview2HeadingValue}" style="max-width: 34rem"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="{$interview2BodyName}">{$interview2BodyValue}</textarea></dd>
                    </dl>

HTML;
}
print <<<HTML
                  </div>
                </div>
              </li>
              <li>
                <div class="wrap-head">
                  <h4>#3の記事</h4>
                  <button type="button" id="btnSwitchDetails03" class="btn-switch" aria-controls="interviewDetails03" aria-expanded="false"></button>
                </div>
                <div class="wrap-details" id="interviewDetails03">
                  <div class="inner-details">
                    <dl>
                      <dt>タイトル</dt>

HTML;
#タイトル入力チェック
$interview3Title = isset($jobInterviewDataJson['articles'][2]['title']) ? $jobInterviewDataJson['articles'][2]['title'] : '';
print <<<HTML
                      <dd><input type="text" name="interview3_title" value="{$interview3Title}"></dd>
                    </dl>
                    <dl style="margin-top: 1.6rem">
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-interview3Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-interview3Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-interview3Image">
                          <input type="hidden" name="upload_image_area" value="interview3_image" id="js-uploadImageArea-interview3Image">
                          <input type="hidden" name="up_image_area[]" value="interview3_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-interview3Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-interview3Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
if (isset($jobInterviewDataJson['articles'][2]['image']) && $jobInterviewDataJson['articles'][2]['image'] != '') {
  $ext = strtolower(pathinfo($info, PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $jobInterviewDataJson['articles'][2]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-interview3Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-interview3Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
for ($i = 0; $i < 2; $i++) {
  $key = $i + 1;
  $interview3HeadingValue = isset($jobInterviewDataJson['articles'][2]['sections'][$i]['heading']) ? $jobInterviewDataJson['articles'][2]['sections'][$i]['heading'] : '';
  $interview3BodyValue = isset($jobInterviewDataJson['articles'][2]['sections'][$i]['body']) ? $jobInterviewDataJson['articles'][2]['sections'][$i]['body'] : '';
  #name属性に連番を付与
  if ($i == 0) {
    $interview3HeadingName = 'interview3_heading';
    $interview3BodyName = 'interview3_body';
  } else {
    $interview3HeadingName = 'interview3_heading' . $i;
    $interview3BodyName = 'interview3_body' . $i;
  }
  print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し{$key}</dt>
                      <dd><input type="text" name="{$interview3HeadingName}" value="{$interview3HeadingValue}" style="max-width: 34rem"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="{$interview3BodyName}">{$interview3BodyValue}</textarea></dd>
                    </dl>

HTML;
}
print <<<HTML
                  </div>
                </div>
              </li>
            </ul>
          </article>
          <hr style="margin-top: -2.4rem">
          <article class="block-movie" data-field="block_movie">
            <div class="box-head">
              <h3>職場紹介動画</h3>
            </div>

HTML;
#職場紹介動画情報取得
$jobMovieData = getJobCardArticle_FindByJobId($jobId, 'movie');
$jobMovieDataJson = array();
#職場紹介動画が無ければ初期化
if (is_array($jobMovieData) === false || count($jobMovieData) === 0) {
  #初期化構造体生成
  $videosMap = array(
    'id' => '',
    'url' => '',
    'title' => '',
  );
  $jobMovieDataJson = array(
    'videos' => array_fill(0, 2, $videosMap),
  );
} else {
  #動画情報デコード
  $jobMovieDataJson = json_decode($jobMovieData['content_json'], true);
}
#動画URL入力チェック
$video1URL = isset($jobMovieDataJson['videos'][0]['url']) ? $jobMovieDataJson['videos'][0]['url'] : '';
$video2URL = isset($jobMovieDataJson['videos'][1]['url']) ? $jobMovieDataJson['videos'][1]['url'] : '';
print <<<HTML
            <div class="inner-blok-movie">
              <dl>
                <dt class="position-top">紹介動画</dt>
                <dd>
                  <input type="text" name="video1_url" value="{$video1URL}">
                  <p>※YouTubeの動画リンクを設定してください</p>
                </dd>
              </dl>
              <hr>
              <dl>
                <dt class="position-top">360度動画</dt>
                <dd>
                  <input type="text" name="video2_url" value="{$video2URL}">
                  <p>※YouTubeの動画リンクを設定してください</p>
                </dd>
              </dl>
            </div>
          </article>
          <hr>
          <article class="block-benefits" data-field="block_benefits">
            <div class="box-head">
              <h3>福利厚生</h3>
            </div>

HTML;
#福利厚生情報取得
$benefitsData = getJobCardArticle_FindByJobId($jobId, 'benefits');
$benefitsDataJson = array();
#福利厚生情報取が無ければ初期化
if (is_array($benefitsData) === false || count($benefitsData) === 0) {
  #初期化構造体生成
  $benefitsMap = array(
    'id' => '',
    'title' => '',
    'body' => '',
    'image' => '',
  );
  $benefitsDataJson = array(
    'sections' => array_fill(0, 4, $benefitsMap),
  );
} else {
  #福利厚生内容デコード
  $benefitsDataJson = json_decode($benefitsData['content_json'], true);
}
print <<<HTML
            <ul class="list-benefits">
              <li>
                <div class="wrap-head">
                  <h4>福利厚生その１</h4>
                  <button type="button" id="btnSwitchBenefits01" class="btn-switch is-active" aria-controls="interviewBenefits01" aria-expanded="true"></button>
                </div>
                <div class="wrap-details is-active" id="interviewBenefits01">
                  <div class="inner-details">
                    <dl>
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-benefits1Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-benefits1Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-benefits1Image">
                          <input type="hidden" name="upload_image_area" value="benefits1_image" id="js-uploadImageArea-benefits1Image">
                          <input type="hidden" name="up_image_area[]" value="benefits1_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-benefits1Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-benefits1Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
if (isset($benefitsDataJson['sections'][0]['image']) && $benefitsDataJson['sections'][0]['image'] != '') {
  #登録画像リスト展開
  $ext = strtolower(pathinfo($benefitsDataJson['sections'][0]['image'], PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $benefitsDataJson['sections'][0]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits1Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits1Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
#タイトル・本文入力チェック
$benefits11Title = isset($benefitsDataJson['sections'][0]['title']) ? $benefitsDataJson['sections'][0]['title'] : '';
$benefits1Body = isset($benefitsDataJson['sections'][0]['body']) ? $benefitsDataJson['sections'][0]['body'] : '';
print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し</dt>
                      <dd><input type="text" name="benefits1_title" value="{$benefits11Title}"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="benefits1_body">{$benefits1Body}</textarea></dd>
                    </dl>
                  </div>
                </div>
              </li>
              <li>
                <div class="wrap-head">
                  <h4>福利厚生その２</h4>
                  <button type="button" id="btnSwitchBenefits02" class="btn-switch" aria-controls="interviewBenefits02" aria-expanded="true"></button>
                </div>
                <div class="wrap-details is-active" id="interviewBenefits02">
                  <div class="inner-details">
                    <dl>
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-benefits2Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-benefits2Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-benefits2Image">
                          <input type="hidden" name="upload_image_area" value="benefits2_image" id="js-uploadImageArea-benefits2Image">
                          <input type="hidden" name="up_image_area[]" value="benefits2_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-benefits2Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-benefits2Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
if (isset($benefitsDataJson['sections'][1]['image']) && $benefitsDataJson['sections'][1]['image'] != '') {
  #登録画像リスト展開
  $ext = strtolower(pathinfo($benefitsDataJson['sections'][1]['image'], PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $benefitsDataJson['sections'][1]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits2Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits2Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
#タイトル・本文入力チェック
$benefits21Title = isset($benefitsDataJson['sections'][1]['title']) ? $benefitsDataJson['sections'][1]['title'] : '';
$benefits2Body = isset($benefitsDataJson['sections'][1]['body']) ? $benefitsDataJson['sections'][1]['body'] : '';
print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し</dt>
                      <dd><input type="text" name="benefits2_title" value="{$benefits21Title}"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="benefits2_body">{$benefits2Body}</textarea></dd>
                    </dl>
                  </div>
                </div>
              </li>
              <li>
                <div class="wrap-head">
                  <h4>福利厚生その３</h4>
                  <button type="button" id="btnSwitchBenefits03" class="btn-switch" aria-controls="interviewBenefits03" aria-expanded="true"></button>
                </div>
                <div class="wrap-details is-active" id="interviewBenefits03">
                  <div class="inner-details">
                    <dl>
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-benefits3Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-benefits3Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-benefits3Image">
                          <input type="hidden" name="upload_image_area" value="benefits3_image" id="js-uploadImageArea-benefits3Image">
                          <input type="hidden" name="up_image_area[]" value="benefits3_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-benefits3Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-benefits3Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
if (isset($benefitsDataJson['sections'][2]['image']) && $benefitsDataJson['sections'][2]['image'] != '') {
  #登録画像リスト展開
  $ext = strtolower(pathinfo($benefitsDataJson['sections'][2]['image'], PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $benefitsDataJson['sections'][2]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits3Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits3Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
#タイトル・本文入力チェック
$benefits31Title = isset($benefitsDataJson['sections'][2]['title']) ? $benefitsDataJson['sections'][2]['title'] : '';
$benefits3Body = isset($benefitsDataJson['sections'][2]['body']) ? $benefitsDataJson['sections'][2]['body'] : '';
print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し</dt>
                      <dd><input type="text" name="benefits3_title" value="{$benefits31Title}"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="benefits3_body">{$benefits3Body}</textarea></dd>
                    </dl>
                  </div>
                </div>
              </li>
              <li>
                <div class="wrap-head">
                  <h4>福利厚生その４</h4>
                  <button type="button" id="btnSwitchBenefits04" class="btn-switch" aria-controls="interviewBenefits04" aria-expanded="true"></button>
                </div>
                <div class="wrap-details is-active" id="interviewBenefits04">
                  <div class="inner-details">
                    <dl>
                      <dt class="position-top">画像</dt>
                      <dd class="dd-select-image">
                        <!-- NOTE 画像登録時は [is-active]付与 -->
                        <div class="select-image" id="js-dragDrop-benefits4Image">
                          <h4>ここにファイルをドロップ</h4>
                          <span>または</span>
                          <input type="file" name="images_tmp" id="js-fileElem-benefits4Image" multiple accept="image/*" style="display:none">
                          <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-benefits4Image">
                          <input type="hidden" name="upload_image_area" value="benefits4_image" id="js-uploadImageArea-benefits4Image">
                          <input type="hidden" name="up_image_area[]" value="benefits4_image">
                          <input type="hidden" name="send_php" value="proc_client03_01_01.php">
                          <button type="button" id="js-fileSelect-benefits4Image">ファイルを選択</button>
                          <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                          <!-- NOTE 警告用表示 -->
                          <div class="wrap-caution" id="js-fileError-benefits4Image" style="display: none;">
                            <h5>ファイルサイズが大きすぎます</h5>
                            <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                          </div>
                        </div>

HTML;
if (isset($benefitsDataJson['sections'][3]['image']) && $benefitsDataJson['sections'][3]['image'] != '') {
  #登録画像リスト展開
  $ext = strtolower(pathinfo($benefitsDataJson['sections'][3]['image'], PATHINFO_EXTENSION));
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $mimeType = 'image/jpeg';
      break;
    case 'png':
      $mimeType = 'image/png';
      break;
    case 'gif':
      $mimeType = 'image/gif';
      break;
    default:
      $mimeType = '';
      break;
  }
  $previewPath = DOMAIN_NAME . $benefitsDataJson['sections'][3]['image'];
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits4Image">
                          <li>
                            <div class="warp-btn">
                              <button type="button" class="btn-change"></button>
                              <button type="button" class="btn-delate"></button>
                            </div>
                            <picture>
                              <source src="{$previewPath}" type="{$mimeType}">
                              <img src="{$previewPath}" alt="インタビュー画像">
                            </picture>
                          </li>
                        </ul>

HTML;
} else {
  print <<<HTML
                        <ul class="selected-image-list" id="js-previewBlock-benefits4Image"></ul>

HTML;
}
print <<<HTML
                        <div class="wrap-notice">
                          <p>※画像は1枚登録可能です</p>
                          <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                          <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                          <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                        </div>
                      </dd>
                    </dl>

HTML;
#タイトル・本文入力チェック
$benefits41Title = isset($benefitsDataJson['sections'][3]['title']) ? $benefitsDataJson['sections'][3]['title'] : '';
$benefits4Body = isset($benefitsDataJson['sections'][3]['body']) ? $benefitsDataJson['sections'][3]['body'] : '';
print <<<HTML
                    <dl style="margin-top: 1.6rem">
                      <dt>見出し</dt>
                      <dd><input type="text" name="benefits4_title" value="{$benefits41Title}"></dd>
                    </dl>
                    <dl>
                      <dt>本文</dt>
                      <dd><textarea name="benefits4_body">{$benefits4Body}</textarea></dd>
                    </dl>
                  </div>
                </div>
              </li>
            </ul>
            <!-- <div class="box-premium-ban">
              <h3>特別バナープランの設定について</h3>
              <p>
                特別バナープランはこちらのページでは設定できません。｢事業所管理］→｢事業所情報｣にて設定が行えます。
              </p>
            </div> -->
          </article>
          <hr>
          <article class="block-workplace" id="blockWorkSpace">
            <div class="box-head">
              <h3>職場環境の特徴</h3>
              <p>求人カードの基本情報を入力ください</p>
            </div>

HTML;
#職場環境の特徴 各項目の値を取得
$metricsMap = [];
if (is_array($workEnvironmentMetricsData)) {
  foreach ($workEnvironmentMetricsData as $row) {
    if (isset($row['metric_id'])) {
      $metricsMap[$row['metric_id']] = $row['value'] ?? '';
    }
  }
}
#項目１
$selectedMetricArea1 = isset($workEnvironmentMetricsData[0]['metric_id']) ? $workEnvironmentMetricsData[0]['metric_id'] : '';
$metricArea1Value = isset($metricsMap[$selectedMetricArea1]) ? htmlspecialchars(normalizeMetricValue($metricsMap[$selectedMetricArea1]), ENT_QUOTES, 'UTF-8') : '';
#項目２
$selectedMetricArea2 = isset($workEnvironmentMetricsData[1]['metric_id']) ? $workEnvironmentMetricsData[1]['metric_id'] : '';
$metricArea2Value = isset($metricsMap[$selectedMetricArea2]) ? htmlspecialchars(normalizeMetricValue($metricsMap[$selectedMetricArea2]), ENT_QUOTES, 'UTF-8') : '';
#項目３
$selectedMetricArea3 = isset($workEnvironmentMetricsData[2]['metric_id']) ? $workEnvironmentMetricsData[2]['metric_id'] : '';
$metricArea3Value = isset($metricsMap[$selectedMetricArea3]) ? htmlspecialchars(normalizeMetricValue($metricsMap[$selectedMetricArea3]), ENT_QUOTES, 'UTF-8') : '';
print <<<HTML
            <div class="box-details">
              <span>項目</span>
              <span>数字</span>
              <dl>
                <dt>
                  <div class="select-workplace" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#職場環境の特徴が選択されていたら
if (isset($selectedMetricArea1) && $selectedMetricArea1 != '') {
  #選択中のラベル取得
  foreach ($workEnvironmentMetrics as $metric) {
    if ($selectedMetricArea1 == $metric['id']) {
      print <<<HTML
                      <input type="hidden" name="work_environment_metrics1" value="{$metric['id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$metric['label']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                      <input type="hidden" name="work_environment_metrics1" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($workEnvironmentMetrics) && is_array($workEnvironmentMetrics) && count($workEnvironmentMetrics) > 0) {
  foreach ($workEnvironmentMetrics as $metric) {
    #checked判定
    $checked = ($selectedMetricArea1 == $metric['id']) ? 'checked' : '';
    print <<<HTML
                        <li>
                          <input type="radio" name="work_environment_metrics1" value="{$metric['id']}" data-unit="{$metric['unit']}" id="workplace01-{$metric['id']}" {$checked}>
                          <label for="workplace01-{$metric['id']}">{$metric['label']}</label>
                        </li>

HTML;
  }
} else {
  print <<<HTML
                        <li>
                          <input type="radio" name="work_environment_metrics1" value="1" id="workplace01-01">
                          <label for="workplace01-01">職場環境の特徴が未設定です</label>
                        </li>

HTML;
}
print <<<HTML
                      </ul>
                    </div>
                  </div>
                </dt>
                <!--NOTE セレクト項目に応じて単位を変更 -->
                <dd>
                  <input type="text" name="work_environment_metrics1_value" value="{$metricArea1Value}" style="max-width: 144px"><span>％</span>
                  <input type="hidden" name="work_environment_metrics1_unit" value="">
                </dd>
              </dl>
              <dl>
                <dt>
                  <div class="select-workplace" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#職場環境の特徴が選択されていたら
if (isset($selectedMetricArea2) && $selectedMetricArea2 != '') {
  #選択中のラベル取得
  foreach ($workEnvironmentMetrics as $metric) {
    if ($selectedMetricArea2 == $metric['id']) {
      print <<<HTML
                      <input type="hidden" name="work_environment_metrics2" value="{$metric['id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$metric['label']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                      <input type="hidden" name="work_environment_metrics2" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($workEnvironmentMetrics) && is_array($workEnvironmentMetrics) && count($workEnvironmentMetrics) > 0) {
  foreach ($workEnvironmentMetrics as $metric) {
    #checked判定
    $checked = ($selectedMetricArea2 == $metric['id']) ? 'checked' : '';
    print <<<HTML
                        <li>
                          <input type="radio" name="work_environment_metrics2" value="{$metric['id']}" data-unit="{$metric['unit']}" id="workplace02-{$metric['id']}" {$checked}>
                          <label for="workplace02-{$metric['id']}">{$metric['label']}</label>
                        </li>

HTML;
  }
} else {
  print <<<HTML
                        <li>
                          <input type="radio" name="work_environment_metrics2" value="1" id="workplace02-01">
                          <label for="workplace02-01">職場環境の特徴が未設定です</label>
                        </li>

HTML;
}
print <<<HTML
                      </ul>
                    </div>
                  </div>
                </dt>
                <!--NOTE セレクト項目に応じて単位を変更 -->
                <dd>
                  <input type="text" name="work_environment_metrics2_value" value="{$metricArea2Value}" style="max-width: 144px"><span>ｈ(時間)</span>
                  <input type="hidden" name="work_environment_metrics2_unit" value="">
                </dd>
              </dl>
              <dl>
                <dt>
                  <div class="select-workplace" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#職場環境の特徴が選択されていたら
if (isset($selectedMetricArea3) && $selectedMetricArea3 != '') {
  #選択中のラベル取得
  foreach ($workEnvironmentMetrics as $metric) {
    if ($selectedMetricArea3 == $metric['id']) {
      print <<<HTML
                      <input type="hidden" name="work_environment_metrics3" value="{$metric['id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$metric['label']}</span>

HTML;
    }
  }
} else {
  print <<<HTML
                      <input type="hidden" name="work_environment_metrics3" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($workEnvironmentMetrics) && is_array($workEnvironmentMetrics) && count($workEnvironmentMetrics) > 0) {
  foreach ($workEnvironmentMetrics as $metric) {
    #checked判定
    $checked = ($selectedMetricArea3 == $metric['id']) ? 'checked' : '';
    print <<<HTML
                        <li>
                          <input type="radio" name="work_environment_metrics3" value="{$metric['id']}" data-unit="{$metric['unit']}" id="workplace03-{$metric['id']}" {$checked}>
                          <label for="workplace03-{$metric['id']}">{$metric['label']}</label>
                        </li>

HTML;
  }
} else {
  print <<<HTML
                        <li>
                          <input type="radio" name="work_environment_metrics3" value="1" id="workplace03-01">
                          <label for="workplace03-01">職場環境の特徴が未設定です</label>
                        </li>

HTML;
}
print <<<HTML
                      </ul>
                    </div>
                  </div>
                </dt>
                <!--NOTE セレクト項目に応じて単位を変更 -->
                <dd>
                  <input type="text" name="work_environment_metrics3_value" value="{$metricArea3Value}" style="max-width: 144px"><span>年</span>
                  <input type="hidden" name="work_environment_metrics3_unit" value="">
                </dd>
              </dl>
              <p class="notice">※入力例：月平均残業、15.3、h</p>
            </div>
          </article>
          <hr>
          <article class="block-free-text" id="blockFreeText">
            <div class="box-head">
              <h3>フリーテキストスペース</h3>
              <p>業務内容の紹介や伝えたいメッセージなど自由に入力ください</p>
            </div>

HTML;
#フリーテキスト情報取得
$jobFreeTextData = getJobCardArticle_FindByJobId($jobId, 'freespace');
$jobFreeTextDataJson = array();
#フリーテキストが無ければ初期化
if (is_array($jobFreeTextData) === false || count($jobFreeTextData) === 0) {
  #初期化構造体生成
  $freeTextMap = array(
    'heading' => '',
    'body' => '',
  );
  $jobFreeTextDataJson = array(
    'title' => '',
    'sections' => array_fill(0, 4, $freeTextMap),
  );
} else {
  #フリーテキストデコード
  $jobFreeTextDataJson = json_decode($jobFreeTextData['content_json'], true);
}
#タイトル・本文入力チェック
$freeText11Title = isset($jobFreeTextDataJson['title']) ? $jobFreeTextDataJson['title'] : '';
$freeText11Heading = isset($jobFreeTextDataJson['sections'][0]['heading']) ? $jobFreeTextDataJson['sections'][0]['heading'] : '';
$freeText1Body = isset($jobFreeTextDataJson['sections'][0]['body']) ? $jobFreeTextDataJson['sections'][0]['body'] : '';
$freeText21Heading = isset($jobFreeTextDataJson['sections'][1]['heading']) ? $jobFreeTextDataJson['sections'][1]['heading'] : '';
$freeText2Body = isset($jobFreeTextDataJson['sections'][1]['body']) ? $jobFreeTextDataJson['sections'][1]['body'] : '';
$freeText31Heading = isset($jobFreeTextDataJson['sections'][2]['heading']) ? $jobFreeTextDataJson['sections'][2]['heading'] : '';
$freeText3Body = isset($jobFreeTextDataJson['sections'][2]['body']) ? $jobFreeTextDataJson['sections'][2]['body'] : '';
$freeText41Heading = isset($jobFreeTextDataJson['sections'][3]['heading']) ? $jobFreeTextDataJson['sections'][3]['heading'] : '';
$freeText4Body = isset($jobFreeTextDataJson['sections'][3]['body']) ? $jobFreeTextDataJson['sections'][3]['body'] : '';
print <<<HTML
            <div class="box-details">
              <dl>
                <dt>タイトル</dt>
                <dd><input type="text" name="free_text_title" value="{$freeText11Title}"></dd>
              </dl>
              <dl style="margin-top: 1.6rem">
                <dt>見出し１</dt>
                <dd><input type="text" name="free_text1_heading" value="{$freeText11Heading}"></dd>
              </dl>
              <dl>
                <dt class="position-top">本文</dt>
                <dd><textarea name="free_text1_body">{$freeText1Body}</textarea></dd>
              </dl>
              <dl style="margin-top: 1.6rem">
                <dt>見出し２</dt>
                <dd><input type="text" name="free_text2_heading" value="{$freeText21Heading}"></dd>
              </dl>
              <dl>
                <dt class="position-top">本文</dt>
                <dd><textarea name="free_text2_body">{$freeText2Body}</textarea></dd>
              </dl>
              <dl style="margin-top: 1.6rem">
                <dt>見出し３</dt>
                <dd><input type="text" name="free_text3_heading" value="{$freeText31Heading}"></dd>
              </dl>
              <dl>
                <dt class="position-top">本文</dt>
                <dd><textarea name="free_text3_body">{$freeText3Body}</textarea></dd>
              </dl>
              <dl style="margin-top: 1.6rem">
                <dt>見出し４</dt>
                <dd><input type="text" name="free_text4_heading" value="{$freeText41Heading}"></dd>
              </dl>
              <dl>
                <dt class="position-top">本文</dt>
                <dd><textarea name="free_text4_body">{$freeText4Body}</textarea></dd>
              </dl>
            </div>
          </article>
          <hr>
          <article class="block-schedule" id="blockSchedule">
            <div class="box-head">
              <h3 style="min-width: 5em">1日の流れ</h3>
              <p>
                出勤から退勤までの業務スケジュールを入力してください。（｢日勤｣または｢夜勤｣のいずれかを必ず入力してください。）
              </p>
            </div>

HTML;
#一日の流れ取得
$jobDailyScheduleData = getJobCardArticle_FindByJobId($jobId, 'dailySchedule');
$jobDailyScheduleDataJson = array();
#一日の流れが無ければ初期化
if (is_array($jobDailyScheduleData) === false || count($jobDailyScheduleData) === 0) {
  #初期化構造体生成
  $scheduleMap = array(
    'time' => '',
    'body' => '',
  );
  $jobDailyScheduleDataJson = array(
    'dailySchedule' => array(
      'dayShift' => array_fill(0, 4, $scheduleMap),
      'nightShift' => array_fill(0, 4, $scheduleMap),
    ),
  );
} else {
  #一日の流れデコード
  $jobDailyScheduleDataJson = json_decode($jobDailyScheduleData['content_json'], true);
}
print <<<HTML
            <div class="box-schedule-list">
              <h4>日勤スケジュール</h4>
              <ul class="schedule-list day-shift">

HTML;
if (isset($jobDailyScheduleDataJson['dayShift']) && is_array($jobDailyScheduleDataJson['dayShift']) && count($jobDailyScheduleDataJson['dayShift']) > 0) {
  foreach ($jobDailyScheduleDataJson['dayShift'] as $dayIndex => $daySchedule) {
    $timeValue = htmlspecialchars($daySchedule['time'], ENT_QUOTES, 'UTF-8');
    $time = explode(':', $timeValue);
    $min = isset($time[1]) ? $time[1] : '--';
    $bodyValue = htmlspecialchars($daySchedule['body'], ENT_QUOTES, 'UTF-8');
    print <<<HTML
                <li>
                  <div class="wrap-time">
                    <div class="select-hour" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
    #ボタン生成フラグ
    $hourButtonGenerated = false;
    for ($h = 5; $h <= 24; $h++) {
      $hourValue = str_pad($h, 2, '0', STR_PAD_LEFT);
      if ($time[0] == $hourValue) {
        $hourButtonGenerated = true;
        print <<<HTML
                        <input type="hidden" name="day_list_hour[]" value="{$hourValue}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$hourValue}</span>

HTML;
        break;
      }
    }
    if ($hourButtonGenerated == false) {
      print <<<HTML
                        <input type="hidden" name="day_list_hour[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>

HTML;
    }
    print <<<HTML
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($h = 5; $h <= 24; $h++) {
      $hourValue = str_pad($h, 2, '0', STR_PAD_LEFT);
      #checked判定
      $checked = ($time[0] === $hourValue) ? 'checked' : '';
      print <<<HTML
                          <li>
                            <input type="radio" name="day_list_hour[]" value="{$hourValue}" id="hour{$hourValue}-{$dayIndex}" {$checked}>
                            <label for="hour{$hourValue}-{$dayIndex}">{$hourValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>時</span>
                    <div class="select-min" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
    #ボタン生成フラグ
    $minButtonGenerated = false;
    for ($m = 0; $m <= 45; $m += 15) {
      $minValue = str_pad($m, 2, '0', STR_PAD_LEFT);
      if ($min === $minValue) {
        $minButtonGenerated = true;
        print <<<HTML
                        <input type="hidden" name="day_list_min[]" value="{$minValue}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$minValue}</span>

HTML;
      }
    }
    if ($minButtonGenerated == false) {
      print <<<HTML
                        <input type="hidden" name="day_list_min[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>

HTML;
    }
    print <<<HTML
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($m = 0; $m <= 45; $m += 15) {
      $minValue = str_pad($m, 2, '0', STR_PAD_LEFT);
      #checked判定
      $checked = ($min === $minValue) ? 'checked' : '';
      print <<<HTML
                          <li>
                            <input type="radio" name="day_list_min[]" value="{$minValue}" id="min{$minValue}-{$dayIndex}" {$checked}>
                            <label for="min{$minValue}-{$dayIndex}">{$minValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>分</span>
                  </div>
                  <input type="text" name="day_list_body[]" value="{$bodyValue}">
                  <!-- <textarea name="day_list_body[]">{$bodyValue}</textarea> -->
                  <div class="wrap-btn">
                    <button type="button" class="item-increase"></button>
                    <button type="button" class="item-decrease"></button>
                  </div>
                </li>

HTML;
  }
} else {
  for ($dayIndex = 0; $dayIndex < 4; $dayIndex++) {
    print <<<HTML
                <li>
                  <div class="wrap-time">
                    <div class="select-hour" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input type="hidden" name="day_list_hour[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($h = 5; $h <= 24; $h++) {
      $hourValue = str_pad($h, 2, '0', STR_PAD_LEFT);
      print <<<HTML
                          <li>
                            <input type="radio" name="day_list_hour[]" value="{$hourValue}" id="hour{$hourValue}-{$dayIndex}">
                            <label for="hour{$hourValue}-{$dayIndex}">{$hourValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>時</span>
                    <div class="select-min" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input type="hidden" name="day_list_min[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($m = 0; $m <= 45; $m += 15) {
      $minValue = str_pad($m, 2, '0', STR_PAD_LEFT);
      print <<<HTML
                          <li>
                            <input type="radio" name="day_list_min[]" value="{$minValue}" id="min{$minValue}-{$dayIndex}">
                            <label for="min{$minValue}-{$dayIndex}">{$minValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>分</span>
                  </div>
                  <input type="text" name="day_list_body[]" value="">
                  <!-- <textarea name="day_list_body[]"></textarea> -->
                  <div class="wrap-btn">
                    <button type="button" class="item-increase"></button>
                    <button type="button" class="item-decrease"></button>
                  </div>
                </li>

HTML;
  }
}
print <<<HTML
              </ul>
            </div>
            <div class="box-schedule-list">
              <h4>夜勤スケジュール</h4>
              <ul class="schedule-list night-shift">

HTML;
if (isset($jobDailyScheduleDataJson['nightShift']) && is_array($jobDailyScheduleDataJson['nightShift']) && count($jobDailyScheduleDataJson['nightShift']) > 0) {
  foreach ($jobDailyScheduleDataJson['nightShift'] as $nightIndex => $nightSchedule) {
    $timeValue = htmlspecialchars($nightSchedule['time'], ENT_QUOTES, 'UTF-8');
    $time = explode(':', $timeValue);
    $min = isset($time[1]) ? $time[1] : '--';
    $bodyValue = htmlspecialchars($nightSchedule['body'], ENT_QUOTES, 'UTF-8');
    print <<<HTML
                <li>
                  <div class="wrap-time">
                    <div class="select-hour" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
    #ボタン生成フラグ
    $hourButtonGenerated = false;
    for ($h = 5; $h <= 24; $h++) {
      $hourValue = str_pad($h, 2, '0', STR_PAD_LEFT);
      if ($time[0] == $hourValue) {
        $hourButtonGenerated = true;
        print <<<HTML
                        <input type="hidden" name="night_list_hour[]" value="{$hourValue}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$hourValue}</span>

HTML;
        break;
      }
    }
    if ($hourButtonGenerated == false) {
      print <<<HTML
                        <input type="hidden" name="night_list_hour[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>

HTML;
    }
    print <<<HTML
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($h = 5; $h <= 24; $h++) {
      $hourValue = str_pad($h, 2, '0', STR_PAD_LEFT);
      #checked判定
      $checked = ($time[0] === $hourValue) ? 'checked' : '';
      print <<<HTML
                          <li>
                            <input type="radio" name="night_list_hour[]" value="{$hourValue}" id="nightHour{$hourValue}-{$nightIndex}" {$checked}>
                            <label for="nightHour{$hourValue}-{$nightIndex}">{$hourValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>時</span>
                    <div class="select-min" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
    #ボタン生成フラグ
    $minButtonGenerated = false;
    for ($m = 0; $m <= 45; $m += 15) {
      $minValue = str_pad($m, 2, '0', STR_PAD_LEFT);
      if ($min === $minValue) {
        $minButtonGenerated = true;
        print <<<HTML
                        <input type="hidden" name="night_list_min[]" value="{$minValue}" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>{$minValue}</span>

HTML;
      }
    }
    if ($minButtonGenerated == false) {
      print <<<HTML
                        <input type="hidden" name="night_list_min[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>

HTML;
    }
    print <<<HTML
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($m = 0; $m <= 45; $m += 15) {
      $minValue = str_pad($m, 2, '0', STR_PAD_LEFT);
      #checked判定
      $checked = ($min === $minValue) ? 'checked' : '';
      print <<<HTML
                          <li>
                            <input type="radio" name="night_list_min[]" value="{$minValue}" id="nightMin{$minValue}-{$nightIndex}" {$checked}>
                            <label for="nightMin{$minValue}-{$nightIndex}">{$minValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>分</span>
                  </div>
                  <input type="text" name="night_list_body[]" value="{$bodyValue}">
                  <!-- <textarea name="night_list_body[]">{$bodyValue}</textarea> -->
                  <div class="wrap-btn">
                    <button type="button" class="item-increase"></button>
                    <button type="button" class="item-decrease"></button>
                  </div>
                </li>

HTML;
  }
} else {
  for ($nightIndex = 0; $nightIndex < 4; $nightIndex++) {
    print <<<HTML
                <li>
                  <div class="wrap-time">
                    <div class="select-hour" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input type="hidden" name="night_list_hour[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($h = 5; $h <= 24; $h++) {
      $hourValue = str_pad($h, 2, '0', STR_PAD_LEFT);
      print <<<HTML
                          <li>
                            <input type="radio" name="night_list_hour[]" value="{$hourValue}" id="nightHour{$hourValue}-{$nightIndex}">
                            <label for="nightHour{$hourValue}-{$nightIndex}">{$hourValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>時</span>
                    <div class="select-min" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input type="hidden" name="night_list_min[]" value="" data-selectbox-hidden>
                        <span class="selectbox__value" data-selectbox-value>-</span>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">

HTML;
    for ($m = 0; $m <= 45; $m += 15) {
      $minValue = str_pad($m, 2, '0', STR_PAD_LEFT);
      print <<<HTML
                          <li>
                            <input type="radio" name="night_list_min[]" value="{$minValue}" id="nightMin{$minValue}-{$nightIndex}">
                            <label for="nightMin{$minValue}-{$nightIndex}">{$minValue}</label>
                          </li>

HTML;
    }
    print <<<HTML
                        </ul>
                      </div>
                    </div>
                    <span>分</span>
                  </div>
                  <input type="text" name="night_list_body[]" value="">
                  <!-- <textarea name="night_list_body[]"></textarea> -->
                  <div class="wrap-btn">
                    <button type="button" class="item-increase"></button>
                    <button type="button" class="item-decrease"></button>
                  </div>
                </li>

HTML;
  }
}
print <<<HTML
              </ul>
            </div>
          </article>
          <hr>
          <article class="block-detail-info" id="blockDetailInfo">
            <div class="box-head">
              <h3>詳細情報</h3>
            </div>
            <dl>
              <dt>仕事内容<i>※</i></dt>
              <dd>
                <ul class="worktype-list">

HTML;
#表示可能リストあればループ処理
if (isset($jobContentOptions) && is_array($jobContentOptions) && count($jobContentOptions) > 0) {
  foreach ($jobContentOptions as $jobContentOption) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'job_content' &&
          $jobOptionGroup['option_id'] === $jobContentOption['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="worktype-item">
                      <input type="checkbox" name="job_content[]" value="{$jobContentOption['id']}" {$checked}>
                      <span>{$jobContentOption['name']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="worktype-item">
                      <input type="checkbox" name="job_content[]" value="none">
                      <span>仕事内容が未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック
$jobContentNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'job_content') {
    $jobContentNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="job_content_notice">{$jobContentNotice}</textarea>
                <div class="wrap-notice">
                  <p>※パート・契約職員の募集の場合は以下を明記ください。</p>
                  <p>・雇用期間の定めの有無および期間</p>
                  <p>・更新および更新上限の有無</p>
                  <p>・更新条件</p>
                </div>
              </dd>
            </dl>
            <dl>
              <dt>診療科目</dt>
              <dd>
                <ul class="department-list">

HTML;
#表示可能リストあればループ処理
if (isset($clinicalDepartments) && is_array($clinicalDepartments) && count($clinicalDepartments) > 0) {
  foreach ($clinicalDepartments as $clinicalDepartment) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'clinical_department' &&
          $jobOptionGroup['option_id'] === $clinicalDepartment['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="department-item">
                      <input type="checkbox" name="clinical_department[]" value="{$clinicalDepartment['id']}" {$checked}>
                      <span>{$clinicalDepartment['name']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="department-item">
                      <input type="checkbox" name="clinical_department[]" value="none">
                      <span>診療科目が未設定です</span>
                    </label>
                  </li>

HTML;
}
print <<<HTML
                </ul>
              </dd>
            </dl>
            <dl>
              <dt>サービス形態</dt>
              <dd>
                <ul class="benefits-list">

HTML;
#表示可能リストあればループ処理
if (isset($serviceTypeOptions) && is_array($serviceTypeOptions) && count($serviceTypeOptions) > 0) {
  foreach ($serviceTypeOptions as $serviceType) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'service_type' &&
          $jobOptionGroup['option_id'] === $serviceType['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="service_type-item">
                      <input type="checkbox" name="service_type[]" value="{$serviceType['id']}" {$checked}>
                      <span>{$serviceType['name']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="service_type-item">
                      <input type="checkbox" name="service_type[]" value="none">
                      <span>サービス形態が未設定です</span>
                    </label>
                  </li>

HTML;
}
print <<<HTML
                </ul>
              </dd>
            </dl>
            <dl>
              <dt>待遇</dt>
              <dd>
                <ul class="benefits-list">

HTML;
#表示可能リストあればループ処理
if (isset($benefitOptions) && is_array($benefitOptions) && count($benefitOptions) > 0) {
  foreach ($benefitOptions as $benefitOption) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'benefits' &&
          $jobOptionGroup['option_id'] === $benefitOption['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="benefits-item">
                      <input type="checkbox" name="benefits[]" value="{$benefitOption['id']}" {$checked}>
                      <span>{$benefitOption['name']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="benefits-item">
                      <input type="checkbox" name="benefits[]" value="none">
                      <span>待遇が未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック
$benefitsNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'benefits') {
    $benefitsNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="benefits_notice">{$benefitsNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>勤務時間<i>※</i></dt>
              <dd>
                <ul class="worktime-list">

HTML;
#表示可能リストあればループ処理
if (isset($workStyleOptions) && is_array($workStyleOptions) && count($workStyleOptions) > 0) {
  foreach ($workStyleOptions as $workStyle) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'work_style' &&
          $jobOptionGroup['option_id'] === $workStyle['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="worktime-item">
                      <input type="checkbox" name="work_style[]" value="{$workStyle['id']}" {$checked}>
                      <span>{$workStyle['name']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="worktime-item">
                      <input type="checkbox" name="work_style[]" value="none">
                      <span>勤務時間が未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック
$workStyleNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'work_style') {
    $workStyleNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="work_style_notice">{$workStyleNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>休日<i>※</i></dt>
              <dd>
                <ul class="holiday-list">

HTML;
#表示可能リストあればループ処理
if (isset($holidayOptions) && is_array($holidayOptions) && count($holidayOptions) > 0) {
  foreach ($holidayOptions as $holiday) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'holidays' &&
          $jobOptionGroup['option_id'] === $holiday['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="holiday-item">
                      <input type="checkbox" name="holidays[]" value="{$holiday['id']}" {$checked}>
                      <span>{$holiday['label']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="holiday-item">
                      <input type="checkbox" name="holidays[]" value="none">
                      <span>休日が未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック：休日
$holidayNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'holidays') {
    $holidayNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
#テキスト入力チェック：長期休暇・特別休暇
$longTermHolidayNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'long_term_holiday') {
    $longTermHolidayNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="holidays_notice">{$holidayNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>長期休暇<br>特別休暇</dt>
              <dd>
                <textarea name="long_term_holiday_notice" style="margin-top: 0">{$longTermHolidayNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>応募要件<i>※</i></dt>
              <dd>
                <ul class="welcome-list">

HTML;
#表示可能リストあればループ処理
if (isset($applicationRequirementOptions) && is_array($applicationRequirementOptions) && count($applicationRequirementOptions) > 0) {
  foreach ($applicationRequirementOptions as $requirement) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'requirements' &&
          $jobOptionGroup['option_id'] === $requirement['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="welcome-item">
                      <input type="checkbox" name="requirements[]" value="{$requirement['id']}" {$checked}>
                      <span>{$requirement['name']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="welcome-item">
                      <input type="checkbox" name="requirements[]" value="none">
                      <span>応募要件が未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック：応募要件
$requirementNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'requirements') {
    $requirementNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
#テキスト入力チェック：歓迎要件
$welcomeNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'welcome_requirements') {
    $welcomeNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="requirement_notice">{$requirementNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>歓迎要件</dt>
              <dd>
                <textarea name="welcome_notice" style="margin-top: 0">{$welcomeNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>教育体制<br>研修</dt>
              <dd>
                <ul class="support-list">

HTML;
#表示可能リストあればループ処理
if (isset($trainingSupportOptions) && is_array($trainingSupportOptions) && count($trainingSupportOptions) > 0) {
  foreach ($trainingSupportOptions as $support) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'supports' &&
          $jobOptionGroup['option_id'] === $support['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="support-item">
                      <input type="checkbox" name="supports[]" value="{$support['id']}" {$checked}>
                      <span>{$support['label']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="support-item">
                      <input type="checkbox" name="supports[]" value="none">
                      <span>教育体制・研修が未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック
$supportNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'supports') {
    $supportNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="support_notice">{$supportNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>アクセス</dt>
              <dd>
                <ul class="access-list">

HTML;
#表示可能リストあればループ処理
if (isset($accessOptions) && is_array($accessOptions) && count($accessOptions) > 0) {
  foreach ($accessOptions as $access) {
    $checked = '';
    if (isset($jobOptionGroupData) && is_array($jobOptionGroupData)) {
      foreach ($jobOptionGroupData as $jobOptionGroup) {
        if (
          isset($jobOptionGroup['option_group_code'], $jobOptionGroup['option_id']) &&
          $jobOptionGroup['option_group_code'] === 'accesses' &&
          $jobOptionGroup['option_id'] === $access['id']
        ) {
          $checked = 'checked';
          break;
        }
      }
    }
    print <<<HTML
                  <li>
                    <label class="access-item">
                      <input type="checkbox" name="accesses[]" value="{$access['id']}" {$checked}>
                      <span>{$access['label']}</span>
                    </label>
                  </li>

HTML;
  }
} else {
  print <<<HTML
                  <li>
                    <label class="access-item">
                      <input type="checkbox" name="accesses[]" value="none">
                      <span>アクセスが未設定です</span>
                    </label>
                  </li>

HTML;
}
#テキスト入力チェック：アクセス
$accessNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'accesses') {
    $accessNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
#テキスト入力チェック：選考プロセス
$selectionProcessNotice = '';
foreach ($jobOptionTextData as $jobOptionText) {
  if (isset($jobOptionText['option_group_code']) && $jobOptionText['option_group_code'] == 'selection_process') {
    $selectionProcessNotice = isset($jobOptionText['option_text']) ? $jobOptionText['option_text'] : '';
    break;
  }
}
print <<<HTML
                </ul>
                <textarea name="access_notice">{$accessNotice}</textarea>
              </dd>
            </dl>
            <dl>
              <dt>選考<br>プロセス</dt>
              <dd>
                <textarea name="selection_process" style="margin-top: 0">{$selectionProcessNotice}</textarea>
              </dd>
            </dl>
          </article>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="location.href='./client03_01.php?facId={$facId}'">戻る</button>
          <button type="button" class="item-check" onclick="checkInput('edit')">登録する</button>
        </div>
        <!--NOTE 修正画面のみ表示 -->
        <button type="button" class="btn-delate-item" onclick="checkDeleteJobCard({$facId},{$jobId})">削除する</button>
      </section>
HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>新規求人カード情報登録</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>新規求人カード情報を登録します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="sendInput('edit');">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/form.js" defer></script>
    <script src="../assets/js/dropZone.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/client03_01_01.js" defer></script>
  </body>
</html>

HTML;
