/**
 * Captura las pantallas del sistema publicado (prototipos de cada caso de uso).
 *
 *   NODE_PATH=<carpeta con puppeteer-core> node Docs/informe/capturas.js [https://taller-mitsu-ceramica.vercel.app]
 *
 * Una entrada con "enlace" abre primero la ruta y luego el primer enlace que contenga ese texto.
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const BASE = process.argv[2] || 'https://taller-mitsu-ceramica.vercel.app';
const SALIDA = path.join(__dirname, 'capturas');
const CUENTAS = {
    admin: ['admin@mitsu.test', 'Admin2026!'], ventas: ['ventas@mitsu.test', 'Ventas2026!'],
    maestra: ['maestra@mitsu.test', 'Maestra2026!'], alumno: ['alumno@mitsu.test', 'Alumno2026!'],
    cliente: ['cliente@mitsu.test', 'Cliente2026!'],
};
const PANTALLAS = {
    anonimo: [['CU01_login', '/login'], ['CU02_recuperar', '/recuperar'], ['CU12_registro', '/registro'],
              ['CU20_tienda', '/'], ['CU20_pieza', '/tienda/pieza?id=1'], ['CU20_tienda_movil', '/', 390]],
    admin: [['CU01_panel', '/panel'], ['CU03_usuarios', '/usuarios'], ['CU03_usuario_nuevo', '/usuarios/nuevo'],
            ['CU04_roles', '/roles'], ['CU05_bitacora', '/bitacora'], ['CU06_configuracion', '/configuracion'],
            ['CU07_maestras', '/maestras'], ['CU07_maestra_nueva', '/maestras/nueva'], ['CU08_talleres', '/talleres'],
            ['CU09_horarios', '/horarios'], ['CU09_calendario', '/clases']],
    ventas: [['CU10_clase', '/clases', 0, '/clases/ver'], ['CU12_alumnos', '/alumnos'], ['CU13_materiales', '/materiales'],
             ['CU14_proveedores', '/proveedores'], ['CU15_compra_nueva', '/compras/nueva'], ['CU15_compra', '/compras', 0, '/compras/ver'],
             ['CU16_kardex', '/materiales', 0, '/materiales/kardex'], ['CU17_piezas', '/piezas'], ['CU17_pieza_form', '/piezas/form'],
             ['CU18_punto_venta', '/ventas/nueva'], ['CU18_nota', '/ventas', 0, '/ventas/ver'], ['CU19_caja', '/caja'],
             ['CU22_pedidos', '/pedidos'], ['CU22_pedido', '/pedidos', 0, '/pedidos/ver']],
    maestra: [['CU11_panel_maestra', '/panel'], ['CU11_asistencia', '/panel', 0, '/clases/ver']],
    alumno: [['CU12_portal', '/portal'], ['CU10_reservar', '/portal/reservar']],
    cliente: [['CU20_mis_pedidos', '/mis-pedidos'], ['CU21_comprobante', '/mis-pedidos', 0, '/mis-pedidos/ver']],
};

const foto = (p, nombre) => p.screenshot({ path: path.join(SALIDA, nombre + '.png') }).then(() => console.log(nombre, p.url().replace(BASE, '')));
const ir = (p, accion) => Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), accion]);

/** Carrito → checkout → pasarela (QR y tarjeta) y al final cancela el pedido para devolver el stock. */
async function compraDePrueba(p) {
    await p.goto(BASE + '/tienda/pieza?id=3', { waitUntil: 'networkidle2' });
    await ir(p, p.click('form[action="/tienda/carrito/agregar"] button'));
    await p.goto(BASE + '/tienda/carrito', { waitUntil: 'networkidle2' });
    await foto(p, 'CU20_carrito');
    await p.goto(BASE + '/tienda/checkout', { waitUntil: 'networkidle2' });
    await p.$eval('input[name=telefono]', el => { el.value = '72040001'; });
    await foto(p, 'CU20_checkout');
    await ir(p, p.click('form[action="/tienda/checkout"] button.btn-primary'));
    await new Promise(t => setTimeout(t, 900));
    await foto(p, 'CU21_pasarela');
    await p.click('[data-metodo=tarjeta]');
    await new Promise(t => setTimeout(t, 500));
    await foto(p, 'CU21_tarjeta');
    await ir(p, p.$eval('form[action="/mis-pedidos/cancelar"]', f => f.submit()));
    console.log('pedido de prueba cancelado:', p.url().replace(BASE, ''));
}

(async () => {
    fs.mkdirSync(SALIDA, { recursive: true });
    const navegador = await puppeteer.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', headless: true });
    for (const [cuenta, lista] of Object.entries(PANTALLAS)) {
        const contexto = await navegador.createBrowserContext();
        const p = await contexto.newPage();
        await p.setViewport({ width: 1366, height: 800 });
        await p.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
        await p.evaluateOnNewDocument(() => { try { localStorage.setItem('mitsu-tema', 'light'); } catch (e) {} });
        if (CUENTAS[cuenta]) {
            await p.goto(BASE + '/login', { waitUntil: 'networkidle2' });
            await p.type('input[name=correo]', CUENTAS[cuenta][0]);
            await p.type('input[name=password]', CUENTAS[cuenta][1]);
            await Promise.all([p.waitForNavigation({ waitUntil: 'networkidle2' }), p.click('button[type=submit]')]);
        }
        for (const [nombre, ruta, ancho, enlace] of lista) {
            const w = ancho || 1366;
            await p.setViewport({ width: w, height: w < 600 ? 844 : 800, isMobile: w < 600 });
            await p.goto(BASE + ruta, { waitUntil: 'networkidle2' });
            if (enlace) {
                const href = await p.evaluate(t => (Array.from(document.querySelectorAll('a')).find(a => a.getAttribute('href').includes(t)) || {}).href, enlace);
                if (!href) { console.log(nombre, 'SIN ENLACE'); continue; }
                await p.goto(href, { waitUntil: 'networkidle2' });
            }
            await new Promise(t => setTimeout(t, 900));
            await p.screenshot({ path: path.join(SALIDA, nombre + '.png') });
            console.log(nombre, p.url().replace(BASE, ''));
        }
        if (cuenta === 'cliente') {
            await compraDePrueba(p);
        }
        await contexto.close();
    }
    await navegador.close();
})();
