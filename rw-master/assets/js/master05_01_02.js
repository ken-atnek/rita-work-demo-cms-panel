/**
 * master05_01_02.js
 * - 転職のヒント：登録/編集（AJAX）
 * - DB保存 / JSON書き出し / 画像保存は proc_master05_01_02.php
 */
const requestURL = './assets/function/proc_master05_01_02.php';

function $(sel) {
  return document.querySelector(sel);
}

function getForm() {
  return document.querySelector('form[name=inputForm]');
}

function getInputValue(id, fallback = '') {
  const el = document.getElementById(id);
  if (!el) return fallback;
  return String(el.value ?? fallback);
}

function setInputValue(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = String(value ?? '');
}

function syncNoUpDateKey(nextKey) {
  if (!nextKey) return;
  setInputValue('noUpDateKey', nextKey);
}

function getEditor() {
  return window.RW_TIPTAP_EDITOR || null;
}

async function postFormData(fd) {
  const res = await fetch(requestURL, { method: 'POST', body: fd });
  const response = await res.json().catch(() => null);
  if (!res.ok || !response) {
    throw new Error('通信に失敗しました');
  }
  if (response.noUpDateKey) syncNoUpDateKey(response.noUpDateKey);
  if (response.status === 'error') {
    throw new Error(response.msg || '処理に失敗しました');
  }
  return response;
}

/**
 * TipTap から呼ばれる画像アップロードhook
 *  window.rwTipTapUploadImage(file) => Promise<string(url)>
 */
window.rwTipTapUploadImage = async (file) => {
  const fd = new FormData();
  fd.append('action', 'uploadInlineImage');
  // form(hidden) を単一の情報源にする
  fd.append('noUpDateKey', getInputValue('noUpDateKey', ''));
  fd.append('method', getInputValue('method', 'new'));
  fd.append('articleId', getInputValue('articleId', '0'));
  fd.append('file', file);

  const json = await postFormData(fd);
  if (!json.url) throw new Error('画像URLの取得に失敗しました');
  return String(json.url);
};
/**
 * 完了モーダルアラート
 *
 */
