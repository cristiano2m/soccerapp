-- ══════════════════════════════════════════════
-- SoccerAPP — Datos de prueba (seed)
-- ══════════════════════════════════════════════

-- Usuarios (password: admin123)
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Super Admin', 'admin@torneo.com', '$2y$12$/cqrNKJKyWS2q7985ofNjufakvdpuPSQP70SOmTofUhF91pey/cda', 'super_admin'),
('Árbitro Demo', 'arbitro1@torneo.com', '$2y$12$/cqrNKJKyWS2q7985ofNjufakvdpuPSQP70SOmTofUhF91pey/cda', 'referee'),
('Árbitro Demo 2', 'arbitro2@torneo.com', '$2y$12$/cqrNKJKyWS2q7985ofNjufakvdpuPSQP70SOmTofUhF91pey/cda', 'referee'),
('Árbitro Demo 3', 'arbitro3@torneo.com', '$2y$12$/cqrNKJKyWS2q7985ofNjufakvdpuPSQP70SOmTofUhF91pey/cda', 'referee');

-- Torneo activo
INSERT INTO torneos (nombre, anio, categoria, cancha_principal, pts_victoria, pts_empate, pts_derrota, estado, descripcion, color_primario) VALUES
('Copa SoccerAPP 2026', 2026, 'Mayor', 'Cancha Municipal', 3, 1, 0, 'activo', 'Torneo de fútbol amateur de la temporada 2026.', '#FFD600');

SET @torneo_id = LAST_INSERT_ID();

-- Equipos
INSERT INTO equipos (torneo_id, nombre, abreviatura, color_hex, delegado, telefono) VALUES
(@torneo_id, 'Tigres FC', 'TIG', '#FF6B00', 'Carlos Pérez', '0991111111'),
(@torneo_id, 'Águilas FC', 'AGU', '#1E88E5', 'Marta Gómez', '0992222222'),
(@torneo_id, 'Halcones FC', 'HAL', '#43A047', 'Jorge Ruiz', '0993333333'),
(@torneo_id, 'Leones FC', 'LEO', '#FFD600', 'Ana Torres', '0994444444'),
(@torneo_id, 'Panteras FC', 'PAN', '#8E24AA', 'Luis Vega', '0995555555'),
(@torneo_id, 'Cóndores FC', 'CON', '#E53935', 'Diego Salas', '0996666666');

-- Canchas
INSERT INTO canchas (torneo_id, nombre, activo) VALUES
(@torneo_id, 'Cancha 1', 1),
(@torneo_id, 'Cancha 2', 1);

-- Usuarios por torneo (árbitros del torneo demo)
INSERT INTO torneo_usuarios (torneo_id, usuario_id, rol)
SELECT @torneo_id, id, 'referee' FROM usuarios WHERE email IN ('arbitro1@torneo.com','arbitro2@torneo.com','arbitro3@torneo.com');

-- Jugadores Tigres FC
INSERT INTO jugadores (equipo_id, nombre, numero, posicion) VALUES
((SELECT id FROM equipos WHERE nombre='Tigres FC' AND torneo_id=@torneo_id), 'Luis Fernández', 1, 'Portero'),
((SELECT id FROM equipos WHERE nombre='Tigres FC' AND torneo_id=@torneo_id), 'Pedro Castillo', 4, 'Defensa'),
((SELECT id FROM equipos WHERE nombre='Tigres FC' AND torneo_id=@torneo_id), 'Mario Suárez', 8, 'Mediocampista'),
((SELECT id FROM equipos WHERE nombre='Tigres FC' AND torneo_id=@torneo_id), 'Erick Mora', 9, 'Delantero'),
((SELECT id FROM equipos WHERE nombre='Tigres FC' AND torneo_id=@torneo_id), 'Luis Andrade', 10, 'Delantero');

