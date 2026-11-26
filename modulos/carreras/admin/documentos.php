<?php
/**
 * CAR · Plan de estudios en PDF
 * El PDF se genera solo con las materias capturadas; aquí no se suben documentos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

$fichaId = entero(get('ficha'));
if ($fichaId === null) {
    no_encontrado('Ficha no válida.');
}
$ficha = car_ficha_autorizada($fichaId);

cabecera('Documentos', $ficha['area'], '../css/modulo.css');
?>

<section class="contenedor seccion">

<nav aria-label="Ruta">
    <ol class="migas">
    <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
    <li><a href="<?= e(car_url_inicio()) ?>">Carreras</a></li>
    <li>Documentos</li>
    </ol>
</nav>

<h1>Documentos de <?= e($ficha['nombre']) ?></h1>

<?php pintar_mensajes(); ?>
<?php car_menu_ficha($ficha, 'documentos'); ?>

<div class="panel mb-6">
    <h2>Plan de estudios en PDF (automático)</h2>
    <p>El sistema arma un PDF con la información que capturaste en <a href="materias.php?ficha=<?= (int)$fichaId ?>">Materias</a>:
        cuatrimestre, clave, horas, propósito, competencia y unidades de cada una.
        En la ficha pública solo se muestran los nombres; este archivo es el detalle para quien lo descargue.</p>
    <p>Al publicar, la ficha ofrece este PDF con las materias de la versión publicada, sin que tengas que subir nada.</p>
    <a class="boton boton--primario" href="plan_pdf.php?ficha=<?= (int)$fichaId ?>">Generar y descargar PDF del plan de estudios</a>
    <p class="leyenda">Lo que descargas aquí es el borrador actual.</p>
</div>

</section>
<?php
pie();
