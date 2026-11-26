<?php
/**
 * MÓDULO PLANTILLA · Consultas
 * Explorador Vocacional UTBB
 *
 * Todas las consultas del módulo viven aquí, no mezcladas con el HTML.
 * Una página que imprime también arma consultas se vuelve imposible de revisar.
 *
 * REGLA SIN EXCEPCIÓN: ningún dato variable se concatena a la cadena SQL.
 * Se usan marcadores ? y se pasan los valores en execute().
 */

declare(strict_types=1);

const CAR_POR_PAGINA = 100;   // todas las carreras caben en una sola página (sin paginado)

const CAR_ESTADOS = [
    'borrador'  => 'Borrador',
    'revision'  => 'En revisión',
    'publicado' => 'Publicado',
];

const CAR_AREAS = [
    'tecnologia'     => 'Tecnología',
    'salud'          => 'Salud',
    'administrativa' => 'Administrativa',
    'servicios'      => 'Servicios',
    'agroindustrial' => 'Agroindustrial',
];

/**
 * Quién puede mover una ficha de un estado a otro.
 * rol => [ estado_actual => [estados a los que puede pasar] ]
 */
const CAR_TRANSICIONES = [
    'editor'       => ['borrador' => ['revision']],
    'coordinacion' => [
        'revision'  => ['publicado', 'borrador'],
        'publicado' => ['borrador'],
    ],
];

/** Clase de insignia del sistema de diseño para un estado. */
function car_clase_estado(string $estado): string
{
    return 'insignia insignia--' . (array_key_exists($estado, CAR_ESTADOS) ? $estado : 'borrador');
}

/* =========================================================================
 * PÚBLICO · solo contenido publicado
 * ====================================================================== */

/** Valores distintos de modalidad, para el filtro. */
function car_modalidades(): array
{
    $con = bd()->query('SELECT DISTINCT modalidad FROM carreras WHERE activa = 1 ORDER BY modalidad');
    return $con->fetchAll(PDO::FETCH_COLUMN);
}

