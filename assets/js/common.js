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
    panel.classList.remove('is-active');
    panel.setAttribute('aria-hidden', 'true');
  }

  const btn = document.getElementById(btnId);
  if (btn) {
    btn.classList.remove('is-active');
    btn.setAttribute('aria-expanded', 'false');
  }
}

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

document.addEventListener('DOMContentLoaded', () => {
  // 初期は閉
  initClosed('innerSearch', 'btnSwitchSearch');
  initClosed('innerFilter', 'btnSwitchFilter');

  // トグル動作
  bindToggle('btnSwitchSearch', 'innerSearch');
  bindToggle('btnSwitchFilter', 'innerFilter');
});
