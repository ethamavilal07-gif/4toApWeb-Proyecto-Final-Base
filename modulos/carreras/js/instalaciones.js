/* Ficha pública: al hacer clic en una instalación se amplía y el resto se desenfoca. */
(function () {
    'use strict';
    var visor = document.getElementById('visor-instalacion');
    if (!visor || typeof visor.showModal !== 'function') { return; }

    var medio = visor.querySelector('[data-visor-medio]');
    var titulo = visor.querySelector('[data-visor-titulo]');
    var descripcion = visor.querySelector('[data-visor-descripcion]');
    var origen = null;

    function abrir(tarjeta) {
        var src = tarjeta.getAttribute('data-src');
        var tipo = tarjeta.getAttribute('data-tipo');
        medio.textContent = '';

        var el;
        if (tipo === 'video') {
            el = document.createElement('video');
            el.src = src;
            el.controls = true;
            el.autoplay = true;
            el.playsInline = true;
        } else {
            el = document.createElement('img');
            el.src = src;
            el.alt = tarjeta.getAttribute('data-titulo') || '';
        }
        medio.appendChild(el);

        titulo.textContent = tarjeta.getAttribute('data-titulo') || '';
        descripcion.textContent = tarjeta.getAttribute('data-descripcion') || '';
        origen = tarjeta;
        visor.showModal();
    }

    function limpiar() {
        var v = medio.querySelector('video');
        if (v) { v.pause(); }
        medio.textContent = '';
        if (origen) { origen.focus(); origen = null; }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-instalacion]'), function (t) {
        t.addEventListener('click', function () { abrir(t); });
        t.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); abrir(t); }
        });
    });

    visor.querySelector('[data-visor-cerrar]').addEventListener('click', function () { visor.close(); });
    // Clic fuera de la ventana (en el fondo desenfocado) la cierra.
    visor.addEventListener('click', function (e) { if (e.target === visor) { visor.close(); } });
    visor.addEventListener('close', limpiar);
})();
