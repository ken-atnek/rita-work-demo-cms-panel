/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client03_01.php';
/**
 * 新規求人カード登録チェック
 *
 */
function checkNewJobCard() {
  let blockModal = document.getElementById('modalBlock');
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人カード状態変更チェック
 *
 */
function checkJobCardStatus(facId, joCardCode, jobCardId, status, execution) {
  let blockModal = document.getElementById('modalBlock');
  //ボタンタグを全て取得
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  blockModal.querySelector('.box-title p').innerHTML = '求人カード状況';
  switch (status) {
    //「下書き」へ
    case '1':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          '求人ID：' + joCardCode + 'を「下書き中」に変更します。よろしいですか？';
      }
      break;
    //「公開」へ
    case '2':
      {
        if (execution == 'restore') {
          blockModal.querySelector('.box-title p').innerHTML = '求人カード再掲載';
          blockModal.querySelector('.box-details p').innerHTML =
            '求人ID：' + joCardCode + 'を再掲載します。よろしいですか？';
        } else {
          blockModal.querySelector('.box-details p').innerHTML =
            '求人ID：' + joCardCode + 'を「公開中」に変更します。よろしいですか？';
        }
      }
      break;
    //「解約」
    case '99':
      {
        blockModal.querySelector('.box-details p').innerHTML =
          '求人ID：' +
          joCardCode +
          'を<span style="font-weight:bold;color:#0000CD;">解約</span>します。よろしいですか？';
      }
      break;
    //デフォルト：削除
    default:
      {
        blockModal.querySelector('.box-title p').innerHTML = '求人カード削除';
        blockModal.querySelector('.box-details p').innerHTML =
          '求人ID：' +
          joCardCode +
          'を<span style="font-weight:bold;color:#DD0000;">削除</span>します。よろしいですか？';
      }
      break;
  }
  //キャンセルボタン生成
  let cancelButton =
    '<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>';
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
  //登録ボタン生成
  let addButton = `<button type="button" class="btn-confirm" onclick="changeJobCardStatus(${facId},'${joCardCode}',${jobCardId},${status},'${execution}');">はい</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人カード状態変更
 *
 */
async function changeJobCardStatus(facId, joCardCode, jobCardId, status, execution) {
  let cFd = new FormData();
  cFd.append('action', 'changeStatus');
  cFd.append('facId', facId);
  cFd.append('jobId', jobCardId);
  cFd.append('jobCardCode', joCardCode);
  cFd.append('changeStatus', status);
  cFd.append('execution', execution);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    let blockModal = document.getElementById('modalBlock');
    //ボタンタグを全て取得
    let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
    //ボタンタグを削除
    buttonList.forEach((ElementButton) => {
      ElementButton.remove();
    });
    if (list['status'] == 'error') {
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = 'カード状況変更失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        'カード状況の変更に失敗しました。<br>お手数ですが最初からやり直してください。';
      //ボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
    } else {
      blockModal.classList.add('bg-black');
      blockModal.querySelector('.box-title p').innerHTML = list['title'];
      blockModal.querySelector('.box-details p').innerHTML = list['msg'];
      //一覧へ戻るボタン生成
      let newButton = `<button type="button" class="btn-cancel" onclick="closeModalToPage('client03_01.php?facId=${list['facId']}');">一覧に戻る</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('client03_01.php')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
