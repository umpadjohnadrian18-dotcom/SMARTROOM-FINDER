/* Shared by the student page and the admin page. Page must define: ADMIN, CSRF, BASE ('' or '../'). */
const $ = id => document.getElementById(id), map = $('map');
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let data = null, selected = null, placing = false;

function toast(m) { const t = document.createElement('div'); t.className = 'toast'; t.textContent = m; document.body.appendChild(t); setTimeout(() => t.remove(), 3200); }
async function post(action, body) {
  const f = new FormData(); f.append('action', action); f.append('csrf', CSRF);
  for (const k in body) f.append(k, body[k]);
  try { return await (await fetch(BASE + 'api.php', {method:'POST', body:f})).json(); } catch { return {error:'Network error. Try again.'}; }
}

// map image: swap the template for assets/map.png once it exists
const img = $('mapimg');
img.onload = () => map.classList.remove('tpl');
img.onerror = () => img.remove();
img.src = BASE + 'map.png';
if (img.complete && img.naturalWidth) map.classList.remove('tpl');

$('date').value = new Date().toLocaleDateString('en-CA');
$('time').value = new Date().toTimeString().slice(0, 5);

async function search(keepSel) {
  const p = new URLSearchParams({action:'search', q:$('q').value, date:$('date').value, time:$('time').value, students:$('students').value || 0, type:$('type').value});
  try { data = await (await fetch(BASE + 'api.php?' + p)).json(); } catch { toast('Could not load rooms.'); return; }
  const shown = data.rooms.filter(r => r.match);
  if (!keepSel || !data.rooms.some(r => r.id == selected)) selected = data.result ? data.result.room_id : (shown[0]?.id ?? null);
  renderAll();
}
function renderAll() { renderList(); renderMap(); renderStats(); renderDetail(); }

function status(r) {
  if (r.busy_now) return '<span class="tag b">Occupied</span>';
  return r.free_all_day ? '<span class="tag f">Free all day</span>' : '<span class="tag f">Free now</span>';
}
function renderList() {
  const best = data.result?.room_id, shown = data.rooms.filter(r => r.match);
  $('list').innerHTML = shown.map(r => `
    <div class="room ${r.busy_now ? 'busy' : 'free'} ${r.id == selected ? 'sel' : ''}" onclick="pick(${r.id})">
      <b>${esc(r.code)}</b> ${status(r)} ${r.id == best ? '<span class="tag best">Best match</span>' : ''}
      <small>${esc(r.type)} · ${r.capacity} seats</small><small>${esc(r.equipment)}</small>
    </div>`).join('') || '<p class="note">No rooms match your search.</p>';
}
function renderMap() {
  map.querySelectorAll('.rm').forEach(e => e.remove());
  data.rooms.forEach(r => {
    const d = document.createElement('div');
    d.className = 'rm ' + (r.type === 'laboratory' ? 'lab ' : '') + (r.busy_now ? 'busy ' : '') + (r.id == selected ? 'on ' : '') + (r.pending ? 'pend' : '');
    d.dataset.p = r.pending; d.style.left = r.map_x + '%'; d.style.top = r.map_y + '%'; d.textContent = r.code; d.title = r.name;
    d.onclick = ev => { ev.stopPropagation(); pick(r.id); }; map.appendChild(d);
  });
  const res = data.result, pin = $('pin'), r = data.rooms.find(x => x.id == selected);
  if (r) { pin.hidden = false; pin.style.left = r.map_x + '%'; pin.style.top = r.map_y + '%'; } else pin.hidden = true;
  $('route').setAttribute('points', res && res.room_id == selected ? res.points.map(p => p.join(',')).join(' ') : '');
}
function renderStats() {
  const r = data.result;
  $('stats').innerHTML = r ? `<span>Path: ${r.path.join(' → ')}</span><span>Cost g(n): ${r.cost}</span><span>Heuristic h(start): ${r.h0}</span><span>Nodes visited: ${r.visited}</span>`
    : '<span>No valid room is available for these requirements.</span>';
}
function pick(id) { selected = id; renderAll(); if (typeof onPick === 'function') onPick(id); }
// request tables call this to jump to a room/date on the map
function focusRoom(id, date) { if (date) $('date').value = date; selected = id; search(true); window.scrollTo({top:0, behavior:'smooth'}); }

