<?php
/*
 * [rw-master/master05_01_01.php]
 *  - 管理画面 -
 *  運営管理：転職のヒント一覧
 *
 * [初版]
 *  2026.02.04
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_contents.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';
#***** ★ DBテーブル読み書きファイル：インクルード ★ *****#
#転職のヒント
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/db_tips_articles.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$searchConditionsSessionKey = 'searchConditions_master05_01';
$pagePrefix = 'mKey05-01_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  #他ページの検索条件はページ移動時に破棄（このページの条件のみ保持）
  $isSearchConditionsKey = ($key === $searchConditionsSessionKey);
  if ($key !== 'sKey' && $key !== 'master_login' && $key !== $noUpDateKey && $isSearchConditionsKey === false) {
    unset($_SESSION[$key]);
  }
}
#セッション本体の初期化
$_SESSION[$noUpDateKey] = array();
#アカウントキー
$_SESSION[$noUpDateKey]['masterKey'] = $_SESSION['master_login']['account_id'];
#データ取得エラー
if ($_SESSION[$noUpDateKey]['masterKey'] < 1) {
  header("Location: ./logout.php");
  exit;
}

#-------------#
#検索・絞り込み条件保持用セッションチェック
$searchConditions = array();
if (isset($_SESSION[$searchConditionsSessionKey]) === false || !is_array($_SESSION[$searchConditionsSessionKey])) {
  #セッション無し：初期化
  $_SESSION[$searchConditionsSessionKey] = array(
    'startDay' => '',
    'endDay' => '',
    'sortTarget' => 'article_id',
    'idSortOrder' => 'desc',
    'updateDateSortOrder' => 'desc',
    'displayNumber' => $initialDisplayNumber,
    'pageNumber' => 1
  );
  #初期値セット
  $searchConditions = $_SESSION[$searchConditionsSessionKey];
} else {
  #既存セッションがあれば変数にセット
  $searchConditions = $_SESSION[$searchConditionsSessionKey];
}
#必須キーが欠けている場合は初期化（運用上は常に揃う前提）
$requiredKeys = ['startDay', 'endDay', 'sortTarget', 'idSortOrder', 'updateDateSortOrder', 'displayNumber', 'pageNumber'];
foreach ($requiredKeys as $requiredKey) {
  if (!array_key_exists($requiredKey, $searchConditions)) {
    $searchConditions = array(
      'startDay' => '',
      'endDay' => '',
      'initials' => array(),
      'sortTarget' => 'article_id',
      'idSortOrder' => 'desc',
      'updateDateSortOrder' => 'desc',
      'displayNumber' => $initialDisplayNumber,
      'pageNumber' => 1
    );
    break;
  }
}
$_SESSION[$searchConditionsSessionKey] = $searchConditions;
#-------------#
#表示件数ページ・表示件数設定
$displayNumber = isset($searchConditions['displayNumber']) ? intval($searchConditions['displayNumber']) : $initialDisplayNumber;
$pageNumber = isset($searchConditions['pageNumber']) ? intval($searchConditions['pageNumber']) : 1;
#-------------#
#検索項目フォームのアクティブ判定
$activeSearchForm = '';
if ($searchConditions['startDay'] !== '' || $searchConditions['endDay'] !== '') {
  $activeSearchForm = ' load is-active';
} else {
  $activeSearchForm = '';
}

#====================#
# 転職のヒント一覧取得
#--------------------#
#検索条件があれば適用して記事一覧を取得
if (is_array($searchConditions) && count($searchConditions) > 0) {
  $tipsArticlesList = searchTipsArticlesList($searchConditions, $pageNumber, $displayNumber);
} else {
  $tipsArticlesList = getTipsArticlesList();
}
#総件数（ページャー用）
$totalTipsArticlesCount = searchTipsArticlesCount($searchConditions);
$totalPages = (int)ceil($totalTipsArticlesCount / $displayNumber);
if ($totalPages < 1) {
  $totalPages = 1;
}
if ($pageNumber < 1) {
  $pageNumber = 1;
} elseif ($pageNumber > $totalPages) {
  $pageNumber = $totalPages;
}
#該当件数（表示用：総件数）
$tipsArticlesCount = $totalTipsArticlesCount;
#ソートボタンのアクティブ判定（番号・契約日 両方に付与）
$idSortOrder = isset($searchConditions['idSortOrder']) ? strtolower((string)$searchConditions['idSortOrder']) : 'desc';
$updateDateSortOrder = isset($searchConditions['updateDateSortOrder']) ? strtolower((string)$searchConditions['updateDateSortOrder']) : 'desc';
if ($idSortOrder !== 'asc' && $idSortOrder !== 'desc') {
  $idSortOrder = 'desc';
}
if ($updateDateSortOrder !== 'asc' && $updateDateSortOrder !== 'desc') {
  $updateDateSortOrder = 'desc';
}
$sortIdAscActive = '';
$sortIdDescActive = '';
$sortUpdateDateAscActive = '';
$sortUpdateDateDescActive = '';
if ($searchConditions['sortTarget'] === 'updated_at') {
  #主ソート：更新日（更新日のみアクティブ表示）
  $sortUpdateDateAscActive = ($updateDateSortOrder === 'asc') ? 'is-active' : '';
  $sortUpdateDateDescActive = ($updateDateSortOrder === 'asc') ? '' : 'is-active';
} else {
  #主ソート：番号（番号のみアクティブ表示）
  $sortIdAscActive = ($idSortOrder === 'asc') ? 'is-active' : '';
  $sortIdDescActive = ($idSortOrder === 'asc') ? '' : 'is-active';
}

#ソートモード判別（主ソートのみ：ページ移動等で維持する）
$sortMode = '';
if ($searchConditions['sortTarget'] === 'updated_at') {
  $sortMode = 'sortUpdateDate_' . strtolower($updateDateSortOrder);
} else {
  $sortMode = 'sortId_' . strtolower($idSortOrder);
}

#-------------#
/**
 * サムネイル画像パスをDB保存形式から相対パスに変換
 * @param string $path ファイルパス
 */
