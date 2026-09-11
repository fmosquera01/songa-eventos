-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         12.3.2-MariaDB - MariaDB Server
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para songa_eventos
DROP DATABASE IF EXISTS `songa_eventos`;
CREATE DATABASE IF NOT EXISTS `songa_eventos` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `songa_eventos`;

-- Volcando estructura para tabla songa_eventos.auditoria
DROP TABLE IF EXISTS `auditoria`;
CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `modulo` varchar(100) DEFAULT NULL,
  `registro_id` bigint(20) unsigned DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_usuario` (`usuario_id`),
  KEY `idx_auditoria_fecha` (`fecha_hora`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.auditoria: ~0 rows (aproximadamente)
DELETE FROM `auditoria`;

-- Volcando estructura para tabla songa_eventos.colaborador_campos
DROP TABLE IF EXISTS `colaborador_campos`;
CREATE TABLE IF NOT EXISTS `colaborador_campos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `colaborador_id` bigint(20) unsigned NOT NULL,
  `campo_id` bigint(20) unsigned NOT NULL,
  `valor_texto` text DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_colaborador_campo` (`colaborador_id`,`campo_id`),
  KEY `fk_cc_campo` (`campo_id`),
  CONSTRAINT `fk_cc_campo` FOREIGN KEY (`campo_id`) REFERENCES `evento_campos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `evento_colaboradores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.colaborador_campos: ~0 rows (aproximadamente)
DELETE FROM `colaborador_campos`;

-- Volcando estructura para tabla songa_eventos.eventos
DROP TABLE IF EXISTS `eventos`;
CREATE TABLE IF NOT EXISTS `eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(50) NOT NULL,
  `fecha_evento` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `estado` enum('BORRADOR','ACTIVO','FINALIZADO','CANCELADO') NOT NULL DEFAULT 'BORRADOR',
  `creado_por` bigint(20) unsigned NOT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `validar_estado` tinyint(1) NOT NULL DEFAULT 1,
  `permitir_duplicado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_eventos_usuario` (`creado_por`),
  CONSTRAINT `fk_eventos_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.eventos: ~0 rows (aproximadamente)
DELETE FROM `eventos`;

-- Volcando estructura para tabla songa_eventos.evento_campos
DROP TABLE IF EXISTS `evento_campos`;
CREATE TABLE IF NOT EXISTS `evento_campos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) unsigned NOT NULL,
  `nombre_original` varchar(150) NOT NULL,
  `nombre_campo` varchar(100) NOT NULL,
  `tipo_dato` enum('TEXTO','NUMERO','DECIMAL','FECHA','BOOLEANO') NOT NULL DEFAULT 'TEXTO',
  `es_requerido` tinyint(1) NOT NULL DEFAULT 0,
  `es_visible` tinyint(1) NOT NULL DEFAULT 1,
  `es_busqueda` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento_campo` (`evento_id`,`nombre_campo`),
  CONSTRAINT `fk_campos_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.evento_campos: ~0 rows (aproximadamente)
DELETE FROM `evento_campos`;

-- Volcando estructura para tabla songa_eventos.evento_colaboradores
DROP TABLE IF EXISTS `evento_colaboradores`;
CREATE TABLE IF NOT EXISTS `evento_colaboradores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) unsigned NOT NULL,
  `cod` varchar(50) NOT NULL,
  `cedula` varchar(30) DEFAULT NULL,
  `apellidos_nombres` varchar(200) NOT NULL,
  `area` varchar(150) DEFAULT NULL,
  `empresa` varchar(150) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `fila_excel` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento_cod` (`evento_id`,`cod`),
  KEY `idx_evento_cedula` (`evento_id`,`cedula`),
  KEY `idx_evento_nombre` (`evento_id`,`apellidos_nombres`),
  CONSTRAINT `fk_colaborador_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31521 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.evento_colaboradores: ~0 rows (aproximadamente)
DELETE FROM `evento_colaboradores`;

-- Volcando estructura para tabla songa_eventos.registros
DROP TABLE IF EXISTS `registros`;
CREATE TABLE IF NOT EXISTS `registros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) unsigned NOT NULL,
  `colaborador_id` bigint(20) unsigned NOT NULL,
  `tipo_registro` varchar(50) NOT NULL DEFAULT 'ASISTENCIA',
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario_id` bigint(20) unsigned NOT NULL,
  `metodo` enum('CODIGO','CEDULA','MANUAL') NOT NULL DEFAULT 'MANUAL',
  `dispositivo` varchar(150) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `observacion` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_registro_evento` (`evento_id`),
  KEY `idx_registro_colaborador` (`colaborador_id`),
  KEY `idx_registro_fecha` (`fecha_hora`),
  KEY `fk_registro_usuario` (`usuario_id`),
  CONSTRAINT `fk_registro_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `evento_colaboradores` (`id`),
  CONSTRAINT `fk_registro_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`),
  CONSTRAINT `fk_registro_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.registros: ~0 rows (aproximadamente)
DELETE FROM `registros`;

