<?php
/*
 * [rw-master/check.php]
 *  - 管理画面 -
 *  ログインチェック
 *
 * [初版]
 *  2024.8.27
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ログイン *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_login.php';

#=============#
# POSTチェック
#-------------#
#POST以外は不正リクエストとして拒否
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("Location: ./index.php");
	exit;
}
#メールアドレス
$userEmail = isset($_POST['userEmail']) ? $_POST['userEmail'] : null;
#パスワード
$userPassword = isset($_POST['userPassword']) ? $_POST['userPassword'] : null;

#***** セッション宣言 *****#
session_cache_limiter('private,must-revalidate');
#セッション名設定
session_name('RW_MASTER_SESSID');
#セッションCookie設定
session_set_cookie_params([
	'lifetime' => 0,                #ブラウザ終了まで有効
	'path' => '/',                  #公開パス配下のすべてでCookieを共有
	'domain' => '',                 #同一ホストに限定（空指定でOK）
	'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
	'httponly' => true,             #JavaScriptからCookieアクセスを禁止（推奨）
	'samesite' => 'Lax'             #クロスサイト時のCookie送信制限（推奨）
]);
session_start();

#ログインフォームで共通利用するエラーメッセージ
$loginErrorMessage = "メールアドレスとパスワードを正しく入力してください";
$_SESSION["login_err"] = "";

#入力値を無害化し、後段の検証を容易にする
$loginEmail = isset($userEmail) ? convertData((string)$userEmail) : '';
$loginPassword = isset($userPassword) ? (string)$userPassword : '';

#メールアドレス／パスワードどちらかでも欠けていれば即エラー
if ($loginEmail === '' || $loginPassword === '') {
	$_SESSION["login_err"] = $loginErrorMessage;
	header("Location: ./index.php?loginERR=1");
	exit;
}

#オペレーター種別かつ施設ひも付きが無いことを確認
$loginData = accounts_Login($loginEmail);
$facilityValue = $loginData['facility_id'] ?? null;
$isOperator = $loginData && isset($loginData['account_type']) && $loginData['account_type'] === 'operator';
$hasNoFacilityBinding = $loginData && $facilityValue === null;
if (!$loginData || !$isOperator || !$hasNoFacilityBinding || !password_verify($loginPassword, $loginData['password_hash'])) {
	#管理者アカウントじゃない場合や認証失敗
	if (!$loginData) {
		$loginErrorMessage = "入力されたメールアドレスは登録されていません。";
	} elseif (!$isOperator || !$hasNoFacilityBinding) {
		$loginErrorMessage = "管理者アカウントでログインしてください。";
	} else {
		$loginErrorMessage = "パスワードが正しくありません。";
	}
	$_SESSION["login_err"] = $loginErrorMessage;
	header("Location: ./index.php?loginERR=1");
	exit;
}

#既存セッションがある場合は同一アカウントか判定
if (!empty($_SESSION['master_login']['status'])) {
	$currentAccountId = $_SESSION['master_login']['account_id'] ?? null;
	if ($currentAccountId === (int)$loginData['account_id']) {
		header("Location: ./master01_01.php");
	} else {
		$_SESSION["login_err"] = "既にログイン中のアカウントがあります。ログアウト後に再度お試しください";
		header("Location: ./index.php?loginERR=1");
	}
	exit;
}

#本人確認が取れたので最終ログイン日時を刻む
accounts_UpdateLastLoginAt($loginData['account_id']);

#セッション固定攻撃を防ぐためIDを再発行
session_regenerate_id(true);

#画面遷移後でも最低限のアカウント情報だけ参照できるよう格納
$_SESSION['master_login'] = [];
$_SESSION['master_login']['account_id'] = (int)$loginData['account_id'];
$_SESSION['master_login']['account_type'] = $loginData['account_type'];
$_SESSION['master_login']['facility_id'] = $facilityValue;
$_SESSION['master_login']['status'] = 1;
$_SESSION['login_err'] = '';
header("Location: ./master01_01.php");
exit;
