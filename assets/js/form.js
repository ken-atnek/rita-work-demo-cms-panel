/**
 * 入力フォーム処理
 *
 */
//エラーを表示する span 要素に付与するクラス名（エラー用のクラス）
const errorClassName = 'error';
//定数・クエリセレクタの重複取得を減らす
let validationForm = document.querySelector('form[name=inputForm]');
let requiredItem = document.querySelectorAll('.required-item');
const sendBtn = document.querySelector('.item-check');
//ロード時はボタン「確認画面へ」無効化
if (sendBtn && validationForm) {
  let inputMethod = validationForm.querySelector('input[name="method"]').value;
  if (inputMethod == 'new') {
    sendBtn.disabled = false;
  }
}
/**
 * 必須入力項目の変数初期化
 *
 */
let form_inputValue = '';
/**
 * 必須入力項目の入力チェック
 *
 */
if (validationForm != null) {
  validationForm.addEventListener('input', checkValue);
  validationForm.addEventListener('change', checkValue);
  function checkValue() {
    // この画面に制御対象ボタンが無い場合は何もしない
    if (!sendBtn) return;
    //未入力時はボタン無効化
    const isRequired = validationForm.checkValidity();
    if (isRequired) {
      sendBtn.disabled = false;
    } else {
      sendBtn.disabled = true;
    }
  }
}
/**
 * Enterキーによる暗黙submitの抑止（オプトイン）
 *
 * 対象formに data-prevent-enter-submit="true" が付いている場合のみ有効。
 * textarea内のEnter（改行）は許可。
 */
if (validationForm && validationForm.dataset.preventEnterSubmit === 'true') {
  validationForm.addEventListener('keydown', (e) => {
    // IME変換確定などは抑止しない
    if (e.isComposing) return;
    if (e.key !== 'Enter') return;
    const target = e.target;
    if (!(target instanceof HTMLElement)) return;
    // textareaのEnterは改行として許可
    if (target.tagName === 'TEXTAREA') return;
    // 明示的なsubmit操作（ボタン等）は阻害しない
    if (target.tagName === 'BUTTON') return;
    if (target.tagName === 'INPUT') {
      const type = (target.getAttribute('type') || 'text').toLowerCase();
      if (type === 'submit' || type === 'button' || type === 'image') return;
    }
    // それ以外のEnterは暗黙submitになり得るため抑止
    e.preventDefault();
  });
}
/**
 * 入力変更、フォーカスが外れた時の入力チェック
 *
 */
requiredItem.forEach((elem) => {
  elem.addEventListener('focus', (e) => {
    let targetElem = e.currentTarget;
    //プレースホルダー非表示
    targetElem.placeholder = '';
    //エラー出力が有れば削除
    if (targetElem.parentNode.classList.contains(errorClassName)) {
      targetElem.classList.remove(errorClassName, 'error-email', 'error-phone_number');
      targetElem.parentNode.classList.remove(errorClassName);
      //入力値を復活
      if (form_inputValue != null) {
        targetElem.value = form_inputValue;
      }
    }
  });
  elem.addEventListener('blur', (e) => {
    let targetElem = e.currentTarget;
    //エラー出力が有れば削除
    if (targetElem.parentNode.classList.contains(errorClassName)) {
      targetElem.classList.remove(errorClassName, 'error-email', 'error-phone_number');
      targetElem.parentNode.classList.remove(errorClassName);
      //targetElem.style.color = '';
    }
    //値（value プロパティ）の前後の空白文字を削除
    const elemValue = targetElem.value.trim();
    //値が空の場合はエラーを表示してフォームの送信を中止
    if (elemValue.length === 0) {
      targetElem.parentNode.classList.add(errorClassName);
      createError(targetElem, errorClassName);
    } else {
      //値の入力があれば、入力値チェック（各フィールドの blur で実施）
    }
  });
});
//メールアドレス欄のblur時にバリデーション
document.querySelectorAll('.email').forEach((elem) => {
  elem.addEventListener('blur', () => {
    checkEmailElem();
  });
});
//電話番号欄のblur時にバリデーション
document.querySelectorAll('.phone_number').forEach((elem) => {
  elem.addEventListener('blur', () => {
    checkTelElem();
  });
});
/**
 *「required」クラスを指定された要素の入力チェック
 *
 */
