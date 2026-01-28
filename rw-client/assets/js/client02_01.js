/**
 * ページ読み込み時に初期表示を制御
 *
 */
document.addEventListener('DOMContentLoaded', function () {
  //事業形態ラジオボタン取得
  const typeRadios = document.querySelectorAll('input[name="facility_type"]');
  //切り替え対象の項目（設立年月日～備考）だけ取得
  const toggleFieldNames = [
    //'established_date',
    'facility_scale',
    'is_emergency_designated',
    'business_hours',
    'holidays',
    'capacity_patients',
    'average_patients',
    'staff_composition',
    'visit_area',
    'is_dormitory',
    'is_childcare_support',
    //'remarks',
  ];
  const fieldDls = Array.from(document.querySelectorAll('form.block-form dl[data-field]')).filter(
    (dl) => toggleFieldNames.includes(dl.getAttribute('data-field'))
  );
  //事業形態ごとの表示項目定義
  //value: hospital, clinic, dental_clinic, nursing_care, home_nursing_station
  const fieldMap = {
    hospital: [
      //病院
      'facility_scale',
      'is_emergency_designated',
      'business_hours',
      'holidays',
      'average_patients',
      'staff_composition',
      'is_dormitory',
      'is_childcare_support',
      'remarks',
    ],
    clinic: [
      //診療所
      'facility_scale',
      'is_emergency_designated',
      'business_hours',
      'holidays',
      'average_patients',
      'staff_composition',
      'is_dormitory',
      'is_childcare_support',
      'remarks',
    ],
    dental_clinic: [
      //歯科診療所
      'business_hours',
      'holidays',
      'average_patients',
      'staff_composition',
      'remarks',
    ],
    nursing_care: [
      //介護・福祉事業所
      'facility_scale',
      'business_hours',
      'holidays',
      'capacity_patients',
      'staff_composition',
      'remarks',
    ],
    home_nursing_station: [
      //訪問介護ステーション
      'business_hours',
      'holidays',
      'staff_composition',
      'visit_area',
      'remarks',
    ],
  };
  function getFacilityTypeValue() {
    const checked = document.querySelector('input[name="facility_type"]:checked');
    if (checked) return checked.value;
    //selectbox等でhiddenに値が入る実装にも対応
    const hidden = document.querySelector(
      'input[type="hidden"][name="facility_type"][data-selectbox-hidden]'
    );
    return hidden ? hidden.value : '';
  }
  //事業形態選択による入力項目の表示切替
  function updateFields() {
    //選択中の事業形態取得（未選択は ''）
    const value = getFacilityTypeValue();
    if (value === '') return;
    //fieldDlsを毎回再取得
    const fieldDls = Array.from(document.querySelectorAll('form.block-form dl[data-field]')).filter(
      (dl) => toggleFieldNames.includes(dl.getAttribute('data-field'))
    );
    //表示する項目リスト
    const showFields = fieldMap[value] || [];
    //まず全て表示（他の項目は常に表示）
    fieldDls.forEach((dl) => {
      dl.style.display = 'none';
    });
    //表示対象のみ表示
    showFields.forEach((field) => {
      const el = document.querySelector(`form.block-form dl[data-field="${field}"]`);
      if (el) el.style.display = '';
    });
  }
  //グローバルスコープで参照できるようにする
  window.updateFields = updateFields;
  //初期化
  typeRadios.forEach((radio) => {
    radio.addEventListener('change', updateFields);
  });
  //ページロード時に初期表示
  updateFields();
});
/**
 * 特別バナープランチェックボックス切替でバナー登録エリアを表示する
 *
 */
