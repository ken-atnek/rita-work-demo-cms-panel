<?php
/*
 * [rw-client/index.php]
 *  - 【事業所】管理画面 -
 *  ログインページ：パスワード再設定
 *
 * [初版]
 *  2026.1.22
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/../../cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/client/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#アカウント情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_account.php';

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array();
$makeTag['tag'] = '';
$makeTag['status'] = '';
$makeTag['msg'] = '';

#=============#
# POSTチェック
#-------------#
#モード
$mode = isset($_POST['mode']) ? $_POST['mode'] : null;

#======================#
# モード毎に処理振り分け
#----------------------#
switch ($mode) {
	#***** パスワード再設定フォーム *****#
	case 'resetPwForm': {
			#***** タグ生成開始 *****#
			$makeTag['tag'] .= <<<HTML
        <!-- パスワードリセットフォーム -->
        <form name="resetForm" method="post" class="reset-password">
          <h2>パスワードの再設定</h2>
          <p>
            ご登録のメールアドレスを入力してください。<br>パスワード再設定用のURLをお送りします。
          </p>
          <span class="title">メールアドレス</span>
          <input type="text" name="userEmail" inputmode="email">
          <input type="hidden" name="mode" value="sendResetPwURL">
          <button type="button" onclick="sendResetURL()">設定用URLを送信</button>
        </form>

HTML;
		}
		break;
	#***** パスワード再設定URL送信 *****#
	case 'sendResetPwURL': {
			#メールアドレス
			$userEmail = isset($_POST['userEmail']) ? $_POST['userEmail'] : null;
			if ($userEmail !== null && $userEmail !== '') {
				#メールアドレスをキーにアカウント情報を取得
				$accountData = accounts_FindByEmail($userEmail);
				#パスワード再設定用にトークン発行＆メール送信処理
				if ($accountData) {
					try {
						#トランザクション開始
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						$result = DB_Transaction(1);
						$errorFlg = 0;
						if ($result == false) {
							#エラーログ出力
							$data = [
								'pageName' => 'client_reset_password',
								'reason' => 'トランザクション開始失敗',
							];
							makeLog($data);
							$errorFlg = 1;
						} else {
							#トークン発行
							$selector = bin2hex(random_bytes(8));
							$token = random_bytes(32);
							$token_hash = hash('sha256', $token);
							#有効期限：30分後
							$expires_at = date("Y-m-d H:i:s", time() + 1800);
							#リクエスト情報取得
							$requested_ip = $_SERVER['REMOTE_ADDR'] ?? '';
							$requested_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
							#登録用配列：初期化
							$dbFiledData = array();
							#登録情報セット
							$dbFiledData['account_id'] = array(':account_id', $accountData['account_id'], 0);
							$dbFiledData['selector'] = array(':selector', $selector, 0);
							$dbFiledData['token_hash'] = array(':token_hash', $token_hash, 0);
							$dbFiledData['expires_at'] = array(':expires_at', $expires_at, 0);
							$dbFiledData['requested_ip'] = array(':requested_ip', $requested_ip, 0);
							$dbFiledData['requested_user_agent'] = array(':requested_user_agent', $requested_user_agent, 0);
							#更新用キー：初期化
							$dbFiledValue = array();
							#処理モード：[1].新規追加｜[2].更新｜[3].削除
							$processFlg = 1;
							#実行モード：[1].トランザクション｜[2].即実行
							$exeFlg = 2;
							$dbSuccessFlg = SQL_Process($DB_CONNECT, "account_password_resets", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
							#全ての処理成功
							if ($dbSuccessFlg == 1) {
								#DBコミット
								# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
								DB_Transaction(2);
								#パスワード再設定用URL生成
								$resetUrl = $CMS_PANEL_URL . "/rw-client/resetPassword.php?selector=" . $selector . "&validator=" . bin2hex($token);
								#メール送信処理
								$sendResult = sendResetPassword($userEmail, $resetUrl);
								#メール送信失敗
								if ($sendResult != 'ok') {
									$errorFlg = 1;
									$makeTag['status'] = 'error';
									$makeTag['msg'] = 'パスワード再発行メール送信に失敗しました。<br>お手数ですが、再度お試し下さい。';
								}
							} else {
								#DBロールバック
								# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
								DB_Transaction(3);
								$errorFlg = 1;
								$makeTag['status'] = 'error';
								$makeTag['msg'] = 'パスワード再発行メール送信に失敗しました。<br>お手数ですが、再度お試し下さい。';
							}
						}
					} catch (Exception $e) {
						#エラーログ出力
						$data = [
							'pageName' => 'client_reset_password',
							'reason' => 'トランザクション開始失敗',
							'errorMessage' => $e->getMessage(),
						];
						makeLog($data);
						$errorFlg = 1;
						$makeTag['status'] = 'error';
						$makeTag['msg'] = 'パスワード再発行メール送信に失敗しました。<br>お手数ですが、再度お試し下さい。';
					}
				} else {
					#エラーログ出力
					$data = [
						'pageName' => 'client_reset_password',
						'reason' => 'アカウントデータ無し',
					];
					makeLog($data);
					#該当アカウントなし
					$errorFlg = 1;
					$makeTag['status'] = 'error';
					$makeTag['msg'] = 'ご入力のメールアドレスは登録されていません。<br>再度ご確認のうえ、お試し下さい。';
				}
				#***** タグ生成開始 *****#
				if ($errorFlg == 0) {
					$makeTag['tag'] .= <<<HTML
        <form name="loginForm" method="post" class="again-reset-password">
          <h2 style="text-align:center;">送信が完了しました</h2>
          <p>
            ご登録のメールアドレス宛に、パスワード再設定用のURLを送信しました。<br>
            メールが届かない場合は、迷惑メールフォルダをご確認のうえ、下記ボタンより再送信をお試しください。
          </p>
          <button type="button" onclick="showResetPassword()">設定用URLを再送信</button>
        </form>

HTML;
				} else {
					$makeTag['tag'] .= <<<HTML
        <form name="loginForm" method="post"class="again-reset-password box-log-in">
          <h2>メールの送信に失敗しました</h2>
          <p>{$makeTag['msg']}</p>
          <button type="button" onclick="showResetPassword()">設定用URLを再送信</button>
        </form>

HTML;
				}
			} else {
				$makeTag['status'] = 'error';
				#***** タグ生成開始 *****#
				$makeTag['tag'] .= <<<HTML
        <!-- パスワードリセットフォーム -->
        <form name="resetForm" method="post" class="reset-password">
          <h2>パスワードの再設定</h2>
          <p>
            ご登録のメールアドレスを入力してください。<br />パスワード再設定用のURLをお送りします。
          </p>
          <span class="title">メールアドレス</span>
          <input type="text" name="userEmail" inputmode="email">
          <input type="hidden" name="mode" value="sendURL">
          <div class="text-caution">メールアドレスを入力して下さい。</div>
          <button type="button" onclick="sendResetURL()">設定用URLを送信</button>
        </form>

HTML;
			}
		}
		break;
	#***** パスワード再設定 *****#
	case 'setNewPassword': {
			#=============#
			# POSTチェック
			#-------------#
			$selector = isset($_POST['selector']) ? $_POST['selector'] : '';
			$validator = isset($_POST['validator']) ? $_POST['validator'] : '';
			$userEmail = isset($_POST['userEmail']) ? $_POST['userEmail'] : '';
			$newPassword = isset($_POST['newPassword']) ? $_POST['newPassword'] : '';
			$showForm = false;
			#DBから該当トークンを検索
			$resetData = accountPasswordReset_FindBySelectorAndValidator($selector, $validator);
			#有効トークン判定
			if ($resetData !== null && $newPassword !== '') {
				#DBからアカウント情報を取得
				$accountData = accounts_FindById_and_Email($resetData['account_id'], $userEmail);
				if ($accountData === null || $accountData === false) {
					$makeTag['status'] = 'error';
					#***** タグ生成開始 *****#
					$makeTag['tag'] .= <<<HTML
        <!-- パスワードリセットフォーム -->
        <form name="newPwForm" method="post" class="reset-password box-log-in">
          <h2 style="text-align:center;">パスワード再設定不可</h2>
          <div class="text-caution">入力されたメールアドレスでは<br>パスワードの再設定ができません。<br>再度ご確認のうえ、お試しください。</div>
          <a href="javascript:void(0);" class="link-pw" onclick="showResetPassword()">パスワードを再設定する</a>
        </form>

HTML;
					#パスワード再設定トークン削除
					#登録用配列：初期化
					$dbDeleteFiledData = array();
					#更新用キー：初期化
					$dbDeleteFiledValue = array();
					$dbDeleteFiledValue['selector'] = array(':selector', $selector, 1);
					#処理モード：[1].新規追加｜[2].更新｜[3].削除
					$processDeleteFlg = 3;
					#実行モード：[1].トランザクション｜[2].即実行
					$exeDeleteFlg = 2;
					$dbDeleteSuccessFlg = SQL_Process($DB_CONNECT, "account_password_resets", $dbDeleteFiledData, $dbDeleteFiledValue, $processDeleteFlg, $exeDeleteFlg);
					#エラー応答終了
					break;
				}
				try {
					#トランザクション開始
					# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
					$result = DB_Transaction(1);
					$errorFlg = 0;
					if ($result == false) {
						#エラーログ出力
						$data = [
							'pageName' => 'client_new_password',
							'reason' => 'トランザクション開始失敗',
						];
						makeLog($data);
						$errorFlg = 1;
					} else {
						#パスワードハッシュ生成
						$newPwHash = password_hash($newPassword, PASSWORD_BCRYPT);
						#登録用配列：初期化
						$dbFiledData = array();
						#登録情報セット
						$dbFiledData['password_hash'] = array(':password_hash', $newPwHash, 0);
						#更新用キー：初期化
						$dbFiledValue = array();
						$dbFiledValue['account_id'] = array(':account_id', $resetData['account_id'], 1);
						#処理モード：[1].新規追加｜[2].更新｜[3].削除
						$processFlg = 2;
						#実行モード：[1].トランザクション｜[2].即実行
						$exeFlg = 2;
						$dbSuccessFlg = SQL_Process($DB_CONNECT, "accounts", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
						#処理成功
						if ($dbSuccessFlg == 1) {
							#パスワード再設定トークン削除
							#登録用配列：初期化
							$dbDeleteFiledData = array();
							#更新用キー：初期化
							$dbDeleteFiledValue = array();
							$dbDeleteFiledValue['selector'] = array(':selector', $selector, 1);
							#処理モード：[1].新規追加｜[2].更新｜[3].削除
							$processDeleteFlg = 3;
							#実行モード：[1].トランザクション｜[2].即実行
							$exeDeleteFlg = 2;
							$dbDeleteSuccessFlg = SQL_Process($DB_CONNECT, "account_password_resets", $dbDeleteFiledData, $dbDeleteFiledValue, $processDeleteFlg, $exeDeleteFlg);
						}
						#全ての処理成功
						if ($dbSuccessFlg == 1 && $dbDeleteSuccessFlg == 1) {
							#DBコミット
							# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
							DB_Transaction(2);
							$makeTag['status'] = 'success';
						} else {
							#DBロールバック
							# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
							DB_Transaction(3);
							$errorFlg = 1;
							$makeTag['status'] = 'error';
						}
					}
				} catch (Exception $e) {
					#エラーログ出力
					$data = [
						'pageName' => 'client_new_password',
						'reason' => 'トランザクション開始失敗',
						'errorMessage' => $e->getMessage(),
					];
					makeLog($data);
					$errorFlg = 1;
					$makeTag['status'] = 'error';
				}
				#エラー判定によりタグ分岐
				if ($errorFlg == 0) {
					#***** タグ生成開始 *****#
					$makeTag['tag'] .= <<<HTML
        <form name="loginForm" method="post" class="again-reset-password">
          <h2 style="text-align:center;">パスワード再設定完了</h2>
          <p>パスワードの再設定を行いました。<br>下記ボタンよりログイン画面へお進みください。</p>
          <button type="button" onclick="location.href='./'">ログイン画面へ</button>
        </form>

HTML;
				} else {
					#***** タグ生成開始 *****#
					$makeTag['tag'] .= <<<HTML
        <!-- パスワードリセットフォーム -->
        <form name="newPwForm" method="post" class="reset-password box-log-in">
          <h2 style="text-align:center;">パスワード再設定失敗</h2>
          <div class="text-caution">パスワードの再設定に失敗しました。<br>お手数ですが、再度お試し下さい。</div>
          <a href="javascript:void(0);" class="link-pw" onclick="showResetPassword()">パスワードを再設定する</a>
        </form>

HTML;
				}
			} else {
				$makeTag['status'] = 'error';
				#***** タグ生成開始 *****#
				$makeTag['tag'] .= <<<HTML
        <!-- パスワードリセットフォーム -->
        <form name="newPwForm" method="post" class="reset-password box-log-in">
          <h2 style="text-align:center;">パスワード再設定不可</h2>
          <div class="text-caution">パスワード再設定用のURLが無効です。<br>再設定用のURLの有効期限は30分です。</div>
          <a href="javascript:void(0);" class="link-pw" onclick="showResetPassword()">パスワードを再設定する</a>
        </form>

HTML;
			}
		}
		break;
}
#-------------------------------------------#
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#

#-------------------------------------------#
#パスワード再設定用URL送信処理
function sendResetPassword($sendTarget, $resetUrl)
{
	global $sendAddressList, $DEFINE_NO_REPLY;
	#***** メール送信処理 *****#
	#-------------------------#
	#（↓さわるな）
	#-------------------------#
	# ★メールの送信先
	$orderNameTo = 'リタワークサポート';
	#元のエンコーディングを保存
	$orgEncoding = mb_internal_encoding();
	#メール各種変数代入 (mbを日本語へ宣言)
	#環境依存文字の文字化け対策
	mb_language("uni");
	mb_internal_encoding('UTF-8');
	#メール送信時間
	$sendDateTime = date("Y/n/j-H:i", time());
	#--------------------------------------#
	#メールアドレスに「_」が入っている場合の文字化け対策
	$fromAddress = $sendTarget;
	#名前も一応文字化け対策セット
	$fromName = 'マスターアカウント 様';
	#送信元設定：名前有り
	$headerFrom = 'From: "' . $fromName . '"<' . $fromAddress . '>';
	#メール本体作成
	$subjectHead = '[リタワークサポート] マスターアカウント 様よりパスワード再設定処理のご依頼';
	$bodyText  = '';
	$bodyText .= 'マスターアカウント 様よりパスワード再設定処理のご依頼がありました。' . "\n";
	$bodyText .= "\n";
	$bodyText .= '■操作日時：' . $sendDateTime . "\n";
	$bodyText .= "\n";
	$bodyText .= '--------------------' . "\n";
	$bodyText .= "\n";
	$bodyText .= $sendDateTime . "\n";
	$bodyText  = str_replace("\r\n", "\n", $bodyText);
	#メール宛先名称
	$toName = mb_encode_mimeheader($orderNameTo, 'ISO-2022-JP');
	#宛先メアド
	$sendTo = $toName . ' <' . $sendTarget . '>';
	#メール送信！(返り値はTRUEorFALSE)
	$result = array();
	/*
	$result[] = @mb_send_mail($sendTarget, $subjectHead, $bodyText, $headerFrom, "-f$fromAddress");
	#複数宛先送信
	foreach ($sendAddressList as $target) {
		$sendTargets = $toName . ' <' . $target . '>';
		#var_dump($target);
		#メール送信！(返り値はTRUEorFALSE)
		$result[] = @mb_send_mail($sendTargets, $subjectHead, $bodyText, $headerFrom, "-f$fromAddress");
	}
	*/
	#var_dump($sendTarget,$subjectHead,$bodyText,$headerFrom); exit();
	#--------------------------------------#
	#回答者へ受付送信-3 (返信先設定：名前有り)
	$sendUser = $fromName . ' <' . $fromAddress . '>';
	#送信元設定：名前有り
	$send_to_noreply = $DEFINE_NO_REPLY;
	$headerFromUser = 'From: "' . $toName . '"<' . $send_to_noreply . '>';
	#
	$replySubjectHead = 'リタワーク管理画面｜パスワード再設定フォーム';
	$replyBodyText  = '';
	$replyBodyText .= 'リタワークです。' . "\n";
	$replyBodyText .= '以下のページからパスワードの再設定を行って下さい。' . "\n";
	$replyBodyText .= "\n";
	$replyBodyText .= $resetUrl . "\n";
	$replyBodyText .= "\n";
	$replyBodyText .= '--------------------' . "\n";
	$replyBodyText .= 'パスワード再設定URLの有効期限は30分です。' . "\n";
	$replyBodyText .= '30分以内に操作できなかった場合は、再度パスワード再設定の手続きを行ってください。' . "\n";
	$replyBodyText .= '--------------------' . "\n";
	$replyBodyText .= "\n";
	$replyBodyText .= '※このメールは送信専用メールアドレスから送信されております。' . "\n";
	$replyBodyText .= 'ご返信いただいても内容の確認および、ご返信ができませんのでご了承ください。' . "\n";
	$replyBodyText .= 'また、このメールに心当たりがない場合は、お手数ですが破棄していただけますと幸いです。' . "\n";
	$replyBodyText .= "\n";
	$replyBodyText .= $sendDateTime . "\n";
	$replyBodyText .= "\n";
	$replyBodyText  = str_replace("\r\n", "\n", $replyBodyText);
	#var_dump($sendUser,$replySubjectHead,$replyBodyText,$headerFromUser); exit();
	$resultUser = @mb_send_mail($sendUser, $replySubjectHead, $replyBodyText, $headerFromUser, "-f$sendTo");
	#★
	$data = [
		'pageName' => 'client_reset_password',
		'sendTarget' => $sendTarget,
		'send_date' => $sendDateTime,
		'result' => $resultUser,
	];
	makeLog($data);
	#★
	#送信失敗時は未送信へpush
	if ($resultUser) {
		return 'ok';
	} else {
		return 'ng';
	}
	#保存しておいたエンコーディングに戻す
	mb_internal_encoding($orgEncoding);
}
