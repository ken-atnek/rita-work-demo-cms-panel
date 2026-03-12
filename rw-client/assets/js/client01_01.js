/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client01_01.php';

//応募状況変更：キャンセル時にUIを戻すための退避
const __appStatusPrevByGroupName = Object.create(null);
let __appStatusLastGroupName = '';
let __appStatusPending = null;
let __closeModalOriginal = null;
function __cssEscape(value) {
  const v = String(value ?? '');
  if (window.CSS && typeof window.CSS.escape === 'function') return window.CSS.escape(v);
  return v.replace(/[^a-zA-Z0-9_\-]/g, (m) => `\\${m}`);
}
function __getAppStatusParts(groupName) {
  const name = String(groupName || '');
  if (!name) return { box: null, head: null, valueEl: null, hiddenEl: null, radios: [] };
  const hiddenEl = document.querySelector(
    `input[data-selectbox-hidden][name="${__cssEscape(name)}"]`
  );
  const box = hiddenEl ? hiddenEl.closest('[data-selectbox]') : null;
  const head = box ? box.querySelector('.selectbox__head') : null;
  const valueEl = box ? box.querySelector('[data-selectbox-value]') : null;
  const radios = box
    ? Array.from(box.querySelectorAll(`input[type="radio"][name="${__cssEscape(name)}"]`))
    : [];
  return { box, head, valueEl, hiddenEl, radios };
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
function __setSelectBoxState(box, hiddenEl, value) {
  if (!box) return;
  const v = String(value ?? '').trim();
  if (v) {
    box.classList.add('is-selected');
    box.classList.remove('is-empty');
    if (hiddenEl) hiddenEl.value = v;
  } else {
    box.classList.remove('is-selected');
    box.classList.add('is-empty');
    if (hiddenEl) hiddenEl.value = '';
  }
}
function __applyAppStatusToUi(groupName, value) {
  const { box, head, valueEl, hiddenEl, radios } = __getAppStatusParts(groupName);
  if (!box || !valueEl || !hiddenEl) return;

  const targetValue = String(value ?? '').trim();
  let label = null;

  if (targetValue !== '') {
    const radio = radios.find((r) => String(r.value) === targetValue) || null;
    radios.forEach((r) => {
      r.checked = radio ? r === radio : false;
    });
    if (radio && radio.id) {
      label = box.querySelector(`label[for="${__cssEscape(radio.id)}"]`);
    }
    if (label) valueEl.textContent = String(label.textContent || '').trim();
    __syncHeadStatusClass(head, label);
    __setSelectBoxState(box, hiddenEl, targetValue);
  } else {
    radios.forEach((r) => {
      r.checked = false;
    });
    valueEl.textContent = '選択してください';
    __syncHeadStatusClass(head, null);
    __setSelectBoxState(box, hiddenEl, '');
  }

  box.classList.remove('is-open');
  if (head) head.setAttribute('aria-expanded', 'false');
}
function __captureAppStatusPrevValue() {
  const recordPrev = (groupName) => {
    if (!groupName) return;
    __appStatusLastGroupName = String(groupName);
    const { hiddenEl } = __getAppStatusParts(groupName);
    const prev = hiddenEl ? String(hiddenEl.value ?? '') : '';
    __appStatusPrevByGroupName[String(groupName)] = String(prev);
  };

  const getGroupNameFromTarget = (target) => {
    if (!(target instanceof Element)) return '';
    if (target instanceof HTMLInputElement && target.type === 'radio') {
      const n = String(target.name || '');
      return n.startsWith('application_status') ? n : '';
    }
    const label = target.closest('label');
    if (label && label.htmlFor) {
      const input = document.getElementById(label.htmlFor);
      if (input instanceof HTMLInputElement && input.type === 'radio') {
        const n = String(input.name || '');
        return n.startsWith('application_status') ? n : '';
      }
    }
    const li = target.closest('li');
    if (li) {
      const input = li.querySelector('input[type="radio"][name^="application_status"]');
      if (input instanceof HTMLInputElement) {
        const n = String(input.name || '');
        return n.startsWith('application_status') ? n : '';
      }
    }
    return '';
  };

  document.addEventListener(
    'pointerdown',
    (e) => {
      const groupName = getGroupNameFromTarget(e.target);
      if (groupName) recordPrev(groupName);
    },
    true
  );

  document.addEventListener(
    'click',
    (e) => {
      const groupName = getGroupNameFromTarget(e.target);
      if (groupName) recordPrev(groupName);
    },
    true
  );
}
function __setModalCloseToCancel() {
  if (__closeModalOriginal === null && typeof window.closeModal === 'function') {
    __closeModalOriginal = window.closeModal;
  }
  if (typeof window.closeModal === 'function') {
    window.closeModal = () => {
      cancelApplicationStatusChange();
    };
  }
}
function __restoreModalCloseDefault() {
  if (__closeModalOriginal && typeof __closeModalOriginal === 'function') {
    window.closeModal = __closeModalOriginal;
  }
  __closeModalOriginal = null;
}
function cancelApplicationStatusChange() {
  if (__appStatusPending && __appStatusPending.groupName) {
    __applyAppStatusToUi(__appStatusPending.groupName, __appStatusPending.prevValue);
  }
  __appStatusPending = null;
  const orig = __closeModalOriginal;
  __restoreModalCloseDefault();
  if (typeof orig === 'function') {
    orig();
  } else {
    const modal =
      document.getElementById('modalBlockAlert') || document.getElementById('modalBlock');
    if (modal) {
      modal.classList.remove('is-active');
      modal.classList.remove('bg-orange');
      modal.classList.remove('bg-black');
    }
  }
  document.documentElement.style.overflow = '';
}
window.cancelApplicationStatusChange = cancelApplicationStatusChange;
__captureAppStatusPrevValue();
/**
 * 応募状況ボタン切替
 *
 */
function getCurrentDisplayNumber() {
  const root = document.querySelector('.block-search-results') || document;
  const checked = root.querySelector('input[name="displayNumber"]:checked');
  if (checked && checked.value) return parseInt(checked.value, 10);
  const hidden = root.querySelector('input[data-selectbox-hidden][name="displayNumber"]');
  if (hidden && hidden.value) return parseInt(hidden.value, 10);
  return 10;
}
/**
 * 検索条件確認：直近の並び替え・ステータス状態（ページ移動でも維持する）
 */
let currentSortMode = 'sortApplicationsDate_desc';
let currentSearchMode = 'applied';
function detectInitialSearchMode() {
  const btn = document.querySelector('.block-status button.is-active');
  if (!btn) return 'applied';
  const statusClass = Array.from(btn.classList).find((c) => c.startsWith('status-'));
  return statusClass ? statusClass.replace('status-', '') : 'applied';
}
function detectInitialSortMode() {
  const block = document.querySelector('.block-search-results');
  const fromData = block ? block.getAttribute('data-current-sort-mode') || '' : '';
  if (fromData) return fromData;
  const active = document.querySelector('.wrap-sort-btn button.is-active');
  const onclick = active ? active.getAttribute('onclick') || '' : '';
  if (onclick.includes('sortInterviewDate_asc')) return 'sortInterviewDate_asc';
  if (onclick.includes('sortInterviewDate_desc')) return 'sortInterviewDate_desc';
  if (onclick.includes('sortApplicationsDate_asc')) return 'sortApplicationsDate_asc';
  if (onclick.includes('sortApplicationsDate_desc')) return 'sortApplicationsDate_desc';
  return 'sortApplicationsDate_desc';
}
document.addEventListener('DOMContentLoaded', () => {
  currentSearchMode = detectInitialSearchMode();
  currentSortMode = detectInitialSortMode();
});
async function requestApplications({ action, searchMode, sortMode, pageNumber }) {
  const displayNumber = getCurrentDisplayNumber();
  const fd = new FormData();
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  if (noUpDateKeyEl && noUpDateKeyEl.value) {
    fd.append('noUpDateKey', noUpDateKeyEl.value);
  }
  fd.append('action', action);
  fd.append('searchMode', searchMode);
  fd.append('sortMode', sortMode);
  fd.append('displayNumber', String(displayNumber));
  fd.append('pageNumber', String(pageNumber));
  const response = await fetch(requestURL, {
    method: 'POST',
    body: fd,
  });
  if (!response.ok) throw new Error('Network response was not ok');
  const data = await response.json();
  if (data && data.status === 'error') {
    alert(data.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
    location.href = './client01_01.php';
    throw new Error(data.title || 'Session error');
  }
  if (data && data.noUpDateKey && noUpDateKeyEl) {
    noUpDateKeyEl.value = String(data.noUpDateKey);
  }
  return data;
}
async function searchConditions(action, searchMode, sortMode) {
  try {
    if (searchMode && searchMode !== 'none') {
      currentSearchMode = searchMode;
    }
    if (sortMode && sortMode !== 'none') {
      currentSortMode = sortMode;
    }
    const list = await requestApplications({
      action,
      searchMode: currentSearchMode,
      sortMode: currentSortMode,
      pageNumber: 1,
    });
    //表示中の情報入替
    document.querySelector('.block-search-results').remove();
    //ページ表示
    document.querySelector('.block-status').insertAdjacentHTML('afterend', list['tag']);
    //ボタンタグアクティブ判定
    const blockStatusButton = document.querySelectorAll('.block-status button');
    blockStatusButton.forEach((button) => {
      button.classList.remove('is-active');
    });
    switch (currentSearchMode) {
      //応募中
      case 'applied':
        {
          document.querySelector('.status-applied').classList.add('is-active');
        }
        break;
      //面接中
      case 'interview':
        {
          document.querySelector('.status-interview').classList.add('is-active');
        }
        break;
      //採用
      case 'hired':
        {
          document.querySelector('.status-hired').classList.add('is-active');
        }
        break;
      //不採用
      case 'rejected':
        {
          document.querySelector('.status-rejected').classList.add('is-active');
        }
        break;
      //デフォルト：応募中
      default:
        {
          document.querySelector('.status-applied').classList.add('is-active');
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
 * 応募状況ステータス変更チェック
 *
 */
function checkApplicationStatus(
  facId,
  userLineId,
  userLineName,
  jobCardId,
  status,
  searchMode,
  sortMode
) {
  const groupName = __appStatusLastGroupName;
  const prevValue = groupName
    ? Object.prototype.hasOwnProperty.call(__appStatusPrevByGroupName, groupName)
      ? __appStatusPrevByGroupName[groupName]
      : __getAppStatusParts(groupName).hiddenEl?.value || ''
    : '';
  __appStatusPending = {
    groupName: groupName,
    prevValue: String(prevValue || ''),
    nextValue: String(status || ''),
  };
  __setModalCloseToCancel();
  const blockModal = document.getElementById('modalBlockAlert');
  if (!blockModal) return;
  const boxTitleP = blockModal.querySelector('.box-title p');
  const boxDetails = blockModal.querySelector('.box-details');
  if (!boxTitleP || !boxDetails) return;
  boxTitleP.textContent = '応募状況変更';
  //既存内容をクリア（以前の .wrap-details 削除や .box-btn の残骸をまとめて解消）
  boxDetails.innerHTML = '';
  const statusLabelMap = {
    applied: '応募中',
    interview: '面接中',
    hired: '採用',
    rejected: '不採用',
  };
  const statusLabel = statusLabelMap[status] || '応募中';
  const messageP = document.createElement('p');
  messageP.textContent = `${userLineName}様の応募状況を「${statusLabel}」に変更します。よろしいですか？`;
  boxDetails.appendChild(messageP);
  const btnWrap = document.createElement('div');
  btnWrap.className = 'box-btn';
  boxDetails.appendChild(btnWrap);
  //キャンセルボタン生成
  const cancelBtn = document.createElement('button');
  cancelBtn.type = 'button';
  cancelBtn.className = 'btn-cancel';
  cancelBtn.textContent = 'キャンセル';
  cancelBtn.addEventListener('click', () => {
    cancelApplicationStatusChange();
  });
  btnWrap.appendChild(cancelBtn);
  //登録ボタン生成
  const confirmBtn = document.createElement('button');
  confirmBtn.type = 'button';
  confirmBtn.className = 'btn-confirm';
  confirmBtn.textContent = 'はい';
  confirmBtn.addEventListener('click', () => {
    changeApplicationStatus(
      facId,
      userLineId,
      userLineName,
      jobCardId,
      status,
      searchMode,
      sortMode
    );
  });
  btnWrap.appendChild(confirmBtn);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 応募状況ステータス変更
 *
 */
async function changeApplicationStatus(
  facId,
  userLineId,
  userLineName,
  jobCardId,
  status,
  searchMode,
  sortMode
) {
  const displayNumber = getCurrentDisplayNumber();
  const nextSearchMode = status;
  const nextSortMode = sortMode && sortMode !== 'none' ? sortMode : currentSortMode;
  let cFd = new FormData();
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  if (noUpDateKeyEl && noUpDateKeyEl.value) {
    cFd.append('noUpDateKey', noUpDateKeyEl.value);
  }
  cFd.append('action', 'changeStatus');
  cFd.append('facId', facId);
  cFd.append('lineId', userLineId);
  cFd.append('lineName', userLineName);
  cFd.append('jobId', jobCardId);
  cFd.append('changeStatus', status);
  //変更後のステータス（タブ）を表示する
  cFd.append('searchMode', nextSearchMode);
  cFd.append('sortMode', nextSortMode);
  cFd.append('displayNumber', String(displayNumber));
  cFd.append('pageNumber', String(1));
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    __restoreModalCloseDefault();
    const blockModal =
      document.getElementById('modalBlockAlert') || document.getElementById('modalBlock');
    if (blockModal) {
      blockModal.classList.remove('is-active');
      blockModal.classList.remove('bg-orange');
      blockModal.classList.remove('bg-black');
    }
    document.documentElement.style.overflow = '';
    const list = await response.json();
    if (list && list.status === 'error') {
      if (__appStatusPending && __appStatusPending.groupName) {
        __applyAppStatusToUi(__appStatusPending.groupName, __appStatusPending.prevValue);
      }
      __appStatusPending = null;
      __restoreModalCloseDefault();
      alert(list.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
      location.href = './client01_01.php';
      return;
    }
    //以後のページング/再検索も変更後タブを基準にする
    currentSearchMode = nextSearchMode;
    currentSortMode = nextSortMode;
    const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
    if (list && list.noUpDateKey && noUpDateKeyEl) {
      noUpDateKeyEl.value = String(list.noUpDateKey);
    }
    //ボタンタグ内の応募状況人数入れ替え
    const blockStatusButtonList = document.querySelectorAll('.block-status button');
    blockStatusButtonList.forEach((button) => {
      const statusClass = Array.from(button.classList).find((c) => c.startsWith('status-'));
      const statusLabel = statusClass ? statusClass.replace('status-', '') : '';
      //アクティブクラス削除
      button.classList.remove('is-active');
      if (statusLabel == list['activeButtons']) {
        button.classList.add('is-active');
      }
      const countEl = button.querySelector('.count');
      if (countEl && list['applicationCounts'] && statusLabel in list['applicationCounts']) {
        countEl.textContent = String(list['applicationCounts'][statusLabel]);
      }
    });
    //表示中の情報入替
    document.querySelector('.block-search-results').remove();
    //ページ表示
    document.querySelector('.block-status').insertAdjacentHTML('afterend', list['tag']);
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    const areaClient = document.querySelector('.area-client');
    if (areaClient) areaClient.scrollIntoView(true);
    //ここまで来たら画面状態は成功として確定
    __appStatusPending = null;
  } catch (error) {
    console.error('送信エラー:', error);
    if (__appStatusPending && __appStatusPending.groupName) {
      __applyAppStatusToUi(__appStatusPending.groupName, __appStatusPending.prevValue);
    }
    __appStatusPending = null;
    __restoreModalCloseDefault();
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * ページャー：ページ移動
 *
 */
async function movePage(pageNumber) {
  try {
    const list = await requestApplications({
      action: 'page',
      searchMode: currentSearchMode,
      sortMode: currentSortMode,
      pageNumber,
    });
    //表示中の情報入替
    document.querySelector('.block-search-results').remove();
    //ページ表示
    document.querySelector('.block-status').insertAdjacentHTML('afterend', list['tag']);
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
 * お知らせモーダル作成
 *
 */
async function makeNotificationsModal(action, notificationsId) {
  const sFd = new FormData();
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  if (noUpDateKeyEl && noUpDateKeyEl.value) {
    sFd.append('noUpDateKey', noUpDateKeyEl.value);
  }
  sFd.append('action', action);
  sFd.append('notificationsId', String(notificationsId || ''));
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
      location.href = './client01_01.php';
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
    blockModal.classList.add('bg-black');
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
