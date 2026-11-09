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
})();
