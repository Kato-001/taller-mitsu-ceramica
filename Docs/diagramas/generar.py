"""
Genera todos los diagramas del informe en Docs/diagramas/svg y la lista de casos
de uso en Docs/informe/datos/casos.json.

    python Docs/diagramas/generar.py
    node Docs/diagramas/render.js        (convierte a PNG)
"""

import json
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
import uml
import re
from uml import Lienzo, clase, asociacion, paquete, nodo, componente, ancho, RELLENO, RELLENO_2, GRIS, TINTA, ACENTO, CU, ROB_LINEA
from casos import CASOS, ACTORES, PAQUETES

AQUI = os.path.dirname(os.path.abspath(__file__))
SVG = os.path.join(AQUI, 'svg')
DATOS = os.path.join(AQUI, '..', 'informe', 'datos')
os.makedirs(SVG, exist_ok=True)
ESQUEMA = json.load(open(os.path.join(DATOS, 'esquema.json'), encoding='utf8'))['tablas']


def ruta(nombre):
    return os.path.join(SVG, nombre + '.svg')


CICLOS = {'I': 'Ciclo I · Seguridad y configuración', 'II': 'Ciclo II · Clases y reservas', 'III': 'Ciclo III · Inventario, ventas y tienda en línea'}

# ---------------------------------------------------------------------------
# 1. Secuencia y comunicación por caso de uso
# ---------------------------------------------------------------------------
for cu in CASOS:
    titulo = f"{cu['id']} {cu['nombre']}"
    cu['mensajes'] = uml.secuencia(titulo, cu['part'], cu['pasos'], ruta(f"sec_{cu['id']}"))
    uml.comunicacion(titulo, cu['part'], cu['pasos'], ruta(f"com_{cu['id']}"))

# ---------------------------------------------------------------------------
# 2. Casos de uso
# ---------------------------------------------------------------------------
LADO = {'adm': 'izq', 'admv': 'izq', 'mae': 'der', 'alu': 'der', 'cli': 'der', 'vis': 'der', 'usr': 'izq'}
EXTRAS = [('CU10', 'CU09', '«include»'), ('CU20', 'CU21', '«include»'), ('CU18', 'CU19', '«include»'), ('CU16', 'CU13', '«extend»')]


def actores_de(casos):
    claves = []
    for cu in casos:
        for a in cu['actores']:
            if a not in claves:
                claves.append(a)
    return claves


for ciclo, titulo in CICLOS.items():
    lista = [cu for cu in CASOS if cu['ciclo'] == ciclo]
    acts = actores_de(lista)
    rel = [(a, cu['id']) for cu in lista for a in cu['actores']]
    ids = {cu['id'] for cu in lista}
    extras = [e for e in EXTRAS if e[0] in ids and e[1] in ids]
    uml.casos_de_uso(titulo, [(a, ACTORES[a], LADO[a]) for a in acts],
                     [(cu['id'], f"{cu['id']} {cu['nombre']}") for cu in lista], rel, ruta(f'cu_ciclo_{ciclo}'), extras=extras, ancho_caso=270)

# diagrama general: actores con los paquetes
c = Lienzo()
paq_pos = {}
for k, (p, nombre) in enumerate(PAQUETES.items()):
    x, y = 0, k * 130
    paq_pos[p] = paquete(c, x, y, 360, 100, nombre)
    casos_p = [cu['id'] for cu in CASOS if cu['paquete'] == p]
    c.texto(x + 180, y + 66, ' · '.join(casos_p[:5]), 11, 'middle', color=GRIS)
    if len(casos_p) > 5:
        c.texto(x + 180, y + 82, ' · '.join(casos_p[5:]), 11, 'middle', color=GRIS)
act_pos = {'adm': (-230, 90), 'admv': (-230, 330), 'mae': (590, 150), 'alu': (590, 290), 'cli': (590, 430), 'vis': (590, 20)}
usos = {'adm': ['P1', 'P2', 'P3', 'P4'], 'admv': ['P2', 'P3', 'P4'], 'mae': ['P2'], 'alu': ['P2', 'P4'], 'cli': ['P4'], 'vis': ['P4']}
for a, ps in usos.items():
    for p in ps:
        x, y, w, h = paq_pos[p]
        ax, ay = act_pos[a]
        c.linea([(ax + (16 if ax < 0 else -16), ay), (x if ax < 0 else x + w, y + h / 2)])
for a, (x, y) in act_pos.items():
    fondo = uml.icono(c, 'actor', x, y - 10)
    c.texto(x, fondo + 14, ACTORES[a], 12, 'middle', negrita=True)
c.guardar(ruta('cu_general'))

# generalización de actores
c = Lienzo()
cajas = {}
pos = {'usr': (300, 0), 'adm': (0, 170), 'admv': (150, 170), 'mae': (300, 170), 'alu': (450, 170), 'cli': (600, 170), 'vis': (600, 0)}
for a, (x, y) in pos.items():
    fondo = uml.icono(c, 'actor', x, y)
    c.texto(x, fondo + 14, ACTORES[a], 12, 'middle', negrita=True)
    cajas[a] = (x - 30, y - 22, 60, 66)
for a in ('adm', 'admv', 'mae', 'alu', 'cli'):
    x = pos[a][0]
    c.linea([(x, 140), (x, 110), (300, 110), (300, 72)], fin='triangulo' if a == 'mae' else None)
c.linea([(600, 140), (600, 70)], fin='triangulo')
c.texto(620, 108, 'el cliente es un visitante que ingresó', 10.5, color=GRIS)
c.texto(300 + 10, 96, 'todos los usuarios inician sesión (CU01, CU02)', 10.5, color=GRIS)
c.guardar(ruta('actores'))