function tipsDbRelFromStoredPath($path)
{
  $path = (string)$path;
  if ($path === '') return '';
  $parsedPath = parse_url($path, PHP_URL_PATH);
  if (is_string($parsedPath) && $parsedPath !== '') {
    $path = $parsedPath;
  }
  $path = str_replace('\\', '/', $path);
  $pos = strpos($path, '/db/');
  if ($pos !== false) {
    return ltrim(substr($path, $pos + 4), '/');
  }
  if (strpos($path, 'db/') === 0) {
    return substr($path, 3);
  }
  return ltrim($path, '/');
}
/**
 * サムネイル画像パスを管理画面用URLに変換
 * @param string $path ファイルパス
 */
function tipsStoredPathToAdminUrl($path)
{
  $rel = tipsDbRelFromStoredPath($path);
  if ($rel === '') return '';
  $base = rtrim((string)DOMAIN_NAME, '/');
  return $base . '/db/' . ltrim($rel, '/');
}

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data: https://rita-work.jp; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master05.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-05-01-01">
      <section class="page-nav">
        <h2>運営管理</h2>
        <nav>
          <a href="./master05_01_01.php" class="is-active">転職のヒント</a>
          <a href="./master05_02.php">&emsp;ー&emsp;TOP表示設定</a>
          <a href="./master05_03_01.php">事業所へのお知らせ</a>
        </nav>
      </section>
      <section class="container-applicant-list">
        <h2>転職のヒント</h2>
        <form name="searchForm" class="block-search">
          <input type="hidden" name="noUpDateKey" value="{$noUpDateKey}">
          <button type="button" id="btnSwitchSearch" class="btn-switch {$activeSearchForm}" aria-controls="innerSearch" aria-expanded="false"></button>
          <h3>条件で検索</h3>
          <article id="innerSearch" class="{$activeSearchForm}">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-last-update">
                  <h4>最終更新日</h4>
                  <div class="wrap-period">
                    <input type="date" name="searchStartDay" value="{$searchConditions['startDay']}">
                    <span>〜</span>
                    <input type="date" name="searchEndDay" value="{$searchConditions['endDay']}">
                  </div>
                </li>
              </ul>
              <div class="box-btn">
                <button type="button" class="item-clear" onclick="searchConditions('reset','none')">条件をクリア</button>
                <button type="button" class="item-search" onclick="searchConditions('search','none')">条件で検索</button>
              </div>
            </div>
          </article>
        </form>
        <article class="block-search-results" data-current-sort-mode="{$sortMode}">
          <div class="box-head">
            <p class="announce-results">条件に<span>{$tipsArticlesCount}件</span>が該当</p>
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
print <<<HTML
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
  print <<<HTML
                  <li>
                    <input type="radio" name="displayNumber" id="display{$number}" value="{$number}"{$checked} onchange="searchConditions('search','none')">
                    <label for="display{$number}">{$number}</label>
                  </li>

