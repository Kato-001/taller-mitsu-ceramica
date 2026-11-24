/* =====================================================================
   Taller Mitsü · Reportes (CU23) y comando de voz (CU25)
   Arma los filtros según el tipo de campo y dicta comandos con el
   reconocimiento de voz del navegador o, si no lo permite, con Vosk. La frase la interpreta el servidor
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

  // Después de un comando de voz se baja al resultado
  const resultado = document.querySelector('#resultado[data-ir]');
  if (resultado) setTimeout(() => document.getElementById('form-reporte').scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);

  /* ------------------------------------------------ Comando de voz */
  const boton = document.getElementById('voz-boton');
  if (!boton) return;
  const frase = document.getElementById('voz-frase');
  const estado = document.getElementById('voz-estado');
  const Reconocedor = window.SpeechRecognition || window.webkitSpeechRecognition;
  // Brave trae el micrófono pero bloquea su servicio de dictado, y Firefox no lo tiene:
  // ahí se usa Vosk, un reconocedor libre que corre dentro de la página (assets/voz).
  try { indexedDB.deleteDatabase('/vosk'); } catch (e) { /* restos de la versión anterior */ }
  const soloVosk = !Reconocedor || !!navigator.brave;

  // Se prueba cada opción en orden: si el servicio de dictado falla con un idioma,
  // se intenta con el siguiente y al final con el dictado local del navegador (sin internet).
  const opciones = [{ lang: 'es-ES' }, { lang: 'es-MX' }, { lang: 'es-US' }];
  if (Reconocedor && typeof Reconocedor.available === 'function') opciones.push({ lang: 'es-ES', local: true });
  let actual = 0;
  let rec = null;
  let escuchando = false;

  const avisos = {
    'not-allowed': 'El micrófono está bloqueado: pulse el candado junto a la dirección, permita el micrófono y recargue.',
    'service-not-allowed': 'Este navegador bloquea el dictado. Abra la página en Microsoft Edge o Google Chrome.',
    'no-speech': 'No se escuchó nada. Acérquese al micrófono e intente otra vez.',
    'audio-capture': 'No se encontró un micrófono. Revise que esté conectado.',
  };

  function parar() { escuchando = false; boton.classList.remove('escuchando'); }

  function consultar(dicho) {
    frase.value = dicho;
    estado.textContent = 'Consultando: ' + dicho;
    document.getElementById('form-voz').submit();
  }

  /* Dictado con Vosk */
  // Solo se reconocen las palabras de los comandos: así se equivoca mucho menos.
  // "stock" no está en el diccionario español de Vosk; se oye "esto" y el servidor lo corrige.
  const VOCABULARIO = ('reporte reportes productos producto piezas vendidos vendidas ventas venta pedidos pedido tienda compras compra materiales material inventario '
    + 'movimientos consumo merma reservas reserva asistencia asistió faltó clases clase alumnos clientes cajas caja calificaciones bitácora '
    + 'esto bajo agotado suficiente precio total puntaje cantidad unidades mínimo con en de del el la los las a al y por que este esta '
    + 'mayor mayores menor menores igual entre más menos bolivianos hoy ayer mes pasado año semana última últimos días '
    + 'efectivo tarjeta crédito contado transferencia entregados entregado pagado pagados cancelado cancelados pendiente válida anulada activo inactivo '
    + 'agrupadas agrupados agrupado agrupada taller maestra categoría proveedor estado cliente método pago vendedor nivel ciudad tipo '
    + 'cero uno dos tres cuatro cinco seis siete ocho nueve diez quince veinte treinta cuarenta cincuenta sesenta setenta ochenta noventa cien doscientos quinientos mil '
    + 'pe efe excel ese uve hola').split(' ').concat('[unk]');

  let modelo = null;
  let cortar = null;

  const MODELO = new URL('/assets/voz/modelo-es.tar.gz', location.href).href;
  const avisar = (t) => { estado.textContent = t; };

  /* Descarga por partes: muestra el avance, reintenta y continúa donde se cortó. */
  async function descargar() {
    const cabeza = await fetch(MODELO, { method: 'HEAD' });
    const total = Number(cabeza.headers.get('content-length')) || 39748104;
    const cache = await caches.open('mitsu-voz');
    const tramo = 2 * 1024 * 1024;
    const partes = [];
    let bajado = 0;
    for (let ini = 0; ini < total; ini += tramo) {
      const clave = '/voz-parte/' + total + '/' + ini;
      let blob = null;
      const guardada = await cache.match(clave);
      if (guardada) blob = await guardada.blob();
      for (let intento = 1; !blob; intento += 1) {
        try {
          const fin = Math.min(ini + tramo, total) - 1;
          const r = await fetch(MODELO, { headers: { Range: 'bytes=' + ini + '-' + fin }, signal: AbortSignal.timeout(120000) });
          if (r.status === 200) return r.blob();   // el servidor no admite partes: llega completo
          if (r.status !== 206) throw new Error('HTTP ' + r.status);
          blob = await r.blob();
          if (blob.size !== fin - ini + 1) { blob = null; throw new Error('parte incompleta'); }
          await cache.put(clave, new Response(blob)).catch(() => {});
        } catch (e) {
          if (intento >= 5) throw e;
          avisar('La conexión se cortó; reintentando…');
          await new Promise((t) => setTimeout(t, 1500 * intento));
        }
      }
      partes.push(blob);
      bajado += blob.size;
      avisar('Descargando el dictado: ' + Math.round(100 * bajado / total) + ' % (solo la primera vez). Mientras tanto puede escribir el comando.');
    }
    return new Blob(partes, { type: 'application/gzip' });
  }

  function ponerScript() {
    return new Promise((ok, mal) => {
      if (window.Vosk) { ok(); return; }
      const s = document.createElement('script');
      s.src = '/assets/voz/vosk.js?v=2';
      s.onload = () => ok();
      s.onerror = () => mal(new Error('vosk.js'));
      document.head.append(s);
    });
  }

  /* Las partes quedan guardadas en el navegador (Cache API): las siguientes veces no se descarga.
     El modelo se prepara en memoria: cada pestaña usa el suyo. */
  function crear(url) {
    return new Promise((ok, mal) => {
      const m = new window.Vosk.Model(url);
      const limite = setTimeout(() => { m.worker.terminate(); mal(new Error('tardó demasiado')); }, 90000);
      m.on('load', (x) => { clearTimeout(limite); if (x.result) ok(m); else mal(new Error('modelo')); });
      m.on('error', (x) => { clearTimeout(limite); m.worker.terminate(); mal(new Error(x.error || 'modelo')); });
    });
  }

  function cargarModelo() {
    if (modelo) return modelo;
    modelo = (async () => {
      avisar('Preparando el dictado…');
      await ponerScript();
      const url = URL.createObjectURL(await descargar());
      avisar('Preparando el dictado… espere unos segundos.');
      try {
        return await crear(url);
      } catch (e) {
        return await crear(url);   // segundo intento
      }
    })();
    modelo.then(() => { if (!escuchando) avisar('Dictado listo: pulse el micrófono y hable.'); },
      (e) => { modelo = null; avisar('No se pudo preparar el dictado (' + e.message + '). Pulse el micrófono para reintentar o escriba el comando.'); });
    return modelo;
  }

  async function escucharVosk() {
    let m;
    try {
      m = await cargarModelo();
    } catch (e) {
      parar();
      return;
    }
    if (!escuchando) return;
    let audio;
    try {
      audio = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, channelCount: 1 } });
    } catch (e) {
      parar();
      estado.textContent = avisos[e.name === 'NotFoundError' ? 'audio-capture' : 'not-allowed'];
      return;
    }
    const cerrarAudio = () => audio.getTracks().forEach((t) => t.stop());
    if (!escuchando) { cerrarAudio(); return; }
    const ctx = new AudioContext();
    await ctx.resume();
    const kaldi = new m.KaldiRecognizer(ctx.sampleRate, JSON.stringify(VOCABULARIO));
    const fuente = ctx.createMediaStreamSource(audio);
    const nodo = ctx.createScriptProcessor(4096, 1, 1);
    let parcial = '';
    let listo = false;
    const terminar = (dicho) => {
      if (listo) return;
      listo = true;
      clearTimeout(limite);
      nodo.disconnect();
      fuente.disconnect();
      cerrarAudio();
      ctx.close();
      kaldi.remove();
      cortar = null;
      parar();
      if (dicho) consultar(dicho); else estado.textContent = avisos['no-speech'];
    };
    const limite = setTimeout(() => terminar(parcial), 12000);
    cortar = () => terminar(parcial);
    kaldi.on('partialresult', (x) => { if (x.result.partial) { parcial = x.result.partial.replace(/\[unk\]/g, '').trim(); frase.value = parcial; } });
    kaldi.on('result', (x) => { const dicho = x.result.text.replace(/\[unk\]/g, '').trim(); if (dicho) terminar(dicho); });
    nodo.onaudioprocess = (e) => { if (!listo) kaldi.acceptWaveform(e.inputBuffer); };
    fuente.connect(nodo);
    nodo.connect(ctx.destination);
    estado.textContent = 'Escuchando… diga su comando.';
  }

  if (soloVosk) cargarModelo().catch(() => {});   // se prepara al abrir la página

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
      } catch (e) { actual = 0; await escucharVosk(); return; }
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
      if (!dicho && error === 'network') { actual = 0; escucharVosk(); return; }   // último recurso
      parar();
      if (dicho) {
        consultar(dicho);
      } else if (error && error !== 'aborted') {
        estado.textContent = avisos[error] || 'No se pudo usar el dictado. Puede escribir el comando.';
      } else {
        estado.textContent = 'No se escuchó nada. Intente otra vez.';
      }
    };
    rec.start();
  }

  boton.addEventListener('click', () => {
    if (escuchando) { if (cortar) cortar(); else if (rec) rec.stop(); else parar(); return; }
    escuchando = true;
    boton.classList.add('escuchando');
    estado.textContent = 'Preparando el micrófono…';
    (soloVosk ? escucharVosk() : escuchar()).catch(() => { parar(); estado.textContent = 'No se pudo usar el dictado. Puede escribir el comando.'; });
  });
})();
