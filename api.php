<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'search';
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

function out($d, int $code = 200) { http_response_code($code); echo json_encode($d); exit; }
function token_ok(): bool { return hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? ''); }
function admin_only() { if (!is_admin() || !token_ok()) out(['error' => 'Admin only'], 403); }
function user_only() { if (!is_user() || !token_ok()) out(['error' => 'Please sign in again'], 403); }
function p(string $k): string { return trim((string)($_POST[$k] ?? '')); }
function t(string $v): bool { return (bool)preg_match('/^\d\d:\d\d$/', $v); }

/* Is the room already taken by a class or an approved request in that window? */
function conflict(int $room, string $date, string $s, string $e, int $skip = 0): ?string {
    $q = db()->prepare('SELECT subject FROM schedules WHERE room_id=? AND day=? AND start_time<? AND end_time>? LIMIT 1');
    $q->execute([$room, date('l', strtotime($date)), $e, $s]);
    if ($r = $q->fetch()) return 'a scheduled class (' . $r['subject'] . ')';
    $q = db()->prepare("SELECT 1 FROM room_requests WHERE room_id=? AND req_date=? AND status='approved' AND id<>? AND start_time<? AND end_time>? LIMIT 1");
    $q->execute([$room, $date, $skip, $e, $s]);
    return $q->fetch() ? 'another approved reservation' : null;
}

/* ---------- A* search: Main Entrance -> hallway -> room ---------- */
function astar(array $nodes, array $edges, array $goals): ?array {
    $h = function ($n) use ($nodes, $goals) {
        $m = INF;
        foreach ($goals as $g) $m = min($m, hypot($nodes[$n][0] - $nodes[$g][0], $nodes[$n][1] - $nodes[$g][1]));
        return $m;
    };
    $open = ['E' => $h('E')]; $g = ['E' => 0]; $prev = []; $closed = []; $visited = 0;
    while ($open) {
        $cur = null; $best = INF;
        foreach ($open as $n => $f) if ($f < $best) { $best = $f; $cur = $n; }   // lowest f(n)
        unset($open[$cur]); $closed[$cur] = 1; $visited++;
        if (in_array($cur, $goals, true)) {
            $path = [$cur];
            while (isset($prev[$cur])) { $cur = $prev[$cur]; array_unshift($path, $cur); }
            return ['path' => $path, 'cost' => round($g[end($path)], 1), 'h0' => round($h('E'), 1), 'visited' => $visited];
        }
        foreach ($edges[$cur] ?? [] as [$m, $c]) {
            if (isset($closed[$m])) continue;
            $ng = $g[$cur] + $c;
            if (!isset($g[$m]) || $ng < $g[$m]) { $g[$m] = $ng; $prev[$m] = $cur; $open[$m] = $ng + $h($m); }
        }
    }
    return null;
}

