<?php
#-------------------------------------------#
# 求人カード関連：DB書き込み共通処理
#  - option_links / option_link_extras / job_documents / job_metric_values
#  - 基本方針：DELETE → (必要なら) INSERT
#-------------------------------------------#
/*
 * [SQL実行]
 *  引数
 *   $connect    ：DB接続
 *   $table      ：テーブル
 *   $inSqlParams：パラメータ(フィールド名をキーとした配列)
 *   $whereParams：入力フィールド(フィールド名をキーとした配列)
 *   $processFlg ：処理フラグ
 */
function DB_jobExec_SQL_process($connect, $table, $inSqlParams, $whereParams, $processFlg)
{
	$exeFlg = 2;
	$successFlg = SQL_Process($connect, $table, $inSqlParams, $whereParams, $processFlg, $exeFlg);
	return ($successFlg == 1);
}
/*
 * [求人ID検証]
 *  - jobIdが空/非数値だと PDO::PARAM_INT で 0 に丸められ、
 *    job_id=0 でDELETE/INSERTが走ってPRIMARY重複を起こし得るためガードする
 */
function DB_jobIsValidJobId($jobId)
{
	return (is_numeric($jobId) && (int)$jobId > 0);
}
/*
 * [job_option_links・job_option_link_extras 共通削除処理]
 *  引数
 *   $connect  ：DB接続
 *   $table    ：テーブル
 *   $jobId    ：求人ID
 *   $groupCode：オプショングループコード
 */
function DB_jobDeleteRowsBy_job_and_group($connect, $table, $jobId, $groupCode)
{
	if (!DB_jobIsValidJobId($jobId)) {
		return false;
	}
	$where = array();
	$where['job_id'] = array(':job_id', $jobId, 1);
	$where['option_group_code'] = array(':option_group_code', $groupCode, 0);
	return DB_jobExec_SQL_process($connect, $table, array(), $where, 3);
}
/*
 * [job_option_links登録・更新処理]
 *  引数
 *   $connect  ：DB接続
 *   $jobId    ：求人ID
 *   $groupCode：オプショングループコード
 *   $optionIds：オプションID
 */
function DB_jobReplace_option_links($connect, $jobId, $groupCode, $optionIds)
{
	if (!DB_jobIsValidJobId($jobId)) {
		return false;
	}
	if (!DB_jobDeleteRowsBy_job_and_group($connect, 'job_option_links', $jobId, $groupCode)) {
		return false;
	}
	if (empty($optionIds) || !is_array($optionIds)) {
		return true;
	}
	foreach ($optionIds as $optionId) {
		if ($optionId === '' || $optionId === null) {
			continue;
		}
		$data = array();
		$data['job_id'] = array(':job_id', $jobId, 1);
		$data['option_group_code'] = array(':option_group_code', $groupCode, 0);
		$data['option_id'] = array(':option_id', $optionId, 0);
		$data['created_at'] = array(':created_at', date('Y-m-d H:i:s'), 0);
		if (!DB_jobExec_SQL_process($connect, 'job_option_links', $data, array(), 1)) {
			return false;
		}
	}
	return true;
}
/*
 * [job_option_link_extras登録・更新処理]
 *  引数
 *   $connect  ：DB接続
 *   $jobId    ：求人ID
 *   $groupCode：オプショングループコード
 *   $text     ：オプションテキスト
 */
function DB_jobSet_option_text($connect, $jobId, $groupCode, $text)
{
	if (!DB_jobIsValidJobId($jobId)) {
		return false;
	}
	if (!DB_jobDeleteRowsBy_job_and_group($connect, 'job_option_link_extras', $jobId, $groupCode)) {
		return false;
	}
	if ($text === null || $text === '') {
		return true;
	}
	$data = array();
	$data['job_id'] = array(':job_id', $jobId, 1);
	$data['option_group_code'] = array(':option_group_code', $groupCode, 0);
	$data['option_text'] = array(':option_text', $text, 0);
	$data['created_at'] = array(':created_at', date('Y-m-d H:i:s'), 0);
	return DB_jobExec_SQL_process($connect, 'job_option_link_extras', $data, array(), 1);
}
/*
 * [job_documents削除処理]
 *  引数
 *   $connect：DB接続
 *   $jobId  ：求人ID
 *   $docType：ドキュメントタイプ
 */
function DB_jobDeleteDocumentBy_job_and_type($connect, $jobId, $docType)
{
	if (!DB_jobIsValidJobId($jobId)) {
		return false;
	}
	$where = array();
	$where['job_id'] = array(':job_id', $jobId, 1);
	$where['doc_type'] = array(':doc_type', $docType, 0);
	return DB_jobExec_SQL_process($connect, 'job_documents', array(), $where, 3);
}
/*
 * [job_documents登録・更新処理]
 *  引数
 *   $connect  ：DB接続
 *   $jobId    ：求人ID
 *   $docType  ：ドキュメントタイプ
 *   $json     ：jsonデータ
 *   $enabled  ：有効フラグ(1=有効、0=無効)
 */
function DB_jobSet_document_json($connect, $jobId, $docType, $json, $enabled = 1)
{
	if (!DB_jobIsValidJobId($jobId)) {
		return false;
	}
	if (!DB_jobDeleteDocumentBy_job_and_type($connect, $jobId, $docType)) {
		return false;
	}
	if ($json === null || $json === '') {
		return true;
	}
	$data = array();
	$data['job_id'] = array(':job_id', $jobId, 1);
	$data['doc_type'] = array(':doc_type', $docType, 0);
	$data['enabled'] = array(':enabled', $enabled, 1);
	$data['content_json'] = array(':content_json', $json, 0);
	$data['created_at'] = array(':created_at', date('Y-m-d H:i:s'), 0);
	return DB_jobExec_SQL_process($connect, 'job_documents', $data, array(), 1);
}
/*
 * [job_metric_values登録・更新・削除処理]
 *  引数
 *   $connect：DB接続
 *   $jobId  ：求人ID
 *   $metrics：メトリックデータ配列
 */
function DB_jobReplaceWorkEnvironment_metrics($connect, $jobId, $metrics)
{
	if (!DB_jobIsValidJobId($jobId)) {
		return false;
	}
	#既存削除（job_id単位で全削除）
	$where = array();
	$where['job_id'] = array(':job_id', $jobId, 1);
	if (!DB_jobExec_SQL_process($connect, 'job_metric_values', array(), $where, 3)) {
		return false;
	}
	if (empty($metrics) || !is_array($metrics)) {
		return true;
	}
	foreach ($metrics as $item) {
		$metricId = $item['metric_id'] ?? ($item['metric'] ?? null);
		$value = $item['value'] ?? null;
		$unit = $item['unit'] ?? '';
		if ($metricId === null || $metricId === '' || $value === null || $value === '') {
			continue;
		}
		$data = array();
		$data['job_id'] = array(':job_id', $jobId, 1);
		$data['metric_id'] = array(':metric_id', $metricId, 0);
		$data['value'] = array(':value', $value, 0);
		$data['unit'] = array(':unit', $unit, 0);
		$data['created_at'] = array(':created_at', date('Y-m-d H:i:s'), 0);
		if (!DB_jobExec_SQL_process($connect, 'job_metric_values', $data, array(), 1)) {
			return false;
		}
	}
	return true;
}
