<?php

declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
/*
 * [assets/lib/jsPDF/receipt_html.php]
 *  領収書PDF生成
 *
 * [初版]
 *  2026.3.10
 */

#***** 定数定義ファイル：インクルード *****#
// このファイルは assets/lib/jsPDF 配下のため、プロジェクトルートへ3階層戻る
require_once dirname(__DIR__, 3) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#事業所請求情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_invoice.php';

#=============#
# POSTチェック
#-------------#
#事業所ID：必須
$facId = isset($_POST['facilityId']) ? (int)$_POST['facilityId'] : 0;
if ($facId <= 0) {
  http_response_code(400);
  echo 'invalid facility_id';
  exit;
}
#請求ID：必須
$invoiceId = isset($_POST['invoiceId']) ? (int)$_POST['invoiceId'] : 0;
if ($invoiceId <= 0) {
  http_response_code(400);
  echo 'invalid invoice_id';
  exit;
}

#========================#
# SESSION開始（master/client両対応）
#------------------------#
# ※このエンドポイントはAJAXで直接呼ばれるため、ここで確実にセッションを復元する
# ※セッションを書き戻す必要はないため read_and_close で読み取り専用にする
if (session_status() !== PHP_SESSION_ACTIVE) {
  $sessionNamesToTry = [];
  # clientを優先（施設ひも付けチェックを適用できるため）
  if (isset($_COOKIE['RW_CLIENT_SESSID'])) {
    $sessionNamesToTry[] = 'RW_CLIENT_SESSID';
  }
  if (isset($_COOKIE['RW_MASTER_SESSID'])) {
    $sessionNamesToTry[] = 'RW_MASTER_SESSID';
  }

  # Cookieが無い状態で session_start() すると新規セッション発行→既存ログインCookie上書きの原因になるため、ここで弾く
  if (count($sessionNamesToTry) === 0) {
    http_response_code(403);
    echo 'forbidden';
    exit;
  }

  # start_processing.php と同じCookie属性（path=/ 等）で復元できるように明示
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);

  foreach ($sessionNamesToTry as $sessionName) {
    $_SESSION = [];
    session_name($sessionName);
    session_start(['read_and_close' => true]);

    $hasMasterLogin = !empty($_SESSION['master_login']['status']);
    $hasClientLogin = !empty($_SESSION['client_login']['status']);
    if ($hasMasterLogin || $hasClientLogin) {
      break;
    }
  }
}

#================#
# SESSIONチェック
#----------------#
#「master_login」「client_login」が両方とも存在しない場合はアクセス不可
if (empty($_SESSION['master_login']['status']) && empty($_SESSION['client_login']['status'])) {
  http_response_code(403);
  echo 'forbidden';
  exit;
}

# client側ログインの場合は、セッションの施設IDとPOST施設IDの一致を必須にする
if (!empty($_SESSION['client_login']['status'])) {
  $sessionFacId = (int)($_SESSION['client_login']['facility_id'] ?? 0);
  if ($sessionFacId <= 0 || $sessionFacId !== $facId) {
    http_response_code(403);
    echo 'forbidden';
    exit;
  }
}

