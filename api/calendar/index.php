<?php
/**
 * Calendar Events API
 * GET    /api/calendar/        – list events (by user, optional date range)
 * POST   /api/calendar/        – create event
 * PUT    /api/calendar/        – update event
 * DELETE /api/calendar/        – delete event
 */

require_once dirname(dirname(__DIR__)) . '/config/bootstrap.php';

header('Content-Type: application/json');

// ---------------------------------------------------------------
// Helper: label → FullCalendar background color
// ---------------------------------------------------------------
function calendarLabelColor(string $label): string {
    $map = [
        'primary' => '#2c7be5',
        'danger'  => '#e63757',
        'success' => '#00d27a',
        'warning' => '#f5803e',
    ];
    return $map[$label] ?? '#2c7be5';
}

$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $user['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------
// GET – fetch events for FullCalendar (supports ?start= &end= )
// ---------------------------------------------------------------
if ($method === 'GET') {
    $start = $_GET['start'] ?? date('Y-m-01');
    $end   = $_GET['end']   ?? date('Y-m-t');

    $events = Database::fetchAll(
        "SELECT * FROM calendar_events
          WHERE user_id = :uid
            AND start_date < :end
            AND (end_date >= :start OR (end_date IS NULL AND start_date >= :start2))
          ORDER BY start_date ASC",
        ['uid' => $userId, 'start' => $start, 'end' => $end, 'start2' => $start]
    );

    // Transform to FullCalendar event format
    $result = array_map(function ($e) {
        return [
            'id'          => (int) $e['event_id'],
            'title'       => $e['title'],
            'start'       => $e['start_date'],
            'end'         => $e['end_date'],
            'allDay'      => (bool) $e['all_day'],
            'description' => $e['description'],
            'label'       => $e['label'] ?? '',
            'classNames'  => $e['label'] ? ['fc-event-' . $e['label']] : [],
            'backgroundColor' => calendarLabelColor($e['label'] ?? ''),
        ];
    }, $events);

    echo json_encode(['success' => true, 'events' => $result]);
    exit;
}

// ---------------------------------------------------------------
// POST – create event
// ---------------------------------------------------------------
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $title = trim($body['title'] ?? '');
    $start = trim($body['start'] ?? '');

    if (!$title || !$start) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Title and start date are required.']);
        exit;
    }

    $end         = !empty($body['end'])         ? $body['end']         : null;
    $allDay      = !empty($body['allDay'])       ? 1                    : 0;
    $description = trim($body['description']    ?? '');
    $label       = trim($body['label']          ?? '');

    Database::execute(
        "INSERT INTO calendar_events (user_id, title, description, start_date, end_date, all_day, label)
         VALUES (:uid, :title, :desc, :start, :end, :allday, :label)",
        [
            'uid'    => $userId,
            'title'  => $title,
            'desc'   => $description ?: null,
            'start'  => $start,
            'end'    => $end,
            'allday' => $allDay,
            'label'  => $label ?: null,
        ]
    );

    $newId = (int) Database::lastInsertId();

    // Log activity
    Database::execute(
        "INSERT INTO activity_logs (user_id, action, module_name, reference_code, ip_address, new_value)
         VALUES (:uid, 'CREATE', 'CALENDAR', :ref, :ip, :nv)",
        [
            'uid' => $userId,
            'ref' => 'EVT-' . $newId,
            'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'nv'  => json_encode(['title' => $title, 'start' => $start]),
        ]
    );

    echo json_encode(['success' => true, 'event_id' => $newId, 'message' => 'Event created.']);
    exit;
}

// ---------------------------------------------------------------
// PUT – update event
// ---------------------------------------------------------------
if ($method === 'PUT') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int) ($body['event_id'] ?? 0);

    if (!$eventId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'event_id is required.']);
        exit;
    }

    // Verify ownership
    $existing = Database::fetch(
        "SELECT * FROM calendar_events WHERE event_id = :id AND user_id = :uid",
        ['id' => $eventId, 'uid' => $userId]
    );
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit;
    }

    $title       = trim($body['title']       ?? $existing['title']);
    $start       = trim($body['start']       ?? $existing['start_date']);
    $end         = !empty($body['end'])       ? $body['end']  : null;
    $allDay      = isset($body['allDay'])     ? ($body['allDay'] ? 1 : 0) : (int) $existing['all_day'];
    $description = trim($body['description'] ?? ($existing['description'] ?? ''));
    $label       = trim($body['label']       ?? ($existing['label'] ?? ''));

    Database::execute(
        "UPDATE calendar_events
            SET title = :title, description = :desc, start_date = :start,
                end_date = :end, all_day = :allday, label = :label
          WHERE event_id = :id AND user_id = :uid",
        [
            'title'  => $title,
            'desc'   => $description ?: null,
            'start'  => $start,
            'end'    => $end,
            'allday' => $allDay,
            'label'  => $label ?: null,
            'id'     => $eventId,
            'uid'    => $userId,
        ]
    );

    // Log activity
    Database::execute(
        "INSERT INTO activity_logs (user_id, action, module_name, reference_code, ip_address, old_value, new_value)
         VALUES (:uid, 'UPDATE', 'CALENDAR', :ref, :ip, :ov, :nv)",
        [
            'uid' => $userId,
            'ref' => 'EVT-' . $eventId,
            'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'ov'  => json_encode(['title' => $existing['title'], 'start' => $existing['start_date']]),
            'nv'  => json_encode(['title' => $title, 'start' => $start]),
        ]
    );

    echo json_encode(['success' => true, 'message' => 'Event updated.']);
    exit;
}

// ---------------------------------------------------------------
// DELETE – delete event
// ---------------------------------------------------------------
if ($method === 'DELETE') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int) ($body['event_id'] ?? $_GET['event_id'] ?? 0);

    if (!$eventId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'event_id is required.']);
        exit;
    }

    $existing = Database::fetch(
        "SELECT * FROM calendar_events WHERE event_id = :id AND user_id = :uid",
        ['id' => $eventId, 'uid' => $userId]
    );
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found.']);
        exit;
    }

    Database::execute(
        "DELETE FROM calendar_events WHERE event_id = :id AND user_id = :uid",
        ['id' => $eventId, 'uid' => $userId]
    );

    // Log activity
    Database::execute(
        "INSERT INTO activity_logs (user_id, action, module_name, reference_code, ip_address, old_value)
         VALUES (:uid, 'DELETE', 'CALENDAR', :ref, :ip, :ov)",
        [
            'uid' => $userId,
            'ref' => 'EVT-' . $eventId,
            'ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'ov'  => json_encode(['title' => $existing['title']]),
        ]
    );

    echo json_encode(['success' => true, 'message' => 'Event deleted.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
