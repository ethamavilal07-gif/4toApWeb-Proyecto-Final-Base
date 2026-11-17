<?php
/**
 * CAR · Materias de una ficha (alta, consulta, modificación)
 */

declare(strict_types=1);

require_once _DIR_ . '/../../../nucleo/comun.php';

requerir_rol('editor', 'coordinacion');

require_once _DIR_ . '/../includes/consultas.php';
require_once _DIR_ . '/../includes/panel.php';

$fichaId = entero(get('ficha'));
if ($fichaId === null) {
    no_encontrado('Ficha no válida.');
}
$ficha    = car_ficha_autorizada($fichaId);
$editable = $ficha['estado'] === 'borrador';
$volver   = 'materias.php?ficha=' . $fichaId;

// ¿Se está editando una materia? Debe pertenecer a ESTA ficha.
$editando = null;
$editarId = entero(get('editar'));
if ($editarId !== null) {
    $editando = car_materia_obtener($editarId);
    if ($editando === null || (int)$editando['ficha_id'] !== $fichaId) {
        no_encontrado('La materia no existe.');
    }
}

$datos = [
    'cuatrimestre'  => (string)($editando['cuatrimestre']  ?? ''),
    'clave_materia' => (string)($editando['clave_materia'] ?? ''),
    'nombre'        => (string)($editando['nombre']        ?? ''),
    'horas_totales' => (string)($editando['horas_totales'] ?? ''),
    'descripcion'   => (string)($editando['descripcion']   ?? ''),
    'orden'         => (string)($editando['orden']         ?? '1'),
];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verificar_token();

    if (!$editable) {
        mensaje('error', 'La ficha no está en borrador; no se puede modificar.');
        redirigir($volver);
    }

    // Si viene materia_id es modificación; debe pertenecer a esta ficha.
    $materiaId = entero(post('materia_id'));
    if ($materiaId !== null) {
        $existente = car_materia_obtener($materiaId);
        if ($existente === null || (int)$existente['ficha_id'] !== $fichaId) {
            no_encontrado('La materia no existe.');
        }
    }

    foreach ($datos as $campo => $_) {
        $datos[$campo] = post($campo);
    }

    /* --- Validación en el servidor --- */
    $cuatri = filter_var($datos['cuatrimestre'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
    if ($cuatri === false) {
        $errores['cuatrimestre'] = 'Indica un cuatrimestre entre 1 y 12.';
    }

    if ($datos['nombre'] === '') {
        $errores['nombre'] = 'El nombre es obligatorio.';
    } elseif (mb_strlen($datos['nombre']) > 150) {
        $errores['nombre'] = 'Máximo 150 caracteres.';
    }

    if (mb_strlen($datos['clave_materia']) > 20) {
        $errores['clave_materia'] = 'Máximo 20 caracteres.';
    }

    $horas = null;
    if ($datos['horas_totales'] !== '') {
        $horas = filter_var($datos['horas_totales'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
        if ($horas === false) {
            $errores['horas_totales'] = 'Las horas deben ser un número entre 1 y 999.';
        }
    }

    $orden = filter_var($datos['orden'] === '' ? '1' : $datos['orden'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
    if ($orden === false) {
        $errores['orden'] = 'El orden debe ser un número entre 1 y 99.';
    }

    if (mb_strlen($datos['descripcion']) > 1000) {
        $errores['descripcion'] = 'Máximo 1000 caracteres.';
    }

    /* --- Guardar --- */
    if (!$errores) {
        $fila = [
            'cuatrimestre'  => $cuatri,
            'clave_materia' => $datos['clave_materia'] !== '' ? $datos['clave_materia'] : null,
            'nombre'        => $datos['nombre'],
            'horas_totales' => $horas,
            'descripcion'   => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
            'orden'         => $orden,
        ];

        if ($materiaId === null) {
            car_materia_crear($fichaId, $fila);
            mensaje('exito', 'Materia agregada.');
        } else {
            car_materia_actualizar($materiaId, $fila);
            mensaje('exito', 'Materia actualizada.');
        }

        redirigir($volver);   // POST-Redirect-GET
    }

    // Con errores se vuelve a pintar el formulario, conservando lo escrito.
    if ($materiaId !== null) {
        $editando = $existente;
    }
}

$materias = car_materias_de_ficha($fichaId);

cabecera('Materias', $ficha['area'], '../css/modulo.css');
?>

<section class="contenedor seccion">

<nav aria-label="Ruta">
    <ol class="migas">
    <li><a href="<?= RUTA_BASE ?>/panel.php">Panel</a></li>
    <li><a href="<?= e(car_url_inicio()) ?>">Carreras</a></li>
    <li>Materias</li>
    </ol>
</nav>

<h1>Materias de <?= e($ficha['nombre']) ?></h1>

<?php pintar_mensajes(); ?>
<?php car_menu_ficha($ficha, 'materias'); ?>

<?php if ($errores): ?>
    <div class="alerta alerta--error" role="alert">
    <p><span class="alerta__titulo">Revisa los datos.</span> Hay campos con problemas.</p>
    </div>
<?php endif; ?>

<?php if ($editable): ?>
<form class="formulario panel mb-6" method="POST" action="">
    <?= campo_token() ?>
    <?php if ($editando): ?>
    <input type="hidden" name="materia_id" value="<?= (int)$editando['id'] ?>">
    <?php endif; ?>
    <h2><?= $editando ? 'Editar materia' : 'Agregar materia' ?></h2>

    <div class="fila">
    <div class="campo <?= isset($errores['cuatrimestre']) ? 'campo--invalido' : '' ?>" style="flex:0 1 140px">
        <label class="campo__etiqueta" for="cuatrimestre">Periodo *</label>
        <input class="campo__control" type="number" id="cuatrimestre" name="cuatrimestre"
            min="1" max="12" value="<?= e($datos['cuatrimestre']) ?>" required>
        <?php if (isset($errores['cuatrimestre'])): ?><small class="campo__error"><?= e($errores['cuatrimestre']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['clave_materia']) ? 'campo--invalido' : '' ?>" style="flex:0 1 160px">
        <label class="campo__etiqueta" for="clave_materia">Clave</label>
        <input class="campo__control" type="text" id="clave_materia" name="clave_materia"
            maxlength="20" value="<?= e($datos['clave_materia']) ?>">
        <?php if (isset($errores['clave_materia'])): ?><small class="campo__error"><?= e($errores['clave_materia']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['nombre']) ? 'campo--invalido' : '' ?>" style="flex:1 1 260px">
        <label class="campo__etiqueta" for="nombre">Nombre *</label>
        <input class="campo__control" type="text" id="nombre" name="nombre"
            maxlength="150" value="<?= e($datos['nombre']) ?>" required>
        <?php if (isset($errores['nombre'])): ?><small class="campo__error"><?= e($errores['nombre']) ?></small><?php endif; ?>
    </div>
    </div>

    <div class="fila">
    <div class="campo <?= isset($errores['horas_totales']) ? 'campo--invalido' : '' ?>" style="flex:0 1 160px">
        <label class="campo__etiqueta" for="horas_totales">Horas totales</label>
        <input class="campo__control" type="number" id="horas_totales" name="horas_totales"
            min="1" max="999" value="<?= e($datos['horas_totales']) ?>">
        <?php if (isset($errores['horas_totales'])): ?><small class="campo__error"><?= e($errores['horas_totales']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['orden']) ? 'campo--invalido' : '' ?>" style="flex:0 1 120px">
        <label class="campo__etiqueta" for="orden">Orden</label>
        <input class="campo__control" type="number" id="orden" name="orden"
            min="1" max="99" value="<?= e($datos['orden']) ?>">
        <?php if (isset($errores['orden'])): ?><small class="campo__error"><?= e($errores['orden']) ?></small><?php endif; ?>
    </div>
    </div>

    <div class="campo <?= isset($errores['descripcion']) ? 'campo--invalido' : '' ?>">
    <label class="campo__etiqueta" for="descripcion">Descripción</label>
    <textarea class="campo__control" id="descripcion" name="descripcion" rows="3"
                maxlength="1000"><?= e($datos['descripcion']) ?></textarea>
        <?php if (isset($errores['descripcion'])): ?><small class="campo__error"><?= e($errores['descripcion']) ?></small><?php endif; ?>
    </div>

    <div class="fila">
        <button class="boton boton--primario" type="submit"><?= $editando ? 'Guardar cambios' : 'Agregar' ?></button>
        <?php if ($editando): ?><a class="boton boton--secundario" href="<?= e($volver) ?>">Cancelar</a><?php endif; ?>
    </div>
    </form>
    <?php else: ?>
    <div class="alerta alerta--info"><p>La ficha no está en borrador: solo puedes consultar las materias.</p></div>
    <?php endif; ?>

    <?php if (!$materias): ?>
    <div class="vacio"><h3>Sin materias</h3><p>Agrega la primera con el formulario.</p></div>
    <?php else: ?>
    <div class="tabla-envoltura">
    <table class="tabla">
        <caption><?= count($materias) ?> materia<?= count($materias) === 1 ? '' : 's' ?></caption>
        <thead>
        <tr><th scope="col">Periodo</th><th scope="col">Clave</th><th scope="col">Nombre</th>
            <th scope="col">Horas</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($materias as $m): ?>
        <tr>
            <td><?= (int)$m['cuatrimestre'] ?></td>
            <td><?= e($m['clave_materia']) ?></td>
            <td><?= e($m['nombre']) ?></td>
            <td><?= $m['horas_totales'] !== null ? (int)$m['horas_totales'] : '—' ?></td>
            <td>
            <?php if ($editable): ?>
            <div class="tabla__acciones">
                <a class="boton boton--secundario boton--pequeno" href="?ficha=<?= $fichaId ?>&amp;editar=<?= (int)$m['id'] ?>">Editar</a>
                <form method="POST" action="materia_eliminar.php"
                        data-confirmar="¿Eliminar esta materia? No se puede deshacer."
                        data-confirmar-boton="Eliminar" data-confirmar-tipo="peligro">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="boton boton--peligro boton--pequeno" type="submit">Eliminar</button>
                </form>
            </div>
            <?php endif; ?>
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