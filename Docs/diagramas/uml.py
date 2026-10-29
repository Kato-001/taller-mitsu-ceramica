"""
Librería mínima para dibujar diagramas UML en SVG con un estilo común.

Se usa desde generar.py. Cada función recibe una especificación sencilla
(listas y tuplas) y devuelve un objeto Lienzo que se guarda como .svg; luego
render.js lo convierte a .png con el navegador.
"""

import math
from html import escape
from PIL import ImageFont

FUENTE = 'Arial, Helvetica, sans-serif'
TINTA = '#222222'
LINEA = '#3b3b3b'
RELLENO = '#fbf6ee'      # crema del taller
RELLENO_2 = '#f1e4d3'    # arcilla clara
ACENTO = '#9c5b3a'       # terracota
GRIS = '#777777'

_fuentes = {}


def ancho(texto, tam=12, negrita=False):
    clave = (tam, negrita)
    if clave not in _fuentes:
        _fuentes[clave] = ImageFont.truetype('arialbd.ttf' if negrita else 'arial.ttf', tam)
    return _fuentes[clave].getlength(str(texto))


class Lienzo:
    def __init__(self):
        self.el = []
        self.x0 = self.y0 = math.inf
        self.x1 = self.y1 = -math.inf

    def _caja(self, x0, y0, x1, y1):
        self.x0, self.y0 = min(self.x0, x0, x1), min(self.y0, y0, y1)
        self.x1, self.y1 = max(self.x1, x0, x1), max(self.y1, y0, y1)

    # --- primitivas
    def rect(self, x, y, w, h, fill=RELLENO, stroke=LINEA, rx=0, dash=None, sw=1.2):
        d = f' stroke-dasharray="{dash}"' if dash else ''
        self.el.append(f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" rx="{rx}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"{d}/>')
        self._caja(x, y, x + w, y + h)

    def elipse(self, cx, cy, rx, ry, fill=RELLENO, stroke=LINEA, sw=1.2):
        self.el.append(f'<ellipse cx="{cx:.1f}" cy="{cy:.1f}" rx="{rx:.1f}" ry="{ry:.1f}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')
        self._caja(cx - rx, cy - ry, cx + rx, cy + ry)

    def linea(self, pts, dash=None, fin=None, ini=None, stroke=LINEA, sw=1.2):
        """fin/ini: 'llena', 'abierta', 'triangulo', 'rombo', 'rombo_lleno' o None."""
        d = f' stroke-dasharray="{dash}"' if dash else ''
        m = (f' marker-end="url(#{fin})"' if fin else '') + (f' marker-start="url(#{ini}_i)"' if ini else '')
        p = ' '.join(f'{x:.1f},{y:.1f}' for x, y in pts)
        self.el.append(f'<polyline points="{p}" fill="none" stroke="{stroke}" stroke-width="{sw}"{d}{m}/>')
        for x, y in pts:
            self._caja(x, y, x, y)

    def poligono(self, pts, fill=RELLENO, stroke=LINEA, sw=1.2):
        p = ' '.join(f'{x:.1f},{y:.1f}' for x, y in pts)
        self.el.append(f'<polygon points="{p}" fill="{fill}" stroke="{stroke}" stroke-width="{sw}"/>')
        for x, y in pts:
            self._caja(x, y, x, y)

    def texto(self, x, y, s, tam=12, anchor='start', negrita=False, cursiva=False, color=TINTA, fondo=None):
        s = str(s)
        w = ancho(s, tam, negrita)
        x0 = x - (w / 2 if anchor == 'middle' else w if anchor == 'end' else 0)
        if fondo:
            self.el.append(f'<rect x="{x0 - 2:.1f}" y="{y - tam:.1f}" width="{w + 4:.1f}" height="{tam + 4:.1f}" fill="{fondo}"/>')
        estilo = (' font-weight="bold"' if negrita else '') + (' font-style="italic"' if cursiva else '')
        self.el.append(f'<text x="{x:.1f}" y="{y:.1f}" font-family="{FUENTE}" font-size="{tam}" text-anchor="{anchor}" fill="{color}"{estilo}>{escape(s)}</text>')
        self._caja(x0, y - tam, x0 + w, y + 3)
        return w

    def guardar(self, ruta, margen=18):
        x0, y0 = self.x0 - margen, self.y0 - margen
        w, h = self.x1 - self.x0 + 2 * margen, self.y1 - self.y0 + 2 * margen
        marcadores = ''
        for nombre, forma, relleno in (('llena', 'M0,0 L10,4 L0,8 z', LINEA), ('abierta', 'M0,0 L10,4 L0,8', 'none'),
                                       ('triangulo', 'M0,0 L12,6 L0,12 z', 'white'), ('rombo', 'M0,5 L7,0 L14,5 L7,10 z', 'white'),
                                       ('rombo_lleno', 'M0,5 L7,0 L14,5 L7,10 z', LINEA)):
            vb = '0 0 14 12' if nombre in ('triangulo',) else '0 0 14 10' if 'rombo' in nombre else '0 0 10 8'
            rx, ry = (12, 6) if nombre == 'triangulo' else (14, 5) if 'rombo' in nombre else (10, 4)
            mw, mh = (12, 12) if nombre == 'triangulo' else (14, 10) if 'rombo' in nombre else (10, 8)
            for suf, orient in (('', 'auto'), ('_i', 'auto-start-reverse')):
                marcadores += (f'<marker id="{nombre}{suf}" viewBox="{vb}" refX="{rx if suf == "" or "rombo" not in nombre else 0}" refY="{ry}" '
                               f'markerWidth="{mw}" markerHeight="{mh}" markerUnits="userSpaceOnUse" orient="{orient}">'
                               f'<path d="{forma}" fill="{relleno}" stroke="{LINEA}" stroke-width="1.2"/></marker>')
        svg = (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w:.0f}" height="{h:.0f}" viewBox="{x0:.1f} {y0:.1f} {w:.1f} {h:.1f}">'
               f'<defs>{marcadores}</defs><rect x="{x0:.1f}" y="{y0:.1f}" width="{w:.1f}" height="{h:.1f}" fill="white"/>'
               + ''.join(self.el) + '</svg>')
        with open(ruta, 'w', encoding='utf8') as f:
            f.write(svg)