# ---------------------------------------------------------------------------
# 3. Paquetes
# ---------------------------------------------------------------------------
c = Lienzo()
P = {}
P['P1'] = paquete(c, 260, 0, 260, 90, PAQUETES['P1'])
P['P2'] = paquete(c, 0, 190, 260, 90, PAQUETES['P2'])
P['P3'] = paquete(c, 330, 190, 260, 90, PAQUETES['P3'])
P['P4'] = paquete(c, 660, 190, 280, 90, PAQUETES['P4'])
for p in ('P2', 'P3', 'P4'):
    asociacion(c, P[p], P['P1'], tipo='dep', etiqueta='«usa»')
asociacion(c, P['P4'], P['P3'], tipo='dep', etiqueta='«usa»')
for p, t in (('P1', 'Ciclo I'), ('P2', 'Ciclo II'), ('P3', 'Ciclo III'), ('P4', 'Ciclo III')):
    x, y, w, h = P[p]
    c.texto(x + w / 2, y + h / 2 + 5, t, 12, 'middle', color=GRIS)
c.guardar(ruta('paquetes'))



def caso_elipse(c, x, y, texto, rx=110, ry=30, tam=11):
    c.elipse(x, y, rx, ry, fill=CU)
    partes = uml._partir(texto, rx * 2 - 40, tam)
    for j, t in enumerate(partes):
        c.texto(x, y + 4 - 6.5 * (len(partes) - 1) + 13 * j, t, tam, 'middle')


def nombre_paquete(p):
    return PAQUETES[p].split(' ', 1)[1].upper()


for p in PAQUETES:
    lista = [cu for cu in CASOS if cu['paquete'] == p]
    # 3.1.1 el paquete solo
    c = Lienzo()
    paquete(c, 0, 0, 420, 170, nombre_paquete(p))
    c.guardar(ruta(f'paq_{p}'))

    # 3.1.2 paquete con sus casos de uso
    c = Lienzo()
    paso = 76
    alto = max(220, len(lista) * paso)
    py = alto / 2
    xp, yp, wp, hp = 0, py - 90, 300, 180
    cx = 640
    for k, cu in enumerate(lista):
        cy = paso / 2 + k * paso + (alto - len(lista) * paso) / 2
        c.linea([(xp + wp, py), (cx - 110, cy)], dash='5,4')
        c.texto(xp + wp + (cx - 110 - xp - wp) * 0.58, py + (cy - py) * 0.58 + 4, '«include»', 10.5, 'middle', fondo='white')
    paquete(c, xp, yp, wp, hp, nombre_paquete(p))
    for k, cu in enumerate(lista):
        cy = paso / 2 + k * paso + (alto - len(lista) * paso) / 2
        caso_elipse(c, cx, cy, f"{cu['id']} {cu['nombre'].upper()}")
    c.guardar(ruta(f'relpaq_{p}'))

    # 3.1.3 vista del paquete: actores y casos de uso dentro del paquete
    c = Lienzo()
    acts = actores_de(lista)
    paso = 74
    alto = 60 + len(lista) * paso
    posc = {cu['id']: (560, 70 + k * paso) for k, cu in enumerate(lista)}
    bary = sorted(acts, key=lambda a: sum(posc[cu['id']][1] for cu in lista if a in cu['actores']) / max(1, sum(a in cu['actores'] for cu in lista)))
    posa = {a: (110, 40 + alto * (k + 1) / (len(bary) + 1)) for k, a in enumerate(bary)}
    paquete(c, 0, 0, 820, alto + 40, nombre_paquete(p))
    for cu in lista:
        for a in cu['actores']:
            (xa, ya), (xc, yc) = posa[a], posc[cu['id']]
            c.linea([(xa + 18, ya), (xc - 130, yc)], fin='llena')
    ids = set(posc)
    for k, (a, b, et) in enumerate(e for e in EXTRAS if e[0] in ids and e[1] in ids):
        (x1, y1), (x2, y2) = posc[a], posc[b]
        xr = x1 + 130 + 24 + 14 * k
        c.linea([(x1 + 118, y1 + 10), (xr, y1 + 10), (xr, y2 + 10), (x2 + 120, y2 + 10)], dash='6,4', fin='abierta')
        c.texto(xr + 4, (y1 + y2) / 2 + 14, et, 10.5, color=GRIS, fondo=uml.PAQ)
    for cu in lista:
        caso_elipse(c, *posc[cu['id']], f"{cu['id']} {cu['nombre'].upper()}", rx=130, ry=28)
    for a, (x, y) in posa.items():
        fondo = uml.icono(c, 'actor', x, y - 10)
        c.texto(x, fondo + 14, ACTORES[a], 12, 'middle', negrita=True)
    c.guardar(ruta(f'vista_{p}'))

# diagrama de cada caso de uso para su ficha (como en Enterprise Architect)
for cu in CASOS:
    socios = [e for e in EXTRAS if cu['id'] in e[:2]]
    otros = [x for e in socios for x in e[:2] if x != cu['id']]
    nombres = {x['id']: x['nombre'] for x in CASOS}
    acts = [(a, ACTORES[a], 'izq' if k % 2 == 0 else 'der') for k, a in enumerate(cu['actores'])]
    uml.casos_de_uso(f"{cu['id']} {cu['nombre']}", acts,
                     [(cu['id'], f"{cu['id']} {cu['nombre']}")] + [(o, f"{o} {nombres[o]}") for o in otros],
                     [(a, cu['id']) for a in cu['actores']], ruta(f"cu_{cu['id']}"), extras=socios, ancho_caso=250,
                     hijos={'usr': [ACTORES[k] for k in ('adm', 'admv', 'mae', 'alu', 'cli')]} if 'usr' in cu['actores'] else None)

