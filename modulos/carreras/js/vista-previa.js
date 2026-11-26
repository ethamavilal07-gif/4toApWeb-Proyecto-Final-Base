/**
 * CAR · Vista previa de imágenes antes de guardar
 *
 * Uso: <input type="file" data-vista="id-de-la-img"> y, en cualquier parte,
 *      <img id="id-de-la-img" hidden>.
 * Al elegir un archivo se muestra en esa <img>. Si se quita la selección,
 * vuelve a la imagen que había guardada (o se oculta si no había ninguna).
 * Con data-vista-video="id-de-un-video" también se previsualizan videos MP4.
 * Es solo una ayuda visual: el tipo y el tamaño los valida el servidor.
 */
(function () {
  'use strict';

  var TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
  var TIPOS_VIDEO = ['video/mp4'];

  function preparar(entrada) {
    var img = document.getElementById(entrada.getAttribute('data-vista'));
    if (!img) { return; }

    var vid = document.getElementById(entrada.getAttribute('data-vista-video') || '');
    var vacio = document.getElementById(entrada.getAttribute('data-vista-vacio') || '');
    var limpiar = document.getElementById(entrada.getAttribute('data-vista-limpiar') || '');
    var original = img.getAttribute('src') || '';
    var originalVideo = vid ? (vid.getAttribute('src') || '') : '';
    var temporal = null;

    function ver(srcImagen, srcVideo) {
      if (srcImagen) { img.src = srcImagen; img.hidden = false; }
      else { img.removeAttribute('src'); img.hidden = true; }
      if (vid) {
        if (srcVideo) { vid.src = srcVideo; vid.hidden = false; }
        else { vid.removeAttribute('src'); vid.hidden = true; if (vid.load) { vid.load(); } }
      }
      if (vacio) { vacio.hidden = !!(srcImagen || srcVideo); }
    }

    entrada.addEventListener('change', function () {
      if (temporal) {
        URL.revokeObjectURL(temporal);
        temporal = null;
      }

      var archivo = entrada.files && entrada.files[0];
      if (archivo && TIPOS.indexOf(archivo.type) !== -1) {
        temporal = URL.createObjectURL(archivo);
        ver(temporal, '');
        if (limpiar) { limpiar.hidden = false; }
      } else if (archivo && vid && TIPOS_VIDEO.indexOf(archivo.type) !== -1) {
        temporal = URL.createObjectURL(archivo);
        ver('', temporal);
        if (limpiar) { limpiar.hidden = false; }
      } else {
        ver(original, originalVideo);
        if (limpiar) { limpiar.hidden = true; }
      }
    });

    if (limpiar) {
      limpiar.addEventListener('click', function () {
        entrada.value = '';
        entrada.dispatchEvent(new Event('change'));
      });
    }
  }

  Array.prototype.forEach.call(document.querySelectorAll('input[data-vista]'), preparar);
})();
