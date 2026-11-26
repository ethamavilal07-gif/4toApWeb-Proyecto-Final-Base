<?php
/**
 * CAR · Índice público de carreras
 * Filtros: búsqueda, área, modalidad y duración.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../nucleo/comun.php';
require_once __DIR__ . '/includes/consultas.php';

// --- Entrada: todo lo que llega por la dirección se trata como no confiable ---
$busqueda  = get('q');
$area      = get('area');
$modalidad = get('modalidad');
$duracion  = get('duracion');
$pagina    = entero(get('pagina')) ?? 1;

$resultado = car_listar_publicas($busqueda, $area, $modalidad, $duracion, $pagina);

$hayFiltros = ($busqueda !== '' || $area !== '' || $modalidad !== '' || $duracion !== '');

cabecera('Carreras', '', 'css/modulo.css');
?>

<section class="portada">
  <div class="contenedor">
    <h1>Encuentra tu carrera</h1>
    <p class="entradilla">
      Conoce el plan de estudios, el perfil de egreso, las instalaciones y el
      campo laboral de cada carrera de la universidad.
    </p>
  </div>
</section>

<section class="contenedor seccion">

  <?php pintar_mensajes(); ?>

  <div class="disposicion-filtros">

    <!-- Filtros -->
    <form class="panel" method="GET" action="" data-filtros>
      <h2>Filtrar</h2>

      <div class="campo">
        <label class="campo__etiqueta" for="q">Buscar</label>
        <input class="campo__control" type="search" id="q" name="q"
              value="<?= e($busqueda) ?>" placeholder="Nombre o palabra clave">
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="area">Área</label>
        <select class="campo__control" id="area" name="area">
          <option value="">Todas</option>
          <?php foreach (CAR_AREAS as $clave => $texto): ?>
            <option value="<?= e($clave) ?>" <?= $area === $clave ? 'selected' : '' ?>>
              <?= e($texto) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="modalidad">Modalidad</label>
        <select class="campo__control" id="modalidad" name="modalidad">
          <option value="">Todas</option>
          <?php foreach (car_modalidades() as $m): ?>
            <option value="<?= e($m) ?>" <?= $modalidad === $m ? 'selected' : '' ?>>
              <?= e($m) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="campo">
        <label class="campo__etiqueta" for="duracion">Duración</label>
        <select class="campo__control" id="duracion" name="duracion">
          <option value="">Todas</option>
          <?php foreach (car_duraciones() as $d): ?>
            <option value="<?= e($d) ?>" <?= $duracion === $d ? 'selected' : '' ?>>
              <?= e($d) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($hayFiltros): ?>
        <p class="centrado mt-4 sin-margen"><a href="?">Quitar filtros</a></p>
      <?php endif; ?>
    </form>

    <!-- Resultados -->
    <div>
      <p class="leyenda">
        <?= (int)$resultado['total'] ?> carrera<?= $resultado['total'] === 1 ? '' : 's' ?>
      </p>

      <?php if (!$resultado['filas']): ?>

        <div class="vacio">
          <h3>No hay carreras con esos filtros</h3>
          <p class="ancho-lectura">Prueba con otra palabra o quita los filtros.</p>
          <a class="boton boton--secundario" href="?">Quitar filtros</a>
        </div>

      <?php else: ?>

        <div class="rejilla">
          <?php foreach ($resultado['filas'] as $f): ?>
            <?php $publicada = $f['ficha_id'] !== null; ?>

            <<?= $publicada ? 'a' : 'div' ?> class="tarjeta tarjeta--area <?= e(clase_area($f['area'])) ?>"
              <?= $publicada ? 'href="ficha.php?id=' . (int)$f['id'] . '"' : '' ?>>

              <div class="tarjeta__cuerpo">
                <h3 class="tarjeta__titulo"><?= e($f['nombre']) ?></h3>
                <p class="leyenda"><?= e($f['duracion']) ?> · <?= e($f['modalidad']) ?></p>
              </div>

              <div class="tarjeta__pie">
                <span class="insignia insignia--info"><?= e($f['clave']) ?></span>
                <span><?= $publicada ? 'Ver ficha' : 'Próximamente' ?></span>
              </div>
            </<?= $publicada ? 'a' : 'div' ?>>

          <?php endforeach; ?>
        </div>

        <?php if ($resultado['paginas'] > 1): ?>
          <nav aria-label="Paginación">
            <ul class="paginacion">
              <?php
              // Se conservan los filtros al cambiar de página.
              $base = http_build_query(array_filter([
                  'q'         => $busqueda,
                  'area'      => $area,
                  'modalidad' => $modalidad,
                  'duracion'  => $duracion,
              ]));
              for ($p = 1; $p <= $resultado['paginas']; $p++):
              ?>
                <li>
                  <?php if ($p === $resultado['pagina']): ?>
                    <span aria-current="page"><?= $p ?></span>
                  <?php else: ?>
                    <a href="?<?= e($base) ?><?= $base ? '&amp;' : '' ?>pagina=<?= $p ?>"><?= $p ?></a>
                  <?php endif; ?>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>

      <?php endif; ?>
    </div>

  </div>
</section>

<script src="js/filtros.js"></script>
<?php
pie();