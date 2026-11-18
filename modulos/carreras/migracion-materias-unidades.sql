-- =============================================================================
-- CAR · Migración: materias con propósito, competencia y unidades
--
-- PARA QUIEN YA TIENE la base creada con el esquema anterior.
-- Ejecutar UNA sola vez (en phpMyAdmin: pestaña SQL, o por consola):
--     mysql -u root explorador_vocacional < migracion-materias-unidades.sql
--
-- Quien instale desde cero NO la necesita: esquema.sql ya incluye todo.
-- Compatible con MariaDB 10.4 (XAMPP) y MySQL 8.
-- =============================================================================

USE explorador_vocacional;
SET NAMES utf8mb4;

-- 1) "Descripción" pasa a ser "Propósito de aprendizaje de la asignatura"
ALTER TABLE car_materias
    CHANGE COLUMN descripcion proposito TEXT NULL;

-- 2) Campos nuevos de la materia
ALTER TABLE car_materias
    ADD COLUMN competencia   TEXT NULL AFTER proposito,
    ADD COLUMN horas_semana  TINYINT UNSIGNED NULL AFTER horas_totales,
    ADD COLUMN num_unidades  TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER competencia;

-- 3) Unidades de cada materia (de 1 a 5, las valida la aplicación)
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
