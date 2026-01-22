<?php
/*
 * [rw-master/master01_01.php]
 *  - 管理画面 -
 *  応募者一覧
 *
 * [初版]
 *  2025.12.15
 */

#***** 定数定義ファイル：インクルード *****#
require_once $_SERVER['DOCUMENT_ROOT'] . '/cms_config/common/define.php';
#***** 定数・関数宣言ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/common/set_function.php';
#***** DB設定ファイル：インクルード *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/database/set_db.php';
#***** ★ 処理開始：セッション宣言ファイルインクルード ★ *****#
require_once DOCUMENT_ROOT_PATH . '/cms_config/master/start_processing.php';

#================#
# SESSIONチェック
#----------------#
#セッションキー
$pagePrefix = 'mKey01-01_';
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

#***** タグ生成開始 *****#
print <<<HTML
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta name="format-detection" content="telephone=no">
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico">
    <link rel="stylesheet" href="../assets/css/master01.css">
  </head>

  <body>

HTML;
@include './inc_header.php';
print <<<HTML
    <main class="inner-01-01">
      <section class="container-status">
        <h2>現在の応募状況</h2>
        <nav class="block-status">
          <button type="button" class="status-registered is-active">
            <span class="label">登録中</span>
            <span class="count">100</span>
          </button>
          <button type="button" class="status-applied">
            <span class="label">応募中</span>
            <span class="count">800</span>
          </button>
          <button type="button" class="status-interview">
            <span class="label">面談中</span>
            <span class="count">150</span>
          </button>
          <button type="button" class="status-hired">
            <span class="label">内定</span>
            <span class="count">1200</span>
          </button>
          <button type="button" class="status-unresponsive">
            <span class="label">音信不通</span>
            <span class="count">1020</span>
          </button>
        </nav>
        <article class="block-search-results">
          <div class="box-head">
            <p class="announce-results">条件に<span>123件</span>が該当</p>
            <div class="list-display" data-selectbox>
              <button type="button" class="selectbox__head" aria-expanded="false">
                <input type="hidden" name="display" value="10" data-selectbox-hidden />
                <span class="selectbox__value" data-selectbox-value>10</span>
              </button>
              <div class="list-wrapper">
                <ul class="selectbox__panel">
                  <li>
                    <input type="radio" name="display" id="display01" value="10" checked />
                    <label for="display01">10</label>
                  </li>
                  <li>
                    <input type="radio" name="display" id="display02" value="20" />
                    <label for="display02">20</label>
                  </li>
                  <li>
                    <input type="radio" name="display" id="display03" value="30" />
                    <label for="display03">30</label>
                  </li>
                  <li>
                    <input type="radio" name="display" id="display04" value="50" />
                    <label for="display04">50</label>
                  </li>
                  <li>
                    <input type="radio" name="display" id="display05" value="100" />
                    <label for="display05">100</label>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <ul class="list-search-results is-admin">
            <li>
              <div>名前</div>
              <div>職種</div>
              <div>応募先</div>
              <div>応募状況</div>
              <div>応募日</div>
            </li>
            <!-- NOTE  インラインでz-indexを付与 -->
            <li style="z-index: 5">
              <div class="item-name">山田 太郎</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケア癒やしDX花園</span>
                  </div>
                  <div class="wrap-apply-status">
                    <!--NOTE  連番注意　list01-status- -->
                    <div class="apply-status" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input
                          type="hidden"
                          name="ApplyStatus01Method"
                          value="1"
                          data-selectbox-hidden
                        />
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>
                        <i></i>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="1"
                              id="list01-status01"
                              checked
                            />
                            <label for="list01-status01" class="status-registered">登録中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="2"
                              id="list01-status02"
                            />
                            <label for="list01-status02" class="status-applied">応募中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="3"
                              id="list01-status03"
                            />
                            <label for="list01-status03" class="status-interview">面談中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="4"
                              id="list01-status04"
                            />
                            <label for="list01-status04" class="status-hired">内定</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="5"
                              id="list01-status05"
                            />
                            <label for="list01-status05" class="status-unresponsive"
                              >音信不通</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date">2025/10/24</div>
                </li>
                <li>
                  <div class="item-job"><span>言語聴覚士</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケアあああ</span>
                  </div>
                  <div class="wrap-apply-status">
                    <!--NOTE  連番注意　list01-status- -->
                    <div class="apply-status" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input
                          type="hidden"
                          name="ApplyStatus02Method"
                          value="1"
                          data-selectbox-hidden
                        />
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>
                        <i></i>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="1"
                              id="list02-status01"
                              checked
                            />
                            <label for="list02-status01" class="status-registered">登録中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="2"
                              id="list02-status02"
                            />
                            <label for="list02-status02" class="status-applied">応募中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="3"
                              id="list02-status03"
                            />
                            <label for="list02-status03" class="status-interview">面談中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="4"
                              id="list02-status04"
                            />
                            <label for="list02-status04" class="status-hired">内定</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="5"
                              id="list02-status05"
                            />
                            <label for="list02-status05" class="status-unresponsive"
                              >音信不通</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date">2025/9/22</div>
                </li>
              </ul>
            </li>
            <!-- NOTE  インラインでz-indexを付与 -->
            <li style="z-index: 4">
              <div class="item-name">森山 ハナコ</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケア癒やしDX花園</span>
                  </div>
                  <div class="wrap-apply-status">
                    <!--NOTE  連番注意　list01-status- -->
                    <div class="apply-status" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input
                          type="hidden"
                          name="ApplyStatus03Method"
                          value="1"
                          data-selectbox-hidden
                        />
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>
                        <i></i>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="1"
                              id="list03-status01"
                              checked
                            />
                            <label for="list03-status01" class="status-registered">登録中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="2"
                              id="list03-status02"
                            />
                            <label for="list03-status02" class="status-applied">応募中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="3"
                              id="list03-status03"
                            />
                            <label for="list03-status03" class="status-interview">面談中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="4"
                              id="list03-status04"
                            />
                            <label for="list03-status04" class="status-hired">内定</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="5"
                              id="list03-status05"
                            />
                            <label for="list03-status05" class="status-unresponsive"
                              >音信不通</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date">2025/8/4</div>
                </li>
              </ul>
            </li>
            <!-- NOTE  インラインでz-indexを付与 -->
            <li style="z-index: 3">
              <div class="item-name">山田 太郎</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケア癒やしDX花園</span>
                  </div>
                  <div class="wrap-apply-status">
                    <!--NOTE  連番注意　list01-status- -->
                    <div class="apply-status" data-selectbox>
                      <button type="button" class="selectbox__head" aria-expanded="false">
                        <input
                          type="hidden"
                          name="ApplyStatus04Method"
                          value="1"
                          data-selectbox-hidden
                        />
                        <span class="selectbox__value" data-selectbox-value>選択してください</span>
                        <i></i>
                      </button>
                      <div class="list-wrapper">
                        <ul class="selectbox__panel">
                          <!-- NOTE  インラインでz-indexを付与 -->
                          <li style="z-index: 2">
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="1"
                              id="list04-status01"
                              checked
                            />
                            <label for="list04-status01" class="status-registered">登録中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="2"
                              id="list04-status02"
                            />
                            <label for="list04-status02" class="status-applied">応募中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="3"
                              id="list04-status03"
                            />
                            <label for="list04-status03" class="status-interview">面談中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="4"
                              id="list04-status04"
                            />
                            <label for="list04-status04" class="status-hired">内定</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="5"
                              id="list04-status05"
                            />
                            <label for="list04-status05" class="status-unresponsive"
                              >音信不通</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date">2025/8/4</div>
                </li>
              </ul>
            </li>
            <li>
              <div class="item-name">森山 ハナコ</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケア癒やしDX花園</span>
                  </div>
                  <div></div>
                  <div class="item-date">2025/8/4</div>
                </li>
                <li>
                  <div class="item-job"><span>介護士</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケア癒やしDX花園</span>
                  </div>
                  <div></div>
                  <div class="item-date">2025/8/4</div>
                </li>
                <li>
                  <div class="item-job"><span>言語聴覚士</span></div>
                  <div class="item-destination">
                    <span>住宅型有料老人ホーム メディケア癒やしDX花園</span>
                  </div>
                  <div></div>
                  <div class="item-date">2025/12/11</div>
                </li>
              </ul>
            </li>
          </ul>
          <div class="box-pager">
            <div class="box_number">
              <nav>
                <a href="#" class="is-active"></a>
                <a href="#"></a>
                <a href="#"></a>
                <a href="#"></a>
                <a href="#"></a>
                <a href="#"></a>
                <a href="#"></a>
                <a href="#"></a>
              </nav>
              <button type="button" class="btn_next"></button>
            </div>
            <div class="box_input">
              <input type="text" placeholder="1/16513" />
            </div>
          </div>
        </article>
      </section>
      <section class="container-announcement">
        <h2>運営からのお知らせ</h2>
        <ul class="list-announcement">
          <li onclick="openModal()">
            <div class="item-date">2025/10/30</div>
            <p>こんな機能が使えるようになりました。</p>
          </li>
          <li onclick="openModal()">
            <div class="item-date">2025/10/30</div>
            <p>こんな機能が使えるようになりました。</p>
          </li>
          <li onclick="openModal()">
            <div class="item-date">2025/10/30</div>
            <p>こんな機能が使えるようになりました。</p>
          </li>
          <li onclick="openModal()">
            <div class="item-date">2025/10/30</div>
            <p>こんな機能が使えるようになりました。</p>
          </li>
          <li onclick="openModal()">
            <div class="item-date">2025/10/30</div>
            <p>こんな機能が使えるようになりました。</p>
          </li>
        </ul>
      </section>

