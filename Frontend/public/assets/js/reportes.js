/* =====================================================================
   Taller Mitsü · Reportes (CU23) y comando de voz (CU25)
   Arma los filtros según el tipo de campo y dicta comandos con el
   reconocimiento de voz del navegador. La frase la interpreta el servidor
   con reglas fijas (ComandoVoz.php).
   ===================================================================== */
(function () {
  'use strict';

  /* ------------------------------------------------ Filtros */
  const cont = document.getElementById('filtros');
  const form = document.getElementById('form-reporte');
  if (cont && form) {
    const campos = JSON.parse(cont.dataset.campos);
    const operadores = JSON.parse(cont.dataset.operadores);
    const sinValor = JSON.parse(cont.dataset.sinValor);
    let n = cont.children.length;

    const control = (fam, campo, nombre, valor) => {
      let el;
      if (fam === 'opcion') {
        el = document.createElement('select');
        el.className = 'select';
        el.add(new Option('Elija…', ''));
        campo.opciones.forEach(([v, t]) => el.add(new Option(t, v, false, v === valor)));
        if (valor && !campo.opciones.some(([v]) => v === valor)) el.add(new Option(valor, valor, true, true));
      } else {
        el = document.createElement('input');
        el.className = 'input';
        el.type = fam === 'fecha' ? 'date' : fam === 'numero' ? 'number' : 'text';
        if (fam === 'numero') el.step = 'any';
        if (fam === 'texto') el.maxLength = 120;
        el.value = fam === 'numero' ? String(valor).replace(',', '.') : valor;
      }
      el.name = nombre;
      el.setAttribute('aria-label', 'Valor');
      return el;
    };

    const pintarValores = (fila, v, w) => {
      const zona = fila.querySelector('[data-valores]');
      const campo = campos[fila.querySelector('[data-campo]').value];
      const op = fila.querySelector('[data-operador]').value;
      zona.replaceChildren();
      if (sinValor.includes(op)) return;
      zona.append(control(campo.tipo, campo, zona.dataset.nombre + '[v]', v));
      if (op === 'entre') {
        const y = document.createElement('span');
        y.className = 'faint';
        y.textContent = 'y';
        zona.append(y, control(campo.tipo, campo, zona.dataset.nombre + '[w]', w));
      }
    };

    const pintar = (fila, op, v, w) => {
      const campo = campos[fila.querySelector('[data-campo]').value];
      const sel = fila.querySelector('[data-operador]');
      sel.replaceChildren();
      Object.entries(operadores[campo.tipo]).forEach(([k, t]) => sel.add(new Option(t, k, false, k === op)));
      pintarValores(fila, v, w);
    };

    cont.querySelectorAll('[data-filtro]').forEach((fila) => {
      const z = fila.querySelector('[data-valores]');
      pintar(fila, fila.querySelector('[data-operador]').dataset.valor, z.dataset.v, z.dataset.w);
    });

    cont.addEventListener('change', (e) => {
      const fila = e.target.closest('[data-filtro]');
      if (e.target.matches('[data-campo]')) pintar(fila, '', '', '');
      if (e.target.matches('[data-operador]')) {
        const valores = fila.querySelectorAll('[data-valores] .input, [data-valores] .select');
        pintarValores(fila, valores[0] ? valores[0].value : '', valores[1] ? valores[1].value : '');
      }
    });
    cont.addEventListener('click', (e) => {
      const b = e.target.closest('[data-quitar]');
      if (b) b.closest('[data-filtro]').remove();
    });
    document.getElementById('agregar-filtro').addEventListener('click', () => {
      if (cont.children.length >= +cont.dataset.max) return;
      cont.insertAdjacentHTML('beforeend', document.getElementById('plantilla-filtro').innerHTML.replaceAll('__i__', n++));
      pintar(cont.lastElementChild, '', '', '');
    });
    // Al cambiar de reporte cambian los campos: se carga sin los filtros anteriores
    document.getElementById('rep-tipo').addEventListener('change', (e) => { location.href = '/reportes?tipo=' + encodeURIComponent(e.target.value); });
  }

  /* ------------------------------------------------ Comando de voz */
  const boton = document.getElementById('voz-boton');
  if (!boton) return;
  const frase = document.getElementById('voz-frase');
  const estado = document.getElementById('voz-estado');
  const Reconocedor = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!Reconocedor) {
    boton.disabled = true;
    estado.textContent = 'Este navegador no permite dictar (use Chrome o Edge). Puede escribir el comando.';
    return;
  }
  // Se prueba cada opción en orden: si el servicio de dictado falla con un idioma,
  // se intenta con el siguiente y al final con el dictado local del navegador (sin internet).
  const opciones = [{ lang: 'es-ES' }, { lang: 'es-MX' }, { lang: 'es-US' }];
  if (typeof Reconocedor.available === 'function') opciones.push({ lang: 'es-ES', local: true });
  let actual = 0;
  let rec = null;
  let escuchando = false;

  const avisos = {
    'not-allowed': 'El micrófono está bloqueado: pulse el candado junto a la dirección, permita el micrófono y recargue.',
    'service-not-allowed': 'Este navegador bloquea el dictado. Abra la página en Google Chrome.',
    'no-speech': 'No se escuchó nada. Acérquese al micrófono e intente otra vez.',
    'audio-capture': 'No se encontró un micrófono. Revise que esté conectado.',
    network: 'El navegador no pudo conectarse a su servicio de dictado. Use Google Chrome (no Brave, Opera ni el navegador de VS Code), sin VPN ni bloqueadores, o escriba el comando.',
  };

  function parar() { escuchando = false; boton.classList.remove('escuchando'); }

  async function prepararLocal() {
    const datos = { langs: ['es-ES'], processLocally: true };
    const hay = await Reconocedor.available(datos);
    if (hay === 'available') return true;
    if (hay === 'unavailable') return false;
    estado.textContent = 'Descargando el dictado en español (solo la primera vez)…';
    return Reconocedor.install(datos);
  }

  async function escuchar() {
    const op = opciones[actual];
    if (op.local) {
      try {
        if (!(await prepararLocal())) throw new Error('sin dictado local');
      } catch (e) { parar(); actual = 0; estado.textContent = avisos.network; return; }
    }
    rec = new Reconocedor();
    rec.lang = op.lang;
    rec.interimResults = true;
    if (op.local) rec.processLocally = true;
    let dicho = '';
    let error = '';
    rec.onstart = () => { estado.textContent = 'Escuchando… diga su comando.'; };
    rec.onresult = (e) => {
      dicho = Array.from(e.results).map((r) => r[0].transcript).join(' ').trim();
      frase.value = dicho;
    };
    rec.onerror = (e) => { error = e.error; };
    rec.onend = () => {
      if (!dicho && (error === 'network' || error === 'language-not-supported') && actual < opciones.length - 1) {
        actual += 1;
        escuchar();
        return;
      }
      parar();
      if (dicho) {
        estado.textContent = 'Consultando: ' + dicho;
        document.getElementById('form-voz').submit();
      } else if (error && error !== 'aborted') {
        if (error === 'network') actual = 0;
        estado.textContent = avisos[error] || 'No se pudo usar el dictado. Puede escribir el comando.';
      } else {
        estado.textContent = 'No se escuchó nada. Intente otra vez.';
      }
    };
    rec.start();
  }

  boton.addEventListener('click', () => {
    if (escuchando) { if (rec) rec.stop(); return; }
    escuchando = true;
    boton.classList.add('escuchando');
    estado.textContent = 'Preparando el micrófono…';
    escuchar().catch(() => { parar(); estado.textContent = 'No se pudo usar el dictado. Puede escribir el comando.'; });
  });
})();
