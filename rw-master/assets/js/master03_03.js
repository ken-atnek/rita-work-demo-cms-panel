/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master03_03.php';
/**
 * パスワード入力チェック
 *
 */
function checkPasswordSetting(facId, accountStatus) {
  const pwForm = document.querySelector('form[name="inputForm"]');
  if (!pwForm) return;
  //let currentPassword = '';
  //if (accountStatus == 'edit') {
  //  currentPassword = pwForm.querySelector('input[name="currentPassword"]').value;
  //}
  const newPassword = pwForm.querySelector('input[name="newPassword"]').value;
  const confirmPassword = pwForm.querySelector('input[name="confirmNewPassword"]').value;
  //入力チェック
  let checkFlag = true;
  let errorType = '';
  //パスワード未入力チェック
  if (newPassword == '' || newPassword == null) {
    checkFlag = false;
    errorType = 'newPassword_none';
  } else if (confirmPassword == '' || confirmPassword == null) {
    checkFlag = false;
    errorType = 'confirmPassword_none';
  }
  //確認用パスワード入力チェック
  if (newPassword !== confirmPassword) {
    checkFlag = false;
    errorType = 'password_check';
  }
  let blockModal = document.getElementById('modalBlock');
  //ボタンタグを全て取得
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  blockModal.classList.add('bg-orange');
  if (checkFlag == true) {
    blockModal.querySelector('.box-title p').innerHTML = 'パスワード設定';
    if (accountStatus == 'new') {
      blockModal.querySelector('.box-details p').innerHTML =
        'パスワードを登録します。よろしいですか？';
    } else {
      blockModal.querySelector('.box-details p').innerHTML =
        'パスワードを変更します。よろしいですか？';
    }
    //ボタン生成
    let newButton =
      '<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>';
    blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
    //はいボタン生成
    let addButton =
      '<button type="button" class="btn-confirm" onclick="setPassword(\'' +
      facId +
      "', '" +
      accountStatus +
      '\');">はい</button>';
    blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
  } else {
    blockModal.querySelector('.box-title p').innerHTML = 'パスワード設定失敗';
    switch (errorType) {
      //新しいパスワード未入力
      case 'newPassword_none':
        {
          blockModal.querySelector('.box-details p').innerHTML =
            '新しいパスワードを入力して下さい。';
        }
        break;
      //確認用パスワード未入力
      case 'confirmPassword_none':
        {
          blockModal.querySelector('.box-details p').innerHTML =
            '確認用パスワードを入力して下さい。';
        }
        break;
      //新しいパスワード・確認用パスワードチェックエラー
      case 'password_check':
        {
          blockModal.querySelector('.box-details p').innerHTML =
            '新しいパスワードと確認用パスワードが一致しません。';
        }
        break;
    }
    //ボタン生成
    let newButton =
      '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
    blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
  }
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * パスワード設定
 *
 */
async function setPassword(facId, accountStatus) {
  const pwForm = document.querySelector('form[name="inputForm"]');
  if (!pwForm) return;
  const blockModal = document.getElementById('modalBlock');
  try {
    //フォームデータ生成
    const makeFd = new FormData(pwForm);
    makeFd.append('method', accountStatus);
    makeFd.append('facId', facId);
    const response = await fetch(requestURL, {
      method: 'POST',
      body: makeFd,
    });
    if (!response.ok) throw new Error('Failed to fetch reset form');
    const list = await response.json();
    //モーダル表示内容更新
    if (blockModal) {
      //ボタンタグを全て削除
      const buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
      buttonList.forEach((ElementButton) => {
        ElementButton.remove();
      });
      blockModal.querySelector('.box-title p').innerHTML = list['title'] || 'パスワード設定';
      blockModal.querySelector('.box-details p').innerHTML = list['msg'] || '処理が完了しました。';
      const isSuccess = list.status === 'success';
      blockModal.classList.remove('bg-orange');
      blockModal.classList.remove('bg-black');
      blockModal.classList.add(isSuccess ? 'bg-orange' : 'bg-black');
      //閉じる（成功時は再読込して new/edit 表示を反映）
      const closeButton = isSuccess
        ? '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'./master03_01_01.php?method=edit&facId=' +
          facId +
          '\')">閉じる</button>'
        : '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', closeButton);
      blockModal.classList.add('is-active');
      document.documentElement.style.overflow = 'hidden';
    }
  } catch (error) {
    console.error(error);
    if (blockModal) {
      const buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
      buttonList.forEach((ElementButton) => {
        ElementButton.remove();
      });
      blockModal.querySelector('.box-title p').innerHTML = 'パスワード設定';
      blockModal.querySelector('.box-details p').innerHTML =
        '通信に失敗しました。時間をおいて再度お試しください。';
      blockModal.classList.remove('bg-orange');
      blockModal.classList.remove('bg-black');
      blockModal.classList.add('bg-black');
      const closeButton =
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', closeButton);
      blockModal.classList.add('is-active');
      document.documentElement.style.overflow = 'hidden';
    }
  }
}
/**
 * パスワード表示ボタン
 *
 */
function togglePassword(el, target) {
  const targetTag = document.getElementById(target);
  targetTag.type = targetTag.type === 'password' ? 'text' : 'password';
  //アイコン変更
  if (el.classList.contains('is-close') === true) {
    el.classList.remove('is-close');
    el.classList.add('is-open');
  } else {
    el.classList.remove('is-open');
    el.classList.add('is-close');
  }
}
