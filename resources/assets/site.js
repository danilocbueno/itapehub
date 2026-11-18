(function () {
  'use strict';

  // Horários relativos: a página é estática, então calculamos no navegador.
  var pad = function (n) { return (n < 10 ? '0' : '') + n; };
  var hm = function (d) { return pad(d.getHours()) + ':' + pad(d.getMinutes()); };
  var sameDay = function (a, b) { return a.toDateString() === b.toDateString(); };

  function relative(date, now) {
    var min = Math.round((now - date) / 60000);
    if (min < 1) return 'agora';
    if (min < 60) return 'há ' + min + ' min';
    if (sameDay(date, now) && min < 6 * 60) return 'há ' + Math.floor(min / 60) + ' h';
    if (sameDay(date, now)) return 'hoje, ' + hm(date);
    var yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
    if (sameDay(date, yesterday)) return 'ontem, ' + hm(date);
    return pad(date.getDate()) + '/' + pad(date.getMonth() + 1) + ', ' + hm(date);
  }

  function refreshTimes() {
    var now = new Date();
    document.querySelectorAll('time[data-rel]').forEach(function (el) {
      var date = new Date(el.getAttribute('datetime'));
      if (!isNaN(date)) el.textContent = relative(date, now);
    });
    // "Hoje"/"Ontem" também mudam se a página ficar aberta.
    document.querySelectorAll('[data-day]').forEach(function (el) {
      var parts = el.getAttribute('data-day').split('-');
      var day = new Date(+parts[0], +parts[1] - 1, +parts[2]);
      var yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
      if (sameDay(day, now)) el.textContent = 'Hoje';
      else if (sameDay(day, yesterday)) el.textContent = 'Ontem';
    });
  }

  refreshTimes();
  setInterval(refreshTimes, 60000);

  // Previsão do tempo: um dia de cada vez, começando por hoje.
  document.querySelectorAll('[data-rotate]').forEach(function (box) {
    var slides = box.querySelectorAll('.weather-slide');
    if (slides.length < 2) return;
    var current = 0;
    var paused = false;
    ['mouseenter', 'focusin'].forEach(function (e) { box.addEventListener(e, function () { paused = true; }); });
    ['mouseleave', 'focusout'].forEach(function (e) { box.addEventListener(e, function () { paused = false; }); });
    setInterval(function () {
      if (paused || document.hidden) return;
      slides[current].hidden = true;
      slides[current].classList.remove('is-active');
      current = (current + 1) % slides.length;
      slides[current].hidden = false;
      slides[current].classList.add('is-active');
    }, 4000);
  });

  // Slides do cinema na Agenda: as setas rolam a faixa uma "página" por vez e
  // somem quando tudo cabe na tela (no celular, rola com o dedo).
  document.querySelectorAll('[data-slides]').forEach(function (track) {
    var buttons = track.parentNode.querySelectorAll('[data-slide]');
    var update = function () {
      var max = track.scrollWidth - track.clientWidth;
      buttons.forEach(function (button) {
        button.hidden = max <= 4;
        button.disabled = button.getAttribute('data-slide') === '-1' ? track.scrollLeft <= 4 : track.scrollLeft >= max - 4;
      });
    };
    buttons.forEach(function (button) {
      button.addEventListener('click', function () {
        track.scrollBy({ left: +button.getAttribute('data-slide') * track.clientWidth * 0.9, behavior: 'smooth' });
      });
    });
    track.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
  });

  // Copiar o resumo do dia.
  document.querySelectorAll('[data-copy]').forEach(function (button) {
    button.addEventListener('click', function () {
      var field = document.getElementById(button.getAttribute('data-copy'));
      var status = document.querySelector('[data-copy-status]');
      var done = function () { if (status) status.textContent = 'Texto copiado.'; };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(field.value).then(done, function () { field.select(); });
      } else {
        field.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* o texto fica selecionado */ }
      }
    });
  });

  // Busca: o índice (busca.json) é baixado na primeira busca e procurado aqui
  // mesmo, sem servidor. Ignora acentos e maiúsculas; cada palavra precisa
  // aparecer no título, no resumo ou na fonte, em qualquer ordem.
  var base = window.ITAPE_BASE || '';
  var searchIndex = null;
  var plain = function (text) {
    return String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  };
  var escapeHtml = function (text) {
    return String(text).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  function loadIndex() {
    searchIndex = searchIndex || fetch(base + '/busca.json')
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function (entries) {
        return entries.map(function (e) {
          e.nt = plain(e.t);
          e.na = plain(e.t + ' ' + e.s + ' ' + e.f);
          return e;
        });
      })
      .catch(function (error) {
        searchIndex = null; // tenta de novo na próxima busca
        throw error;
      });
    return searchIndex;
  }

  // Menor é melhor: título que começa com a busca, título com todas as
  // palavras, palavras no resumo ou na fonte. No empate, a mais nova primeiro.
  function search(entries, query) {
    var q = plain(query).trim().replace(/\s+/g, ' ');
    var words = q.split(' ');
    var found = [];
    entries.forEach(function (e) {
      if (!words.every(function (w) { return e.na.indexOf(w) !== -1; })) return;
      var score = e.nt.indexOf(q) === 0 ? 0 : (words.every(function (w) { return e.nt.indexOf(w) !== -1; }) ? 1 : 2);
      found.push({ e: e, score: score });
    });
    found.sort(function (a, b) { return a.score - b.score || (a.e.d < b.e.d ? 1 : a.e.d > b.e.d ? -1 : 0); });
    return found.map(function (f) { return f.e; });
  }

  function resultHtml(e) {
    var date = e.d.slice(8, 10) + '/' + e.d.slice(5, 7) + '/' + e.d.slice(0, 4);
    var target = e.i ? '' : ' target="_blank" rel="noopener"';
    return '<a class="search-result" href="' + escapeHtml(e.u) + '"' + target + '>' +
      '<span class="search-result-title">' + escapeHtml(e.t) + '</span>' +
      '<span class="search-result-meta">' + escapeHtml(e.f) + ' · ' + date + '</span></a>';
  }

  // Liga um campo de busca à sua lista de resultados (no modal e na página).
  function searchBox(form, onEmpty) {
    var input = form.querySelector('[data-search-input]');
    var results = form.querySelector('[data-search-results]');
    var summary = form.querySelector('[data-search-summary]');
    var limit = +results.getAttribute('data-limit') || 200;

    var render = function () {
      var raw = input.value.trim();
      if (!raw) {
        form.setAttribute('data-empty', '');
        summary.textContent = '';
        results.innerHTML = '';
        if (onEmpty) onEmpty();
        return;
      }
      form.removeAttribute('data-empty');
      loadIndex().then(function (entries) {
        if (input.value.trim() !== raw) return; // chegou outra tecla enquanto baixava
        var found = search(entries, raw);
        var shown = found.slice(0, limit);
        summary.textContent = found.length === 0 ? '' :
          found.length + (found.length === 1 ? ' notícia' : ' notícias') + ' com “' + raw + '”' +
          (shown.length < found.length ? ' (mostrando ' + shown.length + ')' : '') + '.';
        results.innerHTML = shown.length ? shown.map(resultHtml).join('') :
          '<p class="search-empty">Nenhuma notícia com “' + escapeHtml(raw) + '”. Tente outra palavra.</p>';
      }, function () {
        summary.textContent = '';
        results.innerHTML = '<p class="search-empty">Não foi possível carregar a busca agora. Tente de novo.</p>';
      });
    };

    input.addEventListener('input', render);

    // Setas: do campo para os resultados e entre eles.
    form.addEventListener('keydown', function (event) {
      if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
      var links = Array.prototype.slice.call(results.querySelectorAll('a'));
      if (!links.length) return;
      var current = links.indexOf(document.activeElement);
      if (current === -1 && event.key === 'ArrowUp') return;
      event.preventDefault();
      if (event.key === 'ArrowDown') (links[current + 1] || links[0]).focus();
      else if (current === 0) input.focus();
      else links[current - 1].focus();
    });

    return { input: input, render: render };
  }

  var page = document.querySelector('[data-search-page]');
  var dialog = document.querySelector('[data-search-dialog]');
  var toggle = document.querySelector('[data-search-toggle]');
  var pageBox = null;

  // Página /busca/?q=: mostra todos os resultados e mantém a busca no endereço.
  if (page) {
    pageBox = searchBox(page);
    pageBox.input.value = new URLSearchParams(location.search).get('q') || '';
    pageBox.render();
    pageBox.input.addEventListener('input', function () {
      var q = pageBox.input.value.trim();
      history.replaceState(null, '', location.pathname + (q ? '?q=' + encodeURIComponent(q) : ''));
    });
    page.addEventListener('submit', function (event) { event.preventDefault(); });
  }

  // Modal: abre pela lupa, Ctrl+K (⌘K no Mac) ou "/"; Enter leva à página com tudo.
  if (dialog && typeof dialog.showModal === 'function') {
    var box = searchBox(dialog.querySelector('[data-search-panel]'));
    var shortcut = dialog.querySelector('[data-search-shortcut]');
    if (shortcut && /Mac|iPhone|iPad/.test(navigator.userAgent)) shortcut.textContent = '⌘ K';

    var open = function () {
      if (pageBox) { pageBox.input.focus(); return; } // na página de busca, usa o campo dela
      if (!dialog.open) dialog.showModal();
      document.documentElement.classList.add('has-open-search');
      box.render();
      requestAnimationFrame(function () { box.input.focus(); box.input.select(); });
    };
    var close = function () { if (dialog.open) dialog.close(); };

    if (toggle) toggle.addEventListener('click', function (event) { event.preventDefault(); open(); });
    dialog.querySelector('[data-search-close]').addEventListener('click', close);
    dialog.addEventListener('close', function () {
      document.documentElement.classList.remove('has-open-search');
      if (toggle) toggle.focus();
    });
    // Clique fora do painel fecha.
    dialog.addEventListener('click', function (event) { if (event.target === dialog) close(); });

    document.addEventListener('keydown', function (event) {
      var target = event.target;
      var editing = target instanceof HTMLElement && (target.matches('input, textarea, select') || target.isContentEditable);
      var command = (event.metaKey || event.ctrlKey) && !event.altKey && event.key.toLowerCase() === 'k';
      var slash = event.key === '/' && !editing && !event.metaKey && !event.ctrlKey && !event.altKey;
      if (!command && !slash) return;
      event.preventDefault();
      if (dialog.open && command) close();
      else open();
    });
  }
})();