# ---------------------------------------------------------------------------
# 3b. Clases de análisis por caso de uso: «form» → «control» → «entidad»
# ---------------------------------------------------------------------------
TABLA_DE = {'Sesion': 'sesion_clase'}


def tabla_de(nombre):
    t = TABLA_DE.get(nombre) or re.sub(r'(?<!^)(?=[A-Z])', '_', nombre).lower()
    return t if t in ESQUEMA else None


def corto(texto, n=44):
    return texto if len(texto) <= n else texto[:n - 1] + '…'


for cu in CASOS:
    tipos = {k: t for k, t, _ in cu['part']}
    nombres = {k: n for k, _, n in cu['part']}
    ops = {k: [] for k in tipos}
    campos = []

    def recorrer(lista):
        for p in lista:
            if p[0] == 'm':
                destino, texto = p[2], p[3]
                if p[1] == 'a':
                    for arg in re.findall(r'\(([^)]*)\)', texto)[:1]:
                        campos.extend(a.strip() for a in arg.split(',') if a.strip() and a.strip() not in campos)
                if destino != 'a' and texto not in ops[destino]:
                    ops[destino].append(texto)
            elif p[0] == 's':
                if p[2] not in ops[p[1]]:
                    ops[p[1]].append(p[2])
            elif p[0] == 'alt':
                for _, sub in p[1]:
                    recorrer(sub)
            elif p[0] in ('opt', 'loop'):
                recorrer(p[2])
    recorrer(cu['pasos'])

    def crear(cv, x, y, k):
        t, n = tipos[k], nombres[k]
        metodos = ['+ ' + corto(m) for m in ops[k][:6]]
        if t == 'frm':
            return clase(cv, x, y, n, ['+ ' + a for a in campos[:6]] or ['+ mensaje'], ['+ mostrarInterfaz()'] + metodos,
                         estereotipo='«form»', minimo=190, tam=10.5, fill='white', cab='#EAF1FB')
        if t == 'ctrl':
            return clase(cv, x, y, n, ['- modelos', '- sesion'], metodos, estereotipo='«control»', minimo=200, tam=10.5, fill='white', cab='#EAF1FB')
        if t == 'bd':
            return clase(cv, x, y, n, ['(transacción en PostgreSQL)'], metodos, estereotipo='«procedimiento»', minimo=190, tam=10.5, fill='white', cab='#EAF1FB')
        tb = tabla_de(n)
        attrs = []
        if tb:
            cols = [col for col in ESQUEMA[tb]['columnas']]
            attrs = [f"+ {col['nombre']}: {tipo_corto_simple(col['tipo'])}" for col in cols[:6]] + (['…'] if len(cols) > 6 else [])
        else:
            attrs = ['(servicio externo de pago)']
        return clase(cv, x, y, n, attrs, metodos, estereotipo='«servicio»' if not tb else '«entidad»', minimo=180, tam=10.5, fill='white', cab='#EAF1FB')

    def tipo_corto_simple(t):
        return re.sub(r'\(.*\)', '', t.replace('character varying', 'varchar').replace('timestamp with time zone', 'timestamp')
                      .replace('timestamp without time zone', 'timestamp')).strip()

    tmp = Lienzo()
    claves_ent = [k for k, t in tipos.items() if t in ('ent', 'bd')]
    med = {k: crear(tmp, 0, 0, k) for k in tipos if k != 'a'}
    gap = 26
    alto_e = sum(med[k][3] for k in claves_ent) + gap * (len(claves_ent) - 1)
    alto = max(alto_e, med['f'][3], med['c'][3])
    c = Lienzo()
    xf = 110
    bf = crear(c, xf, (alto - med['f'][3]) / 2, 'f')
    xc = bf[0] + bf[2] + 90
    bc = crear(c, xc, (alto - med['c'][3]) / 2, 'c')
    xe = bc[0] + bc[2] + 110
    y = (alto - alto_e) / 2
    bes = []
    for k in claves_ent:
        bes.append(crear(c, xe, y, k))
        y += med[k][3] + gap
    ya = alto / 2
    fondo = uml.icono(c, 'actor', 20, ya - 10)
    c.texto(20, fondo + 14, nombres['a'], 11.5, 'middle', negrita=True)
    c.linea([(40, ya), (bf[0], ya)], fin='llena')
    c.linea([(bf[0] + bf[2], ya), (bc[0], ya)], fin='llena')
    c.texto(bf[0] + bf[2] + 10, ya - 6, '1', 10.5, 'middle')
    c.texto(bc[0] - 12, ya - 6, '1', 10.5, 'middle')
    xm = bc[0] + bc[2] + 50
    for be in bes:
        ye = be[1] + be[3] / 2
        c.linea([(bc[0] + bc[2], ya), (xm, ya), (xm, ye), (be[0], ye)], fin='llena')
    xa, ya0 = c.x0 - 16, c.y0 - 36
    c.rect(xa, ya0, c.x1 + 16 - xa, c.y1 + 14 - ya0, fill='none', stroke=TINTA, sw=1.3)
    c.el.insert(0, c.el.pop())
    uml._pentagono(c, xa, ya0, f"{cu['id']} {cu['nombre']}")
    c.guardar(ruta(f"clases_{cu['id']}"))


