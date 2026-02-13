/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master03_02.php';

/**
 * ステータス変更：キャンセル時に元に戻す
 * - initSelectBox() が radio click で hidden/value を即更新するため、
 *  「変更前」を click の capture フェーズで退避しておく。
 */
const __jobCardStatusPrevByJobId = Object.create(null);
let __jobCardStatusPending = null;
function __getJobCardStatusParts(jobCardId) {
  const name = `list_status${jobCardId}`;
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
function __applyJobCardStatusToUi(jobCardId, statusValue) {
  const { box, head, valueEl, hiddenEl, radios } = __getJobCardStatusParts(jobCardId);
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
  const blockModal = document.getElementById('modalBlock');
  return blockModal?.querySelector('.box-title button') || null;
}
function __setModalCloseToCancel() {
  const btn = __getModalCloseButton();
  if (!btn) return;
  if (!btn.dataset.__defaultOnclick) {
    btn.dataset.__defaultOnclick = btn.getAttribute('onclick') || 'closeModal()';
  }
  btn.setAttribute('onclick', 'cancelJobCardStatusChange();');
}
function __restoreModalCloseDefault() {
  const btn = __getModalCloseButton();
  if (!btn) return;
  const def = btn.dataset.__defaultOnclick || 'closeModal()';
  btn.setAttribute('onclick', def);
}
function cancelJobCardStatusChange() {
  try {
    if (__jobCardStatusPending && __jobCardStatusPending.jobCardId) {
      const prev = String(__jobCardStatusPending.prevValue || '').trim();
      if (prev) __applyJobCardStatusToUi(__jobCardStatusPending.jobCardId, prev);
    }
  } finally {
    __jobCardStatusPending = null;
    __restoreModalCloseDefault();
    if (typeof closeModal === 'function') closeModal();
  }
}
window.cancelJobCardStatusChange = cancelJobCardStatusChange;
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
    const jobCardId = parseInt(m[1], 10);
    if (!Number.isFinite(jobCardId)) return;
    const { hiddenEl } = __getJobCardStatusParts(jobCardId);
    const prev = hiddenEl ? String(hiddenEl.value || '').trim() : '';
    if (prev) __jobCardStatusPrevByJobId[String(jobCardId)] = prev;
  },
  true
);
/**
 * 新規求人カード登録チェック
 *
 */
function checkNewJobCard() {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人カード状態変更チェック
 *
 */
function checkJobCardStatus(facId, joCardCode, jobCardId, status, execution) {
  let blockModal = document.getElementById('modalBlock');
  const prevValue =
    __jobCardStatusPrevByJobId[String(jobCardId)] ||
    __getJobCardStatusParts(jobCardId).hiddenEl?.value ||
    '';
  //変更が無い場合は何もしない（同一値選択での無駄なモーダル抑止）
  if (String(prevValue || '') === String(status || '')) {
    return;
  }
  __jobCardStatusPending = {
    jobCardId: Number(jobCardId),
    facId: Number(facId),
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
  blockModal.querySelector('.box-title p').innerHTML = '求人カード状況';
  switch (status) {
    //「下書き」へ
    case '1':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          '求人ID：' + joCardCode + 'を「下書き中」に変更します。よろしいですか？';
      }
      break;
    //「公開」へ
    case '2':
      {
        if (execution == 'restore') {
          blockModal.querySelector('.box-title p').innerHTML = '求人カード再掲載';
          blockModal.querySelector('.box-details p').innerHTML =
            '求人ID：' + joCardCode + 'を再掲載します。よろしいですか？';
        } else {
          blockModal.querySelector('.box-details p').innerHTML =
            '求人ID：' + joCardCode + 'を「公開中」に変更します。よろしいですか？';
        }
      }
      break;
    //「解約」
    case '99':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          '求人ID：' +
          joCardCode +
          'を<span style="font-weight:bold;color:#0000CD;">解約</span>します。よろしいですか？';
      }
      break;
    //デフォルト：削除
    default:
      {
        blockModal.querySelector('.box-title p').innerHTML = '求人カード削除';
        blockModal.querySelector('.box-details p').innerHTML =
          '求人ID：' +
          joCardCode +
          'を<span style="font-weight:bold;color:#DD0000;">削除</span>します。よろしいですか？';
      }
      break;
  }
  //キャンセルボタン生成
  let cancelButton =
    '<button type="button" class="btn-cancel" onclick="cancelJobCardStatusChange();">キャンセル</button>';
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
  //登録ボタン生成
  const safeJobCardCode = String(joCardCode)
    .replace(/\\/g, '\\\\')
    .replace(/'/g, "\\'")
    .replace(/\r/g, '\\r')
    .replace(/\n/g, '\\n');
  const safeExecution = String(execution)
    .replace(/\\/g, '\\\\')
    .replace(/'/g, "\\'")
    .replace(/\r/g, '\\r')
    .replace(/\n/g, '\\n');
  let addButton = `<button type="button" class="btn-confirm" onclick="changeJobCardStatus(${facId},'${safeJobCardCode}',${jobCardId},${status},'${safeExecution}');">はい</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人カード状態変更
 *
 */
async function changeJobCardStatus(facId, joCardCode, jobCardId, status, execution) {
  let cFd = new FormData();
  cFd.append('action', 'changeStatus');
  cFd.append('facId', facId);
  cFd.append('jobId', jobCardId);
  cFd.append('jobCardCode', joCardCode);
  cFd.append('changeStatus', status);
  cFd.append('execution', execution);
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
      //失敗時：UIは元に戻す
      if (__jobCardStatusPending && __jobCardStatusPending.jobCardId) {
        const prev = String(__jobCardStatusPending.prevValue || '').trim();
        if (prev) __applyJobCardStatusToUi(__jobCardStatusPending.jobCardId, prev);
      }
      __jobCardStatusPending = null;
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
      __jobCardStatusPending = null;
      __restoreModalCloseDefault();
      blockModal.classList.add('bg-black');
      blockModal.querySelector('.box-title p').innerHTML = list['title'];
      blockModal.querySelector('.box-details p').innerHTML = list['msg'];
      //一覧へ戻るボタン生成
      let newButton = `<button type="button" class="btn-cancel" onclick="closeModalToPage('master03_02.php?facId=${list['facId']}');">一覧に戻る</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', `closeModalToPage('master03_02.php?facId=${list['facId']}')`);
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    //通信失敗時も元に戻す
    if (__jobCardStatusPending && __jobCardStatusPending.jobCardId) {
      const prev = String(__jobCardStatusPending.prevValue || '').trim();
      if (prev) __applyJobCardStatusToUi(__jobCardStatusPending.jobCardId, prev);
    }
    __jobCardStatusPending = null;
    __restoreModalCloseDefault();
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * プレビューページを開く
 *
 */
function openPreviewPage(url) {
  window.open(url, '_blank');
}
