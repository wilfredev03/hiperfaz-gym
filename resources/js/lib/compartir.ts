import html2canvas from 'html2canvas-pro';

// Convierte un bloque de la pantalla en una imagen PNG lista para enviar. Se
// pide el doble de resolución para que se lea nítida en el celular del cliente.
//
// Antes se usaba html-to-image (dibuja vía <svg><foreignObject>, clonando
// cada nodo con TODO su estilo computado — cientos de propiedades, muchas
// irrelevantes — incrustado inline). Con Tailwind v4, ese estilo computado
// trae `oklch()`/`oklab()`/`color(srgb ...)` para valores por defecto que ni
// siquiera se usan visiblemente (ej. el color de `outline` o `border` que
// Tailwind nunca sobreescribe), y ese foreignObject deja de pintar por
// completo — ni abriendo el SVG solo, sin pasar por canvas — resultando en
// una imagen en blanco. html2canvas-pro repinta directo con Canvas 2D (sin
// SVG) y sabe convertir esos colores modernos, así que no choca con esto.
export async function generarImagen(nodo: HTMLElement): Promise<Blob> {
    // con la tipografía aún cargando, el texto saldría con otra fuente
    await document.fonts?.ready;

    const canvas = await html2canvas(nodo, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true,
    });

    const blob = await new Promise<Blob | null>((resolve) =>
        canvas.toBlob(resolve, 'image/png'),
    );

    if (!blob) {
        throw new Error('No se pudo generar la imagen.');
    }

    return blob;
}

export function nombreDeArchivo(texto: string): string {
    return (
        texto
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'documento'
    );
}

// La hoja nativa de compartir (WhatsApp, correo, Telegram, AirDrop...) solo
// existe en navegadores que aceptan archivos en Web Share y bajo HTTPS (o
// localhost). Donde no está, quedan descargar y los enlaces directos.
export function puedeCompartirArchivo(archivo: File): boolean {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.canShare === 'function' &&
        navigator.canShare({ files: [archivo] })
    );
}

// devuelve false si la persona cerró la hoja sin elegir una app
export async function compartirArchivo(
    archivo: File,
    titulo: string,
    texto: string,
): Promise<boolean> {
    try {
        await navigator.share({ files: [archivo], title: titulo, text: texto });
        return true;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return false;
        }
        throw error;
    }
}

export function descargarArchivo(archivo: File): void {
    const url = URL.createObjectURL(archivo);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = archivo.name;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// wa.me pide el número en formato internacional sin signos: 0414-1234567 →
// 584141234567. Sin teléfono (o con uno que no se reconoce) se abre WhatsApp
// para elegir el contacto a mano.
export function enlaceWhatsapp(telefono: string | null, texto: string): string {
    const digitos = (telefono ?? '').replace(/\D/g, '');
    let internacional = '';

    if (digitos.startsWith('58') && digitos.length >= 12) {
        internacional = digitos;
    } else if (digitos.startsWith('0') && digitos.length === 11) {
        internacional = `58${digitos.slice(1)}`;
    }

    const base = internacional
        ? `https://wa.me/${internacional}`
        : 'https://wa.me/';

    return `${base}?text=${encodeURIComponent(texto)}`;
}

export function enlaceCorreo(asunto: string, cuerpo: string): string {
    return `mailto:?subject=${encodeURIComponent(asunto)}&body=${encodeURIComponent(cuerpo)}`;
}
