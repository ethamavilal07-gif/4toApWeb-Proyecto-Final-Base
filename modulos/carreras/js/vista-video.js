/**
 * CAR · Vista previa del video mientras se escribe la dirección
 *
 * Uso: <input type="url" data-vista-video="id-del-contenedor">
 * Reconoce YouTube, Vimeo y archivos .mp4/.webm/.ogg. Si la dirección no se
 * puede incrustar, se oculta la vista previa (la ficha pública mostrará un
 * enlace). Es solo una ayuda visual; la dirección se valida en el servidor.
 */
(function () {
  'use strict';

  function datosDe(texto) {
    var url;
    try {
      url = new URL(texto.trim());
    } catch (e) {
      return null;
    }
    if (url.protocol !== 'http:' && url.protocol !== 'https:') { return null; }

    var host = url.hostname.toLowerCase().replace(/^(www|m)\./, '');
    var id = null;

    if (host === 'youtu.be') {
      id = url.pathname.slice(1);
    } else if (host === 'youtube.com') {
      var m = url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]{11})/);
      id = m ? m[1] : url.searchParams.get('v');
    }
    if (id !== null && /^[A-Za-z0-9_-]{11}$/.test(id)) {
      return { tipo: 'iframe', src: 'https://www.youtube-nocookie.com/embed/' + id };
    }

    if (host === 'vimeo.com') {
      var v = url.pathname.match(/^\/(\d+)/);
      if (v) { return { tipo: 'iframe', src: 'https://player.vimeo.com/video/' + v[1] }; }
    }

    if (/\.(mp4|webm|ogg)$/i.test(url.pathname)) {
      return { tipo: 'video', src: url.href };
    }
    return null;
  }

  function preparar(entrada) {
    var caja = document.getElementById(entrada.getAttribute('data-vista-video'));
    if (!caja) { return; }

    var temporizador = null;

    function pintar() {
      var datos = datosDe(entrada.value);
      caja.textContent = '';
      if (datos === null) {
        caja.hidden = true;
        return;
      }

      var elemento;
      if (datos.tipo === 'iframe') {
        elemento = document.createElement('iframe');
        elemento.src = datos.src;
        elemento.title = 'Vista previa del video';
        elemento.setAttribute('allowfullscreen', '');
        elemento.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      } else {
        elemento = document.createElement('video');
        elemento.src = datos.src;
        elemento.controls = true;
        elemento.preload = 'metadata';
      }
      caja.appendChild(elemento);
      caja.hidden = false;
    }

    // Se espera un momento tras la última tecla para no recargar el video en cada letra.
    entrada.addEventListener('input', function () {
      window.clearTimeout(temporizador);
      temporizador = window.setTimeout(pintar, 400);
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll('input[data-vista-video]'), preparar);
})();
