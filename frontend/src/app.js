const api = '/api/incidencias';
const $ = s => document.querySelector(s);
const ESTADOS = ['abierta', 'en_proceso', 'resuelta'];
const PRIORIDADES = ['baja', 'media', 'alta'];

async function llamar(url, metodo, cuerpo) {
  const r = await fetch(url, {
    method: metodo,
    headers: { 'Content-Type': 'application/json' },
    body: cuerpo ? JSON.stringify(cuerpo) : undefined
  });
  const d = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(d.error || 'Error ' + r.status);
  return d;
}

function celda(texto) {
  const td = document.createElement('td');
  td.textContent = texto ?? '';
  return td;
}

function selector(opciones, actual, alCambiar, prefijoClase) {
  const sel = document.createElement('select');
  opciones.forEach(o => sel.add(new Option(o, o, false, o === actual)));
  if (prefijoClase) sel.className = prefijoClase + actual;
  sel.onchange = () => alCambiar(sel.value);
  const td = document.createElement('td');
  td.append(sel);
  return td;
}

async function cargar() {
  const datos = await llamar(api, 'GET');
  const tb = $('#lista');
  tb.replaceChildren();
  datos.forEach(i => {
    const guardar = cambios => accion(() => llamar(`${api}/${i.id}`, 'PUT', {
      titulo: i.titulo, descripcion: i.descripcion,
      estado: i.estado, prioridad: i.prioridad, ...cambios
    }));

    const del = document.createElement('button');
    del.textContent = 'Eliminar';
    del.onclick = () => confirm('¿Eliminar la incidencia?') && accion(() => llamar(`${api}/${i.id}`, 'DELETE'));
    const tdb = document.createElement('td');
    tdb.append(del);

    const tr = document.createElement('tr');
    tr.append(
      celda(i.id), celda(i.titulo), celda(i.descripcion),
      selector(PRIORIDADES, i.prioridad, v => guardar({ prioridad: v }), 'p-'),
      selector(ESTADOS, i.estado, v => guardar({ estado: v })),
      tdb
    );
    tb.append(tr);
  });
}

async function accion(fn) {
  try { await fn(); $('#msg').textContent = ''; await cargar(); }
  catch (e) { $('#msg').textContent = e.message; }
}

$('#form').onsubmit = ev => {
  ev.preventDefault();
  accion(async () => {
    await llamar(api, 'POST', {
      titulo: $('#titulo').value,
      descripcion: $('#descripcion').value,
      prioridad: $('#prioridad').value,
      estado: $('#estado').value
    });
    $('#form').reset();
  });
};

accion(cargar);
