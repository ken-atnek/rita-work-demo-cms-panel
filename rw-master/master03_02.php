<?php
/*
 * [rw-master/master03_02.php]
 *  - 管理画面 -
 *  求人カード一覧
 *
 * [初版]
 *  2025.12.26
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
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

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'mKey03-02_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  if ($key !== 'sKey' && $key !== 'master_login' && $key !== $noUpDateKey) {
    unset($_SESSION[$key]);
  }
}
#セッション本体の初期化
$_SESSION[$noUpDateKey] = array();
#アカウントキー
$_SESSION[$noUpDateKey]['masterKey'] = $_SESSION['master_login']['account_id'];
#データ取得エラー
if ($_SESSION[$noUpDateKey]['masterKey'] < 1) {
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
    'benefitOptions'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[master03_02] master JSON load failed: ' . $e->getMessage());
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
#待遇マスタ
$benefitOptions = $jsonMasters['benefitOptions'] ?? [];

#=============#
# POSTチェック
#-------------#
#事業所ID（編集／削除時のみ）
$facId = isset($_GET['facId']) ? $_GET['facId'] : null;
#事業所IDがあれば事業所情報取得
$jobCardCount = 0;
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
  #求人カード情報取得
  $jobCardList = getJobList($facId);
  $jobCardCount = count($jobCardList);
} else {
  #事業所ID無し：処理終了
  header("Location: ./master03_01.php");
  exit;
}

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data: https://rita-work.jp; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
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
    <main class="inner-03-01-02">
      <section class="page-nav">
        <h2>事業所管理</h2>
        <nav>
          <a href="./master03_01_01.php?method=edit&facId={$facId}">事業所情報</a>
          <a href="./master03_02.php?facId={$facId}" class="is-active">求人カード一覧</a>
          <a href="./master03_03.php?facId={$facId}">パスワード設定</a>
        </nav>
      </section>
      <section class="container-job-card">
        <h2>求人カード一覧<span>住宅型有料老人ホーム メディケア癒やしDX花園</span></h2>
        <article class="block-card-list">
          <p class="announce-results"><span>{$jobCardCount}件</span>が登録中</p>
          <!--NOTE  特別バナー契約時のみ表示 -->

HTML;
if (isset($facilityDetailsJson['specialBanner']['enabled']) && $facilityDetailsJson['specialBanner']['enabled'] == true) {
  print <<<HTML
          <div class="premium-ban-status">特別バナープラン契約中</div>

HTML;
}
print <<<HTML
          <ul class="card-list">

HTML;
#表示可能リストあればループ処理
if (isset($jobCardList) && is_array($jobCardList) && count($jobCardList) > 0) {
  foreach ($jobCardList as $jobCard) {
    #ステータス判定
    $isActiveClass = '';
    #プレビューリンクURLパラメータ
    $previewUrlParam = '/details/?id=' . $jobCard['job_code'];
    switch ($jobCard['is_active']) {
      #下書き中：draft
      case 1:
        $isActiveClass = '';
        $previewUrlParam = '/details/?id=' . $jobCard['job_code'] . '&preview=preview_9f3a7c';
        break;
      #公開中：public
      case 2:
        $isActiveClass = '';
        $previewUrlParam = '/details/?id=' . $jobCard['job_code'];
        break;
      #掲載停止中：private
      case 99:
        $isActiveClass = 'class="is-inactive"';
        $previewUrlParam = '/details/?id=' . $jobCard['job_code'] . '&preview=preview_9f3a7c';
        break;
      #デフォルト：下書き中
      default:
        $isActiveClass = '';
        $previewUrlParam = '/details/?id=' . $jobCard['job_code'] . '&preview=preview_9f3a7c';
        break;
    }
    #募集職種
    foreach ($jobCategories as $jobCategory) {
      if ($jobCategory['id'] == $jobCard['job_category_id']) {
        $jobCategoryName = $jobCategory['name'];
        break;
      }
    }
    #契約プラン
    foreach ($contractPlans as $contractPlan) {
      if ($contractPlan['id'] == $jobCard['contract_plan_id']) {
        $contractPlanName = $contractPlan['name'];
        break;
      }
    }
    #掲載日
    $publishedDate = date("Y/m/d", strtotime($jobCard['published_start']));
    #最終更新日
    $lastUpdateDate = $jobCard['updated_at'] != null ? date("Y/m/d H:i", strtotime($jobCard['updated_at'])) : '---';
    #雇用形態
    foreach ($employmentTypes as $employmentType) {
      if ($employmentType['id'] == $jobCard['employment_type_id']) {
        $employmentTypeName = $employmentType['name'];
        break;
      }
    }
    #住所
    $locationAddress = $facilityData['prefecture'] . $facilityData['city'] . $facilityData['address_line'];
    #給与情報
    $salaryInfo = '';
    switch ($jobCard['salary_unit_id']) {
      #月給
      case 'monthly':
        if ($jobCard['first_year_income_range_id'] != null) {
          foreach ($firstYearIncomeRanges as $incomeRange) {
            if ($incomeRange['id'] == $jobCard['first_year_income_range_id']) {
              $salaryInfo = '初年度年収：' . number_format($incomeRange['min']) . '円〜' . number_format($incomeRange['max']) . '円';
              break;
            }
          }
        } else {
          $salaryInfo = '月給：' . number_format($jobCard['salary_min']) . '円〜' . number_format($jobCard['salary_max']) . '円';
        }
        break;
      #時給
      case 'hourly':
        $salaryInfo = '時給：' . number_format($jobCard['salary_min']) . '円〜' . number_format($jobCard['salary_max']) . '円';
        break;
    }
    #PR画像パス取得
    $heroImagePath = '';
    if (isset($jobCard['hero_image_primary']) && $jobCard['hero_image_primary'] != null) {
      $heroImages = json_decode($jobCard['hero_image_primary'], true);
      if (is_array($heroImages) && count($heroImages) > 0) {
        $heroImagePath = DOMAIN_NAME . $heroImages[0];
      }
    }
    #公開ステータス「name」属性連番対応
    $statusName = 'list_status' . $jobCard['job_id'];
    #checked判定
    $checkedDraft = ($jobCard['is_active'] == 1) ? 'checked' : '';
    $checkedPublic = ($jobCard['is_active'] == 2) ? 'checked' : '';
    #value値／label設定
    $valueNum = ($jobCard['is_active'] == 1) ? '1' : '2';
    $labelName = ($jobCard['is_active'] == 1) ? '下書き中' : '公開中';
    print <<<HTML
            <li {$isActiveClass}>
              <div class="box-head">
                <div class="wrap-title">
                  <div class="joc-category">{$jobCategoryName}</div>
                  <picture>
                    <source srcset="{$heroImagePath}">
                    <img src="{$heroImagePath}" alt="PR画像">
                  </picture>
                </div>
                <!--NOTE  連番注意 list01-status- -->
                <div class="select-status" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">
                    <input type="hidden" name="{$statusName}" value="{$valueNum}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$labelName}</span>
                    <i></i>
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">
                      <li>
                        <input type="radio" name="{$statusName}" value="1" id="list{$jobCard['job_id']}-status01" {$checkedDraft} onchange="checkJobCardStatus({$facId}, '{$jobCard['job_code']}', {$jobCard['job_id']}, this.value,'');">
                        <label for="list{$jobCard['job_id']}-status01" class="status-draft">下書き中</label>
                      </li>
                      <li>
                        <input type="radio" name="{$statusName}" value="2" id="list{$jobCard['job_id']}-status02" {$checkedPublic} onchange="checkJobCardStatus({$facId}, '{$jobCard['job_code']}', {$jobCard['job_id']}, this.value,'');">
                        <label for="list{$jobCard['job_id']}-status02" class="status-published">公開中</label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="box-details">
                <a href="./master03_02_01.php?method=edit&facId={$jobCard['facility_id']}&jobId={$jobCard['job_id']}"></a>
                <div class="item-id">{$jobCard['job_code']}</div>
                <div class="item-plan">{$contractPlanName}</div>
                <div class="item-contract-date">{$publishedDate}</div>
                <div class="item-last-update">{$lastUpdateDate}</div>
                <h3>{$jobCard['card_title']}</h3>
                <ul class="list-job-highlights">

HTML;
    #待遇登録情報取得
    $dbBenefitsData = getJobOptionGroup_FindByJobId($jobCard['job_id'], 'benefits');
    foreach ($dbBenefitsData as $benefitData) {
      foreach ($benefitOptions as $benefitOption) {
        if ($benefitOption['id'] == $benefitData['option_id']) {
          print <<<HTML
                  <li>{$benefitOption['name']}</li>

HTML;
          break;
        }
      }
    }
    print <<<HTML
                </ul>
                <ul class="list-meta">
                  <li class="meta-job-type">{$jobCategoryName}</li>
                  <li class="meta-employment-type">{$employmentTypeName}</li>
                  <li class="meta-location">{$locationAddress}</li>
                  <li class="meta-salary">{$salaryInfo}</li>
                </ul>
              </div>
              <div class="box-btn">
                <div class="box-btn-inner">
                  <button type="button" class="btn-preview" onclick="location.href='{$previewUrlParam}'">プレビュー</button>
                  <button type="button" class="btn-change-plan" onclick="location.href='./master03_02_01.php?method=edit&planAction=change&facId={$jobCard['facility_id']}&jobId={$jobCard['job_id']}#targetSelectPlan'">プラン変更</button>
                </div>
                <div class="box-btn-inner">

HTML;
    if ($jobCard['is_active'] != 99) {
      print <<<HTML
                  <button type="button" class="btn-cancel" onclick="checkJobCardStatus({$facId}, '{$jobCard['job_code']}', {$jobCard['job_id']}, '99','');">プランを解約</button>

HTML;
    } else {
      print <<<HTML
                  <button type="button" class="btn-cancel" onclick="checkJobCardStatus({$facId}, '{$jobCard['job_code']}', {$jobCard['job_id']}, '0','delete');">削除</button>
                  <button type="button" class="btn-cancel" onclick="checkJobCardStatus({$facId}, '{$jobCard['job_code']}', {$jobCard['job_id']}, '2','restore');">掲載再開</button>

HTML;
    }
    print <<<HTML
                </div>
              </div>
            </li>


HTML;
  }
}
print <<<HTML
          </ul>
        </article>
        <article class="block-premium-announce">
          <div class="box-contents">
            <h3>特別バナープランの設定について</h3>
            <p>
              特別バナープランはこちらのページでは設定できません。｢事業所管理］→｢事業所情報｣にて設定が行えます。
            </p>
          </div>
        </article>
        <div class="bottom-box-btn">
          <button type="button" class="item-register" onclick="checkNewJobCard()"><span>新規求人カード登録</span></button>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>新規求人カード</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>新しく求人カードを作成します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="location.href='./master03_02_01.php?method=new&facId={$facId}'">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/master03_02.js" defer></script>
  </body>
</html>

HTML;
