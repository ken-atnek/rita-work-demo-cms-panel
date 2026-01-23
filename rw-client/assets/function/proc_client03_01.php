<?php
/*
 * [rw-client/assets/function/proc_client03_01.php]
 *  - 【事業所】管理画面 -
 *  求人カード登録／編集 処理
 *
 * [初版]
 *  2026.1.22
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
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
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';
#求人カードDB書き込み共通ヘルパー
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_job_card_write_helpers.php';

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array();
$makeTag['tag'] = '';
$makeTag['status'] = '';
$makeTag['title'] = '';
$makeTag['msg'] = '';
$makeTag['facId'] = '';

#===================================#
# フロント側マスタ定義JSONファイル取得
#-----------------------------------#
#取得項目一覧
$jsonMasters = [];
try {
  $jsonMasters = getJson_FrontEndMaster_many([
    'jobCategories',
    'contractPlans'
  ]);
} catch (Throwable $e) {
  if (function_exists('makeLog')) {
    makeLog('[proc_client03_01] master JSON load failed: ' . $e->getMessage());
  }
  $jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#契約プランマスタ
$contractPlans = $jsonMasters['contractPlans'] ?? [];

#=============#
# POSTチェック
#-------------#
#確認／修正／登録
$action = isset($_POST['action']) ? $_POST['action'] : null;
#事業所ID
$facId = isset($_POST['facId']) ? $_POST['facId'] : null;
#求人カードID
$jobId = isset($_POST['jobId']) ? $_POST['jobId'] : null;
#求人カードコード
$jobCardCode = isset($_POST['jobCardCode']) ? $_POST['jobCardCode'] : null;
#求人カードステータス
$change_status = isset($_POST['changeStatus']) ? $_POST['changeStatus'] : 1;
#再掲載 or 削除フラグ
$execution = isset($_POST['execution']) ? $_POST['execution'] : null;
#-------------#
#事業所IDがあれば事業所情報取得
if ($facId !== null) {
  #事業所情報取得
  $facilityData = getFacility_FindById($facId);
} else {
  #事業所情報無し：処理終了 (エラー応答)
  $makeTag['status'] = 'error';
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
 * プランステータス更新
 * @param string $jobCardJsonPath JSONファイルパス
 * @param string $status ステータス
 */
