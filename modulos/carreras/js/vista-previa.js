/**
 * CAR · Vista previa de imágenes antes de guardar
 *
 * Uso: <input type="file" data-vista="id-de-la-img"> y, en cualquier parte,
 *      <img id="id-de-la-img" hidden>.
 * Al elegir un archivo se muestra en esa <img>. Si se quita la selección,
 * vuelve a la imagen que había guardada (o se oculta si no había ninguna).
 * Es solo una ayuda visual: el tipo y el tamaño los valida el servidor.
 */
(function () {
  'use strict';

  var TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

  function preparar(entrada) {
    var img = document.getElementById(entrada.getAttribute('data-vista'));
    if (!img) { return; }

    var vacio = document.getElementById(entrada.getAttribute('data-vista-vacio') || '');
    var limpiar = document.getElementById(entrada.getAttribute('data-vista-limpiar') || '');
    var original = img.getAttribute('src') || '';
    var temporal = null;

    entrada.addEventListener('change', function () {
      if (temporal) {
        URL.revokeObjectURL(temporal);
        temporal = null;
      }

      var archivo = entrada.files && entrada.files[0];
      if (archivo && TIPOS.indexOf(archivo.type) !== -1) {
        temporal = URL.createObjectURL(archivo);
        img.src = temporal;
        img.hidden = false;
        if (vacio) { vacio.hidden = true; }
        if (limpiar) { limpiar.hidden = false; }
      } else if (original !== '') {
        img.src = original;
        img.hidden = false;
        if (limpiar) { limpiar.hidden = true; }
      } else {
        img.removeAttribute('src');
        img.hidden = true;
        if (vacio) { vacio.hidden = false; }
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
