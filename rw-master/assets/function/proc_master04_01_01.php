<?php
/*
 * [rw-master/assets/function/proc_master04_01.php]
 *  - 管理画面 -
 *  応募者一覧：検索/絞り込み/並び替え/ページング（AJAX）
 *
 * [初版]
 *  2026.1.29
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#応募者情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_applications.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#求人カード情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_jobs.php';

#==============#
# 事業所一覧取得
#--------------#
$facilityList = getFacilityList(false);

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array(
	'tag' => '',
	'status' => '',
	'title' => '',
	'msg' => '',
);
#新規作成直後のハイライト対象
$highlightApplicationId = 0;

#=============#
# POSTチェック
#-------------#
#セッションキー
$noUpDateKey = isset($_POST['noUpDateKey']) ? (string)$_POST['noUpDateKey'] : '';
#noUpDateKey は「画面インスタンス識別用」。
#画面遷移/マルチタブ等でキーが更新されている場合があるため、
#POSTキーが無効ならセッション側の現行キーへフォールバックする。
$currentNoUpDateKey = isset($_SESSION['sKey']) ? (string)$_SESSION['sKey'] : '';
if ($noUpDateKey === '' || isset($_SESSION[$noUpDateKey]) === false) {
	if ($currentNoUpDateKey !== '' && isset($_SESSION[$currentNoUpDateKey])) {
		$noUpDateKey = $currentNoUpDateKey;
	} else {
		#AJAX向け：JSONでエラー返却（fetch側で画面リロード誘導）
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = 'セッションエラー';
		$makeTag['msg'] = 'セッションが切れました。ページを再読み込みしてください。';
		$makeTag['noUpDateKey'] = $currentNoUpDateKey;
		echo json_encode($makeTag);
		exit;
	}
}
#応答には常に現行のキーを含め、フロント側のhiddenを更新できるようにする
$makeTag['noUpDateKey'] = ($currentNoUpDateKey !== '' ? $currentNoUpDateKey : $noUpDateKey);

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
		makeLog('[proc_master04_01] master JSON load failed: ' . $e->getMessage());
	}
	$jsonMasters = [];
}
#募集職種マスタ
$jobCategories = $jsonMasters['jobCategories'] ?? [];
#JS文脈用のjson_encodeフラグ
$jsonHex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

#-------------#
#検索・ステータス変更
$action = isset($_POST['action']) ? $_POST['action'] : '';
#検索モード
$searchMode = 'all';
#ソートモード
$sortMode = isset($_POST['sortMode']) ? $_POST['sortMode'] : '';
#事業所ID（任意：masterは空=全件）
$facId = isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : 0;
#求職者LINEユーザーID
$lineUserId = isset($_POST['lineId']) ? (string)$_POST['lineId'] : '';
#-------------#

#==================================#
# 応募者プロフィール（名前/メモ）更新
#----------------------------------#
if ($action === 'updateApplicantProfile') {
	if ($lineUserId === '') {
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = '登録エラー';
		$makeTag['msg'] = '求職者IDが取得できませんでした。ページを再読み込みしてください。';
		echo json_encode($makeTag);
		exit;
	}
	$applicantNameRaw = isset($_POST['applicantName']) ? (string)$_POST['applicantName'] : '';
	$memoRaw = isset($_POST['memo']) ? (string)$_POST['memo'] : '';
	$applicantNameTrim = trim($applicantNameRaw);
	$memoTrim = trim($memoRaw);
	$applicantName = ($applicantNameTrim === '') ? null : $applicantNameRaw;
	$memo = ($memoTrim === '') ? null : $memoRaw;
	$now = date('Y-m-d H:i:s');
	try {
		$result = DB_Transaction(1);
		if ($result == false) {
			$makeTag['status'] = 'error';
			$makeTag['title'] = '登録エラー';
			$makeTag['msg'] = 'トランザクション開始に失敗しました。';
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode($makeTag);
			exit;
		}
		#applicants_memo へUPSERT（line_user_id UNIQUE前提）
		$strSQL = "INSERT INTO applicants_memo (line_user_id, applicant_name, memo, created_at, updated_at)\n"
			. "VALUES (:line_user_id, :applicant_name, :memo, :created_at, :updated_at)\n"
			. "ON DUPLICATE KEY UPDATE applicant_name = VALUES(applicant_name), memo = VALUES(memo), updated_at = VALUES(updated_at)";
		$stmt = $DB_CONNECT->prepare($strSQL);
		$stmt->bindValue(':line_user_id', (string)$lineUserId, PDO::PARAM_STR);
		if ($applicantName === null) {
			$stmt->bindValue(':applicant_name', null, PDO::PARAM_NULL);
		} else {
			$stmt->bindValue(':applicant_name', (string)$applicantName, PDO::PARAM_STR);
		}
		if ($memo === null) {
			$stmt->bindValue(':memo', null, PDO::PARAM_NULL);
		} else {
			$stmt->bindValue(':memo', (string)$memo, PDO::PARAM_STR);
		}
		$stmt->bindValue(':created_at', (string)$now, PDO::PARAM_STR);
		$stmt->bindValue(':updated_at', (string)$now, PDO::PARAM_STR);
		$stmt->execute();
		$stmt->closeCursor();
		#applications 側の applicant_name も同期（一覧表示・更新モーダルの名前表示用）
		$upd = $DB_CONNECT->prepare('UPDATE applications SET applicant_name = :applicant_name, updated_at = :updated_at WHERE line_user_id = :line_user_id');
		$upd->bindValue(':line_user_id', (string)$lineUserId, PDO::PARAM_STR);
		if ($applicantName === null) {
			$upd->bindValue(':applicant_name', null, PDO::PARAM_NULL);
		} else {
			$upd->bindValue(':applicant_name', (string)$applicantName, PDO::PARAM_STR);
		}
		$upd->bindValue(':updated_at', (string)$now, PDO::PARAM_STR);
		$upd->execute();
		$upd->closeCursor();
		DB_Transaction(2);
		$makeTag['status'] = 'success';
		$makeTag['title'] = '登録';
		$makeTag['msg'] = '名前・メモを登録しました。';
	} catch (Throwable $e) {
		DB_Transaction(3);
		if (function_exists('makeLog')) {
			makeLog('[proc_master04_01] updateApplicantProfile failed: ' . $e->getMessage());
		}
		$makeTag['status'] = 'error';
		$makeTag['title'] = '登録エラー';
		$makeTag['msg'] = '名前・メモの登録に失敗しました。';
	}
}

#======================#
# 新規：空の応募行を作成
#----------------------#
if ($action === 'createDraftApplication') {
	if ($lineUserId === '') {
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = '新規作成エラー';
		$makeTag['msg'] = '求職者IDが取得できませんでした。ページを再読み込みしてください。';
		echo json_encode($makeTag);
		exit;
	}
	#応募者情報（名前など）は最新応募から引き継ぐ
	$latest = getApplicationBylineUserId($lineUserId);
	$lineDisplayName = is_array($latest) ? ($latest['line_display_name'] ?? null) : null;
	$applicantName = is_array($latest) ? ($latest['applicant_name'] ?? null) : null;
	#$corporationId = is_array($latest) && isset($latest['corporation_id']) ? (int)$latest['corporation_id'] : 0;
	#if ($corporationId < 1) {
	#	header('Content-Type: application/json; charset=UTF-8');
	#	$makeTag['status'] = 'error';
	#	$makeTag['title'] = '新規作成エラー';
	#	$makeTag['msg'] = '法人IDが取得できませんでした。ページを再読み込みしてください。';
	#	echo json_encode($makeTag);
	#	exit;
	#}
	#draft用 job_id を生成（UNIQUE(job_id, line_user_id) を満たすため）
	$now = date('Y-m-d H:i:s');
	$inserted = false;
	try {
		$result = DB_Transaction(1);
		if ($result == false) {
			$makeTag['status'] = 'error';
			$makeTag['title'] = '新規作成エラー';
			$makeTag['msg'] = 'トランザクション開始に失敗しました。';
			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode($makeTag);
			exit;
		}
		for ($attempt = 0; $attempt < 10; $attempt++) {
			$dbFiledData = array();
			#applications の job_id / facility_id / corporation_id / updated_at は NOT NULL。
			#「空行」として扱うため、job_id はダミー値（同一line_user_id内で一意）を採番し、他は0/空で登録する。
			#job_id は SQL_Process 側で PDO::PARAM_INT バインドされるため、32bit 範囲に収める。
			$draftJobId = random_int(1000000000, 1999999999);
			$chk = $DB_CONNECT->prepare('SELECT COUNT(*) FROM applications WHERE line_user_id = :line_user_id AND job_id = :job_id');
			$chk->bindValue(':line_user_id', (string)$lineUserId, PDO::PARAM_STR);
			$chk->bindValue(':job_id', (int)$draftJobId, PDO::PARAM_INT);
			$chk->execute();
			$exists = (int)$chk->fetchColumn();
			$chk->closeCursor();
			if ($exists > 0) {
				continue;
			}
			$dbFiledData['job_id'] = array(':job_id', $draftJobId, 1);
			# job_id_uq は求人の一意コードだが、空行では未設定（0を空扱い）
			$dbFiledData['job_id_uq'] = array(':job_id_uq', 0, 1);
			$dbFiledData['job_category_id'] = array(':job_category_id', null, 2);
			$dbFiledData['facility_id'] = array(':facility_id', 0, 1);
			$dbFiledData['corporation_id'] = array(':corporation_id', 0, 1);
			$dbFiledData['line_user_id'] = array(':line_user_id', $lineUserId, 0);
			$dbFiledData['line_display_name'] = array(':line_display_name', $lineDisplayName, ($lineDisplayName === null || $lineDisplayName === '') ? 2 : 0);
			$dbFiledData['applicant_name'] = array(':applicant_name', $applicantName, ($applicantName === null || $applicantName === '') ? 2 : 0);
			$dbFiledData['status'] = array(':status', 'registered', 0);
			$dbFiledData['interview_at'] = array(':interview_at', null, 2);
			$dbFiledData['memo'] = array(':memo', null, 2);
			$dbFiledData['created_at'] = array(':created_at', $now, 0);
			$dbFiledData['updated_at'] = array(':updated_at', $now, 0);
			#処理モード：[1].新規追加｜[2].更新｜[3].削除
			$processFlg = 1;
			#実行モード：[1].トランザクション｜[2].即実行
			$exeFlg = 2;
			$dbSuccessFlg = SQL_Process($DB_CONNECT, 'applications', $dbFiledData, array(), $processFlg, $exeFlg);
			if ($dbSuccessFlg == 1) {
				$highlightApplicationId = (int)$DB_CONNECT->lastInsertId();
				$inserted = ($highlightApplicationId > 0);
				break;
			}
		}
		if ($inserted) {
			DB_Transaction(2);
			$makeTag['status'] = 'success';
			$makeTag['title'] = '新規作成';
			$makeTag['msg'] = '空の応募行を作成しました。';
			$makeTag['highlightApplicationId'] = $highlightApplicationId;
		} else {
			DB_Transaction(3);
			$makeTag['status'] = 'error';
			$makeTag['title'] = '新規作成エラー';
			$makeTag['msg'] = '空の応募行の作成に失敗しました。';
		}
	} catch (Throwable $e) {
		DB_Transaction(3);
		if (function_exists('makeLog')) {
			makeLog([
				'pageName' => 'proc_master04_01_01',
				'reason' => 'createDraftApplication failed',
				'errorMessage' => $e->getMessage(),
			]);
		}
		$makeTag['status'] = 'error';
		$makeTag['title'] = '新規作成エラー';
		$makeTag['msg'] = '新規作成に失敗しました。';
	}
}
#表示件数
$displayNumber = isset($_POST['displayNumber']) ? intval($_POST['displayNumber']) : $initialDisplayNumber;
#ページ番号
$pageNumber = isset($_POST['pageNumber']) ? intval($_POST['pageNumber']) : 1;
#-------------#

#更新系アクション（一覧は毎回丸ごと再描画）
$updateActions = ['changeStatus', 'changeJobCategory', 'changeFacility', 'setInterviewAt', 'deleteApplication'];
if (in_array($action, $updateActions, true)) {
	#=============#
	# POSTチェック
	#-------------#
	#応募ID（PK）
	$applicationId = isset($_POST['applicationId']) ? (int)$_POST['applicationId'] : 0;
	#事業所ID（更新対象行の現在値：WHERE に使用）
	$appliedJobsFacId = isset($_POST['facId']) ? (int)$_POST['facId'] : 0;
	#求職者名（メッセージ用）
	$userName = isset($_POST['lineName']) ? (string)$_POST['lineName'] : '';
	#求人カードID
	$jobId = isset($_POST['jobId']) ? (int)$_POST['jobId'] : 0;
	#追加パラメータ
	$changeStatus = isset($_POST['changeStatus']) ? (string)$_POST['changeStatus'] : '';
	$jobCategoryId = isset($_POST['jobCategoryId']) ? trim((string)$_POST['jobCategoryId']) : '';
	$newFacId = isset($_POST['newFacId']) ? (int)$_POST['newFacId'] : 0;
	$interviewAt = isset($_POST['interviewAt']) ? (string)$_POST['interviewAt'] : '';
	#更新対象の特定は application_id を優先（未確定データでも更新できるようにする）
	$usePkUpdate = ($applicationId > 0);
	if ($lineUserId === '' || ($usePkUpdate === false && ($jobId < 1 || $appliedJobsFacId < 1))) {
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = '更新エラー';
		$makeTag['msg'] = '更新に必要な情報が不足しています。';
		echo json_encode($makeTag);
		exit;
	}
	#アクション別バリデーション
	$interviewAtSqlType = 0;
	$newFacilityCorpId = 0;
	switch ($action) {
		case 'changeStatus': {
				$allowedStatuses = [];
				if (isset($applicationStatusMaster) && is_array($applicationStatusMaster)) {
					$allowedStatuses = array_keys($applicationStatusMaster);
				}
				if ($changeStatus === '' || !in_array($changeStatus, $allowedStatuses, true) || $changeStatus === 'friend_only') {
					header('Content-Type: application/json; charset=UTF-8');
					$makeTag['status'] = 'error';
					$makeTag['title'] = '応募状況変更エラー';
					$makeTag['msg'] = '応募状況変更の入力値が不正です。';
					echo json_encode($makeTag);
					exit;
				}
			}
			break;
		case 'changeJobCategory': {
				$allowedJobCategoryIds = [];
				if (is_array($jobCategories)) {
					foreach ($jobCategories as $c) {
						if (isset($c['id'])) $allowedJobCategoryIds[] = (string)$c['id'];
					}
				}
				if ($jobCategoryId === '' || (!empty($allowedJobCategoryIds) && !in_array($jobCategoryId, $allowedJobCategoryIds, true))) {
					header('Content-Type: application/json; charset=UTF-8');
					$makeTag['status'] = 'error';
					$makeTag['title'] = '職種変更エラー';
					$makeTag['msg'] = '職種変更の入力値が不正です。';
					echo json_encode($makeTag);
					exit;
				}
			}
			break;
		case 'changeFacility': {
				$allowedFacilityIds = [];
				if (is_array($facilityList)) {
					foreach ($facilityList as $f) {
						if (isset($f['facility_id'])) $allowedFacilityIds[] = (int)$f['facility_id'];
					}
				}
				if ($newFacId < 1 || (!empty($allowedFacilityIds) && !in_array($newFacId, $allowedFacilityIds, true))) {
					header('Content-Type: application/json; charset=UTF-8');
					$makeTag['status'] = 'error';
					$makeTag['title'] = '応募先変更エラー';
					$makeTag['msg'] = '応募先変更の入力値が不正です。';
					echo json_encode($makeTag);
					exit;
				}
				#facility_idから法人情報を逆引き（DB更新用）
				$facilityData = getFacility_FindById($newFacId);
				$newFacilityCorpId = is_array($facilityData) && isset($facilityData['corporation_id']) ? (int)$facilityData['corporation_id'] : 0;
				if ($newFacilityCorpId < 1) {
					header('Content-Type: application/json; charset=UTF-8');
					$makeTag['status'] = 'error';
					$makeTag['title'] = '応募先変更エラー';
					$makeTag['msg'] = '応募先の法人情報が取得できませんでした。';
					echo json_encode($makeTag);
					exit;
				}
			}
			break;
		case 'setInterviewAt': {
				$interviewAt = trim($interviewAt);
				if ($interviewAt === '') {
					$interviewAtSqlType = 2; #NULL
				} else {
					if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $interviewAt)) {
						header('Content-Type: application/json; charset=UTF-8');
						$makeTag['status'] = 'error';
						$makeTag['title'] = '面接日設定エラー';
						$makeTag['msg'] = '面接日の形式が不正です。';
						echo json_encode($makeTag);
						exit;
					}
					[$y, $m, $d] = array_map('intval', explode('-', $interviewAt));
					if (!checkdate($m, $d, $y)) {
						header('Content-Type: application/json; charset=UTF-8');
						$makeTag['status'] = 'error';
						$makeTag['title'] = '面接日設定エラー';
						$makeTag['msg'] = '面接日が不正です。';
						echo json_encode($makeTag);
						exit;
					}
				}
			}
			break;
		case 'deleteApplication': {
				if ($applicationId < 1 || $lineUserId === '') {
					header('Content-Type: application/json; charset=UTF-8');
					$makeTag['status'] = 'error';
					$makeTag['title'] = '削除エラー';
					$makeTag['msg'] = '削除に必要な情報が不足しています。';
					echo json_encode($makeTag);
					exit;
				}
			}
			break;
		default:
			break;
	}
	try {
		#トランザクション開始
		# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
		$result = DB_Transaction(1);
		if ($result == false) {
			#エラーログ出力
			$data = [
				'pageName' => 'proc_master04_01',
				'reason' => 'トランザクション開始失敗',
			];
			makeLog($data);
			$makeTag['status'] = 'error';
			$makeTag['title'] = '応募状況変更エラー';
			$makeTag['msg'] = 'トランザクション開始に失敗しました。';
			header('Content-Type: application/json');
			echo json_encode($makeTag);
			exit;
		} else {
			#登録用配列：初期化
			$dbFiledData = array();
			#登録情報セット
			switch ($action) {
				case 'changeStatus': {
						$dbFiledData['status'] = array(':status', $changeStatus, 0);
					}
					break;
				case 'changeJobCategory': {
						$dbFiledData['job_category_id'] = array(':job_category_id', $jobCategoryId, 0);
					}
					break;
				case 'changeFacility': {
						$dbFiledData['facility_id'] = array(':facility_id_new', $newFacId, 1);
						$dbFiledData['corporation_id'] = array(':corporation_id', (int)$newFacilityCorpId, 1);
					}
					break;
				case 'setInterviewAt': {
						$dbFiledData['interview_at'] = array(':interview_at', $interviewAt, $interviewAtSqlType);
					}
					break;
				default:
					break;
			}
			if ($action !== 'deleteApplication') {
				$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
			}
			#更新用キー：初期化
			$dbFiledValue = array();
			if ($usePkUpdate) {
				$dbFiledValue['application_id'] = array(':application_id', $applicationId, 1);
				#安全のため応募者IDでも縛る（他ユーザーの行を書き換えない）
				$dbFiledValue['line_user_id'] = array(':line_user_id', $lineUserId, 0);
			} else {
				#互換：旧キー（ブラウザキャッシュ等）
				$dbFiledValue['job_id'] = array(':job_id', $jobId, 1);
				$dbFiledValue['facility_id'] = array(':facility_id', $appliedJobsFacId, 1);
				$dbFiledValue['line_user_id'] = array(':line_user_id', $lineUserId, 0);
			}
			#処理モード：[1].新規追加｜[2].更新｜[3].削除
			$processFlg = ($action === 'deleteApplication') ? 3 : 2;
			#DB更新
			#実行モード：[1].トランザクション｜[2].即実行
			$exeFlg = 2;
			$dbSuccessFlg = SQL_Process($DB_CONNECT, "applications", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
			if ($dbSuccessFlg == 1) {
				#DBコミット
				# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
				DB_Transaction(2);
				$makeTag['status'] = 'success';
				$makeTag['title'] = ($action === 'deleteApplication') ? '削除' : '更新';
				$userNameEsc = htmlspecialchars((string)$userName, ENT_QUOTES, 'UTF-8');
				switch ($action) {
					case 'changeStatus': {
							$makeTag['title'] = '応募状況変更';
							switch ($changeStatus) {
								case 'registered':
									$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">登録中</span>で登録しました。';
									break;
								case 'applied':
									$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">応募中</span>で登録しました。';
									break;
								case 'interview':
									$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">面接中</span>で登録しました。';
									break;
								case 'hired':
									$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">採用</span>で登録しました。';
									break;
								case 'rejected':
									$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">不採用</span>で登録しました。';
									break;
								case 'unresponsive':
									$makeTag['msg'] = $userNameEsc . '様の応募状況を<span style="font-weight:bold;">連絡待ち</span>で登録しました。';
									break;
								default:
									$makeTag['msg'] = $userNameEsc . '様の応募状況を更新しました。';
									break;
							}
						}
						break;
					case 'changeJobCategory':
						$makeTag['title'] = '職種変更';
						$makeTag['msg'] = $userNameEsc . '様の職種を更新しました。';
						break;
					case 'changeFacility':
						$makeTag['title'] = '応募先変更';
						$makeTag['msg'] = $userNameEsc . '様の応募先を更新しました。';
						break;
					case 'setInterviewAt':
						$makeTag['title'] = '面接日設定';
						$makeTag['msg'] = $userNameEsc . '様の面接日を更新しました。';
						break;
					case 'deleteApplication':
						$makeTag['title'] = '削除';
						$makeTag['msg'] = '応募行を削除しました。';
						break;
					default:
						$makeTag['msg'] = $userNameEsc . '様の応募情報を更新しました。';
						break;
				}
			} else {
				#更新失敗：ロールバックしてエラー返却
				DB_Transaction(3);
				$makeTag['status'] = 'error';
				$makeTag['title'] = '更新エラー';
				$makeTag['msg'] = '応募情報の更新に失敗しました。';
			}
		}
	} catch (Exception $e) {
		DB_Transaction(3);
		#エラーログ出力
		$data = [
			'pageName' => 'proc_master04_01',
			'reason' => '応募状況更新失敗',
			'errorMessage' => $e->getMessage(),
		];
		makeLog($data);
		$makeTag['status'] = 'error';
		$makeTag['title'] = '更新エラー';
		$makeTag['msg'] = '更新に失敗しました。';
	}
}
#-------------#
#前回の状態維持
$searchConditionsSessionKey = 'searchConditions_master04_01';
$prevSearchConditions = isset($_SESSION[$searchConditionsSessionKey]) && is_array($_SESSION[$searchConditionsSessionKey]) ? $_SESSION[$searchConditionsSessionKey] : null;
if (!is_array($prevSearchConditions)) {
	$prevSearchConditions = [
		'line_user_id' => $lineUserId,
		'searchMode' => $searchMode,
		'sortTarget' => 'application_at',
		'applicationSortOrder' => 'desc',
		'interviewSortOrder' => 'desc',
		'displayNumber' => $initialDisplayNumber,
		'pageNumber' => 1,
	];
	$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
}
$requiredKeys = ['line_user_id', 'searchMode', 'sortTarget', 'applicationSortOrder', 'interviewSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
	if (!array_key_exists($requiredKey, $prevSearchConditions)) {
		$allowedSearchModes = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
		$fixedSearchMode = isset($prevSearchConditions['searchMode']) ? (string)$prevSearchConditions['searchMode'] : $searchMode;
		if (!isset($allowedSearchModes[$fixedSearchMode])) {
			$fixedSearchMode = 'all';
		}
		$fixedSortTarget = isset($prevSearchConditions['sortTarget']) ? (string)$prevSearchConditions['sortTarget'] : 'application_at';
		if ($fixedSortTarget !== 'application_at' && $fixedSortTarget !== 'interview_at') {
			$fixedSortTarget = 'application_at';
		}
		$fixedApplicationSortOrder = isset($prevSearchConditions['applicationSortOrder']) ? strtolower((string)$prevSearchConditions['applicationSortOrder']) : 'desc';
		if ($fixedApplicationSortOrder !== 'asc' && $fixedApplicationSortOrder !== 'desc') {
			$fixedApplicationSortOrder = 'desc';
		}
		$fixedInterviewSortOrder = isset($prevSearchConditions['interviewSortOrder']) ? strtolower((string)$prevSearchConditions['interviewSortOrder']) : 'desc';
		if ($fixedInterviewSortOrder !== 'asc' && $fixedInterviewSortOrder !== 'desc') {
			$fixedInterviewSortOrder = 'desc';
		}
		$prevSearchConditions = [
			'line_user_id' => isset($prevSearchConditions['line_user_id']) ? (string)$prevSearchConditions['line_user_id'] : '',
			'searchMode' => $fixedSearchMode,
			'sortTarget' => $fixedSortTarget,
			'applicationSortOrder' => $fixedApplicationSortOrder,
			'interviewSortOrder' => $fixedInterviewSortOrder,
			'displayNumber' => isset($prevSearchConditions['displayNumber']) ? (int)$prevSearchConditions['displayNumber'] : $initialDisplayNumber,
			'pageNumber' => isset($prevSearchConditions['pageNumber']) ? (int)$prevSearchConditions['pageNumber'] : 1,
		];
		$_SESSION[$searchConditionsSessionKey] = $prevSearchConditions;
		break;
	}
}
#-------------#
#前回の検索条件
$prevSearchMode = isset($prevSearchConditions['searchMode']) ? (string)$prevSearchConditions['searchMode'] : 'all';
#facility_id が未指定なら前回条件を引き継ぐ（masterは常に0/空でOK）
if ($facId < 1) {
	$facId = isset($prevSearchConditions['facility_id']) ? (int)$prevSearchConditions['facility_id'] : 0;
}
#searchMode が none/空 の場合は前回条件を引き継ぐ（表示件数変更など）
if ($searchMode === '' || $searchMode === 'none') {
	$searchMode = $prevSearchMode;
}
#searchMode の正規化（想定外キーでのundefined index等を防止）
$allowedSearchModes = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
if (!isset($allowedSearchModes[$searchMode])) {
	$searchMode = 'all';
}
$prevSortTarget = $prevSearchConditions['sortTarget'];
$prevApplicationSortOrder = strtolower((string)$prevSearchConditions['applicationSortOrder']);
$prevInterviewSortOrder = strtolower((string)$prevSearchConditions['interviewSortOrder']);
if ($prevApplicationSortOrder !== 'asc' && $prevApplicationSortOrder !== 'desc') {
	$prevApplicationSortOrder = 'desc';
}
if ($prevInterviewSortOrder !== 'asc' && $prevInterviewSortOrder !== 'desc') {
	$prevInterviewSortOrder = 'desc';
}
$sortTarget = $prevSortTarget;
$applicationSortOrder = $prevApplicationSortOrder;
$interviewSortOrder = $prevInterviewSortOrder;
#ソートモード指定があれば上書き
if ($sortMode !== '' && $sortMode !== 'none') {
	switch ($sortMode) {
		#--------------
		# 応募日順にソート
		#--------------
		#応募日の昇順
		case 'sortApplicationsDate_asc': {
				$sortTarget = 'application_at';
				$applicationSortOrder = 'asc';
			}
			break;
		#応募日の降順
		case 'sortApplicationsDate_desc': {
				$sortTarget = 'application_at';
				$applicationSortOrder = 'desc';
			}
			break;
		#----------------
		# 面接日順にソート
		#----------------
		#面接日の昇順
		case 'sortInterviewDate_asc': {
				$sortTarget = 'interview_at';
				$interviewSortOrder = 'asc';
			}
			break;
		#面接日の降順
		case 'sortInterviewDate_desc': {
				$sortTarget = 'interview_at';
				$interviewSortOrder = 'desc';
			}
			break;
		#デフォルト：前回条件
		default:
			$sortTarget = $prevSortTarget;
			$applicationSortOrder = $prevApplicationSortOrder;
			$interviewSortOrder = $prevInterviewSortOrder;
			break;
	}
}
#ソートモードのアクティブ判定（応募日・面接日 両方に付与）
$sortApplicationsAscActive = (strtolower((string)$applicationSortOrder) === 'asc') ? 'is-active' : '';
$sortApplicationsDescActive = (strtolower((string)$applicationSortOrder) === 'asc') ? '' : 'is-active';
$sortInterviewDateAscActive = (strtolower((string)$interviewSortOrder) === 'asc') ? 'is-active' : '';
$sortInterviewDateDescActive = (strtolower((string)$interviewSortOrder) === 'asc') ? '' : 'is-active';
#表示側へ渡すソートモード文字列（主ソート：ページ移動等で維持する）
$sortModeValue = 'none';
if ($sortTarget === 'application_at') {
	$sortModeValue = 'sortApplicationsDate_' . strtolower((string)$applicationSortOrder);
} elseif ($sortTarget === 'interview_at') {
	$sortModeValue = 'sortInterviewDate_' . strtolower((string)$interviewSortOrder);
}
#-------------#
#検索条件配列生成してSESSIONに保存
switch ($action) {
	#条件で検索
	case 'search':
	case 'changeStatus':
	case 'changeJobCategory':
	case 'changeFacility':
	case 'setInterviewAt':
	case 'deleteApplication':
	case 'updateApplicantProfile':
	case 'createDraftApplication': {
			#検索条件が変わる操作は原則1ページ目に戻す
			if (!isset($_POST['pageNumber'])) {
				$pageNumber = 1;
			}
			$searchConditions = [
				'line_user_id' => $lineUserId,
				'searchMode' => $searchMode,
				'sortTarget' => $sortTarget,
				'applicationSortOrder' => $applicationSortOrder,
				'interviewSortOrder' => $interviewSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#ページ移動
	case 'page': {
			$searchConditions = [
				'line_user_id' => $lineUserId,
				'searchMode' => $searchMode,
				'sortTarget' => $sortTarget,
				'applicationSortOrder' => $applicationSortOrder,
				'interviewSortOrder' => $interviewSortOrder,
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
	#デフォルト：全てクリア
	default: {
			$searchConditions = [
				'line_user_id' => $lineUserId,
				'searchMode' => 'all',
				'sortTarget' => 'application_at',
				'applicationSortOrder' => 'desc',
				'interviewSortOrder' => 'desc',
				'displayNumber' => $displayNumber,
				'pageNumber' => $pageNumber,
			];
		}
		break;
}
#SESSIONに保存
$_SESSION[$searchConditionsSessionKey] = $searchConditions;
#ページ番号・表示件数
$searchConditions = $_SESSION[$searchConditionsSessionKey];
$searchMode = isset($searchConditions['searchMode']) && (string)$searchConditions['searchMode'] !== '' ? (string)$searchConditions['searchMode'] : 'all';
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
if ($displayNumber < 1) {
	$displayNumber = $initialDisplayNumber;
}
#応募人数取得
$applicationCounts = [];
$searchModeOptions = array_merge(['all' => 'すべて'], (array)$applicationMasterSetting);
foreach ($searchModeOptions as $statusKey => $status) {
	$tmpConditions = $searchConditions;
	$tmpConditions['searchMode'] = (string)$statusKey;
	$applicationCounts[$statusKey] = (int)searchApplicationJobCount($tmpConditions);
}
#キーが無い場合も想定して0で補完
foreach (array_keys($searchModeOptions) as $statusKey) {
	if (array_key_exists($statusKey, $applicationCounts) === false) {
		$applicationCounts[$statusKey] = 0;
	}
}
#総件数（ページャー用）
$totalApplicationCount = isset($applicationCounts[$searchMode]) ? (int)$applicationCounts[$searchMode] : 0;
$totalPages = (int)ceil($totalApplicationCount / $displayNumber);
if ($totalPages < 1) {
	$totalPages = 1;
}
if ($pageNumber < 1) {
	$pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
	$pageNumber = $totalPages;
}
#応募者一覧取得（LIMIT/OFFSET）
$applicationJobList = getApplicationJobList($searchConditions, $pageNumber, $displayNumber);
#新規作成直後：対象行を必ず先頭に寄せる（面接日ソート時でも最上段に表示）
if ($highlightApplicationId > 0 && is_array($applicationJobList)) {
	$targetIdx = null;
	foreach ($applicationJobList as $idx => $row) {
		if ((int)($row['application_id'] ?? 0) === (int)$highlightApplicationId) {
			$targetIdx = $idx;
			break;
		}
	}
	if ($targetIdx !== null) {
		$targetRow = $applicationJobList[$targetIdx];
		array_splice($applicationJobList, $targetIdx, 1);
		array_unshift($applicationJobList, $targetRow);
	} else {
		try {
			#現在の検索条件で落ちた場合でも、作成した行は先頭に表示したい
			$strSQL = "SELECT application_id, job_id, job_category_id, facility_id, corporation_id, line_user_id, line_display_name, applicant_name, status, interview_at, created_at, updated_at FROM applications WHERE application_id = :application_id AND line_user_id = :line_user_id LIMIT 1";
			$stmt = $DB_CONNECT->prepare($strSQL);
			$stmt->bindValue(':application_id', (int)$highlightApplicationId, PDO::PARAM_INT);
			$stmt->bindValue(':line_user_id', (string)$lineUserId, PDO::PARAM_STR);
			$stmt->execute();
			$targetRow = $stmt->fetch(PDO::FETCH_ASSOC);
			$stmt->closeCursor();
			if (is_array($targetRow)) {
				array_unshift($applicationJobList, $targetRow);
				#表示件数を超えたら末尾を落とす（ページング整合）
				if (count($applicationJobList) > (int)$displayNumber) {
					$applicationJobList = array_slice($applicationJobList, 0, (int)$displayNumber);
				}
			}
		} catch (Throwable $e) {
			#表示優先：ここで失敗しても一覧生成は継続
		}
	}
}
#該当件数（表示用：総件数）
$applicationCount = $totalApplicationCount;

#inline JS 用（searchConditions() 引数）
$searchModeJs = json_encode((string)$searchMode, $jsonHex);
$sortModeValueJs = json_encode((string)$sortModeValue, $jsonHex);
$sortModeValueEsc = htmlspecialchars((string)$sortModeValue, ENT_QUOTES, 'UTF-8');
$searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
$sortModeValueJsAttr = htmlspecialchars((string)$sortModeValueJs, ENT_QUOTES, 'UTF-8');

#***** タグ生成開始 *****#
$makeTag['tag'] .= <<<HTML
          <article class="block-applicant-list" data-current-sort-mode="{$sortModeValueEsc}">
            <div class="box-head" style="z-index:9999;">
              <p class="announce-results">条件に<span>{$applicationCount}件</span>が該当</p>
              <div class="list-display" data-selectbox>

HTML;
#表示件数格納用変数を初期化
$currentDisplayNumber = isset($displayNumber) ? $displayNumber : $initialDisplayNumber;
#表示数が選択されている場合
foreach ($displayNumberList as $displayNumber) {
	if ($displayNumber === (int)$searchConditions['displayNumber']) {
		$currentDisplayNumber = $displayNumber;
		break;
	}
}
$makeTag['tag'] .= <<<HTML
                <button type="button" class="selectbox__head" aria-expanded="false">
                  <input type="hidden" name="displayNumber" value="{$currentDisplayNumber}" data-selectbox-hidden>
                  <span class="selectbox__value" data-selectbox-value>{$currentDisplayNumber}</span>
                </button>
                <div class="list-wrapper">
                  <ul class="selectbox__panel">

HTML;
#表示件数選択リストループで差し込む
foreach ($displayNumberList as $number) {
	$checked = ($number === (int)$searchConditions['displayNumber']) ? ' checked' : '';
	$makeTag['tag'] .= <<<HTML
                    <li>
                      <input type="radio" name="displayNumber" id="display{$number}" value="{$number}" {$checked} onchange="searchConditions('search','none',{$sortModeValueJsAttr})">
                      <label for="display{$number}">{$number}</label>
                    </li>

HTML;
}
$makeTag['tag'] .= <<<HTML
                  </ul>
                </div>
              </div>
              <button type="button" class="item-register"><span>新規</span></button>
            </div>
            <ul class="list-personal-results">
              <li>
                <div>
                  応募日
                  <span class="wrap-sort-btn">
                    <button type="button" class="arrow-top {$sortApplicationsAscActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortApplicationsDate_asc')"></button>
                    <button type="button" class="arrow-bottom {$sortApplicationsDescActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortApplicationsDate_desc')"></button>
                  </span>
                </div>
                <div>
                  面接日
                  <span class="wrap-sort-btn">
                    <button type="button" class="arrow-top {$sortInterviewDateAscActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortInterviewDate_asc')"></button>
                    <button type="button" class="arrow-bottom {$sortInterviewDateDescActive}" onclick="searchConditions('search',{$searchModeJsAttr},'sortInterviewDate_desc')"></button>
                  </span>
                </div>
                <div>職種</div>
                <div>応募先</div>
                <div>応募状況</div>
                <div>削除</div>
              </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($applicationJobList) && count($applicationJobList) > 0) {
	$zIndexNo = count($applicationJobList);
	foreach ($applicationJobList as $applicationKey => $application) {
		#Liのz-index設定
		$zIndexStyle = 'style="z-index:' . ($zIndexNo - $applicationKey) . ';"';
		#事業所ID
		$appliedJobsFacId = isset($application['facility_id']) ? (int)$application['facility_id'] : 0;
		#事業所名
		$facilityName = '';
		if (isset($application['facility_id']) && $application['facility_id'] !== '' && $application['facility_id'] !== null) {
			$facilityData = getFacility_FindById($application['facility_id']);
			$facilityName = $facilityData['name'] ?? '';
		}
		$facilityNameEsc = htmlspecialchars((string)$facilityName, ENT_QUOTES, 'UTF-8');
		#ライン表示名
		$lineDisplayName = isset($application['line_display_name']) ? (string)$application['line_display_name'] : '';
		$lineDisplayNameEsc = htmlspecialchars($lineDisplayName, ENT_QUOTES, 'UTF-8');
		#名前
		$name = isset($application['applicant_name']) ? (string)$application['applicant_name'] : '';
		$nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
		#ステータス変更用に名前セット（JS側で使用：$nameがあれば優先）
		$sendStatusChangeName = $name !== '' ? $name : $lineDisplayName;
		#応募日
		$appliedDate = !empty($application['created_at']) ? date('Y/m/d', strtotime($application['created_at'])) : '';
		$appliedDateEsc = htmlspecialchars((string)$appliedDate, ENT_QUOTES, 'UTF-8');
		#面接日
		$interviewAtDateValue = !empty($application['interview_at']) ? date('Y-m-d', strtotime($application['interview_at'])) : '';
		$interviewAtDateValueEsc = htmlspecialchars((string)$interviewAtDateValue, ENT_QUOTES, 'UTF-8');
		#応募ステータス
		$db_applicationStatus = $application['status'] ?? '';
		#ステータス「friend_only」は登録中に変換
		if ($db_applicationStatus === 'friend_only') {
			$db_applicationStatus = 'registered';
		}
		#inline JS用エスケープ
		$jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
		$lineUserIdJs = json_encode((string)($application['line_user_id'] ?? ''), $jsonHex);
		$facilityNameJs = json_encode((string)$facilityName, $jsonHex);
		$sendStatusChangeNameJs = json_encode((string)$sendStatusChangeName, $jsonHex);
		$searchModeJs = json_encode((string)($searchConditions['searchMode'] ?? ''), $jsonHex);
		$sortModeJs = json_encode((string)$sortMode, $jsonHex);
		$lineUserIdJsAttr = htmlspecialchars((string)$lineUserIdJs, ENT_QUOTES, 'UTF-8');
		$facilityNameJsAttr = htmlspecialchars((string)$facilityNameJs, ENT_QUOTES, 'UTF-8');
		$sendStatusChangeNameJsAttr = htmlspecialchars((string)$sendStatusChangeNameJs, ENT_QUOTES, 'UTF-8');
		$searchModeJsAttr = htmlspecialchars((string)$searchModeJs, ENT_QUOTES, 'UTF-8');
		$sortModeJsAttr = htmlspecialchars((string)$sortModeJs, ENT_QUOTES, 'UTF-8');
		#更新（onchange）用：キー
		$applicationIdInt = (int)($application['application_id'] ?? 0);
		$appliedJobsFacIdInt = (int)$appliedJobsFacId;
		$jobIdInt = (int)($application['job_id'] ?? 0);
		$makeTag['tag'] .= <<<HTML
              <!-- NOTE インラインでz-indexを付与 -->
							<li {$zIndexStyle} data-application-id="{$applicationIdInt}">
                <div class="item-apply-date">{$appliedDateEsc}</div>
                <form class="item-interview-date">
					<input type="date" name="interview_at{$applicationKey}" value="{$interviewAtDateValueEsc}" onfocus="this.dataset.prevValue=this.value;" onchange="confirmSetInterviewAt(this, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});" >
                </form>
                <form class="item-job-type">
                  <div class="select-job-type" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
		#職種が選択されていたら
		if (isset($application['job_category_id']) && $application['job_category_id'] != '') {
			#選択中のラベル取得
			foreach ($jobCategories as $jobCategory) {
				if ($application['job_category_id'] == $jobCategory['id']) {
					$makeTag['tag'] .= <<<HTML
                      <input type="hidden" name="job_category{$applicationKey}" value="{$jobCategory['id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$jobCategory['name']}</span>

HTML;
					break;
				}
			}
		} else {
			$makeTag['tag'] .= <<<HTML
                      <input type="hidden" name="job_category{$applicationKey}" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>---</span>

HTML;
		}
		$makeTag['tag'] .= <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
		#表示可能リストあればループ処理
		if (isset($jobCategories) && is_array($jobCategories) && count($jobCategories) > 0) {
			foreach ($jobCategories as $jobCategoryKey => $jobCategory) {
				$jobCategoryNameJs = json_encode((string)($jobCategory['name'] ?? ''), $jsonHex);
				$jobCategoryNameJsAttr = htmlspecialchars((string)$jobCategoryNameJs, ENT_QUOTES, 'UTF-8');
				#checked判定
				$checked = ($application['job_category_id'] == $jobCategory['id']) ? 'checked' : '';
				$makeTag['tag'] .= <<<HTML
                        <li>
                          <input type="radio" name="job_category{$applicationKey}" value="{$jobCategory['id']}" id="job{$applicationKey}_{$jobCategoryKey}" {$checked} onclick="return confirmChangeJobCategory(event, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$facilityNameJsAttr}, {$jobIdInt}, this.value, {$jobCategoryNameJsAttr}, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});">
                          <label for="job{$applicationKey}_{$jobCategoryKey}">{$jobCategory['name']}</label>
                        </li>

HTML;
			}
		} else {
			$makeTag['tag'] .= <<<HTML
                        <li>
                          <input type="radio" name="job_category{$applicationKey}" value="1" id="job{$applicationKey}_01">
                          <label for="job{$applicationKey}_01">募集職種が未設定です</label>
                        </li>

HTML;
		}
		$makeTag['tag'] .= <<<HTML
                      </ul>
                    </div>
                  </div>
                </form>
                <form class="item-destination">
                  <div class="select-destination" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
		#応募先が選択されていたら
		if (isset($appliedJobsFacId) && $appliedJobsFacId != 0) {
			#選択中のラベル取得
			foreach ($facilityList as $facility) {
				if ($appliedJobsFacId == $facility['facility_id']) {
					$makeTag['tag'] .= <<<HTML
                      <input type="hidden" name="facility{$applicationKey}" value="{$facility['facility_id']}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$facility['name']}</span>

HTML;
					break;
				}
			}
		} else {
			$makeTag['tag'] .= <<<HTML
                      <input type="hidden" name="facility{$applicationKey}" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>

HTML;
		}
		$makeTag['tag'] .= <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
		#表示可能リストあればループ処理
		if (isset($facilityList) && is_array($facilityList) && count($facilityList) > 0) {
			foreach ($facilityList as $facilityKey => $facility) {
				$destinationNameJs = json_encode((string)($facility['name'] ?? ''), $jsonHex);
				$destinationNameJsAttr = htmlspecialchars((string)$destinationNameJs, ENT_QUOTES, 'UTF-8');
				#checked判定
				$checked = ($appliedJobsFacId == $facility['facility_id']) ? 'checked' : '';
				$makeTag['tag'] .= <<<HTML
                        <li>
                          <input type="radio" name="facility{$applicationKey}" value="{$facility['facility_id']}" id="destination{$applicationKey}_{$facilityKey}" {$checked} onclick="return confirmChangeDestination(event, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$jobIdInt}, this.value, {$destinationNameJsAttr}, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});">
                          <label for="destination{$applicationKey}_{$facilityKey}">{$facility['name']}</label>
                        </li>

HTML;
			}
		} else {
			$makeTag['tag'] .= <<<HTML
                        <li>
                          <input type="radio" name="facility{$applicationKey}" value="1" id="destination{$applicationKey}_01">
                          <label for="destination{$applicationKey}_01">応募先が未設定です</label>
                        </li>

HTML;
		}
		$makeTag['tag'] .= <<<HTML
                      </ul>
                    </div>
                  </div>
                </form>
                <form class="item-apply-status">
                  <!--NOTE 連番注意 list01-status- -->
                  <div class="apply-status" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">

HTML;
		#応募状況ステータスが選択されていたら
		if (isset($db_applicationStatus) && $db_applicationStatus != '') {
			#選択中のラベル取得
			foreach ($applicationStatusMaster as $appStatusKey => $appStatus) {
				if ($db_applicationStatus == $appStatusKey) {
					$appStatusKeyEsc = htmlspecialchars((string)$appStatusKey, ENT_QUOTES, 'UTF-8');
					$appStatusEsc = htmlspecialchars((string)$appStatus, ENT_QUOTES, 'UTF-8');
					$makeTag['tag'] .= <<<HTML
                      <input type="hidden" name="application_status{$applicationKey}" value="{$appStatusKeyEsc}" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>{$appStatusEsc}</span>
                      <i></i>

HTML;
				}
			}
		} else {
			$makeTag['tag'] .= <<<HTML
                      <input type="hidden" name="application_status{$applicationKey}" value="" data-selectbox-hidden>
                      <span class="selectbox__value" data-selectbox-value>---</span>
                      <i></i>

HTML;
		}
		$makeTag['tag'] .= <<<HTML
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">

HTML;
		#表示可能リストあればループ処理
		if (isset($applicationStatusMaster) && is_array($applicationStatusMaster) && count($applicationStatusMaster) > 0) {
			foreach ($applicationStatusMaster as $appStatusKey => $appStatus) {
				#checked判定
				$checked = ($db_applicationStatus == $appStatusKey) ? 'checked' : '';
				#zIndex設定
				$zIndexStyleStatus = '';
				if ($appStatusKey === 'registered') {
					$zIndexStyleStatus = 'style="z-index:2;"';
				}
				$appStatusKeyEsc = htmlspecialchars((string)$appStatusKey, ENT_QUOTES, 'UTF-8');
				$appStatusEsc = htmlspecialchars((string)$appStatus, ENT_QUOTES, 'UTF-8');
				$appliedJobsFacIdInt = (int)$appliedJobsFacId;
				$jobIdInt = (int)($application['job_id'] ?? 0);
				$makeTag['tag'] .= <<<HTML
                        <!-- NOTE インラインでz-indexを付与 -->
                        <li {$zIndexStyleStatus}>
                          <input type="radio" name="application_status{$applicationKey}" value="{$appStatusKeyEsc}" id="application_status{$applicationKey}_{$appStatusKeyEsc}" {$checked} onclick="return confirmChangeStatus(event, {$appliedJobsFacIdInt}, {$lineUserIdJsAttr}, {$sendStatusChangeNameJsAttr}, {$facilityNameJsAttr}, {$jobIdInt}, this.value, {$searchModeJsAttr}, {$sortModeJsAttr}, {$applicationIdInt});">
                          <label for="application_status{$applicationKey}_{$appStatusKeyEsc}" class="status-{$appStatusKeyEsc}">{$appStatusEsc}</label>
                        </li>

HTML;
			}
		} else {
			$makeTag['tag'] .= <<<HTML
                        <!-- NOTE インラインでz-indexを付与 -->
                        <li style="z-index: 2">
                          <input type="radio" name="application_status{$applicationKey}" value="1" id="application_status{$applicationKey}-none" checked>
                          <label for="application_status{$applicationKey}-none" class="status-registered">応募状況ステータスが未設定です</label>
                        </li>

HTML;
		}
		$makeTag['tag'] .= <<<HTML
                      </ul>
                    </div>
                  </div>
                </form>
                <div class="item-delate"><button type="button"></button></div>
              </li>

HTML;
	}
} else {
	$makeTag['tag'] .= <<<HTML
              <li class="no-data" style="display:flex;justify-content:center;align-items:center;padding:2em 0;">
                <div>該当するデータが存在しません。</div>
              </li>

HTML;
}
$makeTag['tag'] .= <<<HTML
            </ul>

HTML;
$makeTag['tag'] .= makePagerBoxTag((int)$pageNumber, (int)$totalPages, $pagerDisplayMax, 'movePage');
$makeTag['tag'] .= <<<HTML
        </article>

HTML;
#-------------------------------------------#
#json 応答
#ハイライト対象があれば返却（postAndRedraw側で枠線ハイライト）
if ($highlightApplicationId > 0 && !isset($makeTag['highlightApplicationId'])) {
	$makeTag['highlightApplicationId'] = $highlightApplicationId;
}
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
