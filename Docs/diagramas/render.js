/**
 * Convierte los .svg de Docs/diagramas/svg en .png (a doble resolución) con el navegador.
 *
 *   NODE_PATH=<carpeta con puppeteer-core> node Docs/diagramas/render.js [filtro]
 */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const origen = path.join(__dirname, 'svg');
const destino = path.join(__dirname, 'png');
const filtro = process.argv[2] || '';

(async () => {
    fs.mkdirSync(destino, { recursive: true });
    const navegador = await puppeteer.launch({ executablePath: EDGE, headless: true });
    const pagina = await navegador.newPage();
    const archivos = fs.readdirSync(origen).filter(f => f.endsWith('.svg') && f.includes(filtro));
    for (const f of archivos) {
        const svg = fs.readFileSync(path.join(origen, f), 'utf8');
        const [, w, h] = svg.match(/width="(\d+)" height="(\d+)"/);
        await pagina.setViewport({ width: +w, height: +h, deviceScaleFactor: 2 });
        await pagina.setContent(`<html><body style="margin:0">${svg}</body></html>`);
        await pagina.screenshot({ path: path.join(destino, f.replace('.svg', '.png')), clip: { x: 0, y: 0, width: +w, height: +h } });
    }
    await navegador.close();
    console.log(`${archivos.length} diagramas convertidos`);
})();
