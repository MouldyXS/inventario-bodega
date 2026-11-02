import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

// Tema: 'automatico' (sigue al sistema, también si cambia con la página abierta),
// 'claro' u 'oscuro'. La preferencia se guarda en localStorage; si no está
// disponible, el tema queda en automático.
const sistemaOscuro = window.matchMedia('(prefers-color-scheme: dark)');
const ETIQUETAS_TEMA = { automatico: 'automático', claro: 'claro', oscuro: 'oscuro' };

const leerPreferencia = () => {
    try {
        const guardada = localStorage.getItem('tema');

        return guardada in ETIQUETAS_TEMA ? guardada : 'automatico';
    } catch {
        return 'automatico';
    }
};

const aplicarTema = (preferencia) => {
    const oscuro = preferencia === 'oscuro' || (preferencia === 'automatico' && sistemaOscuro.matches);

    document.documentElement.setAttribute('data-bs-theme', oscuro ? 'dark' : 'light');
    document.querySelectorAll('[data-tema-alternar]').forEach((boton) => {
        (boton.querySelector('[data-tema-etiqueta]') ?? boton).textContent = `Tema: ${ETIQUETAS_TEMA[preferencia]}`;
    });
};

aplicarTema(leerPreferencia());

// En automático, un cambio del sistema se aplica al instante; en manual se ignora.
sistemaOscuro.addEventListener('change', () => aplicarTema(leerPreferencia()));

document.addEventListener('click', (evento) => {
    if (!evento.target.closest('[data-tema-alternar]')) {
        return;
    }

    const orden = ['automatico', 'claro', 'oscuro'];
    const siguiente = orden[(orden.indexOf(leerPreferencia()) + 1) % orden.length];

    try {
        if (siguiente === 'automatico') {
            localStorage.removeItem('tema');
        } else {
            localStorage.setItem('tema', siguiente);
        }
    } catch {
        // Sin almacenamiento: el cambio vale solo para esta página.
    }

    aplicarTema(siguiente);
});

// Barra lateral (PC): compacta o ampliada. El estado inicial lo fija el <head>
// del layout; aquí solo se alterna y se guarda (sin almacenamiento, vale para esta página).
document.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-menu-alternar]');

    if (!boton) {
        return;
    }

    const compacto = document.documentElement.getAttribute('data-menu') !== 'compacto';

    if (compacto) {
        document.documentElement.setAttribute('data-menu', 'compacto');
    } else {
        document.documentElement.removeAttribute('data-menu');
    }

    boton.setAttribute('aria-expanded', String(!compacto));

    try {
        if (compacto) {
            localStorage.setItem('menu', 'compacto');
        } else {
            localStorage.removeItem('menu');
        }
    } catch {
        // Sin almacenamiento: el cambio vale solo para esta página.
    }
});

// Cantidad con − / +: cambia el valor del mismo <input type="number"> respetando su mínimo
// y avisa con un evento "input" para que lo oigan la vista previa y las validaciones.
document.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-stepper]');

    if (!boton) {
        return;
    }

    const campo = boton.parentElement.querySelector('input[type="number"]');
    const minimo = campo.min === '' ? Number.NEGATIVE_INFINITY : Number(campo.min);
    const maximo = campo.max === '' ? Number.POSITIVE_INFINITY : Number(campo.max);
    const paso = Number(boton.dataset.stepper);
    // Con el campo vacío, ambos botones llevan al mínimo (o a 0/1 si no hay mínimo).
    const inicial = campo.value === '' ? (Number.isFinite(minimo) ? minimo : Math.max(0, paso)) : Number(campo.value) + paso;
    const nuevo = Math.min(maximo, Math.max(minimo, inicial));

    campo.value = String(nuevo);
    campo.dispatchEvent(new Event('input', { bubbles: true })); // sin focus(): no abre el teclado de la tablet
});

// Contraseña: botón «Mostrar / Ocultar» (el mismo <input>, solo cambia su tipo).
document.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-mostrar-clave]');

    if (!boton) {
        return;
    }

    const campo = document.getElementById(boton.getAttribute('aria-controls'));
    const mostrar = campo.type === 'password';

    campo.type = mostrar ? 'text' : 'password';
    boton.textContent = mostrar ? 'Ocultar' : 'Mostrar';
    boton.setAttribute('aria-pressed', String(mostrar));
});

// Lector de códigos de barras tipo teclado (Requerimientos §7): escribe el código
// y pulsa Enter. En los formularios marcados con data-enter-avanza, Enter en un
// campo pasa al siguiente en lugar de enviar el formulario a medio llenar; el
// formulario solo se envía con su botón.
document.addEventListener('keydown', (evento) => {
    const campo = evento.target;

    if (evento.key !== 'Enter' || !campo.form?.hasAttribute('data-enter-avanza')) {
        return;
    }

    if (campo.tagName === 'TEXTAREA' || campo.type === 'submit' || campo.tagName === 'BUTTON') {
        return;
    }

    evento.preventDefault();

    const campos = [...campo.form.elements].filter((elemento) =>
        !elemento.disabled && elemento.type !== 'hidden' && elemento.offsetParent !== null
        && ['INPUT', 'SELECT', 'TEXTAREA'].includes(elemento.tagName));
    const siguiente = campos[campos.indexOf(campo) + 1];

    siguiente?.focus();
});