# ---------------------------------------------------------------------------
# 5. Modelo de dominio (conceptual)
# ---------------------------------------------------------------------------
def rejilla(c, filas, crear, gapx=50, gapy=60):
    cajas, y = {}, 0
    anchos = []
    tmp = Lienzo()
    medidas = {}
    for fila in filas:
        for n in fila:
            medidas[n] = crear(tmp, 0, 0, n)
    total = max(sum(medidas[n][2] for n in fila) + gapx * (len(fila) - 1) for fila in filas)
    for fila in filas:
        w = sum(medidas[n][2] for n in fila) + gapx * (len(fila) - 1)
        x = (total - w) / 2
        h = max(medidas[n][3] for n in fila)
        for n in fila:
            cajas[n] = crear(c, x, y, n)
            x += medidas[n][2] + gapx
        y += h + gapy
    return cajas


DOMINIO = {
    'Usuario': ['nombre', 'correo', 'estado'], 'Rol': ['nombre'], 'Permiso': ['código'],
    'Maestra': ['especialidad'], 'Alumno': ['ci', 'fecha de nacimiento', 'tutor'], 'Taller': ['nombre', 'nivel', 'precio'],
    'Horario': ['día', 'hora', 'cupo'], 'Clase': ['fecha', 'estado'], 'Reserva': ['estado (asistió/faltó)'],
    'Proveedor': ['nombre', 'NIT'], 'Compra': ['fecha', 'total'], 'Material': ['stock', 'stock mínimo', 'costo'],
    'Pieza': ['código', 'precio', 'stock', 'foto'], 'Venta': ['número', 'total', 'estado'], 'Caja': ['monto inicial', 'diferencia'],
    'Cliente': ['nombre', 'CI/NIT'], 'Pedido': ['número', 'entrega', 'estado'], 'Pago': ['método', 'estado'],
}
c = Lienzo()
B = rejilla(c, [['Permiso', 'Rol', 'Usuario', 'Caja', 'Cliente'],
                ['Taller', 'Maestra', 'Alumno', 'Venta', 'Pedido', 'Pago'],
                ['Horario', 'Clase', 'Reserva', 'Pieza'],
                ['Proveedor', 'Compra', 'Material']],
            lambda cv, x, y, n: clase(cv, x, y, n, DOMINIO[n], minimo=120), gapx=95, gapy=90)
for a, b, m1, m2, et in [('Rol', 'Permiso', '*', '*', 'tiene'), ('Usuario', 'Rol', '*', '1', 'pertenece'),
                         ('Maestra', 'Usuario', '0..1', '1', 'es'), ('Alumno', 'Usuario', '0..1', '1', 'es'),
                         ('Horario', 'Taller', '*', '1', ''), ('Horario', 'Maestra', '*', '1', 'dicta'), ('Clase', 'Horario', '*', '1', 'genera'),
                         ('Reserva', 'Clase', '*', '1', ''), ('Reserva', 'Alumno', '*', '1', 'reserva'),
                         ('Compra', 'Proveedor', '*', '1', ''), ('Compra', 'Material', '*', '1..*', 'incluye'),
                         ('Venta', 'Pieza', '*', '1..*', 'incluye'), ('Venta', 'Caja', '*', '1', ''), ('Venta', 'Cliente', '*', '0..1', ''),
                         ('Pedido', 'Cliente', '*', '1', 'hace'), ('Pago', 'Pedido', '*', '1', ''), ('Pedido', 'Pieza', '*', '1..*', 'incluye'),
                         ('Caja', 'Usuario', '*', '1', 'abre')]:
    asociacion(c, B[a], B[b], m1, m2, et)
c.guardar(ruta('dominio'))

# ---------------------------------------------------------------------------
# 6. Clases persistentes y diagrama relacional (desde el esquema real)
# ---------------------------------------------------------------------------
def nombre_clase(t):
    return ''.join(p.capitalize() for p in t.split('_'))


def fks(t):
    return [(k['columnas'], k['referencia']) for k in ESQUEMA[t].get('restricciones', []) if k['tipo'] == 'f']


def pk(t):
    for k in ESQUEMA[t].get('restricciones', []):
        if k['tipo'] == 'p':
            return k['columnas'].split(',')
    return []


def tipo_corto(t):
    return (t.replace('character varying', 'varchar').replace('timestamp with time zone', 'timestamptz')
             .replace('timestamp without time zone', 'timestamp').replace('time without time zone', 'time'))


def caja_persistente(cv, x, y, t):
    fk_cols = {c for cols, _ in fks(t) for c in cols.split(',')}
    attrs = [f"{col['nombre']}: {tipo_corto(col['tipo'])}" for col in ESQUEMA[t]['columnas']
             if col['nombre'] not in fk_cols and col['nombre'] != 'id']
    return clase(cv, x, y, nombre_clase(t), attrs[:9] + (['…'] if len(attrs) > 9 else []), minimo=150, tam=10.5)


def caja_relacional(cv, x, y, t):
    p = pk(t)
    fk_cols = {c for cols, _ in fks(t) for c in cols.split(',')}
    attrs = []
    for col in ESQUEMA[t]['columnas']:
        marca = 'PK ' if col['nombre'] in p else 'FK ' if col['nombre'] in fk_cols else '     '
        attrs.append(f"{marca}{col['nombre']}: {tipo_corto(col['tipo'])}")
    return clase(cv, x, y, t, attrs, minimo=170, tam=10.5, fill='white')