# ---------------------------------------------------------------------------
# Íconos de estereotipos (Jacobson / ICONIX)
# ---------------------------------------------------------------------------
def icono(c, tipo, x, y, r=15):
    """Dibuja el ícono centrado en (x, y). Devuelve la coordenada y inferior."""
    if tipo == 'actor':
        c.elipse(x, y - 14, 7, 7, fill='white')
        c.linea([(x, y - 7), (x, y + 10)])
        c.linea([(x - 12, y - 1), (x + 12, y - 1)])
        c.linea([(x - 10, y + 24), (x, y + 10), (x + 10, y + 24)])
        return y + 26
    if tipo == 'frm':
        c.linea([(x - r - 10, y - r), (x - r - 10, y + r)], sw=1.5)
        c.linea([(x - r - 10, y), (x - r, y)], sw=1.5)
        c.elipse(x, y, r, r, fill=RELLENO_2, sw=1.5)
        return y + r
    if tipo == 'ctrl':
        c.elipse(x, y, r, r, fill=RELLENO_2, sw=1.5)
        c.linea([(x + 2, y - r - 5), (x - 5, y - r), (x + 2, y - r + 5)], sw=1.5)
        return y + r
    if tipo == 'ent':
        c.elipse(x, y, r, r, fill=RELLENO_2, sw=1.5)
        c.linea([(x - r, y + r + 2), (x + r, y + r + 2)], sw=1.5)
        return y + r + 2
    if tipo == 'bd':
        c.elipse(x, y - r + 4, r, 5, fill=RELLENO_2)
        c.rect(x - r, y - r + 4, 2 * r, 2 * r - 6, fill=RELLENO_2)
        c.elipse(x, y - r + 4, r, 5, fill=RELLENO_2)
        return y + r
    raise ValueError(tipo)


