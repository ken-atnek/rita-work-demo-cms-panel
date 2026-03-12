/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client07_01.php';
/**
 * 送信前チェック
 *
 */
async function checkInput() {
  // .validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  //必須入力項目確認（各種入力チェック）
  let errorMessage = '';
  let errorInput = '';
  let errFlag = 0;
  //== 件名入力チェック
  const selectSubject = document.querySelector('input[type="hidden"][name="selectSubject"]').value;
  if ((errFlag === 0 && selectSubject === '') || selectSubject === null) {
    errorMessage = '件名を選択してください。';
    errorInput = 'input[type="hidden"][name="selectSubject"]';
    errFlag = 1;
  }
  //== 返信方法入力チェック
  const selectReplyMethod = document.querySelector(
    'input[type="hidden"][name="selectReplyMethod"]'
  ).value;
  if ((errFlag === 0 && selectReplyMethod === '') || selectReplyMethod === null) {
    errorMessage = '返信方法を選択してください。';
    errorInput = 'input[type="hidden"][name="selectReplyMethod"]';
    errFlag = 1;
  }
  //== 本文入力チェック
  const messageBody = document.querySelector('textarea[name="messageBody"]').value;
  if ((errFlag === 0 && messageBody === '') || messageBody === null) {
    errorMessage = '本文を入力してください。';
    errorInput = 'textarea[name="messageBody"]';
    errFlag = 1;
  }
  //エラーがあれば処理を中断してエラー応答
  if (errorMessage != '') {
    let blockModal = document.getElementById('modalBlockAlert');
    //ボタンタグを全て取得
    let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
    //ボタンタグを削除
    buttonList.forEach((ElementButton) => {
      ElementButton.remove();
    });
    blockModal.querySelector('.box-title p').innerHTML = 'メッセージ入力確認';
    blockModal.querySelector('.box-details p').innerHTML = errorMessage;
    //閉じるボタン生成
    let cancelButton =
      '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
    blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
    blockModal.classList.add('bg-orange');
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
    //入力項目までスクロール
    document.querySelector(errorInput).scrollIntoView(true);
    return;
  }
  if (errFlag === 0) {
    let checkForm = document.querySelector('form[name=inputForm]');
    let cFd = new FormData(checkForm);
    try {
      const response = await fetch(requestURL, {
        method: 'POST',
        body: cFd,
      });
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      if (data && data.noUpDateKey && noUpDateKeyEl) {
        noUpDateKeyEl.value = String(data.noUpDateKey);
      }
      if (data && data.status === 'error' && !data.tag) {
        alert(data.msg || '通信エラーが発生しました。ページを再読み込みしてください。');
        location.href = './client07_01.php';
        return;
      }
      //表示中の情報入替
      const currentInner = document.querySelector('.modal-article .inner-modal');
      if (currentInner) currentInner.remove();
      //ページ表示
      const modalArticle = document.querySelector('.modal-article');
      if (modalArticle && data && data.tag) {
        modalArticle.insertAdjacentHTML('afterbegin', data.tag);
      }
      const blockModal = document.getElementById('modalBlock');
      if (!blockModal) return;
      blockModal.classList.add('bg-orange');
      blockModal.classList.add('is-active');
      document.documentElement.style.overflow = 'hidden';
    } catch (error) {
      console.error('送信エラー:', error);
      alert('通信エラーが発生しました。ページを再読み込みしてください。');
    }
  }
}
/**
 * 送信
 *
 */
async function sendInput() {
  // .validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  const noUpDateKeyEl = document.querySelector('input[name="noUpDateKey"]');
  let sendForm = document.querySelector('form[name=inputForm]');
  let sFd = new FormData(sendForm);
  sFd.append('action', 'sendInput');
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: sFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    if (list && list.noUpDateKey && noUpDateKeyEl) {
      noUpDateKeyEl.value = String(list.noUpDateKey);
    }
    let blockModal = document.getElementById('modalBlockAlert');
    if (list['status'] == 'error') {
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = 'メッセージ送信失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        'メッセージの送信に失敗しました。再度やり直してください。';
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
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'client07_02.php\');">一覧に戻る</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('client07_02.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
