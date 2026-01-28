<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <title>リタワーク｜コントロールパネル(管理者)</title>
    <meta name="robots" content="noindex,nofollow" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta
      http-equiv="Content-Security-Policy"
      content="default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';"
    />
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no" />
    <meta name="format-detection" content="telephone=no" />
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon/favicon.svg" />
    <link
      rel="apple-touch-icon"
      sizes="180x180"
      href="../assets/images/favicon/apple-touch-icon.png"
    />
    <link rel="shortcut icon" href="../assets/images/favicon/favicon.ico" />
    <link rel="stylesheet" href="../assets/css/master04.css" />
  </head>

  <body>
    <!--#include virtual="./inc_header.html"-->
    <main class="inner-04-01">
      <section class="page-nav">
        <h2>求職者管理</h2>
        <nav>
          <a href="#" class="is-active">求職者一覧</a>
        </nav>
      </section>
      <section class="container-applicant-list">
        <h2>求職者一覧</h2>
        <form class="block-search">
          <button
            type="button"
            id="btnSwitchSearch"
            class="btn-switch"
            aria-controls="innerSearch"
            aria-expanded="false"
          ></button>
          <h3>条件で検索</h3>
          <article id="innerSearch">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-name">
                  <h4>名前</h4>
                  <input type="text" />
                </li>
                <li class="item-job">
                  <h4>職種</h4>
                  <div class="select-job-type" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">
                      <input type="hidden" name="referJobType" value="" data-selectbox-hidden />
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">
                        <li>
                          <input type="radio" name="referJobType" value="1" id="job01" />
                          <label for="job01">看護師</label>
                        </li>
                        <li>
                          <input type="radio" name="referJobType" value="2" id="job02" />
                          <label for="job02">介護士</label>
                        </li>
                        <li>
                          <input type="radio" name="referJobType" value="3" id="job03" />
                          <label for="job03">理学療法士</label>
                        </li>
                        <li>
                          <input type="radio" name="referJobType" value="4" id="job04" />
                          <label for="job04">作業療法士</label>
                        </li>
                        <li>
                          <input type="radio" name="referJobType" value="5" id="job05" />
                          <label for="job05">言語聴覚士</label>
                        </li>
                      </ul>
                    </div>
                  </div>
                </li>
                <li class="item-status">
                  <h4>応募状況</h4>
                  <div class="apply-status" data-selectbox>
                    <button type="button" class="selectbox__head" aria-expanded="false">
                      <input
                        type="hidden"
                        name="ApplyStatus01Search"
                        value="1"
                        data-selectbox-hidden
                      />
                      <span class="selectbox__value" data-selectbox-value>選択してください</span>
                      <i></i>
                    </button>
                    <div class="list-wrapper">
                      <ul class="selectbox__panel">
                        <!-- NOTE  インラインでz-indexを付与 -->
                        <!-- <li style="z-index: 2">
                          <input
                            type="radio"
                            name="ApplyStatus01Search"
                            value="1"
                            id="list01-status01"
                          />
                          <label for="list01-status01" class="status-registered">登録中</label>
                        </li> -->
                        <li>
                          <input
                            type="radio"
                            name="ApplyStatus01Search"
                            value="2"
                            id="list01-status02"
                          />
                          <label for="list01-status02" class="status-applied">応募中</label>
                        </li>
                        <li>
                          <input
                            type="radio"
                            name="ApplyStatus01Search"
                            value="3"
                            id="list01-status03"
                          />
                          <label for="list01-status03" class="status-interview">面接中</label>
                        </li>
                        <li>
                          <input
                            type="radio"
                            name="ApplyStatus01Search"
                            value="4"
                            id="list01-status04"
                          />
                          <label for="list01-status04" class="status-hired">採用</label>
                        </li>
                        <li>
                          <input
                            type="radio"
                            name="ApplyStatus01Search"
                            value="5"
                            id="list01-status05"
                          />
                          <label for="list01-status05" class="status-rejected">不採用</label>
                        </li>
                        <!-- <li>
                          <input
                            type="radio"
                            name="ApplyStatus01Search"
                            value="6"
                            id="list01-status06"
                          />
                          <label for="list01-status06" class="status-unresponsive">連絡待ち</label>
                        </li> -->
                      </ul>
                    </div>
                  </div>
                </li>
                <li class="item-last-update">
                  <h4>最終更新日</h4>
                  <div class="wrap-period">
                    <input type="date" name="searchStartDay" />
                    <span>〜</span>
                    <input type="date" name="searchEndDay" />
                  </div>
                </li>
              </ul>
              <div class="box-btn">
                <button type="button" class="item-clear">条件をクリア</button>
                <button type="button" class="item-search">条件で検索</button>
              </div>
            </div>
          </article>
        </form>
        <form class="block-filter">
          <button
            type="button"
            id="btnSwitchFilter"
            class="btn-switch"
            aria-controls="innerFilter"
            aria-expanded="false"
          ></button>
          <h3>絞り込み</h3>
          <article id="innerFilter">
            <div class="blockGrid">
              <ul class="box-search-items">
                <li class="item-name">
                  <h4>名前</h4>
                  <div class="wrap-select">
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials01" /><label for="select-initials01"
                        >あ行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials02" /><label for="select-initials02"
                        >か行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials03" /><label for="select-initials03"
                        >さ行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials04" /><label for="select-initials04"
                        >た行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials05" /><label for="select-initials05"
                        >な行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials06" /><label for="select-initials06"
                        >は行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials07" /><label for="select-initials07"
                        >ま行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials08" /><label for="select-initials08"
                        >や行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials09" /><label for="select-initials09"
                        >ら行</label
                      >
                    </div>
                    <div class="item-check-box">
                      <input type="checkbox" id="select-initials10" /><label for="select-initials10"
                        >わ行</label
                      >
                    </div>
                  </div>
                </li>
              </ul>
              <div class="box-btn">
                <button type="button" class="item-clear">絞り込みを解除</button>
              </div>
            </div>
          </article>
        </form>
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
          <ul class="list-search-results is-client">
            <li>
              <div>名前</div>
              <div>職種</div>
              <div>応募状況</div>
              <div>
                応募日<span class="wrap-sort-btn"
                  ><button type="button" class="arrow-top"></button
                  ><button type="button" class="arrow-bottom is-active"></button
                ></span>
              </div>
              <div>
                面接日<span class="wrap-sort-btn"
                  ><button type="button" class="arrow-top"></button
                  ><button type="button" class="arrow-bottom is-active"></button
                ></span>
              </div>
            </li>
            <!-- NOTE  インラインでz-indexを付与 -->
            <li style="z-index: 5">
              <div class="item-name">山田 太郎</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
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
                            />
                            <label for="list01-status01" class="status-registered">登録中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="2"
                              id="list01-status02"
                              checked
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
                            <label for="list01-status03" class="status-interview">面接中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="4"
                              id="list01-status04"
                            />
                            <label for="list01-status04" class="status-hired">採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="5"
                              id="list01-status05"
                            />
                            <label for="list01-status05" class="status-rejected">不採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus01Method"
                              value="6"
                              id="list01-status06"
                            />
                            <label for="list01-status06" class="status-unresponsive"
                              >連絡待ち</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date date-apply">2025/9/22</div>
                  <div class="item-date">2025/10/05</div>
                </li>
                <li>
                  <div class="item-job"><span>言語聴覚士</span></div>
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
                              checked
                            />
                            <label for="list02-status03" class="status-interview">面接中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="4"
                              id="list02-status04"
                            />
                            <label for="list02-status04" class="status-hired">採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="5"
                              id="list02-status05"
                            />
                            <label for="list02-status05" class="status-rejected">不採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus02Method"
                              value="6"
                              id="list02-status06"
                            />
                            <label for="list02-status06" class="status-unresponsive"
                              >連絡待ち</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date date-apply">2025/9/22</div>
                  <div class="item-date">2025/10/05</div>
                </li>
              </ul>
            </li>
            <!-- NOTE  インラインでz-indexを付与 -->
            <li style="z-index: 4">
              <div class="item-name">森山 ハナコ</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
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
                            <label for="list03-status03" class="status-interview">面接中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="4"
                              id="list03-status04"
                              checked
                            />
                            <label for="list03-status04" class="status-hired">採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="5"
                              id="list03-status05"
                            />
                            <label for="list03-status05" class="status-rejected">不採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus03Method"
                              value="6"
                              id="list03-status06"
                            />
                            <label for="list03-status06" class="status-unresponsive"
                              >連絡待ち</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date date-apply">2025/9/22</div>
                  <div class="item-date">2025/10/05</div>
                </li>
              </ul>
            </li>
            <!-- NOTE  インラインでz-indexを付与 -->
            <li style="z-index: 3">
              <div class="item-name">山田 太郎</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
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
                            <label for="list04-status03" class="status-interview">面接中</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="4"
                              id="list04-status04"
                            />
                            <label for="list04-status04" class="status-hired">採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="5"
                              id="list04-status05"
                              checked
                            />
                            <label for="list04-status05" class="status-rejected">不採用</label>
                          </li>
                          <li>
                            <input
                              type="radio"
                              name="ApplyStatus04Method"
                              value="6"
                              id="list04-status06"
                            />
                            <label for="list04-status06" class="status-unresponsive"
                              >連絡待ち</label
                            >
                          </li>
                        </ul>
                      </div>
                    </div>
                  </div>
                  <div class="item-date date-apply">2025/9/22</div>
                  <div class="item-date">2025/10/05</div>
                </li>
              </ul>
            </li>
            <li>
              <div class="item-name">森山 ハナコ</div>
              <ul class="list-contact">
                <li>
                  <div class="item-job"><span>看護師</span></div>
                  <div></div>
                  <div class="item-date">2025/8/4</div>
                </li>
                <li>
                  <div class="item-job"><span>介護士</span></div>
                  <div></div>
                  <div class="item-date">2025/8/4</div>
                </li>
                <li>
                  <div class="item-job"><span>言語聴覚士</span></div>
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
      <!--#include virtual="./inc_page-top.html"-->
    </main>
    <script src="../assets/js/common.js" defer></script>
  </body>
</html>
