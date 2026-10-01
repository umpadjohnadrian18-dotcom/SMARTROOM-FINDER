<?php
require __DIR__ . '/config.php';
if (!is_admin()) { header('Location: admin_login.php'); exit; }
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex"><title>Admin · Smart Room Finder</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="app.css">
</head><body>
<header class="top">
  <div class="brand"><span class="dot"></span>Smart Room Finder · Admin</div>
  <a href="admin_login.php?logout=1">Sign out</a>
</header>
<main>
  <div class="kpis" id="kpis"></div>
  <div class="card">
    <h2>Room requests</h2>
    <div class="tabs" id="tabs"></div>
    <div class="tablewrap" id="reqs"><p class="note">Loading…</p></div>
  </div>

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
        <img id="mapimg" alt="Floor map">
        <div class="hall">HALLWAY</div><div class="ent">MAIN ENTRANCE</div><div class="tplnote">Map template · add map.png</div>
        <svg viewBox="0 0 100 100" preserveAspectRatio="none"><polyline id="route" class="path" points=""/></svg>
        <div id="pin" class="pin" hidden></div>
      </div>
      <div class="legend"><span>Badge: pending requests on this date</span><span>Pink: occupied</span></div>
      <div class="stats" id="stats"></div>
      <div id="detail"></div>
    </div>
  </div>
</main>
<script>
const ADMIN = true, BASE = '', CSRF = <?= json_encode(csrf()) ?>;
</script>
<script src="app.js"></script>
<script>
let filter = 'pending';
const labels = {pending:'Pending', approved:'Approved', rejected:'Rejected', cancelled:'Cancelled', all:'All'};
async function loadReqs() {
  let j; try { j = await (await fetch('api.php?action=list_requests&status=' + (filter === 'all' ? '' : filter))).json(); } catch { return; }
  if (!j.requests) return;
  const c = j.counts || {};
  $('kpis').innerHTML = ['pending','approved','rejected'].map(s => `<div class="kpi"><b>${c[s] || 0}</b>${labels[s]}</div>`).join('') + `<div class="kpi"><b>${data ? data.rooms.length : '–'}</b>Rooms</div>`;
  $('tabs').innerHTML = Object.keys(labels).map(s => `<button class="${s === filter ? 'on' : ''}" onclick="filter='${s}';loadReqs()">${labels[s]}${c[s] ? ' (' + c[s] + ')' : ''}</button>`).join('');
  $('reqs').innerHTML = j.requests.length ? `<table><tr><th>Student</th><th>Room</th><th>When</th><th>Reason</th><th>Status</th><th></th></tr>${j.requests.map(r => `
    <tr><td><b>${esc(r.full_name)}</b><br><span class="note">${esc(r.student_no)} · ${esc(r.course)} · ${esc(r.year_level)}</span></td>
    <td><a href="#" onclick="focusRoom(${r.room_id}, '${esc(r.req_date)}');return false">${esc(r.room_code)}</a></td>
    <td>${esc(r.req_date)}<br><span class="note">${r.s}–${r.e}</span></td><td>${esc(r.reason)}</td>
    <td><span class="tag ${r.status}">${labels[r.status]}</span>${r.admin_note ? `<br><span class="note">${esc(r.admin_note)}</span>` : ''}</td>
    <td class="acts">${r.status === 'pending' ? `<button class="g" onclick="decide(${r.id},'approved')">Accept</button><button class="r" onclick="decide(${r.id},'rejected')">Reject</button>`
      : r.status === 'cancelled' ? '' : `<button class="x" onclick="decide(${r.id},'${r.status === 'approved' ? 'rejected' : 'approved'}')">${r.status === 'approved' ? 'Revoke' : 'Approve instead'}</button>`}</td></tr>`).join('')}</table>`
    : `<p class="note">No ${filter === 'all' ? '' : labels[filter].toLowerCase() + ' '}requests.</p>`;
}
async function decide(id, status) {
  const note = prompt(status === 'approved' ? 'Optional note for the student:' : 'Reason for rejecting (shown to the student):', '');
  if (note === null) return;
  const j = await post('decide', {id, status, note});
  if (j.error) toast(j.error); else { toast('Request ' + status); loadReqs(); search(true); }
}
loadReqs(); setInterval(loadReqs, 20000);
</script>
</body></html>
