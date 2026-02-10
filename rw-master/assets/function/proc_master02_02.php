<?php
/*
 * [rw-master/assets/function/proc_master02_02.php]
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
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#法人情報
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_corporations.php';

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
#新規／編集／削除
$method = isset($_POST['method']) ? $_POST['method'] : null;
#確認／修正／登録
$action = isset($_POST['action']) ? $_POST['action'] : null;
#法人ID（編集／削除時のみ）
$corpId = isset($_POST['corpId']) ? $_POST['corpId'] : null;
#-------------#
#契約日
$contract_date = isset($_POST['contract_date']) ? $_POST['contract_date'] : null;
if ($contract_date === '') {
	$viewContractDate = '----年--月--日';
} else {
	$viewContractDate = date('Y年m月d日', strtotime($contract_date));
}
#法人名
$company_name = isset($_POST['company_name']) ? $_POST['company_name'] : null;
#ふりがな
$company_name_kana = isset($_POST['company_name_kana']) ? $_POST['company_name_kana'] : null;
#住所
$address1 = isset($_POST['address1']) ? $_POST['address1'] : null;
$address2 = isset($_POST['address2']) ? $_POST['address2'] : null;
$address3 = isset($_POST['address3']) ? $_POST['address3'] : null;
#電話番号
$phone_number = isset($_POST['phone_number']) ? $_POST['phone_number'] : null;
#メールアドレス
$email = isset($_POST['email']) ? $_POST['email'] : null;

#================#
# メニュータイトル
#----------------#
#メニュータイトル
$menuTitle = "法人情報";
$sideMenuTitle = "法人管理";
if ($method === 'new') {
	$menuTitle = "新規法人登録";
	$sideMenuTitle = "新規法人管理";
} elseif ($method === 'edit') {
	$menuTitle = "法人情報 ー " . $company_name;
	$sideMenuTitle = "法人情報";
}

#***** タグ生成開始 *****#
switch ($action) {
	#***** 入力チェック *****#
	case 'checkInput': {
			$makeTag['tag'] .= <<<HTML
      <section class="container-company-check">
        <h2>法人情報の入力内容確認</h2>
        <article class="block-check">
          <dl>
            <dt>契約日</dt>
            <dd>{$viewContractDate}</dd>
          </dl>
          <dl>
            <dt>法人名</dt>
            <dd>{$company_name}</dd>
          </dl>
          <dl>
            <dt>ふりがな</dt>
            <dd>{$company_name_kana}</dd>
          </dl>
          <dl>
            <dt>住所</dt>
            <dd class="item-add"><span>〒{$address1}</span>{$address2} {$address3}</dd>
          </dl>
          <dl>
            <dt>電話番号</dt>
            <dd>{$phone_number}</dd>
          </dl>
          <dl>
            <dt>E-mail</dt>
            <dd>{$email}</dd>
          </dl>
        </article>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="historyBack()">戻る</button>
          <button type="button" class="item-check" onclick="sendInput()">登録する</button>
        </div>
        <form name="inputForm" style="display:none;">
          <input type="hidden" name="action" value="sendInput">
          <input type="hidden" name="method" value="{$method}">
          <input type="hidden" name="corpId" value="{$corpId}">
          <input type="hidden" name="contract_date" value="{$contract_date}">
          <input type="hidden" name="company_name" value="{$company_name}">
          <input type="hidden" name="company_name_kana" value="{$company_name_kana}">
          <input type="hidden" name="address1" value="{$address1}">
          <input type="hidden" name="address2" value="{$address2}">
          <input type="hidden" name="address3" value="{$address3}">
          <input type="hidden" name="phone_number" value="{$phone_number}">
          <input type="hidden" name="email" value="{$email}">
        </form>
      </section>

HTML;
		}
		break;
	#***** 修正 *****#
	case 'fixInput': {
			$makeTag['tag'] .= <<<HTML
      <section class="container-company-register">
        <a href="javascript:history.back()" class="link-page-back">戻る</a>
        <h2>{$menuTitle}</h2>
        <form name="inputForm" class="block-form">
          <input type="hidden" name="method" value="{$method}">
          <input type="hidden" name="action" value="checkInput">
          <input type="hidden" name="corpId" value="{$corpId}">
          <dl>
            <dt>契約日</dt>
            <dd>
              <div class="input-date"><input type="date" name="contract_date" value="{$contract_date}" class="required-item" required></div>
            </dd>
          </dl>
          <dl>
            <dt>法人名</dt>
            <dd><input type="text" name="company_name" value="{$company_name}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt>ふりがな</dt>
            <dd><input type="text" name="company_name_kana" value="{$company_name_kana}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt>住所</dt>
            <dd class="item-add">
              <input type="text" name="address1" value="{$address1}" class="required-item" required id="zipCode">
              <input type="text" name="address2" value="{$address2}">
              <input type="text" name="address3" value="{$address3}">
            </dd>
          </dl>
          <dl>
            <dt>電話番号</dt>
            <dd><input type="text" name="phone_number" value="{$phone_number}" autocomplete="on" class="required-item phone_number" required></dd>
          </dl>
          <dl>
            <dt>E-mail</dt>
            <dd><input type="text" name="email" value="{$email}" autocomplete="on" class="required-item email" required></dd>
          </dl>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="history.back(2)">戻る</button>
          <button type="button" class="item-check" onclick="checkInput()">入力を確認する</button>
        </div>
        <!--NOTE 修正画面のみ表示 -->

HTML;
			if ($method === 'edit') {
				$makeTag['tag'] .= <<<HTML
        <button type="button" class="btn-delate-item" onclick="checkDeleteCorporation({$corpId},'{$company_name}')">削除する</button>

HTML;
			}
			$makeTag['tag'] .= <<<HTML
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
						'pageName' => 'proc_master02_02',
						'reason' => 'トランザクション開始失敗',
					];
					makeLog($data);
				} else {
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
					#DB登録情報準備
					switch ($method) {
						#***** 新規登録 *****#
						case 'new': {
								$getCorporationId = getLastCorporationId();
								if ($getCorporationId === false) {
									#例外処理へ
									throw new Exception('最新法人ID取得失敗');
								} else {
									#最新法人ID取得成功
									$corporationCode = 'corp_' . sprintf("%04d", $getCorporationId['AUTO_INCREMENT']);
								}
								#登録用配列：初期化
								$dbFiledData = array();
								#登録情報セット
								$dbFiledData['corporation_code'] = array(':corporation_code', $corporationCode, 0);
								$dbFiledData['contract_date'] = array(':contract_date', $contract_date, 0);
								$dbFiledData['name'] = array(':name', $company_name, 0);
								$dbFiledData['name_kana'] = array(':name_kana', $company_name_kana, 0);
								$dbFiledData['postal_code'] = array(':postal_code', $postal_code['zipCode'], 0);
								$dbFiledData['prefecture'] = array(':prefecture', $prefecture, 0);
								$dbFiledData['city'] = array(':city', $city, 0);
								$dbFiledData['address_line'] = array(':address_line', $address_line, 0);
								$dbFiledData['phone'] = array(':phone', $phone, 0);
								$dbFiledData['email'] = array(':email', $email, 0);
								$dbFiledData['created_at'] = array(':created_at', date("Y-m-d H:i:s"), 0);
								#更新用キー：初期化
								$dbFiledValue = array();
								#処理モード：[1].新規追加｜[2].更新｜[3].削除
								$processFlg = 1;
							}
							break;
						#***** 編集 *****#
						case 'edit': {
								#登録用配列：初期化
								$dbFiledData = array();
								#登録情報セット
								$dbFiledData['contract_date'] = array(':contract_date', $contract_date, 0);
								$dbFiledData['name'] = array(':name', $company_name, 0);
								$dbFiledData['name_kana'] = array(':name_kana', $company_name_kana, 0);
								$dbFiledData['postal_code'] = array(':postal_code', $postal_code['zipCode'], 0);
								$dbFiledData['prefecture'] = array(':prefecture', $prefecture, 0);
								$dbFiledData['city'] = array(':city', $city, 0);
								$dbFiledData['address_line'] = array(':address_line', $address_line, 0);
								$dbFiledData['phone'] = array(':phone', $phone, 0);
								$dbFiledData['email'] = array(':email', $email, 0);
								$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
								#更新用キー：初期化
								$dbFiledValue = array();
								$dbFiledValue['corporation_id'] = array(':corporation_id', $corpId, 1);
								#処理モード：[1].新規追加｜[2].更新｜[3].削除
								$processFlg = 2;
							}
							break;
						#***** 削除 *****#
						case 'delete': {
								#登録用配列：初期化
								$dbFiledData = array();
								#登録情報セット
								$dbFiledData['is_active'] = array(':is_active', 0, 1);
								$dbFiledData['updated_at'] = array(':updated_at', date("Y-m-d H:i:s"), 0);
								#更新用キー：初期化
								$dbFiledValue = array();
								$dbFiledValue['corporation_id'] = array(':corporation_id', $corpId, 1);
								#処理モード：[1].新規追加｜[2].更新｜[3].削除
								$processFlg = 2;
							}
							break;
					}
					#DB更新
					#実行モード：[1].トランザクション｜[2].即実行
					$exeFlg = 2;
					$dbSuccessFlg = SQL_Process($DB_CONNECT, "corporations", $dbFiledData, $dbFiledValue, $processFlg, $exeFlg);
					#全ての処理成功
					if ($dbSuccessFlg == 1) {
						#DBコミット
						# 1 = BEGIN／ 2 = COMMIT／ 3 = ROLLBACK
						DB_Transaction(2);
						$makeTag['status'] = 'success';
						switch ($method) {
							#***** 新規登録 *****#
							case 'new': {
									$makeTag['title'] = '新規法人登録';
									$makeTag['msg'] = '登録が完了しました。';
								}
								break;
							#***** 編集 *****#
							case 'edit': {
									$makeTag['title'] = '法人情報編集';
									$makeTag['msg'] = '更新が完了しました。';
								}
								break;
							#***** 削除 *****#
							case 'delete': {
									$makeTag['title'] = '法人情報削除';
									$makeTag['msg'] = $company_name . 'の削除が完了しました。';
								}
								break;
						}
						#----------------------------
						# DB更新完了のJSONファイル作成
						#----------------------------
						$cmd = '/usr/bin/php8.3 ' . DEFINE_JSON_FUNCTION_MASTER . '/workJson/makeCorporations.php 2>&1 &';
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
					'pageName' => 'proc_master02_02',
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
#json 応答
echo json_encode($makeTag);
#-------------------------------------------#
#===========================================#
