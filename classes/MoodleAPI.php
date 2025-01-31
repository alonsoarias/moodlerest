<?php
declare(strict_types=1);

/**
 * Clase MoodleAPI para interactuar con la API REST de Moodle.
 */
class MoodleAPI {
    private string $baseUrl;
    private string $token;
    private string $restFormat;

    /**
     * Constructor de la clase MoodleAPI.
     *
     * @param string $baseUrl URL base de Moodle.
     * @param string $token Token de autenticación.
     * @param string $restFormat Formato de respuesta (por defecto 'json').
     */
    public function __construct(string $baseUrl, string $token, string $restFormat = 'json') {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
        $this->restFormat = $restFormat;
    }

    /**
     * Valida la conexión con Moodle.
     *
     * @return bool True si la conexión es exitosa, false en caso contrario.
     */
    public function validateConnection(): bool {
        try {
            $this->getSiteInfo();
            return true;
        } catch (Exception $e) {
            error_log("MoodleAPI validateConnection Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene información del sitio.
     *
     * @return array Información del sitio.
     * @throws Exception Si la llamada a la API falla.
     */
    public function getSiteInfo(): array {
        return $this->makeRequest('core_webservice_get_site_info', []);
    }

    /**
     * Obtiene todos los cursos que contienen actividades BBB.
     *
     * @return array Lista de cursos con al menos una actividad BBB.
     * @throws Exception Si la llamada a la API falla.
     */
    public function getCoursesWithBBB(): array {
        $courses = $this->makeRequest('core_course_get_courses', []);
        $courseIds = array_map(fn($course) => $course['id'], $courses);

        $bbbActivities = $this->getAllBBBActivities($courseIds);
        $coursesWithBBB = [];

        // Crear un mapa de cursos con actividades BBB
        $bbbByCourse = [];
        foreach ($bbbActivities['bigbluebuttonbns'] as $bbb) {
            $courseId = $bbb['course'] ?? null;
            if ($courseId) {
                $bbbByCourse[$courseId][] = $bbb;
            }
        }

        // Filtrar solo los cursos que tienen actividades BBB
        foreach ($courses as $course) {
            if (isset($bbbByCourse[$course['id']])) {
                $course['bbb_count'] = count($bbbByCourse[$course['id']]);
                $coursesWithBBB[] = $course;
            }
        }

        return $coursesWithBBB;
    }

    /**
     * Obtiene todas las actividades BBB para los cursos especificados.
     *
     * @param array $courseIds Lista de IDs de cursos.
     * @return array Respuesta de la API con actividades BBB.
     * @throws Exception Si la llamada a la API falla.
     */
    private function getAllBBBActivities(array $courseIds): array {
        if (empty($courseIds)) {
            return ['bigbluebuttonbns' => []];
        }

        return $this->makeRequest('mod_bigbluebuttonbn_get_bigbluebuttonbns_by_courses', [
            'courseids' => $courseIds
        ]);
    }

    /**
     * Obtiene la estructura de actividades BBB de un curso.
     *
     * @param int $courseId ID del curso.
     * @return array Estructura del curso con secciones y actividades BBB.
     * @throws Exception Si la llamada a la API falla.
     */
    public function getBBBActivities(int $courseId): array {
        $courseContents = $this->makeRequest('core_course_get_contents', ['courseid' => $courseId]);
        $bbbActivities = $this->getAllBBBActivities([$courseId]);

        // Indexar actividades BBB por coursemodule.
        $bbbByCourseModule = [];
        foreach ($bbbActivities['bigbluebuttonbns'] as $bbb) {
            $coursemodule = $bbb['coursemodule'] ?? null;
            if ($coursemodule) {
                $bbbByCourseModule[$coursemodule] = $bbb;
            }
        }

        $sections = [];
        foreach ($courseContents as $section) {
            $sectionData = [
                'id' => $section['id'],
                'name' => $section['name'] ?: "Sección {$section['section']}",
                'section' => $section['section'],
                'visible' => $section['visible'],
                'availability' => $this->parseAvailability($section['availability'] ?? null),
                'activities' => []
            ];

            foreach ($section['modules'] as $module) {
                if (isset($bbbByCourseModule[$module['id']])) {
                    $bbb = $bbbByCourseModule[$module['id']];
                    $sectionData['activities'][] = [
                        'id' => $bbb['id'],
                        'name' => $bbb['name'],
                        'intro' => $bbb['intro'] ?? '',
                        'visible' => $module['visible'],
                        'activity_url' => $this->getBBBActivityUrl($courseId, $module['id']),
                        'availability' => $this->parseAvailability($module['availability'] ?? null),
                        'module_info' => $module
                    ];
                }
            }

            if (!empty($sectionData['activities'])) {
                $sections[] = $sectionData;
            }
        }

        return [
            'sections' => $sections,
            'course_url' => $this->getCourseUrl($courseId)
        ];
    }

    /**
     * Obtiene la información de un curso.
     *
     * @param int $courseId ID del curso.
     * @return array Información del curso.
     * @throws Exception Si la llamada a la API falla.
     */
    public function getCourseInfo(int $courseId): array {
        $course = $this->makeRequest('core_course_get_courses_by_field', [
            'field' => 'id',
            'value' => $courseId
        ]);
        $courseInfo = $course['courses'][0] ?? [];

        if (!empty($courseInfo)) {
            $courseInfo['course_url'] = $this->getCourseUrl($courseId);
        }

        return $courseInfo;
    }

    /**
     * Obtiene el nombre de un grupo por su ID.
     *
     * @param int $groupId ID del grupo.
     * @return string|null Nombre del grupo o null si no se encuentra.
     * @throws Exception Si la llamada a la API falla.
     */
    public function getGroupById(int $groupId): ?string {
        $groups = $this->makeRequest('core_group_get_groups', [
            'courseid' => 0, // Puedes ajustar este parámetro según tus necesidades
            'id' => $groupId
        ]);

        if (!empty($groups['groups'])) {
            return $groups['groups'][0]['name'] ?? null;
        }

        return null;
    }

    /**
     * Obtiene la URL del curso.
     *
     * @param int $courseId ID del curso.
     * @return string URL del curso.
     */
    private function getCourseUrl(int $courseId): string {
        return "{$this->baseUrl}/course/view.php?id={$courseId}";
    }

    /**
     * Obtiene la URL de una actividad BBB.
     *
     * @param int $courseId ID del curso.
     * @param int $coursemoduleId ID del módulo del curso.
     * @return string URL de la actividad.
     */
    private function getBBBActivityUrl(int $courseId, int $coursemoduleId): string {
        return "{$this->baseUrl}/mod/bigbluebuttonbn/view.php?id={$coursemoduleId}";
    }

    /**
     * Parsea el JSON de disponibilidad.
     *
     * @param string|null $availability JSON string de disponibilidad.
     * @return array|null Array asociativo de disponibilidad o null si no hay.
     */
    public function parseAvailability(?string $availability): ?array {
        if (empty($availability)) return null;

        try {
            return json_decode($availability, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log("Error parsing availability: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Realiza la llamada a la API de Moodle.
     *
     * @param string $function Nombre de la función de la API.
     * @param array $params Parámetros de la solicitud.
     * @return array Respuesta de la API.
     * @throws Exception Si la llamada falla.
     */
    private function makeRequest(string $function, array $params = []): array {
        $url = sprintf('%s/webservice/rest/server.php', rtrim($this->baseUrl, '/'));

        $params['wstoken'] = $this->token;
        $params['wsfunction'] = $function;
        $params['moodlewsrestformat'] = $this->restFormat;

        $ch = curl_init();
        if ($ch === false) {
            throw new Exception('No se pudo inicializar cURL');
        }

        $fullUrl = $url . '?' . http_build_query($params);
        error_log("API Request: {$function} with params " . json_encode($params));

        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Ajusta el tiempo de espera según tus necesidades
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new Exception("Error en la llamada a la API: " . ($error ?: "HTTP Code: $httpCode"));
        }

        try {
            $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            error_log("API Response for {$function}: " . json_encode($result));

            if (isset($result['exception'])) {
                throw new Exception($result['message'] ?? 'Error desconocido en la API de Moodle');
            }

            return $result;
        } catch (JsonException $e) {
            throw new Exception("Error decodificando respuesta JSON: " . $e->getMessage());
        }
    }
}
?>
