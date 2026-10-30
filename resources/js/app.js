import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

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
