<?php
class LangHandler {
    private $translations;
    private $language;
    private $lastError = null; // Para almacenar mensajes de error detallados

    /**
     * Constructor de la clase LangHandler.
     * Carga las traducciones para el idioma especificado.
     *
     * @param string $language El código de idioma (ej. 'es', 'en').
     */
    public function __construct($language = 'es') {
        $this->language = $language;
        $this->translations = $this->loadTranslations();
    }

    /**
     * Carga las traducciones desde el archivo INI correspondiente al idioma.
     * Registra errores si el archivo no se encuentra o no se puede analizar.
     *
     * @return array Un array asociativo con las traducciones cargadas, o un array vacío en caso de error.
     */
    private function loadTranslations() {
        // La ruta del archivo de idioma. __DIR__ se refiere al directorio donde se encuentra LangHandler.php
        // Se asume que los archivos de traducción tienen la extensión .ini (o .in si es lo que usas).
        $filePath = __DIR__ . "/{$this->language}.ini"; // Cambiado a .ini para una convención más común, ajusta si tus archivos son .in
        error_log("LangHandler: Intentando cargar el archivo de idioma: {$filePath}");

        // Verificar si el archivo existe
        if (!file_exists($filePath)) {
            $this->lastError = "LangHandler: Archivo de idioma no encontrado: {$filePath}. Por favor, verifica la ruta y el nombre del archivo.";
            error_log($this->lastError);
            return [];
        }

        // Cargar y parsear el archivo INI
        // parse_ini_file() debería manejar UTF-8 si el archivo está guardado correctamente en UTF-8 sin BOM.
        $translations = parse_ini_file($filePath, true);

        // Verificar si se cargó correctamente
        if ($translations === false) {
            $this->lastError = "LangHandler: Error al parsear el archivo de idioma: {$filePath}. Verifica el formato INI y los caracteres especiales.";
            error_log($this->lastError);
            return [];
        }

        error_log("LangHandler: Traducciones cargadas exitosamente para el idioma: {$this->language} desde {$filePath}");
        return $translations;
    }

    /**
     * Obtiene la traducción para una sección y clave dadas.
     * Se ha añadido lógica para decodificar secuencias de escape Unicode (\uXXXX)
     * si están presentes en la cadena de traducción.
     *
     * @param string $section La sección en el archivo INI (ej. 'DASHBOARD').
     * @param string $key La clave de traducción dentro de la sección (ej. 'MAIL_LIST').
     * @param mixed $default El valor por defecto a devolver si la traducción no se encuentra.
     * @return string|null La traducción o el valor por defecto.
     */
    public function getTranslation($section, $key, $default = null) {
        // Verificar si las traducciones están cargadas o están vacías
        if (!is_array($this->translations) || empty($this->translations)) {
            error_log("LangHandler: Traducciones no cargadas o vacías para el idioma {$this->language}. Último error de carga: " . ($this->lastError ?? 'Ninguno.'));
            return $default;
        }

        // Verificar si la sección existe
        if (!isset($this->translations[$section])) {
            error_log("LangHandler: Sección de traducción no encontrada: '{$section}' en el idioma '{$this->language}'.");
            return $default;
        }

        // Verificar si la clave existe dentro de la sección
        if (!isset($this->translations[$section][$key])) {
            error_log("LangHandler: Clave de traducción no encontrada: '{$section}.{$key}' en el idioma '{$this->language}'.");
            return $default;
        }

        $value = $this->translations[$section][$key];

        // Intenta decodificar secuencias de escape Unicode si están presentes.
        // Esto es útil si los archivos INI contienen literales como 'c\u00f3digos'.
        // Se envuelve el valor en comillas dobles para que json_decode lo trate como una cadena JSON válida.
        if (is_string($value) && preg_match('/\\\\u([0-9a-fA-F]{4})/', $value)) {
            $decodedValue = json_decode('"' . $value . '"', true);
            // Verifica si la decodificación fue exitosa y no hubo errores de JSON.
            if (json_last_error() === JSON_ERROR_NONE && $decodedValue !== null) {
                return $decodedValue;
            }
        }

        return $value;
    }

    /**
     * Verifica si una traducción existe para una sección y clave dadas.
     *
     * @param string $section La sección.
     * @param string $key La clave.
     * @return bool True si la traducción existe, false en caso contrario.
     */
    public function hasTranslation($section, $key) {
        return isset($this->translations[$section][$key]);
    }

    /**
     * Obtiene el último mensaje de error ocurrido durante la carga de traducciones.
     *
     * @return string|null El último mensaje de error, o null si no hubo errores.
     */
    public function getLastError() {
        return $this->lastError;
    }

    /**
     * Método de depuración: Obtiene todas las traducciones cargadas.
     * ¡No usar en producción para evitar exponer datos sensibles!
     *
     * @return array El array de traducciones cargadas.
     */
    public function getLoadedTranslationsDebug() {
        return $this->translations;
    }
}
?>
