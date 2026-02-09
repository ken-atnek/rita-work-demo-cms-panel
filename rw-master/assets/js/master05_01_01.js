/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master05_01_01.php';

/**
 * ステータス変更：キャンセル時に元に戻す
 * - initSelectBox() が radio click で hidden/value を即更新するため、
 *  「変更前」を click の capture フェーズで退避しておく。
 */
const __tipsStatusPrevByArticleId = Object.create(null);
let __tipsStatusPending = null;
function __getStatusSelectBoxParts(articleId) {
  const name = `list_status${articleId}`;
  const hiddenEl = document.querySelector(
    `input[data-selectbox-hidden][name="${CSS.escape(name)}"]`
  );
  const box = hiddenEl ? hiddenEl.closest('[data-selectbox]') : null;
  const head = box ? box.querySelector('.selectbox__head') : null;
  const valueEl = box ? box.querySelector('[data-selectbox-value]') : null;
  const radios = box ? Array.from(box.querySelectorAll('input[type="radio"]')) : [];
  return { box, head, valueEl, hiddenEl, radios, name };
}
function __syncHeadStatusClass(head, label) {
  if (!head) return;
  Array.from(head.classList).forEach((c) => {
    if (String(c).startsWith('status-')) head.classList.remove(c);
  });
  if (!label) return;
  Array.from(label.classList).forEach((c) => {
    if (String(c).startsWith('status-')) head.classList.add(c);
  });
}
function __applyStatusToUi(articleId, statusValue) {
  const { box, head, valueEl, hiddenEl, radios } = __getStatusSelectBoxParts(articleId);
  if (!box || !hiddenEl || !valueEl) return;
  const next = String(statusValue || '').trim();
  if (!next) return;
  const radio = radios.find((r) => String(r.value) === next);
  if (radio) radio.checked = true;
  hiddenEl.value = next;
  const label = radio ? box.querySelector(`label[for="${CSS.escape(radio.id)}"]`) : null;
  if (label) valueEl.textContent = String(label.textContent || '').trim();
  __syncHeadStatusClass(head, label);
  //開いている場合は閉じる
  box.classList.remove('is-open');
  if (head) head.setAttribute('aria-expanded', 'false');
}
function __getModalCloseButton() {
  const blockModal = document.getElementById('modalBlock');
  const btn = blockModal?.querySelector('.box-title button');
  return btn || null;
}
function __setModalCloseToCancel() {
  const btn = __getModalCloseButton();
  if (!btn) return;
  if (!btn.dataset.__defaultOnclick) {
    btn.dataset.__defaultOnclick = btn.getAttribute('onclick') || 'closeModal()';
  }
  btn.setAttribute('onclick', 'cancelTipsStatusChange();');
}
function __restoreModalCloseDefault() {
  const btn = __getModalCloseButton();
  if (!btn) return;
  const def = btn.dataset.__defaultOnclick || 'closeModal()';
  btn.setAttribute('onclick', def);
}
function cancelTipsStatusChange() {
  try {
    if (__tipsStatusPending && __tipsStatusPending.articleId) {
      const { articleId, prevValue } = __tipsStatusPending;
      if (prevValue) __applyStatusToUi(articleId, prevValue);
    }
  } finally {
    __tipsStatusPending = null;
    __restoreModalCloseDefault();
    closeModal();
  }
}
window.cancelTipsStatusChange = cancelTipsStatusChange;
//capture: radio click の前に hidden の現状（=変更前）を退避
document.addEventListener(
  'click',
  (e) => {
    const t = e.target;
    if (!(t instanceof HTMLInputElement)) return;
    if (t.type !== 'radio') return;
    const name = String(t.name || '');
    if (!name.startsWith('list_status')) return;
    const m = name.match(/^list_status(\d+)$/);
    if (!m) return;
    const articleId = parseInt(m[1], 10);
    if (!Number.isFinite(articleId)) return;
    const { hiddenEl } = __getStatusSelectBoxParts(articleId);
    const prev = hiddenEl ? String(hiddenEl.value || '').trim() : '';
    if (prev) __tipsStatusPrevByArticleId[String(articleId)] = prev;
  },
  true
);
/**
 * 検索条件確認：直近の並び替え状態（ページ移動・絞り込みでも維持する）
 *
 */
