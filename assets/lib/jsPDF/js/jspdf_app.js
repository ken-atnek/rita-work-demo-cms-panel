/**
 * 領収書PDF作成処理
 * - inline onclick から呼べるよう window.makeReceiptPDF を公開
 */
(() => {
  const getStatusEl = () => document.getElementById('pdfStatus');
  const setStatus = (s, overlayEl) => {
    const statusEl = getStatusEl();
    if (statusEl) statusEl.textContent = String(s);
    if (overlayEl) overlayEl.textContent = String(s);
  };
  const lock = (x, triggerEl) => {
    if (triggerEl && triggerEl instanceof HTMLButtonElement) {
      triggerEl.disabled = Boolean(x);
      triggerEl.setAttribute('aria-busy', x ? 'true' : 'false');
    }
  };

  const ensureLibs = () => {
    const okCanvas = typeof window.html2canvas === 'function';
    const okPdf = window.jspdf && typeof window.jspdf.jsPDF === 'function';
    if (!okCanvas || !okPdf) {
      console.error('html2canvas:', window.html2canvas, 'jspdf:', window.jspdf);
      setStatus('エラー：html2canvas/jsPDF が読み込まれていません');
      return false;
    }
    return true;
  };

  const showOverlay = () => {
    const ov = document.createElement('div');
    ov.id = 'pdfOverlay';
    ov.textContent = 'PDF生成中…';
    ov.style.position = 'fixed';
    ov.style.inset = '0';
    ov.style.zIndex = '2147483647';
    ov.style.display = 'grid';
    ov.style.placeItems = 'center';
    ov.style.background = 'rgba(255,255,255,0.92)';
    ov.style.fontWeight = '800';
    ov.style.letterSpacing = '0.04em';
    document.body.appendChild(ov);
    return ov;
  };

  const waitImages = async (root) => {
    const imgs = Array.from(root.querySelectorAll('img'));
    await Promise.all(
      imgs.map((img) => {
        if (img.complete && img.naturalWidth > 0) return Promise.resolve();
        return new Promise((resolve) => {
          img.addEventListener('load', resolve, { once: true });
          img.addEventListener('error', resolve, { once: true });
        });
      })
    );
  };

  // canvas → jsPDF（2ページまで分割）
  const canvasToPdf = (canvas) => {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });

    const pageWmm = 210;
    const pageHmm = 297;
    const imgHmm = (canvas.height * pageWmm) / canvas.width;

    if (imgHmm <= pageHmm + 0.01) {
      pdf.addImage(canvas.toDataURL('image/jpeg', 0.98), 'JPEG', 0, 0, pageWmm, imgHmm);
      return pdf;
    }

    const pageHpx = Math.floor((pageHmm * canvas.width) / pageWmm);

    // 1ページ目
    const c1 = document.createElement('canvas');
    c1.width = canvas.width;
    c1.height = Math.min(pageHpx, canvas.height);
    c1.getContext('2d')?.drawImage(
      canvas,
      0,
      0,
      canvas.width,
      c1.height,
      0,
      0,
      canvas.width,
      c1.height
    );
    pdf.addImage(
      c1.toDataURL('image/jpeg', 0.98),
      'JPEG',
      0,
      0,
      pageWmm,
      (c1.height * pageWmm) / c1.width
    );

    // 2ページ目
    const remain = canvas.height - pageHpx;
    if (remain > 0) {
      pdf.addPage();
      const c2 = document.createElement('canvas');
      c2.width = canvas.width;
      c2.height = remain;
      c2.getContext('2d')?.drawImage(
        canvas,
        0,
        pageHpx,
        canvas.width,
        remain,
        0,
        0,
        canvas.width,
        remain
      );
      pdf.addImage(
        c2.toDataURL('image/jpeg', 0.98),
        'JPEG',
        0,
        0,
        pageWmm,
        (c2.height * pageWmm) / c2.width
      );
    }
    return pdf;
  };

  // 一時DOMを生成してマウント（display:noneは禁止）
  const mountTempDom = (html) => {
    const wrap = document.createElement('div');
    wrap.id = 'pdfMount';
    wrap.innerHTML = html;

    const target = wrap.querySelector('#pdfTarget');
    if (!target) throw new Error('receipt html に #pdfTarget がありません');

    // PDF用固定レイアウト強制
    target.classList.add('pdf-mode');

    // 画面外に置く（ページ描画への干渉を減らす）
    wrap.style.position = 'fixed';
    wrap.style.left = '-10000px';
    wrap.style.top = '0';
    wrap.style.zIndex = '1';
    wrap.style.background = '#fff';
    wrap.style.visibility = 'visible';
    wrap.style.opacity = '1';

    document.body.appendChild(wrap);
    return { wrap, target };
  };

  window.makeReceiptPDF = async (facilityId, invoiceId, triggerEl) => {
    if (!ensureLibs()) return;

    const trigger = triggerEl ?? document.activeElement;

    if (!facilityId) {
      setStatus('エラー：事業所IDが未指定です');
      return;
    }
    if (!invoiceId) {
      setStatus('エラー：領収書IDが未指定です');
      return;
    }

    lock(true, trigger);
    setStatus('領収書HTML取得中…');

    let overlay;
    let wrap;
    try {
      overlay = showOverlay();
      setStatus('領収書HTML取得中…', overlay);

      const body = new URLSearchParams({
        facilityId: String(facilityId),
        invoiceId: String(invoiceId),
      });

      const res = await fetch('../assets/lib/jsPDF/receipt_html.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body,
        credentials: 'same-origin',
        cache: 'no-store',
      });

      if (!res.ok) {
        const detail = await res.text().catch(() => '');
        throw new Error(`receipt_html 取得失敗: ${res.status} ${detail}`);
      }
      const html = await res.text();

      setStatus('PDF生成中…', overlay);

      const mounted = mountTempDom(html);
      wrap = mounted.wrap;
      const target = mounted.target;

      if (document.fonts?.ready) await document.fonts.ready;
      await waitImages(target);
      await new Promise((r) => requestAnimationFrame(r));

      const rect = target.getBoundingClientRect();
      // scaleを少し下げて負荷（カクつき）を軽減。画質が必要なら2に戻せます。
      const dpr = window.devicePixelRatio || 1;
      const scale = Math.min(2, Math.max(1.5, dpr));

      const canvas = await window.html2canvas(target, {
        scale,
        useCORS: true,
        backgroundColor: '#ffffff',
        scrollX: 0,
        scrollY: 0,
        windowWidth: Math.max(1, Math.ceil(rect.width)),
        windowHeight: Math.max(1, Math.ceil(rect.height)),
      });

      const pdf = canvasToPdf(canvas);

      // 1) Blob化して新規タブで表示（ブラウザ依存のsave挙動回避）
      const blob = pdf.output('blob');
      const url = URL.createObjectURL(blob);

      const w = window.open(url, '_blank');
      if (!w) {
        setStatus('ポップアップがブロックされました（許可して再実行）', overlay);
        URL.revokeObjectURL(url);
        return;
      }
      setStatus('プレビューを開きました', overlay);
      setTimeout(() => URL.revokeObjectURL(url), 60_000);

      setStatus('完了', overlay);
    } catch (e) {
      console.error(e);
      setStatus('エラー：PDF生成に失敗（Console確認）', overlay);
    } finally {
      if (wrap) wrap.remove();
      if (overlay) overlay.remove();
      lock(false, trigger);
    }
  };
})();