HTML;
@include './inc_page-top.html';
print <<<HTML
    </main>
    <article class="modal-article" id="modalBlock">
      <div class="inner-modal">
        <div class="box-title">
          <p>利用規約を改定いたしました<span>（改定日：2025年10月30日）</span></p>
          <button type="button" onclick="closeModal()" class="btn-top-close"></button>
        </div>
        <div class="box-details">
          <div class="wrap-details">
            <span class="item-date">3025/12/11</span>
            <div class="item-image">
              <picture>
                <source src="../assets/images/_dummy/01.jpg" />
                <img src="../assets/images/_dummy/01.jpg" alt="" />
              </picture>
            </div>
            <div class="item-text">
              <p>
                本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。<br />
                <br />
                本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。
                <br />
                本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。本文が入ります。
              </p>
            </div>
          </div>
          <button type="button" onclick="closeModal()" class="btn-bottom-close">閉じる</button>
        </div>
      </div>
    </article>
    <script src="../assets/js/common.js" defer></script>
    <script>
      function openModal() {
        const modal = document.getElementById('modalBlock');
        if (!modal) return;
        modal.classList.add('is-active');
        document.documentElement.classList.add('modal-open');
        document.body.classList.add('modal-open');
      }
      function closeModal() {
        const modal = document.getElementById('modalBlock');
        if (modal) modal.classList.remove('is-active');
        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open');
      }
    </script>
  </body>
</html>

HTML;