if ($action === 'search') {
    $date = preg_match('/^\d{4}-\d\d-\d\d$/', $_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');
    $day  = date('l', strtotime($date));
    $time = t($_GET['time'] ?? '') ? $_GET['time'] : date('H:i');
    $q    = strtolower(trim($_GET['q'] ?? ''));
    $q    = preg_replace('/^(room|rm)\s*/', '', $q);
    $type = in_array($_GET['type'] ?? '', ['classroom','laboratory'], true) ? $_GET['type'] : '';
    $need = max(0, (int)($_GET['students'] ?? 0));

    $rooms = db()->query('SELECT * FROM rooms ORDER BY type, LENGTH(code), code')->fetchAll();
    $st = db()->prepare('SELECT id,room_id,TIME_FORMAT(start_time,"%H:%i") s,TIME_FORMAT(end_time,"%H:%i") e,subject FROM schedules WHERE day=? ORDER BY start_time');
    $st->execute([$day]);
    $slots = [];
    foreach ($st->fetchAll() as $s) $slots[$s['room_id']][] = $s;
    // approved requests block the room on that date (this is how the admin page connects to the student page)
    $st = db()->prepare("SELECT room_id,TIME_FORMAT(start_time,'%H:%i') s,TIME_FORMAT(end_time,'%H:%i') e FROM room_requests WHERE req_date=? AND status='approved'");
    $st->execute([$date]);
    foreach ($st->fetchAll() as $s) { $s['id'] = 0; $s['req'] = 1; $s['subject'] = 'Approved reservation'; $slots[$s['room_id']][] = $s; }
    $pend = [];
    if (is_admin()) {
        $st = db()->prepare("SELECT room_id,COUNT(*) c FROM room_requests WHERE req_date=? AND status='pending' GROUP BY room_id");
        $st->execute([$date]);
        foreach ($st->fetchAll() as $r) $pend[$r['room_id']] = (int)$r['c'];
    }

    $list = []; $goals = []; $nodes = ['E' => [3, 50], 'HL' => [20, 50], 'HM' => [50, 50], 'HR' => [80, 50]];
    $edges = [];
    $link = function ($a, $b) use (&$edges, &$nodes) {
        $c = hypot($nodes[$a][0] - $nodes[$b][0], $nodes[$a][1] - $nodes[$b][1]);
        $edges[$a][] = [$b, $c]; $edges[$b][] = [$a, $c];
    };
    $link('E', 'HL'); $link('HL', 'HM'); $link('HM', 'HR');

    foreach ($rooms as $r) {
        $rs = $slots[$r['id']] ?? [];
        usort($rs, fn($x, $y) => strcmp($x['s'], $y['s']));
        $busy = false;
        foreach ($rs as $s) if ($s['s'] <= $time && $time < $s['e']) $busy = true;
        $r['slots'] = $rs; $r['busy_now'] = $busy; $r['free_all_day'] = !$rs; $r['pending'] = $pend[$r['id']] ?? 0;
        $matchQ = $q === '' || str_contains(strtolower($r['code']), $q) || str_contains(strtolower($r['name']), $q);
        $r['match'] = $matchQ;
        $r['valid'] = $matchQ && !$busy && (!$type || $r['type'] === $type) && $r['capacity'] >= $need;
        $id = 'r' . $r['id']; $nodes[$id] = [(float)$r['map_x'], (float)$r['map_y']];
        $near = 'HL'; $d = INF;
        foreach (['HL', 'HM', 'HR'] as $h) { $x = abs($nodes[$h][0] - $nodes[$id][0]); if ($x < $d) { $d = $x; $near = $h; } }
        $link($near, $id);
        if ($r['valid']) $goals[] = $id;
        $list[] = $r;
    }

    $res = $goals ? astar($nodes, $edges, $goals) : null;
    $trace = null;
    if ($res) {
        $trace = [
            'room_id' => (int)substr(end($res['path']), 1),
            'points' => array_map(fn($n) => $nodes[$n], $res['path']),
            'path' => array_map(fn($n) => $n[0] === 'r' ? 'Room' : ($n === 'E' ? 'Main Entrance' : 'Hallway'), $res['path']),
            'cost' => $res['cost'], 'h0' => $res['h0'], 'visited' => $res['visited'],
        ];
    }
    out(['day' => $day, 'date' => $date, 'time' => $time, 'rooms' => $list, 'result' => $trace]);
}

/* ---------- Student: requests ---------- */
$RQ = 'SELECT q.id,q.room_id,q.student_no,q.course,q.year_level,q.reason,q.req_date,q.status,q.admin_note,q.created_at,q.decided_at,
  TIME_FORMAT(q.start_time,"%H:%i") s,TIME_FORMAT(q.end_time,"%H:%i") e,r.code room_code,r.name room_name,u.full_name
  FROM room_requests q JOIN rooms r ON r.id=q.room_id JOIN users u ON u.id=q.user_id ';

if ($action === 'my_requests') {
    if (!is_user()) out(['error' => 'Sign in'], 403);
    $st = db()->prepare($RQ . 'WHERE q.user_id=? ORDER BY q.created_at DESC, q.id DESC LIMIT 100');
    $st->execute([$_SESSION['user']]);
    out(['requests' => $st->fetchAll()]);
}
if ($action === 'submit_request') {
    user_only();
    $u = me(); $room = (int)p('room_id'); $date = p('date'); $s = p('start'); $e = p('end');
    $course = mb_substr(p('course'), 0, 60); $year = mb_substr(p('year_level'), 0, 20); $reason = mb_substr(p('reason'), 0, 500);
    if (!$room || $course === '' || $year === '' || strlen($reason) < 5) out(['error' => 'Please fill in every field (reason needs a few words).'], 422);
    if (!preg_match('/^\d{4}-\d\d-\d\d$/', $date) || $date < date('Y-m-d')) out(['error' => 'Pick today or a future date.'], 422);
    if (!t($s) || !t($e) || $s >= $e) out(['error' => 'End time must be after start time.'], 422);
    if ($date === date('Y-m-d') && $e <= date('H:i')) out(['error' => 'That time has already passed.'], 422);
    $x = db()->prepare('SELECT 1 FROM rooms WHERE id=?'); $x->execute([$room]);
    if (!$x->fetch()) out(['error' => 'Unknown room.'], 422);
    if ($c = conflict($room, $date, $s, $e)) out(['error' => "That room is already taken by $c during this time."], 409);
    $x = db()->prepare("SELECT COUNT(*) FROM room_requests WHERE user_id=? AND status='pending'"); $x->execute([$u['id']]);
    if ($x->fetchColumn() >= 5) out(['error' => 'You already have 5 pending requests. Wait for a decision or cancel one.'], 429);
    db()->prepare('INSERT INTO room_requests (user_id,room_id,student_no,course,year_level,reason,req_date,start_time,end_time) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $room, $u['student_no'], $course, $year, $reason, $date, $s, $e]);
    out(['ok' => true]);
}
if ($action === 'cancel_request') {
    user_only();
    db()->prepare("UPDATE room_requests SET status='cancelled',decided_at=NOW() WHERE id=? AND user_id=? AND status='pending'")->execute([(int)p('id'), $_SESSION['user']]);
    out(['ok' => true]);
}

/* ---------- Admin: requests ---------- */
if ($action === 'list_requests') {
    if (!is_admin()) out(['error' => 'Admin only'], 403);
    $st = in_array($_GET['status'] ?? '', ['pending','approved','rejected','cancelled'], true) ? $_GET['status'] : '';
    $q = db()->prepare($RQ . ($st ? 'WHERE q.status=? ' : 'WHERE ?=? ') . 'ORDER BY q.status="pending" DESC, q.req_date, q.start_time LIMIT 200');
    $q->execute($st ? [$st] : [1, 1]);
    $counts = db()->query('SELECT status,COUNT(*) c FROM room_requests GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    out(['requests' => $q->fetchAll(), 'counts' => $counts]);
}
if ($action === 'decide') {
    admin_only();
    $id = (int)p('id'); $status = p('status'); $note = mb_substr(p('note'), 0, 255);
    if (!in_array($status, ['approved', 'rejected'], true)) out(['error' => 'Bad status'], 422);
    $st = db()->prepare("SELECT * FROM room_requests WHERE id=? AND status IN ('pending','approved','rejected')"); $st->execute([$id]);
    $r = $st->fetch(); if (!$r) out(['error' => 'Request not found'], 404);
    if ($status === 'approved' && ($c = conflict((int)$r['room_id'], $r['req_date'], substr($r['start_time'], 0, 5), substr($r['end_time'], 0, 5), $id)))
        out(['error' => "Cannot approve: the room is taken by $c in that time."], 409);
    db()->prepare('UPDATE room_requests SET status=?,admin_note=?,decided_at=NOW() WHERE id=?')->execute([$status, $note, $id]);
    out(['ok' => true]);
}

/* ---------- Admin: rooms and schedules ---------- */
if ($action === 'update_room') {
    admin_only();
    $type = p('type') === 'laboratory' ? 'laboratory' : 'classroom';
    db()->prepare('UPDATE rooms SET type=?,capacity=?,equipment=?,map_x=?,map_y=? WHERE id=?')->execute([
        $type, max(0, (int)p('capacity')), mb_substr(p('equipment'), 0, 255),
        min(100, max(0, (float)p('map_x'))), min(100, max(0, (float)p('map_y'))), (int)p('id'),
    ]);
    out(['ok' => true]);
}
if ($action === 'add_slot') {
    admin_only();
    $s = p('start'); $e = p('end'); $room = (int)p('room_id');
    if (!in_array(p('day'), $days, true) || !t($s) || !t($e) || $s >= $e) out(['error' => 'Check the day and times'], 422);
    $x = db()->prepare('SELECT 1 FROM schedules WHERE room_id=? AND day=? AND start_time<? AND end_time>?'); $x->execute([$room, p('day'), $e, $s]);
    if ($x->fetch()) out(['error' => 'That overlaps an existing class in this room.'], 409);
    db()->prepare('INSERT INTO schedules (room_id,day,start_time,end_time,subject) VALUES (?,?,?,?,?)')->execute([$room, p('day'), $s, $e, mb_substr(p('subject'), 0, 120)]);
    out(['ok' => true]);
}
if ($action === 'del_slot') {
    admin_only();
    db()->prepare('DELETE FROM schedules WHERE id=?')->execute([(int)p('id')]);
    out(['ok' => true]);
}
out(['error' => 'Unknown action'], 400);