ESTEREOTIPO = {'actor': '', 'frm': '«frm»', 'ctrl': '«ctrl»', 'ent': '«entidad»', 'bd': '«bd»'}


def _etiqueta(c, tipo, nombre, x, y):
    """Nombre bajo el ícono; separa el estereotipo en una línea propia."""
    est = ESTEREOTIPO[tipo]
    yy = y + 14
    if est:
        c.texto(x, yy, est, 10.5, 'middle', color=GRIS)
        yy += 14
    for parte in nombre.split('\n'):
        c.texto(x, yy, parte, 12, 'middle', negrita=(tipo != 'actor'))
        yy += 14
    return yy


def _pentagono(c, x, y, texto, tam=12):
    w = ancho(texto, tam, True) + 18
    c.poligono([(x, y), (x + w, y), (x + w, y + 12), (x + w - 8, y + 20), (x, y + 20)], fill='white')
    c.texto(x + 6, y + 15, texto, tam, negrita=True)
    return w


# ---------------------------------------------------------------------------
# Diagrama de secuencia
# ---------------------------------------------------------------------------
def secuencia(titulo, participantes, pasos, ruta):
    """
    participantes: [(clave, tipo, nombre)], tipo en actor|frm|ctrl|ent|bd
    pasos:
      ('m', de, a, texto)          mensaje síncrono
      ('r', de, a, texto)          respuesta (línea discontinua)
      ('s', de, texto)             mensaje a sí mismo
      ('alt', [(guarda, [pasos]), (guarda, [pasos])])
      ('opt', guarda, [pasos]) · ('loop', guarda, [pasos])
    """
    c = Lienzo()
    idx = {p[0]: i for i, p in enumerate(participantes)}
    n = len(participantes)
    contador = [0]

    # separaciones entre líneas de vida según el texto más largo que las cruza
    sep = [max(150, ancho(p[2].split('\n')[0], 12, True) / 2 + ancho(q[2].split('\n')[0], 12, True) / 2 + 40)
           for p, q in zip(participantes, participantes[1:])]

    def recorrer(lista, f):
        for p in lista:
            if p[0] in ('m', 'r'):
                f(p)
            elif p[0] == 's':
                f(p)
            elif p[0] == 'alt':
                for _, sub in p[1]:
                    recorrer(sub, f)
            else:
                recorrer(p[2], f)

    num = [0]

    def medir(p):
        if p[0] == 's':
            i = idx[p[1]]
            w = ancho(f'99: {p[2]}', 11.5) + 30
            if i < n - 1 and sep[i] < w:
                sep[i] = w
            return
        i, j = sorted((idx[p[1]], idx[p[2]]))
        w = ancho(f'99: {p[3]}', 11.5) + 36
        actual = sum(sep[i:j])
        if actual < w:
            sep[j - 1] += w - actual

    recorrer(pasos, medir)
    xs = [0]
    for s in sep:
        xs.append(xs[-1] + s)

    # cabeceras
    y_icono = 40
    fondo_cab = 0
    for (clave, tipo, nombre), x in zip(participantes, xs):
        icono(c, tipo, x, y_icono)
        fondo_cab = max(fondo_cab, _etiqueta(c, tipo, nombre, x, y_icono + (26 if tipo == 'actor' else 18)))
    y = fondo_cab + 16
    y_inicio = fondo_cab - 4
    fragmentos = []

    def rango(lista):
        ids = []

        def f(p):
            if p[0] == 's':
                ids.append(idx[p[1]])
            else:
                ids.extend((idx[p[1]], idx[p[2]]))
        recorrer(lista, f)
        return min(ids), max(ids)

    def dibujar(lista, nivel):
        nonlocal y
        for p in lista:
            if p[0] in ('m', 'r'):
                contador[0] += 1
                x1, x2 = xs[idx[p[1]]], xs[idx[p[2]]]
                d = 3 if x2 > x1 else -3
                c.linea([(x1 + d, y), (x2 - d, y)], dash='6,4' if p[0] == 'r' else None, fin='abierta' if p[0] == 'r' else 'llena')
                etiqueta = f'{contador[0]}: {p[3]}'
                c.texto((x1 + x2) / 2, y - 5, etiqueta, 11.5, 'middle', fondo='white')
                y += 30
            elif p[0] == 's':
                contador[0] += 1
                x = xs[idx[p[1]]]
                c.linea([(x + 3, y - 6), (x + 34, y - 6), (x + 34, y + 10), (x + 6, y + 10)], fin='llena')
                c.texto(x + 40, y + 2, f'{contador[0]}: {p[2]}', 11.5, fondo='white')
                y += 36
            else:
                cuerpos = p[1] if p[0] == 'alt' else [(p[1], p[2])]
                todos = [q for _, sub in cuerpos for q in sub]
                i, j = rango(todos)
                pad = 26 - nivel * 7
                xa, xb = xs[i] - pad - 14, xs[j] + pad + 14
                for q in todos:
                    if q[0] == 's':
                        xb = max(xb, xs[idx[q[1]]] + 52 + ancho(f'99: {q[2]}', 11.5))
                y0 = y - 8
                y += 26
                separadores = []
                for k, (guarda, sub) in enumerate(cuerpos):
                    if k:
                        separadores.append((y - 14, guarda))
                        y += 14
                    else:
                        primera = guarda
                    dibujar(sub, nivel + 1)
                y1 = y - 8
                fragmentos.append((p[0], xa, y0, xb, y1, primera, separadores))
                y += 12

    dibujar(pasos, 0)
    y_fin = y + 4
    # líneas de vida (debajo de todo)
    vidas = []
    for x in xs:
        vidas.append(f'<line x1="{x}" y1="{y_inicio}" x2="{x}" y2="{y_fin}" stroke="{GRIS}" stroke-width="1" stroke-dasharray="5,4"/>')
    c.el[0:0] = vidas
    # fragmentos combinados
    for tipo, xa, y0, xb, y1, guarda, separadores in fragmentos:
        marco = f'<rect x="{xa:.1f}" y="{y0:.1f}" width="{xb - xa:.1f}" height="{y1 - y0:.1f}" fill="none" stroke="{ACENTO}" stroke-width="1.3"/>'
        c.el.insert(len(vidas), marco)
        c._caja(xa, y0, xb, y1)
        w = _pentagono(c, xa, y0, tipo)
        c.texto(xa + w + 8, y0 + 15, f'[{guarda}]', 11.5, cursiva=True, color=ACENTO)
        for ys, g in separadores:
            c.linea([(xa, ys), (xb, ys)], dash='7,4', stroke=ACENTO)
            c.texto(xa + 8, ys + 14, f'[{g}]', 11.5, cursiva=True, color=ACENTO)
    # marco sd
    xa, ya = c.x0 - 14, -8
    xb, yb = c.x1 + 14, y_fin + 10
    c.rect(xa, ya, xb - xa, yb - ya, fill='none', stroke=TINTA, sw=1.3)
    c.el.insert(0, c.el.pop())
    _pentagono(c, xa, ya, f'sd {titulo}')
    c.guardar(ruta)
    return contador[0]


