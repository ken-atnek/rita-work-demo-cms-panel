/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master02_02.php';
/**
 * 送信前チェック
 *
 */
async function checkInput() {
  // .validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  let errFlag = 0;
  //「required」を指定した要素を検証
  let chk_required = checkRequiredElem();
  if (chk_required == 'err') {
    errFlag = 1;
  }
  //.email を指定した要素
  if (errFlag === 0) {
    //.email を指定した要素を検証
    let chk_email = checkEmailElem();
    if (chk_email == 'err') {
      errFlag = 1;
    }
  }
  //.phone_number を指定した要素
  if (errFlag === 0) {
    //.phone_number を指定した要素を検証
    let chk_tel = checkTelElem();
    if (chk_tel == 'err') {
      errFlag = 1;
    }
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
      const list = await response.json();
      //表示中の情報入替
      document.querySelector('.container-company-register').remove();
      //ページ表示
      document.querySelector('.page-nav').insertAdjacentHTML('afterend', list['tag']);
      //mainタグクラス名切替
      const mainTag = document.querySelector('main');
      mainTag.classList.remove('inner-02-02');
      mainTag.classList.add('inner-02-03');
      sendBtn.disabled = true;
      //ページの上端までスクロール
      const areaMaster = document.querySelector('.area-master');
      if (areaMaster) areaMaster.scrollIntoView(true);
    } catch (error) {
      console.error('送信エラー:', error);
      alert('通信エラーが発生しました。ページを再読み込みしてください。');
    }
  }
}
/**
 * 入力内容修正
 *
 */
async function historyBack() {
  // .validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  let backForm = document.querySelector('form[name=inputForm]');
  let bFd = new FormData(backForm);
  bFd.append('action', 'fixInput');
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: bFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    //表示中の情報入替
    document.querySelector('.container-company-check').remove();
    //ページ表示
    document.querySelector('.page-nav').insertAdjacentHTML('afterend', list['tag']);
    //mainタグクラス名切替
    const mainTag = document.querySelector('main');
    mainTag.classList.remove('inner-02-03');
    mainTag.classList.add('inner-02-02');
    sendBtn.disabled = true;
    //ページの上端までスクロール
    const areaMaster = document.querySelector('.area-master');
    if (areaMaster) areaMaster.scrollIntoView(true);
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 送信
 *
 */
async function sendInput() {
  // .validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  let sendForm = document.querySelector('form[name=inputForm]');
  let sFd = new FormData(sendForm);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: sFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    let blockModal = document.getElementById('modalBlock');
    if (list['status'] == 'error') {
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = '登録失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '法人情報の登録に失敗しました。<br>お手数ですが最初からやり直してください。';
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
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'master02_01.php\');">一覧に戻る</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //事業所作成ボタン生成
      let addButton =
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'master03_01_01.php?method=new=' +
        list['facId'] +
        '\');" style="width:26rem;">続けて事業所を登録する</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('master02_01.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 法人削除チェック
 *
 */
function checkDeleteCorporation(corpId, corpName) {
  //確認メッセージ
  let blockModal = document.getElementById('modalBlock');
  //削除確認メッセージ
  blockModal.querySelector('.box-title p').innerHTML = '法人情報削除';
  blockModal.querySelector('.box-details p').innerHTML =
    `${corpName}の<br>登録情報を削除します。よろしいですか？`;
  //ボタン再生成
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  //ボタン生成
  let newButton =
    `<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>` +
    `<button type="button" class="btn-confirm" onclick="deleteCorporation('${corpId}','${corpName}');">削除する</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 法人削除実行
 *
 */
async function deleteCorporation(corpId, corpName) {
  //formData生成
  let dFd = new FormData();
  dFd.append('method', 'delete');
  dFd.append('action', 'sendInput');
  dFd.append('corpId', corpId);
  dFd.append('company_name', corpName);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: dFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    //確認モーダルクリア
    let blockModal = document.getElementById('modalBlock');
    blockModal.classList.remove('is-active');
    blockModal.classList.remove('bg-orange');
    if (list['status'] == 'error') {
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = '削除失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '法人情報の削除に失敗しました。<br>お手数ですが最初からやり直してください。';
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
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'master02_01.php\');">一覧に戻る</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('master02_01.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 郵便番号から住所検索
 *
 */
const api = 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=';
let zipInput = document.getElementById('zipCode');
async function zipCloud() {
  //入力された郵便番号から「-」を削除
  const zipCode = this.value.replace('-', '');
  let url = api + zipCode;
  if (zipCode.length === 7) {
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error('Network response was not ok');
      const data = await response.json();
      if (data.status === 400 || data.results === null) {
        alert('住所が取得できませんでした。');
        document.querySelector('input[name="address2"]').value = '';
        document.querySelector('input[name="address3"]').value = '';
      } else {
        const address01 = data.results[0].address1;
        const address02 = data.results[0].address2 + data.results[0].address3;
        //住所入力完了で「次へ進む」ボタン有効
        document.querySelector('input[name="address2"]').value = address01;
        document.querySelector('input[name="address3"]').value = address02;
      }
    } catch (error) {
      console.error('住所取得エラー:', error);
      alert('住所が取得できませんでした。');
      document.querySelector('input[name="address2"]').value = '';
      document.querySelector('input[name="address3"]').value = '';
    }
  } else {
    //住所再入力中は「次へ進む」ボタン無効
    document.querySelector('input[name="address2"]').value = '';
    document.querySelector('input[name="address3"]').value = '';
    alert('住所が取得できませんでした。');
  }
}
//郵便番号入力時に住所検索
if (zipInput != null) {
  zipInput.addEventListener('blur', zipCloud);
}
