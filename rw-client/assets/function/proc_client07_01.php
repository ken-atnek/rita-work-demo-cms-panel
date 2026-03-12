<?php
/*
 * [rw-client/assets/function/proc_client07_01.php]
 *  - 管理画面 -
 *  法人登録／編集 処理
 *
 * [初版]
 *  2025.12.19
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
#事業所情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities.php';
#問い合わせ情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_facilities_inquiries.php';

#================#
# 応答用タグ初期化
#----------------#
$makeTag = array(
	'tag' => '',
	'status' => '',
	'title' => '',
	'msg' => '',
);

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
#応答には常に現行のキーを含め、フロント側のhiddenを更新できるようにする
$makeTag['noUpDateKey'] = ($currentNoUpDateKey !== '' ? $currentNoUpDateKey : $noUpDateKey);

#確認／修正／登録
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

#事業所ID（セッションからのみ取得）
$facId = isset($_SESSION['client_login']['facility_id']) ? (int)$_SESSION['client_login']['facility_id'] : 0;
if ($facId < 1) {
	header('Content-Type: application/json; charset=UTF-8');
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'ログインエラー';
	$makeTag['msg'] = 'ログイン情報が確認できません。ページを再読み込みしてください。';
	echo json_encode($makeTag);
	exit;
}

#==============#
# 事業所情報取得
#--------------#
$facilityData = getFacility_FindById($facId);
if (!$facilityData) {
	header('Content-Type: application/json; charset=UTF-8');
	$makeTag['status'] = 'error';
	$makeTag['title'] = 'データ取得失敗';
	$makeTag['msg'] = '事業所情報の取得に失敗しました。ページを再読み込みしてください。';
	echo json_encode($makeTag);
	exit;
}
#-------------#
#件名
$selectSubject = isset($_POST['selectSubject']) ? (string)$_POST['selectSubject'] : '';
$allowedSubjects = ['plan', 'password', 'other'];
if (!in_array($selectSubject, $allowedSubjects, true)) {
	$selectSubject = '';
}
#件名判定
switch ($selectSubject) {
	case 'plan': {
			$subjectText = 'プランについて';
		}
		break;
	case 'password': {
			$subjectText = 'パスワードについて';
		}
		break;
	case 'other': {
			$subjectText = 'その他について';
		}
		break;
	default: {
			$subjectText = '未選択';
		}
}
#返信方法
$selectReplyMethod = isset($_POST['selectReplyMethod']) ? (string)$_POST['selectReplyMethod'] : '';
$allowedReplyMethods = ['email', 'phone', 'other'];
if (!in_array($selectReplyMethod, $allowedReplyMethods, true)) {
	$selectReplyMethod = '';
}
#返信方法判定
switch ($selectReplyMethod) {
	case 'email': {
			$replyMethodText = 'メール';
		}
		break;
	case 'phone': {
			$replyMethodText = '電話';
		}
		break;
	case 'other': {
			$replyMethodText = 'その他';
		}
		break;
	default: {
			$replyMethodText = '未選択';
		}
}
#問い合わせ内容
$messageBody = isset($_POST['messageBody']) ? (string)$_POST['messageBody'] : '';
#改行はDBで保持（必要なら \r\n→\n に正規化）。HTML化は表示時。
$messageBody = str_replace(["\r\n", "\r"], "\n", $messageBody);

#***** タグ生成開始 *****#
switch ($action) {
	#***** 入力チェック *****#
	case 'checkInput': {
			$subjectTextEsc = htmlspecialchars((string)$subjectText, ENT_QUOTES, 'UTF-8');
			$messageBodyHtml = nl2br(htmlspecialchars((string)$messageBody, ENT_QUOTES, 'UTF-8'));
			$makeTag['tag'] .= <<<HTML
      <div class="inner-modal">
        <div class="box-title">
          <p>{$subjectTextEsc}</p>
          <button type="button" onclick="closeModal();" class="btn-top-close"></button>
        </div>
        <div class="box-from">
          <!-- <span class="item-from"></span> -->
          <span class="item-response">{$replyMethodText}</span>
        </div>
        <div class="box-details">
          <div class="wrap-details">
            <div class="item-text">
              <p>{$messageBodyHtml}</p>
            </div>
          </div>
          <div class="box-btn">
            <button type="button" onclick="closeModal();" class="btn-bottom-close">戻る</button>
            <button type="button" class="btn-confirm" onclick="sendInput();">送信する</button>
          </div>
        </div>
      </div>

HTML;
		}
		break;
	#***** 登録 *****#
	case 'sendInput': {
			try {
				#サーバ側バリデーション
				if ($selectSubject === '' || $selectReplyMethod === '' || trim($messageBody) === '') {
					$makeTag['status'] = 'error';
					$makeTag['title'] = '入力エラー';
					$makeTag['msg'] = '入力内容を確認してください。';
					break;
				}
				#トランザクション開始
				# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
				$result = DB_Transaction(1);
				if ($result == false) {
					#エラーログ出力
					$data = [
						'pageName' => 'proc_client07_01',
						'reason' => 'メッセージ送信失敗',
					];
					makeLog($data);
					$makeTag['status'] = 'error';
					$makeTag['title'] = 'メッセージ送信失敗';
					$makeTag['msg'] = 'メッセージの送信に失敗しました。再度やり直してください。';
				} else {
					#登録用配列：初期化
					$dbFiledData = array();
					#登録情報セット
					$dbFiledData['facility_id'] = array(':facility_id', $facId, 1);
					$dbFiledData['category'] = array(':category', $selectSubject, 0);
					$dbFiledData['body'] = array(':body', $messageBody, 0);
					$dbFiledData['reply_channel'] = array(':reply_channel', $selectReplyMethod, 0);
					$dbFiledData['handling_status'] = array(':handling_status', 'new', 0);
					$dbFiledData['record_status'] = array(':record_status', 'active', 0);
					$dbFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
					#更新用キー：初期化
					$dbFiledValue = array();
					#処理モード：[1].新規追加｜[2].更新｜[3].削除
					$processFlg = 1;
					#DB更新
					#実行モード：[1].トランザクション｜[2].即実行
					$exeFlg = 2;
					$dbSuccessFlg = SQL_Process($DB_CONNECT, "facility_inquiries", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
					#全ての処理成功
					if ($dbSuccessFlg == 1) {
						$inquiryId = 0;
						try {
							$inquiryId = (int)$DB_CONNECT->lastInsertId();
						} catch (Throwable $e) {
							$inquiryId = 0;
						}
						#問い合わせ完了メール送信
						$resultSendMail = sendMail_Facility_Inquiry(
							$facilityData['email'],
							$facilityData['name'],
							$subjectText,
							$messageBody,
							$replyMethodText
						);
						#メール送信まで完了したらDB更新
						if ($resultSendMail == false) {
							#エラーログ出力
							$data = [
								'pageName' => 'proc_client07_01',
								'reason' => 'メッセージ送信失敗',
								'toEmail' => $facilityData['email'],
							];
							makeLog($data);
							#ロールバック実行
							DB_Transaction(3);
							#エラー応答
							$makeTag['status'] = 'error';
							$makeTag['title'] = 'メッセージ送信失敗';
							$makeTag['msg'] = 'メッセージの送信に失敗しました。再度やり直してください。';
						} else {
							#メール送信成功日時をDBに保存する
							#登録用配列：初期化
							$dbFiledData = array();
							#登録情報セット
							$dbFiledData['admin_mail_sent_at'] = array(':admin_mail_sent_at', date("Y-m-d H:i:s"), 0);
							$dbFiledData['facility_mail_sent_at'] = array(':facility_mail_sent_at', date("Y-m-d H:i:s"), 0);
							#更新用キー：初期化
							$dbFiledValue = array();
							if ($inquiryId > 0) {
								$dbFiledValue['inquiry_id'] = array(':inquiry_id', $inquiryId, 1);
							}
							#処理モード：[1].新規追加｜[2].更新｜[3].削除
							$processFlg = 2;
							#DB更新
							#実行モード：[1].トランザクション｜[2].即実行
							$exeFlg = 2;
							$dbUpdateFlg = 0;
							if ($inquiryId > 0) {
								$dbUpdateFlg = SQL_Process($DB_CONNECT, "facility_inquiries", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
								if ($dbUpdateFlg != 1) {
									makeLog([
										'pageName' => 'proc_client07_01',
										'reason' => 'メール送信日時の保存に失敗',
										'inquiry_id' => $inquiryId,
									]);
								}
							} else {
								makeLog([
									'pageName' => 'proc_client07_01',
									'reason' => 'inquiry_id の取得に失敗（メール送信日時保存スキップ）',
								]);
							}
							#DBコミット
							# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
							DB_Transaction(2);
							$makeTag['status'] = 'success';
							$makeTag['title'] = 'メッセージ送信完了';
							$makeTag['msg'] = 'メッセージの送信が完了しました。';
						}
					} else {
						#DBロールバック
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(3);
						#エラーログ出力
						$data = [
							'pageName' => 'proc_client07_01',
							'reason' => 'メッセージ送信失敗',
						];
						makeLog($data);
						$makeTag['status'] = 'error';
						$makeTag['title'] = 'メッセージ送信失敗';
						$makeTag['msg'] = 'メッセージの送信に失敗しました。再度やり直してください。';
					}
				}
			} catch (Throwable $e) {
				#エラーログ出力
				$data = [
					'pageName' => 'proc_client07_01',
					'reason' => 'メッセージ送信失敗',
					'errorMessage' => $e->getMessage(),
				];
				makeLog($data);
				DB_Transaction(3);
				$makeTag['status'] = 'error';
				$makeTag['title'] = 'メッセージ送信失敗';
				$makeTag['msg'] = 'メッセージの送信に失敗しました。再度やり直してください。';
			}
		}
		break;
}
#-------------------------------------------#
#問い合わせ完了メール送信
function sendMail_Facility_Inquiry($toEmail, $toName, $subjectText, $messageBody, $replyMethodText)
{
	global $DEFINE_NO_REPLY, $DEFINE_MAIL_SENDER_NAME, $sendAddressList;
	global $DEFINE_MASTER_NOTIFY_EMAIL, $DEFINE_MASTER_NOTIFY_NAME;
	#メールタイトル
	$mailTitle = '【RITA】問い合わせ完了のお知らせ (' . $subjectText . ')';
	#メール本文
	$mailBody = <<<EOD
{$toName} 様

いつもRITAをご利用いただき、誠にありがとうございます。
問い合わせを受け付けました。
内容を確認の上、担当者よりご連絡いたしますので今しばらくお待ちください。
─────────────────────────────
【問い合わせ内容】
■件名
{$subjectText}

■内容
{$messageBody}

■返信方法
{$replyMethodText}

─────────────────────────────
■RITAサポートセンター
E-mail：officeiga.ceo@gmail.com
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
	$notifyTitle = $subjectText;
	$notifyBody = $toName . "様からの問い合わせ\n"
		. "\n"
		. "【内容】\n"
		. $messageBody . "\n"
		. "\n"
		. "【返信方法】\n"
		. $replyMethodText . "\n";
	#メール送信
	$resultMaster = sendMail_Common($masterEmail, $masterName, $notifyTitle, $notifyBody, $DEFINE_NO_REPLY, $DEFINE_MAIL_SENDER_NAME, []);
	if ($resultMaster == false) {
		makeLog([
			'pageName' => 'proc_client07_01',
			'reason' => 'マスターメール送信失敗（問い合わせ）',
			'toEmail' => $masterEmail,
			'facilityEmail' => $toEmail,
		]);
	}
	#応答
	return true;
}
#-------------------------------------------#
#json 応答
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
