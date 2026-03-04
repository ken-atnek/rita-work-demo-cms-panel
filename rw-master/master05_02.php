<?php
/*
 * [rw-master/master05_02.php]
 *  - 管理画面 -
 *  運営管理：転職のヒント「トップ表示設定」
 *
 * [初版]
 *  2026.02.09
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
$pagePrefix = 'mKey05-02_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  if ($key !== 'sKey' && $key !== 'master_login' && $key !== $noUpDateKey) {
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

#===============================#
# 転職のヒントトップページ一覧取得
#-------------------------------#
$tipsArticlesList = getTipsArticlesTopList();

#-------------#
// NOTE: 既存共通関数との再定義衝突を避ける（将来の共通化に備える）
if (!function_exists('tipsDbRelFromStoredPath')) {
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
}
if (!function_exists('tipsStoredPathToAdminUrl')) {
  function tipsStoredPathToAdminUrl($path)
  {
    $rel = tipsDbRelFromStoredPath($path);
    if ($rel === '') return '';
    $base = rtrim((string)DOMAIN_NAME, '/');
    return $base . '/db/' . ltrim($rel, '/');
  }
}

#-------------#
#inline JS用エスケープ
$jsonHex = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$noUpDateKeyJs = json_encode((string)$noUpDateKey, $jsonHex);
$noUpDateKeyJsAttr = htmlspecialchars((string)$noUpDateKeyJs, ENT_QUOTES, 'UTF-8');

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
    <main class="inner-05-02">
      <section class="page-nav">
        <h2>運営管理</h2>
        <nav>
          <a href="./master05_01_01.php">転職のヒント</a>
          <a href="./master05_02.php" class="is-active">&emsp;ー&emsp;TOP表示設定</a>
          <a href="./master05_03_01.php">事業所へのお知らせ</a>
        </nav>
      </section>
      <section class="container-article-list">
        <h2>TOP表示設定</h2>
        <article class="block-results">
          <ul class="list-search-results">
            <li>
              <div class="title-number">表示順</div>
              <div class="title-article">記事</div>
            </li>

HTML;
#表示可能リストあればループで差し込む
if (is_array($tipsArticlesList) && count($tipsArticlesList) > 0) {
  $zIndexNo = count($tipsArticlesList);
  foreach ($tipsArticlesList as $articleKey => $article) {
    #Liのz-index設定
    $zIndexStyle = 'style="z-index:' . ($zIndexNo - $articleKey) . ';"';
    #表示順番号
    $listNo = $articleKey + 1;
    #記事ID
    $articleId = isset($article['article_id']) ? intval($article['article_id']) : 0;
    #記事タイトル
    $articleTitle = isset($article['title']) ? htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8') : '';
    #記事本文プレーンテキスト
    $articleBodyText = isset($article['body_text']) ? htmlspecialchars($article['body_text'], ENT_QUOTES, 'UTF-8') : '';
    #サムネイル画像パス取得
    $tipsImagePath = '';
    if (isset($article['tips_image_path']) && $article['tips_image_path'] != null) {
      $tipsImageJsonDecoded = json_decode($article['tips_image_path'], true);
      if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
        $tipsImagePath = tipsStoredPathToAdminUrl($tipsImageJsonDecoded[0]);
      }
    }
    $tipsImagePathEsc = htmlspecialchars((string)$tipsImagePath, ENT_QUOTES, 'UTF-8');
    print <<<HTML
            <!-- NOTE インラインでz-indexを付与 -->
            <li {$zIndexStyle} data-article-id="{$articleId}" draggable="true">
              <div class="item-number">{$listNo}</div>
              <div class="item-image">
                <picture>
                  <source srcset="{$tipsImagePathEsc}">
                  <img src="{$tipsImagePathEsc}" alt="サムネイル">
                </picture>
              </div>
              <div class="item-details">
                <p class="title">{$articleTitle}</p>
                <p class="contents">{$articleBodyText}</p>
              </div>
              <div class="item-icon"><a href="./master05_01_02.php?method=edit&articleId={$articleId}" class="btn-edit"></a></div>
              <div class="item-icon">
                <form>
                  <button type="button" class="btn-top" onclick="moveLows('move','top',{$articleId},{$noUpDateKeyJsAttr})"></button>
                </form>
              </div>
              <div class="item-icon">
                <form>
                  <button type="button" class="btn-bottom" onclick="moveLows('move','bottom',{$articleId},{$noUpDateKeyJsAttr})"></button>
                </form>
              </div>
              <div class="item-icon">
                <form>
                  <button type="button" class="btn-move"></button>
                </form>
              </div>
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
        </article>
        <article class="block-premium-announce">
          <div class="box-contents">
            <h3>記事の表示/非表示について</h3>
            <p>
              記事の表示/非表示こちらのページでは設定できません。｢転職のヒント］→｢転職のヒント情報｣にて設定が行えます。
            </p>
          </div>
        </article>
        <div class="bottom-box-btn">
          <button type="button" class="item-register" onclick="checkMoveLows();"><span>登録する</span></button>
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
          <p>トップ表示設定</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>記事の並び順を変更します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn-confirm" onclick="saveLows()">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script>window.__NO_UP_DATE_KEY__ = {$noUpDateKeyJs};</script>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script src="./assets/js/master05_02.js" defer></script>
  </body>
</html>

HTML;
