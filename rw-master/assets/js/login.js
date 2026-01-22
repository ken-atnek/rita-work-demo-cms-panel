/**
 * パスワード忘れボタン
 *
 */
async function showResetPassword() {
  //フォームデータ生成
  const makeFd = new FormData();
  makeFd.append('mode', 'resetPwForm');
  //パスワードリセットフォームを取得するリクエストを送信
  try {
    const response = await fetch('./assets/function/login.php', {
      method: 'POST',
      body: makeFd,
    });
    if (!response.ok) throw new Error('Failed to fetch reset form');
    const list = await response.json();
    // ログインフォーム・リセットフォーム・新パスワードフォームをすべて削除
    const loginForm = document.querySelector('form[name="loginForm"]');
    if (loginForm) loginForm.remove();
    const resetForm = document.querySelector('form[name="resetForm"]');
    if (resetForm) resetForm.remove();
    const newPwForm = document.querySelector('form[name="newPwForm"]');
    if (newPwForm) newPwForm.remove();
    //取得したフォームHTMLをロゴ下へ挿入して切り替え
    document.querySelector('.item-logo').insertAdjacentHTML('afterend', list.tag);
  } catch (error) {
    console.error(error);
  }
}
/**
 * パスワード再設定用URL発行
 *
 */
async function sendResetURL() {
  //フォームデータ生成
  const resetForm = document.querySelector('form[name="resetForm"]');
  const makeFd = new FormData(resetForm);
  //パスワードリセットフォームを取得するリクエストを送信
  try {
    const response = await fetch('./assets/function/login.php', {
      method: 'POST',
      body: makeFd,
    });
    if (!response.ok) {
      throw new Error('Failed to fetch reset form');
    }
    const list = await response.json();
    const currentForm = document.querySelector('form[name="resetForm"]');
    if (currentForm) {
      currentForm.remove();
    }
    //取得したフォームHTMLをロゴ下へ挿入して切り替え
    document.querySelector('.item-logo').insertAdjacentHTML('afterend', list.tag);
  } catch (error) {
    console.error(error);
  }
}
/**
 * パスワード再設定
 *
 */
async function sendNewPassword() {
  const newPwForm = document.querySelector('form[name="newPwForm"]');
  if (!newPwForm) return;
  const newPassword = newPwForm.querySelector('input[name="newPassword"]').value;
  const confirmPassword = newPwForm.querySelector('input[name="confirmPassword"]').value;
  //エラー表示用divがなければ作成
  let errorDivTextCaution = newPwForm.querySelector('.text-caution');
  if (!errorDivTextCaution) {
    errorDivTextCaution = document.createElement('div');
    errorDivTextCaution.className = 'text-caution';
    errorDivTextCaution.style.display = 'none';
    newPwForm.appendChild(errorDivTextCaution);
  }
  //バリデーション
  if (!newPassword || !confirmPassword) {
    errorDivTextCaution.textContent = '新しいパスワードを入力してください。';
    errorDivTextCaution.style.display = 'block';
    setInputFocusClearError(newPwForm, errorDivTextCaution);
    return;
  }
  if (newPassword !== confirmPassword) {
    errorDivTextCaution.textContent = '確認用パスワードが一致しません。';
    errorDivTextCaution.style.display = 'block';
    setInputFocusClearError(newPwForm, errorDivTextCaution);
    return;
  }
  //エラーが無ければ再設定処理
  errorDivTextCaution.textContent = '';
  errorDivTextCaution.style.display = 'none';
  try {
    //フォームデータ生成
    const makeFd = new FormData(newPwForm);
    const response = await fetch('./assets/function/login.php', {
      method: 'POST',
      body: makeFd,
    });
    if (!response.ok) throw new Error('Failed to fetch reset form');
    const list = await response.json();
    //新パスワードフォームを削除
    if (newPwForm) newPwForm.remove();
    //取得したフォームHTMLをロゴ下へ挿入して切り替え
    document.querySelector('.item-logo').insertAdjacentHTML('afterend', list.tag);
  } catch (error) {
    console.error(error);
  }
}
//入力欄にフォーカスしたらエラーを消す
function setInputFocusClearError(newPwForm, errorDivTextCaution) {
  const newPassword = newPwForm.querySelector('input[name="newPassword"]');
  const confirmPassword = newPwForm.querySelector('input[name="confirmPassword"]');
  if (newPassword) {
    newPassword.addEventListener('focus', function handler() {
      errorDivTextCaution.textContent = '';
      errorDivTextCaution.style.display = 'none';
      newPassword.removeEventListener('focus', handler);
    });
  }
  if (confirmPassword) {
    confirmPassword.addEventListener('focus', function handler() {
      errorDivTextCaution.textContent = '';
      errorDivTextCaution.style.display = 'none';
      confirmPassword.removeEventListener('focus', handler);
    });
  }
}
//パスワード表示ボタン
function togglePassword(target) {
  const targetTag = document.getElementById(target);
  targetTag.type = targetTag.type === 'password' ? 'text' : 'password';
}
