/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master07_01.php';

/**
 * ステータス変更：キャンセル時に元に戻す
 * - initSelectBox() が radio click で hidden/value を即更新するため、
 *  「変更前」を click の capture フェーズで退避しておく。
 */
const __inquiriesStatusPrevByInquiryId = Object.create(null);
let __inquiriesStatusPending = null;
function __getInquiriesStatusParts(inquiryId) {
  const name = `list${inquiryId}statusMethod`;
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
function __applyInquiriesStatusToUi(inquiryId, statusValue) {
  const { box, head, valueEl, hiddenEl, radios } = __getInquiriesStatusParts(inquiryId);
  if (!box || !hiddenEl || !valueEl) return;
  const next = String(statusValue || '').trim();
  if (!next) return;
  const radio = radios.find((r) => String(r.value) === next);
  if (radio) radio.checked = true;
  hiddenEl.value = next;
  const label = radio ? box.querySelector(`label[for="${CSS.escape(radio.id)}"]`) : null;
  if (label) valueEl.textContent = String(label.textContent || '').trim();
  __syncHeadStatusClass(head, label);
  box.classList.remove('is-open');
  if (head) head.setAttribute('aria-expanded', 'false');
}
function __getModalCloseButton() {
  const blockModal = document.getElementById('modalBlockAlert');
  return blockModal?.querySelector('.box-title button') || null;
}
function __setModalCloseToCancel() {
  const btn = __getModalCloseButton();
  if (!btn) return;
  if (!btn.dataset.__defaultOnclick) {
    btn.dataset.__defaultOnclick = btn.getAttribute('onclick') || 'closeModal()';
  }
  btn.setAttribute('onclick', 'cancelInquiriesStatusChange();');
}
function __restoreModalCloseDefault() {
  const btn = __getModalCloseButton();
  if (!btn) return;
  const def = btn.dataset.__defaultOnclick || 'closeModal()';
  btn.setAttribute('onclick', def);
}
function cancelInquiriesStatusChange() {
  try {
    if (__inquiriesStatusPending && __inquiriesStatusPending.inquiryId) {
      const prev = String(__inquiriesStatusPending.prevValue || '').trim();
      if (prev) __applyInquiriesStatusToUi(__inquiriesStatusPending.inquiryId, prev);
    }
  } finally {
    __inquiriesStatusPending = null;
    __restoreModalCloseDefault();
    if (typeof closeModal === 'function') closeModal();
  }
}
window.cancelInquiriesStatusChange = cancelInquiriesStatusChange;
//capture: radio click の前に hidden の現状（=変更前）を退避
document.addEventListener(
  'click',
  (e) => {
    const t = e.target;
    let radio = null;
    if (t instanceof HTMLInputElement && t.type === 'radio') {
      radio = t;
    } else if (t instanceof HTMLLabelElement && t.control instanceof HTMLInputElement) {
      if (t.control.type === 'radio') radio = t.control;
    }
    if (!radio) return;

    const name = String(radio.name || '');
    const m = name.match(/^list(\d+)statusMethod$/);
    if (!m) return;
    const inquiryId = parseInt(m[1], 10);
    if (!Number.isFinite(inquiryId)) return;

    const { hiddenEl } = __getInquiriesStatusParts(inquiryId);
    const prev = hiddenEl ? String(hiddenEl.value || '').trim() : '';
    if (prev) __inquiriesStatusPrevByInquiryId[String(inquiryId)] = prev;
  },
  true
);
/**
 * 検索条件確認：直近の並び替え状態（ページ移動・絞り込みでも維持する）
 *
 */
let currentSortMode = 'sortSendedDate_desc';
function clearSearchFormInputs() {
  const facilityNameEl = document.querySelector('input[name="searchFacilityName"]');
  const startEl = document.querySelector('input[name="searchStartDay"]');
  const endEl = document.querySelector('input[name="searchEndDay"]');
  if (facilityNameEl) facilityNameEl.value = '';
  if (startEl) startEl.value = '';
  if (endEl) endEl.value = '';
}

function clearFilterFormInputs() {
  const filterForm = document.querySelector('form[name=filterForm]');
  if (!filterForm) return;
  const checkboxes = filterForm.querySelectorAll('input[type="checkbox"][name="searchInitials[]"]');
  checkboxes.forEach((c) => {
    c.checked = false;
  });
  const radios = filterForm.querySelectorAll('input[type="radio"][name="filterStatus"]');
  radios.forEach((r) => {
    r.checked = false;
  });
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
  //絞り込みフォーム
  const filterForm = document.querySelector('form[name=filterForm]');
  //表示件数取得
  const displayNumber = getCurrentDisplayNumber();
  //フォームを連結
  const cFd = new FormData(searchForm);
  if (filterForm) {
    const filterFd = new FormData(filterForm);
    for (const [k, v] of filterFd.entries()) {
      cFd.append(k, v);
    }
  }
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
    location.href = './master07_01.php';
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
    document.querySelector('.block-vendor-list').remove();
    //ページ表示
    document.querySelector('.block-filter').insertAdjacentHTML('afterend', list['tag']);
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
          clearFilterFormInputs();
        }
        break;
      case 'release':
        {
          clearFilterFormInputs();
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
    document.querySelector('.block-vendor-list').remove();
    //ページ表示
    document.querySelector('.block-filter').insertAdjacentHTML('afterend', list['tag']);
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
      location.href = './master07_01.php';
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
/**
 * メッセージ対応ステータス変更チェック
 *
 */
function checkInquiriesStatus(inquiryId, facilityName, status) {
  let blockModal = document.getElementById('modalBlockAlert');
  if (!blockModal) return;
  const prevValue =
    __inquiriesStatusPrevByInquiryId[String(inquiryId)] ||
    __getInquiriesStatusParts(inquiryId).hiddenEl?.value ||
    '';
  //変更が無い場合は何もしない（同一値選択での無駄なモーダル抑止）
  if (String(prevValue || '') === String(status || '')) {
    return;
  }
  __inquiriesStatusPending = {
    inquiryId: Number(inquiryId),
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
  blockModal.querySelector('.box-title p').innerHTML = '対応ステータス変更';
  switch (status) {
    //「未対応」へ
    case 'new':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          facilityName + '様からの問い合わせを<br>「未対応」に変更します。よろしいですか？';
      }
      break;
    //「対応済み」へ
    case 'done':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          facilityName +
          '様からの問い合わせを<br><span style="font-size:inherit;font-weight:600;color:#395bbe;">「対応済」</span>に変更します。よろしいですか？';
      }
      break;
    //デフォルト：削除
    default:
      {
        //エラー応答
        alert('通信エラーが発生しました。ページを再読み込みしてください。');
        return;
      }
      break;
  }
  //キャンセルボタン生成
  let cancelButton =
    '<button type="button" class="btn-cancel" onclick="cancelInquiriesStatusChange();">キャンセル</button>';
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
  //登録ボタン生成
  const statusJs = JSON.stringify(String(status || ''));
  let addButton = `<button type="button" class="btn-confirm" onclick='changeInquiriesStatus(${Number(
    inquiryId
  )}, ${statusJs});'>はい</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
  blockModal.classList.remove('bg-black');
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * メッセージ対応ステータス変更
 *
 */
async function changeInquiriesStatus(inquiryId, status) {
  let cFd = new FormData();
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  cFd.append('action', 'changeStatus');
  cFd.append('inquiryId', inquiryId);
  cFd.append('changeStatus', status);
  cFd.append('noUpDateKey', noUpDateKeyEl.value);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const res = await response.json();
    if (res && res.noUpDateKey && noUpDateKeyEl) {
      noUpDateKeyEl.value = String(res.noUpDateKey);
    }
    let blockModal = document.getElementById('modalBlockAlert');
    if (!blockModal) return;
    //ボタンタグを全て取得
    let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
    //ボタンタグを削除
    buttonList.forEach((ElementButton) => {
      ElementButton.remove();
    });
    if (!res || res['status'] === 'error') {
      //失敗時：UIは元に戻す
      if (__inquiriesStatusPending && __inquiriesStatusPending.inquiryId) {
        const prev = String(__inquiriesStatusPending.prevValue || '').trim();
        if (prev) __applyInquiriesStatusToUi(__inquiriesStatusPending.inquiryId, prev);
      }
      __inquiriesStatusPending = null;
      __restoreModalCloseDefault();
      blockModal.classList.remove('bg-black');
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = '対応ステータス変更変更失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '問い合わせの対応ステータス変更に失敗しました。<br>お手数ですが最初からやり直してください。';
      //ボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
    } else {
      //成功：現在値を「変更後」として退避
      if (__inquiriesStatusPending && __inquiriesStatusPending.inquiryId) {
        const next = String(__inquiriesStatusPending.nextValue || '').trim();
        if (next)
          __inquiriesStatusPrevByInquiryId[String(__inquiriesStatusPending.inquiryId)] = next;
      }
      __inquiriesStatusPending = null;
      __restoreModalCloseDefault();
      blockModal.classList.remove('bg-orange');
      blockModal.classList.add('bg-black');
      blockModal.querySelector('.box-title p').innerHTML = res['title'] || '対応ステータス変更';
      blockModal.querySelector('.box-details p').innerHTML =
        res['msg'] || 'ステータスを更新しました。';
      //閉じるボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    //通信失敗時も元に戻す
    if (__inquiriesStatusPending && __inquiriesStatusPending.inquiryId) {
      const prev = String(__inquiriesStatusPending.prevValue || '').trim();
      if (prev) __applyInquiriesStatusToUi(__inquiriesStatusPending.inquiryId, prev);
    }
    __inquiriesStatusPending = null;
    __restoreModalCloseDefault();
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
