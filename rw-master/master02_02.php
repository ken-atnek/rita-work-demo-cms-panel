<?php
/*
 * [rw-master/master02_02.php]
 *  - 管理画面 -
 *  法人登録／編集
 *
 * [初版]
 *  2025.12.18
 */

#***** 定数定義ファイル：インクルード *****#
require_once dirname(__DIR__) . '/cms_config/common/define.php';
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
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'mKey02-02_';
#このページのユニークなセッションキーを生成
$noUpDateKey = $pagePrefix . bin2hex(random_bytes(8));
$_SESSION['sKey'] = $noUpDateKey;
#不要なセッション削除
foreach ($_SESSION as $key => $val) {
  #一覧（master02_01）の検索条件だけ保持（戻る操作で条件保持するため）
  $isSearchConditionsKey = ($key === 'searchConditions_master02_01');
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

#=============#
# POSTチェック
#-------------#
#新規／編集
$method = isset($_GET['method']) ? $_GET['method'] : null;
#モードチェック
if ($method === null || ($method !== 'new' && $method !== 'edit')) {
  #不正アクセス：トップページへリダイレクト
  header("Location: ./master02_01.php");
  exit;
}
#-------------#
#法人ID（編集／削除時のみ）
$corpId = isset($_GET['corpId']) ? $_GET['corpId'] : null;
#法人IDがあれば法人情報取得
if ($corpId !== null) {
  $corporationData = getCorporations_FindById_Code($corpId, null);
} else {
  $corporationData = array(
    'name' => '',
    'name_kana' => '',
    'postal_code' => '',
    'prefecture' => '',
    'city' => '',
    'address_line' => '',
    'phone' => '',
    'email' => ''
  );
}

#===============================#
# メニュータイトル／日付初期値設定
#-------------------------------#
#メニュータイトル
$menuTitle = "法人情報";
$sideMenuTitle = "法人管理";
#契約日初期値
$today = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
$contractDate = $today->format('Y-m-d');
if ($method === 'new') {
  $menuTitle = "新規法人登録";
  $sideMenuTitle = "新規法人管理";
} elseif ($method === 'edit') {
  if (!isset($corporationData) || empty($corporationData)) {
    #法人データが無い場合は不正アクセス：トップページへリダイレクト
    header("Location: ./master02_01.php");
    exit;
  } else {
    $menuTitle = "法人情報 - " . htmlspecialchars($corporationData['name'], ENT_QUOTES, 'UTF-8');
    $contractDate = $corporationData['contract_date'];
  }
  $sideMenuTitle = "法人情報";
}

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; connect-src 'self' https://zipcloud.ibsnet.co.jp; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master02.css">
  </head>

  <body>
HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-02-02">
      <section class="page-nav">
        <h2>法人管理</h2>
        <nav>
          <span>{$sideMenuTitle}</span>
        </nav>
      </section>
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
              <div class="input-date"><input type="date" name="contract_date" value="{$contractDate}" class="required-item" required></div>
            </dd>
          </dl>
          <dl>
            <dt>法人名</dt>
            <dd><input type="text" name="company_name" value="{$corporationData['name']}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt>ふりがな</dt>
            <dd><input type="text" name="company_name_kana" value="{$corporationData['name_kana']}" class="required-item" required></dd>
          </dl>
          <dl>
            <dt>住所</dt>
            <dd class="item-add">
              <input type="text" name="address1" value="{$corporationData['postal_code']}" class="required-item" required id="zipCode">
              <input type="text" name="address2" value="{$corporationData['prefecture']}{$corporationData['city']}">
              <input type="text" name="address3" value="{$corporationData['address_line']}">
            </dd>
          </dl>
          <dl>
            <dt>電話番号</dt>
            <dd><input type="text" name="phone_number" value="{$corporationData['phone']}" autocomplete="on" class="required-item phone_number" required></dd>
          </dl>
          <dl>
            <dt>E-mail</dt>
            <dd><input type="text" name="email" value="{$corporationData['email']}" autocomplete="on" class="required-item email" required></dd>
          </dl>
        </form>
        <div class="bottom-box-btn">
          <button type="button" class="item-back" onclick="history.back()">戻る</button>
          <button type="button" class="item-check" onclick="checkInput()">入力を確認する</button>
        </div>

HTML;
if ($method === 'edit') {
  print <<<HTML
        <!--NOTE 修正画面のみ表示 -->
        <button type="button" class="btn-delate-item" onclick="checkDeleteCorporation({$corpId},'{$corporationData['name']}')">削除する</button>

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
          <p>新規事業所削除</p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <p>新規法人情報を削除します。よろしいですか？</p>
          <div class="box-btn">
            <button type="button" class="btn-cancel">キャンセル</button>
            <button type="button" class="btn-confirm">はい</button>
          </div>
        </div>
      </div>
    </article>
    <script type="text/javascript" src="../assets/js/modal.js"></script>
    <script type="text/javascript" src="../assets/js/form.js"></script>
    <script type="text/javascript" src="./assets/js/master02_02.js"></script>
  </body>
</html>

HTML;
