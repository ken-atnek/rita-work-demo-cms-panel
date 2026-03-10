/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client07_02.php';
/**
 * 検索条件確認：直近の並び替え状態（ページ移動・絞り込みでも維持する）
 *
 */
let currentSortMode = 'sortSendedDate_desc';
function clearSearchFormInputs() {
  const startEl = document.querySelector('input[name="searchStartDay"]');
  const endEl = document.querySelector('input[name="searchEndDay"]');
  if (startEl) startEl.value = '';
  if (endEl) endEl.value = '';

  // 件名セレクトボックス（hidden/value/radio/状態）をクリア
  const subjectBox = document.querySelector('.select-subject[data-selectbox]');
  if (subjectBox) {
    const hidden = subjectBox.querySelector('input[data-selectbox-hidden][name="selectSubject"]');
    const valueEl = subjectBox.querySelector('[data-selectbox-value]');
    const head = subjectBox.querySelector('.selectbox__head');
    const radios = subjectBox.querySelectorAll('input[type="radio"][name="selectSubject"]');

    radios.forEach((r) => {
      r.checked = false;
    });
    if (hidden) hidden.value = '';
    if (valueEl) valueEl.textContent = '選択してください';
    subjectBox.classList.remove('is-selected');
    subjectBox.classList.add('is-empty');
    subjectBox.classList.remove('is-open');
    if (head) head.setAttribute('aria-expanded', 'false');
  }
}
function detectCurrentSortMode() {
  const block = document.querySelector('.block-history-list');
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
  const root = document.querySelector('.block-history-list') || document;
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
    location.href = './client07_02.php';
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
 * お問い合わせ検索・絞り込み
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
    document.querySelector('.block-history-list').remove();
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
          clearSearchFormInputs();
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
    document.querySelector('.block-history-list').remove();
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
    const areaMaster = document.querySelector('.area-master');
    if (areaMaster) areaMaster.scrollIntoView(true);
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * メッセージ確認モーダル作成
 *
 */
async function makeInquiryModal(action, inquiryId) {
  const sFd = new FormData();
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  if (noUpDateKeyEl && noUpDateKeyEl.value) {
    sFd.append('noUpDateKey', noUpDateKeyEl.value);
  }
  sFd.append('action', action);
  sFd.append('inquiryId', String(inquiryId || ''));
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: sFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const data = await response.json();
    if (data && data.noUpDateKey && noUpDateKeyEl) {
      noUpDateKeyEl.value = String(data.noUpDateKey);
    }
    if (data && data.status === 'error' && !data.tag) {
      alert(data.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
      location.href = './client07_02.php';
      return;
    }
    if (!data || !data.tag) {
      alert('通信エラーが発生しました。ページを再読み込みしてください。');
      return;
    }
    //表示中の情報入替
    const currentInner = document.querySelector('.modal-article .inner-modal');
    if (currentInner) currentInner.remove();
    //ページ表示
    const modalArticle = document.querySelector('.modal-article');
    if (modalArticle && data && data.tag) {
      modalArticle.insertAdjacentHTML('afterbegin', data.tag);
    }
    const blockModal = document.getElementById('modalBlock');
    if (!blockModal) return;
    blockModal.classList.remove('bg-orange');
    blockModal.classList.remove('bg-black');
    blockModal.classList.add('bg-black');
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
