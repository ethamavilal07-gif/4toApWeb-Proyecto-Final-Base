<?php
/**
 * CAR · Revisión de una ficha (solo coordinación)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../nucleo/comun.php';

requerir_rol('coordinacion');

require_once __DIR__ . '/../includes/consultas.php';
require_once __DIR__ . '/../includes/panel.php';

$fichaId = entero(get('ficha'));
if ($fichaId === null) {
    no_encontrado('Ficha no válida.');
}
$ficha = car_ficha_autorizada($fichaId);

$materias      = car_materias_de_ficha($fichaId);
$instalaciones = car_instalaciones_de_ficha($fichaId);
$documentos    = car_documentos_de_ficha($fichaId);
$historial     = car_historial($fichaId);

cabecera('Revisar ficha', $ficha['area'], '../css/modulo.css');
?>

<section class="contenedor seccion">

<nav aria-label="Ruta">
    <ol class="migas">
    <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
    <li><a href="bandeja.php">Bandeja de revisión</a></li>
    <li><?= e($ficha['nombre']) ?></li>
    </ol>
</nav>


<div class="fila fila--separada">
    <h1 class="sin-margen"><?= e($ficha['nombre']) ?></h1>
    <span class="<?= e(car_clase_estado($ficha['estado'])) ?>"><?= e(CAR_ESTADOS[$ficha['estado']]) ?></span>
</div>

<?php pintar_mensajes(); ?>

<?php $publicadaEn = car_publicada_en($fichaId); ?>
<?php if ($publicadaEn !== null && $ficha['estado'] !== 'publicado'): ?>
    <div class="alerta alerta--info" role="status">
    <p><span class="alerta__titulo">El público sigue viendo la versión publicada</span>
        del <?= e(date('d/m/Y H:i', strtotime($publicadaEn))) ?>.
        Lo que ves aquí no es visible hasta que se publique.
        <a href="../ficha.php?id=<?= (int)$ficha['carrera_id'] ?>">Ver la versión publicada</a></p>
    </div>
<?php endif; ?>

<div class="panel mb-6">
    <h2>Contenido</h2>
    <h3>Resumen</h3>
    <p><?= nl2br(e($ficha['resumen'])) ?></p>
    <h3>Perfil de egreso</h3>
    <p><?= nl2br(e($ficha['perfil_egreso'])) ?></p>
    <h3>Campo laboral</h3>
    <p><?= nl2br(e($ficha['campo_laboral'])) ?></p>

    <p class="leyenda">
    <?= count($materias) ?> materia<?= count($materias) === 1 ? '' : 's' ?> ·
    <?= count($instalaciones) ?> instalación<?= count($instalaciones) === 1 ? '' : 'es' ?> ·
    <?= count($documentos) ?> documento<?= count($documentos) === 1 ? '' : 's' ?> ·
    Portada: <?= $ficha['imagen_portada'] ? 'sí' : 'no' ?>
    </p>

    <?php if ($documentos): ?>
        <ul>
        <?php foreach ($documentos as $d): ?>
            <li><a href="../descargar.php?id=<?= (int)$d['id'] ?>"><?= e($d['titulo']) ?></a></li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    </div>

    <?php if ($ficha['estado'] === 'revision'): ?>
    <div class="panel mb-6">
        <h2>Dictamen</h2>

        <form method="POST" action="cambiar_estado.php" class="mb-6"
            data-confirmar="¿Publicar esta ficha? Reemplazará a la versión publicada anteriormente, si la hay."
            data-confirmar-boton="Publicar">
        <?= campo_token() ?>
        <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
        <input type="hidden" name="nuevo" value="publicado">
        <button class="boton boton--primario" type="submit">Publicar</button>
        </form>

        <form method="POST" action="cambiar_estado.php">
        <?= campo_token() ?>
        <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
        <input type="hidden" name="nuevo" value="borrador">
        <div class="campo">
          <label class="campo__etiqueta" for="observaciones">Observaciones para devolver a borrador *</label>
        <textarea class="campo__control" id="observaciones" name="observaciones" rows="3"
                    maxlength="1000" required></textarea>
        </div>
        <button class="boton boton--peligro" type="submit">Devolver a borrador</button>
        </form>
    </div>

<?php elseif ($ficha['estado'] === 'publicado'): ?>
    <div class="panel mb-6">
        <h2>Pedir cambios</h2>
        <p class="leyenda">La ficha vuelve a borrador para que el editor la modifique. El público
        <strong>sigue viendo la versión publicada</strong> hasta que publiques la nueva.</p>
        <form method="POST" action="cambiar_estado.php"
            data-confirmar="La ficha vuelve a borrador para modificarla. El público seguirá viendo la versión publicada hasta que se publique la nueva. ¿Continuar?"
            data-confirmar-boton="Devolver a borrador">
        <?= campo_token() ?>
        <input type="hidden" name="ficha" value="<?= (int)$ficha['id'] ?>">
        <input type="hidden" name="nuevo" value="borrador">
        <div class="campo">
          <label class="campo__etiqueta" for="observaciones">Qué debe cambiar *</label>
            <textarea class="campo__control" id="observaciones" name="observaciones" rows="2"
                    maxlength="1000" required></textarea>
        </div>
        <button class="boton boton--peligro" type="submit">Devolver a borrador</button>
        </form>
    </div>
<?php else: ?>
    <div class="alerta alerta--info"><p>La ficha está en borrador: el editor aún no la envía a revisión.</p></div>
    <?php endif; ?>

    <h2>Historial</h2>
    <?php if (!$historial): ?>
    <p class="leyenda">Sin cambios de estado registrados.</p>
    <?php else: ?>
    <div class="tabla-envoltura">
        <table class="tabla">
        <thead>
            <tr><th scope="col">Fecha</th><th scope="col">Cambio</th><th scope="col">Quién</th><th scope="col">Observaciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($historial as $h): ?>
            <tr>
            <td><?= e(date('d/m/Y H:i', strtotime($h['creado_en']))) ?></td>
            <td><?= e(CAR_ESTADOS[$h['estado_anterior']]) ?> → <?= e(CAR_ESTADOS[$h['estado_nuevo']]) ?></td>
            <td><?= e($h['usuario'] ?? 'Sistema') ?></td>
            <td><?= e($h['observaciones'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    </div>
    <?php endif; ?>

</section>
<script src="../js/confirmar.js"></script>
<?php
pie();