function checkRequiredElem() {
  let return_required = 'ok';
  //「required」クラスを指定された要素の集まり
  //「required」を指定した要素を検証
  requiredItem.forEach((elem) => {
    //エラー出力が有れば削除
    if (elem.classList.contains(errorClassName)) {
      elem.classList.remove(errorClassName);
      elem.parentNode.classList.remove(errorClassName);
    }
    //値（value プロパティ）の前後の空白文字を削除
    const elemValue = elem.value.trim();
    //値が空の場合はエラーを表示してフォームの送信を中止
    if (elemValue.length === 0) {
      createError(elem, 'required-Item');
      return_required = 'err';
    }
  });
  return return_required;
}
//HTML側（inline handler等）から利用できるようにグローバルへ公開
window.checkRequiredElem = checkRequiredElem;
/**
 *「email」クラスを指定された要素の入力チェック
 *
 */
function checkEmailElem() {
  let return_email = 'ok';
  //email クラスを指定された要素の集まりを都度取得
  const emailItem = document.querySelectorAll('.email');
  emailItem.forEach((elem) => {
    //エラー出力が有れば削除
    if (elem.classList.contains(errorClassName)) {
      elem.classList.remove(errorClassName, 'error-email');
      elem.parentNode.classList.remove(errorClassName);
    }
    //「Email」の検証に使用する正規表現パターン
    const pattern = /^([a-z0-9+_-]+)(\.[a-z0-9+_-]+)*@([a-z0-9-]+\.)+[a-z]{2,6}$/iu;
    //値が空でなければ
    if (elem.value !== '') {
      //test() メソッドで値を判定し、マッチしなければエラーを表示してフォームの送信を中止
      if (!pattern.test(elem.value)) {
        createError(elem, 'required-Email');
        return_email = 'err';
      }
    } else {
      createError(elem, 'required-Email');
      return_email = 'err';
    }
  });
  return return_email;
}
/**
 *「phone_number」クラスを指定された要素の入力チェック
 *
 */
function checkTelElem() {
  let return_tel = 'ok';
  //phone_number クラスを指定された要素の集まりを都度取得
  const telItem = document.querySelectorAll('.phone_number');
  telItem.forEach((elem) => {
    //エラー出力が有れば削除
    if (elem.classList.contains(errorClassName)) {
      elem.classList.remove(errorClassName, 'error-phone_number');
      elem.parentNode.classList.remove(errorClassName);
    }
    //電話番号の検証に使用する正規表現パターン
    const pattern = /^\(?\d{2,5}\)?[-().\s]{0,2}\d{1,4}[-).\s]{0,2}\d{3,4}$/;
    //値が空でなければ
    if (elem.value !== '') {
      //test() メソッドで値を判定し、マッチしなければエラーを表示してフォームの送信を中止
      if (!pattern.test(elem.value)) {
        createError(elem, 'required-phone_number');
        return_tel = 'err';
      }
    } else {
      createError(elem, 'required-phone_number');
      return_tel = 'err';
    }
  });
  return return_tel;
}
/**
 * エラーメッセージを表示する span 要素を生成して親要素に追加する関数
 *
 * elem ：対象の要素
 * errorMessage ：表示するエラーメッセージクラス
 */
function createError(elem, errorMessage) {
  elem.placeholder = '';
  //エラーメッセージ振り分け
  switch (errorMessage) {
    case 'required-Email':
      {
        elem.placeholder = '';
        elem.classList.add(errorClassName, 'error-email');
        elem.parentNode.classList.add(errorClassName);
        form_inputValue = elem.value;
        elem.value = '';
        elem.placeholder = 'Eメールアドレス形式で入力して下さい。';
      }
      break;
    case 'required-Email_Check':
      {
        elem.placeholder = '';
        elem.classList.add(errorClassName, 'error-email_check');
        elem.parentNode.classList.add(errorClassName);
        form_inputValue = elem.value;
        elem.value = '';
        elem.placeholder = '入力されたメールアドレスが違います。';
      }
      break;
    case 'required-phone_number':
      {
        elem.placeholder = '';
        elem.classList.add(errorClassName, 'error-phone_number');
        elem.parentNode.classList.add(errorClassName);
        form_inputValue = elem.value;
        elem.value = '';
        elem.placeholder = '半角数字で入力して下さい。';
      }
      break;
    default:
      {
        elem.placeholder = '';
        elem.classList.add(errorClassName);
        elem.parentNode.classList.add(errorClassName);
        form_inputValue = elem.value;
        elem.value = '';
        elem.placeholder = '入力は必須です';
      }
      break;
  }
  //ボタンを無効化する
  if (sendBtn) {
    sendBtn.disabled = true;
  }
}
