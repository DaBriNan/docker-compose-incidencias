CREATE TABLE incidencias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(100) NOT NULL,
  descripcion TEXT,
  estado ENUM('abierta','en_proceso','resuelta') NOT NULL DEFAULT 'abierta',
  prioridad ENUM('baja','media','alta') NOT NULL DEFAULT 'media',
  creada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  actualizada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_titulo CHECK (CHAR_LENGTH(titulo) >= 3)
) DEFAULT CHARSET=utf8mb4;

INSERT INTO incidencias (titulo, descripcion, estado, prioridad) VALUES
('Proyector sin señal', 'El proyector del aula 3 no detecta la entrada HDMI.', 'abierta', 'media'),
('Red lenta en laboratorio', 'El switch del laboratorio pierde paquetes.', 'en_proceso', 'alta'),
('Cuenta bloqueada', 'Usuario no puede iniciar sesión en el portal.', 'resuelta', 'baja');