# ---------------------------------------------------------------------------
# Diagrama de comunicación (se deriva de la misma especificación de secuencia)
# ---------------------------------------------------------------------------
def comunicacion(titulo, participantes, pasos, ruta):
    c = Lienzo()
    tipos = {p[0]: p[1] for p in participantes}
    nombres = {p[0]: p[2] for p in participantes}
    # orden de numeración igual al de secuencia
    enlaces = {}
    cont = [0]
    orden = {p[0]: i for i, p in enumerate(participantes)}

    def recorrer(lista, guarda=None):
        for p in lista:
            if p[0] in ('m', 'r'):
                cont[0] += 1
                if p[0] == 'r':
                    continue
                a, b = p[1], p[2]
                texto = f'{cont[0]}: ' + (f'[{guarda}] ' if guarda else '') + p[3]
                enlaces.setdefault(tuple(sorted((a, b), key=orden.get)), []).append((a, b, texto))
            elif p[0] == 's':
                cont[0] += 1
                enlaces.setdefault((p[1], p[1]), []).append((p[1], p[1], f'{cont[0]}: {p[2]}'))
            elif p[0] == 'alt':
                for g, sub in p[1]:
                    recorrer(sub, g)
            else:
                recorrer(p[2], ('*' if p[0] == 'loop' else '') + p[1])

    recorrer(pasos)

    # posiciones: actor, interfaz y controlador en fila; entidades en columna a la derecha
    izq = [p for p in participantes if p[1] in ('actor', 'frm')]
    ctrls = [p for p in participantes if p[1] == 'ctrl']
    ents = [p for p in participantes if p[1] in ('ent', 'bd')]
    pos = {}
    alto = max(1, len(ents)) * 120
    ymed = alto / 2
    x = 0
    for p in izq:
        pos[p[0]] = (x, ymed)
        # el espacio depende de los mensajes entre este y el siguiente
        x += 260
    for k, p in enumerate(ctrls):
        pos[p[0]] = (x, ymed + (k - (len(ctrls) - 1) / 2) * 150)
    xe = x + 330
    for k, p in enumerate(ents):
        pos[p[0]] = (xe + (40 if len(ents) > 2 and k in (0, len(ents) - 1) else 0) * 0, (k + 0.5) * 120)
    # ajustar distancias horizontales a los textos más largos
    for (a, b), msgs in enlaces.items():
        if a == b:
            continue
        need = max(ancho(t, 11.5) for _, _, t in msgs) + 70
        (xa, ya), (xb, yb) = pos[a], pos[b]
        if abs(ya - yb) < 1 and abs(xb - xa) < need:
            delta = need - abs(xb - xa)
            corte = max(xa, xb)
            for k2, (px, py) in pos.items():
                if px >= corte - 0.5:
                    pos[k2] = (px + delta, py)
    # entidades: separar lo necesario del controlador (un solo desplazamiento)
    delta_e = 0
    for (a, b), msgs in enlaces.items():
        if a == b or not (tipos[a] in ('ent', 'bd') or tipos[b] in ('ent', 'bd')):
            continue
        need = max(ancho(t, 11.5) for _, _, t in msgs) * 0.9 + 120
        e = a if tipos[a] in ('ent', 'bd') else b
        o = b if e == a else a
        if tipos[o] in ('ent', 'bd'):
            continue
        delta_e = max(delta_e, need - (pos[e][0] - pos[o][0]))
    for k2 in pos:
        if tipos[k2] in ('ent', 'bd'):
            pos[k2] = (pos[k2][0] + delta_e, pos[k2][1])

    # enlaces
    for (a, b), msgs in enlaces.items():
        if a == b:
            x0, y0 = pos[a]
            c.linea([(x0 + 10, y0 - 18), (x0 + 30, y0 - 50), (x0 - 30, y0 - 50), (x0 - 10, y0 - 18)])
            for k, (_, _, t) in enumerate(msgs):
                c.texto(x0, y0 - 58 - 14 * k, t, 11.5, 'middle', fondo='white')
            continue
        (xa, ya), (xb, yb) = pos[a], pos[b]
        c.linea([(xa, ya), (xb, yb)])
    for (a, b), msgs in enlaces.items():
        if a == b:
            continue
        (xa, ya), (xb, yb) = pos[a], pos[b]
        L = math.hypot(xb - xa, yb - ya)
        ux, uy = (xb - xa) / L, (yb - ya) / L
        nx, ny = -uy, ux
        if ny > 1e-9 or (abs(ny) < 1e-9 and nx < 0):
            nx, ny = -nx, -ny   # normal hacia arriba
        t_pos = 0.5 if tipos[a] not in ('ent', 'bd') and tipos[b] not in ('ent', 'bd') else 0.55
        mx, my = xa + (xb - xa) * t_pos, ya + (yb - ya) * t_pos
        ida = [m for m in msgs if m[0] == a]
        vuelta = [m for m in msgs if m[0] == b]
        for grupo, signo in ((ida, 1), (vuelta, -1)):
            if not grupo:
                continue
            o, d = grupo[0][0], grupo[0][1]
            (ox, oy), (dx, dy) = pos[o], pos[d]
            L2 = math.hypot(dx - ox, dy - oy)
            vx, vy = (dx - ox) / L2, (dy - oy) / L2
            px, py = mx + nx * 10 * signo, my + ny * 10 * signo
            c.linea([(px - vx * 14, py - vy * 14), (px + vx * 14, py + vy * 14)], fin='llena', sw=1.4)
            alto_bloque = 14 * len(grupo)
            for k, (_, _, t) in enumerate(grupo):
                if signo == 1:
                    ty = py + ny * 12 - (alto_bloque - 14 * (k + 1)) + (0 if ny < -0.3 else -4)
                else:
                    ty = py - ny * 12 + 14 * (k + 1) - 2
                anchor = 'middle' if abs(ny) > 0.6 else ('end' if nx * signo < 0 else 'start')
                c.texto(px + (nx * 8 * signo if abs(ny) <= 0.6 else 0), ty, t, 11.5, anchor, fondo='white')
    # nodos
    for clave, (x, y) in pos.items():
        tipo = tipos[clave]
        fondo = icono(c, tipo, x, y - (6 if tipo == 'actor' else 0))
        _etiqueta(c, tipo, nombres[clave], x, fondo + (0 if tipo == 'actor' else 3))
    # marco
    xa, ya = c.x0 - 16, c.y0 - 34
    xb, yb = c.x1 + 16, c.y1 + 12
    c.rect(xa, ya, xb - xa, yb - ya, fill='none', stroke=TINTA, sw=1.3)
    c.el.insert(0, c.el.pop())
    _pentagono(c, xa, ya, f'comunicación {titulo}')
    c.guardar(ruta)


