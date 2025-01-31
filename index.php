<?php
declare(strict_types=1);

require_once 'config.php';

// Inicializar BBBManager con una instancia de MoodleAPI
try {
    $moodleApi = new MoodleAPI(MOODLE_URL, MOODLE_TOKEN, MOODLE_REST_FORMAT);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error al inicializar la API de Moodle: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

try {
    $manager = new BBBManager($moodleApi);
    $manager->initialize();
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error al inicializar BBBManager: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Obtener los datos procesados
$data = $manager->getData();
$courseId = $manager->getCourseId();
$error = $manager->getError();
$message = $manager->getMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="styles/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="?" class="navbar-brand d-flex align-items-center">
                <i class="bi bi-camera-video-fill me-2"></i>
                <?= htmlspecialchars(APP_NAME) ?> <small class="ms-2 opacity-75">v<?= htmlspecialchars(APP_VERSION) ?></small>
            </a>
            <?php if ($courseId && isset($data['courseInfo'])): ?>
                <div class="d-flex align-items-center gap-3">
                    <a href="?" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i>
                        Volver a cursos
                    </a>
                    <div class="vr bg-white opacity-25 h-100"></div>
                    <?php
                        // Obtener 'course_url' desde 'courseInfo' o construirla si no está presente
                        $courseUrl = isset($data['courseInfo']['course_url']) ? $data['courseInfo']['course_url'] : (isset($data['courseInfo']['siteurl']) ? $data['courseInfo']['siteurl'] . "/course/view.php?id=" . urlencode((string)$courseId) : '#');
                    ?>
                    <a href="<?= htmlspecialchars($courseUrl) ?>" 
                       target="_blank"
                       class="btn btn-light btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>
                        Ir al curso
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container pb-5">
        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 fade-in">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($message): ?>
            <div class="alert alert-success d-flex align-items-center mb-4 fade-in">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($courseId && isset($data['courseInfo'])): ?>
            <div class="course-title fade-in mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-mortarboard-fill text-primary me-2"></i>
                    <?= htmlspecialchars($data['courseInfo']['fullname']) ?>
                </h1>
                <small class="text-muted"><?= htmlspecialchars($data['courseInfo']['shortname']) ?></small>
            </div>
        <?php endif; ?>

        <?php if (!$courseId): ?>
            <!-- Lista de cursos con BBB -->
            <div class="card fade-in">
                <div class="card-header bg-white py-3">
                    <h4 class="card-title mb-0 d-flex align-items-center">
                        <i class="bi bi-grid-3x3-gap me-2 text-primary"></i>
                        Cursos con BigBlueButton
                    </h4>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($data['coursesWithBBB'])): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-search display-1 mb-3"></i>
                            <p class="lead mb-0">No se encontraron cursos con actividades BigBlueButton</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($data['coursesWithBBB'] as $course): ?>
                                <div class="list-group-item p-4 course-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="?course_id=<?= htmlspecialchars((string)$course['id']) ?>" 
                                                   class="course-link">
                                                    <?= htmlspecialchars($course['fullname']) ?>
                                                </a>
                                            </h5>
                                            <div class="text-muted small">
                                                <i class="bi bi-code-slash me-1"></i>
                                                <?= htmlspecialchars($course['shortname']) ?>
                                                <?php if (!$course['visible']): ?>
                                                    <span class="badge bg-warning ms-2">Oculto</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">
                                            <?= htmlspecialchars((string)$course['bbb_count']) ?> BBB
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Vista de actividades BBB por secciones -->
            <?php if (empty($data['bbbData']['sections'])): ?>
                <div class="card">
                    <div class="card-body p-5 text-center text-muted">
                        <i class="bi bi-camera-video display-1 mb-3"></i>
                        <p class="lead mb-0">No se encontraron actividades BigBlueButton en este curso</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($data['bbbData']['sections'] as $section): ?>
                    <div class="card section-card mb-4 fade-in">
                        <div class="card-header">
                            <h2 class="section-title">
                                <i class="bi bi-folder-fill me-2"></i>
                                <?= htmlspecialchars($section['name']) ?>
                                <?php if (!$section['visible']): ?>
                                    <span class="badge bg-warning ms-2">Sección oculta</span>
                                <?php endif; ?>
                            </h2>
                            
                            <?php
                                // Decodificar la disponibilidad de la sección si existe
                                $sectionAvailability = isset($section['availability']) ? json_decode($section['availability'], true) : null;
                            ?>
                            <?php if ($manager->hasRestrictions($sectionAvailability)): ?>
                                <div class="restrictions-container section-restrictions mt-2">
                                    <div class="restrictions-title">
                                        <i class="bi bi-lock-fill me-2"></i>
                                        Restricciones de la sección
                                    </div>
                                    <?php foreach ($manager->getFormattedRestrictions($sectionAvailability) as $restriction): ?>
                                        <div class="restriction-item <?= htmlspecialchars($restriction['class']) ?>">
                                            <i class="bi <?= htmlspecialchars($restriction['icon']) ?> me-2"></i>
                                            <?= htmlspecialchars($restriction['text']) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($section['activities'] as $activity): ?>
                                    <div class="col-12 col-md-6 mb-3">
                                        <div class="card bbb-card h-100">
                                            <div class="card-header">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h5 class="card-title mb-0">
                                                        <?= htmlspecialchars($activity['name']) ?>
                                                        <?php if (!empty($activity['module_info']['description'])): ?>
                                                            <i class="bi bi-info-circle text-muted ms-2" 
                                                               title="<?= htmlspecialchars(strip_tags($activity['module_info']['description'])) ?>"
                                                               aria-label="Descripción de la actividad"></i>
                                                        <?php endif; ?>
                                                    </h5>
                                                    <?php if (!$activity['visible']): ?>
                                                        <span class="badge bg-warning">
                                                            <i class="bi bi-eye-slash me-1"></i>
                                                            Oculto
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body d-flex flex-column">
                                                <?php if (!empty($activity['intro'])): ?>
                                                    <div class="activity-intro mb-3">
                                                        <?= nl2br(htmlspecialchars(strip_tags($activity['intro']))) ?>
                                                    </div>
                                                <?php endif; ?>
    
                                                <?php 
                                                    // Decodificar la disponibilidad de la actividad si existe
                                                    $activityAvailability = isset($activity['availability']) ? json_decode($activity['availability'], true) : null;
                                                    // Obtener las restricciones formateadas
                                                    $formattedRestrictions = $manager->getFormattedRestrictions($activityAvailability);
                                                    if (!empty($formattedRestrictions)): 
                                                ?>
                                                    <div class="restrictions-container activity-restrictions">
                                                        <div class="restrictions-title">
                                                            <i class="bi bi-shield-lock me-2"></i>
                                                            Condiciones de acceso
                                                        </div>
                                                        <?php foreach ($formattedRestrictions as $restriction): ?>
                                                            <div class="restriction-item <?= htmlspecialchars($restriction['class']) ?>">
                                                                <i class="bi <?= htmlspecialchars($restriction['icon']) ?> me-2"></i>
                                                                <?= htmlspecialchars($restriction['text']) ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
    
                                                <div class="activity-meta mt-2">
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock-history me-1"></i>
                                                        Creado el: <?= isset($activity['module_info']['added']) ? date('d/m/Y', (int)$activity['module_info']['added']) : 'Desconocida' ?>
                                                    </small>
                                                </div>
    
                                                <div class="bbb-actions mt-3">
                                                    <?php
                                                        // Asegurarse de que 'activity_url' existe y es una cadena
                                                        $activityUrl = isset($activity['activity_url']) && is_string($activity['activity_url']) ? $activity['activity_url'] : '#';
                                                    ?>
                                                    <a href="<?= htmlspecialchars($activityUrl) ?>" 
                                                       class="btn btn-primary w-100"
                                                       target="_blank">
                                                        <i class="bi bi-door-open me-2"></i>
                                                        Unirse a la sesión
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                placement: 'top',
                trigger: 'hover focus'
            });
        });

        // Auto-ocultar alertas de éxito
        document.querySelectorAll('.alert-success').forEach(alert => {
            setTimeout(() => {
                alert.classList.add('fade');
                setTimeout(() => alert.remove(), 150);
            }, 3000);
        });
    });
    </script>
</body>
</html>
