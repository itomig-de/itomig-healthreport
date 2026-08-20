<?php
declare(strict_types=1);

namespace Itomig\iTop\Extension\HealthReport\Service;

use RuntimeException;

/**
 * Prueft einen per HTTP hochgeladenen ZIP-Upload (Fehlercode, Herkunft,
 * Dateiendung) und liefert den temporaeren Pfad der Datei.
 *
 * Gemeinsame Validierungslogik fuer den Konsolen-Upload
 * (HealthReportController) und den Portal-Upload
 * (HealthReportUploadBrickController) — beide erwarten ein Feld "zip" in
 * $_FILES. Muster identisch zur ehemaligen web/process.php.
 */
class ZipUploadValidator
{
    private const UPLOAD_ERROR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE   => 'Datei überschreitet upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'Datei überschreitet MAX_FILE_SIZE im Formular.',
        UPLOAD_ERR_PARTIAL    => 'Datei wurde nur teilweise hochgeladen.',
        UPLOAD_ERR_NO_FILE    => 'Keine Datei hochgeladen.',
        UPLOAD_ERR_NO_TMP_DIR => 'Kein tmp-Verzeichnis verfügbar.',
        UPLOAD_ERR_CANT_WRITE => 'Schreibfehler beim Upload.',
        UPLOAD_ERR_EXTENSION  => 'Upload durch PHP-Extension blockiert.',
    ];

    /**
     * @param string $sFieldName Name des Datei-Feldes in $_FILES (Standard: "zip")
     * @throws RuntimeException Wenn der Upload fehlerhaft, nicht http-basiert oder keine .zip-Datei ist.
     */
    public function validate(string $sFieldName = 'zip'): string
    {
        $iErrorCode = $_FILES[$sFieldName]['error'] ?? UPLOAD_ERR_NO_FILE;
        if (empty($_FILES[$sFieldName]['tmp_name']) || $iErrorCode !== UPLOAD_ERR_OK) {
            $sReason = self::UPLOAD_ERROR_MESSAGES[$iErrorCode] ?? 'unbekannter Fehler';
            throw new RuntimeException(\Dict::Format('Itomig:HealthReport:Error:UploadFailed', $sReason));
        }

        $sTmpPath = $_FILES[$sFieldName]['tmp_name'];
        if (!is_uploaded_file($sTmpPath)) {
            throw new RuntimeException(\Dict::S('Itomig:HealthReport:Error:NotUploaded'));
        }

        $sOrigName = (string) $_FILES[$sFieldName]['name'];
        $sExt = strtolower(pathinfo($sOrigName, PATHINFO_EXTENSION));
        if ($sExt !== 'zip') {
            throw new RuntimeException(\Dict::S('Itomig:HealthReport:Error:NotZip'));
        }

        return $sTmpPath;
    }
}