let currentSortMode = 'sortId_desc';
function detectInitialSortMode() {
  const block = document.querySelector('.block-search-results');
  const fromData = block ? block.getAttribute('data-current-sort-mode') || '' : '';
  if (fromData) return fromData;
  const active = document.querySelector('.wrap-sort-btn button.is-active');
  const onclick = active ? active.getAttribute('onclick') || '' : '';
  if (onclick.includes('sortUpdateDate_asc')) return 'sortUpdateDate_asc';
  if (onclick.includes('sortUpdateDate_desc')) return 'sortUpdateDate_desc';
  if (onclick.includes('sortId_asc')) return 'sortId_asc';
  if (onclick.includes('sortId_desc')) return 'sortId_desc';
  return 'sortId_desc';
}
document.addEventListener('DOMContentLoaded', () => {
  currentSortMode = detectInitialSortMode();
});
function getCurrentDisplayNumber() {
  const root = document.querySelector('.block-search-results') || document;
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
  //フォームを連結
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
    location.href = './master05_01_01.php';
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
/**
 * 記事・絞り込み
 *
 */
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
    document.querySelector('.block-search-results').remove();
    //ページ表示
    document.querySelector('.block-search').insertAdjacentHTML('afterend', list['tag']);
    //サーバ側の現行ソート状態に同期（セッション復元/正規化などのズレを吸収）
    currentSortMode = detectInitialSortMode();
    //input情報クリア
    switch (action) {
      //条件をクリア
      case 'reset':
        {
          document.querySelector('input[name="searchStartDay"]').value = '';
          document.querySelector('input[name="searchEndDay"]').value = '';
        }
        break;
    }
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    const areaMaster = document.querySelector('.area-master');
    if (areaMaster) areaMaster.scrollIntoView(true);
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
    document.querySelector('.block-search-results').remove();
    //ページ表示
    document.querySelector('.block-search').insertAdjacentHTML('afterend', list['tag']);
    //サーバ側の現行ソート状態に同期
    currentSortMode = detectInitialSortMode();
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    const areaMaster = document.querySelector('.area-master');
    if (areaMaster) areaMaster.scrollIntoView(true);
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 記事公開状態変更チェック
 *
 */
function checkTipsStatus(articleId, articleCode, status) {
  let blockModal = document.getElementById('modalBlock');
  const prevValue =
    __tipsStatusPrevByArticleId[String(articleId)] ||
    __getStatusSelectBoxParts(articleId).hiddenEl?.value ||
    '';
  __tipsStatusPending = {
    articleId: Number(articleId),
    articleCode: String(articleCode || ''),
    prevValue: String(prevValue || ''),
    nextValue: String(status || ''),
  };
  __setModalCloseToCancel();
  //ボタンタグを全て取得
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  blockModal.querySelector('.box-title p').innerHTML = 'ステータス';
  switch (status) {
    //「下書き」へ
    case 'draft':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          '記事番号：' + articleId + 'を「下書き中」に変更します。よろしいですか？';
      }
      break;
    //「公開」へ
    case 'public':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          '記事番号：' + articleId + 'を「公開中」に変更します。よろしいですか？';
      }
      break;
  }
  //キャンセルボタン生成
  let cancelButton =
    '<button type="button" class="btn-cancel" onclick="cancelTipsStatusChange();">キャンセル</button>';
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
  //登録ボタン生成
  let addButton = `<button type="button" class="btn-confirm" onclick="changeTipsStatus(${articleId},'${articleCode}','${status}');">はい</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人カード状態変更
 *
 */
async function changeTipsStatus(articleId, articleCode, status) {
  let cFd = new FormData();
  cFd.append('action', 'changeStatus');
  const noUpDateKey =
    document.querySelector('form[name=searchForm] input[name=noUpDateKey]')?.value || '';
  if (noUpDateKey) cFd.append('noUpDateKey', String(noUpDateKey));
  cFd.append('changeArticleId', articleId);
  cFd.append('changeArticleCode', articleCode);
  cFd.append('changeStatus', status);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    let blockModal = document.getElementById('modalBlock');
    //ボタンタグを全て取得
    let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
    //ボタンタグを削除
    buttonList.forEach((ElementButton) => {
      ElementButton.remove();
    });
    if (list['status'] == 'error') {
      // 失敗時：UIは元に戻す（変更処理は走っていない）
      if (__tipsStatusPending && __tipsStatusPending.articleId) {
        const prev = String(__tipsStatusPending.prevValue || '').trim();
        if (prev) __applyStatusToUi(__tipsStatusPending.articleId, prev);
      }
      __tipsStatusPending = null;
      __restoreModalCloseDefault();
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = 'カード状況変更失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        'カード状況の変更に失敗しました。<br>お手数ですが最初からやり直してください。';
      //ボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
    } else {
      __tipsStatusPending = null;
      __restoreModalCloseDefault();
      blockModal.classList.add('bg-black');
      blockModal.querySelector('.box-title p').innerHTML = list['title'];
      blockModal.querySelector('.box-details p').innerHTML = list['msg'];
      //一覧へ戻るボタン生成
      let newButton = `<button type="button" class="btn-cancel" onclick="closeModalToPage('master05_01_01.php');">一覧に戻る</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('master05_01_01.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    // 通信失敗時も元に戻す
    if (__tipsStatusPending && __tipsStatusPending.articleId) {
      const prev = String(__tipsStatusPending.prevValue || '').trim();
      if (prev) __applyStatusToUi(__tipsStatusPending.articleId, prev);
    }
    __tipsStatusPending = null;
    __restoreModalCloseDefault();
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 新規記事登録チェック
 *
 */
function checkNewTips() {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
