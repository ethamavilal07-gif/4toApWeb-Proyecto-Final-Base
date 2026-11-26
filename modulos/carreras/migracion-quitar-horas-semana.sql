-- CAR · Quita el campo "Horas por semana" de las materias.
-- Se puede ejecutar varias veces sin problema.
ALTER TABLE car_materias DROP COLUMN IF EXISTS horas_semana;
