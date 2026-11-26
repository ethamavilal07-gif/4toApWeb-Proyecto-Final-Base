/* Listado de carreras: los filtros se aplican solos (sin pulsar "Aplicar"). */
(function () {
    'use strict';
    var form = document.querySelector('[data-filtros]');
    if (!form) { return; }

    function enviar() {
        // Siempre se vuelve a la primera página de resultados.
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    }

    form.querySelectorAll('select').forEach(function (s) {
        s.addEventListener('change', enviar);
    });

    var buscar = form.querySelector('input[type="search"]');
    if (buscar) {
        var espera = null;
        buscar.addEventListener('input', function () {
            clearTimeout(espera);
            try { sessionStorage.setItem('car-buscar-foco', '1'); } catch (e) { /* sin almacenamiento */ }
            espera = setTimeout(enviar, 450);
        });
        // Tras recargar, el cursor vuelve al final del cuadro de búsqueda.
        try {
            if (sessionStorage.getItem('car-buscar-foco') === '1') {
                sessionStorage.removeItem('car-buscar-foco');
                buscar.focus();
                var n = buscar.value.length;
                buscar.setSelectionRange(n, n);
            }
        } catch (e) { /* sin almacenamiento */ }
    }
})();
