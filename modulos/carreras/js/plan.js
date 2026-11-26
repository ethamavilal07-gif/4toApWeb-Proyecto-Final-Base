/* Plan de estudios: carrusel de cuatrimestres con flechas (y deslizar con el dedo). */
(function () {
    'use strict';
    document.querySelectorAll('[data-plan]').forEach(function (plan) {
        var pista = plan.querySelector('[data-plan-pista]');
        var prev = plan.querySelector('[data-plan-prev]');
        var next = plan.querySelector('[data-plan-next]');
        if (!pista || !prev || !next) { return; }

        function paso() {
            var t = pista.querySelector('.car-plan__tarjeta');
            if (!t) { return pista.clientWidth; }
            var gap = parseFloat(getComputedStyle(pista).columnGap) || 0;
            return t.offsetWidth + gap;
        }
        function actualizar() {
            var max = pista.scrollWidth - pista.clientWidth - 2;
            prev.disabled = pista.scrollLeft <= 2;
            next.disabled = pista.scrollLeft >= max;
            plan.classList.toggle('car-plan--sin-desborde', max <= 0);
        }
        prev.addEventListener('click', function () { pista.scrollBy({ left: -paso(), behavior: 'smooth' }); });
        next.addEventListener('click', function () { pista.scrollBy({ left: paso(), behavior: 'smooth' }); });
        pista.addEventListener('scroll', actualizar, { passive: true });
        window.addEventListener('resize', actualizar);
        actualizar();
    });
})();
