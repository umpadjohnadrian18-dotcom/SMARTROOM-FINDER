<?php
require __DIR__ . '/config.php';
$u = me(); if (!$u) { header('Location: login.php'); exit; }
$rooms = db()->query('SELECT id,code,name FROM rooms ORDER BY type, LENGTH(code), code')->fetchAll();
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Smart Room Finder</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="app.css">
</head><body>
<header class="top">
  <div class="brand"><span class="dot"></span>Smart Room Finder</div>
  <span class="who"><?= e($u['full_name']) ?> · <?= e($u['student_no']) ?></span>
  <a href="logout.php">Sign out</a>
</header>
<main>
  <div class="card search">
    <label>Room<input id="q" placeholder="e.g. 205 or CL3"></label>
    <label>Date<input id="date" type="date"></label>
    <label>Time<input id="time" type="time"></label>
    <label>Students<input id="students" type="number" min="0" placeholder="0"></label>
    <label>Type<select id="type"><option value="">Any</option><option value="classroom">Classroom</option><option value="laboratory">Laboratory</option></select></label>
    <button id="go">Find room</button>
  </div>
  <div class="grid">
    <div class="card"><h2>Rooms</h2><div id="list"></div></div>
    <div class="card">
      <div class="map tpl" id="map">
        <!-- Your real map: save it as map.png. The template below disappears automatically. -->
        <img id="mapimg" alt="Floor map">
        <div class="hall">HALLWAY</div><div class="ent">MAIN ENTRANCE</div><div class="tplnote">Map template · add map.png</div>
        <svg viewBox="0 0 100 100" preserveAspectRatio="none"><polyline id="route" class="path" points=""/></svg>
        <div id="pin" class="pin" hidden></div>
      </div>
      <div class="legend"><span>White: free now</span><span>Pink: occupied</span><span>Dark: selected</span></div>
      <div class="stats" id="stats"></div>
      <div id="detail"></div>
    </div>
  </div>
  <div class="card">
    <h2>My requests</h2>
    <div class="tablewrap" id="mine"><p class="note">Loading…</p></div>
  </div>
</main>

<dialog id="dlg">
  <form id="rf" method="dialog">
    <h2 class="w">Request a room</h2>
    <label class="w">Room<select id="f_room" required><?php foreach ($rooms as $r) echo '<option value="' . $r['id'] . '">' . e($r['name']) . '</option>'; ?></select></label>
    <label>Date<input id="f_date" type="date" required></label>
    <label>Student number<input id="f_no" value="<?= e($u['student_no']) ?>" readonly></label>
    <label>Start<input id="f_start" type="time" required></label>
    <label>End<input id="f_end" type="time" required></label>
    <label>Course<input id="f_course" placeholder="e.g. BSIT" maxlength="60" required></label>
    <label>Year level<select id="f_year" required><option value="">Select…</option><option>1st year</option><option>2nd year</option><option>3rd year</option><option>4th year</option><option>5th year</option></select></label>
    <label class="w">Reason for using the room<textarea id="f_reason" maxlength="500" required placeholder="e.g. Group study for Networking finals"></textarea></label>
    <div class="w alert" id="f_err" hidden></div>
    <div class="w acts"><button type="submit" id="f_go">Send request</button><button type="button" class="x" onclick="dlg.close()">Cancel</button></div>
  </form>
</dialog>

<script>
const ADMIN = false, BASE = '', CSRF = <?= json_encode(csrf()) ?>;
</script>
<script src="app.js"></script>
<script>
const dlg = $('dlg'), seen = {};
function openRequest(id) {
  $('f_room').value = id; $('f_date').value = $('date').value; $('f_date').min = new Date().toLocaleDateString('en-CA');
  $('f_start').value = $('time').value; $('f_end').value = ''; $('f_err').hidden = true; dlg.showModal();
}
$('rf').addEventListener('submit', async ev => {
  ev.preventDefault(); $('f_go').disabled = true;
  const j = await post('submit_request', {room_id:$('f_room').value, date:$('f_date').value, start:$('f_start').value, end:$('f_end').value,
    course:$('f_course').value, year_level:$('f_year').value, reason:$('f_reason').value});
  $('f_go').disabled = false;
  if (j.error) { $('f_err').textContent = j.error; $('f_err').hidden = false; return; }
  dlg.close(); $('f_reason').value = ''; toast('Request sent. Wait for the admin to approve it.'); loadMine();
});
async function cancelReq(id) { if (!confirm('Cancel this request?')) return; const j = await post('cancel_request', {id}); if (j.error) toast(j.error); loadMine(); }
async function loadMine() {
  let j; try { j = await (await fetch('api.php?action=my_requests')).json(); } catch { return; }
  if (!j.requests) return;
  j.requests.forEach(r => { if (seen[r.id] && seen[r.id] !== r.status && r.status !== 'cancelled') { toast(`Room ${r.room_code} request was ${r.status}.`); search(true); } seen[r.id] = r.status; });
  $('mine').innerHTML = j.requests.length ? `<table><tr><th>Room</th><th>When</th><th>Reason</th><th>Status</th><th></th></tr>${j.requests.map(r => `
    <tr><td><b>${esc(r.room_code)}</b></td><td>${esc(r.req_date)}<br><span class="note">${r.s}–${r.e}</span></td>
    <td>${esc(r.reason)}<br><span class="note">${esc(r.course)} · ${esc(r.year_level)}</span></td>
    <td><span class="tag ${r.status}">${r.status[0].toUpperCase() + r.status.slice(1)}</span>${r.admin_note ? `<br><span class="note">Admin: ${esc(r.admin_note)}</span>` : ''}</td>
    <td class="acts"><button class="x" onclick="focusRoom(${r.room_id}, '${esc(r.req_date)}')">Show on map</button>${r.status === 'pending' ? `<button class="x" onclick="cancelReq(${r.id})">Cancel</button>` : ''}</td></tr>`).join('')}</table>`
    : '<p class="note">You have not requested a room yet. Pick a room above and press “Request room”.</p>';
}
loadMine(); setInterval(loadMine, 20000);
</script>
</body></html>
