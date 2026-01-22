/**
 * モーダルクウィンドウ用
 *
 */
const htmlElement = document.querySelector('html');
/**
 * モーダルクローズ
 *
 */
function closeModal() {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.remove('is-active');
  blockModal.classList.remove('bg-orange');
  blockModal.classList.remove('bg-black');
  htmlElement.style.overflow = '';
}
/**
 * モーダルクローズしてページ移動
 *
 */
function closeModalToPage(page) {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.remove('is-active');
  blockModal.classList.remove('bg-orange');
  blockModal.classList.remove('bg-black');
  htmlElement.style.overflow = '';
  //ページ移動
  location.href = page;
}

//HTML側（inline onclick）から呼べるようにグローバルへ公開
window.closeModal = closeModal;
window.closeModalToPage = closeModalToPage;