GRUPOS = {
    'P1': [['rol', 'rolpermiso', 'permiso'], ['bitacora', 'usuario', 'recuperacion'], ['sesion', 'configuracion', 'categoria', 'unidad', 'metodo_pago']],
    'P2': [['taller', 'horario', 'maestra'], ['sesion_clase', 'usuario'], ['reserva', 'alumno']],
    'P3': [['proveedor', 'compra', 'usuario'], ['detalle_compra', 'movimiento_material'], ['categoria', 'material', 'unidad']],
    'P4': [['caja', 'venta', 'metodo_pago', 'cliente'], ['detalle_venta', 'movimiento_pieza', 'pedido', 'pago'],
           ['historial_precio', 'pieza', 'detalle_pedido'], ['pieza_imagen']],
}
for p, filas in GRUPOS.items():
    for tipo, crear in (('rel', caja_relacional), ('per', caja_persistente)):
        c = Lienzo()
        B = rejilla(c, filas, crear, gapx=60, gapy=80)
        presentes = set(B)
        for t in presentes:
            for cols, ref in fks(t):
                if ref in presentes and ref != t:
                    if tipo == 'rel':
                        asociacion(c, B[t], B[ref], 'N', '1')
                    else:
                        comp = t.startswith('detalle_') or t in ('rolpermiso', 'pieza_imagen')
                        asociacion(c, B[ref], B[t], '1', '*', tipo='comp' if comp else 'asoc')
        c.guardar(ruta(f'{tipo}_{p}'))

# ---------------------------------------------------------------------------
# 7. Despliegue, capas y componentes
# ---------------------------------------------------------------------------
c = Lienzo()
n1 = nodo(c, 0, 40, 230, 150, 'PC o celular')
c.texto(115, 110, 'Navegador web', 11.5, 'middle')
c.texto(115, 128, 'HTML · CSS · JavaScript', 10.5, 'middle', color=GRIS)
n2 = nodo(c, 340, 0, 300, 230, 'Vercel (región gru1)', '«servidor»')
componente(c, 365, 60, 'api/index.php', 'PHP 8.3 · vercel-php', w=250)
componente(c, 365, 130, 'Assets estáticos', 'Frontend/public · CSS · JS · imágenes', w=250)
n3 = nodo(c, 760, 0, 300, 230, 'Supabase (sa-east-1)', '«servidor»')
componente(c, 785, 60, 'Supavisor (pooler)', 'puerto 6543 · modo transacción', w=250)
componente(c, 785, 130, 'PostgreSQL 17', 'esquema mitsu · RLS · triggers', w=250, estereotipo='«base de datos»')
n4 = nodo(c, 400, 310, 220, 90, 'Gmail SMTP', '«servidor externo»')
n5 = nodo(c, 780, 310, 220, 90, 'Google OAuth', '«servidor externo»')
asociacion(c, n1, n2, etiqueta='HTTPS')
asociacion(c, n2, n3, etiqueta='TCP/TLS · PDO pdo_pgsql')
asociacion(c, n2, n4, etiqueta='SMTP/TLS (comprobantes)', tipo='dep')
asociacion(c, n2, n5, etiqueta='HTTPS (opcional)', tipo='dep')
c.guardar(ruta('despliegue'))

c = Lienzo()
capas = [('Capa de presentación', 'Frontend/views (layouts, parciales, vistas por paquete) · Frontend/public (CSS, JS)'),
         ('Capa de control', 'api/index.php (Router) · Bakend/app/controllers (uno por grupo de casos de uso)'),
         ('Capa de servicios y núcleo', 'Bakend/app/core: Auth, Session, Csrf, Validator, View, Mailer · services: Pasarela'),
         ('Capa de modelo (acceso a datos)', 'Bakend/app/models: un modelo por entidad, consultas con PDO y parámetros'),
         ('Capa de datos', 'PostgreSQL 17 · esquema mitsu: tablas, triggers, funciones PA1–PA12, RLS')]
for k, (t, d) in enumerate(capas):
    caja = paquete(c, 0, k * 110, 720, 80, t)
    c.texto(360, k * 110 + 58, d, 11.5, 'middle')
    if k:
        c.linea([(360, (k - 1) * 110 + 80), (360, k * 110)], dash='6,4', fin='abierta')
c.guardar(ruta('capas'))

c = Lienzo()
K = {}
K['nav'] = componente(c, 0, 0, 'Navegador', 'vistas HTML + app.js')
K['router'] = componente(c, 260, 0, 'Router', 'api/index.php')
K['nucleo'] = componente(c, 540, 0, 'Núcleo', 'Auth · Session · Csrf · View')
for k, (p, nombre) in enumerate(PAQUETES.items()):
    K[p] = componente(c, k * 250, 130, f'Controladores {p}', nombre.split(' ', 1)[1][:28], w=230)
    K['m' + p] = componente(c, k * 250, 250, f'Modelos {p}', '', w=230)
K['pasarela'] = componente(c, 1010, 130, 'Pasarela', 'tarjeta · QR · PayPal')
K['mailer'] = componente(c, 1010, 250, 'Mailer', 'SMTP')
K['bd'] = componente(c, 380, 370, 'PostgreSQL', 'esquema mitsu', estereotipo='«base de datos»')
asociacion(c, K['nav'], K['router'], tipo='dep', etiqueta='HTTP')
asociacion(c, K['router'], K['nucleo'], tipo='dep')
for p in PAQUETES:
    asociacion(c, K['router'], K[p], tipo='dep')
    asociacion(c, K[p], K['m' + p], tipo='dep')
    asociacion(c, K['m' + p], K['bd'], tipo='dep')
asociacion(c, K['P4'], K['pasarela'], tipo='dep')
asociacion(c, K['pasarela'], K['mailer'], tipo='dep')
c.guardar(ruta('componentes'))