# ---------------------------------------------------------------------------
# Diagrama de casos de uso
# ---------------------------------------------------------------------------
def casos_de_uso(titulo, actores, casos, relaciones, ruta, extras=None, ancho_caso=230, columnas=1):
    """
    actores: [(clave, nombre, 'izq'|'der')]
    casos:   [(clave, texto)]
    relaciones: [(actor, caso)]
    extras: [(desde_caso, hacia_caso, '«include»'|'«extend»')]
    """
    c = Lienzo()
    fila = 64
    filas = math.ceil(len(casos) / columnas)
    posc = {}
    x_sis = 0
    for k, (clave, _) in enumerate(casos):
        col, f = divmod(k, filas)
        posc[clave] = (x_sis + 40 + ancho_caso / 2 + col * (ancho_caso + 60), 70 + f * fila)
    ancho_sis = 80 + columnas * ancho_caso + (columnas - 1) * 60 + (90 if extras else 0)
    alto_sis = 70 + filas * fila
    c.rect(x_sis, 0, ancho_sis, alto_sis, fill='white', stroke=TINTA, sw=1.3)
    c.texto(x_sis + ancho_sis / 2, 24, titulo, 14, 'middle', negrita=True)
    # actores ordenados por baricentro
    pa = {}
    for lado in ('izq', 'der'):
        grupo = [a for a in actores if a[2] == lado]
        bary = []
        for a in grupo:
            ys = [posc[cs][1] for (ac, cs) in relaciones if ac == a[0]]
            bary.append((sum(ys) / len(ys) if ys else alto_sis / 2, a))
        bary.sort(key=lambda t: t[0])
        n = len(bary)
        for k, (_, a) in enumerate(bary):
            y = (alto_sis * (k + 1) / (n + 1)) if n else 0
            x = x_sis - 170 if lado == 'izq' else x_sis + ancho_sis + 170
            pa[a[0]] = (x, y)
    for ac, cs in relaciones:
        (xa, ya), (xc, yc) = pa[ac], posc[cs]
        borde = xc - ancho_caso / 2 if xa < xc else xc + ancho_caso / 2
        c.linea([(xa + (16 if xa < xc else -16), ya), (borde, yc)])
    for clave, texto in casos:
        x, y = posc[clave]
        c.elipse(x, y, ancho_caso / 2, 23)
        partes = _partir(texto, ancho_caso - 40, 11.5)
        for k, p in enumerate(partes):
            c.texto(x, y + 4 - 7 * (len(partes) - 1) + 14 * k, p, 11.5, 'middle')
    for k, (a, b, et) in enumerate(extras or []):
        (x1, y1), (x2, y2) = posc[a], posc[b]
        if abs(x1 - x2) < 1:
            xr = x1 + ancho_caso / 2 + 22 + 16 * (k % 3)
            c.linea([(x1 + ancho_caso / 2 - 12, y1 + 8), (xr, y1 + 8), (xr, y2 + 8), (x2 + ancho_caso / 2 - 10, y2 + 8)], dash='6,4', fin='abierta')
            c.texto(xr + 4, (y1 + y2) / 2 + 12, et, 10.5, color=GRIS, fondo='white')
        else:
            c.linea([(x1, y1), (x2, y2)], dash='6,4', fin='abierta')
            c.texto((x1 + x2) / 2, (y1 + y2) / 2 - 4, et, 10.5, 'middle', color=GRIS, fondo='white')
    for clave, nombre, _ in actores:
        x, y = pa[clave]
        fondo = icono(c, 'actor', x, y - 10)
        for k, p in enumerate(nombre.split('\n')):
            c.texto(x, fondo + 14 + 14 * k, p, 12, 'middle', negrita=True)
    c.guardar(ruta)


