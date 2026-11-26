<?php
/**
 * CAR · Ficha pública de una carrera
 * Muestra la VERSIÓN PUBLICADA. Si la ficha vuelve a borrador para modificarla,
 * esta versión sigue visible hasta que se publique la nueva.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

$carreraId = entero(get('id'));
if ($carreraId === null) {
    no_encontrado('Carrera no válida.');
}

// Si la carrera nunca se publicó, el público recibe 404:
// no se revela que existe un borrador.
$publicada = car_ficha_publica($carreraId);
if ($publicada === null) {
    no_encontrado('Esta carrera no tiene ficha publicada.');
}

$ficha          = $publicada['ficha'];
$materias       = $publicada['materias'];
$instalaciones  = $publicada['instalaciones'];
$documentos     = $publicada['documentos'];
$galeria        = $publicada['galeria'];

// Se agrupan las materias por cuatrimestre.
$porCuatri = [];
foreach ($materias as $m) {
    $porCuatri[(int)$m['cuatrimestre']][] = $m;
}
// Los cuatrimestres de estadía (prácticas) no llevan materias, pero aparecen en el plan.
$estadias = array_map('intval', $publicada['estadias'] ?? []);
foreach ($estadias as $q) {
    $porCuatri[$q] = $porCuatri[$q] ?? [];
}
ksort($porCuatri);

// El video se incrusta si es de YouTube, Vimeo o un archivo de video directo;
// si es de otro sitio se deja un enlace (solo si empieza con http:// o https://).
$video = (string)($ficha['video_url'] ?? '');
$videoValido = $video !== '' && preg_match('#^https?://#i', $video) === 1;
$videoEmbed = $videoValido ? car_video_embed($video) : null;

cabecera($ficha['nombre'], $ficha['area'], 'css/modulo.css');

$conFondo = !empty($ficha['imagen_fondo']);
$estiloHero = $conFondo
    ? " style=\"--portada-imagen:url('../uploads/" . e($ficha['imagen_fondo']) . "')\""
    : '';
?>

<section class="portada car-hero<?= $conFondo ? ' portada--imagen car-hero--foto' : '' ?>"<?= $estiloHero ?>>
<div class="contenedor">
    <h1><?= e($ficha['nombre']) ?></h1>
    <ul class="car-hero__datos">
    <li><?= e(CAR_AREAS[$ficha['area']] ?? $ficha['area']) ?></li>
    <li><?= e($ficha['modalidad']) ?></li>
    <li><?= e($ficha['duracion']) ?></li>
    </ul>
</div>
</section>

<section class="contenedor seccion car-ficha">

<?php pintar_mensajes(); ?>

<div class="car-intro car-intro--con-imagen">
    <div class="car-intro__lateral">
        <a class="boton boton--primario car-intro__volver" href="./">&larr; Todas las carreras</a>
    <?php if ($ficha['imagen_portada']): ?>
        <img class="car-intro__imagen" src="uploads/<?= e($ficha['imagen_portada']) ?>"
            alt="Portada de <?= e($ficha['nombre']) ?>">
    <?php endif; ?>
    </div>
    <div class="car-intro__texto">
    <h2>Resumen</h2>
    <p><?= nl2br(e($ficha['resumen'])) ?></p>
    <?php if ($videoValido && $videoEmbed === null): ?>
        <p><a class="boton boton--primario" href="<?= e($video) ?>" target="_blank" rel="noopener">Ver video de la carrera</a></p>
    <?php endif; ?>
    </div>
</div>

<?php if ($galeria): ?>
<div class="car-bloque">
    <h2 class="car-titulo-centrado">Conoce la carrera</h2>
    <div class="car-carrusel" data-carrusel aria-roledescription="carrusel"
        aria-label="Fotos de <?= e($ficha['nombre']) ?>">
    <div class="car-carrusel__pista">
        <?php foreach ($galeria as $g): ?>
        <figure class="car-carrusel__slide">
            <img src="uploads/<?= e($g['imagen_servidor']) ?>"
                alt="Foto <?= (int)$g['orden'] ?> de <?= e($ficha['nombre']) ?>" loading="lazy">
        </figure>
        <?php endforeach; ?>
    </div>
    <?php if (count($galeria) > 1): ?>
        <button class="car-carrusel__btn car-carrusel__btn--ant" type="button" aria-label="Foto anterior">&#8249;</button>
        <button class="car-carrusel__btn car-carrusel__btn--sig" type="button" aria-label="Foto siguiente">&#8250;</button>
    <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="car-dos-col">
    <div class="panel car-tarjeta-texto">
    <h2>Perfil de egreso</h2>
    <p><?= nl2br(e($ficha['perfil_egreso'])) ?></p>
    </div>
    <div class="panel car-tarjeta-texto">
    <h2>Campo laboral</h2>
    <p><?= nl2br(e($ficha['campo_laboral'])) ?></p>
    </div>
</div>

<div class="car-bloque">
<h2 class="car-titulo-centrado">Plan de estudios</h2>
<?php if (!$porCuatri): ?>
    <p>Las materias se publicarán próximamente.</p>
<?php else: ?>
    <div class="car-plan" data-plan>
        <button class="car-plan__flecha car-plan__flecha--prev" type="button" data-plan-prev
                aria-label="Cuatrimestres anteriores">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <div class="car-plan__pista" data-plan-pista tabindex="0" aria-label="Materias por cuatrimestre">
        <?php foreach ($porCuatri as $num => $lista): ?>
            <article class="car-plan__tarjeta">
            <h3 class="car-plan__titulo"><?= (int)$num ?>.º Cuatrimestre</h3>
            <?php if (in_array((int)$num, $estadias, true)): ?>
            <div class="car-plan__estadia">
                <span class="car-plan__estadia-etiqueta">Estadía</span>
                <p>Prácticas profesionales que se realizan durante este cuatrimestre.</p>
            </div>
            <?php else: ?>
            <ul class="car-plan__lista">
                <?php foreach ($lista as $m): ?>
                <li>
                    <span class="car-plan__check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>
                    </span>
                    <span><?= e($m['nombre']) ?>
                    <?php if ($m['horas_totales']): ?>
                        <span class="leyenda">· <?= (int)$m['horas_totales'] ?> h</span>
                    <?php endif; ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
        <button class="car-plan__flecha car-plan__flecha--next" type="button" data-plan-next
                aria-label="Cuatrimestres siguientes">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>
    <p class="car-plan__descarga">
        <a class="boton boton--primario" href="plan_pdf.php?id=<?= (int)$carreraId ?>">Descargar plan de estudios detallado (PDF)</a>
        <span class="leyenda">Incluye propósito, competencia y unidades de cada materia.</span>
    </p>
<?php endif; ?>
</div>

<div class="car-bloque">
<h2 class="car-titulo-centrado">Instalaciones</h2>
<?php if (!$instalaciones): ?>
    <p>Aún no hay instalaciones registradas.</p>
<?php else: ?>
    <div class="rejilla">
    <?php foreach ($instalaciones as $i): ?>
        <?php
          $esVideo  = !empty($i['video_servidor']);
          $archivo  = $esVideo ? $i['video_servidor'] : ($i['imagen_servidor'] ?? '');
          $conMedio = $archivo !== '' && $archivo !== null;
        ?>
        <div class="tarjeta<?= $conMedio ? ' car-instalacion' : '' ?>"
            <?php if ($conMedio): ?>
            data-instalacion data-tipo="<?= $esVideo ? 'video' : 'imagen' ?>"
            data-src="uploads/<?= e($archivo) ?>"
            data-titulo="<?= e($i['nombre']) ?>" data-descripcion="<?= e($i['descripcion']) ?>"
            role="button" tabindex="0" aria-haspopup="dialog"
            aria-label="Ampliar: <?= e($i['nombre']) ?>"
            <?php endif; ?>>
        <?php if ($esVideo): ?>
            <div class="car-instalacion__medio">
            <video class="tarjeta__medio" src="uploads/<?= e($i['video_servidor']) ?>#t=0.1"
                preload="metadata" muted playsinline tabindex="-1"></video>
            <span class="car-instalacion__play" aria-hidden="true">&#9654;</span>
            </div>
        <?php elseif ($i['imagen_servidor']): ?>
            <img class="tarjeta__medio" src="uploads/<?= e($i['imagen_servidor']) ?>"
                alt="<?= e($i['nombre']) ?>">
        <?php endif; ?>
        <div class="tarjeta__cuerpo">
            <h3 class="tarjeta__titulo"><?= e($i['nombre']) ?></h3>
            <p><?= e($i['descripcion']) ?></p>
        </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
</div>

<?php if ($videoEmbed !== null): ?>
<div class="car-bloque car-bloque--centrado">
    <h2>Video de la carrera</h2>
    <div class="car-video">
    <?php if ($videoEmbed['tipo'] === 'iframe'): ?>
        <iframe src="<?= e($videoEmbed['src']) ?>" title="Video de <?= e($ficha['nombre']) ?>"
                loading="lazy" allowfullscreen
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
                referrerpolicy="strict-origin-when-cross-origin"></iframe>
    <?php else: ?>
        <video src="<?= e($videoEmbed['src']) ?>" controls preload="metadata"
               title="Video de <?= e($ficha['nombre']) ?>"></video>
    <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</section>

<!-- ======================= Visita ======================= -->
<section class="contenedor seccion">
  <div class="panel">
    <div class="fila fila--separada">
      <div class="ancho-lectura">
        <h2 class="sin-margen">¿Prefieres verlo en persona?</h2>
        <p class="sin-margen">
          Las visitas guiadas recorren laboratorios y talleres con estudiantes de cada
          carrera. El cupo es limitado y el registro, gratuito.
        </p>
      </div>
      <a class="boton boton--primario" href="<?= RUTA_BASE ?>/modulos/visitas/">Agendar visita</a>
    </div>
  </div>
</section>

<dialog class="car-visor" id="visor-instalacion" aria-labelledby="visor-titulo">
    <button class="car-visor__cerrar" type="button" data-visor-cerrar aria-label="Cerrar">&times;</button>
    <div class="car-visor__medio" data-visor-medio></div>
    <div class="car-visor__texto">
    <h3 id="visor-titulo" data-visor-titulo></h3>
    <p data-visor-descripcion></p>
    </div>
</dialog>

<script src="js/carrusel.js"></script>
<script src="js/instalaciones.js"></script>
<script src="js/plan.js"></script>
<?php
pie();