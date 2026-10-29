/* =====================================================================
   Taller Mitsü · Punto de venta (CU18)
   El carrito vive en el navegador; al cobrar se envía como JSON.
   El servidor vuelve a validar todo y toma el precio de la base de datos.
   ===================================================================== */
(function () {
  'use strict';

  const datos = document.getElementById('catalogo-json');
  const form = document.getElementById('form-venta');
  if (!datos || !form) return;

  const catalogo = new Map(JSON.parse(datos.textContent).map((p) => [p.id, p]));
  const carrito = new Map();   // pieza_id → cantidad
  const lista = document.getElementById('carrito-items');
  const vacio = document.getElementById('carrito-vacio');
  const totalEl = document.getElementById('total');
  const json = document.getElementById('carrito-json');
  const btn = document.getElementById('btn-cobrar');
  const recibido = document.getElementById('recibido');
  const cambio = document.getElementById('cambio');
  const campoRecibido = document.getElementById('campo-recibido');
  const bs = (n) => 'Bs ' + n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const aviso = (msg) => (window.mitsuToast ? window.mitsuToast('error', msg) : alert(msg));

  function total() {
    let t = 0;
    carrito.forEach((cant, id) => { t += catalogo.get(id).precio * cant; });
    return Math.round(t * 100) / 100;
  }

  function esEfectivo() {
    const m = form.querySelector('input[name="metodo_pago_id"]:checked');
    return m && m.dataset.efectivo === '1';
  }

  function pintar() {
    lista.querySelectorAll('.carrito-item').forEach((n) => n.remove());
    carrito.forEach((cant, id) => {
      const p = catalogo.get(id);
      const fila = document.createElement('div');
      fila.className = 'carrito-item';
      fila.innerHTML = '<div><strong></strong><small></small></div>'
        + '<div class="cantidad-ctrl"><button type="button" data-menos aria-label="Quitar uno">−</button><span></span><button type="button" data-mas aria-label="Agregar uno">+</button></div>'
        + '<strong class="mono"></strong>';
      fila.querySelector('div strong').textContent = p.nombre;          // texto plano: sin inyección de HTML
      fila.querySelector('small').textContent = p.codigo + ' · ' + bs(p.precio);
      fila.querySelector('.cantidad-ctrl span').textContent = cant;
      fila.lastElementChild.textContent = bs(p.precio * cant);
      fila.querySelector('[data-menos]').addEventListener('click', () => cambiar(id, -1));
      fila.querySelector('[data-mas]').addEventListener('click', () => cambiar(id, 1));
      lista.appendChild(fila);
    });
    vacio.hidden = carrito.size > 0;
    const t = total();
    totalEl.textContent = bs(t);
    json.value = JSON.stringify(Array.from(carrito, ([pieza_id, cantidad]) => ({ pieza_id, cantidad })));
    campoRecibido.style.display = esEfectivo() ? '' : 'none';
    recibido.required = esEfectivo() && carrito.size > 0;
    recibido.min = esEfectivo() ? t : 0;
    const r = parseFloat(recibido.value);
    cambio.textContent = isNaN(r) ? 'Cambio: Bs 0,00' : (r >= t ? 'Cambio: ' + bs(r - t) : 'Faltan ' + bs(t - r));
    cambio.style.color = !isNaN(r) && r < t ? 'var(--danger)' : '';
    btn.disabled = carrito.size === 0 || (esEfectivo() && (isNaN(r) || r < t));
  }

  function cambiar(id, delta) {
    const p = catalogo.get(id);
    const nueva = (carrito.get(id) || 0) + delta;
    if (nueva > p.stock) { aviso('Solo hay ' + p.stock + ' unidad(es) de ' + p.nombre + '.'); return; }
    if (nueva <= 0) carrito.delete(id); else carrito.set(id, nueva);
    pintar();
  }

  document.querySelectorAll('[data-pieza]').forEach((b) => b.addEventListener('click', () => cambiar(parseInt(b.dataset.pieza, 10), 1)));
  form.querySelectorAll('input[name="metodo_pago_id"]').forEach((r) => r.addEventListener('change', pintar));
  recibido.addEventListener('input', pintar);

  // Búsqueda instantánea en el catálogo
  const buscar = document.getElementById('buscar-pieza');
  buscar.addEventListener('input', () => {
    const q = buscar.value.trim().toLowerCase();
    document.querySelectorAll('[data-buscar]').forEach((c) => { c.hidden = q !== '' && !c.dataset.buscar.includes(q); });
  });

  form.addEventListener('submit', (e) => {
    if (carrito.size === 0) { e.preventDefault(); aviso('Agregue al menos una pieza.'); }
  });

  // Modal para registrar un cliente nuevo sin salir de la venta
  const modal = document.getElementById('modal-cliente');
  const abrir = document.querySelector('[data-abrir-cliente]');
  if (modal && abrir) {
    abrir.addEventListener('click', (e) => { e.preventDefault(); modal.classList.add('open'); modal.querySelector('input[name="nombre"]').focus(); });
    modal.querySelectorAll('[data-cerrar-cliente]').forEach((b) => b.addEventListener('click', () => modal.classList.remove('open')));
  }

  pintar();
})();
