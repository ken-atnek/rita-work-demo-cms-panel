/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master05_02.php';

/**
 * 運営管理：転職のヒント TOP表示設定（並び替え）
 * - 上下ボタン：1つ上/下へ移動
 * - D&D：btn-move をハンドルに行を並び替え（SESSIONに保持）
 * - 保存：SESSION順をDBへ確定（top_sortを1..Nで再付番）
 */
let currentNoUpDateKey =
  typeof window.__NO_UP_DATE_KEY__ === 'string' ? window.__NO_UP_DATE_KEY__ : '';
function updateNoUpDateKey(resJson) {
  if (resJson && typeof resJson.noUpDateKey === 'string' && resJson.noUpDateKey !== '') {
    currentNoUpDateKey = resJson.noUpDateKey;
    window.__NO_UP_DATE_KEY__ = currentNoUpDateKey;
  }
}
function replaceList(tagHtml) {
  const current = document.querySelector('.list-search-results');
  if (current) current.remove();
  const block = document.querySelector('.block-results');
  if (block) block.insertAdjacentHTML('afterbegin', tagHtml);
  const areaMaster = document.querySelector('.area-master');
  if (areaMaster) areaMaster.scrollIntoView(true);
}
async function postReorder(formData) {
  const response = await fetch(requestURL, { method: 'POST', body: formData });
  if (!response.ok) throw new Error('Network response was not ok');
  const json = await response.json();
  updateNoUpDateKey(json);
  return json;
}
/**
 * 上下ボタン：1つ上/下へ移動
 *
 */
