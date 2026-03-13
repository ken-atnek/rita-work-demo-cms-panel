/**
 * 共通：パネルの開閉（is-active）をトグル
 *
 */
function togglePanel(panelId, btnSelector) {
  const panel = document.getElementById(panelId);
  if (!panel) return false;
  //パネルの表示状態をトグル
  const isOpen = panel.classList.toggle('is-active');
  //アクセシビリティ対応：表示/非表示をaria属性で反映
  panel.setAttribute('aria-hidden', String(!isOpen));
  const btn = btnSelector ? document.querySelector(btnSelector) : null;
  if (btn) {
    //ボタンの状態もトグル
    btn.setAttribute('aria-expanded', String(isOpen));
    btn.classList.toggle('is-active', isOpen);
  }
  return isOpen;
}
/**
 * 初期化：閉じた状態にする
 *
 */
function initClosed(panelId, btnId) {
  const panel = document.getElementById(panelId);
  if (panel) {
    //初期状態：パネルを閉じる
    panel.classList.remove('is-active');
    panel.setAttribute('aria-hidden', 'true');
  }
  const btn = document.getElementById(btnId);
  if (btn) {
    //初期状態：ボタンも非アクティブ
    btn.classList.remove('is-active');
    btn.setAttribute('aria-expanded', 'false');
  }
}
/**
 * クリック紐付け
 *
 */
function bindToggle(btnId, panelId) {
  const btn = document.getElementById(btnId);
  if (!btn) return;
  //クリックでパネルの開閉をトグル
  btn.addEventListener('click', () => {
    togglePanel(panelId, `#${btnId}`);
  });
}
/**
 * API送信先 共通定数
 *
 */
const requestURL = './assets/function/proc_master03_02_01.php';
/**
 * 給与形態選択による入力項目の表示切替
 *
 */
function toggleSalaryType(type) {
  //月給・時給のラジオボタン取得
  const monthlyRadio = document.getElementById('radio-salary01');
  const hourlyRadio = document.getElementById('radio-salary02');
  //月給・時給の表示エリア取得
  const wrapPrice = document.querySelector('.wrap-price');
  const selectSalary02 = document.querySelector('.select-salary02');
  //賞与エリア取得
  const itemBonus = document.querySelector('.item-bonus');
  //初年度年収エリア取得
  const wrapYearlySalary = document.querySelector('.wrap-yearly-salary');
  if (!wrapPrice || !selectSalary02 || !wrapYearlySalary) return;
  if (type === 'monthly') {
    //月給選択時の表示切替
    wrapPrice.classList.add('is-block-active');
    selectSalary02.classList.remove('is-block-active');
    itemBonus.style.display = 'flex';
    wrapYearlySalary.classList.remove('is-inactive');
    if (monthlyRadio) monthlyRadio.checked = true;
    if (hourlyRadio) hourlyRadio.checked = false;
  } else if (type === 'hourly') {
    //時給選択時の表示切替
    wrapPrice.classList.remove('is-block-active');
    selectSalary02.classList.add('is-block-active');
    itemBonus.style.display = 'none';
    wrapYearlySalary.classList.add('is-inactive');
    if (monthlyRadio) monthlyRadio.checked = false;
    if (hourlyRadio) hourlyRadio.checked = true;
  }
}
/**
 * 賞与有り／無し選択による入力項目の表示切替
 *
 */
function toggleBonusType() {
  //賞与のチェックボックスとテキストエリア取得
  const bonusCheckbox = document.getElementById('bonusCheckbox');
  const bonusText = document.getElementById('bonusAmount');
  if (!bonusCheckbox || !bonusText) return;
  //チェック有無で賞与入力欄の表示切替
  if (bonusCheckbox.checked) {
    bonusText.style.display = '';
  } else {
    bonusText.style.display = 'none';
  }
}
//チェックボックス変更時に実行できるようにグローバル化
window.toggleBonusType = toggleBonusType;
/**
 * ページ読み込み時に初期表示を制御
 *
 */
