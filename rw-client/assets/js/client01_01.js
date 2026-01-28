/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client01_01.php';
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
  const blockModal = document.getElementById('modalBlock');
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
    const list = await response.json();
    if (list && list.status === 'error') {
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
function makeNewsModal() {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  htmlElement.style.overflow = 'hidden';
}
