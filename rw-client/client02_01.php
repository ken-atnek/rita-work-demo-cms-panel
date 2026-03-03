<?php
/*
 * [rw-client/client02_01.php]
 *  - 【事業所】管理画面 -
 *  事業所登録／編集
 *
 * [初版]
 *  2026.1.22
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/client/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'cKey02-01_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  #一覧（client02_01）の検索条件だけ保持（戻る操作で条件保持するため）
  $isSearchConditionsKey = ($key === 'searchConditions_client02_01');
  if ($key !== 'sKey' && $key !== 'client_login' && $key !== $noUpDateKey && $isSearchConditionsKey === false) {
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

#==============#
# 事業所情報取得
#--------------#
#事業所ID
$facId = isset($_SESSION['client_login']['facility_id']) ? $_SESSION['client_login']['facility_id'] : null;
$facilityData = getFacility_FindById($_SESSION['client_login']['facility_id']);
if (!$facilityData) {
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
    'facilityTypes',
    'jobCategories',
    'areas'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[client02_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#事業所種別マスタ
$facilityTypes = $jsonMasters['facilityTypes'] ?? [];
#職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#募集エリアマスタ
$recruitmentArea = $jsonMasters['areas'] ?? [];
#募集エリア
$recruitmentAreaList = [];
foreach (($recruitmentArea['groups'] ?? []) as $group) {
  foreach (($group['items'] ?? []) as $item) {
    if (!isset($item['id'], $item['label'])) {
      continue;
    }
    $recruitmentAreaList[] = [
      'id'    => $item['id'],
      'label' => $item['label'],
    ];
  }
}

#=============#
# 法人一覧取得
#-------------#
$corporationsList = getCorporationList();

#-------------#
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
  $facilityData = array(
    'corporation_id' => '',
    'facility_type_id' => '',
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
  $facilityDetails = array(
    'department_name' => '',
    'contact_person' => '',
    'is_emergency_designated' => '',
  );
  $facilityDetailsJson = array(
    'businessHours' => '',
    'holidays' => '',
    'staffComposition' => '',
    'facilityScale' => '',
    'averagePatients' => '',
    'capacityPatients' => '',
    'dormitory' => '',
    'childcareSupport' => '',
    'typeSpecific' => array(
      'visitArea' => '',
      'note' => '',
    ),
    'specialBanner' => array(
      'enabled' => '',
      'logoImagePath' => '',
      'introVideoUrl' => '',
    ),
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
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; connect-src 'self' https://zipcloud.ibsnet.co.jp; img-src 'self' data: https://rita-work.jp; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master03.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-03-01-01">
      <section class="page-nav">
        <h2>事業所管理</h2>
        <nav>
          <a href="javascript:void(0);" class="is-active">事業所情報</a>
        </nav>
      </section>
      <section class="container-vendor-register">
        <a href="javascript:history.back()" class="link-page-back">戻る</a>
        <h2>事業所情報</h2>
        <form name="inputForm" class="block-form">
          <input type="hidden" name="method" value="edit">
          <input type="hidden" name="action" value="checkInput">
          <input type="hidden" name="facId" value="{$facId}">
          <input type="hidden" name="facCode" value="{$facilityData['facility_code']}">
          <dl>
            <dt class="is-required">事業所名</dt>
            <dd><input type="text" name="facility_name" value="{$facilityData['name']}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt class="is-required">ふりがな</dt>
            <dd><input type="text" name="facility_name_kana" value="{$facilityData['name_kana']}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt class="is-required">事業形態</dt>
            <dd>
              <div class="select-job-category" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#事業形態が選択されていたら
if (isset($facilityData['facility_type_id']) && $facilityData['facility_type_id'] != '') {
  #選択中のラベル取得
  foreach ($facilityTypes as $facilityType) {
    if ($facilityData['facility_type_id'] == $facilityType['id']) {
      print <<<HTML
                  <input type="hidden" name="facility_type" value="{$facilityType['id']}" data-selectbox-hidden class="required-item" required>
                  <span class="selectbox__value" data-selectbox-value>{$facilityType['label']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                  <input type="hidden" name="facility_type" value="" data-selectbox-hidden class="required-item" required>
                  <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($facilityTypes) && is_array($facilityTypes) && count($facilityTypes) > 0) {
  foreach ($facilityTypes as $facilityType) {
    #checked判定
    $checked = ($facilityData['facility_type_id'] == $facilityType['id']) ? 'checked' : '';
    print <<<HTML
                    <li>
                      <input type="radio" name="facility_type" value="{$facilityType['id']}" id="{$facilityType['id']}" {$checked}>
                      <label for="{$facilityType['id']}">{$facilityType['label']}</label>
                    </li>

HTML;
  }
} else {
  print <<<HTML
                    <li>
                      <input type="radio" name="facility_type" value="1" id="job01">
                      <label for="job01">事業形態が未設定です</label>
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
            <dt>部署名</dt>
            <dd><input type="text" name="department_name" value="{$facilityDetails['department_name']}" style="max-width: 60rem"></dd>
          </dl>
          <dl>
            <dt class="is-required">担当者名</dt>
            <dd><input type="text" name="contact_person" value="{$facilityDetails['contact_person']}" class="required-item" required style="max-width: 34rem"></dd>
          </dl>
          <dl>
            <dt class="is-required">住所</dt>
            <dd>
              <input type="text" name="address1" value="{$facilityData['postal_code']}" class="required-item" required id="zipCode" id="zipCode" style="max-width: 14rem">
              <input type="text" name="address2" value="{$facilityData['prefecture']}{$facilityData['city']}">
              <input type="text" name="address3" value="{$facilityData['address_line']}">
            </dd>
          </dl>
          <dl>
            <dt class="is-required">エリア</dt>
            <dd>
              <div class="select-area" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
#事業形態が選択されていたら
if (isset($facilityData['recruitment_area']) && $facilityData['recruitment_area'] != '') {
  #選択中のラベル取得
  foreach ($recruitmentAreaList as $recruitmentArea) {
    if ($facilityData['recruitment_area'] == $recruitmentArea['id']) {
      print <<<HTML
                  <input type="hidden" name="recruitment_area" value="{$recruitmentArea['id']}" data-selectbox-hidden>
                  <span class="selectbox__value" data-selectbox-value>{$recruitmentArea['label']}</span>

HTML;
      break;
    }
  }
} else {
  print <<<HTML
                  <input type="hidden" name="recruitment_area" value="" data-selectbox-hidden>
                  <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">

HTML;
#表示可能リストあればループ処理
if (isset($recruitmentAreaList) && is_array($recruitmentAreaList) && count($recruitmentAreaList) > 0) {
  foreach ($recruitmentAreaList as $recruitmentArea) {
    #checked判定
    if (!isset($facilityData['recruitment_area'])) {
      $facilityData['recruitment_area'] = '';
    }
    $checked = ($facilityData['recruitment_area'] == $recruitmentArea['id']) ? 'checked' : '';
    print <<<HTML
                    <li>
                      <input type="radio" name="recruitment_area" value="{$recruitmentArea['id']}" id="{$recruitmentArea['id']}" {$checked}>
                      <label for="{$recruitmentArea['id']}">{$recruitmentArea['label']}</label>
                    </li>

HTML;
  }
} else {
  print <<<HTML
                    <li>
                      <input type="radio" name="recruitment_area" value="area1" id="area01">
                      <label for="area01">募集エリアが未設定です</label>
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
            <dt class="is-required">地図表示<br>URL</dt>
            <dd><textarea name="map_url" class="required-item" required>{$facilityData['map_url']}</textarea></dd>
          </dl>
          <dl>
            <dt class="is-required">地図リンク用<br>URL</dt>
            <dd><textarea name="map_link_url" class="required-item" required>{$facilityData['map_link_url']}</textarea></dd>
          </dl>
          <dl>
            <dt class="is-required">電話番号</dt>
            <dd><input type="text" name="phone_number" value="{$facilityData['phone']}" autocomplete="on" class="required-item phone_number" required style="max-width: 34rem"></dd>
          </dl>
          <dl>
            <dt>E-mail</dt>
            <dd><input type="text" name="email" value="{$facilityData['email']}" autocomplete="on" class="required-item email" style="max-width: 60rem; border-color:#ababab; background-color:#eee;" readonly></dd>
          </dl>
          <hr>
          <dl data-field="established_date">
            <dt>設立年月日</dt>
            <dd>
              <div class="input-date"><input type="date" name="established_date" value="{$facilityData['established_date']}"></div>
            </dd>
          </dl>
          <dl data-field="facility_scale">
            <dt>施設規模</dt>
            <dd><textarea name="facility_scale">{$facilityDetailsJson['facilityScale']}</textarea></dd>
          </dl>
          <dl data-field="is_emergency_designated">
            <dt>緊急指定</dt>
            <dd>
              <div class="wrap-toggle-button">
                <label class="toggle-button">

HTML;
#checked判定
if (isset($facilityDetails['is_emergency_designated']) && $facilityDetails['is_emergency_designated'] == 1) {
  print <<<HTML
                  <input type="checkbox" name="is_emergency_designated" value="1" checked>

HTML;
} else {
  print <<<HTML
                  <input type="checkbox" name="is_emergency_designated" value="1">

HTML;
}
print <<<HTML
                </label>
              </div>
            </dd>
          </dl>
          <dl data-field="business_hours">
            <dt>営業時間</dt>
            <dd><textarea name="business_hours">{$facilityDetailsJson['businessHours']}</textarea></dd>
          </dl>
          <dl data-field="holidays">
            <dt>休業日</dt>
            <dd><input type="text" name="holidays" value="{$facilityDetailsJson['holidays']}" style="max-width: 42rem"></dd>
          </dl>
          <dl data-field="capacity_patients">
            <dt>利用者定員数</dt>
            <dd><input type="text" name="capacity_patients" value="{$facilityDetailsJson['capacityPatients']}" style="max-width: 42rem"></dd>
          </dl>
          <dl data-field="average_patients">
            <dt>平均患者数</dt>
            <dd><input type="text" name="average_patients" value="{$facilityDetailsJson['averagePatients']}" style="max-width: 42rem"></dd>
          </dl>
          <dl data-field="staff_composition">
            <dt>スタッフ構成</dt>
            <dd><textarea name="staff_composition">{$facilityDetailsJson['staffComposition']}</textarea></dd>
          </dl>
          <dl data-field="is_dormitory">
            <dt>社宅・寮</dt>
            <dd>
              <div class="wrap-toggle-button">
                <label class="toggle-button">

HTML;
#checked判定
if ($facilityDetailsJson['dormitory'] == 1) {
  print <<<HTML
                  <input type="checkbox" name="is_dormitory" value="1" checked>

HTML;
} else {
  print <<<HTML
                  <input type="checkbox" name="is_dormitory" value="1">

HTML;
}
print <<<HTML
                </label>
              </div>
            </dd>
          </dl>
          <dl data-field="is_childcare_support">
            <dt>託児所</dt>
            <dd>
              <div class="wrap-toggle-button">
                <label class="toggle-button">

HTML;
#checked判定
if ($facilityDetailsJson['childcareSupport'] == 1) {
  print <<<HTML
                  <input type="checkbox" name="is_childcare_support" value="1" checked>

HTML;
} else {
  print <<<HTML
                  <input type="checkbox" name="is_childcare_support" value="1">

HTML;
}
print <<<HTML
                </label>
              </div>
            </dd>
          </dl>
          <dl data-field="visit_area">
            <dt>訪問エリア</dt>
            <dd><textarea name="visit_area">{$facilityDetailsJson['typeSpecific']['visitArea']}</textarea></dd>
          </dl>
          <dl data-field="remarks">
            <dt>備考</dt>
            <dd><textarea name="remarks">{$facilityDetailsJson['typeSpecific']['note']}</textarea></dd>
          </dl>
          <div class="inner-ban-plan">
            <div class="box-head">
              <h3>特別バナープラン</h3>
              <div class="wrap-toggle-button" style="display:none;">
                <label class="toggle-button">

HTML;
if ($facilityDetailsJson['specialBanner']['enabled'] == true) {
  print <<<HTML
                  <input type="checkbox" name="special_banner" value="1" checked onclick="toggleSpecialBannerPlan()">

HTML;
} else {
  print <<<HTML
                  <input type="checkbox" name="special_banner" value="1" onclick="toggleSpecialBannerPlan()">

HTML;
}
print <<<HTML
                </label>
              </div>
            </div>
            <dl class="js-special-banner-inputs" style="display: none;">
              <dt class="is-required">事務所ロゴ画像</dt>
              <dd>
                <!-- NOTE 画像登録時は [is-active]付与 -->
                <div class="select-image" id="js-dragDrop-mainLogo">
                  <h4>ここにファイルをドロップ</h4>
                  <span>または</span>
                  <input type="file" name="images_tmp" id="js-fileElem-mainLogo" multiple accept="image/*" style="display:none">
                  <input type="hidden" name="special_banner_logo" value="">
                  <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-mainLogo">
                  <input type="hidden" name="upload_image_area" value="special_banner_logo_list" id="js-uploadImageArea-mainLogo">
                  <input type="hidden" name="up_image_area[]" value="special_banner_logo_list">
                  <input type="hidden" name="send_php" value="proc_client02_01.php">
                  <button type="button" id="js-fileSelect-mainLogo">ファイルを選択</button>
                  <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                  <!-- NOTE 警告用表示 -->
                  <div class="wrap-caution" id="js-fileError-mainLogo" style="display: none;">
                    <h5>ファイルサイズが大きすぎます</h5>
                    <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                  </div>
                </div>

HTML;
if (
  isset($facilityDetailsJson['specialBanner']['logoImagePath']) && is_array($facilityDetailsJson['specialBanner']['logoImagePath']) && count($facilityDetailsJson['specialBanner']['logoImagePath']) > 0
) {
  print <<<HTML
                  <ul class="selected-image-list" id="js-previewBlock-mainLogo">

HTML;
  #登録画像リスト展開
  foreach ($facilityDetailsJson['specialBanner']['logoImagePath'] as $info) {
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
                          <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                        </picture>
                      </li>

HTML;
  }
  print <<<HTML
                  </ul>

HTML;
} else {
  print <<<HTML
                <ul class="selected-image-list" id="js-previewBlock-mainLogo" style="display: none;"></ul>

HTML;
}
print <<<HTML
                <div class="wrap-notice">
                  <p>※画像は1枚登録してください</p>
                  <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                  <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                  <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
                </div>
              </dd>
            </dl>
            <dl class="js-special-banner-inputs" style="display: none;">
              <dt class="is-required">事業所紹介動画</dt>
              <dd>
                <input type="text" name="special_banner_video_url" value="{$facilityDetailsJson['specialBanner']['introVideoUrl']}">
                <p>※YouTubeの動画リンクを設定してください</p>
              </dd>
            </dl>
          </div>
          <div class="inner-select-company">
            <div class="box-head">
              <h3>法人名を選択</h3>
            </div>
            <dl>
              <dt>法人名</dt>
              <dd>
                <div class="select-company is-client" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false" style="pointer-events:none; border-color:#ababab; background-color:#eee;">

HTML;
#法人が選択されていたら
if (isset($facilityData['corporation_id']) && $facilityData['corporation_id'] != '') {
  #法人コードをキーに法人情報を取得
  $selectedCorporation = getCorporations_FindById_Code($facilityData['corporation_id'], null);
  $selectedCorporationName = $selectedCorporation['name'];
  print <<<HTML
                    <input type="hidden" name="facility_corporation_code" value="{$selectedCorporation['corporation_code']}" data-selectbox-hidden class="required-item" required>
                    <span class="selectbox__value" data-selectbox-value>{$selectedCorporationName}</span>

HTML;
} else {
  print <<<HTML
                    <input type="hidden" name="facility_corporation_code" value="" data-selectbox-hidden class="required-item" required>
                    <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
}
print <<<HTML
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">

HTML;
#表示可能リストあればループで差し込む
if (is_array($corporationsList) && count($corporationsList) > 0) {
  foreach ($corporationsList as $corporation) {
    $corpInputID = 'company' . sprintf('%02d', $corporation['corporation_id']);
    $corpInputValue = $corporation['corporation_code'];
    $corpInputName = $corporation['name'];
    #checked判定
    $checked = ($facilityData['corporation_id'] == $corporation['corporation_id']) ? 'checked' : '';
    print <<<HTML
                      <li>
                        <input type="radio" name="facility_corporation_code" value="{$corpInputValue}" id="{$corpInputID}" {$checked}>
                        <label for="{$corpInputID}">{$corpInputName}</label>
                      </li>

HTML;
  }
}
#JSONエスケープ処理
$jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$facilityNameJs = json_encode((string)$facilityData['name'], $jsonHex);
$facilityCodeJs = json_encode((string)$facilityData['facility_code'], $jsonHex);
$facilityNameJsAttr = htmlspecialchars((string)$facilityNameJs, ENT_QUOTES, 'UTF-8');
$facilityCodeJsAttr = htmlspecialchars((string)$facilityCodeJs, ENT_QUOTES, 'UTF-8');
$facIdInt = (int)$facId;
print <<<HTML
                    </ul>
                  </div>
                </div>
              </dd>
            </dl>
          </div>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="history.back()">戻る</button>
          <button type="button" class="item-check" onclick="checkInput()">入力を確認する</button>
        </div>
        <!--NOTE 修正画面のみ表示 -->
        <button type="button" class="btn-delate-item" onclick="checkDeleteFacility({$facIdInt}, {$facilityNameJsAttr}, {$facilityCodeJsAttr})">削除する</button>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>新規事業所削除</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>新規法人情報を削除します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel">キャンセル</button>
            <button type="button" class="btn-confirm">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/form.js" defer></script>
    <script src="../assets/js/dropZone.js?18432003032026" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/client02_01.js" defer></script>
    <script>
    //複数アップロードエリア対応：ID命名規則に従い全領域を初期化
    document.addEventListener('DOMContentLoaded', function() {
      //対象となるアップロードエリアのIDリスト：areaIdsを増やすことで複数アップロードエリア対応可能
      const areaIds = ['mainLogo'];
      areaIds.forEach(function(area) {
        let drop = document.getElementById('js-dragDrop-' + area);
        let btn = document.getElementById('js-fileSelect-' + area);
        let input = document.getElementById('js-fileElem-' + area);
        let inputMode = document.getElementById('js-uploadImageMode-' + area);
        let inputArea = document.getElementById('js-uploadImageArea-' + area);
        let preview = document.getElementById('js-previewBlock-' + area);
        let error = document.getElementById('js-fileError-' + area);
        // 1枚登録モード時はドラッグ＆ドロップエリアを非表示
        if (inputMode && inputMode.value === 'only' && drop) {
            const liCount = preview.querySelectorAll('li').length;
            if (liCount >= 1) {
              drop.classList.add('is-active');
            } else {
              drop.classList.remove('is-active');
            }
        }
        if (drop && btn && input && preview) {
          initDropZone({
            dropZone: drop,
            selectFileButton: btn,
            fileInput: input,
            inputMode: inputMode,
            inputArea: inputArea,
            previewBlock: preview,
            fileError: error
          });
        }
      });
    });
    </script>

  </body>
</html>

HTML;
