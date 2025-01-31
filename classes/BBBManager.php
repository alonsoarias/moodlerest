<?php
declare(strict_types=1);

/**
 * Clase BBBManager para gestionar las actividades BigBlueButton (BBB) en Moodle.
 */
class BBBManager {
    private ?string $error = null;
    private ?string $message = null;
    private ?int $courseId = null;
    private array $data = [];

    // Cache para nombres de grupos
    private array $groupCache = [];

    public function __construct(
        private readonly MoodleAPI $api
    ) {
        $this->courseId = filter_var($_GET['course_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
    }

    /**
     * Inicializa el gestor BBB.
     *
     * @return void
     */
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

    /**
     * Carga los datos necesarios según si se ha seleccionado un curso.
     *
     * @return void
     */
    private function loadData(): void {
        if ($this->courseId) {
            try {
                $this->data['courseInfo'] = $this->api->getCourseInfo($this->courseId);
                $this->data['bbbData'] = $this->api->getBBBActivities($this->courseId);
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                error_log("BBBManager loadData Error: " . $e->getMessage());
            }
        } else {
            $this->data['coursesWithBBB'] = $this->api->getCoursesWithBBB();
        }
    }

    /**
     * Analiza y formatea las restricciones de acceso.
     *
     * @param array|null $availability Datos de disponibilidad.
     * @return array Restricciones formateadas.
     */
    public function getFormattedRestrictions(?array $availability): array {
        if (!$this->hasRestrictions($availability)) return [];

        $restrictions = [];
        foreach ($availability['c'] as $condition) {
            $restriction = $this->formatRestriction($condition);
            if ($restriction) $restrictions[] = $restriction;
        }

        return $restrictions;
    }

    /**
     * Formatea una restricción según su tipo.
     *
     * @param array $condition Condición de restricción.
     * @return array|null Restricción formateada o null si no es válida.
     */
    private function formatRestriction(array $condition): ?array {
        $type = $condition['type'] ?? 'unknown';
        $method = 'format' . ucfirst($type) . 'Restriction';

        return method_exists($this, $method)
            ? $this->$method($condition)
            : $this->formatUnknownRestriction($condition);
    }

    /**
     * Formatea una restricción de tipo 'date'.
     *
     * @param array $condition Condición de restricción.
     * @return array Restricción formateada.
     */
    private function formatDateRestriction(array $condition): array {
        $timestamp = isset($condition['t']) ? (int)$condition['t'] : 0;
        $date = $timestamp ? date('d/m/Y H:i', $timestamp) : 'Fecha desconocida';
        $operator = $condition['d'] ?? '>=';

        // Determinar si es 'desde' o 'hasta'
        $text = match ($operator) {
            '>=' => 'Disponible desde: ',
            '<=' => 'Disponible hasta: ',
            default => 'Disponible en: '
        };

        return [
            'type' => 'date',
            'icon' => 'bi-calendar-event',
            'class' => 'text-primary',
            'text' => $text . $date
        ];
    }

    /**
     * Formatea una restricción de tipo 'group'.
     *
     * @param array $condition Condición de restricción.
     * @return array Restricción formateada.
     */
    private function formatGroupRestriction(array $condition): array {
        $groupId = $condition['id'] ?? 0;
        $groupName = $this->getGroupName((int)$groupId) ?? "Grupo #{$groupId}";
        return [
            'type' => 'group',
            'icon' => 'bi-people-fill',
            'class' => 'text-success',
            'text' => 'Grupo requerido: ' . htmlspecialchars($groupName)
        ];
    }

    /**
     * Formatea una restricción de tipo 'profile'.
     *
     * @param array $condition Condición de restricción.
     * @return array Restricción formateada.
     */
    private function formatProfileRestriction(array $condition): array {
        $field = htmlspecialchars($condition['sf'] ?? 'Campo desconocido');
        $value = htmlspecialchars($condition['v'] ?? 'Valor desconocido');
        return [
            'type' => 'profile',
            'icon' => 'bi-person-badge',
            'class' => 'text-info',
            'text' => "Campo '{$field}': {$value}"
        ];
    }

    /**
     * Formatea una restricción de tipo 'completion'.
     *
     * @param array $condition Condición de restricción.
     * @return array Restricción formateada.
     */
    private function formatCompletionRestriction(array $condition): array {
        $activityId = $condition['cm'] ?? 'Desconocido';
        $status = (isset($condition['e']) && $condition['e'] == 1) ? 'completada' : 'no completada';
        return [
            'type' => 'completion',
            'icon' => 'bi-check-circle',
            'class' => 'text-warning',
            'text' => "Requiere actividad '{$activityId}' {$status}"
        ];
    }

    /**
     * Formatea una restricción de tipo 'grade'.
     *
     * @param array $condition Condición de restricción.
     * @return array Restricción formateada.
     */
    private function formatGradeRestriction(array $condition): array {
        $minGrade = htmlspecialchars($condition['min'] ?? '0');
        return [
            'type' => 'grade',
            'icon' => 'bi-award',
            'class' => 'text-danger',
            'text' => "Calificación mínima: {$minGrade}%"
        ];
    }

    /**
     * Formatea una restricción desconocida.
     *
     * @param array $condition Condición de restricción.
     * @return array Restricción formateada.
     */
    private function formatUnknownRestriction(array $condition): array {
        return [
            'type' => 'unknown',
            'icon' => 'bi-question-circle',
            'class' => 'text-secondary',
            'text' => 'Restricción no reconocida: ' . json_encode($condition)
        ];
    }

    /**
     * Verifica si una sección o actividad tiene restricciones.
     *
     * @param array|null $availability Datos de disponibilidad.
     * @return bool True si hay restricciones, false de lo contrario.
     */
    public function hasRestrictions(?array $availability): bool {
        if (empty($availability['c'])) return false;
        foreach ($availability['c'] as $condition) {
            if (!empty($condition['type'])) return true;
        }
        return false;
    }

    /**
     * Obtiene el nombre de un grupo con cache.
     *
     * @param int $groupId ID del grupo.
     * @return string|null Nombre del grupo o null si no se encuentra.
     */
    private function getGroupName(int $groupId): ?string {
        if (isset($this->groupCache[$groupId])) {
            return $this->groupCache[$groupId];
        }

        try {
            $groupName = $this->api->getGroupById($groupId);
            $this->groupCache[$groupId] = $groupName;
            return $groupName;
        } catch (Exception $e) {
            error_log("Error obteniendo nombre del grupo {$groupId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene los datos necesarios para la presentación.
     *
     * @return array Datos del gestor.
     */
    public function getData(): array {
        return $this->data;
    }

    /**
     * Obtiene el ID del curso actual.
     *
     * @return int|null ID del curso o null si no está seleccionado.
     */
    public function getCourseId(): ?int {
        return $this->courseId;
    }

    /**
     * Obtiene el mensaje de error.
     *
     * @return string|null Mensaje de error o null si no hay.
     */
    public function getError(): ?string {
        return $this->error;
    }

    /**
     * Obtiene el mensaje de éxito.
     *
     * @return string|null Mensaje de éxito o null si no hay.
     */
    public function getMessage(): ?string {
        return $this->message;
    }
}
?>
