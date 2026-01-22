// ----------------------------
// ul内のliごとにhidden重複を除去するユーティリティ
// ----------------------------
function cleanAllHiddenInUl(ul, hiddenName) {
  const lis = ul.querySelectorAll('li');
  lis.forEach((li) => {
    const hiddens = li.querySelectorAll('input[type="hidden"][name="' + hiddenName + '"]');
    if (hiddens.length > 1) {
      for (let i = 1; i < hiddens.length; i++) {
        hiddens[i].remove();
      }
    }
  });
}
// ----------------------------
// li追加時のhidden重複防止ユーティリティ
// ----------------------------
function removeDuplicateHiddenInLi(li, hiddenName) {
  const hiddens = li.querySelectorAll('input[type="hidden"][name="' + hiddenName + '"]');
  if (hiddens.length > 1) {
    // 先頭以外を削除
    for (let i = 1; i < hiddens.length; i++) {
      hiddens[i].remove();
    }
  }
}
// ----------------------------
// 共通：パネルの開閉（is-active）をトグル
// ----------------------------
function togglePanel(panelId, btnSelector) {
  const panel = document.getElementById(panelId);
  if (!panel) return false;

  const isOpen = panel.classList.toggle('is-active');
  panel.setAttribute('aria-hidden', String(!isOpen));

  const btn = btnSelector ? document.querySelector(btnSelector) : null;
  if (btn) {
    btn.setAttribute('aria-expanded', String(isOpen));
    btn.classList.toggle('is-active', isOpen);
  }

  return isOpen;
}

// ----------------------------
// 初期化：閉じた状態にする
// ----------------------------
function initClosed(panelId, btnId) {
  const panel = document.getElementById(panelId);
  if (panel) {
    // 読み込み時にアクティブ状態ならアクティブのまま
    if (panel.classList.contains('load') && panel.classList.contains('is-active')) {
      return;
    }
    panel.classList.remove('is-active');
    panel.setAttribute('aria-hidden', 'true');
  }

  const btn = document.getElementById(btnId);
  if (btn) {
    btn.classList.remove('is-active');
    btn.setAttribute('aria-expanded', 'false');
  }
}
//グローバルスコープで参照できるようにする
window.initClosed = initClosed;
// ----------------------------
// クリック紐付け
// ----------------------------
function bindToggle(btnId, panelId) {
  const btn = document.getElementById(btnId);
  if (!btn) return;

  btn.addEventListener('click', () => {
    togglePanel(panelId, `#${btnId}`);
  });
}
//グローバルスコープで参照できるようにする
window.bindToggle = bindToggle;

document.addEventListener('DOMContentLoaded', () => {
  // 初期は閉
  initClosed('innerSearch', 'btnSwitchSearch');
  initClosed('innerFilter', 'btnSwitchFilter');

  // トグル動作
  bindToggle('btnSwitchSearch', 'innerSearch');
  bindToggle('btnSwitchFilter', 'innerFilter');

  // 初期化
  initSelectBox();
});

