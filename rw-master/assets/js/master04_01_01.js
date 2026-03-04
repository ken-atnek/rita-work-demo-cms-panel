/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master04_01_01.php';

/**
 * 応募状況ボタン切替
 *
 */
function getCurrentDisplayNumber() {
  const root = document.querySelector('.block-applicant-list') || document;
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
let currentSearchMode = 'all';
let currentPageNumber = 1;
function replaceApplicantList(nextTag) {
  const currentResults = document.querySelector('.block-applicant-list');
  const blockApplicantDetails = document.querySelector('.block-applicant-details');
  if (!nextTag || !blockApplicantDetails) {
    throw new Error('Invalid AJAX response');
  }
  if (currentResults) currentResults.remove();
  blockApplicantDetails.insertAdjacentHTML('afterend', nextTag);
}
function syncModesFromDom() {
  const detectedSortMode = detectCurrentSortMode();
  if (detectedSortMode) currentSortMode = detectedSortMode;
  const detectedSearchMode = detectCurrentSearchMode();
  if (detectedSearchMode) currentSearchMode = detectedSearchMode;
}
function scrollToTop() {
  const areaMaster = document.querySelector('.area-master');
  if (areaMaster) areaMaster.scrollIntoView(true);
}
function resolveSearchMode(preferred) {
  if (preferred && preferred !== 'none') return String(preferred);
  if (currentSearchMode && currentSearchMode !== 'none') return String(currentSearchMode);
  const detected = detectCurrentSearchMode();
  return detected ? String(detected) : 'all';
}
function resolveSortMode(preferred) {
  if (preferred && preferred !== 'none') return String(preferred);
  if (currentSortMode && currentSortMode !== 'none') return String(currentSortMode);
  const detected = detectCurrentSortMode();
  return detected ? String(detected) : 'sortApplicationsDate_desc';
}
async function postAndRedraw({ action, extraFields, searchMode, sortMode, pageNumber }) {
  const displayNumber = getCurrentDisplayNumber();
  const searchForm = document.querySelector('form[name=searchForm]');
  if (!searchForm) throw new Error('searchForm not found');
  const noUpDateKeyEl =
    searchForm.querySelector('input[name="noUpDateKey"]') ||
    document.querySelector('input[name="noUpDateKey"]');
  const fd = new FormData(searchForm);
  fd.set('action', String(action));
  fd.set('searchMode', resolveSearchMode(searchMode));
  fd.set('sortMode', resolveSortMode(sortMode));
  fd.set('displayNumber', String(displayNumber));
  fd.set('pageNumber', String(pageNumber || 1));
  currentPageNumber = parseInt(String(pageNumber || 1), 10) || 1;
  if (extraFields && typeof extraFields === 'object') {
    Object.entries(extraFields).forEach(([key, value]) => {
      if (value === undefined || value === null) return;
      fd.set(String(key), String(value));
    });
  }
  const response = await fetch(requestURL, {
    method: 'POST',
    body: fd,
  });
  if (!response.ok) throw new Error('Network response was not ok');
  const data = await response.json();
  if (data && data.status === 'error') {
    alert(data.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
    location.href = './master04_01.php';
    throw new Error(data.title || 'Update error');
  }
  if (data && data.noUpDateKey && noUpDateKeyEl) {
    noUpDateKeyEl.value = String(data.noUpDateKey);
  }
  const nextTag = data && typeof data.tag === 'string' ? data.tag : '';
  replaceApplicantList(nextTag);
  syncModesFromDom();
  initSelectBox();
  //新規作成など：特定行のハイライト
  const highlightId =
    data && data.highlightApplicationId ? parseInt(data.highlightApplicationId, 10) : 0;
  if (highlightId > 0) {
    requestAnimationFrame(() => {
      highlightApplicationRow(highlightId);
    });
  }
  // scrollToTop();
  return data;
}
function normalizeTextForCompare(value) {
  return String(value ?? '').replace(/\r\n/g, '\n');
}
function highlightApplicationRow(applicationId) {
  const id = parseInt(applicationId, 10);
  if (!id) return;
  const row = document.querySelector(
    `.block-applicant-list .list-personal-results > li[data-application-id="${id}"]`
  );
  if (!row) return;
  const originalBorder = row.style.border;
  row.style.transition = 'border-color 0.6s';
  row.style.border = '1px solid #f29400';
  setTimeout(() => {
    row.style.border = originalBorder || '';
  }, 600);
}
function detectCurrentSortMode() {
  const block = document.querySelector('.block-applicant-list');
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
function detectCurrentSearchMode() {
  const searchForm = document.querySelector('form[name=searchForm]');
  const hidden = searchForm?.querySelector('input[data-selectbox-hidden][name="searchMode"]');
  if (hidden && hidden.value) return String(hidden.value);
  const checked = searchForm?.querySelector('input[type="radio"][name="searchMode"]:checked');
  if (checked && checked.value) return String(checked.value);
  return null;
}
document.addEventListener('DOMContentLoaded', () => {
  currentSortMode = detectCurrentSortMode();
  const detectedSearchMode = detectCurrentSearchMode();
  if (detectedSearchMode) currentSearchMode = detectedSearchMode;
  //inputForm の初期値を保存（差分チェック用）
  const inputForm = document.forms?.inputForm;
  const nameInput = inputForm?.querySelector?.('input[name="applicant_name"]');
  const memoTextarea = inputForm?.querySelector?.('textarea[name="memo"]');
  if (nameInput) nameInput.dataset.initialValue = normalizeTextForCompare(nameInput.value);
  if (memoTextarea) memoTextarea.dataset.initialValue = normalizeTextForCompare(memoTextarea.value);
});
//「新規」ボタン（一覧差し替え後も動くように委譲で処理）
document.addEventListener('click', (e) => {
  const btn = e.target?.closest?.('button.item-register');
  if (!btn) return;
  e.preventDefault();
  createNewApplicationRow();
});
//inputForm「登録する」ボタン：名前/メモの更新
document.addEventListener('click', (e) => {
  const btn = e.target?.closest?.('form[name="inputForm"] button.item-confirm');
  if (!btn) return;
  e.preventDefault();
  const inputForm = document.forms?.inputForm;
  const nameInput = inputForm?.querySelector?.('input[name="applicant_name"]');
  const memoTextarea = inputForm?.querySelector?.('textarea[name="memo"]');
  if (!nameInput || !memoTextarea) {
    openResultModal({
      title: '登録エラー',
      messageLines: ['入力欄が見つかりませんでした。ページを再読み込みしてください。'],
      theme: 'bg-red',
    });
    return;
  }
  const nextName = nameInput.value ?? '';
  const nextMemo = memoTextarea.value ?? '';
  const prevName = normalizeTextForCompare(nameInput.dataset.initialValue ?? '');
  const prevMemo = normalizeTextForCompare(memoTextarea.dataset.initialValue ?? '');
  const changed =
    prevName !== normalizeTextForCompare(nextName) ||
    prevMemo !== normalizeTextForCompare(nextMemo);
  if (!changed) {
    openResultModal({
      title: '更新不要',
      messageLines: ['変更がありません。'],
      theme: 'bg-black',
    });
    return;
  }
  openConfirmModal({
    title: '登録確認',
    messageLines: ['名前・メモを登録します。', 'よろしいですか？'],
    onConfirm: async () => {
      const data = await postAndRedraw({
        action: 'updateApplicantProfile',
        searchMode: 'all',
        sortMode: currentSortMode,
        pageNumber: currentPageNumber || 1,
        extraFields: {
          applicantName: nextName,
          memo: nextMemo,
        },
      });
      //更新後の初期値を更新
      nameInput.dataset.initialValue = normalizeTextForCompare(nextName);
      memoTextarea.dataset.initialValue = normalizeTextForCompare(nextMemo);
      //見出しの表示名も可能なら更新
      const titleSpan = document.querySelector('.container-applicant-details h2 span');
      if (titleSpan && String(nextName).trim() !== '') {
        titleSpan.textContent = String(nextName).trim();
      }
      return {
        nextModal: {
          title: (data && data.title) || '登録完了',
          htmlMessage: (data && data.msg) || '登録しました。',
          allowHtml: true,
          theme: 'bg-black',
        },
      };
    },
  });
});
//「削除」ボタン（一覧差し替え後も動くように委譲で処理）
document.addEventListener('click', (e) => {
  const btn = e.target?.closest?.('.item-delate button');
  if (!btn) return;
  e.preventDefault();
  const row = btn.closest('li[data-application-id]');
  const applicationId = row ? parseInt(row.getAttribute('data-application-id') || '0', 10) : 0;
  if (!applicationId) {
    openResultModal({
      title: '削除エラー',
      messageLines: ['削除対象の応募IDが取得できませんでした。'],
      theme: 'bg-red',
    });
    return;
  }
  confirmAndPostAndRedraw({
    modalTitle: '削除確認',
    modalLines: ['この応募行を削除します。', 'よろしいですか？'],
    action: 'deleteApplication',
    searchMode: 'all',
    sortMode: currentSortMode,
    pageNumber: currentPageNumber || 1,
    extraFields: { applicationId },
  });
});
let __createDraftApplicationBusy = false;
async function createNewApplicationRow() {
  if (__createDraftApplicationBusy) return;
  __createDraftApplicationBusy = true;
  try {
    await postAndRedraw({
      action: 'createDraftApplication',
      searchMode: currentSearchMode,
      sortMode: currentSortMode,
      pageNumber: 1,
    });
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  } finally {
    __createDraftApplicationBusy = false;
  }
}
async function requestApplications({ action, searchMode, sortMode, pageNumber }) {
  const displayNumber = getCurrentDisplayNumber();
  const searchForm = document.querySelector('form[name=searchForm]');
  if (!searchForm) throw new Error('searchForm not found');
  const noUpDateKeyEl =
    searchForm?.querySelector('input[name="noUpDateKey"]') ||
    document.querySelector('input[name="noUpDateKey"]');
  const fd = new FormData(searchForm);
  fd.append('action', action);
  //searchMode はフォーム値を優先しつつ、明示指定があれば上書き
  if (searchMode && searchMode !== 'none') {
    fd.set('searchMode', searchMode);
  } else {
    const detectedSearchMode = detectCurrentSearchMode();
    if (detectedSearchMode) currentSearchMode = detectedSearchMode;
  }
  //sortMode は直近状態を維持しつつ、明示指定があれば上書き
  fd.append('sortMode', sortMode || 'none');
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
    location.href = './master04_01.php';
    throw new Error(data.title || 'Session error');
  }
  if (data && data.noUpDateKey && noUpDateKeyEl) {
    noUpDateKeyEl.value = String(data.noUpDateKey);
  }
  return data;
}
async function searchConditions(action, searchMode, sortMode) {
  try {
    if (action === 'reset') {
      currentSearchMode = 'none';
    }
    //検索ボタンは searchMode='none' で呼ばれるため、フォームの最新値に同期してから送信する。
    if (searchMode && searchMode !== 'none') {
      currentSearchMode = searchMode;
    } else {
      const detectedSearchMode = detectCurrentSearchMode();
      if (detectedSearchMode) currentSearchMode = detectedSearchMode;
    }
    if (sortMode && sortMode !== 'none') {
      currentSortMode = sortMode;
    } else {
      const detectedSortMode = detectCurrentSortMode();
      if (detectedSortMode) currentSortMode = detectedSortMode;
    }
    const list = await requestApplications({
      action,
      searchMode: currentSearchMode,
      sortMode: currentSortMode,
      pageNumber: 1,
    });
    currentPageNumber = 1;
    const nextTag = list && typeof list.tag === 'string' ? list.tag : '';
    replaceApplicantList(nextTag);
    syncModesFromDom();
    initSelectBox();
    scrollToTop();
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 変更系（一覧は毎回丸ごと再描画）
 *
 */
async function changeJobCategory(
  facId,
  userLineId,
  userLineName,
  facilityName,
  jobCardId,
  jobCategoryId,
  searchMode,
  sortMode
) {
  try {
    await postAndRedraw({
      action: 'changeJobCategory',
      searchMode,
      sortMode,
      pageNumber: 1,
      extraFields: {
        facId,
        lineId: userLineId,
        lineName: userLineName,
        facilityName,
        jobId: jobCardId,
        jobCategoryId,
      },
    });
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
async function changeDestination(
  facId,
  userLineId,
  userLineName,
  jobCardId,
  nextFacilityId,
  searchMode,
  sortMode
) {
  try {
    await postAndRedraw({
      action: 'changeFacility',
      searchMode,
      sortMode,
      pageNumber: 1,
      extraFields: {
        facId,
        lineId: userLineId,
        lineName: userLineName,
        jobId: jobCardId,
        newFacId: nextFacilityId,
      },
    });
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}

async function setInterviewAt(
  facId,
  userLineId,
  userLineName,
  jobCardId,
  interviewAt,
  searchMode,
  sortMode
) {
  try {
    await postAndRedraw({
      action: 'setInterviewAt',
      searchMode,
      sortMode,
      pageNumber: 1,
      extraFields: {
        facId,
        lineId: userLineId,
        lineName: userLineName,
        jobId: jobCardId,
        interviewAt,
      },
    });
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 確認モーダル（共通）
 *
 */
function openConfirmModal({ title, messageLines, onConfirm, onCancel }) {
  const blockModal = document.getElementById('modalBlock');
  if (!blockModal) return;
  const boxTitleP = blockModal.querySelector('.box-title p');
  const boxDetails = blockModal.querySelector('.box-details');
  if (!boxTitleP || !boxDetails) return;
  boxTitleP.textContent = title || '確認';
  boxDetails.innerHTML = '';
  const messageP = document.createElement('p');
  if (Array.isArray(messageLines) && messageLines.length > 0) {
    messageLines.forEach((line, idx) => {
      if (idx > 0) messageP.appendChild(document.createElement('br'));
      messageP.appendChild(document.createTextNode(String(line)));
    });
  }
  boxDetails.appendChild(messageP);
  const btnWrap = document.createElement('div');
  btnWrap.className = 'box-btn';
  boxDetails.appendChild(btnWrap);
  const cancelBtn = document.createElement('button');
  cancelBtn.type = 'button';
  cancelBtn.className = 'btn-cancel';
  cancelBtn.textContent = 'キャンセル';
  cancelBtn.addEventListener('click', () => {
    try {
      if (typeof onCancel === 'function') onCancel();
    } finally {
    }
    if (typeof closeModal === 'function') closeModal();
    blockModal.classList.remove('is-active');
    blockModal.classList.remove('bg-orange');
    blockModal.classList.remove('bg-black');
    document.documentElement.style.overflow = '';
  });
  btnWrap.appendChild(cancelBtn);
  const confirmBtn = document.createElement('button');
  confirmBtn.type = 'button';
  confirmBtn.className = 'btn-confirm';
  confirmBtn.textContent = 'はい';
  confirmBtn.addEventListener('click', async () => {
    let nextModal = null;
    try {
      if (typeof onConfirm === 'function') {
        const result = await onConfirm();
        if (result && typeof result === 'object' && result.nextModal) {
          nextModal = result.nextModal;
        }
      }
    } finally {
      blockModal.classList.remove('is-active');
      blockModal.classList.remove('bg-orange');
      blockModal.classList.remove('bg-black');
      document.documentElement.style.overflow = '';
    }
    if (nextModal) {
      openResultModal(nextModal);
    }
  });
  btnWrap.appendChild(confirmBtn);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 完了モーダル（閉じるのみ）
 *
 */
function openResultModal({ title, messageLines, htmlMessage, allowHtml, theme }) {
  const blockModal = document.getElementById('modalBlock');
  if (!blockModal) return;
  const boxTitleP = blockModal.querySelector('.box-title p');
  const boxDetails = blockModal.querySelector('.box-details');
  if (!boxTitleP || !boxDetails) return;
  boxTitleP.textContent = title || '更新完了';
  boxDetails.innerHTML = '';
  const messageP = document.createElement('p');
  if (allowHtml && typeof htmlMessage === 'string') {
    messageP.innerHTML = htmlMessage;
  } else if (Array.isArray(messageLines) && messageLines.length > 0) {
    messageLines.forEach((line, idx) => {
      if (idx > 0) messageP.appendChild(document.createElement('br'));
      messageP.appendChild(document.createTextNode(String(line)));
    });
  } else if (typeof htmlMessage === 'string' && htmlMessage !== '') {
    messageP.textContent = String(htmlMessage);
  } else {
    messageP.textContent = '更新しました。';
  }
  boxDetails.appendChild(messageP);
  const btnWrap = document.createElement('div');
  btnWrap.className = 'box-btn';
  boxDetails.appendChild(btnWrap);
  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'btn-cancel';
  closeBtn.textContent = '閉じる';
  closeBtn.addEventListener('click', () => {
    if (typeof closeModal === 'function') closeModal();
    blockModal.classList.remove('is-active');
    blockModal.classList.remove('bg-orange');
    blockModal.classList.remove('bg-black');
    document.documentElement.style.overflow = '';
  });
  btnWrap.appendChild(closeBtn);
  blockModal.classList.remove('bg-orange');
  blockModal.classList.remove('bg-black');
  blockModal.classList.add(theme === 'bg-orange' ? 'bg-orange' : 'bg-black');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
function syncSearchModeForm(nextSearchMode) {
  const searchForm = document.querySelector('form[name=searchForm]');
  if (!searchForm) return;
  const searchModeHidden = searchForm.querySelector(
    'input[data-selectbox-hidden][name="searchMode"]'
  );
  if (searchModeHidden) searchModeHidden.value = String(nextSearchMode || '');
  searchForm.querySelectorAll('input[type="radio"][name="searchMode"]').forEach((r) => {
    r.checked = String(r.value) === String(nextSearchMode);
  });
  const statusValue = searchForm.querySelector('.apply-status [data-selectbox-value]');
  const statusLabel = searchForm.querySelector(
    `label[for="application_status-${String(nextSearchMode)}"]`
  );
  if (statusValue) {
    statusValue.textContent = statusLabel ? statusLabel.textContent || '' : '';
  }
}
function getCurrentSelectboxHiddenValueFromEvent(event) {
  const inputEl = event?.currentTarget || event?.target;
  if (!inputEl || !inputEl.closest) return null;
  const selectboxEl = inputEl.closest('[data-selectbox]');
  if (!selectboxEl) return null;
  const inputName = inputEl.getAttribute('name') || '';
  const hiddenEls = selectboxEl.querySelectorAll('input[data-selectbox-hidden]');
  if (!hiddenEls || hiddenEls.length < 1) return null;
  if (inputName) {
    for (const el of hiddenEls) {
      if ((el.getAttribute('name') || '') === inputName) {
        return el.value ?? '';
      }
    }
  }
  //fallback：同名が取れない場合は最初のhidden
  return hiddenEls[0].value ?? '';
}
async function confirmAndPostAndRedraw({
  modalTitle,
  modalLines,
  action,
  extraFields,
  searchMode,
  sortMode,
  pageNumber,
}) {
  openConfirmModal({
    title: modalTitle,
    messageLines: modalLines,
    onConfirm: async () => {
      const data = await postAndRedraw({
        action,
        extraFields,
        searchMode,
        sortMode,
        pageNumber: pageNumber || 1,
      });
      return {
        nextModal: {
          title: (data && data.title) || '更新完了',
          htmlMessage: (data && data.msg) || '更新しました。',
          allowHtml: true,
          theme: 'bg-black',
        },
      };
    },
  });
}
/**
 * 変更系：確認 → 更新 → 一覧全再描画
 * - ※radioはクリック時に呼び、event.preventDefault() で選択確定前に止める
 */
function confirmChangeStatus(
  event,
  facId,
  userLineId,
  userLineName,
  facilityName,
  jobCardId,
  status,
  searchMode,
  sortMode,
  applicationId
) {
  //すでに選択済みの値をクリックした場合は何もしない（onclickは発火するためガード）
  if (event) {
    const currentVal = getCurrentSelectboxHiddenValueFromEvent(event);
    const clickedVal = String(event.currentTarget?.value ?? status ?? '');
    if (currentVal !== null && String(currentVal) === clickedVal) {
      return true;
    }
  }
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
    event.stopPropagation?.();
  }
  const statusLabelMap = {
    registered: '登録中',
    applied: '応募中',
    interview: '面接中',
    hired: '採用',
    rejected: '不採用',
    unresponsive: '連絡待ち',
  };
  const statusLabel = statusLabelMap[status] || String(status);
  //master04_01_01 は応募状況で絞り込まない
  const nextSearchMode = 'all';
  const nextSortMode = resolveSortMode(sortMode);
  openConfirmModal({
    title: '応募状況変更',
    messageLines: [
      `${facilityName}様への応募状況を「${statusLabel}」に変更します。`,
      'よろしいですか？',
    ],
    onConfirm: async () => {
      const data = await postAndRedraw({
        action: 'changeStatus',
        searchMode: nextSearchMode,
        sortMode: nextSortMode,
        pageNumber: 1,
        extraFields: {
          applicationId,
          facId,
          lineId: userLineId,
          lineName: userLineName,
          jobId: jobCardId,
          changeStatus: status,
        },
      });
      //以後のページング/再検索も現在のソートを維持
      currentSortMode = String(nextSortMode || '');
      return {
        nextModal: {
          title: (data && data.title) || '更新完了',
          htmlMessage: (data && data.msg) || '更新しました。',
          allowHtml: true,
          theme: 'bg-black',
        },
      };
    },
  });
  return false;
}
function confirmChangeJobCategory(
  event,
  facId,
  userLineId,
  userLineName,
  facilityName,
  jobCardId,
  jobCategoryId,
  jobCategoryLabel,
  searchMode,
  sortMode,
  applicationId
) {
  if (event) {
    const currentVal = getCurrentSelectboxHiddenValueFromEvent(event);
    const clickedVal = String(event.currentTarget?.value ?? jobCategoryId ?? '');
    if (currentVal !== null && String(currentVal) === clickedVal) {
      return true;
    }
  }
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
    event.stopPropagation?.();
  }
  const label = jobCategoryLabel ? String(jobCategoryLabel) : '（未選択）';
  const modalLines =
    facilityName && String(facilityName).trim() !== ''
      ? [`${facilityName}に応募している職種を「${label}」に変更します。`, 'よろしいですか？']
      : [`職種を「${label}」に変更します。`, 'よろしいですか？'];
  confirmAndPostAndRedraw({
    modalTitle: '職種変更',
    modalLines,
    action: 'changeJobCategory',
    searchMode,
    sortMode,
    pageNumber: 1,
    extraFields: {
      applicationId,
      facId,
      lineId: userLineId,
      lineName: userLineName,
      facilityName,
      jobId: jobCardId,
      jobCategoryId,
    },
  });
  return false;
}
function confirmChangeDestination(
  event,
  facId,
  userLineId,
  userLineName,
  jobCardId,
  nextFacilityId,
  nextFacilityName,
  searchMode,
  sortMode,
  applicationId
) {
  if (event) {
    const currentVal = getCurrentSelectboxHiddenValueFromEvent(event);
    const clickedVal = String(event.currentTarget?.value ?? nextFacilityId ?? '');
    if (currentVal !== null && String(currentVal) === clickedVal) {
      return true;
    }
  }
  if (event && typeof event.preventDefault === 'function') {
    event.preventDefault();
    event.stopPropagation?.();
  }
  const label = nextFacilityName ? String(nextFacilityName) : '（未選択）';
  confirmAndPostAndRedraw({
    modalTitle: '応募先変更',
    modalLines: [`応募先を「${label}」に変更します。`, 'よろしいですか？'],
    action: 'changeFacility',
    searchMode,
    sortMode,
    pageNumber: 1,
    extraFields: {
      applicationId,
      facId,
      lineId: userLineId,
      lineName: userLineName,
      jobId: jobCardId,
      newFacId: nextFacilityId,
    },
  });
  return false;
}
function confirmSetInterviewAt(
  inputEl,
  facId,
  userLineId,
  userLineName,
  jobCardId,
  interviewAt,
  searchMode,
  sortMode,
  applicationId
) {
  const prev = inputEl?.dataset?.prevValue ?? '';
  const next = interviewAt || '';
  if (String(prev) === String(next)) {
    return;
  }
  const label = next ? next : '（未設定）';
  openConfirmModal({
    title: '面接日設定',
    messageLines: [`面接日を「${label}」に変更します。`, 'よろしいですか？'],
    onCancel: () => {
      if (inputEl) inputEl.value = prev;
    },
    onConfirm: async () => {
      const data = await postAndRedraw({
        action: 'setInterviewAt',
        searchMode,
        sortMode,
        pageNumber: 1,
        extraFields: {
          applicationId,
          facId,
          lineId: userLineId,
          lineName: userLineName,
          jobId: jobCardId,
          interviewAt: next,
        },
      });
      return {
        nextModal: {
          title: (data && data.title) || '更新完了',
          htmlMessage: (data && data.msg) || '更新しました。',
          allowHtml: true,
          theme: 'bg-black',
        },
      };
    },
  });
}
/**
 * 互換：既存の呼び出し名を残す
 *
 */
function checkApplicationStatus(
  facId,
  userLineId,
  userLineName,
  facilityName,
  jobCardId,
  status,
  searchMode,
  sortMode
) {
  //互換：旧実装（onchange）からの呼び出し
  confirmChangeStatus(
    null,
    facId,
    userLineId,
    userLineName,
    facilityName,
    jobCardId,
    status,
    searchMode,
    sortMode,
    null
  );
}
/**
 * ページャー：ページ移動
 *
 */
async function movePage(pageNumber) {
  try {
    currentPageNumber = parseInt(String(pageNumber || 1), 10) || 1;
    const list = await requestApplications({
      action: 'page',
      searchMode: currentSearchMode,
      sortMode: currentSortMode,
      pageNumber,
    });
    const nextTag = list && typeof list.tag === 'string' ? list.tag : '';
    replaceApplicantList(nextTag);
    syncModesFromDom();
    initSelectBox();
    scrollToTop();
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
