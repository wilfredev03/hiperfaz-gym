// Cantidades de una línea de venta con soporte de peso/volumen. La cantidad
// SIEMPRE vive en kg (es lo que recibe el backend y con lo que se calcula el
// subtotal); `unidadEntrada` solo decide cómo se lee y se teclea el número:
// cada pantalla decide con cuál unidad arranca (el mostrador en gramos, porque
// la pesa los muestra; mayoristas en kilos).

export type UnidadEntrada = 'g' | 'kg';

export interface LineaCantidad {
    cantidad: string;
    fraccionable: boolean;
    unidadEntrada: UnidadEntrada;
}

// Las unidades de peso/volumen aceptan cantidades decimales (0.850 kg); el
// resto se vende por pieza entera. No hay una bandera en unidades_medida
// para esto todavía, así que se infiere por la abreviatura.
const UNIDADES_FRACCIONABLES = new Set(['kg', 'g', 'l', 'ml']);

export function esFraccionable(abreviatura: string): boolean {
    return UNIDADES_FRACCIONABLES.has(abreviatura.trim().toLowerCase());
}

export function minimo(linea: LineaCantidad): number {
    return linea.fraccionable ? 0.001 : 1;
}

function redondeada(linea: LineaCantidad, valor: number): string {
    return linea.fraccionable
        ? String(Math.round(valor * 1000) / 1000)
        : String(Math.round(valor));
}

// paso "a mano" para los botones +/-; escribir directo en el input sigue
// permitiendo cantidades exactas como 0.850 kg. En gramos el paso es más
// fino (10 g) porque 0.1 kg de un salto sería demasiado para ajustar un
// corte que ya se pesó.
export function paso(linea: LineaCantidad): number {
    if (!linea.fraccionable) return 1;
    return linea.unidadEntrada === 'g' ? 0.01 : 0.1;
}

export function pasoInput(linea: LineaCantidad): string {
    if (!linea.fraccionable) return '1';
    return linea.unidadEntrada === 'g' ? '1' : '0.001';
}

export function minimoInput(linea: LineaCantidad): string {
    return pasoInput(linea);
}

// lo que la persona ve/teclea según el toggle g/kg
export function cantidadMostrada(linea: LineaCantidad): string {
    if (linea.fraccionable && linea.unidadEntrada === 'g') {
        return String(Math.round((Number(linea.cantidad) || 0) * 1000));
    }
    return linea.cantidad;
}

// del número tecleado a la cantidad guardada (kg)
export function cantidadDesdeInput(
    linea: LineaCantidad,
    valorMostrado: string,
): string {
    return linea.fraccionable && linea.unidadEntrada === 'g'
        ? String((Number(valorMostrado) || 0) / 1000)
        : valorMostrado;
}

export function normalizada(linea: LineaCantidad): string {
    const valor = Number(linea.cantidad);
    if (!Number.isFinite(valor) || valor <= 0) {
        return linea.fraccionable ? '0.001' : '1';
    }
    return redondeada(linea, valor);
}

export function incrementada(linea: LineaCantidad): string {
    return redondeada(linea, (Number(linea.cantidad) || 0) + paso(linea));
}

// `null` = bajó del mínimo: la línea se elimina, igual que en cualquier carrito
export function decrementada(linea: LineaCantidad): string | null {
    const nueva = (Number(linea.cantidad) || 0) - paso(linea);
    return nueva < minimo(linea) ? null : redondeada(linea, nueva);
}
