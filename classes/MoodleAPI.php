<?php
declare(strict_types=1);

class MoodleAPI {
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly string $restFormat = 'json'
    ) {}

    /**
     * Obtiene la lista de cursos con actividades BBB
     */
    public function getCoursesWithBBB(): array {
        $courses = $this->makeRequest('core_course_get_courses');
        
        if (empty($courses)) {
            return [];
        }

        $coursesWithBBB = [];
        foreach ($courses as $course) {
            try {
                $bbbActivities = $this->getBBBActivities((int)$course['id']);
                if (!empty($bbbActivities['sections'])) {
                    $totalBBB = array_sum(array_map(
                        fn($section) => count($section['activities']), 
                        $bbbActivities['sections']
                    ));
                    
                    $coursesWithBBB[] = [
                        'id' => $course['id'],
                        'fullname' => $course['fullname'],
                        'shortname' => $course['shortname'],
                        'bbb_count' => $totalBBB,
                        'visible' => $course['visible'] ?? true,
                        'url' => $this->getCourseUrl($course['id'])
                    ];
                }
            } catch (Exception $e) {
                error_log("Error procesando curso {$course['id']}: " . $e->getMessage());
                continue;
            }
        }

        return $coursesWithBBB;
    }

    /**
     * Lista todas las secciones del curso y sus actividades BBB
     */
    public function getBBBActivities(int $courseId): array {
        // Primero obtenemos todas las actividades BBB
        $activities = $this->makeRequest('mod_bigbluebuttonbn_get_bigbluebuttonbns_by_courses', [
            'courseids[0]' => $courseId
        ]);

        // Obtenemos la estructura del curso con secciones
        $courseContents = $this->makeRequest('core_course_get_contents', [
            'courseid' => $courseId
        ]);

        // Preparamos la estructura de secciones
        $sections = [];
        foreach ($courseContents as $section) {
            $sectionInfo = [
                'id' => $section['id'],
                'name' => $section['name'] ?: "Tema {$section['section']}",
                'section' => $section['section'],
                'visible' => $section['visible'],
                'availability' => isset($section['availability']) ? 
                    json_decode($section['availability'], true) : null,
                'activities' => []
            ];

            // Buscamos actividades BBB en esta sección
            foreach ($section['modules'] ?? [] as $module) {
                foreach ($activities['bigbluebuttonbns'] ?? [] as $bbb) {
                    if ($bbb['coursemodule'] == $module['id']) {
                        $bbb['module_info'] = $module;
                        $bbb['activity_url'] = $this->getBBBActivityUrl($courseId, $module['id']);
                        $bbb['availability'] = isset($module['availability']) ? 
                            json_decode($module['availability'], true) : null;
                        $sectionInfo['activities'][] = $bbb;
                    }
                }
            }

            if (!empty($sectionInfo['activities'])) {
                $sections[] = $sectionInfo;
            }
        }

        return [
            'sections' => $sections,
            'course_url' => $this->getCourseUrl($courseId)
        ];
    }

    /**
     * Obtiene información detallada de un curso
     */
    public function getCourseInfo(int $courseId): array {
        $result = $this->makeRequest('core_course_get_courses_by_field', [
            'field' => 'id',
            'value' => $courseId
        ]);

        if (empty($result['courses'])) {
            throw new Exception('Curso no encontrado');
        }

        $courseInfo = $result['courses'][0];
        $courseInfo['url'] = $courseInfo['url'] ?? $this->getCourseUrl($courseId);
        return $courseInfo;
    }

    /**
     * Valida la conexión con Moodle
     */
    public function validateConnection(): bool {
        try {
            $result = $this->makeRequest('core_webservice_get_site_info');
            return isset($result['sitename']);
        } catch (Exception $e) {
            error_log("Error de conexión: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Construye la URL del curso
     */
    private function getCourseUrl(int $courseId): string {
        return rtrim($this->baseUrl, '/') . '/course/view.php?id=' . $courseId;
    }

    /**
     * Construye la URL de la actividad BBB
     */
    private function getBBBActivityUrl(int $courseId, int $cmid): string {
        return rtrim($this->baseUrl, '/') . '/mod/bigbluebuttonbn/view.php?id=' . $cmid;
    }

    /**
     * Realiza la llamada a la API de Moodle
     * 
     * @throws Exception Si hay un error en la llamada a la API
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'MoodleREST/1.0'
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