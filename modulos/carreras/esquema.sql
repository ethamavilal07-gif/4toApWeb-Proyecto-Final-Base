-- =============================================================================
-- MÓDULO CAR · Explorador de carreras · esquema.sql  (UN SOLO ARCHIVO)
-- Equipo A-1 · Prefijo de tablas: car_
--
-- Crea TODAS las tablas del módulo. No incluye datos: las fichas, materias, fotos y
-- demás contenido se capturan desde el panel.
-- Para una base NUEVA: se ejecuta DESPUÉS de sql/01-nucleo.sql y de
-- herramientas/crear-usuarios.php. Referencias externas: solo usuarios y carreras.
-- Para volver a empezar: borra las tablas car_ (o la base) y vuelve a importar este archivo.
-- =============================================================================

USE explorador_vocacional;
SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- car_fichas · una ficha institucional por carrera (relación 1 a 1)
-- Estados: borrador -> revision -> publicado. Solo 'publicado' es visible.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_fichas (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    carrera_id     INT UNSIGNED NOT NULL,
    resumen        TEXT NOT NULL,
    perfil_egreso  TEXT NOT NULL,
    campo_laboral  TEXT NOT NULL,
    video_url      VARCHAR(255) NULL,
    imagen_portada VARCHAR(255) NULL,          -- nombre generado por el servidor
    imagen_fondo   VARCHAR(255) NULL,          -- fondo de la primera vista (hero)
    estado         ENUM('borrador','revision','publicado') NOT NULL DEFAULT 'borrador',
    actualizado_por INT UNSIGNED NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_car_fichas_carrera (carrera_id),
    KEY ix_car_fichas_estado (estado),

    CONSTRAINT fk_car_fichas_carrera
        FOREIGN KEY (carrera_id) REFERENCES carreras(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_car_fichas_usuario
        FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_materias · plan de estudios, una fila por materia
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_materias (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id      INT UNSIGNED NOT NULL,
    cuatrimestre  TINYINT UNSIGNED NOT NULL,
    clave_materia VARCHAR(20) NULL,
    nombre        VARCHAR(150) NOT NULL,
    horas_totales SMALLINT UNSIGNED NULL,
    proposito     TEXT NULL,                   -- propósito de aprendizaje de la asignatura
    competencia   TEXT NULL,                   -- competencia a la que contribuye
    num_unidades  TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- de 1 a 5
    orden         TINYINT UNSIGNED NOT NULL DEFAULT 1,

    KEY ix_car_materias_ficha_cuatri (ficha_id, cuatrimestre),

    CONSTRAINT fk_car_materias_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_unidades · unidades de cada materia (de 1 a 5)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_unidades (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_id       INT UNSIGNED NOT NULL,
    numero           TINYINT UNSIGNED NOT NULL,
    nombre           VARCHAR(150) NOT NULL,
    horas_saber      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    horas_saber_hacer SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    horas_totales    SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    UNIQUE KEY uq_car_unidades_materia_numero (materia_id, numero),

    CONSTRAINT fk_car_unidades_materia
        FOREIGN KEY (materia_id) REFERENCES car_materias(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_instalaciones · laboratorios y espacios (galería)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_instalaciones (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id        INT UNSIGNED NOT NULL,
    nombre          VARCHAR(150) NOT NULL,
    descripcion     TEXT NOT NULL,
    imagen_servidor VARCHAR(255) NULL,         -- nombre generado por el servidor
    video_servidor  VARCHAR(255) NULL,         -- video mp4 (una instalación lleva foto O video)
    orden           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_car_instalaciones_ficha (ficha_id),

    CONSTRAINT fk_car_instalaciones_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_estadias · cuatrimestres de estadía (prácticas); en ellos no se capturan materias
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_estadias (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id     INT UNSIGNED NOT NULL,
    cuatrimestre TINYINT UNSIGNED NOT NULL,

    UNIQUE KEY uq_car_estadias_ficha_cuatri (ficha_id, cuatrimestre),

    CONSTRAINT fk_car_estadias_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_galeria · hasta 6 fotos por ficha, para el carrusel de la ficha pública
-- orden va de 1 a 6 y es único por ficha (un lugar por foto).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_galeria (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id        INT UNSIGNED NOT NULL,
    orden           TINYINT UNSIGNED NOT NULL,
    imagen_servidor VARCHAR(255) NOT NULL,     -- nombre generado por el servidor
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_car_galeria_ficha_orden (ficha_id, orden),

    CONSTRAINT fk_car_galeria_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_documentos · PDF del plan de estudios, con registro de descargas
-- archivo_servidor es el nombre generado por el servidor; nombre_original
-- solo se usa para mostrarlo al descargar.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_documentos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id         INT UNSIGNED NOT NULL,
    titulo           VARCHAR(150) NOT NULL DEFAULT 'Plan de Estudios',
    archivo_servidor VARCHAR(255) NOT NULL,
    nombre_original  VARCHAR(255) NOT NULL,
    mime_type        VARCHAR(100) NOT NULL DEFAULT 'application/pdf',
    tamano_bytes     INT UNSIGNED NOT NULL DEFAULT 0,
    restringido      TINYINT(1) NOT NULL DEFAULT 0,
    descargas        INT UNSIGNED NOT NULL DEFAULT 0,
    subido_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_car_documentos_ficha (ficha_id),

    CONSTRAINT fk_car_documentos_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_publicaciones · versión publicada de cada ficha (lo que ve el público)
-- Se genera al publicar. El borrador puede cambiar sin afectar lo publicado.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_publicaciones (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id       INT UNSIGNED NOT NULL,
    resumen        TEXT NOT NULL,                 -- copia para el índice y la búsqueda
    imagen_portada VARCHAR(255) NULL,             -- copia para las tarjetas del índice
    contenido      LONGTEXT NOT NULL,             -- JSON: ficha, materias, instalaciones, galería, documentos
    publicado_por  INT UNSIGNED NULL,
    publicado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_car_publicaciones_ficha (ficha_id),

    CONSTRAINT fk_car_publicaciones_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_car_publicaciones_usuario
        FOREIGN KEY (publicado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- car_revisiones · bitácora de cambios de estado y observaciones
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS car_revisiones (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ficha_id        INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NULL,
    estado_anterior ENUM('borrador','revision','publicado') NOT NULL,
    estado_nuevo    ENUM('borrador','revision','publicado') NOT NULL,
    observaciones   TEXT NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_car_revisiones_ficha (ficha_id),

    CONSTRAINT fk_car_revisiones_ficha
        FOREIGN KEY (ficha_id) REFERENCES car_fichas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_car_revisiones_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
