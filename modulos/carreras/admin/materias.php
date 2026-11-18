<?php
/**
 * CAR · Materias de una ficha (alta, consulta, modificación)
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
    'horas_semana'  => (string)($editando['horas_semana']  ?? ''),
    'proposito'     => (string)($editando['proposito']     ?? ''),
    'competencia'   => (string)($editando['competencia']   ?? ''),
    'num_unidades'  => (string)(($editando['num_unidades'] ?? 0) ?: ''),
    'orden'         => (string)($editando['orden']         ?? '1'),
];

// Hasta 5 unidades. Se pintan siempre las 5 y el JS muestra solo las elegidas.
const MAX_UNIDADES = 5;
$unidades = array_fill(1, MAX_UNIDADES, ['nombre' => '', 'horas_saber' => '', 'horas_saber_hacer' => '']);
if ($editando) {
    foreach (car_unidades_de_materia((int)$editando['id']) as $u) {
        $n = (int)$u['numero'];
        if ($n >= 1 && $n <= MAX_UNIDADES) {
            $unidades[$n] = [
                'nombre'            => (string)$u['nombre'],
                'horas_saber'       => (string)$u['horas_saber'],
                'horas_saber_hacer' => (string)$u['horas_saber_hacer'],
            ];
        }
    }
}
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

    // Unidades: llegan como arreglos paralelos (índice 0 = unidad 1).
    foreach (['nombre' => 'unidad_nombre', 'horas_saber' => 'unidad_saber', 'horas_saber_hacer' => 'unidad_hacer'] as $clave => $campoPost) {
        $recibido = $_POST[$campoPost] ?? [];
        $recibido = is_array($recibido) ? array_values($recibido) : [];
        for ($i = 1; $i <= MAX_UNIDADES; $i++) {
            $valor = $recibido[$i - 1] ?? '';
            $unidades[$i][$clave] = is_string($valor) ? trim($valor) : '';
        }
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

    $semana = null;
    if ($datos['horas_semana'] !== '') {
        $semana = filter_var($datos['horas_semana'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if ($semana === false) {
            $errores['horas_semana'] = 'Las horas por semana deben ser un número entre 1 y 99.';
        }
    }

    if (mb_strlen($datos['proposito']) > 1000) {
        $errores['proposito'] = 'Máximo 1000 caracteres.';
    }

    if (mb_strlen($datos['competencia']) > 1000) {
        $errores['competencia'] = 'Máximo 1000 caracteres.';
    }

    // Cantidad de unidades: de 1 a 5. Solo se validan y guardan esas.
    $numUnidades = filter_var($datos['num_unidades'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_UNIDADES]]);
    $unidadesFila = [];
    if ($numUnidades === false) {
        $errores['num_unidades'] = 'Elige de 1 a ' . MAX_UNIDADES . ' unidades.';
    } else {
        for ($i = 1; $i <= $numUnidades; $i++) {
            $u = $unidades[$i];

            if ($u['nombre'] === '') {
                $errores["u{$i}_nombre"] = 'El nombre de la unidad es obligatorio.';
            } elseif (mb_strlen($u['nombre']) > 150) {
                $errores["u{$i}_nombre"] = 'Máximo 150 caracteres.';
            }

            $saber = filter_var($u['horas_saber'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
            if ($saber === false) {
                $errores["u{$i}_saber"] = 'Número entre 0 y 999.';
            }
            $hacer = filter_var($u['horas_saber_hacer'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]);
            if ($hacer === false) {
                $errores["u{$i}_hacer"] = 'Número entre 0 y 999.';
            }

            if ($saber !== false && $hacer !== false) {
                $unidadesFila[] = [
                    'numero'            => $i,
                    'nombre'            => $u['nombre'],
                    'horas_saber'       => $saber,
                    'horas_saber_hacer' => $hacer,
                    'horas_totales'     => $saber + $hacer,   // se calcula aquí, no se confía en el navegador
                ];
            }
        }
    }

    /* --- Guardar --- */
    if (!$errores) {
        $fila = [
            'cuatrimestre'  => $cuatri,
            'clave_materia' => $datos['clave_materia'] !== '' ? $datos['clave_materia'] : null,
            'nombre'        => $datos['nombre'],
            'horas_totales' => $horas,
            'horas_semana'  => $semana,
            'proposito'     => $datos['proposito'] !== '' ? $datos['proposito'] : null,
            'competencia'   => $datos['competencia'] !== '' ? $datos['competencia'] : null,
            'num_unidades'  => $numUnidades,
            'orden'         => $orden,
        ];

        if ($materiaId === null) {
            car_materia_crear($fichaId, $fila, $unidadesFila);
            mensaje('exito', 'Materia agregada.');
        } else {
            car_materia_actualizar($materiaId, $fila, $unidadesFila);
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
        <label class="campo__etiqueta" for="cuatrimestre">Cuatrimestre *</label>
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

    <div class="campo <?= isset($errores['horas_semana']) ? 'campo--invalido' : '' ?>" style="flex:0 1 160px">
        <label class="campo__etiqueta" for="horas_semana">Horas por semana</label>
        <input class="campo__control" type="number" id="horas_semana" name="horas_semana"
            min="1" max="99" value="<?= e($datos['horas_semana']) ?>">
        <?php if (isset($errores['horas_semana'])): ?><small class="campo__error"><?= e($errores['horas_semana']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['orden']) ? 'campo--invalido' : '' ?>" style="flex:0 1 120px">
        <label class="campo__etiqueta" for="orden">Orden</label>
        <input class="campo__control" type="number" id="orden" name="orden"
            min="1" max="99" value="<?= e($datos['orden']) ?>">
        <?php if (isset($errores['orden'])): ?><small class="campo__error"><?= e($errores['orden']) ?></small><?php endif; ?>
    </div>
    </div>

    <div class="campo <?= isset($errores['proposito']) ? 'campo--invalido' : '' ?>">
    <label class="campo__etiqueta" for="proposito">Propósito de aprendizaje de la Asignatura</label>
    <textarea class="campo__control" id="proposito" name="proposito" rows="3"
                maxlength="1000"><?= e($datos['proposito']) ?></textarea>
        <?php if (isset($errores['proposito'])): ?><small class="campo__error"><?= e($errores['proposito']) ?></small><?php endif; ?>
    </div>

    <div class="campo <?= isset($errores['competencia']) ? 'campo--invalido' : '' ?>">
    <label class="campo__etiqueta" for="competencia">Competencia a la que contribuye la asignatura</label>
    <textarea class="campo__control" id="competencia" name="competencia" rows="3"
                maxlength="1000"><?= e($datos['competencia']) ?></textarea>
        <?php if (isset($errores['competencia'])): ?><small class="campo__error"><?= e($errores['competencia']) ?></small><?php endif; ?>
    </div>

    <fieldset class="mb-6">
    <legend>Unidades</legend>

    <div class="campo <?= isset($errores['num_unidades']) ? 'campo--invalido' : '' ?>" style="max-width:220px">
        <label class="campo__etiqueta" for="num_unidades">Número de unidades *</label>
        <select class="campo__control" id="num_unidades" name="num_unidades" required>
        <option value="">Elige…</option>
        <?php for ($n = 1; $n <= MAX_UNIDADES; $n++): ?>
            <option value="<?= $n ?>" <?= $datos['num_unidades'] === (string)$n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
        </select>
        <?php if (isset($errores['num_unidades'])): ?><small class="campo__error"><?= e($errores['num_unidades']) ?></small><?php endif; ?>
    </div>

    <?php for ($i = 1; $i <= MAX_UNIDADES; $i++): $u = $unidades[$i]; ?>
    <div class="panel mb-6" data-unidad="<?= $i ?>">
        <h3>Unidad <?= $i ?></h3>

        <div class="campo <?= isset($errores["u{$i}_nombre"]) ? 'campo--invalido' : '' ?>">
        <label class="campo__etiqueta" for="u<?= $i ?>_nombre">Nombre de la unidad</label>
        <input class="campo__control" type="text" id="u<?= $i ?>_nombre" name="unidad_nombre[]"
                maxlength="150" value="<?= e($u['nombre']) ?>">
        <?php if (isset($errores["u{$i}_nombre"])): ?><small class="campo__error"><?= e($errores["u{$i}_nombre"]) ?></small><?php endif; ?>
        </div>

        <div class="fila">
        <div class="campo <?= isset($errores["u{$i}_saber"]) ? 'campo--invalido' : '' ?>" style="flex:0 1 180px">
            <label class="campo__etiqueta" for="u<?= $i ?>_saber">Horas del Saber</label>
            <input class="campo__control" type="number" id="u<?= $i ?>_saber" name="unidad_saber[]"
                min="0" max="999" value="<?= e($u['horas_saber']) ?>" data-suma="saber">
            <?php if (isset($errores["u{$i}_saber"])): ?><small class="campo__error"><?= e($errores["u{$i}_saber"]) ?></small><?php endif; ?>
        </div>

        <div class="campo <?= isset($errores["u{$i}_hacer"]) ? 'campo--invalido' : '' ?>" style="flex:0 1 180px">
            <label class="campo__etiqueta" for="u<?= $i ?>_hacer">Horas del Saber Hacer</label>
            <input class="campo__control" type="number" id="u<?= $i ?>_hacer" name="unidad_hacer[]"
                min="0" max="999" value="<?= e($u['horas_saber_hacer']) ?>" data-suma="hacer">
            <?php if (isset($errores["u{$i}_hacer"])): ?><small class="campo__error"><?= e($errores["u{$i}_hacer"]) ?></small><?php endif; ?>
        </div>

        <div class="campo" style="flex:0 1 180px">
            <label class="campo__etiqueta" for="u<?= $i ?>_total">Horas Totales</label>
            <input class="campo__control" type="number" id="u<?= $i ?>_total" readonly tabindex="-1"
                value="<?= ($u['horas_saber'] !== '' || $u['horas_saber_hacer'] !== '') ? (int)$u['horas_saber'] + (int)$u['horas_saber_hacer'] : '' ?>"
                data-suma="total">
        </div>
        </div>
    </div>
    <?php endfor; ?>
    </fieldset>

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
            <th scope="col">Horas</th><th scope="col">Hrs/sem</th><th scope="col">Unidades</th><th scope="col">Acciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($materias as $m): ?>
        <tr>
            <td><?= (int)$m['cuatrimestre'] ?></td>
            <td><?= e($m['clave_materia']) ?></td>
            <td><?= e($m['nombre']) ?></td>
            <td><?= $m['horas_totales'] !== null ? (int)$m['horas_totales'] : '—' ?></td>
            <td><?= $m['horas_semana'] !== null ? (int)$m['horas_semana'] : '—' ?></td>
            <td><?= (int)$m['num_unidades'] > 0 ? (int)$m['num_unidades'] : '—' ?></td>
            <td>
            <?php if ($editable): ?>
            <div class="tabla__acciones">
                <a class="boton boton--secundario boton--pequeno" href="?ficha=<?= $fichaId ?>&amp;editar=<?= (int)$m['id'] ?>">Editar</a>
                <form method="POST" action="materia_eliminar.php"
                        data-confirmar="¿Eliminar esta materia? No se puede deshacer."
                        data-confirmar-boton="Eliminar" data-confirmar-tipo="peligro">
                <?= campo_token() ?>
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
<script src="../js/unidades.js"></script>
<?php
pie();