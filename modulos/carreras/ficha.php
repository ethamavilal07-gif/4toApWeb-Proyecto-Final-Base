<?php
/**
 * CAR · Ficha pública de una carrera
 * Solo se muestra si la ficha está PUBLICADA.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

$carreraId = entero(get('id'));
if ($carreraId === null) {
    no_encontrado('Carrera no válida.');
}

// Si la ficha no existe o no está publicada, el público recibe 404:
// no se revela que existe un borrador.
$ficha = car_ficha_publica($carreraId);
if ($ficha === null) {
    no_encontrado('Esta carrera no tiene ficha publicada.');
}

$materias       = car_materias_de_ficha((int)$ficha['id']);
$instalaciones  = car_instalaciones_de_ficha((int)$ficha['id']);
$documentos     = car_documentos_de_ficha((int)$ficha['id']);
$galeria        = car_galeria_de_ficha((int)$ficha['id']);

// Se agrupan las materias por cuatrimestre.
$porCuatri = [];
foreach ($materias as $m) {
    $porCuatri[(int)$m['cuatrimestre']][] = $m;
}

// El video se enlaza solo si la dirección empieza con http:// o https://
$video = (string)($ficha['video_url'] ?? '');
$videoValido = $video !== '' && preg_match('#^https?://#i', $video) === 1;

cabecera($ficha['nombre'], $ficha['area'], 'css/modulo.css');

$conFondo = !empty($ficha['imagen_fondo']);
$estiloHero = $conFondo
    ? " style=\"--portada-imagen:url('uploads/" . e($ficha['imagen_fondo']) . "')\""
    : '';
?>

<section class="portada car-hero<?= $conFondo ? ' portada--imagen car-hero--foto' : '' ?>"<?= $estiloHero ?>>
<div class="contenedor">
    <p class="car-hero__volver"><a href="./">&larr; Todas las carreras</a></p>
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

<div class="car-intro<?= $ficha['imagen_portada'] ? ' car-intro--con-imagen' : '' ?>">
    <?php if ($ficha['imagen_portada']): ?>
    <img class="car-intro__imagen" src="uploads/<?= e($ficha['imagen_portada']) ?>"
        alt="Portada de <?= e($ficha['nombre']) ?>">
    <?php endif; ?>
    <div class="car-intro__texto">
    <h2>Resumen</h2>
    <p><?= nl2br(e($ficha['resumen'])) ?></p>
    <?php if ($videoValido): ?>
        <p><a class="boton boton--primario" href="<?= e($video) ?>" target="_blank" rel="noopener">Ver video de la carrera</a></p>
    <?php endif; ?>
    </div>
</div>

<?php if ($galeria): ?>
<div class="car-bloque">
    <h2>Conoce la carrera</h2>
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
<h2>Plan de estudios</h2>
<?php if (!$porCuatri): ?>
    <p>Las materias se publicarán próximamente.</p>
<?php else: ?>
    <div class="rejilla">
    <?php foreach ($porCuatri as $num => $lista): ?>
        <div class="panel">
        <h3><?= (int)$num ?>.º periodo</h3>
        <ul>
            <?php foreach ($lista as $m): ?>
            <li>
                <?= e($m['nombre']) ?>
                <?php if ($m['horas_totales']): ?>
                <span class="leyenda">· <?= (int)$m['horas_totales'] ?> h</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

</div>

<?php if ($documentos): ?>
<div class="car-bloque">
    <h2>Descargas</h2>
    <ul>
    <?php foreach ($documentos as $d): ?>
        <li>
        <a href="descargar.php?id=<?= (int)$d['id'] ?>"><?= e($d['titulo']) ?></a>
        <span class="leyenda">· PDF · <?= (int)$d['descargas'] ?> descargas</span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="car-bloque">
<h2>Instalaciones</h2>
<?php if (!$instalaciones): ?>
    <p>Aún no hay instalaciones registradas.</p>
<?php else: ?>
    <div class="rejilla">
    <?php foreach ($instalaciones as $i): ?>
        <div class="tarjeta">
        <?php if ($i['imagen_servidor']): ?>
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

</section>

<script src="js/carrusel.js"></script>
<?php
pie();