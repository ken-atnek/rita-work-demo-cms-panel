<?php
/*
 * [cms_config/common/workJson/makeCorporations.php]
 *  - 管理画面 -
 *  新規法人登録／編集／削除後のJSONファイル作成
 *
 * [初版]
 *  2025.12.20
 */

#===========================================#
# 基本設定
#-------------------------------------------#
require(__DIR__ . '/../../database/set_db.php');
require(__DIR__ . '/../../database/db_corporations.php');
#-------------------------------------------#
#===========================================#
#JSON保存先
require(__DIR__ . '/../../common/define.php');
$saveDir = DEFINE_JSON_DIR_PATH . '/master';
#-------------------------------------------#
#===========================================#
#書き込み用ディレクトリが無い場合はベースディレクトリ作成
if (!is_dir($saveDir)) {
	@mkdir($saveDir, 0777, true);
}
#書き込み用jsonファイルが無い場合はベースファイルを作成
$corporationsJson = 'corporations.json';
if (!file_exists($saveDir . '/' . $corporationsJson)) {
	makeJson($saveDir, $corporationsJson);
}
#===========================================#
#法人一覧取得
$corporationsList = getCorporationList();
#表示可能リストあればベースデータ生成
if (is_array($corporationsList) && count($corporationsList) > 0) {
	#JSONデータ生成
	$writeData = [];
	foreach ($corporationsList as $corpData) {
		$writeData[] = [
			'id' => $corpData['corporation_code'],
			'contractDate' => $corpData['contract_date'],
			'name' => $corpData['name'],
			'nameKana' => $corpData['name_kana'],
			'postalCode' => $corpData['postal_code'],
			'prefecture' => $corpData['prefecture'],
			'city' => trim($corpData['city']),
			'addressLine' => trim($corpData['address_line']),
			'phone' => $corpData['phone'],
			'email' => $corpData['email'],
		];
	}
	#JSONエンコード
	$news_json = json_encode($writeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	#ファイル書き込み
	$write_json = fopen($saveDir . '/' . $corporationsJson, "w");
	fwrite($write_json, $news_json);
	fclose($write_json);
} else {
	#表示可能リスト無し：空ファイル作成
	makeJson($saveDir, $corporationsJson);
}
#===========================================#
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
