<?php
/**
 * CAR · Documentos de la ficha (PDF del plan de estudios)
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
$ficha    = car_ficha_autorizada($fichaId);
$editable = $ficha['estado'] === 'borrador';

$titulo = 'Plan de Estudios';
$restringido = false;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificar_token();

    if (!$editable) {
        mensaje('error', 'La ficha no está en borrador; no se pueden subir documentos.');
        redirigir('documentos.php?ficha=' . $fichaId);
    }

    $titulo      = post('titulo');
    $restringido = post('restringido') === '1';

    if ($titulo === '') {
        $errores['titulo'] = 'El título es obligatorio.';
    } elseif (mb_strlen($titulo) > 150) {
        $errores['titulo'] = 'Máximo 150 caracteres.';
    }

    if (!$errores) {
        try {
            // Tipo real (finfo), tamaño y nombre generado: lo hace guardar_archivo().
            $guardado = guardar_archivo($_FILES['archivo'] ?? [], car_carpeta_docs(), SOLO_DOCUMENTO, 10);

            // El nombre original solo se usa para mostrarlo al descargar.
            $original = preg_replace('/[^\w.\- ]+/u', '_', basename((string)$_FILES['archivo']['name']));
            $original = mb_substr((string)$original, 0, 200);

            car_documento_crear($fichaId, [
                'titulo'           => $titulo,
                'archivo_servidor' => $guardado,
                'nombre_original'  => $original,
                'mime_type'        => 'application/pdf',
                'tamano_bytes'     => (int)filesize(car_carpeta_docs() . '/' . $guardado),
                'restringido'      => $restringido ? 1 : 0,
            ]);

            mensaje('exito', 'Documento subido.');
            redirigir('documentos.php?ficha=' . $fichaId);   // POST-Redirect-GET
        } catch (RuntimeException $e) {
            $errores['archivo'] = $e->getMessage();
        }
    }
}

$documentos = car_documentos_de_ficha($fichaId);

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

<?php if ($editable): ?>
<form class="formulario panel mb-6" method="POST" action="" enctype="multipart/form-data">
    <?= campo_token() ?>
    <h2>Subir documento</h2>

    <div class="campo <?= isset($errores['titulo']) ? 'campo--invalido' : '' ?>">
      <label class="campo__etiqueta" for="titulo">Título *</label>
    <input class="campo__control" type="text" id="titulo" name="titulo"
            value="<?= e($titulo) ?>" maxlength="150" required>
    <?php if (isset($errores['titulo'])): ?><small class="campo__error"><?= e($errores['titulo']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['archivo']) ? 'campo--invalido' : '' ?>">
      <label class="campo__etiqueta" for="archivo">Archivo PDF *</label>
    <input class="campo__control" type="file" id="archivo" name="archivo" accept="application/pdf" required>
    <small class="campo__ayuda">Solo PDF. Máximo 10 MB. El servidor comprueba el tipo real.</small>
    <?php if (isset($errores['archivo'])): ?><small class="campo__error"><?= e($errores['archivo']) ?></small><?php endif; ?>
    </div>

    <div class="campo">
    <label>
        <input type="checkbox" name="restringido" value="1" <?= $restringido ? 'checked' : '' ?>>
        Restringido (solo usuarios con sesión pueden descargarlo)
    </label>
    </div>

    <button class="boton boton--primario" type="submit">Subir</button>
</form>
<?php else: ?>
    <div class="alerta alerta--info"><p>La ficha no está en borrador: solo puedes consultar los documentos.</p></div>
<?php endif; ?>

<?php if (!$documentos): ?>
    <div class="vacio"><h3>Sin documentos</h3><p>Sube el PDF del plan de estudios.</p></div>
<?php else: ?>
    <div class="tabla-envoltura">
    <table class="tabla">
        <caption><?= count($documentos) ?> documento<?= count($documentos) === 1 ? '' : 's' ?></caption>
        <thead>
        <tr><th scope="col">Título</th><th scope="col">Archivo</th><th scope="col">Acceso</th>
            <th scope="col">Descargas</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($documentos as $d): ?>
        <tr>
            <td><?= e($d['titulo']) ?></td>
            <td><?= e($d['nombre_original']) ?> <span class="leyenda">(<?= e(number_format($d['tamano_bytes'] / 1024, 0)) ?> KB)</span></td>
            <td><?= (int)$d['restringido'] === 1 ? 'Con sesión' : 'Público' ?></td>
            <td><?= (int)$d['descargas'] ?></td>
            <td>
            <div class="tabla__acciones">
                <a class="boton boton--secundario boton--pequeno" href="../descargar.php?id=<?= (int)$d['id'] ?>">Descargar</a>
                <?php if ($editable): ?>
                <form method="POST" action="documento_eliminar.php"
                        data-confirmar="¿Eliminar este documento? No se puede deshacer."
                        data-confirmar-boton="Eliminar" data-confirmar-tipo="peligro">
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <button class="boton boton--peligro boton--pequeno" type="submit">Eliminar</button>
                </form>
                <?php endif; ?>
            </div>
            </td>
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