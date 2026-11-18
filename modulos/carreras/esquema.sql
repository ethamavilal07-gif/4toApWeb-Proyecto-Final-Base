-- =============================================================================
-- MÓDULO CAR · Explorador de carreras · esquema.sql
-- Equipo A-1 · Prefijo de tablas: car_
-- Se ejecuta DESPUÉS de sql/01-nucleo.sql y de herramientas/crear-usuarios.php
-- Referencias externas: solo usuarios y carreras.
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
    horas_semana  TINYINT UNSIGNED NULL,
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
    orden           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY ix_car_instalaciones_ficha (ficha_id),

    CONSTRAINT fk_car_instalaciones_ficha
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


-- =============================================================================
-- DATOS DE PRUEBA
-- Se buscan carreras y usuarios por clave/correo, no por número de id.
-- =============================================================================

-- TIC: publicada (la visitará cualquiera)
INSERT INTO car_fichas (carrera_id, resumen, perfil_egreso, campo_laboral, video_url, estado, actualizado_por)
SELECT c.id,
       'Forma profesionales que desarrollan, implementan y administran soluciones de software, redes e infraestructura para las organizaciones.',
       'El egresado diseña aplicaciones web y móviles, administra bases de datos y redes, y gestiona proyectos de tecnología con enfoque en la innovación digital.',
       'Desarrollo de software, administración de infraestructura, ciberseguridad, consultoría en TI, emprendimiento tecnológico y áreas de sistemas en cualquier empresa.',
       NULL, 'publicado',
       (SELECT id FROM usuarios WHERE correo = 'tic@utbb.mx')
  FROM carreras c WHERE c.clave = 'TIC';

-- MED: en revisión (aparece en la bandeja de coordinación)
INSERT INTO car_fichas (carrera_id, resumen, perfil_egreso, campo_laboral, estado, actualizado_por)
SELECT c.id,
       'Formación médica integral con práctica clínica desde los primeros ciclos.',
       'El egresado diagnostica, trata y previene enfermedades con ética y sentido humano.',
       'Hospitales, clínicas, salud pública, investigación y consultorio propio.',
       'revision',
       (SELECT id FROM usuarios WHERE correo = 'med@utbb.mx')
  FROM carreras c WHERE c.clave = 'MED';

-- GAS: devuelta a borrador con observaciones
INSERT INTO car_fichas (carrera_id, resumen, perfil_egreso, campo_laboral, estado, actualizado_por)
SELECT c.id,
        'Cocina profesional, administración de alimentos y bebidas e innovación culinaria.',
        'Pendiente de completar.',
        'Restaurantes, hoteles, banquetes y emprendimiento gastronómico.',
        'borrador',
        (SELECT id FROM usuarios WHERE correo = 'gas@utbb.mx')
    FROM carreras c WHERE c.clave = 'GAS';

-- Materias de TIC
INSERT INTO car_materias (ficha_id, cuatrimestre, clave_materia, nombre, horas_totales, proposito, orden)
SELECT f.id, m.cuatri, m.clave, m.nombre, m.horas, m.descr, m.orden
    FROM car_fichas f
    JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'TIC'
    JOIN (
        SELECT 1 AS cuatri, 'TIC-101' AS clave, 'Fundamentos de programación' AS nombre, 90 AS horas, 'Lógica y primeros programas.' AS descr, 1 AS orden
        UNION ALL SELECT 1, 'TIC-102', 'Matemáticas para ingeniería', 75, NULL, 2
        UNION ALL SELECT 2, 'TIC-201', 'Programación orientada a objetos', 90, NULL, 1
        UNION ALL SELECT 2, 'TIC-202', 'Bases de datos', 90, 'Modelado relacional y SQL.', 2
        UNION ALL SELECT 3, 'TIC-301', 'Estructuras de datos', 90, NULL, 1
        UNION ALL SELECT 3, 'TIC-302', 'Redes de computadoras', 75, NULL, 2
        UNION ALL SELECT 4, 'TIC-401', 'Aplicaciones web', 105, 'Desarrollo front end y back end.', 1
        UNION ALL SELECT 4, 'TIC-402', 'Ingeniería de software', 75, NULL, 2
        UNION ALL SELECT 5, 'TIC-501', 'Desarrollo móvil', 90, NULL, 1
        UNION ALL SELECT 5, 'TIC-502', 'Seguridad informática', 75, NULL, 2
        ) m;

-- Instalaciones de TIC
INSERT INTO car_instalaciones (ficha_id, nombre, descripcion, orden)
SELECT f.id, i.nombre, i.descr, i.orden
  FROM car_fichas f
  JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'TIC'
  JOIN (
        SELECT 'Laboratorio de cómputo' AS nombre, 'Equipos de escritorio para prácticas de programación y bases de datos.' AS descr, 1 AS orden
  UNION ALL SELECT 'Laboratorio de redes', 'Switches, routers y cableado estructurado para prácticas de conectividad.', 2
  UNION ALL SELECT 'Sala de desarrollo', 'Espacio colaborativo para proyectos integradores.', 3
  ) i;

-- Bitácora de revisiones
INSERT INTO car_revisiones (ficha_id, usuario_id, estado_anterior, estado_nuevo, observaciones)
SELECT f.id, (SELECT id FROM usuarios WHERE correo = 'tic@utbb.mx'), 'borrador', 'revision', 'Enviada a revisión.'
  FROM car_fichas f JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'TIC';

INSERT INTO car_revisiones (ficha_id, usuario_id, estado_anterior, estado_nuevo, observaciones)
SELECT f.id, (SELECT id FROM usuarios WHERE correo = 'coordinacion@utbb.mx'), 'revision', 'publicado', 'Contenido aprobado.'
  FROM car_fichas f JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'TIC';

INSERT INTO car_revisiones (ficha_id, usuario_id, estado_anterior, estado_nuevo, observaciones)
SELECT f.id, (SELECT id FROM usuarios WHERE correo = 'med@utbb.mx'), 'borrador', 'revision', 'Enviada a revisión.'
  FROM car_fichas f JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'MED';

INSERT INTO car_revisiones (ficha_id, usuario_id, estado_anterior, estado_nuevo, observaciones)
SELECT f.id, (SELECT id FROM usuarios WHERE correo = 'gas@utbb.mx'), 'borrador', 'revision', 'Enviada a revisión.'
  FROM car_fichas f JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'GAS';

INSERT INTO car_revisiones (ficha_id, usuario_id, estado_anterior, estado_nuevo, observaciones)
SELECT f.id, (SELECT id FROM usuarios WHERE correo = 'coordinacion@utbb.mx'), 'revision', 'borrador',
       'Completa el perfil de egreso y agrega al menos dos instalaciones.'
  FROM car_fichas f JOIN carreras c ON c.id = f.carrera_id AND c.clave = 'GAS';

-- No se siembran filas en car_documentos: el PDF se sube desde el panel
-- (así el archivo real y su registro siempre coinciden).