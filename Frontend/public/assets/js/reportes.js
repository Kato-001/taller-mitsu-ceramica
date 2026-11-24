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

  /* ------------------------------------------------ Comando de voz */
  const boton = document.getElementById('voz-boton');
  if (!boton) return;
  const frase = document.getElementById('voz-frase');
  const estado = document.getElementById('voz-estado');
  const Reconocedor = window.SpeechRecognition || window.webkitSpeechRecognition;
  // Brave trae el micrófono pero bloquea su servicio de dictado, y Firefox no lo tiene:
  // ahí se usa Vosk, un reconocedor libre que corre dentro de la página (assets/voz).
  const soloVosk = !Reconocedor || !!navigator.brave;
  if (soloVosk) estado.textContent += ' La primera vez se descargan 40 MB para dictar en este navegador.';

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
  let modelo = null;
  let cortar = null;

  function cargarModelo() {
    if (modelo) return modelo;
    modelo = new Promise((ok, mal) => {
      const s = document.createElement('script');
      s.src = '/assets/voz/vosk.js';
      s.onload = () => window.Vosk.createModel(new URL('/assets/voz/modelo-es.tar.gz', location.href).href).then(ok, mal);
      s.onerror = () => mal(new Error('vosk'));
      document.head.append(s);
    });
    modelo.catch(() => { modelo = null; });
    return modelo;
  }

  async function escucharVosk() {
    let m;
    try {
      estado.textContent = 'Preparando el dictado (la primera vez descarga 40 MB)… todavía no hable.';
      m = await cargarModelo();
    } catch (e) {
      parar();
      estado.textContent = 'No se pudo cargar el dictado. Revise su conexión o escriba el comando.';
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
    const kaldi = new m.KaldiRecognizer(ctx.sampleRate);
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
    kaldi.on('partialresult', (x) => { if (x.result.partial) { parcial = x.result.partial; frase.value = parcial; } });
    kaldi.on('result', (x) => { if (x.result.text) terminar(x.result.text); });
    nodo.onaudioprocess = (e) => { if (!listo) kaldi.acceptWaveform(e.inputBuffer); };
    fuente.connect(nodo);
    nodo.connect(ctx.destination);
    estado.textContent = 'Escuchando… diga su comando.';
  }

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