function renderDetail() {
  const r = data.rooms.find(x => x.id == selected); if (!r) { $('detail').innerHTML = '<p class="note">Pick a room from the list or the map.</p>'; return; }
  let h = `<h3>${esc(r.name)} ${status(r)}</h3>
    <p class="note">${esc(r.type)} · ${r.capacity} seats · ${esc(r.equipment) || 'No equipment listed'}</p>
    <b>${esc(data.day)}, ${esc(data.date)}</b>`;
  h += r.slots.length ? `<table>${r.slots.map(s => `<tr><td>${s.s}–${s.e}</td><td>${esc(s.subject)}</td>${ADMIN && !s.req ? `<td><button class="x" onclick="delSlot(${s.id})">Remove</button></td>` : ''}</tr>`).join('')}</table>`
    : '<p class="note">Nothing scheduled. This room is free all day.</p>';
  if (!ADMIN) h += `<p><button class="y" onclick="openRequest(${r.id})">Request room ${esc(r.code)}</button></p>`;
  if (ADMIN) h += `
    <div class="edit">
      <label>Type<select id="e_type"><option value="classroom" ${r.type==='classroom'?'selected':''}>Classroom</option><option value="laboratory" ${r.type==='laboratory'?'selected':''}>Laboratory</option></select></label>
      <label>Capacity<input id="e_cap" type="number" value="${r.capacity}"></label>
      <label class="w">Equipment<input id="e_eq" value="${esc(r.equipment)}"></label>
      <label>Map X %<input id="e_x" type="number" step="0.1" value="${r.map_x}"></label>
      <label>Map Y %<input id="e_y" type="number" step="0.1" value="${r.map_y}"></label>
      <div class="w acts"><button onclick="saveRoom(${r.id})">Save room</button><button class="x" onclick="togglePlace()">${placing ? 'Cancel placing' : 'Place on map by clicking'}</button></div>
      <label>Start<input id="s_start" type="time"></label><label>End<input id="s_end" type="time"></label>
      <label class="w">Subject / class<input id="s_sub"></label>
      <div class="w"><button class="y" onclick="addSlot(${r.id})">Add class on ${esc(data.day)}s</button></div>
    </div>`;
  $('detail').innerHTML = h;
}

// admin: click the map to move the selected room's marker
function togglePlace() { placing = !placing; map.classList.toggle('placing', placing); renderDetail(); }
map.addEventListener('click', ev => {
  if (!ADMIN || !placing || !$('e_x')) return;
  const b = map.getBoundingClientRect();
  $('e_x').value = ((ev.clientX - b.left) / b.width * 100).toFixed(1); $('e_y').value = ((ev.clientY - b.top) / b.height * 100).toFixed(1);
  const pin = $('pin'); pin.style.left = $('e_x').value + '%'; pin.style.top = $('e_y').value + '%'; toast('Position set. Press Save room.');
});
async function adminAct(a, b, msg) { const j = await post(a, b); if (j.error) toast(j.error); else { toast(msg); search(true); } }
const saveRoom = id => adminAct('update_room', {id, type:$('e_type').value, capacity:$('e_cap').value, equipment:$('e_eq').value, map_x:$('e_x').value, map_y:$('e_y').value}, 'Room saved');
const addSlot = room_id => adminAct('add_slot', {room_id, day:data.day, start:$('s_start').value, end:$('s_end').value, subject:$('s_sub').value}, 'Class added');
const delSlot = id => confirm('Remove this schedule entry?') && adminAct('del_slot', {id}, 'Removed');

$('go').onclick = () => search(false);
['q','students'].forEach(i => $(i).addEventListener('keydown', e => e.key === 'Enter' && search(false)));
['date','time','type'].forEach(i => $(i).addEventListener('change', () => search(false)));
search(false);
