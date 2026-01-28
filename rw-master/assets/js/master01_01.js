/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master01_01.php';
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
let currentSearchMode = 'registered';

function detectInitialSearchMode() {
  const btn = document.querySelector('.block-status button.is-active');
  if (!btn) return 'registered';
  const statusClass = Array.from(btn.classList).find((c) => c.startsWith('status-'));
  return statusClass ? statusClass.replace('status-', '') : 'registered';
}

function detectInitialSortMode() {
  const block = document.querySelector('.block-search-results');
  const fromData = block ? (block.getAttribute('data-current-sort-mode') || '') : '';
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
    location.href = './master01_01.php';
    throw new Error(data.title || 'Session error');
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
      //登録中
      case 'registered':
        {
          document.querySelector('.status-registered').classList.add('is-active');
        }
        break;
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
      //連絡待ち
      case 'unresponsive':
        {
          document.querySelector('.status-unresponsive').classList.add('is-active');
        }
        break;
      //デフォルト：登録中
      default:
        {
          document.querySelector('.status-registered').classList.add('is-active');
        }
        break;
    }
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    document.querySelector('.area-master').scrollIntoView(true);
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
    document.querySelector('.area-master').scrollIntoView(true);
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