/** Valores distintos de duración, para el filtro. */
function car_duraciones(): array
{
    $con = bd()->query('SELECT DISTINCT duracion FROM carreras WHERE activa = 1 ORDER BY duracion');
    return $con->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Índice público: todas las carreras activas con filtros.
 * ficha_id solo trae valor si la ficha está PUBLICADA; así el público
 * nunca ve contenido en borrador ni en revisión.
 */
function car_listar_publicas(
    string $busqueda = '',
    string $area = '',
    string $modalidad = '',
    string $duracion = '',
    int $pagina = 1
): array {
    car_asegurar_publicaciones();   // fichas publicadas antes de existir la tabla de publicaciones

    $condiciones = ['c.activa = 1'];
    $valores     = [];

    if ($busqueda !== '') {
        $condiciones[] = '(c.nombre LIKE ? OR c.clave LIKE ? OR p.resumen LIKE ?)';
        $comodin = '%' . $busqueda . '%';
        array_push($valores, $comodin, $comodin, $comodin);
    }
    if ($area !== '' && array_key_exists($area, CAR_AREAS)) {
        $condiciones[] = 'c.area = ?';
        $valores[]     = $area;
    }
    if ($modalidad !== '') {
        $condiciones[] = 'c.modalidad = ?';
        $valores[]     = $modalidad;
    }
    if ($duracion !== '') {
        $condiciones[] = 'c.duracion = ?';
        $valores[]     = $duracion;
    }

    $donde = 'WHERE ' . implode(' AND ', $condiciones);
    // Lo que ve el público es la VERSIÓN PUBLICADA (car_publicaciones), no el
    // borrador: devolver una ficha a borrador no la quita del índice.
    $union = "LEFT JOIN car_fichas f ON f.carrera_id = c.id
            LEFT JOIN car_publicaciones p ON p.ficha_id = f.id";

    $con = bd()->prepare("SELECT COUNT(*) FROM carreras c $union $donde");
    $con->execute($valores);
    $total = (int)$con->fetchColumn();

    $pagina = max(1, $pagina);
    $desde  = ($pagina - 1) * CAR_POR_PAGINA;
    $limite = CAR_POR_PAGINA;

    $con = bd()->prepare(
        "SELECT c.id, c.nombre, c.clave, c.area, c.duracion, c.modalidad,
                p.id AS ficha_id, p.resumen, p.imagen_portada
        FROM carreras c
        $union
        $donde
    ORDER BY c.nombre
        LIMIT $limite OFFSET $desde"
    );
    $con->execute($valores);

    return [
        'filas'   => $con->fetchAll(),
        'total'   => $total,
        'pagina'  => $pagina,
        'paginas' => (int)ceil($total / CAR_POR_PAGINA),
    ];
}

/**
 * Ficha PÚBLICA de una carrera (por id de carrera), o null si nunca se publicó.
 * Devuelve la VERSIÓN PUBLICADA: ['ficha' => ..., 'materias' => ..., 'instalaciones' => ...,
 * 'galeria' => ..., 'documentos' => ...]. Mientras se edita un borrador nuevo, el
 * público sigue viendo esta versión.
 */
function car_ficha_publica(int $carreraId): ?array
{
    $con = bd()->prepare(
        'SELECT p.contenido, p.publicado_en, f.id AS ficha_id, f.estado,
                c.nombre, c.clave, c.area, c.duracion, c.modalidad
        FROM car_fichas f
        JOIN carreras c ON c.id = f.carrera_id
        LEFT JOIN car_publicaciones p ON p.ficha_id = f.id
        WHERE f.carrera_id = ? AND c.activa = 1'
    );
    $con->execute([$carreraId]);
    $fila = $con->fetch();
    if (!$fila) {
        return null;
    }

    // Ficha publicada antes de existir esta tabla: se genera su versión ahora.
    if ($fila['contenido'] === null) {
        if ($fila['estado'] !== 'publicado') {
            return null;
        }
        car_generar_publicacion((int)$fila['ficha_id'], null);
        return car_ficha_publica($carreraId);
    }

    $contenido = json_decode((string)$fila['contenido'], true);
    if (!is_array($contenido)) {
        return null;
    }

    $contenido['estadias'] = $contenido['estadias'] ?? [];

    $contenido['ficha'] = array_merge($contenido['ficha'], [
        'id'           => (int)$fila['ficha_id'],
        'nombre'       => $fila['nombre'],
        'clave'        => $fila['clave'],
        'area'         => $fila['area'],
        'duracion'     => $fila['duracion'],
        'modalidad'    => $fila['modalidad'],
        'publicado_en' => $fila['publicado_en'],
    ]);

    // El contador de descargas que se muestra es el real, no el de la fecha de publicación.
    foreach ($contenido['documentos'] as &$d) {
        $vivo = car_documento_obtener((int)$d['id']);
        if ($vivo !== null) {
            $d['descargas'] = (int)$vivo['descargas'];
        }
    }
    unset($d);

    return $contenido;
}

/* =========================================================================
 * PUBLICACIONES · la versión que ve el público
 * ====================================================================== */

/** Imágenes y PDF que usa una versión publicada: ['imagenes' => [...], 'docs' => [...]]. */
function car_archivos_de_contenido(array $c): array
{
    $imagenes = [$c['ficha']['imagen_portada'] ?? null, $c['ficha']['imagen_fondo'] ?? null];
    foreach ($c['instalaciones'] ?? [] as $i) {
        $imagenes[] = $i['imagen_servidor'] ?? null;
        $imagenes[] = $i['video_servidor'] ?? null;
    }
    foreach ($c['galeria'] ?? [] as $g) {
        $imagenes[] = $g['imagen_servidor'] ?? null;
    }
    $docs = [];
    foreach ($c['documentos'] ?? [] as $d) {
        $docs[] = $d['archivo_servidor'] ?? null;
    }
    return [
        'imagenes' => array_values(array_unique(array_filter($imagenes))),
        'docs'     => array_values(array_unique(array_filter($docs))),
    ];
}

/**
 * Guarda una copia de lo que hay AHORA en la ficha como su versión publicada.
 * Se llama al publicar (dentro de la transacción de car_cambiar_estado).
 * Devuelve los archivos de la versión anterior que ya nadie usa, para que
 * quien llama los borre del disco una vez confirmada la transacción.
 */
function car_generar_publicacion(int $fichaId, ?int $usuarioId): array
{
    $ficha = car_ficha_obtener($fichaId);
    if ($ficha === null) {
        throw new RuntimeException('La ficha no existe.');
    }

    $materias = car_materias_de_ficha($fichaId);
    $unidades = car_unidades_de_ficha($fichaId);
    foreach ($materias as &$m) {
        $m['unidades'] = $unidades[(int)$m['id']] ?? [];
    }
    unset($m);

    $contenido = [
        'ficha' => [
            'resumen'        => $ficha['resumen'],
            'perfil_egreso'  => $ficha['perfil_egreso'],
            'campo_laboral'  => $ficha['campo_laboral'],
            'video_url'      => $ficha['video_url'],
            'imagen_portada' => $ficha['imagen_portada'],
            'imagen_fondo'   => $ficha['imagen_fondo'] ?? null,
        ],
        'materias'      => $materias,
        'estadias'      => car_estadias_de_ficha($fichaId),
        'instalaciones' => car_instalaciones_de_ficha($fichaId),
        'galeria'       => car_galeria_de_ficha($fichaId),
        'documentos'    => car_documentos_de_ficha($fichaId),
    ];

    $json = json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    // Archivos de la versión anterior que la nueva ya no usa.
    $huerfanos = ['imagenes' => [], 'docs' => []];
    $previa = bd()->prepare('SELECT contenido FROM car_publicaciones WHERE ficha_id = ?');
    $previa->execute([$fichaId]);
    $anterior = $previa->fetchColumn();
    if ($anterior !== false) {
        $viejo = json_decode((string)$anterior, true);
        if (is_array($viejo)) {
            $antes = car_archivos_de_contenido($viejo);
            $ahora = car_archivos_de_contenido($contenido);
            $huerfanos['imagenes'] = array_values(array_diff($antes['imagenes'], $ahora['imagenes']));
            $huerfanos['docs']     = array_values(array_diff($antes['docs'], $ahora['docs']));
        }
    }

    $con = bd()->prepare(
        'INSERT INTO car_publicaciones (ficha_id, resumen, imagen_portada, contenido, publicado_por, publicado_en)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE resumen = VALUES(resumen), imagen_portada = VALUES(imagen_portada),
            contenido = VALUES(contenido), publicado_por = VALUES(publicado_por), publicado_en = NOW()'
    );
    $con->execute([$fichaId, $ficha['resumen'], $ficha['imagen_portada'], $json, $usuarioId]);

    return $huerfanos;
}

/** Genera la versión publicada de las fichas que ya estaban publicadas y no la tienen. */
function car_asegurar_publicaciones(): void
{
    $ids = bd()->query(
        "SELECT f.id FROM car_fichas f
        LEFT JOIN car_publicaciones p ON p.ficha_id = f.id
        WHERE f.estado = 'publicado' AND p.id IS NULL"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ids as $id) {
        car_generar_publicacion((int)$id, null);
    }
}

/** Fecha en que se publicó la versión visible de una ficha, o null si nunca se publicó. */
function car_publicada_en(int $fichaId): ?string
{
    $con = bd()->prepare('SELECT publicado_en FROM car_publicaciones WHERE ficha_id = ?');
    $con->execute([$fichaId]);
    $fecha = $con->fetchColumn();
    return $fecha === false ? null : (string)$fecha;
}

/** ¿La versión publicada de alguna ficha usa todavía este archivo? */
function car_archivo_en_publicacion(string $archivo): bool
{
    $patron = '%"' . addcslashes($archivo, '%_\\') . '"%';
    $con = bd()->prepare('SELECT COUNT(*) FROM car_publicaciones WHERE contenido LIKE ?');
    $con->execute([$patron]);
    return (int)$con->fetchColumn() > 0;
}

/**
 * Borra un archivo del disco SOLO si la versión publicada no lo usa. Así el
 * editor puede cambiar o quitar una imagen del borrador sin romper lo que el
 * público está viendo. Si se conserva, se borra solo al publicar la versión nueva.
 */
function car_borrar_archivo(string $carpeta, ?string $archivo): void
{
    if ($archivo === null || $archivo === '' || car_archivo_en_publicacion($archivo)) {
        return;
    }
    @unlink(rtrim($carpeta, '/\\') . '/' . basename($archivo));
}

/** Datos de un documento dentro de las versiones publicadas, o null. */
function car_documento_publicado(int $id): ?array
{
    $con = bd()->query('SELECT ficha_id, contenido FROM car_publicaciones');
    foreach ($con->fetchAll() as $fila) {
        $c = json_decode((string)$fila['contenido'], true);
        foreach ($c['documentos'] ?? [] as $d) {
            if ((int)$d['id'] === $id) {
                $d['ficha_id'] = (int)$fila['ficha_id'];
                return $d;
            }
        }
    }
    return null;
}


/* =========================================================================
 * PANEL · fichas
 * ====================================================================== */

/** Ficha de una carrera en cualquier estado, o null si aún no existe. */
function car_ficha_de_carrera(int $carreraId): ?array
{
    $con = bd()->prepare(
        'SELECT f.*, c.nombre, c.clave, c.area, c.duracion, c.modalidad
        FROM car_fichas f
        JOIN carreras c ON c.id = f.carrera_id
        WHERE f.carrera_id = ?'
    );
    $con->execute([$carreraId]);
    return $con->fetch() ?: null;
}

/** Ficha por su id (cualquier estado), o null. */
function car_ficha_obtener(int $fichaId): ?array
{
    $con = bd()->prepare(
        'SELECT f.*, c.nombre, c.clave, c.area, c.duracion, c.modalidad
        FROM car_fichas f
        JOIN carreras c ON c.id = f.carrera_id
        WHERE f.id = ?'
    );
    $con->execute([$fichaId]);
    return $con->fetch() ?: null;
}

/** Si la carrera no tiene ficha, crea una vacía en borrador. Devuelve su id. */
function car_asegurar_ficha(int $carreraId, ?int $usuarioId): int
{
    $existente = car_ficha_de_carrera($carreraId);
    if ($existente !== null) {
        return (int)$existente['id'];
    }
    $con = bd()->prepare(
        "INSERT INTO car_fichas (carrera_id, resumen, perfil_egreso, campo_laboral, estado, actualizado_por)
        VALUES (?, '', '', '', 'borrador', ?)"
    );
    $con->execute([$carreraId, $usuarioId]);
    return (int)bd()->lastInsertId();
}

/** Modificación de los textos de la ficha. La portada se actualiza aparte. */
function car_actualizar_ficha(int $fichaId, array $datos, ?int $usuarioId): void
{
    $con = bd()->prepare(
        'UPDATE car_fichas
            SET resumen = ?, perfil_egreso = ?, campo_laboral = ?, video_url = ?, actualizado_por = ?
        WHERE id = ?'
    );
    $con->execute([
        $datos['resumen'],
        $datos['perfil_egreso'],
        $datos['campo_laboral'],
        $datos['video_url'],
        $usuarioId,
        $fichaId,
    ]);
}

/** Guarda el nombre (generado por el servidor) de la imagen de portada. */
function car_guardar_portada(int $fichaId, ?string $archivo): void
{
    $con = bd()->prepare('UPDATE car_fichas SET imagen_portada = ? WHERE id = ?');
    $con->execute([$archivo, $fichaId]);
}

/** Guarda el nombre (generado por el servidor) de la imagen de fondo de la primera vista. */
function car_guardar_fondo(int $fichaId, ?string $archivo): void
{
    $con = bd()->prepare('UPDATE car_fichas SET imagen_fondo = ? WHERE id = ?');
    $con->execute([$archivo, $fichaId]);
}

/* =========================================================================
 * GALERÍA · hasta 6 fotos por ficha (carrusel de la ficha pública)
 * ====================================================================== */

const CAR_GALERIA_MAX = 6;

function car_galeria_de_ficha(int $fichaId): array
{
    $con = bd()->prepare('SELECT * FROM car_galeria WHERE ficha_id = ? ORDER BY orden');
    $con->execute([$fichaId]);
    return $con->fetchAll();
}

/** Foto de un lugar concreto (1 a 6) de la ficha, o null. */
function car_galeria_obtener(int $fichaId, int $orden): ?array
{
    $con = bd()->prepare('SELECT * FROM car_galeria WHERE ficha_id = ? AND orden = ?');
    $con->execute([$fichaId, $orden]);
    return $con->fetch() ?: null;
}

/** Pone una foto en un lugar; si ya había una, la reemplaza. */
function car_galeria_guardar(int $fichaId, int $orden, string $archivo): void
{
    $con = bd()->prepare(
        'INSERT INTO car_galeria (ficha_id, orden, imagen_servidor) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE imagen_servidor = VALUES(imagen_servidor)'
    );
    $con->execute([$fichaId, $orden, $archivo]);
}

function car_galeria_eliminar(int $fichaId, int $orden): void
{
    $con = bd()->prepare('DELETE FROM car_galeria WHERE ficha_id = ? AND orden = ?');
    $con->execute([$fichaId, $orden]);
}

/**
 * Bandeja de la coordinación: una fila por carrera, con el estado de su
 * ficha (estado NULL = todavía no tiene ficha).
 */
function car_listar_bandeja(string $estado = '', string $busqueda = ''): array
{
    $condiciones = [];
    $valores     = [];

    if ($estado === 'sin_ficha') {
        $condiciones[] = 'f.id IS NULL';
    } elseif ($estado !== '' && array_key_exists($estado, CAR_ESTADOS)) {
        $condiciones[] = 'f.estado = ?';
        $valores[]     = $estado;
    }
    if ($busqueda !== '') {
        $condiciones[] = '(c.nombre LIKE ? OR c.clave LIKE ?)';
        $comodin = '%' . $busqueda . '%';
        array_push($valores, $comodin, $comodin);
    }

    $donde = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

    $con = bd()->prepare(
        "SELECT c.id AS carrera_id, c.nombre, c.clave, c.area,
                f.id AS ficha_id, f.estado, f.actualizado_en,
                u.nombre AS actualizado_por,
                p.publicado_en AS publicada_en
        FROM carreras c
    LEFT JOIN car_fichas f ON f.carrera_id = c.id
    LEFT JOIN car_publicaciones p ON p.ficha_id = f.id
    LEFT JOIN usuarios u   ON u.id = f.actualizado_por
        $donde
    ORDER BY FIELD(f.estado, 'revision', 'borrador', 'publicado'), c.nombre"
    );
    $con->execute($valores);
    return $con->fetchAll();
}

/* =========================================================================
 * PROCESO DE ESTADOS
 * ====================================================================== */

/**
 * Cambia el estado de una ficha y lo registra en car_revisiones.
 * Valida la transición según el ROL (aquí, en el servidor) y exige
 * observaciones cuando se devuelve a borrador.
 * Lanza excepción si algo no es válido: las páginas la atrapan y la
 * muestran con mensaje('error', ...).
 */
function car_cambiar_estado(int $fichaId, string $nuevo, ?string $observaciones, int $usuarioId, string $rol): void
{
    if (!array_key_exists($nuevo, CAR_ESTADOS)) {
        throw new InvalidArgumentException('Estado no válido.');
    }

    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $con = $pdo->prepare('SELECT estado FROM car_fichas WHERE id = ? FOR UPDATE');
        $con->execute([$fichaId]);
        $actual = $con->fetchColumn();

        if ($actual === false) {
            throw new RuntimeException('La ficha no existe.');
        }

        $permitidos = CAR_TRANSICIONES[$rol][$actual] ?? [];
        if (!in_array($nuevo, $permitidos, true)) {
            throw new RuntimeException('Ese cambio de estado no está permitido para tu rol.');
        }

        $observaciones = $observaciones !== null ? trim($observaciones) : null;
        if ($nuevo === 'borrador' && ($observaciones === null || $observaciones === '')) {
            throw new InvalidArgumentException('Escribe las observaciones para devolver la ficha.');
        }

        $con = $pdo->prepare('UPDATE car_fichas SET estado = ?, actualizado_por = ? WHERE id = ?');
        $con->execute([$nuevo, $usuarioId, $fichaId]);

        $con = $pdo->prepare(
            'INSERT INTO car_revisiones (ficha_id, usuario_id, estado_anterior, estado_nuevo, observaciones)
            VALUES (?, ?, ?, ?, ?)'
        );
        $con->execute([$fichaId, $usuarioId, $actual, $nuevo, $observaciones]);

        // Publicar = la versión que ve el público pasa a ser esta. Devolver a
        // borrador NO toca la versión publicada: sigue visible hasta la próxima publicación.
        $huerfanos = ['imagenes' => [], 'docs' => []];
        if ($nuevo === 'publicado') {
            $huerfanos = car_generar_publicacion($fichaId, $usuarioId);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Ya confirmado: se borran del disco los archivos de la versión anterior que sobran.
    foreach ($huerfanos['imagenes'] as $archivo) {
        @unlink(__DIR__ . '/../uploads/' . basename($archivo));
    }
    foreach ($huerfanos['docs'] as $archivo) {
        @unlink(__DIR__ . '/../uploads/docs/' . basename($archivo));
    }
}

/** Bitácora de una ficha, de la más reciente a la más antigua. */
function car_historial(int $fichaId): array
{
    $con = bd()->prepare(
        'SELECT r.*, u.nombre AS usuario
        FROM car_revisiones r
        LEFT JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.ficha_id = ?
        ORDER BY r.creado_en DESC, r.id DESC'
    );
    $con->execute([$fichaId]);
    return $con->fetchAll();
}

/* =========================================================================
 * MATERIAS · alta, consulta, modificación y baja
 * ====================================================================== */

function car_materias_de_ficha(int $fichaId): array
{
    $con = bd()->prepare(
        'SELECT * FROM car_materias WHERE ficha_id = ? ORDER BY cuatrimestre, orden, id'
    );
    $con->execute([$fichaId]);
    return $con->fetchAll();
}

function car_materia_obtener(int $id): ?array
{
    $con = bd()->prepare('SELECT * FROM car_materias WHERE id = ?');
    $con->execute([$id]);
    return $con->fetch() ?: null;
}

/** Unidades de una materia, en orden. */
function car_unidades_de_materia(int $materiaId): array
{
    $con = bd()->prepare('SELECT * FROM car_unidades WHERE materia_id = ? ORDER BY numero');
    $con->execute([$materiaId]);
    return $con->fetchAll();
}

/** Unidades de varias materias de una vez: [materia_id => [unidades]]. */
function car_unidades_de_ficha(int $fichaId): array
{
    $con = bd()->prepare(
        'SELECT u.* FROM car_unidades u
        JOIN car_materias m ON m.id = u.materia_id
        WHERE m.ficha_id = ?
        ORDER BY u.materia_id, u.numero'
    );
    $con->execute([$fichaId]);
    $porMateria = [];
    foreach ($con->fetchAll() as $u) {
        $porMateria[(int)$u['materia_id']][] = $u;
    }
    return $porMateria;
}

/** Reemplaza las unidades de una materia por las recibidas (misma transacción que el llamador). */
function car_unidades_guardar(int $materiaId, array $unidades): void
{
    $borrar = bd()->prepare('DELETE FROM car_unidades WHERE materia_id = ?');
    $borrar->execute([$materiaId]);

    $ins = bd()->prepare(
        'INSERT INTO car_unidades (materia_id, numero, nombre, horas_saber, horas_saber_hacer, horas_totales)
        VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($unidades as $u) {
        $ins->execute([
            $materiaId, $u['numero'], $u['nombre'],
            $u['horas_saber'], $u['horas_saber_hacer'], $u['horas_totales'],
        ]);
    }
}

/** Cuatrimestres de estadía de una ficha (números, de menor a mayor). */
function car_estadias_de_ficha(int $fichaId): array
{
    $con = bd()->prepare('SELECT cuatrimestre FROM car_estadias WHERE ficha_id = ? ORDER BY cuatrimestre');
    $con->execute([$fichaId]);
    return array_map('intval', $con->fetchAll(PDO::FETCH_COLUMN));
}

function car_es_estadia(int $fichaId, int $cuatrimestre): bool
{
    $con = bd()->prepare('SELECT COUNT(*) FROM car_estadias WHERE ficha_id = ? AND cuatrimestre = ?');
    $con->execute([$fichaId, $cuatrimestre]);
    return (int)$con->fetchColumn() > 0;
}

/** Cuántas materias tiene un cuatrimestre. */
function car_contar_materias(int $fichaId, int $cuatrimestre): int
{
    $con = bd()->prepare('SELECT COUNT(*) FROM car_materias WHERE ficha_id = ? AND cuatrimestre = ?');
    $con->execute([$fichaId, $cuatrimestre]);
    return (int)$con->fetchColumn();
}

function car_estadia_marcar(int $fichaId, int $cuatrimestre): void
{
    $con = bd()->prepare('INSERT IGNORE INTO car_estadias (ficha_id, cuatrimestre) VALUES (?, ?)');
    $con->execute([$fichaId, $cuatrimestre]);
}

function car_estadia_quitar(int $fichaId, int $cuatrimestre): void
{
    $con = bd()->prepare('DELETE FROM car_estadias WHERE ficha_id = ? AND cuatrimestre = ?');
    $con->execute([$fichaId, $cuatrimestre]);
}

/** Siguiente número de orden dentro de un cuatrimestre: las materias van en el orden en que se guardan. */
function car_siguiente_orden(int $fichaId, int $cuatrimestre): int
{
    $con = bd()->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM car_materias WHERE ficha_id = ? AND cuatrimestre = ?');
    $con->execute([$fichaId, $cuatrimestre]);
    return min(255, (int)$con->fetchColumn());
}

/** Crea la materia y sus unidades en una sola transacción. */
function car_materia_crear(int $fichaId, array $d, array $unidades = []): int
{
    $pdo = bd();
    $pdo->beginTransaction();
    try {
        $con = $pdo->prepare(
            'INSERT INTO car_materias
                (ficha_id, cuatrimestre, clave_materia, nombre, horas_totales,
                proposito, competencia, num_unidades, orden)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $con->execute([
            $fichaId, $d['cuatrimestre'], $d['clave_materia'], $d['nombre'],
            $d['horas_totales'], $d['proposito'], $d['competencia'],
            $d['num_unidades'], car_siguiente_orden($fichaId, (int)$d['cuatrimestre']),
        ]);
        $id = (int)$pdo->lastInsertId();
        car_unidades_guardar($id, $unidades);
        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Actualiza la materia y reemplaza sus unidades en una sola transacción. */
function car_materia_actualizar(int $id, array $d, array $unidades = []): void
{
    $pdo = bd();
    $pdo->beginTransaction();
    try {
        $previa = $pdo->prepare('SELECT ficha_id, cuatrimestre FROM car_materias WHERE id = ?');
        $previa->execute([$id]);
        $antes = $previa->fetch();

        $con = $pdo->prepare(
            'UPDATE car_materias
                SET cuatrimestre = ?, clave_materia = ?, nombre = ?, horas_totales = ?,
                    proposito = ?, competencia = ?, num_unidades = ?
            WHERE id = ?'
        );
        $con->execute([
            $d['cuatrimestre'], $d['clave_materia'], $d['nombre'],
            $d['horas_totales'], $d['proposito'], $d['competencia'],
            $d['num_unidades'], $id,
        ]);

        // Si la materia cambia de cuatrimestre, queda al final del nuevo.
        if ($antes && (int)$antes['cuatrimestre'] !== (int)$d['cuatrimestre']) {
            $pdo->prepare('UPDATE car_materias SET orden = ? WHERE id = ?')
                ->execute([car_siguiente_orden((int)$antes['ficha_id'], (int)$d['cuatrimestre']), $id]);
        }
        car_unidades_guardar($id, $unidades);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function car_materia_eliminar(int $id): void
{
    $con = bd()->prepare('DELETE FROM car_materias WHERE id = ?');
    $con->execute([$id]);
}

/* =========================================================================
 * INSTALACIONES · alta, consulta, modificación y baja
 * ====================================================================== */

function car_instalaciones_de_ficha(int $fichaId): array
{
    $con = bd()->prepare('SELECT * FROM car_instalaciones WHERE ficha_id = ? ORDER BY orden, id');
    $con->execute([$fichaId]);
    return $con->fetchAll();
}

function car_instalacion_obtener(int $id): ?array
{
    $con = bd()->prepare('SELECT * FROM car_instalaciones WHERE id = ?');
    $con->execute([$id]);
    return $con->fetch() ?: null;
}

function car_instalacion_crear(int $fichaId, array $d): int
{
    $con = bd()->prepare(
        'INSERT INTO car_instalaciones (ficha_id, nombre, descripcion, imagen_servidor, video_servidor, orden)
        VALUES (?, ?, ?, ?, ?, ?)'
    );
    // El orden es automático: cada instalación nueva queda al final.
    $sig = bd()->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM car_instalaciones WHERE ficha_id = ?');
    $sig->execute([$fichaId]);
    $orden = min(255, (int)$sig->fetchColumn());
    $con->execute([$fichaId, $d['nombre'], $d['descripcion'], $d['imagen_servidor'], $d['video_servidor'] ?? null, $orden]);
    return (int)bd()->lastInsertId();
}

function car_instalacion_actualizar(int $id, array $d): void
{
    $con = bd()->prepare(
        'UPDATE car_instalaciones
            SET nombre = ?, descripcion = ?, imagen_servidor = ?, video_servidor = ?
        WHERE id = ?'
    );
    $con->execute([$d['nombre'], $d['descripcion'], $d['imagen_servidor'], $d['video_servidor'] ?? null, $id]);
}

function car_instalacion_eliminar(int $id): void
{
    $con = bd()->prepare('DELETE FROM car_instalaciones WHERE id = ?');
    $con->execute([$id]);
}

/* =========================================================================
 * DOCUMENTOS · plan de estudios en PDF
 * ====================================================================== */

function car_documentos_de_ficha(int $fichaId): array
{
    $con = bd()->prepare('SELECT * FROM car_documentos WHERE ficha_id = ? ORDER BY subido_en DESC, id DESC');
    $con->execute([$fichaId]);
    return $con->fetchAll();
}

/**
 * Documento con los datos de su ficha y carrera, para verificar permisos
 * antes de entregarlo (estado de la ficha y carrera a la que pertenece).
 */
function car_documento_obtener(int $id): ?array
{
    $con = bd()->prepare(
        'SELECT d.*, f.estado AS ficha_estado, f.carrera_id
        FROM car_documentos d
        JOIN car_fichas f ON f.id = d.ficha_id
        WHERE d.id = ?'
    );
    $con->execute([$id]);
    return $con->fetch() ?: null;
}

function car_documento_crear(int $fichaId, array $d): int
{
    $con = bd()->prepare(
        'INSERT INTO car_documentos
            (ficha_id, titulo, archivo_servidor, nombre_original, mime_type, tamano_bytes, restringido)
        VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $con->execute([
        $fichaId, $d['titulo'], $d['archivo_servidor'], $d['nombre_original'],
        $d['mime_type'], $d['tamano_bytes'], $d['restringido'],
    ]);
    return (int)bd()->lastInsertId();
}

/** Borra el registro y devuelve el nombre del archivo para borrarlo del disco. */
function car_documento_eliminar(int $id): ?string
{
    $doc = car_documento_obtener($id);
    if ($doc === null) {
        return null;
    }
    $con = bd()->prepare('DELETE FROM car_documentos WHERE id = ?');
    $con->execute([$id]);
    return $doc['archivo_servidor'];
}

function car_documento_sumar_descarga(int $id): void
{
    $con = bd()->prepare('UPDATE car_documentos SET descargas = descargas + 1 WHERE id = ?');
    $con->execute([$id]);
}

/* =========================================================================
 * VIDEO · vista incrustada
 * ====================================================================== */

/**
 * Datos para incrustar una el video dentro de la página, o null si la dirección no
 * se puede mostrar (entonces en la ficha pública queda solo como enlace).
 * Reconoce YouTube, Vimeo y archivos .mp4 / .webm / .ogg directos.
 *
 * @return array{tipo:string,src:string}|null
 */
function car_video_embed(?string $url): ?array
{
    $url = trim((string)$url);
    if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
        return null;
    }

    $partes = parse_url($url);
    if ($partes === false || empty($partes['host'])) {
        return null;
    }
    $host = strtolower(preg_replace('/^(www|m)\./i', '', $partes['host']));
    $ruta = $partes['path'] ?? '';

    // YouTube
    if ($host === 'youtube.com' || $host === 'youtu.be') {
        $id = null;
        if ($host === 'youtu.be') {
            $id = ltrim($ruta, '/');
        } elseif (preg_match('#^/(embed|shorts|live)/([A-Za-z0-9_-]{11})#', $ruta, $m)) {
            $id = $m[2];
        } else {
            parse_str($partes['query'] ?? '', $q);
            $id = $q['v'] ?? null;
        }
        if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1) {
            return ['tipo' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/' . $id];
        }
        return null;
    }

    // Vimeo
    if ($host === 'vimeo.com' && preg_match('#^/(\d+)#', $ruta, $m)) {
        return ['tipo' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . $m[1]];
    }

    // Archivo de video directo
    if (preg_match('/\.(mp4|webm|ogg)$/i', $ruta) === 1) {
        return ['tipo' => 'video', 'src' => $url];
    }

    return null;
}