def _partir(texto, maximo, tam=12, negrita=False):
    palabras, lineas, actual = texto.split(), [], ''
    for p in palabras:
        prueba = (actual + ' ' + p).strip()
        if ancho(prueba, tam, negrita) <= maximo or not actual:
            actual = prueba
        else:
            lineas.append(actual)
            actual = p
    lineas.append(actual)
    return lineas


# ---------------------------------------------------------------------------
# Clases, paquetes, nodos y componentes (posiciones dadas a mano)
# ---------------------------------------------------------------------------
def _recortar(caja, hacia):
    """Punto donde la recta del centro de la caja hacia 'hacia' corta su borde."""
    x, y, w, h = caja
    cx, cy = x + w / 2, y + h / 2
    dx, dy = hacia[0] - cx, hacia[1] - cy
    if dx == 0 and dy == 0:
        return cx, cy
    t = min((w / 2) / abs(dx) if dx else math.inf, (h / 2) / abs(dy) if dy else math.inf)
    return cx + dx * t, cy + dy * t


def clase(c, x, y, nombre, atributos=(), operaciones=(), estereotipo=None, minimo=150, tam=11.5, fill=RELLENO):
    w = max([minimo, ancho(nombre, 12.5, True) + 24] + [ancho(a, tam) + 18 for a in list(atributos) + list(operaciones)])
    hcab = 26 + (14 if estereotipo else 0)
    h = hcab + max(1, len(atributos)) * 15 + 8 + (len(operaciones) * 15 + 8 if operaciones else 0)
    c.rect(x, y, w, h, fill=fill)
    c.rect(x, y, w, hcab, fill=RELLENO_2)
    yy = y + 18
    if estereotipo:
        c.texto(x + w / 2, yy, estereotipo, 10.5, 'middle', color=GRIS)
        yy += 14
    c.texto(x + w / 2, yy, nombre, 12.5, 'middle', negrita=True)
    yy = y + hcab + 15
    for a in atributos:
        c.texto(x + 8, yy, a, tam)
        yy += 15
    if operaciones:
        yy = y + hcab + max(1, len(atributos)) * 15 + 8
        c.linea([(x, yy), (x + w, yy)])
        yy += 15
        for o in operaciones:
            c.texto(x + 8, yy, o, tam)
            yy += 15
    return (x, y, w, h)


