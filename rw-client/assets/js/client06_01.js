/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client06_01.php';
/**
 * 検索条件確認：直近の並び替え状態（ページ移動・絞り込みでも維持する）
 *
 */
let currentSortMode = 'sortInvoiceDate_desc';
function getPrevMonthRangeYM() {
  const now = new Date();
  const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const y = prev.getFullYear();
  const m = String(prev.getMonth() + 1).padStart(2, '0');
  const ym = `${y}-${m}`;
  return { start: ym, end: ym };
}
function detectCurrentSortMode() {
  const block = document.querySelector('.block-vendor-list');
  const root = block || document;
  const dataMode = block?.getAttribute('data-current-sort-mode');
  if (dataMode) return dataMode;
  const activeBtn = root.querySelector('button.is-active[onclick]');
  if (activeBtn) {
    const onclick = activeBtn.getAttribute('onclick') || '';
    const match = onclick.match(/searchConditions\('search','([^']+)'\)/);
    if (match && match[1]) return match[1];
  }
  return null;
}
//初期表示：サーバ側の状態に合わせる
{
  const detected = detectCurrentSortMode();
  if (detected) currentSortMode = detected;
}
function getCurrentDisplayNumber() {
  const root = document.querySelector('.block-vendor-list') || document;
  const checked = root.querySelector('input[name="displayNumber"]:checked');
  if (checked && checked.value) return parseInt(checked.value, 10);
  const hidden = root.querySelector('input[data-selectbox-hidden][name="displayNumber"]');
  if (hidden && hidden.value) return parseInt(hidden.value, 10);
  return 10;
}
//フォーム連結
async function requestCorporations({ action, sortMode, pageNumber }) {
  //検索フォーム
  const searchForm = document.querySelector('form[name=searchForm]');
  //表示件数取得
  const displayNumber = getCurrentDisplayNumber();
  //フォーム生成
  const cFd = new FormData(searchForm);
  cFd.append('action', action);
  cFd.append('sortMode', sortMode);
  cFd.append('displayNumber', String(displayNumber));
  cFd.append('pageNumber', String(pageNumber));
  //フォーム送信
  const response = await fetch(requestURL, {
    method: 'POST',
    body: cFd,
  });
  if (!response.ok) throw new Error('Network response was not ok');
  const data = await response.json();
  if (data && data.status === 'error') {
    alert(data.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
    location.href = './client06_01.php';
    throw new Error(data.title || 'Session error');
  }
  //サーバ側で noUpDateKey が更新/フォールバックされた場合に備えて同期
  if (data && data.noUpDateKey) {
    const noUpDateKeyInput = document.querySelector(
      'form[name=searchForm] input[name=noUpDateKey]'
    );
    if (noUpDateKeyInput) {
      noUpDateKeyInput.value = String(data.noUpDateKey);
    }
  }
  return data;
}
async function searchConditions(action, sortMode) {
  try {
    if (sortMode && sortMode !== 'none') {
      currentSortMode = sortMode;
    }
    const list = await requestCorporations({
      action,
      sortMode: currentSortMode || 'none',
      pageNumber: 1,
    });
    //表示中の情報入替
    document.querySelector('.block-vendor-list').remove();
    //ページ表示
    document.querySelector('.block-search').insertAdjacentHTML('afterend', list['tag']);
    // サーバ側の現行ソート状態に同期（セッション初期化などのズレを吸収）
    {
      const detected = detectCurrentSortMode();
      if (detected) currentSortMode = detected;
    }
    //input情報クリア
    switch (action) {
      //条件をクリア
      case 'reset':
        {
          const { start, end } = getPrevMonthRangeYM();
          const startEl = document.querySelector('input[name="searchStartDay"]');
          const endEl = document.querySelector('input[name="searchEndDay"]');
          if (startEl) startEl.value = start;
          if (endEl) endEl.value = end;
        }
        break;
    }
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    const areaClient = document.querySelector('.area-client');
    if (areaClient) areaClient.scrollIntoView(true);
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * ページャー：ページ移動
 *
 */
async function movePage(pageNumber) {
  try {
    const list = await requestCorporations({
      action: 'page',
      sortMode: currentSortMode || 'none',
      pageNumber,
    });
    //表示中の情報入替
    document.querySelector('.block-vendor-list').remove();
    //ページ表示
    document.querySelector('.block-search').insertAdjacentHTML('afterend', list['tag']);
    // サーバ側の現行ソート状態に同期
    {
      const detected = detectCurrentSortMode();
      if (detected) currentSortMode = detected;
    }
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    const areaClient = document.querySelector('.area-client');
    if (areaClient) areaClient.scrollIntoView(true);
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 契約プラン：開閉トグル
 *
 */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-arrow');
  if (!btn) return;
  const itemPlan = btn.closest('.item-plan');
  if (!itemPlan) return;
  const innerPlan = itemPlan.querySelector('.inner-plan');
  if (!innerPlan) return;
  innerPlan.classList.toggle('is-active');
});
/**
 * 請求月：開始月を選択したら終了月も同月に揃える
 * - 開始月を変更したタイミングだけ反映（終了月を後から個別変更する運用も可能）
 */
function syncEndMonthToStart() {
  const startEl = document.querySelector('input[name="searchStartDay"]');
  const endEl = document.querySelector('input[name="searchEndDay"]');
  if (!startEl || !endEl) return;
  const start = (startEl.value || '').trim();
  if (!start) return;
  endEl.value = start;
}
document.addEventListener('change', (e) => {
  const target = e.target;
  if (!(target instanceof HTMLInputElement)) return;
  if (target.name !== 'searchStartDay') return;
  syncEndMonthToStart();
});
document.addEventListener('input', (e) => {
  const target = e.target;
  if (!(target instanceof HTMLInputElement)) return;
  if (target.name !== 'searchStartDay') return;
  syncEndMonthToStart();
});
