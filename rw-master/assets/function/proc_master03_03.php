<?php
/*
 * [rw-master/assets/function/proc_master03_03.php]
 *  - 管理画面 -
 *  事業所パスワード設定・変更処理
 *
 * [初版]
 *  2026.1.20
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#アカウント情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_account.php';
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array();
$makeTag['tag'] = '';
$makeTag['status'] = '';
$makeTag['title'] = '';
$makeTag['msg'] = '';

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
		header('Content-Type: application/json; charset=UTF-8');
		$makeTag['status'] = 'error';
		$makeTag['title'] = 'セッションエラー';
		$makeTag['msg'] = 'セッションが切れました。ページを再読み込みしてください。';
		$makeTag['noUpDateKey'] = $currentNoUpDateKey;
		echo json_encode($makeTag);
		exit;
	}
}
$makeTag['noUpDateKey'] = ($currentNoUpDateKey !== '' ? $currentNoUpDateKey : $noUpDateKey);
#-------------#
#新規／編集
$method = isset($_POST['method']) ? $_POST['method'] : null;
#事業所ID
$facId = isset($_POST['facId']) ? $_POST['facId'] : null;
#現在のパスワード
#$currentPassword = isset($_POST['currentPassword']) ? $_POST['currentPassword'] : null;
#新パスワード
$newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : null;
#新パスワード（確認用）
$confirmNewPassword = isset($_POST['confirmNewPassword']) ? $_POST['confirmNewPassword'] : null;
#-------------#
#入力バリデーション（サーバ側）
if ($method !== 'new' && $method !== 'edit') {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '処理モードが不正です。ページを再読み込みしてください。';
	echo json_encode($makeTag);
	exit;
}
if ($facId === null || $facId === '') {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '事業所IDが不正です。ページを再読み込みしてください。';
	echo json_encode($makeTag);
	exit;
}
if ($newPassword === null || $newPassword === '' || $confirmNewPassword === null || $confirmNewPassword === '') {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '新しいパスワードを入力してください。';
	echo json_encode($makeTag);
	exit;
}
if ($newPassword !== $confirmNewPassword) {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '新しいパスワードと確認用パスワードが一致しません。';
	echo json_encode($makeTag);
	exit;
}
#if ($method === 'edit' && ($currentPassword === null || $currentPassword === '')) {
#	$makeTag['status'] = 'error';
#	$makeTag['title'] = 'パスワード設定';
#	$makeTag['msg'] = '現在のパスワードを入力してください。';
#	echo json_encode($makeTag);
#	exit;
#}
#-------------#
#事業所IDがあれば事業所情報取得
$accountData = null;
if ($facId !== null) {
	$facilityData = getFacility_FindById($facId);
} else {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '事業所情報の取得に失敗しました。再度やり直してください。';
	echo json_encode($makeTag);
	exit;
}
#施設データ取得確認
if (!is_array($facilityData) || !isset($facilityData['email']) || $facilityData['email'] === '') {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '事業所情報の取得に失敗しました。再度やり直してください。';
	echo json_encode($makeTag);
	exit;
}
#メールアドレスをキーにアカウント情報を取得
if ($method === 'new') {
	#初回設定（待機アカウントのみ）
	$accountData = accounts_Waiting_FindByEmail($facilityData['email']);
} else {
	#変更（通常アカウント）
	$accountData = accounts_FindByEmail($facilityData['email']);
}
#データ取得エラー
if (!is_array($accountData) || count($accountData) === 0) {
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'パスワード設定';
	$makeTag['msg'] = '事業所アカウント情報の取得に失敗しました。再度やり直してください。';
	echo json_encode($makeTag);
	exit;
}
#編集モードの場合は現在のパスワード照合
#if ($method === 'edit') {
#	if (!isset($accountData['password_hash']) || $accountData['password_hash'] === '' || !password_verify($currentPassword, $accountData['password_hash'])) {
#		$makeTag['status'] = 'error';
#		$makeTag['title'] = 'パスワード設定';
#		$makeTag['msg'] = '現在のパスワードが正しくありません。再度やり直してください。';
#		echo json_encode($makeTag);
#		exit;
#	}
#}

#==================#
# パスワード設定開始
#------------------#
try {
	#トランザクション開始
	# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
	$result = DB_Transaction(1);
	if ($result == false) {
		#エラーログ出力
		$data = [
			'pageName' => 'proc_master03_03',
			'reason' => 'トランザクション開始失敗',
		];
		makeLog($data);
	} else {
		#パスワードハッシュ生成
		$newPwHash = password_hash($newPassword, PASSWORD_BCRYPT);
		#登録用配列：初期化
		$dbFiledData = array();
		#登録情報セット
		$dbFiledData['password_hash'] = array(':password_hash', $newPwHash, 0);
		#初回設定の場合は有効化
		if ($method === 'new') {
			$dbFiledData['is_active'] = array(':is_active', 1, 1);
		}
		#更新用キー：初期化
		$dbFiledValue = array();
		$dbFiledValue['account_id'] = array(':account_id', $accountData['account_id'], 1);
		#処理モード：[1].新規追加｜[2].更新｜[3].削除
		$processFlg = 2;
		#実行モード：[1].トランザクション｜[2].即実行
		$exeFlg = 2;
		$dbSuccessFlg = SQL_Process($DB_CONNECT, "accounts", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
		if ($dbSuccessFlg == 1) {
			#パスワード設定完了メール送信
			$resultSendMail = sendMail_Facility_PasswordSetComplete(
				$facilityData['email'],
				$facilityData['name'],
				$newPassword,
				[
					'facility_code' => isset($facilityData['facility_code']) ? (string)$facilityData['facility_code'] : '',
					'facility_name' => (string)$facilityData['name'],
					'facility_email' => (string)$facilityData['email'],
				]
			);
			#メール送信まで完了したらDB更新
			if ($resultSendMail == false) {
				#エラーログ出力
				$data = [
					'pageName' => 'proc_master03_03',
					'reason' => 'パスワード設定完了メール送信失敗',
					'toEmail' => $facilityData['email'],
				];
				makeLog($data);
				#ロールバック実行
				DB_Transaction(3);
				#エラー応答
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'パスワード設定';
				$makeTag['msg'] = 'パスワード設定完了メールの送信に失敗しました。再度やり直してください。';
				echo json_encode($makeTag);
				exit;
			}
			#コミット実行
			DB_Transaction(2);
			$makeTag['status'] = 'success';
			$makeTag['title'] = 'パスワード設定';
			$makeTag['msg'] = '登録が完了しました。';
			echo json_encode($makeTag);
			exit;
		} else {
			#ロールバック実行
			DB_Transaction(3);
			#エラーログ出力
			$data = [
				'pageName' => 'proc_master03_03',
				'reason' => 'パスワード更新失敗',
			];
			makeLog($data);
			$makeTag['status'] = 'error';
			$makeTag['title'] = 'パスワード設定';
			$makeTag['msg'] = 'パスワードの更新に失敗しました。再度やり直してください。';
			echo json_encode($makeTag);
			exit;
		}
	}
} catch (Exception $e) {
	#エラーログ出力
	$data = [
		'pageName' => 'proc_master03_03',
		'reason' => 'トランザクション開始失敗',
		'errorMessage' => $e->getMessage(),
	];
	makeLog($data);
	$makeTag['status'] = 'error';
}
#-------------------------------------------#
#パスワード設定完了メール送信
function sendMail_Facility_PasswordSetComplete($toEmail, $toName, $newPassword, array $context = [])
{
	global $CMS_PANEL_URL, $DEFINE_NO_REPLY, $DEFINE_MAIL_SENDER_NAME, $sendAddressList;
	global $DEFINE_MASTER_NOTIFY_EMAIL, $DEFINE_MASTER_NOTIFY_NAME;
	#メールタイトル
	$mailTitle = '【RITA】パスワード設定完了のお知らせ';
	#メール本文
	$mailBody = <<<EOD
{$toName} 様

いつもRITAをご利用いただき、誠にありがとうございます。
このたび、事業所アカウントのパスワード設定が完了いたしましたのでお知らせいたします。
下記の内容をご確認のうえ、ログインをお願いいたします。
─────────────────────────────
【ログイン情報】
■ログインURL
{$CMS_PANEL_URL}/rw-client/

ＩＤ(メールアドレス)：{$toEmail}
パスワード：{$newPassword}

─────────────────────────────
■RITAサポートセンター
E-mail：info@a-fact.co.jp
─────────────────────────────
EOD;
	#-------------------------------------------
	# 事業所向け送信（従来本文のまま）
	#  - 失敗したら false を返す（既存挙動）
	#-------------------------------------------
	$resultFacility = sendMail_Common($toEmail, $toName, $mailTitle, $mailBody, $DEFINE_NO_REPLY, $DEFINE_MAIL_SENDER_NAME, $sendAddressList);
	if ($resultFacility == false) {
		return false;
	}
	#-------------------------------------------
	# マスター通知（パスワードは含めない）
	#  - 事業所送信と併用時は「ベストエフォート」
	#-------------------------------------------
	$masterEmail = isset($DEFINE_MASTER_NOTIFY_EMAIL) ? trim((string)$DEFINE_MASTER_NOTIFY_EMAIL) : '';
	$masterName = isset($DEFINE_MASTER_NOTIFY_NAME) ? (string)$DEFINE_MASTER_NOTIFY_NAME : 'マスター';
	if ($masterEmail === '') {
		return true;
	}
	#メール本文生成
	$facilityId = null;
	if (isset($context['facility_code']) && (string)$context['facility_code'] !== '') {
		$facilityId = (string)$context['facility_code'];
	}
	$now = date('Y-m-d H:i:s');
	$notifyTitle = '【RITA】マスター通知：事業所パスワード設定完了';
	$notifyBody = "事業所アカウントのパスワード設定が完了しました。\n"
		. "\n"
		. "日時：{$now}\n"
		. ($facilityId !== null ? "事業所ID：{$facilityId}\n" : '')
		. "事業所名：{$toName}\n"
		. "ＩＤ(メールアドレス)：{$toEmail}\n"
		. "パスワード：{$newPassword}\n";
	#メール送信
	$resultMaster = sendMail_Common($masterEmail, $masterName, $notifyTitle, $notifyBody, $DEFINE_NO_REPLY, $DEFINE_MAIL_SENDER_NAME, []);
	if ($resultMaster == false) {
		makeLog([
			'pageName' => 'proc_master03_03',
			'reason' => 'マスター通知メール送信失敗（パスワード設定完了）',
			'toEmail' => $masterEmail,
			'facilityEmail' => $toEmail,
		]);
	}
	#応答
	return true;
}
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
