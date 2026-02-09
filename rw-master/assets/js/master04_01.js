/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master04_01.php';
/**
 * 応募状況ボタン切替
 *
 */
//フォーム連結（同名キーは複数値として append）
function mergeFormData(...forms) {
  const merged = new FormData();
  for (const form of forms) {
    if (!form) continue;
    const fd = new FormData(form);
    for (const [k, v] of fd.entries()) {
      merged.append(k, v);
    }
  }
  return merged;
}
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
let currentSearchMode = 'none';
function detectCurrentSortMode() {
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
});
async function requestApplications({ action, searchMode, sortMode, pageNumber }) {
  const displayNumber = getCurrentDisplayNumber();
  const searchForm = document.querySelector('form[name=searchForm]');
  const filterForm = document.querySelector('form[name=filterForm]');
  const noUpDateKeyEl =
    searchForm?.querySelector('input[name="noUpDateKey"]') ||
    document.querySelector('input[name="noUpDateKey"]');
  const fd = mergeFormData(searchForm, filterForm);
  //職種：checked radio を優先、無ければ selectbox hidden
  //（initSelectBox未初期化/再初期化漏れでも検索が壊れないようにする）
  {
    const checked = searchForm?.querySelector('input[type="radio"][name="referJobType"]:checked');
    if (checked && checked.value != null) {
      fd.set('referJobType', String(checked.value || ''));
    } else {
      const referJobHidden = searchForm?.querySelector(
        'input[data-selectbox-hidden][name="referJobType"]'
      );
      if (referJobHidden) {
        fd.set('referJobType', String(referJobHidden.value || ''));
      }
    }
  }
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
    const nextTag = list && typeof list.tag === 'string' ? list.tag : '';
    const currentResults = document.querySelector('.block-search-results');
    const filterBlock = document.querySelector('.block-filter');
    if (!nextTag || !filterBlock) {
      console.error('AJAX応答が不正です', list);
      throw new Error('Invalid AJAX response');
    }
    //表示中の情報入替
    if (currentResults) currentResults.remove();
    //ページ表示
    filterBlock.insertAdjacentHTML('afterend', nextTag);
    //サーバ側の現行状態に同期（セッション初期化などのズレを吸収）
    {
      const detectedSortMode = detectCurrentSortMode();
      if (detectedSortMode) currentSortMode = detectedSortMode;
      const detectedSearchMode = detectCurrentSearchMode();
      if (detectedSearchMode) currentSearchMode = detectedSearchMode;
    }
    //input情報クリア（フォーム自体は差し替えないため、UIも同期する）
    switch (action) {
      case 'reset':
        {
          const searchForm = document.querySelector('form[name=searchForm]');
          if (searchForm) {
            const referNameEl = searchForm.querySelector('input[name="referName"]');
            if (referNameEl) referNameEl.value = '';
            const facilityNameEl = searchForm.querySelector('input[name="facility_name"]');
            if (facilityNameEl) facilityNameEl.value = '';
            const startDayEl = searchForm.querySelector('input[name="startDay"]');
            if (startDayEl) startDayEl.value = '';
            const endDayEl = searchForm.querySelector('input[name="endDay"]');
            if (endDayEl) endDayEl.value = '';
            //職種（selectbox）
            const referJobHidden = searchForm.querySelector(
              'input[data-selectbox-hidden][name="referJobType"]'
            );
            if (referJobHidden) referJobHidden.value = '';
            searchForm.querySelectorAll('input[type="radio"][name="referJobType"]').forEach((r) => {
              r.checked = false;
            });
            const jobTypeValue = searchForm.querySelector(
              '.select-job-type [data-selectbox-value]'
            );
            if (jobTypeValue) jobTypeValue.textContent = '選択してください';
            //応募状況（selectbox）
            const searchModeHidden = searchForm.querySelector(
              'input[data-selectbox-hidden][name="searchMode"]'
            );
            if (searchModeHidden) searchModeHidden.value = '';
            //未選択に戻す（プレースホルダradioは持たない）
            searchForm.querySelectorAll('input[type="radio"][name="searchMode"]').forEach((r) => {
              r.checked = false;
            });
            const statusValue = searchForm.querySelector('.apply-status [data-selectbox-value]');
            if (statusValue) statusValue.textContent = '選択してください';
          }
          //絞り込み（チェックボックス）
          document
            .querySelectorAll('input[type="checkbox"][name="searchInitials[]"]')
            .forEach((c) => {
              c.checked = false;
            });
          currentSearchMode = 'none';
        }
        break;
      case 'release':
        {
          document
            .querySelectorAll('input[type="checkbox"][name="searchInitials[]"]')
            .forEach((c) => {
              c.checked = false;
            });
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
  const blockModal = document.getElementById('modalBlock');
  if (!blockModal) return;
  const boxTitleP = blockModal.querySelector('.box-title p');
  const boxDetails = blockModal.querySelector('.box-details');
  if (!boxTitleP || !boxDetails) return;
  boxTitleP.textContent = '応募状況変更';
  //既存内容をクリア（以前の .wrap-details 削除や .box-btn の残骸をまとめて解消）
  boxDetails.innerHTML = '';
  const statusLabelMap = {
    registered: '登録中',
    applied: '応募中',
    interview: '面接中',
    hired: '採用',
    rejected: '不採用',
    unresponsive: '連絡待ち',
  };
  const statusLabel = statusLabelMap[status] || '登録中';
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
    if (typeof closeModal === 'function') closeModal();
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
  const searchForm = document.querySelector('form[name=searchForm]');
  const filterForm = document.querySelector('form[name=filterForm]');
  let cFd = mergeFormData(searchForm, filterForm);
  cFd.append('action', 'changeStatus');
  cFd.append('facId', facId);
  cFd.append('lineId', userLineId);
  cFd.append('lineName', userLineName);
  cFd.append('jobId', jobCardId);
  cFd.append('changeStatus', status);
  //変更後のステータス（タブ）を表示する
  cFd.set('searchMode', nextSearchMode);
  cFd.append('sortMode', nextSortMode);
  cFd.append('displayNumber', String(displayNumber));
  cFd.append('pageNumber', String(1));
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const blockModal = document.getElementById('modalBlock');
    if (blockModal) {
      blockModal.classList.remove('is-active');
      blockModal.classList.remove('bg-orange');
      blockModal.classList.remove('bg-black');
    }
    document.documentElement.style.overflow = '';
    const list = await response.json();
    if (list && list.status === 'error') {
      alert(list.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
      location.href = './master04_01.php';
      return;
    }
    //以後のページング/再検索も変更後タブを基準にする
    currentSearchMode = nextSearchMode;
    currentSortMode = nextSortMode;
    //検索フォーム側（応募状況セレクト）も変更後ステータスに同期
    //※フォーム自体は差し替えないため、ここでhidden/radio/表示を更新する
    if (searchForm) {
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
    const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
    if (list && list.noUpDateKey && noUpDateKeyEl) {
      noUpDateKeyEl.value = String(list.noUpDateKey);
    }
    const nextTag = list && typeof list.tag === 'string' ? list.tag : '';
    const currentResults = document.querySelector('.block-search-results');
    const filterBlock = document.querySelector('.block-filter');
    if (!nextTag || !filterBlock) {
      console.error('AJAX応答が不正です', list);
      throw new Error('Invalid AJAX response');
    }
    //表示中の情報入替
    if (currentResults) currentResults.remove();
    //ページ表示
    filterBlock.insertAdjacentHTML('afterend', nextTag);
    //サーバ側の現行状態に同期
    {
      const detectedSortMode = detectCurrentSortMode();
      if (detectedSortMode) currentSortMode = detectedSortMode;
      const detectedSearchMode = detectCurrentSearchMode();
      if (detectedSearchMode) currentSearchMode = detectedSearchMode;
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
    const list = await requestApplications({
      action: 'page',
      searchMode: currentSearchMode,
      sortMode: currentSortMode,
      pageNumber,
    });
    const nextTag = list && typeof list.tag === 'string' ? list.tag : '';
    const currentResults = document.querySelector('.block-search-results');
    const filterBlock = document.querySelector('.block-filter');
    if (!nextTag || !filterBlock) {
      console.error('AJAX応答が不正です', list);
      throw new Error('Invalid AJAX response');
    }
    //表示中の情報入替
    if (currentResults) currentResults.remove();
    //ページ表示
    filterBlock.insertAdjacentHTML('afterend', nextTag);
    //サーバ側の現行状態に同期
    {
      const detectedSortMode = detectCurrentSortMode();
      if (detectedSortMode) currentSortMode = detectedSortMode;
      const detectedSearchMode = detectCurrentSearchMode();
      if (detectedSearchMode) currentSearchMode = detectedSearchMode;
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