-- Jugadores Águilas FC
INSERT INTO jugadores (equipo_id, nombre, numero, posicion) VALUES
((SELECT id FROM equipos WHERE nombre='Águilas FC' AND torneo_id=@torneo_id), 'Andrés Paredes', 1, 'Portero'),
((SELECT id FROM equipos WHERE nombre='Águilas FC' AND torneo_id=@torneo_id), 'Marco Jiménez', 3, 'Defensa'),
((SELECT id FROM equipos WHERE nombre='Águilas FC' AND torneo_id=@torneo_id), 'Pablo Reyes', 6, 'Mediocampista'),
((SELECT id FROM equipos WHERE nombre='Águilas FC' AND torneo_id=@torneo_id), 'Sebastián Cruz', 7, 'Delantero'),
((SELECT id FROM equipos WHERE nombre='Águilas FC' AND torneo_id=@torneo_id), 'Andrés Loor', 11, 'Delantero');

-- Jugadores Halcones FC
INSERT INTO jugadores (equipo_id, nombre, numero, posicion) VALUES
((SELECT id FROM equipos WHERE nombre='Halcones FC' AND torneo_id=@torneo_id), 'Iván Soto', 1, 'Portero'),
((SELECT id FROM equipos WHERE nombre='Halcones FC' AND torneo_id=@torneo_id), 'Bryan Zambrano', 2, 'Defensa'),
((SELECT id FROM equipos WHERE nombre='Halcones FC' AND torneo_id=@torneo_id), 'Daniel Vera', 5, 'Mediocampista'),
((SELECT id FROM equipos WHERE nombre='Halcones FC' AND torneo_id=@torneo_id), 'Cristian Solís', 9, 'Delantero'),
((SELECT id FROM equipos WHERE nombre='Halcones FC' AND torneo_id=@torneo_id), 'Fernando Burgos', 10, 'Delantero');

-- Jugadores Leones FC
INSERT INTO jugadores (equipo_id, nombre, numero, posicion) VALUES
((SELECT id FROM equipos WHERE nombre='Leones FC' AND torneo_id=@torneo_id), 'Roberto Idrovo', 1, 'Portero'),
((SELECT id FROM equipos WHERE nombre='Leones FC' AND torneo_id=@torneo_id), 'Henry Castro', 4, 'Defensa'),
((SELECT id FROM equipos WHERE nombre='Leones FC' AND torneo_id=@torneo_id), 'Walter Tello', 8, 'Mediocampista'),
((SELECT id FROM equipos WHERE nombre='Leones FC' AND torneo_id=@torneo_id), 'Jefferson Macías', 9, 'Delantero'),
((SELECT id FROM equipos WHERE nombre='Leones FC' AND torneo_id=@torneo_id), 'Kevin Alvarado', 11, 'Delantero');

-- Jugadores Panteras FC
INSERT INTO jugadores (equipo_id, nombre, numero, posicion) VALUES
((SELECT id FROM equipos WHERE nombre='Panteras FC' AND torneo_id=@torneo_id), 'Wilson Quinde', 1, 'Portero'),
((SELECT id FROM equipos WHERE nombre='Panteras FC' AND torneo_id=@torneo_id), 'Ronald Pico', 3, 'Defensa'),
((SELECT id FROM equipos WHERE nombre='Panteras FC' AND torneo_id=@torneo_id), 'Jhon Mendoza', 6, 'Mediocampista'),
((SELECT id FROM equipos WHERE nombre='Panteras FC' AND torneo_id=@torneo_id), 'Carlos Espinoza', 7, 'Delantero'),
((SELECT id FROM equipos WHERE nombre='Panteras FC' AND torneo_id=@torneo_id), 'Steven Bone', 10, 'Delantero');

-- Jugadores Cóndores FC
INSERT INTO jugadores (equipo_id, nombre, numero, posicion) VALUES
((SELECT id FROM equipos WHERE nombre='Cóndores FC' AND torneo_id=@torneo_id), 'Jorge Aguirre', 1, 'Portero'),
((SELECT id FROM equipos WHERE nombre='Cóndores FC' AND torneo_id=@torneo_id), 'Patricio León', 2, 'Defensa'),
((SELECT id FROM equipos WHERE nombre='Cóndores FC' AND torneo_id=@torneo_id), 'Edison Vélez', 5, 'Mediocampista'),
((SELECT id FROM equipos WHERE nombre='Cóndores FC' AND torneo_id=@torneo_id), 'Galo Pinargote', 9, 'Delantero'),
((SELECT id FROM equipos WHERE nombre='Cóndores FC' AND torneo_id=@torneo_id), 'Néstor Cedeño', 11, 'Delantero');