for p, nombre in PAQUETES.items():
    lista = [cu for cu in CASOS if cu['paquete'] == p]
    frms, ctrls, ents = [], [], []
    for cu in lista:
        for k, t, n in cu['part']:
            dest = frms if t == 'frm' else ctrls if t == 'ctrl' else ents if t in ('ent', 'bd') else None
            if dest is not None and n not in dest:
                dest.append(n)
    usos = {}
    for cu in lista:
        t = {k: n for k, _, n in cu['part']}
        for k, tt, n in cu['part']:
            if tt in ('ent', 'bd'):
                usos.setdefault(n, []).append(ctrls.index(t['c']))
    frms.sort(key=lambda n: min(ctrls.index({k: m for k, _, m in cu['part']}['c']) for cu in lista if any(m == n for _, _, m in cu['part'])))
    ents.sort(key=lambda n: (n == 'Bitacora', sum(usos[n]) / len(usos[n])))
    c = Lienzo()
    cx = {}
    alto = max(len(frms), len(ctrls), len(ents)) * 70
    for col, (lst, est, x) in enumerate(((frms, '«vista»', 0), (ctrls, '«controlador»', 260), (ents, '«modelo»', 520))):
        y0 = (alto - len(lst) * 70) / 2
        for k, n in enumerate(lst):
            e = '«función SQL»' if n.startswith(('fn_', 'sp_')) else '«servicio»' if n == 'Pasarela' else est
            cx[n] = componente(c, x, y0 + k * 70, n, '', w=220, estereotipo=e)
    for cu in lista:
        t = {k: n for k, _, n in cu['part']}
        asociacion(c, cx[t['f']], cx[t['c']], tipo='dep')
        for k, tt, n in cu['part']:
            if tt in ('ent', 'bd') and n in cx:
                asociacion(c, cx[t['c']], cx[n], tipo='dep')
    c.guardar(ruta(f'componentes_{p}'))

# ---------------------------------------------------------------------------
# 8. Modelo de negocio: diagramas de actividad con carriles
# ---------------------------------------------------------------------------
def actividad(carriles, nodos, aristas, nombre, titulo=None, ancho_c=250):
    """Diagrama de actividad con carriles.
    nodos: (clave, carril, fila, tipo, texto); tipo: ini, fin, acc, dec o barra (carril = (desde, hasta)).
    aristas: (origen, destino, guarda[, 'izq' | 'der'])."""
    c = Lienzo()
    alto_f = 78
    sup = 34 if titulo else 0                       # franja del título
    xs = [ancho_c * k + ancho_c / 2 for k in range(len(carriles))]
    alto = (max(n[2] for n in nodos) + 1) * alto_f + 50 + sup
    if titulo:
        c.rect(0, 0, ancho_c * len(carriles), sup, fill='white')
        c.texto(ancho_c * len(carriles) / 2, 22, titulo, 13, 'middle', negrita=True)
    for k, t in enumerate(carriles):
        c.rect(k * ancho_c, sup, ancho_c, alto - sup, fill='white' if k % 2 else '#fdfaf5')
        c.rect(k * ancho_c, sup, ancho_c, 34, fill=RELLENO_2)
        c.texto(xs[k], sup + 22, t, 12.5, 'middle', negrita=True)
    geo = {}
    for clave, carril, fila, tipo, texto in nodos:
        y = sup + 70 + fila * alto_f
        if tipo == 'barra':
            x0, x1 = xs[carril[0]] - 80, xs[carril[1]] + 80
            geo[clave] = (x0, y - 4, x1 - x0, 8)
            continue
        x = xs[carril]
        if tipo == 'acc':
            partes = uml._partir(texto, 180, 11.5)
            h = 18 + 14 * len(partes)
            geo[clave] = (x - 100, y - h / 2, 200, h)
        elif tipo == 'dec':
            geo[clave] = (x - 16, y - 16, 32, 32)
        else:
            geo[clave] = (x - 11, y - 11, 22, 22)
    tipos = {n[0]: n[3] for n in nodos}
    for a, b, g, *forma in aristas:
        xa, ya, wa, ha = geo[a]
        xb, yb, wb, hb = geo[b]
        ca, cb = (xa + wa / 2, ya + ha / 2), (xb + wb / 2, yb + hb / 2)
        if tipos[a] == 'barra':                       # sale de la barra hacia abajo
            pts = [(cb[0], ya + ha), (cb[0], yb)]
        elif tipos[b] == 'barra':                     # llega a la barra desde arriba
            pts = [(ca[0], ya + ha), (ca[0], yb)]
        elif forma == ['izq']:
            pts = [(xa, ca[1]), (xa - 22, ca[1]), (xb - 22, cb[1]), (xb, cb[1])]
        elif forma == ['der']:
            xr = cb[0] + 112
            pts = [(xa + wa, ca[1]), (xr, ca[1]), (xr, cb[1]), (xb + wb, cb[1])]
        elif abs(ca[0] - cb[0]) < 1:
            if cb[1] > ca[1]:
                pts = [(ca[0], ya + ha), (cb[0], yb)]
            else:
                pts = [(xa + wa, ca[1]), (xa + wa + 18, ca[1]), (xa + wa + 18, cb[1]), (xb + wb, cb[1])]
        elif abs(ca[1] - cb[1]) < 1:
            pts = [(xa + wa if cb[0] > ca[0] else xa, ca[1]), (xb if cb[0] > ca[0] else xb + wb, cb[1])]
        else:
            if cb[1] < ca[1]:
                pts = [(xa, ca[1]), (cb[0], ca[1]), (cb[0], yb + hb)]
            else:
                pts = [(xa + wa if cb[0] > ca[0] else xa, ca[1]), (cb[0], ca[1]), (cb[0], yb)]
        c.linea(pts, fin='llena')
        if g and forma == ['der']:
            c.texto(pts[0][0] + 4, pts[0][1] - 6, g, 10.5, cursiva=True, color=ACENTO, fondo='white')
        elif g and abs(pts[0][0] - pts[1][0]) < 1:   # tramo vertical: la guarda va al costado
            c.texto(pts[0][0] + 6, (pts[0][1] + pts[1][1]) / 2 + 4, g, 10.5, cursiva=True, color=ACENTO, fondo='white')
        elif g:
            c.texto((pts[0][0] + pts[1][0]) / 2, pts[0][1] - 6 if abs(pts[0][1] - pts[1][1]) < 1 else (pts[0][1] + pts[1][1]) / 2,
                    g, 10.5, 'middle', cursiva=True, color=ACENTO, fondo='white')
    for clave, carril, fila, tipo, texto in nodos:
        x, y, w, h = geo[clave]
        if tipo == 'barra':
            c.rect(x, y, w, h, fill=TINTA, stroke=TINTA)
        elif tipo == 'acc':
            c.rect(x, y, w, h, rx=12, fill=RELLENO)
            partes = uml._partir(texto, 180, 11.5)
            for j, t in enumerate(partes):
                c.texto(x + w / 2, y + 22 + 14 * j, t, 11.5, 'middle')
        elif tipo == 'dec':
            c.poligono([(x + 16, y), (x + 32, y + 16), (x + 16, y + 32), (x, y + 16)], fill=RELLENO_2)
            if texto:
                c.texto(x + 30, y - 8, texto, 10.5, cursiva=True, color=GRIS, fondo='white')
        elif tipo == 'ini':
            c.elipse(x + 11, y + 11, 11, 11, fill=TINTA)
        else:
            c.elipse(x + 11, y + 11, 11, 11, fill='white')
            c.elipse(x + 11, y + 11, 6, 6, fill=TINTA)
    c.guardar(ruta(nombre))