async function moveLows(action, method, articleId, noUpDateKey) {
  try {
    const sFd = new FormData();
    sFd.append('action', String(action));
    sFd.append('method', String(method));
    sFd.append('articleId', String(articleId));
    sFd.append('noUpDateKey', String(noUpDateKey || currentNoUpDateKey));
    const res = await postReorder(sFd);
    if (res && res.status === 'error') {
      alert(res.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
      return;
    }
    if (res && typeof res.tag === 'string') {
      replaceList(res.tag);
    }
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 保存：SESSION順をDBに確定
 *
 */
async function saveLows() {
  try {
    const sFd = new FormData();
    sFd.append('action', 'save');
    sFd.append('method', 'save');
    sFd.append('noUpDateKey', String(currentNoUpDateKey));
    const res = await postReorder(sFd);
    const blockModal = document.getElementById('modalBlock');
    if (!blockModal) return;
    blockModal.classList.remove('bg-orange');
    blockModal.classList.remove('bg-black');
    const titleEl = blockModal.querySelector('.box-title p');
    const msgEl = blockModal.querySelector('.box-details p');
    const btnBox = blockModal.querySelector('.box-btn');
    if (btnBox) btnBox.querySelectorAll('button').forEach((b) => b.remove());
    if (!res || res.status === 'error') {
      blockModal.classList.add('bg-orange');
      if (titleEl) titleEl.innerHTML = '並び替え失敗';
      if (msgEl)
        msgEl.innerHTML =
          '記事の並び替えに失敗しました。<br>お手数ですが最初からやり直してください。';
      if (btnBox) {
        btnBox.insertAdjacentHTML(
          'beforeend',
          '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>'
        );
      }
    } else {
      blockModal.classList.add('bg-black');
      if (titleEl) titleEl.innerHTML = res.title || '並び替え完了';
      if (msgEl) msgEl.innerHTML = res.msg || '並び順を保存しました。';
      if (btnBox) {
        btnBox.insertAdjacentHTML(
          'beforeend',
          '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'master05_02.php\');">一覧に戻る</button>'
        );
      }
      const closeBtn = blockModal.querySelector('.box-title button');
      if (closeBtn) closeBtn.setAttribute('onclick', "closeModalToPage('master05_02.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 確認モーダル表示
 *
 */
function checkMoveLows() {
  const blockModal = document.getElementById('modalBlock');
  if (!blockModal) return;
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * Drag & Drop (btn-move ハンドル)
 *
 */
let dragAllowedArticleId = '';
let draggingEl = null;
let dropTargetEl = null;
let pendingTargetEl = null;
let pendingInsertAfter = false;
let didDrop = false;
function getListUl() {
  return document.querySelector('.list-search-results');
}
function getOrderFromDom() {
  const ul = getListUl();
  if (!ul) return [];
  return Array.from(ul.querySelectorAll('li[data-article-id]')).map((li) =>
    String(li.dataset.articleId || '')
  );
}
async function syncOrderToSession(orderArr) {
  try {
    const sFd = new FormData();
    sFd.append('action', 'move');
    sFd.append('method', 'setOrder');
    sFd.append('order', JSON.stringify(orderArr));
    sFd.append('noUpDateKey', String(currentNoUpDateKey));
    const res = await postReorder(sFd);
    if (res && res.status === 'error') {
      alert(res.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
      return;
    }
    if (res && typeof res.tag === 'string') {
      replaceList(res.tag);
    }
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
function installDnDOnce() {
  const block = document.querySelector('.block-results');
  if (!block) return;
  if (block.dataset.dndInitialized === '1') return;
  block.dataset.dndInitialized = '1';
  //ハンドル押下でドラッグ許可
  block.addEventListener('pointerdown', (e) => {
    const btn = e.target.closest('.btn-move');
    if (!btn) return;
    const li = btn.closest('li[data-article-id]');
    if (!li) return;
    dragAllowedArticleId = String(li.dataset.articleId || '');
  });
  block.addEventListener('dragstart', (e) => {
    const li = e.target.closest('li[data-article-id]');
    if (!li) return;
    const id = String(li.dataset.articleId || '');
    if (!id || id !== dragAllowedArticleId) {
      e.preventDefault();
      return;
    }
    draggingEl = li;
    dropTargetEl = null;
    pendingTargetEl = null;
    pendingInsertAfter = false;
    didDrop = false;
    const ul = getListUl();
    if (ul) ul.classList.add('is-dnd-dragging');
    draggingEl.classList.add('is-dragging');
    try {
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', id);
    } catch {}
  });
  block.addEventListener('dragover', (e) => {
    const ul = getListUl();
    if (!ul || !draggingEl) return;
    e.preventDefault();
    const target = e.target.closest('li[data-article-id]');
    if (!target || target === draggingEl) return;
    if (dropTargetEl && dropTargetEl !== target) {
      dropTargetEl.classList.remove('is-drop-target');
    }
    dropTargetEl = target;
    dropTargetEl.classList.add('is-drop-target');
    const rect = target.getBoundingClientRect();
    pendingInsertAfter = e.clientY - rect.top > rect.height / 2;
    pendingTargetEl = target;
  });
  block.addEventListener('drop', (e) => {
    if (!draggingEl) return;
    e.preventDefault();
    const ul = getListUl();
    if (ul && pendingTargetEl && pendingTargetEl !== draggingEl) {
      ul.insertBefore(
        draggingEl,
        pendingInsertAfter ? pendingTargetEl.nextSibling : pendingTargetEl
      );
    }
    const order = getOrderFromDom();
    didDrop = true;
    if (ul) ul.classList.remove('is-dnd-dragging');
    if (dropTargetEl) dropTargetEl.classList.remove('is-drop-target');
    dropTargetEl = null;
    pendingTargetEl = null;
    pendingInsertAfter = false;
    draggingEl.classList.remove('is-dragging');
    draggingEl = null;
    dragAllowedArticleId = '';
    if (order.length > 0) {
      syncOrderToSession(order);
    }
  });
  block.addEventListener('dragend', () => {
    const ul = getListUl();
    if (ul) ul.classList.remove('is-dnd-dragging');
    if (dropTargetEl) dropTargetEl.classList.remove('is-drop-target');
    dropTargetEl = null;
    pendingTargetEl = null;
    pendingInsertAfter = false;
    if (draggingEl) draggingEl.classList.remove('is-dragging');
    draggingEl = null;
    dragAllowedArticleId = '';
    didDrop = false;
  });
}
document.addEventListener('DOMContentLoaded', () => {
  installDnDOnce();
});