-- Volcando estructura para tabla songa_eventos.sorteos
DROP TABLE IF EXISTS `sorteos`;
CREATE TABLE IF NOT EXISTS `sorteos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_id` bigint(20) unsigned NOT NULL,
  `creado_por` bigint(20) unsigned NOT NULL,
  `estado` enum('ACTIVO','FINALIZADO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sorteo_evento` (`evento_id`),
  KEY `idx_sorteo_usuario` (`creado_por`),
  CONSTRAINT `fk_sorteos_evento` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sorteos_usuario` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.sorteos: ~0 rows (aproximadamente)
DELETE FROM `sorteos`;

-- Volcando estructura para tabla songa_eventos.sorteo_excluidos
DROP TABLE IF EXISTS `sorteo_excluidos`;
CREATE TABLE IF NOT EXISTS `sorteo_excluidos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sorteo_id` bigint(20) unsigned NOT NULL,
  `colaborador_id` bigint(20) unsigned NOT NULL,
  `motivo` enum('NO_PRESENTE','GANADOR') NOT NULL,
  `premio_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `observacion` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sorteo_colaborador` (`sorteo_id`,`colaborador_id`),
  KEY `idx_excluido_colaborador` (`colaborador_id`),
  KEY `idx_excluido_premio` (`premio_id`),
  KEY `fk_excluido_usuario` (`usuario_id`),
  CONSTRAINT `fk_excluido_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `evento_colaboradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_excluido_premio` FOREIGN KEY (`premio_id`) REFERENCES `sorteo_premios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_excluido_sorteo` FOREIGN KEY (`sorteo_id`) REFERENCES `sorteos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_excluido_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.sorteo_excluidos: ~0 rows (aproximadamente)
DELETE FROM `sorteo_excluidos`;

-- Volcando estructura para tabla songa_eventos.sorteo_ganadores
DROP TABLE IF EXISTS `sorteo_ganadores`;
CREATE TABLE IF NOT EXISTS `sorteo_ganadores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sorteo_id` bigint(20) unsigned NOT NULL,
  `premio_id` bigint(20) unsigned NOT NULL,
  `colaborador_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ganador_evento_colaborador` (`sorteo_id`,`colaborador_id`),
  UNIQUE KEY `uk_ganador_premio_colaborador` (`premio_id`,`colaborador_id`),
  KEY `idx_ganador_colaborador` (`colaborador_id`),
  KEY `fk_ganador_usuario` (`usuario_id`),
  CONSTRAINT `fk_ganador_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `evento_colaboradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ganador_premio` FOREIGN KEY (`premio_id`) REFERENCES `sorteo_premios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ganador_sorteo` FOREIGN KEY (`sorteo_id`) REFERENCES `sorteos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ganador_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.sorteo_ganadores: ~0 rows (aproximadamente)
DELETE FROM `sorteo_ganadores`;

-- Volcando estructura para tabla songa_eventos.sorteo_intentos
DROP TABLE IF EXISTS `sorteo_intentos`;
CREATE TABLE IF NOT EXISTS `sorteo_intentos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sorteo_id` bigint(20) unsigned NOT NULL,
  `premio_id` bigint(20) unsigned NOT NULL,
  `colaborador_id` bigint(20) unsigned NOT NULL,
  `resultado` enum('NO_PRESENTE','GANADOR') NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_intento_sorteo` (`sorteo_id`),
  KEY `idx_intento_premio` (`premio_id`),
  KEY `idx_intento_colaborador` (`colaborador_id`),
  KEY `fk_intento_usuario` (`usuario_id`),
  CONSTRAINT `fk_intento_colaborador` FOREIGN KEY (`colaborador_id`) REFERENCES `evento_colaboradores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_intento_premio` FOREIGN KEY (`premio_id`) REFERENCES `sorteo_premios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_intento_sorteo` FOREIGN KEY (`sorteo_id`) REFERENCES `sorteos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_intento_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.sorteo_intentos: ~0 rows (aproximadamente)
DELETE FROM `sorteo_intentos`;

-- Volcando estructura para tabla songa_eventos.sorteo_premios
DROP TABLE IF EXISTS `sorteo_premios`;
CREATE TABLE IF NOT EXISTS `sorteo_premios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sorteo_id` bigint(20) unsigned NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `posicion` int(11) NOT NULL DEFAULT 1,
  `estado` enum('PENDIENTE','EN_PROCESO','COMPLETADO') NOT NULL DEFAULT 'PENDIENTE',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_premio_sorteo` (`sorteo_id`),
  CONSTRAINT `fk_premio_sorteo` FOREIGN KEY (`sorteo_id`) REFERENCES `sorteos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.sorteo_premios: ~0 rows (aproximadamente)
DELETE FROM `sorteo_premios`;

-- Volcando estructura para tabla songa_eventos.usuarios
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('ADMIN','OPERADOR') NOT NULL DEFAULT 'OPERADOR',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `usuario_login` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uk_usuarios_login` (`usuario_login`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Volcando datos para la tabla songa_eventos.usuarios: ~1 rows (aproximadamente)
DELETE FROM `usuarios`;
INSERT INTO `usuarios` (`id`, `usuario`, `nombre`, `password_hash`, `rol`, `activo`, `creado_en`, `actualizado_en`, `usuario_login`) VALUES
	(1, 'admin', 'Administrador', '$2y$10$sfa8iZeVH8oZr7HGTTTEQOzsSu7FzpxjRStdKVL2zIjK00SYcU9Tq', 'ADMIN', 1, '2026-08-24 15:52:04', '2026-09-03 20:29:25', 'admin');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
