<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../auth/middleware.php';
require_role(['super_admin']);

$pluginFile = __DIR__ . '/../../wordpress-plugin/soccerapp/soccerapp.php';

if (!file_exists($pluginFile)) {
    http_response_code(404);
    die('Plugin file not found.');
}

if (!class_exists('ZipArchive')) {
    // Fallback: descargar solo el archivo PHP principal
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="soccerapp.php"');
    header('Content-Length: ' . filesize($pluginFile));
    readfile($pluginFile);
    exit;
}

$tmpFile = tempnam(sys_get_temp_dir(), 'soccerapp_plugin_') . '.zip';

$zip = new ZipArchive();
if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('No se pudo crear el archivo ZIP.');
}

$zip->addFile($pluginFile, 'soccerapp/soccerapp.php');

// Agregar assets si existen
$assetsDir = __DIR__ . '/../../wordpress-plugin/soccerapp/assets';
if (is_dir($assetsDir)) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($assetsDir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $relative = 'soccerapp/assets/' . ltrim(str_replace($assetsDir, '', $file->getRealPath()), '/\\');
        $zip->addFile($file->getRealPath(), str_replace('\\', '/', $relative));
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="soccerapp-wordpress-plugin.zip"');
header('Content-Length: ' . filesize($tmpFile));
header('Pragma: no-cache');
header('Expires: 0');
readfile($tmpFile);
unlink($tmpFile);
exit;
