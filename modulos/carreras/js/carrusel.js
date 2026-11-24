/**
 * CAR · Carrusel infinito de la ficha pública
 * La foto central va enfocada y las vecinas desenfocadas (lo resuelve el CSS
 * con data-pos). Gira solo, sin fin; se detiene al pasar el cursor o con el
 * foco en los botones, y respeta "reducir movimiento" del sistema.
 */
(function () {
  'use strict';

  var INTERVALO = 3500;

  function iniciar(caja) {
    var slides = Array.prototype.slice.call(caja.querySelectorAll('.car-carrusel__slide'));
    var n = slides.length;
    if (n === 0) { return; }

    caja.classList.add('is-js');

    var actual = 0;
    var posiciones = [];
    var temporizador = null;

    function posicion(i) {
      var d = i - actual;
      if (d > n / 2)   { d -= n; }
      if (d <= -n / 2) { d += n; }
      return d;
    }

    function pintar(primera) {
      slides.forEach(function (slide, i) {
        var d = posicion(i);
        var visible = Math.abs(d) <= 2;
        var anterior = posiciones[i];

        // Si una foto "da la vuelta" de un extremo al otro, se coloca sin
        // animar para que no cruce el carrusel por delante de las demás.
        var salto = primera || (anterior !== undefined && Math.abs(d - anterior) > 2);
        if (salto) { slide.style.transition = 'none'; }

        slide.setAttribute('data-pos', visible ? String(d) : 'oculta');
        slide.setAttribute('aria-hidden', d === 0 ? 'false' : 'true');
        posiciones[i] = d;

        if (salto) {
          void slide.offsetWidth;          // fuerza el reflujo antes de restaurar
          slide.style.transition = '';
        }
      });
    }

    function ir(i) {
      actual = ((i % n) + n) % n;
      pintar(false);
    }

    function detenerGiro() {
      if (temporizador !== null) {
        window.clearInterval(temporizador);
        temporizador = null;
      }
    }

    function iniciarGiro() {
      detenerGiro();
      if (n < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
      temporizador = window.setInterval(function () { ir(actual + 1); }, INTERVALO);
    }

    // Clic en una foto lateral: pasa al centro.
    slides.forEach(function (slide, i) {
      slide.addEventListener('click', function () {
        if (i !== actual) { ir(i); iniciarGiro(); }
      });
    });

    var ant = caja.querySelector('.car-carrusel__btn--ant');
    var sig = caja.querySelector('.car-carrusel__btn--sig');
    if (ant) { ant.addEventListener('click', function () { ir(actual - 1); iniciarGiro(); }); }
    if (sig) { sig.addEventListener('click', function () { ir(actual + 1); iniciarGiro(); }); }

    caja.addEventListener('mouseenter', detenerGiro);
    caja.addEventListener('mouseleave', iniciarGiro);
    caja.addEventListener('focusin', detenerGiro);
    caja.addEventListener('focusout', iniciarGiro);

    // Teclado: flechas cuando el carrusel tiene el foco.
    caja.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowLeft')  { ir(actual - 1); }
      if (ev.key === 'ArrowRight') { ir(actual + 1); }
    });

    // Deslizar con el dedo.
    var inicioX = null;
    caja.addEventListener('touchstart', function (ev) {
      inicioX = ev.touches[0].clientX;
      detenerGiro();
    }, { passive: true });
    caja.addEventListener('touchend', function (ev) {
      if (inicioX !== null) {
        var dx = ev.changedTouches[0].clientX - inicioX;
        if (Math.abs(dx) > 40) { ir(actual + (dx < 0 ? 1 : -1)); }
      }
      inicioX = null;
      iniciarGiro();
    });

    // Pausa si la pestaña no está visible.
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { detenerGiro(); } else { iniciarGiro(); }
    });

    pintar(true);
    iniciarGiro();
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-carrusel]'), iniciar);
})();
