<?php
declare(strict_types=1);

// Configuración de Moodle
const MOODLE_URL = 'https://tu-moodle';
const MOODLE_TOKEN = 'tu-token';
const MOODLE_REST_FORMAT = 'json';

// Configuración de la aplicación
const APP_NAME = 'Gestor BBB Moodle';
const APP_VERSION = '1.0.0';

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de errores para desarrollo
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

// Configuración de sesión
session_start();

// Habilitar almacenamiento en búfer de salida
ob_start();

// Configuración de rutas
define('BASE_PATH', dirname(__FILE__));
define('CLASSES_PATH', BASE_PATH . '/classes');
define('STYLES_PATH', BASE_PATH . '/styles');

// Cargar clases
require_once CLASSES_PATH . '/MoodleAPI.php';
require_once CLASSES_PATH . '/BBBManager.php';