#-------------#
#エスケープ関数
function h(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function yen(int $n): string
{
  return '¥' . number_format($n);
}

#===================================#
# フロント側マスタ定義JSONファイル取得
#-----------------------------------#
#取得項目一覧
$jsonMasters = [];
try {
  $jsonMasters = getJson_FrontEndMaster_many([
    'contractPlans'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[receipt_html] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#契約プランマスタ
$contractPlans = $jsonMasters['contractPlans'] ?? [];
#契約プランIDをキー、プラン名を値とする連想配列を生成（ループ内での参照用）
$contractPlanNameById = [];
if (is_array($contractPlans)) {
  foreach ($contractPlans as $p) {
    if (!is_array($p) || !isset($p['id'])) {
      continue;
    }
    $contractPlanNameById[(string)$p['id']] = (string)($p['name'] ?? '');
  }
}

#-------------#
#DBから請求情報を取得
$invoiceDetails = getFacilityInvoiceDetail($facId, $invoiceId);
if (!$invoiceDetails) {
  http_response_code(404);
  echo 'invoice not found';
  exit;
}

#請求日（cutoff_at）
$billingDate = date('Y-m-d', strtotime((string)($invoiceDetails['cutoff_at'] ?? 'now')));

#請求書番号（invoice_id から採番）
$invoiceNo = 'INV-' . str_pad((string)$invoiceId, 10, '0', STR_PAD_LEFT);

#請求期間（billing_period 月初〜月末）
$billingPeriodRaw = (string)($invoiceDetails['billing_period'] ?? '');
$servicePeriodText = '';
if ($billingPeriodRaw !== '') {
  try {
    $start = new DateTimeImmutable($billingPeriodRaw, new DateTimeZone('Asia/Tokyo'));
    $end = $start->modify('last day of this month');
    $servicePeriodText = $start->format('Y年n月j日') . '〜' . $end->format('Y年n月j日');
  } catch (Throwable $e) {
    $servicePeriodText = '';
  }
}

#金額（DBスナップショット）
$subtotalExTax = (int)($invoiceDetails['amount_total'] ?? 0);
$taxAmount = (int)($invoiceDetails['tax_amount'] ?? 0);
$grandTotal = (int)($invoiceDetails['amount_total_incl_tax'] ?? 0);

$billingDateEsc = h($billingDate);
$invoiceNoEsc = h($invoiceNo);
$facilityNameEsc = h((string)($invoiceDetails['facility_name'] ?? ''));
$facilityNameLine = ($facilityNameEsc !== '') ? ($facilityNameEsc . ' 御中') : '-';
$servicePeriodEsc = h($servicePeriodText !== '' ? $servicePeriodText : '-');
$grandTotalText = h(yen($grandTotal));
$subtotalText = h(yen($subtotalExTax));
$taxText = h(yen($taxAmount));

#明細行（facility_invoice_items）
$invoiceItems = [];
if (function_exists('getFacilityInvoiceItems')) {
  $invoiceItems = getFacilityInvoiceItems($invoiceId);
}
if (!is_array($invoiceItems)) {
  $invoiceItems = [];
}

# 特別バナー明細は jobCodes を持たないため、表示補完用に「他プランの求人ID一覧」を集計しておく
$invoiceJobCodesForDisplay = [];
if (count($invoiceItems) > 0) {
  foreach ($invoiceItems as $row) {
    if (!is_array($row)) {
      continue;
    }
    $planId = (string)($row['plan_id'] ?? '');
    if ($planId === 'special_banner') {
      continue;
    }
    $metaRaw = $row['meta_json'] ?? null;
    if (!is_string($metaRaw) || $metaRaw === '') {
      continue;
    }
    $decoded = json_decode($metaRaw, true);
    if (!is_array($decoded) || !isset($decoded['jobCodes']) || !is_array($decoded['jobCodes'])) {
      continue;
    }
    foreach ($decoded['jobCodes'] as $code) {
      if (!is_string($code)) {
        continue;
      }
      $code = trim($code);
      if ($code === '') {
        continue;
      }
      if (!in_array($code, $invoiceJobCodesForDisplay, true)) {
        $invoiceJobCodesForDisplay[] = $code;
      }
    }
  }
}

#***** タグ生成開始 *****#
print <<<HTML
<div id="pdfTarget" class="invoice">
  <div class="invoice-inner">
    <div class="header">
      <div class="brand">
        <div class="logo" aria-hidden="true">RITA</div>
        <div class="company">
          <div class="name">株式会社 RITA</div>
          <div class="meta">〒860-0950 熊本県熊本市中央区水前寺 4-20-36-801 ロマネスク水前寺ルネッサンス</div>
        </div>
      </div>
      <div class="doc">
        <div class="title">請 求 書</div>
        <div class="no">請求日：{$billingDateEsc}</div>
        <div class="no">請求書番号：{$invoiceNoEsc}</div>
      </div>
    </div>

    <div class="block">
      <div class="card">
        <h3>宛先</h3>
        <div class="kv">
          <div class="k">事業所名</div>
          <div class="v">{$facilityNameLine}</div>
        </div>
      </div>

      <div class="card">
        <h3>請求内容</h3>
        <div class="kv">
          <div class="k">サービス期間</div>
          <div class="v">{$servicePeriodEsc}</div>
        </div>
      </div>
    </div>

    <div class="total-box">
      <div>
        <div class="label">ご請求金額（税込）</div>
        <div class="small">※本書は請求書です</div>
      </div>
      <div class="amount">{$grandTotalText}</div>
    </div>

    <table>
      <thead>
        <tr>
          <th style="width:18%;">求人ID</th>
          <th style="width:34%;">サービス名</th>
          <th style="width:16%;" class="right">単価</th>
          <th style="width:16%;" class="right">数量</th>
          <th style="width:16%;" class="right">金額</th>
        </tr>
      </thead>
      <tbody>

HTML;

if (count($invoiceItems) > 0) {
  foreach ($invoiceItems as $row) {
    if (!is_array($row)) {
      continue;
    }
    $planId = (string)($row['plan_id'] ?? '');
    $qty = (int)($row['quantity'] ?? 0);
    $unit = (int)($row['unit_price'] ?? 0);
    $amount = (int)($row['amount'] ?? 0);

    $meta = [];
    $metaRaw = $row['meta_json'] ?? null;
    if (is_string($metaRaw) && $metaRaw !== '') {
      $decoded = json_decode($metaRaw, true);
      if (is_array($decoded)) {
        $meta = $decoded;
      }
    }

    $jobCodes = [];
    if (isset($meta['jobCodes']) && is_array($meta['jobCodes'])) {
      foreach ($meta['jobCodes'] as $code) {
        if (is_string($code) && trim($code) !== '') {
          $jobCodes[] = trim($code);
        }
      }
    }

    # 特別バナー明細はjobCodesを持たないため、請求内の求人ID一覧を補完して表示（2行目にズレるのを防ぐ）
    if ($planId === 'special_banner' && count($jobCodes) === 0 && count($invoiceJobCodesForDisplay) > 0) {
      $jobCodes = $invoiceJobCodesForDisplay;
    }

    $jobCodesHtml = (count($jobCodes) > 0)
      ? implode('<br>', array_map(function ($s) {
        return h((string)$s);
      }, $jobCodes))
      : '';

    $planName = '';
    if (isset($meta['planName']) && is_string($meta['planName'])) {
      $planName = trim($meta['planName']);
    }
    if ($planName === '') {
      if ($planId === 'special_banner') {
        $planName = '特別バナー';
      } elseif (isset($contractPlanNameById[$planId])) {
        $planName = (string)$contractPlanNameById[$planId];
      } else {
        $planName = $planId;
      }
    }

    $planNameHtml = h($planName);

    // jobCodes が quantity と一致する場合は、求人IDごとに1行ずつ展開（サンプル画像の体裁）
    if ($qty > 1 && count($jobCodes) > 0 && count($jobCodes) === $qty) {
      $unitText = h(yen($unit));
      $isEven = ($qty > 0 && $unit * $qty === $amount);
      $base = ($qty > 0) ? (int)floor($amount / $qty) : $amount;
      $remain = ($qty > 0) ? ($amount - ($base * ($qty - 1))) : $amount;
      for ($i = 0; $i < $qty; $i++) {
        $jobCodeEsc = h((string)$jobCodes[$i]);
        $lineAmount = $isEven ? $unit : (($i === $qty - 1) ? $remain : $base);
        $amountText = h(yen($lineAmount));
        print <<<HTML
          <tr>
            <td>{$jobCodeEsc}</td>
            <td>{$planNameHtml}</td>
            <td class="right">{$unitText}</td>
            <td class="right">1</td>
            <td class="right">{$amountText}</td>
          </tr>

HTML;
      }
      continue;
    }

    // 集計行として表示
    $jobCell = ($jobCodesHtml !== '') ? $jobCodesHtml : '-';
    $unitText = h(yen($unit));
    $amountText = h(yen($amount));

    print <<<HTML
          <tr>
            <td>{$jobCell}</td>
            <td>{$planNameHtml}</td>
            <td class="right">{$unitText}</td>
            <td class="right">{$qty}</td>
            <td class="right">{$amountText}</td>
          </tr>

HTML;
  }
} else {
  print <<<HTML
          <tr>
            <td colspan="5" class="muted">明細がありません</td>
          </tr>

HTML;
}

print <<<HTML
      </tbody>
    </table>

    <div class="card" style="margin-top:8mm;">
      <h3>金額</h3>
      <div class="kv">
        <div class="k">小計</div>
        <div class="v right">{$subtotalText}</div>
        <div class="k">消費税</div>
        <div class="v right">{$taxText}</div>
        <div class="k">合計</div>
        <div class="v right">{$grandTotalText}</div>
      </div>
    </div>

    <div class="footer">
      <div class="note">ご利用いただき、誠にありがとうございます。</div>
      <div class="stamp">STAMP</div>
    </div>

    <!-- 2ページで切りたい場合の決め打ち -->
    <!-- <div class="page-break"></div> -->
  </div>
</div>

HTML;