def asociacion(c, a, b, m1='', m2='', etiqueta='', tipo='asoc', puntos=None):
    """tipo: asoc | comp (rombo lleno en a) | agreg | gen (triángulo en b) | dep (discontinua a→b)."""
    ca = (a[0] + a[2] / 2, a[1] + a[3] / 2)
    cb = (b[0] + b[2] / 2, b[1] + b[3] / 2)
    medio = puntos or []
    p1 = _recortar(a, medio[0] if medio else cb)
    p2 = _recortar(b, medio[-1] if medio else ca)
    pts = [p1] + medio + [p2]
    if tipo == 'gen':
        c.linea(pts, fin='triangulo')
    elif tipo == 'dep':
        c.linea(pts, dash='6,4', fin='abierta')
    elif tipo == 'comp':
        c.linea(pts, ini='rombo_lleno')
    elif tipo == 'agreg':
        c.linea(pts, ini='rombo')
    else:
        c.linea(pts)

    def cerca(p, q, d=22):
        L = math.hypot(q[0] - p[0], q[1] - p[1]) or 1
        ux, uy = (q[0] - p[0]) / L, (q[1] - p[1]) / L
        return p[0] + ux * d - uy * 9, p[1] + uy * d + ux * 9 + 4
    if m1:
        x, y = cerca(p1, pts[1])
        c.texto(x, y, m1, 11, 'middle', fondo='white')
    if m2:
        x, y = cerca(p2, pts[-2])
        c.texto(x, y, m2, 11, 'middle', fondo='white')
    if etiqueta:
        k = len(pts) // 2
        xm, ym = ((pts[k - 1][0] + pts[k][0]) / 2, (pts[k - 1][1] + pts[k][1]) / 2)
        c.texto(xm, ym - 5, etiqueta, 11, 'middle', cursiva=True, color=GRIS, fondo='white')