function toggleSpecialBannerPlan() {
  //特別バナープランのチェックボックス取得
  const checkbox = document.querySelector('input[name="special_banner"]');
  //ロゴ画像・紹介動画入力エリアのdlタグ群取得
  const bannerInputs = document.querySelectorAll('dl.js-special-banner-inputs');
  //ロゴ画像用inputタグ取得
  let specialBannerLogoTmp = document.querySelector('input[name="images_tmp"]');
  //紹介動画用inputタグ取得
  let specialBannerVideoUrl = document.querySelector('input[name="special_banner_video_url"]');
  if (!checkbox || !bannerInputs.length) return;
  bannerInputs.forEach(function (dl) {
    if (checkbox.checked) {
      dl.style.display = 'grid';
      //必須入力項目に追加
      let previewEl = document.getElementById('js-previewBlock-mainLogo');
      let previewList = previewEl ? previewEl.children : [];
      //プレビュー画像が０なら画像登録必須に追加
      if (previewList.length <= 0) {
        //ロゴ画像
        if (specialBannerLogoTmp) {
          specialBannerLogoTmp.classList.add('required-item');
          specialBannerLogoTmp.setAttribute('required', 'required');
        }
        //紹介動画登録
        if (specialBannerVideoUrl) {
          specialBannerVideoUrl.classList.add('required-item');
          specialBannerVideoUrl.setAttribute('required', 'required');
        }
        //必須入力項目を再読み込み
        validationForm = document.querySelector('form[name=inputForm]');
        requiredItem = document.querySelectorAll('.required-item');
      }
    } else {
      dl.style.display = 'none';
      //必須入力項目から削除
      //ロゴ画像
      if (specialBannerLogoTmp) {
        specialBannerLogoTmp.classList.remove('required-item');
        specialBannerLogoTmp.removeAttribute('required');
      }
      //紹介動画登録
      if (specialBannerVideoUrl) {
        specialBannerVideoUrl.classList.remove('required-item');
        specialBannerVideoUrl.removeAttribute('required');
      }
      //必須入力項目を再読み込み
      validationForm = document.querySelector('form[name=inputForm]');
      requiredItem = document.querySelectorAll('.required-item');
    }
  });
}
/**
 * 画像登録エリア初期化
 *
 */
function initSpecialBannerDropZones() {
  const checkbox = document.querySelector('input[name="special_banner"]');
  if (!checkbox || !checkbox.checked) return;
  const areaIds = ['mainLogo'];
  areaIds.forEach(function (area) {
    let drop = document.getElementById('js-dragDrop-' + area);
    let btn = document.getElementById('js-fileSelect-' + area);
    let input = document.getElementById('js-fileElem-' + area);
    let inputMode = document.getElementById('js-uploadImageMode-' + area);
    let inputArea = document.getElementById('js-uploadImageArea-' + area);
    let preview = document.getElementById('js-previewBlock-' + area);
    let error = document.getElementById('js-fileError-' + area);
    if (inputMode && inputMode.value === 'only' && drop && preview) {
      const liCount = preview.querySelectorAll('li').length;
      //OFF→ON 復帰時に、隠れているプレビューを表示
      if (liCount >= 1) {
        preview.style.display = 'grid';
      } else {
        preview.style.display = 'none';
      }
      if (liCount >= 1) {
        drop.classList.add('is-active');
      } else {
        drop.classList.remove('is-active');
      }
    }
    if (drop && btn && input && preview) {
      initDropZone({
        dropZone: drop,
        selectFileButton: btn,
        fileInput: input,
        inputMode: inputMode,
        inputArea: inputArea,
        previewBlock: preview,
        fileError: error,
      });
    }
  });
}
/**
 * 特別バナープラン関連イベントをバインド（DOM差し替え後も呼ぶ）
 */
function bindSpecialBannerPlanHandlers() {
  const checkbox = document.querySelector('input[name="special_banner"]');
  if (!checkbox) return;
  //DOM差し替え後の再バインド時に二重登録しない
  if (checkbox.dataset.specialBannerBound === '1') return;
  checkbox.dataset.specialBannerBound = '1';
  checkbox.addEventListener('change', function () {
    toggleSpecialBannerPlan();
    initSpecialBannerDropZones();
  });
}
//グローバルスコープで参照できるようにする
window.toggleSpecialBannerPlan = toggleSpecialBannerPlan;
//ページロード時に初期表示を制御
document.addEventListener('DOMContentLoaded', function () {
  toggleSpecialBannerPlan();
  initSpecialBannerDropZones();
  bindSpecialBannerPlanHandlers();
});
/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_client02_01.php';
/**
 * メールアドレスアカウントチェック
 *
 */