# Proceso actual del taller (antes del sistema)
actividad(
    ['Alumno / Cliente', 'Recepción (administrativa)', 'Maestra', 'Gerencia (administradora)'],
    [('i', 0, 0, 'ini', ''),
     ('a1', 0, 1, 'acc', 'Consulta horarios por WhatsApp o en el local'),
     ('a2', 1, 1, 'acc', 'Revisa el cuaderno de horarios y cupos'),
     ('d1', 1, 2, 'dec', ''),
     ('a3', 1, 3, 'acc', 'Anota la reserva en el cuaderno'),
     ('a4', 0, 3, 'acc', 'Busca otro horario'),
     ('a5', 2, 4, 'acc', 'Dicta la clase y anota la asistencia en papel'),
     ('a6', 2, 5, 'acc', 'Avisa a recepción el material usado'),
     ('i2', 0, 5, 'ini', ''),
     ('a7', 0, 6, 'acc', 'Elige una pieza en el local o por redes'),
     ('a8', 1, 6, 'acc', 'Cobra, anota la venta y entrega la pieza'),
     ('a9', 3, 7, 'acc', 'Cuenta el dinero y revisa stock al cierre'),
     ('d2', 3, 8, 'dec', ''),
     ('a10', 3, 9, 'acc', 'Compra material al proveedor'),
     ('f', 3, 10, 'fin', '')],
    [('i', 'a1', ''), ('a1', 'a2', ''), ('a2', 'd1', ''), ('d1', 'a3', '[hay cupo]'), ('d1', 'a4', '[sin cupo]'), ('a4', 'a1', '', 'izq'), ('i2', 'a7', ''),
     ('a3', 'a5', ''), ('a5', 'a6', ''), ('a6', 'a8', ''), ('a7', 'a8', ''), ('a8', 'a9', ''), ('a9', 'd2', ''),
     ('d2', 'a10', '[stock bajo]'), ('d2', 'f', '[stock suficiente]', 'der'), ('a10', 'f', '')],
    'actividad_negocio')

# 5.1.1 Reserva de clases y registro de asistencia
actividad(
    ['Alumno', 'Sistema Mitsü', 'Maestra'],
    [('i', 0, 0, 'ini', ''),
     ('a1', 0, 1, 'acc', 'Ingresa a su portal y abre «Reservar clase»'),
     ('s1', 1, 2, 'acc', 'Muestra las clases de la semana con sus lugares libres'),
     ('a2', 0, 3, 'acc', 'Elige una clase y presiona «Reservar mi lugar»'),
     ('d1', 1, 4, 'dec', '¿Hay cupo?'),
     ('a3', 0, 4, 'acc', 'Ve el aviso y elige otra clase'),
     ('s2', 1, 5, 'acc', 'Registra la reserva (fn_reservar_clase) y descuenta un lugar'),
     ('b1', (0, 2), 6, 'barra', ''),
     ('a4', 0, 7, 'acc', 'Ve la reserva en «Mis clases» y asiste'),
     ('m1', 2, 7, 'acc', 'Abre la lista de alumnos de su clase del día'),
     ('m2', 2, 8, 'acc', 'Marca «Presente» o «Falta» y guarda'),
     ('s3', 1, 9, 'acc', 'Guarda la asistencia y la registra en la bitácora'),
     ('b2', (0, 2), 10, 'barra', ''),
     ('f', 1, 11, 'fin', '')],
    [('i', 'a1', ''), ('a1', 's1', ''), ('s1', 'a2', ''), ('a2', 'd1', ''), ('d1', 'a3', '[no]'), ('a3', 'a2', '', 'izq'),
     ('d1', 's2', '[sí]'), ('s2', 'b1', ''), ('b1', 'a4', ''), ('b1', 'm1', ''), ('m1', 'm2', ''), ('m2', 's3', ''),
     ('a4', 'b2', ''), ('s3', 'b2', ''), ('b2', 'f', '')],
    'actividad_reservas', 'Diagrama de actividades: reserva de clases y registro de asistencia')

