/**
 * Funciones auxiliares para armar el informe en Word con formato APA 7
 * (librería docx de npm). Las usa generar_informe.js.
 */
const fs = require('fs');
const path = require('path');
const d = require('docx');

const FUENTE = 'Times New Roman';
const CODIGO = 'Consolas';
const LINEA_APA = { style: d.BorderStyle.SINGLE, size: 8, color: '000000' };
const SIN = { style: d.BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const GRILLA = { style: d.BorderStyle.SINGLE, size: 4, color: '8C8C8C' };

let nFigura = 0;
let nTabla = 0;
let ancho = 624; // px útiles: 6,5 in en vertical; 9 in (864 px) en horizontal

function horizontal(si) { ancho = si ? 864 : 624; }
const twips = () => (ancho === 864 ? 12960 : 9360);
const anchosTw = anchos => anchos.map(a => Math.round(a / 100 * twips()));

/** Texto con **negrita** y *cursiva* en línea. */
function runs(texto, base = {}) {
    const partes = String(texto).split(/(\*\*[^*]+\*\*|\*[^*]+\*)/g).filter(Boolean);
    return partes.map(p => {
        if (p.startsWith('**')) return new d.TextRun({ text: p.slice(2, -2), bold: true, font: FUENTE, ...base });
        if (p.startsWith('*') && p.length > 2) return new d.TextRun({ text: p.slice(1, -1), italics: true, font: FUENTE, ...base });
        return new d.TextRun({ text: p, font: FUENTE, ...base });
    });
}

const H1 = t => new d.Paragraph({ heading: d.HeadingLevel.HEADING_1, children: [new d.TextRun(t)], pageBreakBefore: true });
const H1s = t => new d.Paragraph({ heading: d.HeadingLevel.HEADING_1, children: [new d.TextRun(t)] });
const H2 = t => new d.Paragraph({ heading: d.HeadingLevel.HEADING_2, children: [new d.TextRun(t)] });
const H3 = t => new d.Paragraph({ heading: d.HeadingLevel.HEADING_3, children: [new d.TextRun(t)] });
const H4 = t => new d.Paragraph({ heading: d.HeadingLevel.HEADING_4, children: [new d.TextRun(t)] });

/** Párrafo APA: doble espacio y sangría de primera línea de 1,27 cm. */
const P = (t, op = {}) => new d.Paragraph({
    children: runs(t), spacing: { line: 480, after: 0 }, indent: op.sinSangria ? undefined : { firstLine: 720 },
    alignment: op.centro ? d.AlignmentType.CENTER : d.AlignmentType.LEFT,
});

/** Lista con viñetas (o numerada si numerada = true). */
const L = (items, numerada = false) => items.map((t, i) => new d.Paragraph({
    children: runs((numerada ? `${i + 1}. ` : '') + t),
    bullet: numerada ? undefined : { level: 0 },
    indent: numerada ? { left: 720, hanging: 360 } : undefined,
    spacing: { line: 360, after: 60 },
}));

const salto = () => new d.Paragraph({ children: [new d.PageBreak()] });
const vacio = () => new d.Paragraph({ children: [] });

function tamPng(archivo) {
    const b = fs.readFileSync(archivo);
    return [b.readUInt32BE(16), b.readUInt32BE(20), b];
}

/** Imagen ajustada al ancho útil (y a una altura máxima). */
function imagen(archivo, maxAncho = ancho, maxAlto = ancho === 864 ? 440 : 640) {
    const [w, h, buf] = tamPng(archivo);
    let ew = Math.min(maxAncho, w / 2 > 200 ? w / 2 : w);
    let eh = ew * h / w;
    if (eh > maxAlto) { eh = maxAlto; ew = eh * w / h; }
    return new d.ImageRun({ type: 'png', data: buf, transformation: { width: Math.round(ew), height: Math.round(eh) } });
}

/** Figura APA: "Figura N" en negrita, título en cursiva, imagen y nota. */
function figura(titulo, archivo, nota, op = {}) {
    nFigura++;
    const r = [
        new d.Paragraph({ children: [new d.TextRun({ text: `Figura ${nFigura}`, bold: true, font: FUENTE })], spacing: { before: 240, after: 0 }, keepNext: true }),
        new d.Paragraph({ children: [new d.TextRun({ text: titulo, italics: true, font: FUENTE })], spacing: { after: 120 }, keepNext: true }),
        new d.Paragraph({ children: [imagen(archivo, op.ancho, op.alto)], alignment: d.AlignmentType.CENTER, keepNext: !!nota }),
    ];
    if (nota) r.push(new d.Paragraph({ children: [new d.TextRun({ text: 'Nota. ', italics: true, font: FUENTE, size: 20 }), ...runs(nota, { size: 20 })], spacing: { after: 240 } }));
    return r;
}

function celda(texto, op = {}) {
    const lineas = String(texto ?? '').split('\n');
    return new d.TableCell({
        children: lineas.map(l => new d.Paragraph({
            children: runs(l, { size: op.tam || 20, bold: op.negrita, font: op.mono ? CODIGO : FUENTE }),
            alignment: op.derecha ? d.AlignmentType.RIGHT : d.AlignmentType.LEFT,
        })),
        borders: op.bordes || { top: SIN, bottom: SIN, left: SIN, right: SIN },
        shading: op.fondo ? { fill: op.fondo, type: d.ShadingType.CLEAR, color: 'auto' } : undefined,
        margins: { top: 50, bottom: 50, left: 90, right: 90 },
        columnSpan: op.span,
        width: op.ancho ? { size: Math.round(op.ancho / 100 * twips()), type: d.WidthType.DXA } : undefined,
        verticalAlign: d.VerticalAlign.TOP,
    });
}

/** Tabla APA: solo líneas horizontales (arriba, bajo el encabezado y al final). */
function tabla(titulo, encabezados, filas, nota, op = {}) {
    nTabla++;
    const anchos = op.anchos || encabezados.map(() => Math.floor(100 / encabezados.length));
    const tam = op.tam || 20;
    const cab = new d.TableRow({
        tableHeader: true,
        children: encabezados.map((e, i) => celda(e, { negrita: true, tam, ancho: anchos[i], bordes: { top: LINEA_APA, bottom: LINEA_APA, left: SIN, right: SIN } })),
    });
    const cuerpo = filas.map((f, k) => new d.TableRow({
        cantSplit: true,
        children: f.map((v, i) => celda(v, {
            tam, ancho: anchos[i], mono: op.mono && op.mono.includes(i), derecha: op.derecha && op.derecha.includes(i),
            bordes: { top: SIN, left: SIN, right: SIN, bottom: k === filas.length - 1 ? LINEA_APA : SIN },
        })),
    }));
    const r = [
        new d.Paragraph({ children: [new d.TextRun({ text: `Tabla ${nTabla}`, bold: true, font: FUENTE })], spacing: { before: 240 }, keepNext: true }),
        new d.Paragraph({ children: [new d.TextRun({ text: titulo, italics: true, font: FUENTE })], spacing: { after: 120 }, keepNext: true }),
        new d.Table({ rows: [cab, ...cuerpo], width: { size: twips(), type: d.WidthType.DXA }, columnWidths: anchosTw(anchos), layout: d.TableLayoutType.FIXED }),
    ];
    if (nota) r.push(new d.Paragraph({ children: [new d.TextRun({ text: 'Nota. ', italics: true, font: FUENTE, size: 20 }), ...runs(nota, { size: 20 })], spacing: { before: 60, after: 240 } }));
    else r.push(vacio());
    return r;
}

/** Ficha de caso de uso (tabla de dos columnas con grilla suave). */
function ficha(cu, actores) {
    nTabla++;
    const b = { top: GRILLA, bottom: GRILLA, left: GRILLA, right: GRILLA };
    const fila = (k, v) => new d.TableRow({ cantSplit: true, children: [celda(k, { negrita: true, fondo: 'F1E4D3', bordes: b, ancho: 24 }), celda(v, { bordes: b, ancho: 76 })] });
    const num = l => l.map((t, i) => `${i + 1}. ${t}`).join('\n');
    const filas = [
        fila('Caso de uso', `${cu.id} ${cu.nombre}`),
        fila('Ciclo / paquete', `Ciclo ${cu.ciclo} · ${cu.paquete}`),
        fila('Actores', cu.actores.map(a => actores[a]).join(', ')),
        fila('Propósito', cu.proposito),
        fila('Precondición', cu.pre),
        fila('Flujo principal', num(cu.flujo)),
        fila('Flujos alternos', cu.alternos.join('\n')),
        fila('Postcondición', cu.post),
        fila('Ruta en el sistema', cu.ruta),
    ];
    return [
        new d.Paragraph({ children: [new d.TextRun({ text: `Tabla ${nTabla}`, bold: true, font: FUENTE })], spacing: { before: 240 }, keepNext: true }),
        new d.Paragraph({ children: [new d.TextRun({ text: `Ficha del caso de uso ${cu.id} ${cu.nombre}`, italics: true, font: FUENTE })], spacing: { after: 120 }, keepNext: true }),
        new d.Table({ rows: filas, width: { size: twips(), type: d.WidthType.DXA }, columnWidths: anchosTw([24, 76]), layout: d.TableLayoutType.FIXED }),
        vacio(),
    ];
}

/** Bloque de código: Consolas 8,5, interlineado sencillo y fondo gris claro. */
function codigo(texto, tam = 17) {
    const lineas = String(texto).replace(/\r/g, '').replace(/\t/g, '    ').split('\n');
    while (lineas.length && !lineas[lineas.length - 1].trim()) lineas.pop();
    return [
        ...lineas.map((l, i) => new d.Paragraph({
            children: [new d.TextRun({ text: l || ' ', font: CODIGO, size: tam })],
            shading: { fill: 'F4F1EC', type: d.ShadingType.CLEAR, color: 'auto' },
            spacing: { line: 240, before: i === 0 ? 120 : 0, after: i === lineas.length - 1 ? 160 : 0 },
            indent: { left: 120, right: 120 },
        })),
    ];
}

/** Resultado de una consulta como tabla compacta. */
function resultado(columnas_, filas, tam = 16) {
    const columnas = columnas_;
    if (!filas || !filas.length) return [new d.Paragraph({ children: runs('*(sin filas)*', { size: 18 }) })];
    const b = { top: GRILLA, bottom: GRILLA, left: GRILLA, right: GRILLA };
    const recorte = v => { const s = v === null ? 'null' : typeof v === 'object' ? JSON.stringify(v) : String(v); return s.length > 60 ? s.slice(0, 57) + '…' : s; };
    return [
        new d.Table({
            rows: [
                new d.TableRow({ tableHeader: true, children: columnas.map(c => celda(c, { negrita: true, tam, fondo: 'F1E4D3', bordes: b, mono: [0] })) }),
                ...filas.map(f => new d.TableRow({ children: columnas.map(c => celda(recorte(f[c]), { tam, bordes: b })) })),
            ],
            width: { size: twips(), type: d.WidthType.DXA },
            columnWidths: anchosTw(columnas_.map(() => 100 / columnas_.length)),
        }),
        vacio(),
    ];
}

/** Referencia APA con sangría francesa. */
const ref = t => new d.Paragraph({ children: runs(t), spacing: { line: 480 }, indent: { left: 720, hanging: 720 } });

module.exports = { d, FUENTE, H1, H1s, H2, H3, H4, P, L, salto, vacio, figura, tabla, ficha, codigo, resultado, ref, imagen, horizontal, runs, celda, SIN };
