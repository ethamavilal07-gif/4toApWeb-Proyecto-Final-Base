<?php
/**
 * CAR · Página amable cuando una carrera no tiene ficha publicada (o no existe).
 * Responde 404 (no se revela si hay un borrador), pero con el diseño del sitio
 * y con salidas: ver todas las carreras o conocer las que ya tienen ficha.
 */

declare(strict_types=1);

/**
 * Pinta la página completa y termina.
 * $carrera: datos de la carrera si existe; null si la dirección no corresponde a ninguna.
 */
function car_pagina_sin_ficha(?array $carrera): never
{
    http_response_code(404);

    // Hasta tres carreras que SÍ tienen ficha publicada, para no dejar al visitante sin nada que ver.
    $sugeridas = [];
    foreach (car_listar_publicas()['filas'] as $f) {
        if ($f['ficha_id'] !== null && (!$carrera || (int)$f['id'] !== (int)$carrera['id'])) {
            $sugeridas[] = $f;
        }
        if (count($sugeridas) === 3) {
            break;
        }
    }

    $titulo = $carrera ? $carrera['nombre'] : 'Carrera no encontrada';
    cabecera($titulo, $carrera['area'] ?? '', 'css/modulo.css');
    ?>

<section class="portada car-hero">
  <div class="contenedor">
    <p class="car-hero__volver"><a href="./">&larr; Todas las carreras</a></p>
    <h1><?= e($titulo) ?></h1>
    <?php if ($carrera): ?>
    <ul class="car-hero__datos">
      <li><?= e(CAR_AREAS[$carrera['area']] ?? $carrera['area']) ?></li>
      <li><?= e($carrera['modalidad']) ?></li>
      <li><?= e($carrera['duracion']) ?></li>
    </ul>
    <?php endif; ?>
  </div>
</section>

<section class="contenedor seccion">
  <div class="car-sinficha">
    <div class="car-sinficha__icono" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="44" height="44" fill="none" stroke="currentColor"
           stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17v3z"/>
        <path d="m14.5 7.5 3 3"/>
      </svg>
    </div>
    <?php if ($carrera): ?>
      <h2>Estamos preparando esta ficha</h2>
      <p>
        El equipo de <strong><?= e($carrera['nombre']) ?></strong> está terminando de armar su
        plan de estudios, perfil de egreso e instalaciones. Muy pronto podrás conocerla completa.
      </p>
    <?php else: ?>
      <h2>No encontramos esa carrera</h2>
      <p>La dirección no corresponde a ninguna carrera de la universidad. Revisa el enlace o elige una del listado.</p>
    <?php endif; ?>
    <div class="car-sinficha__acciones">
      <a class="boton boton--primario" href="./">Ver todas las carreras</a>
      <a class="boton boton--secundario" href="<?= RUTA_BASE ?>/">Ir al inicio</a>
    </div>
  </div>

  <?php if ($sugeridas): ?>
  <h2 class="car-titulo-centrado">Mientras tanto, conoce estas carreras</h2>
  <div class="rejilla">
    <?php foreach ($sugeridas as $s): ?>
      <a class="tarjeta tarjeta--area <?= e(clase_area($s['area'])) ?>"
         href="ficha.php?id=<?= (int)$s['id'] ?>">
        <div class="tarjeta__cuerpo">
          <h3 class="tarjeta__titulo"><?= e($s['nombre']) ?></h3>
          <p class="leyenda"><?= e($s['duracion']) ?> · <?= e($s['modalidad']) ?></p>
        </div>
        <div class="tarjeta__pie">
          <span class="insignia insignia--info"><?= e($s['clave']) ?></span>
          <span>Ver ficha</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php
    pie();
    exit;
}
