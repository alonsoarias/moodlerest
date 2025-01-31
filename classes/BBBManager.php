<?php
declare(strict_types=1);

class BBBManager {
    private ?string $error = null;
    private ?string $message = null;
    private ?int $courseId = null;
    private array $data = [];

    public function __construct(
        private readonly MoodleAPI $api
    ) {
        $this->courseId = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    }

    public function initialize(): void {
        try {
            if (!$this->api->validateConnection()) {
                throw new Exception('No se pudo establecer conexión con Moodle. Verifica las credenciales.');
            }

            $this->loadData();
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            error_log("BBBManager Error: " . $e->getMessage());
        }
    }

    private function loadData(): void {
        if ($this->courseId) {
            $this->data['courseInfo'] = $this->api->getCourseInfo($this->courseId);
            $this->data['bbbData'] = $this->api->getBBBActivities($this->courseId);
        } else {
            $this->data['coursesWithBBB'] = $this->api->getCoursesWithBBB();
        }
    }

    /**
     * Analiza y formatea las restricciones de acceso
     */
    public function getFormattedRestrictions(?array $availability): array {
        if (empty($availability)) {
            return [];
        }

        $restrictions = [];
        foreach ($availability['c'] ?? [] as $condition) {
            $formatted = $this->formatRestriction($condition);
            if ($formatted) {
                $restrictions[] = $formatted;
            }
        }

        return $restrictions;
    }

    /**
     * Formatea un tipo específico de restricción
     */
    private function formatRestriction(array $condition): ?array {
        if (!isset($condition['type'])) {
            return null;
        }

        $result = [
            'type' => $condition['type'],
            'icon' => $this->getRestrictionIcon($condition['type']),
            'text' => '',
            'class' => $this->getRestrictionClass($condition['type'])
        ];

        switch ($condition['type']) {
            case 'date':
                if (isset($condition['d'], $condition['t'])) {
                    $date = date('Y-m-d H:i', (int)$condition['t']);
                    $result['text'] = $condition['d'] === '>=' ? 
                        "Disponible desde: $date" : 
                        "Disponible hasta: $date";
                }
                break;

            case 'group':
                $result['text'] = isset($condition['id']) ? 
                    "Restringido al grupo: {$condition['id']}" : '';
                break;

            case 'profile':
                if (isset($condition['sf'], $condition['v'])) {
                    $result['text'] = "Campo de perfil '{$condition['sf']}' debe ser: {$condition['v']}";
                }
                break;

            case 'completion':
                if (isset($condition['cm'])) {
                    $estado = ($condition['e'] ?? 1) == 1 ? 'completada' : 'no completada';
                    $result['text'] = "Requiere actividad {$condition['cm']} $estado";
                }
                break;

            case 'grade':
                if (isset($condition['id'], $condition['min'])) {
                    $result['text'] = "Calificación mínima: {$condition['min']}";
                }
                break;

            default:
                return null;
        }

        return $result['text'] ? $result : null;
    }

    /**
     * Obtiene el icono correspondiente al tipo de restricción
     */
    private function getRestrictionIcon(string $type): string {
        return match($type) {
            'date' => 'bi-calendar-event',
            'group' => 'bi-people-fill',
            'profile' => 'bi-person-vcard',
            'completion' => 'bi-check-circle',
            'grade' => 'bi-star-fill',
            default => 'bi-shield-lock'
        };
    }

    /**
     * Obtiene la clase CSS correspondiente al tipo de restricción
     */
    private function getRestrictionClass(string $type): string {
        return match($type) {
            'date' => 'text-primary',
            'group' => 'text-success',
            'profile' => 'text-info',
            'completion' => 'text-warning',
            'grade' => 'text-danger',
            default => 'text-secondary'
        };
    }

    /**
     * Verifica si una sección o actividad tiene restricciones
     */
    public function hasRestrictions(?array $availability): bool {
        return !empty($availability['c'] ?? []);
    }

    public function getData(): array {
        return $this->data;
    }

    public function getCourseId(): ?int {
        return $this->courseId;
    }

    public function getError(): ?string {
        return $this->error;
    }

    public function getMessage(): ?string {
        return $this->message;
    }
}