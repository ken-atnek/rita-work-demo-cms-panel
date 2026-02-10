<?php
/*
 * [rw-master/master05_01_02.php]
 *  - 管理画面 -
 *  運営管理：転職のヒント登録／編集
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
$pagePrefix = 'mKey05-01_';
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

#=============#
# POSTチェック
#-------------#
#新規／編集
$method = isset($_GET['method']) ? $_GET['method'] : null;
#モードチェック
if ($method === null || ($method !== 'new' && $method !== 'edit')) {
  #不正アクセス：トップページへリダイレクト
  header("Location: ./master05_01_01.php");
  exit;
}
#-------------#
#転職のヒント記事取得（編集／削除時のみ）
$articleId = isset($_GET['articleId']) ? $_GET['articleId'] : null;
#法人IDがあれば法人情報取得
if ($articleId !== null) {
  $articleData = getTipsArticles_FindById($articleId);
} else {
  $articleData = array(
    'article_id' => '',
    'code' => '',
    'status' => '',
    'is_top' => '',
    'top_sort' => '',
    'title' => '',
    'body_text' => '',
    'body_json' => '',
    'tips_image_path' => '',
    'updated_at' => ''
  );
}

#===================#
# タイトルEscape処理
#-------------------#
$viewTitleEsc = "";
$setTitleEsc = "";
if ($articleData['title'] != '') {
  $viewTitleEsc = htmlspecialchars($articleData['title'], ENT_QUOTES, 'UTF-8');
  $setTitleEsc = $viewTitleEsc;
} else {
  $viewTitleEsc = "新規記事登録";
  $setTitleEsc = "";
}

#===================#
# 表示用データ整形
#-------------------#
$articleIdInt = (int)($articleData['article_id'] ?? 0);
$isTopChecked = (int)($articleData['is_top'] ?? 0) === 1 ? ' checked' : '';
#表示期間（オプション）
# - UIでのON/OFFは持たず、ページ側のフラグで機能自体の有効/無効を切替
# - 無効化する場合：このフラグを false にすると、表示期間の入力UIを出さず、初期値 type を 'none' に固定
$periodFeatureEnabled = false;
$initialPeriodType = $periodFeatureEnabled ? 'from' : 'none';
$initialPeriodFrom = '';
$initialPeriodTo = '';
#jsonデコード（本文：初期化）
$initialBodyJson = null;
if (isset($articleData['body_json']) && (string)$articleData['body_json'] !== '') {
  $decoded = json_decode((string)$articleData['body_json'], true);
  if (json_last_error() === JSON_ERROR_NONE) {
    #新形式: { editor: <tiptap-json>, period: {type,from,to} }
    if (is_array($decoded) && isset($decoded['editor']) && is_array($decoded['editor'])) {
      $initialBodyJson = $decoded['editor'];
      if ($periodFeatureEnabled && isset($decoded['period']) && is_array($decoded['period'])) {
        $initialPeriodType = isset($decoded['period']['type']) ? (string)$decoded['period']['type'] : 'from';
        $initialPeriodFrom = isset($decoded['period']['from']) ? (string)$decoded['period']['from'] : '';
        $initialPeriodTo = isset($decoded['period']['to']) ? (string)$decoded['period']['to'] : '';
      }
    } else {
      #旧形式: tiptap-json そのもの
      $initialBodyJson = $decoded;
    }
  }
}

#-------------#
/**
 * tips画像のパス/URLを、DB保存用の「/db を含まない相対パス」へ正規化する（ローカル版）
 *
 * 目的:
 * - 編集画面で TipTap に渡す初期JSONは「管理画面で参照できるURL（DOMAIN_NAME + /db/...）」へ変換する。
 * - その変換の前段として、DBに入っている値が
 *   - tips/...（DB相対）
 *   - /db/tips/...（フロント相対）
 *   - https://.../db/tips/...（ドメイン付き）
 *   のいずれでも同じ形式に寄せられるようにする。
 *
 * 入力例:
 * - 'tips/tips_0001/image1.jpg'
 * - '/db/tips/tips_0001/image1.jpg'
 * - 'https://example.com/db/tips/tips_0001/image1.jpg'
 *
 * 返り値:
 * - 'tips/tips_0001/image1.jpg' のようなDB相対パス（空/不正は ''）
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
 * tips画像のパス/URLを、管理画面プレビュー用URLへ変換する（ローカル版）
 *
 * - 返り値は DOMAIN_NAME + '/db/' + <DB相対> の形式
 * - src属性にそのまま渡せる URL を作るために使用
 */
