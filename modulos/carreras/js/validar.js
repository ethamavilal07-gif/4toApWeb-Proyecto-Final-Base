/**
 * CAR · Mensajes de validación dentro de la página
 *
 * Sustituye la burbuja nativa del navegador ("Completa este campo") por un
 * mensaje con el estilo del sistema de diseño (.campo__error / .campo--invalido).
 * La validación real sigue haciéndose en el servidor; esto solo mejora el aviso.
 */
(function () {
  'use strict';

  function mensajeDe(campo) {
    var v = campo.validity;
    if (v.valueMissing) {
      if (campo.type === 'file') { return 'Selecciona un archivo.'; }
      if (campo.type === 'checkbox') { return 'Debes marcar esta casilla.'; }
      return 'Este campo es obligatorio.';
    }
    if (v.typeMismatch)    { return 'El formato no es válido.'; }
    if (v.tooShort)        { return 'Es demasiado corto (mínimo ' + campo.minLength + ' caracteres).'; }
    if (v.tooLong)         { return 'Máximo ' + campo.maxLength + ' caracteres.'; }
    if (v.rangeUnderflow)  { return 'El valor mínimo es ' + campo.min + '.'; }
    if (v.rangeOverflow)   { return 'El valor máximo es ' + campo.max + '.'; }
    if (v.stepMismatch || v.badInput) { return 'Escribe un número válido.'; }
    if (v.patternMismatch) { return 'El formato no es válido.'; }
    return 'Revisa este campo.';
  }

  function limpiar(campo) {
    var caja = campo.closest('.campo');
    if (!caja) { return; }
    caja.classList.remove('campo--invalido');
    var previo = caja.querySelector('.campo__error[data-js]');
    if (previo) { previo.remove(); }
    campo.removeAttribute('aria-invalid');
  }

  // 'invalid' no burbujea: se escucha en la fase de captura.
  document.addEventListener('invalid', function (ev) {
    var campo = ev.target;
    var caja = campo.closest ? campo.closest('.campo') : null;
    if (!caja) { return; }

    ev.preventDefault();   // evita la burbuja nativa

    limpiar(campo);
    caja.classList.add('campo--invalido');
    campo.setAttribute('aria-invalid', 'true');

    var aviso = document.createElement('small');
    aviso.className = 'campo__error';
    aviso.setAttribute('data-js', '1');
    aviso.setAttribute('role', 'alert');
    aviso.textContent = mensajeDe(campo);
    caja.appendChild(aviso);

    // Solo el primer campo inválido recibe el foco.
    var formulario = campo.form;
    if (formulario && formulario.querySelector(':invalid') === campo) {
      campo.focus();
    }
  }, true);

  function alEscribir(ev) {
    if (ev.target.matches && ev.target.matches('.campo--invalido .campo__control, .campo--invalido input')) {
      if (ev.target.checkValidity()) { limpiar(ev.target); }
    }
  }
  document.addEventListener('input', alEscribir);
  document.addEventListener('change', alEscribir);
})();
