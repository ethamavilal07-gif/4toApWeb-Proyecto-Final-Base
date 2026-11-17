/**
 * CAR · Confirmaciones dentro de la página
 *
 * Uso: en un <form> se agrega
 *   data-confirmar="Texto de la pregunta"
 *   data-confirmar-boton="Eliminar"        (opcional, texto del botón)
 *   data-confirmar-tipo="peligro"          (opcional, botón rojo)
 * El formulario solo se envía si la persona pulsa el botón de confirmar.
 */
(function () {
  'use strict';

  var dialogo = null;
  var texto = null;
  var botonOk = null;
  var pendiente = null;

  function crear() {
    dialogo = document.createElement('dialog');
    dialogo.className = 'car-dialogo';
    dialogo.setAttribute('aria-labelledby', 'car-dialogo-titulo');
    dialogo.innerHTML =
      '<form method="dialog" class="car-dialogo__caja">' +
      '<h2 id="car-dialogo-titulo" class="car-dialogo__titulo">Confirma la acción</h2>' +
      '<p class="car-dialogo__texto"></p>' +
      '<div class="car-dialogo__acciones">' +
      '<button type="submit" value="cancelar" class="boton boton--secundario">Cancelar</button>' +
      '<button type="submit" value="confirmar" class="boton boton--primario car-dialogo__ok">Confirmar</button>' +
      '</div></form>';
    document.body.appendChild(dialogo);

    texto = dialogo.querySelector('.car-dialogo__texto');
    botonOk = dialogo.querySelector('.car-dialogo__ok');

    // Al cerrarse: si se confirmó, se envía el formulario que estaba pendiente.
    dialogo.addEventListener('close', function () {
      var formulario = pendiente;
      pendiente = null;
      if (formulario && dialogo.returnValue === 'confirmar') {
        formulario.submit();
      }
    });

    // Un clic en el fondo oscurecido equivale a cancelar.
    dialogo.addEventListener('click', function (ev) {
      if (ev.target === dialogo) {
        dialogo.close('cancelar');
      }
    });
  }

  function abrir(formulario) {
    if (dialogo === null) {
      crear();
    }
    pendiente = formulario;
    texto.textContent = formulario.getAttribute('data-confirmar');
    botonOk.textContent = formulario.getAttribute('data-confirmar-boton') || 'Confirmar';
    botonOk.className = 'boton car-dialogo__ok ' +
      (formulario.getAttribute('data-confirmar-tipo') === 'peligro' ? 'boton--peligro' : 'boton--primario');

    dialogo.returnValue = '';   // Esc no cambia returnValue: se reinicia en cada apertura
    dialogo.showModal();
  }

  document.addEventListener('submit', function (ev) {
    var formulario = ev.target;
    if (!formulario.matches || !formulario.matches('form[data-confirmar]')) {
      return;
    }

    // Navegadores sin <dialog>: se usa la confirmación básica como último recurso.
    if (typeof HTMLDialogElement !== 'function') {
      if (!window.confirm(formulario.getAttribute('data-confirmar'))) {
        ev.preventDefault();
      }
      return;
    }

    ev.preventDefault();
    abrir(formulario);
  });
})();