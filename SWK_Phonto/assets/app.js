document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-qr]').forEach((element) => {
    if (window.QRCode) {
      new QRCode(element, { text: element.dataset.qr, width: 160, height: 160 });
    } else {
      element.textContent = element.dataset.qr;
    }
  });

  const form = document.querySelector('#face-search-form');
  if (!form) return;

  const status = document.querySelector('#search-status');
  const results = document.querySelector('#search-results');

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    results.replaceChildren();
    status.innerHTML = '<div class="spinner"></div><p>กำลังเปรียบเทียบใบหน้า...</p>';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
      });
      const contentType = response.headers.get('content-type') || '';
      const payload = contentType.includes('application/json')
        ? await response.json()
        : { message: await response.text() };

      if (!response.ok) throw new Error(payload.message || 'ค้นหาไม่สำเร็จ');
      if (!payload.results.length) {
        status.innerHTML = '<div class="notice">ไม่พบรูปภาพที่มีใบหน้าตรงกัน กรุณาถ่ายรูปหน้าตรงและมีแสงสว่างเพียงพอ</div>';
        return;
      }

      status.innerHTML = `<div class="notice success">พบ ${payload.results.length} รูปที่ใบหน้าผ่านเกณฑ์ความเหมือน</div>`;
      payload.results.forEach((photo) => {
        const article = document.createElement('article');
        article.className = 'photo';

        const image = document.createElement('img');
        image.loading = 'lazy';
        image.src = photo.image_url;
        image.alt = photo.file_name;

        const meta = document.createElement('div');
        meta.className = 'meta';
        const name = document.createElement('strong');
        name.textContent = photo.file_name;
        const score = document.createElement('p');
        score.textContent = `ความเหมือน ${(photo.similarity * 100).toFixed(1)}%`;
        const download = document.createElement('a');
        download.className = 'btn';
        download.href = photo.download_url;
        download.textContent = 'ดาวน์โหลด';
        const qr = document.createElement('div');
        qr.className = 'qr';

        meta.append(name, score, download, qr);
        article.append(image, meta);
        results.append(article);
        if (window.QRCode) new QRCode(qr, { text: photo.download_url, width: 120, height: 120 });
      });
    } catch (error) {
      const message = document.createElement('div');
      message.className = 'notice error';
      message.textContent = error instanceof Error ? error.message : 'ค้นหาไม่สำเร็จ';
      status.replaceChildren(message);
    }
  });
});