document.addEventListener('DOMContentLoaded', function () {
  //月給・時給のラジオボタン取得
  const monthlyRadio = document.getElementById('radio-salary01');
  const hourlyRadio = document.getElementById('radio-salary02');
  //給与形態の初期表示制御
  if (monthlyRadio && monthlyRadio.checked) {
    toggleSalaryType('monthly');
  } else if (hourlyRadio && hourlyRadio.checked) {
    toggleSalaryType('hourly');
  }
  //賞与有無の初期表示も制御
  toggleBonusType();
  //求人カード登録ページは登録ボタン解放
  //求人カード登録ボタンを有効化
  document.querySelector('.item-check').disabled = false;
  //契約プランラジオボタン取得
  const typeRadios = document.querySelectorAll('input[name="contract_plan"]');
  //切り替え対象の項目（設立年月日～備考）だけ取得
  const toggleFieldNames = ['block_interview', 'block_movie', 'block_benefits'];
  //契約プランごとの表示項目定義
  //value: premium, standard, light
  const fieldMap = {
    premium: [
      //プレミアムプラン
      'block_interview',
      'block_movie',
      'block_benefits',
    ],
    standard: [
      //スタンダードプラン
      'block_interview',
    ],
    light: [
      //ライトプラン
    ],
  };
  //契約プランの値を取得（ラジオ or hidden）
  function getContractPlanValue() {
    const checked = document.querySelector('input[name="contract_plan"]:checked');
    if (checked) return checked.value;
    const hidden = document.querySelector(
      'input[type="hidden"][name="contract_plan"][data-selectbox-hidden]'
    );
    return hidden ? hidden.value : '';
  }
  //契約プラン選択による入力項目の表示切替
  function updateFields() {
    //選択中の契約プラン取得（未選択は ''）
    const value = getContractPlanValue();
    //対象articleは「定義順」で取得（DOM順と一致する前提）
    const orderedArticles = toggleFieldNames
      .map((field) => document.querySelector(`form.block-form article[data-field="${field}"]`))
      .filter(Boolean);
    //表示する項目リスト
    const showFields = fieldMap[value] || [];
    //表示/非表示フラグを事前に確定
    const visibleFlags = orderedArticles.map((article) =>
      showFields.includes(article.getAttribute('data-field'))
    );
    //article自体の表示/非表示を切替
    orderedArticles.forEach((article, idx) => {
      const isVisible = visibleFlags[idx];
      article.style.display = isVisible ? '' : 'none';
      article.setAttribute('aria-hidden', String(!isVisible));
    });
    //hrタグの表示制御（区切り線の表示/非表示）
    // - 最初の前hrは「最初のarticleが表示」のときのみ表示
    // - 各article間のhrは「両隣のarticleが表示」のときのみ表示
    // - 最後の後hrは「最後のarticleが表示」のときのみ表示
    if (orderedArticles.length > 0) {
      const first = orderedArticles[0];
      const hrBeforeFirst = first.previousElementSibling;
      if (hrBeforeFirst && hrBeforeFirst.tagName === 'HR') {
        //最初の前hrは常に表示
        hrBeforeFirst.style.display = '';
      }
      for (let i = 0; i < orderedArticles.length - 1; i++) {
        const hrBetween = orderedArticles[i].nextElementSibling;
        if (hrBetween && hrBetween.tagName === 'HR') {
          hrBetween.style.display = visibleFlags[i] && visibleFlags[i + 1] ? '' : 'none';
        }
      }
      const lastIdx = orderedArticles.length - 1;
      const last = orderedArticles[lastIdx];
      const hrAfterLast = last.nextElementSibling;
      if (hrAfterLast && hrAfterLast.tagName === 'HR') {
        if (value == 'light') {
          hrAfterLast.style.display = visibleFlags[lastIdx] ? '' : 'none';
        } else {
          hrAfterLast.style.display = '';
        }
      }
    }
    //契約プラン切替に合わせて目次も同期（例：ライトプランは「機能拡張プラン」を非表示）
    if (typeof window.refreshJobCardToc === 'function') {
      window.refreshJobCardToc();
    }
  }
  //グローバルスコープで参照できるようにする
  window.updateFields = updateFields;
  //契約プランラジオのchangeイベントで表示切替
  typeRadios.forEach((radio) => {
    radio.addEventListener('change', updateFields);
  });
  //ページロード時に初期表示
  updateFields();
  /**
   * 募集職種ごとの「詳細情報」表示制御
   * - 募集職種切替時にAJAXで許可リスト取得
   * - 許可されていないチェック項目を非表示＆選択解除
   */
  const groupToCheckboxName = {
    job_content: 'job_content',
    clinical_department: 'clinical_department',
    service_type: 'service_type',
    benefits: 'benefits',
    work_style: 'work_style',
    holidays: 'holidays',
    requirements: 'requirements',
    supports: 'supports',
    accesses: 'accesses',
  };
  //選択中の募集職種IDを取得
  function getSelectedJobCategoryId() {
    const checked = document.querySelector('input[type="radio"][name="job_category"]:checked');
    return checked ? String(checked.value || '') : '';
  }
  //募集職種ごとに許可された詳細項目IDリストを取得（AJAX）
  async function fetchAllowedOptionIdsByCategory(jobCategoryId) {
    if (!jobCategoryId) return null;
    try {
      const fd = new FormData();
      fd.append('action', 'getJobDetailOptionsByCategory');
      const facIdEl = document.querySelector('input[type="hidden"][name="facId"]');
      const facId = facIdEl ? String(facIdEl.value || '') : '';
      if (facId) fd.append('facId', facId);
      fd.append('job_category_id', jobCategoryId);
      const res = await fetch(requestURL, { method: 'POST', body: fd, cache: 'no-store' });
      if (!res.ok) return null;
      const json = await res.json();
      return json;
    } catch (e) {
      return null;
    }
  }
  //チェックボックスグループの表示/非表示を制御
  function applyGroupFilter(groupCode, allowedIds, hasConfig) {
    const checkboxName = groupToCheckboxName[groupCode];
    if (!checkboxName) return;
    const checkboxes = Array.from(
      document.querySelectorAll(`input[type="checkbox"][name="${checkboxName}[]"]`)
    );
    if (checkboxes.length === 0) return;
    const dl = checkboxes[0].closest('dl');
    const allowedSet = new Set((Array.isArray(allowedIds) ? allowedIds : []).map((v) => String(v)));
    let visibleCount = 0;
    for (const cb of checkboxes) {
      const li = cb.closest('li');
      //設定が無い場合は「全表示」、設定があり空なら「全非表示」
      const shouldShow = !hasConfig ? true : allowedSet.has(String(cb.value));
      if (!shouldShow) cb.checked = false;
      if (li) li.style.display = shouldShow ? '' : 'none';
      if (shouldShow) visibleCount++;
    }
    //1件も表示できない場合はグループごと非表示
    if (dl) {
      dl.style.display = visibleCount > 0 ? '' : 'none';
      dl.setAttribute('aria-hidden', String(!(visibleCount > 0)));
    }
  }
  //募集職種変更時に詳細項目の表示/非表示を反映
  async function updateJobDetailByCategory() {
    const selectedId = getSelectedJobCategoryId();
    if (!selectedId) return;
    const resJson = await fetchAllowedOptionIdsByCategory(selectedId);
    if (!resJson || resJson.status !== 'success') return;
    const configuredGroups = Array.isArray(resJson.configuredGroups)
      ? resJson.configuredGroups
      : [];
    const allowedOptionIds = resJson.allowedOptionIds || {};
    Object.keys(groupToCheckboxName).forEach((groupCode) => {
      const hasConfig = configuredGroups.includes(groupCode);
      const ids = hasConfig ? allowedOptionIds[groupCode] : undefined;
      applyGroupFilter(groupCode, ids, hasConfig);
    });
  }
  //募集職種ラジオのchangeイベントで詳細項目を反映
  document
    .querySelectorAll('input[type="radio"][name="job_category"]')
    .forEach((radio) => radio.addEventListener('change', updateJobDetailByCategory));
  //ページロード時に初期表示
  updateJobDetailByCategory();
});
//グローバルスコープで参照できるようにする
window.toggleSalaryType = toggleSalaryType;
/**
 * 送信前チェック
 *
 */