function tipsStoredPathToAdminUrl($path)
{
  $rel = tipsDbRelFromStoredPath($path);
  if ($rel === '') return '';
  $base = rtrim((string)DOMAIN_NAME, '/');
  return $base . '/db/' . ltrim($rel, '/');
}
/**
 * TipTap JSON の image.attrs.src を再帰的に書き換え、管理画面で表示できるURLに変換する（ローカル版）
 *
 * 仕様:
 * - http(s) の外部URLはここでは触らない（そのまま表示させる）
 * - tmp_upload 配下（プレビュー用/ドラフト用）のURLもここでは触らない
 * - DB相対や /db 相対の src は DOMAIN_NAME + /db/... に変換してプレビューできるようにする
 *
 * 注意:
 * - 保存時（AJAX側）では、管理画面URLをDB相対へ戻す正規化が別途行われる前提。
 */
function rewriteTiptapJsonImageSrcsToAdminUrl(&$node)
{
  if (!is_array($node)) return;
  if (isset($node['type']) && $node['type'] === 'image') {
    if (isset($node['attrs']) && is_array($node['attrs']) && isset($node['attrs']['src'])) {
      $src = (string)$node['attrs']['src'];
      if ($src !== '' && !preg_match('/^https?:\/\//i', $src) && strpos($src, (string)DEFINE_PREVIEW_IMAGE_DIR_PATH) !== 0) {
        $node['attrs']['src'] = tipsStoredPathToAdminUrl($src);
      }
    }
  }
  if (isset($node['content']) && is_array($node['content'])) {
    foreach ($node['content'] as $i => $child) {
      rewriteTiptapJsonImageSrcsToAdminUrl($node['content'][$i]);
    }
  }
}