// Selector de productos de Recibir y Retirar: la lista completa ya viene impresa por el servidor y aquí
// solo se filtra al escribir (todas las palabras, en cualquier parte del nombre, la marca o la categoría;
// sin tildes ni mayúsculas, como la colación del servidor). Sin este script queda el formulario GET.
const normalizar = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

document.querySelectorAll('[data-selector-producto]').forEach((selector) => {
    const campo = selector.querySelector('#q');
    const lista = selector.querySelector('[data-lista]');
    const contador = selector.querySelector('[data-contador]');
    const anuncio = selector.querySelector('[data-anuncio]');
    const vacio = selector.querySelector('[data-vacio]');
    const vacioTexto = selector.querySelector('[data-vacio-texto]');
    const interruptor = selector.querySelector('[data-solo-existencias]');
    const solo = interruptor?.querySelector('input');
    const items = [...lista.querySelectorAll('[data-producto]')];
    let temporizador;

    interruptor?.removeAttribute('hidden');

    // Con el filtro en vivo el botón sobra (Enter y la tecla de búsqueda del teclado táctil siguen enviando el formulario).
    selector.querySelector('[data-buscar]')?.setAttribute('hidden', '');
    campo.closest('.col-md-9')?.classList.replace('col-md-9', 'col-md-12');

    const visibles = () => items.filter((item) => !item.hidden);
    const enlaces = () => visibles().map((item) => item.querySelector('a'));
    // Un código de barras (solo cifras y guiones) se envía al servidor, que lo resuelve como siempre.
    const parecePorCodigo = (texto) => /^[0-9-]{6,}$/.test(texto);

    const filtrar = (anunciar = false) => {
        const consulta = normalizar(campo.value);
        const palabras = consulta.split(/\s+/).filter(Boolean);

        items.forEach((item) => {
            const coincide = palabras.every((palabra) => item.dataset.busqueda.includes(palabra))
                && !(solo?.checked && item.hasAttribute('data-agotado'));

            item.hidden = !coincide;
        });

        // Primero los que empiezan por lo escrito, luego los que lo tienen en una palabra, luego el resto.
        const rango = (item) => {
            if (consulta === '') {
                return 0;
            }

            return item.dataset.nombre.startsWith(consulta) ? 0 : (` ${item.dataset.nombre}`.includes(` ${consulta}`) ? 1 : 2);
        };
        const orden = new Map(items.map((item, indice) => [item, indice]));

        lista.append(...[...items].sort((a, b) => rango(a) - rango(b) || orden.get(a) - orden.get(b)));

        const total = visibles().length;
        const texto = `${total} ${total === 1 ? 'producto' : 'productos'}`;

        contador.textContent = texto;
        vacio.hidden = total > 0;
        vacioTexto.textContent = total > 0 ? '' : (campo.value.trim() !== ''
            ? `No hay productos activos que coincidan con «${campo.value.trim()}».`
            : 'No hay productos con existencias.');

        if (anunciar) {
            clearTimeout(temporizador);
            temporizador = setTimeout(() => {
                anuncio.textContent = total > 0 ? texto : 'Sin coincidencias';
            }, 400);
        }
    };

    campo.addEventListener('input', () => filtrar(true));
    solo?.addEventListener('change', () => filtrar(true));

    campo.addEventListener('keydown', (evento) => {
        const texto = campo.value.trim();

        if (evento.key === 'ArrowDown') {
            const primero = enlaces()[0];

            if (primero) {
                evento.preventDefault();
                primero.focus();
            }
        } else if (evento.key === 'Escape' && campo.value !== '') {
            campo.value = '';
            filtrar(true);
        } else if (evento.key === 'Enter') {
            const coincidencias = enlaces();

            if (texto === '' || (coincidencias.length > 0 && !parecePorCodigo(texto))) {
                evento.preventDefault();

                if (coincidencias.length === 1) {
                    window.location.href = coincidencias[0].href;
                } else if (coincidencias.length > 1) {
                    coincidencias[0].focus();
                }
            }
        }
    });

    lista.addEventListener('keydown', (evento) => {
        const actuales = enlaces();
        const indice = actuales.indexOf(document.activeElement);

        if (indice === -1 || !['ArrowDown', 'ArrowUp', 'Escape'].includes(evento.key)) {
            return;
        }

        evento.preventDefault();

        if (evento.key === 'ArrowDown') {
            actuales[Math.min(indice + 1, actuales.length - 1)].focus();
        } else if (evento.key === 'ArrowUp' && indice > 0) {
            actuales[indice - 1].focus();
        } else {
            campo.focus();
        }
    });

    selector.querySelector('[data-limpiar]')?.addEventListener('click', (evento) => {
        evento.preventDefault();
        campo.value = '';

        if (solo) {
            solo.checked = false;
        }

        filtrar(true);
        campo.focus();
    });

    filtrar();
});