async function checkInput(method) {
  let blockModal = document.getElementById('modalBlock');
  //ボタンタグを全て取得
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  //必須入力項目確認（各種入力チェック）
  let errorMessage = '';
  let errorInput = '';
  //== 掲載日入力チェック
  const publishedStart = document.querySelector('input[type="date"][name="published_start"]').value;
  if (publishedStart === '' || publishedStart === null) {
    errorMessage = '掲載日を入力してください。';
    errorInput = 'input[type="date"][name="published_start"]';
  }
  //== LステップURL入力チェック
  const LStepURL = document.querySelector('input[type="text"][name="lstep_url"]').value;
  if (LStepURL === '' || LStepURL === null) {
    errorMessage = 'Lステップの友達追加URLを入力してください。';
    errorInput = 'input[type="text"][name="lstep_url"]';
  }
  //== 募集職種入力チェック
  const jobCategory = document.querySelector('input[type="hidden"][name="job_category"]').value;
  if (jobCategory === '' || jobCategory === null) {
    errorMessage = '募集職種を選択してください。';
    errorInput = 'input[type="hidden"][name="job_category"]';
  }
  //== PR画像登録チェック
  const heroImage = document.getElementById('js-fileElem-heroImage').value;
  if (heroImage === '' || heroImage === null) {
    //リスト画像が無いかチェック
    const heroImageList = document.querySelectorAll('#js-previewBlock-heroImage li');
    if (heroImageList.length == 0) {
      errorMessage = 'PR画像を登録してください。';
      errorInput = 'input[type="hidden"][name="job_category"]';
    }
  }
  //== カードタイトル入力チェック
  const cardTitle = document.querySelector('input[type="text"][name="card_title"]').value;
  if (cardTitle === '' || cardTitle === null) {
    errorMessage = 'カードタイトルを入力してください。';
    errorInput = 'input[type="text"][name="card_title"]';
  }
  //== 雇用形態入力チェック
  const employmentType = document.querySelector(
    'input[type="hidden"][name="employment_type"]'
  ).value;
  if (employmentType === '' || employmentType === null) {
    errorMessage = '雇用形態を選択してください。';
    errorInput = 'input[type="text"][name="card_title"]';
  }
  //== 給与入力チェック
  const selectSalaryRadio = document.querySelectorAll('input[type="radio"][name="select_salary"]');
  let selectSalary = '';
  selectSalaryRadio.forEach((select) => {
    if (select.checked == true) {
      selectSalary = select.value;
    }
  });
  if (selectSalary == 'monthly') {
    const monthlyMin = document.querySelector('input[type="text"][name="monthly_min"]').value;
    const monthlyMax = document.querySelector('input[type="text"][name="monthly_max"]').value;
    if ((monthlyMin === '' || monthlyMin === null) && (monthlyMax === '' || monthlyMax === null)) {
      errorMessage = '月給を入力してください。';
      errorInput = 'input[type="text"][name="monthly_min"]';
    }
  } else if (selectSalary == 'hourly') {
    const hourlyMin = document.querySelector('input[type="text"][name="hourly_min"]').value;
    if (hourlyMin === '' || hourlyMin === null) {
      errorMessage = '時給を入力してください。';
      errorInput = 'input[type="text"][name="hourly_min"]';
    }
  }
  //== 契約プラン入力チェック
  const contractPlan = document.querySelector('input[type="hidden"][name="contract_plan"]').value;
  if (contractPlan === '' || contractPlan === null) {
    errorMessage = '契約プランを選択してください。';
    errorInput = 'input[type="hidden"][name="contract_plan"]';
  }
  //エラーがあれば処理を中断してエラー応答
  if (errorMessage != '') {
    blockModal.querySelector('.box-title p').innerHTML = '求人カード登録情報確認';
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
  //エラーが無ければ登録確認モーダル表示
  switch (method) {
    case 'new':
      {
        blockModal.querySelector('.box-title p').innerHTML = '新規求人カード情報登録';
        blockModal.querySelector('.box-details p').innerHTML =
          '新規求人カード情報を登録します。よろしいですか？';
        //キャンセルボタン生成
        let cancelButton =
          '<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>';
        blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
        //登録ボタン生成
        let addButton =
          '<button type="button" class="btn-confirm" onclick="sendInput(\'' +
          method +
          '\');">はい</button>';
        blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
      }
      break;
    case 'edit':
      {
        blockModal.querySelector('.box-title p').innerHTML = '求人カード編集';
        blockModal.querySelector('.box-details p').innerHTML =
          '求人カード情報を更新します。よろしいですか？';
        //キャンセルボタン生成
        let cancelButton =
          '<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>';
        blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', cancelButton);
        //登録ボタン生成
        let addButton =
          '<button type="button" class="btn-confirm" onclick="sendInput(\'' +
          method +
          '\');">はい</button>';
        blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
      }
      break;
  }
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 送信
 *
 */
async function sendInput(method) {
  //.validationForm を指定した form 要素が存在すれば
  if (!validationForm) return;
  //保存中にページ離脱した場合、ドラフト破棄が先に走るとセッション競合し得るため抑止
  window.__rwUploadDraftDiscardDisable = true;
  //送信処理開始
  let sendForm = document.querySelector('form[name=inputForm]');
  //submit直前に4種のradioをremove（不要な値送信防止）
  if (sendForm) {
    const targetNames = [
      'day_list_hour[]',
      'day_list_min[]',
      'night_list_hour[]',
      'night_list_min[]',
    ];
    targetNames.forEach((name) => {
      const radios = sendForm.querySelectorAll('input[type="radio"][name="' + name + '"]');
      radios.forEach((radio) => radio.remove());
    });
  }
  //送信用FormData生成
  let sFd = new FormData(sendForm);
  sFd.append('action', 'sendInput');
  sFd.append('method', method);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: sFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    let blockModal = document.getElementById('modalBlock');
    //サーバー応答がエラーの場合
    if (list['status'] == 'error') {
      window.__rwUploadDraftDiscardDisable = false;
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = '登録失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '求人カードの登録に失敗しました。<br>お手数ですが最初からやり直してください。';
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
      //サーバー応答が正常の場合
      blockModal.classList.add('bg-black');
      blockModal.querySelector('.box-title p').innerHTML = list['title'];
      blockModal.querySelector('.box-details p').innerHTML = list['msg'];
      //ボタン再生成
      let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
      //ボタンタグを削除
      buttonList.forEach((ElementButton) => {
        ElementButton.remove();
      });
      //一覧へ戻るボタン生成
      let newButton = `<button type="button" class="btn-cancel" onclick="closeModalToPage('master03_02.php?facId=${list['facId']}');">一覧に戻る</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //求人カード作成ボタン生成
      let addButton = `<button type="button" class="btn-cancel" onclick="closeModalToPage('master03_02_01.php?method=new&facId=${list['facId']}');" style="width:26rem;">続けて求人カードを登録する</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', addButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('master03_02.php?facId=${list['facId']}')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
    //リクエスト完了後は解除（必要なら離脱時破棄が動く）
    window.__rwUploadDraftDiscardDisable = false;
  } catch (error) {
    window.__rwUploadDraftDiscardDisable = false;
    //通信エラー時の処理
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 求人プラン変更チェック
 *
 */
function checkChangeJobCardPlan(el, facId, jobId, jobCode) {
  //選択プラン取得
  const selectPlan = el.value;
  //プラン変更確認メッセージ表示
  let blockModal = document.getElementById('modalBlock');
  //削除確認メッセージ表示
  blockModal.querySelector('.box-title p').innerHTML = '契約プラン';
  blockModal.querySelector('.box-details p').innerHTML = `プランを変更します。よろしいですか？`;
  //ボタン再生成（既存ボタン削除→新規生成）
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  //ボタン生成
  let newButton =
    `<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>` +
    `<button type="button" class="btn-confirm" onclick="changeJobCardPlan('${facId}','${jobId}','${jobCode}','${selectPlan}');">はい</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人プラン変更実行
 *
 */
async function changeJobCardPlan(facId, jobId, jobCode, selectPlan) {
  //プラン変更用formData生成
  let cFd = new FormData();
  cFd.append('method', 'changePlan');
  cFd.append('action', 'sendInput');
  cFd.append('facId', facId);
  cFd.append('jobId', jobId);
  cFd.append('job_code', jobCode);
  cFd.append('changePlanName', selectPlan);
  try {
    const response = await fetch(requestURL, {
      method: 'POST',
      body: cFd,
    });
    if (!response.ok) throw new Error('Network response was not ok');
    const list = await response.json();
    //確認モーダルクリア
    let blockModal = document.getElementById('modalBlock');
    blockModal.classList.remove('is-active');
    blockModal.classList.remove('bg-orange');
    //サーバー応答がエラーの場合
    if (list['status'] == 'error') {
      blockModal.classList.add('bg-orange');
      blockModal.querySelector('.box-title p').innerHTML = 'プラン変更失敗';
      blockModal.querySelector('.box-details p').innerHTML =
        '求人カードのプラン変更に失敗しました。<br>お手数ですが最初からやり直してください。';
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
      //サーバー応答が正常の場合
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
      let newButton = `<button type="button" class="btn-cancel" onclick="closeModal();">閉じる</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', 'closeModal()');
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    //通信エラー時の処理
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * 求人カード削除チェック
 *
 */
function checkDeleteJobCard(facId, jobCardId) {
  //削除確認メッセージ表示
  let blockModal = document.getElementById('modalBlock');
  //削除確認メッセージ
  blockModal.querySelector('.box-title p').innerHTML = '求人カード情報削除';
  blockModal.querySelector('.box-details p').innerHTML =
    `求人カード情報を削除します。よろしいですか？`;
  //ボタン再生成（既存ボタン削除→新規生成）
  let buttonList = blockModal.querySelector('.box-btn').querySelectorAll('button');
  //ボタンタグを削除
  buttonList.forEach((ElementButton) => {
    ElementButton.remove();
  });
  //ボタン生成
  let newButton =
    `<button type="button" class="btn-cancel" onclick="closeModal();">キャンセル</button>` +
    `<button type="button" class="btn-confirm" onclick="deleteJobCard('${facId}','${jobCardId}');">削除する</button>`;
  blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
  blockModal.classList.add('bg-orange');
  blockModal.classList.add('is-active');
  document.documentElement.style.overflow = 'hidden';
}
/**
 * 求人カード削除実行
 *
 */
async function deleteJobCard(facId, jobCardId) {
  //削除用formData生成
  let dFd = new FormData();
  dFd.append('method', 'delete');
  dFd.append('action', 'sendInput');
  dFd.append('facId', facId);
  dFd.append('jobId', jobCardId);
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
        '求人カード情報の削除に失敗しました。<br>お手数ですが最初からやり直してください。';
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
      //サーバー応答が正常の場合
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
      let newButton = `<button type="button" class="btn-cancel" onclick="closeModalToPage('master03_02.php?facId=${list['facId']}');">一覧に戻る</button>`;
      blockModal.querySelector('.box-btn').insertAdjacentHTML('beforeend', newButton);
      //「✕」ボタンも変更
      blockModal
        .querySelector('.box-title')
        .querySelector('button')
        .setAttribute('onclick', "closeModalToPage('master03_02.php?facId=${list['facId']}')");
    }
    blockModal.classList.add('is-active');
    document.documentElement.style.overflow = 'hidden';
  } catch (error) {
    //通信エラー時の処理
    console.error('送信エラー:', error);
    alert('通信エラーが発生しました。ページを再読み込みしてください。');
  }
}
/**
 * １日の流れ：スケジュール行追加
 *
 */
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('item-decrease')) {
    //残りのスケジュール行を取得（追加ボタンの有効化制御）
    const nowDayShiftLi = document.querySelectorAll('.day-shift > li');
    if (nowDayShiftLi.length >= 1) {
      nowDayShiftLi[0].querySelector('.item-increase').disabled = false;
    }
    const nowNightShiftLi = document.querySelectorAll('.night-shift > li');
    if (nowNightShiftLi.length >= 1) {
      nowNightShiftLi[0].querySelector('.item-increase').disabled = false;
    }
    //行追加開始（li要素を複製して追加）
    const li = e.target.closest('li');
    const ul = li.parentElement;
    const clone = li.cloneNode(true);
    //input値クリア（radioのvalueは消さない）
    clone.querySelectorAll('input').forEach((input) => {
      const type = (input.type || '').toLowerCase();
      if (type === 'radio') {
        input.checked = false;
        return;
      }
      //セレクトボックスのhiddenは初期化
      if (type === 'hidden' && input.hasAttribute('data-selectbox-hidden')) {
        input.value = '';
        return;
      }
      if (type === 'text') {
        input.value = '';
        return;
      }
    });
    //textarea値クリア
    clone.querySelectorAll('textarea').forEach(function (textarea) {
      textarea.value = '';
    });
    //liの直後に追加（次の要素があればその前、なければ末尾）
    if (li.nextSibling) {
      ul.insertBefore(clone, li.nextSibling);
    } else {
      ul.appendChild(clone);
    }
    //ul内の全liのid/for属性を0から順に振り直す（重複防止）
    Array.from(ul.children).forEach((liElem, idx) => {
      liElem.querySelectorAll('[id]').forEach((el) => {
        const oldId = el.id;
        el.id = oldId.replace(/\d+$/, idx);
      });
      liElem.querySelectorAll('label[for]').forEach((label) => {
        const oldFor = label.htmlFor;
        label.htmlFor = oldFor.replace(/\d+$/, idx);
      });
    });
    //追加した行のinput要素のみ一瞬枠線ハイライト
    const inputs = clone.querySelectorAll('input');
    inputs.forEach((input) => {
      const originalBorder = input.style.border;
      input.style.transition = 'border-color 0.6s';
      input.style.border = '1px solid #f29400';
      setTimeout(() => {
        input.style.border = originalBorder || '';
      }, 600);
    });
    //追加した行のtextarea要素のみ一瞬枠線ハイライト
    const new_textarea = clone.querySelectorAll('textarea');
    new_textarea.forEach((area) => {
      const originalBorder = area.style.border;
      area.style.transition = 'border-color 0.6s';
      area.style.border = '1px solid #f29400';
      setTimeout(() => {
        area.style.border = originalBorder || '';
      }, 600);
    });
    //セレクトボックス：初期は閉じる
    initClosed('innerSearch', 'btnSwitchSearch');
    initClosed('innerFilter', 'btnSwitchFilter');
    //セレクトボックス：トグル動作
    bindToggle('btnSwitchSearch', 'innerSearch');
    bindToggle('btnSwitchFilter', 'innerFilter');
    //セレクトボックス：初期化
    initSelectBox();
  }
});
/**
 * １日の流れ：スケジュール行削除
 *
 */
