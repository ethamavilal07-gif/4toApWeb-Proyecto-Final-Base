/* Materias: si el cuatrimestre es de estadía no se capturan materias. */
(function () {
    'use strict';
    var casilla = document.getElementById('es_estadia');
    var campos = document.querySelector('[data-materia-campos]');
    var boton = document.getElementById('boton-guardar');
    var selector = document.getElementById('num_unidades');
    if (!casilla || !campos) { return; }

    function aplicar() {
        var estadia = casilla.checked;
        campos.hidden = estadia;
        // Un campo deshabilitado no se valida ni se envía.
        Array.prototype.forEach.call(campos.querySelectorAll('input, select, textarea'), function (c) {
            c.disabled = estadia;
        });
        if (!estadia && selector) {
            // Se vuelve a calcular cuántas unidades se muestran.
            selector.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (boton) {
            boton.textContent = estadia ? 'Marcar como estadía' : (boton.getAttribute('data-texto') || 'Agregar');
        }
    }

    casilla.addEventListener('change', aplicar);
    aplicar();
})();