# 5.1.2 Consumo de materiales y compra a proveedores
actividad(
    ['Maestra', 'Administrativo', 'Sistema Mitsü', 'Proveedor'],
    [('i', 0, 0, 'ini', ''),
     ('m1', 0, 1, 'acc', 'Usa arcilla, esmaltes o minerales en la clase o la producción'),
     ('r1', 1, 2, 'acc', 'Registra el consumo o la merma en el kardex del material'),
     ('s1', 2, 3, 'acc', 'Descuenta el stock y guarda el movimiento'),
     ('d1', 2, 4, 'dec', '¿Stock bajo?'),
     ('s2', 2, 5, 'acc', 'Muestra la alerta de stock bajo en «Materiales»'),
     ('r2', 1, 6, 'acc', 'Pide el material al proveedor'),
     ('p1', 3, 7, 'acc', 'Envía el material con su factura'),
     ('r3', 1, 8, 'acc', 'Registra la compra con cantidades y costos'),
     ('s3', 2, 9, 'acc', 'Registra la compra (fn_registrar_compra): sube el stock y el costo promedio'),
     ('f', 2, 10, 'fin', '')],
    [('i', 'm1', ''), ('m1', 'r1', ''), ('r1', 's1', ''), ('s1', 'd1', ''), ('d1', 's2', '[sí]'), ('d1', 'f', '[no]', 'der'),
     ('s2', 'r2', ''), ('r2', 'p1', ''), ('p1', 'r3', ''), ('r3', 's3', ''), ('s3', 'f', '')],
    'actividad_inventario', 'Diagrama de actividades: consumo de materiales y compra a proveedores')

# 5.1.3 Venta en caja y cierre de caja
actividad(
    ['Cliente', 'Administrativo (caja)', 'Sistema Mitsü', 'Gerencia'],
    [('i', 1, 0, 'ini', ''),
     ('r1', 1, 1, 'acc', 'Abre la caja con el monto inicial'),
     ('s1', 2, 2, 'acc', 'Registra la caja abierta'),
     ('c1', 0, 3, 'acc', 'Elige las piezas que quiere comprar'),
     ('r2', 1, 4, 'acc', 'Agrega las piezas, el cliente y el método de pago'),
     ('d1', 2, 5, 'dec', '¿Venta válida?'),
     ('r3', 1, 5, 'acc', 'Ve el aviso y corrige la venta'),
     ('s2', 2, 6, 'acc', 'Registra la venta (fn_registrar_venta), descuenta el stock y emite la nota NV'),
     ('c2', 0, 7, 'acc', 'Paga y recibe su nota de venta'),
     ('r4', 1, 8, 'acc', 'Al final del día cuenta el efectivo y cierra la caja'),
     ('s3', 2, 9, 'acc', 'Calcula el efectivo esperado y la diferencia (sp_cerrar_caja)'),
     ('g1', 3, 10, 'acc', 'Revisa el arqueo, las ventas y la bitácora'),
     ('f', 3, 11, 'fin', '')],
    [('i', 'r1', ''), ('r1', 's1', ''), ('s1', 'c1', ''), ('c1', 'r2', ''), ('r2', 'd1', ''), ('d1', 'r3', '[no]'), ('r3', 'r2', '', 'izq'),
     ('d1', 's2', '[sí]'), ('s2', 'c2', ''), ('c2', 'r4', ''), ('r4', 's3', ''), ('s3', 'g1', ''), ('g1', 'f', '')],
    'actividad_ventas', 'Diagrama de actividades: venta en caja y cierre de caja')

# 5.1.4 Compra en la tienda en línea y pago
actividad(
    ['Cliente', 'Sistema Mitsü', 'Administrativo'],
    [('i', 0, 0, 'ini', ''),
     ('c1', 0, 1, 'acc', 'Ve las piezas en la tienda y las agrega al carrito'),
     ('c2', 0, 2, 'acc', 'Ingresa con su cuenta y confirma el pedido'),
     ('s1', 1, 3, 'acc', 'Crea el pedido (fn_crear_pedido) y reserva el stock 30 minutos'),
     ('c3', 0, 4, 'acc', 'Paga con QR, tarjeta o PayPal'),
     ('d1', 1, 5, 'dec', '¿Resultado del pago?'),
     ('c4', 0, 5, 'acc', 'Ve el rechazo e intenta de nuevo'),
     ('a1', 2, 5, 'acc', 'Verifica el depósito del QR y lo confirma'),
     ('s2', 1, 6, 'acc', 'Marca el pedido como pagado y guarda el comprobante'),
     ('a2', 2, 7, 'acc', 'Prepara y entrega el pedido'),
     ('s3', 1, 8, 'acc', 'Marca el pedido como entregado'),
     ('f', 1, 9, 'fin', '')],
    [('i', 'c1', ''), ('c1', 'c2', ''), ('c2', 's1', ''), ('s1', 'c3', ''), ('c3', 'd1', ''), ('d1', 'c4', '[rechazado]'), ('c4', 'c3', '', 'izq'),
     ('d1', 'a1', '[QR: ya pagué]'), ('d1', 's2', '[aprobado]'), ('a1', 's2', ''), ('s2', 'a2', ''), ('a2', 's3', ''), ('s3', 'f', '')],
    'actividad_tienda', 'Diagrama de actividades: compra en la tienda en línea y pago')

# ---------------------------------------------------------------------------
# Datos de los casos de uso para el informe
# ---------------------------------------------------------------------------
salida = [{k: v for k, v in cu.items() if k not in ('part', 'pasos')} | {
    'participantes': [{'tipo': t, 'nombre': n} for _, t, n in cu['part']]} for cu in CASOS]
json.dump({'casos': salida, 'actores': ACTORES, 'paquetes': PAQUETES}, open(os.path.join(DATOS, 'casos.json'), 'w', encoding='utf8'),
          ensure_ascii=False, indent=1)
print(len(os.listdir(SVG)), 'diagramas SVG')
