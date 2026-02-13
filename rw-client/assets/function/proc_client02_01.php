<?php
/*
 * [rw-client/assets/function/proc_client02_01.php]
 *  - 【事業所】管理画面 -
 *  事業所登録／編集／削除 処理
 *
 * [初版]
 *  2026.1.22
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
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
# 応答用タグ初期化
#----------------#
$makeTag = array(
  'tag' => '',
  'status' => '',
  'title' => '',
  'msg' => '',
);

#===================================#
# フロント側マスタ定義JSONファイル取得（無ければ空）
#-----------------------------------#
$facilityTypes = [];
$recruitmentAreaList = [];
if (function_exists('getJson_FrontEndMaster_many')) {
  try {
    $jsonMasters = getJson_FrontEndMaster_many([
      'facilityTypes',
      'areas',
    ]);
    $facilityTypes = $jsonMasters['facilityTypes'] ?? [];
    #募集エリアマスタ（areas.json）をフラットなリストに整形
    $recruitmentArea = $jsonMasters['areas'] ?? [];
    $recruitmentAreaList = [];
    foreach (($recruitmentArea['groups'] ?? []) as $group) {
      foreach (($group['items'] ?? []) as $item) {
        $recruitmentAreaList[] = $item;
      }
    }
  } catch (Throwable $e) {
    $facilityTypes = [];
    $recruitmentAreaList = [];
    if (function_exists('makeLog')) {
      $data = [
        'pageName' => 'proc_client02_01',
        'reason' => 'マスタJSON取得で例外',
        'errorMessage' => $e->getMessage(),
      ];
      makeLog($data);
    }
  }
}
if (!is_array($facilityTypes)) {
  $facilityTypes = [];
}
if (!is_array($recruitmentAreaList)) {
  $recruitmentAreaList = [];
}

#=============#
# 法人一覧取得
#-------------#
$corporationsList = getCorporationList();

#-------------#
#新規／編集／削除
$method = isset($_POST['method']) ? $_POST['method'] : null;
#確認／修正／登録
$action = isset($_POST['action']) ? $_POST['action'] : null;
#事業所ID（編集／削除時のみ）
$facId = isset($_POST['facId']) ? $_POST['facId'] : null;
#事業所コード（編集／削除時のみ）
$facCode = isset($_POST['facCode']) ? $_POST['facCode'] : null;
#画像アップロード先（セッション領域）
if (isset($_POST['up_image_area'])) {
  $imageUploadSessionKey = $_POST['up_image_area'];
  if (!is_array($imageUploadSessionKey)) {
    $imageUploadSessionKey = array($imageUploadSessionKey);
  }
} else {
  $imageUploadSessionKey = array();
}
$targetImageUploadSessionKey = $imageUploadSessionKey[0] ?? '';
#-------------#
#事業所名
$facility_name = isset($_POST['facility_name']) ? $_POST['facility_name'] : null;
#ふりがな
$facility_name_kana = isset($_POST['facility_name_kana']) ? $_POST['facility_name_kana'] : null;
#事業形態
$facility_type = isset($_POST['facility_type']) ? $_POST['facility_type'] : null;
$facilityTypeName = '';
foreach ($facilityTypes as $facilityType) {
  if (is_array($facilityType) && ($facilityType['id'] ?? null) === $facility_type) {
    $facilityTypeName = $facilityType['label'] ?? '';
    break;
  }
}
#部署名
$department_name = isset($_POST['department_name']) ? $_POST['department_name'] : null;
#担当者名
$contact_person = isset($_POST['contact_person']) ? $_POST['contact_person'] : null;
#住所
$address1 = isset($_POST['address1']) ? $_POST['address1'] : null;
$address2 = isset($_POST['address2']) ? $_POST['address2'] : null;
$address3 = isset($_POST['address3']) ? $_POST['address3'] : null;
#募集エリア
$recruitment_area = isset($_POST['recruitment_area']) ? $_POST['recruitment_area'] : null;
$recruitmentAreaName = '';
foreach ($recruitmentAreaList as $area) {
  if (is_array($area) && ($area['id'] ?? null) === $recruitment_area) {
    $recruitmentAreaName = $area['label'] ?? '';
    break;
  }
}
#地図表示URL
$map_url = isset($_POST['map_url']) ? $_POST['map_url'] : null;
#地図リンクURL
$map_link_url = isset($_POST['map_link_url']) ? $_POST['map_link_url'] : null;
#電話番号
$phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : null;
#メールアドレス
$email = isset($_POST['email']) ? $_POST['email'] : null;
#メールアカウントチェック
$uniqueEmail = isset($_POST['set_email']) ? $_POST['set_email'] : null;
#設立年月日
$established_date = isset($_POST['established_date']) ? $_POST['established_date'] : null;
#施設規模
$facility_scale = isset($_POST['facility_scale']) ? $_POST['facility_scale'] : null;
#緊急指定
$is_emergency_designated = isset($_POST['is_emergency_designated']) ? $_POST['is_emergency_designated'] : 0;
#営業時間
$business_hours = isset($_POST['business_hours']) ? $_POST['business_hours'] : null;
#休業日
$holidays = isset($_POST['holidays']) ? $_POST['holidays'] : null;
#利用者定員数
$capacity_patients = isset($_POST['capacity_patients']) ? $_POST['capacity_patients'] : null;
#平均患者数
$average_patients = isset($_POST['average_patients']) ? $_POST['average_patients'] : null;
#スタッフ構成
$staff_composition = isset($_POST['staff_composition']) ? $_POST['staff_composition'] : null;
#社宅・寮
$is_dormitory = isset($_POST['is_dormitory']) ? $_POST['is_dormitory'] : 0;
#託児所
$is_childcare_support = isset($_POST['is_childcare_support']) ? $_POST['is_childcare_support'] : 0;
#訪問エリア
$visit_area = isset($_POST['visit_area']) ? $_POST['visit_area'] : null;
#備考
$remarks = isset($_POST['remarks']) ? $_POST['remarks'] : null;
#特別バナープラン
$special_banner = isset($_POST['special_banner']) ? $_POST['special_banner'] : 0;
#事務所ロゴ画像
$special_banner_logo = isset($_POST['special_banner_logo']) ? $_POST['special_banner_logo'] : null;
#事業所紹介動画
$special_banner_video_url = isset($_POST['special_banner_video_url']) ? $_POST['special_banner_video_url'] : null;
#法人名
$facility_corporation_code = isset($_POST['facility_corporation_code']) ? $_POST['facility_corporation_code'] : null;
#法人コードをキーに法人情報を取得
$facilityCorporationName = '';
if ($facility_corporation_code !== null && $facility_corporation_code !== '') {
  $facilityCorporation = getCorporations_FindById_Code(null, $facility_corporation_code);
  $facilityCorporationName = $facilityCorporation['name'] ?? '';
}
#メールアドレス重複チェック（accounts.login_email）
# - is_active=9(待機)も含めて重複扱い
# - 編集時は同一facility_idの自分自身を許可
$checkAccountEmail = null;
if ($uniqueEmail !== null && $uniqueEmail !== '') {
  $checkAccountEmail = $uniqueEmail;
} elseif ($email !== null && $email !== '') {
  $checkAccountEmail = $email;
}
if ($checkAccountEmail !== null && $checkAccountEmail !== '') {
  try {
    $stmt = $DB_CONNECT->prepare('SELECT account_id, facility_id, login_email FROM accounts WHERE login_email = :email LIMIT 1');
    $stmt->execute([':email' => $checkAccountEmail]);
    $existingAccount = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($existingAccount) && count($existingAccount) > 0) {
      $isSameFacility = (
        $method === 'edit'
        && $facId !== null
        && $facId !== ''
        && isset($existingAccount['facility_id'])
        && (string)$existingAccount['facility_id'] === (string)$facId
      );
      if ($isSameFacility !== true) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'メールアドレス重複エラー';
        $makeTag['msg'] = '指定されたメールアドレスは既に登録されているため、使用できません。<br>別のメールアドレスを指定してください。';
        echo json_encode($makeTag);
        exit;
      }
    }
  } catch (Exception $e) {
    $data = [
      'pageName' => 'proc_client02_01',
      'reason' => 'メールアドレス重複チェックで例外',
      'errorMessage' => $e->getMessage(),
    ];
    makeLog($data);
  }
}

/**
 * 再帰ディレクトリ削除
 */