def paquete(c, x, y, w, h, nombre, fill=RELLENO):
    tw = ancho(nombre, 12, True) + 20
    c.rect(x, y, min(tw, w * 0.8), 20, fill=RELLENO_2)
    c.texto(x + 8, y + 15, nombre, 12, negrita=True)
    c.rect(x, y + 20, w, h - 20, fill=fill)
    return (x, y + 20, w, h - 20)


def nodo(c, x, y, w, h, nombre, estereotipo='«dispositivo»'):
    d = 14
    c.poligono([(x, y), (x + d, y - d), (x + w + d, y - d), (x + w, y)], fill=RELLENO_2)
    c.poligono([(x + w, y), (x + w + d, y - d), (x + w + d, y + h - d), (x + w, y + h)], fill='#e6d3bd')
    c.rect(x, y, w, h, fill=RELLENO)
    c.texto(x + w / 2, y + 18, estereotipo, 10.5, 'middle', color=GRIS)
    c.texto(x + w / 2, y + 34, nombre, 12.5, 'middle', negrita=True)
    return (x, y, w, h)


def componente(c, x, y, nombre, detalle='', w=None, estereotipo='«componente»'):
    w = w or max(170, ancho(nombre, 12, True) + 50, ancho(detalle, 10.5) + 30)
    h = 54 if detalle else 44
    c.rect(x, y, w, h, fill=RELLENO)
    # ícono de componente
    ix, iy = x + w - 24, y + 8
    c.rect(ix, iy, 14, 16, fill=RELLENO_2, sw=1)
    c.rect(ix - 4, iy + 3, 8, 4, fill='white', sw=1)
    c.rect(ix - 4, iy + 9, 8, 4, fill='white', sw=1)
    c.texto(x + (w - 18) / 2, y + 17, estereotipo, 10, 'middle', color=GRIS)
    c.texto(x + (w - 18) / 2, y + 33, nombre, 12, 'middle', negrita=True)
    if detalle:
        c.texto(x + (w - 18) / 2, y + 47, detalle, 10.5, 'middle', color=GRIS)
    return (x, y, w, h)