// ---------------------------------------------------------------
// セレクトボックス（状態付与 + hidden反映 / 開閉はCSS）
// ---------------------------------------------------------------
let selectBoxGlobalEventsInitialized = false;
let openBox = null;
// initSelectBox()の再実行でイベントが二重登録されないようにする（DOM cloneの影響を受けない）
const selectBoxInitializedSet = new WeakSet();
function initSelectBox() {
  (() => {
    const boxes = Array.from(document.querySelectorAll('[data-selectbox]'));
    if (boxes.length === 0) return;

    /** @type {HTMLElement | null} */
    //let openBox = null;

    const getParts = (box) => {
      const head = box.querySelector('.selectbox__head');
      const valueEl = box.querySelector('[data-selectbox-value]');
      const hiddenEl = box.querySelector('[data-selectbox-hidden]');
      return { head, valueEl, hiddenEl };
    };

    // ★追加：labelの status-* クラスを head に同期する
    const syncHeadStatusClass = (head, label) => {
      if (!head) return;

      // headから status-系クラスを削除
      Array.from(head.classList).forEach((c) => {
        if (c.startsWith('status-')) head.classList.remove(c);
      });

      if (!label) return;

      // labelに付いてる status-系クラスを head に付与
      Array.from(label.classList).forEach((c) => {
        if (c.startsWith('status-')) head.classList.add(c);
      });
    };

    // 状態クラス制御
    // hiddenのnameが配列（[]付き）かどうかで値のセット方法を分岐
    const setEmptyState = (box, hiddenEl) => {
      box.classList.remove('is-selected');
      box.classList.add('is-empty');
      if (!hiddenEl) return;
      // 配列hiddenでも、box内のhiddenElのみを更新する
      hiddenEl.value = '';
    };

    const setSelectedState = (box, hiddenEl, value) => {
      box.classList.add('is-selected');
      box.classList.remove('is-empty');
      if (!hiddenEl) return;
      hiddenEl.value = value;
    };

    const close = (box) => {
      const { head } = getParts(box);
      if (!head) return;

      box.classList.remove('is-open');
      head.setAttribute('aria-expanded', 'false');

      if (openBox === box) openBox = null;
    };

    const open = (box) => {
      const { head } = getParts(box);
      if (!head) return;

      if (openBox && openBox !== box) close(openBox);

      box.classList.add('is-open');
      head.setAttribute('aria-expanded', 'true');

      openBox = box;
    };

    const toggle = (box) => {
      box.classList.contains('is-open') ? close(box) : open(box);
    };

    // 初期化 & イベント
    // ul単位でhidden重複を除去
    if (boxes.length > 0) {
      // 最初のboxからulを取得（全て同じul内想定）
      const firstBox = boxes[0];
      const ul = firstBox.closest('ul');
      if (ul) {
        // name属性は最初のhiddenから取得
        const firstHidden = firstBox.querySelector('input[type="hidden"]');
        if (firstHidden && firstHidden.name) {
          cleanAllHiddenInUl(ul, firstHidden.name);
        }
      }
    }

    boxes.forEach((box, idx) => {
      const { head, valueEl, hiddenEl } = getParts(box);
      if (!head || !valueEl) return;

      const radios = box.querySelectorAll('input[type="radio"]');

      // hiddenのnameが[]で終わるか判定（今後の拡張用に残すが、実際の値セットはbox内hiddenElのみ）
      let isArray = false;
      if (hiddenEl && hiddenEl.name && /\[\]$/.test(hiddenEl.name)) {
        isArray = true;
      }

      // li要素内のhidden重複を除去（li直下でなくてもOK）
      if (hiddenEl && hiddenEl.name) {
        removeDuplicateHiddenInLi(box, hiddenEl.name);
      }

      // 初期状態：閉
      box.classList.remove('is-open');
      head.setAttribute('aria-expanded', 'false');

      // 初期値反映：radioのcheckedよりhiddenのvalueを優先
      // （編集ページでradioグループの都合でcheckedが最後の1件しか成立せず、
      // initSelectBoxが他行のhiddenを空に上書きしてしまうのを防ぐ）
      const hiddenValue = hiddenEl ? String(hiddenEl.value || '').trim() : '';
      if (hiddenValue !== '') {
        // 表示文字は既にHTML側でセットされている想定だが、radio/labelがあれば同期しておく
        const radioByValue = box.querySelector(
          `input[type="radio"][value="${CSS.escape(hiddenValue)}"]`
        );
        const label = radioByValue ? box.querySelector(`label[for="${radioByValue.id}"]`) : null;
        if (label) valueEl.textContent = label.textContent.trim();
        syncHeadStatusClass(head, label);
        setSelectedState(box, hiddenEl, hiddenValue, idx, isArray);
      } else {
        // hiddenが空の時のみcheckedを参照
        const checked = box.querySelector('input[type="radio"]:checked');
        if (checked) {
          const label = box.querySelector(`label[for="${checked.id}"]`);
          if (label) valueEl.textContent = label.textContent.trim();
          syncHeadStatusClass(head, label); // ★追加：初期状態も同期
          setSelectedState(box, hiddenEl, checked.value, idx, isArray);
        } else {
          syncHeadStatusClass(head, null); // ★追加：status-* を消す
          setEmptyState(box, hiddenEl, idx, isArray);
        }
      }

      // 重要：initSelectBox()の再実行でイベントが二重登録されると
      // クリック1回で「開く→閉じる」が連続発火し、既存boxが動かないように見える。
      // WeakSetで初期化済みを判定（cloneで誤判定しない）。
      if (selectBoxInitializedSet.has(box)) return;

      // ヘッダクリックで開閉（クラス付与のみ）
      head.addEventListener('click', (e) => {
        e.preventDefault();
        toggle(box);
      });

      // 選択時：表示更新 + headクラス同期 + hidden反映 + 閉じる
      radios.forEach((r) => {
        r.addEventListener('change', () => {
          const label = box.querySelector(`label[for="${r.id}"]`);
          if (label) valueEl.textContent = label.textContent.trim();
          syncHeadStatusClass(head, label); // ★追加：選択時も同期
          setSelectedState(box, hiddenEl, r.value, idx, isArray);
          close(box);
        });
      });

      selectBoxInitializedSet.add(box);
    });

    // 外側クリックで閉じる
    if (!selectBoxGlobalEventsInitialized) {
      document.addEventListener('click', (e) => {
        if (!openBox) return;
        if (!openBox.contains(e.target)) close(openBox);
      });

      // Escで閉じる
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && openBox) close(openBox);
      });
      selectBoxGlobalEventsInitialized = true;
    }
  })();
}
window.initSelectBox = initSelectBox;