function rrmdir(string $dir): bool
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
      if (!rrmdir($path)) {
        return false;
      }
    } else {
      @unlink($path);
    }
  }
  return @rmdir($dir);
}
/**
 * ディレクトリ作成（既存ならtrue）
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
  #***** ページ離脱・リロード時：アップロードドラフト破棄（tmp_upload + session） *****#
  case 'discardUploadDraft': {
      // up_image_area[] で指定されたセッション領域のみ破棄
      if (!isset($imageUploadSessionKey) || !is_array($imageUploadSessionKey)) {
        $imageUploadSessionKey = [];
      }
      $pathsToDelete = [];
      foreach ($imageUploadSessionKey as $sKey) {
        if (!is_string($sKey) || $sKey === '') {
          continue;
        }
        if (isset($_SESSION[$sKey]) && is_array($_SESSION[$sKey])) {
          foreach ($_SESSION[$sKey] as $row) {
            if (!is_array($row)) {
              continue;
            }
            $tmp = $row['tmp_name'] ?? '';
            if (is_string($tmp) && $tmp !== '') {
              $pathsToDelete[] = $tmp;
            }
          }
        }
        unset($_SESSION[$sKey]);
      }
      cleanupFiles($pathsToDelete);
      $makeTag['status'] = 'success';
      $makeTag['title'] = 'discarded';
      $makeTag['msg'] = '';
      header('Content-Type: application/json');
      echo json_encode($makeTag);
      exit;
    }
    break;
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
          $uniqueName = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $savePath = $tmpDir . $uniqueName;
          $previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $uniqueName;
          if (move_uploaded_file($file['tmp_name'], $savePath)) {
            #セッションにファイル情報を保存
            # - onlyモードは「1枠」のため、成功時に既存を掃除して置換する
            if (!isset($_SESSION[$targetImageUploadSessionKey]) || !is_array($_SESSION[$targetImageUploadSessionKey])) {
              $_SESSION[$targetImageUploadSessionKey] = [];
            }
            if ($upImageMode === 'only') {
              foreach ($_SESSION[$targetImageUploadSessionKey] as $old) {
                if (!is_array($old)) {
                  continue;
                }
                $oldTmp = $old['tmp_name'] ?? '';
                if (is_string($oldTmp) && $oldTmp !== '' && file_exists($oldTmp) && is_file($oldTmp)) {
                  @unlink($oldTmp);
                }
              }
              $_SESSION[$targetImageUploadSessionKey] = [];
            }
            $_SESSION[$targetImageUploadSessionKey][] = [
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
    break;
  #***** 画像入れ替え（プレビューからの入れ替え） *****#
  case 'replaceUploadImage': {
      if (empty($targetImageUploadSessionKey)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = 'アップロードエリア名が指定されていません。';
        header('Content-Type: application/json');
        echo json_encode($makeTag);
        exit;
      }
      $replaceIndex = isset($_POST['replace_index']) ? intval($_POST['replace_index']) : null;
      $makeTag['file_url'] = '';
      $makeTag['file_name'] = '';
      $file = isset($_FILES['images_tmp']) ? $_FILES['images_tmp'] : null;
      $ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
      $allowed = ['jpg', 'jpeg', 'png', 'gif'];
      if ($replaceIndex === null || !$file || !is_uploaded_file($file['tmp_name']) || !in_array($ext, $allowed, true)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = '入れ替え画像が指定されていません。';
        header('Content-Type: application/json');
        echo json_encode($makeTag);
        exit;
      }
      $tmpDir = __DIR__ . '/../../../tmp_upload/';
      if (!file_exists($tmpDir)) {
        @mkdir($tmpDir, 0777, true);
      }
      $uniqueName = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
      $savePath = $tmpDir . $uniqueName;
      $previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $uniqueName;
      if (!move_uploaded_file($file['tmp_name'], $savePath)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = 'アップロード失敗';
        $makeTag['msg'] = 'ファイルの保存に失敗しました。';
        header('Content-Type: application/json');
        echo json_encode($makeTag);
        exit;
      }
      #DB登録済み画像の入れ替えでも扱えるよう、必要ならセッションをDBから展開する
      if (!isset($_SESSION[$targetImageUploadSessionKey]) || !is_array($_SESSION[$targetImageUploadSessionKey])) {
        $_SESSION[$targetImageUploadSessionKey] = [];
      }
      if (!array_key_exists($replaceIndex, $_SESSION[$targetImageUploadSessionKey])) {
        $logoList = [];
        if ($facId !== null && $facId !== '') {
          $facilityDetails = getFacilityDetails_FindById($facId);
          $facilityDetailsJson = [];
          if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
            $facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
            if (!is_array($facilityDetailsJson)) {
              $facilityDetailsJson = [];
            }
          }
          $logoList = $facilityDetailsJson['specialBanner']['logoImagePath'] ?? [];
          if (!is_array($logoList)) {
            $logoList = [];
          }
        }
        $_SESSION[$targetImageUploadSessionKey] = [];
        foreach ($logoList as $p) {
          if (!is_string($p) || $p === '') {
            $_SESSION[$targetImageUploadSessionKey][] = [];
            continue;
          }
          $_SESSION[$targetImageUploadSessionKey][] = [
            'tmp_name' => '',
            'preview' => DOMAIN_NAME . $p,
            'name' => basename($p),
            'path' => $p,
            'is_db' => true,
          ];
        }
      }
      #既存tmpがあれば削除
      $old = $_SESSION[$targetImageUploadSessionKey][$replaceIndex] ?? null;
      if (is_array($old)) {
        $oldTmp = $old['tmp_name'] ?? '';
        if (is_string($oldTmp) && $oldTmp !== '' && file_exists($oldTmp) && is_file($oldTmp)) {
          @unlink($oldTmp);
        }
      }
      $replaceFrom = (is_array($old) && isset($old['path']) && is_string($old['path'])) ? $old['path'] : '';
      $newRow = [
        'tmp_name' => $savePath,
        'preview' => $previewPath,
        'name' => $uniqueName,
        'original' => $file['name'],
        'type' => $file['type'],
        'size' => $file['size'],
        'uploaded_at' => time(),
      ];
      if (is_string($replaceFrom) && $replaceFrom !== '') {
        $newRow['replace_from_path'] = $replaceFrom;
      }
      $_SESSION[$targetImageUploadSessionKey][$replaceIndex] = $newRow;
      #応答
      $makeTag['status'] = 'success';
      $makeTag['file_url'] = '/tmp_upload/' . $uniqueName;
      $makeTag['file_name'] = $uniqueName;
      #プレビュー用タグ生成（セッションの並びをそのまま反映）
      $makeTag['tag'] = '';
      foreach ($_SESSION[$targetImageUploadSessionKey] as $row) {
        if (!is_array($row)) {
          continue;
        }
        $src = '';
        if (isset($row['tmp_name']) && is_string($row['tmp_name']) && $row['tmp_name'] !== '') {
          $src = $row['preview'] ?? '';
        } elseif (isset($row['preview']) && is_string($row['preview']) && $row['preview'] !== '') {
          $src = $row['preview'];
        } elseif (isset($row['path']) && is_string($row['path']) && $row['path'] !== '') {
          $src = DOMAIN_NAME . $row['path'];
        }
        if (!is_string($src) || $src === '') {
          continue;
        }
        $srcExt = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        switch ($srcExt) {
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
        $makeTag['tag'] .= <<<HTML
                  <li>
                    <div class="warp-btn">
                      <button type="button" class="btn-change"></button>
                      <button type="button" class="btn-delate"></button>
                    </div>
                    <picture>
                      <source src="{$src}" type="{$mimeType}">
                      <img src="{$src}" alt="ロゴ画像プレビュー">
                    </picture>
                  </li>

HTML;
      }
      #応答
      header('Content-Type: application/json');
      echo json_encode($makeTag);
      exit;
    }
    break;
  #***** 画像削除（プレビュー or 本体からの削除） *****#
  case 'deleteUploadImage': {
      #エリア名が未指定の場合はエラー応答
      if (empty($targetImageUploadSessionKey)) {
        $makeTag['status'] = 'error';
        $makeTag['title'] = '削除失敗';
        $makeTag['msg'] = 'アップロードエリア名が指定されていません。';
        header('Content-Type: application/json');
        echo json_encode($makeTag);
        exit;
      }
      $fileName = isset($_POST['file_name']) ? $_POST['file_name'] : '';
      if (!is_string($fileName) || $fileName === '') {
        $makeTag['status'] = 'error';
        $makeTag['title'] = '削除失敗';
        $makeTag['msg'] = '削除対象ファイルが指定されていません。';
        header('Content-Type: application/json');
        echo json_encode($makeTag);
        exit;
      }
      #セッションがある場合：tmp/擬似DBリストから削除
      if (isset($_SESSION[$targetImageUploadSessionKey]) && is_array($_SESSION[$targetImageUploadSessionKey])) {
        $wasMaterialized = false;
        foreach ($_SESSION[$targetImageUploadSessionKey] as $row) {
          if (!is_array($row)) continue;
          if ((isset($row['is_db']) && $row['is_db'] === true) || (isset($row['path']) && is_string($row['path']) && $row['path'] !== '')) {
            $wasMaterialized = true;
            break;
          }
        }
        $deleted = false;
        foreach ($_SESSION[$targetImageUploadSessionKey] as $idx => $info) {
          if (($info['name'] ?? '') === $fileName) {
            if (isset($info['tmp_name']) && is_string($info['tmp_name']) && $info['tmp_name'] !== '' && file_exists($info['tmp_name'])) {
              @unlink($info['tmp_name']);
            }
            array_splice($_SESSION[$targetImageUploadSessionKey], $idx, 1);
            $deleted = true;
            break;
          }
        }
        #削除応答
        if ($deleted) {
          $makeTag['status'] = 'success';
        } else {
          $makeTag['status'] = 'error';
          $makeTag['title'] = '削除失敗';
          $makeTag['msg'] = '削除対象が見つかりませんでした。';
        }
        #空になった場合：DB由来のmaterializedなら空を保持（保存時に全削除を反映）
        if (empty($_SESSION[$targetImageUploadSessionKey])) {
          if ($wasMaterialized) {
            $_SESSION[$targetImageUploadSessionKey] = [
              ['is_db' => true],
            ];
          } else {
            unset($_SESSION[$targetImageUploadSessionKey]);
          }
        }
        #応答
        header('Content-Type: application/json');
        echo json_encode($makeTag);
        exit;
      }
      #セッションが無い場合：DBの現状リストをセッションへ展開し、そこから除去（DB/本番は保存で確定）
      $facilityDetails = getFacilityDetails_FindById($facId);
      $facilityDetailsJson = [];
      if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
        $facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
        if (!is_array($facilityDetailsJson)) {
          $facilityDetailsJson = [];
        }
      }
      $logoList = $facilityDetailsJson['specialBanner']['logoImagePath'] ?? [];
      if (!is_array($logoList)) {
        $logoList = [];
      }
      $_SESSION[$targetImageUploadSessionKey] = [];
      foreach ($logoList as $p) {
        if (!is_string($p) || $p === '') {
          continue;
        }
        $_SESSION[$targetImageUploadSessionKey][] = [
          'tmp_name' => '',
          'preview' => DOMAIN_NAME . $p,
          'name' => basename($p),
          'path' => $p,
          'is_db' => true,
        ];
      }
      $deleted = false;
      foreach ($_SESSION[$targetImageUploadSessionKey] as $idx => $info) {
        if (($info['name'] ?? '') === $fileName) {
          array_splice($_SESSION[$targetImageUploadSessionKey], $idx, 1);
          $deleted = true;
          break;
        }
      }
      #削除応答
      if ($deleted) {
        $makeTag['status'] = 'success';
      } else {
        $makeTag['status'] = 'error';
        $makeTag['title'] = '削除失敗';
        $makeTag['msg'] = '削除対象が見つかりませんでした。';
      }
      #空になった場合：DB由来のmaterializedなら空を保持（保存時に全削除を反映）
      if (empty($_SESSION[$targetImageUploadSessionKey])) {
        $_SESSION[$targetImageUploadSessionKey] = [
          ['is_db' => true],
        ];
      }
      #応答
      header('Content-Type: application/json');
      echo json_encode($makeTag);
      exit;
    }
    break;
  #***** 入力チェック *****#
  case 'checkInput': {
      #共通項目
      #部署名
      if ($department_name === null || $department_name === '') {
        $viewDepartmentName = '-';
      } else {
        $viewDepartmentName = $department_name;
      }
      #設立年月日
      if ($established_date === null || $established_date === '') {
        $viewEstablishedDate = '-';
      } else {
        $viewEstablishedDate = date('Y年m月d日', strtotime($established_date));
      }
      $makeTag['tag'] .= <<<HTML
      <section class="container-vendor-check">
        <h2>事業所情報の入力内容確認</h2>
        <article class="block-check">
          <dl>
            <dt>事業所名</dt>
            <dd>{$facility_name}</dd>
          </dl>
          <dl>
            <dt>ふりがな</dt>
            <dd>{$facility_name_kana}</dd>
          </dl>
          <dl>
            <dt>事業形態</dt>
            <dd>{$facilityTypeName}</dd>
          </dl>
          <dl>
            <dt>部署名</dt>
            <dd>{$viewDepartmentName}</dd>
          </dl>
          <dl>
            <dt>担当者名</dt>
            <dd>{$contact_person}</dd>
          </dl>
          <dl>
            <dt>住所</dt>
            <dd class="item-add"><span>〒{$address1}</span>{$address2} {$address3}</dd>
          </dl>
          <dl>
            <dt>エリア</dt>
            <dd>{$recruitmentAreaName}</dd>
          </dl>
          <dl>
            <dt>地図表示<br>URL</dt>
            <dd>{$map_url}</dd>
          </dl>
          <dl>
            <dt>地図リンク用<br>URL</dt>
            <dd>{$map_link_url}</dd>
          </dl>
          <dl>
            <dt>電話番号</dt>
            <dd>{$phone_number}</dd>
          </dl>
          <dl>
            <dt>E-mail</dt>
            <dd>{$email}</dd>
          </dl>
          <dl>
            <dt>設立年月日</dt>
            <dd>{$viewEstablishedDate}</dd>
          </dl>

HTML;
      #病院／診療所／介護・福祉事業所
      if ($facility_type == 'hospital' || $facility_type == 'clinic' || $facility_type == 'nursing_care') {
        if ($facility_scale === null || $facility_scale === '') {
          $viewFacilityScale = '-';
        } else {
          $viewFacilityScale = nl2br($facility_scale);
        }
        $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>施設規模</dt>
            <dd>{$viewFacilityScale}</dd>
          </dl>

HTML;
      }
      #病院／診療所
      if ($facility_type == 'hospital' || $facility_type == 'clinic') {
        $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>緊急指定</dt>
            <dd>
              <div class="wrap-toggle-button">
                <label class="toggle-button">

HTML;
        #checked判定
        if ($is_emergency_designated == 1) {
          $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" checked>

HTML;
        } else {
          $makeTag['tag'] .= <<<HTML
                  <input type="checkbox">

HTML;
        }
        $makeTag['tag'] .= <<<HTML
                </label>
              </div>
            </dd>
          </dl>

HTML;
      }
      #共通項目
      if ($business_hours === null || $business_hours === '') {
        $viewBusinessHours = '-';
      } else {
        $viewBusinessHours = nl2br($business_hours);
      }
      $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>営業時間</dt>
            <dd>{$viewBusinessHours}</dd>
          </dl>

HTML;
      #共通項目
      if ($holidays === null || $holidays === '') {
        $viewHolidays = '-';
      } else {
        $viewHolidays = nl2br($holidays);
      }
      $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>休業日</dt>
            <dd>{$viewHolidays}</dd>
          </dl>

HTML;
      #介護・福祉事業所
      if ($facility_type == 'nursing_care') {
        if ($capacity_patients === null || $capacity_patients === '') {
          $viewCapacityPatients = '-';
        } else {
          $viewCapacityPatients = nl2br($capacity_patients);
        }
        $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>利用者定員数</dt>
            <dd>{$viewCapacityPatients}</dd>
          </dl>

HTML;
      }
      #病院／診療所／歯科診療所
      if ($facility_type == 'hospital' || $facility_type == 'clinic' || $facility_type == 'dental_clinic') {
        if ($average_patients === null || $average_patients === '') {
          $viewAveragePatients = '-';
        } else {
          $viewAveragePatients = nl2br($average_patients);
        }
        $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>平均患者数</dt>
            <dd>{$viewAveragePatients}</dd>
          </dl>

HTML;
      }
      #共通項目
      if ($staff_composition === null || $staff_composition === '') {
        $viewStaffComposition = '-';
      } else {
        $viewStaffComposition = nl2br($staff_composition);
      }
      $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>スタッフ構成</dt>
            <dd>{$viewStaffComposition}</dd>
          </dl>

HTML;
      #訪問看護ステーション
      if ($facility_type == 'home_nursing_station') {
        if ($visit_area === null || $visit_area === '') {
          $viewVisitArea = '-';
        } else {
          $viewVisitArea = nl2br($visit_area);
        }
        $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>訪問エリア</dt>
            <dd>{$viewVisitArea}</dd>
          </dl>

HTML;
      }
      #共通項目
      if ($remarks === null || $remarks === '') {
        $viewRemarks = '-';
      } else {
        $viewRemarks = nl2br($remarks);
      }
      $makeTag['tag'] .= <<<HTML
          <dl>
            <dt>備考</dt>
            <dd>{$viewRemarks}</dd>
          </dl>

HTML;
      #共通項目
      if ($special_banner == 1) {
        #checked判定
        $checked = ($special_banner == 1) ? 'checked' : '';
        $makeTag['tag'] .= <<<HTML
          <div class="inner-ban-plan">
            <div class="box-head">
              <h3>特別バナープラン</h3>
              <div class="wrap-toggle-button" style="display:none;">
                <label class="toggle-button">
                  <input type="checkbox" {$checked}>
                </label>
              </div>
            </div>

HTML;
        #画像あり判定：SESSIONから取得
        if ($special_banner == 1) {
          $makeTag['tag'] .= <<<HTML
            <dl>
              <dt>事務所ロゴ画像</dt>

HTML;
          #登録済みリスト展開
          $facilityDetails = getFacilityDetails_FindById($facId);
          #テーブル内JSONデコード
          $facilityDetailsJson = [];
          if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
            $facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
            if (!is_array($facilityDetailsJson)) {
              $facilityDetailsJson = [];
            }
          }
          if (isset($facilityDetailsJson['specialBanner']['logoImagePath']) && is_array($facilityDetailsJson['specialBanner']['logoImagePath'])) {
            foreach ($facilityDetailsJson['specialBanner']['logoImagePath'] as $idx => $path) {
              $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
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
              $previewPath = DOMAIN_NAME . $path;
              $makeTag['tag'] .= <<<HTML
              <dd>
                <picture>
                  <source src="{$previewPath}" type="{$mimeType}">
                  <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                </picture>
              </dd>

HTML;
            }
          }
          #登録画像リスト展開
          if (!empty($targetImageUploadSessionKey) && isset($_SESSION[$targetImageUploadSessionKey]) && is_array($_SESSION[$targetImageUploadSessionKey])) {
            foreach ($_SESSION[$targetImageUploadSessionKey] as $info) {
              $ext = strtolower(pathinfo($info['name'], PATHINFO_EXTENSION));
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
              $previewPath = DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $info['name'];
              $makeTag['tag'] .= <<<HTML
              <dd>
                <picture>
                  <source src="{$previewPath}" type="{$mimeType}">
                  <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                </picture>
              </dd>

HTML;
            }
          }
          $makeTag['tag'] .= <<<HTML
            </dl>

HTML;
        }
        #紹介動画URLあり判定
        if ($special_banner == 1 && $special_banner_video_url !== null && $special_banner_video_url !== '') {
          $makeTag['tag'] .= <<<HTML
            <dl>
              <dt>事業所紹介動画</dt>
              <dd>{$special_banner_video_url}</dd>
            </dl>

HTML;
        }
        $makeTag['tag'] .= <<<HTML
          </div>

HTML;
      }
      $makeTag['tag'] .= <<<HTML
          <div class="inner-select-company">
            <div class="box-head">
              <h3>法人名を選択</h3>
            </div>
            <dl>
              <dt>法人名</dt>
              <dd>{$facilityCorporationName}</dd>
            </dl>
          </div>
        </article>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="historyBack()">戻る</button>
          <button type="button" class="item-check" onclick="sendInput()">登録する</button>
        </div>
        <form name="inputForm" style="display:none;">
          <input type="hidden" name="action" value="sendInput">
          <input type="hidden" name="method" value="edit">
          <input type="hidden" name="facId" value="{$facId}">
          <input type="hidden" name="facCode" value="{$facCode}">
          <input type="hidden" name="facility_name" value="{$facility_name}">
          <input type="hidden" name="facility_name_kana" value="{$facility_name_kana}">
          <input type="hidden" name="facility_type" value="{$facility_type}">
          <input type="hidden" name="department_name" value="{$department_name}">
          <input type="hidden" name="contact_person" value="{$contact_person}">
          <input type="hidden" name="address1" value="{$address1}">
          <input type="hidden" name="address2" value="{$address2}">
          <input type="hidden" name="address3" value="{$address3}">
          <input type="hidden" name="recruitment_area" value="{$recruitment_area}">
          <input type="hidden" name="map_url" value="{$map_url}">
          <input type="hidden" name="map_link_url" value="{$map_link_url}">
          <input type="hidden" name="phone_number" value="{$phone_number}">
          <input type="hidden" name="email" value="{$email}">
          <input type="hidden" name="established_date" value="{$established_date}">
          <input type="hidden" name="facility_scale" value="{$facility_scale}">
          <input type="hidden" name="is_emergency_designated" value="{$is_emergency_designated}">
          <input type="hidden" name="business_hours" value="{$business_hours}">
          <input type="hidden" name="holidays" value="{$holidays}">
          <input type="hidden" name="capacity_patients" value="{$capacity_patients}">
          <input type="hidden" name="average_patients" value="{$average_patients}">
          <input type="hidden" name="staff_composition" value="{$staff_composition}">
          <input type="hidden" name="is_dormitory" value="{$is_dormitory}">
          <input type="hidden" name="is_childcare_support" value="{$is_childcare_support}">
          <input type="hidden" name="visit_area" value="{$visit_area}">
          <input type="hidden" name="remarks" value="{$remarks}">
          <input type="hidden" name="special_banner" value="{$special_banner}">
          <input type="hidden" name="special_banner_video_url" value="{$special_banner_video_url}">
          <input type="hidden" name="up_image_area[]" value="special_banner_logo_list">
          <input type="hidden" name="facility_corporation_code" value="{$facility_corporation_code}">
        </form>
      </section>

HTML;
    }
    break;
  #***** 修正 *****#
  case 'fixInput': {
      $makeTag['tag'] .= <<<HTML
      <section class="container-vendor-register">
        <a href="javascript:history.back()" class="link-page-back">戻る</a>
        <h2>事業所情報</h2>
        <form name="inputForm" class="block-form">
          <input type="hidden" name="method" value="edit">
          <input type="hidden" name="action" value="checkInput">
          <input type="hidden" name="facId" value="{$facId}">
          <input type="hidden" name="facCode" value="{$facCode}">
          <dl>
            <dt class="is-required">事業所名</dt>
            <dd><input type="text" name="facility_name" value="{$facility_name}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt class="is-required">ふりがな</dt>
            <dd><input type="text" name="facility_name_kana" value="{$facility_name_kana}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt class="is-required">事業形態</dt>
            <dd>
              <div class="select-job-category" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
      #選択中のラベル取得
      foreach ($facilityTypes as $facilityType) {
        if ($facility_type == $facilityType['id']) {
          $makeTag['tag'] .= <<<HTML
                  <input type="hidden" name="facility_type" value="{$facility_type}" data-selectbox-hidden class="required-item" required>
                  <span class="selectbox__value" data-selectbox-value>{$facilityType['label']}</span>

HTML;
          break;
        }
      }
      $makeTag['tag'] .= <<<HTML
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">

HTML;
      #表示可能リストあればループ処理
      if (isset($facilityTypes) && is_array($facilityTypes) && count($facilityTypes) > 0) {
        foreach ($facilityTypes as $facilityType) {
          #checked判定
          $checked = ($facility_type == $facilityType['id']) ? 'checked' : '';
          $makeTag['tag'] .= <<<HTML
                    <li>
                      <input type="radio" name="facility_type" value="{$facilityType['id']}" id="{$facilityType['id']}" {$checked}>
                      <label for="{$facilityType['id']}">{$facilityType['label']}</label>
                    </li>

HTML;
        }
      } else {
        $makeTag['tag'] .= <<<HTML
                    <li>
                      <input type="radio" name="facility_type" value="1" id="job01">
                      <label for="job01">事業形態が未設定です</label>
                    </li>

HTML;
      }
      $makeTag['tag'] .= <<<HTML
                  </ul>
                </div>
              </div>
            </dd>
          </dl>
          <dl>
            <dt>部署名</dt>
            <dd><input type="text" name="department_name" value="{$department_name}" style="max-width: 60rem"></dd>
          </dl>
          <dl>
            <dt class="is-required">担当者名</dt>
            <dd><input type="text" name="contact_person" value="{$contact_person}" class="required-item" required style="max-width: 34rem"></dd>
          </dl>
          <dl>
            <dt class="is-required">住所</dt>
            <dd>
              <input type="text" name="address1" value="{$address1}" class="required-item" required id="zipCode" id="zipCode" style="max-width: 14rem">
              <input type="text" name="address2" value="{$address2}">
              <input type="text" name="address3" value="{$address3}">
            </dd>
          </dl>
          <dl>
            <dt class="is-required">エリア</dt>
            <dd>
              <div class="select-area" data-selectbox>
                <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
      #選択中のラベル取得
      foreach ($recruitmentAreaList as $recruitmentArea) {
        if ($recruitment_area == $recruitmentArea['id']) {
          $makeTag['tag'] .= <<<HTML
                  <input type="hidden" name="recruitment_area" value="{$recruitment_area}" data-selectbox-hidden>
                  <span class="selectbox__value" data-selectbox-value>{$recruitmentArea['label']}</span>

HTML;
          break;
        }
      }
      $makeTag['tag'] .= <<<HTML
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">

HTML;
      #表示可能リストあればループ処理
      if (isset($recruitmentAreaList) && is_array($recruitmentAreaList) && count($recruitmentAreaList) > 0) {
        foreach ($recruitmentAreaList as $recruitmentArea) {
          #checked判定
          $checked = ($recruitment_area == $recruitmentArea['id']) ? 'checked' : '';
          $makeTag['tag'] .= <<<HTML
                    <li>
                      <input type="radio" name="recruitment_area" value="{$recruitmentArea['id']}" id="{$recruitmentArea['id']}" {$checked}>
                      <label for="{$recruitmentArea['id']}">{$recruitmentArea['label']}</label>
                    </li>

HTML;
        }
      } else {
        $makeTag['tag'] .= <<<HTML
                    <li>
                      <input type="radio" name="recruitment_area" value="area1" id="area01">
                      <label for="area01">募集エリアが未設定です</label>
                    </li>

HTML;
      }
      $makeTag['tag'] .= <<<HTML
                  </ul>
                </div>
              </div>
            </dd>
          </dl>
          <dl>
            <dt class="is-required">地図表示<br>URL</dt>
            <dd><textarea name="map_url" class="required-item" required>{$map_url}</textarea></dd>
          </dl>
          <dl>
            <dt class="is-required">地図リンク用<br>URL</dt>
            <dd><textarea name="map_link_url" class="required-item" required>{$map_link_url}</textarea></dd>
          </dl>
          <dl>
            <dt class="is-required">電話番号</dt>
            <dd><input type="text" name="phone_number" value="{$phone_number}" autocomplete="on" class="required-item phone_number" required style="max-width: 34rem"></dd>
          </dl>
          <dl>
            <dt>E-mail</dt>
            <dd><input type="text" name="email" value="{$email}" autocomplete="on" class="required-item email" required style="max-width: 60rem; border-color: #ababab; background-color: #eee;"></dd>
          </dl>
          <hr>
          <dl data-field="established_date">
            <dt>設立年月日</dt>
            <dd>
              <div class="input-date"><input type="date" name="established_date" value="{$established_date}"></div>
            </dd>
          </dl>
          <dl data-field="facility_scale">
            <dt>施設規模</dt>
            <dd><textarea name="facility_scale">{$facility_scale}</textarea></dd>
          </dl>
          <dl data-field="is_emergency_designated">
            <dt>緊急指定</dt>
            <dd>
              <div class="wrap-toggle-button">
                <label class="toggle-button">

HTML;
      #checked判定
      if ($is_emergency_designated == 1) {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="is_emergency_designated" value="1" checked>

HTML;
      } else {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="is_emergency_designated" value="1">

HTML;
      }
      $makeTag['tag'] .= <<<HTML
                </label>
              </div>
            </dd>
          </dl>
          <dl data-field="business_hours">
            <dt>営業時間</dt>
            <dd><textarea name="business_hours">{$business_hours}</textarea></dd>
          </dl>
          <dl data-field="holidays">
            <dt>休業日</dt>
            <dd><input type="text" name="holidays" value="{$holidays}" style="max-width: 42rem"></dd>
          </dl>
          <dl data-field="capacity_patients">
            <dt>利用者定員数</dt>
            <dd><input type="text" name="capacity_patients" value="{$capacity_patients}" style="max-width: 42rem"></dd>
          </dl>
          <dl data-field="average_patients">
            <dt>平均患者数</dt>
            <dd><input type="text" name="average_patients" value="{$average_patients}" style="max-width: 42rem"></dd>
          </dl>
          <dl data-field="staff_composition">
            <dt>スタッフ構成</dt>
            <dd><textarea name="staff_composition">{$staff_composition}</textarea></dd>
          </dl>
          <dl data-field="is_dormitory">
            <dt>社宅・寮</dt>
            <dd>
              <div class="wrap-toggle-button">
                <label class="toggle-button">

HTML;
      #checked判定
      if ($is_dormitory == 1) {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="is_dormitory" value="1" checked>

HTML;
      } else {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="is_dormitory" value="1">

HTML;
      }
      $makeTag['tag'] .= <<<HTML
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
      if ($is_childcare_support == 1) {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="is_childcare_support" value="1" checked>

HTML;
      } else {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="is_childcare_support" value="1">

HTML;
      }
      $makeTag['tag'] .= <<<HTML
                </label>
              </div>
            </dd>
          </dl>
          <dl data-field="visit_area">
            <dt>訪問エリア</dt>
            <dd><textarea name="visit_area">{$visit_area}</textarea></dd>
          </dl>
          <dl data-field="remarks">
            <dt>備考</dt>
            <dd><textarea name="remarks">{$remarks}</textarea></dd>
          </dl>
          <div class="inner-ban-plan">
            <div class="box-head">
              <h3>特別バナープラン</h3>
              <div class="wrap-toggle-button" style="display:none;">
                <label class="toggle-button">

HTML;
      #checked判定
      if ($special_banner == 1) {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="special_banner" value="1" checked onclick="toggleSpecialBannerPlan()">

HTML;
      } else {
        $makeTag['tag'] .= <<<HTML
                  <input type="checkbox" name="special_banner" value="1" onclick="toggleSpecialBannerPlan()">

HTML;
      }
      $makeTag['tag'] .= <<<HTML
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
      #画像プレビュー（特別バナーOFFでもhiddenで保持しておき、ONに戻したとき復帰できるようにする）
      $previewLis = '';
      $sessionPreviews = [];
      if (!empty($targetImageUploadSessionKey) && isset($_SESSION[$targetImageUploadSessionKey]) && is_array($_SESSION[$targetImageUploadSessionKey])) {
        foreach ($_SESSION[$targetImageUploadSessionKey] as $info) {
          if (!is_array($info)) {
            continue;
          }
          $name = $info['name'] ?? '';
          if (!is_string($name) || $name === '') {
            continue;
          }
          $src = $info['preview'] ?? (DEFINE_PREVIEW_IMAGE_DIR_PATH . '/' . $name);
          if (is_string($src) && $src !== '') {
            $sessionPreviews[] = $src;
          }
        }
      }

      $dbPreviews = [];
      if (empty($sessionPreviews) && $facId !== null && $facId !== '') {
        $facilityDetails = getFacilityDetails_FindById($facId);
        $facilityDetailsJson = [];
        if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
          $facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
          if (!is_array($facilityDetailsJson)) {
            $facilityDetailsJson = [];
          }
        }
        $logoList = $facilityDetailsJson['specialBanner']['logoImagePath'] ?? [];
        if (is_array($logoList)) {
          foreach ($logoList as $path) {
            if (!is_string($path) || $path === '') {
              continue;
            }
            $dbPreviews[] = DOMAIN_NAME . $path;
          }
        }
      }
      $renderPreviews = !empty($sessionPreviews) ? $sessionPreviews : $dbPreviews;
      #1枠運用：表示も先頭1件のみ
      if (count($renderPreviews) > 1) {
        $renderPreviews = [reset($renderPreviews)];
      }
      #プレビューリスト作成
      foreach ($renderPreviews as $src) {
        $srcExt = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        switch ($srcExt) {
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
        $previewLis .= <<<HTML
                  <li>
                    <div class="warp-btn">
                      <button type="button" class="btn-change"></button>
                      <button type="button" class="btn-delate"></button>
                    </div>
                    <picture>
                      <source src="{$src}" type="{$mimeType}">
                      <img src="{$src}" alt="ロゴ画像プレビュー">
                    </picture>
                  </li>

HTML;
      }
      #プレビューリストが空なら非表示にする
      $previewStyle = '';
      if ($special_banner != 1 || $previewLis === '') {
        $previewStyle = ' style="display: none;"';
      }
      $makeTag['tag'] .= <<<HTML
                <ul class="selected-image-list" id="js-previewBlock-mainLogo"{$previewStyle}>

HTML;
      $makeTag['tag'] .= $previewLis;
      $makeTag['tag'] .= <<<HTML
                </ul>

HTML;
      $makeTag['tag'] .= <<<HTML
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
                <input type="text" name="special_banner_video_url" value="{$special_banner_video_url}">
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
                <div class="select-company" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false" style="pointer-events:none; border-color:#ababab; background-color:#eee;">

HTML;
      #法人が選択されていたら
      if (isset($facility_corporation_code) && $facility_corporation_code != '') {
        #法人コードをキーに法人情報を取得
        $selectedCorporation = getCorporations_FindById_Code(null, $facility_corporation_code);
        $selectedCorporationName = $selectedCorporation['name'];
        $makeTag['tag'] .= <<<HTML
                    <input type="hidden" name="facility_corporation_code" value="{$facility_corporation_code}" data-selectbox-hidden class="required-item" required>
                    <span class="selectbox__value" data-selectbox-value>{$selectedCorporationName}</span>

HTML;
      } else {
        $makeTag['tag'] .= <<<HTML
                    <input type="hidden" name="facility_corporation_code" value="" data-selectbox-hidden class="required-item" required>
                    <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
      }
      $makeTag['tag'] .= <<<HTML
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
          $checked = ($facility_corporation_code == $corpInputValue) ? 'checked' : '';
          $makeTag['tag'] .= <<<HTML
                      <li>
                        <input type="radio" name="facility_corporation_code" value="{$corpInputValue}" id="{$corpInputID}" {$checked}>
                        <label for="{$corpInputID}">{$corpInputName}</label>
                      </li>

HTML;
        }
      }
      #JSONエスケープ処理
      $jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
      $facilityNameJs = json_encode((string)$facility_name, $jsonHex);
      $facilityCodeJs = json_encode((string)$facCode, $jsonHex);
      $facilityNameJsAttr = htmlspecialchars((string)$facilityNameJs, ENT_QUOTES, 'UTF-8');
      $facilityCodeJsAttr = htmlspecialchars((string)$facilityCodeJs, ENT_QUOTES, 'UTF-8');
      $facIdInt = (int)$facId;
      $makeTag['tag'] .= <<<HTML
                    </ul>
                  </div>
                </div>
              </dd>
            </dl>
          </div>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="history.back(2)">戻る</button>
          <button type="button" class="item-check" onclick="checkInput()">入力を確認する</button>
        </div>
        <!--NOTE 修正画面のみ表示 -->
        <button type="button" class="btn-delate-item" onclick="checkDeleteFacility({$facIdInt}, {$facilityNameJsAttr}, {$facilityCodeJsAttr})">削除する</button>
      </section>

HTML;
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
            'pageName' => 'proc_client02_01',
            'reason' => 'トランザクション開始失敗',
          ];
          makeLog($data);
        } else {
          #----------------------------
          # method別 前処理
          # delete時は登録情報をPOSTしない仕様のため、参照しない
          #----------------------------
          $getCorporationData = null;
          $postal_code = null;
          $prefecture = '';
          $city = '';
          $address_line = '';
          $phone = null;
          if ($method === 'new' || $method === 'edit') {
            #法人コードから法人IDを取得
            $getCorporationData = getCorporations_FindById_Code(null, $facility_corporation_code);
            #郵便番号フォーマット
            $postal_code = formatPostalCode($address1);
            #都道府県・市区町村・番地に分割
            $addressParts = separateAddress($address2 . ' ' . $address3);
            $prefecture = isset($addressParts['state']) ? $addressParts['state'] : '';
            $city = isset($addressParts['city']) ? $addressParts['city'] : '';
            $address_line = isset($addressParts['other']) ? $addressParts['other'] : '';
            #電話番号フォーマット
            $phone = str_replace(['-', '−', '―', 'ー', '‐'], '', $phone_number);
            $phone = formatPhoneNumber($phone, false);
          } elseif ($method === 'delete') {
            #削除は最低限のパラメータのみ必須
            if ($facId === null || $facId === '') {
              throw new Exception('事業所IDが未指定です');
            }
            if ($facility_name === null || $facility_name === '') {
              $facility_name = '事業所';
            }
          } else {
            throw new Exception('不正な処理methodです');
          }

          #----------------------------
          # edit/delete は facCode をPOSTから信用しない（FS操作に使われるため）
          #----------------------------
          $facilityDataFromDb = null;
          if ($method === 'edit' || $method === 'delete') {
            if ($facId === null || $facId === '') {
              throw new Exception('事業所IDが未指定です');
            }
            $facilityDataFromDb = getFacility_FindById($facId);
            if (!is_array($facilityDataFromDb) || !isset($facilityDataFromDb['facility_code'])) {
              throw new Exception('事業所コードの取得に失敗しました');
            }
            $facCodeDb = (string)$facilityDataFromDb['facility_code'];
            if ($facCodeDb === '' || !preg_match('/^fac_\d{4}$/', $facCodeDb)) {
              throw new Exception('事業所コードが不正です');
            }
            $facCode = $facCodeDb;
            if (($facility_name === null || $facility_name === '') && isset($facilityDataFromDb['name'])) {
              $facility_name = (string)$facilityDataFromDb['name'];
            }
          }
          #DB登録結果フラグ：初期化
          $dbCompleteFlg = true;
          #新規登録時の仮パスワード（accounts.password_hash NOT NULL対策：待機アカウント用のランダム値）
          $tempPassword = null;
          #------------------------------------
          # ファイル操作は「コミット後」に確定する
          #  - DB失敗/ロールバック時は tmp を掃除
          #------------------------------------
          $pendingMkdirDirs = [];
          $pendingMoves = [];
          $pendingDeleteDirs = [];
          $pendingDeleteFiles = [];
          $pendingTmpFiles = [];
          $pendingClearSessions = [];
          #DB登録情報準備
          switch ($method) {
            #***** 新規登録 *****#
            case 'new': {
                $getFacilityId = getLastFacilityId();
                if ($getFacilityId === false) {
                  #例外処理へ
                  throw new Exception('最新事業所ID取得失敗');
                } else {
                  #最新事業所ID取得成功
                  $facilityCode = 'fac_' . sprintf("%04d", $getFacilityId['AUTO_INCREMENT']);
                }
                #登録用配列：初期化
                $dbFiledData = array();
                #登録情報セット
                $dbFiledData['facility_code'] = array(':facility_code', $facilityCode, 0);
                $dbFiledData['corporation_id'] = array(':corporation_id', $getCorporationData['corporation_id'], 1);
                $dbFiledData['facility_type_id'] = array(':facility_type_id', $facility_type, 0);
                $dbFiledData['name'] = array(':name', $facility_name, 0);
                $dbFiledData['name_kana'] = array(':name_kana', $facility_name_kana, 0);
                $dbFiledData['established_date'] = array(':established_date', $established_date, 0);
                $dbFiledData['postal_code'] = array(':postal_code', $postal_code['zipCode'], 0);
                $dbFiledData['prefecture'] = array(':prefecture', $prefecture, 0);
                $dbFiledData['city'] = array(':city', $city, 0);
                $dbFiledData['address_line'] = array(':address_line', trim($address_line), 0);
                $dbFiledData['recruitment_area'] = array(':recruitment_area', $recruitment_area, 0);
                $dbFiledData['phone'] = array(':phone', $phone, 0);
                $dbFiledData['email'] = array(':email', $email, 0);
                $dbFiledData['map_url'] = array(':map_url', $map_url, 0);
                $dbFiledData['map_link_url'] = array(':map_link_url', $map_link_url, 0);
                $dbFiledData['is_active'] = array(':is_active', 1, 1);
                $dbFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
                #更新用キー：初期化
                $dbFiledValue = array();
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 1;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "facilities", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                #追加した事業所IDを取得
                $newFacilityId = $DB_CONNECT->lastInsertId();
                #事業所の基本情報登録完了後に詳細情報を登録する
                if ($dbSuccessFlg == 1) {
                  #--------------------------------------------------------------------
                  # 事業所アカウントを先に作成（ここで失敗した場合、以降のファイル操作を抑止）
                  #--------------------------------------------------------------------
                  #仮パスワード生成（より安全な乱数）
                  try {
                    $tempPassword = bin2hex(random_bytes(4));
                  } catch (Exception $e) {
                    $tempPassword = null;
                  }
                  if ($tempPassword === null || $tempPassword === '') {
                    #エラーログ出力
                    $data = [
                      'pageName' => 'proc_client02_01',
                      'reason' => '仮パスワード生成失敗',
                    ];
                    makeLog($data);
                    $dbCompleteFlg = false;
                  }
                  if ($dbCompleteFlg == true) {
                    #パスワードハッシュ化
                    $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
                    #登録用配列：初期化
                    $dbAccountFiledData = array();
                    #登録情報セット
                    $dbAccountFiledData['account_type'] = array(':account_type', 'facility', 0);
                    $dbAccountFiledData['login_email'] = array(':login_email', $email, 0);
                    $dbAccountFiledData['password_hash'] = array(':password_hash', $passwordHash, 0);
                    $dbAccountFiledData['facility_id'] = array(':facility_id', $newFacilityId, 1);
                    #初回パスワード設定待ち
                    $dbAccountFiledData['is_active'] = array(':is_active', 9, 1);
                    $dbAccountFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
                    #更新用キー：初期化
                    $dbAccountFiledValue = array();
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $accountProcessFlg = 1;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $accountExeFlg = 2;
                    #DB更新
                    $dbAccountSuccessFlg = SQL_Process($DB_CONNECT, "accounts", $dbAccountFiledData, $dbAccountFiledValue, $accountProcessFlg, $accountExeFlg);
                    if ($dbAccountSuccessFlg != 1) {
                      #エラーログ出力
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '事業所アカウント情報DB登録失敗',
                      ];
                      makeLog($data);
                      #DB登録失敗
                      $dbCompleteFlg = false;
                    }
                  }
                  if ($dbCompleteFlg != true) {
                    break;
                  }
                  #JSON/画像ディレクトリ作成はコミット後に実施
                  $facilityJsonDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityCode . '/';
                  $pendingMkdirDirs[] = $facilityJsonDir;

                  #--- 特別バナープラン画像の確定予約（コミット後に移動） ---#
                  $bannerImageDir = DEFINE_FILE_DIR_PATH . '/facilities/' . $facilityCode . '/';
                  $pendingMkdirDirs[] = $bannerImageDir;
                  $logoImagePathArr = [];
                  foreach ($imageUploadSessionKey as $area) {
                    if ($special_banner == 1 && isset($_SESSION[$area]) && is_array($_SESSION[$area])) {
                      foreach ($_SESSION[$area] as $imgIndex => $img) {
                        $src = $img['tmp_name'] ?? '';
                        $name = $img['name'] ?? '';
                        if (!is_string($src) || $src === '' || !is_string($name) || $name === '') {
                          continue;
                        }
                        $dst = rtrim($bannerImageDir, '/\\') . '/' . $name;
                        $pendingMoves[] = ['src' => $src, 'dst' => $dst];
                        $pendingTmpFiles[] = $src;
                        $newPublicPath = '/db/images/facilities/' . $facilityCode . '/' . $name;
                        if (is_int($imgIndex) && $imgIndex >= 0 && $imgIndex < count($logoImagePathArr)) {
                          $logoImagePathArr[$imgIndex] = $newPublicPath;
                        } else {
                          $logoImagePathArr[] = $newPublicPath;
                        }
                      }
                      $pendingClearSessions[] = $area;
                    }
                  }
                  #--- ここまで確定予約 ---#

                  #特別バナー無効化時は「完全削除」
                  if ($special_banner != 1) {
                    #DB登録済みの画像ファイルをコミット後に削除
                    if (isset($logoImagePathArr) && is_array($logoImagePathArr)) {
                      foreach ($logoImagePathArr as $oldPath) {
                        if (!is_string($oldPath) || $oldPath === '') {
                          continue;
                        }
                        $oldBase = basename($oldPath);
                        if (!is_string($oldBase) || $oldBase === '') {
                          continue;
                        }
                        $pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
                      }
                    }
                    #アップロード途中のtmpがあれば掃除してセッションも破棄
                    foreach ($imageUploadSessionKey as $area) {
                      if (isset($_SESSION[$area]) && is_array($_SESSION[$area])) {
                        foreach ($_SESSION[$area] as $row) {
                          if (!is_array($row)) continue;
                          $src = $row['tmp_name'] ?? '';
                          if (is_string($src) && $src !== '') {
                            $pendingTmpFiles[] = $src;
                          }
                        }
                        $pendingClearSessions[] = $area;
                      }
                    }
                    $logoImagePathArr = [];
                    $special_banner_video_url = '';
                  }

                  #--- 詳細情報用JSONデータ作成 ---#
                  $makeDetailsJson = array(
                    'businessHours' => $business_hours,
                    'holidays' => $holidays,
                    'staffComposition' => $staff_composition,
                    'facilityScale' => $facility_scale,
                    'averagePatients' => $average_patients,
                    'capacityPatients' => $capacity_patients,
                    'dormitory' => $is_dormitory,
                    'childcareSupport' => $is_childcare_support,
                    'typeSpecific' => array(
                      'visitArea' => $visit_area,
                      'note' => $remarks,
                    ),
                    "recruitJobs" => array(),
                    'specialBanner' => array(
                      'enabled' => ($special_banner == 1) ? true : false,
                      'logoImagePath' => ($special_banner == 1) ? $logoImagePathArr : [],
                      'introVideoUrl' => ($special_banner == 1) ? $special_banner_video_url : '',
                    ),
                  );
                  #JSONエンコード
                  $detailsJson = json_encode($makeDetailsJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                  #登録用配列：初期化
                  $dbDetailFiledData = array();
                  #登録情報セット
                  $dbDetailFiledData['facility_id'] = array(':facility_id', $newFacilityId, 1);
                  $dbDetailFiledData['department_name'] = array(':department_name', $department_name, 0);
                  $dbDetailFiledData['contact_person'] = array(':contact_person', $contact_person, 0);
                  $dbDetailFiledData['is_emergency_designated'] = array(':is_emergency_designated', $is_emergency_designated, 1);
                  $dbDetailFiledData['details_json'] = array(':details_json', $detailsJson, 0);
                  $dbDetailFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
                  #更新用キー：初期化
                  $dbDetailFiledValue = array();
                  #処理モード：[1].新規追加｜[2].更新｜[3].削除
                  $detailProcessFlg = 1;
                  #実行モード：[1].トランザクション｜[2].即実行
                  $detailExeFlg = 2;
                  #DB更新
                  $dbDetailSuccessFlg = SQL_Process($DB_CONNECT, "facility_details", $dbDetailFiledData, $dbDetailFiledValue, $detailProcessFlg, $detailExeFlg);
                  if ($dbDetailSuccessFlg != 1) {
                    #エラーログ出力
                    $data = [
                      'pageName' => 'proc_client02_01',
                      'reason' => '事業所詳細情報DB登録失敗',
                    ];
                    makeLog($data);
                    #DB登録失敗
                    $dbCompleteFlg = false;
                  }
                } else {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_client02_01',
                    'reason' => '事業所基本情報DB登録失敗',
                  ];
                  makeLog($data);
                  #DB登録失敗
                  $dbCompleteFlg = false;
                }
              }
              break;
            #***** 編集 *****#
            case 'edit': {
                #登録用配列：初期化
                $dbFiledData = array();
                #登録情報セット
                $dbFiledData['corporation_id'] = array(':corporation_id', $getCorporationData['corporation_id'], 1);
                $dbFiledData['facility_type_id'] = array(':facility_type_id', $facility_type, 0);
                $dbFiledData['name'] = array(':name', $facility_name, 0);
                $dbFiledData['name_kana'] = array(':name_kana', $facility_name_kana, 0);
                $dbFiledData['established_date'] = array(':established_date', $established_date, 0);
                $dbFiledData['postal_code'] = array(':postal_code', $postal_code['zipCode'], 0);
                $dbFiledData['prefecture'] = array(':prefecture', $prefecture, 0);
                $dbFiledData['city'] = array(':city', $city, 0);
                $dbFiledData['address_line'] = array(':address_line', trim($address_line), 0);
                $dbFiledData['recruitment_area'] = array(':recruitment_area', trim($recruitment_area), 0);
                $dbFiledData['phone'] = array(':phone', $phone, 0);
                $dbFiledData['email'] = array(':email', $email, 0);
                $dbFiledData['map_url'] = array(':map_url', $map_url, 0);
                $dbFiledData['map_link_url'] = array(':map_link_url', $map_link_url, 0);
                $dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
                #更新用キー：初期化
                $dbFiledValue = array();
                $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 2;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "facilities", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                #事業所の基本情報登録完了後に詳細情報を登録する
                if ($dbSuccessFlg == 1) {
                  #JSON/画像ディレクトリ作成はコミット後に実施
                  $facilityJsonDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facCode . '/';
                  $pendingMkdirDirs[] = $facilityJsonDir;

                  #--- 特別バナープラン画像の確定予約（コミット後に移動） ---#
                  $bannerImageDir = DEFINE_FILE_DIR_PATH . '/facilities/' . $facCode . '/';
                  $pendingMkdirDirs[] = $bannerImageDir;
                  $logoImagePathArr = [];
                  #登録済みリスト展開
                  $facilityDetails = getFacilityDetails_FindById($facId);
                  #テーブル内JSONデコード
                  $facilityDetailsJson = [];
                  if (isset($facilityDetails['details_json']) && $facilityDetails['details_json']) {
                    $facilityDetailsJson = json_decode($facilityDetails['details_json'], true);
                    if (!is_array($facilityDetailsJson)) {
                      $facilityDetailsJson = [];
                    }
                  }
                  $existingLogoPaths = [];
                  if (isset($facilityDetailsJson['specialBanner']['logoImagePath']) && is_array($facilityDetailsJson['specialBanner']['logoImagePath'])) {
                    $existingLogoPaths = $facilityDetailsJson['specialBanner']['logoImagePath'];
                    foreach ($existingLogoPaths as $path) {
                      $logoImagePathArr[] = $path;
                    }
                  }

                  #特別バナー無効化時は「完全削除」
                  if ($special_banner != 1) {
                    foreach ($existingLogoPaths as $oldPath) {
                      if (!is_string($oldPath) || $oldPath === '') {
                        continue;
                      }
                      $oldBase = basename($oldPath);
                      if (!is_string($oldBase) || $oldBase === '') {
                        continue;
                      }
                      $pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
                    }
                    #アップロード途中のtmpがあれば掃除してセッションも破棄
                    foreach ($imageUploadSessionKey as $area) {
                      if (isset($_SESSION[$area]) && is_array($_SESSION[$area])) {
                        foreach ($_SESSION[$area] as $row) {
                          if (!is_array($row)) continue;
                          $src = $row['tmp_name'] ?? '';
                          if (is_string($src) && $src !== '') {
                            $pendingTmpFiles[] = $src;
                          }
                        }
                        $pendingClearSessions[] = $area;
                      }
                    }
                    $logoImagePathArr = [];
                    $special_banner_video_url = '';
                  }
                  #--- セッション画像の反映処理 ---#
                  foreach ($imageUploadSessionKey as $area) {
                    if (!($special_banner == 1 && isset($_SESSION[$area]) && is_array($_SESSION[$area]))) {
                      continue;
                    }
                    #セッションが「DBリストを展開済み（materialized）」かどうか判定
                    $isMaterialized = false;
                    foreach ($_SESSION[$area] as $row) {
                      if (!is_array($row)) continue;
                      if ((isset($row['is_db']) && $row['is_db'] === true) || (isset($row['path']) && is_string($row['path']) && $row['path'] !== '')) {
                        $isMaterialized = true;
                        break;
                      }
                    }
                    if ($isMaterialized) {
                      #セッションの並び順を最終形として採用（削除/置換/追加を反映）
                      $finalPaths = [];
                      $finalBasenames = [];
                      foreach ($_SESSION[$area] as $imgIndex => $img) {
                        if (!is_array($img)) continue;
                        $src = $img['tmp_name'] ?? '';
                        $name = $img['name'] ?? '';
                        $path = $img['path'] ?? '';
                        if (is_string($src) && $src !== '' && is_string($name) && $name !== '') {
                          $dst = rtrim($bannerImageDir, '/\\') . '/' . $name;
                          $pendingMoves[] = ['src' => $src, 'dst' => $dst];
                          $pendingTmpFiles[] = $src;
                          $newPublicPath = '/db/images/facilities/' . $facCode . '/' . $name;
                          $finalPaths[] = $newPublicPath;
                          $finalBasenames[] = basename($newPublicPath);
                          #置換元が分かるならコミット後に削除
                          $replaceFrom = $img['replace_from_path'] ?? ($existingLogoPaths[$imgIndex] ?? '');
                          if (is_string($replaceFrom) && $replaceFrom !== '') {
                            $oldBase = basename($replaceFrom);
                            if (is_string($oldBase) && $oldBase !== '') {
                              $pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
                            }
                          }
                        } elseif (is_string($path) && $path !== '') {
                          $finalPaths[] = $path;
                          $finalBasenames[] = basename($path);
                        } else {
                          #削除済み/空
                          continue;
                        }
                      }
                      #削除されたDB画像をコミット後に削除
                      foreach ($existingLogoPaths as $oldPath) {
                        if (!is_string($oldPath) || $oldPath === '') continue;
                        $oldBase = basename($oldPath);
                        if ($oldBase === '') continue;
                        if (!in_array($oldBase, $finalBasenames, true)) {
                          $pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
                        }
                      }
                      $logoImagePathArr = $finalPaths;
                    } else {
                      #従来どおり：DBの既存 + セッションの新規（追加/置換）
                      foreach ($_SESSION[$area] as $imgIndex => $img) {
                        $src = $img['tmp_name'] ?? '';
                        $name = $img['name'] ?? '';
                        if (!is_string($src) || $src === '' || !is_string($name) || $name === '') {
                          continue;
                        }
                        $dst = rtrim($bannerImageDir, '/\\') . '/' . $name;
                        $pendingMoves[] = ['src' => $src, 'dst' => $dst];
                        $pendingTmpFiles[] = $src;
                        $newPublicPath = '/db/images/facilities/' . $facCode . '/' . $name;
                        if (is_int($imgIndex) && $imgIndex >= 0 && $imgIndex < count($logoImagePathArr)) {
                          $oldPublicPath = $logoImagePathArr[$imgIndex];
                          $logoImagePathArr[$imgIndex] = $newPublicPath;
                          if (is_string($oldPublicPath) && $oldPublicPath !== '') {
                            $oldBase = basename($oldPublicPath);
                            if (is_string($oldBase) && $oldBase !== '') {
                              $pendingDeleteFiles[] = rtrim($bannerImageDir, '/\\') . '/' . $oldBase;
                            }
                          }
                        } else {
                          $logoImagePathArr[] = $newPublicPath;
                        }
                      }
                    }
                    $pendingClearSessions[] = $area;
                  }
                  #--- ここまでセッション画像の反映処理 ---#

                  #--- 詳細情報用JSONデータ作成 ---#
                  $makeDetailsJson = array(
                    'businessHours' => $business_hours,
                    'holidays' => $holidays,
                    'staffComposition' => $staff_composition,
                    'facilityScale' => $facility_scale,
                    'averagePatients' => $average_patients,
                    'capacityPatients' => $capacity_patients,
                    'dormitory' => $is_dormitory,
                    'childcareSupport' => $is_childcare_support,
                    'typeSpecific' => array(
                      'visitArea' => $visit_area,
                      'note' => $remarks,
                    ),
                    "recruitJobs" => array(),
                    'specialBanner' => array(
                      'enabled' => ($special_banner == 1) ? true : false,
                      'logoImagePath' => ($special_banner == 1) ? $logoImagePathArr : [],
                      'introVideoUrl' => ($special_banner == 1) ? $special_banner_video_url : '',
                    ),
                  );
                  #JSONエンコード
                  $detailsJson = json_encode($makeDetailsJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                  #登録用配列：初期化
                  $dbDetailFiledData = array();
                  #登録情報セット
                  $dbDetailFiledData['department_name'] = array(':department_name', $department_name, 0);
                  $dbDetailFiledData['contact_person'] = array(':contact_person', $contact_person, 0);
                  $dbDetailFiledData['is_emergency_designated'] = array(':is_emergency_designated', $is_emergency_designated, 1);
                  $dbDetailFiledData['details_json'] = array(':details_json', $detailsJson, 0);
                  $dbDetailFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
                  #更新用キー：初期化
                  $dbDetailFiledValue = array();
                  $dbDetailFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                  #処理モード：[1].新規追加｜[2].更新｜[3].削除
                  $detailProcessFlg = 2;
                  #実行モード：[1].トランザクション｜[2].即実行
                  $detailExeFlg = 2;
                  #DB更新
                  $dbDetailSuccessFlg = SQL_Process($DB_CONNECT, "facility_details", $dbDetailFiledData, $dbDetailFiledValue, $detailProcessFlg, $detailExeFlg);
                  if ($dbDetailSuccessFlg != 1) {
                    #エラーログ出力
                    $data = [
                      'pageName' => 'proc_client02_01',
                      'reason' => '事業所詳細情報DB更新失敗',
                    ];
                    makeLog($data);
                    #DB登録失敗
                    $dbCompleteFlg = false;
                  } else {
                    #--------------------------------------------------------
                    # 事業所メールアドレス変更時にアカウント側(login_email)も同期
                    #  - is_active=9(待機)でも発行画面で参照できるようにする
                    #--------------------------------------------------------
                    #登録用配列：初期化
                    $dbAccountFiledData = array();
                    #登録情報セット
                    $dbAccountFiledData['login_email'] = array(':login_email', $email, 0);
                    #更新用キー：初期化
                    $dbAccountFiledValue = array();
                    $dbAccountFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $accountProcessFlg = 2;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $accountExeFlg = 2;
                    #DB更新
                    $dbAccountSuccessFlg = SQL_Process($DB_CONNECT, "accounts", $dbAccountFiledData, $dbAccountFiledValue, $accountProcessFlg, $accountExeFlg);
                    if ($dbAccountSuccessFlg != 1) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '事業所アカウント情報(login_email)DB更新失敗',
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                    }
                  }
                } else {
                  #エラーログ出力
                  $data = [
                    'pageName' => 'proc_client02_01',
                    'reason' => '事業所基本情報DB更新失敗',
                  ];
                  makeLog($data);
                  #DB登録失敗
                  $dbCompleteFlg = false;
                }
              }
              break;
            #***** 削除 *****#
            case 'delete': {
                #---------------------------------------------------------------------------
                # 事業所に紐づく求人カード情報を削除（DB）
                #  - jobs は施設配下のカードの親
                #  - 関連テーブル（job_*）は job_id でぶら下がる
                #  - JSON/画像は facilities/{facCode}/ 配下を削除するため、ここではDB削除に集中
                #---------------------------------------------------------------------------
                try {
                  $jobIdsForDelete = [];
                  $stmt = $DB_CONNECT->prepare('SELECT job_id FROM jobs WHERE facility_id = :facility_id');
                  $stmt->execute([':facility_id' => $facId]);
                  $jobIdsForDelete = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                  if (!is_array($jobIdsForDelete)) {
                    $jobIdsForDelete = [];
                  }
                  foreach ($jobIdsForDelete as $jobIdForDelete) {
                    $jobIdForDelete = (string)$jobIdForDelete;
                    if ($jobIdForDelete === '') {
                      continue;
                    }
                    #job_documents（job_id単位で全削除）
                    #登録用配列：初期化
                    $dbJobDocFiledData = array();
                    #更新用キー：初期化
                    $dbJobDocFiledValue = array();
                    $dbJobDocFiledValue['job_id'] = array(':job_id', $jobIdForDelete, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $jobDocProcessFlg = 3;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $jobDocExeFlg = 2;
                    #DB更新
                    $dbJobDocSuccessFlg = SQL_Process($DB_CONNECT, "job_documents", $dbJobDocFiledData, $dbJobDocFiledValue, $jobDocProcessFlg, $jobDocExeFlg);
                    if ($dbJobDocSuccessFlg != 1) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '求人カード関連DB削除失敗（job_documents）',
                        'job_id' => $jobIdForDelete,
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                      break;
                    }
                    #job_metric_values（job_id単位で全削除）
                    #登録用配列：初期化
                    $dbMetricFiledData = array();
                    #更新用キー：初期化
                    $dbMetricFiledValue = array();
                    $dbMetricFiledValue['job_id'] = array(':job_id', $jobIdForDelete, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $metricProcessFlg = 3;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $metricExeFlg = 2;
                    #DB更新
                    $dbMetricSuccessFlg = SQL_Process($DB_CONNECT, "job_metric_values", $dbMetricFiledData, $dbMetricFiledValue, $metricProcessFlg, $metricExeFlg);
                    if ($dbMetricSuccessFlg != 1) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '求人カード関連DB削除失敗（job_metric_values）',
                        'job_id' => $jobIdForDelete,
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                      break;
                    }
                    #job_option_links（job_id単位で全削除）
                    #登録用配列：初期化
                    $dbOptLinkFiledData = array();
                    #更新用キー：初期化
                    $dbOptLinkFiledValue = array();
                    $dbOptLinkFiledValue['job_id'] = array(':job_id', $jobIdForDelete, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $optLinkProcessFlg = 3;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $optLinkExeFlg = 2;
                    #DB更新
                    $dbOptLinkSuccessFlg = SQL_Process($DB_CONNECT, "job_option_links", $dbOptLinkFiledData, $dbOptLinkFiledValue, $optLinkProcessFlg, $optLinkExeFlg);
                    if ($dbOptLinkSuccessFlg != 1) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '求人カード関連DB削除失敗（job_option_links）',
                        'job_id' => $jobIdForDelete,
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                      break;
                    }
                    #job_option_link_extras（job_id単位で全削除）
                    #登録用配列：初期化
                    $dbOptExtraFiledData = array();
                    #更新用キー：初期化
                    $dbOptExtraFiledValue = array();
                    $dbOptExtraFiledValue['job_id'] = array(':job_id', $jobIdForDelete, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $optExtraProcessFlg = 3;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $optExtraExeFlg = 2;
                    #DB更新
                    $dbOptExtraSuccessFlg = SQL_Process($DB_CONNECT, "job_option_link_extras", $dbOptExtraFiledData, $dbOptExtraFiledValue, $optExtraProcessFlg, $optExtraExeFlg);
                    if ($dbOptExtraSuccessFlg != 1) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '求人カード関連DB削除失敗（job_option_link_extras）',
                        'job_id' => $jobIdForDelete,
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                      break;
                    }
                    #applications（存在する場合は job_id 単位で削除）
                    try {
                      $stmtApp = $DB_CONNECT->prepare('DELETE FROM applications WHERE job_id = :job_id');
                      $stmtApp->execute([':job_id' => $jobIdForDelete]);
                    } catch (Exception $e) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '求人応募データ削除で例外（applications）',
                        'job_id' => $jobIdForDelete,
                        'errorMessage' => $e->getMessage(),
                      ];
                      makeLog($data);
                    }
                    #jobs（最後に削除）
                    #登録用配列：初期化
                    $dbJobFiledData = array();
                    #更新用キー：初期化
                    $dbJobFiledValue = array();
                    $dbJobFiledValue['job_id'] = array(':job_id', $jobIdForDelete, 1);
                    $dbJobFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $jobProcessFlg = 3;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $jobExeFlg = 2;
                    #DB更新
                    $dbJobSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbJobFiledData, $dbJobFiledValue, $jobProcessFlg, $jobExeFlg);
                    if ($dbJobSuccessFlg != 1) {
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '求人カードDB削除失敗（jobs）',
                        'job_id' => $jobIdForDelete,
                      ];
                      makeLog($data);
                      $dbCompleteFlg = false;
                      break;
                    } else {
                      #accounts（事業所削除によりアカウントも削除）
                      #登録用配列：初期化
                      $dbAccountFiledData = array();
                      #更新用キー：初期化
                      $dbAccountFiledValue = array();
                      $dbAccountFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                      #処理モード：[1].新規追加｜[2].更新｜[3].削除
                      $accountProcessFlg = 3;
                      #実行モード：[1].トランザクション｜[2].即実行
                      $accountExeFlg = 2;
                      #DB更新
                      $dbAccountSuccessFlg = SQL_Process($DB_CONNECT, "accounts", $dbAccountFiledData, $dbAccountFiledValue, $accountProcessFlg, $accountExeFlg);
                      if ($dbAccountSuccessFlg != 1) {
                        #エラーログ出力
                        $data = [
                          'pageName' => 'proc_client02_01',
                          'reason' => '事業所アカウント情報DB削除失敗',
                        ];
                        makeLog($data);
                        #DB登録失敗
                        $dbCompleteFlg = false;
                      }
                    }
                  }
                } catch (Exception $e) {
                  $data = [
                    'pageName' => 'proc_client02_01',
                    'reason' => '求人カード一覧取得／削除処理で例外',
                    'errorMessage' => $e->getMessage(),
                  ];
                  makeLog($data);
                  $dbCompleteFlg = false;
                }
                if ($dbCompleteFlg != true) {
                  break;
                }
                #登録用配列：初期化
                $dbFiledData = array();
                #更新用キー：初期化
                $dbFiledValue = array();
                $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                #処理モード：[1].新規追加｜[2].更新｜[3].削除
                $processFlg = 3;
                #実行モード：[1].トランザクション｜[2].即実行
                $exeFlg = 2;
                #DB更新
                $dbSuccessFlg = SQL_Process($DB_CONNECT, "facilities", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
                #事業所の基本情報削除完了後に詳細情報を削除する
                if ($dbSuccessFlg == 1) {
                  #登録用配列：初期化
                  $dbDetailFiledData = array();
                  #更新用キー：初期化
                  $dbDetailFiledValue = array();
                  $dbDetailFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                  #処理モード：[1].新規追加｜[2].更新｜[3].削除
                  $detailProcessFlg = 3;
                  #実行モード：[1].トランザクション｜[2].即実行
                  $detailExeFlg = 2;
                  #DB更新
                  $dbDetailSuccessFlg = SQL_Process($DB_CONNECT, "facility_details", $dbDetailFiledData, $dbDetailFiledValue, $detailProcessFlg, $detailExeFlg);
                  #基本情報・詳細情報削除完了後の処理
                  if ($dbDetailSuccessFlg == 1) {
                    #アカウントマスターから事業所アカウント情報を削除
                    #登録用配列：初期化
                    $dbAccountFiledData = array();
                    #更新用キー：初期化
                    $dbAccountFiledValue = array();
                    $dbAccountFiledValue['facility_id'] = array(':facility_id', $facId, 1);
                    #処理モード：[1].新規追加｜[2].更新｜[3].削除
                    $accountProcessFlg = 3;
                    #実行モード：[1].トランザクション｜[2].即実行
                    $accountExeFlg = 2;
                    #DB更新
                    $dbAccountSuccessFlg = SQL_Process($DB_CONNECT, "accounts", $dbAccountFiledData, $dbAccountFiledValue, $accountProcessFlg, $accountExeFlg);
                    if ($dbAccountSuccessFlg == 1) {
                      #アカウント情報削除成功後に事業所ディレクトリ削除を「コミット後」に実施
                      if (is_string($facCode) && $facCode !== '' && preg_match('/^fac_\\d{4}$/', $facCode)) {
                        $pendingDeleteDirs[] = DEFINE_JSON_DIR_PATH . '/facilities/' . $facCode . '/';
                        $pendingDeleteDirs[] = DEFINE_FILE_DIR_PATH . '/facilities/' . $facCode . '/';
                      } else {
                        $data = [
                          'pageName' => 'proc_client02_01',
                          'reason' => '事業所コード未指定のためディレクトリ削除をスキップ',
                        ];
                        makeLog($data);
                      }
                    } else {
                      #エラーログ出力
                      $data = [
                        'pageName' => 'proc_client02_01',
                        'reason' => '事業所アカウント情報DB削除失敗',
                      ];
                      makeLog($data);
                      #DB登録失敗
                      $dbCompleteFlg = false;
                    }
                  } else {
                    #エラーログ出力
                    $data = [
                      'pageName' => 'proc_client02_01',
                      'reason' => '事業所詳細情報DB削除失敗',
                    ];
                    makeLog($data);
                    #DB登録失敗
                    $dbCompleteFlg = false;
                  }
                }
              }
              break;
          }
          #全ての処理成功
          if ($dbCompleteFlg == true) {
            #DBコミット
            # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
            DB_Transaction(2);
            $makeTag['status'] = 'success';
            switch ($method) {
              #***** 新規登録 *****#
              case 'new': {
                  $makeTag['title'] = '新規事業所登録';
                  $makeTag['msg'] = '登録が完了しました。';
                  $makeTag['facId'] = $newFacilityId;
                }
                break;
              #***** 編集 *****#
              case 'edit': {
                  $makeTag['title'] = '事業所情報編集';
                  $makeTag['msg'] = '更新が完了しました。';
                  $makeTag['facId'] = $facId;
                }
                break;
              #***** 削除 *****#
              case 'delete': {
                  $makeTag['title'] = '事業所情報削除';
                  $makeTag['msg'] = $facility_name . 'の削除が完了しました。';
                  $makeTag['facId'] = $facId;
                }
                break;
            }
            #----------------------------
            # コミット後のファイル確定処理
            #----------------------------
            $fsErrors = [];
            #mkdir
            $pendingMkdirDirs = array_values(array_unique(array_filter($pendingMkdirDirs, 'is_string')));
            foreach ($pendingMkdirDirs as $dir) {
              if ($dir === '') continue;
              if (!ensureDir($dir)) {
                $fsErrors[] = 'mkdir失敗: ' . $dir;
              }
            }
            #move
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
            #delete files (置換で不要になった古いファイルなど)
            $pendingDeleteFiles = array_values(array_unique(array_filter($pendingDeleteFiles, 'is_string')));
            foreach ($pendingDeleteFiles as $f) {
              if ($f === '') continue;
              if (file_exists($f) && is_file($f)) {
                @unlink($f);
              }
            }
            #delete dirs
            $pendingDeleteDirs = array_values(array_unique(array_filter($pendingDeleteDirs, 'is_string')));
            foreach ($pendingDeleteDirs as $dir) {
              if ($dir === '') continue;
              if (is_dir($dir)) {
                if (!rrmdir($dir)) {
                  $fsErrors[] = 'dir削除失敗: ' . $dir;
                }
              }
            }
            #tmp掃除（move成功したtmpだけ掃除。失敗時にtmpを消すと画像が失われるため）
            cleanupFiles($movedTmpFiles);
            #セッション掃除（コミット後にまとめて）
            $pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
            foreach ($pendingClearSessions as $sKey) {
              unset($_SESSION[$sKey]);
            }
            if (count($fsErrors) > 0) {
              $data = [
                'pageName' => 'proc_client02_01',
                'reason' => 'コミット後のファイル確定処理で失敗',
                'fsErrors' => $fsErrors,
              ];
              makeLog($data);
              #UI上は登録成功扱い（DBは確定済み）にして注意文を付与
              $makeTag['msg'] .= '<br>※画像/ディレクトリ確定処理で一部失敗しました。ログをご確認ください。';
            }
            #----------------------------
            # DB更新完了のJSONファイル作成
            #----------------------------
            if ($method !== 'delete') {
              $jsonFacId = ($method === 'new') ? $newFacilityId : $facId;
              $cmd = '/usr/bin/php8.3 ' . DEFINE_JSON_FUNCTION_MASTER . '/workJson/makeFacility.php ' . $jsonFacId . ' 2>&1 &';
              exec($cmd, $output, $return_var);
            }
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
            $makeTag['status'] = 'error';
          }
        }
      } catch (Exception $e) {
        #エラーログ出力
        $data = [
          'pageName' => 'proc_client02_01',
          'reason' => 'トランザクション開始失敗',
          'errorMessage' => $e->getMessage(),
        ];
        makeLog($data);
        #例外時もクリーンアップ（tmpファイルとセッション）
        cleanupFiles($pendingTmpFiles ?? []);
        if (isset($pendingClearSessions) && is_array($pendingClearSessions)) {
          $pendingClearSessions = array_values(array_unique(array_filter($pendingClearSessions, 'is_string')));
          foreach ($pendingClearSessions as $sKey) {
            unset($_SESSION[$sKey]);
          }
        }
        $makeTag['status'] = 'error';
      }
    }
    break;
}
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
