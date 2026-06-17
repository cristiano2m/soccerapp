-- ══════════════════════════════════════════════
-- SoccerAPP — Esquema de Base de Datos
-- ══════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ══════════════════════════════════════════════
-- USUARIOS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('super_admin','organizer','referee') NOT NULL DEFAULT 'referee',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ══════════════════════════════════════════════
-- TORNEOS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS torneos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    anio YEAR NOT NULL,
    categoria VARCHAR(60) DEFAULT 'Mayor',
    cancha_principal VARCHAR(100),
    pts_victoria TINYINT DEFAULT 3,
    pts_empate TINYINT DEFAULT 1,
    pts_derrota TINYINT DEFAULT 0,
    estado ENUM('borrador','activo','finalizado') DEFAULT 'borrador',
    descripcion TEXT,
    logo_url VARCHAR(255),
    color_primario VARCHAR(7) DEFAULT '#FFD600',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ══════════════════════════════════════════════
-- EQUIPOS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS equipos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    abreviatura VARCHAR(4),
    color_hex VARCHAR(7) DEFAULT '#3B9EFF',
    delegado VARCHAR(100),
    telefono VARCHAR(20),
    logo_url VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
    INDEX idx_torneo (torneo_id)
);

-- ══════════════════════════════════════════════
-- TORNEO_USUARIOS (roles por torneo)
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS torneo_usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    rol ENUM('organizer','referee') NOT NULL DEFAULT 'organizer',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_torneo_usuario (torneo_id, usuario_id)
);

-- ══════════════════════════════════════════════
-- CANCHAS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS canchas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_cancha_torneo (torneo_id, nombre)
);

-- ══════════════════════════════════════════════
-- JUGADORES
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS jugadores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    posicion ENUM('Portero','Defensa','Mediocampista','Delantero') NOT NULL,
    cedula VARCHAR(20),
    foto_url VARCHAR(255),
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
    INDEX idx_equipo (equipo_id),
    UNIQUE KEY uq_numero_equipo (equipo_id, numero)
);

-- ══════════════════════════════════════════════
-- JORNADAS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS jornadas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    torneo_id INT UNSIGNED NOT NULL,
    numero TINYINT UNSIGNED NOT NULL,
    fecha DATE,
    nombre VARCHAR(60),
    FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_jornada_torneo (torneo_id, numero)
);

-- ══════════════════════════════════════════════
-- PARTIDOS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS partidos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    jornada_id INT UNSIGNED NOT NULL,
    torneo_id INT UNSIGNED NOT NULL,
    equipo_local_id INT UNSIGNED NOT NULL,
    equipo_visita_id INT UNSIGNED NOT NULL,
    cancha VARCHAR(100),
    hora TIME,
    estado ENUM('programado','en_curso','finalizado','suspendido','wo') DEFAULT 'programado',
    arbitro_id INT UNSIGNED,
    arbitro_id2 INT UNSIGNED,
    arbitro_id3 INT UNSIGNED,
    arbitro_habilitado TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (jornada_id) REFERENCES jornadas(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_local_id) REFERENCES equipos(id),
    FOREIGN KEY (equipo_visita_id) REFERENCES equipos(id),
    FOREIGN KEY (arbitro_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (arbitro_id2) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (arbitro_id3) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_jornada (jornada_id),
    INDEX idx_torneo_estado (torneo_id, estado)
);

-- ══════════════════════════════════════════════
-- RESULTADOS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS resultados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partido_id INT UNSIGNED NOT NULL UNIQUE,
    goles_local TINYINT UNSIGNED DEFAULT 0,
    goles_visita TINYINT UNSIGNED DEFAULT 0,
    wo_local TINYINT(1) DEFAULT 0,
    wo_visita TINYINT(1) DEFAULT 0,
    observaciones TEXT,
    registrado_por INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- ══════════════════════════════════════════════
-- GOLES
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS goles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partido_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NOT NULL,
    equipo_id INT UNSIGNED NOT NULL,
    minuto TINYINT UNSIGNED,
    tipo ENUM('normal','penalti','autogol') DEFAULT 'normal',
    FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id),
    INDEX idx_partido (partido_id)
);

-- ══════════════════════════════════════════════
-- TARJETAS
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS tarjetas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partido_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NOT NULL,
    tipo ENUM('amarilla','roja','doble_amarilla') NOT NULL,
    minuto TINYINT UNSIGNED,
    FOREIGN KEY (partido_id) REFERENCES partidos(id) ON DELETE CASCADE,
    FOREIGN KEY (jugador_id) REFERENCES jugadores(id)
);

-- ══════════════════════════════════════════════
-- SEGURIDAD + API
-- ══════════════════════════════════════════════
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(150),
    intentado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip, intentado_at)
);

CREATE TABLE IF NOT EXISTS api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    nombre VARCHAR(100),
    permisos SET('read','write') DEFAULT 'read',
    activo TINYINT(1) DEFAULT 1,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS imagenes_sociales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('proxima_fecha','resultados','posiciones') NOT NULL,
    torneo_id INT UNSIGNED NOT NULL,
    jornada_id INT UNSIGNED,
    prompt_ia TEXT,
    imagen_url VARCHAR(255),
    generado_por INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

SET FOREIGN_KEY_CHECKS = 1;