function updateJobStatus(string $jobCardJsonPath, string $status): void
{
  $allowed = ['public', 'draft', 'private'];
  if (!in_array($status, $allowed, true)) {
    throw new InvalidArgumentException("status must be one of: public, draft, private");
  }
  if (!is_file($jobCardJsonPath)) {
    throw new RuntimeException("JSON file not found: {$jobCardJsonPath}");
  }
  $json = file_get_contents($jobCardJsonPath);
  if ($json === false) {
    throw new RuntimeException("Failed to read: {$jobCardJsonPath}");
  }
  $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
  #ここだけ更新
  $data['status'] = $status;
  #必要なら PRETTY_PRINT を外す（ファイルサイズ優先）
  $out = json_encode(
    $data,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
  );
  #原子的に置換（途中で落ちても壊れにくい）
  $tmp = $jobCardJsonPath . '.tmp';
  if (file_put_contents($tmp, $out . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException("Failed to write temp: {$tmp}");
  }
  if (!rename($tmp, $jobCardJsonPath)) {
    @unlink($tmp);
    throw new RuntimeException("Failed to replace: {$jobCardJsonPath}");
  }
}

#***** タグ生成開始 *****#
switch ($action) {
  #***** ステータス変更 *****#
  case 'changeStatus': {
      try {
        #トランザクション開始
        # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
        $result = DB_Transaction(1);
        if ($result == false) {
          #エラーログ出力
          $data = [
            'pageName' => 'proc_client03_01',
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
          #***** ステータス変更 *****#
          if ($execution != 'delete') {
            #登録用配列：初期化
            $dbFiledData = array();
            #登録情報セット
            $dbFiledData['is_active'] = array(':is_active', $change_status, 1);
            $dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
            if ($change_status == '99') {
              $dbFiledData['published_end'] = array(':published_end', date("Y-m-d"), 0);
            } else {
              $dbFiledData['published_end'] = array(':published_end', null, 0);
            }
            #更新用キー：初期化
            $dbFiledValue = array();
            $dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
            $dbFiledValue['facility_id'] = array(':facility_id', $facId, 1);
            #処理モード：[1].新規追加｜[2].更新｜[3].削除
            $processFlg = 2;
            #DB更新
            #実行モード：[1].トランザクション｜[2].即実行
            $exeFlg = 2;
            $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
            if ($dbSuccessFlg == 1) {
              #１）カードマスターjson作成：job_〇〇〇.json
              #json保存先
              $jobsCardMasterJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
              if (!is_dir($jobsCardMasterJsonSaveDir)) {
                @mkdir($jobsCardMasterJsonSaveDir, 0777, true);
              }
              #書き込み用jsonファイルが無い場合はベースファイルを作成
              $jobsCardMasterJson = $jobCardCode . '.json';
              if (!file_exists($jobsCardMasterJsonSaveDir . '/' . $jobsCardMasterJson)) {
                makeJson($jobsCardMasterJsonSaveDir, $jobsCardMasterJson);
              }
              #ステータス判定
              switch ($change_status) {
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
              #作成済み求人カードjsonファイル取得
              $jobCardJsonPath = $jobsCardMasterJsonSaveDir . '/' . $jobsCardMasterJson;
              #カードマスターjson更新
              updateJobStatus($jobCardJsonPath, $statusText);
            }
          } else {
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
            #DB更新
            #実行モード：[1].トランザクション｜[2].即実行
            $exeFlg = 2;
            $dbSuccessFlg = SQL_Process($DB_CONNECT, "jobs", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
            if ($dbSuccessFlg == 1) {
              #関連する登録情報を削除（DB）
              $deleteOk = true;
              $dbCompleteFlg = true;
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
                  'pageName' => 'proc_client03_01_01',
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
            }
          }
          #求人カード登録完了後に詳細情報を登録する
          if ($dbSuccessFlg == 1) {
            #DBコミット
            # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
            DB_Transaction(2);
            $makeTag['status'] = 'success';
            $makeTag['title'] = '求人カード状況';
            $makeTag['facId'] = $facId;
            switch ($change_status) {
              #***** 下書き中 *****#
              case '1': {
                  $makeTag['msg'] = '求人ID：' . $jobCardCode . 'を<span style="font-weight:bold;">下書き中</span>で登録しました。';
                }
                break;
              #***** 公開中 *****#
              case '2': {
                  if ($execution === 'restore') {
                    $makeTag['title'] = '求人カード再掲載';
                    $makeTag['msg'] = '求人ID：' . $jobCardCode . 'の掲載を再開しました。<br>求人カードの内容を確認又は更新してください。';
                  } else {
                    $makeTag['msg'] = '求人ID：' . $jobCardCode . 'を<span style="font-weight:bold;">公開中</span>で登録しました。';
                  }
                }
                break;
              #***** プラン解約 *****#
              case '99': {
                  $makeTag['title'] = '求人カード状況';
                  $makeTag['msg'] = '求人ID：' . $jobCardCode . 'を<span style="font-weight:bold;color:#0000CD;">解約</span>しました。';
                }
                break;
              #***** プラン削除 *****#
              case '0': {
                  $makeTag['title'] = '求人カード状況';
                  $makeTag['msg'] = '求人ID：' . $jobCardCode . 'を<span style="font-weight:bold;color:#DD0000;">削除</span>しました。';
                }
                break;
            }
            #--------------------------------------------------------------
            # 求人カードステータスDB更新完了のJSONファイル作成；jobsIndex.json
            #--------------------------------------------------------------
            #json保存先
            $jobsIndexJsonSaveDir = DEFINE_JSON_DIR_PATH . '/facilities/' . $facilityData['facility_code'] . '/jobs/';
            if (!is_dir($jobsIndexJsonSaveDir)) {
              @mkdir($jobsIndexJsonSaveDir, 0777, true);
            }
            #書き込み用jsonファイルが無い場合はベースファイルを作成
            $jobsIndexJson = 'jobsIndex.json';
            if (!file_exists($jobsIndexJsonSaveDir . '/' . $jobsIndexJson)) {
              makeJson($jobsIndexJsonSaveDir, $jobsIndexJson);
            }
            #jsonデータ生成
            $jobIndexResult = createJobIndex_JSON($jobsIndexJsonSaveDir, $jobsIndexJson, $facId, $facilityData);
            #----------------------------
            # DB更新完了のJSONファイル作成
            #----------------------------
            #１）求人カード全インデックスJSON作成：jobsIndexAll.json
            $cmd = '/usr/bin/php8.3 ' . DEFINE_JSON_FUNCTION_MASTER . '/workJson/makeIndexAll.php 2>&1 &';
            exec($cmd, $output, $return_var);
          } else {
            #DBロールバック
            # 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
            DB_Transaction(3);
            $makeTag['status'] = 'error';
          }
        }
      } catch (Exception $e) {
        #エラーログ出力
        $data = [
          'pageName' => 'proc_client03_01',
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
  $jobsIndexWriteData = [];
  #表示可能リストあればループ処理
  if (isset($jobCardList) && is_array($jobCardList) && count($jobCardList) > 0) {
    #jsonデータ生成
    $jobsIndexWriteData = [
      'facilityId' => $facilityData['facility_code'],
      'items' => [],
    ];
    $jobsIndexWriteItems = [];
    #住所データ作成
    $workLocation = $facilityData['prefecture'] . $facilityData['city'] . $facilityData['address_line'];
    foreach ($jobCardList as $jobCard) {
      #ステータス判定
      if ($jobCard['is_active'] != 2) {
        continue;
      }
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
  #JSONエンコード
  $news_jobsIndex_json = json_encode($jobsIndexWriteData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  #ファイル書き込み
  $write_jobsIndex_json = fopen($jobsIndexJsonSaveDir . '/' . $jobsIndexJson, "w");
  fwrite($write_jobsIndex_json, $news_jobsIndex_json);
  fclose($write_jobsIndex_json);
}
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