document.addEventListener('click', function (e) {
  if (e.target.classList.contains('item-increase')) {
    //削除ボタン押下時：該当行を削除
    const li = e.target.closest('li');
    li.remove();
    //残りのスケジュール行を取得（追加ボタンの有効化制御）
    const nowDayShiftLi = document.querySelectorAll('.day-shift > li');
    if (nowDayShiftLi.length == 1) {
      nowDayShiftLi[0].querySelector('.item-increase').disabled = true;
    }
    const nowNightShiftLi = document.querySelectorAll('.night-shift > li');
    if (nowNightShiftLi.length == 1) {
      nowNightShiftLi[0].querySelector('.item-increase').disabled = true;
    }
  }
});
/**
 * 初期化：DOMContentLoaded
 *
 */
document.addEventListener('DOMContentLoaded', () => {
  //初期は閉じる（各パネル）
  //initClosed('interviewDetails01', 'btnSwitchDetails01');
  initClosed('interviewDetails02', 'btnSwitchDetails02');
  initClosed('interviewDetails03', 'btnSwitchDetails03');
  initClosed('btnSwitchBenefits02', 'interviewBenefits02');
  initClosed('btnSwitchBenefits03', 'interviewBenefits03');
  initClosed('btnSwitchBenefits04', 'interviewBenefits04');
  //トグル動作（各パネル）
  bindToggle('btnSwitchDetails01', 'interviewDetails01');
  bindToggle('btnSwitchDetails02', 'interviewDetails02');
  bindToggle('btnSwitchDetails03', 'interviewDetails03');
  bindToggle('btnSwitchBenefits01', 'interviewBenefits01');
  bindToggle('btnSwitchBenefits02', 'interviewBenefits02');
  bindToggle('btnSwitchBenefits03', 'interviewBenefits03');
  bindToggle('btnSwitchBenefits04', 'interviewBenefits04');
  //サイドメニュー表示 + 目次アクティブ制御
  const blockNav = document.getElementById('blockNavMenu');
  if (!blockNav) return;
  //サイドメニューは400pxスクロールで表示
  const ACTIVE_POINT = 400;
  const navLinks = Array.from(blockNav.querySelectorAll('a[href^="#"]'));
  const sectionLinkMap = new Map();
  const activatedIds = new Set();
  let isNavVisible = false;
  const observedIds = new Set();
  let observer = null;
  let lastScrollY = window.scrollY || 0;
  let scrollDirection = 'down';
  //「画面中央付近に来たら」判定用（中央の帯）例: 0.45〜0.55 => 画面の中央10%に入ったらアクティブ
  const CENTER_BAND_TOP_RATIO = 0.45;
  const CENTER_BAND_BOTTOM_RATIO = 0.55;
  //初期状態：目次は非表示 + aのis-activeは全て外す
  blockNav.classList.remove('is-active');
  blockNav.style.zIndex = '-1';
  navLinks.forEach((a) => a.classList.remove('is-active'));
  //目次リンク -> 対象セクションを紐づけ
  navLinks.forEach((a) => {
    const hash = a.getAttribute('href');
    if (!hash || !hash.startsWith('#')) return;
    const targetId = decodeURIComponent(hash.slice(1));
    if (!targetId) return;
    const section = document.getElementById(targetId);
    if (!section) return;
    sectionLinkMap.set(targetId, a);
  });
  //目次のアクティブ状態を全解除
  const clearActiveLinks = () => {
    activatedIds.clear();
    navLinks.forEach((a) => a.classList.remove('is-active'));
  };
  //指定IDの目次リンクをアクティブ化
  const activateLinkById = (id) => {
    if (!id) return;
    if (activatedIds.has(id)) return;
    const a = sectionLinkMap.get(id);
    if (!a) return;
    activatedIds.add(id);
    a.classList.add('is-active');
  };
  //指定IDの目次リンクのアクティブ解除
  const deactivateLinkById = (id) => {
    if (!id) return;
    if (!activatedIds.has(id)) return;
    const a = sectionLinkMap.get(id);
    if (a) a.classList.remove('is-active');
    activatedIds.delete(id);
  };
  //上スクロール時：画面下から外れたセクションのアクティブ解除
  const deactivateOutOfViewportSectionsOnScrollUp = () => {
    if (scrollDirection !== 'up') return;
    if (!isNavVisible) return;
    if (window.scrollY < ACTIVE_POINT) return;

    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    Array.from(activatedIds).forEach((id) => {
      const section = document.getElementById(id);
      //DOMから消えた/非表示は refreshJobCardToc 側で処理するが、念のためここでも外す
      if (!section || !isSectionDisplayed(section)) {
        deactivateLinkById(id);
        return;
      }
      const rect = section.getBoundingClientRect();
      //上スクロール時に「画面内から外れた」は、基本的に“下側に抜けた”を指す（※上側に抜けた＝過去のセクションはアクティブ維持）
      const isOutFromBottom = rect.top >= viewportHeight;
      if (isOutFromBottom) deactivateLinkById(id);
    });
  };
  //レイアウト変化等で画面下に押し出されたアクティブを解除
  const deactivateOutFromBottomActiveLinks = () => {
    if (!isNavVisible) return;
    if (window.scrollY < ACTIVE_POINT) return;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    Array.from(activatedIds).forEach((id) => {
      const section = document.getElementById(id);
      if (!section || !isSectionDisplayed(section)) {
        deactivateLinkById(id);
        return;
      }
      const rect = section.getBoundingClientRect();
      const isOutFromBottom = rect.top >= viewportHeight;
      if (isOutFromBottom) deactivateLinkById(id);
    });
  };
  //画面中央帯に入ったセクションの目次リンクをアクティブ化
  const activateVisibleSections = () => {
    if (window.scrollY < ACTIVE_POINT) return;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    const bandTop = viewportHeight * CENTER_BAND_TOP_RATIO;
    const bandBottom = viewportHeight * CENTER_BAND_BOTTOM_RATIO;
    sectionLinkMap.forEach((_, id) => {
      const section = document.getElementById(id);
      if (!section) return;
      const rect = section.getBoundingClientRect();
      //中央の帯と交差したらアクティブ
      const isNearCenter = rect.bottom > bandTop && rect.top < bandBottom;
      if (isNearCenter) activateLinkById(id);
    });
  };
  //セクションが表示状態か判定（display:none等も考慮）
  const isSectionDisplayed = (el) => {
    if (!el) return false;
    //display:none の場合は getClientRects() が空になりやすい
    if (el.getClientRects().length === 0) return false;
    const style = window.getComputedStyle(el);
    if (!style) return true;
    if (style.display === 'none') return false;
    if (style.visibility === 'hidden') return false;
    return true;
  };
  //目次リンクの表示/非表示・アクティブ状態をセクションに同期
  const refreshJobCardToc = () => {
    //セクションの可視/不可視に合わせて、目次リンク自体も出し分け
    sectionLinkMap.forEach((a, id) => {
      const section = document.getElementById(id);
      //セクション自体がDOMから無くなった場合も非表示＆アクティブ解除
      if (!section) {
        a.style.display = 'none';
        a.classList.remove('is-active');
        activatedIds.delete(id);
        if (observedIds.has(id)) observedIds.delete(id);
        return;
      }
      const shouldShow = isSectionDisplayed(section);
      a.style.display = shouldShow ? '' : 'none';
      //非表示になった項目はアクティブも解除しておく
      if (!shouldShow) {
        a.classList.remove('is-active');
        activatedIds.delete(id);
      }
      //observer 登録/解除も同期（対応ブラウザのみ）
      if (observer && section) {
        if (shouldShow && !observedIds.has(id)) {
          observer.observe(section);
          observedIds.add(id);
        } else if (!shouldShow && observedIds.has(id)) {
          observer.unobserve(section);
          observedIds.delete(id);
        }
      }
    });
    //表示中で、かつ画面内にあるセクションがあれば反映
    if (isNavVisible) activateVisibleSections();
    //レイアウト変化で「下に押し出されて画面外」になったアクティブは解除（例：ライト→スタンダード/プレミアムで上部に入力枠が増え、下のセクションが見えなくなる）
    deactivateOutFromBottomActiveLinks();
  };
  //DOM変化（表示切替/削除）でも目次を追従させる
  let tocRefreshQueued = false;
  const scheduleRefreshJobCardToc = () => {
    if (tocRefreshQueued) return;
    tocRefreshQueued = true;
    //DOM変化時の目次同期をrAFでバッチ処理
    window.requestAnimationFrame(() => {
      tocRefreshQueued = false;
      refreshJobCardToc();
    });
  };
  //契約プラン切替から呼べるように公開
  window.refreshJobCardToc = refreshJobCardToc;
  //サイドメニューの表示/非表示制御（スクロール位置で切替）
  const updateNavVisibility = () => {
    if (window.scrollY >= ACTIVE_POINT) {
      blockNav.classList.add('is-active');
      blockNav.style.zIndex = '0';
      if (!isNavVisible) {
        isNavVisible = true;
        //表示開始時点で既に画面内にあるセクションを反映
        activateVisibleSections();
        deactivateOutFromBottomActiveLinks();
      }
    } else {
      blockNav.classList.remove('is-active');
      blockNav.style.zIndex = '-1';
      isNavVisible = false;
      //ページ上部に戻ったらアクティブ解除（通過分も含めてリセット）
      clearActiveLinks();
    }
  };
  //初回実行（ページロード時）
  updateNavVisibility();
  //表示制御のみ scroll で軽量に監視
  let ticking = false;
  window.addEventListener(
    'scroll',
    () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(() => {
        const currentY = window.scrollY || 0;
        scrollDirection = currentY < lastScrollY ? 'up' : 'down';
        lastScrollY = currentY;
        updateNavVisibility();
        //上スクロール時：画面内から外れたセクションのアクティブを外す
        deactivateOutOfViewportSectionsOnScrollUp();
        ticking = false;
      });
    },
    { passive: true }
  );
  //IntersectionObserver：各セクションが画面内に入ったら対応する目次をアクティブ化
  if ('IntersectionObserver' in window) {
    //画面中央帯に入ったら isIntersecting になるようrootMargin調整
    observer = new IntersectionObserver(
      (entries) => {
        //目次非表示中（=上部）ではアクティブを付けない
        if (window.scrollY < ACTIVE_POINT) return;
        entries.forEach((entry) => {
          const id = entry.target && entry.target.id;
          if (!id) return;
          if (entry.isIntersecting) {
            //下スクロール時は通過した目次を維持するため「追加のみ」
            activateLinkById(id);
            return;
          }
        });
      },
      {
        root: null,
        //中央の帯（画面の中央10%）に入ったら isIntersecting になるようにする
        rootMargin: '-45% 0px -45% 0px',
        threshold: 0,
      }
    );
    //各セクションを監視対象に登録
    sectionLinkMap.forEach((_, id) => {
      const section = document.getElementById(id);
      if (section) {
        observer.observe(section);
        observedIds.add(id);
      }
    });
  } else {
    //フォールバック：IntersectionObserver未対応環境（表示開始後、画面内に入ったセクションを都度アクティブ化）
    window.addEventListener(
      'scroll',
      () => {
        if (window.scrollY < ACTIVE_POINT) return;
        activateVisibleSections();
      },
      { passive: true }
    );
  }
  //初期状態（契約プランの表示切替後も含む）に合わせて目次を同期
  refreshJobCardToc();
  //入力エリアがページ内から無くなった場合にもアクティブ解除したい（例：契約プラン以外の条件でセクションが非表示/削除されるケース）
  const tocWatchRoot = document.querySelector('form.block-form') || document.body;
  if ('MutationObserver' in window && tocWatchRoot) {
    //DOM変化（表示切替/削除）を監視して目次を同期
    const domObserver = new MutationObserver(() => {
      scheduleRefreshJobCardToc();
    });
    domObserver.observe(tocWatchRoot, {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: ['style', 'class', 'aria-hidden'],
    });
  }
  //ドラッグ＆ドロップアップロード
  //対象となるアップロードエリアのIDリスト：areaIdsを増やすことで複数アップロードエリア対応可能
  const areaIds = [
    'heroImage',
    'interview1Image',
    'interview2Image',
    'interview3Image',
    'benefits1Image',
    'benefits2Image',
    'benefits3Image',
    'benefits4Image',
  ];
  //各画像アップロードエリアの初期化
  areaIds.forEach(function (area) {
    let drop = document.getElementById('js-dragDrop-' + area);
    let btn = document.getElementById('js-fileSelect-' + area);
    let input = document.getElementById('js-fileElem-' + area);
    let inputMode = document.getElementById('js-uploadImageMode-' + area);
    let inputArea = document.getElementById('js-uploadImageArea-' + area);
    let preview = document.getElementById('js-previewBlock-' + area);
    let error = document.getElementById('js-fileError-' + area);
    //1枚登録モード時、プレビュー画像が1枚以上ある場合のみ非表示
    if (inputMode && inputMode.value === 'only' && drop && preview) {
      const liCount = preview.querySelectorAll('li').length;
      if (liCount >= 1) {
        drop.classList.add('is-active');
      } else {
        drop.classList.remove('is-active');
      }
    }
    //ドラッグ＆ドロップエリアの初期化
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
  //職場環境の特徴のセレクト変更により単位を入れ替える
  const unitChangeConfigs = [
    {
      radioName: 'work_environment_metrics1',
      valueSpanSelector: 'input[name="work_environment_metrics1_value"] + span',
      unitSpanSelector: 'input[name="work_environment_metrics1_unit"]',
    },
    {
      radioName: 'work_environment_metrics2',
      valueSpanSelector: 'input[name="work_environment_metrics2_value"] + span',
      unitSpanSelector: 'input[name="work_environment_metrics2_unit"]',
    },
    {
      radioName: 'work_environment_metrics3',
      valueSpanSelector: 'input[name="work_environment_metrics3_value"] + span',
      unitSpanSelector: 'input[name="work_environment_metrics3_unit"]',
    },
  ];
  //表示用単位のマッピング
  const unitDisplayMap = {
    day: '日',
    year: '年',
    yen: '万円',
    h: 'ｈ(時間)',
    '%': '％',
  };
  //職場環境の特徴の単位切替（ラジオ選択で表示単位を変更）
  unitChangeConfigs.forEach((config) => {
    const radios = document.querySelectorAll(`input[name="${config.radioName}"]`);
    let valueSpan;
    let unitSpan;
    if (config.valueSpanSelector) {
      valueSpan = document.querySelector(config.valueSpanSelector);
    }
    if (config.unitSpanSelector) {
      unitSpan = document.querySelector(config.unitSpanSelector);
    }
    //ラジオ選択時に単位表示を切替
    radios.forEach((radio) => {
      radio.addEventListener('change', function () {
        if (valueSpan && radio.dataset.unit) {
          valueSpan.textContent = unitDisplayMap[radio.dataset.unit] || radio.dataset.un;
          //セレクトボックス：初期化
          initSelectBox();
          unitSpan.value = radio.dataset.unit;
        }
      });
      //初期表示時にも単位を反映
      if (radio.checked && valueSpan && radio.dataset.unit) {
        valueSpan.textContent = unitDisplayMap[radio.dataset.unit] || radio.dataset.unit;
        unitSpan.value = radio.dataset.unit;
      }
    });
  });
});
