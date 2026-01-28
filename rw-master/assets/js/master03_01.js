/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master03_01.php';
/**
 * 検索条件確認：直近の並び替え状態（ページ移動・絞り込みでも維持する）
 *
 */
let currentSortMode = 'sortId_desc';
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
  const cFd = mergeFormData(searchForm, filterForm);
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
    location.href = './master03_01.php';
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
 * 事業所検索・絞り込み
 *
 */
function mergeFormData(...forms) {
  const merged = new FormData();
  for (const form of forms) {
    const fd = new FormData(form);
    for (const [k, v] of fd.entries()) {
      //同名キーが複数ある場合は「複数値」として append される
      merged.append(k, v);
    }
  }
  return merged;
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
          document.querySelector('input[name="searchFacilityId"]').value = '';
          document.querySelector('input[name="searchFacilityName"]').value = '';
          document.querySelector('input[name="searchStartDay"]').value = '';
          document.querySelector('input[name="searchEndDay"]').value = '';
          const selectInitialsDiv = document.querySelectorAll('.item-check-box');
          //チェックボックスのchecked解除
          selectInitialsDiv.forEach((initialDiv) => {
            //チェックボックス判定
            let targetRadio = initialDiv.querySelector('input[type="radio"]');
            if (targetRadio != null) {
              targetRadio.checked = false;
            }
          });
        }
        break;
      //絞り込み解除
      case 'release':
        {
          const selectInitialsDiv = document.querySelectorAll('.item-check-box');
          //チェックボックスのchecked解除
          selectInitialsDiv.forEach((initialDiv) => {
            //チェックボックス判定
            let targetCheckBox = initialDiv.querySelector('input[type="checkbox"]');
            if (targetCheckBox != null) {
              targetCheckBox.checked = false;
            }
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
 * 新規事業所登録チェック
 *
 */
function checkNewFacility() {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  htmlElement.style.overflow = 'hidden';
}
