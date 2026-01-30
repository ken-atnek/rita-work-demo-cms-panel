<?php
/*
 * [rw-master/master01_01_01.php]
 *  - 管理画面 -
 *  応募者詳細
 *
 * [初版]
 *  2026.01.27
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_applications.php';

function e($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

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

$applicationId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT, [
  'options' => ['min_range' => 1]
]);
if (!$applicationId) {
  header('Location: ./master01_01.php');
  exit;
}

$jsonMasters = [];
try {
  $jsonMasters = getJson_FrontEndMaster_many([
    'jobCategories'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[master01_01_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
$jobCategories = $jsonMasters['jobCategories'] ?? [];

$application = getApplicationById($applicationId);
if (!$application) {
  header('Location: ./master01_01.php');
  exit;
}

$jobCategoryId = isset($application['job_category_id']) ? (string)$application['job_category_id'] : '';
$jobCategoryName = trim(findJobCategoryNameById($jobCategories, $jobCategoryId));
$statusKey = isset($application['status']) ? (string)$application['status'] : '';
$statusLabel = isset($applicationStatus[$statusKey]) ? (string)$applicationStatus[$statusKey] : $statusKey;

$vApplicationId = e($application['application_id'] ?? '');
$vJobId = e($application['job_id'] ?? '');
$vJobIdUq = e($application['job_id_uq'] ?? '');
$vJobCategory = e($jobCategoryId);
$vJobCategoryName = $jobCategoryName !== '' ? '（' . e($jobCategoryName) . '）' : '';
$vFacilityId = e($application['facility_id'] ?? '');
$vCorporationId = e($application['corporation_id'] ?? '');
$vLineUserId = e($application['line_user_id'] ?? '');
$vLineDisplayName = e($application['line_display_name'] ?? '');
$vApplicantName = e($application['applicant_name'] ?? '');
$vStatus = e($statusLabel);
$vInterviewAt = e($application['interview_at'] ?? '');
$vMemo = nl2br(e($application['memo'] ?? ''));
$vCreatedAt = e($application['created_at'] ?? '');
$vUpdatedAt = e($application['updated_at'] ?? '');

print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master01.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-01-01">
      <section class="container-status">
        <h2>応募者詳細</h2>
        <p style="margin: 16px 0;">
          <a href="./master01_01.php">一覧へ戻る</a>
        </p>

        <div style="background: #fff; border-radius: 8px; padding: 16px;">
          <dl>
            <dt>application_id</dt><dd>{$vApplicationId}</dd>
            <dt>job_id</dt><dd>{$vJobId}</dd>
            <dt>job_id_uq</dt><dd>{$vJobIdUq}</dd>
            <dt>job_category_id</dt><dd>{$vJobCategory}{$vJobCategoryName}</dd>
            <dt>facility_id</dt><dd>{$vFacilityId}</dd>
            <dt>corporation_id</dt><dd>{$vCorporationId}</dd>
            <dt>line_user_id</dt><dd>{$vLineUserId}</dd>
            <dt>line_display_name</dt><dd>{$vLineDisplayName}</dd>
            <dt>applicant_name</dt><dd>{$vApplicantName}</dd>
            <dt>status</dt><dd>{$vStatus}</dd>
            <dt>interview_at</dt><dd>{$vInterviewAt}</dd>
            <dt>memo</dt><dd>{$vMemo}</dd>
            <dt>created_at</dt><dd>{$vCreatedAt}</dd>
            <dt>updated_at</dt><dd>{$vUpdatedAt}</dd>
          </dl>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <script src="../assets/js/common.js" defer></script>
  </body>
</html>

HTML;
