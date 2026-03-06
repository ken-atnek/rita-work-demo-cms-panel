<?php
/*
 * [有効アカウント情報取得]
 *  引数
 *   $intId：アカウントID
 *   $strEmail：メールアドレス
 */
function accounts_FindById_and_Email($intId, $strEmail)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				account_id, account_type, facility_id, login_email, password_hash 
			FROM 
				accounts 
			WHERE 
				is_active = 1 AND 
				account_id = :account_id AND 
				login_email = :login_email AND 
				(locked_until IS NULL OR locked_until < NOW()) LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':account_id', $intId, PDO::PARAM_INT);
		$newStmt->bindValue(':login_email', $strEmail, PDO::PARAM_STR);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$account = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $account ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [有効アカウント情報取得]
 *  引数
 *   $strEmail：メールアドレス
 */
function accounts_FindByEmail($strEmail)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				account_id, account_type, facility_id, login_email, password_hash 
			FROM 
				accounts 
			WHERE 
				is_active = 1 AND 
				login_email = :login_email AND 
				(locked_until IS NULL OR locked_until < NOW()) LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':login_email', $strEmail);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$account = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $account ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [パスワード設定待ちアカウント情報取得]
 *  引数
 *   $strEmail：メールアドレス
 */
function accounts_Waiting_FindByEmail($strEmail)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				account_id, account_type, facility_id, login_email, password_hash 
			FROM 
				accounts 
			WHERE 
				is_active = 9 AND 
				login_email = :login_email AND 
				(locked_until IS NULL OR locked_until < NOW()) LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':login_email', $strEmail);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$account = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#存在しない場合はnullを返却して呼び出し側で判定
		return $account ?: null;
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
/*
 * [パスワード再設定用トークン取得]
 *  引数
 *   $selector ：セレクタ―
 *   $validator：バリデータ
 */
function accountPasswordReset_FindBySelectorAndValidator($selector, $validator)
{
	global $DB_CONNECT;
	try {
		#SQL定義
		$strSQL = "
			SELECT 
				* 
			FROM 
				account_password_resets 
			WHERE 
				selector = :selector AND expires_at > NOW() LIMIT 1
		";
		#プリペアードステートメント作成
		$newStmt = $DB_CONNECT->prepare($strSQL);
		#変数バインド
		$newStmt->bindValue(':selector', $selector);
		#SQL実行
		$newStmt->execute();
		#実行結果取得
		$resetData = $newStmt->fetch(PDO::FETCH_ASSOC);
		#ステートメントクローズ
		$newStmt->closeCursor();
		#バリデータ(=token)のハッシュを比較
		if ($resetData) {
			$calcHash = hash('sha256', hex2bin($validator));
			if (hash_equals($resetData['token_hash'], $calcHash)) {
				return $resetData;
			} else {
				return null;
			}
		} else {
			return null;
		}
	} catch (PDOException $e) {
		echo $e->getMessage();
		exit;
	}
}
