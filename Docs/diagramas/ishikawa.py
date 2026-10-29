"""
Diagrama de Ishikawa (causa-efecto) del Taller Mitsü.
Las causas salen de la lista final de problemas (entrevista a la gerente).

    python Docs/diagramas/ishikawa.py
"""

import os
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

EFECTO = 'Gestión manual y\ndesintegrada de clases,\ninventario y ventas\ndel Taller Mitsü'
ARRIBA = [
    ('Métodos y procesos', ['Horarios y reservas en hojas de Excel', 'Ventas anotadas a mano, sin nota numerada',
                            'Sin cierre de caja ni arqueo diario']),
    ('Personas', ['Personal reducido (no se puede contratar más)', 'Maestras asignadas sin registro central',
                  'Recepción responde reservas una por una']),
    ('Tecnología', ['Excel y software de finanzas no integrados', 'Sin página para ver piezas o clases',
                    'Sin respaldos ni control de acceso']),
]
ABAJO = [
    ('Materiales e inventario', ['Stock de arcilla y minerales sin registro', 'Compras urgentes por falta de material',
                                 'Solo dos proveedores (Cbba. y La Paz)']),
    ('Medición y control', ['Sin historial de asistencia de alumnos', 'Sin reportes de ventas ni indicadores',
                            'Precios y costos sin historial']),
    ('Clientes y entorno', ['Alumnos no ven cupos en tiempo real', 'Clientes no pueden comprar ni pagar en línea',
                            'Competencia de otros talleres de la ciudad']),
]

fig, ax = plt.subplots(figsize=(16, 8.6))
ax.set_xlim(0, 16)
ax.set_ylim(0, 8.6)
ax.axis('off')
plt.rcParams['font.family'] = 'Arial'
TINTA, ARCILLA, CREMA = '#2b2b2b', '#9c5b3a', '#f1e4d3'
Y0 = 4.3
ax.annotate('', xy=(12.2, Y0), xytext=(0.4, Y0), arrowprops=dict(arrowstyle='-|>', lw=2.6, color=TINTA, mutation_scale=22))
ax.text(14.05, Y0, EFECTO, ha='center', va='center', fontsize=12.5, fontweight='bold', color='white',
        bbox=dict(boxstyle='round,pad=0.6', fc=ARCILLA, ec=ARCILLA))

for fila, (grupos, signo) in enumerate(((ARRIBA, 1), (ABAJO, -1))):
    for k, (categoria, causas) in enumerate(grupos):
        xb = 2.2 + k * 3.6           # donde la espina toca el eje
        xt = xb - 1.9                # extremo de la espina
        yt = Y0 + signo * 3.4
        ax.plot([xt, xb], [yt, Y0], color=TINTA, lw=1.8)
        ax.text(xt, yt + signo * 0.32, categoria, ha='center', va='center', fontsize=12, fontweight='bold',
                bbox=dict(boxstyle='round,pad=0.35', fc=CREMA, ec=ARCILLA))
        for j, causa in enumerate(causas):
            t = 0.25 + j * 0.27
            x, y = xt + (xb - xt) * t, yt + (Y0 - yt) * t
            ax.plot([x, x + 1.55], [y, y], color=ARCILLA, lw=1.1)
            ax.text(x + 0.08, y + 0.07, causa, fontsize=8.6, va='bottom', color=TINTA, wrap=True)

ax.text(0.4, 0.15, 'Fuente: elaboración propia a partir de la entrevista a la gerente y de la lista final de problemas.',
        fontsize=9, style='italic', color='#666')
destino = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'png', 'ishikawa.png')
plt.savefig(destino, dpi=200, bbox_inches='tight', facecolor='white')
print('ishikawa.png')
