import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('change', this.go);
    }

    disconnect() {
        this.element.removeEventListener('change', this.go);
    }

    go = () => {
        const target = this.element.value;
        if (!target) {
            return;
        }

        if (window.Turbo) {
            window.Turbo.visit(target);
            return;
        }

        window.location.assign(target);
    };
}
