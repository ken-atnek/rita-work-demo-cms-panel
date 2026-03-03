/**
 * モーダルクウィンドウ用
 *
 */
// NOTE: document.documentElement は常に存在するため、外部JS依存の変数は持たない
/**
 * モーダルクローズ
 *
 */
function closeModal() {
  const modals = [
    document.getElementById('modalBlock'),
    document.getElementById('modalBlockAlert'),
  ];
  let didClose = false;
  modals.forEach((blockModal) => {
    if (!blockModal) return;
    blockModal.classList.remove('is-active');
    blockModal.classList.remove('bg-orange');
    blockModal.classList.remove('bg-black');
    didClose = true;
  });
  if (!didClose) return;
  document.documentElement.style.overflow = '';
}
/**
 * モーダルクローズしてページ移動
 *
 */
function closeModalToPage(page) {
  closeModal();
  //ページ移動
  location.href = page;
}
/**
 * HTML側（inline onclick）から呼べるようにグローバルへ公開
 *
 */
window.closeModal = closeModal;
window.closeModalToPage = closeModalToPage;