#-------------#
#initialBodyJson画像パス書き換え
if (is_array($initialBodyJson)) {
  rewriteTiptapJsonImageSrcsToAdminUrl($initialBodyJson);
}
#initialBodyHtml生成
$initialBodyHtml = '';
if (isset($articleData['body_text']) && (string)$articleData['body_text'] !== '') {
  $initialBodyHtml = (string)$articleData['body_text'];
}
#inline JS（onclick等）用
$jsonHex = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$rwTipsConfigJs = json_encode([
  'noUpDateKey' => (string)$noUpDateKey,
  'method' => (string)$method,
  'articleId' => $articleIdInt,
  'initialBodyJson' => $initialBodyJson,
  'initialBodyHtml' => $initialBodyHtml,
  'periodFeatureEnabled' => (bool)$periodFeatureEnabled,
  'initialPeriodType' => (string)$initialPeriodType,
  'initialPeriodFrom' => (string)$initialPeriodFrom,
  'initialPeriodTo' => (string)$initialPeriodTo,
], $jsonHex | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
#CSP nonce（inline script 用）
$cspNonce = base64_encode(random_bytes(16));
$cspNonceEsc = htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8');
$cspMeta = "default-src 'self'; img-src 'self' data: https://rita-work.jp; style-src 'self' 'unsafe-inline'; script-src 'self' https://esm.sh 'nonce-{$cspNonce}'; script-src-elem 'self' https://esm.sh 'nonce-{$cspNonce}'; script-src-attr 'unsafe-inline'; connect-src 'self' https://esm.sh;";

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="{$cspMeta}">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/tiptap_app.css">
    <link rel="stylesheet" href="../assets/css/master05.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-05-01-02">
      <section class="page-nav">
        <h2>運営管理</h2>
        <nav>
          <a href="./master05_01_01.php" class="is-active">転職のヒント</a>
          <a href="./master05_02.php">&emsp;ー&emsp;TOP表示設定</a>
          <a href="./master05_03_01.php">事業所へのお知らせ</a>
        </nav>
      </section>
      <section class="container-register">
        <a href="./master05_01_01.php" class="link-page-back">戻る</a>
        <h2>転職のヒント<span>{$viewTitleEsc}</span></h2>
        <form name="inputForm" class="block-form" id="tipsForm">
          <input type="hidden" name="noUpDateKey" id="noUpDateKey" value="{$noUpDateKey}">
          <input type="hidden" name="method" id="method" value="{$method}">
          <input type="hidden" name="action" value="checkInput">
          <input type="hidden" name="articleId" id="articleId" value="{$articleIdInt}">
          <dl>
            <dt class="is-required">タイトル</dt>
            <dd class="dd-top">
              <input type="text" name="title" value="{$setTitleEsc}">
              <div class="item-switch">
                <span>TOPページに表示</span>
                <div class="wrap-toggle-button">
                  <label class="toggle-button">
                    <input type="checkbox" name="is_top" value="1" {$isTopChecked}>
                  </label>
                </div>
              </div>
            </dd>
          </dl>

HTML;
#表示期間選択エリア：$periodFeatureEnabledフラグ設定 (true: 表示／ false: 非表示)
if ($periodFeatureEnabled) {
  print <<<HTML
          <dl>
            <dt class="position-top">表示期間</dt>
            <dd>
              <div class="periodBox">
                <div class="periodTypes">
                  <label><input type="radio" name="periodType" value="from">〇月〇日から表示</label>
                  <label><input type="radio" name="periodType" value="registered_to">登録日から〇月〇日まで表示</label>
                  <label><input type="radio" name="periodType" value="from_to">〇月〇日から〇月〇日まで表示</label>
                </div>
                <div class="periodDates">
                  <div class="field" id="periodFromWrap">
                    <span>開始</span>
                    <input type="date" id="periodFrom">
                  </div>
                  <div class="field" id="periodToWrap">
                    <span>終了</span>
                    <input type="date" id="periodTo">
                  </div>
                  <div class="small" id="registeredAtInfo"></div>
                </div>
              </div>
            </dd>
          </dl>

HTML;
}
print <<<HTML
          <dl>
            <dt class="position-top">メニュー</dt>
            <dd>
              <div class="toolbar" id="toolbar">
                <button type="button" data-cmd="bold">B</button>
                <button type="button" data-cmd="italic">I</button>
                <button type="button" data-cmd="strike">S</button>
                <button type="button" data-cmd="h4">H1</button>
                <button type="button" data-cmd="h5">H2</button>
                <button type="button" data-cmd="bullet">箇条書き</button>
                <button type="button" data-cmd="ordered">番号</button>
                <span class="sep" aria-hidden="true"></span>
                <input type="color" id="textColor" value="#000000" title="文字色">
                <button type="button" data-cmd="unsetColor">色解除</button>
                <span class="sep" aria-hidden="true"></span>
                <button type="button" data-cmd="imageUpload">画像アップロード</button>
                <input id="imageInput" type="file" accept="image/*" multiple style="display:none">
                <div class="hint">ヒント：画像は「ボタン」または「ドラッグ＆ドロップ」「貼り付け（Ctrl+V）」でも挿入できます。</div>
              </div>
            </dd>
          </dl>
          <dl>
            <dt class="is-required position-top">本文</dt>
            <dd class="dd-editor"><div class="editor" id="TipTapEditor"></div><!--<textarea name="body_text"></textarea>--></dd>
          </dl>
          <dl>
            <dt class="is-required position-top">サムネイル<br />画像</dt>
            <dd class="edit-image">
              <!-- NOTE 画像登録時は [is-active]付与 -->
              <div class="select-image" id="js-dragDrop-tipsImage">
                <h4>ここにファイルをドロップ</h4>
                <span>または</span>
                <input type="file" name="images_tmp" id="js-fileElem-tipsImage" multiple accept="image/*" style="display:none">
                <input type="hidden" name="tips_image" value="">
                <input type="hidden" name="upload_image_mode" value="only" id="js-uploadImageMode-tipsImage">
                <input type="hidden" name="upload_image_area" value="tips_image" id="js-uploadImageArea-tipsImage">
                <input type="hidden" name="up_image_area[]" value="tips_image">
                <input type="hidden" name="send_php" value="proc_master05_01_02.php">
                <button type="button" id="js-fileSelect-tipsImage">ファイルを選択</button>
                <span>※縦横サイズがオーバーしている場合は自動でリサイズされます</span>
                <!-- NOTE 警告用表示 -->
                <div class="wrap-caution" id="js-fileError-tipsImage" style="display: none;">
                  <h5>ファイルサイズが大きすぎます</h5>
                  <p>画像の容量を圧縮してしてから再度アップロードしてください。</p>
                </div>
              </div>

HTML;
if (isset($articleData['tips_image_path']) && $articleData['tips_image_path'] != null) {
  $tipsImagePath = '';
  #登録画像リスト展開
  $tipsImageJsonDecoded = json_decode($articleData['tips_image_path'], true);
  if (is_array($tipsImageJsonDecoded) && isset($tipsImageJsonDecoded[0]) && is_string($tipsImageJsonDecoded[0])) {
    $tipsImagePath = $tipsImageJsonDecoded[0];
  }
  if ($tipsImagePath === '') {
    #画像情報が壊れている/空の場合はプレビュー無し扱い
    print <<<HTML
              <ul class="selected-image-list" id="js-previewBlock-tipsImage" style="display: none;"></ul>

HTML;
  } else {
    $ext = strtolower(pathinfo($tipsImagePath, PATHINFO_EXTENSION));
    switch ($ext) {
      case 'jpg':
      case 'jpeg':
        $mimeType = 'image/jpeg';
        break;
      case 'png':
        $mimeType = 'image/png';
        break;
      case 'gif':
        $mimeType = 'image/gif';
        break;
      default:
        $mimeType = '';
        break;
    }
    $previewPath = tipsStoredPathToAdminUrl($tipsImagePath);
    print <<<HTML
              <ul class="selected-image-list" id="js-previewBlock-tipsImage">
                <li>
                  <div class="warp-btn">
                    <button type="button" class="btn-change"></button>
                    <button type="button" class="btn-delate"></button>
                  </div>
                  <picture>
                    <source src="{$previewPath}" type="{$mimeType}">
                    <img src="{$previewPath}" alt="ロゴ画像プレビュー">
                  </picture>
                </li>
              </ul>

HTML;
  }
} else {
  print <<<HTML
              <ul class="selected-image-list" id="js-previewBlock-tipsImage" style="display: none;"></ul>

HTML;
}
print <<<HTML
              <div class="wrap-notice">
                <p>※画像は1枚登録してください</p>
                <p>※JPG、PNG、GIF形式の画像ファイルが登録できます</p>
                <p>※1ファイルあたり最大10MBのファイルが登録できます</p>
                <p>※横1200px、縦675px以上の画像の登録を推奨しています</p>
              </div>
            </dd>
          </dl>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="location.href='master05_01_01.php'">戻る</button>
          <button type="button" class="item-check" onclick="checkInput()">入力を確認する</button>
        </div>

HTML;
if ($method === 'edit') {
  print <<<HTML
        <!--NOTE 修正画面のみ表示 -->
        <button type="button" class="btn-delate-item" onclick="checkDeleteTips('{$articleId}','{$noUpDateKey}')">削除する</button>

HTML;
}
print <<<HTML
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <!-- NOTE 修正画面用 is-active付与(bg-orange or bg-black)でモーダル表示 -->
    <article class="modal-alert" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>新規記事情報</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>登録が完了しました。</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel">一覧に戻る</button>
          </div>
        </div>
      </div>
    </article>
    <!-- NOTE 画像アップロード用ドロップゾーン（Simple editor風） -->
    <article class="tipTap_dropzone" id="dropzoneOverlay" hidden>
      <div class="dropzone-card" id="dropzoneCard" role="button" tabindex="0" aria-label="画像をドラッグ＆ドロップ、またはクリックしてファイルを選択">
        <button type="button" class="btn-tiptap-close" id="dropzoneClose"></button>
        <div class="dropzone-title">画像をアップロード</div>
        <div class="dropzone-sub">
          ここに画像ファイルをドラッグ＆ドロップしてください。<br>
          または、このエリアをクリックしてファイルを選択できます。
        </div>
        <div class="dropzone-actions">
          <button type="button" id="dropzonePick">ファイルを選択</button>
        </div>
        <div class="dropzone-note">対応形式：jpg / png / gif / webp（最大 5MB）</div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script src="../assets/js/form.js" defer></script>
    <script src="../assets/js/dropZone.js" defer></script>
    <script src="../assets/js/modal.js" defer></script>
    <script nonce="{$cspNonceEsc}">
    window.RW_MASTER05_01_02 = {$rwTipsConfigJs};
    window.RW_TIPTAP_INITIAL = {
      json: window.RW_MASTER05_01_02.initialBodyJson || null,
      html: window.RW_MASTER05_01_02.initialBodyHtml || '',
    };
    //複数アップロードエリア対応：ID命名規則に従い全領域を初期化
    document.addEventListener('DOMContentLoaded', function() {
      //対象となるアップロードエリアのIDリスト：areaIdsを増やすことで複数アップロードエリア対応可能
      const areaIds = ['tipsImage'];
      areaIds.forEach(function(area) {
        let drop = document.getElementById('js-dragDrop-' + area);
        let btn = document.getElementById('js-fileSelect-' + area);
        let input = document.getElementById('js-fileElem-' + area);
        let inputMode = document.getElementById('js-uploadImageMode-' + area);
        let inputArea = document.getElementById('js-uploadImageArea-' + area);
        let preview = document.getElementById('js-previewBlock-' + area);
        let error = document.getElementById('js-fileError-' + area);
        //1枚登録モード時はドラッグ＆ドロップエリアを非表示
        if (inputMode && inputMode.value === 'only' && drop) {
            const liCount = preview.querySelectorAll('li').length;
            if (liCount >= 1) {
              drop.classList.add('is-active');
            } else {
              drop.classList.remove('is-active');
            }
        }
        if (drop && btn && input && preview) {
          initDropZone({
            dropZone: drop,
            selectFileButton: btn,
            fileInput: input,
            inputMode: inputMode,
            inputArea: inputArea,
            previewBlock: preview,
            fileError: error
          });
        }
      });
    });
    </script>
    <script src="./assets/js/master05_01_02.js" defer></script>
    <script type="module" src="../assets/lib/TipTap/js/tiptap_app.js"></script>
  </body>
</html>

HTML;