HTML;
}
print <<<HTML
                </ul>
              </div>
            </div>
          </div>
          <ul class="list-search-results">
            <li>
              <div>
                番号
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortIdAscActive}" onclick="searchConditions('search','sortId_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortIdDescActive}" onclick="searchConditions('search','sortId_desc')"></button>
                </span>
              </div>
              <div>サムネイル</div>
              <div>記事</div>
              <div>ステータス</div>
              <div>
                最終更新日
                <span class="wrap-sort-btn">
                  <button type="button" class="arrow-top {$sortUpdateDateAscActive}" onclick="searchConditions('search','sortUpdateDate_asc')"></button>
                  <button type="button" class="arrow-bottom {$sortUpdateDateDescActive}" onclick="searchConditions('search','sortUpdateDate_desc')"></button>
                </span>
              </div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($tipsArticlesList) && count($tipsArticlesList) > 0) {
  $zIndexNo = count($tipsArticlesList);
  foreach ($tipsArticlesList as $articleKey => $article) {
    #Liのz-index設定
    $zIndexStyle = 'style="z-index:' . ($zIndexNo - $articleKey) . ';"';
    #記事ID
    $articleId = isset($article['article_id']) ? intval($article['article_id']) : 0;
    #記事コード
    $articleCode = isset($article['code']) ? htmlspecialchars($article['code'], ENT_QUOTES, 'UTF-8') : '';
    #記事タイトル
    $articleTitle = isset($article['title']) ? htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') : '';
    #記事本文プレーンテキスト
    $articleBodyText = isset($article['body_text']) ? htmlspecialchars($article['body_text'], ENT_QUOTES, 'UTF-8') : '';
    #公開ステータス
    $articleStatus = isset($article['status']) ? intval($article['status']) : 0;
    #登録日・更新日
    $articleCreatedAt = isset($article['created_at']) ? htmlspecialchars($article['created_at'], ENT_QUOTES, 'UTF-8') : '';
    $articleUpdatedAt = isset($article['updated_at']) ? htmlspecialchars($article['updated_at'], ENT_QUOTES, 'UTF-8') : '';
    #更新日フォーマット
    $formattedUpdatedAt = '';
    if ($articleUpdatedAt !== '') {
      $formattedUpdatedAt = date('Y/m/d', strtotime($articleUpdatedAt));
    } elseif ($articleCreatedAt !== '') {
      $formattedUpdatedAt = date('Y/m/d', strtotime($articleCreatedAt));
    }
    #サムネイル画像パス取得
    $tipsImagePath = '';
    if (isset($article['tips_image_path']) && $article['tips_image_path'] != null) {
      $tipsImageJsonDecoded = json_decode($article['tips_image_path'], true);
      if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
        $tipsImagePath = tipsStoredPathToAdminUrl($tipsImageJsonDecoded[0]);
      }
    }
    #公開ステータス「name」属性連番対応
    $statusName = 'list_status' . $articleId;
    #checked判定
    $checkedDraft = ($article['status'] == 'draft') ? 'checked' : '';
    $checkedPublic = ($article['status'] == 'public') ? 'checked' : '';
    #value値／label設定
    $valueName = ($article['status'] == 'draft') ? 'draft' : 'public';
    $labelName = ($article['status'] == 'draft') ? '下書き中' : '公開中';
    print <<<HTML
            <!-- NOTE  インラインでz-indexを付与 -->
            <li {$zIndexStyle} onclick="location.href='./master05_01_02.php?method=edit&articleId={$articleId}'">
              <div class="item-number">{$articleId}</div>
              <div class="item-image">

HTML;
    if ($tipsImagePath !== '') {
      print <<<HTML
                <picture>
                  <source src="{$tipsImagePath}">
                  <img src="{$tipsImagePath}" alt="サムネイル">
                </picture>

HTML;
    }
    print <<<HTML
              </div>
              <div class="item-details">
                <p class="title">{$articleTitle}</p>
                <p class="contents">{$articleBodyText}</p>
              </div>
              <div class="box-status" onclick="event.stopPropagation();">
                <div class="select-status" data-selectbox>
                  <button type="button" class="selectbox__head" aria-expanded="false">
                    <input type="hidden" name="{$statusName}" value="{$valueName}" data-selectbox-hidden>
                    <span class="selectbox__value" data-selectbox-value>{$labelName}</span>
                    <i></i>
                  </button>
                  <div class="list-wrapper">
                    <ul class="selectbox__panel">
                      <li>
                        <input type="radio" name="{$statusName}" value="draft" id="list{$articleId}-status01" {$checkedDraft} onchange="checkTipsStatus({$articleId}, '{$articleCode}',this.value,'');">
                        <label for="list{$articleId}-status01" class="status-draft">下書き中</label>
                      </li>
                      <li>
                        <input type="radio" name="{$statusName}" value="public" id="list{$articleId}-status02" {$checkedPublic} onchange="checkTipsStatus({$articleId}, '{$articleCode}',this.value,'');">
                        <label for="list{$articleId}-status02" class="status-published">公開中</label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="item-date">{$formattedUpdatedAt}</div>
            </li>

HTML;
  }
} else {
  print <<<HTML
            <li class="no-data" style="display:flex;justify-content:center;align-items:center;padding:2em 0;">
              <div>該当するデータが存在しません。</div>
            </li>

HTML;
}
print <<<HTML
          </ul>

HTML;
#ページャー表示
print makePagerBoxTag((int)$pageNumber, (int)$totalPages, $pagerDisplayMax, 'movePage');
print <<<HTML
        </article>
        <div class="bottom-box-btn">
          <button type="button" class="item-register" onclick="checkNewTips()"><span>新規記事登録</span></button>
        </div>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>新規記事登録</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>新規記事を作成します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="closeModalToPage('master05_01_02.php?method=new')">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/master05_01_01.js" defer></script>
  </body>
</html>

HTML;
