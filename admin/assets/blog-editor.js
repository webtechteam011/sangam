(function () {
  'use strict';
  var CFG = window.BLOG_EDITOR || {};
  var $ = function (id) { return document.getElementById(id); };
  var form = $('post-form');
  if (!form) { return; }
  var editor = $('editor'), source = $('source'), hidden = $('f-content');
  var title = $('f-title'), slug = $('f-slug');
  var dirty = false, sourceMode = false, savedRange = null, slugTouched = form.dataset.new !== '1' || slug.value !== '';
  var saving = false;

  try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

  /* ---------------------------------------------------------- init content */
  editor.innerHTML = hidden.value.trim() || '<p><br></p>';

  function markDirty() { dirty = true; setState('Unsaved changes'); }
  function setState(t) { var s = $('savestate'); if (s) { s.textContent = t; } }
  function currentHtml() { return sourceMode ? source.value : editor.innerHTML; }
  function sync() { hidden.value = currentHtml(); }

  /* ------------------------------------------------------------ helpers */
  function slugify(s) {
    return s.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 100);
  }
  function textOf(html) { var d = document.createElement('div'); d.innerHTML = html; return (d.textContent || '').replace(/\s+/g, ' ').trim(); }
  function saveRange() { var s = window.getSelection(); if (s.rangeCount && editor.contains(s.anchorNode)) { savedRange = s.getRangeAt(0).cloneRange(); } }
  function restoreRange() {
    editor.focus();
    if (savedRange) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(savedRange); }
  }
  function insertHtml(html) { restoreRange(); document.execCommand('insertHTML', false, html); markDirty(); onInput(); }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---------------------------------------------------------- toolbar */
  document.addEventListener('selectionchange', saveRange);
  $('toolbar').addEventListener('mousedown', function (e) { if (e.target.closest('button')) { e.preventDefault(); } });
  $('toolbar').addEventListener('click', function (e) {
    var b = e.target.closest('button[data-cmd]');
    if (!b) { return; }
    run(b.dataset.cmd);
  });
  $('toolbar').querySelector('select').addEventListener('change', function () {
    restoreRange();
    document.execCommand('formatBlock', false, '<' + this.value + '>');
    markDirty(); onInput();
  });

  function run(cmd) {
    if (cmd === 'source') { return toggleSource(); }
    if (cmd === 'fullscreen') { document.body.classList.toggle('editor-full'); return; }
    if (sourceMode) { return; }
    restoreRange();
    switch (cmd) {
      case 'link':
        saveRange();
        ask('Insert link', '', false).then(function (r) {
          if (!r) { return; }
          restoreRange();
          var sel = window.getSelection();
          if (sel.isCollapsed) { document.execCommand('insertHTML', false, '<a href="' + esc(r.url) + '">' + esc(r.url) + '</a>'); }
          else { document.execCommand('createLink', false, r.url); }
          markDirty(); onInput();
        });
        return;
      case 'unlink': document.execCommand('unlink'); break;
      case 'image': openMedia(function (m) {
        insertHtml('<p><img src="' + esc(m.path) + '" alt="' + esc(m.alt || '') + '" width="' + (m.width || '') + '" height="' + (m.height || '') + '"></p>');
      }); return;
      case 'video':
        ask('Embed YouTube video', '', false, 'Paste a YouTube link').then(function (r) {
          if (!r) { return; }
          var m = r.url.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([A-Za-z0-9_-]{6,20})/);
          if (!m) { alert('That does not look like a YouTube link.'); return; }
          insertHtml('<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/' + m[1] + '" title="Video" allowfullscreen></iframe></div><p><br></p>');
        });
        return;
      case 'table':
        insertHtml('<table><thead><tr><th>Heading</th><th>Heading</th><th>Heading</th></tr></thead><tbody>' +
          '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr></tbody></table><p><br></p>');
        return;
      case 'callout': {
        var sel = window.getSelection(), txt = sel.isCollapsed ? 'Write your tip or note here.' : esc(sel.toString());
        insertHtml('<div class="callout"><p>' + txt + '</p></div><p><br></p>');
        return;
      }
      case 'button':
        saveRange();
        ask('Call-to-action button', '', true).then(function (r) {
          if (!r) { return; }
          insertHtml('<p class="align-center"><a class="cta-button" href="' + esc(r.url) + '">' + esc(r.label || 'Book your stay') + '</a></p>');
        });
        return;
      case 'hr': document.execCommand('insertHorizontalRule'); break;
      default: document.execCommand(cmd);
    }
    markDirty(); onInput();
  }

  document.addEventListener('keydown', function (e) {
    var mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(true); }
    if (mod && e.key.toLowerCase() === 'k' && document.activeElement === editor) { e.preventDefault(); run('link'); }
    if (e.key === 'Escape' && document.body.classList.contains('editor-full')) { document.body.classList.remove('editor-full'); }
  });

  function toggleSource() {
    if (!sourceMode) {
      source.value = formatHtml(editor.innerHTML);
      editor.hidden = true; source.hidden = false; sourceMode = true;
    } else {
      editor.innerHTML = source.value || '<p><br></p>';
      source.hidden = true; editor.hidden = false; sourceMode = false;
    }
    $('btn-source').classList.toggle('on', sourceMode);
    $('imgbar').hidden = true;
    onInput();
  }
  function formatHtml(h) { return h.replace(/<\/(p|h[1-6]|ul|ol|li|blockquote|table|tr|figure|div)>/g, '</$1>\n').replace(/\n{2,}/g, '\n').trim(); }
  source.addEventListener('input', function () { markDirty(); onInput(); });

  /* ------------------------------------------------- ask dialog (URL etc.) */
  var dlg = $('ask-dlg');
  function ask(heading, url, withLabel, placeholder) {
    return new Promise(function (resolve) {
      $('ask-title').textContent = heading;
      $('ask-url').value = url || '';
      $('ask-url').placeholder = placeholder || 'https://…  or  /contact';
      $('ask-label-wrap').hidden = !withLabel;
      $('ask-label').value = '';
      dlg.returnValue = '';
      dlg.addEventListener('close', function done() {
        dlg.removeEventListener('close', done);
        var u = $('ask-url').value.trim();
        if (dlg.returnValue !== 'ok' || !u) { return resolve(null); }
        if (!/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(u)) { u = 'https://' + u; }
        resolve({ url: u, label: $('ask-label').value.trim() });
      });
      dlg.showModal();
      $('ask-url').focus();
    });
  }

  /* ------------------------------------------------------ paste cleanup */
  var ALLOWED = { P: 1, BR: 1, H2: 1, H3: 1, H4: 1, STRONG: 1, EM: 1, U: 1, S: 1, UL: 1, OL: 1, LI: 1, BLOCKQUOTE: 1, A: 1, IMG: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TH: 1, TD: 1, FIGURE: 1, FIGCAPTION: 1, HR: 1 };
  var RENAME = { H1: 'H2', H5: 'H4', H6: 'H4', B: 'STRONG', I: 'EM', DEL: 'S', STRIKE: 'S' };
  function cleanNode(node) {
    Array.prototype.slice.call(node.childNodes).forEach(function (ch) {
      if (ch.nodeType === 8) { node.removeChild(ch); return; }
      if (ch.nodeType !== 1) { return; }
      var tag = ch.tagName;
      if (/^(SCRIPT|STYLE|META|LINK|IFRAME|OBJECT|EMBED|FORM|INPUT|BUTTON|SVG|NOSCRIPT)$/.test(tag)) { node.removeChild(ch); return; }
      if (RENAME[tag]) {
        var n = document.createElement(RENAME[tag]);
        while (ch.firstChild) { n.appendChild(ch.firstChild); }
        node.replaceChild(n, ch); ch = n; tag = ch.tagName;
      }
      cleanNode(ch);
      if (!ALLOWED[tag]) {
        while (ch.firstChild) { node.insertBefore(ch.firstChild, ch); }
        node.removeChild(ch); return;
      }
      Array.prototype.slice.call(ch.attributes).forEach(function (a) {
        var keep = (tag === 'A' && a.name === 'href' && /^(https?:|mailto:|tel:|\/|#)/i.test(a.value)) ||
                   (tag === 'IMG' && (a.name === 'alt' || (a.name === 'src' && /^(https?:\/\/|\/)/i.test(a.value))));
        if (!keep) { ch.removeAttribute(a.name); }
      });
    });
  }
  editor.addEventListener('paste', function (e) {
    var cd = e.clipboardData; if (!cd) { return; }
    var files = Array.prototype.slice.call(cd.files || []).filter(function (f) { return /^image\//.test(f.type); });
    if (files.length) { e.preventDefault(); files.forEach(uploadAndInsert); return; }
    var html = cd.getData('text/html');
    if (!html) { return; }
    e.preventDefault();
    var tmp = document.createElement('div');
    tmp.innerHTML = html.replace(/<!--[\s\S]*?-->/g, '');
    cleanNode(tmp);
    document.execCommand('insertHTML', false, tmp.innerHTML);
  });
  editor.addEventListener('dragover', function (e) { if (e.dataTransfer && e.dataTransfer.types.indexOf('Files') > -1) { e.preventDefault(); } });
  editor.addEventListener('drop', function (e) {
    var files = Array.prototype.slice.call((e.dataTransfer && e.dataTransfer.files) || []).filter(function (f) { return /^image\//.test(f.type); });
    if (files.length) { e.preventDefault(); saveRange(); files.forEach(uploadAndInsert); }
  });
  function uploadAndInsert(file) {
    setState('Uploading image…');
    uploadFile(file).then(function (m) {
      insertHtml('<p><img src="' + esc(m.path) + '" alt="' + esc(m.alt || '') + '" width="' + m.width + '" height="' + m.height + '"></p>');
      setState('Image added');
    }, function (err) { alert(err); setState(''); });
  }

  /* --------------------------------------------------------- image bar */
  var imgbar = $('imgbar'), activeImg = null;
  editor.addEventListener('click', function (e) {
    if (e.target.tagName === 'IMG') {
      activeImg = e.target;
      document.querySelectorAll('#editor img.sel').forEach(function (i) { i.classList.remove('sel'); });
      activeImg.classList.add('sel');
      $('img-alt').value = activeImg.getAttribute('alt') || '';
      imgbar.hidden = false;
    } else if (activeImg) { activeImg.classList.remove('sel'); activeImg = null; imgbar.hidden = true; }
  });
  $('img-alt').addEventListener('input', function () { if (activeImg) { activeImg.setAttribute('alt', this.value); markDirty(); onInput(); } });
  $('img-remove').addEventListener('click', function () {
    if (!activeImg) { return; }
    var fig = activeImg.closest('figure') || activeImg.closest('p') || activeImg;
    fig.parentNode.removeChild(fig); activeImg = null; imgbar.hidden = true; markDirty(); onInput();
  });
  $('img-caption').addEventListener('click', function () {
    if (!activeImg || activeImg.closest('figure')) { return; }
    var fig = document.createElement('figure'), cap = document.createElement('figcaption');
    cap.textContent = 'Caption';
    var host = activeImg.parentNode.tagName === 'P' ? activeImg.parentNode : activeImg;
    host.parentNode.replaceChild(fig, host);
    fig.appendChild(activeImg); fig.appendChild(cap);
    markDirty(); onInput();
  });

  /* ---------------------------------------------------- media library */
  var mdlg = $('media-dlg'), mgrid = $('media-grid'), mcb = null, mpage = 1;
  function openMedia(cb) {
    mcb = cb; mpage = 1; mgrid.innerHTML = '';
    loadMedia();
    mdlg.showModal();
  }
  function loadMedia() {
    fetch(CFG.mediaUrl + '?json=1&page=' + mpage, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      d.items.forEach(addTile);
      $('media-more').hidden = !d.more;
      if (!d.items.length && mpage === 1) { mgrid.innerHTML = '<p class="muted">No images yet — upload one above.</p>'; }
    });
  }
  function addTile(m, prepend) {
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'tile';
    b.innerHTML = '<img src="' + esc(m.thumb || m.path) + '" alt="" loading="lazy"><span>' + esc(m.alt || m.original || '') + '</span>';
    b.addEventListener('click', function () { mdlg.close(); if (mcb) { mcb(m); } });
    if (prepend && mgrid.firstChild) { mgrid.insertBefore(b, mgrid.firstChild); } else { mgrid.appendChild(b); }
  }
  $('media-more').addEventListener('click', function () { mpage++; loadMedia(); });
  $('media-close').addEventListener('click', function () { mdlg.close(); });
  $('media-browse').addEventListener('click', function () { $('media-file').click(); });
  $('media-file').addEventListener('change', function () { Array.prototype.forEach.call(this.files, function (f) { uploadFile(f).then(function (m) { addTile(m, true); $('media-status').textContent = 'Uploaded ' + m.original; }, function (err) { $('media-status').textContent = err; }); }); this.value = ''; });
  var drop = $('media-drop');
  ['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('over'); }); });
  drop.addEventListener('drop', function (e) {
    e.preventDefault();
    Array.prototype.forEach.call(e.dataTransfer.files, function (f) { uploadFile(f).then(function (m) { addTile(m, true); }, function (err) { $('media-status').textContent = err; }); });
  });
  function uploadFile(file) {
    var fd = new FormData();
    fd.append('_csrf', CFG.csrf); fd.append('action', 'upload'); fd.append('file', file);
    return fetch(CFG.mediaUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { throw d.error || 'Upload failed'; }
      return d.item;
    });
  }

  /* image pickers (cover + social) */
  function previewFor(box) {
    var input = box.querySelector('input'), prev = $(box.dataset.preview);
    if (!prev) { return; }
    if (prev.tagName === 'DIV') { prev.innerHTML = input.value ? '<img src="' + esc(input.value) + '" alt="">' : '<span>No image</span>'; }
  }
  document.querySelectorAll('.img-pick').forEach(function (box) {
    var input = box.querySelector('input');
    previewFor(box);
    input.addEventListener('input', function () { previewFor(box); markDirty(); analyse(); });
    box.querySelector('.pick-btn').addEventListener('click', function () {
      openMedia(function (m) {
        input.value = m.path; previewFor(box);
        if (box.dataset.alt && !$(box.dataset.alt).value) { $(box.dataset.alt).value = m.alt || ''; }
        markDirty(); analyse();
      });
    });
  });
  $('cover-clear').addEventListener('click', function () { $('f-cover').value = ''; $('f-cover-alt').value = ''; previewFor($('f-cover').parentNode); markDirty(); analyse(); });

  /* ----------------------------------------------------------- tags */
  var tagBox = $('tag-box'), tagText = $('tag-text'), tagField = $('f-tags');
  var tags = tagField.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
  function renderTags() {
    tagBox.querySelectorAll('.chip').forEach(function (c) { c.remove(); });
    tags.forEach(function (t, i) {
      var c = document.createElement('span'); c.className = 'chip';
      c.innerHTML = esc(t) + ' <button type="button" aria-label="Remove ' + esc(t) + '">×</button>';
      c.querySelector('button').addEventListener('click', function () { tags.splice(i, 1); renderTags(); markDirty(); });
      tagBox.insertBefore(c, tagText);
    });
    tagField.value = tags.join(', ');
  }
  function addTag(t) {
    t = t.replace(/^#/, '').replace(/,/g, ' ').trim();
    if (t && tags.map(function (x) { return x.toLowerCase(); }).indexOf(t.toLowerCase()) < 0 && tags.length < 15) { tags.push(t); renderTags(); markDirty(); }
    tagText.value = '';
  }
  tagText.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(tagText.value); }
    else if (e.key === 'Backspace' && !tagText.value && tags.length) { tags.pop(); renderTags(); markDirty(); }
  });
  tagText.addEventListener('change', function () { addTag(tagText.value); });
  tagText.addEventListener('blur', function () { addTag(tagText.value); });
  renderTags();

  /* ------------------------------------------------------------ FAQ */
  $('faq-add').addEventListener('click', function () {
    var d = document.createElement('div'); d.className = 'faq-row';
    d.innerHTML = '<input name="faq_q[]" maxlength="250" placeholder="Question"><textarea name="faq_a[]" rows="2" maxlength="2000" placeholder="Answer"></textarea><button type="button" class="btn danger sm faq-del" aria-label="Remove question">✕</button>';
    $('faq-list').appendChild(d); d.querySelector('input').focus(); markDirty();
  });
  $('faq-list').addEventListener('click', function (e) { var b = e.target.closest('.faq-del'); if (b) { b.parentNode.remove(); markDirty(); } });

  /* ------------------------------------------------- slug / status UI */
  title.addEventListener('input', function () { if (!slugTouched) { slug.value = slugify(title.value); } analyse(); });
  slug.addEventListener('input', function () { slugTouched = true; analyse(); });
  slug.addEventListener('blur', function () { slug.value = slugify(slug.value); analyse(); });
  var statusSel = $('f-status'), dateIn = $('f-date');
  function dateHint() {
    var h = $('date-hint'), d = dateIn.value ? new Date(dateIn.value) : null;
    if (statusSel.value !== 'published') { h.textContent = 'Not visible to the public until published.'; return; }
    if (d && d > new Date()) { h.textContent = '⏰ Will go live automatically on ' + d.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) + '.'; }
    else { h.textContent = dateIn.value ? 'Visible on the blog now.' : 'Will be published immediately when you save.'; }
  }
  statusSel.addEventListener('change', dateHint); dateIn.addEventListener('input', dateHint); dateHint();
  $('btn-publish').addEventListener('click', function () { statusSel.value = 'published'; dateHint(); save(false, true); });

  /* ------------------------------------------------- counters + SERP */
  function counter(span) {
    var inp = $(span.dataset.for), min = +span.dataset.min, max = +span.dataset.max;
    function upd() { var n = inp.value.length; span.textContent = n + ' chars'; span.className = 'cnt ' + (n >= min && n <= max ? 'ok' : 'bad'); }
    inp.addEventListener('input', upd); upd();
  }
  document.querySelectorAll('.cnt[data-for]').forEach(counter);

  /* ------------------------------------------------------ SEO analyser */
  function analyse() {
    var html = currentHtml(), tmp = document.createElement('div'); tmp.innerHTML = html;
    var text = (tmp.textContent || '').replace(/\s+/g, ' ').trim();
    var words = text ? text.split(' ').length : 0;
    var kw = $('f-kw').value.trim().toLowerCase();
    var seoTitle = $('f-mtitle').value.trim() || (title.value.trim() ? title.value.trim() + ' | ' + CFG.siteName : '');
    var desc = $('f-mdesc').value.trim() || $('f-excerpt').value.trim() || text.slice(0, 155);
    var sl = slug.value || slugify(title.value);
    var firstP = (tmp.querySelector('p') || {}).textContent || '';
    var first100 = text.split(' ').slice(0, 100).join(' ').toLowerCase();
    var heads = Array.prototype.map.call(tmp.querySelectorAll('h2,h3'), function (h) { return h.textContent.toLowerCase(); });
    var imgs = tmp.querySelectorAll('img'), noAlt = Array.prototype.filter.call(imgs, function (i) { return !(i.getAttribute('alt') || '').trim(); }).length;
    var links = Array.prototype.map.call(tmp.querySelectorAll('a[href]'), function (a) { return a.getAttribute('href'); });
    var internal = links.filter(function (h) { return h.charAt(0) === '/' || h.indexOf('thesangamhouse.com') > -1; }).length;
    var external = links.length - internal;
    var kwSlug = slugify(kw), count = 0;
    if (kw && words) { count = (text.toLowerCase().split(kw).length - 1); }
    var density = words ? (count * kw.split(' ').length / words * 100) : 0;

    // SERP preview
    $('serp-url').textContent = (CFG.siteUrl || '').replace(/^https?:\/\//, '') + ' › blog › ' + (sl || '…');
    $('serp-title').textContent = seoTitle || 'Your post title';
    $('serp-desc').textContent = desc.slice(0, 160) || 'Your meta description will appear here.';

    var checks = [
      [seoTitle.length >= 30 && seoTitle.length <= 60 ? 'ok' : (seoTitle.length ? 'warn' : 'bad'), 'SEO title is ' + seoTitle.length + ' characters (aim for 30–60)', 2],
      [desc.length >= 100 && desc.length <= 160 ? 'ok' : (desc.length ? 'warn' : 'bad'), 'Meta description is ' + desc.length + ' characters (aim for 100–160)', 2],
      [kw ? 'ok' : 'bad', kw ? 'Focus keyword set' : 'Set a focus keyword', 1],
      [kw && seoTitle.toLowerCase().indexOf(kw) > -1 ? 'ok' : 'bad', 'Keyword in the SEO title', 2],
      [kw && desc.toLowerCase().indexOf(kw) > -1 ? 'ok' : 'bad', 'Keyword in the meta description', 1],
      [kwSlug && sl.indexOf(kwSlug) > -1 ? 'ok' : 'bad', 'Keyword in the URL slug', 1],
      [kw && first100.indexOf(kw) > -1 ? 'ok' : 'bad', 'Keyword in the first 100 words', 2],
      [kw && heads.some(function (h) { return h.indexOf(kw) > -1; }) ? 'ok' : 'bad', 'Keyword in a subheading (H2/H3)', 1],
      [!kw ? 'bad' : (density >= 0.4 && density <= 2.5 ? 'ok' : 'warn'), 'Keyword density ' + density.toFixed(1) + '% (aim for 0.5–2%)', 1],
      [words >= 900 ? 'ok' : (words >= 500 ? 'warn' : 'bad'), words + ' words (900+ ranks best, 500 minimum)', 3],
      [tmp.querySelectorAll('h2').length >= 2 ? 'ok' : 'bad', 'At least two H2 subheadings', 1],
      [imgs.length ? (noAlt ? 'warn' : 'ok') : 'bad', imgs.length ? (noAlt ? noAlt + ' image(s) missing alt text' : 'All images have alt text') : 'Add at least one image in the body', 1],
      [$('f-cover').value ? ($('f-cover-alt').value.trim() ? 'ok' : 'warn') : 'bad', $('f-cover').value ? ($('f-cover-alt').value.trim() ? 'Cover image with alt text' : 'Cover image needs alt text') : 'Add a cover image', 1],
      [internal ? 'ok' : 'bad', internal ? 'Has internal link(s)' : 'Link to another page (e.g. /accommodation or /contact)', 1],
      [external ? 'ok' : 'warn', external ? 'Has external link(s)' : 'Consider linking to a trusted source', 1],
      [firstP.length > 0 && firstP.length < 400 ? 'ok' : 'warn', 'Opening paragraph is short and clear', 1]
    ];
    var total = 0, got = 0;
    checks.forEach(function (c) { total += c[2]; got += c[0] === 'ok' ? c[2] : (c[0] === 'warn' ? c[2] * 0.5 : 0); });
    var score = Math.round(got / total * 100);
    var sc = $('seo-score'); sc.textContent = score + '/100'; sc.className = 'score ' + (score >= 80 ? 'ok' : score >= 55 ? 'warn' : 'bad');
    $('seo-checks').innerHTML = checks.map(function (c) { return '<li class="' + c[0] + '">' + esc(c[1]) + '</li>'; }).join('');

    $('wc').textContent = words.toLocaleString() + ' words';
    $('rt').textContent = Math.max(1, Math.ceil(words / 200)) + ' min read';
  }
  var t = null;
  function onInput() { clearTimeout(t); t = setTimeout(analyse, 250); }
  editor.addEventListener('input', function () { markDirty(); onInput(); });
  form.addEventListener('input', function (e) { if (!editor.contains(e.target) && e.target !== source) { markDirty(); onInput(); } });

  /* ------------------------------------------------------------- save */
  var errBox = $('js-errors');
  function save(stay, publishing) {
    if (saving) { return; }
    if (!title.value.trim()) { title.focus(); errBox.hidden = false; errBox.textContent = 'Title is required.'; return; }
    sync();
    saving = true; setState('Saving…');
    var fd = new FormData(form);
    fd.set('ajax', '1'); fd.set('action', 'save');
    fetch(CFG.postUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      saving = false;
      if (!d.ok) { errBox.hidden = false; errBox.innerHTML = '<strong>Could not save:</strong> ' + d.errors.map(esc).join(' · '); setState('Not saved'); return; }
      errBox.hidden = true; dirty = false;
      var firstSave = form.dataset.id === '0';
      $('f-id').value = d.id; form.dataset.id = d.id; slug.value = d.slug; slugTouched = true;
      dateIn.value = d.published_at || dateIn.value;
      statusSel.value = d.status; dateHint();
      var pv = $('btn-preview'); pv.hidden = false; pv.href = '/blog/' + d.slug + '?preview=1';
      var live = d.state === 'published' || d.state === 'scheduled';
      $('btn-save').textContent = live ? 'Update post' : 'Save draft';
      $('btn-publish').hidden = live;
      setState('Saved ' + d.updated + (d.state === 'scheduled' ? ' · scheduled' : ''));
      if (firstSave && window.history.replaceState) { window.history.replaceState(null, '', '/admin/blog-edit?id=' + d.id); }
      analyse();
    }).catch(function () { saving = false; setState('Save failed — check connection'); });
  }
  form.addEventListener('submit', function (e) {
    var submitter = e.submitter;
    if (submitter && submitter.name === 'action') { dirty = false; sync(); return; }   // trash / restore revision
    e.preventDefault(); save(false);
  });

  // Autosave drafts only (never silently alters a live post).
  setInterval(function () {
    if (dirty && !saving && statusSel.value === 'draft' && title.value.trim() && form.dataset.live !== '1') { save(true); }
  }, 60000);
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  analyse();
})();