async function checkUniqueEmail(email) {
  let cFd = new FormData();
  cFd.append('set_email', email);
  //編集時の自分自身は重複扱いしないため、識別情報も送る
  const form = document.querySelector('form[name=inputForm]');
  const method = form?.querySelector('input[name="method"]')?.value;
  const facId = form?.querySelector('input[name="facId"]')?.value;
  if (method) cFd.append('method', method);
  if (facId) cFd.append('facId', facId);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    if (list['status'] == 'error') {
      //エラーモーダル
      let blockModal = document.getElementById('modalBlock');
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
        '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      blockModal.classList.add('bg-orange');
      blockModal.classList.add('is-active');
      htmlElement.style.overflow = 'hidden';
    }
  } catch (error) {
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 送信前チェック
 *
 */
async function checkInput() {
  //法人が選択されているかチェック
  const selectCorporation = document.querySelector(
    'input[type="hidden"][name="facility_corporation_code"]'
  ).value;
  //.validationForm を指定した form 要素が存在すれば
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
      if (list['status'] == 'error') {
        //エラーモーダル
        let blockModal = document.getElementById('modalBlock');
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
          '<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>';
        blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
        blockModal.classList.add('bg-orange');
        blockModal.classList.add('is-active');
        htmlElement.style.overflow = 'hidden';
      } else {
        //表示中の情報入替
        document.querySelector('.container-vendor-register').remove();
        //ページ表示
        document.querySelector('.page-nav').insertAdjacentHTML('afterend', list['tag']);
        //mainタグクラス名切替
        const mainTag = document.querySelector('main');
        mainTag.classList.remove('inner-03-01-01');
        mainTag.classList.add('inner-03-01-02');
        sendBtn.disabled = true;
        //ページの上端までスクロール
        const areaClient = document.querySelector('.area-client');
        if (areaClient) areaClient.scrollIntoView(true);
      }
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
  //.validationForm を指定した form 要素が存在すれば
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
    document.querySelector('.container-vendor-check').remove();
    //ページ表示
    document.querySelector('.page-nav').insertAdjacentHTML('afterend', list['tag']);
    //mainタグクラス名切替
    const mainTag = document.querySelector('main');
    mainTag.classList.remove('inner-03-01-02');
    mainTag.classList.add('inner-03-01-01');
    sendBtn.disabled = true;
    //事業形態選択による入力項目の表示切替
    updateFields();
    //事業形態切替イベント再バインド
    const typeRadios = document.querySelectorAll('input[name="facility_type"]');
    typeRadios.forEach((radio) => {
      radio.addEventListener('change', updateFields);
    });
    //特別バナープランのステータス確認
    toggleSpecialBannerPlan();
    initSpecialBannerDropZones();
    bindSpecialBannerPlanHandlers();
    //セレクトボックス：初期は閉
    initClosed('innerSearch', 'btnSwitchSearch');
    initClosed('innerFilter', 'btnSwitchFilter');
    //セレクトボックス：トグル動作
    bindToggle('btnSwitchSearch', 'innerSearch');
    bindToggle('btnSwitchFilter', 'innerFilter');
    //セレクトボックス：初期化
    initSelectBox();
    //ページの上端までスクロール
    const areaClient = document.querySelector('.area-client');
    if (areaClient) areaClient.scrollIntoView(true);
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
  //.validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  //送信中にページ離脱した場合、ドラフト破棄が先に走るとセッション競合し得るため抑止
  window.__rwUploadDraftDiscardDisable = true;
  //送信処理開始
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
      window.__rwUploadDraftDiscardDisable = false;
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = '登録失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '事業所情報の登録に失敗しました。<br>お手数ですが最初からやり直してください。';
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
      //閉じるボタン生成
      let newButton =
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'client02_01.php\');">事業所情報</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //求人カード作成ボタン生成
      let addButton =
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'client03_01.php?facId=' +
        list['facId'] +
        '\');" style="width:26rem;">求人カード一覧へ</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('client02_01.php')");
    }
    blockModal.classList.add('is-active');
    htmlElement.style.overflow = 'hidden';
    //リクエスト完了後は解除
    window.__rwUploadDraftDiscardDisable = false;
  } catch (error) {
    window.__rwUploadDraftDiscardDisable = false;
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 事業所削除チェック
 *
 */
function checkDeleteFacility(facId, facName, facCode) {
  //確認メッセージ
  let blockModal = document.getElementById('modalBlock');
  //削除確認メッセージ
  blockModal.querySelector('.box-title p').innerHTML = '事業所情報削除';
  blockModal.querySelector('.box-details p').innerHTML =
    `${facName}の<br>登録情報を削除します。よろしいですか？`;
  //ボタン再生成
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  //ボタン生成
  let newButton =
    `<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>` +
    `<button type="button" class="btn-confirm" onclick="deleteFacility('${facId}','${facName}','${facCode}');">削除する</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  htmlElement.style.overflow = 'hidden';
}
/**
 * 事業所削除実行
 *
 */
async function deleteFacility(facId, facName, facCode) {
  //formData生成
  let dFd = new FormData();
  dFd.append('method', 'delete');
  dFd.append('action', 'sendInput');
  dFd.append('facId', facId);
  dFd.append('facCode', facCode);
  dFd.append('facility_name', facName);
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
        '事業所情報の削除に失敗しました。<br>お手数ですが最初からやり直してください。';
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
        '<button type="button" class="btn-cancel" onclick="closeModalToPage(\'client02_01.php\');">一覧に戻る</button>';
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('client02_01.php')");
    }
    blockModal.classList.add('is-active');
    htmlElement.style.overflow = 'hidden';
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