function openSuccessModal(message) {
  const blockModal = document.getElementById('modalBlock');
  if (!blockModal) return;
  blockModal.classList.remove('bg-orange');
  blockModal.classList.add('bg-black');
  const titleEl = blockModal.querySelector('.box-title p');
  const msgEl = blockModal.querySelector('.box-details p');
  if (titleEl) titleEl.textContent = '新規記事情報';
  if (msgEl) msgEl.textContent = message || '登録が完了しました。';
  //ボタン再生成（成功時：一覧へ戻る）
  const btnWrap = blockModal.querySelector('.box-btn');
  if (btnWrap) {
    btnWrap.innerHTML =
      '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'./master05_01_01.php\')">一覧に戻る</button>';
  }
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * エラーモーダルアラート
 *
 */
function openErrorModal(title, message) {
  const blockModal = document.getElementById('modalBlock');
  if (!blockModal) return;
  blockModal.classList.remove('bg-black');
  blockModal.classList.add('bg-orange');
  const titleEl = blockModal.querySelector('.box-title p');
  const msgEl = blockModal.querySelector('.box-details p');
  if (titleEl) titleEl.textContent = title || 'エラー';
  if (msgEl) msgEl.textContent = message || '処理に失敗しました。';
  const btnWrap = blockModal.querySelector('.box-btn');
  if (btnWrap) {
    btnWrap.innerHTML =
      '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
  }
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}

//
function ensureEditorContentFromConfig() {
  const editor = getEditor();
  if (!editor) return false;
  const initCfg = window.RW_MASTER05_01_02 || {};
  if (initCfg.initialBodyJson) {
    try {
      editor.commands.setContent(initCfg.initialBodyJson);
      return true;
    } catch (e) {
      console.warn(e);
    }
  }
  return false;
}
/**
 * 送信前チェック
 *
 */
async function checkInput() {
  try {
    const editor = getEditor();
    if (!editor) {
      openErrorModal(
        '記事登録失敗',
        'エディタの初期化に失敗しました。ページを再読み込みしてください。'
      );
      return;
    }
    const title = $('input[name="title"]')?.value?.trim() || '';
    const isTop = $('input[name="is_top"]')?.checked ? 1 : 0;
    if (!title) {
      openErrorModal('記事登録失敗', 'タイトルを入力してください。');
      return;
    }
    //表示期間（オプション）
    // - 機能無効化（ページ側フラグ）時は period UI が存在しない想定
    // - periodFeatureEnabled=false または initialPeriodType==='none' は「機能自体を使わない」として扱う
    const initCfg = window.RW_MASTER05_01_02 || {};
    const periodFeatureEnabled = initCfg.periodFeatureEnabled !== false;
    const periodUiExists = !!document.querySelector('input[name="periodType"]');
    const periodEnabled =
      periodFeatureEnabled && periodUiExists && String(initCfg.initialPeriodType || '') !== 'none';

    let periodType = 'none';
    let from = '';
    let to = '';
    if (periodEnabled) {
      const periodFrom = document.getElementById('periodFrom');
      const periodTo = document.getElementById('periodTo');
      const selected = document.querySelector('input[name="periodType"]:checked');
      periodType = selected ? String(selected.value) : 'from';
      from =
        periodType === 'from' || periodType === 'from_to' ? String(periodFrom?.value || '') : '';
      to =
        periodType === 'registered_to' || periodType === 'from_to'
          ? String(periodTo?.value || '')
          : '';
      if (periodType === 'from_to' && from && to && from > to) {
        openErrorModal(
          '記事登録失敗',
          '表示期間が不正です（開始日が終了日より後になっています）。'
        );
        return;
      }
    }
    const checkForm = getForm();
    if (!checkForm) {
      openErrorModal('記事登録失敗', 'フォームが見つかりません。ページを再読み込みしてください。');
      return;
    }
    //HTMLのformデータを単一の情報源にする
    const cfd = new FormData(checkForm);
    cfd.set('action', 'checkInput');
    cfd.set('title', title);
    //checkbox は未チェックだと form から消えるので明示
    cfd.set('is_top', String(isTop));
    const bodyPayload = {
      editor: editor.getJSON(),
      period: { type: periodType, from: from, to: to },
    };
    cfd.append('body_json', JSON.stringify(bodyPayload));
    cfd.append('body_text', editor.getHTML());
    const response = await postFormData(cfd);
    if (!response.tag) {
      throw new Error('確認ページの生成に失敗しました。');
    }
    //既存の確認画面があれば差し替え
    const existingConfirm = document.querySelector('.container-confirm');
    if (existingConfirm) existingConfirm.remove();
    //入力画面は削除せず非表示（修正で戻れるように）
    const editContainer = document.querySelector('.container-register');
    if (editContainer) editContainer.style.display = 'none';
    //ページ表示
    document.querySelector('.page-nav').insertAdjacentHTML('afterend', response.tag);
    //ページの上端までスクロール
    const areaMaster = document.querySelector('.area-master');
    if (areaMaster) areaMaster.scrollIntoView(true);
  } catch (error) {
    console.error(error);
    openErrorModal('記事登録失敗', String(error?.message || error));
  }
}
/**
 * 入力内容修正
 * - 確認ページから入力画面へ戻す
 *
 */
function fixInput() {
  //確認ページタグ削除
  const confirmContainer = document.querySelector('.container-confirm');
  if (confirmContainer) confirmContainer.remove();
  //入力ページを再表示
  const editContainer = document.querySelector('.container-register');
  if (editContainer) editContainer.style.display = '';
  //ページの上端までスクロール
  const areaMaster = document.querySelector('.area-master');
  if (areaMaster) areaMaster.scrollIntoView(true);
}
/**
 * 送信
 *
 */
async function sendInput() {
  try {
    const sendForm = getForm();
    if (!sendForm) {
      openErrorModal('記事登録失敗', 'フォームが見つかりません。ページを再読み込みしてください。');
      return;
    }
    const editor = getEditor();
    if (!editor) {
      openErrorModal(
        '記事登録失敗',
        'エディタの初期化に失敗しました。ページを再読み込みしてください。'
      );
      return;
    }
    const title = $('input[name="title"]')?.value?.trim() || '';
    const isTop = $('input[name="is_top"]')?.checked ? 1 : 0;
    if (!title) {
      openErrorModal('記事登録失敗', 'タイトルを入力してください。');
      return;
    }
    //表示期間（オプション）
    const initCfg = window.RW_MASTER05_01_02 || {};
    const periodFeatureEnabled = initCfg.periodFeatureEnabled !== false;
    const periodUiExists = !!document.querySelector('input[name="periodType"]');
    const periodEnabled =
      periodFeatureEnabled && periodUiExists && String(initCfg.initialPeriodType || '') !== 'none';

    let periodType = 'none';
    let from = '';
    let to = '';
    if (periodEnabled) {
      const periodFrom = document.getElementById('periodFrom');
      const periodTo = document.getElementById('periodTo');
      const selected = document.querySelector('input[name="periodType"]:checked');
      periodType = selected ? String(selected.value) : 'from';
      from =
        periodType === 'from' || periodType === 'from_to' ? String(periodFrom?.value || '') : '';
      to =
        periodType === 'registered_to' || periodType === 'from_to'
          ? String(periodTo?.value || '')
          : '';
      if (periodType === 'from_to' && from && to && from > to) {
        openErrorModal(
          '記事登録失敗',
          '表示期間が不正です（開始日が終了日より後になっています）。'
        );
        return;
      }
    }
    //送信中にページ離脱した場合、ドラフト破棄が先に走るとセッション競合し得るため抑止
    window.__rwUploadDraftDiscardDisable = true;
    //HTMLのformデータを単一の情報源にする
    const sfd = new FormData(sendForm);
    sfd.set('action', 'sendInput');
    sfd.set('title', title);
    //checkbox は未チェックだと form から消えるので明示
    sfd.set('is_top', String(isTop));
    const bodyPayload = {
      editor: editor.getJSON(),
      period: { type: periodType, from: from, to: to },
    };
    sfd.append('body_json', JSON.stringify(bodyPayload));
    sfd.append('body_text', editor.getHTML());
    const response = await postFormData(sfd);
    openSuccessModal(response.msg);
  } catch (error) {
    console.error(error);
    openErrorModal('記事登録失敗', String(error?.message || error));
  } finally {
    window.__rwUploadDraftDiscardDisable = false;
  }
}
/**
 * 記事削除チェック
 *
 */
function checkDeleteTips(articleId, noUpDateKey) {
  //確認メッセージ
  let blockModal = document.getElementById('modalBlock');
  //削除確認メッセージ
  blockModal.querySelector('.box-title p').innerHTML = '記事削除';
  blockModal.querySelector('.box-details p').innerHTML = `記事を削除します。よろしいですか？`;
  //ボタン再生成
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  //ボタン生成
  let newButton =
    `<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>` +
    `<button type="button" class="btn-confirm" onclick="deleteTips('${articleId}','${noUpDateKey}');">削除する</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 記事削除実行
 *
 */
async function deleteTips(articleId, noUpDateKey) {
  //formData生成
  let dFd = new FormData();
  dFd.append('method', 'delete');
  dFd.append('action', 'sendInput');
  dFd.append('articleId', String(articleId));
  dFd.append('noUpDateKey', String(noUpDateKey || getInputValue('noUpDateKey', '')));
  try {
    //送信中にページ離脱した場合、ドラフト破棄が先に走るとセッション競合し得るため抑止
    window.__rwUploadDraftDiscardDisable = true;
    const list = await postFormData(dFd);
    //確認モーダルクリア
    let blockModal = document.getElementById('modalBlock');
    blockModal.classList.remove('is-active');
    blockModal.classList.remove('bg-orange');
    blockModal.classList.remove('bg-black');
    if (list['status'] == 'error') {
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = '削除失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '記事の削除に失敗しました。<br>お手数ですが最初からやり直してください。';
      //ボタン再生成
      let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
      //ボタンタグを削除
      buttonList.forEach((ElementButton) => {
        ElementButton.remove();
      });
      //ボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
    } else {
      blockModal.classList.add('bg-black');
      blockModal.querySelector('.box-title p').innerHTML = list['title'];
      blockModal.querySelector('.box-details p').innerHTML = list['msg'];
      //ボタン再生成
      let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
      //ボタンタグを削除
      buttonList.forEach((ElementButton) => {
        ElementButton.remove();
      });
      //ボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'master05_01_01.php\');">一覧に戻る</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('master05_01_01.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    openErrorModal('記事削除失敗', String(error?.message || error));
  } finally {
    window.__rwUploadDraftDiscardDisable = false;
  }
}
/**
 * グローバルスコープで参照できるようにする
 *
 */
window.checkInput = checkInput;
window.fixInput = fixInput;
window.sendInput = sendInput;
window.checkDeleteTips = checkDeleteTips;
window.deleteTips = deleteTips;
/**
 * TipTapエディタ有効化チェック
 *
 */
window.addEventListener('rw:tiptap-ready', () => {
  ensureEditorContentFromConfig();
});
document.addEventListener('DOMContentLoaded', () => {
  //既に editor が初期化済みのケース
  ensureEditorContentFromConfig();
  const initCfg = window.RW_MASTER05_01_02 || {};
  //====================
  // 表示期間（オプション）
  //====================
  const periodFeatureEnabled = initCfg.periodFeatureEnabled !== false;
  const periodRadios = Array.from(document.querySelectorAll('input[name="periodType"]'));
  const periodFrom = document.getElementById('periodFrom');
  const periodTo = document.getElementById('periodTo');
  const periodFromWrap = document.getElementById('periodFromWrap');
  const periodToWrap = document.getElementById('periodToWrap');
  const registeredAtInfo = document.getElementById('registeredAtInfo');
  //日付変換
  function localTodayYMD() {
    const d = new Date();
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
  }
  //表示期間：チェックボックス確認
  function applyPeriodState() {
    const selected = document.querySelector('input[name="periodType"]:checked');
    const type = selected ? String(selected.value) : 'from';
    //UI表示制御（tiptap_app_def.js の挙動に寄せる）
    //〇月〇日から表示
    if (type === 'from') {
      if (periodFromWrap) periodFromWrap.style.display = '';
      if (periodToWrap) periodToWrap.style.display = 'none';
      if (registeredAtInfo) registeredAtInfo.textContent = '';
      if (periodFrom) {
        periodFrom.disabled = false;
        if (!periodFrom.value) periodFrom.value = localTodayYMD();
      }
      if (periodTo) {
        periodTo.disabled = true;
        periodTo.value = '';
      }
      return;
    }
    //登録日から〇月〇日まで表示
    if (type === 'registered_to') {
      if (periodFromWrap) periodFromWrap.style.display = 'none';
      if (periodToWrap) periodToWrap.style.display = '';
      if (registeredAtInfo) {
        registeredAtInfo.textContent = '登録日: （初回保存時に自動設定）';
      }
      if (periodFrom) {
        periodFrom.disabled = true;
        periodFrom.value = '';
      }
      if (periodTo) {
        periodTo.disabled = false;
        if (!periodTo.value) periodTo.value = localTodayYMD();
      }
      return;
    }
    //〇月〇日から〇月〇日まで表示
    if (type === 'from_to') {
      if (periodFromWrap) periodFromWrap.style.display = '';
      if (periodToWrap) periodToWrap.style.display = '';
      if (registeredAtInfo) registeredAtInfo.textContent = '';
      if (periodFrom) {
        periodFrom.disabled = false;
        if (!periodFrom.value) periodFrom.value = localTodayYMD();
      }
      if (periodTo) {
        periodTo.disabled = false;
        if (!periodTo.value) periodTo.value = localTodayYMD();
      }
      return;
    }
    //想定外の値は from と同等に扱う
    if (periodFromWrap) periodFromWrap.style.display = '';
    if (periodToWrap) periodToWrap.style.display = 'none';
    if (registeredAtInfo) registeredAtInfo.textContent = '';
    if (periodFrom) {
      periodFrom.disabled = false;
      if (!periodFrom.value) periodFrom.value = localTodayYMD();
    }
    if (periodTo) {
      periodTo.disabled = true;
      periodTo.value = '';
    }
  }
  // 機能無効化時はUIもイベントも触らない
  if (
    !periodFeatureEnabled ||
    String(initCfg.initialPeriodType || '') === 'none' ||
    periodRadios.length === 0
  ) {
    // no-op
  } else {
    //初期値反映
    try {
      const initType = String(initCfg.initialPeriodType || 'from');
      const initFrom = String(initCfg.initialPeriodFrom || '');
      const initTo = String(initCfg.initialPeriodTo || '');
      let normalizedType = initType;
      const allowed = ['from', 'registered_to', 'from_to'];
      if (!allowed.includes(normalizedType)) {
        if (initFrom && initTo) normalizedType = 'from_to';
        else if (initFrom) normalizedType = 'from';
        else if (initTo) normalizedType = 'registered_to';
        else normalizedType = 'from';
      }
      periodRadios.forEach((r) => {
        r.checked = String(r.value) === normalizedType;
      });
      if (periodFrom) periodFrom.value = initFrom;
      if (periodTo) periodTo.value = initTo;
    } catch {
      // no-op
    }
    applyPeriodState();
    periodRadios.forEach((r) => r.addEventListener('change', applyPeriodState));
    if (periodFrom) periodFrom.addEventListener('change', applyPeriodState);
    if (periodTo) periodTo.addEventListener('change', applyPeriodState);
  }
});
