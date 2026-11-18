/**
 * CAR · Formulario de materias: unidades
 *
 * - Muestra solo tantos bloques de unidad como indique el selector (1 a 5).
 *   Los bloques ocultos se deshabilitan para que no viajen al servidor.
 * - Calcula "Horas Totales" de cada unidad (saber + saber hacer).
 *
 * El servidor repite ambas cosas: esto solo mejora la experiencia.
 * Sin JavaScript se ven las 5 unidades y el servidor usa las primeras N.
 */
(function () {
  'use strict';

  var selector = document.getElementById('num_unidades');
  if (!selector) { return; }

  var bloques = Array.prototype.slice.call(document.querySelectorAll('[data-unidad]'));

  function mostrar() {
    var n = parseInt(selector.value, 10) || 0;
    bloques.forEach(function (bloque) {
      var visible = parseInt(bloque.getAttribute('data-unidad'), 10) <= n;
      bloque.hidden = !visible;
      // Un campo disabled no se envía; así el índice de los arreglos sigue
      // coincidiendo con el número de unidad solo en las visibles.
      Array.prototype.forEach.call(bloque.querySelectorAll('input'), function (campo) {
        campo.disabled = !visible;
      });
    });
  }

  function sumar(bloque) {
    var saber = bloque.querySelector('[data-suma="saber"]');
    var hacer = bloque.querySelector('[data-suma="hacer"]');
    var total = bloque.querySelector('[data-suma="total"]');
    if (!saber || !hacer || !total) { return; }
    if (saber.value === '' && hacer.value === '') { total.value = ''; return; }
    total.value = (parseInt(saber.value, 10) || 0) + (parseInt(hacer.value, 10) || 0);
  }

  bloques.forEach(function (bloque) {
    bloque.addEventListener('input', function () { sumar(bloque); });
  });
  selector.addEventListener('change', mostrar);

  mostrar();
})();
